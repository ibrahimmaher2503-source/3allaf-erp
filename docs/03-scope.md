# 03 — Scope and Boundaries

## In Scope

- Company identity, document numbering, tax, payments, printers/templates, branches, stores, drawers, users, roles, and policies.
- Bilingual products, attributes, images, categories, brands, suppliers, barcodes, imports, price lists/versions, approval, and label queues.
- Purchase orders, invoices, receipts, supplier returns, weighted-average cost, availability, transfers, entry/exit/adjustment documents, negative/fractional controls, and concurrent stock counts.
- POS search/cart/customer, suspended sales, cash/manual electronic payments with evidence, optional invoice tax, non-stacking discounts, thermal/A4 invoices, Gift Receipts, returns/exchanges, and Gift Cards.
- Customers, children/birthdays, shared loyalty, separated Product Wallet and Party Wallet ledgers, histories, expiry, approval, and audit.
- Cash drawers, shifts, blind closing, variance review, cash movement, and daily closing outputs.
- Party booking, editable working invoice, multiple payments on account, operating order, consumable issue/return, rental reservation/check-out/return/inspection, damage/depreciation, and final settlement.
- Standalone retail or party quotations with no operational/financial effect.
- Dashboard, alerts, reports, PDF/Excel exports, audit logs, PWA support, restricted offline POS, synchronization, conflict review, backups, monitoring, UAT, training, and controlled go-live.

## Out of Scope and Explicit Exclusions

- General ledger or complete accounting suite.
- Human-resources system.
- Advanced marketing automation.
- Public e-commerce website.
- Payment-provider or payment-gateway integration.
- AI agent functionality in the product.
- Quotation-to-invoice conversion in Phase 1.
- Microservices, a separate frontend, a separate API for normal screens, GraphQL, headless architecture, or full SPA.
- Filament, Inertia.js, Vue, React, Angular, Next.js, and Nuxt.
- Automated test creation or execution under the current owner directive.

## Future-Ready Scope

The modular-monolith design must not prevent a future public web store, website-customer payment gateway, quotation conversion, accounting, HR, marketing, or AI capability. No Phase 1 screen or data flow may imply these future features are delivered.

## Phase 1 Boundary

Requirements MD-01 through NFR-07 and milestones DM 1.1 through DM 6.4 define Phase 1. Material changes to an approved requirement, process, report, permission, data model, or acceptance criterion require a logged impact assessment, authorized approval, scheduling, and updated traceability.

## Dependencies

- Owner-approved business master data, operational policies, role/permission matrix, document/print layouts, target devices/browsers, and hosting/runtime decisions.
- A future Laravel project and verified versions before package selection or setup commands.
- Branch users for workflow validation, named UAT owners, training, production-data approval, and Phase Gates.
- Printer/scanner/camera/storage infrastructure for receipt, invoice, label, barcode, and payment-evidence workflows.

## Constraints

- Arabic-first, full RTL, English LTR, desktop/tablet responsive operation.
- One repository/application/deployment and conventional Laravel server authorization.
- Historical approved transactions remain immutable.
- High-volume data remains server-paginated, filtered, and safely queried.
- No unbounded offline operations; server values prevail for stock, price, wallet, and loyalty conflicts.
- This delivery contains Markdown documentation only and makes no implementation claims.

## Assumptions

- `Store` is the canonical stock-location term; `warehouse` is treated as an operational synonym pending owner terminology approval.
- System Administrator includes the implementation plan's `Owner` capabilities unless the owner requires a separate role.
- Warehouse Manager covers the implementation plan's `Warehouse Officer`; Party Manager covers `Party Officer`; Accountant/Reviewer covers `Accountant/Auditor`, pending approval.
- Money uses fixed decimal precision and quantities support configured fractional precision; exact precision and currency await owner/database decisions.
- Tax is selected per invoice as required, with tax configuration supplied before POS delivery.
- Package capabilities are candidates, not approvals; names and versions await compatibility review.

## Open Decisions

- Final role names and full permission matrix.
- Canonical terms for store versus warehouse and payment-on-account receipt versus deposit receipt.
- Currency, money/quantity precision, tax rules, discount authority, open-price rules, negative-stock override rules, and document numbering formats.
- Offline POS enablement, limits, expiration, target devices, allowed evidence capture, conflict handling, and operational ownership.
- Product hierarchy, composite/service behavior, barcode allocation, branch price exceptions, label formats, and initial data migration.
- Customer consent, loyalty, wallet, gift-card, return, party, rental, damage/depreciation, and cancellation policies.
- Hosting, database, Redis, queue, scheduler, storage, monitoring, backup, recovery, and production-domain choices.

## Payment Boundary

Phase 1 records cash and manually recorded electronic payments. Electronic payments may store POS-terminal receipt images as evidence. No provider authorization, capture, refund, reconciliation API, or public web gateway is included.

## Retail and Party Separation

Retail product activity uses branch selling stores, POS sales, Product Wallet, retail returns, and retail inventory. Party activity uses party stores, services, consumables, rental assets, party invoices, Party Wallet, and party settlement. The system must block mixed retail/party lines and preserve separated operations and reporting.

## Product and Party Invoice Separation

Products and party services/consumables/assets cannot coexist on one order or invoice. A clear blocking warning must identify the conflict. Quotations are also typed as retail or party and cannot create operational effects.

## Wallet Separation

The customer profile may show shared identity, history appropriate to role, and a shared loyalty balance. Product Wallet and Party Wallet are distinct ledgers. Retail cashiers cannot view Party Wallet balance/debt; Party Managers cannot view Product Wallet balance/debt. Cross-wallet settlement is prohibited unless a future approved requirement explicitly changes it.

## Accounting Boundary

The system records operational documents, payments, wallets, costs, stock valuation, cash movements, and reports. It is not a general ledger. Any future accounting integration must preserve source-document identity, immutability, and activity separation.

## Offline Boundary

Offline POS may accept only cash or manually recorded electronic payments within configured limits. It blocks credit, wallets, loyalty redemption, special discounts, and conflict-prone activity. Local transactions require authenticated, encrypted-as-supported, expiring storage, idempotent synchronization, server validation, and review queues for stock/price/wallet/loyalty conflicts. Exact limits and device/security choices are blockers.

## Change Control

Every requested change must state source, affected Requirement/Story/Flow/Screen/Milestone/Acceptance/Task IDs, data and permission impact, risk, effort, owner, and decision status. Approved changes update the PRD normalization, traceability matrix, acceptance criteria, backlog, decisions, blockers, and current milestone before implementation.
