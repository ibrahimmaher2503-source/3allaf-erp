# 43 — Receiving, Matching, and Purchase Document Numbering Policy

**Product:** TOY & JOY
**Phase:** Phase 1
**Status:** Owner-approved local policy baseline — DEC-050 (2026-08-06)
**Authority:** PUR-01–PUR-06, AC-PUR-05
**Blockers:** BLK-008 (numbering, print templates), BLK-010 (receipt semantics)
**Depends on:** TSK-014 approved local Purchase Order slice

---

## 1. Purpose

Defines whether receiving is implicit in invoice approval or an explicit separate document, and how invoice, PO, and receipt quantities and costs are matched.

This is the highest-risk decision in TSK-015. It changes the table structure, the state machine, and the permission matrix. It cannot be deferred until after coding starts.

---

## 2. The Core Decision — OI-RCV-01

`docs/02-prd.md` §177 states that **approval of a purchase invoice increases inventory**. `docs/35` §5 titles the state machine "Purchase Invoice/Receipt" and states approval posts stock and cost atomically.

Two readings are possible:

| Model | Behaviour | Consequence |
|---|---|---|
| **A — Invoice-posts-stock** (proposed, matches PRD §177) | Approving the invoice posts stock and WAC. No separate receipt document. | Simpler. Partial receipt requires multiple invoices. |
| **B — Separate receipt document** | Invoice is financial; a distinct receipt document posts stock. | Supports partial receipt and invoice/goods mismatch, but is not what PRD §177 says. |

If the owner chooses B, this is a **PRD conflict** and must be recorded in `.ai/DECISIONS.md` before implementation, per the authority order in `AI_INDEX.md`. It must not be resolved silently in code.

---

## 3. Matching Rules

| Question | Proposed default | Decision key |
|---|---|---|
| Partial receipt allowed? | Only under Model B | OI-RCV-02 |
| Invoice without a PO allowed? | Yes, with a distinct permission and reason | OI-RCV-03 |
| Over-receipt allowed? | No by default | OI-RCV-04 |
| Over-receipt tolerance if allowed | DEC-050 approved baseline — percentage or absolute, per product or per line | OI-RCV-05 |
| Who approves over-receipt | Approval permission, not a role default | OI-RCV-06 |
| Invoice quantity ≠ PO quantity | Block at submission; require approval to proceed | OI-RCV-07 |
| Invoice cost ≠ PO cost | Warn always; approval required above a tolerance | OI-RCV-08 |
| Cost variance approval threshold | DEC-050 approved baseline | OI-RCV-08 |
| One invoice → many POs | Proposed: yes, lines carry their own PO line reference | OI-RCV-09 |
| One PO → many invoices | Proposed: yes, required for partial supply | OI-RCV-10 |

Where many-to-many linkage is approved, the link belongs on the **line**, not the header. A header-only link cannot express a mixed invoice and will force incorrect PO closure.

---

## 4. PO State Effects

Once receiving is implemented, the PO state machine in `docs/35` gains transitions that TSK-014 deliberately left unimplemented:

- `Approved` → `Partially Received` when received quantity is greater than zero and less than ordered.
- `Partially Received` → `Received` when every line reaches its ordered quantity, or the PO is closed short with a reason.
- Closure short of ordered quantity requires permission, reason, and audit.

These transitions must be driven by posted stock movements, never by a manually set status field.

---

## 5. Numbering

Numbering uses the existing `document_sequences` table and `AllocatePurchaseOrderNumberAction` pattern. Formats are pending BLK-008 / OI-NUM-01:

| Document | Proposed format | Decision key |
|---|---|---|
| Purchase Order | `PO-{YYYY}-{00000}` | OI-NUM-01 |
| Purchase Invoice | `PINV-{YYYY}-{00000}` | OI-NUM-01 |
| Goods Receipt (Model B only) | `GRN-{YYYY}-{00000}` | OI-NUM-01 |
| Supplier Return | `PRET-{YYYY}-{00000}` | OI-NUM-01 |

Rules: numbers are allocated inside the approval transaction, never at draft creation; gaps are permitted and must never be back-filled; per-branch versus company-wide sequencing is **DEC-050 approved baseline — OI-NUM-02**.

---

## 6. Print Output

Per `docs/38-print-export-specification.md`. Pending values:

| Item | Decision key |
|---|---|
| A4 layout and required fields | OI-PRT-01 |
| Number of copies | OI-PRT-02 |
| Target printer | OI-PRT-03 |
| Arabic/English label text and print direction | OI-PRT-04 |
| Whether cost appears on the warehouse copy | OI-PRT-05 |

OI-PRT-05 matters operationally: a receiving copy that shows unit cost exposes purchase pricing to warehouse staff. The default proposal is a cost-free receiving copy and a separate cost-bearing finance copy.

---

## 7. Non-Negotiable Invariants

1. Stock posting, cost posting, PO state change, and audit occur in one transaction.
2. Approved receiving documents are immutable; corrections are referenced documents (`docs/19`).
3. Receiving is scoped to stores the user is assigned to; scope is enforced server-side.
4. Self-approval is denied where separation of duties applies (`docs/17`).
5. Every override — over-receipt, no-PO invoice, cost variance — stores an actor, a reason, and an audit event.

---

## 8. Manual Browser Verification

Verify: approval posts stock to the correct store only; PO moves to Partially Received then Received; over-receipt blocked and then permitted only with approval; cost variance path; invoice without PO path; one invoice across two POs; number allocated once and not on draft; A4 print in Arabic and English; unauthorized store denied; retried approval does not double-post.

No automated tests are created or executed.
