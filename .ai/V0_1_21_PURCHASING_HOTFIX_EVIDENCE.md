# v0.1.21 Purchasing Localization/Layout Hotfix Evidence

- Baseline: `125bcb705d40f19c3dc17e2af63eb339f024d76c`.
- Focused tests: initial directly related pass 15/16 passed; the sole failure was an over-broad sequence assertion. Corrected hotfix test passed 4/4 and 249 assertions.
- Visual matrix: eight task-local fixture cases covering Purchase Invoice list/create in Arabic/English at 1440px/390px.
- Visual result: no mixed-language UI copy, raw enum, clipping, overlap, or document-level horizontal overflow. Codes/references remained isolated LTR.
- Build: one Node 20.20.2 Vite build passed.
- Evidence artifacts: `.ai/evidence/v021-purchasing-hotfix/results.json` and eight PNG captures.
- Boundary: no production access, database operation, Artisan, migration, UAT command, broad suite, PDF, deployment, tag, push, or remote contact.
