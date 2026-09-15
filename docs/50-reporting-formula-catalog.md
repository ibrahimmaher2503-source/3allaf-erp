# 50 — Reporting Formula Catalog

**Product:** TOY & JOY
**Phase:** Phase 1
**Status:** Derived implementation specification — team-adopted defaults, owner approval outstanding
**Authority:** RPT-01–RPT-03, Required Report Groups
**Blockers:** BLK-017 — "KPI formulas, margin access, report layouts, export limits" recorded as missing

---

## 1. Purpose

`docs/34` specifies the dashboard, report, and alert surfaces. It does not define the arithmetic behind them. BLK-017 names this gap directly.

AC-RPT-01 requires that every widget reconciles to its source transactions and that an undefined formula fails review. This catalog is what makes that check possible.

---

## 2. Governing Rules

1. Every figure traces to source transactions — never to a cached aggregate that cannot be re-derived.
2. Every query is scoped by role, branch, store, and activity type. Cross-scope leakage is a defect, not a configuration issue.
3. Margin and cost figures are separately permissioned (`docs/22` §247). A user with sales access does not implicitly get margin access.
4. Retail and party figures never merge in one total unless the report explicitly declares a combined view.
5. Reports read committed, approved documents. Drafts appear only where the report name says so.

---

## 3. Sales and POS

| Figure | Formula |
|---|---|
| Gross sales | Σ approved invoice line gross (before discount) |
| Total discount | Σ line discount + allocated invoice discount |
| Net sales | Gross sales − total discount |
| Tax collected | Σ invoice tax where tax was enabled on that invoice (POS-04) |
| Total sales | Net sales + tax |
| Invoice count | Count of approved invoices, excluding cancelled |
| Average invoice value | Net sales ÷ invoice count |
| Cost of sales | Σ `sale` movement (quantity × stored consumed cost) — `docs/47` CF-01 |
| Gross margin | Net sales − cost of sales |
| Margin % | Gross margin ÷ net sales |

Cost of sales uses the cost **stored on the movement**, never a recomputed current average. This is why `docs/47` requires it to be stored.

Returns are reported separately and subtracted in a stated net-of-returns figure, never silently netted into gross sales.

---

## 4. Inventory

| Figure | Formula |
|---|---|
| On hand | `stock_balances.on_hand` per product/store |
| Available | On hand − reserved |
| In transit | Σ dispatched but unreceived transfer quantity |
| Stock valuation | Σ (on hand × average cost) + in-transit carried value — `docs/47` §6 |
| Low stock | On hand ≤ reorder threshold and > 0 |
| Zero stock | On hand = 0 |
| Unpriced products | Active products with no approved sale price (`docs/24` §7) |
| Count difference | Counted quantity − reference balance adjusted for movements during count (INV-08) |

Valuation must reconcile to the signed sum of movement values. A mismatch is a ledger defect.

---

## 4b. Inventory Ageing, Turnover, and Dead Stock

Not named in RPT-01's Inventory group, but required in practice: on-hand and valuation tell you what you own, not whether it is moving. These three figures are what expose capital buried in stock that has not sold.

All three read the existing `stock_movements` ledger and need no new schema.

| Figure | Formula |
|---|---|
| Last inbound date | Most recent inbound movement date for product/store (`purchase_receipt`, `transfer_in`, `adjustment_in`, `opening`) |
| Last outbound date | Most recent outbound movement date (`sale`, `transfer_out`, `party_consumption`, `adjustment_out`) |
| Stock age (days) | Current date − last inbound date, per product/store |
| Ageing buckets | 0–90, 91–180, 181–360, over 360 days |
| Ageing value per bucket | Σ (on hand × average cost) for products in that bucket |
| Cost of sales for period | Σ (`sale` quantity × stored consumed cost) over the period — `docs/47` CF-01 |
| Average inventory value | (opening valuation + closing valuation) ÷ 2 for the period |
| Turnover ratio | Cost of sales for period ÷ average inventory value |
| Days of inventory | Period days ÷ turnover ratio |
| Dead stock | On hand > 0 and no outbound movement within the configured dead-stock window |

Owner-configurable via `docs/46`, proposed defaults: ageing buckets as above; dead-stock window 180 days; turnover computed monthly.

Notes that decide correctness:

1. Ageing is measured from **last inbound**, not from product creation date. A product created two years ago and restocked last week is not old stock.
2. Turnover uses **cost of sales**, not sales revenue. Using revenue against a cost-valued inventory inflates the ratio by the margin and makes the figure meaningless for comparison.
3. Both are computed **per store**, consistent with per-store weighted average (`DEC-043` A-02). A company-wide roll-up is a sum of store figures, never a recomputation on merged balances.
4. Ageing and turnover expose cost. They inherit the cost/margin permission in §2 rule 3, not the general inventory-view permission.

---

## 4c. Profitability

RPT-01 lists top products, average invoice value, and stock value, but names no margin figure. Margin is nevertheless the number the owner will ask for first, and every input for it already exists.

| Figure | Formula |
|---|---|
| Gross margin | Net sales − cost of sales (§3) |
| Margin % | Gross margin ÷ net sales |
| Margin by product | Per-product net sales − Σ that product's `sale` movement cost |
| Margin by category / brand | Aggregation of product margin |
| Margin by branch / store | Aggregation scoped to the selling store |
| Discount impact on margin | Σ discount granted ÷ gross margin before discount |
| Top products by revenue | Rank by net sales |
| Top products by margin | Rank by gross margin |
| Cost trend per product | Weighted-average cost by month from inbound movements |

Ranking by revenue and ranking by margin produce **different lists**. Both must be available, because a purchasing decision made from the revenue list alone will restock the products that sell most while earning least.

Margin is a separately permissioned capability. A Branch Manager may hold sales access without cost or margin access; the report must return a permission denial, not a zeroed column.

---

## 5. Purchasing

| Figure | Formula |
|---|---|
| Purchases | Σ approved purchase invoice totals |
| Purchases at cost basis | Σ line inventory cost basis (`docs/41` §4) |
| Supplier returns | Σ approved supplier return value |
| Net purchases | Purchases − supplier returns |
| Last supplier price | Most recent approved invoice line cost for product/supplier (PUR-01) |
| Open PO value | Σ approved PO lines not yet fully received |

Opening stock is excluded from every purchasing figure (`docs/44` §3).

---

## 6. Cash and Shifts

| Figure | Formula |
|---|---|
| Expected cash | Opening balance + cash sales + cash in − cash out − refunds paid in cash |
| Expected electronic | Σ electronic payment rows for the shift |
| Variance | Actual − expected, per method |
| Drawer balance | Σ movements on the drawer since last reconciliation |

CSH-02 forbids exposing expected values to the cashier before submission. Any report or endpoint carrying these figures is manager/reviewer scoped only.

---

## 7. Customers, Loyalty, and Wallets

Points and wallet figures follow `docs/52`. Product Wallet and Party Wallet never appear in a shared total (CUS-02). A report showing one must not expose the other, including in export columns.

---

## 8. Alerts

RPT-02 lists the required alerts. Each needs a threshold, an evaluation cadence, and a recipient scope:

low stock, zero stock, unpriced products, pending price approval, transfer receipt pending, count difference pending, open/unclosed invoices, shift variance, upcoming party commitments, overdue party balances, asset return/damage issues.

Thresholds and cadence are owner-configurable (`docs/46`), defaulting to daily evaluation with branch-scoped recipients. An alert never carries a figure the recipient is not permitted to see.

---

## 9. Export

RPT-03 requires PDF and Excel export by permission, with filters across date range, branch, store, user, supplier, product, category, payment method, customer, party status, and document status.

Export is a distinct permission from view. An export inherits the scope of the query that produced it and records an audit event with the filter set applied.

Row limits are owner-configurable; unbounded export is prohibited (NFR-05).

---

## 10. Manual Browser Verification

Verify: each figure recomputed by hand from source rows matches the widget; ageing buckets computed from last inbound date and not from product creation; turnover computed against cost of sales rather than revenue; dead-stock list excludes products with recent outbound movement; ranking by revenue and by margin produce different lists where expected; a role without margin access receives a denial rather than zeros; margin hidden from a non-margin role; a cross-scope filter returns nothing rather than other branches' data; valuation reconciles to movement sums; expected cash unreachable from a cashier session; Product and Party wallet never appear together; export respects scope, records an audit event, and refuses an unbounded range; RTL/LTR and mobile clean.

No automated tests are created or executed.
