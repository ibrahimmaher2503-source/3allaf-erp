# 24 — Pricing and Label Policy

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Derived implementation policy based on the approved PRD  
**Authority:** PRC-03–PRC-08  
**Owner-configurable values:** Approval thresholds, open-price limits, branch exceptions, and label settings  
**Production decision pending:** Final price authority, effective timing, and label hardware/layouts

---

## 1. Purpose

This policy defines local implementation conventions for price proposals, price versions, effective prices, open-price controls, unpriced products, and label queues.

---

## 2. Requirement Classification

| Item | Classification |
|---|---|
| Cost change must not automatically change selling price | PRD Requirement |
| Immutable price-list versions | PRD Requirement |
| One active shelf price per item/location | PRD Requirement |
| Pricing approval when configured | PRD Requirement |
| Label queue based on remaining stock by location | PRD Requirement |
| Unpriced item cannot be sold or labeled | PRD Requirement |
| Open price requires authorization and configured bounds | PRD Requirement |
| Exact approval limit | Owner-Configurable Value |
| Exact min/max deviation | Owner-Configurable Value |
| Automatically percentage-derived selling-price rounding | Owner Approved — upward to the next 5 EGP using decimal-safe arithmetic |
| Exact label dimensions/printer | Production Decision Pending |

---

## 3. Price States

- `draft`
- `submitted`
- `approved`
- `rejected`
- `superseded`
- `cancelled`

Approved price versions are immutable.

---

## 4. Price Sources

Prices may originate from:

- Product card.
- Approved Excel import.
- Purchase invoice context after receiving-store selection.

A source price remains a proposal until approved where approval is configured.

---

## 5. Effective Price Rules

1. A location may have only one active price for the same item and price context.
2. Approving a new version supersedes the prior active version.
3. Historical sales keep their original values.
4. Effective date/time is configurable.
5. Branch exceptions require explicit authorization.
6. Stale or concurrent approval must be blocked safely.
7. A positive automatically percentage-derived selling price rounds upward with `ceiling(calculated_price / 5) × 5`; Product Card base prices and explicit manual overrides remain verbatim.

---

## 6. Cost-Triggered Review

1. A purchase-cost change may create a pricing-review signal.
2. It must not automatically change the sale price.
3. Pricing Officer review remains separate from purchase approval.
4. Historical prices remain unchanged.

---

## 7. Unpriced Products

A product without an approved sale price:

- Shows price zero and a visible pricing-pending state.
- May be received into stock.
- Cannot be sold.
- Cannot generate a printable barcode label.

---

## 8. Open Price

Open-price selling requires:

- Explicit permission.
- Reference price.
- Configured minimum and maximum.
- Mandatory reason where configured.
- Audit record.
- Branch/store scope.
- Denial while offline if conflict-prone.

Exact limits remain configurable.

---

## 9. Label Queue

After price approval:

1. Queue quantity is based on remaining stock by location.
2. Queue is tied to a selected store/branch.
3. The approved local price or approved branch exception is used.
4. Queue generation is idempotent.
5. Reprint is separately audited.
6. Unpriced products are blocked.

---

## 10. Approval Separation

Where configured, the proposer must not approve the same price version.

Self-approval requires explicit permission and configured limits.

---

## 11. Manual Browser Verification

Verify:

1. New version supersedes prior version.
2. Historical sales remain unchanged.
3. Cost change creates review but no automatic selling-price update.
4. Unpriced item is blocked from sale and label.
5. One active price per location.
6. Open-price bounds and reason.
7. Unauthorized approval and branch exception denial.
8. Label queue quantity/location correctness.
9. Retry does not duplicate queue entries.
10. RTL/LTR, responsive, console, and network.

No automated tests are created or executed.
