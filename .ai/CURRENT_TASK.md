# Current Task — Egyptian Feed Store ERP

**Date:** 2026-09-17
**Change class:** D3
**Source baseline:** checked-out local `main` commit `4a8ecbdf3a0c70b58ec7b86f9f35530107c5afb5`, which contains merged P0 Phase 7; the owner-supplied `085097e1b45a89c2ef32d179e005d2a9d09e5ee4` reference is historical rather than the current checkout.
**Production application baseline:** recorded exact Hotfix39 `76e2f1fa780fb6da758a471902761ca6f40acbd8`; not live-verified in this task
**Status:** P0.8 COMPLETE locally for automated and MariaDB integrity evidence. Authenticated browser UAT was explicitly outside this task; the full-day MariaDB end-to-end gate remains open. No release or production action authorized.

**Supervisor execution gate — 2026-09-15:** Execute one P0 phase at a time. A read-only Safety Agent must review every database migration before a writable agent begins. Each writable agent uses an isolated Git worktree and a strict file scope. The Lead reviews the diff, focused MariaDB tests, and business rules before merge. Inventory, Cash, AR, AP, Landed Cost, and Refund work requires explicit manual Lead review. P1 remains blocked until the P0 full-day MariaDB end-to-end gate passes.

**Active phase:** P0.8 credit settlement is implemented and verified locally without a schema migration. Customer and supplier oldest-first proposals remain editable before posting, customer/supplier profiles expose document-derived AR/AP, and same-company supplier allocation is enforced for cash and non-cash payments. P1 remains blocked until the P0 full-day MariaDB end-to-end gate passes.

**POS credit checkout follow-up:** The owner-authorized D3 local fix adds pure credit checkout and partial collection through the existing payment and AR actions. Focused tests cover unpaid, partial, full, cash-customer denial, and credit limit. Authenticated browser UAT remains open.

Implement the owner-supplied 11-phase Egyptian feed-store extension without changing the modular-monolith architecture, `products.product_type`, historical stock movements, POS cash drawers, or existing wallet ledgers. Reuse the current Models, Actions, queries, transaction, locking, idempotency, stock movement/balance, product-supplier, purchasing, sales, returns, pricing, and POS infrastructure.

The current owner instruction explicitly authorizes focused Unit/Feature tests, the existing suite, `migrate --seed`, and an integrity audit for this named scope. Every database execution must use a dedicated local MariaDB database; SQLite and production are prohibited. Browser control is not part of this task.

Completion requires successful seed data plus verified unit conversion, inventory, customer AR, supplier AP, cash, foreign-key, decimal-money, orphan, and nonnegative-stock integrity checks.



