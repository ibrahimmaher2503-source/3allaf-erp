# v0.1.21 Hotfix 3 Evidence

- Baseline: exact Hotfix 2 commit `e14eae0316b0063f95e77c2cabaa83f76c60eb13`.
- Design: `ProductPricingReadiness` is the single company-keyed, current-query evaluator consumed by Setup Center, persistent setup context, Products/Product Card, and Pricing Lists. Pricing uses the same Product Card prerequisite result.
- Rules: null, zero, negative base price and missing mandatory sellable-product identity/category/barcode-mode fields are affected; the bounded list exposes localized name, LTR item code, current value, direct edit, and view-all action.
- Pricing: List 0 inherited/effective counts are separate from explicit manual override count.
- UAT audit: the production guard, existing sequence reuse, two marked positive-priced products, validated GTIN path, allocator local path, and marker-only purge remain present. No UAT command ran.
- Production-correction audit: every named correction through Hotfix 2 is represented in source.
- Focused validation: source/unit coverage checked shared zero/corrected-price results, prerequisite ordering, localization, UAT non-mutation/positive pricing, pricing count semantics, and retained corrections. No database test ran.
- Visual matrix: 16 task-local cases (four surfaces × Arabic/English × desktop/mobile) passed mixed-language, raw-state/variable, and document-overflow checks. Representative Arabic mobile Pricing and English desktop Setup captures were inspected.
- Frontend: one Node 20.20.2 Vite build passed; manifest SHA-256 `cc095c1ecdfd4690ed5e500b18c78c63d74043ae0933e548c72f5111a92fdf78`.
- Operator state: production data was not inspected; any currently invalid Product Cards remain unchanged and require later authorized operator correction.
