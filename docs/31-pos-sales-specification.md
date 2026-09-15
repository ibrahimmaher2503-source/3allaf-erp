# 31 — POS Sales Specification

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Detailed implementation specification derived from the approved PRD, Implementation Plan, Architecture, Roles/Permissions, and policies 17–29  
**Authority order:** PRD functional behavior → Implementation Plan sequencing/phase gates → approved decisions/policies → this specification  
**Important:** Exact production master data, legal wording, hardware models, provider choices, and final numeric limits remain configurable where the source documents do not define them.

---


## 1. Purpose

Define the complete retail POS lifecycle from product discovery through cart, customer, tax, discount, payment, evidence, approval, stock posting, suspension/retrieval, printing, Gift Receipt, and restricted offline behavior.

## 2. Traceability

- PRD: POS-01–POS-07, INV-02, PRC-07–PRC-08, CUS-01, NFR-01–NFR-06.
- Implementation Plan: DM 3.1, DM 3.2, DM 3.4.
- Tasks: TSK-023, TSK-024, TSK-026, TSK-029–TSK-030.
- Policies: docs/18, 19, 24, 26, 27.

## 3. Preconditions

A cashier may start a sale only when:

- Authenticated and active.
- Assigned to branch/store/drawer.
- Has active shift.
- Assigned selling store is resolved.
- POS permission is present.
- Connectivity/offline eligibility is known.
- Required pricing/payment configuration exists.

## 4. Sale Context

Every sale stores and displays:

- Sale UUID.
- Final document number after approval.
- Branch.
- Selling store.
- Cashier.
- Cash drawer.
- Shift.
- Customer if selected.
- Currency.
- Price source/version.
- Tax selection.
- Discount source.
- Payment records.
- Stock movements.
- Request/idempotency keys.
- Timestamps and status.

The context is server-resolved; hidden browser fields are not trusted.

## 5. POS States

- `new`
- `suspended`
- `payment_pending`
- `approved`
- `cancelled`
- `reversed` through referenced correction
- `offline_provisional`
- `sync_conflict`
- `rejected`

Approved sales are immutable.

## 6. Product Search

Search by:

- Exact barcode.
- Exact item code.
- Arabic/English name.
- Model number.
- Keywords.

Response includes only authorized/safe fields:

- Name.
- Code/barcode.
- Current approved price.
- Pricing-pending state.
- Availability in assigned selling store.
- Other-branch availability as informational only.
- Fractional flag.
- Open-price eligibility.
- Optional image.

Exact code/barcode matches take priority.

## 7. Add to Cart

Before adding:

- Product active.
- Saleable type.
- Approved price exists unless authorized open-price.
- Quantity valid.
- Fraction allowed where configured.
- Store scope valid.
- Stock policy passes.
- Offline policy passes.

Cart line snapshots:

- Product reference.
- Description.
- Unit.
- Quantity.
- Original unit price.
- Applied price/version.
- Discount.
- Net line amount.
- Tax basis where applicable.
- Open-price reason if used.

## 8. Quantity Changes

- Quantity change permission may be separate.
- Whole-only products reject fractions.
- Zero removes line only before approval.
- Negative quantity is prohibited.
- Stock is revalidated at final approval.
- Restricted roles may require manager override for quantity changes.
- Every sensitive override is audited.

## 9. Customer

POS supports:

- Search by normalized phone/name.
- Select existing customer.
- Register minimum new customer.
- Optional sale without customer unless policy requires one.
- Unique phone validation.
- Purpose-scoped sensitive fields.

Customer selection must not expose Party Wallet to Cashier.

## 10. Pricing

### Standard price

Use active approved price for selected location.

### Unpriced

- Display zero and pricing-pending.
- Block sale.
- Block label print.

### Open price

Requires:

- Permission.
- Reference price.
- Configured min/max.
- Reason where configured.
- Audit.
- Online status unless explicitly approved otherwise.

Server recalculates final price at approval.

## 11. Discounts

- One discount type only per applicable amount.
- A second discount replaces, not stacks.
- Display replacement confirmation.
- Limits and approval are configurable.
- Offline special discounts are blocked.
- Preserve original and net values.
- Print exact breakdown.

## 12. Tax

- Optional per invoice.
- Authorized selection.
- Tax settings snapshot preserved.
- Unified normal invoice sequence.
- Recalculate server-side before approval.
- Rounding is configurable.

## 13. Totals

At minimum calculate:

- Gross item total.
- Item/invoice discount total.
- Net after discount.
- Optional tax.
- Final total.
- Payment total.
- Remaining/change according to payment policy.

All calculations use defined precision and deterministic order.

## 14. Payments

Supported:

- Cash.
- Manually recorded electronic.

Electronic payment may require:

- Method.
- Amount.
- Reference.
- POS-terminal receipt attachment.
- Evidence validation and protected storage.

Mixed payment is implemented only if approved/configured. No gateway transaction is executed.

Duplicate payment posting is blocked with idempotency.

## 15. Approval and Posting

Final approval runs atomically:

1. Reauthenticate/authorize current context.
2. Lock/validate shift, drawer, price, and stock.
3. Validate cart, customer, tax, discount, payments, evidence.
4. Allocate final document number.
5. Create immutable sale and lines.
6. Create payments.
7. Post stock movements.
8. Update balance summary.
9. Record audit.
10. Commit.
11. Produce printable result.

Failure rolls back all effects.

## 16. Suspended Sales

Suspension stores:

- Owner cashier.
- Branch/store/drawer/shift context.
- Cart lines.
- Customer.
- Price snapshot for display only.
- Expiry/configuration.
- Notes.

Rules:

- Cashier sees own holds by default.
- Another cashier requires explicit permission.
- Retrieval reprices/revalidates using current server truth.
- Suspended sale has no stock/payment effect.
- Expired/cancelled holds preserve audit as configured.

## 17. Cancellation and Correction

- Draft/new/suspended cancellation follows permission and reason policy.
- Approved sale is not edited or deleted.
- Return/refund/exchange/reversal uses referenced documents.
- Original remains preserved.

## 18. Gift Receipt

- Linked to approved sale.
- Contains no prices.
- Contains eligible line references.
- Unique/reprint history.
- Supports later return/exchange validation.
- Access and reprint are permission-controlled.

## 19. Printing

Thermal and A4 invoice include:

- Company/branch.
- Invoice number/date.
- Cashier.
- Customer if allowed.
- Items, quantities, original prices.
- Discount line and net value.
- Gross, discount, net, tax, final total.
- Payment summary.
- Reprint marker where applicable.

Gift Receipt excludes price values.

## 20. Restricted Offline POS

Allowed only for eligible device/user/branch/shift and configured limits.

Allowed:

- Cash.
- Manually recorded electronic payment under rules.

Blocked:

- Credit.
- Wallets.
- Loyalty redemption.
- Special discounts.
- Unpriced/open-price unsafe actions.
- Stale data outside allowed age.
- Conflict-prone operations.

Each provisional transaction stores:

- Local UUID.
- Idempotency key.
- Payload hash.
- User/device/branch/shift binding.
- Policy/schema version.
- Created/expiry time.

On sync, server revalidates and either posts, rejects, or creates conflict. Server price/stock/wallet/loyalty truth prevails.

## 21. UI Layout

Dedicated POS shell:

- Search/scanner input.
- Product result.
- Cart table/cards.
- Customer area.
- Totals panel.
- Tax/discount controls.
- Suspend/retrieve actions.
- Payment drawer.
- Connectivity/offline banner.
- Approval/print result.

Keyboard/scanner/touch optimized. No unnecessary admin navigation.

## 22. Manual Browser Scenarios

Verify:

- Barcode/code/name search.
- Product add/remove/quantity.
- Whole/fractional.
- Assigned-store sale and other-store informational search.
- Unpriced block.
- Open-price permission/range/reason.
- Customer selection/registration.
- Tax optional.
- Discount replacement/no stack.
- Cash/electronic/evidence.
- Double-submit protection.
- Concurrent stock/price conflict.
- Suspend/retrieve/expiry/other-user denial.
- Approval creates payment, stock, audit, print.
- Gift Receipt no price.
- Offline eligibility/block/sync/conflict.
- RTL/LTR, desktop/mobile/tablet, console/network, print preview.

No automated tests are created or executed.
