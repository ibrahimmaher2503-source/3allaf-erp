# 26 — Discount, Return, Exchange, and Refund Policy

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Derived implementation policy based on the approved PRD  
**Authority:** POS-05–POS-07 and RET-01–RET-04  
**Owner-configurable values:** Discount limits, return window, refund limits, and exception reasons  
**Production decision pending:** Final commercial return terms and finance approval limits

---

## 1. Purpose

This policy defines local implementation rules for non-stacking discounts, return validation, product inspection, exchanges, refunds, and Gift Card settlement.

---

## 2. Requirement Classification

| Item | Classification |
|---|---|
| Only one discount type may apply to an amount | PRD Requirement |
| Replace rather than stack | PRD Requirement |
| Validate invoice or Gift Receipt | PRD Requirement |
| Same-item and different-item exchange | PRD Requirement |
| Cash refund or Gift Card settlement | PRD Requirement |
| Product condition inspection | PRD Requirement |
| Damaged/non-saleable process | PRD Requirement |
| Exact return window | Owner-Configurable Value |
| Exact discount/refund limits | Owner-Configurable Value |
| No-reference exceptions | Owner-Configurable Value |

---

## 3. Discount Rules

1. Discounts do not stack on the same amount.
2. Applying a second discount requires explicit replacement.
3. Item and invoice discount interaction must preserve the one-discount rule.
4. Limits are configurable by role.
5. Override requires permission, reason, and audit.
6. Printed totals show original price, discount, net, optional tax, and final total.

---

## 4. Return Reference

Primary references:

- Original invoice.
- Gift Receipt.

No-reference returns are not assumed. If later allowed, they require explicit policy, permission, reason, and limits.

---

## 5. Return Inspection

Before stock disposition, record condition such as:

- Sellable.
- Non-saleable.
- Damaged.
- Requires manager review.

Exact condition list remains configurable.

---

## 6. Settlement Options

Supported outcomes:

- Same-item exchange.
- Different-item exchange with difference settlement.
- Cash refund.
- Gift Card equal to eligible return value.

Each outcome preserves source references and audit.

---

## 7. Return Window and Limits

- Return eligibility period is configurable.
- Cash refund limits are configurable.
- Manager approval may be required based on value, condition, missing reference, or timing.
- No numeric default is treated as final production policy.

---

## 8. Stock Disposition

1. Sellable items may return to saleable stock.
2. Damaged/non-saleable items use a separate controlled disposition.
3. Stock movement occurs only through an approved return/exchange process.
4. Duplicate/excess returns are blocked.

---

## 9. Gift Receipt

1. Contains no prices.
2. Identifies the original sale.
3. Supports validation for return/exchange.
4. Does not expose purchase values to the recipient.

---

## 10. Manual Browser Verification

Verify:

1. Discount replacement and no stacking.
2. Discount permission and limits.
3. Invoice/Gift Receipt validation.
4. Duplicate/excess return block.
5. Condition inspection.
6. Same/different exchange.
7. Cash refund.
8. Gift Card settlement.
9. Unauthorized and out-of-window paths.
10. RTL/LTR, print, responsive, console, and network.

No automated tests are created or executed.
