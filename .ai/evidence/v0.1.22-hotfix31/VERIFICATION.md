# v0.1.22-hotfix31 Focused Verification

- Baseline: exact Hotfix30 commit `05f67897402d918001fd4da6a9c3204a26358c6d` in a separate clean worktree.
- PASS: touched PHP/Blade syntax and one Blade compilation pass.
- PASS: Arabic and English locale JSON; exact Arabic supplier-only label is `استخدام منتجات المورد فقط`.
- PASS: purchase-invoice compact metadata, bounded line scroller, sticky aligned desktop header, totals, notes-after-lines, responsive wrapping, and price-list single-row desktop filter geometry.
- PASS: the compiled Hotfix30 lookup handles real `منتج` and `001` input events, visible loading/results/empty/error states, supplier-only/all-product requests, multiple-match Arrow/Enter selection, exact unique scanner selection, stale-request safety, quantity/cost/invoice-field progression, one next row/focus, repeated-Enter suppression, and parent-submit prevention.
- PASS: exactly one clean Node 20.20.2 / Vite 8.2.0 production build. Manifest SHA-256 `43ee8a205f5c4467d00ced437109f1478e86a67c4edf9aa3b24be79dba5cf11a`; compiled app JavaScript `assets/app-CIj8B5-k.js` SHA-256 `d3943fbaff090cbc93123b0597b173271fe4399a22d6a6cab40681e34c64e6b4`.
- PASS: compiled asset identity, Git diff hygiene, and promotion/activation Bash syntax.
- ACTIVATION-GATED: authenticated Arabic/English HTTP 200 for the four required routes and production-shaped purchase-invoice lookup/keyboard runtime execute before and after switching.
- Migration status: no Hotfix31 migration exists or ran. Migration 000111 is required to be already `Ran`; activation executes no migration.
