# 36 — Module Data Contracts

## Shared UI Preference Contract — TSK-004B

`user_ui_preferences` has one row per user and validated fields for appearance, accent color, sidebar/navbar mode, content width, table density, font scale, and reduced motion. It is user-owned presentation state and cannot change business or authorization behavior. Guide context uses the safe DTO described in `docs/40-contextual-page-guide-specification.md`.

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Detailed implementation specification derived from the approved PRD, Implementation Plan, Architecture, Roles/Permissions, and policies 17–29  
**Authority order:** PRD functional behavior → Implementation Plan sequencing/phase gates → approved decisions/policies → this specification  
**Important:** Exact production master data, legal wording, hardware models, provider choices, and final numeric limits remain configurable where the source documents do not define them.

---


## 1. Purpose

Define canonical entity contracts, cross-module references, identifiers, constraints, audit/version fields, and transactional invariants. Exact migration types may be adapted to the approved production database while preserving semantics.

## 2. Universal Conventions

All primary business entities use:

- UUID primary/reference identity where practical.
- Created/updated timestamps.
- Actor fields where meaningful.
- Branch/store/activity scope.
- Status enum/string.
- Version integer for optimistic concurrency where needed.
- Soft delete only for eligible master/draft records.
- Approved/final records are not physically deleted.
- Audit/request IDs.
- Money stored in fixed precision decimal/minor units according to currency decision.
- Quantity stored in fixed precision.
- Time stored consistently with timezone-aware display.

## 3. Platform Contracts

### companies

Key fields: id, code, names, address/contact, tax identity, currency, precision, timezone, status, version.

### branches

id, code, names, address/contact, timezone, status.

### stores

id, branch_id nullable according to model, code, name, type, selling_enabled, party_enabled, status.

### branch_selling_stores

branch_id, store_id, effective_from/to, reason, approved_by, version. Non-overlap constraint.

### cash_drawers

id, code, name, branch_id, store_id, status.

### payment_methods

id, code, names, type, evidence_required, offline_allowed, status.

### tax_settings

id, code, rate, mode, effective dates, status, version.

### document_sequences

document_type, scope fields, prefix/suffix, padding, reset period, next value, version, unique scope.

### printer_configurations/templates

purpose, branch/store, paper, connection/config metadata, template version, status.

## 4. Identity and Authorization

### users

identity/profile/status/locale fields; no plaintext secrets.

### roles / permissions / pivots

canonical keys and scope assignments.

### user_branch_scopes / user_store_scopes

unique user/scope pairs, effective/status metadata.

## 5. Approval/Audit/Attachment

### approval_records

approvable type/id, action, states, requester, approver, reason, limits, record version/hash, request ID, scope, timestamp.

### audit_logs

event UUID, category/name, actor, entity/source, scope, reason, before/after safe JSON, changed fields, request ID, related approval.

### attachments

purpose, owner type/id, uploader, scope, names, disk/path, MIME, size, hash, dimensions, status, retention, redaction metadata.

## 6. Catalog

### products

immutable item code, bilingual names/descriptions, type, status, UOM, category, brand, preferred supplier, average cost, reorder threshold, attributes, fractional flag, version.

### categories

parent_id, code/name, status; cycle prevention.

### brands

code/name/status.

### product_suppliers

product, supplier, preferred flag/effective history.

### barcodes

product, value, type, supplier code/local serial, status; unique barcode.

### product_images

product, attachment, role main/additional, sort order; one main, max four additional.

## 7. Purchasing

### suppliers

code, names, contact, terms, status.

### purchase_orders / lines

supplier, store/context, status, dates, number, totals, version; lines product/qty/received.

### purchase_invoices / lines

supplier, source PO, receiving store, supplier reference, tax/discount, status, totals, approval, number; lines qty/cost basis.

### purchase_returns / lines

supplier, source invoice, store, reason, status, cost/reference quantities.

## 8. Pricing

### price_lists / versions / lines

location/scope, version status, effective time, source, approver; line product, price, reference/min/max for open price.

Unique active product/location rule.

### label_queues / print_events

price version, product, location, target quantity, printed quantity, status; event printer/user/reason/copies.

## 9. Inventory

### stock_movements

append-only: product, store, movement type, quantity signed, cost context, source type/id/line, posted time, idempotency key.

### stock_balances

product/store unique, on_hand, reserved, in_transit as modeled, average cost, version.

### transfers / lines

source/destination, state, quantities requested/dispatched/received/damaged/short/refused.

### inventory_adjustments / lines

type, reason, store, state, qty, override metadata.

### stock_counts / lines

scope/reference time, state; line reference balance, subsequent movement, counted quantity, reconciliation result, counted/recounted by.

## 10. Retail POS

### sales / lines

context fields, state, number, customer, totals, tax/discount snapshots, idempotency. Lines product, quantity, original price, discount, net, price version, stock source.

### suspended_sales / lines

owner/context, expiry, draft cart snapshots.

### payments

source type/id, shift, method, amount, reference, status, idempotency.

### payment_evidence

payment/attachment relation.

### gift_receipts / lines

sale reference, unique number, eligible lines, no price fields in output.

### retail_returns / exchanges / lines

source sale/gift receipt, inspection, settlement, status, stock disposition.

### gift_cards / gift_card_ledger

unique identifier, summary balance/status; append-only events.

## 11. Cash Control

### shifts

drawer, cashier, branch/store, state, opening float, times, version, close number.

### shift_totals

expected/actual by method, variances, submitted/approved metadata, immutable snapshot.

### cash_movements

shift/drawer, type, amount, reason, source, state.

## 12. Customers

### customers

unique normalized phone, bilingual/contact/consent fields, status.

### customer_children

customer, name/date fields, consent/purpose constraints.

### loyalty_ledger

customer, activity, event, points, expiry, source, rule version, idempotency.

### product_wallet_ledger / party_wallet_ledger

separate tables/contracts, amount/debit-credit, source, balance snapshot optional, idempotency, approval.

## 13. Parties

### party_bookings

customer/child, schedule/location/contact, party store, status, responsibilities, version, number.

### party_invoices / lines

booking, working/final state, totals, versions, number; party-only line types.

### party_payments

invoice, payment relation, receipt number, amount, status.

### party_operating_orders / lines

booking/invoice, state, services/responsibilities/actual changes.

### consumable issues/lines

order, store, product, planned/issued/consumed/returned quantities, movement references.

## 14. Assets

### rental_assets

unique code, category, location, condition, status, cost visibility fields, version.

### asset_reservations

asset, booking, interval, status; no-overlap enforcement.

### asset_checkouts / returns

source, times, locations, condition, inspector/responsible, attachment references.

### asset_damage / depreciation

asset, source event/party, assessment, cost optional, approval, final status.

## 15. Quotations

quotation header/lines with activity type, customer, validity, status, prices/terms, no posting effects.

## 16. Reporting/Offline

Purpose-built read models need not be source-of-truth tables.

Offline contracts:

- devices/enrollment.
- offline_transactions.
- sync_batches.
- sync_conflicts.

Include user/device/branch binding, schema/policy version, payload hash, idempotency, disposition.

## 17. Required Constraints

- Unique item code, barcode, phone, document number, Gift Card ID.
- One active price per product/location.
- One active shift per drawer; cashier rule configurable.
- No overlapping asset reservation interval.
- Unique source idempotency.
- Non-overlapping selling-store mappings.
- Foreign keys preserve source references.
- Check constraints/enums where database supports.
- Index all common scope/status/date/search keys.

## 18. Data Ownership and Cross-Module References

- Modules own mutations.
- Other modules reference source IDs and call explicit actions.
- No duplicated business truth.
- Stock movement is inventory source truth.
- Wallet/loyalty/gift ledgers are source truth.
- Historical documents snapshot required values.

## 19. Migration and Seed Rules

- Migrations reversible where safe.
- No production master data invented.
- Canonical permissions/roles may be seeded.
- Local fixtures clearly marked non-production.
- Concurrency and uniqueness verified against target database before production.

## 20. Manual Verification

Use browser workflows and database inspection only when authorized to confirm constraints, source linkage, immutability, scope, duplicate prevention, and reconciliation. No automated tests under current directive.
