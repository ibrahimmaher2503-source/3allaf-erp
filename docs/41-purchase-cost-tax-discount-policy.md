# 41 — Purchase Cost, Rounding, Tax, and Discount Policy

**Product:** TOY & JOY
**Phase:** Phase 1
**Status:** Owner-approved local policy baseline — DEC-050 (2026-08-06)
**Authority:** PUR-04, PUR-05, MD-01, INV-01
**Blockers:** BLK-008 (currency, tax, precision), BLK-010 (purchasing terms, discounts/tax)
**Owner-configurable values:** Precision, rounding mode, tax applicability/rate, discount limits
**Production boundary:** DEC-050 approves the documented values for the local/dev baseline. Real production tax registration, currency configuration, and release/UAT evidence remain environment and owner-operational prerequisites.

---

## 1. Purpose

This policy defines how a purchase invoice converts quantities, unit costs, discounts, and tax into an inventory cost that is posted to `stock_movements` and `stock_balances.average_cost`.

`docs/24-pricing-policy.md` governs **selling** price. This document governs **purchase cost only**. A purchase-cost change never changes a selling price automatically (PRC-03).

---

## 2. Requirement Classification

| Item | Classification |
|---|---|
| Approval updates inventory cost using weighted-average costing | PRD Requirement (PUR-05) |
| Approval posts an auditable stock movement atomically | PRD Requirement (PUR-05) |
| Money is fixed decimal, never binary floating point | Approved standard (`docs/09`, `docs/07`) |
| Cost fields are separately permissioned | Approved standard (`docs/22` §247) |
| Exact decimal places for unit cost / line / document / WAC | **DEC-050 approved baseline — OI-COST-01** |
| Rounding mode | **DEC-050 approved baseline — OI-COST-02** |
| Tax inside or outside inventory cost | **DEC-050 approved baseline — OI-COST-05** |
| Discount reduces inventory cost or not | **DEC-050 approved baseline — OI-COST-06** |
| Duplicate product line handling | **DEC-050 approved baseline — OI-COST-09** |
| Currency and multi-currency support | **DEC-050 approved baseline — OI-COST-10** |

---

## 3. Precision and Rounding

Until the owner decides, the following are **proposals only** and must not be treated as approved:

| Value | Proposed default | Decision key |
|---|---|---|
| Storage type (money) | `decimal(19,4)` per `docs/07` §9 | OI-COST-01 |
| Unit cost decimals | 4 (stored), 2–4 displayed | OI-COST-01 |
| Line total decimals | 2 | OI-COST-01 |
| Invoice total decimals | 2 | OI-COST-01 |
| Weighted-average cost decimals | 4 (higher than display, to limit drift) | OI-COST-01 |
| Rounding mode | Half-up | OI-COST-02 |
| Currency | Single company currency from `companies.currency` | OI-COST-10 |
| Multi-currency in one invoice | Not supported in Phase 1 | OI-COST-10 |

Rules that hold regardless of the owner's answer:

1. Rounding happens once per defined step, never repeatedly on an already-rounded value.
2. The rounded line total is stored, not recomputed at read time.
3. The document total is the sum of stored rounded line totals plus document-level adjustments; it is never recomputed from raw unrounded values.
4. Any residual difference between the supplier's printed total and the computed total is stored as an explicit variance field, never silently absorbed into cost.

---

## 4. Calculation Order

Proposed canonical order — **DEC-050 approved baseline OI-COST-07**:

```
1. quantity                    (validated, fractional only where permitted)
2. gross line = quantity x unit cost
3. line discount               (OI-DISC-02)
4. allocated document discount (OI-DISC-02)
5. taxable base
6. tax                         (OI-TAX-03 line-level or document-level)
7. rounding                    (OI-COST-02)
8. inventory cost basis        (OI-COST-05 / OI-COST-06 decide inclusion)
```

The implementation must expose this order as a single named calculation service (`docs/22` §5 permits "Weighted-average cost calculation" as a valid service). No calculation may be duplicated inside Livewire components, imports, or print views.

---

## 5. Weighted-Average Cost

Proposed formula — **DEC-050 approved baseline OI-COST-03**:

```
new_average = ((existing_on_hand x existing_average) + (received_quantity x received_unit_cost_basis))
              / (existing_on_hand + received_quantity)
```

Scoping question that must be answered before any code: is `average_cost` maintained **per product per store** or **per product company-wide**? `docs/36` §9 models `stock_balances` as product/store unique with an `average_cost` column, which implies per-store. This must be confirmed — **OI-COST-04**.

Edge cases requiring an explicit owner answer:

| Case | Proposed handling | Decision key |
|---|---|---|
| First receipt, on-hand = 0 | New average = received unit cost basis | OI-COST-08 |
| On-hand negative at posting time | Block posting; require override permission and reason | OI-COST-08 |
| Received quantity = 0 | Reject line | OI-COST-08 |
| Unit cost = 0 | Allow only with explicit permission, reason, and audit | OI-COST-08 |
| Unit cost negative | Always reject | OI-COST-08 |
| Same product on two lines | Calculate separately, post two movements, one combined WAC update | OI-COST-09 |

---

## 6. Tax

Nothing about tax may be assumed. `tax_settings` exists in the Platform module but carries no approved production values (BLK-008).

| Question | Decision key |
|---|---|
| Is purchase tax applied at all in Phase 1? | OI-TAX-01 |
| Scope: per product, per supplier, per store, or global? | OI-TAX-02 |
| Prices tax-inclusive or tax-exclusive? | OI-TAX-03 |
| Rounding at line level or document level? | OI-TAX-03 |
| Tax decimal places | OI-TAX-04 |
| Tax presentation on screen and A4 print | OI-TAX-05 |

If the answer to OI-TAX-01 is "no tax in Phase 1", the invoice must still store a tax structure with zero values so that enabling tax later does not require a data migration of historical documents.

---

## 7. Discount

| Question | Proposed default | Decision key |
|---|---|---|
| Discount type | Percentage and fixed amount both supported | OI-DISC-01 |
| Level | Line and document, with document discount allocated to lines pro-rata by line value | OI-DISC-02 |
| Before or after tax | Before tax | OI-DISC-03 |
| Maximum discount | DEC-050 approved baseline | OI-DISC-04 |
| Who may exceed the limit | Approval permission, never a role default | OI-DISC-05 |
| Zero discount | Allowed | OI-DISC-06 |
| Negative discount | Always rejected | OI-DISC-06 |
| Multiple discounts on one line | Sequential, in stored order, never compounded silently | OI-DISC-07 |

A document-level discount must be allocated to lines before the inventory cost basis is computed. An unallocated document discount cannot enter weighted-average cost and would corrupt valuation.

---

## 8. Non-Negotiable Invariants

These do not depend on any owner answer and must be enforced in code:

1. Cost posting and stock posting occur in one database transaction. Partial posting is impossible.
2. An approved purchase invoice is immutable (`docs/19`). Corrections use a referenced correction document.
3. Every WAC change writes an audit event with before/after values in the same transaction.
4. Posting is idempotent via `stock_movements.idempotency_key`. A retried approval never doubles stock or cost.
5. Concurrent approval against a stale balance version is rejected and rolled back (AC-PUR-05).
6. `unit cost`, `total cost`, and `average cost` are permission-gated fields, hidden from Cashier by default.

---

## 9. Manual Browser Verification

Verify:

1. Line total, document total, and WAC match the approved formula for a hand-calculated example.
2. Rounding produces the same result on screen, in print, and in the stored record.
3. First receipt against zero on-hand sets the average correctly.
4. Two lines of the same product produce the expected single balance.
5. Negative or zero cost is handled per the approved decision.
6. Tax and discount inclusion match the approved decision exactly.
7. A retried approval does not double stock or cost.
8. Cost fields are hidden from an unauthorized role.
9. RTL/LTR, responsive, console, and network are clean.

No automated tests are created or executed.
