# v0.1.22-hotfix1 Post-Deployment Correction Evidence

**Date:** 2026-09-01  
**Branch:** `hotfix/v0.1.22-post-deployment-review`  
**Baseline:** tagged v0.1.22 commit `3a92ba2980eb26bca17a30b270d98af32b0645ce`  
**Release identity:** `0.1.22-hotfix1`

## Correction ledger

- Arabic: added missing residence, inventory, purchasing, filter-deletion, barcode-label, pricing-remediation, physical-size and enum labels; affected raw statuses use localized labels.
- Setup: a satisfied required or optional rule is `completed`; `ready` is no longer emitted as a second satisfied state. Progress and counters include required steps only.
- Product Filters: delete permission is enforced server-side; unused records delete, referenced values require same-group replacement, safe family-only removal, or archive; historical variant assignments cannot be detached. Replacement de-duplicates existing target assignments transactionally and every outcome is audited.
- Inventory Balances: reset clears every balance filter and returns the canonical balance URL while retaining authorization and server pagination.
- Quantities: Product Cards cannot enable fractional quantities. Purchase, inventory, returns and POS inputs/actions accept whole product units only; displays remove stored decimal padding. Monetary cost, price, tax, discount and payment precision remains unchanged. The diagnostic command is read-only.
- Barcode workspace: searchable bilingual product selection, actual active barcode selection, outlet/template/printer context, copies, preview and PDF output are implemented with bounded results and server authorization.
- Product Card pricing: average inventory cost and Base Consumer Price List 0 are explicitly separated; missing/zero selling price remediation targets the exact base-price field and barcode-only labels remain possible.
- Barcodes: EAN-13 checksum validation and standards-based EAN-13 guard/data patterns are rendered; printable ASCII Code 128-B uses start, weighted checksum, stop, quiet zones and human-readable text.
- Printing: persisted template layout includes physical mm width/height, 203/300 DPI profile, orientation, margin, gap, A4 rows/columns, symbology and visible fields. Exact 50 × 30 mm and 40 × 25 mm profiles are forced to their physical size; 58/80 mm thermal and A4 profiles are wired through preview/browser/PDF output. A4 uses a label grid; roll/label PDF uses the saved physical page box.
- Purchase Invoice cards: remaining quantity is explicitly labeled in whole units.
- Version: the application identity is canonically `0.1.22-hotfix1`.

## Verification actually completed

- One consolidated focused pass: **4 tests, 59 assertions, all passed** (`tests/Unit/V022Hotfix1PostDeploymentTest.php`).
- All changed/new PHP files passed PHP 8.4 syntax checks; Arabic/English JSON parsed (`5541` / `2360` keys); `git diff --check` passed.
- Barcode validation covered a valid `4006381333931` EAN-13 and `TJ-ABC-001` Code 128 result, SVG/bar structures, source checksum/pattern contracts, and invalid-input guards through focused assertions/source inspection.
- The preserved v0.1.22 printing matrix remains valid and was not repeated: Arabic/English × desktop/mobile × template library/printer profiles, **8 cases passed**, with correct direction, no overflow or mixed-language leakage, and 44 px controls.
- One allowed frontend build passed with Node `v20.20.2`, npm `10.8.2`, Vite `v8.2.0`. Manifest SHA-256: `cc095c1ecdfd4690ed5e500b18c78c63d74043ae0933e548c72f5111a92fdf78`.
- Migration delta: one additive nullable JSON column, `2026_09_01_000103_add_barcode_label_layout_to_print_templates.php`. The preceding isolated 96-migration MariaDB chain and rollback/remigrate evidence remains valid; this correction pass did not repeat database work. No migration was run against shared or production data.

## Remaining acceptance

Only physical-printer output remains: an operator must print and measure/scanner-check one sample on each configured thermal/label/A4 device class. This cannot be truthfully completed without the physical hardware.

## Safety boundary

Production runtime/database, shared data, active release symlink, tags, pushes, remotes and deployment/cutover were untouched. The release prepared from the final commit is immutable and links only `.env` and `storage` to the existing shared paths; neither target was read or mutated during preparation.

## Immediate corrective hotfix — v0.1.22-hotfix2

**Baseline:** `4434f7b5ac993990f114cc2cfdd05c1d1337b24e`
**Branch:** `hotfix/v0.1.22-hotfix2-product-barcode`

- Product Card root cause: the form supplied an unchecked hidden `fractional_quantity=false` while validating that same key as prohibited. Validation happened outside the guarded save path, so the canonical `products.sale_price` write never ran. The form now normalizes the control to boolean false, validates it as required boolean, requires a positive base consumer price, catches and localizes validation, focuses the precise invalid field, reloads the persisted product, and recomputes readiness from current database state. The persisted/readiness source remains `products.sale_price`; no stock, movement, identity, item-code or barcode mutation was added.
- Barcode root cause: the Purchase Invoice action and `/pricing/labels` used different render paths, and the legacy destination renderer substituted item code text for an absent barcode. All paths now enter `BarcodeLabelService` and the single standalone `pricing.label-print` renderer. The normal workflow requires only product, copies and Preview; outlet/template/printer are advanced optional settings. A company default printer/template is selected when present; otherwise the built-in profile is thermal 50 × 30 mm, 203 DPI, 1 mm margin and one label per physical page.
- The renderer selects only active real barcode records, primary first, exposes a selector for additional active records, never treats item/model code as barcode, renders valid EAN-13 or Code 128 bars plus the exact LTR value, supports preserved-state manual/generate remediation, and shares identical preview/print/PDF composition. A4 templates use configured rows, columns and independent horizontal/vertical gaps.

### Single isolated focused verification pass

- Disposable socket-only MariaDB database `rajeh_hotfix2_verify`: 97 migrations applied. Product `2452424254252` (`TEST-MODEL-0002`) began with `sale_price=NULL`, saved through the rendered Livewire Product Card with the unchecked non-fractional control, reloaded as `25.00`, `fractional_quantity=0`, and immediately rendered ready with no stale warning or raw validation/translation key.
- Authenticated Arabic Chromium: workspace RTL; no `Select template`; no sidebar/header in print view; 50 × 30 mm/203 DPI attributes exact; two requested copies rendered exactly two independent SVG symbols; A4 rendered through the selected A4 profile. No console errors or horizontal overflow.
- Rendered-symbol decode: EAN-13 copy 1 = `4006381333931`; EAN-13 copy 2 = `4006381333931`; Code 128 = `TESTLOCAL0001`. The displayed values matched each decoded symbol. PNG captures of every tested symbol accompany the HTML/SVG decode evidence.
- No-barcode remediation generated one isolated local Code 128 record only after the explicit action and returned with product 2 still selected and copies `2`. Merely opening preview created nothing. Purchase Invoice `/purchasing/invoices/1/destinations/1/labels` redirected to `/pricing/labels?invoice=1&store=1` with the same product and two-copy prefill.
- Focused PHPUnit: 4 tests, 26 assertions, all passed (`tests/Unit/V0122Hotfix2CorrectiveTest.php`). PHP 8.4 syntax passed for all changed PHP route/service/test files. `lang/ar.json` and `lang/en.json` parsed successfully. Frontend assets did not change, so no second Node/Vite build was required.
- Visual evidence: `.ai/evidence/v0.1.22-hotfix2/barcode-workspace-ar-desktop.png`, `thermal-50x30-preview-two-ean.png`, `a4-template-preview.png`, `product-card-ar-after-save.png`, individual symbol PNGs, and `browser-results.json`. All four requested cases were visually inspected; no clipping, overlap, oversized printed page around thermal labels, or unintended horizontal overflow was observed. A4 whitespace is the configured unused sheet area.

Physical printer output remains the sole hardware-only check: print, measure and scanner-test one sample on each configured device class. Production/shared databases, production runtime, active symlink, deployment, tags, pushes, remotes and existing releases were untouched.
