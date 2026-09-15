# AI Documentation Index

This is the mandatory task-aware documentation router for every AI coding agent. It preserves source authority while limiting context to what the active task needs.

> Do not read every file in `docs/` by default.
> Start from the active task and use this index to load only the required documentation.
> Expand reading only when a referenced requirement, conflict, dependency, blocker, or missing implementation detail requires it.

## Authority Order

1. `docs/02-prd.md` - authoritative for functional behavior.
2. Approved Implementation Plan (`docs/10-milestones.md`) - authoritative for Development Milestones, Delivery Criteria, and Phase Gates.
3. `.ai/DECISIONS.md` - authoritative for approved project decisions and exceptions.
4. `docs/04-roles-permissions.md` - authoritative authorization matrix.
5. `docs/08-architecture.md` - authoritative technical architecture.
6. `docs/17` through `docs/29` - approved implementation policies.
7. `docs/30` through `docs/58` - detailed local implementation specifications. `docs/30`–`docs/57` are present and routed below; `docs/58` is a reserved slot and must not be treated as content until a canonical file is supplied.
8. Owner-supplied derived specifications within that range remain subordinate to the PRD, approved decisions, roles/permissions, and architecture; owner-configurable defaults are not approvals.
9. `TASKS.md` - executable task scope and traceability.
10. `.ai/UI_SCREENS.md` - canonical screen registry.
11. Existing implementation - authoritative only for what is actually implemented, never for missing business rules.

No lower-priority document may silently override a higher-priority source. Record a real conflict in `.ai/DECISIONS.md` and stop before implementing contradictory behavior.

## Operational Governance Files

- `.ai/ERP_CHANGE_POLICY.md` is mandatory for task classification, separate source/production baselines, escalation, proportional verification, pending UI batching, immutable releases, source control, and reporting.
- `.ai/PENDING_CHANGE_BATCH.md` is the authoritative open ledger for completed, undeployed V0/V1 changes. Read and update it for V0/V1 work, and read it before any batch-closure or release workflow.

## Current Routing Override — Egyptian Feed Store ERP

The active owner-directed task is the 2026-09-15 Egyptian feed-store extension recorded in `.ai/CURRENT_TASK.md`. Route implementation through the existing Catalog, Purchasing, Retail, Customer, Inventory, Platform, factories, and seeders named by the owner-supplied phase plan. The plan explicitly preserves existing architecture, `products.product_type`, stock history, product-supplier relationships, returns, pricing, POS drawers/shifts, locking, and idempotency. The earlier client-feedback routing override below is historical while this task is active.

## Current Routing Override — Client Feedback Remediation (Expanded Master Request)

The active owner-directed work is the expanded client-feedback remediation request. Read `.ai/CURRENT_TASK.md`, `.ai/CURRENT_MILESTONE.md`, the Client Feedback Remediation status-sync section in `TASKS.md`, `docs/Master Change Request — Client Feedback Remediation & Setup UX Overhaul.md`, `docs/client-feedback-remediation-checklist.md`, and DEC-085/DEC-086 in `.ai/DECISIONS.md`. Route only the requirement, acceptance, screen, blocker, and evidence entries needed by the active remediation slice. The simplified CF queue is the factual status ledger; it does not narrow the expanded request. TSK-045 remains historical local implementation traceability and is not the active routing override.

The earlier TSK-004B override below is historical and is superseded while the expanded client-feedback remediation request is active.

TSK-004B is the shared Platform feature described in `docs/40-contextual-page-guide-specification.md`.


TSK-011 is the current closure-reviewed task in DM 2.1 and is Completed for approved local scope.

TSK-010 is completed for the approved local scope and must not be reopened unless a regression or dependency defect is discovered. TSK-011 must not be reopened unless a regression or dependency defect is discovered.

TSK-011 may extend the existing Product implementation only for:

- Full bilingual product-card fields.
- Approved product types.
- Searchable/reportable attributes.
- Protected product media.
- Product detail and full edit form.

Do not begin:

- TSK-012 staged Excel import.
- TSK-013 full supplier master/history.
- TSK-018 pricing labels.
- Inventory.
- Purchasing.
- POS.

TSK-017 pricing is authorized only as the documented reversible Local/Dev slice under `DEC-054`; do not infer Production pricing authority, Production branch exceptions, POS posting, UAT, or release approval.

Keep TSK-009 at its actual recorded status. Do not claim Phase 1 or Phase 2 gate completion.

## Minimal Mandatory Reading

For every coding or documentation task, read only:

1. `AGENTS.md`
2. `.ai/ERP_CHANGE_POLICY.md`
3. `AI_INDEX.md`
4. `.ai/CURRENT_TASK.md`
5. `.ai/CURRENT_MILESTONE.md`
6. The relevant task section in `TASKS.md`
7. The task-referenced entries in `.ai/BLOCKERS.md`
8. The task-referenced entries in `.ai/DECISIONS.md`
9. The latest relevant section of `.ai/HANDOFF.md`
10. The latest relevant session in `.ai/SESSION_SUMMARY.md`
11. The task-specific sources in the routing matrix below

Read latest relevant entries, not complete histories, when safe. Do not require full `.ai/PROGRESS.md`, `.ai/TEST_RESULTS.md`, `.ai/UI_SCREENS.md`, or the complete decision register unless the task requires them.

## Architecture Reading Rule

For implementation work, read the applicable portions of `docs/08-architecture.md`: architecture overview, module boundary, request/use-case lifecycle, authorization/security, states, transactions/concurrency, and deployment as relevant. Read the complete file only for an architecture change, multi-module work, a conflict, a proposed package/shared abstraction, or when the correct module boundary cannot be established.

| Topic | Required `docs/08-architecture.md` sections |
|---|---|
| Platform | 1 Architecture, 2 Platform, 6 Request Flow, 14 Deployment & Packages |
| Authentication | 6 Request Flow, 8 Authorization & Data Scope |
| PWA / offline | 10 Routes/Views/UI, 12 Queue/Cache/Offline, 13 Concurrency |
| Catalog | 2 Catalog, 11 Files/Import/Print/Reporting |
| Purchasing | 2 Purchasing, 7 States/Immutability, 9 Inventory & Value Ledgers, 13 Concurrency |
| Inventory | 2 Inventory, 7 States/Immutability, 9 Inventory & Value Ledgers, 13 Concurrency |
| POS | 2 Retail, 7 States/Immutability, 10 Routes/Views/UI, 12 Offline, 13 Concurrency |
| Cash control | 2 CashControl, 7 States/Immutability, 13 Concurrency |
| Customers / wallets | 2 Customers, 8 Authorization, 9 Customer Value |
| Parties / assets | 2 Parties/Assets, 7 States/Immutability, 9 Ledgers, 13 Concurrency |
| Reporting | 2 Reporting, 8 Authorization, 11 Files/Reporting |
| Release | 12 Queue/Cache/Offline, 14 Deployment & Packages |

## PRD and Milestone Rules

Locate and read only the PRD requirement IDs listed for the active task in `TASKS.md`, plus directly related NFR definitions. Read the full PRD only for broad cross-module scope, a functional contradiction, unresolved IDs, scope/exclusion changes, or a Phase 1-wide rule. Read the active DM description, Delivery Criteria, required inputs, and relevant Phase Gate in `docs/10-milestones.md`, not the complete plan for a narrow task.

## Task Routing Matrix

Abbreviations: `A` = architecture topics above; `D` = `docs/36-module-data-contracts.md`; `U` = `docs/37-ui-screen-specifications.md` plus only listed IDs in `.ai/UI_SCREENS.md`; `P` = `docs/38-print-export-specification.md`; `G` = relevant `docs/12`, `docs/13`, and `docs/39` gate/acceptance sections. Every row also uses its `TASKS.md` PRD IDs and active DM/DC.

| Task | Milestone | Mandatory policy/specification docs | A | Schema/data docs | UI docs | Print/export docs | Acceptance/release docs |
|---|---|---|---|---|---|---|---|
| 001 | DM 1.1 | 30, 21 | Platform | D where platform records change | UI-SYS-003,004,010 | 38 when backup/health output exists | 39 for backup/release evidence |
| 002 | DM 1.1 | 30, 04 | Authentication | D only if identity/session entities change | UI-AUTH-001,002; UI-SYS-008,009 | N/A | G |
| 003 | DM 1.1 | 20, 30 | PWA/offline | D if offline records change | UI-SYS-001,002; UI-OFF-001 | N/A | G |
| 004 | DM 1.2 | 20, 37, 56, 57 | Platform | N/A | referenced UI-SYS IDs | N/A | G |
| 005 | DM 1.2 | 30, 36, 38 | Platform | D | UI-ADM-002,006-009 | 38 | G |
| 006 | DM 1.2 | 30, 35, 36 | Platform | D | UI-ADM-003,004 | N/A | G |
| 007 | DM 1.2 | 30, 32, 35, 36 | Cash control | D | UI-ADM-005 | 38 | G |
| 008 | DM 1.3 | 04, 16, 30 | Authentication | D for scope relations | UI-ADM-010-012; UI-SYS-009 | N/A | G |
| 009 | DM 1.4 | 17, 18, 19, 30, 35, 36 | Platform | D | UI-SYS-005,006; UI-AUD-001 | 38 when output exists | G |
| 010 | DM 2.1 | 23, 35, 36, 37, 38, 56, 57 | Catalog | D | UI-CAT-001-003,006,007 | 38 | G |
| 011 | DM 2.1 | 18, 19, 23, 36, 37, 57 | Catalog | D | UI-CAT-001-003 | 38 when media/export applies | G |
| 012 | DM 2.1 | 19, 23, 35, 36, 37, 38, 56, 57 | Catalog | D | UI-CAT-004,005 | 38 | G |
| 013 | DM 2.1 | 23, 36, 37 | Catalog/Purchasing | D | UI-CAT-008 | N/A | G |
| 014 | DM 2.2 | 17, 19, 35, 36, 37, 38, 56, 57 | Purchasing | D | UI-PUR-001 | 38 | G |
| 015 | DM 2.2 | 17, 18, 19, 35, 36, 37, 38, 56, 57 | Purchasing/Inventory | D | UI-PUR-002; UI-INV-002,003 | 38 | G |
| 016 | DM 2.2 | 17, 19, 35, 36, 37, 38, 57 | Purchasing/Inventory | D | UI-PUR-003 | 38 | G |
| 017 | DM 2.3 | 24, 35, 36, 37, 38 | Catalog/POS | D | UI-PRC-001,002; UI-POS-001 | 38 | G |
| 018 | DM 2.3 | 24, 35, 36, 37, 38 | Catalog | D | UI-PRC-003 | 38 | G |
| 019 | DM 2.4 | 25, 35, 36, 37, 56, 57 | Inventory | D | UI-INV-001-003 | 38 when reports print | G |
| 020 | DM 2.4 | 17, 19, 25, 35, 36, 37, 38, 56, 57 | Inventory | D | UI-INV-004-007 | 38 transfer outputs | G |
| 021 | DM 2.4 | 17, 19, 25, 35, 36, 37 | Inventory | D | UI-INV-011 | 38 when document output exists | G |
| 022 | DM 2.4 | 17, 19, 25, 35, 36, 37, 38, 56, 57 | Inventory | D | UI-INV-008-010 | 38 | G |
| 023 | DM 3.1 | 24, 31, 32, 35, 36, 37, 38, 57 | POS/Cash/PWA | D | UI-POS-001,002,006,007 | 38 | G |
| 024 | DM 3.2 | 18, 19, 24, 26, 31, 32, 35-38, 57 | POS/Cash | D | UI-POS-001,007; UI-SYS-005 | 38 | G |
| 025 | DM 3.3 | 17, 19, 26, 32, 35-38, 57 | Cash control | D | UI-POS-003-005 | 38 | G |
| 026 | DM 3.4 | 30, 31, 32, 35-38, 51, 57 | POS/PWA | D | UI-OFF-001-003; UI-POS-001 | 38 when receipt/conflict output exists | G |
| 027 | DM 4.1 | 27, 35-37, 52, 57 | Customers | D | UI-CUS-001-003 | 38 when output exists | G |
| 028 | DM 4.2 | 19, 27, 35-37, 52, 57 | Customers/wallets | D | UI-CUS-002,004,005 | 38 when statement/export exists | G |
| 029 | DM 4.3 | 26, 27, 31, 35-38, 52, 57 | POS/Customers | D | UI-POS-010,011 | 38 | G |
| 030 | DM 4.4 | 26, 31, 35-38, 57 | POS/Inventory | D | UI-POS-008-010 | 38 | G |
| 031 | DM 5.1 | 28, 33, 35-38, 57 | Parties | D | UI-PTY-001-003 | 38 | G |
| 032 | DM 5.2 | 19, 27, 28, 33, 35-38, 57 | Parties/Customers | D | UI-PTY-004; UI-CUS-005 | 38 | G |
| 033 | DM 5.3 | 28, 33, 35-38, 57 | Parties/Inventory | D | UI-PTY-005,006 | 38 | G |
| 034 | DM 5.4 | 18, 19, 29, 33, 35-38, 57 | Parties/assets | D | UI-PTY-007-012 | 38 | G |
| 035 | DM 5.4 | 17, 18, 19, 29, 33, 35-38, 57 | Parties/assets | D | UI-PTY-012-014 | 38 | G |
| 036 | DM 5.5 | 17, 19, 27-29, 33, 35-38, 57 | Parties/assets | D | UI-PTY-015 | 38 | G |
| 037 | DM 6.1 | 17, 19, 35-38, 57 | POS/Parties | D | UI-QTN-001 | 38 | G |
| 038 | DM 6.1 | 34, 36-38, 50, 55, 56 | Reporting | D/read models | UI-ADM-001; UI-RPT-001 | 38 | 39 |
| 039 | DM 6.1 | 34, 36, 37, 39, 50, 55 | Reporting | D if alert state persists | UI-ADM-001; UI-SYS-007; UI-RPT-001 | N/A | 39 |
| 040 | DM 6.2 | 19, 34, 36-38, 50, 55, 56 | Reporting | D | UI-RPT-002; UI-AUD-001; UI-OFF-003 | 38 | 39 |
| 041 | DM 6.4 | 19, 23, 25, 36-39, 54 | Platform/catalog/inventory | D | approved import/admin screen IDs | 38 | 39 |
| 042 | DM 6.4 | 30, 31, 32, 34, 38, 39, 53, 56 | Release | D only for approved config records | relevant existing IDs | 38 | 39 |
| 043 | DM 6.3 | 34, 38, 39 | Release | N/A | scenario-specific IDs only | 38 | 39 |
| 044 | DM 6.4 | 30, 34, 38, 39, 53 | Release | N/A | production-smoke IDs only | 38 | 39 |
| 045 | DM 2.1 / DM 3.1 follow-on | 18, 19, 23, 24, 25, 31, 35, 36, 37, 38, 56, 57 | Catalog/Pricing/Inventory/Purchasing/POS | D | UI-CAT-001-003,009; UI-POS-001,007 | 38 for labels/receipts | G |
| 046 | DM 6.4 follow-on | 04, 16, 30, 34, 36, 37, 53, 54, 57 | Platform/release and all setup dependencies | D for configured masters | existing setup/admin/catalog/pricing/inventory/customer/Party IDs | 38 where configured output applies | 39 |

### Canonical Derived Documents 41–57

The following numbered documents are now present in the repository and must be read only when their topic is relevant:

- `docs/41`–`docs/46` — TSK-015 purchase cost/tax/discount, invoice import, receiving/matching, opening stock, authorization, and financial configuration/cutover specifications; the documented local policy baseline is approved under DEC-050; production operational inputs remain pending.
- `docs/47-inventory-ledger-and-cost-flow-policy.md` — inventory movement ledger, cost flow, valuation, and movement invariants.
- `docs/48-pos-financial-calculation-policy.md` — POS arithmetic, tax/discount ordering, payments, receipts, gift receipts, and cashier-shift boundaries.
- `docs/49-validation-and-error-contracts.md` — validation layers, error shape, state/concurrency, imports, and shared UI states.
- `docs/50-reporting-formula-catalog.md` — reporting formulas, inventory ageing/turnover/dead stock, profitability, and export verification.
- `docs/51-offline-pos-operating-policy.md` — restricted offline POS behavior, limits, provisional records, synchronization, and conflict review.
- `docs/52-wallet-loyalty-giftcard-financial-rules.md` — separated wallet ledgers, loyalty points, gift-card liability, and audit rules.
- `docs/53-deployment-backup-and-rollback-runbook.md` — deployment, backup/restore, verification, rollback, and monitoring procedures; infrastructure choices remain open.
- `docs/54-production-data-migration-and-reconciliation.md` — production data migration, reconciliation, dry run, cutover, and rollback boundaries.
- `docs/55-chart-and-visualization-specification.md` — chart catalog, formula/permission boundaries, drilldown, RTL, accessibility, and performance requirements.
- `docs/56-large-dataset-performance-specification.md` — indexes, period snapshots, bounded queries, Livewire table conventions, table UI at scale, lazy loading, caching, background work, and observability; deferred techniques remain measurement-gated.
- `docs/57-ui-interaction-and-data-entry-standard.md` — keyboard/scanner focus loops, draft autosave, duplicate submission protection, numeric/selection input, feedback, error prevention, Arabic behavior, role-aware controls, and POS interaction.
- `.ai/TSK-015_DOC_COVERAGE_REPORT.md`, `.ai/TSK-015_OWNER_INPUTS.md`, `.ai/TSK-015_IMPLEMENTATION_PLAN.md`, `.ai/DEC-043_TSK-015_ENGINEERING_BASELINE.md`, `.ai/DEMO_MASTER_DATA.md` — TSK-015 control and provenance records.
- `.ai/TSK-038_ANALYTICS_ADDENDUM.md` — proposed TSK-038 scope addition for inventory analytics and profitability; owner approval remains outstanding.
- `.ai/PERFORMANCE_TASKS.md` — proposed TSK-P01–TSK-P12 performance task set; Group D remains explicitly deferred until measured need.

These documents define derived specifications and proposed defaults. They do not authorize implementation, production data, production roles, financial posting, or owner decisions that remain marked `PENDING`/`OPEN`.

### TSK-009 Slice Routing and Canonical Filenames

For the Immutability and Correction Foundation slice, read the TSK-009 row above plus `docs/17-approval-policy.md`, `docs/18-attachment-media-policy.md`, `docs/19-audit-immutability-policy.md`, `docs/30-platform-operations-specification.md`, `docs/35-document-state-machines.md`, `docs/36-module-data-contracts.md`, `docs/37-ui-screen-specifications.md`, and `docs/38-print-export-specification.md`. No current Platform master is a correction source, so source-module UI and browser verification remain deferred.

The canonical numbered files are `docs/37-ui-screen-specifications.md` and `docs/38-print-export-specification.md`. Do not route tasks to alternate filenames; historical status notes may mention earlier missing names without making them active dependencies.

### TSK-020 Exact Minimal Reading Set

`AGENTS.md`; this index; `.ai/CURRENT_TASK.md`; `.ai/CURRENT_MILESTONE.md`; the TSK-020 section in `TASKS.md`; BLK-012; applicable decisions; PRD `INV-03`; the DM 2.4 section in `docs/10-milestones.md`; architecture sections 2 Inventory, 6, 7, 9, and 13; `docs/17-approval-policy.md`; `docs/19-audit-immutability-policy.md`; `docs/25-inventory-exception-policy.md`; `docs/35-document-state-machines.md`; `docs/36-module-data-contracts.md`; only TSK-020 IDs in `.ai/UI_SCREENS.md`; relevant `docs/37` behavior; transfer outputs in `docs/38`; `docs/09-coding-standards.md`; relevant `docs/15` controls; and the linked portions of `docs/12` and `docs/13`. It does not require customer, POS, loyalty, party, asset, reporting, UAT, or unrelated product documents unless a discovered dependency requires them.

## Conditional Reading

| Condition | Additional reading |
|---|---|
| Migration/model | 36 and relevant `docs/07` section |
| State change | 35 and 17 |
| Approval | 17, 19, 04 |
| Attachment | 18, 19 |
| User-facing screen | 37 and relevant UI registry IDs |
| Thermal/A4/PDF/Excel/label | 38 |
| Financial or stock posting | architecture transaction/concurrency sections, 19, 36 |
| Report/dashboard/alert | 34 and 38 |
| Offline behavior | 30, 31, architecture offline sections |
| Interactive line entry / scanner / keyboard focus / UI feedback | 20, 37, 57, `.ai/UI_SCREENS.md` |
| High-volume table / pagination / caching / background exports / DB indexing | 56, `.ai/PERFORMANCE_TASKS.md` |
| Milestone/phase closure | 39 and active DM/DC |
| Package | 21 and architecture packages section |
| Authorization | 04 and 16 |
| Retail/party boundary | PRD separation rule, 27, 28, 33 |
| Contradiction | PRD, relevant decision, blocker; then stop and report |
| V0/V1 implementation or pending-batch closure | `.ai/PENDING_CHANGE_BATCH.md` |

## Inspection Strategy and Prohibitions

1. Resolve active task and read its row.
2. Read the minimal state files and task section.
3. Read listed requirements, DM/DC, and policies/specifications.
4. Search exact entity, class, route, table, screen ID, or permission names.
5. Inspect only relevant routes, migrations, models, Livewire components, policies, and views.
6. Expand only for a verified dependency or implementation/enforcement point.

Do not recursively read the repository, all `docs/`, whole historical logs, unrelated task sections, or documents already safely inspected in the session. Do not scan `vendor/`, `node_modules/`, generated assets, caches, logs, or storage by default. Do not inspect every application file before an initial task plan. For absence checks, search exact names and relevant module directories first; never infer absence from one missing expected path.

## Maintenance

Update this index when a task is added/renumbered, a policy/specification is added, a dependency or authority changes, a screen ID is added, a document is renamed, or a milestone/phase gate changes. This index routes reading; it never replaces authoritative sources.
