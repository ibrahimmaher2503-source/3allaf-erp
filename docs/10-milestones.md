# 10 — Implementation Milestones

## Governance

Each Development Milestone (DM) is a complete, demonstrable, manually verifiable increment. `Status` begins as `Not Started`. Only one DM may be active. A Phase Gate reviews delivered scope, actual evidence, open issues, required business data, role/audit behavior, client feedback, and written approval before the next phase. Material changes follow change control and update all traceability artifacts.

All backend deliverables below include validated routes/use cases, policies/gates, database design implementation, transactions/locks/idempotency where required, state transitions, attachments, audit, and error handling. All UI deliverables include Flux-first responsive Arabic RTL/English LTR screens and relevant default/loading/empty/error/success/disabled/denied/validation/confirmation/print states.

## Phase 1 — Foundation, Access and Operational Controls

### DM 1.1 — Platform Foundation

- **Scope / related requirements:** Laravel application, responsive/PWA baseline, environments, backup, audit framework, error monitoring; NFR-01–NFR-07 and platform portions of MD-01.
- **Dependencies / required inputs:** BLK-001–BLK-005; runtime/hosting/database/domain, Redis/queue/scheduler, storage/backup/monitoring, browsers/devices, authentication/session and offline policy.
- **Backend deliverables:** verified one-repository Laravel modular-monolith foundation; sessions/security/error handling; locale/context foundation; audit/attachment/approval/numbering primitives; queue/scheduler/cache decisions; backup/restore/monitoring baseline; restricted PWA shell.
- **UI deliverables / Screen IDs:** Auth and shared shells, profile, permission-denied/system-error, connectivity/update states; UI-AUTH-001–002, UI-SYS-001–010, UI-OFF-001.
- **Data requirements:** users/security context, settings/audit/attachment/approval primitives only; no production master data inferred.
- **Permissions / audit:** authenticated sessions, broad platform gates, session/device/scope events, append-only audit; no role grant implied.
- **Manual verification:** sign-in/reset/session/CSRF/errors; responsive RTL/LTR shells; access denial; PWA update/expiration; attachment baseline; backup creation and restore; monitoring signal.
- **Delivery / handoff / status:** demonstrable foundation, smoke-path evidence, backup/restore and baseline security review, actual commands/runbook once project exists; hand off architecture/version/known limitations. **Status: Not Started.**

### DM 1.2 — Organisation and Branch Setup

- **Scope / related requirements:** company, branches, stores/default selling-store mapping, drawers, payments, tax, numbering, printers/templates; MD-01, INV-02, CSH-01, NFR-06.
- **Dependencies / required inputs:** DM 1.1; BLK-006 and BLK-008; approved lists, invoice identity/tax/methods/numbering/printer requirements.
- **Backend deliverables:** masters, scoped configuration, unique sequences, selling-store mapping, lifecycle rules, policies and audit.
- **UI deliverables / Screen IDs:** UI-ADM-002–009.
- **Data requirements:** approved company/branch/store/drawer/payment/tax/sequence/printer records.
- **Permissions / audit:** Administrator manage; Branch Manager limited proposed rights; sensitive changes/overrides approved and audited.
- **Manual verification:** create/update/deactivate safely, duplicate prevention, mapping/sequence concurrency, unauthorized edits, print-template configuration.
- **Delivery / handoff / status:** settings demonstrable and business data validated; hand off approved masters and open exceptions. **Status: Not Started.**

### DM 1.3 — Users, Roles and Permissions

- **Scope / related requirements:** role-based access, branch/store/activity/action boundaries for all source roles; MD-01, CUS-02, NFR-03.
- **Dependencies / required inputs:** DM 1.2; BLK-005 and BLK-007; approved canonical names and matrix.
- **Backend deliverables:** user lifecycle, roles, atomic permissions, branch/store scopes, policies/gates, sensitive field/export/approval/override rules.
- **UI deliverables / Screen IDs:** UI-ADM-010–012 plus denied states across every layout.
- **Data requirements:** approved users/roles/scopes; no default sensitive grant.
- **Permissions / audit:** least privilege, server enforcement, grant/revoke audit, last-admin/self-lockout protection.
- **Manual verification:** sign in as every role; allowed/denied modules/actions/scopes; direct URL/action denial; wallet and expected-close field isolation.
- **Delivery / handoff / status:** all approved roles see only permitted areas/branches; hand off signed matrix and account list. **Status: Not Started.**

### DM 1.4 — Core Controls

- **Scope / related requirements:** approval states, audit fields, attachments, immutable history; NFR-01–NFR-04 and NFR-06.
- **Dependencies / required inputs:** DM 1.3; approval rules, attachment policy, numbering, retention.
- **Backend deliverables:** reusable but non-generic approval records, per-document transition pattern, immutable/source-reference controls, protected attachment access, append-only audit and correlation.
- **UI deliverables / Screen IDs:** document timeline/audit panel/upload/confirmation patterns; UI-AUD-001 and relevant UI-SYS states.
- **Data requirements:** approval/action/reason catalogs and attachment constraints.
- **Permissions / audit:** explicit Approve/Override/Reverse/Cancel/Logical Delete/Export; all framework events self-auditing.
- **Manual verification:** invalid transitions, approval separation, direct-edit prevention, safe attachments, before/after/source context, duplicate numbers/actions.
- **Delivery / handoff / status:** critical actions capture required context and history; hand off state/audit/attachment conventions. **Status: Not Started.**

### Phase 1 Delivery Criteria / Phase Gate

- All approved user roles can sign in and only view permitted areas and branches.
- Branch, warehouse/store, cash drawer, tax, numbering, and payment-method settings operate correctly.
- Critical actions record user, timestamp, branch/context, and before/after data where applicable.
- Technical smoke verification, backup/restore verification, and baseline security review are passed manually and evidenced.
- Inputs for Phase 2 are approved: branch/store list, role/permission matrix, company invoice/tax/payment/printer details, product hierarchy/catalog/barcodes, suppliers/opening inventory, and pricing approval rules.
- Phase Gate record includes demonstration, evidence, critical-defect closure, master-data validation, role/audit verification, client review/open items, handoff package, and written approval.

## Phase 2 — Product Catalog, Purchasing, Pricing and Inventory

### DM 2.1 — Product and Supplier Masters

- **Scope / related requirements:** products, stable codes/barcodes, bilingual attributes/images, types, categories/brands, suppliers/preference/history; MD-02–MD-05, PRC-01–PRC-02, PUR-01–PUR-02.
- **Dependencies / required inputs:** Phase 1 gate; BLK-009–BLK-010; hierarchy, codes, product/import data, supplier records.
- **Backend deliverables:** masters/relations/unique constraints, image protection, type rules, supplier preference history, staged Excel mapping/validation/error report.
- **UI deliverables / Screen IDs:** UI-CAT-001–008.
- **Data requirements:** approved catalog/supplier/import templates; stable item code independent of barcode.
- **Permissions / audit:** product/supplier/import/create/update/approve/export-error; preferred supplier/barcode/type/status/image changes audited.
- **Manual verification:** manual creation, all product types, bilingual/search fields, barcode uniqueness/format, image limit, create-only/update import, invalid-row isolation.
- **Delivery / handoff / status:** catalog/supplier masters and import are demonstrable; hand off validated data/error log. **Status: Not Started.**

### DM 2.2 — Purchase Cycle

- **Scope / related requirements:** purchase orders/invoices, Excel import, optional tax, receipt, weighted-average cost, supplier returns/history; PUR-03–PUR-06, PRC-03.
- **Dependencies / required inputs:** DM 2.1; purchasing authorization, templates, opening-stock and tax/discount rules.
- **Backend deliverables:** PO state machine, invoice/receipt approval transaction, import, movements, average-cost calculation, source-linked returns.
- **UI deliverables / Screen IDs:** UI-PUR-001–003.
- **Data requirements:** suppliers/products/stores, purchase references, approved cost/rounding policy.
- **Permissions / audit:** entry/import/submit/receipt/approve/return/reverse, cost access; every state/cost/movement/source audited.
- **Manual verification:** all PO states, partial/full receipt, duplicate prevention, formula/reconciliation, import errors, supplier return, sale price remains unchanged.
- **Delivery / handoff / status:** approved receipt changes correct store stock and cost traceably; hand off purchasing evidence and formula examples. **Status: Not Started.**

### DM 2.3 — Pricing and Barcode Labels

- **Scope / related requirements:** location prices, immutable versions/approval, one active shelf price, pending/unpriced, open price, label queues/reprints; PRC-03–PRC-08.
- **Dependencies / required inputs:** DM 2.2; BLK-011 and device/print inputs; authority, exceptions, effective rules, label templates.
- **Backend deliverables:** proposal/import/source, version state machine, atomic activation, open-price policy, location queue calculation and print events.
- **UI deliverables / Screen IDs:** UI-PRC-001–003 and POS pending/open-price states.
- **Data requirements:** price lists, location prices, approval/limits/reasons, printers/labels.
- **Permissions / audit:** propose/approve/exception/open-price/print/reprint; all values/reasons/locations/events audited.
- **Manual verification:** historical sale preservation, concurrent approval, one active price, unpriced block, queue quantity/location price, branch exception, authorized/in-range open price.
- **Delivery / handoff / status:** label output, scanning/lookup, price controls and unpriced alerts verified; hand off approved versions/templates. **Status: Not Started.**

### DM 2.4 — Inventory Operations

- **Scope / related requirements:** balances/availability, transfers, entries/exits/adjustments, negative/fractional controls, stock cards, full/partial concurrent counts; INV-01–INV-09.
- **Dependencies / required inputs:** DM 2.3; BLK-012; store policy, reasons, transfer/count/difference approvals.
- **Backend deliverables:** append-only movement ledger/materialized balances, transfer state machine/in-transit, adjustment docs, count snapshots/movement reconciliation/uncounted review.
- **UI deliverables / Screen IDs:** UI-INV-001–011.
- **Data requirements:** opening inventory, store mappings, reason/disposition lists, assigned counters.
- **Permissions / audit:** store scope, source/destination transition rights, Counter vs Manager separation, negative override; all movements/count edits audited.
- **Manual verification:** balance/movement reconciliation, other-branch informational availability, partial receipt/damage/refusal, negative/fractional rules, selling during counts, no uncounted zero.
- **Delivery / handoff / status:** no critical discrepancy between movement ledger and balances; hand off stock reconciliation and open differences. **Status: Not Started.**

### Phase 2 Delivery Criteria / Phase Gate

- A received purchase invoice increases the correct store stock and updates cost records.
- Price changes affect only remaining stock and future transactions; prior invoices remain unchanged.
- Transfer, return, count, and adjustment flows leave traceable stock movements.
- Barcode scanning, product lookup, label output, and low-stock alerts are verified.
- No critical discrepancy exists between stock movement ledger and calculated balances.
- Inputs for Phase 3 are approved: POS workflow/receipts, discount matrix, drawer allocation, and shift-closing procedure.
- The standard Phase Gate demonstration, evidence, defect closure, data/policy validation, role/audit review, handoff, and written approval are complete.

## Phase 3 — Point of Sale, Cash Drawers and Daily Finance

### DM 3.1 — POS Checkout

- **Scope / related requirements:** scan/search, cart/quantity, location price, customer, suspended sales, optional tax, thermal/A4; POS-01–POS-02, POS-04, INV-02.
- **Dependencies / required inputs:** Phase 2 gate; POS workflow, hardware, receipts, store mappings.
- **Backend deliverables:** sale/suspended-sale lifecycle, server repricing/stock locks, source links, atomic approval/number/movements.
- **UI deliverables / Screen IDs:** dedicated UI-POS-001–002, 006–007.
- **Data requirements:** products/prices/stock/customer/store/shift/drawer.
- **Permissions / audit:** POS, quantity, customer, suspend/retrieve, print, store rule; full sale context audited.
- **Manual verification:** barcode/name/code, keyboard/touch, cart retention, hold/retrieve, other-branch block, unpriced/negative rules, concurrent stock, thermal/A4.
- **Delivery / handoff / status:** cashier completes/holds/retrieves/prints approved invoice. **Status: Not Started.**

### DM 3.2 — Discount and Payment Rules

- **Scope / related requirements:** one discount type, cash/manual electronic payment, evidence, optional tax totals and print breakdown, open price; PRC-08, POS-03–POS-06.
- **Dependencies / required inputs:** DM 3.1; BLK-008 and BLK-013; methods/evidence/tax/discount authority.
- **Backend deliverables:** payment records/evidence authorization, one-discount invariant/replacement, total calculation snapshots, open-price controls.
- **UI deliverables / Screen IDs:** settlement/evidence/discount/tax/print states in UI-POS-001 and UI-POS-007.
- **Data requirements:** methods, tax versions, discount/open-price limits/reasons.
- **Permissions / audit:** payment/tax/discount/open price/evidence/approve; all overrides and attachments audited.
- **Manual verification:** evidence types/access, stacked-discount block/replacement, tax selection, exact totals/rounding, range/reason, duplicate submit.
- **Delivery / handoff / status:** totals and evidence reconcile and print correctly. **Status: Not Started.**

### DM 3.3 — Cash Drawer and Shift Cycle

- **Scope / related requirements:** opening float, linked collections/disbursements, blind closing, variance review, thermal/A4 reports; CSH-01–CSH-04.
- **Dependencies / required inputs:** DM 3.2; drawer allocations/shift procedures/variance approvals.
- **Backend deliverables:** exclusive active shifts, cash movements, actual/expected totals, blind submission and immutable review/close.
- **UI deliverables / Screen IDs:** UI-POS-003–005.
- **Data requirements:** drawers, payment methods, opening balances, review owners.
- **Permissions / audit:** Cashier own shift, no expected before submission; Manager expected/variance/approve; every amount/state audited.
- **Manual verification:** conflict opening, transaction linkage, cash movement, blind close data leak check, variance, thermal/A4 reconciliation.
- **Delivery / handoff / status:** closing reports reconcile to sales and cash movements. **Status: Not Started.**

### DM 3.4 — Operational Integrity

- **Scope / related requirements:** complete sale linkage and controlled offline/PWA continuity; POS-02–POS-05, NFR-01, NFR-04, NFR-06.
- **Dependencies / required inputs:** DM 3.3; DEC-018/BLK-004 and approved device/limit/conflict policy.
- **Backend deliverables:** offline device/session eligibility, idempotent provisional queue/sync, server revalidation/numbering, conflict records/review, expiry/cleanup.
- **UI deliverables / Screen IDs:** connectivity/cart continuity plus UI-OFF-001–003.
- **Data requirements:** enrolled devices, cached policy/version, limits, conflict ownership/dispositions.
- **Permissions / audit:** OfflinePOS/Sync/Resolve; device/user/branch/shift bound; every local/sync/conflict event audited.
- **Manual verification:** only permitted payments, prohibited offline features, expiration/logout, reconnect retries, duplicates, server truth, conflicts and recovery.
- **Delivery / handoff / status:** sale linkage and approved offline scope are traceable; hand off device/support/conflict runbook. **Status: Not Started.**

### Phase 3 Delivery Criteria / Phase Gate

- Cashier can complete, hold, retrieve, and print an approved invoice using permitted methods.
- Invoice totals show gross items, discount, net, selected tax, and final total accurately.
- Cashier cannot view expected closing total before submission; authorized managers can review variance.
- Electronic payment proof is retained with the relevant transaction.
- End-of-shift report totals reconcile to recorded sales and permitted cash movements.
- Inputs for Phase 4 are approved: loyalty, Gift Card/expiry, return-condition, and refund-authorization policies.
- Standard Phase Gate evidence, defects, role/audit checks, handoff, client review, and written approval are complete.

## Phase 4 — Customers, Loyalty, Gift Cards and Returns

### DM 4.1 — Customer Profile and Loyalty

- **Scope / related requirements:** unique phone, consent/contact/children, unified history, shared activity-rule loyalty; MD-06, CUS-01, CUS-03–CUS-04.
- **Dependencies / inputs:** Phase 3 gate; BLK-014; consent/duplicate/loyalty rules.
- **Backend:** customer/child masters, duplicate prevention/controlled resolution, immutable loyalty ledger/rules/expiry/concurrency.
- **UI / Screens:** UI-CUS-001–003.
- **Data/permissions/audit:** purpose-scoped customer fields; loyalty view/earn/redeem/adjust; consent, access/export, and movements audited.
- **Manual verification:** duplicate phone, histories, consent, retail/party earn rates, redemption/expiry/insufficient/concurrent/offline blocks.
- **Delivery / handoff / status:** registration prevents unintended duplicates and shared loyalty is traceable. **Status: Not Started.**

### DM 4.2 — Separated Wallets

- **Scope / related requirements:** Product Wallet and Party Wallet separate in data, UI, permissions, history, settlement/reporting; CUS-02, CUS-04.
- **Dependencies / inputs:** DM 4.1; wallet policy/limits/owners.
- **Backend:** independent append-only ledgers/actions/policies and source reconciliation; no generic transfer.
- **UI / Screens:** UI-CUS-004–005 and role-redacted customer profile.
- **Data/permissions/audit:** wallet-specific source/movement data; Cashier Party Wallet N; Party Manager Product Wallet N; every movement/access audited.
- **Manual verification:** cross-role direct URL/query/export denial, source/balance reconciliation, concurrent settlements, referenced corrections.
- **Delivery / handoff / status:** wallets remain separate in UI, permissions, and history. **Status: Not Started.**

### DM 4.3 — Gift Cards and Gift Receipts

- **Scope / related requirements:** price-free Gift Receipt; unique Gift Card issue/balance/validity/use/void; POS-07, RET-04.
- **Dependencies / inputs:** DM 4.2; gift policies and print format.
- **Backend:** Gift Receipt references/privacy/reprint/use; locked Gift Card summary + immutable ledger.
- **UI / Screens:** UI-POS-010–011.
- **Data/permissions/audit:** source/holder/validity/value; issue/redeem/void/print rights; every event audited.
- **Manual verification:** no prices, reference validation, duplicate/reprint, partial/full/concurrent/expired/void card behavior.
- **Delivery / handoff / status:** instruments are traceable and balance-safe. **Status: Not Started.**

### DM 4.4 — Returns and Exchanges

- **Scope / related requirements:** validate source, condition review, same/different exchange, refund/Gift Card, approved stock return; RET-01–RET-03.
- **Dependencies / inputs:** DM 4.3; BLK-013; return/condition/refund/approval/non-saleable policies.
- **Backend:** referenced return/exchange state/settlement, over-return prevention, condition disposition and movements.
- **UI / Screens:** UI-POS-008–009 and Gift Receipt/Card links.
- **Data/permissions/audit:** original source, lines, condition, settlement, approvals; exception rights and every effect audited.
- **Manual verification:** all four outcomes, difference settlement, damaged/non-saleable path, source/value/quantity constraints, unauthorized exceptions.
- **Delivery / handoff / status:** stock and settlement update correctly with original reference. **Status: Not Started.**

### Phase 4 Delivery Criteria / Phase Gate

- Customer search and registration prevent unintended duplicate profiles.
- Product Wallet and Party Wallet remain separate in UI, permissions, and accounting/operational history.
- Gift Card issue, balance check, and redemption are traceable.
- Return/exchange updates stock and settlement correctly and retains original reference.
- All return exceptions require the assigned approval level.
- Inputs for Phase 5 are approved: party packages/services, consumables, rental register, deposit/payment and cancellation policy, operating/asset checklists.
- Standard Phase Gate evidence, critical-defect closure, data/policy, role/audit review, handoff, and written approval are complete.

## Phase 5 — Party Booking and Asset Operations

### DM 5.1 — Booking and Preliminary Party Invoice

- **Scope / requirements:** separate party workflow/stores, customer/child/schedule/location/plans/responsibilities, editable working invoice; PTY-01–PTY-03.
- **Dependencies / inputs:** Phase 4 gate; BLK-015; party masters, stores, schedule/cancellation/edit policy.
- **Backend:** booking/working-invoice states, typed party lines, schedule/conflict hooks, immutable change history.
- **UI / Screens:** UI-PTY-001–003.
- **Data/permissions/audit:** party customer/lines/schedule/responsibilities; party scope; every amendment/reschedule/cancel audited.
- **Manual verification:** no retail mixing, required data, calendar, editable-before/frozen-after close, permission/cancellation paths.
- **Delivery / handoff / status:** booking can be created/amended without exposing retail finances. **Status: Not Started.**

### DM 5.2 — Deposits and Party Wallet

- **Scope / requirements:** multiple party payments on account and separate receipts, party-only balances; PTY-04, CUS-02, CUS-04.
- **Dependencies / inputs:** DM 5.1; DEC-019; payment/receipt/Party Wallet policies.
- **Backend:** idempotent party payments, required receipt identity/label, party balance and Party Wallet source links.
- **UI / Screens:** UI-PTY-004 and UI-CUS-005.
- **Data/permissions/audit:** methods, payment/receipt, wallet movement; no Product Wallet exposure; every payment/print audited.
- **Manual verification:** multiple/partial/duplicate/concurrent payments, exact receipt label, role isolation and balance reconciliation.
- **Delivery / handoff / status:** independent receipts reduce party balance correctly. **Status: Not Started.**

### DM 5.3 — Party Execution

- **Scope / requirements:** operating order, reservations, consumable issue/actual/return and controlled changes; PTY-05, AST-05.
- **Dependencies / inputs:** DM 5.2; party operation/consumable masters and issue/return rules.
- **Backend:** order state/version, assignments, party-store consumable movements and references.
- **UI / Screens:** UI-PTY-005–006.
- **Data/permissions/audit:** order resources/actuals; party/store issue/return/complete; every change/movement audited.
- **Manual verification:** party-store only, availability, controlled additions/removals, actual consumption, unused return, post-complete block.
- **Delivery / handoff / status:** consumables follow controlled issue/return transactions. **Status: Not Started.**

### DM 5.4 — Rental Asset Lifecycle

- **Scope / requirements:** separate asset master, reservation conflict, checkout/return/condition states, damage/loss/depreciation/cost review; AST-01–AST-04.
- **Dependencies / inputs:** DM 5.3; BLK-016; asset register, buffers/checklists, damage/maintenance/depreciation policy.
- **Backend:** asset state machine, interval locking, documents, condition/evidence, assessment/approval/history.
- **UI / Screens:** UI-PTY-007–014.
- **Data/permissions/audit:** assets/locations/conditions/events/costs; specific reserve/checkout/inspect/assess/approve rights; all events audited.
- **Manual verification:** no double booking, time zones/buffers, valid states, required before/after condition, damage/loss/maintenance/retirement and cost access.
- **Delivery / handoff / status:** assets and damage/loss are controlled and traceable. **Status: Not Started.**

### DM 5.5 — Final Closure

- **Scope / requirements:** final party invoice, payments reconciliation, Party Wallet settlement, remaining amount/credit, final receipt; PTY-06, CUS-02.
- **Dependencies / inputs:** DM 5.4; final-close/credit/approval/receipt rules.
- **Backend:** readiness checks, atomic finalization/numbering/payment and wallet reconciliation, immutable final.
- **UI / Screens:** UI-PTY-015.
- **Data/permissions/audit:** completed operations/assets/payments; finalize/settle/approve/print; all inputs/results audited.
- **Manual verification:** unresolved-operation block, totals, multiple payments, Party Wallet only, remaining/credit, concurrency, final immutability/receipt.
- **Delivery / handoff / status:** final closure produces reconciled invoice and status. **Status: Not Started.**

### Phase 5 Delivery Criteria / Phase Gate

- A booking can be created, amended under authority, and linked to a customer without exposing retail finances.
- Multiple payments/deposits produce correct independent receipts and reduce party balance correctly.
- Consumables and rental assets follow controlled issue/return transactions.
- Damage or loss is recorded with responsible workflow and cost information.
- Final party closure produces a reconciled invoice and final status.
- Inputs for Phase 6 are approved: report formulas/outputs, UAT owners/scenarios, final opening data, branch/printer/training readiness.
- Standard Phase Gate evidence, critical-defect closure, role/audit checks, handoff, client review, and written approval are complete.

## Phase 6 — Dashboards, Reports, Acceptance and Go-Live

### DM 6.1 — Dashboard and Reporting

- **Scope / requirements:** dashboard KPIs/alerts and all report groups/filters; standalone quotations are scheduled here as a Proposed cross-activity completion item because the Implementation Plan omits a quotation DM; QTN-01–QTN-03, RPT-01–RPT-03.
- **Dependencies / inputs:** Phase 5 gate; BLK-017; formulas/access/layouts and quotation terms/status rules.
- **Backend:** scoped report queries/formula lineage/alerts; typed non-posting quotation state and print/share.
- **UI / Screens:** UI-ADM-001, UI-QTN-001, UI-RPT-001.
- **Data/permissions/audit:** source transactions, scope/field/margin access; quotation/report/print rights; sensitive access and statuses audited.
- **Manual verification:** every KPI/filter/alert group reconciles; quotation creates no effects/conversion; pagination/empty/error and role scope.
- **Delivery / handoff / status:** reports reconcile and honor boundaries; quotation scheduling requires owner confirmation. **Status: Not Started.**

### DM 6.2 — Export and Audit Views

- **Scope / requirements:** authorized Excel/PDF export and sensitive audit views; RPT-03, NFR-01–NFR-03, NFR-05.
- **Dependencies / inputs:** DM 6.1; formats, row limits, retention, redaction, owners.
- **Backend:** safe scoped export jobs/artifacts, append-only audit query/redaction and authorized downloads.
- **UI / Screens:** UI-RPT-002, UI-AUD-001.
- **Data/permissions/audit:** Export/Audit View/Export independent; every request/download audited.
- **Manual verification:** formats/values/formula safety/range/expiry/cross-scope denial; audit source/before-after and immutability.
- **Delivery / handoff / status:** exports correct/access-controlled; hand off report catalog and audit/export evidence. **Status: Not Started.**

### DM 6.3 — User Acceptance Testing

- **Scope / requirements:** scenario-based UAT across all roles, defects, retesting, approvals, release readiness; all 72 requirements and source acceptance scenarios.
- **Dependencies / inputs:** DM 6.2; named owners, data, devices, scenarios, evidence repository, severity/sign-off rules.
- **Backend/UI deliverables:** no new scope by default; only approved defect corrections with updated docs/traceability.
- **UI / Screen IDs:** all screens and outputs.
- **Permissions / audit:** use production-like roles/scopes; preserve UAT evidence and decisions.
- **Manual verification:** execute `docs/14-test-plan.md`; automated tests remain deferred; verify roles, integrity, print, responsive RTL/LTR, offline/sync, backup/restore and end-to-end journeys.
- **Delivery / handoff / status:** all UAT scenarios passed; critical defects closed; approval records and known limitations handed off. **Status: Not Started.**

### DM 6.4 — Production Readiness and Launch

- **Scope / requirements:** validated master-data import, training, production configuration, baseline backup, controlled go-live and handover; all requirements/Delivery Criteria.
- **Dependencies / inputs:** DM 6.3; final approved products/suppliers/customers/stock/users, branch readiness, printers, training attendance, hosting/domain/support.
- **Backend:** controlled import/cutover/reconciliation, production secrets/config, workers/scheduler/storage/monitoring/backups and recovery procedures.
- **UI / Screens:** all operational screens; import/error/admin/notification states.
- **Data/permissions/audit:** maker/checker imports, least-privilege production users, cutover/baseline/audit reconciliation.
- **Manual verification:** production config, users/scopes, data totals, scanners/printers, backup/restore, monitoring, smoke journeys, rollback/support readiness.
- **Delivery / handoff / status:** client sign-off, production release, backup/recovery confirmation, training and operational handover complete. **Status: Not Started.**

### Phase 6 Delivery Criteria / Final Phase Gate

- All agreed reports reconcile to their originating transactions and honor permission boundaries.
- Exports are correct and access-controlled.
- End-to-end UAT scenarios are passed; no unresolved critical defects remain.
- Production master data, users, printers, settings, and backup process are approved.
- Client sign-off and operational handover are completed.
- Handover contains approved configuration/master data, test/UAT evidence and issue status, guidance/procedures, known limitations/open low-priority items/owners, and production release/backup/recovery confirmation.

## Cross-Phase Phase Gate Checklist

1. Demonstrate every milestone in agreed scope.
2. Record actual manual verification/UAT evidence and close critical defects.
3. Validate required master data and policies.
4. Verify role-based access and audit requirements.
5. Record client review, open items, owners, and target actions.
6. Update progress, blockers, decisions, handoff, acceptance, and traceability.
7. Obtain formal written approval before proceeding.
