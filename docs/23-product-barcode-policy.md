# 23 — Product and Barcode Policy

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Derived implementation policy based on the approved PRD  
**Authority:** PRD requirements MD-02–MD-05 and PRC-01–PRC-02  
**Owner-configurable values:** Product master data, unit lists, import limits, and production barcode allocation controls  
**Production decision pending:** Final catalog hierarchy, opening data, exact units, and production import template

---

## 1. Purpose

This policy defines the local implementation rules for product identity, categories, brands, supplier links, attributes, barcodes, images, search, and import behavior.

It does not invent new commercial rules. Where the PRD does not specify an exact value, the value remains configurable and requires owner approval before production.

---

## 2. Requirement Classification

| Item | Classification |
|---|---|
| Stable internal item code | PRD Requirement |
| Supplier barcode support | PRD Requirement |
| Local barcode format: 4-digit supplier code + 6-digit serial | PRD Requirement |
| Product types: standard, composite, service | PRD Requirement |
| Arabic and English names/descriptions | PRD Requirement |
| Main image + up to four additional images | PRD Requirement |
| Colour, size, character, and age as searchable attributes only | PRD Requirement |
| Exact category hierarchy | Owner-Configurable Value |
| Exact unit-of-measure list | Owner-Configurable Value |
| Exact supplier code assignments | Owner-Configurable Value |
| Import row and file limits | Owner-Configurable Value |

---

## 3. Product Identity

1. Every product has an immutable internal item code.
2. Supplier changes must not change the internal item code.
3. Product identity and barcode identity remain separate.
4. Historical documents retain the original product reference.
5. Duplicate active item codes are prohibited.
6. A product status may be active or inactive.
7. A used product cannot be physically deleted from history.

---

## 4. Product Types

Supported types:

- `standard`
- `composite`
- `service`

Type-specific behavior must remain consistent with the PRD and must not create stock behavior for services unless explicitly approved.

Changing type after transactional use is blocked unless an approved migration/correction process exists.

---

## 5. Product Fields

The product card supports:

- Arabic name.
- English name.
- Arabic description.
- English description.
- Item/model number.
- Type.
- Status.
- Unit of measure.
- Category and subcategory.
- Brand.
- Preferred supplier.
- Average cost.
- Reorder threshold.
- Dimensions.
- Weight.
- Target age.
- Suitable gender.
- Character.
- Colour.
- Main image.
- Up to four additional images.
- Key points.
- Arabic keywords.
- English keywords.
- Fractional-quantity flag.

Required versus optional fields remain configurable by implementation stage, but identity, type, status, and one display name must be present locally.

---

## 6. Categories and Brands

1. Categories may be hierarchical.
2. Cycles are prohibited.
3. A subcategory must belong to one parent.
4. Inactive categories and brands remain visible on historical records.
5. Production category depth remains owner-configurable.
6. Category and brand codes, if used, must be unique within their configured scope.

---

## 7. Units and Fractional Quantities

1. Fractional quantities are allowed only where the product is explicitly configured to allow them.
2. Products not configured for fractions accept whole quantities only.
3. Unit precision is configurable.
4. Historical documents preserve the unit used.
5. Unit conversion is not assumed unless later approved.

---

## 8. Barcode Rules

### 8.1 Supplier Barcode

A product may use an international or supplier-provided barcode.

### 8.2 Local Barcode

The local barcode supports:

- Four-digit supplier code.
- Six-digit serial number.

The internal item code remains independent.

### 8.3 Controls

1. Barcode values are unique.
2. Barcode allocation is concurrency-safe.
3. A barcode already used historically cannot be silently reassigned.
4. Supplier-code assignment is configurable master data.
5. Local serial allocation is sequential within its configured scope.
6. Barcode rendering and barcode allocation are separate concerns.

---

## 9. Images

1. One main image is allowed.
2. Up to four additional images are allowed.
3. Files follow `18-attachment-media-policy.md`.
4. Image order is explicit.
5. Main-image changes are audited where required.
6. Historical references must not break when images are replaced.

---

## 10. Search Behavior

Search supports:

- Exact internal item code.
- Exact barcode.
- Name in Arabic or English.
- Model number.
- Category.
- Brand.
- Supplier.
- Keywords.
- Colour, size, character, age.

Exact code/barcode matches should be prioritized.

---

## 11. Excel Import

The import workflow must support:

- Upload.
- Column mapping.
- Validation.
- `Create Only`.
- `Update Existing`.
- Review before approval.
- Error report.
- Rejected-row download.

Invalid rows are not written.

The exact template, file size, and row limit remain configurable and require owner approval before production.

---

## 12. Manual Browser Verification

Verify:

1. Stable item code.
2. Duplicate code/barcode rejection.
3. Supplier change preserves identity.
4. Product types and validations.
5. Image count and main-image rules.
6. Fractional quantity behavior.
7. Exact code/barcode search.
8. Category cycle prevention.
9. Import create/update/error behavior.
10. RTL/LTR, desktop/mobile, console, and network.

No automated tests are created or executed.
