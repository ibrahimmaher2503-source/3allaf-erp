# v0.1.22-hotfix22 Focused Verification

Date: 2026-09-08
Source baseline: `63d542558fc301f7b6554da5c5747ca2f4124a46`
Production baseline: `04d09977abd9b3d56bc83fd067a78642f5127ca7`

## Result

PASS.

- Real configured navigation rendered for cashier, manager, and administrator permission profiles.
- The real `initial-setup` item, which intentionally has no explicit `key`, received a deterministic fallback and rendered its nested groups.
- Keyed/keyless links, permission filtering, headings, separators, nested groups, empty items, and optional fields rendered without undefined-array-key warnings.
- The actual authenticated dashboard layout rendered database-free with the real navigation component.
- Preserved Hotfix21 server/browser cash examples, split validation, and multi-page sidebar restoration passed.
- All Blade templates compiled; 886 compiled PHP files passed syntax; affected Livewire roots and both locale JSON files passed.
- Exactly one Vite production build ran. Manifest SHA-256: `18634cc4cb4e59e983bc62be66bec52bc3dcdb7efc537559c3ad763b053a859c`.
- App JS: `assets/app-ST3E3GyZ.js`, SHA-256 `7173aa54896453aa9eb94569c2e60d07d93701fc447d8f9dbb60affa1aa523e1`.
- App CSS: `assets/app-by0zavLn.css`, SHA-256 `e49147ba328aa1713361dad190c59303cd551058ce0bc6b62b7eb0083f0ba3dc`.

## Harness continuation

The first navigation invocation exposed a zero-argument Eloquent fixture-construction issue; subsequent affected-only runs corrected suppressed-warning handling, a Laravel 13 `flatMap` callback mismatch, and database-free UI-preference isolation. The navigation/dashboard runtime contract then passed. Passed payment, sidebar, Blade, Livewire, locale, and asset checks were not repeated.

## Activation contract

`.ai/evidence/v0.1.22-hotfix22/verify-navigation-runtime.php --activation` uses real active users and host data inside a rolled-back transaction, renders distinct permission-filtered navigation profiles, and renders the authenticated dashboard. The activation script must execute it as `rajeh` before changing the active symlink and fail closed on any error.

No production access, database operation, migration, deployment, activation, service action, sudo, tag, push, or external service occurred during development verification.
