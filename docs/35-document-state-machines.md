# 35 — Document State Machines

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Detailed implementation specification derived from the approved PRD, Implementation Plan, Architecture, Roles/Permissions, and policies 17–29  
**Authority order:** PRD functional behavior → Implementation Plan sequencing/phase gates → approved decisions/policies → this specification  
**Important:** Exact production master data, legal wording, hardware models, provider choices, and final numeric limits remain configurable where the source documents do not define them.

---


## 1. Purpose

Provide explicit state/transition maps for all Phase 1 controlled documents. This document supplements the generic approval policy; module rules remain authoritative where more specific.

## 2. Global Rules

- State changes occur through named actions.
- Server authorization and scope required.
- Invalid/skipped/backward transitions fail.
- Submitted/approved/final/closed records lock relevant fields.
- Approved/final history is immutable.
- Correction uses reference document/event.
- Reason required for reject/cancel/reverse/override where specified.
- Transitions are transaction-safe and idempotent.
- Stale versions are rejected.

## 3. Common Draft Approval Machine

`draft → submitted → approved`

Alternatives:

- `submitted → rejected`
- `submitted → draft` via controlled withdrawal
- `draft/submitted → cancelled`
- `approved → reversed/superseded` by reference

## 4. Purchase Order

States:

- Draft.
- Submitted.
- Approved.
- Partially Received.
- Received.
- Cancelled.
- Closed.

Transitions:

- Draft → Submitted.
- Submitted → Approved through a named, authorized approval action; requester self-approval is denied and this local PO approval has no stock, cost, invoice, or receipt effect.
- Approved → Partially Received/Received through downstream receipts; these transitions remain TSK-015 and must be driven by posted receiving effects, not a manually assigned status.
- Draft/Submitted → Cancelled in the approved local TSK-014 slice with a required reason and audit event.
- Approved → Closed in the approved local TSK-014 slice as an administrative close with no stock, cost, invoice, or receipt effect.
- Partially Received → Received through downstream receipts.
- Received → Closed through the downstream receiving/closure policy.
- No stock effect from the PO itself.

**Local boundary note (DEC-044):** TSK-014 implements the Draft, Submitted, Approved, Cancelled, and local Approved → Closed slice only. `Partially Received` and `Received`, receipt links, short closure, and all stock/cost effects remain downstream TSK-015 behavior and are not implemented by the PO screen.

## 5. Purchase Invoice/Receipt

- Draft.
- Submitted.
- Approved.
- Rejected.
- Reversed.

Approval posts stock/cost atomically. Approved record immutable.

## 6. Supplier Return

- Draft.
- Submitted.
- Approved.
- Rejected.
- Cancelled before posting.
- Reversed after posting.

Approval creates an ApprovalRecord in Pending state at submit; only a separate authorized approver transitions it to Approved and posts stock. Approval reduces eligible stock and references an approved purchase-invoice line. Cost is copied from that source line and revalidated at approval; no WAC/fallback/no-reference path exists. Reason rows and approved effective financial-setting versions remain Owner-configurable inputs.

## 7. Price Version

- Draft.
- Submitted.
- Approved.
- Rejected.
- Superseded.
- Cancelled.

Approval activates one location price and supersedes prior active version.

## 8. Label Queue/Job

- Pending.
- In Progress.
- Partially Printed.
- Completed.
- Failed.
- Cancelled.

Reprint creates print event, not silent reset.

## 9. Stock Transfer

- Draft.
- Submitted.
- Approved.
- Dispatched/In Transit.
- Partially Received.
- Received.
- Difference Review.
- Cancelled.

Difference review required for shortage/damage/refusal. Source, transit, destination effects must reconcile.

## 10. Inventory Adjustment

- Draft.
- Submitted.
- Approved.
- Rejected.
- Reversed.
- Cancelled.

Approval posts movement. Negative override separately recorded.

## 11. Stock Count

- Planned.
- Open.
- Counting.
- Submitted.
- Recount Required.
- Reconciliation Review.
- Approved.
- Cancelled.

Stock Counter cannot approve reconciliation. Uncounted items enter review, never auto-zero.

## 12. Sale

- New.
- Suspended.
- Payment Pending.
- Approved.
- Cancelled.
- Offline Provisional.
- Sync Conflict.
- Rejected.
- Reversed only via referenced correction.

## 13. Retail Return/Exchange

- Draft.
- Inspection.
- Submitted.
- Approved.
- Rejected.
- Completed.
- Reversed.

Approval posts settlement and stock disposition.

## 14. Gift Card

Summary status:

- Active.
- Partially Used.
- Fully Used.
- Expired.
- Voided.

Ledger events are append-only: issue/redeem/void/expiry/correction.

## 15. Shift

- Draft Opening.
- Open.
- Closing Submitted.
- Variance Review.
- Closed.
- Cancelled.

Expected values become visible to managers only after submission.

## 16. Booking

- Draft.
- Tentative.
- Confirmed.
- Rescheduled.
- Cancelled.
- In Operation.
- Completed Pending Settlement.
- Closed.

## 17. Party Working/Final Invoice

- Draft.
- Active Working.
- Frozen for Operation.
- Finalizing.
- Final.
- Cancelled.
- Corrected by reference.

## 18. Party Operating Order

- Draft.
- Released.
- In Progress.
- Completed.
- Cancelled.

## 19. Asset Reservation/Checkout/Return

Reservation:

- Draft.
- Reserved.
- Cancelled.
- Fulfilled.

Asset operational status:

- Available.
- Reserved.
- Checked Out.
- Under Inspection.
- Damaged.
- Under Maintenance.
- Retired.
- Lost.

## 20. Quotation

- Draft.
- Issued.
- Accepted.
- Rejected.
- Expired.
- Cancelled.
- Closed.

No stock/reservation/payment/wallet effect.

## 21. Approval Request

- Pending.
- Approved.
- Rejected.
- Withdrawn.
- Cancelled.
- Expired.

## 22. Attachment

- Temporary.
- Active.
- Quarantined.
- Redacted.
- Expired.
- Deleted.

Approved-source evidence cannot be independently deleted through ordinary UI.

## 23. Import Batch

- Uploaded.
- Mapping.
- Validating.
- Review.
- Submitted.
- Approved.
- Partially Applied only if explicitly supported; default atomic valid-row behavior per task.
- Rejected.
- Cancelled.
- Failed.

Invalid rows never write.

## 24. Offline Transaction

- Local Draft.
- Queued.
- Syncing.
- Accepted.
- Rejected.
- Conflict.
- Corrected/Resolved.

## 25. Transition Table Template

Every module implementation must record:

- From.
- Action.
- To.
- Permission.
- Separation.
- Required fields.
- Reason.
- Locks/version.
- Side effects.
- Audit event.
- Print/export effect.
- Reversal path.

## 26. Manual Browser Verification

For every implemented machine verify all allowed transitions, denied transitions, stale/double actions, role separation, locked fields, terminal immutability, and referenced correction.
