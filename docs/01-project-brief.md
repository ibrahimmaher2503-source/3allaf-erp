ذ# 01 — Project Brief

## Project Summary

TOY & JOY Phase 1 will provide one cloud-hosted, browser-based system for retail-store, warehouse, POS, customer, and party operations. It is designed for central administration and multiple branches/stores while retaining trustworthy transaction history, role-scoped visibility, inventory integrity, controlled cash handling, and strict separation between retail and party financial activity.

## Business Context

TOY & JOY operates product retail and party services/rentals. Both activities need shared customer identity and loyalty, but they have different inventory, invoice, wallet, operational, and permission boundaries. Current work converts an approved PRD and milestone plan into a single traceable execution system for future human and AI implementers.

## Problem Statement

Without a governed system, product catalog, stock, price changes, purchases, sales, payments, returns, shifts, party bookings, rentals, and approvals can diverge across branches and lose historical traceability. An implementation must cover the full operating journey without mixing activity balances or allowing approved transactions to be silently rewritten.

## Product Vision

Deliver a fast, approachable, Arabic-first operational application that non-technical branch and warehouse users can use confidently, while managers receive reliable controls, reports, audit trails, and future extension paths.

## Phase 1 Goals

- Establish company, branch, store, drawer, tax, payment, numbering, printer, user, role, approval, and audit controls.
- Govern bilingual products, supplier relationships, barcodes, price versions, labels, purchasing, transfers, counts, and stock movements.
- Provide a dedicated branch POS, manual payment evidence, optional tax, non-stacking discounts, receipts, shifts, and daily reconciliation.
- Manage customers, shared loyalty, separated wallets, gift cards/receipts, returns, exchanges, and settlement history.
- Run party booking, editable working invoices, payments on account, operating orders, consumables, rental assets, inspection, damage/depreciation, and final settlement.
- Provide scope-filtered dashboards, alerts, reports, PDF/Excel export, audit access, and controlled production handover.
- Provide restricted offline POS continuity without weakening stock, price, wallet, loyalty, or audit integrity.

## User Groups

System Administrators, Branch Managers, Cashiers, Purchasing Officers, Warehouse Managers, Pricing Officers, Party Managers, Stock Counters, and Accountants/Reviewers. Implementation-plan labels such as Owner, Warehouse Officer, Party Officer, and Accountant/Auditor require role-name reconciliation; see `.ai/DECISIONS.md`.

## Core Activities

Configuration and access control; catalog and supplier maintenance; imports; purchasing and receipt; price approval and labels; inventory availability, transfers, counts, and adjustments; POS and shift operation; customer/loyalty/wallet management; returns and gift instruments; party booking and execution; asset lifecycle; quotations; reporting, export, audit, offline synchronization, UAT, and go-live.

## Expected Outcomes

- One trustworthy operational history for every approved source and corrective document.
- Accurate location-scoped inventory, weighted-average cost, approved prices, and traceable movements.
- Fast cashier flow with controlled discounts, tax, payments, drawer/shift reconciliation, and receipts.
- Unified customers and shared loyalty without wallet or activity leakage.
- Conflict-free party asset scheduling and accountable issue/return/damage history.
- Permission-scoped management information and auditable exceptions.

## Product Principles

1. Approved documents are immutable; corrections are referenced and auditable.
2. Retail and party items never share an order or invoice.
3. Product Wallet and Party Wallet remain separate in ledger, UI, authorization, settlement, and reporting.
4. Authorization is server-enforced and scoped by role, branch, store, document, and action.
5. UI completeness is part of feature completeness.
6. Existing framework, Flux UI, and approved mature packages are reused before custom solutions.
7. Offline data is provisional and restricted; server truth and auditable conflict review prevail.

## Success Factors

Approved master data and policies arrive before affected milestones; users can complete all acceptance scenarios; stock and financial reports reconcile to their source ledgers; critical defects are closed; role and activity boundaries are verified; print and device workflows work in target branches; backups can be restored; client phase-gate approvals are recorded.

## Major Risks

- Missing policies for tax, discounts, returns, loyalty, gifts, party deposits/cancellation, asset damage, and offline limits.
- Concurrent price, stock, wallet, numbering, or synchronization actions causing integrity errors if locks and idempotency are incomplete.
- Offline POS complexity and unconfirmed device/browser support.
- Bilingual RTL/LTR, thermal printing, label printing, scanners, and attachment capture varying by device.
- Overbuilding custom UI or infrastructure instead of reusing stable capabilities.
- Source conflicts being silently normalized rather than owner-approved.

## Scope Summary

Phase 1 includes all modules and requirements MD-01 through NFR-07 documented in `02-prd.md`. Explicit exclusions and future-ready boundaries are in `03-scope.md`.

## Technical Summary

One Laravel modular monolith using Blade, full-page Livewire, Flux UI, Tailwind CSS, restricted Alpine.js, and Vite. Laravel sessions, Form Requests, policies/gates, database transactions, locking, state transitions, queues/scheduler where needed, immutable ledgers, authorized file storage, reporting, and restricted PWA/offline boundaries are documented in `08-architecture.md`.

## UI Approach

Four layouts—Auth, Admin, Operations, and POS—inside one application. A small shared visual system supports responsive desktop/tablet use, Arabic RTL, English LTR, accessible controls, server-driven lists, complete UI states, and purpose-built print layouts. `.ai/UI_SCREENS.md` is the authoritative screen inventory.

## Current Status

Documentation baseline prepared; implementation is Not Started. Current phase is Phase 1 — Foundation, Access and Operational Controls; current milestone is DM 1.1 — Platform Foundation; progress is 0%.
