# v0.1.22-hotfix17 Verification Evidence

**Date:** 2026-09-06
**Baseline:** exact Hotfix16 commit `31883f5c823458d30738708ec96e1d02337f4c9b`
**Branch:** `hotfix/v0.1.22-hotfix17-pos-compact-refunds`
**Result:** PASS

The one authorized consolidated, database-free verification command completed successfully:

- release baseline, branch, version, and migration-source contracts passed;
- all 17 changed/new non-Blade PHP files passed syntax under the available PHP 8.5.9 CLI;
- every changed/new Blade template compiled with Laravel's Blade compiler and each compiled artifact passed PHP syntax;
- required POS/open-order/checkout/refund/receipt routes and duplicate-name guard passed;
- focused source contracts passed for compact order tabs, bounded customer lookup, barcode/SKU add, wide checkout, cash/card/mixed presentation, distinct insufficient/change states, refund quantity reservation, scope, locking, completion idempotency, safe references, linked inventory reversal, shift reconciliation, and configured `sales_return` printing;
- Arabic and English locale JSON parsed and every scoped literal cashier-visible key was present and nonblank in both locales;
- `git diff --check` passed;
- Vite was correctly skipped because no Vite/frontend input file changed.

The PHP wrapper emitted non-fatal warnings that it could not create cPanel metadata lockfiles under `/etc`; every lint process returned success and no repository or production state depended on those lockfiles.

Not run by instruction: broad automated suites, browser automation, databases, migrations, seeders, production/live HTTP, services, backups, deployment, activation, tags, pushes, or remote operations.
