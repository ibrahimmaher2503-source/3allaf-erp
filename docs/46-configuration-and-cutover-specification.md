# 46 — Financial Configuration and Cutover Specification

**Product:** TOY & JOY
**Phase:** Phase 1
**Status:** Team-adopted engineering baseline — owner approval outstanding
**Authority:** DEC-043
**Blockers:** BLK-006, BLK-008, BLK-010

---

## 1. Purpose

Defines the owner-facing Settings screens that carry the TSK-015 defaults, how those settings are versioned, and how they lock at cutover.

The problem this solves: the owner is unreachable, delivery cannot stop, and a wrong financial value silently corrupts every posted document. The answer is defaults that are editable **until the first posting** and frozen after it.

---

## 2. Screen Inventory

Three screens. Register them in `.ai/UI_SCREENS.md`.

| Screen | Route (proposed) | Permission | Purpose |
|---|---|---|---|
| Purchasing Settings | `/admin/settings/purchasing` | `settings.purchasing.manage` | Tax, rounding, precision, discount, approval limits |
| Import Settings | `/admin/settings/import` | `settings.import.manage` | File limits, column headers, duplicate key |
| Cutover Setup | `/admin/cutover` | `cutover.manage` | One-pass wizard: branches, stores, users, settings review, opening stock, lock |

Cutover Setup is a wizard, not a settings page. It runs once and closes.

---

## 3. Settings Data Model

Follows the versioned pattern in `docs/36` §8.

### `financial_setting_versions`

`key`, `value`, `value_type`, `effective_from`, `effective_to` (nullable), `created_by`, `approval_record_id` (nullable), `version`, `locked_at` (nullable), `notes`.

Rules:

1. A change **inserts a new version** and closes the prior one. Update-in-place is prohibited.
2. Resolution is by `effective_from <= document approval time`, never by "current row".
3. A document stores the resolved `financial_setting_version_id` set it was approved under, so its arithmetic is reproducible years later.
4. Every change writes one audit event with before/after values in the same transaction.
5. A locked key rejects new versions at the action layer, not only in the UI.

---

## 4. Locked vs Unlocked Keys

| Class | Behaviour |
|---|---|
| **Locking** | Editable until the first approved purchase invoice or opening adjustment exists, then permanently read-only |
| **Open** | Editable at any time; historical documents remain bound to their own version |

Locking keys (per DEC-043 §4): tax enabled, tax inclusive/exclusive, tax-in-cost, discount-reduces-cost, rounding mode, numbering format and scope.

Open keys: limits, thresholds, display decimals, import limits, column headers, print settings.

The distinction is not stylistic. A locking key changes the meaning of already-posted numbers; an open key only changes what is permitted next.

---

## 5. Lock Trigger

```
lock_condition = EXISTS(approved purchase_invoice)
              OR EXISTS(approved opening_stock_adjustment)
```

Evaluated server-side on every settings mutation. On lock, the screen shows the lock timestamp and the document that triggered it, and offers no override — unlocking is an inventory revaluation procedure outside Phase 1.

---

## 6. Cutover Wizard Steps

```
1. Branches         — real data required, BLK-006
2. Stores           — type, owning branch, receives-goods flag
3. Users and scopes — branch/store assignment per user
4. Settings review  — every locking key shown with its consequence in plain Arabic
5. Opening stock    — per store, quantity and cost, or explicit "start at zero"
6. Confirmation     — totals per store; requires an approver who is not the enterer
7. Lock             — sets the cutover timestamp; blocks any backdated movement
```

Step 4 must state consequences, not field names. "Tax is not included in inventory cost — this cannot be changed after the first invoice" is usable by an owner; "tax_in_cost: false" is not.

Step 6 approval follows `docs/17` separation of duties. The existing `ApproveRequest` self-approval denial applies.

---

## 7. Backdating

After the cutover timestamp is set, any document with an effective date earlier than it is rejected at the action layer. There is no permission that permits it. A pre-cutover correction is a referenced adjustment dated after cutover.

---

## 8. Demo Data Interaction

Production seeding does not create settings, policy values, master data, or a Demo marker. Cutover remains blocked until every required operational value is entered from an approved owner source and the readiness checks pass; synthetic or Demo rows must never be migrated into Production.

---

## 9. Manual Browser Verification

Verify: default values present on a fresh install; changing an open key creates a new version and leaves the old document's arithmetic unchanged; a locking key is editable before the first posting and read-only after it; the lock message names the triggering document; a locked key rejected at the action layer when the UI is bypassed; backdated movement rejected; cutover blocked while demo data is present; step 6 self-approval denied; every change audited once; Arabic RTL and mobile layouts clean.

No automated tests are created or executed.
