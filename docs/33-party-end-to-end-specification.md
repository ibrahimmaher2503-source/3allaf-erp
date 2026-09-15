# 33 — Party End-to-End Specification

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Detailed implementation specification derived from the approved PRD, Implementation Plan, Architecture, Roles/Permissions, and policies 17–29  
**Authority order:** PRD functional behavior → Implementation Plan sequencing/phase gates → approved decisions/policies → this specification  
**Important:** Exact production master data, legal wording, hardware models, provider choices, and final numeric limits remain configurable where the source documents do not define them.

---


## 1. Purpose

Define the full party lifecycle: booking, calendar, customer/child data, working invoice, multiple payments on account, operating order, services, consumables, asset reservation/checkout/return/inspection, changes, final readiness, Party Wallet settlement, final invoice, and receipts.

## 2. Traceability

- PRD: PTY-01–PTY-06, AST-01–AST-05, CUS-01–CUS-04, NFR-01–NFR-06.
- Implementation Plan: DM 5.1–DM 5.5.
- Tasks: TSK-031–TSK-036.
- Policies: docs/27–29.

## 3. Separation

- Party workflow is separate from retail POS.
- Party stores are separate operational contexts.
- Retail products cannot be mixed into party documents.
- Product Wallet is never used in party settlement.
- Party Wallet is hidden from retail Cashier.
- Shared customer identity and loyalty do not merge financial balances.

## 4. Party Lifecycle

`Booking & Working Invoice → Payments on Account → Operating Order → Issue Consumables / Check Out Assets → Return & Inspect → Final Invoice / Settlement`

## 5. Booking States

- `draft`
- `tentative`
- `confirmed`
- `rescheduled`
- `cancelled`
- `in_operation`
- `completed_pending_settlement`
- `closed`

Exact commercial hold/expiry rules remain configurable.

## 6. Booking Data

Required/conditional fields:

- Booking UUID/number.
- Customer.
- Child where relevant.
- Party date.
- Start/end time.
- Timezone.
- Location/address.
- Primary contact.
- Secondary contact.
- Notes.
- Planned services.
- Planned consumables.
- Planned rental assets.
- Assigned responsibilities.
- Party store/context.
- Status.
- Cancellation/reschedule reason.
- Audit/version.

## 7. Calendar and Conflicts

Calendar views:

- Day/week/month.
- Branch/party store.
- Status.
- Service/resource.
- Asset conflicts.

Conflict checks:

- Asset overlap.
- Resource/responsibility conflict where configured.
- Location conflict where configured.
- Buffer times where configured.
- Stale concurrent booking.

Conflicts block confirmation unless explicit override exists.

## 8. Working Invoice

States:

- `draft`
- `active_working`
- `frozen_for_operation`
- `finalizing`
- `final`

Line types:

- Service.
- Consumable.
- Rental asset charge.
- Other approved party-only charge.
- Discount/tax only if party policy permits.

Rules:

- Editable before final close.
- Changes preserve version/audit.
- Controlled additions/removals.
- No retail line.
- Prices snapshot per version.
- Final invoice immutable.

## 9. Payments on Account

Each payment records:

- Party invoice.
- Amount.
- Method.
- Reference.
- Evidence.
- Actor.
- Date/time.
- Branch/store.
- Receipt number.
- Status.

Each produces separate receipt labeled exactly:

`Payment on Account for Party Invoice No. [number]`

Duplicate posting is blocked. Overpayment behavior remains configurable.

## 10. Party Balance

Derived:

- Working/final invoice total.
- Payments on account.
- Party Wallet settlement.
- Other approved settlement.
- Remaining amount or credit.

Product Wallet is excluded.

## 11. Operating Order

States:

- `draft`
- `released`
- `in_progress`
- `completed`
- `cancelled`

Contains:

- Booking reference.
- Services.
- Responsibilities.
- Planned consumables.
- Planned assets.
- Actual additions/removals.
- Operational notes/checklist.
- Source version.

Release validates booking confirmation and resource readiness.

## 12. Consumables

For each item:

- Product.
- Party store.
- Planned quantity.
- Issued quantity.
- Actual consumed quantity.
- Returnable unused quantity where permitted.
- Unit.
- Responsible user.
- Issue/return movement references.

Rules:

- Approved issue posts stock movement.
- Actuals are preserved.
- Unused return requires referenced return movement.
- No direct balance editing.
- Whole/fraction rules follow product policy.

## 13. Rental Assets

Integration includes:

- Reservation against party date/time.
- No overlap.
- Checkout readiness.
- Pre-condition.
- Checkout document.
- Return document.
- Post-condition.
- Inspection.
- Damage/loss/maintenance flow.
- Final status.

Final party close is blocked while required assets remain unresolved.

## 14. Controlled Changes

Before completion:

- Authorized additions/removals.
- Reason based on state.
- Repricing/recalculation.
- Resource and stock revalidation.
- Version/audit.

After final close:

- No direct edits.
- Referenced correction only.

## 15. Final Readiness

Checklist includes:

- Booking valid.
- Working invoice current.
- Operating order completed or approved exception.
- Consumables issued/returned/reconciled.
- Assets returned and inspected or approved unresolved disposition.
- Payments posted.
- Party Wallet authorization.
- Totals current.
- Required approvals complete.
- No stale version.
- No unresolved blocking conflict.

## 16. Final Settlement

Atomic action:

1. Lock booking/invoice/payment/wallet/readiness records.
2. Recalculate final lines/totals.
3. Reconcile payments on account.
4. Apply authorized Party Wallet amount.
5. Determine remaining due or credit.
6. Validate final payment if required.
7. Allocate final invoice/receipt numbers.
8. Freeze final invoice.
9. Write ledger/payment/audit records.
10. Mark booking closed.

Retry must not duplicate finalization.

## 17. Cancellation and Reschedule

Configurable policy governs commercial consequences. Technical rules:

- Reason required.
- Preserve original schedule/version.
- Release reservations safely.
- Reverse/adjust consumable/asset commitments.
- Preserve payments and settlement decisions.
- Audit every action.

## 18. UI Screens

- Party booking list.
- Calendar.
- Booking create/edit/detail.
- Working invoice editor.
- Payment-on-account entry/list/receipt.
- Operating order editor/execution.
- Consumable issue/actual/return.
- Asset reservation/checkout/return/inspection.
- Final readiness.
- Final settlement.
- Final invoice/receipt.
- Party history/timeline.

## 19. Manual Browser Scenarios

Verify:

- Retail/party mixing blocked.
- Booking required fields.
- Calendar conflict/reschedule/cancel.
- Working invoice changes and history.
- Multiple payments and exact receipt wording.
- Duplicate/overpayment behavior.
- Operating order release/execution.
- Consumable issue/actual/return and stock reconciliation.
- Asset overlap/checkout/return/damage.
- Readiness blockers.
- Party Wallet only; Product Wallet denied.
- Final totals/remaining/credit.
- Double-close prevention.
- Immutable final/correction.
- RTL/LTR, responsive, print, console/network.

No automated tests are created or executed.
