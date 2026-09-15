# 13 — Definition of Done

## Additional user-facing screen requirement — TSK-004B

Every future user-facing task must register/update its canonical Screen ID, localized purpose, approved actions, related Stories/Flows/criteria, step-by-step guide content, stable tour selectors where useful, role-aware guidance, RTL/LTR/mobile behavior, and sensitive-data review. Explicit deferral must be documented.

A feature/task is Done only when every applicable item below is satisfied and evidenced. `Not Applicable` requires a written reason. Automated tests are not part of the current Definition of Done.

## Requirement and Scope

- [ ] All linked Requirement, User Story, Flow, UI Screen, Acceptance Criteria, Security, and Task IDs are implemented without silent changes.
- [ ] The feature remains inside the current milestone and approved Phase 1 scope; any change has an approved decision/change-control record.
- [ ] Business rules, alternate/failure paths, state transitions, separation, and source references are complete.
- [ ] No unresolved critical blocker; lower-priority open item has owner, impact, and target action.

## Data and Business Integrity

- [ ] Logical database design, names, relationships, uniqueness, indexes, precision, and retention policies are respected.
- [ ] Server validation handles required fields, types, ranges, duplicates, bilingual data, safe files, and state-specific rules.
- [ ] Financial/stock/document operations are transactional, idempotent where retryable, and protected from documented races with locks/unique constraints/version checks.
- [ ] Approved/final/closed documents and ledgers are immutable; corrections use referenced reversal/return/cancellation/adjustment documents.
- [ ] Stock, payment, shift, loyalty, Product Wallet, Party Wallet, Gift Card, and party settlement effects reconcile to source records.
- [ ] Retail and party lines/workflows/stores remain separate; Product Wallet and Party Wallet remain separately modeled and authorized.
- [ ] Document numbers are unique and allocated through the configured concurrency-safe sequence.

## Authorization, Security, and Audit

- [ ] Authentication/session/CSRF and server-side policy/gate checks protect every route/action/download/export.
- [ ] Authorization covers role, branch, store, activity, document type/state, ownership, field sensitivity, approval/override, and configured limits.
- [ ] UI control visibility accurately reflects permissions without being the only control; direct request denial is manually verified.
- [ ] Files, attachments, imports, exports, and offline storage meet `docs/15-security-checklist.md`.
- [ ] Mandatory audit captures actor, timestamp, session/device where available, scope, source, reason, protected before/after, and approval/override context.
- [ ] Errors and logs reveal no secrets, payment evidence, cross-scope existence, or unnecessary customer/wallet data.

## UI Completeness

- [ ] Every functional backend capability has the complete documented UI; infrastructure-only exceptions are explicit.
- [ ] Existing Flux UI/Laravel/approved package components are reused before custom construction; any custom component has a documented gap and minimal scope.
- [ ] Default, loading/submitting, empty, error, success, disabled, permission-denied, validation, confirmation, and unsaved-change states are complete where relevant.
- [ ] Forms have labels, required indicators, logical sections, inline validation, duplicate-submit prevention, safe cancel, and bilingual grouping.
- [ ] Lists use server pagination/filtering/sorting/indexed search/eager loading and clear row actions; no unbounded browser dataset.
- [ ] The screen is responsive on approved desktop/tablet/POS devices, keyboard accessible, touch appropriate, readable in contrast, and communicates status without color alone.
- [ ] Arabic RTL and English LTR strings, layout, dates/numbers, icons, controls, dialogs, tables, and text expansion are complete.
- [ ] POS, when affected, remains barcode/keyboard/touch friendly; cart/totals/branch/store/drawer/shift/connectivity stay visible and cart survives recoverable errors.
- [ ] Required thermal/A4/PDF/label/Gift/party/quotation output is complete and verified; printing does not mutate source.

## Performance and Operations

- [ ] Queries are scoped first, select required data, avoid N+1, use appropriate indexes, and cap expensive ranges.
- [ ] Queue/scheduler/cache use is justified, idempotent, observable, permission-safe, and does not defer required atomic integrity.
- [ ] Backup/restore, monitoring, error handling, deployment, retention, and support documentation are updated when affected.
- [ ] Offline changes, when affected, cover device/session binding, expiration, safe IndexedDB/service-worker behavior, prohibited operations, sync retry/idempotency, server truth, and conflict review.

## Manual Verification and Handoff

- [ ] All linked acceptance criteria and `TASKS.md` browser steps are manually reviewed using representative roles, scopes, data, edge cases, and approved devices.
- [ ] Permission denial, validation failure, duplicate/concurrency, empty/error, print, responsive, Arabic RTL, and English LTR cases are evidenced.
- [ ] Data, stock, financial, wallet, and audit results are reconciled where affected.
- [ ] Actual commands/scenarios/results/evidence/verifier/date are recorded in `.ai/TEST_RESULTS.md`; no unperformed result is claimed.
- [ ] Product/architecture/UI/security/milestone/traceability/task documentation reflects the implementation.
- [ ] `.ai/PROGRESS.md`, `.ai/CURRENT_MILESTONE.md`, `.ai/BLOCKERS.md`, `.ai/DECISIONS.md`, and `.ai/HANDOFF.md` are current.
- [ ] Handoff explains changed areas, how to run the actual project, remaining work, risks, data/config needs, and next task.

## Reuse and Simplicity Gate

- [ ] The solution did not rebuild authentication, permissions, audit, Excel, PDF, barcodes, backup, media/uploads, data tables, pagination, filters, sorting, pickers, searchable selects, dialogs, drawers, toasts/alerts, charts, navigation, breadcrumbs, form controls, or UI states when Laravel, Flux UI, or an approved mature package already satisfied the requirement.
- [ ] No speculative repository pattern, generic service, base class, helper/trait sprawl, internal package, unused API, event architecture, microservice, design-system framework, workflow/permission/form/table builder, or custom one-use abstraction was added.

## Automated Testing Directive

Automated tests are currently deferred by explicit project-owner directive. No automated test code shall be created or executed unless a new explicit instruction changes this policy.

- Do not create PHPUnit, Pest, unit, feature, integration, browser, Playwright, Cypress, or end-to-end tests.
- Do not run automated suites or make them a delivery condition.
- A future explicit owner instruction may revise this section; until then, scenario-based manual verification and UAT are the evidence standard.
