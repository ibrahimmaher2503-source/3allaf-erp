# QA/UAT Product Audit and Cleanup Handoff — NOT RUN

**Release:** `v0.1.22-hotfix15`
**Prepared:** 2026-09-05
**Execution status:** NOT RUN
**Production access:** NONE during preparation

This activity is deliberately separate from promotion and activation. It must not be folded into a deployment script or run automatically. The release only provides protected commands.

## Mandatory operator sequence

1. Confirm the active application is the intended Hotfix15 release, maintenance is OFF, and the exact active company identity is still ID `1`, code `TOY&JOY-01`. If either identity differs, stop; do not substitute a guessed value.
2. Run the read-only audit first as root from the active release:

   `/opt/cpanel/ea-php84/root/usr/bin/php artisan catalog:audit-test-products --company-id=1 --company-code='TOY&JOY-01'`

3. Preserve the complete output. Review each exact product ID, identity, operational/history dependency count, and configuration dependency count with the owner. A name or code match is only a candidate signal; it is not deletion authorization.
4. Do not delete anything that has an operational or historical dependency. Archive it through the authorized application workflow so every stock, sale, purchasing, pricing, accounting, audit, and document reference remains intact.
5. Before any production cleanup, create and verify a fresh database backup using the established production backup workflow. Record its path and SHA-256.
6. Only after owner approval of explicit dependency-free IDs, run the cleanup separately as root, replacing `123 456` with the approved IDs from the immediately preceding audit:

   `/opt/cpanel/ea-php84/root/usr/bin/php artisan catalog:cleanup-test-products 123 456 --company-id=1 --company-code='TOY&JOY-01' --confirm='DELETE-DEPENDENCY-FREE-TEST-PRODUCTS'`

7. Preserve command output, re-run the read-only audit, and record results in `.ai/TEST_RESULTS.md` only if these production operations actually occur.

## Fail-closed properties

- Both commands require root.
- Company ID and code must match the same active company.
- Cleanup accepts explicit IDs only; it never deletes by wildcard.
- Each product must still carry an explicit QA/TEST/UAT/DEMO identity marker.
- Dependencies and company exclusivity are re-evaluated inside the cleanup transaction.
- Any operational/history dependency, missing product, ambiguous company ownership, changed scope, or identity mismatch aborts cleanup.
- Deletion removes only dependency-free product configuration and records an audit event; no cascade into business history is authorized.

No audit, cleanup, database read/write, backup, seeder, or production command was executed while preparing this handoff.
