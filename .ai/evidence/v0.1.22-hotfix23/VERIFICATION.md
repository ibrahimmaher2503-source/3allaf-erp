# v0.1.22-hotfix23 Focused Verification

**Date:** 2026-09-08
**Source baseline:** exact Hotfix22 commit `163548b2f10f217ebf989589701f98a5a60b49ec`
**Production baseline:** exact Hotfix20 commit `04d09977abd9b3d56bc83fd067a78642f5127ca7`
**Result:** PASS with the owner-authorized deterministic layout fallback; visual screenshots unavailable.

## Browser boundary

One bounded Playwright Chromium installation was attempted in the non-root workspace. The download failed with `ENOSPC` because the host temporary filesystem had only about 20 MiB free. It was not retried. No browser screenshot or visual PASS is claimed.

## Actual render and deterministic layout proof

- Laravel rendered the authenticated database-free dashboard with the real application layout, real normalized navigation data, and final Vite asset references.
- The render contained exactly one `data-sidebar-scroll` element and one `application-navigation` id. Sidebar and content are direct app-shell children; header and main remain inside the content column.
- The Hotfix20 272px/5rem desktop grid, sticky sidebar relationship, logical RTL/LTR placement, and mobile breakpoint remain present.
- Only `[data-sidebar-scroll]` receives `overflow-y:auto` and bounded `scrollTop` assignments.
- Instrumented restoration kept window, documentElement, and body scroll positions unchanged at zero; an already-correct restored position remained unchanged, and an off-screen active item was revealed only through the internal scroller.
- The final app bundle contains no active-navigation `scrollIntoView`. The one remaining bundled call belongs to the unrelated guided-tour target. The catalog validation field call is Blade-local and unchanged.

## Preserved contracts

- Hotfix22 real/keyless navigation data rendered for cashier, manager, and administrator profiles, including nested/optional shapes.
- Hotfix21 server-authoritative cash and split-payment contract passed.
- All 886 compiled Blade PHP files passed syntax; affected PHP and Livewire-root contracts passed.
- Arabic and English locale JSON passed.
- Git diff hygiene passed.

## Replacement production assets

The prior Hotfix23 build was deleted and is superseded. Exactly one clean replacement Vite 8.2.0 build ran after removing the active-navigation call.

- Manifest: `public/build/manifest.json`
  SHA-256: `5d171d1aae53e952aa727a1093dcf427d0366a881481e713caaa371e67243687`
- JavaScript: `public/build/assets/app-DGJ2yKaj.js`
  SHA-256: `988e8ee9c13d186c4d3692ed1b7a3cf9680ba8897e2b4ec5272f2646b58392e5`
- CSS: `public/build/assets/app-DWxi_J5Q.css`
  SHA-256: `9d49b148f0775f0d0b8b104f77c6fa39ea6910c14dfac8c93cbcdd067d371228`

No migration, database access, production access, deployment, activation, sudo, tag, push, or external service other than the single explicitly authorized failed browser download occurred.
