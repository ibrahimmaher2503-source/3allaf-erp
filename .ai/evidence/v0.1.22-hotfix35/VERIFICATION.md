# v0.1.22-hotfix35 Focused Verification

- Baseline: exact active Hotfix34 commit `397fd3529a087f08f402fd11c88692481bb0654b` in a separate clean `rajeh_ahmed` worktree; localization commit `57cb2a85666cc7d9cfad02edce33eb5bfa766df6` was cherry-picked without discarding content.
- Route-cache boundary: Hotfix34's `App\Http\Controllers\LocaleController` dispatch and cached HTTP endpoint proof are preserved unchanged.
- Controller contract: POST `/locale`, route name `locale.switch`, web middleware/CSRF, redirect-back behavior, session persistence, and one-year `locale` cookie are preserved. Validation uses the controller's exact `['ar', 'en', 'ar-EG']` allowlist; malformed and unsupported values leave the preference unchanged.
- Concise packaged verification covers touched PHP syntax, cached route creation and boot, locale parity, protected tokens, unchanged `ar`/`en`, RTL/Cairo and three-way switching, exact asset identity, authenticated dashboard/alerts/approvals/sales/purchasing/inventory rendering, Egyptian greeting and presentation-only alert/source labels, no database mutation, archive/manifest integrity, Git diff hygiene, Bash syntax, and isolated pre/post-switch forced failures.
- Activation-gated authenticated verification runs the representative routes in `ar`, `en`, and `ar-EG` inside a transaction before and after switching; every transaction is rolled back.
- Vite provenance: build count zero. Exact Hotfix34 compiled assets are reused.
- Migration status: Hotfix35 adds and executes no migration. Migration 000111 must already be `Ran`; activation executes no migration.
