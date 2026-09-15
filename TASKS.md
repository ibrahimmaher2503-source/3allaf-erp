# TOY & JOY Phase 1 — Implementation Backlog

## Detailed Specification References (DEC-040)

`AI_INDEX.md` is the detailed reading router. The following concise references apply in addition to each task's existing dependencies; they do not approve production values or extend business scope.

| Tasks | Required detailed references |
|---|---|
| TSK-001 through TSK-009 | docs/30; docs/35, docs/36, docs/37, and docs/38 where the task changes controlled states, data, user-facing screens, or outputs; docs/39 for release/backup evidence |
| TSK-010 through TSK-013 | docs/23, docs/36, docs/37; docs/35 for states and docs/38 for import/barcode/export outputs |
| TSK-014 through TSK-016 | docs/35, docs/36, docs/37, docs/38; docs/17-19 where approval, attachments, or immutability applies |
| TSK-017 through TSK-018 | docs/24, docs/35, docs/36, docs/37, docs/38 |
| TSK-019 through TSK-022 | docs/25, docs/35, docs/36, docs/37; docs/38 where a document/report prints |
| TSK-023 | docs/31, docs/32, docs/35, docs/36, docs/37, docs/38 |
| TSK-024 | docs/31, docs/32, docs/35, docs/36, docs/37, docs/38 |
| TSK-025 | docs/32, docs/35, docs/36, docs/37, docs/38 |
| TSK-026 | docs/31, docs/35, docs/36, docs/37, docs/38 |
| TSK-027 through TSK-029 | docs/27, docs/35, docs/36, docs/37; docs/31 for POS integration and docs/38 for outputs |
| TSK-030 | docs/26, docs/31, docs/35, docs/36, docs/37, docs/38 |
| TSK-031 through TSK-036 | docs/28, docs/29, docs/33, docs/35, docs/36, docs/37, docs/38 as applicable |
| TSK-037 | docs/35, docs/36, docs/37, docs/38 |
| TSK-038 through TSK-040 | docs/34, docs/36, docs/37, docs/38, docs/39 |
| TSK-041 | docs/23, docs/25, docs/36, docs/37, docs/38, docs/39 |
| TSK-042 through TSK-044 | docs/30, docs/34, docs/38, docs/39 and only module references required by the actual scenario |

## Backlog Rules

Every task is executable/reviewable, delivers a visible result, and begins `Not Started`. Work only on tasks in `.ai/CURRENT_MILESTONE.md`; do not open more than one milestone. Every task inherits `AGENTS.md`, `docs/09-coding-standards.md`, `docs/13-definition-of-done.md`, the shared UI contract in `.ai/UI_SCREENS.md`, and the manual-only verification directive.

Reuse Laravel, Flux UI, and a single approved mature package where appropriate. Do not build authentication, permissions, audit, Excel, PDF, barcode, backup, media/upload, data table, pagination, filter/sort, picker, searchable select, modal/drawer, toast/alert, chart, sidebar, breadcrumbs, form controls, loading, or empty state from scratch when an approved capability exists. Package names/versions are not approved until the actual Laravel project is inspected. Automated tests are currently deferred; no task creates or runs automated tests.

## Client Feedback Remediation — 2026-08-20 status sync

The owner-expanded scope is tracked in `docs/Master Change Request — Client Feedback Remediation & Setup UX Overhaul.md` and `docs/client-feedback-remediation-checklist.md`, not as a new Phase task. The client-fix queue is **12 DONE / 3 PARTIAL / 0 ACTIVE (CF-01–CF-15)**: CF-08, CF-13, and CF-14 are PARTIAL. The expanded ledger has **8 Local/Dev slices with requirement-level PARTIAL evidence / 7 open**. The earlier **15 DONE / 0 ACTIVE** wording is retained as historical status and explicitly superseded.

Latest local evidence includes structured customer names/children/duplicate email, supplier/PO payment terms, Global/Branch/Location printer scope, payment/tax and sequence acceptance, migration rollback/forward plus stable authorization seeding, owner-decision CTAs, Arabic RTL and English LTR browser batches, §66 **15 tests / 118 assertions PASS**, and multi-branch **20 tests / 104 assertions PASS**. Focused customer/child and supplier/PO QA verified local persistence/reload paths.

Owner policy/value approval, genuine owner data, warehouse taxonomy and inheritance provenance, physical printers/devices, Production, UAT, release approval, and final requirement-by-requirement closure remain open. This status sync does not change authoritative Phase Gates or Go-Live claims.

## Status Accuracy (2026-08-09)

Task statuses below were re-verified against **actual production code** — migrations, module classes, and routes — not against previous status text. The full classification, per-task evidence, and dependency analysis live in `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.

A screen that renders `PENDING` cards is **not** an implementation. Several tasks previously recorded as `Completed` were readiness boundaries only and are now labelled `READINESS_ONLY`, `FOUNDATION_ONLY`, or `MISSING`. Nothing was downgraded on opinion: each label cites the table, module, or route that does or does not exist.

Classifications used: `FULL_IMPLEMENTATION`, `PARTIAL_IMPLEMENTATION`, `READINESS_ONLY`, `FOUNDATION_ONLY`, `MISSING`, `DOWNSTREAM_DEPENDENCY`, `OWNER_DECISION_REQUIRED`, `EXTERNAL_ONLY`.

**No task in this repository is `RELEASE_READY`.**

## Phase 1 — Foundation, Access and Operational Controls

### TSK-001 — Establish the Laravel Platform and Operational Baseline

- **Task ID / Phase / Milestone / Status:** TSK-001; Phase 1; DM 1.1; **In Progress — specific local work remains: the actual local backup/restore capability/status and setup/run/recovery deployment/rollback runbooks are not implemented, and custom bilingual 419/429 error views are not present (framework fallback only).**
- **Implementation Progress:** The local platform assigns a server-generated UUID request ID through Laravel Context and returns it as `X-Request-ID` on normal and rendered exception responses. Resilient bilingual error views, maintenance behavior, health authorization, and the local runtime/build baseline are present and manually verified. The remaining local gap is the required actual backup/restore capability/status and setup/run/recovery documentation; production provider, destination, RPO/RTO, monitoring, and support decisions remain separate production blockers.
- **Title / Purpose / Description:** Establish and verify the single-repository Laravel modular-monolith baseline, environments, safe configuration, queues/scheduler/cache decision, logging/monitoring, attachment storage, backup/restore and deployment runbook. Implementation was explicitly authorized on 2026-08-02.
- **Traceability:** PRD NFR-01, NFR-04, NFR-07; Stories US-032; Flows FLW-AUTH-01, FLW-RPT-03; UI UI-SYS-003–004, UI-SYS-010; AC AC-NFR-01, AC-NFR-04, AC-NFR-07; Security SEC-027–031.
- **Dependencies / Required Inputs:** Local PHP/Laravel/SQLite baseline implemented; BLK-001–BLK-002 remain for production hosting/domain/database, Redis/queue/scheduler, storage/backup RPO/RTO, and monitoring/support owners.
- **Database Entities:** platform configuration plus `attachments`, `audit_logs`, `approval_records` logical baseline; no production schema beyond authorized foundation.
- **Backend / Livewire / Blade Deliverables:** Laravel foundation and environment/runbooks; health/backup status read models; full-page health/backup screens only if approved; safe error Blade views. No speculative module code.
- **UI / Flux / Alpine / Vite:** UI-SYS-003, UI-SYS-004, UI-SYS-010; Flux Cards/Tables/Badges/Alerts/Progress/Error; Alpine none; Vite verified common entry and production assets.
- **Suggested Packages:** Candidate capability only for backup and error monitoring after compatibility/security/license review; no package preselected or installed.
- **Permissions / Validation / Audit / States / Print:** Administrator/Support/Reviewer scope; no secret display; environment/backup actions confirmed/audited; health states healthy/degraded/down; backup queued/success/fail; print authorized status report only.
- **Manual Browser Verification:** Confirm production-safe errors/health authorization; create and restore an approved isolated backup; verify worker/scheduler/storage/monitoring signal and no secret leakage.
- **Definition of Done:** `docs/13-definition-of-done.md`, DM 1.1 exit criteria, actual setup/run/recovery documentation, recorded evidence, no unresolved critical platform blocker.

### TSK-002 — Implement Authentication, Sessions, and Account Recovery

- **Task ID / Phase / Milestone / Status:** TSK-002; Phase 1; DM 1.1; **Completed for approved local scope**.
- **Closure Audit (2026-08-03):** Browser verification passed for valid and invalid login, generic credential errors, rate limiting, reset request, actual expired-token rejection, single-use-token rejection after a successful local reset, session regeneration, logout/direct denial, CSRF origin rejection, locale/direction, and responsive auth layout. Production identity, MFA, lockout, password, mail, and session-policy values remain in BLK-005.
- **Title / Purpose / Description:** Deliver maintained Laravel session authentication, login, forgot/reset password, logout/revocation, locale/profile basics, and safe denied behavior without a custom auth framework.
- **Traceability:** NFR-01, NFR-03–NFR-04; US-032; FLW-AUTH-01; UI-AUTH-001–002, UI-SYS-008–009; AC-NFR-01, AC-NFR-03–04; SEC-001–005, SEC-011.
- **Dependencies / Required Inputs:** TSK-001; BLK-005; identity fields, password/reset/session/MFA/lockout policy.
- **Database Entities:** `users`, framework session/reset structures as verified, `audit_logs`.
- **Backend / Livewire / Blade Deliverables:** auth routes/controllers/actions/policies using framework conventions; profile/session actions; Blade/Livewire auth/profile screens per actual starter approach.
- **UI / Flux / Alpine / Vite:** Flux Card/Input/Checkbox/Button/Alert/Loading/Dialog; Alpine none unless framework component requires minimal behavior; Vite common auth assets.
- **Suggested Packages:** Laravel-native authentication capability/starter appropriate to Blade/Livewire; do not add duplicate auth package.
- **Permissions / Validation / Audit / States / Print:** guest/authenticated boundaries; credential/reset/password validation; generic errors/rate limits; login/reset/session events; active/locked/expired; Print None.
- **Manual Browser Verification:** Valid/invalid/locked login, rate limit, reset expiry/single use, session regeneration/logout/revocation, CSRF, direct denied route, RTL/LTR/responsive/accessibility.
- **Definition of Done:** DoD plus owner session policy satisfied and all actual results recorded.

### TSK-003 — Build Application Layouts and Restricted PWA Shell

- **Task ID / Phase / Milestone / Status:** TSK-003; Phase 1; DM 1.1; **Completed for approved local scope**.
- **Closure Audit (2026-08-03):** Auth, Admin, Operations, and POS shells were reviewed in the browser at desktop/mobile widths in Arabic RTL and English LTR. Manifest, service worker, online/offline state, private-cache boundary, navigation visibility, and direct denial passed. Actual devices, printer/scanner integration, install/update support, and offline policy remain production/UAT concerns in BLK-003/BLK-004.
- **Title / Purpose / Description:** Build Auth, Admin, Operations, and lightweight POS layouts in one Laravel app with context navigation, locale/direction, responsive behavior, PWA install/update shell and connectivity state.
- **Traceability:** NFR-03–NFR-05, NFR-07; US-032; FLW-AUTH-01, FLW-OFF-01; UI UI-SYS-001–002, UI-OFF-001; AC-NFR-03–05, AC-NFR-07, AC-UI-01–04; SEC-011–013, SEC-031–034.
- **Dependencies / Required Inputs:** TSK-001–002; BLK-003–BLK-004; browsers/devices, PWA/offline decision, context-switch policy.
- **Database Entities:** user/role/scope context and future offline device registry (final table names at implementation).
- **Backend / Livewire / Blade Deliverables:** scoped context resolver; Blade layouts; Livewire navigation/context/PWA state where useful; service worker only for approved shell/static policy.
- **UI / Flux / Alpine / Vite:** Flux Sidebar/Header/Breadcrumb/Dropdown/Badge/Dialog/Toast; Alpine/small TypeScript for connectivity/install/update only; Vite common + minimal POS/PWA entry.
- **Suggested Packages:** PWA capability candidate only after service-worker compatibility/security review; prefer platform APIs for simple install/connectivity.
- **Permissions / Validation / Audit / States / Print:** scope-aware navigation and server policy; validate context switch; audit sensitive scope changes/device enrollment; online/offline/update/unsupported; None.
- **Manual Browser Verification:** Layouts at target widths, keyboard/touch, Arabic RTL/English LTR, direct denial, locale/context, install/update/cache behavior, unsynced-queue protection.
- **Definition of Done:** DoD; no separate frontend/API/SPA; no sensitive response cache; approved device behavior evidenced.

### TSK-004 — Establish the Shared UI Foundation

- **Task ID / Phase / Milestone / Status:** TSK-004; Phase 1; DM 1.1; **Completed for approved local scope**.
- **Closure Audit (2026-08-03):** The UI showcase and current platform screens were visually reviewed for page shell/header, forms, tables, filters, pagination, dialogs, alerts/toasts, badges, loading/empty/error/denied/success/disabled states, audit/timeline patterns, print-base structure, RTL/LTR, responsive behavior, focus/contrast basics, and bounded mobile layout. The health metadata table retains a bounded inner horizontal scroll at 390px as a recorded Low presentation note; no document overflow or Critical/High defect remains.
- **Title / Purpose / Description:** Implement the limited shared visual language and reusable shell/page/table/form/status/timeline/audit/print patterns using Flux UI first.
- **Traceability:** NFR-05, NFR-07; US-032; all flows/screens; UI UI-SYS-001, UI-SYS-005–010; AC-UI-01–05; SEC-006–007, SEC-011, SEC-016, SEC-024.
- **Dependencies / Required Inputs:** TSK-003; approved bilingual font/brand colors if available; Flux version compatibility.
- **Database Entities:** None beyond UI read models; attachment/audit patterns consume their documented entities.
- **Backend / Livewire / Blade Deliverables:** scoped/paginated list conventions, localized validation/error responses; reusable Blade/Livewire page patterns and print base templates.
- **UI / Flux / Alpine / Vite:** Flux form controls, table/pagination, modal/dialog/drawer, toast/alert, badges, breadcrumbs, cards, states; Alpine only minimal interaction; Tailwind semantic tokens/logical directions; Vite shared styles.
- **Suggested Packages:** None for internal design system; Flux UI is primary. Font/package only after license/performance review.
- **Permissions / Validation / Audit / States / Print:** component examples must demonstrate denied/validation/loading/empty/error/success/disabled; no sensitive data; print base thermal/A4/label/PDF.
- **Manual Browser Verification:** Component/pattern matrix across locales/directions/viewports/keyboard/touch/contrast/text expansion/print preview.
- **Definition of Done:** DoD; no duplicate custom Flux components; pattern documentation and examples reflect actual implementation.

### TSK-004B — Implement Persistent UI Customizer and Contextual Tutorial Assistant

- **Task ID / Phase / Milestone / Status:** TSK-004B; Platform shared feature; **In Progress (2026-08-04)**.
- **Purpose:** Add persistent Appearance Customizer and permission-aware, bilingual Contextual Page Guide/Tutorial Assistant without changing business workflows, authorization grants, commercial policy, or unrelated task statuses.
- **Implementation:** Shared `TutorialRegistry`, `UserFlowRegistry`, `PageGuideContext`, `user_ui_preferences`, persistent dashboard/POS controls, bounded drawer/full guide/flow views, and a small explicit-selector guided tour.
- **Initial real coverage:** UI-SYS-001/002/003/004, UI-ADM-002/003/004/005/010/011/012, UI-CAT-001/002/003/004/006/007. Non-existent proposed screens are not fabricated.
- **Definition of Done:** Shared controls, persisted preferences, registered guides/flows, permission/scope filtering, tour behavior, documentation, and real-browser evidence under `artifacts/platform-dashboard-assistant/`; no Critical/High defect; no UAT/production-readiness claim.
- **Traceability:** US-046; FLW-UI-01, FLW-HELP-01, FLW-HELP-02; AC-UI-06–15; `docs/40-contextual-page-guide-specification.md`.

### Cross-cutting local slice — Initial Setup Dashboard / First Launch Configuration

- **Status:** Implemented for Local/Dev scope on 2026-08-06; Arabic-first visual/translation polish verified on 2026-08-07; Production/UAT values and Owner approvals remain open.
- **Purpose:** Present a permission-gated first-launch setup dashboard that derives readiness from authoritative data, provides the safe pending financial-setting input, and links the Owner to existing data-entry screens. The screen now also publishes `UI-ADM-013` contextual help, a six-step Arabic/English interactive tour, and the `FLW-ADM-06` ordered setup workflow.
- **Required steps:** Company identity; active branch/store structure; active supplier-return reason catalog; approved/effective supplier-return numbering and print financial settings; active role assignment for the opening team.
- **Optional step:** Printer profile review. Production device acceptance remains a separate UAT gate.
- **Safety:** No production defaults, reason catalog entries, production approval limits, production users, or approvals are created automatically. The owner-authorized Demo register may contain clearly labeled pending examples; pending/locked-only financial versions do not count as ready, and only an effective, non-expired version linked to an approved `ApprovalRecord` counts.
- **Routes / permissions:** `/dashboard` shows the setup panel when required data is missing; `/initial-setup` is limited to `company_settings.edit`. Existing settings, branches, authorization baseline, and supplier-return settings screens remain the data-entry surfaces.
- **Evidence:** Browser-verified with Local Demo Administrator at `/dashboard`, `/initial-setup`, and `/purchasing/returns/settings`; after explicit owner authorization, Demo company identity is populated as `TOY & JOY - Local Demo / EGP / ج.م`, readiness is `3/5 (60%)`, and four supplier-return financial policy examples are visible as `Awaiting approval`. Existing payment/tax/numbering/printer Demo rows are explicitly labeled; `supplier_return_reasons=0`, `approved_versions=0`, and no `ApprovalRecord` was created for these Demo values. Non-administrative Local Demo Cashier received the existing Access Denied screen; console reported zero JavaScript errors. `DemoSeeder` completed successfully on the local SQLite database after preserving invoice-referenced purchase-order lines.

### TSK-005 — Configure Company, Payment, Tax, Numbering, and Printer Settings

- **Task ID / Phase / Milestone / Status:** TSK-005; Phase 1; DM 1.2; **In Progress — specific local work remains: effective-date fields/overlap validation and configuration print-preview flows are not implemented.**
- **Closure Audit (2026-08-03):** Company, payment/evidence, tax, numbering, printer configuration, update, duplicate, audit, and local TBD behavior were browser-verified. The remaining local gaps are the effective-date form/overlap guard and actual thermal/A4/label configuration preview flows; production tax/payment/numbering/template/printer values remain BLK-008.
- **Title / Purpose / Description:** Deliver company identity, payment methods, effective tax settings, concurrency-safe numbering configurations, printer/templates and customer-policy settings.
- **Traceability:** MD-01, NFR-01–NFR-02, NFR-06; US-001; FLW-ADM-05; UI UI-ADM-002, UI-ADM-006–009; AC-MD-01, AC-NFR-01–02, AC-NFR-06; SEC-017–018, SEC-039.
- **Dependencies / Required Inputs:** Owner-authorized temporary sequencing exception DEC-031 and minimum local audit exception DEC-033; BLK-008 remains Open; company/currency/tax/methods/sequences/printers/templates/policies. DEC-033 authorizes only append-only local audit records for successful TSK-005 settings writes, not approval, attachment, retention/redaction, immutability, or production audit policy. Do not infer unapproved business or production policy.
- **Database Entities:** `companies`, `payment_methods`, `tax_settings`, `document_sequences`, `printer_configurations`, `audit_logs`, `approval_records`.
- **Backend / Livewire / Blade Deliverables:** settings models/migrations/actions/policies/version/effective rules/locked numbering; full-page setting screens and Blade print previews.
- **UI / Flux / Alpine / Vite:** Flux Forms/Tables/Date/Select/Switch/Tabs/Preview/Dialog/Badges; Alpine none except print preview; common Vite assets.
- **Suggested Packages:** Barcode/PDF/print capability not selected here; evaluate only when actual outputs require it.
- **Permissions / Validation / Audit / States / Print:** Administrator manage, Reviewer view/export; unique/overlap/pattern/dependency validation; all changes/audits; active/inactive/effective; preview/test prints.
- **Manual Browser Verification:** Duplicate/effective/unsafe deactivate and concurrent sequence allocation; unauthorized access; tax/method/evidence settings; thermal/A4/label preview RTL/LTR.
- **Definition of Done:** DoD and DM 1.2 business inputs approved; past documents unaffected.

### TSK-006 — Configure Branches, Stores, and Selling-Store Mapping

- **Task ID / Phase / Milestone / Status:** TSK-006; Phase 1; DM 1.2; **Completed for approved local scope**.
- **Closure Audit (2026-08-03):** Branch/store lifecycle, duplicate/dependency guards, effective mapping/history, audit, direct denial, scope-filtered navigation, RTL/LTR, and responsive behavior were reviewed. The selling-store picker and action now enforce same-branch mapping. Production branch/store data and assignments remain BLK-006.
- **Title / Purpose / Description:** Deliver branch/store masters, types/status, branch selling-store assignment/history, and controlled manager override context.
- **Traceability:** MD-01, INV-02, NFR-01, NFR-03; US-001, US-013; FLW-ADM-01–02; UI UI-ADM-003–004; AC-MD-01, AC-INV-02; SEC-011–012, SEC-015, SEC-039.
- **Dependencies / Required Inputs:** TSK-005; BLK-006; approved lists/types/mappings/overrides.
- **Database Entities:** `branches`, `stores`, `branch_selling_stores`, `approval_records`, `audit_logs`.
- **Backend / Livewire / Blade Deliverables:** master/state/dependency rules, scope policies, effective mapping and override action; branch/store list/forms/detail.
- **UI / Flux / Alpine / Vite:** Flux Tables/Search/Filters/Pagination/Forms/Combobox/Timeline/Dialog/Badges; Alpine none; common assets.
- **Suggested Packages:** None; Laravel/Flux native patterns.
- **Permissions / Validation / Audit / States / Print:** Administrator manage; branch/warehouse roles scoped view; unique codes, safe deactivate, non-overlap mapping; mapping/override audit; active/inactive; optional register print/export.
- **Manual Browser Verification:** Create/edit/deactivate, duplicate/dependency, map one selling store, cross-scope denial, direct request, override reason/approval/audit, responsive RTL/LTR.
- **Definition of Done:** DoD; approved structure loaded and mapping history trustworthy.

### TSK-007 — Configure Cash Drawer Masters and Assignments

- **Task ID / Phase / Milestone / Status:** TSK-007; Phase 1; DM 1.2; **Completed for approved local scope**.
- **Closure Audit (2026-08-03):** Drawer list/create/update/dependency validation, duplicate handling, branch/store consistency, cross-branch prevention, empty/error/success states, server authorization, audit, RTL/LTR, and responsive behavior were reviewed. Shift dependency is explicitly safe/TBD because shift entities do not yet exist; production drawer allocation remains BLK-006.

- **Title / Purpose / Description:** Create branch/store-scoped drawer masters and safe assignment/status lifecycle used later by shifts.
- **Traceability:** MD-01, CSH-01, NFR-01, NFR-03; US-001, US-024; FLW-ADM-03; UI UI-ADM-005; AC-MD-01, AC-CSH-01; SEC-011–012, SEC-017, SEC-019, SEC-039.
- **Dependencies / Required Inputs:** TSK-006; approved drawer allocation/codes/status policy.
- **Database Entities:** `cash_drawers`, `branches`, `stores`, `audit_logs`.
- **Backend / Livewire / Blade Deliverables:** drawer model/policy/dependency guards; list/create/edit/deactivate screen.
- **UI / Flux / Alpine / Vite:** Flux Table/Filters/Search/Pagination/Form/Select/Badge/Dialog; Alpine none; common assets.
- **Suggested Packages:** None.
- **Permissions / Validation / Audit / States / Print:** Administrator manage; Branch Manager proposed scope; unique code, consistent assignment, no reassign/retire with active shift; changes audited; optional assignment register.
- **Manual Browser Verification:** Duplicate, cross-branch assignment, dependency/active-shift guard, permission/direct URL, empty/error/success, responsive RTL/LTR.
- **Definition of Done:** DoD; drawer data ready for DM 3.3 without inventing opening balances.

### TSK-008 — Implement Users, Roles, Permissions, and Scopes

- **Task ID / Phase / Milestone / Status:** TSK-008; Phase 1; DM 1.3; **Completed (2026-08-03; DEC-038 canonical matrix seeded; all current application surfaces are server-enforced, scope-filtered, tested, and visually verified).**
- **Title / Purpose / Description:** Implement approved role/permission matrix with branch/store/action/document/field/approval/override and own-record scopes.
- **Traceability:** MD-01, CUS-02, NFR-03; US-001, US-032; FLW-ADM-04; UI UI-ADM-010–012, UI-SYS-009; AC-MD-01, AC-CUS-02, AC-NFR-03; SEC-011–016, SEC-039.
- **Dependencies / Required Inputs:** DEC-038 canonical matrix. Future module-specific enforcement remains a dependency of its corresponding implementation task, not a blocker to this completed current-scope authorization foundation.
- **Database Entities:** `users`, `roles`, `permissions`, role/user/permission pivots, `user_branch_scopes`, `user_store_scopes`, `audit_logs`.
- **Backend / Livewire / Blade Deliverables:** policies/gates/scoped queries and grant/revoke actions; user/role/permission screens/matrix.
- **UI / Flux / Alpine / Vite:** Flux Tables/Matrix Controls/Filters/Combobox/Tabs/Drawer/Dialog/Badges; Alpine only matrix affordance if Flux/Livewire insufficient; common assets.
- **Suggested Packages:** Mature Laravel permission package candidate after version/maintenance/fit review; do not build a permission engine or install yet.
- **Permissions / Validation / Audit / States / Print:** Administrator manage, Reviewer view; last-admin/self-lockout, invalid grant/scope, alias and limit validation; every grant/revoke audit; matrix PDF/export by right.
- **Manual Browser Verification:** Every role allowed/denied navigation/direct routes/actions/scopes/fields/exports; Cashier/Party Wallet and Party Manager/Product Wallet isolation; Counter approval denial.
- **Definition of Done:** Completed for current scope: canonical roles/permission catalog seeded; current routes, Livewire actions, navigation, and scoped queries enforced; role/scope changes audited; focused automated and browser verification passed; no `P`/`R` permission silently granted. See `docs/16-authorization-traceability.md` for deferred modules.

### TSK-009 — Implement Approval, Audit, Attachment, and Immutability Controls

- **Task ID / Phase / Milestone / Status:** TSK-009; Phase 1; DM 1.4; **In Progress (new implementation paused during the TSK-001–TSK-008 closure audit; no new TSK-009 implementation occurred in this session).**
- **Title / Purpose / Description:** Deliver append-only approvals/audit, protected attachments, per-document state-transition conventions, immutable approved records, source corrections, and number allocation integration.
- **Traceability:** NFR-01–NFR-04, NFR-06; US-001, US-032; FLW-ADM-05, FLW-RPT-03; UI UI-SYS-005–006, UI-AUD-001; AC-NFR-01–04, AC-NFR-06; SEC-015, SEC-017–020, SEC-022–024, SEC-027.
- **Dependencies / Required Inputs:** TSK-008; `docs/17-approval-policy.md`, `docs/18-attachment-media-policy.md`, `docs/19-audit-immutability-policy.md`, `docs/30-platform-operations-specification.md`, `docs/35-document-state-machines.md`, `docs/36-module-data-contracts.md`, `docs/37-ui-screen-specifications.md`, and `docs/38-print-export-specification.md`. Production storage, legal wording, infrastructure, and final numeric limits remain configurable or pending approval.
- **Database Entities:** `approval_records`, `audit_logs`, `attachments`, `document_sequences`; source/reference fields on future documents.
- **Backend / Livewire / Blade Deliverables:** focused reusable approval/audit/attachment actions/policies, transition/immutable guards, authorized file delivery; inbox/audit/detail patterns.
- **UI / Flux / Alpine / Vite:** Flux Table/Filters/Pagination/Drawer/Diff/Timeline/File Upload/Dialog/Toast/Badges; Alpine none; file preview/print assets only as needed.
- **Suggested Packages:** Mature audit/activity and media capability candidates after compatibility/security review; avoid overlapping packages/custom engines.
- **Permissions / Validation / Audit / States / Print:** explicit submit/approve/reject/override/reverse/cancel/delete/export; MIME/signature/size/hash/purpose; required self-audit; append-only states; audit/approval exports and source prints.
- **Manual Browser Verification:** Stale/unauthorized transition, approval separation, original edit/delete denial, referenced correction, unsafe/cross-scope file, before/after redaction, duplicate numbering/action.
- **Definition of Done:** DoD and Phase 1 gate; critical actions record required context and originals remain preserved.

## Phase 2 — Catalog, Purchasing, Pricing, and Inventory

### TSK-010 — Build Product, Category, Brand, Code, and Barcode Masters

- **Task ID / Phase / Milestone / Status:** TSK-010; Phase 2; DM 2.1; **Completed for approved local scope (2026-08-04).** Production catalog inputs, UAT, and milestone/phase gates remain open.
- **Title / Purpose / Description:** Create stable product identity, category hierarchy, brands, supplier/local barcode allocation and exact search behavior.
- **Traceability:** MD-02–MD-03, MD-05; US-002; FLW-CAT-01; UI UI-CAT-001–003, UI-CAT-006–007; AC-MD-02–03, AC-MD-05; SEC-006, SEC-011–012, SEC-019–021.
- **Dependencies / Required Inputs:** Phase 1 gate; BLK-009 mitigated by `docs/23-product-barcode-policy.md`; production hierarchy, real master data, supplier-code allocation, and final attributes remain configurable or pending.
- **Database Entities:** Local TSK-010 implements `products`, `categories`, `brands`, `barcodes`, and `barcode_sequences`. Full `suppliers`/`product_suppliers` master/history remains deferred to TSK-013; no supplier master was fabricated.
- **Backend / Livewire / Blade Deliverables:** models/migrations/relations/unique and hierarchy rules/barcode action; paginated masters and product list/form foundation.
- **UI / Flux / Alpine / Vite:** Flux Tables/Search/Filters/Pagination/Tree/Forms/Combobox/Badge/Dialog; Alpine scanner field capture only if needed; common assets.
- **Suggested Packages:** Mature barcode generation candidate only for rendering, not identity allocation; inspect compatibility later.
- **Permissions / Validation / Audit / States / Print:** catalog manage and cost-field scope; unique immutable item code/barcode, local format/serial concurrency, hierarchy cycle; audit identity/preference; label print blocked until pricing.
- **Manual Browser Verification:** Create/search exact code/barcode/name, duplicate/concurrent allocation, supplier change stability, attributes no variants, hierarchy cycle, unauthorized fields, RTL/LTR.
- **Definition of Done:** DoD; stable identity and search demonstrable with no stock or price side effect.
- **Current local implementation:** Categories, brands, products, barcodes, and local barcode sequences are migrated with unique/FK/index constraints. Category cycle/dependency guards, immutable normalized item codes, exact-priority bounded search, supplier/local barcode actions, server authorization, transaction-bound audit events, responsive Flux screens, and catalog-gated routes/navigation are implemented. Local System Administrator browser evidence covers the implemented screens and mutation paths. TSK-010 remains complete for its identity scope; product-card fields, types, protected media, and detail/full edit behavior are implemented only under the active TSK-011 scope.
- **Local closure evidence:** DEC-038-approved catalog `View (A)` is seeded for System Administrator, Cashier (limited view), Purchasing Officer, Warehouse Manager, Pricing Officer, and Accountant/Reviewer. Catalog `P`/`R` capabilities remain ungranted. Stable browser verification passed cashier view-only/forged-create denial, reviewer view, branch-manager and no-access direct-route 403 denial, supplier duplicate rejection, allocation-key replay idempotency, category self-parent rejection, descendant-cycle rejection, and database integrity checks. **TSK-010 is closed for approved local scope.** TSK-011 and TSK-012 are also closed for approved local scope; TSK-013 is the next active task and has an implemented local slice with production/UAT boundaries still open.

### TSK-011 — Complete Product Cards, Types, Attributes, and Media

- **Task ID / Phase / Milestone / Status:** TSK-011; Phase 2; DM 2.1; **Completed for approved local scope**.
- **Title / Purpose / Description:** Deliver full bilingual product card, standard/composite/service type behavior, reportable attributes, main + four additional protected images and lifecycle.
- **Traceability:** MD-04–MD-05, PRC-02; US-002, US-005; FLW-CAT-01; UI UI-CAT-001–003; AC-MD-04–05, AC-PRC-02; SEC-006–010, SEC-016–017, SEC-022–024.
- **Dependencies / Required Inputs:** TSK-010; `docs/23-product-barcode-policy.md`; production types, composition data, UOM, required fields, images, and retention remain configurable or pending.
- **Database Entities:** `products`, `product_images`, `attachments`, categories/brands/supplier links; composition lines only if approved.
- **Backend / Livewire / Blade Deliverables:** type-specific validation/actions, protected media, searchable/reportable fields; product detail and full form.
- **UI / Flux / Alpine / Vite:** Flux Form Sections/Inputs/Textareas/Combobox/Radio/Upload/Tabs/Cards/Image/Timeline; Alpine image ordering only if necessary; Vite image styles.
- **Suggested Packages:** Approved media capability candidate after security/version review; do not build a media library or install now.
- **Permissions / Validation / Audit / States / Print:** product/type/media manage, cost scope; 1 main + max 4 extra, safe files, post-use type guard; changes audited; product card print optional.
- **Manual Browser Verification:** All fields/types, invalid combinations, file attacks/limits/access, image order/count, search/report attributes, responsive RTL/LTR.
- **Definition of Done:** DoD; all PRD fields usable and no implicit variant balances.
- **Closure review (2026-08-04):** TSK-011 is **Completed for approved local scope**. Browser verification passed the approved card/type/attribute/media behavior, immutable item code, stale-update denial, protected media limits and authorization, cost denial, RTL/LTR/mobile layout, and the TSK-010 regression boundary. Oversized-file UX now renders a localized inline message before upload for the approved 8 MB application limit and renders a localized server-boundary message when the local PHP upload limit rejects the request. The PHP runtime boundary is documented; it does not weaken the application policy. Composite component behavior remains explicitly deferred by the insufficient approved contract. Production values, UAT, automated-test coverage, and later tasks remain open.

### TSK-012 — Implement Staged Product Excel Import

- **Task ID / Phase / Milestone / Status:** TSK-012; Phase 2; DM 2.1; **Completed for approved local scope (2026-08-04)**.
- **Title / Purpose / Description:** Stage upload, column mapping, Create Only/Update Existing, full validation, review/approval, valid-row write and rejected-row error download.
- **Traceability:** PRC-01, NFR-04–NFR-05; US-004; FLW-CAT-02; UI UI-CAT-004–005; AC-PRC-01, AC-NFR-04–05; SEC-006, SEC-020, SEC-022–026.
- **Dependencies / Required Inputs:** TSK-010–011; `docs/23-product-barcode-policy.md`; production import template, row limits, and final reference rules remain configurable or pending.
- **Database Entities:** import batch/row structures to name during implementation, `attachments`, `products`, `barcodes`, `categories`, `audit_logs`, `approval_records`.
- **Backend / Livewire / Blade Deliverables:** chunked staged parser/validator/idempotent approve action/error artifact; full-page stepper and error list.
- **UI / Flux / Alpine / Vite:** Flux File Upload/Tabs or Stepper/Combobox/Table/Pagination/Progress/Dialog/Download/Toast; Alpine none; Vite common.
- **Suggested Packages:** One mature Excel import/export package candidate after Laravel/version/security/formula review.
- **Permissions / Validation / Audit / States / Print:** Import/Create/Update/Approve/Export Error; file/signature/formula/reference/duplicate/mode; batch states audited; safe Excel error download.
- **Manual Browser Verification:** Valid/mixed-invalid/create-only/update/existing/duplicate/formula/large bounded/cancel/retry/unauthorized download; confirm invalid rows never write.
- **Definition of Done:** DoD and exact acceptance statement evidenced.

### TSK-013 — Implement Supplier Master and Product-Supplier History

- **Task ID / Phase / Milestone / Status:** TSK-013; Phase 2; DM 2.1; **Implemented for approved local scope under explicit owner continuation; role/UAT acceptance and BLK-010 remain open.**
- **Title / Purpose / Description:** Maintain supplier contacts/status/terms, product preference, actual/history views, returns and last-price links without rewriting historical supplier.
- **Traceability:** PUR-01–PUR-02, NFR-01–NFR-02; US-009; FLW-PUR-01–03; UI UI-CAT-008; AC-PUR-01–02; SEC-006, SEC-011–012, SEC-017, SEC-027.
- **Dependencies / Required Inputs:** TSK-010; `docs/23-product-barcode-policy.md` for product-supplier conventions; BLK-010 supplier data, identifiers, terms, and preference authority remain open.
- **Database Entities:** `suppliers`, `product_suppliers`, future purchase sources, `audit_logs`.
- **Backend / Livewire / Blade Deliverables:** Supplier master/relation models and migration, transaction-bound save/status/preference actions with stale-version protection, audit events, and supplier list/detail/form/history UI. Purchase history remains an honest empty state until TSK-015.
- **UI / Flux / Alpine / Vite:** Responsive bilingual Livewire/Flux supplier table, search/status filters, pagination, create/edit modal, detail drawer, linked products, and RTL/LTR shell integration.
- **Suggested Packages:** None.
- **Permissions / Validation / Audit / States / Print:** `suppliers.view`, `suppliers.create`, `suppliers.edit`, and `suppliers.logical_delete` registered; view assigned to system administrator/purchasing/warehouse/reviewer, mutations to system administrator/purchasing. Unique code, status/terms validation, stale-version protection, preferred-supplier guard, and master-data audit events implemented. No print/export/approval workflow fabricated.
- **Manual Browser Verification:** Demo Admin `/catalog/suppliers` rendered with supplier header/table/add action/modal and six guide targets; mobile `390x844` had no horizontal overflow. Role-specific view-only/scoped/no-access, mutation, duplicate/deactivate/preference, and full RTL/LTR acceptance remain pending.
- **Definition of Done:** Local supplier data slice implemented and statically/browser smoke verified; full task remains open for role matrix, production supplier inputs, and purchase-cycle integration.

### TSK-014 — Implement Purchase Orders

- **Task ID / Phase / Milestone / Status:** TSK-014; Phase 2; DM 2.2; **Completed for approved local scope (local implementation and authenticated manual browser verification completed for approved local/demo scope; definition-only Partially Received/Received remain TSK-015; true 390x844 mobile evidence pending; production/UAT gates open)**.
- **Title / Purpose / Description:** Deliver purchase-order line entry and Draft/Submitted/Partially Received/Received/Cancelled/Closed state machine with receipt links.
- **Traceability:** PUR-03, NFR-01–NFR-03, NFR-06; US-010; FLW-PUR-01; UI UI-PUR-001; AC-PUR-03; SEC-011–012, SEC-015, SEC-017–020, SEC-027.
- **Dependencies / Required Inputs:** Phase 2 DM 2.1 complete; supplier/store/product and PO authorization/terms.
- **Database Entities:** `purchase_orders`, `purchase_order_lines`, `document_sequences`, `approval_records`, `audit_logs`.
- **Backend / Livewire / Blade Deliverables:** state/action/number/policy/line calculations; list/full-page editor/detail/timeline.
- **UI / Flux / Alpine / Vite:** Flux Table/Filters/Search/Pagination/Form/Combobox/Line Editor/Date/Badges/Timeline/Dialog; Alpine none; print assets.
- **Suggested Packages:** PDF capability evaluated later with other document outputs; no package required for core flow.
- **Permissions / Validation / Audit / States / Print:** Create/Edit Draft/Submit/Cancel/Close/Print; supplier/product/qty/state/over-receipt; every line/state/reason audited; PO A4/PDF.
- **Manual Browser Verification:** Completed for approved local/demo scope (`DEMO_AUTH=true`): list/detail rendering, draft creation (3 x 12.50 = 37.50), submit transition, approve transition by separate reviewer, self-approval backend denial, approved edit denial, cancellation reason validation, cancellation audit logging, print A4 rendering, reviewer branch scope and print permission, no-access 403 direct denial, Arabic RTL / English LTR visual checks at 1280px viewport (no console errors/overlap, zero PO approval stock/invoice side effects). Definition-only `Partially Received` and `Received` states remain TSK-015. True 390x844 mobile evidence remains pending because CUA Firefox capture returned 0x0 and the available Browser Use session has no viewport-resize capability. Diagnostics passed: locale parity 1035/1035, PHPStan 0 errors, Pint/PHP lint pass, Blade cache pass, Vite build pass (optional fontaine warning), git diff check pass. No PHPUnit/Pest or automated browser tests claimed.
- **Definition of Done:** Completed for approved local/demo scope; PO has zero stock/invoice/cost effect; definition-only Partially Received/Received remain TSK-015; production/UAT gates remain open.

### TSK-015 — Implement Purchase Invoices, Import, Receipt, and Weighted-Average Cost

- **Task ID / Phase / Milestone / Status:** TSK-015; Phase 2; DM 2.2; **Completed for approved local/dev scope — DEC-050 adopted all 83 documented policy inputs; fresh SQLite/seed, invoice CRUD/calculation, staged import validation/review, lifecycle, posting/WAC, print/export, and permission boundaries verified; production master data/UAT/release gates remain open**.
- **Title / Purpose / Description:** Manual/Excel purchase invoice, selected-store receipt, optional tax/discount, atomic approval, stock movement, weighted-average cost and pricing-review signal without sale-price change.
- **Traceability:** PRC-03, PUR-04–PUR-05, NFR-01–NFR-02, NFR-06; US-011; FLW-PUR-02; UI UI-PUR-002, UI-INV-002–003; AC-PRC-03, AC-PUR-04–05; SEC-015, SEC-017–021, SEC-022–026.
- **Dependencies / Required Inputs:** TSK-014; cost/rounding/tax/discount/import/receipt approvals and opening-stock approach.
- **Database Entities:** `purchase_invoices`, `purchase_invoice_lines`, `purchase_orders`, `stock_movements`, `stock_balances`, `products`, `attachments`, `approval_records`, `document_sequences`.
- **Backend / Livewire / Blade Deliverables:** manual/staged import, calculation and locked approval action, movement/cost/audit, immutable source; invoice editor/detail/approval/result.
- **UI / Flux / Alpine / Vite:** Flux Table/Form/Upload/Line Editor/Combobox/Summary/Timeline/Dialog/Progress/Badges; Alpine none; print assets.
- **Suggested Packages:** Reuse approved Excel capability; PDF candidate; no duplicate import engine.
- **Permissions / Validation / Audit / States / Print:** Entry/Import/Submit/Approve/Reverse/Cost View; duplicate supplier ref, store, qty/cost/tax/discount, idempotency/concurrency; full audit; invoice/receipt A4.
- **Manual Browser Verification:** Manual/import valid/invalid, PO partial/full, duplicate, formula including zero stock edge, concurrent receipt, movement/balance reconciliation, sale price unchanged, permission/print.
- **Definition of Done:** DoD and DM 2.2 purchase receipt criteria satisfied with reproducible calculation evidence.

### TSK-016 — Implement Supplier Returns

- **Task ID / Phase / Milestone / Status:** TSK-016; Phase 2; DM 2.2; **Local/Dev Complete — DEC-052 implemented; Production/UAT and Owner master-data/financial approvals remain open.**
- **Title / Purpose / Description:** Create an approved supplier return whose every Phase 1 line references an approved purchase-invoice line, using the original source-line cost/history and exact stock reduction.
- **Traceability:** PUR-06, NFR-01–NFR-02, NFR-06; US-012; FLW-PUR-03; UI UI-PUR-003; AC-PUR-06; SEC-011–012, SEC-015, SEC-017–021, SEC-027.
- **Dependencies / Required Inputs:** TSK-015; DEC-052 closes original-line cost, no fallback, and no-reference rejection. Reason catalog rows and numeric approval limits remain configurable/owner inputs but do not block the schema or guarded local implementation.
- **Database Entities:** `purchase_returns`, `purchase_return_lines`, `purchase_invoices`, `stock_movements`, `stock_balances`, `approval_records`, `document_sequences`.
- **Backend / Livewire / Blade Deliverables:** eligibility/source/cost/state/locked posting and reference correction; list/editor/detail/timeline; cancel/reject/reverse; owner-configurable reason catalog and versioned print/number settings.
- **UI / Flux / Alpine / Vite:** Flux Table/Filters/Search/Pagination/Form/Combobox/Line Editor/Summary/Timeline/Dialog; Alpine none; print assets.
- **Suggested Packages:** Reuse document PDF capability if approved.
- **Permissions / Validation / Audit / States / Print:** Create/Submit/Approve/Reject/Cancel/Reverse/Print; original/supplier/store/available qty/cost/reason; all events/movements audited; supplier return A4/PDF.
- **Manual Browser Verification:** Local Demo Auth verified empty-state/settings/detail/print pages with 0 JS errors; end-to-end lifecycle and balance/idempotency verified by transactional Tinker smoke. Production/UAT hardware, real reasons, financial limits, scopes, and printer approval remain pending.
- **Definition of Done:** DoD; balance and cost history reconcile.

### TSK-017 — Implement Price Proposals, Version Approval, and Open-Price Policy

- **Task ID / Phase / Milestone / Status:** TSK-017; Phase 2; DM 2.3; **Local/Dev slice complete — Production/UAT pending; no Production claim**.
- **Title / Purpose / Description:** Deliver product/import/purchase-context proposals, immutable approval/version/effective price, one active location price, unpriced pending and configured open-price bounds.
- **Traceability:** PRC-03–PRC-05, PRC-07–PRC-08, NFR-01–NFR-02; US-006–008; FLW-CAT-03–04, FLW-POS-01; UI UI-PRC-001–002, UI-POS-001; AC-PRC-03–05, AC-PRC-07–08; SEC-011–012, SEC-015–020, SEC-027.
- **Dependencies / Required Inputs:** Phase 2 DM 2.2 complete; BLK-011 mitigated by `docs/24-pricing-policy.md`; final authority, numeric limits, effective timing, branch exceptions, and production print values remain configurable or pending.
- **Database Entities:** `price_lists`, `price_versions`, `price_lines`, `approval_records`, `audit_logs`, `stock_balances` context.
- **Backend / Livewire / Blade Deliverables:** proposal/import-as-Draft, state/locked atomic activation, lookup and open-price validation; pricing list/editor/history diff/approval screens are implemented for Local/Dev.
- **UI / Flux / Alpine / Vite:** Flux Tables/Filters/Search/Pagination/Form/Upload/Diff/Timeline/Badges/Dialog; Alpine none; common/print assets.
- **Suggested Packages:** Reuse Excel package if approved; no workflow engine.
- **Permissions / Validation / Audit / States / Print:** Propose/Edit/Import/Submit/Approve/Reject/Branch Exception/Open Price; one active, amount/effective/range/reason/stale; full audit; approval summary print.
- **Manual Browser Verification:** Local Demo verified list/create/import-as-Draft/history comparison, Draft → Submitted → Approved, superseding, no-access denial, explicit branch-exception option/permission boundary and missing-reason denial, unpriced-product visibility, Arabic RTL/English LTR, no page overflow, and zero browser console errors. Backend smoke verified effective resolution and open-price permission boundary. Production branch policy/limits, full bounds matrix, POS sale/unpriced enforcement integration, concurrent approval stress, cost-change isolation, mobile, production authority, UAT, and label/printer acceptance remain pending.
- **Definition of Done:** DoD; price history and future lookup deterministic.

### TSK-018 — Implement Location Barcode Label Queues and Printing

- **Task ID / Phase / Milestone / Status:** TSK-018; Phase 2; DM 2.3; **Local/Dev Dummy-data slice implemented and browser-verified 2026-08-07; actual queue/print delivery and Production/UAT pending**.
- **Title / Purpose / Description:** Generate one pending label per remaining unit/location after approved price and deliver selected-printer initial/reprint tracking.
- **Traceability:** PRC-06–PRC-07, NFR-01; US-007; FLW-CAT-05; UI UI-PRC-003; AC-PRC-06–07; SEC-011–012, SEC-015, SEC-020–024, SEC-038.
- **Dependencies / Required Inputs:** TSK-017; existing stock balance/printer configuration contracts; `docs/24-pricing-policy.md`; final label/printer/paper, branch exception, quantity/reprint, and device values remain configurable or pending.
- **Database Entities:** `label_queues`, `label_print_events`, `price_versions`, `price_lines`, `stock_balances`, `printer_configurations`.
- **Backend / Livewire / Blade Deliverables:** Demo-only idempotent queue/print-event schema and seeders, approved-price/stock/printer linkage, guarded `/pricing/labels` queue table, explicit Demo blockers, and disabled print/reprint/generation actions are implemented; real hardware execution and Production label contracts remain deferred.
- **UI / Flux / Alpine / Vite:** Flux Table/Filters/Search/Pagination/Printer Select/Progress/Dialog/Badges/Toast; Alpine/browser print/local integration only if approved; print-specific Vite CSS.
- **Suggested Packages:** Mature barcode renderer only after compatibility/output review; no custom generator.
- **Permissions / Validation / Audit / States / Print:** View/Print/Reprint; location/approved price/quantity/printer/reason; Pending/Partial/Completed/Failed; every event audited; label output.
- **Manual Browser Verification:** Quantity/location/exception, unpriced block, partial/retry/reprint, cross-store denial, scanner readability, Arabic/English label, target printer.
- **Definition of Done:** DoD and DM 2.3 label criteria on approved hardware.

### TSK-019 — Implement Inventory Ledger, Balances, Availability, and Stock Cards

- **Task ID / Phase / Milestone / Status:** TSK-019; Phase 2; DM 2.4; **Completed for Local/Demo; Production/UAT pending**.
- **Title / Purpose / Description:** Establish append-only movements, locked/materialized balances and scoped on-hand/available/in-transit/reserved/reorder/history views.
- **Traceability:** INV-01–INV-02, NFR-01–NFR-03, NFR-05; US-013; FLW-INV-01; UI UI-INV-001–003; AC-INV-01–02, AC-NFR-05; SEC-011–012, SEC-016–021, SEC-027, SEC-037.
- **Dependencies / Required Inputs:** Phase 2 DM 2.3 complete; `docs/25-inventory-exception-policy.md`; opening inventory approach, balance/reservation/reorder formulas, and field visibility remain configurable or pending.
- **Database Entities:** `stock_movements`, `stock_balances`, `products`, `stores`, `branch_selling_stores`.
- **Backend / Livewire / Blade Deliverables:** append-only posting/reconciliation service, visible-store-scoped indexed queries, local opening movements, WAC/availability calculation, and `/inventory` overview/ledger surface. Product stock-card and movement export/print acceptance remain production follow-up.
- **UI / Flux / Alpine / Vite:** Flux Cards/Tables/Filters/Search/Pagination/Badges/Tabs/Timeline/Export; Alpine none; common assets.
- **Suggested Packages:** None; no generic inventory engine or repository layer.
- **Permissions / Validation / Audit / States / Print:** scoped View/Export/Cost; no direct edit; field and bounded-query validation; movement append audit; reports/export/stock card print.
- **Manual Browser Verification:** Reconcile sources/balances, all quantities, reorder, other-branch informational search, cost/cross-scope denial, high volume/pagination/filter/sort, RTL/LTR.
- **Definition of Done:** DoD; no critical ledger/balance discrepancy.

### TSK-020 — Implement Stateful Stock Transfers and Difference Review

- **Task ID / Phase / Milestone / Status:** TSK-020; Phase 2; DM 2.4; **Completed for Local/Demo; Production/UAT pending**.
- **Title / Purpose / Description:** Deliver transfer request/approval/dispatch/in-transit/partial/full receipt and shortage/damage/refusal review with exact movements.
- **Traceability:** INV-03, NFR-01–NFR-02, NFR-06; US-014; FLW-INV-02; UI UI-INV-004–007; AC-INV-03; SEC-011–012, SEC-015, SEC-017–021, SEC-027.
- **Dependencies / Required Inputs:** TSK-019; BLK-012 mitigated by `docs/25-inventory-exception-policy.md`; final state/role separation, difference disposition, reason catalog, and limits remain configurable or pending.
- **Database Entities:** `stock_transfers`, `transfer_lines`, `stock_movements`, `stock_balances`, `approval_records`, `document_sequences`, attachments if evidence.
- **Backend / Livewire / Blade Deliverables:** state actions/locks/idempotency/movements/differences; every transfer line is received/reconciled atomically; local list/dispatch/receipt/difference-review surface with submitted → approved → in-transit → received/difference-review flow.
- **UI / Flux / Alpine / Vite:** Flux Table/Form/Line Editor/Scan Input/Filters/Timeline/Badges/Dialog/Upload; Alpine scanner only if needed; print styles.
- **Suggested Packages:** Reuse approved barcode/upload/PDF capabilities only.
- **Permissions / Validation / Audit / States / Print:** source/destination Create/Approve/Dispatch/Receive/Difference/Cancel; stock/location/qty/state/replay; every transition/quantity/reason; transfer/dispatch/receipt/difference prints.
- **Manual Browser Verification:** Full/partial/damage/short/refusal/cancel, invalid jumps, double action, concurrent stock, cross-scope, movement reconciliation, print/scanner/RTL/LTR.
- **Definition of Done:** DoD; source+transit+destination and differences reconcile.

### TSK-021 — Implement Entries, Exits, Adjustments, and Stock Policies

- **Task ID / Phase / Milestone / Status:** TSK-021; Phase 2; DM 2.4; **Completed for Local/Demo; Production/UAT pending**.
- **Title / Purpose / Description:** Deliver reasoned approved inventory entry/exit/exchange/adjustment with fractional and default negative-stock controls/override.
- **Traceability:** INV-04–INV-06, NFR-01–NFR-02; US-015; FLW-INV-03–05; UI UI-INV-011; AC-INV-04–06; SEC-006, SEC-011–012, SEC-015, SEC-017–021, SEC-027.
- **Dependencies / Required Inputs:** TSK-019; BLK-012 mitigated by `docs/25-inventory-exception-policy.md`; final reasons, limits, negative/fractional, approval, and disposition values remain configurable or pending.
- **Database Entities:** `inventory_adjustments`, `inventory_adjustment_lines`, `stock_movements`, `stock_balances`, `products`, `stores`, `approval_records`, `document_sequences`.
- **Backend / Livewire / Blade Deliverables:** typed draft/submit/approve actions, locks, append-only movements, default negative-stock block, optional authorized override field, and local list/detail state surface. Reversal/disposition production policy remains open.
- **UI / Flux / Alpine / Vite:** Flux Table/Filters/Form/Line Editor/Combobox/Numeric/Summary/Timeline/Dialog/Badges; Alpine none; print assets.
- **Suggested Packages:** None beyond shared document output.
- **Permissions / Validation / Audit / States / Print:** Create/Submit/Approve/Reverse/Override; reason/store/qty/fraction/negative/source; full before-after/override audit; adjustment print.
- **Manual Browser Verification:** Every type/direction, missing reason, whole/fractional, negative default/in-authority/out-of-authority, concurrency, terminal edit/reversal, cross-store/print.
- **Definition of Done:** DoD; exact ledger effects and no direct balance UI.

### TSK-022 — Implement Full/Partial Stock Counts and Reconciliation

- **Task ID / Phase / Milestone / Status:** TSK-022; Phase 2; DM 2.4; **Completed for Local/Demo; Production/UAT pending**.
- **Title / Purpose / Description:** Plan full/partial counts, capture reference, scan/manual/recount while selling continues, reconcile movements, review uncounted items and create approved adjustments.
- **Traceability:** INV-07–INV-09, NFR-01–NFR-03; US-016; FLW-INV-06–07; UI UI-INV-008–010; AC-INV-07–09; SEC-011–012, SEC-015, SEC-017, SEC-019–021, SEC-027.
- **Dependencies / Required Inputs:** TSK-019, TSK-021; `docs/25-inventory-exception-policy.md`; final count scopes, recount, assignment, approval, and uncounted disposition values remain configurable or pending.
- **Database Entities:** `stock_counts`, `count_lines`, `stock_movements`, `stock_balances`, `inventory_adjustments`, `approval_records`, `document_sequences`.
- **Backend / Livewire / Blade Deliverables:** reference snapshot and movement-window calculation, state/separation/locked reconciliation, uncounted preservation, approved count adjustment posting, and local count submit/reconcile surface.
- **UI / Flux / Alpine / Vite:** Flux Table/Filters/Search/Pagination/Scan Input/Numeric/Progress/Diff/Timeline/Dialog/Badges; Alpine scanner/shortcuts only; print styles.
- **Suggested Packages:** Reuse barcode capability; no generic count/workflow package.
- **Permissions / Validation / Audit / States / Print:** Counter plan/input/submit; Manager reconcile/approve; scope/duplicate/qty/stale movement/uncounted; every edit/recount/approval; count/discrepancy/A4.
- **Manual Browser Verification:** Full/partial/category/supplier/store, scan/manual/repeat, sales/movements during count, formula, counter approval denial, uncounted never zero, concurrent reconcile, print/RTL/LTR.
- **Definition of Done:** DoD and Phase 2 gate; ledger/balance/count adjustments reconcile with no critical discrepancy.

## Phase 3 — POS, Payments, Cash Drawers, and Shifts

### TSK-023 — Implement Dedicated POS Checkout and Suspended Sales

- **Task ID / Phase / Milestone / Status:** TSK-023; Phase 3; DM 3.1; **PARTIAL_IMPLEMENTATION — cart, barcode/product resolution, effective pricing, suspend/resume, idempotency, stock posting, numbering, and audit are real. Since DEC-066 a sale is only approved once captured tender equals the payable amount, so checkout now requires a payment. Receipt/gift-receipt rendering against the new figures remains open.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Build fast barcode/name/code POS with assigned-store cart, authorized quantity/customer, suspend/retrieve, atomic sale, stock movement and thermal/A4 output.
- **Local/Dev boundary — 2026-08-07:** Implemented online assigned-store checkout, server-resolved price/stock/store/branch/drawer/shift context, idempotent sale approval, append-only sale movements, suspended/retrieved carts, bilingual POS/sales/detail/thermal-A4 baseline, and denied-role boundary. Tax, discounts, payments/evidence, open price, offline, customer, hardware acceptance, final print policy, formal Phase 3 gate, UAT, and Production readiness remain outside this slice and pending/configurable.
- **Traceability:** PRC-07, INV-02, POS-01–POS-02, POS-04, NFR-05–NFR-06; US-017; FLW-POS-01–02; UI UI-POS-001–002, UI-POS-006–007; AC-PRC-07, AC-INV-02, AC-POS-01–02, AC-POS-04; SEC-006, SEC-011–013, SEC-017–021, SEC-038.
- **Dependencies / Required Inputs:** Phase 2 gate; active product/price/stock/branch/store/drawer/shift foundations; approved POS/receipt/hardware workflow.
- **Database Entities:** `sales`, `sale_lines`, `suspended_sales`, `suspended_sale_lines`, `stock_movements`, `stock_balances`, `customers`, `shifts`, `cash_drawers`, `document_sequences`.
- **Backend / Livewire / Blade Deliverables:** server product lookup/cart/reprice/state/idempotent approval/movements/suspend actions; full-page POS/history/detail; Blade thermal/A4 invoice.
- **UI / Flux / Alpine / Vite:** Flux search/combobox/table/numeric/drawer/dialog/toast/badges; small JS for scanner/shortcuts/print only; dedicated lightweight POS Vite entry.
- **Suggested Packages:** Barcode generation/reader integration only if mature browser/hardware capability needed; PDF output candidate; no POS framework.
- **Permissions / Validation / Audit / States / Print:** POS/Create/Qty/Customer/Suspend/Retrieve/Print and store scope; priced/stock/shift/drawer/state/duplicate; full context audited; New/Suspended/Approved/Cancelled; thermal/A4.
- **Manual Browser Verification:** Rapid scanner/keyboard/touch, search, quantities, wrong-store informational lookup/block, unpriced/negative/stale/concurrent stock, customer, suspend/retrieve/expiry/other user, double submit, cart recovery, prints RTL/LTR.
- **Definition of Done:** DoD; cashier completes/holds/retrieves/prints and sale/stock/linkage reconcile.

### TSK-024 — Implement Discounts, Tax, Payments, Evidence, and Open Price

- **Task ID / Phase / Milestone / Status:** TSK-024; Phase 3; DM 3.2; **Domain layer implemented for Local/Dev under DEC-066 — `sale_payments`, `PosCalculationService` (docs/48 §3), `DiscountPolicy` (POS-05 non-stacking, POSF-04 replacement), `CapturePaymentAction`, and the settlement invariant are in place and automated-tested (31 tests). A sale can no longer be approved without tender. POS tender/discount/tax UI, open-price wiring, cash-rounding line, and receipt rendering remain; POSF-02 stays unset and BLK-008 production values remain Open.**
- **Local/Dev boundary — 2026-08-07:** Added guarded `GET /pos/financial-readiness` behind `pos_sales.view`. It reads only active payment/tax row counts and presents explicit pending cards for discount replacement, tax, payments/evidence, rounding/split residual, open price, and exact print totals. No financial records, defaults, uploads, or mutation actions were added.
- **Title / Purpose / Description:** Add one-discount replacement rule, optional invoice tax, cash/manual electronic settlement/evidence, open-price authorization and exact printed totals.
- **Traceability:** PRC-08, POS-03–POS-06, NFR-01, NFR-04; US-008, US-018; FLW-POS-01, FLW-POS-03; UI UI-POS-001, UI-POS-007, UI-SYS-005; AC-PRC-08, AC-POS-03–06; SEC-006, SEC-015–016, SEC-019–020, SEC-022–024, SEC-027.
- **Dependencies / Required Inputs:** TSK-023; `docs/26-discount-return-policy.md`; BLK-008 tax/payment/numbering and final discount, open-price, evidence, rounding, and financial limits remain configurable or pending.
- **Database Entities:** `sales`, `sale_lines`, `payments`, `payment_evidence`, `attachments`, `tax_settings`, `price_lines`, `approval_records`, `audit_logs`.
- **Backend / Livewire / Blade Deliverables:** deterministic calculations, one-discount invariant, payment/evidence/open-price actions, safe protected files; POS settlement and immutable detail/print.
- **UI / Flux / Alpine / Vite:** Flux payment drawer/forms/radios/upload/summary/dialog/toast/alerts; Alpine print/file preview only; POS/print assets.
- **Suggested Packages:** Reuse approved media/upload/PDF capabilities; no gateway package, no discount engine.
- **Permissions / Validation / Audit / States / Print:** methods/tax/discount/open-price/evidence/approve; range/reason/stack/replacement/totals/file/idempotency; all overrides/payments/files; thermal/A4 exact breakdown.
- **Manual Browser Verification:** Cash/electronic/mixed if approved, required/unsafe evidence, tax/no-tax, discount replace/no-stack, open price limits/reason/role/offline, rounding/totals, duplicate payment, file access, print.
- **Definition of Done:** DoD and DM 3.2 criteria; printed/source/calculated totals reconcile.

### TSK-025 — Implement Shift Opening, Cash Movements, Blind Closing, and Variance Review

- **Task ID / Phase / Milestone / Status:** TSK-025; Phase 3; DM 3.3; **FULL_IMPLEMENTATION for Local/Dev under DEC-066 (docs/32) — replaced the readiness-only boundary. Shift open with opening float and drawer/cashier exclusivity, `cash_movements`, server-derived expected totals, blind actual submission, variance by method, recount, manager approval with separation of duties, immutable close with `shift_close` numbering, audit, idempotency, and stale-version protection are implemented and tested (37 backend tests + 6 browser tests). Real HTTP routes `/pos/shift` and `/pos/shift-variance` replace `/pos/shift-readiness`. POSF-02 cash denomination and the variance tolerance threshold remain unset owner values; thermal/A4 close prints and MariaDB concurrency execution remain open.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Local/Dev boundary — 2026-08-07:** Added guarded `GET /pos/shift-readiness` behind `pos_sales.view`. It reads only scoped active-drawer and current-user open-shift counts; it passes no monetary fields and exposes no shift/cash/payment/variance mutation.
- **Title / Purpose / Description:** Deliver exclusive drawer shift, opening float, linked payments/cash movements, blind actual submission, expected/variance manager review and thermal/A4 close.
- **Traceability:** CSH-01–CSH-04, NFR-01–NFR-03; US-024; FLW-CSH-01–03; UI UI-POS-003–005; AC-CSH-01–04; SEC-011–012, SEC-015–020, SEC-027, SEC-037–038.
- **Dependencies / Required Inputs:** TSK-023–024; drawer allocation, cash-movement/shift/variance/closing/print policy.
- **Database Entities:** `shifts`, `shift_totals`, `cash_movements`, `cash_drawers`, `payments`, `sales`, `approval_records`, `document_sequences`.
- **Backend / Livewire / Blade Deliverables:** locked open/close, expected derivation, variance state/approval and immutable totals; shift screens; thermal/A4 close views.
- **UI / Flux / Alpine / Vite:** Flux Cards/Forms/Money Inputs/Select/Table/Tabs/Timeline/Dialog/Badges; Alpine/browser print only; print assets.
- **Suggested Packages:** Reuse approved PDF/print capability only.
- **Permissions / Validation / Audit / States / Print:** Cashier own Open/Actual Submit; Manager Expected/Review/Approve/Export; exclusive drawer/shift, actual completeness, idempotency; amounts/states audited; thermal/A4.
- **Manual Browser Verification:** Drawer/cashier collision, transaction/movement linkage, blind expected non-exposure (UI/direct response), missing/duplicate submit, variance role/recount/approval, report reconciliation and print.
- **Definition of Done:** DoD; end-of-shift totals reconcile and no pre-submit expected-value leak.

### TSK-026 — Implement Restricted Offline POS, Synchronization, and Conflict Review

- **Task ID / Phase / Milestone / Status:** TSK-026; Phase 3; DM 3.4; **READINESS_ONLY / BLOCKED_BY_OWNER — no offline queue table, no sync endpoint, no conflict model; only `/pos/offline-readiness`. BLK-004 (enabled devices, limits, price age, retry, review ownership, conflict disposition) is genuinely open and is behaviour rather than parameters. Recorded as OD-3 with deliberately no recommended default, because a wrong conflict disposition can duplicate or silently drop sales.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Local/Dev boundary — 2026-08-07:** Added guarded `GET /pos/offline-readiness` behind `pos_sales.view`. It records OFF-01..OFF-05 and NFR-04 as pending, shows PRD permitted/blocked operation classes, and enables no queue, sync, replay, conflict, or transactional offline behavior.
- **Title / Purpose / Description:** Deliver owner-approved device-bound provisional POS queue, restricted offline eligibility, protected/expiring IndexedDB, service-worker messaging, idempotent server sync and explicit conflict disposition.
- **Traceability:** POS-01–POS-05 offline boundary, NFR-01, NFR-03–NFR-06; US-032; FLW-OFF-01–03; UI UI-OFF-001–003, UI-POS-001; AC-NFR-01, AC-NFR-03–06; SEC-004–005, SEC-011–016, SEC-019–020, SEC-032–036.
- **Dependencies / Required Inputs:** TSK-023–025; DEC-018/BLK-004; exact devices/limits/price age/evidence/expiry/review/disposition/security.
- **Database Entities:** `offline_transactions`, `sync_batches`, `sync_conflicts`, `sales`, `payments`, `stock_movements`, `audit_logs`; device enrollment entity to finalize.
- **Backend / Livewire / Blade Deliverables:** eligibility/signed policy/idempotent sync/accept-reject-conflict/server numbering and correction actions; queue/conflict screens.
- **UI / Flux / Alpine / Vite:** Flux persistent badges/banners/tables/diff/progress/dialog/timeline; small TypeScript/Alpine for IndexedDB/connectivity/service worker/queue; dedicated versioned POS offline assets.
- **Suggested Packages:** PWA capability candidate only after security/compatibility review; prefer browser APIs for bounded queue rather than large framework.
- **Permissions / Validation / Audit / States / Print:** OfflinePOS/Sync/Resolve/Approve; device/user/branch/shift/time/limits/schema/hash/auth/idempotency; every local/sync/conflict event; provisional summary not final invoice until server acceptance.
- **Manual Browser Verification:** Cash/manual electronic only; block credit/wallet/loyalty redemption/special discount/unpriced/stale; logout/revoke/expiry/device loss/storage full/schema update; retry/interrupted/duplicate sync; server stock/price truth; each conflict disposition/evidence.
- **Definition of Done:** DoD and owner-enabled scope only; no silent overwrite/duplicate; support/conflict runbook current.

## Phase 4 — Customers, Loyalty, Wallets, Gift Instruments, and Returns

### TSK-027 — Implement Customer Profiles and Shared Loyalty

- **Task ID / Phase / Milestone / Status:** TSK-027; Phase 4; DM 4.1; **ACTUALLY_IMPLEMENTED for the Local/Dev customer and retail-loyalty contract; production/UAT/release remain open.** Real customer, scope, consent, child, merge-history, sales-link, immutable loyalty ledger, earn/redeem/expiry, adjustment approval, audit, idempotency, concurrency, direct-route, and POS-selection paths now exist. Exact legal/production policy values and Party-side history remain owner/downstream boundaries. Status verified against production code and focused evidence on 2026-08-10; see `testing/results/TSK-027-48-TEST-MATRIX.md` and `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Deliver unique-phone customer/consent/contact/children, authorized unified history and shared activity-rule loyalty earn/redeem/expiry.
- **Traceability:** MD-06, CUS-01, CUS-03–CUS-04, NFR-01–NFR-03; US-003, US-023; FLW-CUS-01–03; UI UI-CUS-001–003; AC-MD-06, AC-CUS-01, AC-CUS-03–04; SEC-006, SEC-010–012, SEC-015–021, SEC-027.
- **Dependencies / Required Inputs:** Phase 3 gate; BLK-014 mitigated by `docs/27-customer-loyalty-wallet-gift-policy.md`; final consent wording, legal retention, loyalty rates, rounding, expiry, and approval values remain configurable or pending.
- **Database Entities:** `customers`, `customer_children`, `loyalty_ledger`, sales/party source links, rule/version structures, `audit_logs`.
- **Backend / Livewire / Blade Deliverables:** Append-only policy settings, unique customer master, bilingual contact data, purpose-scoped consent/children, controlled merge, unified retail history, immutable source-linked loyalty ledger, FIFO earn/redeem/expiry, canonical adjustment approval/rejection, scoped audit/idempotency, customer master/profile/loyalty screens, and POS customer selection/registration are implemented for Local/Dev. No TSK-028 wallet, TSK-029 gift-instrument, or TSK-030 return behavior is included.
- **UI / Flux / Alpine / Vite:** Flux Search/Table/Filters/Pagination/Form/Tabs/Cards/Timeline/Badges/Dialog; Alpine none; common assets.
- **Suggested Packages:** None for loyalty engine; explicit rules. Customer phone library only if mature/necessary after locale review.
- **Permissions / Validation / Audit / States / Print:** Customer Sensitive/Merge/Export; Loyalty View/Earn/Redeem/Adjust/Approve; phone/consent/source/rule/balance/expiry/idempotency; all access/movements; profile/loyalty statement.
- **Manual Browser Verification:** New/existing/duplicate phone/merge denial, consent/children privacy, history scope, retail/party rates, earn/redeem/expiry/insufficient/concurrent/offline/duplicate source, RTL/LTR.
- **Definition of Done:** DoD; duplicates prevented and loyalty ledger/balance/source reconcile.

### TSK-028 — Implement Separated Product and Party Wallets

- **Task ID / Phase / Milestone / Status:** TSK-028; Phase 4; DM 4.2; **FOUNDATION_ONLY — `product_wallet_ledger` and `party_wallet_ledger` exist as genuinely separate, append-only, idempotent tables (structural non-transferability holds). Neither carries a holder column, so a per-holder balance cannot be derived at all; there are no mutation actions, no balance derivation, no settlement, correction, or reconciliation. Blocked by the missing `customers` table.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Local/Dev boundary — 2026-08-07:** Implement separate Product Wallet and Party Wallet ledger foundations, guarded empty/readiness screens, and Setup Dashboard `PENDING/TBD` policy values. Do not seed wallet rows or enable settlement, adjustment, transfer, payment, customer linkage, or production behavior.
- **Dependencies / Required Inputs:** TSK-027 dynamic settings/readiness; `docs/27-customer-loyalty-wallet-gift-policy.md`; wallet limits, settlement, adjustment, visibility, reporting, source linkage, and owner/Production values remain configurable or pending.
- **Acceptance boundary:** Close only the verified Local/Dev foundation/readiness slice; retain full TSK-028 requirements and Phase/UAT/Production gates as open.

- **Traceability:** MD-06, CUS-01–CUS-02, CUS-04; NFR-01–NFR-03; US-003, US-022; FLW-CUS-04–05; UI UI-CUS-002, UI-CUS-004–005; AC-MD-06, AC-CUS-01–02, AC-CUS-04; SEC-010–021, SEC-027.
- **Database Entities:** `product_wallet_ledger`, `party_wallet_ledger`, `customers`, source documents, `approval_records`, `audit_logs`.
- **Backend / Livewire / Blade Deliverables:** separately named models/actions/policies/scopes/reconciliation; separate full-page ledger screens and redacted profile tabs.
- **UI / Flux / Alpine / Vite:** Flux Summary Cards/Tables/Filters/Pagination/Dialog/Timeline/Audit Panel; Alpine none; common assets.
- **Suggested Packages:** None; do not install/build generic wallet package.
- **Permissions / Validation / Audit / States / Print:** ProductWallet and PartyWallet distinct rights; source/amount/balance/lock/approval/idempotency; sensitive view/export and every movement; separate statements.
- **Manual Browser Verification:** Cashier Party Wallet and Party Manager Product Wallet denied via nav/direct/query/export; authorized settlement/correction/concurrency; no transfer; ledger/balance/source reconciliation.
- **Definition of Done:** DoD and DM 4.2 criterion; physical and policy separation demonstrated.

### TSK-029 — Implement Gift Cards and Gift Receipts

- **Task ID / Phase / Milestone / Status:** TSK-029; Phase 4; DM 4.3; **READINESS_ONLY — `/gift-cards` and `/gift-receipts` are read-only closures stating that issue, balance, redeem, void, and expiry are disabled. No `gift_cards` table exists. Blocked by `customers` (holder) and by gift card as a tender method. POS-07 price suppression is fully specified and still unimplemented, including its page-source leak check.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Deliver price-free Gift Receipt issue/reprint/use and unique Gift Card issue/balance/partial/full redeem/void/expiry ledger.
- **Traceability:** POS-07, RET-02, RET-04, NFR-01–NFR-03, NFR-06; US-019, US-021; FLW-POS-04, FLW-RET-03; UI UI-POS-010–011; AC-POS-07, AC-RET-02, AC-RET-04; SEC-011–012, SEC-015–020, SEC-024, SEC-027.
- **Dependencies / Required Inputs:** TSK-028; `docs/27-customer-loyalty-wallet-gift-policy.md`; final Gift Card/Gift Receipt eligibility, validity, holder, void, reprint, and format values remain configurable or pending.
- **Database Entities:** `gift_receipts`, `gift_receipt_lines`, `gift_cards`, `gift_card_ledger`, `sales`, `payments`, `document_sequences`.
- **Backend / Livewire / Blade Deliverables:** unique references/locked ledger/idempotent use/privacy/print actions; list/detail/issue/use screens and Blade outputs.
- **UI / Flux / Alpine / Vite:** Flux Search/Table/Filters/Pagination/Cards/Money/Date/Dialog/Timeline/Badges; Alpine browser print only; print assets.
- **Suggested Packages:** Reuse approved barcode/PDF capability for card/receipt rendering if required.
- **Permissions / Validation / Audit / States / Print:** Issue/Reprint/Validate/Redeem/Void/View; source/eligibility/no-price/unique/balance/expiry/concurrency/reason; all events; Gift Receipt and card issue/use receipt.
- **Manual Browser Verification:** Price absence, eligible lines, invalid/reused/reprint, unique/concurrent ID/use, partial/full/expired/void/overuse, role/scope/direct access and print RTL/LTR.
- **Definition of Done:** DoD; every balance/reference/event traceable and private.

### TSK-030 — Implement Returns and Exchanges

- **Task ID / Phase / Milestone / Status:** TSK-030; Phase 4; DM 4.4; **READINESS_ONLY — `/pos/returns-readiness` only; no `sales_returns` or `return_lines` tables and no return, refund, exchange, restock, or payment-reversal behaviour. The TSK-024 payment rows a refund must reverse now exist, so the remaining blocker is the `customers` table plus BLK-013 numeric limits (configurable per DEC-065).** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Validate original invoice/Gift Receipt, inspect condition/approval, then same/different exchange, cash refund, or Gift Card settlement with stock disposition and references.
- **Traceability:** RET-01–RET-03, NFR-01–NFR-03, NFR-06; US-020; FLW-RET-01–02; UI UI-POS-008–010; AC-RET-01–03; SEC-006, SEC-011–012, SEC-015, SEC-017–024, SEC-027.
- **Dependencies / Required Inputs:** TSK-029; BLK-013 mitigated by `docs/26-discount-return-policy.md`; final return window, exceptions, condition, refund, approval, non-saleable, and damage values remain configurable or pending.
- **Database Entities:** `retail_returns`, `retail_return_lines`, `exchanges`, `exchange_lines`, `sales`, `sale_lines`, `payments`, `gift_cards`, `stock_movements`, `stock_balances`, `attachments`, `approval_records`, `document_sequences`.
- **Backend / Livewire / Blade Deliverables:** source eligibility/over-return/condition/settlement/locked posting and correction actions; stepper screens/detail/outputs.
- **UI / Flux / Alpine / Vite:** Flux Reference Search/Stepper/Tables/Radios/Upload/Summary/Payment Drawer/Timeline/Dialog/Badges; Alpine scanner/print only; print assets.
- **Suggested Packages:** Reuse approved media/barcode/PDF capabilities; no generic returns engine.
- **Permissions / Validation / Audit / States / Print:** Create/Inspect/Refund/Exchange/Approve/Reverse/Print; source/window/qty/value/condition/stock/settlement/limits/idempotency; full audit; return/exchange/refund print.
- **Manual Browser Verification:** Valid/invalid/Gift source, partial/duplicate/excess, all four outcomes/difference, condition approval, sellable/non-saleable/damaged, concurrent stock/payment, terminal immutable, role/print.
- **Definition of Done:** DoD and Phase 4 gate; stock/financial/source history reconciles.

## Phase 5 — Party Booking, Operations, and Rental Assets

### TSK-031 — Implement Party Bookings and Working Invoices

- **Task ID / Phase / Milestone / Status:** TSK-031; Phase 5; DM 5.1; **MISSING — there is no Party module in `app/Modules/` and no party tables exist. The only surface is a read-only readiness route. Booking, working invoice, customer/child linkage, and invoice mutations are entirely unimplemented. BLK-015/BLK-016 are Mitigated (DEC-039 adopts docs/28, docs/29, docs/33 locally), so this band is policy-clear; it is blocked by the `customers` keystone, with TSK-024 payments now available.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Deliver party-only booking/calendar, customer/child/schedule/location/contact/plans/responsibilities and editable working invoice frozen at final close.
- **Traceability:** PTY-01–PTY-03, NFR-01–NFR-03; US-025; FLW-PTY-01–02; UI UI-PTY-001–003; AC-PTY-01–03; SEC-006, SEC-010–015, SEC-017, SEC-019–020, SEC-027.
- **Dependencies / Required Inputs:** Phase 4 gate; BLK-015 mitigated by `docs/28-party-operations-policy.md`; final party stores, services/packages, schedule, cancellation, edit, responsibility, price, and real master data remain configurable or pending.
- **Database Entities:** `party_bookings`, `party_invoices`, `party_invoice_lines`, `customers`, `customer_children`, `stores`, `document_sequences`, `audit_logs`.
- **Backend / Livewire / Blade Deliverables:** party-typed state/schedule/conflict and versioned edit actions; booking list/calendar/form and working-invoice editor/detail.
- **UI / Flux / Alpine / Vite:** Flux Table/Filters/Search/Pagination/Calendar/Form/Date-Time/Combobox/Line Editor/Timeline/Dialog/Badges; Alpine calendar affordance only if needed; print assets.
- **Suggested Packages:** Calendar capability only if Flux/native cannot meet schedule requirement; no SPA calendar framework by default.
- **Permissions / Validation / Audit / States / Print:** Party Booking/Invoice Create/Edit/Confirm/Reschedule/Cancel/Print; activity/store/required/timezone/state/line type; every change; booking/working invoice.
- **Manual Browser Verification:** Retail line/store block, required/contact/child/schedule/timezone, calendar/reschedule/conflict, edit before/freeze after, cancel/permission/direct route, responsive RTL/LTR and print.
- **Definition of Done:** DoD; booking works without retail financial exposure.

### TSK-032 — Implement Party Payments on Account and Party Balance

- **Task ID / Phase / Milestone / Status:** TSK-032; Phase 5; DM 5.2; **MISSING — there is no Party module in `app/Modules/` and no party tables exist. The only surface is a read-only readiness route. Payment on account, receipt, party balance, reversal, and wallet mutations are entirely unimplemented. BLK-015/BLK-016 are Mitigated (DEC-039 adopts docs/28, docs/29, docs/33 locally), so this band is policy-clear; it is blocked by the `customers` keystone, with TSK-024 payments now available.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Post multiple party payments, each with exact PRD receipt label, reconcile party balance and integrate only Party Wallet under approved policy.
- **Traceability:** CUS-02, CUS-04, PTY-04, NFR-01–NFR-03, NFR-06; US-026; FLW-PTY-03, FLW-CUS-05; UI UI-PTY-004, UI-CUS-005; AC-CUS-02, AC-PTY-04; SEC-011–020, SEC-022–024, SEC-027.
- **Dependencies / Required Inputs:** TSK-031; `docs/28-party-operations-policy.md`; DEC-019 and final payment/deposit, receipt, evidence, overpayment, Party Wallet, and legal/financial values remain configurable or pending.
- **Database Entities:** `party_payments`, `payments`, `party_invoices`, `party_wallet_ledger`, `payment_evidence`, `attachments`, `document_sequences`.
- **Backend / Livewire / Blade Deliverables:** idempotent party payment/balance actions, receipt numbering/text, evidence and wallet source links; payments screen/ledger; Blade receipt.
- **UI / Flux / Alpine / Vite:** Flux Summary/Payment Form/Method Select/Upload/Table/Pagination/Dialog/Timeline/Badges; Alpine print only; print assets.
- **Suggested Packages:** Reuse approved media/PDF capability; no generic deposit/wallet engine.
- **Permissions / Validation / Audit / States / Print:** PartyPayment Create/Approve/Print and PartyWallet rights; amount/method/source/evidence/idempotency/no Product Wallet; all events; `Payment on Account for Party Invoice No. [number]`.
- **Manual Browser Verification:** Multiple/partial/duplicate/concurrent/overpayment policy, exact receipt/reprint, evidence, balance/source, Cashier/other activity denial, RTL/LTR/thermal-A4 as approved.
- **Definition of Done:** DoD; DEC-019 resolved or PRD wording preserved; party balance reconciles.

### TSK-033 — Implement Party Operating Orders and Consumable Movements

- **Task ID / Phase / Milestone / Status:** TSK-033; Phase 5; DM 5.3; **MISSING — there is no Party module in `app/Modules/` and no party tables exist. The only surface is a read-only readiness route. Operating orders, consumable issue/return, and stock movements are entirely unimplemented. BLK-015/BLK-016 are Mitigated (DEC-039 adopts docs/28, docs/29, docs/33 locally), so this band is policy-clear; it is blocked by the `customers` keystone, with TSK-024 payments now available.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Create/release/execute party order, assign resources, issue/consume party-store consumables, control additions/removals and reference eligible unused returns.
- **Traceability:** PTY-05, AST-05, NFR-01–NFR-03; US-027; FLW-PTY-04–05; UI UI-PTY-005–006; AC-PTY-05, AC-AST-05; SEC-006, SEC-011–015, SEC-017, SEC-019–021, SEC-027.
- **Dependencies / Required Inputs:** TSK-032; `docs/28-party-operations-policy.md`; final operating checklist, party-store mapping, consumables/UOM, availability, return/change, approval, and real master data remain configurable or pending.
- **Database Entities:** `party_operating_orders`, `party_operating_order_lines`, `party_consumable_issues`, `party_consumable_lines`, `stock_movements`, `stock_balances`, `approval_records`, `document_sequences`.
- **Backend / Livewire / Blade Deliverables:** state/version/assignment/change actions, locked issue/return posting; order list/editor and consumable scan/actual screens.
- **UI / Flux / Alpine / Vite:** Flux Tables/Filters/Form/Line Editor/Assignments/Scan/Numeric/Summary/Timeline/Dialog/Badges; Alpine scanner/shortcuts only; print assets.
- **Suggested Packages:** Reuse barcode/PDF capability only.
- **Permissions / Validation / Audit / States / Print:** Create/Edit/Release/Issue/Return/Complete/Override; party/store/stock/fraction/state/reason; all version/movement/assignment events; order/issue/return print.
- **Manual Browser Verification:** Party-only resources, insufficient/concurrent stock, actuals, authorized add/remove, post-complete block, eligible/excess return, source/balance reconciliation, print/RTL/LTR.
- **Definition of Done:** DoD; consumable movements are controlled and reconcile.

### TSK-034 — Implement Rental Asset Master, Calendar, Reservation, Checkout, and Return

- **Task ID / Phase / Milestone / Status:** TSK-034; Phase 5; DM 5.4; **MISSING — there is no Party module in `app/Modules/` and no party tables exist. The only surface is a read-only readiness route. Asset master, calendar, reservation, checkout, return, and condition capture are entirely unimplemented. BLK-015/BLK-016 are Mitigated (DEC-039 adopts docs/28, docs/29, docs/33 locally), so this band is policy-clear; it is blocked by the `customers` keystone, with TSK-024 payments now available.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Maintain unique assets separately from consumables, show availability calendar, lock non-overlapping reservations, and capture checkout/return/pre-post condition/status/location.
- **Traceability:** AST-01–AST-03, NFR-01–NFR-03; US-028; FLW-PTY-06–08; UI UI-PTY-007–012; AC-AST-01–03; SEC-006, SEC-011–013, SEC-015–020, SEC-022–024, SEC-027.
- **Dependencies / Required Inputs:** TSK-033; BLK-016 mitigated by `docs/29-rental-asset-policy.md`; final asset register, categories, locations, interval buffers, checklists, state, maintenance, loss, and finance values remain configurable or pending.
- **Database Entities:** `rental_assets`, `asset_reservations`, `asset_checkouts`, `asset_returns`, `attachments`, `approval_records`, `document_sequences`.
- **Backend / Livewire / Blade Deliverables:** unique master, interval concurrency/state/actions/documents/condition/evidence; asset list/detail/calendar/reserve/checkout/return/inspect screens.
- **UI / Flux / Alpine / Vite:** Flux Tables/Filters/Search/Pagination/Calendar/Date-Time/Combobox/Checklists/Radio/Upload/Timeline/Dialog/Badges; Alpine calendar/local capture only if needed; print assets.
- **Suggested Packages:** Calendar/media/PDF capabilities only after compatibility review; no rental framework.
- **Permissions / Validation / Audit / States / Print:** Master/Reserve/Override/Checkout/Return/Inspect/Status/Cost View; unique/interval/timezone/buffer/state/location/condition/reference/file; all events; reservation/checkout/return/inspection prints.
- **Manual Browser Verification:** Separate consumables, duplicate asset, overlapping concurrent intervals/reschedule/cancel, timezone/buffer, wrong state/location/party, required conditions/files, all statuses, print/responsive RTL/LTR.
- **Definition of Done:** DoD; double booking impossible and complete source history available.

### TSK-035 — Implement Asset Damage, Loss, Maintenance, and Depreciation Review

- **Task ID / Phase / Milestone / Status:** TSK-035; Phase 5; DM 5.4; **MISSING — there is no Party module in `app/Modules/` and no party tables exist. The only surface is a read-only readiness route. Damage, loss, maintenance, depreciation, approval, and correction are entirely unimplemented. BLK-015/BLK-016 are Mitigated (DEC-039 adopts docs/28, docs/29, docs/33 locally), so this band is policy-clear; it is blocked by the `customers` keystone, with TSK-024 payments now available.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Assess source-linked damage/loss/depreciation, responsibility, evidence, optional cost, approval and final asset state without implying general ledger.
- **Traceability:** AST-04, NFR-01–NFR-03; US-029; FLW-PTY-09–10; UI UI-PTY-012–014; AC-AST-04; SEC-006, SEC-011–012, SEC-015–020, SEC-022–024, SEC-027.
- **Dependencies / Required Inputs:** TSK-034; `docs/29-rental-asset-policy.md`; final damage/loss, maintenance, depreciation, method, cost, responsibility, approval limits, and finance values remain configurable or pending.
- **Database Entities:** `asset_damage`, `asset_depreciation`, `rental_assets`, `asset_returns`, `party_bookings`, `attachments`, `approval_records`.
- **Backend / Livewire / Blade Deliverables:** assessment/depreciation state/actions/cost-field policy and immutable history; review lists/details/forms.
- **UI / Flux / Alpine / Vite:** Flux Table/Filters/Search/Pagination/Before-After Cards/Upload/Money/Select/Timeline/Dialog/Audit Panel; Alpine image comparison only if needed; print assets.
- **Suggested Packages:** Reuse approved media/PDF capability; no accounting/depreciation engine unless later scope changes.
- **Permissions / Validation / Audit / States / Print:** Assess/Cost/Edit/Approve/Status/Export; source/party/assessment/evidence/value/method/date/state; full before-after/approval; damage/depreciation report.
- **Manual Browser Verification:** Missing/duplicate/source, cost redaction, damaged/maintenance/retired/lost/available transitions, approval separation, direct delete/edit denial, history/export/RTL/LTR.
- **Definition of Done:** DoD; cost/event is operational history only and final status is traceable.

### TSK-036 — Implement Party Final Closure and Settlement

- **Task ID / Phase / Milestone / Status:** TSK-036; Phase 5; DM 5.5; **MISSING — there is no Party module in `app/Modules/` and no party tables exist. The only surface is a read-only readiness route. Final invoice, receipt, party wallet, credit, settlement, close, and posting are entirely unimplemented. BLK-015/BLK-016 are Mitigated (DEC-039 adopts docs/28, docs/29, docs/33 locally), so this band is policy-clear; it is blocked by the `customers` keystone, with TSK-024 payments now available.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Validate readiness, finalize immutable party invoice, reconcile payments on account, Party Wallet, remaining amount/credit and final receipt atomically.
- **Traceability:** CUS-02, PTY-06, NFR-01–NFR-03, NFR-06; US-026; FLW-PTY-11; UI UI-PTY-015; AC-CUS-02, AC-PTY-06; SEC-011–020, SEC-027.
- **Dependencies / Required Inputs:** TSK-031–035; `docs/28-party-operations-policy.md` and `docs/29-rental-asset-policy.md`; final-close, credit, overpayment, Party Wallet, approval, receipt, readiness, and financial values remain configurable or pending.
- **Database Entities:** `party_invoices`, `party_invoice_lines`, `party_payments`, `payments`, `party_wallet_ledger`, `party_operating_orders`, asset/consumable sources, `document_sequences`, `approval_records`.
- **Backend / Livewire / Blade Deliverables:** readiness query, locked idempotent finalization/number/totals/payment/wallet posting and source freeze; settlement page/detail; final Blade invoice/receipt.
- **UI / Flux / Alpine / Vite:** Flux Checklist/Summary Cards/Tables/Payment/Wallet Panels/Timeline/Dialog/Badges; Alpine print only; print assets.
- **Suggested Packages:** Reuse approved PDF/print capability.
- **Permissions / Validation / Audit / States / Print:** Finalize/Settle/Approve/Print; operation/assets/payments/totals/wallet/activity/concurrency/idempotency; full source/result audit; final invoice/receipt.
- **Manual Browser Verification:** Unresolved asset/order/consumable block, multiple payments, Party Wallet only, Product Wallet denial, remaining/credit, concurrent double close, immutable final/correction path, exact print RTL/LTR.
- **Definition of Done:** DoD and Phase 5 gate; final party financial/operational record reconciles.

## Phase 6 — Quotations, Reporting, Acceptance, and Launch

### TSK-037 — Implement Standalone Retail and Party Quotations

- **Task ID / Phase / Milestone / Status:** TSK-037; Phase 6; DM 6.1 (Proposed mapping); **MISSING — `/quotations-readiness` only; no `quotations` table and no quote creation, approval, output, conversion, or financial effect. Pricing is available; blocked by `customers`.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Create typed retail/party quotation with customer/lines/prices/terms/notes/validity/status, print/share and future identity, with no posting or Phase 1 conversion.
- **Traceability:** QTN-01–QTN-03, NFR-01–NFR-03, NFR-06; US-030; FLW-QTN-01; UI UI-QTN-001; AC-QTN-01–03; SEC-006, SEC-011–013, SEC-017–020, SEC-024, SEC-026–027.
- **Dependencies / Required Inputs:** Phase 5 gate; DEC-025 owner confirmation; quotation statuses/terms/price authority/share/format.
- **Database Entities:** `quotations`, `quotation_lines`, `customers`, `document_sequences`, `audit_logs`, attachments if generated artifact tracked.
- **Backend / Livewire / Blade Deliverables:** typed non-posting state/version/number/actions and explicit no-conversion guard; list/editor/detail; quotation Blade/PDF.
- **UI / Flux / Alpine / Vite:** Flux Table/Filters/Search/Pagination/Form/Line Editor/Date/Summary/Timeline/Dialog/Badges; Alpine share/print only; print assets.
- **Suggested Packages:** Reuse approved PDF capability; no conversion/workflow engine.
- **Permissions / Validation / Audit / States / Print:** Create/Edit/Issue/Print/Share/Cancel; activity/line/price/terms/validity/state; all version/status/output events; quotation PDF/A4.
- **Manual Browser Verification:** Retail and party cases, mixed line block, valid/expired/status/revision, no stock/reservation/payment/wallet/accounting effect, conversion unavailable, role/export/share/print RTL/LTR.
- **Definition of Done:** DoD; mapping approved and non-posting behavior demonstrated.

### TSK-038 — Implement Dashboards and Reconciled Report Catalog

- **Task ID / Phase / Milestone / Status:** TSK-038; Phase 6; DM 6.1; **READINESS_ONLY / BLOCKED_BY_OWNER — `/reports-readiness` only; no KPI calculation, report read-models, drilldown, or reconciliation. BLK-017 is partially stale: `docs/50-reporting-formula-catalog.md` now supplies the formulas but remains team-adopted with owner approval outstanding, so what is missing is adoption rather than authorship. Deliberately sequenced last — reports must reconcile to source transactions, and building them before the upstream money paths are correct would produce reports that are wrong by construction.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Deliver role/date/branch/store scoped KPI dashboard and required report groups with formula/source lineage, pagination and drilldown.
- **Traceability:** RPT-01, RPT-03, NFR-03, NFR-05; US-031; FLW-RPT-01; UI UI-ADM-001, UI-RPT-001; AC-RPT-01, AC-RPT-03, AC-NFR-05; SEC-011–012, SEC-016, SEC-026, SEC-037.
- **Dependencies / Required Inputs:** Phase 5 gate; BLK-017; formula catalog/access/ranges/layouts and complete source data.
- **Database Entities:** all source documents/ledgers and purpose-built read models only if justified/proven.
- **Backend / Livewire / Blade Deliverables:** scoped indexed report queries/formulas/drilldowns; dashboard/report selector/filter/detail pages.
- **UI / Flux / Alpine / Vite:** Flux Filter Bar/Cards/Charts/Tables/Pagination/Tabs/Badges/Empty/Loading; Alpine none; Vite chart assets only if approved.
- **Suggested Packages:** One mature chart capability if Flux/native lacks charts; no analytics platform/package without need.
- **Permissions / Validation / Audit / States / Print:** report/field/cost/margin/branch/store; ranges/filter allow-list/bounded search/formula; sensitive access; dashboard/report PDF/print links.
- **Manual Browser Verification:** Every required widget/group/filter, source drilldown, empty/error, high volume, formula/ledger reconciliation, cross-role/branch/store/cost denial, responsive RTL/LTR.
- **Definition of Done:** DoD; each number has an approved formula and reconciles to source.

### TSK-039 — Implement Operational Alerts and Notifications

- **Task ID / Phase / Milestone / Status:** TSK-039; Phase 6; DM 6.1; **MISSING — `/alerts-readiness` only; no `alerts` table and no trigger evaluation, creation, delivery, acknowledgement, resolution, dismissal, escalation, or source navigation. Inventory-sourced alerts (for example low stock) are dependency-ready today; escalation ownership remains BLK-017.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Surface all PRD low/zero/unpriced/price/transfer/count/invoice/shift/party/balance/asset alerts and role-safe notification navigation.
- **Traceability:** RPT-02, NFR-03, NFR-05; US-031; FLW-RPT-01; UI UI-ADM-001, UI-SYS-007, UI-RPT-001; AC-RPT-02, AC-NFR-03, AC-NFR-05; SEC-011–012, SEC-016, SEC-037.
- **Dependencies / Required Inputs:** TSK-038; alert thresholds/timing/ownership/dismissal/escalation policy.
- **Database Entities:** source module tables and alert/read state structures only where persistence is required.
- **Backend / Livewire / Blade Deliverables:** scoped alert evaluators/queries/scheduler jobs if approved, read/dismiss links; notification list/dashboard widgets.
- **UI / Flux / Alpine / Vite:** Flux Lists/Tables/Filters/Pagination/Badges/Dropdown/Drawer/Toast/Empty; Alpine none; common assets.
- **Suggested Packages:** Laravel notifications/scheduler native; no alert engine unless proven necessary.
- **Permissions / Validation / Audit / States / Print:** source/scope, dismiss/resolve if approved; threshold/duplicate/stale/source existence; sensitive actions audited; no required print.
- **Manual Browser Verification:** Trigger/clear every alert, source link/scope, duplicate/stale behavior, unauthorized detail, scheduler/empty/error, responsive RTL/LTR.
- **Definition of Done:** DoD; alert catalog complete and source-correct.

### TSK-040 — Implement PDF/Excel Export Center and Audit Views

- **Task ID / Phase / Milestone / Status:** TSK-040; Phase 6; DM 6.2; **MISSING — `/exports-audit-readiness` only; no `export_jobs` table and no PDF/Excel/CSV generation, artifact storage, download, audit export, or redaction. Audit *screens* exist and are tested. The export infrastructure is content-agnostic and dependency-ready; redaction rules and layouts remain BLK-017/BLK-008.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Provide safe permissioned PDF/Excel generation/download/expiry and append-only audit filters/detail/before-after/export.
- **Traceability:** RPT-03, NFR-01–NFR-05; US-031–032; FLW-RPT-02–03; UI UI-RPT-002, UI-AUD-001, UI-OFF-003; AC-RPT-03, AC-NFR-01–05; SEC-011–012, SEC-016, SEC-022–029, SEC-037.
- **Dependencies / Required Inputs:** TSK-038–039; export formats/limits/retention/storage/redaction/audit access.
- **Database Entities:** `audit_logs`, `attachments` or export artifact entity, all report sources, `sync_conflicts`.
- **Backend / Livewire / Blade Deliverables:** scoped queued export/artifact authorization/expiry/formula safety and immutable audit query/redaction; export center/audit screens; PDF Blade views.
- **UI / Flux / Alpine / Vite:** Flux Tables/Filters/Search/Pagination/Progress/Badges/Download/Drawer/Diff/Dialog; Alpine browser download/print only; export/print assets.
- **Suggested Packages:** One mature Excel and one PDF capability after compatibility/security review; reuse approved audit capability; no duplicates.
- **Permissions / Validation / Audit / States / Print:** Export format/field/scope/Audit View/Export; range/rows/formulas/redaction/artifact owner/expiry; every request/download/access; PDF/Excel and audit print.
- **Manual Browser Verification:** Every format/filter/value, large queued/fail/retry/expiry, cross-user/link/field denial, formula injection, audit source/before-after/redaction/immutability, RTL/LTR PDF.
- **Definition of Done:** DoD and DM 6.2 criteria; exports reconcile and access is controlled.

### TSK-041 — Import and Reconcile Approved Production Master Data

- **Task ID / Phase / Milestone / Status:** TSK-041; Phase 6; DM 6.4; **READINESS_ONLY / BLOCKED_BY_DEPENDENCY — import mechanics partly exist (`purchase_invoice_import_*`, `product_import_batches`), but BLK-010 is Open and no approved production source files exist, so batch import, destructive replacement, opening-stock posting, and cutover cannot proceed.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Execute controlled validated import/cutover for approved company/branches/stores/drawers/users/products/suppliers/customers/opening stock and supporting masters.
- **Traceability:** MD-01–MD-06, PRC-01, PUR-01, INV-01, NFR-01–NFR-07; applicable stories/flows; UI import/admin/error screens; applicable ACs; SEC-006, SEC-009–012, SEC-017–021, SEC-022–027, SEC-039.
- **Dependencies / Required Inputs:** DM 6.3 passed; final signed data/templates/cutoff/opening valuation and maker/checker owners.
- **Database Entities:** all approved master tables, import batches, `stock_movements`, `stock_balances`, `audit_logs`, `approval_records`, `attachments`.
- **Backend / Livewire / Blade Deliverables:** reusable approved import paths/staged validation/idempotent cutover/opening-stock source document and reconciliation outputs; import progress/errors/review UI.
- **UI / Flux / Alpine / Vite:** Reuse admin/product import Flux patterns; Alpine none; common assets.
- **Suggested Packages:** Reuse approved Excel/import/media capabilities; no second migration tool.
- **Permissions / Validation / Audit / States / Print:** DataImport/Create/Update/Approve/Export Error; schema/reference/duplicate/cutoff/count/value; complete batch/maker/checker audit; reconciliation reports.
- **Manual Browser Verification:** Dry review, invalid isolation, duplicate retry, references, counts/totals/opening ledger, cross-scope/user roles, artifact protection, signed reconciliation.
- **Definition of Done:** DoD; client-approved data and reconciliation with rollback/cutover record.

### TSK-042 — Complete Production Readiness, Devices, Backup, and Training

- **Task ID / Phase / Milestone / Status:** TSK-042; Phase 6; DM 6.4; **READINESS_ONLY / EXTERNAL_ONLY — `config/backup.php`, the backup/restore console commands, and `BackupOperationalTest` exist locally. Production infrastructure, secrets, physical devices, and restore evidence are outside the repository and remain BLK-001/BLK-002/BLK-003.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Verify production configuration/secrets/workers/scheduler/storage/monitoring, target branches/scanners/printers, baseline backup/restore, training and operational runbooks.
- **Traceability:** all requirements, especially NFR-04–NFR-07 and print/device requirements; US-032; all operational flows/screens; AC-NFR-04–07, AC-UI-02–05; SEC-028–040.
- **Dependencies / Required Inputs:** TSK-041; approved hosting/domain/devices/printers/support/backup/training attendees/runbooks.
- **Database Entities:** production configuration references, users/scopes, printer configs, backup/health records, audit logs.
- **Backend / Livewire / Blade Deliverables:** production-safe configuration, supervised workers/scheduler, monitoring/backups/recovery/runbooks; system health/backup UI and user guidance.
- **UI / Flux / Alpine / Vite:** all production assets/version/cache; Flux system status; approved local-device JS only.
- **Suggested Packages:** Only already approved backup/monitoring/PWA/PDF/barcode/media/chart packages; final security/license/vulnerability review.
- **Permissions / Validation / Audit / States / Print:** least privilege, secrets/redaction, device/printer scope, restore ownership; all admin changes; test prints/operational documents.
- **Manual Browser Verification:** Production roles/config, worker/scheduler/storage/monitoring, scanners/thermal/A4/labels/camera, PWA, baseline backup and isolated restore to RPO/RTO, training walkthroughs.
- **Definition of Done:** DoD; production readiness checklist and training/backup/restore/device evidence signed.

### TSK-043 — Execute Scenario-Based Manual UAT and Defect Retesting

- **Task ID / Phase / Milestone / Status:** TSK-043; Phase 6; DM 6.3; **EXTERNAL_ONLY — scenario and evidence scaffolding exists (`testing/results/UAT-SCENARIOS.md`). Actual UAT execution, sign-off, and device acceptance require human execution and cannot be completed by an agent (DEC-064).** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Execute manual UAT across all roles, 72 requirements, source acceptance scenarios, devices, prints, integrity and offline scope; triage/retest defects. This is not an automated-test task.
- **Traceability:** all 72 PRD IDs, US-001–032, all FLWs/UI screens, all ACs, SEC-001–040.
- **Dependencies / Required Inputs:** DM 6.2 complete; named UAT owners, approved scenarios/data/devices/evidence repository/severity/sign-off.
- **Database Entities:** all source data; UAT evidence/defects are operational project records, not invented application tables.
- **Backend / Livewire / Blade Deliverables:** No new scope; only separately approved defect corrections with updated traceability. All screens/outputs under review.
- **UI / Flux / Alpine / Vite:** All implemented UI/device behaviors; no new library for UAT.
- **Suggested Packages:** None; no PHPUnit/Pest/Playwright/Cypress or automated suite.
- **Permissions / Validation / Audit / States / Print:** verify every role/action/scope/field/state/validation/audit/print; use production-like accounts and protected evidence.
- **Manual Browser Verification:** Follow `docs/14-test-plan.md` end to end, record actual Pass/Fail/Blocked/evidence, retest original and directly affected regressions; verify RTL/LTR/responsive/offline/backup restore.
- **Definition of Done:** All agreed UAT scenarios passed, critical defects closed, open items owned, sign-off recorded; automated tests remain Not Created/Not Run.

### TSK-044 — Execute Controlled Go-Live and Operational Handover

- **Task ID / Phase / Milestone / Status:** TSK-044; Phase 6; DM 6.4; **EXTERNAL_ONLY — launch boundary scaffolding exists. Actual production cutover, deployment, client sign-off, and go-live require human and production action.** Status verified against production code on 2026-08-09; see `testing/results/IMPLEMENTATION-GAP-MATRIX.md`.
- **Title / Purpose / Description:** Perform authorized cutover, final production verification, controlled launch, support monitoring and full operational/client handover.
- **Traceability:** all requirements and Delivery Criteria; all stories/flows/screens/AC/security items.
- **Dependencies / Required Inputs:** TSK-041–043 complete; client release approval, final data/config/users/printers/backups/training/support/rollback readiness.
- **Database Entities:** all production data and source records; baseline reconciliation/audit/backup references.
- **Backend / Livewire / Blade Deliverables:** release/cutover/rollback/support procedures and actual production deployment under approved runbook; no unapproved scope.
- **UI / Flux / Alpine / Vite:** production versioned assets and every approved screen/output; no new UI unless separately authorized.
- **Suggested Packages:** None new at go-live; freeze reviewed dependencies.
- **Permissions / Validation / Audit / States / Print:** least-privilege production accounts, maker/checker cutover, full audit/monitoring; production statuses; required baseline/operational prints.
- **Manual Browser Verification:** Final production smoke journeys for each role/module/device/printer, data/ledger/report totals, alerts, backup, monitoring, support escalation and rollback readiness.
- **Definition of Done:** Client sign-off; no unresolved critical defect; configuration/data/user/printer/backup approved; guidance, evidence, issue register, limitations/owners, recovery and release handoff complete.

### TSK-045 — Implement Product Variations, Media Fallback, and POS Selection

- **Task ID / Phase / Milestone / Status:** TSK-045; Phase 2/3 follow-on; DM 2.1 and DM 3.1; **Implemented for Local/Dev code and isolated MariaDB migration/seed verification on 2026-08-12; required headed browser scenarios, screenshots, UAT, and Production/release remain open.**
- **Title / Purpose / Description:** Add explicit standard-product variation families without changing the meaning of ordinary descriptive colour/size fields. Families are non-sellable; child Products own immutable SKU/barcode identities, independent price/stock/status, optional protected media, and bilingual option selection.
- **Traceability:** MD-02–MD-05 as amended by DEC-075; PRC-02–03; INV-01; POS-01–06; US-002,005,008,013,017–018; UI UI-CAT-001–003,009 and UI-POS-001,007.
- **Dependencies / Required Inputs:** Existing Catalog, Attachment, Pricing, Inventory, Purchasing, Retail sale, and POS session-cart foundations. Production option vocabulary, real family/SKU master data, imagery, and UAT remain owner inputs.
- **Database Entities:** extended `products`; `product_option_groups`, `product_option_values`, `product_family_option_groups`, `product_family_option_values`, `product_variant_values`; `sale_lines.variant_snapshot`; existing barcode/media/price/stock/supplier/document tables.
- **Backend / Livewire / Blade Deliverables:** bilingual option lifecycle; locked idempotent 1–3 group / 100-SKU matrix; immutable canonical signatures; conversion guards; family-to-child descriptive synchronization; sellability scopes and transaction enforcement; variation snapshots; media fallback; private POS thumbnails; isolated POS Livewire product browser/cart/checkout summary with existing guarded POST checkout and fallback cart routes.
- **UI / Flux / Alpine / Vite:** Product Options screen, product-editor variation matrix and child detail/media drawer, POS family cards and availability-aware variation drawer, child-SKU cart lines, bilingual RTL/LTR copy, bounded loading/empty/error/disabled states.
- **Permissions / Validation / Audit / States / Print:** existing product/POS permissions; server family rejection; active-option reference guard; unique immutable SKU/barcode/signature; price/stock/status revalidation; audit option/matrix/inactivation/denied changes; sale snapshot available for historical receipt/return consumers.
- **Verification actually completed:** PHP syntax on changed PHP/Volt files; Blade cache; named-route discovery; fresh migration and idempotent DemoSeeder on dedicated XAMPP MariaDB `toyjoy_tsk045_verify_20260812`; data-integrity queries confirming one family, four children, two values per child, two groups, and zero duplicate signatures. Automated tests were not created or run. Headed browser verification and screenshots were not executed because no authorized interactive browser-control capability was available in this session.
- **Definition of Done remaining:** complete the plan's manual headed Chromium scenario matrix, same-viewport screenshots, responsive/keyboard/RTL/LTR checks, thumbnail/media authorization checks, and end-to-end suspend/checkout/receipt/return checks; resolve any defects; obtain UAT/Production/release approvals separately.

## Backlog Status Confirmation

**Status snapshot:** 2026-08-08. The task-level statuses above are authoritative and classify work honestly as `Completed for approved Local/Dev scope`, `Implemented for approved Local/Dev scope`, or `In Progress`; they do not constitute UAT, Phase Gate, Production Readiness, Go-Live, or client sign-off.

### Phase status summary

| Phase | Tasks | Local/Dev scope | Remaining gate / blocker |
|---|---:|---|---|
| Phase 1 — Foundation and Controls | TSK-001–009 + TSK-004B | 6 completed for approved local scope; TSK-001, TSK-004B, TSK-005, and TSK-009 remain In Progress | Backup/restore capability, runbooks, print-preview/config gaps, platform guide work, and closure audit remain open |
| Phase 2 — Catalog, Purchasing, Pricing, Inventory | TSK-010–022 | All task slices implemented/completed for approved Local/Dev scope | Production master data, true queue/print delivery, owner approvals, UAT, and release gates remain open |
| Phase 3 — POS and Daily Finance | TSK-023–026 | All four discovery/readiness slices implemented and browser-verified | Financial, shift/cash, offline transaction/sync mutations, reconciliation, UAT, and Production remain open |
| Phase 4 — Customers, Loyalty, Wallets, Returns | TSK-027–030 | All four Local/Dev settings/foundation/readiness slices implemented and browser-verified | Customer/loyalty, wallet, Gift Card, refund/exchange/restock mutations, UAT, and Production remain open |
| Phase 5 — Parties and Assets | TSK-031–036 | All six Local/Dev Party/asset readiness slices completed and browser-verified | Booking, payment, operating order, asset, settlement, posting, UAT, and Production remain open |
| Phase 6 — Reporting, UAT, Launch | TSK-037–044 | All eight Local/Dev readiness slices completed and browser-verified, including TSK-043 UAT readiness and TSK-044 go-live readiness | Approved UAT owners/scenarios/data/devices/evidence, defect closure, reconciliation, production data/config, backup/restore, rollback, client approval, and Go-Live remain blocked |

### Overall counts

- Total task records: **46** (TSK-001–045 plus TSK-004B).
- In Progress: **4** — TSK-001, TSK-004B, TSK-005, TSK-009.
- Completed/implemented for approved Local/Dev scope: **41**.
- Fully accepted UAT/Production/Go-Live tasks: **0**.
- No task exists for creating or running automated tests; automated tests remain `Not Created / Not Run` under the current owner directive.
- Phase Gates, UAT acceptance, Production Readiness, and Go-Live remain open until the named owners provide the required approvals and evidence.

## Parallel Party implementation update - 2026-08-10

Owner-directed parallel-slice override for US-025 through US-027 only; historical task entries remain preserved.

| Task | Latest local/dev status | Boundary |
|---|---|---|
| TSK-031 / US-025 | FULL | Party booking, Party-only editable working invoice, schedule conflict recheck, audit, and print. |
| TSK-032 + TSK-036 / US-026 | FULL | Multiple Party payments, idempotent receipt, overpay guard, Party Wallet settlement, final close, audit, and print. |
| TSK-033 / US-027 | FULL local/dev | Operating order, Party-store issue/return, source-linked stock movement, authoritative US-028 asset reservation/checkout/return/inspection, reconciliation, completion, audit, and immutability. |

This local/dev status does not advance the Phase 1 gate or claim UAT, production configuration, physical printer delivery, backup/restore, or release approval.

### US-027 integration closure addendum — 2026-08-10

The remaining US-028 dependency is now implemented locally. **TSK-033 / US-027: FULL local/dev** for Party operating order, authoritative rental-asset reservation, scoped checkout/return/inspection, source-linked completion, audit, and immutability. The earlier row is retained as historical status; this addendum is the current owner-directed classification. US-028 remains the authoritative asset reservation domain; no second reservation system was added.
## TSK-046 — Production Setup UI Hardening

- **Milestone:** DM 6.4 release/configuration follow-on.
- **Status:** Implemented and MariaDB/UI-render verified locally; real Production data entry, independent approvals, headed-browser UAT, backup/restore, and release approval remain open.
- **Scope:** Provide one ordered `/initial-setup` UI and an optional pinned owner-data seeding path for company, locations, users/scopes, operational settings, catalog/variations, suppliers/SKUs, approved prices, controlled opening inventory, and optional genuine Customer/Party data. Ensure the System Administrator can access every guarded setup action without bypassing maker/checker rules.
- **Acceptance:** Nine stages render in dependency order; stages 1–8 use truthful readiness checks; Customer/Party stays optional; active locations inherit the active company; the System Administrator role contains every active permission; opening stock is posted only through approved inventory adjustments; no synthetic Production business or personal data is seeded.
- **Verification directive:** Do not create or run automated suites. Use XAMPP MariaDB, PHP syntax, Pint check-only, Blade/route caches, rendered UI contracts, data-integrity queries, and manual headed-browser verification when authorized tooling is available.
# Egyptian Feed Store ERP — 2026-09-15

**Status:** In progress. **Class:** I4. **Environment:** local MariaDB only.

Implement the owner-supplied phases 1–11 in dependency order: product units/feed fields; sale and purchase unit snapshots; customer AR; supplier AP; latest customer product price; purchase charges and landed cost; expenses; general cash accounts; optional inventory batches; factories; and `DemoFeedStoreSeeder`. Closure requires the authorized focused tests, existing suite, `migrate --seed`, and integrity audit. No production action or release is included.
