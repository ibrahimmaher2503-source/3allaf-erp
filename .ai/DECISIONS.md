# Owner Decisions

- 2026-09-15 — Egyptian feed-store extension: preserve the current modular monolith and `products.product_type`; add product-specific units, feed classification, quantity snapshots, commercial customer/supplier ledgers, landed cost, general treasury, expenses, and optional batches incrementally. Existing stock history is immutable, `stock_balances.average_cost` remains the operational inventory-valuation source, balances are derived rather than stored on parties or cash accounts, and the owner explicitly authorizes focused tests, the existing suite, local MariaDB `migrate --seed`, and integrity auditing for this named task only.

- 2026-08-23 — Owner-requested privileged role-permission override: a super-admin or user holding the active `system-administrator` role may manage all active permissions, including sensitive permissions, for every role except the `system-administrator` role itself. Canonical role metadata remains protected, and maker/checker rules remain unchanged.

- 2026-08-26 — Source-handoff repository hygiene: generated/runtime artifacts, environment overrides, secrets, caches, logs, local databases/dumps, temporary exports/backups, and private attachments are excluded from version control while intentional examples, migrations, public source assets, and Laravel placeholder `.gitignore` files remain tracked. Item 36 removed the two tracked browser verification logs and generated PHPUnit compiled-view artifacts without rewriting history; the placeholder files were preserved. The stable source-handoff reference is `v0.1.3-source-handoff-complete`, which will be corrected to the item-35 documentation commit after that commit is created.

- 2026-08-26 — Isolated sanitized release lineage: Release Candidate publication uses `release/v0.1.4-rc1-sanitized`, created by removing only the approved QA/runtime/database path roots from isolated history. The original handoff branch remains unchanged as local recovery history and is not approved for publication. The sanitized lineage has no remotes and passed prohibited-path and high-confidence secret scans. Production deployment, migrations, backup verification, QA/UAT, release approval, and owner production approval remain unclaimed.

- 2026-08-27 — M50A shell architecture: one centralized permission-filtered navigation definition powers the desktop sidebar, mobile drawer, and command search. The global `/dashboard` is an operational cross-module summary; existing route-owned module landing pages remain module workspaces. POS stays a dedicated full-screen archetype under Sales & POS. Arabic uses locally hosted Cairo variable fonts, while locale-derived document direction and logical layout utilities preserve RTL/LTR behavior. M50A adds no route, schema, mutation, or commercial-template dependency.

- 2026-08-27 — M50A.1 visual correction: retain the M50A route/permission architecture, make Sales Overview the Sales & POS landing concept while keeping POS directly reachable, and use Inventory as the first real-data module-dashboard implementation. A centralized module-dashboard specification records ten module contracts for staged M50B delivery. Arabic rendering is accepted only with local WOFF2 response, FontFace readiness, Cairo-first computed styles, and glyph-metric evidence; a CSS family declaration alone is insufficient. Mobile KPI regions are keyboard-focusable horizontal strips, while the global document remains overflow-free.

- 2026-08-27 — M50B.1 internal-page and shell-state boundary: resource lists use shared page headers, GET filter summaries, responsive labeled rows, bounded 20/50/100 pagination, and server-validated sort allowlists. Desktop collapse is Rajeh-owned; only a valid version-2 local preference may select collapsed mode, while missing, legacy, unknown, empty, or corrupt state self-recovers to expanded. Flux remains responsible only for the mobile overlay drawer.

## 2026-08-27 — M50B.2 purchasing landing and payable boundary

- Use the existing authorized `purchasing.orders` route as the module landing/dashboard and order/receiving queue; do not add a route.
- Do not infer supplier due/overdue amounts without an authoritative payable ledger.
- Preserve existing routes, Gates, policies, visible-store/branch scopes, actions, calculations, approvals, inventory mutations, and schema.

## 2026-08-27 — M50B.3 inventory scope and Settings loading boundary

- Use `inventory.index` as the existing module landing route and keep catalog, balances, transfers, counts, adjustments, and barcode labels in one permission-filtered navigation group.
- Display inventory value only from authoritative `stock_balances.total_value` and only when `inventory_stock_card.cost_view` is allowed.
- Treat Flux submit loading and an Alpine idle-disabled state as separate concerns: the Company review button invokes only `previewCompany`, while every Settings save action has an explicit loading target.
- Preserve inventory mutations, costing, barcode allocation, routes, permissions, policies, and schema.


## 2026-08-27 — M50B.4 pricing capability and work-context boundary

- Treat immutable `PriceList`/`PriceVersion`/`PriceLine` records, effective dates, open-price bounds, approval, and POS cart-discount permissions as the authoritative pricing capabilities. Do not present standalone promotions or campaign discounts because no such source-owned model/workflow exists.
- Do not infer margin from unrelated or mixed store costs. Pricing coverage and exception indicators remain authorized-store scoped and read-only.
- The prior header selected the first visible store while most dashboards aggregated all visible stores. The shell now defaults to “All authorized stores,” persists an optional permission-checked store in the existing session context, and applies it to supported Sales, Purchasing, Inventory, and Pricing views. POS continues to use its existing shift/store ownership and is never silently reassigned by the shell.

## 2026-08-27 — M50B.5 Party capability and customer-history boundary

- Use the existing authorized `parties.bookings.index` route as the Party module landing page and keep calendar, invoice/payment, operating-order, and booking-detail workflows on their current routes and action boundaries.
- Treat `PartyBooking.location` and Party invoice service lines as the authoritative current venue/service description. Do not invent package, venue, resource-capacity, deposit, or approval master records that do not exist.
- Reuse the existing Customer identity and expose separately scoped Party history in its profile. Retail sales, Product Wallet, Party Wallet, Party invoices, and Party payments remain distinct ledgers and histories.

## 2026-08-28 — v0.1.13 guided-setup and administration UX boundary

- Keep `InitialSetupStatus` as the readiness authority and derive continuation from the first authorized required incomplete item; visiting a page never marks it complete and no persisted wizard-position field is introduced.
- The canonical hierarchy is `Branch → Store(type=selling) → CashDrawer → PosShift`. Present it as Branch → Sales Outlet → Cashier Till → Shift while preserving every internal class, field, route, permission, action, and relationship.
- Settings navigation wraps existing save methods: navigation happens only after successful validation/persistence, each control targets its exact action, and read-only history has no save action.
- Branch success guidance may prefill only the existing authorized store form. Primary-outlet selection appears only through the existing mapping action and is never fabricated or silently reassigned.


## 2026-08-28 — v0.1.14 navigation, table and action-system boundary

- Server-resolved route patterns are the authority for active module and destination state; local storage controls only the valid expanded/collapsed desktop preference and is migrated to version 3 with an expanded fallback.
- `InitialSetupStatus` remains the sole readiness authority. The primary 21-step context persists on linked pages and the settings-only numbered navigator remains secondary; neither writes progress.
- Management lists share table density, focus, mobile-record, and semantic action presentation. Print/PDF/export documents and the operational POS cart remain specialized and are intentionally excluded.
- Exact `wire:target` values scope confirmed loading states to their actual action. Routes, methods, permissions, policies, schema, calculations, and workflows are unchanged.


## 2026-08-28 — v0.1.15 genuine visual acceptance boundary

- `InitialSetupStatus` remains the sole readiness authority. The UI maps all 22 unique steps, including the existing `opening-configuration` step after approved selling prices; totals remain permission-filtered and derived rather than hard-coded.
- Settings actions retain their original methods and validation. The shared loading button exposes a spinner only for its exact `wire:target`; read-only sections never receive a save action.
- Responsive management tables stop horizontal scrolling when they have transformed into mobile record cards. Desktop/tablet overflow behavior remains available where content genuinely needs it.
- Genuine evidence may read authoritative data only through GET/HEAD requests guarded by a database read-only session and request rollback, with all writable runtime state task-local. No production record mutation is permitted.

## 2026-08-29 — v0.1.16 Setup decision and Cashier boundary

- `InitialSetupStatus` remains the sole readiness calculator; `InitialSetupRouteMap` is the sole ordered group/route ownership map. Product Import is an action inside Product Cards, not a readiness step.
- Setup skip/defer choices are append-only company-scoped records with actor and timestamp. Skipping applies only to optional steps; deferring required work never marks readiness complete and is surfaced after other actionable work.
- Supplier readiness is an active Supplier plus an active preferred financial-settlement Payment Method. Payment terms and supplier-product/SKU references remain optional.
- The built-in Cashier template uses only existing POS, collection/evidence, assigned shift, catalog/stock read, and customer permissions. Existing user branch/store scopes and active till/shift assignment remain the authorization boundary; no user is auto-assigned.

## 2026-08-30 — M52 catalog identity and opening-inventory boundary

- International barcodes remain string identities and use applicable GTIN check digits. Local barcodes are exactly `0` plus the first four numeric supplier-code digits plus a six-digit per-supplier committed sequence, allocated under a database row lock and uniqueness constraint.
- Product cost and base consumer selling price are separate explicit fields; neither is inferred from the other. The shared pricing contract permits changes only from the corresponding approved explicit input.
- Opening Inventory is a dedicated immutable ledger document, not a transfer, purchase invoice, or count. It posts all lines transactionally once; corrections use a referenced reversal. Setup readiness requires an approved non-reversed opening document or the audited company zero-opening decision.

## 2026-08-31 — M53 reusable price-list boundary

- Product Card `sale_price` remains the authoritative Base Price List 0 value and is not duplicated into derived lines. Additional list percentages always calculate from that base and round upward to the next 5 EGP with decimal-scaled integer arithmetic.
- Outlet assignment resolves in the order outlet, branch default, then List 0. Branches remain organizational; only stock-bearing Sales Outlets resolve selling prices.
- Manual product/list overrides are dated, reasoned, company-scoped, and audited. They never overwrite Product Card prices; removing an override restores derived pricing immediately.
- Existing approved price versions/lines and historical invoice/sale snapshots remain immutable. The new resolver/DTO is the contract for later sales, transfer, label, and report integration rather than a rewrite of historical records.

## 2026-09-05 — Hotfix14 pricing and POS cash rounding

- The M53 automatic percentage-derived selling-price rule is confirmed as decimal-safe upward rounding to the next 5 EGP. Base List 0 prices, explicit manual overrides, costs, and historical or posted document snapshots never enter that rounding path.
- POSF-02 is owner-approved at exactly 5 EGP using the existing nearest-denomination algorithm. It applies to cash tender settlement only; electronic and other non-cash-only settlement retains the exact invoice total.
- `pos.cash_rounding_denomination` remains append-only versioned configuration. Hotfix14 appends `5.0000` only when it is not already the effective value. Missing or invalid state disables cash with a localized safe message rather than an unhandled exception; it does not disable a valid non-cash tender.


## 2026-09-05 — Hotfix15 complete-batch boundaries

- The owner-directed Hotfix15 closes all four named scopes together from exact Hotfix14. The UI removals delete presentations only; canonical pages, routes, permissions, underlying readiness logic, and lower sidebar destinations remain.
- Every downloadable import workbook is generated fresh for the authorized company, carries an exact schema/company identity and machine-header contract, and uses a dedicated `Data Entry` worksheet plus current reference worksheets. Opening Inventory pre-fills eligible current products without unit cost; only authorized location and quantity are editable. Altered schemas, company identities, headers, locked references, formulas, or out-of-scope data fail closed or produce explicit row rejections.
- Product/location assignment is configuration only and never creates stock. Opening Inventory remains a separate company/location-scoped immutable document: Product Card cost is authoritative, approval posts the ledger once under a transaction, and correction uses a referenced reversal.
- Because Product is currently a global master without a company ownership column, permanent removal is allowed only when the centralized dependency scan finds no operational/history dependency and exclusive company scope is provable. Any operational/history dependency forces archive; ambiguous multi-company ownership fails closed for central review. Direct model deletion is blocked outside the guarded removal action.
- QA/UAT/demo product discovery and cleanup are separate root-only commands. The audit is read-only; cleanup requires explicit audited IDs, an exact confirmation token, a fresh dependency/scope check inside the transaction, and a pre-cleanup production backup. Neither command is part of release activation and neither was run during candidate preparation.

## 2026-09-06 — Hotfix16 POS open-order, settlement, and receipt boundary

- Open POS orders are durable server records and remain drafts until the existing `RetailSaleAction` completes payment and posting. Their authorization identity is the exact company, branch, selling outlet, cash drawer, shift, cashier, and stable checkout token; no cross-scope fallback is permitted.
- Existing price, tax, approval, cash-rounding, shift, drawer, inventory, accounting, loyalty, audit, numbering, payment evidence, gift-card, idempotency, and immutable sale-snapshot services remain authoritative. Hotfix16 coordinates them and does not reimplement or weaken them.
- Electronic portions are explicit positive allocations. At most one cash portion is allowed and it settles only the residual after electronic portions; change derives only from cash tendered. Safe references reject likely PAN/security data and no PAN, CVV, track data, or PIN field exists.
- Receipt preview requires an approved visible sale and an active printer/template assignment matching the sale company, branch/outlet eligibility, sales-invoice document type, paper size, and locale or bilingual setting. The UI always exposes a localized browser-print fallback rather than silently routing to an unrelated printer.
- The owner authorized exactly one database-free focused Hotfix16 source-contract run plus syntax, Blade, route, locale, diff, and conditional frontend-build checks. Database and browser execution remain unauthorized.

## 2026-09-06 — Hotfix17 POS compact refund and financial reversal boundary

- Hotfix16 durable open orders and `CompletePosOpenOrderAction` remain the sole POS sale-posting path. Hotfix17 changes proportions and interaction placement without introducing a second cart, checkout, payment, inventory, tax, rounding, receipt, or idempotency implementation.
- Refunds extend the existing `RetailReturnAction` lifecycle and tables. Creation reserves remaining refundable quantities; submission remains separate from approval; a non-Super-Admin creator cannot approve or reject their own request; only an approved return can complete.
- Completion serializes on the immutable source sale, locks source/return/payment rows, revalidates company/branch/outlet, quantities, return locations, totals, taxes and Hotfix14 rounding, and posts inventory plus append-only settlement portions in one transaction. A stable completion key and payload hash make retries safe; original sale, payment, and stock rows are never rewritten.
- Any refund allocation using cash requires the cashier's current active assignment to the exact original branch, selling outlet, and drawer. Card/electronic portions never expose cash received, denominations, or change, and safe references pass the existing PAN/CVV guard.
- The current retail sale architecture has no general-ledger journal posting service. Hotfix17 therefore does not invent or duplicate a ledger: it preserves the established operational accounting contract through immutable source-linked refund settlements, reversing inventory movements, shift expected-total deductions, and a financial reversal snapshot for gross revenue, discount, tax, cash rounding, settlement allocation, and returned inventory cost.
- Refund printing resolves only an active `sales_return` template/printer matching the return company, permitted branch/outlet, paper size, and locale/bilingual setting. Browser print remains explicit fallback; cross-scope silent printing is prohibited.

## 2026-09-07 — Hotfix18 corrective activation boundary

- Hotfix18 changes no POS or refund behavior: it retains exact Hotfix17 functionality and repairs only compiler-confirmed Blade/PHP structures in the POS page, cart, checkout panel, and purchasing-return print fallback.
- The owner-verified production baseline is exact Hotfix16 while additive migration 000111 is already Ran. Activation must accept 000111 as either Ran or Pending, run the normal idempotent migration command as `rajeh`, and require it to be Ran afterward; no new migration is permitted.
- Promotion and activation must prepare `bootstrap/cache` with `rajeh:rajeh` ownership and mode `0770` before every possible Artisan call. Exact Hotfix16 remains both the required active release and failure rollback target.


## 2026-09-07 — Hotfix19 Livewire root and extracted-cache boundary

- Hotfix19 retains all Hotfix17/18 POS and refund behavior. The closed refund wizard must still render one permanent Livewire root; its dialog and every conditional state remain inside that wrapper.
- Promotion must create the extracted target bootstrap cache with mode `0775`, recursively set `rajeh:rajeh` ownership, and verify writability as `rajeh`. Activation repeats that preparation before any Artisan command and verifies writability immediately before and after cache generation.
- Exact active Hotfix16 is the only accepted activation baseline and exact rollback target. Migration 000111 is already Ran and must remain Ran; Hotfix19 adds no migration and activation does not execute a migration command.

## 2026-09-15 — Feed-store POS credit checkout (owner-approved local scope)

- The owner explicitly requires cash, unpaid credit, and partially paid POS sales. The Hotfix16 cash-residual rule remains for cash customers; an active credit customer may pay less than the residual in cash and leave the difference in the existing customer AR. Pure credit has no payment method or payment record. Existing credit-limit and approval-time settlement checks remain authoritative.
- This is a local D3 change only. Browser UAT, production, release, and deployment remain open.
