# v0.1.21 Hotfix 2 Evidence

- Baseline: Hotfix 1 commit `03d513347f58d3abf4814783035a84687aad27f8`.
- Focused tests: directly related pass completed 32/33; the only failure was an outdated bounded-search source assertion. After correction, that focused file passed 4 tests / 33 assertions.
- Search contracts covered: exact scanner lookup, unknown modal, duplicate focus/increment, scanner focus, 175ms cancelable debounce, immediate Enter, active loading, stale-result keying, company context, exact/prefix/contained ranking, `0001` fragment behavior, and 20-result cap.
- Barcode contracts covered: separate international/local UAT products, validated GTIN `6220000000013`, allocator-produced local format `01234000001`, prefix ownership protection, marked UAT sequence/barcodes, production confirmation, idempotent batch registry, and allowlisted purge.
- Product Card: corrected the malformed `$isEditing` directive and found no other missing Arabic keys or exposed template-variable pattern in the create/edit view.
- Visual evidence: Purchase Invoice create and Product Card create/edit in Arabic and English; six cases passed mixed-language, exposed-variable, and document-overflow checks. Representative captures were visually inspected.
- No database, migration, Artisan, UAT command, broad suite, PDF, production access, deployment, tag, push, or remote contact occurred.
