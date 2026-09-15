# 38 — Print and Export Specification

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Detailed implementation specification derived from the approved PRD, Implementation Plan, Architecture, Roles/Permissions, and policies 17–29  
**Authority order:** PRD functional behavior → Implementation Plan sequencing/phase gates → approved decisions/policies → this specification  
**Important:** Exact production master data, legal wording, hardware models, provider choices, and final numeric limits remain configurable where the source documents do not define them.

---


## 1. Purpose

Define thermal, A4, label, PDF, Excel, and downloadable artifact behavior across Phase 1.

## 2. Principles

- Dedicated templates.
- Source snapshot and immutable historical values.
- Permission and redaction.
- Locale/direction.
- Stable numbering.
- Reprint history.
- No document mutation from printing.
- Protected artifact delivery.
- Bounded exports.
- Formula injection protection.

## 3. Output Families

### Thermal

- POS invoice.
- Payment-on-account receipt.
- Shift closing receipt.
- Return/refund receipt where approved.
- Gift Card use/issue receipt where required.

### A4

- Sales invoice.
- Purchase order/invoice/return.
- Transfer/receipt/difference.
- Adjustment/count.
- Shift closing report.
- Party working/final invoice.
- Asset checkout/return/inspection.
- Quotation.
- Reports.

### Label

- Product barcode label by selected location and approved price.

### Digital

- PDF.
- Excel.
- CSV only where explicitly approved.
- Protected generated artifacts.

## 4. Common Header/Footer

As applicable:

- Company identity.
- Branch/store.
- Document title.
- Number.
- Status.
- Date/time/timezone.
- Source/reference.
- Actor/approver.
- Page number.
- Locale.
- Reprint/draft marker.
- Confidential/sensitive notice.

## 5. POS Invoice

Must show:

- Items.
- Quantity.
- Original price.
- Discount line.
- Net item value.
- Gross total.
- Total discount.
- Net after discount.
- Optional tax.
- Final total.
- Payments.
- Customer if allowed.

## 6. Gift Receipt

- No prices.
- Original sale reference.
- Eligible lines.
- Validation code/identifier.
- Reprint marker.
- No hidden price metadata in rendered output.

## 7. Party Payment Receipt

Exact label:

`Payment on Account for Party Invoice No. [number]`

Include payment number/date/method/amount, party invoice reference, customer, branch/party context, and reprint status.

## 8. Shift Outputs

Thermal:

- Concise close summary.

A4:

- Payment methods.
- Cash movements.
- Sales/refunds.
- Expected/actual.
- Variance.
- Review/approval.

Expected values follow viewer permission.

## 9. Labels

- Product name/code/barcode.
- Selected location.
- Approved price or branch exception.
- Template/version.
- Print event.
- Reprint reason where required.
- No label for unpriced product.
- Queue quantity based on remaining stock/location.

## 10. PDF

- Approved renderer only after package review.
- Arabic font/shaping verified.
- Stable page breaks.
- No external unsafe resource loading.
- Generated artifact protected.
- Snapshot filters/scope.
- Expiry/retention per policy.

## 11. Excel

- Header metadata/sheet names.
- Stable column keys and localized display headers.
- Numeric/date cells typed correctly.
- Sensitive fields omitted/redacted.
- Formula injection neutralized for user-controlled strings.
- Row limits.
- Large export queued.
- Error export identifies rejected import rows.

## 12. Reprint

- Separate permission where required.
- Reprint event records user, source, printer, copies, reason, timestamp.
- Printed content remains historical snapshot.
- Reprint marker if business output requires.
- Reprint must not post stock/payment again.

## 13. Print Preview and Printer Selection

- Authorized printer list by branch/store/purpose.
- Default may be suggested.
- User confirms selected printer/output.
- Browser print used where integration allows.
- Device-specific integration remains configurable.

## 14. Export Security

- Reauthorize at generation and download.
- Short-lived protected link.
- Scope preserved.
- Artifact owner/permission checked.
- Audit sensitive exports.
- Expire/delete generated artifacts according to policy.

## 15. Failure Handling

- Failed generation shows safe retry.
- No duplicate business action.
- Queue status visible.
- Partial label print tracked.
- Error includes request ID.
- Failed artifact not offered.

## 16. Manual Verification

Verify each output in Arabic/English, thermal/A4/label dimensions where available, page breaks, totals, historical snapshots, redaction, permission, reprint history, no duplicate effects, export safety, protected download, console/network.

## 17. Supplier Return Output

- Route: `/purchasing/returns/{id}/print`.
- A4/browser-print output includes return number/status, supplier/store, source invoice, mandatory reason, line quantities, original source unit cost, total cost, creator/approver, and cost-flow disclaimer.
- Title and footer may be overridden only by approved effective `financial_setting_versions` keys:
  - `purchasing.supplier_return.print_title`
  - `purchasing.supplier_return.print_footer`
- Number sequence remains in `document_sequences`; the first local default is `PRET-{YYYY}-` and is not a production approval. An approved `purchasing.supplier_return.number_prefix` may supply the initial sequence prefix.
- No printer, paper, copy count, or financial approval value is invented; those remain Owner/UAT inputs.
