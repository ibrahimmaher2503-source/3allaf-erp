# v0.1.22-hotfix9 Verification

- Baseline: `90180c270f2ba2425877fe095c7a1c82c049989f`
- Disposable database: `toyjoy_hotfix9_verify`
- PHPUnit: 12 tests / 78 assertions passed.
- PHP syntax, Blade compilation, Arabic/English JSON, Vite production build, and diff hygiene passed.
- Vite manifest SHA-256: `b0d6e69cb067407c8ecc07e72eed30f06fb88a4050a3bd55f4f526c5c20606df`.
- Authenticated Chromium: Arabic/English desktop and Arabic mobile passed; 20 unique setup leaves/cards, correct 01–20 sequence, deferred Party item absent, canonical Essentials and Roles present, header profile retained, footer profile removed, one pricing readiness summary, zero console errors, and zero horizontal overflow.
- Isolated route query counts: dashboard 89; Pricing Lists setup 76; Initial Setup 72; inventory balances 13. Source inspection confirms no query-producing sidebar addition, removes both deferred Party readiness sources, and leaves Hotfix8 inventory code unchanged.
- No Hotfix9 migration. No production/shared runtime, data, database, service, configuration, active symlink, or release directory was accessed or changed.

## PHP 8.5 view-cache correction

- The oversized pricing-label view was decomposed into seven bounded Blade partials; behavior and Hotfix9 navigation remain unchanged.
- PHP 8.5.9 `optimize:clear` and `view:cache` passed with task-local file cache/session overrides. No vendor, Livewire compilation, PCRE setting, or environment configuration was changed.
- `view-cache-correction-browser.json` records authenticated Arabic RTL and English LTR checks against isolated database `toyjoy_hotfix9_viewcache_verify`: pricing-label workspace/search present, 20 unique navigation leaves, Party deferred, one pricing-readiness summary, no overflow, and no console/page errors.
- The existing frontend build remains applicable because no frontend asset source changed; its manifest SHA-256 remains `b0d6e69cb067407c8ecc07e72eed30f06fb88a4050a3bd55f4f526c5c20606df`.
- `.env`, shared storage, `public/storage`, and `bootstrap/cache` release boundaries remain guarded; `storage/storage` was not created. Production and promotion were untouched.
