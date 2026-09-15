# 17 — Approval and Reason Policy

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Approved local implementation baseline; production-sensitive limits remain configurable  
**Authority:** PRD, `04-roles-permissions.md`, `12-acceptance-criteria.md`, `13-definition-of-done.md`, and owner direction  
**Applies to:** TSK-009 and every later task that submits, approves, rejects, cancels, reverses, overrides, or reconciles a controlled record

---

## 1. Purpose

This policy defines the reusable approval model for TOY & JOY. It exists to prevent each module from inventing its own workflow and to preserve clear separation between preparation, approval, correction, and audit.

The policy does not create business modules. It defines conventions that future modules must apply when their own records and screens are implemented.

---

## 2. Core Principles

1. Server-side authorization is mandatory for every transition.
2. UI visibility mirrors authorization but never replaces it.
3. Approved records are immutable.
4. The original approved record is preserved.
5. Corrections use referenced cancellation, reversal, return, or adjustment records.
6. Approval actions are atomic and idempotent.
7. A stale record cannot be approved without reloading the latest version.
8. Approval and override actions require a reason where this policy or the module policy says so.
9. Sensitive actions record actor, branch, store, session/device when available, source, reason, and before/after context.
10. No `P` or `R` permission in the canonical role matrix becomes a production grant unless explicitly granted.

---

## 3. Canonical States

The reusable state set is:

| State | Meaning | Editable? | Terminal? |
|---|---|---:|---:|
| `draft` | Work in progress and not submitted | Yes, by authorized editor | No |
| `submitted` | Awaiting review or approval | No, except controlled withdrawal | No |
| `approved` | Accepted and effective | No | Usually |
| `rejected` | Rejected with a required reason | No; may be copied to a new draft | Yes |
| `cancelled` | Stopped before effect or according to module rules | No | Yes |
| `reversed` | Approved effect neutralized by a referenced correction record | No | Yes |
| `expired` | No longer valid because of time or configured validity | No | Yes |
| `superseded` | Replaced by a newer approved version | No | Yes |

A module may add states required by the PRD, such as `partially_received`, `in_transit`, or `difference_review`, but it must preserve the same transition and audit conventions.

---

## 4. Standard Transitions

| From | Action | To | Required controls |
|---|---|---|---|
| `draft` | Submit | `submitted` | Validate record, actor permission, scope, version |
| `submitted` | Approve | `approved` | Approve permission, separation check, stale check, transaction lock |
| `submitted` | Reject | `rejected` | Reject permission and mandatory rejection reason |
| `submitted` | Withdraw | `draft` | Submitter or authorized manager; mandatory reason after review activity |
| `draft` / `submitted` | Cancel | `cancelled` | Cancel permission and reason |
| `approved` | Reverse | `reversed` | Reverse permission, reason, referenced correction document |
| `approved` | Supersede | `superseded` | New approved version references prior version |
| eligible state | Override | state-specific | Explicit override permission, reason, and additional audit context |

Invalid transitions must return a clear denied or validation response and must not partially write data.

---

## 5. Separation of Duties

### 5.1 Default Rule

The user who submits a record must not approve the same record when the action is financially, operationally, or security sensitive.

### 5.2 Mandatory Separation

Separation is mandatory for:

- Price approval and branch price exceptions.
- Stock adjustments and negative-stock overrides.
- Stock-count reconciliation.
- Transfer difference disposition.
- Purchase invoice approval where stock or cost changes.
- Return/refund exceptions.
- Shift variance settlement.
- Wallet or loyalty manual adjustments.
- Party final settlement exceptions.
- Rental asset damage, loss, depreciation, or retirement approval.
- Permission or scope grant changes affecting the acting user.

### 5.3 Allowed Same-User Approval

Same-user approval is allowed only when all are true:

- The module explicitly permits it.
- The actor has the specific self-approval permission.
- The action is within configured limits.
- A reason is recorded.
- The event is audited as self-approved.

No role receives self-approval implicitly.

---

## 6. Approval Limits

Limits are configuration, not hard-coded policy.

Supported limit dimensions:

- Amount.
- Quantity.
- Discount percentage or value.
- Stock variance.
- Negative stock quantity/value.
- Refund value.
- Open-price deviation.
- Wallet or loyalty adjustment.
- Asset damage or loss value.
- Cross-branch or cross-store scope.

If no limit is configured, the sensitive action is denied unless a higher-level explicit permission allows it.

---

## 7. Canonical Reason Categories

Reason values are managed as controlled data and may be module-specific.

### 7.1 Rejection Reasons

- Missing information.
- Invalid supporting evidence.
- Incorrect amount or quantity.
- Wrong branch/store/scope.
- Duplicate request.
- Policy violation.
- Insufficient authority.
- Stale or superseded request.
- Other — requires free-text explanation.

### 7.2 Cancellation Reasons

- Entered by mistake.
- Customer/supplier request.
- Duplicate document.
- Operational change.
- Expired or no longer needed.
- Source document cancelled.
- Other — requires free-text explanation.

### 7.3 Override Reasons

- Manager-authorized exception.
- Emergency operational continuity.
- Approved stock exception.
- Approved pricing exception.
- Approved refund exception.
- Approved scope reassignment.
- Data correction with reference.
- Other — requires free-text explanation and elevated review.

### 7.4 Reversal/Correction Reasons

- Incorrect source data.
- Duplicate approved transaction.
- Wrong product, quantity, value, branch, or store.
- Payment correction.
- Stock correction.
- Customer or supplier correction.
- System recovery from verified failure.
- Other — requires free-text explanation.

---

## 8. Required Approval Record

Each approval event must capture:

- Approval record UUID.
- Approvable type and ID.
- Action.
- Previous state and resulting state.
- Requester/submitting user.
- Acting reviewer/approver.
- Branch and store context where applicable.
- Role/permission used.
- Reason code and explanation.
- Limit used and measured value where applicable.
- Record version or hash.
- Request/correlation ID.
- Session/device information when available.
- Timestamp.
- Source IP only where lawful and operationally approved.
- Related audit-log reference.
- Referenced correction/reversal document when applicable.

---

## 9. Concurrency and Idempotency

1. Approval actions must run inside a database transaction.
2. The target record must be locked or guarded by optimistic version checking.
3. Repeating the same action with the same idempotency key must not duplicate effects.
4. Number allocation occurs inside the same protected transaction when approval makes a document effective.
5. A stale action returns a conflict message and no write.
6. External side effects, if later added, must be queued only after the database transaction commits.

---

## 10. UI Requirements

Approval-capable screens must show:

- Current state.
- Submitter and submitted time.
- Eligible next actions.
- Required reason field when applicable.
- Version/stale warning.
- Scope context.
- Approval timeline.
- Denial explanation that does not expose sensitive policy internals.
- Confirmation dialog for irreversible actions.

Approval buttons must not appear for unauthorized users, but server-side enforcement remains mandatory.

---

## 11. Module Integration Rule

Every functional task must document:

- Its states.
- Allowed transitions.
- Required permissions.
- Whether separation is mandatory.
- Limits used.
- Reasons used.
- Side effects.
- Correction/reversal behavior.
- Manual browser scenarios.

If a module has no approved module-specific rule, this policy governs the reusable mechanics, while the task must not invent the missing business threshold or eligibility rule.

---

## 12. Manual Browser Verification

For each implemented approval flow verify:

1. Authorized transition succeeds.
2. Unauthorized transition is denied.
3. Direct URL or forged action is denied.
4. Submitter cannot approve when separation is required.
5. Required reason is enforced.
6. Stale approval is rejected.
7. Double-click/retry does not duplicate effects.
8. Approved record cannot be edited or physically deleted.
9. Referenced correction preserves the original.
10. RTL/LTR, desktop/mobile, console, and network behavior are clean.

No automated tests are created or executed under the current project directive.
