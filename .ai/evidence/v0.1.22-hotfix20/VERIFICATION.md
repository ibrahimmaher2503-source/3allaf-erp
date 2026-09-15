# v0.1.22-hotfix20 Verification Evidence

Date: 2026-09-07

Hotfix20 starts from exact Hotfix19 commit `1fd252c6f0e612f8b3a0d4cea3413a53295205a8` and preserves its application behavior, POS/refund implementation, and permanent Livewire root repair.

## Frontend input and build

- Tailwind v4 source inspection was performed once. `resources/css/app.css` uses `@source '../views'`, recursively covering every POS and Livewire Blade template.
- No missing content path, invalid utility, or CSS conflict was confirmed, so no application CSS or Blade markup was changed.
- Exactly one clean production Vite build ran with Vite 8.2.0 and Node 20.20.2.
- Hotfix16 Vite manifest SHA-256: `b0d6e69cb067407c8ecc07e72eed30f06fb88a4050a3bd55f4f526c5c20606df`.
- Hotfix20 Vite manifest SHA-256: `8e5c124882f8b0af416f5a35e28e90adfc2952a38abf70296780b80075c73901`.
- Hotfix16 app CSS SHA-256: `10ef4bd4a92a2d50031e3ff50014ce8e02705a5ffee743e32463769cad5f3252`.
- Hotfix20 `assets/app-CGYY35Pa.css` SHA-256: `56e206d8fc38fc2e01c82d5f2b2b8e8ef20947b67fc5feac0124db18c850ec87`.
- The new manifest and app CSS both differ from Hotfix16.

## Focused verification

- 231 Blade templates compiled with the registered Laravel, Livewire, and Flux application compiler.
- All 231 compiled PHP outputs passed PHP 8.5 syntax.
- All four changed POS Livewire templates retained unconditional root boundaries.
- The closed refund wizard rendered its permanent root and accepted Livewire root-attribute insertion.
- Both locale JSON files parsed; ten representative Hotfix17-20 POS/refund keys exist in both locales with non-empty Arabic values.
- The new Vite manifest, fonts manifest, and every referenced asset are present and non-empty.
- Generated CSS contains the compact three-column desktop grid, wide refund wizard, full-height compact POS panels, dark-mode selector, and RTL selector.
- Application version reports `0.1.22-hotfix20`.

## Visual acceptance boundary

Authenticated POS rendering at 1920x1080 and 1366x768 was technically unavailable because this isolated worktree has no safe non-production environment/database and production access is prohibited. No visual PASS and no screenshots are claimed. The activation workflow must authenticate the exact packaged manifest and CSS and pass Laravel/Livewire/cache preflights before changing the active symlink.

## Operational boundary

Migration 000111 must already be Ran. Hotfix20 adds no migration and the operator scripts execute no migration command. Exact Hotfix16 remains the only accepted active baseline and exact rollback target. No production access, deployment, migration, service action, tag, push, sudo, or remote contact occurred.
