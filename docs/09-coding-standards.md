# 09 — Coding Standards

These standards govern future implementation. They do not claim that a Laravel project or code exists.

## Laravel and PHP

- Follow the actual project's Laravel version conventions, PSR-12, strict typing where consistent with the project, framework naming, and configured formatter/static checks if later approved.
- Prefer routes, middleware, controllers/actions, Form Requests, Eloquent relations/scopes, policies/gates, Blade, Livewire, migrations, seeders, jobs, notifications, and scheduler conventions.
- Controllers are thin coordinators. Livewire components own UI state and orchestration, not reusable business rules or direct uncontrolled ledger mutations.
- Use focused Action classes for complex transactional use cases. Use a Service only for genuine shared domain/integration logic. Do not create generic repositories, base services, helpers, traits, DTO hierarchies, or abstractions without repeated need.
- Depend on clear module contracts; do not reach across modules with duplicated raw queries or cyclic service dependencies.

## Folder and Naming Conventions

- Organize application code by Laravel convention plus explicit module/use-case namespaces documented when the project is created.
- Classes are singular and intention-revealing; tables are plural; foreign keys use `<entity>_id`; enums name the business concept; actions use verbs such as `ApprovePurchaseInvoice`.
- Use the canonical terms in this documentation. `Store` is the logical stock location pending DEC-021; aliases appear only in localized UI copy after owner approval.
- IDs from requirements, stories, flows, screens, acceptance criteria, security items, and tasks appear in relevant code/document comments or PR/task metadata where useful, not as noisy inline comments everywhere.

## Validation and Authorization

- Validate all client input on the server through Form Requests or equivalent Livewire validation; client checks improve UX only.
- Authorize before querying sensitive records where practical and before every action. Policies/gates include role, branch, store, activity, document state, ownership, approval/override, limits, and field sensitivity.
- Never trust IDs, prices, totals, balance, current state, permissions, branch/store, or offline flags sent by the browser. Re-read current server records.
- Normalize and validate phone, codes, barcodes, dates, money, quantities, files, and bilingual required fields consistently.
- Return actionable localized validation feedback without exposing secrets, stack traces, cross-scope existence, or payment/customer-sensitive data.

## Transactions, Locking, and Idempotency

- Wrap purchase receipt, stock posting, transfer dispatch/receipt, count reconciliation, sale approval, payment, return/exchange, shift close, price activation, wallet/loyalty/Gift Card movement, party finalization, asset reservation/transition, numbering, approval, and offline sync posting in correctly bounded database transactions.
- Use row locks, unique constraints, compare-and-swap/version columns, or engine-appropriate exclusion/locking for races. Do not hold transactions while waiting on network calls or user input.
- Every retryable/offline/import/job operation has a deterministic idempotency strategy. A duplicate request returns the prior result or a safe conflict, never a second posting.
- Side effects such as notifications or large exports occur after commit/outbox-equivalent reliability where appropriate; integrity ledgers commit with the source transaction.

## Document States and Immutability

- Represent states with enums and an explicit allowed-transition map per document. Validate current state inside the transaction.
- Approved/final/closed documents and their lines/totals are immutable. No direct edit or physical delete.
- Reversal, cancellation, return, exchange, adjustment, and correction are new source-linked documents/movements with mandatory reason and permission.
- Logical delete applies only to eligible unused/draft master data and is audited.
- Document numbers are allocated atomically from configured sequences and remain unique; never calculate `max + 1` without locking.

## Money and Quantity

- Use fixed decimal/database money values and explicit rounding rules. Never use floating point for money or points that require exactness.
- Store line calculation inputs and results needed to reproduce printed totals: original price, one discount type/value, net, tax, and total.
- Quantity precision is fixed decimal; validate whole quantity when a product disallows fractions.
- Centralize approved calculation policies without creating a generic rules engine. Keep activity-specific rules explicit.

## Ledgers and Separation

- Append stock, loyalty, Product Wallet, Party Wallet, Gift Card, payment, and audit history. Never update ledger entries to “fix” a balance.
- `stock_balances` and summary balances must reconcile to ledgers and are not edited by CRUD UI.
- Retail and party document line types are separate and validated in application and database constraints where possible.
- Product Wallet and Party Wallet use separate models/tables/actions/policies/screens. Do not create a generic wallet transfer or shared unscoped wallet component.
- Loyalty is shared but every movement records activity, source, rule/version, expiry, and idempotency.

## Queries and Performance

- All high-volume lists use server-side pagination, filters, sorting, and bounded indexed search. Never load every record into Livewire/browser memory.
- Select required columns, eager-load known relations, aggregate in the database, avoid N+1 queries, and enforce scope before aggregation.
- Validate sort/filter allow-lists. Cap ranges/export sizes. Use queued exports only with progress, authorization, expiry, and idempotency.
- Add indexes from actual query patterns and inspect query plans before premature cache/denormalization.
- Cache only explicitly scoped non-authoritative data. Stock, price, wallet, loyalty, and Gift Card settlement always revalidates server truth.

## Blade and Livewire

- Blade handles layouts, static composition, components that are genuinely reused, and print views. Escape output by default; render sanitized HTML only through an approved path.
- Prefer full-page Livewire components for interactive pages. Keep public properties minimal, validated, type-safe where supported, and free of sensitive hidden data.
- Use stable keys for dynamic lists, debounce/throttle expensive search, preserve form/cart state on recoverable errors, and guard double submission with disabled/loading state plus server idempotency.
- Complex document edit belongs on a page, not an oversized modal. Child components exist only for independently useful interaction.

## Flux UI and Tailwind

- Use an existing Flux UI component before custom markup for controls, selects, comboboxes, tables, pagination, dialogs, drawers, tabs, dates/times, uploads, toasts, badges, breadcrumbs, navigation, cards, charts, loading, empty, error, and confirmations.
- If Flux cannot satisfy a requirement, record the gap and build the smallest accessible extension; do not create a parallel UI library.
- Use shared semantic Tailwind tokens/patterns and logical direction utilities. Avoid page-specific design systems, uncontrolled arbitrary values, `!important` escalation, and decorative motion.
- Every interactive control needs a label/name, keyboard access, visible focus, sufficient contrast, disabled/loading semantics, and screen-reader feedback where appropriate.

## Alpine.js, TypeScript, and Vite

- Use Alpine.js or small TypeScript only for scanner input, keyboard shortcuts, print handling, connectivity, IndexedDB, service-worker messages, and local-device integration that Livewire/Flux cannot cleanly provide.
- Do not add Vue, React, Inertia, client routers, or client state stores.
- Vite entry points stay minimal and versioned. No inline secrets or environment-sensitive private values reach browser bundles.
- Service worker and offline schemas are versioned; updates, expiration, logout cleanup, and migration failures have safe UI paths.

## Localization, RTL, and LTR

- No user-facing hard-coded strings; use translation keys and approved business terminology.
- Arabic is the primary acceptance direction. Use `lang`/`dir`, CSS logical properties, localized dates/numbers, and intentional icon mirroring.
- Bilingual master fields are explicit; do not overload one column with mixed language content.
- Verify every screen and print layout in Arabic RTL and English LTR, including tables, dialogs, date/time controls, totals, receipts, and validation.

## Files, Imports, Exports, and Printing

- Validate file size, MIME and signature, extension, image dimensions where relevant, hash, purpose, and authorization; use safe generated names and protected storage. Reject executable/unsafe content.
- Import into a staged batch: map, validate, preview, approve, then write valid records in safe chunks. Invalid rows never write; output a sanitized error report.
- Escape Excel cells that could execute formulas. Exports and file downloads reauthorize on every access and expire where generated.
- Print views snapshot authoritative document data, include required identifiers/status, support target paper size/direction, and never mutate a document.

## Error Handling, Logging, and Audit

- Use domain-safe exceptions/results for expected validation/conflict outcomes and centralized handling for unexpected failures.
- User errors explain recovery without leaking internals. Production debug is off.
- Logs use correlation IDs and redact passwords, tokens, secrets, full evidence, unnecessary customer data, and protected offline payloads.
- Mandatory audit records include actor, time, session/device when available, scope, source, reason, and protected before/after values. Audit is append-only.

## Reuse Before Building

Do not build the following from scratch when Laravel, Flux UI, or one approved mature package already solves it: permission/role management, audit log engine, Excel import/export engine, PDF engine, barcode generator, backup, media library, upload component, data table, pagination, filters, sorting, date/time picker, searchable select, modal, drawer, toast/alert, charts, sidebar, breadcrumbs, form controls, loading/empty states, authentication, password reset, or email verification.

Package selection requires a decision record and compatibility/maintenance/security/license/footprint/overlap review. Do not install two packages for the same concern or a heavy package for one small feature.

## Prohibited Overengineering

No speculative repository pattern, generic service, base-class hierarchy, helper/trait sprawl, one-use shared component, internal package proliferation, unused API, event-driven architecture, microservices, custom design-system framework, generic workflow builder, permission builder, form builder, or table builder. Use the simplest solution that preserves requirements, data integrity, authorization, maintainability, UI quality, and delivery speed.

## Verification Directive

Automated tests are currently deferred by explicit project-owner directive. No automated test code shall be created or executed unless a new explicit instruction changes this policy. Do not create PHPUnit, Pest, unit, feature, integration, browser, Playwright, Cypress, or end-to-end tests, and do not run any automated suite.

During future implementation, every task requires the scenario-based manual browser checks listed in `TASKS.md`, `docs/12-acceptance-criteria.md`, and `docs/14-test-plan.md`. Record exact actual results only in `.ai/TEST_RESULTS.md`.

## UI Completion Rule

Every functional task includes a complete UI unless it is explicitly documented infrastructure. Default, loading, empty, error, success, disabled, permission-denied, validation, confirmation, and unsaved-change states are required where relevant, plus responsive desktop/tablet/mobile behavior, Arabic RTL, English LTR, search/filter/sort/pagination, and print/audit context. A placeholder or non-responsive screen is not done.
