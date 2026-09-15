# 48 — POS Financial Calculation Policy

**Product:** TOY & JOY
**Phase:** Phase 1
**Status:** Derived implementation policy — team-adopted defaults, owner approval outstanding
**Authority:** POS-03–POS-07, POS-05, CSH-02–CSH-04, NFR-06
**Blockers:** BLK-008, BLK-013
**Companion to:** `docs/31` (POS specification), `docs/26` (discount/return policy), `docs/41` (rounding conventions)

---

## 1. Purpose

`docs/31` defines the POS flow and `docs/26` defines discount and return behaviour. Neither defines the arithmetic: where rounding occurs, how optional tax interacts with discount, how a split payment settles, and how cash change is rounded.

The same rule as `docs/41` applies — rounding must be defined once and applied identically on screen, on the thermal receipt, on the A4 invoice, and in reports.

---

## 2. Requirement Classification

| Item | Classification |
|---|---|
| Tax is optional **per invoice**, sequence stays unified | PRD Requirement (POS-04) |
| Only one discount type may apply to an amount; no stacking | PRD Requirement (POS-05) |
| Printed invoice shows item, quantity, original price, discount line, net | PRD Requirement (POS-06) |
| Totals show gross, total discount, net, optional tax, final | PRD Requirement (POS-06) |
| Gift Receipt shows no prices | PRD Requirement (POS-07) |
| Cash and manually recorded electronic payment with evidence | PRD Requirement (POS-03) |
| Cashier never sees expected values before closing | PRD Requirement (CSH-02) |
| Rounding level (line vs receipt) | **PENDING — POSF-01** |
| Cash rounding / smallest denomination | **Owner Approved — POSF-02: 5 EGP, nearest denomination, cash tender only** |
| Split payment ordering and residual | **PENDING — POSF-03** |
| Discount replacement behaviour | **PENDING — POSF-04** |

---

## 3. Calculation Order

Proposed canonical order:

```
1. line gross            = quantity × unit price
2. line discount         (one type only, POS-05)
3. line net              = line gross − line discount
4. invoice discount      allocated pro-rata to lines
5. taxable base          = Σ line net after allocation
6. tax                   only if the authorised user enabled it (POS-04)
7. invoice total         = taxable base + tax
8. cash rounding         applied to the payable amount only
```

Rounding is applied at step 3 and step 7 only. Intermediate values are not re-rounded.

Step 8 is critical: cash rounding adjusts **what is collected**, not the invoice total. The difference is posted as a distinct rounding line so that the drawer reconciles and the invoice remains arithmetically intact. Absorbing it into the total makes the receipt total disagree with the sum of its lines.

POSF-02 uses an exact 5 EGP denomination and the existing nearest-denomination rule (`37 → 35`, `38 → 40`, `35 → 35`). It applies only when a cash tender participates in settlement; electronic and other non-cash-only settlements retain the exact invoice total. Missing or invalid configuration blocks cash with a localized safe message and must not prevent non-cash checkout.

---

## 4. Discount Non-Stacking

POS-05 is explicit: only one discount type may apply to an amount. Implementation:

1. When a second discount is applied to the same amount, the system **blocks it or offers replacement**. It never sums them.
2. Replacement is an explicit user choice, recorded with actor and audit.
3. Customer/group discount and item/invoice discount compete for the same amount and cannot coexist on it.
4. A discount above the configured limit requires approval (`DEC-043` §4), and the approval is bound to that invoice.

This must be enforced in the calculation service, not in the UI. A blocked stack that is only prevented by a disabled button is not enforcement (NFR-03).

---

## 5. Tax

POS-04 makes tax a per-invoice choice by an authorised user, with the invoice sequence unchanged. Therefore:

1. Tax applicability is a stored attribute of the invoice, not a global setting read at print time.
2. The tax rate and inclusive/exclusive mode in force at approval are stored on the invoice (`docs/46` §3), so reprinting an old receipt reproduces the original figures.
3. Numbering is unaffected — taxed and untaxed invoices share one sequence (POS-04, NFR-06).
4. Where the settings lock says purchase tax is disabled (`DEC-043`), that does not disable sales tax. They are independent settings.

---

## 6. Payments

| Rule | Behaviour |
|---|---|
| Methods | Cash and manually recorded electronic (POS-03). No gateway in Phase 1 |
| Split payment | Multiple payment rows per invoice, each with method, amount, and evidence reference |
| Ordering | Electronic amounts are entered explicitly; cash settles the residual (POSF-03) |
| Overpayment | Cash overpayment produces change; electronic overpayment is rejected |
| Evidence | Electronic payment requires an attached terminal receipt image via `docs/18` private storage |
| Underpayment | The invoice cannot be approved. Partial settlement is not a Phase 1 concept for retail |

The sum of payment rows must equal the payable amount including any cash-rounding line. This is checked in the approval transaction, not at save time.

---

## 7. Receipt and Gift Receipt

Per POS-06, the printed invoice shows, per item: description, quantity, original price; and where an item discount exists, a following line with the discount and the resulting net item value. Totals show gross items, total discount, net after discount, optional tax, and final total.

Per POS-07, the Gift Receipt shows **no prices at all** — no unit price, no discount, no total, and no tax. It carries enough reference to identify the original sale for exchange or return. Any field that permits price inference (line totals, a payable amount, a discount percentage) is excluded.

---

## 8. Shift Interaction

CSH-02 forbids showing expected values before the cashier submits actual amounts. Therefore the calculation of expected cash and expected electronic totals must run **server-side after submission** (CSH-03), and no endpoint may return expected values to a cashier session before that point. A hidden field in the page source is a violation.

Variance = actual − expected, computed per method, exposed only to authorised managers/reviewers.

---

## 9. Manual Browser Verification

Verify: hand-calculated invoice matches screen, thermal receipt, A4 invoice, and report identically; a second discount on the same amount is blocked or replaced, never summed; discount above limit requires approval; tax toggled per invoice with the sequence unbroken; a reprinted old invoice reproduces original figures after a settings change; split payment must sum exactly; electronic payment without evidence is blocked; cash rounding appears as its own line and the drawer reconciles; Gift Receipt exposes no price anywhere including page source; expected values unreachable from a cashier session before submission; RTL/LTR and mobile clean.

No automated tests are created or executed.
