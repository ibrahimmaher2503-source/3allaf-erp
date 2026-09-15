# v0.1.22-hotfix6 Focused Verification

- Baseline: `84554a303c979a314180b32516546debc567a053`
- Isolated database: `rajeh_hotfix6_verify`
- Canonical setup steps: 21 unique routable definitions; Essentials 5, Settings 5, Operational Readiness 11. The earlier expected 22 included Product Import historically, but Product Import is an action inside Product Cards and is not a separate configured/routable setup step.
- Browser: authenticated Arabic desktop and mobile drawer passed; all links carried `setup=true` and exact `setup_step`, active parents expanded, normal document scrolling remained enabled, no page overflow/errors. Arabic/English Audit Log and combined store-create wording passed.
- Visuals: `arabic-basic-data-desktop.png`, `mobile-basic-data-drawer.png`; structured result: `runtime-verification.json`.
- Focused PHPUnit: 4 tests, 34 assertions, zero errors/failures.
- Compilation: changed PHP syntax, Blade cache, Arabic/English JSON, and diff checks passed.
- Shared layout: `/settings/profile`, same isolated fixture and five post-warm samples, 5 queries/sample and zero duplicates. Hotfix6 median total/app/SQL: 140.345/124.455/12.480 ms. Hotfix5 evidence median total/app/SQL: 136.458/130.258/9.260 ms. Total delta +2.8%; app time improved; query count unchanged. Static registry generation adds no readiness/catalog query and no per-item permission query.
- Migrations: all existing migrations through 000105 Ran; Hotfix6 adds none.
- Frontend build: Node 20.20.0 Vite 8.2.0 passed after correcting the managed desktop sidebar stylesheet; manifest SHA-256 `baed65ad827593d44cbc8352a62c8807ea1a95b924945fbade1b68852302bb3c`.
- Production/shared data and runtime were not accessed or mutated.
