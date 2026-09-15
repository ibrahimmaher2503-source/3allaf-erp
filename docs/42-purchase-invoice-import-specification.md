# 42 — Purchase Invoice Import Specification

**Product:** TOY & JOY
**Phase:** Phase 1
**Status:** Owner-approved local policy baseline — DEC-050 (2026-08-06)
**Authority:** PUR-04, AC-PUR-04
**Blockers:** BLK-010 (purchase templates), BLK-009 (product/barcode identity)
**Production boundary:** DEC-050 approves the documented template, columns, limits, and duplicate policy for local/dev. Real supplier/product master data and UAT evidence remain required before production use.

---

## 1. Purpose

Defines the staged Excel/CSV import path for purchase invoices. It reuses the existing staged-import pattern already implemented for products (`ProductImportBatch`, `ProductImportRow`, `StageProductImportAction`, `DownloadProductImportErrorsAction`) rather than inventing a second import mechanism.

---

## 2. Requirement Classification

| Item | Classification |
|---|---|
| Import must be staged and validated before any stock effect | PRD Requirement (AC-PUR-04) |
| Import errors are isolated and downloadable | Approved pattern (TSK-012) |
| Unsafe file is blocked with no stock effect | PRD Requirement (AC-PUR-04) |
| Official template and exact column names | **DEC-050 approved baseline — OI-IMP-01/02** |
| Mandatory vs optional fields | **DEC-050 approved baseline — OI-IMP-03** |
| Matching keys | **DEC-050 approved baseline — OI-IMP-04** |
| File size / row limits / accepted types | **DEC-050 approved baseline — OI-IMP-05** |
| Bad-row disposition | **DEC-050 approved baseline — OI-IMP-07** |
| Duplicate prevention key | **DEC-050 approved baseline — OI-IMP-08** |
| Retry semantics | **DEC-050 approved baseline — OI-IMP-09** |
| Import mode (create / update / both) | **DEC-050 approved baseline — OI-IMP-10** |

---

## 3. Proposed Template Columns

Proposal only. Exact Arabic/English header text must be approved (OI-IMP-02) because header text becomes a data contract the client's staff will type by hand.

| Column | Required | Notes |
|---|---|---|
| `supplier_code` | Yes | Matches `suppliers.code`, not the display name |
| `supplier_invoice_number` | Yes | Duplicate-detection candidate (OI-IMP-08) |
| `invoice_date` | Yes | Format and timezone per OI-OPEN-03 |
| `receiving_store_code` | Yes | Must be a store the user is scoped to |
| `purchase_order_number` | No | Required only if OI-RCV-03 forbids invoices without a PO |
| `item_code` or `barcode` | Yes (one of) | Matching precedence per OI-IMP-04 |
| `quantity` | Yes | Fractional only where the product permits it |
| `unit_cost` | Yes | Validated against OI-COST-08 rules |
| `line_discount_value` | No | Type declared by `line_discount_type` |
| `line_discount_type` | No | `percentage` or `amount` |
| `tax_rate` or `tax_code` | No | Only if OI-TAX-01 enables purchase tax |
| `notes` | No | Free text, length-limited |

Header row must be exact. Column order is not significant; column presence is.

---

## 4. File Safety

Enforced regardless of owner answers:

1. Extension and MIME are both validated; extension alone is never trusted.
2. Formulas and macros are rejected. `.xlsm` is not accepted. A cell beginning with `=`, `+`, `-`, or `@` is treated as text and flagged, never evaluated.
3. Files are stored through the existing private attachment path (`docs/18`), never in public storage.
4. Original filename is never used as a stored filename.
5. Proposed limits pending OI-IMP-05: 10 MB, 5,000 rows, `.xlsx` and `.csv` only.

---

## 5. Staging Pipeline

```
upload -> validate file -> create import batch (status: staged)
      -> parse rows into import rows
      -> validate each row (supplier, store, product, quantity, cost, permissions)
      -> present preview: valid rows, invalid rows, computed totals
      -> owner/user approval
      -> create purchase invoice in DRAFT only
```

The import never creates an approved invoice, never posts stock, and never touches weighted-average cost. Stock effects occur only through the receiving path in `docs/43`.

---

## 6. Bad-Row Disposition

Three options exist; the owner must pick one (OI-IMP-07):

| Option | Effect |
|---|---|
| A — Reject whole file | Safest, highest re-work. No batch is created. |
| B — Stage valid, isolate invalid | Valid rows proceed after approval; invalid rows are downloadable as an error file. **Proposed default**, matches the existing product-import behaviour. |
| C — Halt batch on first error | Predictable but poor for large files. |

Mixing options per import type is not permitted.

---

## 7. Duplicate Prevention

Candidate keys (OI-IMP-08), at least one must be mandatory:

1. `supplier_id` + `supplier_invoice_number` — recommended primary key, because it matches how the business actually identifies a supplier document.
2. File content hash — catches accidental re-upload of the identical file, but not a re-keyed duplicate.
3. External reference — only useful if the supplier supplies a stable reference.

Proposed: enforce (1) as a unique constraint with an explicit override permission, and use (2) as a soft warning at upload time.

---

## 8. Retry

Proposed (OI-IMP-09): a retry creates a **new batch** that references the failed batch. The original batch is never mutated. This preserves the append-only audit posture in `docs/19` and makes "why did this import run twice?" answerable.

---

## 9. Import Mode

Proposed (OI-IMP-10): **Create Only** in Phase 1. "Update Existing" against a purchase invoice risks editing a document that has already posted stock, which conflicts with immutability. If update is required, it must be limited to Draft documents and gated by a separate permission.

---

## 10. Manual Browser Verification

Verify: template download; exact-header rejection; formula/macro rejection; oversized file rejection; row-limit rejection; unknown supplier/store/product isolation; duplicate invoice number blocked; error file download; preview totals match the computed invoice; approval creates a Draft only with zero stock effect; retry creates a new batch; unauthorized user denied; RTL/LTR and mobile layouts clean.

No automated tests are created or executed.
