# ERP Change Classification and Execution Policy

This repository-wide policy is mandatory for every task performed by every Codex account and every user working through SSH, in every current worktree and every future worktree based on a commit that contains it. It applies from the repository root to every nested path. A user-specific workflow, local convention, or nested instruction file must not weaken or bypass it; additional instructions may only be equally or more restrictive.

This policy governs task classification and execution discipline. It does not grant permission for an action prohibited by `AGENTS.md`, the active task sources, or an explicit owner instruction; the more restrictive instruction controls.

## ERP Change Classification

Before editing, assign every task exactly one initial class. State the class in one short line. Do not perform a separate lengthy analysis phase.

### V0 — Visual or copy-only change

Examples:

- Change documentation or governance without changing runtime behavior.
- Remove or reorder a sidebar item.
- Remove a duplicate Blade component.
- Change text, translation, spacing, color, alignment, icons, or responsive layout.
- Hide an existing UI element without changing its availability or authorization.

Boundaries:

- Do not change controllers, services, models, queries, routes, policies, permissions, validation, databases, or workflows.
- If logic changes become necessary, stop and reclassify before editing.

Execution:

- Make the minimal direct edit.
- For documentation-only work, run only the requested documentation hygiene checks.
- For application presentation work, run syntax and Blade compilation once.
- Run only an existing directly relevant contract test when available.
- Do not run a database, browser matrix, performance profile, Vite build, artifact, version bump, migration, or deployment for an individual change.
- Accumulate V0 changes into one approved UI batch.

Target effort: usually 5–20 minutes.

### V1 — Local UI behavior

Examples:

- Form interaction, modal, pagination controls, frontend validation, or JavaScript behavior.
- No business-rule or persisted-data semantic change.

Execution:

- Review only the affected component and request contract.
- Run one focused frontend or feature check.
- Build Vite once only if frontend source changed.
- Use one relevant desktop/mobile check, not a full browser matrix.
- Batch compatible V1 changes when possible.

### L2 — Application logic

Examples:

- Controllers, services, validation, queries, filters, calculations, API contracts, notifications, or workflow behavior.

Execution:

- Identify direct callers and data dependencies.
- Check authorization and validation.
- Run focused unit or feature tests for the changed behavior.
- Check query count or performance only when changing a hot route or query.
- Do not run unrelated historical suites.

### D3 — Sensitive ERP domain change

Examples:

- Inventory quantities or reconciliation.
- POS and sales.
- Pricing calculations.
- Accounting, financial documents, payments, refunds, or taxes.
- Permissions, roles, authorization scope, approvals, or audit trails.
- Concurrent writes, idempotency, stock locks, or irreversible business state.

Execution:

- Review relationships, transactions, authorization, auditability, idempotency, concurrency, and rollback behavior.
- Protect existing records and tenant or location scope.
- Use focused high-confidence tests covering success, denial, and failure paths.
- Require a database backup only when production data or schema will mutate.
- Never silently mix these changes with cosmetic work.

### I4 — Schema, infrastructure, or release change

Examples:

- Migrations, indexes, runtime links, dependencies, queues, caches, web server or PHP-FPM configuration, deployment, or release packaging.

Execution:

- Use the last proven deployment or promotion workflow as the template.
- Never redesign a working deployment script for a routine release.
- Verify the exact active release, target, hashes, runtime links, generated assets, permissions, maintenance recovery, rollback, and smoke checks.
- Never run Composer in production when the authenticated artifact already contains `vendor`.
- Never rebuild frontend assets in production.
- Explicitly include `public/build` in release artifacts.
- Reject nested `storage/storage`.
- Use `.env`, `storage`, and `public/storage` only through the established shared links.
- Never modify global Git `safe.directory`.
- Do not push or modify remotes unless explicitly requested.

## Mandatory Baseline Separation

Every implementation task must distinguish these two baselines before editing:

- `SOURCE_BASELINE` is the exact commit used to create the working branch.
- `PRODUCTION_BASELINE` is the exact active release path, version, and commit.

Never assume the two baselines are identical. V0 and V1 work must start from the latest governance-bearing development baseline. Deployment preflight must independently verify the exact production baseline; do not infer it from the source branch, a tag, a pending-batch entry, or an earlier deployment record.

## Mandatory Escalation Rule

If a task begins as V0 or V1 but requires logic, queries, permissions, routes, models, database, or workflow changes:

- Stop before making those additional changes.
- Report the discovered dependency in no more than five lines.
- Reclassify the task.
- Continue only within the newly justified scope.
- Never expand scope silently.

## Batching and Release Rules

- Record every completed, undeployed V0 or V1 change in `.ai/PENDING_CHANGE_BATCH.md`, including its classification, short description, commit, affected files, and status.
- Accumulate V0 and compatible V1 changes into that single pending UI batch.
- Do not create a version, artifact, manifest, deployment script, or release for each entry.
- Close the batch only after explicit user instruction.
- At batch closure, perform one combined version bump, verification, build, artifact, and deployment workflow.
- Clear or archive the batch only after production success is confirmed.
- Batch L2 changes only when they affect the same domain and release safely together.
- D3 and I4 changes require explicit release handling.
- Do not mix unrelated sensitive domains in one release.

## Immutable Release Freeze

- Once an artifact and manifest receive their final SHA-256 values, both are immutable.
- Never patch, replace, or silently rebuild the same artifact.
- Any source change after artifact creation belongs to a new version or the next batch.
- Every deployment script must reference one exact immutable artifact and its exact manifest.
- A documentation-only governance commit does not invalidate or rebuild an already prepared application artifact.

## Verification Budget

- Run each required focused verification once.
- Do not repeat a passing check.
- On failure, diagnose the exact failure and rerun only the affected check.
- Broad historical suites, full browser matrices, performance profiling, complete manifest generation, and deep release checks belong to the final release stage, not every edit.
- Never create tests merely to test unchanged framework or deployment behavior.
- Never claim a blocked or unexecuted check as passed.

## Source Control and Multiple Codex Accounts

- Use one writable worktree per active Codex session.
- Never let two Codex accounts modify the same worktree concurrently.
- Preserve unrelated dirty files; never reset, clean, delete, or stash them without explicit authorization.
- Use an isolated worktree when the original is dirty.
- Give small changes one focused commit.
- Do not update multiple `.ai` status files unless the task materially changes project status.

## Task Start Format

At the start of every implementation task, output only:

```text
CHANGE_CLASS=<V0|V1|L2|D3|I4>
SCOPE=<one sentence>
RELEASE_NOW=<YES|NO>
VERIFICATION=<one sentence>
SOURCE_BASELINE=<exact working-branch source commit>
PRODUCTION_BASELINE=<exact active release path, version, and commit>
```

Then implement immediately.

## Completion Format

For non-release changes, report only:

- Change class.
- Changed files.
- Commit.
- Focused verification.
- Whether the change was added to the pending release batch.

Do not produce long forensic reports for V0 or V1 changes.

