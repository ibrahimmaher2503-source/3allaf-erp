# v0.1.22-hotfix8 measured performance evidence

## Root causes

Hotfix7 extended the shared inventory renderer so every inventory child page loaded both paginators, transfers plus approvals/line products, adjustments plus line products, and counts plus stores, locations/stores, members/users, and lines/products. It also loaded up to 200 users and evaluated count permissions, plus category, supplier, and product-option collections, even on routes where those datasets were not rendered. The summary derived totals from those loaded collections. This was route-composition over-fetching introduced or amplified by Hotfix7, not a server-capacity or index defect.

Hotfix8 conditions each dataset on the view that renders it, retains bounded lists, replaces unused count relationship hydration with only the line fields used by the list, and reuses rendered paginator totals. Sidebar/navigation code and shared readiness evaluation were not changed.

## Read-only deployed-data comparison

One cold observation followed by five warm observations per route, Arabic authenticated super administrator, same production database and compiled configuration. Median and conservative six-sample p95 are milliseconds. TTFB is kernel response-materialization time and therefore equals total for this in-process profiler. No session/cache flush or production mutation occurred.

| Route | Hotfix7 med/p95 | Hotfix7 Q/SQL | Hotfix8 med/p95 | Hotfix8 Q/SQL |
|---|---:|---:|---:|---:|
| `/` | 51.5 / 192.3 | 2 / 5.9 | 58.0 / 95.7 | 2 / 8.9 |
| `/login` (authenticated redirect) | 31.7 / 41.0 | 0 / 0.0 | 27.5 / 40.1 | 0 / 0.0 |
| `/inventory/balances` | 263.9 / 555.4 | 30 / 102.4 | 182.4 / 240.3 | 18 / 45.8 |
| `/inventory/counts` | 251.6 / 313.3 | 30 / 76.7 | 180.1 / 214.6 | 20 / 41.3 |
| `/admin/stores` | 248.5 / 652.5 | 17 / 38.8 | 242.4 / 482.4 | 17 / 47.0 |
| `/catalog/products` | 270.0 / 353.2 | 20 / 66.8 | 209.0 / 262.2 | 20 / 44.4 |
| `/pricing` | 224.0 / 289.7 | 22 / 51.9 | 194.3 / 248.5 | 22 / 45.3 |
| `/pricing/labels` | 177.6 / 192.3 | 14 / 36.3 | 140.9 / 157.5 | 14 / 29.7 |
| `/pricing/unpriced` | 184.5 / 226.4 | 10 / 29.7 | 165.0 / 215.9 | 10 / 29.2 |
| `/pos` | 316.8 / 456.2 | 56 / 137.6 | 309.6 / 432.2 | 56 / 160.2 |
| `/reports` | 584.1 / 667.3 | 100 / 271.3 | 533.7 / 593.1 | 100 / 207.5 |

Balances application time/memory/size improved from 172.1 ms/10.5 MiB/209,048 bytes to 137.4 ms/8.5 MiB/209,048 bytes. Counts improved from 190.5 ms/10.5 MiB/223,560 bytes to 132.2 ms/8.5 MiB/223,560 bytes. Shared-layout query count did not increase. The remaining two-hit translation-override fingerprint is pre-existing; no Hotfix7-specific N+1 remains.

## Isolated high-volume comparison

Same disposable MariaDB database `toyjoy_hotfix8_verify`, 50,000 products and 247,000 inserted movements, six samples per version. The historical fixture's fractional rows were normalized to the established whole-number product rule before the final comparison.

| Route | Hotfix7 med/p95 Q/SQL | Hotfix8 med/p95 Q/SQL |
|---|---:|---:|
| Balances | 576.3 / 678.4, 22 / 385.1 | 227.6 / 302.2, 13 / 105.7 |
| Counts | 506.7 / 587.3, 22 / 290.3 | 458.2 / 508.1, 18 / 277.8 |

No Hotfix8 migration or index exists. Existing exact-code/model indexes were retained; the confirmed defect required no new index.
