# M50A.1 module-dashboard registry

This registry defines the information architecture for module landing pages without creating routes or fabricating data. Every route is an existing permission-protected application route. M50A.1 implements the Inventory overview; remaining detailed module dashboards are sequenced through M50B.

| Module | Landing route | Audience permission | KPIs | Alerts and exceptions | Quick actions / linked subpages | Recent activity and primary views |
|---|---|---|---|---|---|---|
| Sales & POS | `sales.index` | `pos_sales.view` | Today sales, invoices, open shifts, suspended sales | Offline conflicts, shift variances | POS, invoices, shifts | Recent sales; sales list and sales report |
| Purchasing | `purchasing.orders` | `purchase_orders.view` | Pending orders, today purchases, returns, approvals | Overdue receiving and returns | Orders, invoices, supplier returns | Recent orders/invoices; purchasing lists |
| Inventory | `inventory.index` | `inventory_stock_card.view` | Visible products, on hand, low/out stock, transfers, counts, adjustments | Low/out stock and transfer differences | Balances, movements, transfers, counts, adjustments, barcodes | Recent movements; balance and ledger tables |
| Catalog & Pricing | `catalog.products` | `products_categories_brands.view` | Product status, barcode and price coverage | Missing identity/pricing | Products, categories, pricing, labels | Recent products/prices; catalog and price lists |
| Customers | `customers.index` | `customers.view` | Active/new customers, loyalty and wallet activity | Consent and duplicate review | Customers, create, groups | Recent customers; customer list/report |
| Suppliers | `catalog.suppliers` | `suppliers.view` | Active suppliers, orders, returns, contact readiness | Contact and supplier exceptions | Suppliers, orders, returns | Recent suppliers/orders; supplier history |
| Parties / Bookings | `parties.bookings.index` | `party_bookings_invoices.view` | Upcoming bookings, orders, settlement, conflicts | Schedule and settlement exceptions | Bookings, operations, calendar | Recent bookings; booking/order workspaces |
| Assets / Rental | `party.assets.index` | `rental_assets.view` | Available, reserved, checked-out, inspection queue | Overdue returns and inspections | Asset workspace and reservation mode | Recent asset operations; asset workspace |
| Reports | `reports.index` | `dashboard_reports.view` | Report/export availability and status | Failed exports | Sales, purchasing, inventory reports, export center | Recent exports; report explorer |
| Administration | `admin.settings` | `company_settings.view` | Setup, locations, users, approvals | Health and configuration gaps | Settings, branches, authorization, audit | Recent audit events; settings and audit |

## Page contract

Each implemented module landing page uses the shared shell and must provide a compact title/scope row, permission-aware quick actions, real scoped KPIs, designed empty states, exception prioritization, recent activity, and a primary operational table or meaningful chart. Mutation actions remain in their existing authorized action boundaries. POS remains directly reachable at `pos` and keeps its dedicated full-screen transaction archetype.
