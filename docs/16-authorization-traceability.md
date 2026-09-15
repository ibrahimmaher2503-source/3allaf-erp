# Authorization Traceability

## TSK-004B Guidance Boundary

The assistant does not create permissions. It filters registered actions and full guide/flow access through existing server Gates, preserves branch/store scope, and uses a safe fallback for missing or unauthorized guides. UI visibility is never the authorization boundary.

`docs/04-roles-permissions.md` is the Canonical Authorization Matrix under DEC-038. `ProductionSeeder` seeds all nine canonical roles and the complete canonical module/action permission catalog. Under DEC-077, the System Administrator role receives every active permission so the owner can complete the guarded Production setup UI; all other roles retain the conservative Production grant map and require explicit scope assignment. Sensitive approval workflows still enforce their maker/checker rules, so full permission does not permit an administrator to approve their own request where independent approval is required.

## Current Application Surfaces

| Surface | Canonical permission | Exact enforcement | UI treatment | Status |
|---|---|---|---|---|
| `GET /dashboard` | `dashboard_reports.view` | `routes/web.php`: `auth`, `verified`, `can:dashboard_reports.view` | Sidebar item wrapped by `@can('dashboard_reports.view')` | Enforced and tested |
| `GET /admin/settings` | `company_settings.view` | `routes/web.php`: `can:company_settings.view`; `pages/admin/settings.blade.php` write methods call `manage-settings`, a Gate alias for `company_settings.edit` | Sidebar item uses `@can('company_settings.view')` | Enforced and tested |
| Settings writes: company, payment, tax, sequence, printer | `company_settings.edit` | Every Livewire save/edit method in `pages/admin/settings.blade.php` calls `Gate::authorize('manage-settings')`; `AppServiceProvider` maps that alias to `company_settings.edit` | Route is read permission; controls are only available to the current administrator grant | Enforced and tested |
| `GET /admin/branches` | `branches_stores.view` | Route middleware; component `mount()` repeats `Gate::authorize('branches_stores.view')`; query uses `Branch::visibleTo(auth()->user())` | Sidebar uses `@can`; create/edit/delete controls use matching `@can` | Enforced and tested |
| Branch create/edit/mapping/status | `branches_stores.create` or `branches_stores.edit` | `openCreateBranchModal`, `openEditBranchModal`, `saveBranch`, `toggleBranchStatus`, `openMappingModal`, `saveSellingStoreMapping` each authorize the exact action | Unauthorized controls hidden | Enforced and tested |
| Branch delete | `branches_stores.logical_delete` | `deleteBranch()` authorizes `branches_stores.logical_delete` | Delete control hidden without that permission | Enforced and tested |
| `GET /admin/stores` | `branches_stores.view` | Route middleware; component `mount()`; `Store::visibleTo(auth()->user())` query and scoped branch filter | Sidebar and write controls use `@can` | Enforced and tested |
| Store create/edit/mapping/status/delete | `branches_stores.create`, `.edit`, `.logical_delete` | Corresponding Livewire actions in `pages/admin/stores.blade.php` authorize the exact permission | Unauthorized controls hidden | Enforced and tested |
| `GET /admin/cash-drawers` | `drawers_payments_tax_numbering_printers.view` | Route middleware; component `mount()`; `CashDrawer::visibleTo`, `Branch::visibleTo`, and `Store::visibleTo` scope all lists | Sidebar and write controls use `@can` | Enforced and tested |
| Cash drawer create/edit/status/delete | `drawers_payments_tax_numbering_printers.create`, `.edit`, `.logical_delete` | Corresponding methods in `pages/admin/drawers.blade.php` authorize the exact action | Unauthorized controls hidden | Enforced and tested |
| `GET /admin/authorization-baseline` | `users_roles_permissions.view` | Route middleware and component `mount()` | Sidebar uses `@can`; Manage button requires `.edit` | Enforced and tested |
| Role/branch/store assignment | `users_roles_permissions.edit` | `editAuthorization`, `saveAuthorization`, and `SaveUserAuthorizationAction::execute()` each authorize; input IDs are validated; transaction writes append-only `SettingsAuditLog`; final system administrator removal is rejected | Manage control hidden without `.edit` | Enforced and tested |
| `GET /admin/system/health` and refresh | `audit_logs.view` | Route middleware; component Gate alias `view-platform-status` maps to `audit_logs.view` | Sidebar uses `@can('audit_logs.view')` | Enforced and tested |
| `GET /admin/system/ui-showcase` and its local interactions | `dashboard_reports.view` | Route middleware; component alias `view-ui-showcase` maps to `dashboard_reports.view` | Sidebar uses `@can('dashboard_reports.view')` | Enforced and tested |
| `GET /system/app` | `dashboard_reports.view` | Route middleware | Sidebar uses `@can('dashboard_reports.view')` | Enforced and tested |
| `GET /pos` | `pos_sales.view` | Route middleware | Sidebar uses `@can('pos_sales.view')` | Enforced and tested |
| Public home, locale switch, auth/reset/verification, profile/security self-service, logout | Not applicable | Laravel/Fortify `auth`, `verified`, password-confirmation, CSRF, and self-user session boundaries; no role-management data/action is exposed | Navigation is the starter self-service menu | Not applicable |
| API routes | Not applicable | No application API routes exist in this scope | None | Not applicable |
| `GET /catalog/products`, `/catalog/categories`, `/catalog/brands` | `products_categories_brands.view` | Catalog routes and each Livewire `mount()` enforce the view Gate; catalog queries are bounded and permission-gated | Sidebar shows Catalog only when the view Gate allows it | Enforced and browser-verified |
| Catalog create/edit/status/barcode actions | `products_categories_brands.create` / `.edit` / `.logical_delete` | Catalog Livewire actions and module actions authorize exact capabilities server-side; forged Cashier create returned HTTP 403 | View-only roles see no write controls; no catalog `P`/`R` grant was added | View-only boundary enforced; future P/R workflows remain ungranted |

## Scope and Audit Conventions

`User::canAccessBranch()` and `User::canAccessStore()` evaluate active scope records; local super administrators bypass only the local scope restriction. `Branch::visibleTo`, `Store::visibleTo`, and `CashDrawer::visibleTo` apply those assignments to all current master-list queries. Authorization assignment is transactional and appends `update_user_authorization` with before/after role and scope IDs to the immutable local `settings_audit_logs` table.

## Deferred Module Enforcement

| Canonical module group | Seeded | Deferred enforcement task |
|---|---:|---|
| Catalog identity/category/brand/barcode view slice | Yes | TSK-010 local scope; current create/edit/status P/R capabilities remain ungranted |
| Supplier master/history, purchasing, pricing, inventory, transfers, stock counts | Yes | TSK-013 and TSK-014 to TSK-022 |
| POS sale actions, holds, shifts, payments, returns, gift instruments | Yes | TSK-023 to TSK-026 and TSK-030 |
| Customers, loyalty, Product Wallet, Party Wallet | Yes | TSK-027 to TSK-029 and TSK-032 |
| Party bookings, operations, assets | Yes | TSK-031 to TSK-036 |
| Quotations, reports, exports, audit views, offline conflicts | Yes | TSK-037 to TSK-040 |

For each deferred module, its task must add model Policy/Gate checks, ownership and approval-limit evaluation, field-level restrictions, scoped queries, export enforcement, audit behavior, and module browser tests before it can claim the corresponding permission enforced.
