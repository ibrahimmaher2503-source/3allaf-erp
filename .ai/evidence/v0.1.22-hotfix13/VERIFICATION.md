# v0.1.22-hotfix13 Verification

Date: 2026-09-05
Baseline: exact Hotfix12 commit `287693218aded875749b643e9427f63318088b05`
Branch: `hotfix/v0.1.22-hotfix13-cash-drawer-integrity`
Version: `0.1.22-hotfix13`

## Root cause evidence

`App\Modules\Platform\Services\UatDataset` generated `TEST-REG-0001` for company ID 1 through a raw `DB::table('cash_drawers')->insertGetId(...)`. Hotfix12 omitted `company_id` from that insert while supplying the outlet's branch/store IDs. This bypassed `SaveCashDrawerAction`, explaining the observed invalid drawer ownership and the later `Company::findOrFail(0)` 404.

The complete source inventory found drawer writes in `SaveCashDrawerAction`, `UatDataset`, `ProductionSeeder`, `DemoErpSeeder`, `RemediationSeeder`, and `CashDrawerFactory`; no drawer import implementation exists. Hotfix13 protects Eloquent writes at the model boundary and corrects the one raw table insert.

## Focused checks actually run

- Changed/new PHP syntax: passed for the 10 final files; the subsequently simplified pure test harness was linted and passed.
- Focused Hotfix13 PHPUnit: 4 tests, 43 assertions, all passed.
- Preserved Hotfix12 shift-route PHPUnit: 1 test, 24 assertions, passed database-free with locale `zz` and a disposable process-only application key. Before the passing invocation, one command used the absent `phpunit.xml` name and one reached 19 assertions before reporting the absent isolated application key; neither accessed a database.
- Database-free route probe: `GET pos/shift` (`pos.shift`), `GET pos/shift/open` (`pos.shift.open`), and `POST pos/shift/open` (`pos.shift.store`) registered exactly.
- `lang/en.json` and `lang/ar.json`: parsed successfully.
- `git diff --check`: passed.

An initial combined attempt failed before assertions because the framework translation override loader queried a deliberately unreachable MySQL endpoint. The regression suite was changed to pure PHPUnit so verification remained database-free. No database connection or mutation occurred.

## Boundaries

No database or migration execution, seeder, Composer, Vite, browser, broad suite, production path, release path, active symlink, service, tag, push, or remote operation occurred. Company-owner/Karim permissions, permission-search presentation, and pending V0 UI files were not changed.
