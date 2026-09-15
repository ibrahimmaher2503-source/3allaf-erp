# v0.1.22-hotfix36 Concise Verification

- Baseline: exact active Hotfix35 commit `2f6b07285ca082cb3c61f1f860e13b0d3d2bccb8`; repair commit `80156c9ab50d9b76c19a797dc30db487b694ecb2` is its direct child and was cherry-picked without conflicts or discarded localization content.
- Locale integrity: all 6,115 `ar-EG` JSON keys and 244 PHP locale leaves remain complete, nonempty, token-compatible, and syntactically valid. Known corruption markers are absent and representative dashboard, products, inventory, sales, purchasing, alerts, and approvals wording is Egyptian and coherent.
- Unchanged boundaries: `ar` and `en` remain byte-for-byte identical to Hotfix35. Business logic, queries, permissions, routes, database, search, keyboard workflows, accounting, locale-controller behavior, RTL/Cairo, and internal technical class names remain unchanged.
- Authenticated rendering: activation performs transactional authenticated checks of representative routes in `ar`, `en`, and `ar-EG` before and after switching. All database work is rolled back; offline preparation does not claim a local database-backed render.
- Vite provenance: build count zero. No frontend build input changed, so exact Hotfix35 compiled assets are reused.
- Package and scripts: the release archive and complete manifest are integrity-checked. Promotion accepts only exact Hotfix35. Activation requires migration 000111 already `Ran`, runs no migrations, fails closed, and restores exact Hotfix35 after any post-switch failure.
