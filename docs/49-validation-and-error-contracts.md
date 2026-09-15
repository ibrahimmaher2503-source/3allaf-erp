# 49 — Validation and Error Contracts

**Product:** TOY & JOY
**Phase:** Phase 1
**Status:** Derived implementation specification — team-adopted defaults
**Authority:** NFR-03, NFR-04, NFR-05, NFR-06
**Note:** `.ai/CURRENT_TASK.md` records that this document was requested by name during TSK-009 and did not exist. This closes that gap.

---

## 1. Purpose

Defines where validation runs, what an error looks like, and how failures are surfaced consistently across Livewire screens, imports, print paths, and offline synchronisation.

Without one contract, each module invents its own error shape and the Arabic/English message set drifts.

---

## 2. Validation Layers

| Layer | Responsibility | May be skipped? |
|---|---|---|
| Client | Formatting hints, immediate feedback | Yes — never authoritative |
| Form request / Livewire rules | Shape, type, required, range | No |
| Action guard | Authorization, scope, state, concurrency | **Never** (NFR-03) |
| Database constraint | Uniqueness, referential integrity | No |

NFR-03 is explicit that authorization is enforced at service level, not through hidden menus or buttons. The same applies to every business rule in this document: a rule enforced only by a disabled control is not enforced.

---

## 3. Error Categories

| Category | HTTP / UI | User sees | Audited |
|---|---|---|---|
| `validation` | 422 / inline field errors | Field-level message | No |
| `authorization` | 403 / denied state | Generic denial, no data leak | **Yes** |
| `state` | 409 / blocked action | Which state blocks it | Yes |
| `concurrency` | 409 / stale notice | Reload prompt | Yes |
| `not_found` | 404 | Generic | No |
| `integrity` | 500 / error state | Generic; request ID shown | Yes |

An authorization denial must never reveal whether the record exists. "You cannot approve invoice #412" leaks the record; "You are not authorised to perform this action" does not.

---

## 4. Error Shape

Every failure carries: `category`, `code`, `message_key`, `field` where applicable, `request_id`, and `source_reference` where a document is involved.

`message_key` — not a literal string. Text resolves from `lang/ar.json` and `lang/en.json` so both locales stay complete. A hard-coded Arabic or English string in a PHP action is a defect.

`request_id` comes from the existing `SetRequestId` middleware and appears in the user-facing error state, so a support report maps to an audit row.

---

## 5. State and Concurrency

1. Every stateful document validates its transition against `docs/35` before acting. An undefined transition is a `state` error, never a silent no-op.
2. Every mutation on a versioned record passes the version it read. A mismatch is a `concurrency` error and the whole transaction rolls back (the existing `AssertSourceVersionCurrent` guard).
3. NFR-06 requires that concurrent actions never create duplicate approved document numbers. Number allocation happens inside the approval transaction, under a lock, never at draft creation.
4. Approved documents reject mutation at the action layer, not only by hiding the edit button (NFR-02).

---

## 6. Batch and Import Errors

Per-row failures do not abort the batch by default (`docs/42` §6). Each failed row stores its category, code, message key, and original row number, and is downloadable as an error file. The batch itself reports counts by category, not a single pass/fail.

A file-level failure (unsafe file, oversize, unreadable) aborts before any row is staged.

---

## 7. Empty, Loading, Denied, and Error States

The four shared components already exist (`components/state/empty`, `loading`, `denied`, `error`). Every list, form, and modal uses them rather than bespoke markup. NFR-05 requires safe loading on high-volume views — pagination or an equivalent bounded strategy, never an unbounded query behind a spinner.

---

## 8. Manual Browser Verification

Verify: a field error renders inline in both locales; an authorization denial reveals no record existence; a blocked state transition names the state; a stale-version save rolls back fully; two concurrent approvals produce one number and one clean failure; an import error file downloads with row numbers; request ID appears in the error state and matches an audit row; all four shared states render in RTL and on mobile.

No automated tests are created or executed.
