# Rajeh ERP 54-Point Cumulative Implementation Audit

**Audit date:** 2026-09-01  
**Baseline branch:** `hotfix/v0.1.21-readiness-pricing-uat`  
**Baseline commit:** `39719b422e53c6f4b4af9566a4057fdfe646fc60`  
**Baseline tree:** `3c1f2e2c3311f9aeaba141a2f589d89b03c63608`  
**Production tag inspected through Git only:** `v0.1.21-hotfix3`  
**Production release path (identity only; not read or modified):** `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.21-hotfix3-readiness-pricing-20260831T155549Z`

This is the final source-and-verification ledger for the owner-authorized cumulative v0.1.22 stabilization. Historical evidence was reused only after current source regression checks. `F1`–`F9` below identify the nine focused family tests in `tests/Unit/V022RequirementFamiliesTest.php`; the earlier focused suites remain cited where they prove finer contracts.

| # | Status | Originating release / commit | Current implementing files | Direct test / evidence | Visual route |
|---:|:---:|---|---|---|---|
| 1 | PASS | v0.1.16 / `048d161` | `ProductionSeeder.php`; `OpenShiftAction.php`; `PosContext.php`; scoped Retail models/routes | F1 + V016: exact cashier grants, gate and drawer/company/branch/store visibility | `/pos`, `/pos/shifts` (unchanged Hotfix evidence) |
| 2 | PASS | v0.1.16 / `048d161` | `config/navigation.php`; `ApplicationNavigation.php`; `InitialSetupRouteMap.php`; settings/setup views | F2 + V016: common group/order registry and route sequence | sidebar, `/initial-setup`, `/admin/settings` |
| 3 | PASS | v0.1.16 / `048d161` | `SetupContinuation.php`; `RecordSetupDecision.php`; setup context/center views | F2 + V016: skip/defer state, separate navigation, no implicit save | `/initial-setup` |
| 4 | BLOCKED | v0.1.22 / stabilization; hotfix2 corrective | print-template settings; `BarcodeLabelService.php`; canonical print view/routes | F3 + V022 + hotfix2 isolated rendered 50×30/A4 checks and EAN/Code128 decode. Physical output unavailable | `/pricing/labels`; operator must print one test page on each configured device class |
| 5 | FIXED | pre-v0.1.16; v0.1.22 | settings view; `SaveLocalSettingsAction.php`; `PrinterConfiguration.php` | F3 + V022 server scope/compatibility assertions; EN/AR desktop/mobile printer-form captures | `/admin/settings?tab=printers` |
| 6 | FIXED | pre-v0.1.16; v0.1.22 | same as #5; `SavePrintTemplateAction.php`; translations | F3 + V022 exact sizes/type compatibility; locale parse; 4 printer visuals | `/admin/settings?tab=printers` |
| 7 | PASS | v0.1.17–18 / `404367c`, `ee497f0` | staged import actions; `MasterDataDocument.php`; import routes/views | F3 + M51/M52: ordered headers, definitions/examples, UTF-8 CSV/XLSX | customer/supplier/catalog import routes |
| 8 | PASS | v0.1.17–18 / `404367c`, `359c035`, `ee497f0` | catalog/customer exchange routes; PDF views | F3 + M51/M52: gate/scope/filter/bounded export and Cairo RTL PDF contracts | product/category/supplier/brand/customer lists |
| 9 | PASS | v0.1.17 / `404367c` | `CustomerGroup.php`; `GroupHierarchy.php`; groups view/actions | F4 + M51: paths, levels, counts and cycle/self guards | `/customers/groups` |
| 10 | PASS | v0.1.17 / `404367c` | `ImportCustomerGroups.php`; customer exchange routes | F4 + M51: hierarchy-safe upsert, row rejection, no implicit move/delete | customer-group import/export |
| 11 | PASS | v0.1.17 / `404367c` | customer groups view | F4 literal-absence regression check | `/customers/groups` |
| 12 | PASS | v0.1.16–18 / `048d161`…`ee497f0` | shared page header/action components and list views | F2 + V016/M51/M52 permission and standardized action contracts | applicable list routes |
| 13 | PASS | v0.1.17 / `404367c` | customer actions/model/views; `ResidenceGeography.php`; exchange routes | F4 + M51 mandatory geography, dependent active city and exchange fields | `/customers`; customer reports/import/export |
| 14 | PASS | v0.1.17 / `404367c` | governorate/city models; `SaveCityAction.php`; settings route/view | F4 + M51 bilingual, active/order, duplicate/use guards | `/admin/settings/cities` |
| 15 | PASS | v0.1.17 / `404367c` | `GroupHierarchy.php`; customer actions/import/views | F4 + M51 leaf-only UI/action/import enforcement | customer create/edit/import |
| 16 | PASS | v0.1.16 / `048d161` | shared form actions and workflow-specific actions | F2 + V016 command-label/loading contract | applicable forms |
| 17 | PASS | v0.1.17 / `404367c` | `Customer.php`; customer policies/actions/history routes | F1/F4 + M51: company visibility without creator outlet restriction | `/customers/{customer}` |
| 18 | PASS | v0.1.17 / `404367c` | merge action; duplicate handling views/audit | F4 + M51: empty-only transactional archive/audit; unsafe relations rejected | duplicate profile handling |
| 19 | PASS | v0.1.17 / `404367c` | phone normalizer; staged import action/batch/view | F4 + M51: normalized existing/in-file duplicate rejection and idempotency | `/customers/import` |
| 20 | PASS | v0.1.17 / `404367c` | customer forms/show; retained relations/migrations | F4 + M51: UI literal absent; shared structures retained | customer create/show |
| 21 | PASS | v0.1.17 / `404367c` | `SupplierGroup.php`; save action; supplier view | F5 + M51 hierarchy, count, cycle/use guard and responsive cards | `/catalog/suppliers` |
| 22 | PASS | v0.1.17 / `404367c` | `SupplierSettlementMethod.php`; supplier action/view | F5 + M51 exact independent localized values/Other detail | suppliers |
| 23 | PASS | v0.1.16–17 / `048d161`, `404367c` | supplier action/view; setup status | F5 + M51 required settlement/no fake default; optional terms | suppliers/setup |
| 24 | PASS | v0.1.16–17 / `048d161`, `404367c` | `InitialSetupStatus.php`; supplier action/model | F2/F5 + corrected focused readiness boundary proves independence | `/initial-setup` step 17 |
| 25 | PASS | v0.1.18 / `ee497f0` | product-options view; catalog actions/models | F6 + M52 standardized create and contained values contract | product filters |
| 26 | PASS | v0.1.18 / `ee497f0` | navigation/translations/catalog views | F6 + M52 user-facing rename; internal identifiers preserved | catalog |
| 27 | PASS | v0.1.18 / `ee497f0` | products/product-form views; save action | M52 tests plus Hotfix 2 AR/EN create/edit desktop captures | `/catalog/products/create` |
| 28 | PASS | v0.1.18, Hotfix 2 / `ee497f0`, `e14eae0` | barcode policy/action; product form; purchasing scanner | M52 + Hotfix 2 tests; tracked AR/EN product/purchase captures | product create and purchase invoice |
| 29 | PASS | v0.1.18 / `ee497f0` | product form; `SaveProductAction.php`; import action | F6 + M52 identical mandatory UI/action/import validation | product create/import |
| 30 | PASS | v0.1.18 / `ee497f0` | product model/action/form | F6 + M52 optional-field persistence contract | product edit |
| 31 | PASS | v0.1.19–20 / `e0b3759`, `9c0487d` | purchase approval; product/pricing audit | F7/F9 + M52/M54: draft inert, approved fields only, old/new/source audit | purchase approval |
| 32 | PASS | v0.1.16, v0.1.18 / `048d161`, `ee497f0` | setup route map/status; product import route/view | F2/F6 + V016/M52: alternative import, exactly 21 steps, no 22 refs | setup/products import |
| 33 | PASS | v0.1.19 / `e0b3759` | base-list action/model; product price source | F7 + M53 List 0 identity/protection/single-source contract | pricing/product card |
| 34 | PASS | v0.1.19 / `e0b3759` | price-list action/model/views/routes | F7 + M53 generated code, outlet/date/status/round-up contract | `/pricing/lists` |
| 35 | PASS | v0.1.19 / `e0b3759` | branch/store actions/models; resolver | F7 + M53 one active assignment, inheritance/fallback/date/audit | branches/stores/pricing |
| 36 | PASS | v0.1.19 / `e0b3759` | pricing matrix; override action; resolver | F7 + M53 base/pre-round/final/manual exception/effective data | product price matrix |
| 37 | PASS | v0.1.19–20 / `e0b3759`, `9c0487d` | purchase approval; resolver; audit | F7/F9 + M53/M54 explicit base update; percentage recalculation; exceptions preserved | purchase approval/pricing |
| 38 | PASS | v0.1.20 / `9c0487d` | distribution action/model/view; resolver | F7/F9 + M54 quantity-only distribution and outlet price resolution | purchase distribution |
| 39 | PASS | v0.1.19–20; hotfix2 corrective | canonical label service/view; barcode SVG renderer; POS resolver | F7/F9 + M54 plus rendered/decode proof for EAN-13 `4006381333931` ×2 and Code 128 `TESTLOCAL0001` | `/pricing/labels`; POS |
| 40 | PASS | v0.1.19–20 / `e0b3759`, `9c0487d` | override approval/audit; invoice snapshots | F7 + M53 bulk preview/history/immutable invoice/discount order/manual no-round | pricing/history |
| 41 | PASS | v0.1.19, Hotfix 3 / `e0b3759`, `39719b4` | `ProductPricingReadiness.php`; setup/products/pricing views | Hotfix 3 tests and 16 tracked bilingual responsive captures | setup/products/pricing/affected |
| 42 | PASS | v0.1.18 / `ee497f0` | setup route map/status; opening actions/models | F8 + M52 direct route and approved/zero readiness contract | setup step 21 |
| 43 | PASS | v0.1.18 / `ee497f0` | opening models/actions/import/view/routes | F8 + M52 manual/template/import/zero/transaction validation | `/inventory/opening` |
| 44 | PASS | v0.1.20 / `9c0487d` | purchase actions/models/invoices view | M54 stage tests; tracked Hotfix 1 AR/EN desktop/mobile purchase captures | purchase invoices |
| 45 | PASS | v0.1.20, Hotfix 2 / `9c0487d`, `e14eae0` | purchasing invoice Livewire view/query | Hotfix 2 exact/ranked/debounce tests and tracked captures | purchase invoice create |
| 46 | PASS | v0.1.20, Hotfix 2 / `9c0487d`, `e14eae0` | quick Product Card methods and product form | M54/Hotfix 2 focus/leak tests; tracked product/purchase captures | purchase invoice quick create |
| 47 | PASS | v0.1.20–21 / `9c0487d`, `9b054e4` | approval/distribution actions; audit | F9 + M54/final acceptance approved-field and draft-inert contracts | purchase approval |
| 48 | PASS | v0.1.20 / `9c0487d` | distribution model/action/view | F9 + M54 per-line purchased/allocated/remaining matrix | purchase distribution |
| 49 | PASS | v0.1.20 / `9c0487d` | distribution save/approve actions/view | F9 + M54 exact received allocation and precise rejection | purchase distribution |
| 50 | FIXED | v0.1.20; v0.1.22 / `9c0487d`, stabilization | approval action; `StockTransfer`; `000102`; receive action | F9 + revised M54; full migration/FK/unique/rollback check; one transaction | transfers/distribution |
| 51 | PASS | v0.1.20; hotfix2 corrective | destination redirect; canonical pricing label workspace/service/view | Isolated approved-invoice action redirected with exact product/copies prefill; same rendered/decoded symbols | Purchase Invoice → `/pricing/labels` |
| 52 | PASS | v0.1.20–Hotfix 1 / `9c0487d`, `03d5133` | purchasing invoices/destination-label views and translations | Hotfix 1 layout tests and 8 tracked bilingual desktop/mobile captures | purchase list/create/distribution |
| 53 | FIXED | v0.1.20; v0.1.22 / `9c0487d`, stabilization | approval action; transfer dispatch/receipt actions; balances | F9 + revised M54: receiving zero, destination in-transit until receipt, draft inert, atomic | purchasing/inventory |
| 54 | PASS | v0.1.16–Hotfix 3 / `048d161`…`39719b4` | setup route map/status/context/center and linked views | V016 21-step tests; Hotfix 3 tracked AR/EN desktop/mobile setup captures; no `22-step` source reference found | setup routes |

## Defects found and corrected

- Item 4: replaced hard-coded print-template projection with a persisted company-scoped reusable library, bilingual identities, document/paper/language contracts, create/edit/preview/test-print, printer assignment, authorization, audit, and compatibility validation.
- Item 5: added reactive scope changes that clear incompatible branch/store values and branch-filter operational locations; retained server-side visibility and relationship enforcement.
- Item 6: replaced the fake generic label size and A4-only document choice with real 58/80 mm, A4/A5, 50 × 30 mm and 40 × 25 mm contracts, enforced on the server and localized.
- Item 50: replaced direct destination inventory posting with one linked transfer document per destination, preserving dispatch/receipt workflow and transactional rollback.
- Item 53: changed purchase approval to dispatch out of the transit-only receiving warehouse and increase destination `in_transit`; destination on-hand changes only through transfer receipt.
- Migration rollback: reordered removal of the v0.1.22 transfer foreign key and composite unique index after isolated MariaDB exposed index dependency error 1553.
- Verification infrastructure: narrowed an existing v0.1.17 supplier-readiness test to the method body so a following `productPricing` PHPDoc no longer produces a false failure. Product behavior did not change.

## Verification totals and release facts

- Focused PHPUnit: final grouped result recorded after the last assertion correction in `.ai/TEST_RESULTS.md`.
- Locale JSON: Arabic and English files parse successfully.
- Blade compilation: `php artisan view:cache` succeeded; compiled views must be cleared before release packaging.
- Frontend: Node `v20.20.2`, npm `10.8.2`; one Vite production build passed using `/opt/cpanel/ea-nodejs20/bin` exclusively.
- Migration: complete 96-migration chain passed in temporary socket-only MariaDB; v0.1.22 columns, FK, uniqueness, rollback/remigrate passed and the database/server/directory were removed. The preceding `000101` legacy-template preservation and constraints also passed in its isolated run.
- New printing browser matrix: eight Playwright checks passed (Arabic/English × desktop/mobile × library/printer), with correct direction, no overflow/mixed-language leak, and minimum 44 px controls.
- Only item 4 remains BLOCKED, solely for physical-printer output. No production/shared database or symlink target was accessed.

## v0.1.22-hotfix1 post-deployment correction addendum — 2026-09-01

The post-deployment review preserved all 54 outcomes and corrected cross-cutting presentation/data-entry defects found after candidate preparation. Items 4, 25, 39, 41, 44–49 and 52–54 now additionally carry the evidence in `.ai/V0_1_22_HOTFIX1_POST_DEPLOYMENT_EVIDENCE.md`: complete Arabic labels without affected raw enums, unambiguous Setup `Completed` semantics and required-only counters, safe Product Filter deletion/remediation, Inventory Balance filter reset, whole-unit product quantities with monetary decimals retained, a complete manual Barcode Label workspace, exact Product Card List 0 remediation, real EAN-13/Code 128 SVGs, thermal/label/A4 physical template wiring, labeled Purchase Invoice remaining units, and canonical `0.1.22-hotfix1` identity.

The final focused correction gate passed 4 tests / 59 assertions, PHP syntax, locale JSON and diff checks plus the single Node 20 build. The previously completed eight-case bilingual responsive printing matrix was preserved rather than repeated. The matrix remains **49 PASS, 4 FIXED, 1 BLOCKED**; item 4 is blocked only on physical-device print/measure/scan acceptance.
