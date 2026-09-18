# Current Task — R2 Inventory Reports

**Date:** 2026-09-18
**Change class:** D3 (reporting/query and UI); no schema change
**Source baseline:** `b95a6fbdeac5a8ebe2b29942592beaffac71444a`
**Worktree starting HEAD:** `812b3847047722796c31250d0221cd7fb715bb58`
**Production baseline:** recorded Hotfix39 `76e2f1fa780fb6da758a471902761ca6f40acbd8`; not live-verified or changed
**Status:** COMPLETE locally. No release action is authorized.

Implement only:

1. Stock Movement Card at `/reports/stock-movement`.
2. Inventory Valuation As Of Date at `/reports/inventory-valuation`.

Both reports use posted `stock_movements`, company and authorized-store scope, Cairo boundaries, server-side permissions, and the existing report export-job pipeline. Historical valuation sums immutable movement `total_cost`; it never substitutes the current product or balance average cost.

The owner explicitly authorized focused automated tests, local MariaDB audits, actual CSV/XLSX/PDF generation, and authenticated browser UAT for this R2 scope. SQLite, production, deployment, release, commit, push, and tag remain prohibited.
