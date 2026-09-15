# v0.1.22-hotfix5 Performance Evidence

> Historical evidence for commit `8c5ccbac20a8382eefc3a5532705c903850890c9`. Its prepared artifact was rejected because Pricing and Unpriced regressed. The authoritative corrective evidence is `CORRECTIVE.md`.

## Method

- Exact baseline: `1f181b08f3291e4eee71220afb84f5a0702a4220`; candidate: the current Hotfix5 tree.
- Same isolated socket-only MariaDB 10.11 database (`rajeh_hotfix5_perf_20260902`), authenticated administrator, database cache/session drivers, Arabic locale, route set, and 10,000-product/100,000-movement fixture for both sides.
- Each route received one warm-up followed by five samples. Median and p95 below are milliseconds; `Q` is query count and `SQL` is aggregate database time. Application time, response bytes, slowest SQL and normalized duplicate groups are retained by `scripts/benchmark-authenticated-routes.php`.

| Route | Before med/p95 | Before Q/SQL | After med/p95 | After Q/SQL |
|---|---:|---:|---:|---:|
| Dashboard | 278.1 / 297.4 | 30 / 143.3 | 211.8 / 270.6 | 29 / 90.7 |
| Products | 337.6 / 441.0 | 29 / 97.7 | 333.2 / 366.9 | 28 / 129.8 |
| Product card | 224.3 / 254.1 | 17 / 54.3 | 162.7 / 178.4 | 16 / 32.1 |
| Inventory overview | 743.8 / 888.0 | 50 / 517.0 | 671.0 / 729.6 | 49 / 448.8 |
| Inventory balances | 685.0 / 757.8 | 50 / 523.3 | 333.0 / 486.5 | 33 / 177.9 |
| Inventory transfers | 825.3 / 930.3 | 50 / 577.2 | 404.8 / 499.7 | 33 / 192.4 |
| Inventory counts | 752.9 / 1011.7 | 50 / 526.3 | 382.4 / 521.7 | 33 / 174.7 |
| Movement register | 687.3 / 703.9 | 50 / 471.2 | 415.2 / 460.8 | 33 / 188.5 |
| Customers | 151.3 / 173.5 | 16 / 31.2 | 174.6 / 227.4 | 15 / 40.0 |
| Customer create | 153.8 / 217.2 | 18 / 35.6 | 172.1 / 191.3 | 17 / 41.2 |
| Customer groups | 166.1 / 212.4 | 14 / 47.0 | 145.4 / 162.4 | 13 / 35.0 |
| Suppliers | 657.5 / 743.6 | 16 / 74.0 | 193.4 / 201.0 | 14 / 38.7 |
| Purchase invoices | 155.6 / 204.1 | 11 / 15.8 | 142.0 / 166.8 | 10 / 19.3 |
| Pricing | 295.3 / 318.9 | 28 / 80.0 | 322.3 / 412.8 | 27 / 149.9 |
| Unpriced products | 247.7 / 277.1 | 28 / 82.7 | 338.9 / 367.9 | 27 / 156.6 |
| Barcode labels | 178.7 / 205.4 | 15 / 45.8 | 134.1 / 170.7 | 14 / 22.4 |
| POS | 241.0 / 322.1 | 26 / 107.3 | 213.5 / 278.0 | 26 / 91.0 |
| Reports | 941.8 / 1426.2 | 92 / 600.2 | 847.6 / 1038.9 | 91 / 489.6 |
| Admin settings | 164.0 / 210.1 | 12 / 25.1 | 162.2 / 236.2 | 11 / 21.1 |
| Profile settings | 139.9 / 149.6 | 6 / 15.9 | 136.5 / 180.5 | 5 / 9.3 |

Small absolute changes on Customers, Pricing, and Unpriced remained below 340 ms median, reduced one query each, and were within local database timing variance. No standard page exceeded 800 ms median; Reports was the only data-heavy page and remained below 1.5 seconds.

## Measured causes and plans

- Setup/readiness computation and permission translation repeated on every shared layout render; request-scoped reuse plus tenant/user/permission/locale-keyed, version-invalidated readiness caching removes repeated work without caching operational balances, prices, or financial totals.
- Inventory child pages executed 17 overview-only aggregates that were never rendered; those queries are now conditional on the overview.
- Suppliers embedded up to 10,000 full product rows in every response (about 1.42 MB); link data is now loaded only when the modal is open and capped at 200, reducing the response to about 0.27 MB.
- Pricing readiness materialized the entire sellable catalog and ran duplicate counts; it now uses one aggregate and one bounded remediation query.
- Exact/prefix product code, model and barcode paths applied functions or contains matching; exact and prefix branches now preserve index use while multilingual contains search remains bounded and paginated.
- MariaDB selected the original product composite index for sellable-product ordering, but corrective `ANALYZE FORMAT=JSON` proved that choice harmful; see `CORRECTIVE.md`.

## Focused verification

- PHP syntax: 13 changed PHP files passed. Blade, config, route and event cache compilation passed; caches were cleared afterward.
- Focused PHPUnit: Hotfix4 regression contract passed 5 tests / 55 assertions. The pre-existing standalone readiness test still errors because it calls `__()` without bootstrapping Laravel's translator; this is unrelated to the hotfix and is recorded rather than concealed.
- Migration `000104`: rollback removed all four introduced indexes; re-apply restored exactly four named indexes on disposable MariaDB.
- Authenticated browser: 20/20 Arabic desktop routes returned 200 with RTL, no horizontal overflow and zero console/page errors. Exact POS code search completed. Mobile navigation opened at 390×844 without overflow.
- `lang/ar.json` and `lang/en.json` decode validation passed. Frontend sources and manifest were unchanged, so no new production build was required; existing largest assets are CSS 449 KB and JS 16 KB.
