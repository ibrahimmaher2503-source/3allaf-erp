# 02 — Product Requirements Document

**Product:** TOY & JOY  
**Phase:** Phase 1 — Retail Stores, Inventory, POS, and Party Operations  
**Status:** Approved Phase 1 functional baseline, normalized for implementation  
**Source authority:** The approved English PRD supplied by I.S. Intelligent Solutions  
**Commercial terms:** Not included  

## Contents

1. Purpose and Vision
2. Scope, Exclusions, Decisions, and Definitions
3. Roles and Permission Model
4. Master Data
5. Product, Barcode, Pricing, and Labels
6. Suppliers and Purchasing
7. Inventory, Transfers, and Stock Count
8. POS Sales, Tax, Discounts, and Receipts
9. Returns, Exchanges, and Gift Cards
10. Customers, Loyalty, and Wallets
11. Cash Drawers, Shifts, and Daily Closing
12. Party Booking, Operations, and Final Settlement
13. Rental Assets, Damage, and Depreciation
14. Quotations
15. Dashboard, Reports, and Alerts
16. Audit, Integrity, and Non-functional Requirements
17. Acceptance Scenarios

## 1. Purpose and Product Vision

This PRD translates the approved Phase 1 operating design into testable product requirements. It covers retail operations and party operations in one cloud system while preserving strict operational and financial separation between them.

The product will provide:

- A browser-based cloud PWA for central administration, branch operations, warehouse work, cashiers, and party teams.
- Arabic-first operational readiness with bilingual data fields where applicable.
- Multi-branch and multi-store inventory control, controlled pricing, POS, purchasing, stock count, parties, customer records, loyalty, wallets, gift cards, reports, and auditability.
- Restricted offline cashier continuity followed by secure server synchronization when connectivity returns.

### Product Principle

The system must preserve trustworthy history. Approved transactions are never physically deleted; cancellation, reversal, return, or correction must create a traceable reference document and an audit-log record.

## 2. Scope, Exclusions, Key Decisions, and Definitions

### In Scope

Settings, branches, stores, products, barcodes, pricing, suppliers, purchasing, inventory, POS, returns, customers, loyalty, wallets, gift cards, cash drawers, shifts, party bookings, party invoicing, rental assets, reporting, and audit logs.

### Explicit Exclusions

General ledger/accounting suite, HR, advanced marketing, public e-commerce website, payment-gateway integration, and AI agent.

### Future-Ready Only

The architecture must not prevent a future web store and payment gateway for website customers only, quotation-to-invoice conversion, accounting, HR, marketing, and AI features. Those capabilities are not delivered in Phase 1.

### Payment Boundary

Phase 1 records cash and manual electronic payments. A POS-terminal receipt image is stored as payment evidence; no payment-provider integration is required.

### Separation Rule

Products and party/rental items must not be mixed in the same order or invoice. The user must see a clear blocking warning if they attempt to mix them.

### Definitions

| Term | Definition |
|---|---|
| Branch | Commercial operating location with an assigned selling store and cash drawers. |
| Store/Warehouse | Physical stock location. A branch may sell only from its assigned selling store unless a manager-authorized change is used. |
| Product Wallet | Customer balance related to retail-product activity only. |
| Party Wallet | Customer balance related to party activity only. |
| Rental Asset | Reusable party equipment or decor that is reserved, checked out, returned, inspected, and may incur damage or depreciation. |
| Consumable | Party material consumed during service delivery and issued from party inventory. |

## 3. User Roles and Permission Model

Permissions must be enforced server-side and represented correctly in the UI. Each right may be restricted by role, branch, store, document type, and action.

| Role | Primary capabilities |
|---|---|
| System Administrator | Company settings, users, roles, policies, global review, and audit access. |
| Branch Manager | Branch monitoring, cash control, approved warehouse override, shift review, and designated return/adjustment approvals. |
| Cashier | POS, own shift, payment evidence, suspended sales, receipt printing, and availability search. No cross-activity wallet exposure. |
| Purchasing Officer | Suppliers, purchase orders, purchase invoices, and supplier returns within authorization. |
| Warehouse Manager | Receiving, transfers, stock entries/exits, stock-count review, inventory adjustments, and reorder follow-up. |
| Pricing Officer | Price proposals, price-list versions, price approval, and barcode-label queues. |
| Party Manager | Bookings, party invoices, deposits/payments on account, operating orders, consumables, rental assets, and return inspections. |
| Stock Counter | Count sessions and draft count reports only; cannot approve reconciliation. |
| Accountant / Reviewer | Read, export, reconcile, and report within delegated scope; no unauthorized operational edits. |

For every relevant module, permission configuration must cover `View`, `Create`, `Edit`, `Logical Delete`, `Print`, `Approve`, `Export`, `Reverse/Cancel`, and `Override`. Sensitive actions additionally require explicit approval permission.

## 4. Core Data Model and Master Data

### MD-01

The system shall maintain company identity, document numbering, tax settings, payment methods, printer templates, branches, stores, cash drawers, users, roles, product categories, brands, suppliers, price lists, and customer policies as configurable master data.

### MD-02

A product shall have a stable internal item code that never changes when its supplier changes. It shall support an international supplier barcode when supplied and a locally generated barcode otherwise.

### MD-03

The local barcode format shall support a four-digit supplier code plus a six-digit serial number. The implementation must keep the underlying item code independent from that barcode.

### MD-04

Product cards shall include Arabic and English names/descriptions, item/model number, type, status, unit of measure, category/subcategory, brand, supplier, average cost, reorder threshold, dimensions, weight, target age, suitable gender, character, colour, main image, up to four additional images, key points, and bilingual keywords.

### MD-05

Colour, size, character, and age are searchable/reportable attributes only in Phase 1; they are not independent variants or separate stock balances.

Owner-approved amendment (TSK-045, 2026-08-12): ordinary colour, size, character, and age fields remain descriptive and never create implicit variants. A standard product may additionally be configured as an explicit variation family through the controlled bilingual option matrix. The family is non-sellable; each generated child Product is an independent immutable SKU/barcode/price/stock identity, and historical sales preserve the selected bilingual options as a line snapshot.

### MD-06

A customer record shall use one unique phone number, consent fields, contact data, party-related children data and birthday dates, purchase/party history, Product Wallet, Party Wallet, loyalty balance, and gift-card activity.

## 5. Product, Barcode, Pricing, and Labels

### PRC-01

Authorized users shall create and update products manually or import them from Excel. The import workflow shall map columns, validate required data, identify barcode/code/category issues, show an error report, and let the user choose `Create Only` or `Update Existing` before approval.

**Acceptance statement:** Invalid rows are not written; a downloadable error list identifies each rejected row.

### PRC-02

The system shall support standard products, composite products, and services where required by the approved product setup.

### PRC-03

Prices may be entered from the product card, approved Excel import, or a purchase invoice after selecting the receiving store. A purchase-cost change must not automatically change a sale price.

### PRC-04

A price change shall create an immutable price-list version, require Pricing Officer approval when configured, and preserve all historical invoices and sales at their original values.

### PRC-05

Once a new price version is approved, the system shall update the sale price of the remaining balance at the affected stores/branches; the same item must not have two active shelf prices at one location.

### PRC-06

The system shall create a label-print queue equal to remaining stock by location after a price version is approved. Labels must always be printed against a selected store/branch and use its approved price or branch exception.

### PRC-07

A product with no approved sale price shall show a zero selling price and an explicit pricing-pending state. It may be received on a purchase invoice, but cannot be sold or have a barcode label printed.

### PRC-08

Open-price selling may be enabled only for authorized roles, with reference price, minimum and maximum limits, mandatory reason where configured, and a full audit record.

## 6. Suppliers and Purchasing

### PUR-01

The system shall maintain supplier contact data, status, terms, historical invoices, returns, and last purchase prices.

### PUR-02

Each product may have a preferred supplier. Each purchase invoice must retain the actual supplier used historically, even when the preferred supplier later changes.

### PUR-03

Users shall create purchase orders and track at least `Draft`, `Submitted`, `Partially Received`, `Received`, `Cancelled`, and `Closed` states.

### PUR-04

Purchase invoices shall support manual entry and approved Excel import with receiving store, items, quantities, unit costs, discounts if used, and optional tax.

### PUR-05

Approval of a purchase invoice shall increase inventory in the selected store and update inventory cost using weighted-average costing. It must create an auditable stock movement.

### PUR-06

Supplier returns shall reference an approved purchase-invoice line for every Phase 1 return line, reduce stock only through an approved return document, and maintain original unit-cost/history traceability. A supplier return without an approved source line is rejected; legacy/unknown stock requires a separately approved explicit-cost `adjustment_out` document.

### Purchasing Workflow

`Purchase Order → Goods Receipt / Purchase Invoice → Pricing Review if Required → Stock Approval → Supplier Reporting`

## 7. Inventory, Transfers, and Stock Count

### INV-01

The system shall display on-hand, available, in-transit, reserved where applicable, reorder state, and movement history by product and store.

### INV-02

A branch POS shall sell only from its assigned selling store. Availability at other branches may be searched for customer service, but not sold directly.

### INV-03

Store transfers shall use a stateful document: `Draft`, `Submitted`, `Approved`, `Dispatched/In Transit`, `Partially Received`, `Received`, `Difference Review`, and `Cancelled`. The destination confirms actual received quantities and may log shortage, damage, or refusal separately.

### INV-04

Inventory entry, inventory exit, and exchange/adjustment documents shall require reason, store, items, quantities, responsible user, and approval according to policy.

### INV-05

Negative stock shall be blocked by default. Where permitted by store policy, an authorized override must be highlighted and fully audited.

### INV-06

Fractional quantities shall be supported only for products specifically configured to allow them.

### INV-07

Stock count shall support full branch counts; partial counts by category, supplier, or store; barcode scanning; manual input; repeated counts; discrepancy reports; and controlled reconciliation.

### INV-08

Counting shall not stop selling. For each counted item, the system shall capture the reference balance and subsequent movements, then reconcile the verified counted quantity against activity that occurred during the count.

### INV-09

Uncounted items must never be automatically set to zero. They must enter a review list requiring Stock Counter and Warehouse Manager approval before any adjustment.

### Transfer Workflow

`Availability / Approval → Dispatch from Source → In Transit → Destination Receipt → Difference / Damage Review`

## 8. POS Sales, Tax, Discounts, and Receipts

### POS-01

The POS shall support product search by name, item code, and barcode; cart quantity changes according to permission; customer lookup/registration; suspended sales; and thermal or A4 printing.

### POS-02

Each sale shall be linked to branch, selling store, cashier, cash drawer, shift, customer if selected, payment records, and stock movements.

### POS-03

The system shall support cash and manually recorded electronic payment. Electronic payment must allow attachment of a POS-terminal receipt image as evidence; no gateway is integrated in Phase 1.

### POS-04

Tax shall be optional per invoice. The invoice sequence remains normal and unified; an authorized user selects whether tax applies to that specific invoice.

### POS-05

Only one discount type may apply to an amount. Customer/group discount and another invoice/item discount must not stack. The system shall block or require a replacement choice rather than cumulative discounting.

### POS-06

The printed invoice shall show each item, quantity, and original price. Where an item discount exists, the next line shall show the discount and resulting net item value. Totals shall show gross items, total discount, net after discount, optional tax, and final total.

### POS-07

The system shall issue a Gift Receipt without prices. The recipient can use it to identify the original sale for exchange or return without seeing purchase prices.

### Offline Cashier Mode

When offline, POS may accept only cash or manually recorded electronic payments within configured operational limits. It must block credit sales, wallets, loyalty redemptions, special discounts, and any operation likely to create a balance or price conflict. On reconnection, server values prevail for stock, price, wallet, and loyalty conflicts; every conflict must be queued for review.

## 9. Retail Returns, Exchanges, and Gift Cards

### RET-01

The system shall validate the original invoice or Gift Receipt before processing a return or exchange, subject to authorization and return policy.

### RET-02

It shall support exchange for the same item, exchange for a different item with settlement of the difference, cash refund, or issuance of a Gift Card equal to the eligible return value.

### RET-03

Before an item returns to stock, the operator shall record its inspection condition. Configured cases require manager approval; rejected/damaged items must follow a separate non-saleable or damage process.

### RET-04

Gift Cards shall have unique identifiers, value, balance, issue/use/void history, validity period, holder/reference where applicable, and permission-controlled use at sale or settlement.

### Returns Workflow

`Validate Reference → Inspect Condition → Choose Refund / Exchange / Gift Card → Approve if Needed → Update Stock and History`

## 10. Customers, Loyalty, and Wallets

### CUS-01

The customer file shall unify profile and history across retail and party activity without combining their financial balances.

### CUS-02

Product Wallet and Party Wallet shall be stored, displayed, authorized, settled, and reported separately. A retail cashier must not view Party Wallet debt/balance, and a Party Manager must not view Product Wallet debt/balance.

### CUS-03

Loyalty points shall be one shared customer balance usable across both activities subject to configurable earn/redeem rules, approval, expiry, and audit. Rules may differ by activity, such as a party earn rate distinct from retail.

### CUS-04

The system shall preserve customer transaction history, party history, payments, returns, gift cards, point movement, and wallet movement with source-document references.

## 11. Cash Drawers, Shifts, and Daily Closing

### CSH-01

A cashier shall open a shift on a specified cash drawer with an opening balance. Every POS transaction and permitted cash movement is linked to the active shift.

### CSH-02

At closing, the cashier enters actual cash and actual electronic amounts without being shown expected values.

### CSH-03

After submission, the system calculates shortage/overage and exposes detailed expected-versus-actual information only to authorized managers/reviewers.

### CSH-04

The system shall produce a concise thermal closing receipt and a detailed A4 daily closing report. It shall report payment methods, cash movement, shifts, cash drawers, and variances.

### Shift Workflow

`Open Shift → POS Sales / Cash Activity → Store Payment Evidence → Enter Actual Totals → Variance Review and Approval`

## 12. Party Booking, Operations, and Final Settlement

### PTY-01

Party operations shall use a separate workflow from retail POS and separate party stores. Party services, consumables, and rental assets cannot be mixed with retail products in one invoice.

### PTY-02

A booking shall capture customer, child information where relevant, party date/time/location, contacts, notes, planned services, consumables, rental assets, and assigned operational responsibilities.

### PTY-03

The party invoice begins as a draft/working invoice and remains editable before final closing to reflect actual party requirements and operational changes.

### PTY-04

The system shall record multiple payments on account against a party invoice. Every payment must issue an individual receipt labeled `Payment on Account for Party Invoice No. [number]`.

### PTY-05

An operating order shall reserve the relevant services and rental assets for the party date, issue consumables from party inventory, and allow controlled additions/removals until completion.

### PTY-06

At final close, the system shall create the final party invoice, reconcile payments on account, settle Party Wallet if used, capture the remaining amount or credit, and issue a final receipt.

### Party Lifecycle

`Booking & Working Invoice → Payments on Account → Operating Order → Issue Consumables / Check Out Assets → Return & Inspect → Final Invoice / Settlement`

## 13. Rental Assets, Returns, Damage, and Depreciation

### AST-01

Rental assets shall be maintained separately from consumables and include unique code, name, category, availability, current location, condition, status, relevant cost data, and history.

### AST-02

The system shall reserve each asset for the party date/time, block conflicting allocation, and record check-out and return documents.

### AST-03

Before and after a party, the responsible user shall record asset condition. The system shall support `Available`, `Reserved`, `Checked Out`, `Under Inspection`, `Damaged`, `Under Maintenance`, `Retired`, and `Lost` states.

### AST-04

Damage and depreciation shall capture the asset, event, party reference, assessment, responsible user, cost impact where entered, approval, and final asset status.

### AST-05

Party consumables shall be issued from party stores and recorded as consumed against the operating order. Returnable unused quantities, if permitted, require a reference return movement.

## 14. Quotations

### QTN-01

Authorized users shall create standalone quotations for retail or party scope with customer, lines, quantities, prices, terms, notes, validity date, and status.

### QTN-02

A quotation shall be printable/shareable but shall not create inventory, reservation, payment, wallet, or accounting effect.

### QTN-03

The design shall retain quotation identity and source references so a future phase can convert an accepted quotation to an invoice with one action; this conversion is not included in Phase 1.

## 15. Dashboard, Reports, and Operational Alerts

### RPT-01

The dashboard shall filter by date, branch, and authorized scope and show sales, invoice count, average invoice value, payment-method summary, cashier/branch performance, drawer balances, shift variances, stock value, low/out-of-stock items, unpriced items, recent purchasing, upcoming parties, deposits due, top products, categories, and customers.

### RPT-02

Operational alerts shall include low stock, zero stock, unpriced products, pending price approval, transfer receipt pending, count difference pending, open/unclosed invoices where relevant, shift variance, upcoming party commitments, overdue party balances, and asset return/damage issues.

### RPT-03

Reports shall be exportable to PDF and Excel according to permission. Filters must include available combinations of date range, branch, store, user, supplier, product, category, payment method, customer, party status, and document status.

### Required Report Groups

| Group | Minimum content |
|---|---|
| Sales & POS | Daily/monthly sales; sales by branch, cashier, payment, product, and category; invoice, tax, and discount details; suspended/cancelled sales where authorized. |
| Inventory | On-hand and availability; stock valuation; product card/movement; reorder; unpriced; price-version/label queue; transfers; count differences; adjustments. |
| Purchasing | Purchase orders; purchase invoices; supplier returns; supplier history; actual/preferred/last supplier price. |
| Cash | Shift closing; drawer movement; payment collection; expected/actual variance and approvals. |
| Customers | Customer history; loyalty earn/redeem; wallet movement and balances; gift-card status/use. |
| Parties | Bookings; deposits/payments on account; outstanding balances; services; consumables; rental assets; check-outs/returns; damage and depreciation. |

## 16. Audit, Data Integrity, and Non-functional Requirements

### NFR-01

Every sensitive event must capture user, timestamp, device/session where available, branch/store context, source document, reason where required, and before/after values. Mandatory audit events include price/list changes, price override, barcode-label printing, preferred-supplier change, transfer and receipt, count edit/reconciliation, shift-variance settlement, wallet/loyalty use, party operating-order edit, cancellation, and logical deletion.

### NFR-02

Approved documents must be immutable. Reversal, cancellation, return, and adjustment must reference the originating document and preserve all original records.

### NFR-03

The application shall enforce authorization at API/service level, not only through hidden menus or buttons.

### NFR-04

The PWA shall use secure authentication, role-scoped sessions, server-side validation, controlled attachment storage for POS receipts, and safe synchronization after offline operation.

### NFR-05

All lists and reports must provide searchable/filterable views appropriate to role scope. High-volume views shall paginate or use an equivalent safe loading strategy.

### NFR-06

Document numbers shall be unique and sequential according to configured numbering rules. Concurrent user actions must not create duplicate approved transaction numbers.

### NFR-07

The system shall be designed for modular future extensions without breaking the approved Phase 1 data separation and document history.

## 17. Acceptance Scenarios and Delivery Checklist

- A user can create a branch, its selling store, cash drawers, price list, user roles, and printer configuration; unauthorized roles cannot alter them.
- A purchase invoice increases selected-store stock and changes weighted-average cost, but does not automatically change selling price.
- A price approval creates a new version, preserves old sales, updates remaining-stock price, and generates the correct location-based label queue.
- A cashier can sell only branch-store inventory, record cash/electronic payment evidence, select optional tax, apply only one discount type, and print the required invoice layout.
- A cashier can issue a price-free Gift Receipt; a later return/exchange validates it without disclosing original price.
- A return supports same-item exchange, different-item exchange, refund, and Gift Card settlement, with condition inspection and traceability.
- A stock count continues while sales occur, calculates the correct reconciled balance, and does not automatically zero uncounted items.
- A party invoice accepts multiple payments on account with separate receipts, remains editable until closing, and settles independently from Product Wallet.
- A rental asset cannot be double-booked, has check-out/return records, and retains damage/depreciation history tied to the party.
- Role permissions prevent cross-activity wallet exposure and block unauthorized approval, export, price override, stock adjustment, and cancellation.
- All required dashboard widgets, alerts, PDF/Excel reports, and audit logs work within the user's authorized branch/store scope.

## Requirement Inventory

This normalized PRD contains 72 unique functional and non-functional Requirement IDs: MD-01–MD-06, PRC-01–PRC-08, PUR-01–PUR-06, INV-01–INV-09, POS-01–POS-07, RET-01–RET-04, CUS-01–CUS-04, CSH-01–CSH-04, PTY-01–PTY-06, AST-01–AST-05, QTN-01–QTN-03, RPT-01–RPT-03, and NFR-01–NFR-07.
