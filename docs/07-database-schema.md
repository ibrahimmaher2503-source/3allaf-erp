# 07 — Logical Database Design

This is a logical design, not SQL or a migration specification. The database engine, Laravel version, key strategy, exact money/quantity precision, and collation require owner/technical approval before implementation.

## Global Data Conventions

- **Primary keys:** internal `id` (big integer or equivalent) is proposed; expose non-guessable public identifiers where documents, cards, offline payloads, or URLs require them. Final strategy is Proposed.
- **Foreign keys:** required relationships use database constraints; deletion is restricted for referenced operational history. Master records are deactivated or soft-deleted only where policy permits.
- **Money:** fixed decimal, proposed `decimal(19,4)`, stored in the company currency with explicit rounded line/document totals. Never use binary floating point. Currency and precision are Open Decisions.
- **Quantity:** proposed `decimal(20,6)` with per-product fractional permission; validate whole numbers when fractional quantity is disabled.
- **Dates/times:** store timestamps in UTC with timezone-aware event/party context where needed; display in authorized branch/user timezone. Store `date` separately for business dates and `time`/timezone context for scheduled parties.
- **Names/text:** UTF-8/Unicode with explicit Arabic and English columns where required; normalized/search columns or database-supported indexes are implementation decisions.
- **Status:** backed by explicit application enums and allowed transition maps; never accept arbitrary strings.
- **Audit columns:** eligible mutable tables include `created_at/by`, `updated_at/by`, and optional `deleted_at/by`; transactional documents also carry `branch_id`, `store_id`, `submitted_at/by`, `approved_at/by`, `cancelled_at/by`, `cancellation_reason`, and immutable source references as applicable.
- **Approval:** use `approval_records` for multi-action history; a current approval snapshot may be denormalized only for safe querying.
- **Approved-document policy:** after approval/finalization, headers, lines, totals, and ledgers are immutable. Correction uses `original_document_type/id`, `reversal_of_type/id`, or a typed source/reference relationship and a new approved document.
- **Document numbering:** numbers come from locked `document_sequences`; uniqueness is at least `(company_id, document_type, configured_scope, number)` and allocation occurs atomically at approval/issue, not unprotected form load.
- **Soft delete:** allowed for eligible unused/draft masters; forbidden for ledgers, approvals, audit, payments, approved documents, movements, and sync/conflict history. Approved history is never physically deleted.
- **Indexes:** every foreign key; status/business date; document number; barcode/item code/phone/card identifier; branch/store scope; source references; audit timestamp/event; reservation interval; sync idempotency; and common report filters. Composite/index order must follow actual query plans.
- **Attachments:** polymorphic authorization is permitted only through a controlled `attachments` relation with ownership/context, storage disk/key, original/safe names, MIME, size, hash, purpose, uploader, scan/validation status, and retention. Never expose raw paths.

## Organisation, Access, and Configuration

| Table | Purpose and Relationships | Important Columns / Status | Keys, Constraints, and Indexes | Delete / Immutability / Audit |
|---|---|---|---|---|
| `companies` | Company identity and localization root. | names, legal/tax identifiers, currency, timezone, locale defaults, status. | PK `id`; unique company code/legal identifier where supplied. | Soft-delete only if unused; settings changes audited/versioned where financial. |
| `branches` | Commercial locations belonging to company. | `company_id`, code, bilingual name, contact, timezone, status. | Unique `(company_id, code)`; indexes company/status. | Deactivate rather than delete once referenced; audit. |
| `stores` | Physical stock locations, including selling/warehouse/party types; belongs to company/optional branch. | `branch_id`, code, type, bilingual name, status, `allows_negative_stock`, policy fields. | Unique `(company_id, code)`; type/status/branch indexes. | Deactivate once referenced; policy changes audited. |
| `branch_selling_stores` | Effective/history mapping of branch to authorized POS selling store. | `branch_id`, `store_id`, effective dates, status, approval/reason. | Prevent overlapping active mapping per branch unless owner approves a model; indexed branch/effective. | Historical mapping immutable; changes approved/audited. |
| `cash_drawers` | Cash drawers assigned to a branch/location. | `branch_id`, optional `store_id`, code, name, status. | Unique `(branch_id, code)`; assignment/status index. | Cannot delete/reassign with active/history dependencies; audit. |
| `users` | Authenticated actors. | identity/contact, password/auth metadata, locale/timezone, status, last login. | Unique login/email/phone per policy; status index. | Deactivate; never delete referenced actor history. Sensitive changes audited. |
| `roles` | Named role masters. | code, bilingual name, description, status. | Unique code; status index. | Soft-delete only if unassigned/unreferenced; changes audited. |
| `permissions` | Atomic module/action capabilities. | module, action, code, sensitivity, status. | Unique code or `(module, action)`; indexed module. | Controlled master; grants audited. |
| `role_user` / `role_permissions` | User-role and role-permission assignments. | subject IDs, effective dates, status, grantor, reason. | Unique active assignment tuples; FK/index all IDs. | Revoke/expire, do not erase grant history; audit. |
| `user_branch_scopes` | User branch visibility/operation scope. | `user_id`, `branch_id`, role/context, effective dates, status. | Unique active tuple; user/branch/status indexes. | Revoke/expire with history. |
| `user_store_scopes` | User store visibility/operation scope. | `user_id`, `store_id`, role/context, effective dates, status. | Unique active tuple; user/store/status indexes. | Revoke/expire with history. |
| `payment_methods` | Cash/manual electronic methods and evidence rules. | code, bilingual name, type, requires evidence, offline eligibility, status. | Unique company code; type/status index. | Deactivate; preserve document snapshots/references. |
| `tax_settings` | Configurable tax rules used only when selected on an invoice. | code/name, rate/logic, effective dates, status. | Prevent overlapping active version per code/scope; effective indexes. | Versioned/immutable after use; audit. |
| `document_sequences` | Unique sequential numbering configurations/counters. | document type, scope type/id, prefix/pattern, next value, reset rule, status, lock version. | Unique active `(company, document_type, scope)`; transactional lock. | Never delete used sequence; counter changes highly audited. |
| `printer_configurations` | Branch/device/format printer and template choices. | branch/store, printer type, paper/label size, template, settings, status. | Unique active context/type as policy; scope/status indexes. | Deactivate; settings and test ownership audited. |

## Catalog, Suppliers, Pricing, and Labels

| Table | Purpose and Relationships | Important Columns / Status | Keys, Constraints, and Indexes | Delete / Immutability / Audit |
|---|---|---|---|---|
| `categories` | Hierarchical product/party classification master. | parent, bilingual names, code, activity type, status. | Unique scoped code; parent/status indexes; cycle prevention. | Deactivate when referenced. |
| `brands` | Product brand master. | code, bilingual name, status. | Unique company code; search/status indexes. | Deactivate when referenced. |
| `suppliers` | Supplier contact/status/terms/history root. | code, names, contacts, terms, tax/contact fields, status. | Unique company code and approved identifiers; search/status indexes. | Deactivate/logical delete only if safe; history preserved/audited. |
| `products` | Stable product/service identity and bilingual card. | `item_code`, names/descriptions, model, type, status, UOM, category/brand, average-cost snapshot, reorder, dimensions/weight, age/gender/character/colour/size, keywords, fractional flag. | Unique `(company_id, item_code)`; indexes type/status/category/brand/attributes/search. | Item code immutable after creation/use; deactivate; all sensitive changes audited. |
| `product_suppliers` | Preferred and historical product-supplier associations. | product, supplier, supplier item reference, preferred flag/effective history, last purchase price snapshot. | One active preferred supplier/product; indexes supplier/product. | Preference versions preserved; actual invoice supplier remains on invoice. |
| `barcodes` | International/supplier/local barcodes independent of item code. | product, value, type, supplier, local supplier code/serial, status. | Global/company-unique barcode; indexed exact value/product. | Used value/history not silently reassigned; changes audited. |
| `product_images` | Main plus up to four additional authorized images. | product, attachment, role/order, alt text, status. | Unique main per product; max count enforced transactionally; product/order index. | Controlled remove/replace; attachment history/authorization. |
| `price_lists` | Named price scope/version root. | company, code, name, scope, status. | Unique company code; scope/status index. | Deactivate; used list preserved. |
| `price_versions` | Immutable proposed/approved price change batch. | price list, source type/id, effective time, status (`Draft`,`Submitted`,`Approved`,`Rejected`,`Cancelled`), approval fields. | Version number unique per list; status/effective index. | Approved immutable; correction is new version; audit. |
| `price_lines` | Product/location price within a version. | version, product, store/branch exception, reference/min/max/open-price settings, proposed/approved amount, effective range. | Unique `(version, product, location scope)`; constraint one active approved price per product/location/time. | Approved immutable; indexed lookup/effective. |
| `label_queues` | Location-specific label quantities from approved price. | version/line, product, store/branch, required/printed quantity, status, printer/template. | Unique generation key; status/location/product indexes. | Append print events; queue calculation/reprints audited, no silent quantity rewrite. |
| `label_print_events` | Evidence of initial/reprint label output. | queue, quantity, printer, user, reason, timestamp. | PK; queue/time/user indexes. | Append-only. |

## Purchasing and Inventory

| Table | Purpose and Relationships | Important Columns / Status | Keys, Constraints, and Indexes | Delete / Immutability / Audit |
|---|---|---|---|---|
| `purchase_orders` | Supplier order header. | number, supplier, branch/store, dates, terms, status (`Draft`,`Submitted`,`Partially Received`,`Received`,`Cancelled`,`Closed`), totals. | Unique scoped number; supplier/status/date indexes. | Draft soft-delete by permission; submitted/approved history immutable. |
| `purchase_order_lines` | Ordered product quantities/cost context. | PO, product, quantity, unit cost, discount/tax if used, received quantity. | Unique/sequence line; PO/product indexes. | Immutable after terminal/receipt except referenced process. |
| `purchase_invoices` | Purchase/goods-receipt header retaining actual supplier. | number, supplier, receiving store, source PO/import, tax/discount/totals, status, supplier invoice ref. | Unique number; duplicate supplier ref rule; store/supplier/date/status indexes. | Approved immutable; source for returns/movements. |
| `purchase_invoice_lines` | Received product/cost details. | invoice, PO line, product, quantity, unit cost, discount/tax, net, cost result context. | Invoice/line unique; product indexes. | Approved immutable. |
| `purchase_returns` | Approved supplier-return header. | number, supplier, store, original purchase optional, reason, status, totals. | Unique number; original/supplier/store/status indexes. | Approved immutable; correction by reference. |
| `purchase_return_lines` | Returned quantities/cost basis. | return, product, original line optional, quantity, unit cost/value, condition/reason. | Line/source indexes; prevent over-return per policy. | Approved immutable. |
| `stock_balances` | Current materialized balance per product/store; derived/updated from ledger. | product, store, on-hand, reserved, in-transit summaries, version. | Unique `(product, store)`; store/product/reorder indexes; row lock for posting. | Never user-edited/deleted; reconciles to movements. |
| `stock_movements` | Append-only inventory ledger. | product, store, direction/type, quantity, unit/total cost, source type/id/line, business/posted time, balance-after optional. | Idempotent unique source event; product/store/time/source indexes. | Append-only, no soft delete; referenced reversal movement only. |
| `stock_transfers` | Stateful store-transfer header. | number, source/destination, status required by INV-03, dates, reason, approval/dispatch/receipt users. | Unique number; source/destination/status/date indexes. | State-restricted; terminal/approved history immutable. |
| `transfer_lines` | Requested/dispatched/received/damaged/short/refused quantities. | transfer, product, quantities, reason/disposition. | Unique line/product rule; transfer/product indexes. | State-restricted; receipt facts immutable after approval. |
| `stock_counts` | Full/partial count session header and reference snapshot definition. | number, branch/store, scope type/query snapshot, reference time, status, assigned counter, approvals. | Unique number; store/status/reference indexes. | Submitted/approved immutable except controlled reconciliation facts. |
| `count_lines` | Per-item reference/count/recount/movement/reconciled values. | count, product, reference balance, counted quantities, movement delta, verified/reconciled quantity, discrepancy, uncounted flag. | Unique `(count, product)`; discrepancy/uncounted indexes. | Every edit/recount audited; no implicit zero. |
| `inventory_adjustments` | Entry/exit/exchange/adjustment header. | number, type, store, reason code/text, status, source reference, override. | Unique number; type/store/status/date indexes. | Approved immutable; reference reversal/correction. |
| `inventory_adjustment_lines` | Adjustment item quantities and cost context. | adjustment, product, signed quantity/type, unit cost, balance context. | Adjustment/product indexes; fractional/negative rules. | Approved immutable. |

## POS, Payments, Returns, and Cash Control

| Table | Purpose and Relationships | Important Columns / Status | Keys, Constraints, and Indexes | Delete / Immutability / Audit |
|---|---|---|---|---|
| `sales` | Approved retail sale header. | number, branch, selling store, cashier, drawer, shift, customer optional, status, gross/discount/net/tax/final, offline/source identifiers. | Unique number and offline idempotency key; scope/date/status/customer indexes. | Approved immutable; cancel/return through references. |
| `sale_lines` | Sold product, original price, one discount, net and tax snapshot. | sale, product, quantity, original price, discount type/value, net, tax, total, price-line reference. | Sale/line/product indexes; one discount type constraint. | Approved immutable. |
| `payments` | Payment records for sale/return/party or other typed sources. | source type/id, method, amount, direction, status, external/manual ref, shift. | Unique idempotency/source key; method/source/shift/date indexes. | Approved append-only; correction via reference payment/reversal. |
| `payment_evidence` | Protected POS-terminal receipt evidence relation. | payment, attachment, evidence type, captured/verified by/time, status. | Payment/evidence uniqueness as policy; payment/status index. | No silent replacement after approval; access and corrections audited. |
| `suspended_sales` / `suspended_sale_lines` | Non-posting held cart snapshot. | branch/store/cashier/shift/customer, cart data, status (`Suspended`,`Retrieved`,`Cancelled`,`Converted`), expiry. | Unique token; cashier/status/expiry indexes. | No stock/payment posting; controlled expiry/cancel and audit. |
| `shifts` | Cashier/drawer operating session. | number, user, branch, drawer, opened/closed times, opening balance, status, blind submitted time. | Only one active shift per cashier/drawer per policy; status/date indexes. | Closed immutable; correction through approved process. |
| `shift_totals` | Submitted actual and system expected totals by payment method. | shift, method, actual, expected, variance, visibility/review metadata. | Unique `(shift, payment_method)`; variance indexes. | Actual immutable after submission; expected hidden from cashier until submission. |
| `cash_movements` | Permitted non-sale cash inflow/outflow linked to shift/drawer. | shift, drawer, type, amount, reason, source, status. | Source/idempotency and shift/date indexes. | Approved append-only; correction by reference. |
| `retail_returns` | Referenced retail return header. | number, original sale/Gift Receipt, customer, store, condition/approval summary, settlement type, status, totals. | Unique number; source/date/status indexes; prevent excess/duplicate amounts. | Approved immutable. |
| `retail_return_lines` | Returned line/condition/value/disposition. | return, original sale line, quantity, eligible value, condition, sellable flag, reason. | Return/source-line indexes. | Approved immutable; drives referenced stock movement. |
| `exchanges` / `exchange_lines` | Link return side, replacement sale side, and difference settlement. | number, return, replacement sale/lines, difference, settlement, status. | Unique number/source links; status/date indexes. | Approved immutable. |

## Customers, Loyalty, Wallets, and Gifts

| Table | Purpose and Relationships | Important Columns / Status | Keys, Constraints, and Indexes | Delete / Immutability / Audit |
|---|---|---|---|---|
| `customers` | Unified identity/profile across activities. | unique phone, names, contacts, consent/version/timestamps, status, preferred locale. | Unique normalized phone; search/status indexes. | Merge/deactivate only by approval; transaction links preserved. |
| `customer_children` | Party-related child names/birthdays and permitted data. | customer, name, birth date, notes/consent scope, status. | Customer/birthday indexes; no unnecessary sensitive data. | Controlled delete/deactivate subject to retention policy. |
| `loyalty_ledger` | Shared immutable earn/redeem/expiry/correction ledger. | customer, activity type, movement type, points, expiry, source type/id, rule/version, balance-after optional. | Idempotent source key; customer/expiry/activity/time indexes. | Append-only; no direct balance edit. |
| `product_wallet_ledger` | Retail-only customer balance movements. | customer, movement type, amount, source, due/settlement context, balance-after optional. | Idempotent source key; customer/date/type indexes. | Append-only and retail-authorized only. |
| `party_wallet_ledger` | Party-only customer balance movements. | same pattern with party sources only. | Idempotent source key; customer/date/type indexes. | Append-only and party-authorized only. |
| `gift_cards` | Unique card identity and current status/balance summary. | identifier, original value, current balance, issued/valid/expired dates, holder/reference, status. | Unique identifier; holder/status/expiry indexes; row lock on use. | Never direct-edit balance; status transitions audited. |
| `gift_card_ledger` | Issue/redeem/void/expiry/correction history. | card, movement, amount, source type/id, balance before/after. | Idempotent source; card/time indexes. | Append-only. |
| `gift_receipts` / `gift_receipt_lines` | Price-free reference to eligible original sale/lines. | unique identifier, sale, issued/reprinted/used state, eligible lines. | Unique identifier; sale/status indexes. | No price fields exposed in output; events preserved. |

## Party Operations and Rental Assets

| Table | Purpose and Relationships | Important Columns / Status | Keys, Constraints, and Indexes | Delete / Immutability / Audit |
|---|---|---|---|---|
| `party_bookings` | Customer party schedule and operating context. | number, customer/child, party date/start/end/timezone/location, contacts, notes, status, responsibility assignments. | Unique number; interval/status/customer/location indexes. | Controlled draft/reschedule/cancel; completed history immutable. |
| `party_invoices` | Working/final party invoice header. | number, booking, customer, status, gross/discount/tax/final, payments/Party Wallet summary, finalized time. | Unique number; booking/customer/status/date indexes. | Editable only before final close; final immutable. |
| `party_invoice_lines` | Party-only service/consumable/rental charge snapshots. | invoice, typed party item/reference, quantity, price/discount/tax/net, actual/final flags. | Invoice/type/ref indexes; database/application activity-type guard. | Final immutable; retail product line prohibited. |
| `party_payments` | Typed view/relation for multiple payments on account and receipts. | party invoice, payment record, receipt number/label, status. | Unique receipt number/payment; invoice/date indexes. | Each approved payment immutable; required individual receipt. |
| `party_operating_orders` | Party execution header. | number, booking/invoice, status, version, assignments, planned/actual dates. | Unique number; booking/status/date indexes. | Controlled edits until completion; history/version audit. |
| `party_operating_order_lines` | Planned/actual services, consumables, and assets. | order, type, reference, planned/actual quantity, assignment/status. | Order/type/ref indexes; no retail type. | Completed facts immutable. |
| `party_consumable_issues` / `party_consumable_lines` | Referenced party-store consumable issue/unused return. | order, store, status, reason; product/quantity/movement refs. | Unique number; order/store/status indexes. | Approved creates movements; returns use references. |
| `rental_assets` | Unique reusable party assets separate from consumables. | code, bilingual name, category, location, condition, required status, relevant cost data. | Unique company code; status/location/category indexes. | Retire rather than delete once used; condition/status changes audited. |
| `asset_reservations` | Time-bound party allocation. | asset, booking/order, start/end/timezone, status, buffer, override. | Exclusion/transaction rule prevents overlapping active intervals; asset/time indexes. | Cancel/release preserves history; overrides audited. |
| `asset_checkouts` | Pre-condition/location/responsibility checkout document. | number, reservation/asset/party, condition, checkout user/time/location, status. | Unique active checkout per asset; asset/party/status indexes. | Approved immutable. |
| `asset_returns` | Return and post-condition document. | number, checkout/asset/party, return time/location, condition, status/outcome. | One approved return per checkout unless correction reference; indexes. | Approved immutable. |
| `asset_damage` | Damage/loss assessment linked to event/party. | asset, return/event/party, assessment, responsibility, evidence, optional cost impact, approval, final status. | Asset/party/status/date indexes. | Approved append-only history; no delete. |
| `asset_depreciation` | Depreciation event/history without implying GL. | asset, event/date/method/context, amount where entered, party optional, approval. | Asset/date/status indexes; duplicate-event guard. | Approved immutable. |

## Quotations, Attachments, Audit, Approval, and Offline

| Table | Purpose and Relationships | Important Columns / Status | Keys, Constraints, and Indexes | Delete / Immutability / Audit |
|---|---|---|---|---|
| `quotations` | Typed retail or party, non-posting offer header. | number, activity type, customer, terms, notes, validity, status, totals, future conversion reference fields. | Unique number; type/customer/status/validity indexes. | Issued versions/status history preserved; no posting effect. |
| `quotation_lines` | Compatible retail or party lines. | quotation, typed reference, quantity, price/discount/tax/net. | Quote/line/ref indexes; enforce one activity type. | Issued snapshot preserved. |
| `attachments` | Authorized files for payment evidence, products, imports, assets, etc. | attachable type/id, purpose, disk/key, names, MIME, size, hash, validation/scan status, uploader, retention. | Hash/context and attachable indexes; storage key unique. | Controlled retention; access/change/delete events audited; approved evidence preserved. |
| `audit_logs` | Append-only sensitive-event history. | actor, timestamp, session/device, branch/store, event, auditable/source, reason, before/after redacted JSON, correlation ID. | Event/time/actor/scope/source/correlation indexes. | Append-only, tamper-protected; no soft delete. |
| `approval_records` | Append-only submit/approve/reject/override history. | approvable type/id, action, status, actor, reason, limits/context, timestamps. | Approvable/status/time indexes; prevent duplicate active decision where needed. | Append-only. |
| `offline_transactions` | Device-local/server registry of provisional POS payloads. | client UUID/idempotency key, device/user/branch/shift, payload hash/version, created/expiry, status (`Queued`,`Sending`,`Synced`,`Conflict`,`Rejected`), server source. | Unique client ID/idempotency; status/device/time indexes. | Payload/history retained per security policy; no silent edit. |
| `sync_batches` | Synchronization attempt grouping and results. | device/user/session, started/completed, counts, status, request/response hashes. | Unique batch UUID; device/status/time indexes. | Append-only attempts/results. |
| `sync_conflicts` | Server/client comparison and explicit disposition. | offline transaction, conflict type, server/client values (protected), owner, status, resolution reason, source correction refs. | Conflict/status/owner/time indexes. | Never auto-delete; resolved record immutable except append-only notes/events. |

## Strict Retail/Party Separation

- Every transaction header has an explicit activity type or belongs to an activity-specific table. Ordinary sales and party invoices cannot share lines.
- A database/application constraint prevents retail product references in party invoice/order types and party service/asset references in retail sales.
- Retail stock movements use authorized retail/selling stores; party consumables use party stores; rental assets use reservation/location history, not consumable stock balances.
- Product Wallet and Party Wallet are physically separate ledger tables with separately named policies, services/actions, report queries, and UI screens. No generic wallet transfer is designed.
- Loyalty remains one shared ledger but records activity type and rule/version.
- Reports and exports join activities only at explicitly authorized customer/management summary boundaries and never merge wallet balances.

## Ledger Reconciliation Rules

`stock_balances` equals approved `stock_movements` by product/store; gift-card summary equals `gift_card_ledger`; loyalty and each wallet balance equals its respective ledger; shift expected totals derive from approved payments and cash movements; party settlement derives from final invoice, party payments, and Party Wallet ledger. Any material mismatch is an incident requiring investigation and a referenced correction, not a direct balance edit.

## Detailed Contract Alignment (DEC-040)

`docs/36-module-data-contracts.md` is the detailed local contract baseline for already-approved entities, source references, idempotency, versions, ledgers, constraints, and cross-module relations. It supplements this schema; it does not authorize migrations or change the production database decision.

- **Derived Implementation Convention:** business entities use stable references, audit/request correlation, explicit status, and optimistic versioning where the relevant contract requires them.
- **Derived Implementation Convention:** approved/final records and ledger movements retain source references and are not physically deleted.
- **Owner-Configurable Value:** exact field precision, production database types, retention durations, provider metadata, and printer/device configuration.
- **Production Decision Pending:** production database engine/version, production master data, final legal wording, storage/backup provider, and final commercial or numeric limits.
