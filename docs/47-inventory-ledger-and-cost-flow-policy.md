# 47 — Inventory Ledger and Cost Flow Policy

**Product:** TOY & JOY
**Phase:** Phase 1
**Status:** DEC-052 engineering baseline adopted for TSK-016 Local/Dev structure and cost-flow enforcement; return-reason catalog content and numeric approval limits remain owner/configuration inputs.
**Authority:** INV-01–INV-09, PUR-05, PUR-06, RET-02, RET-03, NFR-01, NFR-02
**Blockers:** BLK-010 only for production supplier data; no blocker remains for the TSK-016 local schema/eligibility/cost foundation.
**Companion to:** `docs/41` (inbound cost), `docs/25` (exception policy)

---

## 1. Purpose

`docs/41` defines how cost **enters** inventory. This document defines how cost **moves and leaves** it.

Nothing in `docs/` currently answers: at what cost does a sale consume stock, at what cost does a return re-enter it, and what happens to cost when stock moves between two stores that each hold their own weighted average. A search of the documentation set for `COGS` or `cost of goods` returns nothing.

This is the highest-consequence gap in the remaining tasks. Every rule here is irreversible once movements are posted.

---

## 2. Requirement Classification

| Item | Classification |
|---|---|
| Movement history by product and store | PRD Requirement (INV-01) |
| Stateful transfer with destination confirmation | PRD Requirement (INV-03) |
| Adjustments require reason, store, user, approval | PRD Requirement (INV-04) |
| Negative stock blocked; override audited | PRD Requirement (INV-05) |
| Uncounted items never auto-zeroed | PRD Requirement (INV-09) |
| Reversal references the original document | PRD Requirement (NFR-02) |
| Cost consumed by a sale | **PENDING — CF-01** |
| Cost applied to a return | **PENDING — CF-02** |
| Cost carried by a transfer | **PENDING — CF-03** |
| Cost of an adjustment | **PENDING — CF-04** |
| Supplier return cost reversal | **ADOPTED — DEC-052 / CF-05** |
| Count reconciliation cost | **PENDING — CF-06** |

---

## 3. Movement Type Catalog

Every row in `stock_movements` carries exactly one type. The type determines sign and cost behaviour. No type may be added at runtime.

| Type | Sign | Source | Cost behaviour |
|---|---|---|---|
| `purchase_receipt` | + | Purchase invoice approval | Sets cost; recalculates WAC (`docs/41` §5) |
| `purchase_return` | − | Supplier return | Reverses at the invoice's original cost (CF-05) |
| `sale` | − | POS invoice | Consumes at current WAC (CF-01); does not change WAC |
| `sale_return` | + | Retail return | Re-enters at the original sale's consumed cost (CF-02) |
| `transfer_out` | − | Transfer dispatch | Removes at source WAC (CF-03) |
| `transfer_in` | + | Transfer receipt | Enters destination at the cost carried by the movement |
| `adjustment_in` | + | Inventory entry | Requires an explicit cost (CF-04) |
| `adjustment_out` | − | Inventory exit | Removes at current WAC |
| `count_adjustment` | ± | Count reconciliation | Follows CF-06 |
| `opening` | + | Opening adjustment | Seeds WAC (`docs/44`) |
| `party_consumption` | − | Party operating order | Consumes at current WAC |
| `damage_writeoff` | − | Damage/quarantine | Removes at current WAC |

`stock_movements` is append-only. A correction is a new movement referencing the original, never an edit (NFR-02).

---

## 4. Cost Flow Rules

### CF-01 — Sale

Proposed: a sale consumes at the **current weighted average of the selling store at posting time**, and the consumed unit cost is **stored on the movement**.

Storing it matters. Without it, a return three months later has no cost to reverse and margin reports cannot be reproduced.

A sale never changes the weighted average. Only inbound movements do.

### CF-02 — Retail Return

Proposed: the return re-enters at the **cost consumed by the original sale movement**, read from that movement, not from today's WAC.

Alternative rejected: re-entering at current WAC lets a customer return an item at a cost different from the one that left, silently creating or destroying inventory value. RET-01 requires the original invoice to be validated anyway, so the source cost is always reachable.

Returning to stock re-averages the destination balance. A rejected or damaged item follows RET-03 and posts `damage_writeoff` instead of `sale_return`.

### CF-03 — Transfer

With per-store weighted average (`DEC-043` A-02), a transfer is an outbound at source cost and an inbound at that same cost.

Proposed: the `transfer_out` movement stores the source WAC; `transfer_in` uses the stored value, not the destination's current average. The destination re-averages on receipt.

In-transit quantity holds cost. It belongs to neither store's on-hand balance but must appear in total inventory valuation, or the balance sheet loses value during transit.

Shortage, damage, and refusal recorded at destination (INV-03) each post their own typed movement at the carried cost, never a silent quantity reduction.

### CF-04 — Adjustments

`adjustment_in` cannot use a weighted average, because there may be no balance to average against. It requires an explicit unit cost with a reason and approval (INV-04).

Proposed default cost source: last purchase cost for that product, editable by an authorised user, never zero by default. Zero-cost inbound adjustment permanently dilutes the average.

`adjustment_out` removes at current WAC and does not change it.

### CF-05 — Supplier Return

**Adopted by DEC-052:** a supplier return is eligible only when every return line references an approved purchase-invoice line. The `purchase_return` movement reverses at that original line's stored `unit_cost`, never at current WAC and never at an inferred or fallback cost. The balance is re-averaged after the signed outbound movement.

A missing source reference is rejected in Phase 1. It is not a cost-selection problem. Legacy/unknown stock follows a separate `adjustment_out` path with explicit cost and approval; it is not a supplier return.

Return reason is required from the `supplier_return_reasons` catalog. The table is intentionally created without seed rows until the owner supplies the reporting/evaluation catalog. Numeric approval limits are resolved only from an effective `financial_setting_versions` row whose linked `ApprovalRecord` is `approved`; a `locked_at` value alone never activates an unapproved setting. No production values are invented here.

### CF-06 — Count Reconciliation

A count difference is a quantity finding, not a cost finding.

Proposed: shortages post `count_adjustment` outbound at current WAC; surpluses post inbound at current WAC, so the average is unchanged by a count. A count should never move valuation per unit — only total value.

INV-08 requires reconciliation against movements that occurred during the count. The reference balance and the movements captured during counting must both be stored on the count session, so the arithmetic is reproducible after approval.

---

## 5. Invariants

1. Every movement stores the unit cost it moved at. No movement is cost-less.
2. Only inbound movement types recalculate the weighted average.
3. `stock_balances` is derived state; `stock_movements` is the truth. A rebuild of balances from movements must reproduce the balances exactly, and a reconciliation job must be able to prove it.
4. Quantity and cost post in the same transaction as the source document approval.
5. Negative stock is blocked by default (INV-05); an override records reason, actor, and audit. A negative balance makes weighted average mathematically undefined — the override must therefore also record the cost basis used.
6. Every movement carries an idempotency key. Reposting never doubles.
7. Uncounted items enter review; they are never zeroed (INV-09).

---

## 6. Valuation Reporting

Stock valuation (`RPT-01`, Inventory report group) is:

```
store valuation = Σ (on_hand × average_cost) per product
in-transit valuation = Σ (quantity × carried_cost) per transfer
total = store valuations + in-transit
```

Valuation must reconcile to the sum of signed movement values. If it does not, the ledger has been written outside the approved path and the discrepancy is a defect, not a rounding artifact.

---

## 7. Manual Browser Verification

Verify: a sale stores the consumed cost and leaves WAC unchanged; a return re-enters at the original cost and re-averages; a transfer preserves value across two stores with different averages; in-transit value appears in total valuation; a shortage at destination posts its own movement; an inbound adjustment refuses a zero default cost; a supplier return reverses at the invoice cost; a count difference does not change unit cost; negative-stock override records its cost basis; balances rebuilt from movements match stored balances exactly.

No automated tests are created or executed.
