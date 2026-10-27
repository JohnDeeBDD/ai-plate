<?php
/**
 * Example signed deployment webhook endpoint.
 *
 * This example is infrastructure code, not theme code. Install a reviewed copy
 * outside the WordPress/theme directory and configure the web server so that
 * only this file is public. Keep logs, replay records, secrets, and the deploy
 * program outside the document root.
 *
 * Required environment variables:
 * - DEPLOY_WEBHOOK_SECRET: high-entropy HMAC secret.
 * - DEPLOY_REPOSITORY: expected "owner/repository" value.
 *
 * Optional environment variables:
 * - DEPLOY_REF: expected ref (default: refs/heads/main).
 * - DEPLOY_PROGRAM: fixed executable path (default shown below).
 * - DEPLOY_REPLAY_DIR: non-public writable directory for delivery IDs.
 * - DEPLOY_LOG_FILE: non-public writable audit log.
 */

declare(strict_types=1);

const MAX_REQUEST_BYTES = 4096;
const MAX_CLOCK_SKEW_SECONDS = 300;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/**
 * Send a generic JSON response and terminate.
 */
function respond(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(
        array('status' => $status < 400 ? 'accepted' : 'rejected', 'message' => $message),
        JSON_UNESCAPED_SLASHES
    );
    exit;
}

/**
 * Append a single sanitized event to the audit log.
 */
function audit(string $logFile, string $result, string $deliveryId = '', string $sha = ''): void
{
    $record = array(
        'time'        => gmdate('c'),
        'result'      => preg_replace('/[^a-z0-9_.-]/i', '_', $result),
        'delivery_id' => preg_replace('/[^a-zA-Z0-9_.-]/', '_', $deliveryId),
        'sha'         => preg_match('/\A[0-9a-f]{40}\z/i', $sha) ? strtolower($sha) : '',
    );

    @file_put_contents(
        $logFile,
        json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, 'Request rejected.');
}

// Require HTTPS at the application boundary. Configure trusted-proxy handling
// in the web server; do not trust arbitrary X-Forwarded-Proto headers here.
if (($_SERVER['HTTPS'] ?? '') !== 'on') {
    respond(400, 'Request rejected.');
}

$secret     = getenv('DEPLOY_WEBHOOK_SECRET') ?: '';
$repository = getenv('DEPLOY_REPOSITORY') ?: '';
$expectedRef = getenv('DEPLOY_REF') ?: 'refs/heads/main';
$deployProgram = getenv('DEPLOY_PROGRAM') ?: '/usr/local/sbin/deploy-wordpress-theme';
$replayDir  = getenv('DEPLOY_REPLAY_DIR') ?: '/var/lib/theme-deploy/deliveries';
$logFile    = getenv('DEPLOY_LOG_FILE') ?: '/var/log/theme-deploy/webhook.log';

if (strlen($secret) < 32 || !preg_match('/\A[^\/\s]+\/[^\/\s]+\z/', $repository)) {
    audit($logFile, 'configuration_error');
    respond(503, 'Deployment service unavailable.');
}

$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
$contentLength = filter_var(
    $_SERVER['CONTENT_LENGTH'] ?? null,
    FILTER_VALIDATE_INT,
    array('options' => array('min_range' => 0, 'max_range' => MAX_REQUEST_BYTES))
);

if ($contentType !== 'application/json' || $contentLength === false) {
    audit($logFile, 'invalid_request_metadata');
    respond(415, 'Request rejected.');
}

$rawBody = file_get_contents('php://input', false, null, 0, MAX_REQUEST_BYTES + 1);
if ($rawBody === false || $rawBody === '' || strlen($rawBody) > MAX_REQUEST_BYTES) {
    audit($logFile, 'invalid_body_size');
    respond(400, 'Request rejected.');
}

$providedSignature = $_SERVER['HTTP_X_DEPLOY_SIGNATURE_256'] ?? '';
$expectedSignature = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
if (!preg_match('/\Asha256=[0-9a-f]{64}\z/i', $providedSignature)
    || !hash_equals($expectedSignature, strtolower($providedSignature))) {
    audit($logFile, 'invalid_signature');
    respond(403, 'Request rejected.');
}

try {
    $payload = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    audit($logFile, 'invalid_json');
    respond(400, 'Request rejected.');
}

if (!is_array($payload)) {
    audit($logFile, 'invalid_payload');
    respond(400, 'Request rejected.');
}

$allowedKeys = array('repository', 'ref', 'sha', 'timestamp', 'delivery_id');
$payloadKeys = array_keys($payload);
sort($allowedKeys);
sort($payloadKeys);

if ($payloadKeys !== $allowedKeys
    || !is_string($payload['repository'])
    || !is_string($payload['ref'])
    || !is_string($payload['sha'])
    || !is_int($payload['timestamp'])
    || !is_string($payload['delivery_id'])) {
    audit($logFile, 'invalid_payload_shape');
    respond(400, 'Request rejected.');
}

$sha = strtolower($payload['sha']);
$deliveryId = $payload['delivery_id'];

if (!hash_equals($repository, $payload['repository'])
    || !hash_equals($expectedRef, $payload['ref'])
    || !preg_match('/\A[0-9a-f]{40}\z/', $sha)
    || !preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_.-]{15,127}\z/', $deliveryId)
    || abs(time() - $payload['timestamp']) > MAX_CLOCK_SKEW_SECONDS) {
    audit($logFile, 'payload_not_allowed', $deliveryId, $sha);
    respond(403, 'Request rejected.');
}

if (!is_dir($replayDir) || !is_writable($replayDir)) {
    audit($logFile, 'replay_store_unavailable', $deliveryId, $sha);
    respond(503, 'Deployment service unavailable.');
}

// Atomic exclusive creation makes a delivery ID single-use. A scheduled,
// operator-controlled job may remove records older than the retention period.
$deliveryRecord = $replayDir . '/' . hash('sha256', $deliveryId) . '.used';
$deliveryHandle = @fopen($deliveryRecord, 'x');
if ($deliveryHandle === false) {
    audit($logFile, 'replayed_delivery', $deliveryId, $sha);
    respond(409, 'Request rejected.');
}
fwrite($deliveryHandle, (string) $payload['timestamp']);
fclose($deliveryHandle);
@chmod($deliveryRecord, 0600);

if (!is_file($deployProgram) || !is_executable($deployProgram)) {
    audit($logFile, 'deploy_program_unavailable', $deliveryId, $sha);
    respond(503, 'Deployment service unavailable.');
}

// The executable path is server configuration, not request data. The validated
// declarative payload is sent over stdin, so no request field enters a shell
// command. The deploy program must perform locking, exact-SHA verification,
// health checks, and rollback.
$descriptorSpec = array(
    0 => array('pipe', 'r'),
    1 => array('pipe', 'w'),
    2 => array('pipe', 'w'),
);
$process = proc_open(array($deployProgram), $descriptorSpec, $pipes);

if (!is_resource($process)) {
    audit($logFile, 'dispatch_failed', $deliveryId, $sha);
    respond(503, 'Deployment service unavailable.');
}

$deployInput = json_encode(
    array(
        'repository'  => $repository,
        'ref'         => $expectedRef,
        'sha'         => $sha,
        'timestamp'   => $payload['timestamp'],
        'delivery_id' => $deliveryId,
    ),
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
);

fwrite($pipes[0], $deployInput . PHP_EOL);
fclose($pipes[0]);

// Drain but do not return program output to the caller. The deploy program is
// responsible for writing detailed diagnostics to its protected operational log.
stream_get_contents($pipes[1]);
fclose($pipes[1]);
stream_get_contents($pipes[2]);
fclose($pipes[2]);

$exitCode = proc_close($process);
if ($exitCode !== 0) {
    audit($logFile, 'deployment_failed', $deliveryId, $sha);
    respond(500, 'Deployment failed.');
}

audit($logFile, 'deployment_succeeded', $deliveryId, $sha);
respond(200, 'Deployment completed.');
