# M50A — Shell, navigation, and dashboard architecture

## Boundary

M50A changes presentation and read-only dashboard composition only. Existing routes, middleware, policies, actions, models, mutations, and schema remain authoritative. The global dashboard is `/dashboard`; module dashboards remain their existing landing workspaces and are not replaced by new routes.

## Navigation inventory

| Group | Concise destination | Existing route | Existing permission |
|---|---|---|---|
| Home | Dashboard | `dashboard` | `dashboard_reports.view` |
| Home | Alerts & tasks | `alerts.index` | `dashboard_reports.view` |
| Sales & POS | Sales overview | `sales.index` | `pos_sales.view` |
| Sales & POS | Point of sale | `pos` | `pos_sales.view` |
| Sales & POS | Sales invoices | `sales.invoices` | `pos_sales.view` |
| Sales & POS | Customers | `customers.index` | `customers.view` |
| Sales & POS | Shifts & collections | `pos.shift` | `shifts_cash_movements.view` |
| Purchasing & suppliers | Purchase invoices | `purchasing.invoices` | `purchase_invoices_supplier_returns.view` |
| Purchasing & suppliers | Purchase orders & receiving | `purchasing.orders` | `purchase_orders.view` |
| Purchasing & suppliers | Supplier returns | `purchasing.returns` | `purchase_returns.view` |
| Purchasing & suppliers | Suppliers | `catalog.suppliers` | `suppliers.view` |
| Products & inventory | Products | `catalog.products` | `products_categories_brands.view` |
| Products & inventory | Categories & options | `catalog.categories` | `products_categories_brands.view` |
| Products & inventory | Balances & movements | `inventory.index` | `inventory_stock_card.view` |
| Products & inventory | Transfers | `inventory.transfers` | `transfers.view` |
| Products & inventory | Counts & adjustments | `inventory.counts` | `inventory_stock_card.view` |
| Products & inventory | Barcodes | `pricing.labels` | `pricing_labels.view` |
| Pricing | Price lists & rules | `pricing.index` | `pricing_labels.view` |
| Parties & bookings | Bookings | `parties.bookings.index` | `party_bookings_invoices.view` |
| Parties & bookings | Related operations | `parties.orders.index` | `party_bookings_invoices.view` |
| Assets & rental | Rental assets | `party.assets.index?mode=workspace` | `rental_assets.view` |
| Assets & rental | Rental operations | `party.assets.index?mode=reservations` | `rental_assets.view` |
| Reports | Sales | `reports.sales` | `pos_sales.view` |
| Reports | Purchasing | `reports.purchasing` | `purchase_orders.view` |
| Reports | Inventory | `reports.inventory` | `inventory_stock_card.view` |
| Reports | Customers & suppliers | `reports.customers` | `customers.view` |
| Reports | Cash & shifts | `reports.cash` | `shifts_cash_movements.view` |
| Administration | Company, branches & stores | `admin.branches` | `branches_stores.view` |
| Administration | Users & roles | `admin.authorization-baseline` | `users_roles_permissions.view` |
| Administration | Master data | `initial-setup` | `company_settings.edit` |
| Administration | System settings | `admin.settings` | `company_settings.view` |
| Administration | Activity log | `admin.audit` | `audit_logs.view` |

`ApplicationNavigation` drops missing routes and items the current user cannot access before rendering. The same filtered structure powers the sidebar and command search, so search cannot reveal unavailable screens. Only the active group starts expanded. POS remains a primary Sales & POS destination.

## Dashboard hierarchy and data sources

- Today sales: `Sale::visibleTo($user)->approved()`, filtered by `approved_at`; total uses `payable_total` and count uses the same scoped query.
- Today purchases: `PurchaseInvoice` constrained by the `Store::visibleTo($user)` SQL subquery and `invoice_date`.
- Pending receiving: visible-store `PurchaseOrder` rows in established submitted/approved/partially-received states.
- Pending supplier returns: visible-store submitted `PurchaseReturn` rows.
- Pending approvals: `ApprovalRecord::visibleTo($user)` and pending state, shown only to users with an existing decision permission.
- Low stock: visible-store `StockBalance`, available quantity (`on_hand - reserved`) at five or less, bounded to six rows.
- Recent sales, purchases, and stock movements: scoped, eagerly loaded, deterministic newest-first lists bounded to five rows.
- Setup readiness: existing `InitialSetupStatus`, reduced to an administrator-only compact widget.

No KPI is synthesized. Missing permission removes the widget; an empty scoped result displays an explicit empty state.

## Global and module-dashboard architecture

1. **Global operational dashboard** — role-aware daily totals, exceptions, recent activity, and real quick actions.
2. **Module workspaces** — existing route-owned landing pages (`sales.index`, `purchasing.orders`, `inventory.index`, `catalog.products`, `parties.bookings.index`, `reports.index`, `admin.settings`). M50A links to them but does not duplicate their queries.
3. **Page archetypes for M50B onward**:
   - operational list/table with scoped filters and bulk actions;
   - transaction create/edit with fixed action rail and validation summary;
   - immutable detail/approval with timeline and audit panel;
   - fast barcode-first POS;
   - report explorer with filter bar, summary, table, and exports;
   - settings/master-data workspace with compact section navigation;
   - print/document layouts, kept visually separate from the application chrome.

## Direction, responsiveness, and accessibility

The document-level `dir` remains locale-derived. Logical start/end utilities keep sidebar, spacing, menus, and controls correct in RTL/LTR. The sidebar is approximately 272px on desktop, honors the existing collapsed preference, and uses Flux’s accessible off-canvas behavior on smaller screens. Header controls meet a 44px target, command search supports Ctrl/Command+K and Escape, focus rings are visible, numeric identifiers remain `dir=ltr`, and dense data panels retain horizontal-safe layouts.

The approved v0.1.4 footer text and link are unchanged; its compact responsive type is raised to a readable 12–13px where space permits and stays on one line.
