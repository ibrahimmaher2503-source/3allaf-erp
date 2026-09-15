# Rajeh ERP UAT Golden Path

This dataset is development/UAT-only and never runs from migrations, seeders, deployment, or the scheduler. Use a dedicated isolated MySQL/MariaDB database and an existing active company ID.

```bash
php artisan rajeh:uat-seed --company=<id> --dry-run
php artisan rajeh:uat-seed --company=<id>
php artisan rajeh:uat-purge --company=<id> --dry-run
php artisan rajeh:uat-purge --company=<id> --confirm
```

The real seed prints a one-time UAT username, email, and generated password. Records are visibly prefixed `TEST-` / `اختبار —` and registered to one company-bound UAT batch. Re-running seed reuses the active batch without duplicates. Purge refuses production and deletes only registered IDs for the selected company.

Laravel cache generation and every scheduled Artisan command must run as the runtime user `rajeh`. Release preparation must leave generated source `bootstrap/cache/*.php` behind; runtime cache files are generated after release preparation by `rajeh` to prevent recurring bootstrap/cache ownership failures.

## Golden Path

1. Open `/initial-setup`; review the 21 persistent Setup steps and the marked branch, receiving warehouse, warehouse, outlet, register, and UAT administrator.
2. Open `/purchasing/invoices`; resume the marked Draft, scan the marked international barcode, confirm duplicate scans increment one line, and use the three-character bounded search.
3. Review the marked Awaiting Distribution invoice and allocate every unit to the marked outlet.
4. Review the marked Approved invoice: receiving-warehouse remainder is zero, the destination has the stock, and the price snapshot is retained.
5. Open the destination-label route from the Approved invoice and verify the marked product barcode and destination price.
6. Review the marked Reversed invoice and its compensating movements.
7. Review inventory balances/movements and Pricing Lists, including List 0 and the marked percentage list.
8. Run dry-run purge, review table counts, run confirmed purge, then reseed to prove reversibility and idempotency.

## Coverage boundary

The command creates the marked data needed for Setup, Catalog, Purchasing, distribution, Inventory, Pricing, and label paths. The UAT administrator can inspect other existing accessible core-module routes; the command does not fabricate unsupported business workflows. The machine-readable batch `coverage` field and `.ai/V0_1_21_UAT_COVERAGE.md` record routes, results, and corrected defects.
