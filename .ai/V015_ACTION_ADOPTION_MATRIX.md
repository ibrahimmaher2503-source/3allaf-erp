# v0.1.15 Semantic Action Adoption Matrix

Authoritative inventory: **79** Blade table/list views inherited from `.ai/V014_TABLE_ACTION_INVENTORY.md`. Status totals: **10 visually represented**, **36 mechanically converted**, **23 with no row actions**, and **10 intentionally excluded**. The dedicated POS cart remains outside this 79-view inventory because its barcode-first selling controls are not a management table.

| View | Status | Basis |
|---|---|---|
| `resources/views/catalog/brands.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/catalog/categories.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/catalog/product-import.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/catalog/products.blade.php` | Converted and visually represented | Shared semantic actions/table treatment verified in the genuine browser set. |
| `resources/views/catalog/product-variations.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/catalog/suppliers.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/components/data-table.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/components/reports/visual.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/components/tables/data-panel.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/components/tables/filter-bar.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/components/tables/resource-toolbar.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/components/tables/table-shell.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/inventory/index.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/alerts/index.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/customers/groups.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/customers/import.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/customers/index.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/customers/loyalty.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/customers/show.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/pages/exports/index.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/exports/report-pdf.blade.php` | Intentionally excluded | Print/PDF/export document layout; management actions are inappropriate. |
| `resources/views/pages/gift-instruments/cards.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/gift-instruments/index.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/gift-instruments/show.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/pages/party/asset-print.blade.php` | Intentionally excluded | Print/PDF/export document layout; management actions are inappropriate. |
| `resources/views/pages/party/assets.blade.php` | Converted and visually represented | Shared semantic actions/table treatment verified in the genuine browser set. |
| `resources/views/pages/party/bookings/index.blade.php` | Converted and visually represented | Shared semantic actions/table treatment verified in the genuine browser set. |
| `resources/views/pages/party/bookings/show.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/pages/party/calendar.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/pages/party/invoices/index.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/party/invoices/show.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/pages/party/orders/index.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/party/orders/show.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/pages/party/payments/index.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/party/print-invoice.blade.php` | Intentionally excluded | Print/PDF/export document layout; management actions are inappropriate. |
| `resources/views/pages/payments/evidence.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/payments/index.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/pos/offline-conflicts.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/pos/offline-queue.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/pages/pos/shift.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/pos/shift-print-a4.blade.php` | Intentionally excluded | Print/PDF/export document layout; management actions are inappropriate. |
| `resources/views/pages/pos/shift-variance.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/pos/suspended.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/quotations/index.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/quotations/print.blade.php` | Intentionally excluded | Print/PDF/export document layout; management actions are inappropriate. |
| `resources/views/pages/reports/index.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/pages/returns/index.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/returns/print.blade.php` | Intentionally excluded | Print/PDF/export document layout; management actions are inappropriate. |
| `resources/views/pages/returns/show.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/pages/sales/index.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pages/sales/invoices.blade.php` | Converted and visually represented | Shared semantic actions/table treatment verified in the genuine browser set. |
| `resources/views/pages/sales/print.blade.php` | Intentionally excluded | Print/PDF/export document layout; management actions are inappropriate. |
| `resources/views/pages/sales/show.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/pages/wallets/ledger.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/platform/admin/authorization-baseline.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/platform/admin/branches.blade.php` | Converted and visually represented | Shared semantic actions/table treatment verified in the genuine browser set. |
| `resources/views/platform/admin/drawers.blade.php` | Converted and visually represented | Shared semantic actions/table treatment verified in the genuine browser set. |
| `resources/views/platform/admin/role-permissions.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/platform/admin/roles.blade.php` | Converted and visually represented | Shared semantic actions/table treatment verified in the genuine browser set. |
| `resources/views/platform/admin/settings.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/platform/admin/stores.blade.php` | Converted and visually represented | Shared semantic actions/table treatment verified in the genuine browser set. |
| `resources/views/platform/admin/translation-editor.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/platform/system/approval-inbox.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/platform/system/audit-log.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/platform/system/backups.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/platform/system/health.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/platform/system/ui-showcase.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/pricing/index.blade.php` | Converted and visually represented | Shared semantic actions/table treatment verified in the genuine browser set. |
| `resources/views/pricing/labels.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/purchasing/history.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/purchasing/invoice-import.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/purchasing/invoice-print.blade.php` | Intentionally excluded | Print/PDF/export document layout; management actions are inappropriate. |
| `resources/views/purchasing/invoices.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/purchasing/orders.blade.php` | Converted and visually represented | Shared semantic actions/table treatment verified in the genuine browser set. |
| `resources/views/purchasing/print.blade.php` | Intentionally excluded | Print/PDF/export document layout; management actions are inappropriate. |
| `resources/views/purchasing/return-detail.blade.php` | No row actions | Data, detail, filter, form, or shared-structure view has no applicable row/card action cluster. |
| `resources/views/purchasing/return-print.blade.php` | Intentionally excluded | Print/PDF/export document layout; management actions are inappropriate. |
| `resources/views/purchasing/returns.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
| `resources/views/purchasing/return-settings.blade.php` | Converted mechanically | Shared semantic action component present; routes/targets remain source-mapped. |
