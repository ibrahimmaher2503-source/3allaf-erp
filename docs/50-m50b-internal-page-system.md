# M50B Internal Page System

## Page archetypes

1. **Module overview** — operational KPIs, exceptions, trend, recent activity, and authorized quick actions. `sales.index` is the first complete implementation.
2. **Resource list** — `x-app.page`, a compact GET filter toolbar, active-filter chips, `x-tables.data-panel`, responsive rows, and `x-tables.pagination`.
3. **Document detail** — page heading, immutable status/amount summary, line table, timeline/audit, and policy-authorized transitions.
4. **Create/edit form** — page heading, sectional form cards, inline validation, guarded primary action, and cancel/unsaved-change handling.
5. **Operational workspace** — a dedicated full-screen layout for high-frequency work. POS remains this archetype and is not forced into the resource-list shell.

## Shared component contract

- `x-app.page`: page width, title, description, breadcrumb context, and primary actions.
- `x-page-header`: accessible heading hierarchy and responsive action wrapping.
- `x-tables.filter-chips`: visible summary and one-step reset for active filters.
- `x-tables.data-panel`: title, description, record count/actions, scroll boundary, and footer.
- `responsive-resource-table`: desktop table and labeled mobile record rows without document overflow.
- `x-tables.pagination`: scoped record range, bounded 20/50/100 page size, and Laravel navigation links.
- `x-status.badge` and `x-state.*`: shared status, loading, empty, no-result, error, and permission-boundary language.

Filters are GET parameters so pagination and sorting remain linkable. Route owners must validate sort keys and page sizes before applying them. Queries must apply the model's permission/visibility scope before search, filters, aggregates, sorting, count, and pagination. Row actions remain permission-aware and mutation authorization remains server-side.

## Sales & POS mapping

| Workspace | Route | Archetype | Current data/actions |
|---|---|---|---|
| Sales overview | `sales.index` | Module overview + resource list | Today totals/counts, open shifts, suspended sales, seven-day approved trend, collections, top products, recent scoped sales |
| Sales invoices | `sales.invoices` | Resource list | Approved immutable invoices, store/date/search filters, detail action |
| Customers | `customers.index` | Resource list | Scoped profiles, search/group filters, mode-aware profile/history/loyalty action |
| Shifts & collections | `pos.shift` | Operational form + recent list | Active drawer workflow and recent immutable closed shifts |
| Point of sale | `pos` | Dedicated operational workspace | Existing cart, scanner, pricing, payment, shift, and checkout behavior unchanged |

## Direction and accessibility

The document locale owns `lang`/`dir`; Cairo remains the Arabic-first local font. Logical spacing and text alignment support RTL/LTR. Controls retain 44px targets and visible focus, tables transform to labeled mobile rows below 640px, and identifiers remain LTR where meaningful.

## Next sequence

Apply the resource-list foundation to Purchasing, Inventory, Catalog/Pricing, Reports, and Administration. Then refine document detail/approval pages and create/edit forms without introducing a generic workflow engine.
