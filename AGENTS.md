# TOY & JOY Mandatory Agent Instructions

This file governs every AI coding agent working on TOY & JOY Phase 1. The repository contains the documentation baseline and a Laravel 13 application foundation. Project state is dynamic. Always read `.ai/CURRENT_TASK.md`, `.ai/CURRENT_MILESTONE.md`, `.ai/PROGRESS.md`, and the latest relevant `.ai/SESSION_SUMMARY.md` entry for the actual state.

Repository-wide mandatory policy: every Codex account and every user working through SSH, in this worktree or any future worktree based on this commit, must follow `.ai/ERP_CHANGE_POLICY.md`, including its classification, scope-escalation, verification-budget, batching, and reporting rules. No user-specific workflow or nested instruction file may weaken or bypass that policy; additional instructions may only be equally or more restrictive.

## Current Implementation Baseline

- PHP 8.4.21 and Laravel 13.23.0
- Livewire 4.3.4, Flux UI 2.15.0, Tailwind CSS 4, and Vite 8
- Laravel Fortify authentication with passkey and two-factor foundations from the official starter
- XAMPP MySQL/MariaDB managed through phpMyAdmin for local development; the production database remains an owner decision
- Responsive application and authentication shells with locale-aware Arabic RTL and English LTR direction
- Automated tests remain Not Created and Not Run by explicit project-owner directive

## Database Direction

- Use only the XAMPP MySQL/MariaDB server through phpMyAdmin for local, development, staging, and test database operations.
- SQLite is prohibited: do not create SQLite files, configure SQLite connections, run migrations or seeders against SQLite, or add SQLite test/backup fixtures.
- Use a dedicated MySQL/MariaDB database for each disposable workflow and record its name before running destructive database commands.

Inspect the current-state files identified by `AI_INDEX.md` before making any change.

## Task-Aware Required Reading

Before changing code or documentation:

1. `AGENTS.md`
2. `AI_INDEX.md`
3. Resolve `.ai/CURRENT_TASK.md` and `.ai/CURRENT_MILESTONE.md`.
4. Read only the current task section in `TASKS.md`.
5. Follow that task's routing entry in `AI_INDEX.md`.
6. Read only the referenced blockers, decisions, screen IDs, requirements, acceptance criteria, and documentation sections.
7. Expand reading only when a real dependency or contradiction requires it.

The PRD is authoritative for functional behavior. The Implementation Plan is authoritative for Development Milestone order, Delivery Criteria, and Phase Gates. Never resolve a conflict silently; record it in `.ai/DECISIONS.md` with status `Requires Owner Decision`.

## Mandatory Technology Direction

- One Laravel modular-monolith repository and one deployment.
- PHP and Laravel on the backend; Laravel sessions, Form Requests, policies, gates, transactions, queues, and scheduler where justified.
- Blade layouts and templates; full-page Livewire components for interactive screens.
- Flux UI components first, Tailwind CSS for styling, Alpine.js only for small browser behaviors, and Vite for assets.
- Browser-based Arabic-first application with full RTL and English LTR support; restricted POS continuity as a PWA.
- Admin, Operations, POS, and Auth layouts remain inside the same Laravel application.

Do not adopt Filament, Inertia.js, Vue, React, Angular, Next.js, Nuxt, a separate frontend, a separate ordinary-screen API, GraphQL, a full SPA, headless architecture, or microservices.

## Working Rules

- Inspect the entire repository before concluding that a feature is absent.
- Work on only the current milestone and its linked tasks. Do not advance before its Delivery Criteria and Phase Gate are satisfied.
- Preserve every Requirement ID and business rule. Do not delete, merge away, or silently change a requirement.
- Record missing facts as assumptions, open decisions, or blockers with impact and affected milestones.
- Deliver the smallest reviewable vertical slice with a complete UI. A backend-only business feature or placeholder UI is not complete.
- Use Laravel conventions. Do not add repository patterns, generic services, base classes, traits, internal packages, event-driven architecture, or generalized workflow/form/table builders without a documented need.
- Apply authorization on the server through policies and gates; hiding a control is not authorization.
- Use database transactions for financial, inventory, wallet, loyalty, gift-card, shift, and document-approval operations. Use row locks or equivalent concurrency control where races can corrupt results.
- Approved documents are immutable. Correct them through referenced reversal, return, cancellation, or adjustment documents. Use logical deletion only where permitted.
- Keep retail products and party services/assets out of the same order or invoice. Keep Product Wallet and Party Wallet ledgers, visibility, settlement, and reporting separate.
- Never claim implementation, verification, or testing that did not occur. Update `.ai/` status files after every task.
- Every agent must append a factual entry to `.ai/SESSION_SUMMARY.md` at the end of each session that changes the repository or project state. Include the session date, task, work completed, verification actually run, remaining blockers or next action, and whether code, tests, browser checks, commits, or pushes occurred. Do not rewrite prior session entries except to correct a factual error.

## Reuse Before Custom Build

Do not build from scratch when Laravel, Flux UI, or an approved mature package provides the capability. This applies to authentication, password reset, email verification, roles/permissions, audit logging, Excel import/export, PDF generation, barcodes, backups, media handling, uploads, tables, pagination, filtering, sorting, date/time pickers, searchable selects, modals, drawers, toasts, alerts, charts, sidebar, breadcrumbs, form controls, loading states, and empty states.

If a standard component cannot meet a documented requirement, record why, implement the smallest extension, and do not create an internal UI library. Package names and versions require compatibility and maintenance review at implementation time and a decision-log entry.

## UI Rules

- Reuse the common application shell, navigation, page header, breadcrumbs, filters, data table, form sections, summary cards, status badges, dialogs, state panels, details, status timeline, audit panel, and print layouts.
- Every relevant screen must handle default, loading, empty, error, success, disabled, permission-denied, validation, confirmation, and unsaved-change states.
- Large tables use server-side pagination, filtering, sorting, indexed search, eager loading, and clear row actions; never load an unbounded dataset into the browser.
- Forms use logical sections, explicit bilingual fields, server validation, inline errors, required indicators, duplicate-submission protection, and confirmations for sensitive actions.
- All screens must be responsive and support Arabic RTL and English LTR. Printed documents must be verified in every required format.
- POS is a dedicated, fast, barcode-first, keyboard- and touch-friendly Livewire interface. It must preserve cart content on errors and display branch, selling store, drawer, shift, totals, and connectivity status.
- Alpine.js or small TypeScript may be used only for barcode events, shortcuts, print handling, IndexedDB, service worker, offline queue, connectivity detection, and local device integration.

## Automated Testing and Browser-Control Directive

The current task verification directive is determined by `.ai/CURRENT_TASK.md`, `.ai/DECISIONS.md`, and explicit owner instructions. Automated tests are deferred unless those current sources explicitly authorize a named scope.

- Do not create PHPUnit, Pest, unit, feature, integration, browser, end-to-end, Playwright, or Cypress tests.
- Do not run `php artisan test`, Pest, PHPUnit, Playwright, Cypress, or any automated suite.
- Automated tests are not a Definition of Done condition under the current directive.
- During future implementation, perform scenario-based manual browser verification, permission checks, data-integrity checks, print checks, responsive/RTL/LTR checks, backup/restore checks, and UAT as specified in `docs/14-test-plan.md`.
- Record only actual results in `.ai/TEST_RESULTS.md`.

### Historical Local Visual Verification Exception - 2026-08-03

The project owner explicitly authorized browser-control tooling, screenshots, and authenticated local-only visual verification for the named Phase 1 work at that time. This historical exception does not apply to later tasks unless their current authorization explicitly says so. It does not apply to production and does not authorize final TSK-008 grants.

### Historical TSK-008 Automated Verification Exception - 2026-08-03

The project owner explicitly authorized focused local PHPUnit feature tests and Playwright checks for TSK-008 only. This named, dated exception is not generalized to other tasks, production changes, commits, pushes, or authorization behavior for modules that did not exist.

Browser-control or automated testing is prohibited unless the active task has explicit current authorization.

## Required Task Closure

For each implementation task: confirm linked requirements and acceptance criteria, complete all listed UI states, apply validation and server authorization, review audit and concurrency requirements, perform the prescribed manual verification, update documentation and `.ai/` files, append the required factual session summary, and leave no unresolved critical blocker.
