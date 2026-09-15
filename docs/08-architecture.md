# 08 - Application Architecture Minimal

## TSK-004B Shared Platform Boundary

The contextual Page Guide and Appearance Customizer are shared Platform capabilities. Registry content, safe context DTOs, per-user UI preference persistence, and server-side permission filtering live under the existing Laravel modular-monolith Platform boundary. See `docs/40-contextual-page-guide-specification.md`.

## 1. Authority and Purpose

This is the authoritative technical architecture for TOY & JOY Phase 1. It implements the approved Laravel modular-monolith direction and supplements the PRD, Implementation Plan, canonical authorization matrix, approved decisions, and policies. It does not replace a higher-authority source.

The application remains one Laravel repository, deployment, database, authentication system, and asset build. Modules are ownership boundaries, not services. Do not introduce microservices, a separate frontend, a normal-screen API, GraphQL, or a full SPA.

## 2. Architecture Rules

- Module owns business rules.
- Action owns one meaningful use case or mutation.
- Policy or Gate authorizes the exact action and scope.
- Query objects are used only for complex or reusable reads.
- Model owns persistence, relationships, casts, and small local scopes.
- Blade owns layout and print structure; Livewire owns interactive server state; Flux UI is the primary UI toolkit.
- Controllers and Livewire actions validate, authorize, call an Action or Query, and render feedback. They do not own posting, pricing, stock, wallet, approval, numbering, or settlement rules.

Do not add a generic repository layer, God services, speculative base classes, unused traits, empty module folders, or a module service provider without a demonstrated Laravel integration need.

## 3. Module Boundaries

`Platform` owns company/configuration, locale, branches, stores, drawers, users, roles, permissions, scopes, audit, approvals, attachments, document numbering, printers, and shared operational controls.

Future modules are created only when their approved task starts: Catalog, Pricing, Purchasing, Inventory, Retail, CashControl, Customers, Parties, Assets, Quotations, Reporting, and Offline. They are not created as placeholders.

Cross-module writes go through the owning module's Action. Retail and Party documents, stores, wallets, ledgers, settlement, and reporting remain separate as required by the PRD.

## 4. Current Implemented Foundation Structure

Only existing Foundation Platform files are organized under the module boundary:

```text
app/
  Actions/
    Fortify/                         # Starter authentication; remains unchanged
  Http/
    Middleware/                      # Shared request/locale middleware
  Models/
    User.php                         # Laravel/Fortify identity; remains unchanged
  Modules/
    Platform/
      Actions/                       # Existing Platform use cases
      Models/                        # Existing Platform persistence models
  Providers/                         # Laravel/Fortify/Gate registration

routes/
  web.php                            # Small application loader
  platform.php                       # Existing Platform routes
  settings.php                       # Starter account settings routes

resources/views/
  components/                        # Shared Blade components
  layouts/                           # Shared auth/app/POS/print layouts
  platform/                          # Existing Platform admin and system pages
  pages/auth/                        # Starter Fortify views
  pages/settings/                    # Starter account settings views
  pages/pos/                         # Current restricted POS shell
```

The current Foundation contains Platform actions, Platform models, Platform pages, starter Fortify actions/views, shared layouts/components, and a restricted POS shell only. No unimplemented business module is represented by a folder.

## 5. Current Platform Responsibilities

Platform Actions contain current settings, branch/store mapping, drawer, authorization assignment, and health-status use cases. They retain their existing transactions, validation, Gate authorization, append-only settings audit writes, and correlation IDs.

Platform Models retain existing relationships and simple scope methods. `User` remains in `App\\Models` because it is the Laravel/Fortify authenticated identity and is shared across all modules.

The current `SettingsAuditLog` is an append-only Foundation record. The shared `audit_logs` capability, approval workflow, attachment controls, and cross-module audit abstraction are TSK-009 scope and must not be extracted early.

`DocumentSequence` remains a Platform configuration/master model. It is not an allocator until an approved use case requires concurrency-safe allocation.

## 6. Authorization and Scope

Authorization is server-side. Route middleware provides broad entry control; Gates and Policies provide exact permission, branch, store, ownership, state, approval, override, and field control. Hiding UI controls is never a security boundary.

Existing canonical Gate behavior, current permission names, scope queries, and user authorization assignment remain unchanged during structural refactors. New entity-specific Policies are added only when the relevant entity and exact authorization need exist.

## 7. States, Audit, Transactions, and Concurrency

Controlled documents use explicit state maps and PHP Enums when their module introduces states. Approved, final, or closed records are immutable; corrections use referenced reversal, cancellation, return, or adjustment records.

Actions wrap financial, inventory, wallet, loyalty, gift-card, shift, approval, and finalization writes in transactions with the necessary locks or version checks. No refactor may move this logic into views, controllers, or generic services.

## 8. Routes and Views

Routes are split only when a real module has routes. `routes/web.php` is the loader; `routes/platform.php` owns current Platform routes without changing paths, route names, middleware, or permissions.

Platform Blade and Livewire views live under `resources/views/platform`. Shared layouts and components remain shared. The current POS shell and starter authentication/settings screens remain in their existing boundaries until a real Retail or Auth change requires otherwise.

Every user-facing change preserves Arabic RTL, English LTR, responsive layouts, loading, empty, error, denied, validation, confirmation, and safe disabled states where applicable.

## 9. Incremental Refactor Rules

Refactor in small reversible slices:

1. Inventory actual files and references.
2. Move one boundary at a time.
3. Repair namespaces, imports, route registrations, view aliases, and component references.
4. Run permitted syntax, route, view, and build checks after that slice.
5. Perform required manual verification before declaring a task complete.

Do not change business behavior, authentication behavior, authorization behavior, database schema, route URLs, route names, or permissions merely to improve folder structure. Do not proceed to a future module because its planned path appears in this document.

## 10. Packages and Documentation Routing

Use Laravel and Flux facilities first. Select at most one mature primary package per approved capability after compatibility, maintenance, security, license, overlap, RTL impact, and exit-path review.

`AI_INDEX.md` is the task-aware documentation router. It limits reading to the active task's required context but never weakens PRD, security, authorization, audit, transaction, concurrency, or acceptance requirements.

## 11. Production Performance Baseline

Performance is an architectural requirement. The application must scale beyond local demo data without introducing a second frontend stack, a generic repository layer, speculative caches, or unbounded browser state.

Vite production builds must remain hashed, minified, cacheable, and free of unused global dependencies. The common JavaScript entry stays small. Heavy optional capabilities, including charts, specialized scanner integration, offline behavior, large exports, calendars, and editors, are loaded only by the pages that need them. Blade renders meaningful HTML before optional JavaScript is available. Tailwind and Flux remain the single shared CSS/component foundation; expensive blur, backdrop, icon-font, and duplicate component styling are not introduced.

Operational lists are server-driven. They use authorized scoped queries, narrow selects, pagination appropriate to the ordering UX, indexed filters, and eager loading only for relationships rendered by the screen. `Model::all()` and unbounded `get()` are not used for operational tables. Complex reusable reads may use a module Query object, but simple CRUD stays Eloquent. Each Livewire render is treated as repeatable: avoid repeated aggregates, duplicate queries, lookup queries in Blade, polling without a documented need, and full-page updates for isolated interaction.

Indexes and unique constraints follow documented invariants and measured query patterns, especially scope, foreign-key, status, ordering, identifier, source-reference, and report-time predicates. Do not add indexes to every column or cache transactional truth. Cache only safe, explicitly invalidated, correctly scope-keyed reference or reporting data after measurement. Stock, price, wallet, loyalty, gift-card, transfer, settlement, and other transactional values remain authoritative in the database.

Images remain out of the common bundle, use dimensions or aspect ratios where practical, and use lazy loading only for non-critical media. Fonts use the minimum approved families and weights. The current request correlation ID remains available for diagnostics; logs and monitoring never include secrets or unnecessary sensitive data.

## 12. Production Runtime and Deployment Performance

The production deployment environment, not application code, owns PHP OPcache, HTTP compression, HTTP/2 or HTTP/3, PHP runtime worker configuration, TLS, static-asset caching, upload and timeout limits, reverse-proxy behavior, and any CDN decision. Before go-live, verify OPcache in the actual serving runtime, install Composer dependencies with the approved optimized production autoloader, build Laravel's version-appropriate configuration, route, view, and event caches after environment configuration is finalized, and build Vite production assets.

Fingerprinted public assets may receive long-lived immutable caching. Authenticated HTML, authorized data, payment evidence, customer data, and sensitive attachments must not receive that public cache policy. Enable Brotli with gzip fallback where the selected web server or CDN supports it; do not configure Apache directives unless Apache is the approved production server.

Redis, queues, cache drivers, object storage, CDN, worker counts, production database engine, scheduler process, monitoring provider, and host-specific settings remain deployment decisions until the owner approves the production environment. No local configuration implies production readiness.

## 13. Queues, Scheduler, and Data Integrity

Use queues for non-critical, potentially expensive work such as large imports, exports, PDF or label batches, image processing, notifications, backups, and scheduled reporting artifacts. Queued work must define idempotency, retries, failure handling, timeout, observability, duplicate-execution behavior, and required authorization context.

Core integrity commits remain synchronous and transactional. Do not queue approved stock posting, payment settlement, wallet or loyalty movement, gift-card redemption, final price activation, or document state transitions when a delayed commit could report success before integrity is guaranteed. The scheduler handles recurring non-request-critical maintenance only and its production runner is documented during deployment planning.

## 14. Performance Verification and Release Gate

Do not call the application fast based on an empty local database. Before production readiness, use representative authorized data volumes to inspect first meaningful render, navigation, query and Livewire request counts, duplicate queries, response and DOM size, asset transfer size, layout shifts, page-specific code, slow server actions, and relevant database execution plans.

The production release gate verifies: production environment and debug settings; Composer and OPcache optimization; Laravel and Vite caches; compression and asset headers; indexes and unique constraints against real query patterns; paginated lists; image and font behavior; queue and scheduler operations where enabled; request-ID diagnostics; backups and restore; monitoring; and the absence of obvious frontend, network, or query bottlenecks. This gate is implemented and evidenced through the approved release task, not assumed by local development.
## Guided setup presentation boundary (v0.1.13)

The setup journey is a presentation layer over `InitialSetupStatus` and existing permission-protected routes. It does not persist page position or completion flags: continuation is derived from authoritative readiness on each request. Contextual links carry only non-authoritative navigation hints.

The operational location chain remains `Branch → Store(type=selling) → CashDrawer → PosShift`. UI copy presents Store(type=selling) as “Sales Outlet” and CashDrawer as “Cashier Till”; domain identifiers and mutation boundaries remain unchanged.


## v0.1.14 shared navigation, table, and action controls

Nested create/edit/detail routes resolve through `ApplicationNavigation` patterns to one authorized list/module destination. The active group is server-opened and cannot be hidden by a stale client preference; only a validated version-3 expanded/collapsed preference is retained. Linked setup pages render the readiness-derived primary 21-step context, while Settings adds a compact secondary section navigator. Management lists inherit shared density, focus, responsive record-card, and semantic action classes; exact action targets isolate Livewire loading state. See `docs/architecture/v014-navigation-table-action-system.md`.
