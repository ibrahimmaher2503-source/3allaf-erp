# Current Task — Egyptian Feed Store ERP

**Date:** 2026-09-15
**Change class:** I4
**Source baseline:** `0.1.22-hotfix41` (`e4ac8a0a98d788eabcb750c797307fee0aa2efef`)
**Production application baseline:** recorded exact Hotfix39 `76e2f1fa780fb6da758a471902761ca6f40acbd8`; not live-verified in this task
**Status:** PARTIAL locally: scoped implementation, operator UI, seed, focused tests, integrity gates, and the full automated suite pass; authenticated browser UAT remains open. No release or production action authorized.

**POS credit checkout follow-up:** The owner-authorized D3 local fix adds pure credit checkout and partial collection through the existing payment and AR actions. Focused tests cover unpaid, partial, full, cash-customer denial, and credit limit. Authenticated browser UAT remains open.

Implement the owner-supplied 11-phase Egyptian feed-store extension without changing the modular-monolith architecture, `products.product_type`, historical stock movements, POS cash drawers, or existing wallet ledgers. Reuse the current Models, Actions, queries, transaction, locking, idempotency, stock movement/balance, product-supplier, purchasing, sales, returns, pricing, and POS infrastructure.

The current owner instruction explicitly authorizes focused Unit/Feature tests, the existing suite, `migrate --seed`, and an integrity audit for this named scope. Every database execution must use a dedicated local MariaDB database; SQLite and production are prohibited. Browser control is not part of this task.

Completion requires successful seed data plus verified unit conversion, inventory, customer AR, supplier AP, cash, foreign-key, decimal-money, orphan, and nonnegative-stock integrity checks.

