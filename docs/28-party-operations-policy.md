# 28 — Party Operations and Settlement Policy

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Derived implementation policy based on the approved PRD  
**Authority:** PTY-01–PTY-06 and AST-05  
**Owner-configurable values:** Services, packages, cancellation rules, deposits, responsibilities, and final-close checks  
**Production decision pending:** Final party commercial terms, master data, and print formats

---

## 1. Purpose

This policy defines local implementation conventions for party bookings, working invoices, payments on account, operating orders, consumables, and final settlement.

---

## 2. Requirement Classification

| Item | Classification |
|---|---|
| Party and retail separation | PRD Requirement |
| Working invoice editable until final close | PRD Requirement |
| Multiple payments on account | PRD Requirement |
| Required payment receipt wording | PRD Requirement |
| Operating order and consumable issue | PRD Requirement |
| Party Wallet only at settlement | PRD Requirement |
| Exact package/service catalog | Owner-Configurable Value |
| Cancellation/reschedule terms | Owner-Configurable Value |
| Deposit policy | Owner-Configurable Value |
| Final-close readiness checklist | Derived Implementation Convention |

---

## 3. Separation

1. Party services, consumables, and rental assets remain separate from retail products.
2. Mixed invoices/orders are blocked.
3. Party stores are distinct operational contexts.
4. Product Wallet is not used in party settlement.

---

## 4. Booking

A booking captures:

- Customer.
- Child information where relevant.
- Date/time/location.
- Contacts.
- Notes.
- Planned services.
- Consumables.
- Rental assets.
- Assigned responsibilities.

Exact required fields remain configurable.

---

## 5. Working Invoice

1. Starts as draft/working.
2. Remains editable before final close.
3. Records controlled additions/removals.
4. Preserves change history.
5. Becomes immutable after final close.

---

## 6. Payments on Account

1. Multiple payments are supported.
2. Each payment creates a separate receipt.
3. Required label:

`Payment on Account for Party Invoice No. [number]`

4. Payment source and evidence are preserved.
5. Duplicate posting is blocked.

---

## 7. Operating Order

The operating order may:

- Reserve services.
- Reserve rental assets.
- Assign responsibilities.
- Issue consumables.
- Record controlled additions/removals.
- Track completion.

Exact operational checklist remains owner-configurable.

---

## 8. Consumables

1. Issued from party stores.
2. Recorded against the operating order.
3. Actual consumption is preserved.
4. Returnable unused quantities require a referenced return movement if permitted.
5. No direct balance edit.

---

## 9. Final Close

Final close verifies:

- Booking readiness.
- Operating order status.
- Consumables.
- Rental asset return/inspection.
- Payments on account.
- Party Wallet if used.
- Remaining amount or credit.

Final close creates an immutable final party invoice and final receipt.

---

## 10. Manual Browser Verification

Verify:

1. Retail/party mixing block.
2. Booking create/edit/reschedule/cancel.
3. Working invoice edit before close.
4. Edit blocked after close.
5. Multiple payment receipts with exact wording.
6. Operating order and consumables.
7. Party Wallet only.
8. Readiness block before final close.
9. Final settlement reconciliation.
10. RTL/LTR, print, responsive, console, and network.

No automated tests are created or executed.
