# 32 — Cash Drawer and Shift Specification

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Detailed implementation specification derived from the approved PRD, Implementation Plan, Architecture, Roles/Permissions, and policies 17–29  
**Authority order:** PRD functional behavior → Implementation Plan sequencing/phase gates → approved decisions/policies → this specification  
**Important:** Exact production master data, legal wording, hardware models, provider choices, and final numeric limits remain configurable where the source documents do not define them.

---


## 1. Purpose

Define drawer assignment, exclusive shift opening, opening float, cash movements, transaction linkage, blind closing, expected totals, actual submission, variance review, approval, immutable closure, and thermal/A4 outputs.

## 2. Traceability

- PRD: CSH-01–CSH-04, POS-02–POS-03, NFR-01–NFR-06.
- Implementation Plan: DM 3.3.
- Tasks: TSK-007, TSK-025.
- Policies: docs/17, 19, 26.

## 3. Core Entities

- Cash Drawer.
- Shift.
- Shift Totals.
- Cash Movement.
- Payment.
- Sale.
- Approval Record.
- Audit Log.
- Document Sequence.

## 4. Drawer Rules

- Drawer belongs to approved branch/store context.
- Drawer code unique.
- Drawer must be active.
- Assignment history preserved.
- No conflicting active cashier assignment.
- No reassignment/deactivation with active shift.

## 5. Shift States

- `draft_opening`
- `open`
- `closing_submitted`
- `variance_review`
- `closed`
- `cancelled`
- `reopened` only through explicit exceptional policy, if approved

Closed shifts are immutable.

## 6. Open Shift

Required:

- Cashier.
- Drawer.
- Branch/store.
- Opening date/time.
- Opening float.
- Currency.
- Request/idempotency key.

Validation:

- Cashier has permission and assignment.
- No active shift for cashier where prohibited.
- No active shift on same drawer.
- Drawer active.
- Amount precision valid.
- Opening float within configured policy.

Opening is transaction-safe and audited.

## 7. Activity Linkage

Every approved POS sale/payment and permitted cash movement during the shift links to:

- Shift.
- Drawer.
- Cashier.
- Branch/store.
- Payment method.
- Source document.

No orphaned shift activity is allowed.

## 8. Cash Movements

Types may include configurable:

- Cash in.
- Cash out.
- Petty disbursement.
- Safe deposit.
- Float adjustment.
- Referenced correction.

Each requires:

- Type.
- Amount.
- Reason.
- Actor.
- Shift/drawer.
- Source/reference if applicable.
- Approval where configured.

## 9. Expected Totals

Derived from immutable linked activity:

- Opening float.
- Cash sales/receipts.
- Electronic payments.
- Refunds.
- Cash in/out.
- Other configured payment-method totals.

Expected values are not editable.

## 10. Blind Closing

Before submission, Cashier:

- Enters actual cash.
- Enters actual electronic amounts by configured method.
- Does not receive expected totals through UI, HTML, JSON, hidden fields, or preloaded response.
- May enter notes and evidence if required.

The server must not leak expected values before submission.

## 11. Closing Submission

On submit:

1. Validate shift is open.
2. Validate actual totals completeness.
3. Store immutable submitted actuals/version.
4. Calculate expected values server-side.
5. Calculate variance by method and total.
6. Transition to `variance_review` or `closed` according to policy.
7. Audit submission.

Duplicate submit is idempotently rejected/returned.

## 12. Variance

At minimum:

- Expected cash.
- Actual cash.
- Cash shortage/overage.
- Expected electronic by method.
- Actual electronic by method.
- Method variance.
- Total variance.

Sign convention must be consistent and documented.

## 13. Manager Review

Authorized manager/reviewer can:

- View expected vs actual after submission.
- Review source transactions/movements.
- Request recount if configured.
- Add reason/comment.
- Approve settlement.
- Reject/return for controlled recount where policy allows.

Cashier cannot approve own variance unless explicit exceptional permission exists.

## 14. Closure

Final close:

- Locks shift totals.
- Allocates closing document number.
- Records approval.
- Preserves source links.
- Prevents new sale/payment/cash movement linkage.
- Creates printable thermal close and A4 report.

Corrections use referenced events; no direct edit.

## 15. Reports

Thermal close:

- Shift/drawer/cashier.
- Opening/closing times.
- Actual totals.
- Variance summary according to viewer permission.
- Approval status.

A4 close:

- Payment-method detail.
- Cash movements.
- Sales/refunds.
- Expected vs actual.
- Variance and approval.
- Source references.

Cashier print may redact expected detail until authorized stage.

## 16. Concurrency

Lock/check:

- Drawer active-shift uniqueness.
- Cashier active-shift uniqueness if required.
- Closing version.
- New transaction posting against closing shift.
- Duplicate submission/approval.

A transaction racing with close must either complete before the close snapshot or be rejected/retried under a clear rule.

## 17. UI Screens

- Drawer master/assignment.
- Shift open.
- Active shift summary.
- Cash movement entry/history.
- Blind close entry.
- Submitted close confirmation.
- Manager variance review.
- Closed shift detail.
- Thermal/A4 print.

## 18. Manual Browser Scenarios

Verify:

- Drawer collision.
- Cashier collision.
- Open float validation.
- Sale/payment/cash movement linkage.
- Blind expected-value non-exposure including network response.
- Missing/duplicate actual submission.
- Variance calculation.
- Manager-only expected detail.
- Recount/review/approval.
- Post-close transaction denial.
- Immutable close/correction.
- Prints.
- RTL/LTR, desktop/mobile, console/network.

No automated tests are created or executed.
