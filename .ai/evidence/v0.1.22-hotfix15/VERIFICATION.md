# v0.1.22-hotfix15 Focused Verification

**Date:** 2026-09-05  
**Candidate:** `hotfix/v0.1.22-hotfix15-complete-batch`  
**Source baseline:** Hotfix14 commit `32c13a8e54f8dad45691a88318f9b475ff944f15`, tree `141e620ef70ab92095893e36a6182d6c6433250a`  
**Result:** PASS

## Consolidated database-free pass

- PHP 8.5 syntax: PASS for all 47 changed or newly added PHP and Blade-PHP files.
- Blade compilation: PASS with a task-local compiled-view path and an intentionally unavailable database connection.
- Locale JSON: PASS; Arabic parsed with 5,797 keys and English parsed with 2,483 keys.
- Focused source contracts: PASS, 52 assertions covering the closed UI/navigation batch, dynamic import workbooks, company scope, opening-inventory assignment/draft/approval/reversal invariants, approval-time first-movement locking, guarded product removal, protected QA/UAT commands, seeder environment guard, and release version.
- XLSX structure and round-trip: PASS, 10 assertions covering the Data Entry table, data validation, defined-name/reference relationship, schema/company metadata, exact headers, formula-safe cell output, OpenSpout round-trip, and fail-closed missing Data Entry behavior.
- Route registration: PASS; all 9 routes under `inventory/opening` registered, including index, show, template, import, rejections, store, approve, reverse, and zero-decision routes.
- Diff hygiene: PASS; `git diff --check` reported no errors and no patch-rejection or editor-backup artifacts were present.

The first source-contract execution stopped only on stale verification-string assumptions; the implementation did not change. Those individual assertions were corrected to the actual preserved source and only the failed/remaining source-contract checks were executed. The workbook check initially loaded the inherited Hotfix14 class through the temporary `vendor` symlink; it passed when the check explicitly mapped the two current Hotfix15 data-exchange classes. Previously passed checks were not repeated.

## Deliberately not run

- No PHPUnit/Pest or broad automated suite.
- No browser or HTTP checks.
- No database connection, migration, seeder, QA/UAT product audit, or cleanup.
- No Vite build because no JavaScript, CSS, or other frontend asset source changed; Blade-only changes require no asset rebuild.
- No production access, backup, promotion, activation, deployment, service action, tag, push, or remote operation.

Hotfix14 production identity and health are operator-supplied prerequisites and were not rechecked during this task.
