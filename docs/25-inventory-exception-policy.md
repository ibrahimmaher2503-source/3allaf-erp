# 25 — Inventory Exception and Reconciliation Policy

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Derived implementation policy based on the approved PRD  
**Authority:** INV-01–INV-09  
**Owner-configurable values:** Approval limits, reason lists, disposition locations, and count tolerances  
**Production decision pending:** Final warehouse reasons, thresholds, and opening inventory method

---

## 1. Purpose

This policy defines local rules for negative stock, inventory entries/exits/adjustments, transfer differences, fractional quantities, counts, recounts, and reconciliation.

---

## 2. Requirement Classification

| Item | Classification |
|---|---|
| Negative stock blocked by default | PRD Requirement |
| Authorized override when permitted | PRD Requirement |
| Fractional quantities only for configured products | PRD Requirement |
| Transfer difference states | PRD Requirement |
| Count while selling continues | PRD Requirement |
| Uncounted items must never be auto-zeroed | PRD Requirement |
| Counter cannot approve reconciliation | PRD/Role Requirement |
| Exact variance threshold | Owner-Configurable Value |
| Exact reason catalog | Owner-Configurable Value |
| Exact damaged/refused destination | Owner-Configurable Value |

---

## 3. Negative Stock

1. Negative stock is blocked by default.
2. Override requires explicit permission.
3. Override requires reason and audit.
4. Limits may be quantity- or value-based.
5. Offline negative-stock override is not assumed.
6. Exact limits remain configurable.

---

## 4. Inventory Document Types

Supported controlled documents include:

- Entry.
- Exit.
- Exchange/adjustment.
- Transfer.
- Count adjustment.
- Referenced correction/reversal.

Each requires store, products, quantities, responsible user, and reason where applicable.

---

## 5. Transfer Differences

Difference types may include:

- Shortage.
- Damage.
- Refusal.
- Wrong item.
- Excess.
- Other with explanation.

The final disposition is configurable and requires authorized review.

---

## 6. Fractional Quantities

1. Whole quantities by default.
2. Fractions only when the product allows them.
3. Precision remains configurable.
4. Mixed invalid precision is rejected.
5. Historical movements preserve exact quantity and unit.

---

## 7. Stock Counts

Supported count scopes:

- Full branch.
- Store.
- Category.
- Supplier.
- Partial selected scope.

Supported input:

- Barcode scan.
- Manual entry.
- Recount.
- Draft discrepancy review.

---

## 8. Count While Selling Continues

For each counted item:

1. Capture reference balance.
2. Capture movements after reference time.
3. Capture verified counted quantity.
4. Calculate reconciliation against intervening activity.
5. Preserve the calculation inputs and result.

---

## 9. Uncounted Items

1. Never automatically set to zero.
2. Enter a review list.
3. Require Stock Counter review and Warehouse Manager approval before adjustment.
4. Preserve why the item was uncounted.
5. Allow recount or explicit disposition.

---

## 10. Separation of Duties

- Stock Counter may input and submit.
- Stock Counter cannot approve reconciliation.
- Warehouse Manager or authorized manager approves.
- Self-approval is not implied.

---

## 11. Manual Browser Verification

Verify:

1. Negative stock default block.
2. Authorized override with reason.
3. Unauthorized override denial.
4. Fractional and whole-only products.
5. Transfer shortage/damage/refusal paths.
6. Full and partial counts.
7. Selling during count.
8. Reconciliation formula inputs.
9. Uncounted review and no auto-zero.
10. RTL/LTR, responsive, console, and network.

No automated tests are created or executed.
