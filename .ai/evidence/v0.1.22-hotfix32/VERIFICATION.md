# v0.1.22-hotfix32 Focused Verification

- Baseline: exact Hotfix31 commit `3ca22dbe6671f94aaec4132b84fb6a2c126cec82` in a separate clean worktree.
- PASS: the corrected fixture verifier renders the supplier-only editor checkbox and two product lookups, finds the exact Arabic control label once, finds no exact label text inside either lookup, and exits with an exact terminal PASS marker.
- PASS: unchanged compiled browser assets process real `منتج` and `001` input events, loading/results/empty/error states, supplier filtering, multi-match Arrow/Enter, unique scanner selection, invoice row progression/next focus, repeated-Enter suppression, and parent-submit prevention.
- PASS: the isolated forced-failure contract proves a pre-switch verifier failure leaves Hotfix31 active, a post-switch verifier failure restores Hotfix31, and neither failure prints `ACTIVATION=SUCCESS`.
- PASS: touched PHP syntax, Bash syntax, exact Hotfix31 Vite manifest/application asset identity, immutable package identity, and Git diff hygiene. No Blade or locale file changed, so their conditional checks were not run.
- Vite build count: 0. The exact Hotfix31 compiled manifest SHA-256 remains `43ee8a205f5c4467d00ced437109f1478e86a67c4edf9aa3b24be79dba5cf11a`; `assets/app-CIj8B5-k.js` remains `d3943fbaff090cbc93123b0597b173271fe4399a22d6a6cab40681e34c64e6b4`.
- ACTIVATION-GATED: authenticated Arabic/English HTTP 200 for the four required routes and production-shaped invoice search/keyboard verification run before and after switching with checked exit status and PASS marker.
- Migration status: no Hotfix32 migration exists or ran. Migration 000111 must already be `Ran`; activation executes no migration.
