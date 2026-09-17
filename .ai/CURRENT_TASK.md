# Current Task — P0.9 Feed-store pricing

**Date:** 2026-09-17
**Change class:** D3
**Source baseline:** `7e774a81fd4f9a68670d36814cc93d722e4594b0`
**Production baseline:** recorded Hotfix39 `76e2f1fa780fb6da758a471902761ca6f40acbd8`; not live-verified or changed
**Status:** COMPLETE locally for implementation, dedicated MariaDB migration/seed, focused automated checks, and direct integrity evidence. Browser UAT and the P0 full-day MariaDB gate remain open. No release action is authorized.

P0.9 adds company-scoped customer price-list assignment, product/unit/list prices, dated customer special prices, minimum-selling-price enforcement, immutable sale price-source snapshots, and immediate POS repricing on customer or unit change. The existing modular-monolith pricing, POS, audit, permission, transaction, and open-order paths remain authoritative.

The owner explicitly authorized focused Unit/Feature tests, `migrate:fresh --seed`, and integrity checks on a dedicated MariaDB database. SQLite, production, deployment, release, push, and tag are prohibited.
