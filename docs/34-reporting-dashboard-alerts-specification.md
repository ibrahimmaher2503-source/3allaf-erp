# 34 — Reporting, Dashboard, and Alerts Specification

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Detailed implementation specification derived from the approved PRD, Implementation Plan, Architecture, Roles/Permissions, and policies 17–29  
**Authority order:** PRD functional behavior → Implementation Plan sequencing/phase gates → approved decisions/policies → this specification  
**Important:** Exact production master data, legal wording, hardware models, provider choices, and final numeric limits remain configurable where the source documents do not define them.

---


## 1. Purpose

Define all required dashboards, KPIs, alerts, report groups, filters, data lineage, authorization, drill-down, export, performance, and freshness behavior.

## 2. Traceability

- PRD: RPT-01–RPT-03, NFR-01–NFR-07.
- Implementation Plan: DM 6.1, DM 6.2.
- Tasks: TSK-038–TSK-040 and related module reports.
- Architecture: Reporting & Governance module.

## 3. Reporting Principles

- Scope before aggregation.
- Every figure has source lineage.
- No unrestricted cross-user cache.
- Sensitive fields separately authorized.
- Detail views paginated.
- Export bounded and audited.
- Historical values use document snapshots.
- No general-ledger claims.
- Currency/precision explicit.

## 4. Global Filters

Where applicable:

- Date range.
- Branch.
- Store.
- User/cashier.
- Supplier.
- Product.
- Category.
- Payment method.
- Customer.
- Party status.
- Document status.
- Activity: retail/party.
- Comparison period.

Unauthorized filter options are not exposed and server-side scope remains enforced.

## 5. Dashboard KPIs

### 5.1 Sales

- Gross sales.
- Net sales.
- Invoice count.
- Average invoice value = net approved sales / approved invoice count.
- Discounts.
- Tax.
- Returns/refunds.
- Sales by branch/cashier/payment/product/category.

Define inclusion/exclusion of cancelled/reversed records clearly.

### 5.2 Cash Control

- Drawer balances/status.
- Open shifts.
- Unclosed shifts.
- Expected/actual totals for authorized managers only.
- Shift shortages/overages.
- Payment-method summary.
- Cash movements.

### 5.3 Inventory

- Stock value using approved cost basis.
- On-hand.
- Available.
- In-transit.
- Reserved where applicable.
- Low stock.
- Zero stock.
- Reorder.
- Unpriced items.
- Pending label queue.
- Transfer receipt pending.
- Count differences pending.

### 5.4 Purchasing

- Recent purchase orders.
- Purchase invoices/receipts.
- Supplier returns.
- Purchase value.
- Preferred vs actual supplier.
- Last purchase price.
- Pending receipt/approval.

### 5.5 Customers

- New/active customers where defined.
- Top customers by authorized metric.
- Loyalty issued/redeemed/expired.
- Product Wallet balances/movements.
- Party Wallet balances/movements.
- Gift Card issued/outstanding/used/expired.

Wallet separation is mandatory.

### 5.6 Parties and Assets

- Upcoming parties.
- Party payments on account.
- Outstanding party balances.
- Parties pending operation/final close.
- Consumable commitments.
- Assets reserved/checked out/overdue.
- Damage/maintenance/lost issues.

## 6. Operational Alerts

Alert types:

- Low stock.
- Zero stock.
- Unpriced product.
- Pending price approval.
- Pending transfer receipt.
- Transfer difference review.
- Count difference pending.
- Uncounted item review.
- Open/unclosed invoice where relevant.
- Shift variance.
- Unclosed shift.
- Upcoming party.
- Overdue party balance.
- Asset return overdue.
- Asset damage/maintenance/loss.
- Failed import/export/label job.
- Backup/monitoring alert where platform enabled.

Each alert defines:

- Trigger.
- Severity.
- Owner role.
- Scope.
- Created time.
- Due time where applicable.
- Acknowledged/resolved state.
- Source link.
- Suppression/deduplication rule.

## 7. Required Report Groups

### Sales & POS

- Daily/monthly sales.
- Branch/cashier/payment/product/category.
- Invoice detail.
- Tax/discount detail.
- Suspended/cancelled where authorized.
- Returns/exchanges/refunds/Gift Cards.
- Open-price usage.

### Inventory

- Stock on-hand/available/in-transit/reserved.
- Valuation.
- Stock card/movement.
- Reorder/low/zero.
- Unpriced.
- Price versions/label queues.
- Transfers/differences.
- Counts/reconciliation.
- Adjustments/negative-stock overrides.

### Purchasing

- Purchase orders.
- Purchase invoices/receipts.
- Supplier returns.
- Supplier history.
- Preferred/actual supplier.
- Last purchase prices.
- Cost changes.

### Cash

- Shift closing.
- Drawer movements.
- Payment collection.
- Expected/actual variance.
- Approvals.
- Open shifts.

### Customers

- Customer history.
- Loyalty ledger.
- Product Wallet statement.
- Party Wallet statement.
- Gift Card status/use.
- Consent-sensitive exports only by permission.

### Parties

- Bookings/calendar.
- Payments on account.
- Outstanding balances.
- Services.
- Consumables.
- Assets.
- Checkout/return.
- Damage/depreciation.
- Final settlements.

## 8. Formula Catalog

Every KPI/report field documents:

- Business name.
- Stable key.
- Formula.
- Included statuses.
- Excluded statuses.
- Date basis.
- Currency/rounding.
- Source entities.
- Scope.
- Refresh behavior.
- Drill-down route.

No metric may be labeled “margin” unless cost visibility and formula are explicitly authorized.

## 9. Drill-Down

Dashboard cards link to scoped detail lists with inherited filters. Drill-down must not broaden scope. Aggregates and detail totals must reconcile.

## 10. Export

Formats:

- PDF.
- Excel.

Rules:

- Separate export permission.
- Same scope/filters as screen.
- Sensitive fields redacted.
- Formula injection neutralized.
- Row/date limits configurable.
- Large export queued.
- Artifact protected and expiring.
- Export event audited.
- Include generated time, filters, scope, and requester.

## 11. Performance

- Indexed source fields.
- Bounded default ranges.
- Pagination.
- Purpose-built read models for real complexity.
- Queue large exports.
- Cache only explicit non-sensitive aggregates with scope-aware keys and invalidation.
- Show data freshness timestamp.

## 12. UI

- Dashboard filter bar.
- KPI cards.
- Trend charts only where useful.
- Alert list.
- Report catalog.
- Report filters/results.
- Drill-down.
- Export status/download.
- Empty/loading/error/denied states.

## 13. Manual Browser Scenarios

Verify:

- Scope by role/branch/store/activity.
- KPI formula reconciliation.
- Cancelled/reversed exclusion.
- Wallet separation.
- Cashier expected-total denial.
- Alert creation/resolution/source links.
- Filters/pagination/sort.
- Large range limit.
- PDF/Excel permission/redaction.
- Export formula safety by inspection.
- Drill-down reconciliation.
- RTL/LTR, responsive, print/download, console/network.

No automated tests are created or executed.
