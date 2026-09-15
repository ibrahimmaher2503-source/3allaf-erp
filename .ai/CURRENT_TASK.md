# Current Task — Egyptian Feed Store ERP

**Date:** 2026-09-15
**Change class:** I4
**Source baseline:** local Git snapshot `085097e1b45a89c2ef32d179e005d2a9d09e5ee4` (`chore: establish local project baseline`), derived from `0.1.22-hotfix41` (`e4ac8a0a98d788eabcb750c797307fee0aa2efef`)
**Production application baseline:** recorded exact Hotfix39 `76e2f1fa780fb6da758a471902761ca6f40acbd8`; not live-verified in this task
**Status:** PARTIAL locally: scoped implementation, operator UI, seed, focused tests, integrity gates, and the full automated suite pass; authenticated browser UAT remains open. No release or production action authorized.

**Supervisor execution gate — 2026-09-15:** Execute one P0 phase at a time. A read-only Safety Agent must review every database migration before a writable agent begins. Each writable agent uses an isolated Git worktree and a strict file scope. The Lead reviews the diff, focused MariaDB tests, and business rules before merge. Inventory, Cash, AR, AP, Landed Cost, and Refund work requires explicit manual Lead review. P1 remains blocked until the P0 full-day MariaDB end-to-end gate passes.

**Active phase:** P0 Phase 3 is merged as `62eb64d`: inventory transfers support decimal quantities, repeated partial receipts, final receipts, explicit differences, and idempotent receipt events. No other P0 slice is authorized until the Supervisor explicitly opens it.

**POS credit checkout follow-up:** The owner-authorized D3 local fix adds pure credit checkout and partial collection through the existing payment and AR actions. Focused tests cover unpaid, partial, full, cash-customer denial, and credit limit. Authenticated browser UAT remains open.

Implement the owner-supplied 11-phase Egyptian feed-store extension without changing the modular-monolith architecture, `products.product_type`, historical stock movements, POS cash drawers, or existing wallet ledgers. Reuse the current Models, Actions, queries, transaction, locking, idempotency, stock movement/balance, product-supplier, purchasing, sales, returns, pricing, and POS infrastructure.

The current owner instruction explicitly authorizes focused Unit/Feature tests, the existing suite, `migrate --seed`, and an integrity audit for this named scope. Every database execution must use a dedicated local MariaDB database; SQLite and production are prohibited. Browser control is not part of this task.

Completion requires successful seed data plus verified unit conversion, inventory, customer AR, supplier AP, cash, foreign-key, decimal-money, orphan, and nonnegative-stock integrity checks.

