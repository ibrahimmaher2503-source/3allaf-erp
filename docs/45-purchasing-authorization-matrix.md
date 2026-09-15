# 45 — Purchasing Authorization and Approval Matrix

**Product:** TOY & JOY
**Phase:** Phase 1
**Status:** Owner-approved local policy baseline extending `docs/04-roles-permissions.md` — DEC-050 (2026-08-06)
**Authority:** DEC-038 (canonical matrix), NFR-03
**Boundary:** DEC-050 adopts this extension for local/dev enforcement; real production role assignments, branch/store scopes, and UAT grant/deny evidence remain required.

---

## 1. Purpose

`docs/04-roles-permissions.md` is the canonical matrix and remains authoritative. It grants the Purchase Invoice row at module level, but marks Approve, Reverse, Cancel, and cost/receipt override as `R` — requires owner approval. This document expands those `R` cells into the specific capabilities TSK-015 needs, so the owner approves capabilities rather than a table row.

**This document does not grant anything.** Nothing here becomes a permission until it is recorded in `.ai/DECISIONS.md`.

---

## 2. Capability List

Each capability needs: role, branch scope, store scope, approval limit, separation-of-duties requirement, reason requirement, audit requirement.

| # | Capability | Proposed role(s) | SoD | Reason | Audit | Decision key |
|---|---|---|---|---|---|---|
| 1 | Create purchase invoice | Purchasing Officer | — | No | Yes | OI-PERM-01 |
| 2 | Import purchase invoice | Purchasing Officer | — | No | Yes | OI-PERM-02 |
| 3 | Edit Draft | Creator; Purchasing Officer | — | No | Yes | OI-PERM-03 |
| 4 | Submit | Purchasing Officer | — | No | Yes | OI-PERM-04 |
| 5 | Approve | Warehouse Manager or Branch Manager | **Yes** | No | Yes | OI-PERM-05 |
| 6 | Receive stock | Warehouse Manager | Yes | No | Yes | OI-PERM-06 |
| 7 | Reverse / correct | Branch Manager + Administrator | **Yes** | **Yes** | Yes | OI-PERM-07 |
| 8 | View unit cost | Purchasing, Warehouse Manager, Reviewer | — | No | Read-audit optional | OI-PERM-08 |
| 9 | View total cost | As above | — | No | Optional | OI-PERM-09 |
| 10 | Export cost data | Reviewer, Administrator | — | **Yes** | Yes | OI-PERM-10 |
| 11 | Edit tax on an invoice | Restricted; Administrator only | — | Yes | Yes | OI-PERM-11 |
| 12 | Edit discount | Purchasing within limit; above limit requires approval | — | Above limit | Yes | OI-PERM-12 |
| 13 | Exceed PO quantity | Approval permission only | **Yes** | **Yes** | Yes | OI-PERM-13 |
| 14 | Enter invoice without PO | Distinct permission | — | **Yes** | Yes | OI-PERM-14 |

Capability 5 conflicts with the existing note in `docs/04` that Purchasing Officer's approve rights are `R` and that Warehouse "Receiving" is a Warehouse Manager capability. The owner must confirm whether purchasing approval and stock receiving are the same act or two acts by two people — this follows directly from OI-RCV-01.

---

## 3. Approval Limits

Amount limits must be defined per capability, not per role, so that a limit change does not require a role redesign.

| Limit | Decision key |
|---|---|
| Invoice value above which a second approver is required | OI-LIMIT-01 |
| Cost-variance percentage requiring approval | OI-LIMIT-02 |
| Discount value/percentage above which approval is required | OI-LIMIT-03 |
| Over-receipt tolerance requiring approval | OI-LIMIT-04 |
| Currency and evaluation basis for all limits | OI-LIMIT-05 |

---

## 4. Separation of Duties

Proposed rules, pending OI-PERM-05/07/13:

1. The creator of an invoice may never approve it, even with Administrator rights. The existing `ApproveRequest` action already denies self-approval and this must not be relaxed.
2. The person who receives stock should not be the person who enters the supplier's cost, where staffing allows.
3. Reversal requires an approver who is not the original approver.
4. Where the client is small enough that SoD is impossible, the owner must sign an explicit exception in `.ai/DECISIONS.md` rather than the rule being quietly dropped in code.

---

## 5. Enforcement

All of the above is enforced through the existing evaluation order in `docs/04` §Permission Evaluation Order. UI visibility mirrors authorization and never replaces it. Every override writes an audit event carrying actor, reason, before/after values, and request ID in the same transaction as the action.

---

## 6. Manual Browser Verification

Verify each capability against each role at both grant and deny; branch and store scope isolation; approval limit boundary just below and just above; self-approval denial; reversal by original approver denied; cost fields hidden from Cashier; export permission enforced separately from view; every override produces exactly one audit event with a reason.

No automated tests are created or executed.
