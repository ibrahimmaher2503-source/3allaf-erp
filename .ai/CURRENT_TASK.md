# Current Task — R1 Sales Reports

**Date:** 2026-09-17
**Change class:** D2
**Source baseline:** `b95a6fbdeac5a8ebe2b29942592beaffac71444a`
**Production baseline:** recorded Hotfix39 `76e2f1fa780fb6da758a471902761ca6f40acbd8`; not live-verified or changed
**Status:** COMPLETE locally for implementation, focused MariaDB verification, export generation, integrity checks, and authenticated browser UAT. No release action is authorized.

R1 adds permission-scoped Sales Summary and Sales by Product reports, plus embedded customer sales analysis on the existing customer profile. All three surfaces reuse one reporting query path, approved sales, completed returns, historical sale-line snapshots, authorized store scope, Cairo date boundaries, and the existing snapshot/export-job pipeline.

The owner explicitly authorized focused automated tests, browser UAT, and a dedicated MariaDB database. SQLite, production, deployment, release, push, and tag remain prohibited.
