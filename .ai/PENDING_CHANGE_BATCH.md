# Pending Change Batch

## 2026-09-14 — Hotfix39 Customer Notes Batch 3

- **I4 / release candidate / undeployed:** Compact product/customer/supplier masters, incomplete-product Draft/Resume, supplier item identity, dynamic multi-identifier lookup, branch-priced product matrix reuse, opening-inventory header/keyboard grid/paste/XLSX/totals/draft/post/reversal/zero workflow, stable customer code, loyalty help, and structured supplier contacts.
- Exact baseline is Hotfix38 `45d9e78c757cd83a596b11d613475dbdc441d2c3`. Backend export routes/services, stored supplier payment terms, existing pricing contracts, tenant permissions, and historical accounting/inventory records remain intact.
- Activation must prove 000111 and 000112 are already `Ran`, execute only migration 000113, and retain its nullable backward-compatible additions if exact Hotfix38 application rollback occurs.

## 2026-09-14 — Hotfix38 Customer Notes Batch 2

- **I4 / release candidate / undeployed:** Accessible payment/tax modals; UI-hidden tax validity dates; nullable product tax assignment; branch/document continuous numbering; consolidated printer/template management with 15 document types and an accurate relevant-printer count.
- Exact baseline is Hotfix37 `0f0e7aa18d47986f6805c89b3494f4f55042e151`. The only schema change is forward-compatible migration 000112. Historical invoice tax snapshots and accounting calculations are untouched.
- Activation must run exactly migration 000112 after proving 000111 is already `Ran`. Application rollback restores exact Hotfix37 and deliberately retains the nullable schema addition.

## 2026-09-14 — Hotfix37 Customer Notes Batch 1

- **V1 / release candidate / undeployed:** Reusable accessible `!` contextual help; sidebar hover/focus/active/scroll/state/keyboard repair; duplicate-destination suppression and requested supplier/customer/product ordering; branch-screen action/timezone cleanup; company timezone and persisted default list-filter visibility in General Settings; user/role total-card removal with unchanged authorization behavior.
- Exact baseline is Hotfix36 `c5f6d57150a04d95915a72f5146a2aebd369bc83`. No migration or business-data change is included. Optimized unexecuted promotion/activation scripts are restricted to that baseline and exact rollback target.

**Status:** HOTFIX19 CORRECTIVE CANDIDATE — VERIFIED — NOT DEPLOYED
**Scope:** v0.1.22-hotfix19 POS Livewire root and extracted-release cache repair, retaining all Hotfix17/18 work
**Opened after:** operator-verified rollback to exact `v0.1.22-hotfix16`
**Source baseline:** exact Hotfix18 commit `c0fa3ed3623f7c259ba2c29109c08184bfa42eb4`
**Production baseline:** owner-confirmed: exact Hotfix16 commit `31883f5c823458d30738708ec96e1d02337f4c9b`, migration 000111 already Ran, maintenance OFF.
**Baseline disposition:** Hotfix16 remains active and is the exact Hotfix19 activation prerequisite and rollback target. It was not rechecked.

Do not clear or archive this ledger until an authorized operator confirms successful Hotfix19 production activation and smoke verification.

## Pending Changes

| Classification | Short description | Commit | Affected files | Status |
|---|---|---|---|---|
| V1/D3 | Full Hotfix17 compact POS, checkout, customer/product/scanner, order-tab and payment-card redesign | Retained in Hotfix19 | Hotfix17 POS Livewire/components/views | VERIFIED — NOT DEPLOYED |
| D3/I4 | Full Hotfix17 source-linked refund, inventory/payment/accounting/authorization/audit/idempotency and receipt workflow | Retained in Hotfix19 | Migration 000111; return action/calculator/models/components/views/routes | VERIFIED — NOT DEPLOYED |
| V1/D3 | Hotfix18 Blade/PHP compiler repairs without UI or authorization bypass | Retained in Hotfix19 | POS page/cart/checkout and purchasing-return print Blade templates | VERIFIED — NOT DEPLOYED |
| V1 | Permanent closed-state Livewire root for the retained refund wizard | Hotfix19 release commit | `resources/views/livewire/pos/refund-wizard.blade.php` | VERIFIED — NOT DEPLOYED |
| I4 | Post-extraction bootstrap cache creation, recursive `rajeh:rajeh` ownership, mode `0775`, pre/post cache-generation writability, application-user Artisan, already-Ran 000111 preservation and exact Hotfix16 rollback | Hotfix19 operator scripts | Promotion and activation scripts | VERIFIED — ACTIVATION PREFLIGHT DEFERRED |

Production access, deployment, backup execution, migration execution, active-symlink modification, service operation, browser testing, tag, push, and remote contact are outside candidate preparation and remain unexecuted.
## 2026-09-12 — Egyptian Arabic locale completion batch

- **V0 / completed locally / undeployed:** Rewrote all 6,115 `ar-EG` JSON values and 244 PHP locale leaves, added contextual Egyptian render wording and human-readable source labels, and preserved `ar`/`en`. Commit recorded after final verification. No deployment or packaging.

## 2026-09-13 — Hotfix36 Egyptian Arabic quality-repair batch

- **V0 / release candidate / undeployed:** Integrated the direct-child localization repair onto exact Hotfix35, retaining all 6,115 JSON keys, 244 PHP leaves, unchanged `ar`/`en`, and unchanged functional boundaries. Offline package and fail-closed exact-Hotfix35 operator workflow prepared; no production or operator action executed.
