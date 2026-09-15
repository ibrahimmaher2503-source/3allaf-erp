# Canonical User Stories — UI-Only Retest Report

**Run date:** 2026-08-18 (Africa/Cairo)  
**Overall verdict:** **FAIL — three reproduced UI defects and four prerequisite-blocked stories remain.**  
**Scope:** US-001 through US-032 plus US-046 from `docs/05-user-stories.md`.

## 1. Test Boundary

This was a UI-only Chromium retest. Story observations came from visible browser navigation and controls; no PHPUnit, Pest, direct controller request, direct database assertion, API test, Playwright request-context mutation, or physical-device claim was used as story evidence.

Environment preparation rebuilt only the authorized disposable MariaDB database `toyjoy_phase1_remediation_20260818`, then ran the normal testing seed (`ProductionSeeder` plus `DemoErpSeeder`). Seeding supplied deterministic catalog, purchasing, inventory, customer, price, open-shift, and completed-sale prerequisites. It did not count as UI verification.

| Item | Actual |
|---|---|
| Application | Local Laravel server at `http://127.0.0.1:8832` |
| Database | `toyjoy_phase1_remediation_20260818` only |
| Browser | Headed Chromium |
| Authenticated actor | Bootstrap `admin` System Administrator |
| Desktop viewport | 1280 × 900 |
| Mobile viewport | 390 × 844 |
| Locale checks | English/LTR story run; Arabic/RTL mobile smoke |
| Full run | `UI-US-RETEST-2026-08-18T18-18-04-292Z`; 33 story records; 8.8 minutes |
| Focused confirmation | Product Add, POS context, Party store prerequisite, Page Guide, and Appearance Customizer; 43.1 seconds |
| Browser runtime errors | 0 page errors; 0 console errors |
| Automated backend suites | Not run |
| Database business writes from story actions | No new product, sale, return, Gift Card, quotation, Party booking, approval, inventory document, or settlement was committed |

The browser runner reported one passing audit harness in each execution. That means the evidence collection completed; it does **not** mean all user stories passed.

## 2. Verdict Definitions and Summary

- **PASS:** The story is read-only or its tested UI behavior was sufficiently exercised with legitimate fixture data.
- **PARTIAL:** The UI boundary and meaningful state/data were exercised, but the complete mutation, alternate/failure path, role separation, print, concurrency, or lifecycle was not executed.
- **BLOCKED:** A required legitimate fixture, policy, actor, device, or owner input was absent, so the main UI flow could not start or progress.
- **FAIL:** A visible control or required UI workflow did not work as expected.

Final reviewed classification, including the focused Party prerequisite check:

| PASS | PARTIAL | BLOCKED | FAIL | Total |
|---:|---:|---:|---:|---:|
| 3 | 23 | 4 | 3 | 33 |

## 3. Story-by-Story Results

| Story | Verdict | UI-only result |
|---|---|---|
| US-001 — Govern Company and Operating Masters | PARTIAL | Initial Setup, settings, branches, stores, cash drawers, and authorization screens all returned 200 and showed the seeded governed baseline. No sensitive owner master change was committed. |
| US-002 — Maintain Stable Product Identity | **FAIL** | Product Masters rendered and the direct `/catalog/products/create` full form worked. The visible **Add Product** button did not open the expected `Create product identity` dialog after five seconds, reproduced again in the focused run. |
| US-003 — Maintain a Unique Customer Profile | PARTIAL | The seeded Demo Customer was found through customer search and the full create form rendered. New profile persistence, duplicate-phone resolution, merge, and sensitive-history denial were not executed. |
| US-004 — Import Products Safely | PARTIAL | File upload, create/update mode, staging, review, and approval UI boundaries rendered. No approved workbook was supplied, so mapping, row validation, approval, and rejected-row export were not run. |
| US-005 — Configure Product Types | PARTIAL | The direct full product form showed Standard, Composite, and Service choices. No type was persisted and no post-transaction transition was attempted. |
| US-006 — Capture a Sale Price Without Cost Coupling | PARTIAL | Pricing rendered the seeded demo product and its price/version context. No new proposal was submitted. |
| US-007 — Approve Versioned Prices and Labels | PARTIAL | Pricing approval, active-version, and label-queue screens rendered and an approved version was visible. No fresh two-actor approval or physical label print occurred. |
| US-008 — Perform Authorized Open-Price Sale | **BLOCKED** | POS rendered, but the disposable fixture contains no product with an approved open-price bounds/reason policy. The legitimate override workflow could not be invoked. |
| US-009 — Maintain Supplier History | PARTIAL | The Demo Supplier appeared in the supplier master and supplier-history screens. No master edit or unsafe-delete attempt was committed. |
| US-010 — Manage Purchase Orders | PARTIAL | A real seeded purchase order appeared in its approved state. A new draft-to-close lifecycle was not repeated. |
| US-011 — Receive and Approve a Purchase Invoice | PARTIAL | The seeded approved invoice/receipt rendered. No fresh maker/checker receipt, duplicate invoice, or concurrency retry was submitted. |
| US-012 — Return Stock to a Supplier | PARTIAL | The seeded source-linked supplier return rendered in its approved state. No fresh return or print action was committed. |
| US-013 — View Location Inventory Safely | **PASS** | Scoped balances and immutable movement history for `DEMO-PRODUCT-001` rendered with reconciled seeded quantities. This is a read-only story. |
| US-014 — Transfer Stock Between Stores | PARTIAL | The seeded received DEMO-WAREHOUSE → DEMO-SALES transfer rendered. A new draft/submit/approve/dispatch/receipt sequence was not repeated. |
| US-015 — Record Controlled Inventory Documents | PARTIAL | Adjustment list and create form rendered. No stock-affecting approval or reversal was submitted. |
| US-016 — Count Stock While Selling Continues | PARTIAL | Count list and create form rendered. Concurrent selling, assigned Counter entry, Manager reconciliation, and uncounted-item handling were not run. |
| US-017 — Complete a Branch POS Sale | **FAIL** | POS selected MAIN / MAIN-SALES with no open shift while the legitimate seeded open shift, approved price, and stock belong to DEMO / DEMO-SALES. The Demo Building Blocks card appeared as out of stock and `No approved price`; Add was disabled and checkout could not start. |
| US-018 — Apply Payment, Tax, Discount, and Print Rules | PARTIAL | POS payment/calculation UI and the seeded completed sale/receipt entry points rendered. No second settlement, protected evidence upload, tax/discount approval, or physical print was submitted. |
| US-019 — Issue and Use a Gift Receipt | PARTIAL | Gift Receipt UI rendered. No eligible receipt was issued, reprinted, or consumed because an approved policy/receipt prerequisite was not supplied. |
| US-020 — Return or Exchange Inspected Products | PARTIAL | Return/exchange source-selection and draft controls rendered against the seeded completed sale. No return/exchange was posted. |
| US-021 — Govern Gift Cards | PARTIAL | Gift Card issue/history controls rendered. No financial instrument was issued, redeemed, expired, or voided. |
| US-022 — View Unified History With Separated Wallets | **PASS** | Product Wallet and Party Wallet rendered as distinct ledgers; no cross-wallet transfer control was exposed. This was a read-only separation check. |
| US-023 — Earn and Redeem Shared Loyalty | PARTIAL | The Demo Customer loyalty ledger rendered with the seeded earned entry. Redemption, expiry, and concurrent overdraw were not submitted. |
| US-024 — Open, Operate, and Blind-Close a Shift | PARTIAL | Shift and variance screens rendered against the seeded environment. Blind close was not submitted because closing the shared fixture would prevent the remaining POS checks. |
| US-025 — Book a Party and Maintain Its Working Invoice | **BLOCKED** | Booking list/create UI rendered, but the Party store select contained only `Choose Party store` (one placeholder option). A booking and working invoice could not be created legitimately. |
| US-026 — Record Party Payments and Final Settlement | **BLOCKED** | Party invoice index rendered, but no open Party invoice/payment fixture exists in the named remediation database. |
| US-027 — Execute a Party Operating Order | **BLOCKED** | Party operating-order index rendered, but no confirmed Party booking/order exists for release, completion, consumable issue, or return. |
| US-028 — Govern Rental Asset Lifecycle | PARTIAL | Rental asset workspace and lifecycle controls/language rendered. No asset/booking prerequisite exists in this database, so reservation through inspection was not repeated. |
| US-029 — Assess Asset Damage and Depreciation | PARTIAL | Damage, maintenance, loss, and depreciation UI language rendered. No returned asset or approved cost/depreciation policy was available for a legitimate assessment. |
| US-030 — Create a Non-Posting Quotation | PARTIAL | Quotation create/edit controls and non-posting guidance rendered. No quotation was committed. |
| US-031 — Review Dashboards, Alerts, Reports, and Exports | **PASS** | Dashboard, alerts, report catalog, sales, inventory, purchasing reports, and export center all rendered with seeded workflow data and no browser error. This was a read-only report review; no export job was created. |
| US-032 — Preserve Security, Audit, Integrity, and Safe Offline History | PARTIAL | Audit, approvals, offline readiness, Backup & Restore, and health screens returned 200; a guest was redirected to Login from protected Audit. Offline selling remains unavailable, backup encryption/isolated restore remain blocked, and concurrency/device-loss/append-only guarantees cannot be proven by this UI-only run. |
| US-046 — Customize and Learn the Application Interface | **FAIL** | Page Guide and Appearance Customizer launchers were visible. After clicks and 1.5-second focused waits, neither drawer opened; no `/ui/preferences` response occurred and preference persistence could not be exercised. |

## 4. Confirmed Defects

### DEF-UI-US002-01 — Product Add action is inert

- **Severity:** High / Phase Gate blocker.
- **Reproduction:** Login → Catalog → Products → Add Product.
- **Expected:** `Create product identity` dialog opens.
- **Actual:** No dialog after five seconds; no page/console error. The direct full form route remains usable as a workaround, but the primary list action is broken.

### DEF-UI-US017-01 — POS context prevents the legitimate seeded sale

- **Severity:** High / Phase Gate blocker.
- **Reproduction:** Login as `admin` → POS.
- **Expected:** The active shift/store context exposes the priced, stocked demo product for sale.
- **Actual:** POS selects MAIN / MAIN-SALES with no open shift, while the demo shift/price/stock belongs to DEMO / DEMO-SALES. The product is displayed as unpriced/out of stock and Add is disabled.

### DEF-UI-US046-01 — Page Guide and Appearance Customizer launchers do not open

- **Severity:** High for US-046 acceptance.
- **Reproduction:** Login → Dashboard → Page Guide or Appearance Customizer.
- **Expected:** Corresponding drawer opens; display preference can be saved and survives reload.
- **Actual:** Both launchers are visible but neither drawer opens after a 1.5-second wait. No preference request is sent.

## 5. Responsive and Locale Smoke

At 390 × 844, Dashboard, Product Masters, POS, Reports, and Party Bookings returned 200 with zero measured document-level horizontal overflow. The final Party Bookings page switched to `lang="ar"` and `dir="rtl"`, also with zero document-level horizontal overflow.

This is a representative smoke only. It does not prove every story, nested table, dialog, print, or long bilingual string at both directions and both viewport sizes.

## 6. Blockers and Unverified Evidence

- Party store/master data and Party booking/invoice/order prerequisites are absent from this disposable database.
- No approved open-price product/policy exists.
- No approved import workbook was supplied.
- No physical label, thermal, A4, scanner, printer, or device verification occurred.
- No offline device enrollment or approved offline policy exists; the UI states that offline selling is unavailable.
- Backup encryption, external destination, and isolated restore evidence remain unavailable.
- No full role/scope matrix, maker/checker race, stale concurrent decision, duplicate submission, or cross-scope object test was executed because the owner requested UI-only testing and the required deterministic actors/records were not all present.
- No Production, owner business data, UAT acceptance, release approval, commit, or push was involved.

## 7. Evidence

- Full evidence: `artifacts/all-user-stories-ui-retest-2026-08-18T18-18-04-292Z/`
- Focused evidence: `artifacts/all-user-stories-ui-focused-retest-2026-08-18T18-28-37-809Z/`
- Each folder contains `results.json`; the full folder contains one screenshot per story, and the focused folder contains screenshots for Product Add, POS context, Party prerequisite, and the assistant launchers.
- The `artifacts/` directory is intentionally ignored by Git and contains local-only verification evidence.

## 8. Gate Conclusion

The canonical user-story UI retest is complete as an evidence pass, but the Phase Gate remains **FAIL**. US-002, US-017, and US-046 require repair and focused UI retest. US-008 and US-025 through US-027 require legitimate prerequisites before their main flows can be executed. All PARTIAL stories still need their unexecuted mutation, role/scope, failure, print, concurrency, and lifecycle scenarios before UAT or release acceptance.
