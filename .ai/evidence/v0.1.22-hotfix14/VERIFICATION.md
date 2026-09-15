# v0.1.22-hotfix14 Verification

Date: 2026-09-05
Baseline: exact Hotfix13 commit `1c0519dc57b9c5b9af1aa6963dbbb448fce56bbe`
Branch: `hotfix/v0.1.22-hotfix14-pricing-cash-rounding`
Version: `0.1.22-hotfix14`

## Focused checks actually run

- Database-free arithmetic and source contracts passed 20 assertions: percentage-derived `30 + 15% = 34.500 → 35.000`, `37 → 40`, `1246 → 1250`, and `1245 → 1245`; cash payable `37 → 35`, `38 → 40`, and `35 → 35`; base/manual price bypass; cash-only versus exact non-cash settlement; append-only/idempotent setting migration; and localized missing/invalid configuration handling.
- PHP 8.5 syntax passed for all 14 changed/new PHP files.
- The `pos` route subset registered successfully, including checkout, suspended-sale resume, and shift opening.
- `lang/en.json` and `lang/ar.json` parsed successfully.
- `git diff --check` passed.

The standing project directive prohibits adding a new automated test file for this task, so the focused verification used direct database-free assertions against the canonical calculation services and source boundaries. No PHPUnit suite was created or run.

## Boundaries

No database connection, migration, seeder, Composer, Vite, browser, production path, active symlink, service, backup, tag, push, or remote operation occurred. The root operator's supplied exact-Hotfix13 production evidence was accepted as the completed baseline preflight and was not repeated.
