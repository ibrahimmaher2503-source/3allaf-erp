# 29 — Rental Asset, Damage, Loss, and Depreciation Policy

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Derived implementation policy based on the approved PRD  
**Authority:** AST-01–AST-05  
**Owner-configurable values:** Categories, locations, reservation buffers, checklists, assessment methods, and approval limits  
**Production decision pending:** Final asset register and finance/operations rules

---

## 1. Purpose

This policy defines local implementation conventions for rental asset identity, reservation, availability, checkout, return, condition, damage, loss, maintenance, and depreciation.

---

## 2. Requirement Classification

| Item | Classification |
|---|---|
| Rental assets separate from consumables | PRD Requirement |
| Unique asset identity | PRD Requirement |
| Prevent overlapping reservations | PRD Requirement |
| Checkout and return records | PRD Requirement |
| Required asset states | PRD Requirement |
| Before/after condition | PRD Requirement |
| Damage/depreciation history | PRD Requirement |
| Reservation buffer duration | Owner-Configurable Value |
| Condition checklist | Owner-Configurable Value |
| Depreciation method | Owner-Configurable Value |

---

## 3. Asset Identity

Each asset includes:

- Unique code.
- Name.
- Category.
- Availability.
- Current location.
- Condition.
- Status.
- Relevant cost data.
- History.

Historical assets are not physically deleted.

---

## 4. States

Supported states:

- `available`
- `reserved`
- `checked_out`
- `under_inspection`
- `damaged`
- `under_maintenance`
- `retired`
- `lost`

Transitions require permission and audit.

---

## 5. Reservation

1. Reservation is tied to party date/time.
2. Overlapping allocation is blocked.
3. Timezone is explicit.
4. Buffer before/after may be configured.
5. Concurrent reservation attempts must be handled safely.
6. Cancellation/reschedule preserves history.

---

## 6. Checkout and Return

Checkout records:

- Source party.
- Asset.
- Responsible user.
- Date/time.
- Location.
- Pre-condition.
- Evidence where required.

Return records:

- Date/time.
- Returned location.
- Post-condition.
- Missing/damaged status.
- Evidence.
- Inspector.

---

## 7. Damage, Loss, and Maintenance

Events may capture:

- Asset.
- Party/source.
- Assessment.
- Responsible user.
- Cost impact where entered.
- Approval.
- Final status.
- Evidence.

Exact financial impact and approval thresholds remain configurable.

---

## 8. Depreciation

1. Depreciation is operational history only in Phase 1.
2. It does not imply a general ledger.
3. Method and amount are configurable.
4. Approved depreciation records are immutable.
5. Corrections use referenced events.

---

## 9. Manual Browser Verification

Verify:

1. Unique asset code.
2. Consumable separation.
3. Overlapping reservation denial.
4. Concurrent reservation behavior.
5. Checkout and return.
6. Before/after condition.
7. Damage/maintenance/lost/retired transitions.
8. Cost-field permission/redaction.
9. Immutable history and correction references.
10. RTL/LTR, responsive, print, console, and network.

No automated tests are created or executed.
