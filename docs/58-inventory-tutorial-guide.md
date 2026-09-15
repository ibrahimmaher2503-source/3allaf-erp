# Inventory Tutorial Guide — TSK-019–TSK-022

**Status:** Implemented for Local/Demo browser guidance
**Updated:** 2026-08-07
**Scope:** Inventory screens UI-INV-001–UI-INV-011 only

## 1. How to open the guide

1. Sign in through the local Demo Auth route using the configured local Demo identity. Do not place passwords, tokens, or secrets in this document; sensitive values remain `[REDACTED]`.
2. Open one of the Inventory routes below.
3. Click the `?` Page Guide launcher in the application shell.
4. Read the bilingual purpose, approved actions, steps, fields, warnings, errors, FAQ, and next step.
5. Click **Start Interactive Tour** to highlight the real page regions. Missing or unavailable targets are skipped safely.
6. Use **Open Full Guide** or **Open User Flow** for the authenticated full-screen reference.

The guide is permission-aware: it explains available actions using capability keys and does not grant access. A user without Inventory permission must receive the normal denial state without record-existence leakage.

## 2. Screen map

| Screen ID | Page | Named route | Required read capability | Main workflow |
|---|---|---|---|---|
| UI-INV-001 | Inventory Control Center | `/inventory` · `inventory.index` | `inventory_stock_card.view` | `FLW-INV-01` |
| UI-INV-002 | Stock Card | `/inventory/products/{product}` · `inventory.stock-card` | `inventory_stock_card.view` | `FLW-INV-01` |
| UI-INV-003 | Movement Ledger | `/inventory/movements` · `inventory.movements` | `inventory_stock_card.view` | `FLW-INV-01` |
| UI-INV-004 | Transfers | `/inventory/transfers` · `inventory.transfers` | `transfers.view` | `FLW-INV-02` |
| UI-INV-005 | Transfer Dispatch | `/inventory/transfers/{id}/dispatch` · `inventory.transfers.dispatch-page` | `transfers.view` | `FLW-INV-02` |
| UI-INV-006 | Transfer Receipt | `/inventory/transfers/{id}/receive` · `inventory.transfers.receive-page` | `transfers.view` | `FLW-INV-02` |
| UI-INV-007 | Difference Review | `/inventory/transfers/{id}/differences` · `inventory.transfers.differences` | `transfers.difference` | `FLW-INV-02` |
| UI-INV-008 | Stock Counts | `/inventory/counts` · `inventory.counts` | `inventory_stock_card.view` | `FLW-INV-06`, `FLW-INV-07` |
| UI-INV-009 | Count Entry | `/inventory/counts/{id}/entry` · `inventory.counts.entry` | `stock_counts.view` | `FLW-INV-06` |
| UI-INV-010 | Count Reconciliation | `/inventory/counts/{id}/reconcile` · `inventory.counts.reconcile-page` | `stock_counts.reconcile` | `FLW-INV-06`, `FLW-INV-07` |
| UI-INV-011 | Entries, Exits, Adjustments | `/inventory/adjustments` · `inventory.adjustments` | `inventory_stock_card.view` | `FLW-INV-03`, `FLW-INV-04`, `FLW-INV-05` |

## 3. Page-by-page operating tutorial

### UI-INV-001 — Inventory Control Center

1. Confirm the page is marked **Local Demo data only**.
2. Read the summary counters for balances, movements, transfers, adjustments, and counts.
3. In balances, compare `on-hand`, `reserved`, `available`, and `in-transit` per visible store.
4. Open a product stock card when one product needs investigation.
5. Review transfers, adjustments, counts, and the append-only ledger before taking an action.

`available = on-hand - reserved`; `in-transit` remains separate until receipt. The overview is scoped to stores visible to the current user.

### UI-INV-002 — Stock Card

1. Confirm the product code and visible store.
2. Compare the product balance fields by store.
3. Read WAC only when `inventory_stock_card.cost_view` is available.
4. Use the product ledger to trace positive and negative movements to their sources.
5. Treat Demo cost as an operational fixture, not final accounting evidence.

### UI-INV-003 — Movement Ledger

1. Start with the posted time, product, store, and movement type.
2. Read the quantity sign: positive adds stock; negative consumes stock.
3. Trace `source_type` and `source_id` to the transfer, adjustment, or count workflow.
4. Match movements to `on-hand` within the same store/product scope.
5. Do not edit or delete movements; posting is append-only.

### UI-INV-004 — Transfers

1. Confirm source store, destination store, transfer lines, and current status.
2. Follow the local state sequence: submitted → approved → in transit → received or `difference_review`.
3. Use only the action visible for the current state and capability.
4. After dispatch or receipt, verify the resulting ledger movement.
5. Never approve a transfer outside the visible store scope.

### UI-INV-005 — Transfer Dispatch

1. Confirm the source store and that the transfer is approved.
2. Review every transfer line before dispatch.
3. Submit dispatch once; the backend protects the lifecycle from repeated processing.
4. Verify the `transfer_dispatch` movement and `in-transit` state.
5. Dispatch does not mean the destination has received the goods.

### UI-INV-006 — Transfer Receipt

1. Confirm the destination and shipment reference.
2. Enter `received_quantities[<line_id>]` for **every** transfer line; do not submit one aggregate quantity.
3. If actual quantity differs, select only `shortage`, `damage`, or `refusal` and provide a reason.
4. Submit once and expect either `received` or `difference_review`.
5. Verify `transfer_receipt`, destination balance, and in-transit effect in the ledger.
6. Do not retry receipt after `difference_review`; use the difference review screen.

### UI-INV-007 — Transfer Difference Review

1. Confirm that the transfer is in `difference_review` and the difference is under review.
2. Review the difference for every line, not only the first product.
3. Select the server-allowlisted type: `shortage`, `damage`, or `refusal`.
4. Enter a clear, auditable resolution reason.
5. Resolve once, then verify the state and audit trail. Resolution is not a second receipt.

### UI-INV-008 — Stock Counts

1. Confirm store, count scope, and whether the session is `full` or `partial`.
2. Track the lifecycle: draft/in progress → submitted → reconciled.
3. Review counted and uncounted lines before submission.
4. For a partial count, keep out-of-scope and uncounted products explicit; they are not silently converted to zero.
5. Open Count Entry or Count Reconciliation according to state and capability.

### UI-INV-009 — Count Entry

1. Confirm count number, store, and assigned scope.
2. Enter the physical quantity for each counted line only.
3. Resolve duplicate or validation feedback before submitting.
4. Keep counted and uncounted status visible for the reviewer.
5. Submit the session; entry itself does not post an inventory movement.

Hardware scanners and offline acceptance are outside this Local/Demo slice.

### UI-INV-010 — Count Reconciliation

1. Compare snapshot/reference time, expected quantity, counted quantity, and variance.
2. Review subsequent movements included by the movement-window calculation.
3. Confirm that uncounted products are preserved, especially in a partial count.
4. Reconcile only with `stock_counts.reconcile` capability and after separation-of-duties review.
5. Verify the resulting adjustment movement in the append-only ledger.

Production tolerances, Owner approval, and UAT sign-off are not implied by this screen.

### UI-INV-011 — Entries, Exits, and Adjustments

1. Identify whether the record is an entry, exit, or adjustment.
2. Review store, lines, quantity delta, before/after values, and the business reason.
3. Follow draft → submitted → approved; submit alone does not post.
4. Ensure negative stock protection and visible-store scope are respected.
5. After approval, verify `inventory_entry` or `inventory_exit` in the movement ledger and audit trail.

## 4. Common failure handling

- **Access Denied:** the current identity lacks the capability or the record is outside visible-store scope. Do not infer that the record does or does not exist.
- **Generic inventory operation error:** the backend reports the exception internally and displays a safe translated message. Review state, scope, validation, and audit instead of relying on exception text.
- **Missing action:** state, capability, store scope, or separation-of-duties may hide it.
- **Repeated submission:** transfer receipt, difference resolution, adjustment approval, and reconciliation are lifecycle-guarded; refresh and inspect the authoritative state.
- **WAC hidden:** cost visibility is separately permissioned and does not block quantity workflows.

## 5. Local/Demo boundary

These pages and guides are suitable for local browser walkthroughs and deterministic Demo fixtures only. They are **not**:

- Production master data or opening-stock cutover evidence.
- UAT approval, Owner approval, financial posting approval, or official receipt evidence.
- Hardware scanner, printer, PDF, warehouse-operation, or release acceptance.
- A replacement for production reconciliation, migration, policy sign-off, or real warehouse controls.

Arabic uses RTL and English uses LTR. The Appearance Customizer remains independent of inventory business state and must remain persistent and functional.
