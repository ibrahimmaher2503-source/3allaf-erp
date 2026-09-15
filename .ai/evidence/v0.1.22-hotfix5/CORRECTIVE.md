# Hotfix5 Corrective No-Regression Evidence

## Exact cause

`prod_status_parent_code_idx (status, parent_product_id, item_code)` caused MariaDB to replace the ordered unique `products_item_code_unique` scan used by the shared sellable-product query. The regressed plan read all 10,000 active products, accessed about 21,042 pages and performed `Using filesort`; `ANALYZE FORMAT=JSON` measured 552 ms. This query feeds Products, Pricing and Unpriced.

The corrected plan uses `products_item_code_unique`, estimates 1,000 ordered rows for the 500-row bound, and reports `Using where` without filesort. Migration `000104` no longer creates the harmful index; compatibility migration `000105` removes it if an earlier Hotfix5 candidate applied it.

Focused logging also exposed route-local waste: Unpriced ran an invisible pricing dashboard and version paginator; Pricing loaded 500 proposal products while its modal was closed and counted unpriced products twice. Those queries are now conditional and the paginator total is reused. Pricing readiness is cached only as versioned readiness metadata—not effective/live prices—and every Eloquent source mutation advances the existing invalidation version.

## Identical focused benchmark

Same disposable MariaDB 10.11 database `rajeh_hotfix5_perf_20260902`, administrator, cache/session drivers, 10,000 products, 100,000 movements, route order, one warm-up and six samples. Times are milliseconds. p95 is the maximum of the six measured samples, matching the small-sample conservative method.

| Route | Hotfix4 median / p95 | Hotfix4 queries / SQL median | Corrected median / p95 | Corrected queries / SQL median |
|---|---:|---:|---:|---:|
| Products | 327.2 / 364.6 | 30 / 102.2 | 299.2 / 356.6 | 27 / 78.6 |
| Pricing | 302.9 / 442.5 | 31 / 106.3 | 249.3 / 308.7 | 28 / 85.3 |
| Unpriced | 333.8 / 367.5 | 31 / 140.7 | 208.6 / 280.5 | 16 / 57.7 |

The corrected results also beat the originally published response baselines of Products 337.6/441.0, Pricing 295.3/318.9, and Unpriced 247.7/277.1 except for a 3.4 ms Unpriced p95 variance; the identical rerun demonstrates a 87.0 ms p95 improvement for that route. Products SQL is confirmed as a real plan regression, not noise, and is corrected from the original 97.7 ms to 78.6 ms.

## Focused verification

- `V021ReadinessPricingHotfixTest` and `V0122Hotfix4InventoryLabelsSidebarPricingTest`: 9 tests, 88 assertions, zero failures/errors/skips.
- The readiness unit harness now supplies its translator dependency explicitly.
- PHP syntax passed for the four affected PHP/migration files.
- Migrations `000104` and `000105` report `Ran`; `prod_status_parent_code_idx` is absent.
- `git diff --check` passed. No broad matrix, browser, PDF or frontend checks were repeated.
