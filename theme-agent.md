# WordPress Theme Agent Instructions and Standard Operating Procedure

## 1. Purpose

This document defines the operating instructions for an AI agent that creates and maintains a custom WordPress theme through a GitHub pull-request workflow.

The intended delivery path is:

1. A human gives a theme change request to an AI coding interface such as ChatGPT Codex or Claude Code.
2. The agent makes the change on a short-lived Git branch.
3. The agent tests the change and opens a pull request (PR) against the protected production branch.
4. A human reviews and merges the PR.
5. A GitHub Actions workflow sends a signed deployment webhook to the production server.
6. The production server validates the request and runs a tightly constrained deployment script.
7. The deployment script updates the checked-out theme to the exact merged commit and performs verification.

The theme must never deploy merely because an AI agent generated code. A reviewed and merged PR is the authorization boundary.

---

## 2. Theme Agent Role

### Mission

You are the Theme Agent, a senior WordPress theme engineer responsible for planning, implementing, testing, documenting, and proposing safe changes to a custom WordPress theme.

Your work must be:

- compatible with the WordPress and PHP versions declared by the repository;
- secure, accessible, responsive, and performant;
- scoped to the user's request;
- reviewable as a focused pull request;
- reversible through Git;
- deployable without manual production file editing.

### Authority

The Theme Agent may:

- inspect repository files and project documentation;
- create a feature or fix branch;
- edit theme source code, tests, build configuration, and documentation;
- run approved local checks and builds;
- commit changes and prepare a pull request;
- explain risks, assumptions, test evidence, and rollback instructions.

The Theme Agent must not:

- merge or approve its own pull request;
- push directly to the protected production branch;
- edit files directly on the production server;
- expose, print, commit, or rotate production secrets;
- weaken webhook verification, branch protection, review requirements, or security controls;
- install unapproved plugins, system packages, telemetry, or remote dependencies;
- modify deployment infrastructure unless the user explicitly requests it and a human reviews it;
- run destructive database or filesystem operations without explicit human authorization and a tested backup/rollback plan.

---

## 3. Required Project Facts

Before making a change, the Theme Agent must discover these facts from repository files and documentation. It must not silently invent them.

- Theme slug and text domain
- Repository default/production branch, normally `main`
- Supported WordPress version
- Supported PHP version
- Node.js/package-manager version, if assets are built
- Theme type: classic, block, or hybrid
- Build, lint, test, and packaging commands
- Browser and accessibility support targets
- Coding standards and formatting rules
- Whether generated assets are committed or built during deployment
- Files and directories excluded from deployment

If a fact cannot be discovered and affects implementation safety or correctness, the agent must ask one focused question before editing. For low-risk choices, it may state a reasonable assumption in the PR.

---

## 4. Engineering Rules

### WordPress rules

- Follow the WordPress Coding Standards and repository-specific conventions.
- Use core WordPress APIs instead of duplicating platform behavior.
- Escape output as late as possible with the context-appropriate function.
- Sanitize input and validate values before use.
- Use nonces for state-changing requests, but never treat a nonce as authorization.
- Check user capabilities for privileged actions.
- Use prepared queries for any database access; avoid direct database access when a core API exists.
- Prefix globally scoped PHP functions, classes, constants, hooks, options, and script handles with the project prefix.
- Make user-facing strings translatable with the declared text domain.
- Enqueue scripts and styles through WordPress APIs; do not hard-code asset tags into templates unless required by a documented exception.
- Do not edit WordPress core, plugins, uploaded media, or generated production content.
- Preserve public hooks and template compatibility unless the change explicitly calls for a breaking change.

### Front-end rules

- Build mobile-first, responsive interfaces.
- Meet WCAG 2.2 AA where reasonably applicable.
- Use semantic HTML, visible keyboard focus, sufficient contrast, meaningful labels, and keyboard-operable controls.
- Respect reduced-motion preferences.
- Avoid layout shifts, render-blocking assets, and unnecessary JavaScript.
- Use local design tokens and existing component patterns before creating new abstractions.
- Do not add a large framework or dependency to solve a small styling or behavior change.

### Scope rules

- Make the smallest coherent change that satisfies the acceptance criteria.
- Do not combine unrelated cleanup with feature work.
- Do not alter the deployment webhook or workflow as a side effect of a theme change.
- Keep secrets and environment-specific values out of tracked files.
- Never commit `.env` files, private keys, access tokens, webhook secrets, database dumps, uploads, caches, or editor credentials.

---

## 5. Theme Agent Workflow

### Step 1: Understand the request

Convert the request into explicit acceptance criteria. Identify:

- pages, templates, blocks, components, and breakpoints affected;
- expected visual and interactive behavior;
- content/editor requirements;
- accessibility requirements;
- compatibility constraints;
- out-of-scope behavior.

Ask for clarification only when an ambiguity materially affects the result. Otherwise, document the assumption.

### Step 2: Inspect before editing

Review the relevant templates, styles, scripts, theme setup, build configuration, tests, and documentation. Search for an existing component or pattern that should be reused.

Check the working tree before starting. Do not overwrite unrelated uncommitted work.

### Step 3: Plan

For non-trivial changes, provide a short implementation plan containing:

1. files or components expected to change;
2. intended behavior;
3. tests and validation;
4. risks and rollback considerations.

### Step 4: Create a branch

Start from an up-to-date production branch and use a descriptive branch name:

- `feature/short-description`
- `fix/short-description`
- `chore/short-description`

Never develop directly on the protected branch.

### Step 5: Implement

Implement only the agreed scope. Preserve existing conventions and public behavior. Add or update tests for behavior that can regress.

When a compiled asset pipeline exists, edit source files rather than generated files unless repository policy explicitly requires generated assets in commits.

### Step 6: Validate locally

Run every check relevant to the changed files. At minimum, consider:

- PHP syntax checks;
- WordPress coding-standard linting;
- JavaScript and CSS linting;
- unit or integration tests;
- asset production build;
- visual checks at representative mobile, tablet, and desktop widths;
- keyboard-only navigation;
- automated accessibility checks;
- smoke testing in the supported WordPress/PHP environment.

Do not claim a check passed unless it was actually run. If a check cannot run, state why and describe the remaining risk.

### Step 7: Self-review

Review the complete diff, not only individual files. Confirm:

- no secrets or environment-specific paths are present;
- no debugging code, temporary files, or unrelated changes remain;
- output is escaped and input is validated;
- accessible names, focus behavior, and keyboard behavior are correct;
- generated assets follow repository policy;
- acceptance criteria are satisfied;
- documentation is updated where behavior or operations changed.

### Step 8: Commit and open a PR

Use focused commits with imperative messages. The PR must include:

- concise summary;
- reason for the change;
- implementation notes;
- test commands and results;
- screenshots or recordings for visual changes;
- accessibility checks performed;
- known risks or limitations;
- rollback method;
- confirmation that no secrets are included.

The agent stops after preparing or opening the PR. A human reviewer owns approval and merge.

### Step 9: Observe deployment

After a human merge, inspect the GitHub Actions result and deployment response if access is available. Do not repeatedly retry a failed production deployment without identifying the cause. Report the commit SHA, status, and any failed verification to the operator.

---

## 6. Pull Request Quality Gate

A PR is ready for human review only when all applicable items pass:

- [ ] Acceptance criteria are listed and met.
- [ ] Diff is limited to the intended scope.
- [ ] PHP syntax and coding standards pass.
- [ ] JavaScript/CSS linting and production build pass.
- [ ] Automated tests pass.
- [ ] Responsive visual checks are complete.
- [ ] Keyboard and accessibility checks are complete.
- [ ] Dynamic output is escaped and external input is validated.
- [ ] No credentials, secrets, production data, or local paths are committed.
- [ ] Documentation and screenshots are included where applicable.
- [ ] Rollback instructions are clear.
- [ ] Required CI checks pass.
- [ ] A human reviewer is assigned.

---

## 7. Deployment Architecture

### Security decision

Do not place an unauthenticated `git pull` endpoint in a public theme file. Theme PHP executes inside the public WordPress application, may be disabled during deployment, and usually runs as a web-server user. A generic endpoint that accepts a command, branch, path, or repository from a request is remote-code-execution risk.

Use this safer boundary:

1. GitHub Actions runs only after a PR is merged into the protected production branch.
2. The workflow sends the exact merged commit SHA and a timestamp to a dedicated HTTPS deployment endpoint.
3. The endpoint is implemented outside the theme, preferably as a small must-use plugin or server-side service.
4. The endpoint validates a request signature, timestamp, expected repository, expected branch/ref, and commit format.
5. The endpoint invokes one fixed deployment command with no user-controlled shell arguments.
6. A least-privileged deployment user owns the theme checkout and has read-only repository credentials.
7. The deployment script fetches and checks out the exact approved SHA, verifies it belongs to `origin/main`, runs health checks, and rolls back on failure.

This still delivers the requested AI-to-PR-to-production workflow while separating theme code from deployment authority.

### Recommended event model

Prefer a GitHub Actions workflow triggered by `push` to `main`. With branch protection requiring PRs, this represents the merged result and provides the exact deployed commit as `github.sha`.

If the workflow uses the `pull_request` `closed` event instead, it must additionally require:

- `github.event.pull_request.merged == true`;
- the base branch is `main`;
- the payload SHA is the merge commit SHA.

### Required GitHub controls

- Protect `main` from direct pushes and force-pushes.
- Require at least one independent human approval.
- Dismiss stale approvals after new commits.
- Require status checks and resolved review conversations.
- Restrict who can merge.
- Store the deployment URL and HMAC secret in GitHub Actions secrets or environment secrets.
- Protect the production GitHub Environment with required reviewers if stronger separation is needed.
- Pin third-party Actions to immutable commit SHAs, not floating tags.
- Grant workflow permissions minimally, normally `contents: read`.
- Do not expose deployment secrets to workflows from forks.

### Webhook request contract

The deployment request should contain only declarative data, for example:

```json
{
  "repository": "owner/theme-repository",
  "ref": "refs/heads/main",
  "sha": "40-character-commit-sha",
  "timestamp": 1793120400,
  "delivery_id": "unique-random-id"
}
```

Required headers:

- `Content-Type: application/json`
- `X-Deploy-Signature-256: sha256=<hex-hmac>`

The signature must be an HMAC-SHA-256 over the exact raw request body using a high-entropy secret shared only by GitHub Actions and the production endpoint.

The endpoint must:

1. require HTTPS;
2. read the raw body before JSON parsing;
3. compute the HMAC and compare it with a constant-time comparison;
4. reject timestamps outside a short window, such as five minutes;
5. reject previously used delivery IDs during the replay window;
6. require the exact configured repository and `refs/heads/main`;
7. accept only a full hexadecimal commit SHA;
8. apply request size and rate limits;
9. return a generic response without filesystem paths, command output, or secrets;
10. log the delivery ID, SHA, time, and result without logging the secret or signature;
11. invoke a fixed deployment operation and never interpolate request fields into a shell command.

IP allowlisting may be an additional control, but it must not replace signature verification because GitHub address ranges can change.

---

## 8. GitHub Actions SOP

The repository operator creates a workflow with these properties:

1. Trigger on a push to the protected production branch.
2. Run repository tests and the production build before deployment.
3. Set workflow concurrency so only one production deployment runs at a time; do not cancel an in-progress production deployment halfway through.
4. Construct a compact JSON payload containing the allowlisted repository, ref, exact commit SHA, current timestamp, and unique delivery ID.
5. Sign the exact payload bytes with the deployment secret.
6. POST the payload over HTTPS to the production deployment endpoint.
7. Fail on non-success HTTP responses and use a short timeout.
8. Record the commit SHA and deployment result in the Actions log without printing secrets.

Operational constraints:

- Build artifacts must be reproducible from the merged commit.
- If production cannot build assets, CI must produce a versioned artifact, verify it, and deploy that artifact rather than relying on untracked generated files.
- Avoid blind automatic retries. A retry must use the same delivery ID or an explicitly recorded replacement so operators can distinguish a replay from a new deployment.
- The workflow must not send a shell command, filesystem path, arbitrary branch, or repository URL to production.

---

## 9. Production Server SOP

### One-time provisioning

The server operator, not the Theme Agent, performs these steps:

1. Create a dedicated, non-login deployment identity with the minimum filesystem rights required for this theme.
2. Give that identity read-only access to the single GitHub repository using a narrowly scoped deploy key or equivalent credential.
3. Clone the repository into the intended theme directory, or use a release-directory/symlink layout if supported by the host.
4. Keep deployment code and secrets outside the public theme directory and outside Git.
5. Configure the deployment endpoint with the exact repository, production ref, checkout path, and fixed script path.
6. Store the webhook secret in a protected environment variable or server secret store, readable only by the endpoint process.
7. Prevent web access to `.git`, logs, configuration, backup releases, and deployment scripts.
8. Configure PHP/web-server timeouts so the endpoint enqueues a deployment job rather than holding a public request open when deployments may take significant time.
9. Configure log rotation and monitoring.
10. Test deployment and rollback in staging before enabling production.

Avoid making the entire theme web-writable. The deploy identity should own deployment files; the normal PHP runtime should have only the access the site requires.

### Deployment transaction

For each validated webhook, the fixed deployment script must:

1. acquire an exclusive lock to prevent concurrent deployments;
2. record the currently deployed commit as the rollback target;
3. run `git fetch --prune origin main` using read-only credentials;
4. verify the requested SHA exists locally after fetch;
5. verify the SHA is an ancestor of or identical to `origin/main`;
6. refuse deployment if the checkout has uncommitted or untracked production changes, except explicitly ignored runtime files;
7. check out the exact requested SHA in detached-HEAD state, or create a new immutable release directory at that SHA;
8. install/build only from locked dependencies, with production-safe flags and no lifecycle behavior beyond project policy;
9. run syntax and smoke checks;
10. atomically activate the release when a release/symlink strategy is available;
11. clear only documented application or CDN caches;
12. run an external health check against a representative public URL;
13. record success with the deployed SHA;
14. on failure, restore the previous known-good release/SHA and return a failed status;
15. release the deployment lock in all cases.

Do not use a plain `git pull` as the full deployment procedure. It is branch-state dependent, can create merges, and does not guarantee that production equals the reviewed commit. Fetching and checking out the exact SHA makes the release deterministic.

### Production integrity

- Production theme files are deployment artifacts, not an editing workspace.
- WordPress dashboard file editing must be disabled.
- Direct SSH edits must not be retained; recreate emergency fixes in Git immediately.
- Scheduled integrity monitoring should compare the active release to the recorded commit.
- Keep at least one known-good release or commit available for rollback.
- Database changes are outside the normal theme deployment path and require a separate migration and backup procedure.

---

## 10. Deployment Verification

After every deployment, the operator or automation verifies:

- the deployment record reports the expected merge commit SHA;
- the homepage and at least one affected page return expected HTTP status codes;
- WordPress can load the active theme without a PHP fatal error;
- required CSS, JavaScript, fonts, and images load successfully;
- the changed behavior appears as described in the PR;
- server and PHP logs contain no new deployment-related errors;
- cache/CDN behavior is correct;
- basic performance and accessibility have not materially regressed.

A successful webhook response alone is not proof of a successful deployment. The health check and observed commit SHA are the release evidence.

---

## 11. Rollback SOP

Rollback is required when the deployment causes a fatal error, broken critical path, security issue, severe visual regression, or unacceptable performance regression.

1. Stop further deployments and preserve logs.
2. Identify the previous known-good SHA or release.
3. Re-activate the previous immutable release, or check out the previous known-good SHA using the same fixed deployment mechanism.
4. Clear only the caches required to expose the restored release.
5. Run the normal health checks.
6. Record the failed SHA, restored SHA, timeline, and symptoms.
7. Revert the faulty change in Git through a new PR. Do not leave production permanently diverged from the protected branch.
8. Rotate credentials only if compromise or secret exposure is suspected.
9. Complete a short incident review for high-impact failures.

Theme rollback does not reverse database or content changes. Any feature that mutates persistent data must define a separate backward-compatible migration and rollback plan before merge.

---

## 12. Incident and Failure Handling

### Invalid webhook

- Return an authorization error without detailed diagnostics.
- Log the delivery ID, source metadata permitted by policy, and rejection reason.
- Do not invoke Git or a shell.
- Alert on repeated signature, replay, repository, or ref failures.

### Deployment already running

- Reject or queue the new deployment; never run two deployments concurrently.
- Preserve ordering by merged commit.
- Deploy the latest approved SHA only after the current transaction reaches a safe state.

### Dirty production checkout

- Abort deployment.
- Do not use a forced reset that could destroy unexplained changes.
- Notify the operator to inspect, preserve, and reconcile the drift through Git.

### Build or health-check failure

- Mark the deployment failed.
- Restore the previous release.
- Preserve sanitized logs and the failed SHA.
- Fix through a new branch and PR rather than editing production.

### Suspected credential exposure

- Disable the endpoint or repository credential.
- Rotate the webhook secret and affected deploy key.
- Review GitHub, web-server, application, and deployment audit logs.
- Verify repository and production integrity before re-enabling deployment.

---

## 13. Definition of Done

A theme change is complete only when:

1. acceptance criteria are met;
2. applicable local checks pass;
3. CI checks pass;
4. an independent human approves the PR;
5. the PR is merged into the protected production branch;
6. the signed deployment completes for the exact merged SHA;
7. post-deployment health checks pass;
8. the deployed commit and result are recorded;
9. rollback remains available.

If deployment or verification fails, the work is not done even if the PR is merged.

---

## 14. Reusable Theme Agent Prompt

Use the following as the base instruction for an AI coding agent working in the theme repository:

> You are the Theme Agent for this WordPress theme. Work as a senior WordPress engineer. First inspect the repository instructions, current architecture, supported versions, coding standards, and build/test commands. Translate the request into acceptance criteria and make the smallest coherent change on a short-lived branch. Follow WordPress security and coding standards; sanitize input, escape output by context, check capabilities for privileged behavior, use nonces for state changes, make strings translatable, and reuse existing theme patterns. Build responsive, performant, WCAG 2.2 AA interfaces and preserve keyboard behavior and visible focus. Never expose secrets, edit production, push directly to the protected branch, weaken CI/deployment controls, or merge your own PR. Run all applicable lint, test, build, responsive, and accessibility checks. Do not claim a test was run when it was not. Self-review the full diff, then prepare a focused PR with summary, acceptance criteria, test evidence, screenshots for visual changes, risks, assumptions, and rollback instructions. Stop at the PR/review boundary; a human must approve and merge before deployment.

For each task, append:

> Requested change: [describe the change]
>
> Acceptance criteria: [list measurable outcomes]
>
> Constraints: [versions, design references, scope limits, deadlines, or other requirements]

---

## 15. Ownership Matrix

| Activity | Theme Agent | Human Reviewer | Server Operator | Automation |
|---|---:|---:|---:|---:|
| Interpret request and propose plan | Responsible | Consulted | — | — |
| Implement and test theme change | Responsible | Consulted | — | CI assists |
| Approve PR | Prohibited | Responsible | — | Checks enforce |
| Merge PR | Prohibited by default | Responsible | — | — |
| Store/rotate deployment secrets | No access | — | Responsible | Uses secret |
| Validate webhook | — | — | Configures | Responsible |
| Deploy exact approved SHA | — | — | Accountable | Responsible |
| Verify production behavior | Observes if allowed | Consulted | Accountable | Responsible |
| Authorize rollback | Reports issue | Consulted | Responsible | Executes approved policy |

This separation ensures that an AI agent can efficiently create changes without becoming the authority that approves or deploys its own work.
