# 44 — Opening Stock and Cutover Specification

**Product:** TOY & JOY
**Phase:** Phase 1
**Status:** Owner-approved local policy baseline — DEC-050 (2026-08-06)
**Authority:** INV-01, INV-03, PUR-05
**Blockers:** BLK-010 (opening-stock cutover), BLK-012 (opening inventory method)

---

## 1. Purpose

Defines the single approved source of opening stock and the cutover moment at which the system becomes the book of record.

Opening stock is entered once and is nearly impossible to correct cleanly afterwards, because every later weighted-average calculation depends on it. This is why it needs one decision, not a flexible set of options.

---

## 2. The Single-Source Decision — OI-OPEN-01

Exactly one path may create opening stock. Multiple paths guarantee double-counting during cutover.

| Option | Advantage | Cost |
|---|---|---|
| A — Purchase invoice | Reuses the receiving path; supplier history is populated | Creates fake supplier invoices for stock whose true origin is historical; distorts supplier purchase reports |
| B — Opening inventory adjustment (**proposed**) | Honest document type; clearly separable in reports; does not pollute supplier history | Requires an explicit opening cost per line |
| C — Import only | Fast for large catalogs | Import is a transport mechanism, not a document type; it still needs a target document |
| D — Independent path | Maximum control | Extra surface area, extra permissions, extra audit work |

Proposed: **Option B**, with the import in `docs/42` used as the data-entry transport into a single opening adjustment document per store.

---

## 3. Cutover Parameters

| Item | Proposed default | Decision key |
|---|---|---|
| Opening date and time | One timestamp for all stores | OI-OPEN-02 |
| Timezone | `Africa/Cairo`, stored UTC, displayed local | OI-OPEN-03 |
| Opening cost source | Last purchase cost, or a stated valuation cost per line | OI-OPEN-04 |
| Does opening stock seed the weighted average? | Yes — it is the first term of the WAC series | OI-OPEN-05 |
| Supplier reference required? | No, under Option B | OI-OPEN-06 |
| Appears in supplier history? | No, under Option B | OI-OPEN-07 |
| Editable after cutover? | No; correction only through a referenced adjustment | OI-OPEN-08 |
| Who approves opening stock? | Named approver, with separation of duties from the enterer | OI-OPEN-09 |

A single opening timestamp matters: any transaction dated before it will produce a stock balance the system cannot explain. Post-cutover backdating must be blocked outright.

---

## 4. Cutover Sequence

```
1. Freeze operations
2. Physical count per store
3. Approve master data (branches, stores, products, suppliers) — see OI-MD-*
4. Enter/import opening quantities and costs per store
5. Review: line count, total quantity, total value per store
6. Approve — this is the cutover moment
7. Verify posted balances against the count sheets
8. Resume operations; block backdating before the opening timestamp
```

Step 5 is a gate, not a formality. Once step 6 executes, correction requires referenced adjustment documents that will appear permanently in the audit trail.

---

## 5. Invariants

1. Opening stock posts through the same `stock_movements` append-only path as every other movement, with a distinct movement type.
2. It is idempotent — a repeated approval cannot double the opening balance.
3. It is approval-gated and fully audited.
4. It never bypasses store scope.
5. Items counted at zero must be recorded explicitly as zero, never omitted (`docs/25` — uncounted items are never auto-zeroed).

---

## 6. Manual Browser Verification

Verify: one store's opening balance and value match the count sheet; opening stock seeds the weighted average correctly for the first later purchase; a second approval attempt is blocked; backdated movement before the opening timestamp is rejected; edit after cutover is denied and only a referenced correction is offered; unauthorized store denied; opening stock is excluded from supplier purchase reports.

No automated tests are created or executed.
