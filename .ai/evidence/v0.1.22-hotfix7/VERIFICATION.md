# v0.1.22-hotfix7 verification

- Baseline: `c607a30f82dd30a5a889b526882c141ad874b783` (`0.1.22-hotfix6`).
- Focused PHPUnit: 5 tests, 26 assertions, passed.
- Syntax/compile: changed PHP syntax, Blade cache, Arabic/English JSON, route registration and diff check passed.
- Database: disposable socket-only MariaDB `toyjoy_hotfix7_verify`; all migrations and targeted 000106 down/up passed; 4 new tables and 9 new permissions verified.
- Reconciliation: `OPENING=10 COUNTED=10 AFTER=-2 EXPECTED=8 NORMALIZED=8 VARIANCE=0`; transfer guard blocked the open-count location.
- Concurrency: two authenticated counter processes recorded 2 append-only contributions totaling 2; unique request replay did not duplicate.
- EXPLAIN: exact item code used `products_item_code_unique`; model/status used `prod_model_status_idx`. No new catalog-search index was justified or added.
- Browser: Arabic and English desktop plus Arabic 390px balances/stores/count-create/count-entry passed; navigation active state passed; console errors 0; horizontal overflow 0. See `runtime-verification.json` and PNG captures.
- Query samples: balances 41; stores 93 during cold readiness-cache materialization; count creation 41; active count 49. No complete product catalog was loaded; shared layout/session/translation/permission queries account for reported duplicate fingerprints.
- Node: v20.20.0 Vite production build passed.
- Historical regression source selection: 26/33 passed; seven failures/errors were obsolete release/CSS assertions and existing M54 missing-contract fixtures. This is recorded, not treated as a pass.
- Boundary: production, shared production data, active symlink, services, maintenance mode, tags, remotes and endpoints were not accessed or changed.
