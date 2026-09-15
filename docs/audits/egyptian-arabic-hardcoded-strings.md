# Egyptian Arabic hardcoded user-facing string audit

- Date: 2026-09-12
- Source baseline: `3ca22dbe6671f94aaec4132b84fb6a2c126cec82`
- Locale: `ar-EG` (`العربية المصرية`)
- Result: **1515 unique candidate source locations remain** across application PHP, routes, JavaScript, and Blade views.
- Completion review: **123 candidates localized at source/render boundaries**; **1,392 intentionally retained** because they are protected product/business data (412), identifiers/codes/formats (367), non-rendered implementation literals (401), or existing `ar`/`en` bilingual branches whose source wording must remain unchanged (212). Egyptian output for the latter is supplied by the separate `ar-EG` catalog; no stored identifier or business value was rewritten.
- This is an audit only. No functional Blade or Livewire copy was mass-extracted or rewritten as part of the audit.

## Method

The bounded static scan records unique `file:line` locations matching at least one of these user-facing-copy indicators:

1. A direct Arabic/English literal branch controlled by `$isArabic`, `$ar`, the current locale, or a passed locale.
2. A literal Latin or Arabic text node in a Blade template.
3. A static `placeholder`, `title`, `aria-label`, `alt`, or `label` attribute in a Blade template.

The raw scans found 582 locale-branch lines, 1030 visible-text lines, and 42 static user-facing attribute lines before deduplicating overlaps. This is a conservative candidate inventory: it can include codes, demo labels, print constants, or text already intentionally bilingual. Each location needs human review before later extraction. Translation calls such as `__()` are not intentionally counted, although dense single-line markup can still produce conservative false positives.

## Candidate locations by file

| File | Candidate locations |
|---|---:|
| `app/Modules/Platform/Support/ApplicationNavigation.php` | 2 |
| `app/Modules/Pricing/Services/BarcodeLabelService.php` | 2 |
| `app/Support/DataExchange/MasterDataDocument.php` | 6 |
| `app/Support/HumanName.php` | 1 |
| `resources/js/dashboard-assistant.js` | 7 |
| `resources/views/catalog/brands.blade.php` | 4 |
| `resources/views/catalog/categories.blade.php` | 11 |
| `resources/views/catalog/lookups.blade.php` | 1 |
| `resources/views/catalog/product-detail.blade.php` | 22 |
| `resources/views/catalog/product-form.blade.php` | 14 |
| `resources/views/catalog/product-import.blade.php` | 20 |
| `resources/views/catalog/product-options.blade.php` | 5 |
| `resources/views/catalog/product-variations.blade.php` | 9 |
| `resources/views/catalog/products.blade.php` | 54 |
| `resources/views/catalog/reference-import.blade.php` | 2 |
| `resources/views/catalog/supplier-import.blade.php` | 5 |
| `resources/views/catalog/suppliers.blade.php` | 111 |
| `resources/views/components/actions/button.blade.php` | 1 |
| `resources/views/components/app-footer.blade.php` | 2 |
| `resources/views/components/app-logo.blade.php` | 2 |
| `resources/views/components/app-navigation.blade.php` | 1 |
| `resources/views/components/assets/actions.blade.php` | 4 |
| `resources/views/components/assets/dashboard.blade.php` | 10 |
| `resources/views/components/desktop-user-menu.blade.php` | 2 |
| `resources/views/components/line-editor.blade.php` | 1 |
| `resources/views/components/money.blade.php` | 1 |
| `resources/views/components/party/dashboard.blade.php` | 9 |
| `resources/views/components/platform/dashboard-tools.blade.php` | 41 |
| `resources/views/components/pricing/dashboard.blade.php` | 10 |
| `resources/views/components/product-line-lookup.blade.php` | 2 |
| `resources/views/components/purchasing/dashboard.blade.php` | 14 |
| `resources/views/components/reports/visual.blade.php` | 1 |
| `resources/views/components/setup/context.blade.php` | 4 |
| `resources/views/components/setup/section-navigator.blade.php` | 2 |
| `resources/views/components/variant-snapshot.blade.php` | 1 |
| `resources/views/dashboard.blade.php` | 18 |
| `resources/views/errors/403.blade.php` | 5 |
| `resources/views/errors/404.blade.php` | 5 |
| `resources/views/errors/419.blade.php` | 5 |
| `resources/views/errors/429.blade.php` | 5 |
| `resources/views/errors/500.blade.php` | 5 |
| `resources/views/errors/503.blade.php` | 4 |
| `resources/views/inventory/count-report.blade.php` | 4 |
| `resources/views/inventory/index.blade.php` | 24 |
| `resources/views/inventory/opening-workflow.blade.php` | 13 |
| `resources/views/inventory/opening.blade.php` | 11 |
| `resources/views/layouts/app/sidebar.blade.php` | 23 |
| `resources/views/layouts/pos.blade.php` | 3 |
| `resources/views/layouts/print.blade.php` | 1 |
| `resources/views/livewire/pos/cart.blade.php` | 6 |
| `resources/views/livewire/pos/checkout-panel.blade.php` | 7 |
| `resources/views/livewire/pos/product-browser.blade.php` | 5 |
| `resources/views/livewire/pos/refund-wizard.blade.php` | 8 |
| `resources/views/pages/admin/customer-loyalty-settings.blade.php` | 1 |
| `resources/views/pages/alerts/index.blade.php` | 2 |
| `resources/views/pages/auth/forgot-password.blade.php` | 1 |
| `resources/views/pages/auth/login.blade.php` | 1 |
| `resources/views/pages/auth/register.blade.php` | 1 |
| `resources/views/pages/auth/two-factor-challenge.blade.php` | 1 |
| `resources/views/pages/customers/create.blade.php` | 20 |
| `resources/views/pages/customers/groups.blade.php` | 8 |
| `resources/views/pages/customers/import.blade.php` | 5 |
| `resources/views/pages/customers/index.blade.php` | 10 |
| `resources/views/pages/customers/loyalty.blade.php` | 6 |
| `resources/views/pages/customers/show.blade.php` | 23 |
| `resources/views/pages/exports/index.blade.php` | 2 |
| `resources/views/pages/exports/master-data-pdf.blade.php` | 5 |
| `resources/views/pages/exports/report-pdf.blade.php` | 7 |
| `resources/views/pages/gift-instruments/card-print.blade.php` | 2 |
| `resources/views/pages/gift-instruments/cards.blade.php` | 5 |
| `resources/views/pages/gift-instruments/index.blade.php` | 9 |
| `resources/views/pages/gift-instruments/print.blade.php` | 1 |
| `resources/views/pages/gift-instruments/show.blade.php` | 9 |
| `resources/views/pages/party/asset-print.blade.php` | 8 |
| `resources/views/pages/party/asset-show.blade.php` | 7 |
| `resources/views/pages/party/assets.blade.php` | 30 |
| `resources/views/pages/party/bookings/create.blade.php` | 5 |
| `resources/views/pages/party/bookings/index.blade.php` | 19 |
| `resources/views/pages/party/bookings/show.blade.php` | 10 |
| `resources/views/pages/party/calendar.blade.php` | 4 |
| `resources/views/pages/party/invoices/index.blade.php` | 4 |
| `resources/views/pages/party/invoices/settle.blade.php` | 3 |
| `resources/views/pages/party/invoices/show.blade.php` | 12 |
| `resources/views/pages/party/orders/index.blade.php` | 7 |
| `resources/views/pages/party/orders/show.blade.php` | 13 |
| `resources/views/pages/party/payments/index.blade.php` | 5 |
| `resources/views/pages/party/print-invoice.blade.php` | 1 |
| `resources/views/pages/party/print-payment.blade.php` | 1 |
| `resources/views/pages/payments/evidence.blade.php` | 2 |
| `resources/views/pages/payments/index.blade.php` | 2 |
| `resources/views/pages/pos/financial-readiness.blade.php` | 4 |
| `resources/views/pages/pos/index.blade.php` | 6 |
| `resources/views/pages/pos/offline-conflict-show.blade.php` | 2 |
| `resources/views/pages/pos/offline-conflicts.blade.php` | 2 |
| `resources/views/pages/pos/offline-queue.blade.php` | 5 |
| `resources/views/pages/pos/offline-readiness.blade.php` | 4 |
| `resources/views/pages/pos/resume.blade.php` | 3 |
| `resources/views/pages/pos/shift-print-a4.blade.php` | 16 |
| `resources/views/pages/pos/shift-print-thermal.blade.php` | 15 |
| `resources/views/pages/pos/shift-variance.blade.php` | 13 |
| `resources/views/pages/pos/shift.blade.php` | 12 |
| `resources/views/pages/pos/suspended.blade.php` | 1 |
| `resources/views/pages/quotations/index.blade.php` | 6 |
| `resources/views/pages/quotations/print.blade.php` | 1 |
| `resources/views/pages/reports/index.blade.php` | 16 |
| `resources/views/pages/returns/index.blade.php` | 10 |
| `resources/views/pages/returns/print.blade.php` | 8 |
| `resources/views/pages/returns/show.blade.php` | 16 |
| `resources/views/pages/sales/index.blade.php` | 30 |
| `resources/views/pages/sales/invoices.blade.php` | 12 |
| `resources/views/pages/sales/print.blade.php` | 8 |
| `resources/views/pages/sales/show.blade.php` | 24 |
| `resources/views/pages/sales/thermal.blade.php` | 12 |
| `resources/views/pages/settings/appearance.blade.php` | 2 |
| `resources/views/pages/settings/two-factor-setup-modal.blade.php` | 3 |
| `resources/views/pages/wallets/ledger.blade.php` | 11 |
| `resources/views/platform/admin/authorization-baseline.blade.php` | 7 |
| `resources/views/platform/admin/branches.blade.php` | 25 |
| `resources/views/platform/admin/cities.blade.php` | 3 |
| `resources/views/platform/admin/drawers.blade.php` | 11 |
| `resources/views/platform/admin/overview.blade.php` | 18 |
| `resources/views/platform/admin/print-template-preview.blade.php` | 5 |
| `resources/views/platform/admin/printer-preview.blade.php` | 6 |
| `resources/views/platform/admin/role-permissions.blade.php` | 18 |
| `resources/views/platform/admin/roles.blade.php` | 7 |
| `resources/views/platform/admin/settings.blade.php` | 39 |
| `resources/views/platform/admin/stores.blade.php` | 23 |
| `resources/views/platform/admin/translation-editor.blade.php` | 1 |
| `resources/views/platform/help/flow.blade.php` | 11 |
| `resources/views/platform/help/screen.blade.php` | 45 |
| `resources/views/platform/initial-setup.blade.php` | 1 |
| `resources/views/platform/system/app.blade.php` | 2 |
| `resources/views/platform/system/approval-inbox.blade.php` | 10 |
| `resources/views/platform/system/audit-log.blade.php` | 27 |
| `resources/views/platform/system/backups.blade.php` | 1 |
| `resources/views/pricing/index.blade.php` | 27 |
| `resources/views/pricing/label-print.blade.php` | 9 |
| `resources/views/pricing/labels/advanced-settings.blade.php` | 1 |
| `resources/views/pricing/lists.blade.php` | 1 |
| `resources/views/pricing/product-matrix.blade.php` | 3 |
| `resources/views/purchasing/history.blade.php` | 18 |
| `resources/views/purchasing/invoice-import.blade.php` | 12 |
| `resources/views/purchasing/invoice-print.blade.php` | 12 |
| `resources/views/purchasing/invoice-readiness.blade.php` | 3 |
| `resources/views/purchasing/invoices.blade.php` | 24 |
| `resources/views/purchasing/orders.blade.php` | 26 |
| `resources/views/purchasing/partials/order-form.blade.php` | 2 |
| `resources/views/purchasing/print.blade.php` | 25 |
| `resources/views/purchasing/return-detail.blade.php` | 5 |
| `resources/views/purchasing/return-print.blade.php` | 12 |
| `resources/views/purchasing/return-settings.blade.php` | 10 |
| `resources/views/purchasing/returns.blade.php` | 13 |
| `routes/pos-hotfix16.php` | 1 |
| `routes/retail.php` | 21 |

## Follow-up boundary

Treat this as a future extraction backlog. Review and move confirmed copy into locale catalogs in small screen-owned batches; preserve all placeholders, HTML, URLs, codes, currency identifiers, and domain terminology. Do not bulk-rewrite Livewire actions, validation, authorization, calculations, queries, or document workflows.
