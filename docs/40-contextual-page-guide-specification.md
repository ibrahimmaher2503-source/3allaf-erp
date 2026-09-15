# TSK-004B — Contextual Page Guide and Tutorial Assistant

Status: In Progress (2026-08-04)

## Scope

This is a shared Platform capability. It does not alter business workflows, commercial policy, authorization grants, task status outside TSK-004B, or phase gates. It provides deterministic, documentation-backed guidance only; it does not call an external AI provider.

## Architecture

- `TutorialRegistry` maps only real named routes to canonical Screen IDs and stores bilingual purpose, usage, approved actions, flows, acceptance references, notes, warnings, errors, FAQ, and explicit tour selectors.
- `UserFlowRegistry` stores bilingual actor, preconditions, trigger, numbered steps, alternate/failure paths, required permissions, audit expectations, source/destination screens, completion condition, and next flow.
- `PageGuideContext` is a safe DTO. It contains Screen ID, route name, locale, filtered allowed actions, registered content, and bounded tour metadata; it never serializes a model, attachment, request, secret, token, or private path.
- `DashboardAssistantController` protects preference mutation and full guide/flow routes with authentication and server-side permission checks.
- `user_ui_preferences` is one row per user and is the database source of truth. Allowed enum values are validated server-side. Defaults are `system`, `teal`, `expanded`, `sticky`, `wide`, `comfortable`, `normal`, and reduced motion off.
- The shared dashboard and POS shells render the persistent tools. Authentication, print, public, download, and error layouts do not include them.

## User-facing behavior

The desktop controls remain fixed near the logical side edge. Arabic uses RTL logical positioning and English uses LTR positioning. Mobile uses one safe-area-aware compact launcher that opens both actions. The Page Guide drawer scrolls internally and contains purpose, usage, allowed actions, steps, fields, notes, warnings, common errors, next step, FAQ, version, and links to the full guide and related flow. The bounded tour filters missing selectors, supports previous/next/skip/finish, scrolls targets into view, supports RTL/LTR and reduced motion, and restores focus.

Appearance preferences are per-user and do not affect authorization or business behavior. Supported values are light/dark/system, approved accent palette, sidebar mode, navbar mode, content width, table density, font scale, reduced motion, and reset.

## Initial real route coverage

- UI-SYS-001: `dashboard`
- UI-SYS-002: `system.app`
- UI-SYS-003: `admin.audit`
- UI-SYS-004: `system.health`
- UI-ADM-002: `admin.settings`
- UI-ADM-003: `admin.branches`
- UI-ADM-004: `admin.stores`
- UI-ADM-005: `admin.cash-drawers`
- UI-ADM-010/011/012: `admin.authorization-baseline` (shared real route; no fake route created)
- UI-CAT-001: `catalog.products`
- UI-CAT-002: `catalog.products.show`
- UI-CAT-003: `catalog.products.create`, `catalog.products.edit`
- UI-CAT-004: `catalog.products.import`
- UI-CAT-006: `catalog.categories`
- UI-CAT-007: `catalog.brands`

Screens proposed by the product documentation but not present in the live route registry are not fabricated or registered.

## Content and security lifecycle

Content is versioned in code, traceable to Screen IDs, User Stories, Flow IDs, and acceptance references. Missing guides show a safe fallback. Full guide and flow URLs require authentication and at least one current documented permission for a source screen. Unauthorized actions are removed from the allowed-action list. No raw model values, cost, customer data, attachment content, private URLs, request payloads, or implementation secrets are passed to the assistant.

## New story and criteria

US-046 — Customize and Learn the Application Interface. An authenticated user can persist approved appearance preferences and open permission-aware localized guidance for registered screens and flows.

FLW-UI-01 — Customize Interface; FLW-HELP-01 — Open Contextual Page Guide; FLW-HELP-02 — Run Guided Tutorial.

AC-UI-06 Persistent Dashboard Controls; AC-UI-07 Per-User Appearance Persistence; AC-UI-08 Contextual Screen Detection; AC-UI-09 Permission-Aware Guidance; AC-UI-10 Approved User-Flow Guidance; AC-UI-11 Accessible Guided Tour; AC-UI-12 No Sensitive Guide Leakage; AC-UI-13 RTL/LTR Responsive Behavior; AC-UI-14 Safe Missing-Guide Fallback; AC-UI-15 Appearance Has No Business Side Effects.

## Content authoring

Tutorial definitions are stored one screen per file under `app/Modules/Platform/Tutorials/*.php`. `TutorialRegistry` discovers and validates these definitions while keeping route lookup and permission filtering centralized. The authoring contract, example, stable-selector rules, bulk-operation guidance, and verification checklist are documented in `docs/57-tutorial-content-authoring.md`.

Adding a screen therefore requires adding a definition file and stable view selectors; it does not require editing a large central switch or registry method. Existing screens keep their Screen IDs, named routes, localized content, and safe DTO boundary.

The Full Guide is intentionally broader than the interactive tour. It renders an overview, role-aware approved actions, ordered steps, field explanations, operating notes, warnings, error recovery, FAQ, related workflows, a scope disclaimer, and print-friendly navigation. The controller passes only sanitized flow summaries and permission-filtered actions; permission keys, models, private paths, credentials, and exception payloads are never rendered.

## Inventory tutorial registration — TSK-019–TSK-022

The Local/Demo Inventory slice registers the following real named routes and Screen IDs: `UI-INV-001` (`inventory.index`), `UI-INV-002` (`inventory.stock-card`), `UI-INV-003` (`inventory.movements`), `UI-INV-004` (`inventory.transfers`), `UI-INV-005` (`inventory.transfers.dispatch-page`), `UI-INV-006` (`inventory.transfers.receive-page`), `UI-INV-007` (`inventory.transfers.differences`), `UI-INV-008` (`inventory.counts`), `UI-INV-009` (`inventory.counts.entry`), `UI-INV-010` (`inventory.counts.reconcile-page`), and `UI-INV-011` (`inventory.adjustments`).

Each definition includes bilingual purpose, permission-filtered actions, ordered steps, field explanations, warnings, safe error guidance, FAQ, related `FLW-INV-01` through `FLW-INV-07`, and stable `data-guide` targets in the actual Inventory view. The full operating reference is `docs/58-inventory-tutorial-guide.md`. Inventory guidance remains Local/Demo-only and must not imply Production, UAT, hardware, printing, financial, or Owner approval.

## Deferred

No claim is made for every future screen, official UAT, production readiness, phase completion, or AI-generated guidance. Future user-facing tasks must register or update their Screen ID and approved guide metadata as part of their Definition of Done.
