# v0.1.22-hotfix10 Verification

- Baseline: `b19bdf37d4a4fa05bf381e5eaf12ccfbf09c9f07`.
- Branch: `hotfix/v0.1.22-hotfix10-ui-dedup`.
- PHP 8.5.9 syntax passed for the five changed PHP/config/test files.
- Blade `view:cache` passed with task-local output, query observability disabled, the built-in translation database-outage fallback, and an intentionally invalid database connection. No database connection or write occurred.
- Arabic/English JSON, focused source contracts, diff hygiene, unchanged frontend-source identity, and absence of `storage/storage` passed.
- The upper standalone Arabic/English Roles & Permissions configuration entry is absent. The registry, localized label, exact setup URL inputs, and active-state logic for Basic Data → Essentials → Users, roles, and scopes remain present.
- The Product Catalog page-level `ProductPricingReadiness` consumer and component call are absent. The shared setup context still renders the same readiness component for `product-masters` in the upper setup/readiness header.
- No application frontend source or dependency lockfile changed; Vite was not rebuilt.
- Vite manifest SHA-256: `b0d6e69cb067407c8ecc07e72eed30f06fb88a4050a3bd55f4f526c5c20606df`; 12 referenced assets passed regular/non-symlink/non-empty validation.
- Font manifest SHA-256: `66edf17c93351fe01158be7414a441a762f106417c74351881876b405b8b10ca`; 7 unique manifest/embedded-CSS references passed regular/non-symlink/non-empty validation.
- Focused PHPUnit was blocked by execution policy before launch and was not run.
- Arabic desktop, English desktop, and Arabic mobile browser automation was blocked by execution policy before launch and was not run; no console-error or overflow result is claimed.
- Production, databases, migrations, services, symlinks, tags, pushes, remotes, and the original dirty worktree were untouched.
