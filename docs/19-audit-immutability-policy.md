# 19 — Audit, Integrity, and Immutability Policy

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Approved implementation baseline  
**Authority:** PRD NFR-01, NFR-02, NFR-06, canonical permissions, and owner direction  
**Applies to:** TSK-009 and all master, transactional, financial, inventory, customer, party, and security-sensitive modules

---

## 1. Purpose

This policy defines trustworthy history for TOY & JOY. It standardizes append-only audit events, approved-record immutability, correction by reference, sensitive-value redaction, and traceability across modules.

---

## 2. Core Integrity Rules

1. Approved documents are immutable.
2. Approved history is never physically deleted.
3. Reversal, cancellation, return, or adjustment references the original source.
4. Ledger entries are append-only.
5. Audit events are append-only.
6. Direct database/UI balance editing is not permitted.
7. Every sensitive mutation records actor, scope, reason when required, source, and before/after context.
8. Concurrent approval or number allocation must not create duplicates.
9. A system failure must not leave partial business effects.
10. Read/export of sensitive audit data is permission-controlled.

---

## 3. Audit Event Categories

| Category | Examples |
|---|---|
| Authentication | Login success/failure, logout, reset, lockout, session revocation |
| Authorization | Role, permission, branch/store scope grant/revoke |
| Master data | Company, branch, store, drawer, user, product, supplier, policy changes |
| Workflow | Submit, approve, reject, cancel, reverse, override |
| Inventory | Receipt, transfer, count edit/reconciliation, adjustment, negative-stock override |
| Pricing | Proposal, version approval, open-price use, label printing |
| POS and cash | Sale approval, payment evidence, refund, shift opening/closing, variance settlement |
| Customer value | Loyalty, Product Wallet, Party Wallet, gift-card movements |
| Party/assets | Booking changes, party order changes, issue/return, asset condition/damage |
| Attachments | Upload, access where sensitive, redaction, quarantine, expiry |
| Reporting | Sensitive export, audit export, cross-scope denied attempt |
| System | Configuration change, backup/restore operation, critical recovery action |

---

## 4. Mandatory Audit Context

Each audit event must capture where applicable:

- Event UUID.
- Event name and category.
- Actor user ID.
- Effective role/permission.
- Branch/store/drawer/shift context.
- Session/device identifier when available.
- Request/correlation ID.
- Source IP and user agent only where operationally approved.
- Auditable entity type and ID.
- Source document type/ID/number.
- Action.
- Previous state and resulting state.
- Reason code and explanation.
- Before values.
- After values.
- Changed field list.
- Idempotency key where used.
- Timestamp.
- Integrity metadata/version.
- Related approval record.
- Related correction/reversal reference.

---

## 5. Before/After Data

1. Capture only fields needed for traceability.
2. Do not duplicate full binary attachments in audit rows.
3. Secrets, passwords, tokens, private keys, reset tokens, and session secrets are never logged.
4. Sensitive values are redacted according to viewer permission.
5. Large payloads may use a structured field-diff with safe truncation.
6. Monetary and quantity values preserve their precision.
7. Localized display labels are not a substitute for stable field keys.
8. File changes reference attachment IDs and hashes.

---

## 6. Append-Only Enforcement

Audit rows and approved ledger entries:

- Cannot be edited through application screens.
- Cannot be logically deleted by ordinary users.
- Cannot be physically deleted by ordinary application actions.
- May receive a separate annotation/correction event without rewriting the original.
- Must be protected by explicit model/service conventions.
- Must be excluded from generic CRUD behavior.

Administrative database maintenance is outside normal application operation and must follow an approved runbook.

---

## 7. Immutability by Record Type

### 7.1 Draft Masters/Documents

May be edited or logically deleted according to permission and dependency rules.

### 7.2 Submitted Records

Editing is blocked unless the workflow explicitly supports withdrawal back to draft.

### 7.3 Approved Transactions

Cannot be edited or physically deleted. Correction requires a referenced record.

### 7.4 Versioned Configuration

A new effective version supersedes the old version. Historical versions remain readable.

### 7.5 Ledgers and Movements

Balances are derived from or reconciled to append-only entries. Corrections create compensating entries.

---

## 8. Correction Patterns

| Error type | Required correction |
|---|---|
| Wrong approved sale/payment | Return, refund, reversal, or referenced correction |
| Wrong stock movement | Reversal/adjustment linked to original |
| Wrong approved purchase | Supplier return or reversal |
| Wrong approved price | New price version; old history preserved |
| Wrong wallet/loyalty movement | Referenced compensating entry |
| Wrong party settlement | Referenced correction/adjustment |
| Wrong asset status/cost event | New assessment/correction event |
| Wrong master data used historically | Correct master for future use; do not rewrite historical source |

---

## 9. Numbering Integrity

1. Draft identifiers may be UUIDs or non-final references.
2. Final document numbers are allocated only at the approved/effective transition unless the module explicitly requires reservation.
3. Allocation is concurrency-safe.
4. A failed transaction does not consume a number unless the configured numbering policy intentionally preserves gaps.
5. Duplicate final numbers are prohibited.
6. Prefix, sequence, branch/store scope, reset period, and formatting are configuration.
7. Number override requires explicit permission and audit.
8. Historical numbers never change.

---

## 10. Redaction and Audit Visibility

Views and exports must apply permission-aware redaction.

Potentially redacted fields:

- Password/security data — always omitted.
- Customer contact and consent data.
- Product cost and margins.
- Wallet and payment details.
- Payment evidence references.
- Internal support/security diagnostics.
- Before/after values outside the viewer’s scope.
- Other branch/store/activity data.

The fact that an event occurred should remain visible when appropriate, even if sensitive values are redacted.

---

## 11. Audit Search and Export

Audit screens must support bounded, paginated filters such as:

- Date range.
- Actor.
- Category/event.
- Entity/source.
- Branch/store.
- Action.
- Result state.
- Reason.
- Correlation/request ID.

Exports require a separate export permission, respect redaction, record who exported, and use bounded limits.

---

## 12. Failure Handling

1. Business mutation and mandatory audit record must commit atomically where practical.
2. If mandatory audit recording fails, the sensitive mutation fails.
3. Error logs must not expose secrets or full sensitive payloads.
4. Rendered failures include a request/correlation ID.
5. Retry must not duplicate business effects.
6. Recovery actions create their own audit events.

---

## 13. Manual Browser Verification

Verify for each sensitive module:

1. Mutation creates the expected audit event.
2. Unauthorized viewer cannot see sensitive values.
3. Audit record cannot be edited/deleted.
4. Approved source cannot be edited/deleted.
5. Referenced correction preserves original history.
6. Duplicate/retry does not duplicate effects.
7. Concurrent approval/numbering does not create duplicate numbers.
8. Filters and exports respect scope and permission.
9. RTL/LTR, responsive layout, console, and network are clean.

No automated tests are created or executed under the current project directive.
