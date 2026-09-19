# Pending Change Batch

## 2026-09-19 — GitHub source sync

- The local changes recorded below were included in source commit `5d63f62919cbcb4c69f5e8d7347f0b9333217ed4` and pushed to `origin/codex/r1-sales-reports`. They remain undeployed; earlier `uncommitted` labels record their status when each entry was written.

## 2026-09-19 — Make selling-price change discoverable (V0 / undeployed)

- **Source commit:** `e663d786fc96101173e1630cbc187ccb56e0cfcf`; uncommitted.
- Reworded `/pricing/versions` as selling-price changes and approvals, with direct draft/approval guidance, clearer action/form/table labels, and an explicit pointer to the change-price button on `/pricing/workspace`.
- **File:** `resources/views/pricing/index.blade.php`.
- **Verification:** Blade cache compilation and diff hygiene passed. No pricing logic, database, browser check, commit, push, or release.

## 2026-09-19 — Open Basic Data within administration (V1 / undeployed)

- **Source commit:** `e663d786fc96101173e1630cbc187ccb56e0cfcf`; uncommitted.
- Basic Data expands when Administration & settings opens, including when restored sidebar preferences had it collapsed; users can still collapse it manually.
- **Files:** `resources/views/components/app-navigation.blade.php`, `resources/js/sidebar-navigation-state.js`.
- **Verification:** Blade cache compilation, JavaScript syntax, Vite build, and diff hygiene passed. Browser control not authorized; no permissions, routes, data, commit, push, or release.

## 2026-09-18 — Customer wallet clarity

- **V0 / undeployed / uncommitted:** Clear customer sales-wallet title and independent-ledger purpose; primary account/collection link distinguishes receivables. Compact setup warning with diagnostic details disclosure; removed unrelated Party wallet link from product view and duplicate setup controls. Three-column summary, two-decimal money display, clarified Arabic balance/history/export/empty labels; no empty export button.
- **Files:** `resources/views/pages/wallets/ledger.blade.php`, `lang/ar.json`, `lang/ar-EG.json`.
- **Verification:** Locale JSON parse, Blade compilation/PHP syntax and diff hygiene passed. Browser acceptance unverified. No policy activation, query, data, tests, build, commit, push or release changes.

## 2026-09-18 — Dynamic KG/TON report display

- **V1 / undeployed / uncommitted:** Product summary/detail quantities remove trailing zeros and group thousands; known KG quantities at absolute 1000 or above display TON using exact decimal arithmetic. Original unit column and historical unit price remain unchanged, with explanatory note. Unit price uses existing two-decimal currency display. Unknown/BAG units not converted; export unchanged.
- **File:** `resources/views/pages/reports/sales-by-product.blade.php`.
- **Verification:** Manual formatter examples, Blade compilation/PHP syntax and diff hygiene passed. Browser acceptance not performed. No tests, queries, writes, build, commit, push or release.

## 2026-09-18 — Sales returns translation/navigation/form clarity

- **V0 / undeployed / uncommitted:** Added returns.index under Sales using existing returns.view permission; corrected mixed Arabic literal labels in ar using existing Egyptian Arabic copy and clarified source/empty/settlement wording in both locales. Grouped source/item and inspection/settlement controls, full-width reason, draft side-effect warning, register header and translated settlement labels. Existing form fields/actions preserved.
- **Files:** `config/navigation.php`, `resources/views/pages/returns/index.blade.php`, `lang/ar.json`, `lang/ar-EG.json`.
- **Verification:** PHP, compiled Blade syntax, locale JSON parse and diff hygiene passed. Browser acceptance not performed; no tests, queries, writes, builds, commit, push or release.

## 2026-09-18 — Readable top-selling quantities

- **V0 / undeployed / uncommitted:** Reused ProductQuantity::format to remove trailing decimal zeros, grouped whole-number digits without float conversion, retained meaningful fractions, and clarified sold base quantity instead of generic units. No inferred KG/TON conversion.
- **File:** `resources/views/pages/sales/index.blade.php`.
- **Verification:** Blade compilation/PHP syntax and diff hygiene passed. Browser acceptance not performed. No queries, data writes, tests, build, commit, push or release.

## 2026-09-18 — Translate product detail removal controls

- **V0 / undeployed / uncommitted:** Added 10 missing product detail archive/delete labels, confirmation/warning and success translations to ar/ar-EG, with matching en keys. Preserved placeholders and irreversible-action warnings. No behavior/data changes.
- **Files:** `lang/ar.json`, `lang/ar-EG.json`, `lang/en.json`.
- **Verification:** Locale JSON parse, literal-key coverage (zero missing ar/ar-EG), Blade compilation/PHP syntax and diff hygiene passed. Browser acceptance not performed; no tests, database writes, build, commit, push or release.

## 2026-09-18 — Feed-relevant product detail attributes

- **V0 / undeployed / uncommitted:** Removed colour, size, character, target age, gender and dimensions from static detail attribute list; retained weight with unit and bilingual search keywords. Removed unrelated variants/balances badge and renamed section.
- **File:** `resources/views/catalog/product-detail.blade.php`.
- **Verification:** Blade compilation and diff hygiene passed. No dynamic attribute rows exist behind this list; database/schema untouched. Browser acceptance unverified; no tests, build, commit, push or release.

## 2026-09-18 — Remove topbar store selector

- **V0 / undeployed / uncommitted:** Removed shared topbar store dropdown/single-store chip markup rather than relying on CSS hiding. Sidebar company context, work-context state, routes and authorization unchanged.
- **File:** `resources/views/layouts/app/sidebar.blade.php`.
- **Verification:** Blade compilation and diff hygiene passed; browser visual acceptance not performed. No tests, data writes, build, commit, push or release.

## 2026-09-18 — Product report details navigation

- **V1 / undeployed / uncommitted:** Details GET links preserve filters and selected product/unit, reset detail pagination and land on product-detail anchor below long summary. Added back-to-summary link, anchor scroll margin and aria-controls. No backend/query/financial changes.
- **File:** `resources/views/pages/reports/sales-by-product.blade.php`.
- **Verification:** Local reporting query for product 3 / unit 7 returned 2 detail lines. Blade compile/PHP syntax and diff hygiene passed. No browser control/UAT, automated tests, build, commit, push or release.

## 2026-09-18 — Inventory filter layout and movement context

- **V0 / undeployed / uncommitted:** Responsive filter grid with separate wrapping actions; movement ledger displays product/location names and codes, signed base-unit quantities and textual stock direction, source type/internal document ID, movement/reversal IDs, creator and posted date/time. Existing eager-loaded data only, no query/domain changes.
- **File:** `resources/views/inventory/index.blade.php`.
- **Verification:** Blade compilation/PHP syntax and diff hygiene passed. Browser/RTL/LTR/mobile acceptance unverified. No tests, data writes, build, commit, push or release.

## 2026-09-18 — Clarify pricing versions

- **V0 / undeployed / uncommitted:** Mode-specific pricing titles, removed duplicate title panel, explained proposal/review/approval and validity, labeled responsive filters, separated validity dates from version identity, added per-state explanation and explicit submit/approve/compare labels. Reject controls sit inside a native disclosure separate from approval.
- **File:** `resources/views/pricing/index.blade.php`.
- **Verification:** Affected compiled Blade syntax and diff hygiene passed, with existing Blaze compilation warnings. Browser acceptance not performed; no queries/actions/permissions/calculations, tests, database writes, build, commit, push or release changed.

## 2026-09-18 — Visible inventory form fields

- **V0 / undeployed / uncommitted:** Added the missing border width, explicit surface/text colors, padding and minimum height to the existing shared native inventory field classes. Covers selectors, quantity/cost inputs, filters and reason textareas including opening inventory.
- **File:** `resources/views/inventory/index.blade.php`.
- **Verification:** Affected Blade syntax/compilation and diff hygiene; no browser acceptance, build, tests, data writes, commit, push or release.

## 2026-09-18 — Distinguish inventory registers

- **V0 / undeployed / uncommitted:** Clarified Balances as read-only recorded quantities, Counts as physical-count/review sessions, and Transfers as source/destination dispatch/receipt documents. Removed irrelevant global filters/summary/ledger from focused registers and adjustment documents from Counts; retained overview and direct workflow pages.
- **File:** `resources/views/inventory/index.blade.php`.
- **Verification:** Affected Blade compilation/lint and diff hygiene passed. Browser acceptance not performed, no build or release. No queries, routes, domain actions or permissions changed.

## 2026-09-18 — Customers sidebar destination

- **V0 / undeployed / uncommitted:** Added existing `customers.index` destination beside Customer groups in Sales & POS, with unchanged `customers.view` permission and bilingual labels.
- **File:** `config/navigation.php`.
- **Verification:** Navigation PHP syntax and diff hygiene passed. No browser, automated tests, build, database writes, release, commit or push.

## 2026-09-18 — Sidebar accent preferences

- **V0 / undeployed / uncommitted:** Fixed hardcoded teal selection/focus/context-dot colors to follow the existing chosen accent; navy sidebar background preserved.
- **Files:** `resources/views/layouts/app/sidebar.blade.php`, `resources/css/app.css`.
- **Verification:** Diff hygiene and affected Blade compilation passed. Browser acceptance not performed; no asset build, release, commit, or push.

## 2026-09-14 — Hotfix39 Customer Notes Batch 3

- **I4 / release candidate / undeployed:** Compact product/customer/supplier masters, incomplete-product Draft/Resume, supplier item identity, dynamic multi-identifier lookup, branch-priced product matrix reuse, opening-inventory header/keyboard grid/paste/XLSX/totals/draft/post/reversal/zero workflow, stable customer code, loyalty help, and structured supplier contacts.
- Exact baseline is Hotfix38 `45d9e78c757cd83a596b11d613475dbdc441d2c3`. Backend export routes/services, stored supplier payment terms, existing pricing contracts, tenant permissions, and historical accounting/inventory records remain intact.
- Activation must prove 000111 and 000112 are already `Ran`, execute only migration 000113, and retain its nullable backward-compatible additions if exact Hotfix38 application rollback occurs.

## 2026-09-14 — Hotfix38 Customer Notes Batch 2

- **I4 / release candidate / undeployed:** Accessible payment/tax modals; UI-hidden tax validity dates; nullable product tax assignment; branch/document continuous numbering; consolidated printer/template management with 15 document types and an accurate relevant-printer count.
- Exact baseline is Hotfix37 `0f0e7aa18d47986f6805c89b3494f4f55042e151`. The only schema change is forward-compatible migration 000112. Historical invoice tax snapshots and accounting calculations are untouched.
- Activation must run exactly migration 000112 after proving 000111 is already `Ran`. Application rollback restores exact Hotfix37 and deliberately retains the nullable schema addition.

## 2026-09-14 — Hotfix37 Customer Notes Batch 1

- **V1 / release candidate / undeployed:** Reusable accessible `!` contextual help; sidebar hover/focus/active/scroll/state/keyboard repair; duplicate-destination suppression and requested supplier/customer/product ordering; branch-screen action/timezone cleanup; company timezone and persisted default list-filter visibility in General Settings; user/role total-card removal with unchanged authorization behavior.
- Exact baseline is Hotfix36 `c5f6d57150a04d95915a72f5146a2aebd369bc83`. No migration or business-data change is included. Optimized unexecuted promotion/activation scripts are restricted to that baseline and exact rollback target.

**Status:** HOTFIX19 CORRECTIVE CANDIDATE — VERIFIED — NOT DEPLOYED
**Scope:** v0.1.22-hotfix19 POS Livewire root and extracted-release cache repair, retaining all Hotfix17/18 work
**Opened after:** operator-verified rollback to exact `v0.1.22-hotfix16`
**Source baseline:** exact Hotfix18 commit `c0fa3ed3623f7c259ba2c29109c08184bfa42eb4`
**Production baseline:** owner-confirmed: exact Hotfix16 commit `31883f5c823458d30738708ec96e1d02337f4c9b`, migration 000111 already Ran, maintenance OFF.
**Baseline disposition:** Hotfix16 remains active and is the exact Hotfix19 activation prerequisite and rollback target. It was not rechecked.

Do not clear or archive this ledger until an authorized operator confirms successful Hotfix19 production activation and smoke verification.

## 2026-09-18 — Cross-screen Arabic/UI cleanup

- **V0 / locally verified / undeployed:** Removed visible currency inputs from feed-store operation forms while retaining hidden validated values, hid the company selector when exactly one authorized company exists, and removed currency codes from account option labels.
- The one active branch-primary selling store is selected automatically for customer collection. The existing audited branch/store mapping UI is available again in store administration, labels the active mapping as primary, and opens a confirmation modal before any change.
- The sales overview now explains that daily cards use `approved_at`, reports the count/latest approval when today is zero, and shows approval dates in recent rows. Gift-receipt, alert, supplier-payables, and supplier-action copy is localized and clarified.
- Authenticated browser verification passed on 8139 in Arabic and English for `/sales`, `/gift-receipts?sale_id=150`, `/alerts`, `/catalog/suppliers`, `/feed-store/operations`, and `/admin/stores`. The primary-store modal was opened and cancelled without saving. No financial form, mapping change, production action, commit, or push occurred.

## Pending Changes

### 2026-09-18 — Hide Party UI (V0 / undeployed / verification partial)

- Disabled the rental-assets navigation group (Parties already disabled), removed Party/Rental report links, KPI/source/detail/chart presentation, customer Party Wallet links/cards and booking history, and Party/Rental permission groups/options. Party/Rental export jobs are hidden from the current paginated view; pagination remains based on the unchanged backend query.
- Reworded welcome copy for retail/customer/inventory scope with Arabic translations. Files: `config/navigation.php`, customer/report/export/welcome views, role-permissions view, `lang/ar.json`, `lang/ar-EG.json`.
- Actual authenticated Arabic browser PASS: sidebar, navigation search for حفلات (no results), `/reports` after detail filtering, `/customers/40`. Permission screen initially exposed a Collection error; corrected to `reject`, but recheck is BLOCKED because 8139 now refuses connections. Final chart/copy/export rendering remains unverified. Routes, permissions, backend, and data remain intact; direct Party URLs are not disabled. No tests, commit, push, or deployment.

| Classification | Short description | Commit | Affected files | Status |
|---|---|---|---|---|
| V1/D3 | Full Hotfix17 compact POS, checkout, customer/product/scanner, order-tab and payment-card redesign | Retained in Hotfix19 | Hotfix17 POS Livewire/components/views | VERIFIED — NOT DEPLOYED |
| D3/I4 | Full Hotfix17 source-linked refund, inventory/payment/accounting/authorization/audit/idempotency and receipt workflow | Retained in Hotfix19 | Migration 000111; return action/calculator/models/components/views/routes | VERIFIED — NOT DEPLOYED |
| V1/D3 | Hotfix18 Blade/PHP compiler repairs without UI or authorization bypass | Retained in Hotfix19 | POS page/cart/checkout and purchasing-return print Blade templates | VERIFIED — NOT DEPLOYED |
| V1 | Permanent closed-state Livewire root for the retained refund wizard | Hotfix19 release commit | `resources/views/livewire/pos/refund-wizard.blade.php` | VERIFIED — NOT DEPLOYED |
| I4 | Post-extraction bootstrap cache creation, recursive `rajeh:rajeh` ownership, mode `0775`, pre/post cache-generation writability, application-user Artisan, already-Ran 000111 preservation and exact Hotfix16 rollback | Hotfix19 operator scripts | Promotion and activation scripts | VERIFIED — ACTIVATION PREFLIGHT DEFERRED |

Production access, deployment, backup execution, migration execution, active-symlink modification, service operation, browser testing, tag, push, and remote contact are outside candidate preparation and remain unexecuted.

### 2026-09-18 — 3allaf branding UI (V0 / undeployed)

- Changed the visible application brand from TOY & JOY to `3allaf | علاف` across app fallbacks, the authenticated sidebar, auth/print/error shells, welcome copy, translation values, admin settings placeholders, quotation/report printing, exports, and retained direct Party print headers.
- Kept internal identifiers, storage paths, route names, database structures, and business behavior unchanged.
- Verification: `php artisan view:clear`, locale JSON parsing, `git diff --check`, and local `GET /login` (HTTP 200 with `3allaf`) passed. No automated tests, commit, push, deployment, or database change.

### 2026-09-18 — Clarify purchase-invoice reversal (V0 / undeployed)

- Renamed the Arabic transition heading/action from the ambiguous `عكس` to `إلغاء فاتورة الشراء وعكس آثارها`, and explained that the approved invoice's stock receipt and previous product costs are restored; the operation is audited and irreversible from this screen.
- No action, authorization, accounting, inventory, or database behavior changed. `view:clear`, locale JSON parsing, and `git diff --check` passed.

### 2026-09-18 — Hide top-bar work-context selector (V0 / undeployed)

- Hid the top-bar `موقع العمل الحالي / كل المتاجر المصرح بها` control from the visible UI. Existing scope calculation and internal work-context behavior remain unchanged.
- No routes, permissions, queries, or database values changed.

### 2026-09-18 — Supplier-return button availability (V0 / undeployed)

- The button was rendered disabled whenever the active supplier-return reason catalog was empty; the local database currently has zero active reasons. It now opens the draft form and explains the prerequisite, with a direct link to Return Settings, instead of appearing clickable but doing nothing.
- Save validation and the required active-reason business rule remain unchanged. No data was seeded or modified.

### 2026-09-18 — Dashboard cold-cache query-budget fix (V0 / undeployed)

- Request `aed5fa57-6ec8-41c3-8e29-d361d2c5927e` was traced to the dashboard query guard aborting at 101 queries while the first uncached setup-readiness snapshot was built.
- Removed repeated readiness queries (customer/supplier groups, product options, supplier readiness, price readiness, consent policy, and multiple count/exists pairs). Cold-cache snapshot plus dashboard now measured 86 queries in the isolated local runtime; warm cache is lower.
- No business rules or data changed. No automated suite, commit, push, deployment, or release.
## 2026-09-17 — Feed-store operations Arabic UI review

- **V0 / undeployed:** Wrapped the feed-store operations screen in the common application shell, corrected Arabic/Egyptian and English labels, localized customer types and master names, improved allocation guidance, bounded long activity lists, and aligned numeric amounts for RTL reading.
- **Usability refinement:** Added a sticky permission-aware operation jump bar, stable section anchors, and collapsed optional references/notes behind accessible native details so the first-use form is shorter without removing any field.
- **Customer-receipt clarification:** Marked currency as automatic, explained the cash account, required customer/store/amount before enabling invoice allocation, renamed the save action, and added `formnovalidate` so allocation preview is not blocked by unrelated payment fields.
- No routes, controllers, models, permissions, validation, database, or financial behavior changed. Keep this with the pending UI batch; no release, commit, push, or deployment was performed.

## 2026-09-19 — Purchase-order price readability (V0 / undeployed)

- Removed redundant trailing decimal zeros from the visible purchase-unit price input (for example, `38000.0000` now displays as `38000`) while preserving the existing four-decimal input precision, Livewire value, calculations, validation, and saved data.
- Purchase-order details now render the same purchase-unit price as a grouped financial amount with two decimals (for example, `38,000.00`).
- No controller, action, model, query, permission, database, or purchasing workflow behavior changed.

## 2026-09-19 — Remove purchase-invoice draft warning (V0 / undeployed)

- Removed the owner-selected informational warning above the purchase-invoice filters. Approval, posting, inventory, WAC, audit, and sale-price behavior remain unchanged.

## 2026-09-19 — Remove product type/colour column (V0 / undeployed)

- Removed the `Type` table column and its `Standard / No colour` cell from `/catalog/products`. Product type and colour data, forms, filters, imports, validation, and stored records remain unchanged.
- The catalog Blade view compiled and `git diff --check` passed. Browser control was not authorized for this active task, so the exact page was not reloaded by the agent.

## 2026-09-19 — Unpriced-products empty-state translation (V0 / undeployed)

- Replaced the mixed Arabic/English empty-state text on `/pricing/unpriced` with clear Arabic and Egyptian Arabic wording referring to available selling outlets. English source copy and pricing behavior remain unchanged.
- Both locale JSON files parsed, the pricing Blade view compiled, and `git diff --check` passed. Browser control was not authorized for this active task.

## 2026-09-12 — Egyptian Arabic locale completion batch

- **V0 / completed locally / undeployed:** Rewrote all 6,115 `ar-EG` JSON values and 244 PHP locale leaves, added contextual Egyptian render wording and human-readable source labels, and preserved `ar`/`en`. Commit recorded after final verification. No deployment or packaging.

## 2026-09-13 — Hotfix36 Egyptian Arabic quality-repair batch

- **V0 / release candidate / undeployed:** Integrated the direct-child localization repair onto exact Hotfix35, retaining all 6,115 JSON keys, 244 PHP leaves, unchanged `ar`/`en`, and unchanged functional boundaries. Offline package and fail-closed exact-Hotfix35 operator workflow prepared; no production or operator action executed.
