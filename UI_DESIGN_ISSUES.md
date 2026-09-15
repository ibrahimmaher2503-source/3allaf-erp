# TOY & JOY UI / Design Audit

Date: 2026-08-20 (resumed Luna audit)

## Coverage ledger

- Route registry: 335 total routes; 211 GET/HEAD routes, including 192 unique named GET/HEAD UI candidates after excluding debug, asset, Livewire transport, storage, health, and technical endpoints.
- Source coverage reviewed: route registry, `.ai/UI_SCREENS.md`, shared app/sidebar/POS shells, catalog/product/import views, translation catalogs, prior `docs/06-user-flows-ui-audit-2026-08-18.md`, and prior authenticated audit evidence.
- Fresh authenticated browser evidence: local system-administrator fixture on disposable MariaDB `toyjoy_client_feedback_20260819` at `127.0.0.1:3307`, isolated app on `127.0.0.1:8002`. Arabic RTL dashboard and catalog product screen rendered; the seeded DEMO product exposed the product-table, filter, bulk-selection, and row-action states. No business submission or deletion was made.
- Fresh translation verification: focused `ArabicCatalogTranslationTest` RED (1 assertion failure) then GREEN (1 test / 8 assertions); `lang/ar.json` and `lang/en.json` parse successfully.
- Browser limitation: the in-app browser’s authenticated cookie/session was not retained across every direct route navigation in this resumed run; protected route families therefore redirected to `/login` after the first authenticated screen. Existing authenticated evidence in the session log is carried forward, but no new PASS is claimed for screens not directly observed here.

## Non-translation UI/design issues

### UI-RTL-001 — Product creation has no recoverable localized error state

Screen/Route: Catalog Products (`/catalog/products` → Add Product)
Component: Product identity editor / Livewire modal
Severity: Critical
Problem: The prior audit observed the product-editor interaction returning HTTP 500 without a recoverable operator-facing state.
Expected: The modal remains usable and presents a localized validation or recovery message without losing entered values.
Current: Product setup cannot reliably start from the quick editor; prior evidence was carried forward and not freshly reproduced in this resumed pass.
Suggested Fix: Trace the Livewire action failure and return a localized inline error state while preserving form state.
Relevant Files:
- `resources/views/catalog/products.blade.php`
- Product identity Livewire action/component

### UI-RTL-002 — POS operating context contradicts shift authorization

Screen/Route: `/pos`, `/pos/shift`, `/pos/suspended`
Component: Shared POS context and shift header
Severity: Critical
Problem: Prior evidence showed inconsistent selling-store/shift context for the same authenticated administrator.
Expected: The visible branch, selling store, drawer, shift, and readiness state come from one authorized context resolver.
Current: Cashier lifecycle and sales preparation remained blocked in the prior audit; not freshly reproduced here.
Suggested Fix: Reuse one shift-derived POS context for header, readiness, and checkout authorization.
Relevant Files:
- `app/Modules/Retail/Support/PosContextResolver.php`
- `resources/views/pages/pos/index.blade.php`

### UI-RTL-003 — Master-data controls lack stable accessible label associations

Screen/Route: `/catalog/categories` and reusable master-data forms
Component: Flux inputs/selects and validation messages
Severity: High
Problem: Prior evidence found visible captions not consistently associated with controls through stable `id`/`for`, wrapping labels, or accessible names.
Expected: Every control has a deterministic accessible name and validation message association in Arabic RTL and English LTR.
Current: Keyboard and assistive-technology identification is inconsistent; not freshly reproduced here.
Suggested Fix: Add explicit labels/IDs or use the existing Flux label contract consistently.
Relevant Files:
- `resources/views/catalog/categories.blade.php`
- Shared master-data form components

### UI-RTL-004 — Validation exposes internal Livewire state paths

Screen/Route: `/catalog/categories` empty-submit validation
Component: Nested category form validation
Severity: High
Problem: Prior evidence showed messages such as `category form.code` instead of human-readable field labels.
Expected: Validation identifies the affected bilingual field in operator language.
Current: Implementation-oriented state paths were visible in the prior browser audit; not freshly reproduced here.
Suggested Fix: Define human-readable attribute names and map nested validation paths before rendering errors.
Relevant Files:
- `resources/views/catalog/categories.blade.php`
- Category Livewire form component

### UI-RTL-005 — POS mobile touch targets are below the project guideline

Screen/Route: `/pos` at mobile width
Component: Compact POS controls
Severity: Medium
Problem: Prior evidence found at least one control below the project’s 44px minimum touch target in one dimension.
Expected: Barcode-first POS controls remain reliably tappable at supported mobile widths.
Current: Compact controls can be missed during fast checkout; not freshly reproduced here.
Suggested Fix: Bring affected controls to the existing minimum target without changing POS workflow semantics.
Relevant Files:
- `resources/views/pages/pos/index.blade.php`
- `resources/views/livewire/pos/product-browser.blade.php`

### UI-RTL-006 — Reports empty workspace repeats source headings

Screen/Route: `/reports` on mobile
Component: Empty report-source sections
Severity: Medium
Problem: Prior evidence found repeated full sections for empty sources instead of a concise grouped state.
Expected: Empty reports explain the current scope once and provide clear next actions without excessive page length.
Current: Repetition increases scanning cost in Arabic RTL mobile use; not freshly reproduced here.
Suggested Fix: Group empty sources under one bounded state panel while preserving permission-aware links.
Relevant Files:
- `resources/views/pages/reports/index.blade.php`

### UI-AUTH-007 — Role creation and role-permission editing controls are missing

Screen/Route: Access management; implemented route `/admin/authorization-baseline`; specified routes `/admin/roles` and `/admin/roles/{role}/permissions`
Component: Authorization Baseline Livewire page, sidebar Access entry, role/permission matrix
Severity: High
Problem: An authorized administrator can create users and assign existing canonical roles, but cannot add a role or edit the permissions mapped to a role. No matching control, Livewire action, or registered role/permission-management route exists.
Steps to Reproduce: Sign in as an active System Administrator, open the Access management entry, inspect the page toolbar and each user action, then attempt direct navigation to `/admin/roles` and `/admin/roles/{role}/permissions`.
Expected: The approved Roles screen and Permissions matrix expose permission-gated role maintenance and role-permission editing, with server authorization, validation, and audit protection.
Current (implementation updated 2026-08-20): `/admin/roles` and `/admin/roles/{role}/permissions` now exist as separately guarded Livewire screens. The Authorization Baseline and sidebar link to role management; authorized administrators can create/edit local roles and map active, non-sensitive permissions with audit events, while canonical roles and sensitive grants remain read-only. Focused MariaDB verification passes, but this issue remains open pending the required headed-browser RTL/LTR and mobile verification because the in-app browser URL policy blocked the local server after its initial connection failure.
Suggested Fix: Implement the smallest dedicated role list/editor and role-permission matrix on the specified routes, reusing the existing `Role`, `Permission`, gates, Flux patterns, and audit action. Keep canonical-role safety and owner-approved grant boundaries explicit; do not overload the user-assignment modal.
Relevant Files:
- `routes/platform.php`
- `resources/views/platform/admin/authorization-baseline.blade.php`
- `resources/views/layouts/app/sidebar.blade.php`
- `app/Modules/Platform/Models/Role.php`
- `app/Modules/Platform/Models/Permission.php`
- `app/Providers/AppServiceProvider.php`
- `.ai/UI_SCREENS.md`
- `TASKS.md`

## Translation findings and remediation

The prior authenticated Arabic pass recorded three translation clusters: customer-create English-only identity/helper copy; mixed catalog/import prose and controls; and raw settings/readiness keys. Customer/settings localization was already repaired by concurrent repository work. This resumed pass repaired the remaining shared catalog labels that were visibly mixed or malformed in Arabic: `All genders`, `All product types`, `Filter colour`, `Filter character`, `Full product card`, `Product catalog`, `Product Masters`, and `View details`. Technical import column names (`item_code`, `name_ar`, etc.) remain intentionally technical identifiers and were not translated.

## Routes not freshly inspected in this resumed run

- Protected route families (exact registry groups): `/admin/*`, `/catalog/*` beyond the product screen, `/customers/*`, `/purchasing/*`, `/inventory/*`, `/pos/*`, `/parties/*`, `/party/*`, `/pricing/*`, `/reports*`, `/sales*`, `/returns*`, `/gift-*`, `/wallets/*`, `/offline/*`, `/payments*`, `/approvals*`, `/exports*`, `/settings/*`, `/initial-setup`, `/notifications`, and `/system/*`.
- Reason: after the first authenticated screen, direct navigation in the in-app browser repeatedly redirected to `/login`; the browser session could not be retained reliably across the full route batch. Prior authenticated evidence for affected settings, setup, suppliers, customers, reports-readiness, and POS mobile screens is retained in `.ai/SESSION_SUMMARY.md` and earlier audit evidence but is not recounted as fresh evidence here.
- Technical/non-UI routes excluded by design: debugbar assets, Flux assets, Livewire transport endpoints, local storage delivery, passkey option JSON, and `/up` health probe.

## Audit scope boundary

No layout, CSS, component structure, business logic, database logic, API, or destructive action was changed by this resumed audit. Only Arabic translation values, one duplicate translation entry removal, the focused translation test, and this audit ledger were changed.
