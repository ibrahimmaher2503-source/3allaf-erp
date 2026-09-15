# 30 — Platform and Operations Specification

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Detailed implementation specification derived from the approved PRD, Implementation Plan, Architecture, Roles/Permissions, and policies 17–29  
**Authority order:** PRD functional behavior → Implementation Plan sequencing/phase gates → approved decisions/policies → this specification  
**Important:** Exact production master data, legal wording, hardware models, provider choices, and final numeric limits remain configurable where the source documents do not define them.

---


## 1. Purpose

Define the complete Phase 1 platform foundation and operational configuration needed by every module: runtime environments, PWA shell, deployment, queues, scheduler, cache, health, monitoring, backup/restore, company settings, branches, stores, selling-store mappings, cash drawers, payment methods, tax settings, numbering, printers, templates, authentication/session dependencies, audit integration, and operational readiness.

## 2. Traceability

- Implementation Plan: DM 1.1, DM 1.2, DM 1.3, DM 1.4.
- PRD: MD-01, NFR-01–NFR-07, CSH-01, POS-02–POS-04.
- Tasks: TSK-001–TSK-009.
- Related policies: docs/17–22.
- Related blockers: BLK-001–BLK-008.

## 3. Platform Scope

### 3.1 In Scope

- One Laravel modular-monolith application.
- Blade layouts, full-page Livewire, Flux UI, Tailwind, Vite.
- Environment-specific configuration.
- Secure session authentication and account recovery.
- Arabic-first UI with English LTR.
- Restricted PWA shell and connectivity status.
- Request/correlation IDs.
- Safe 403/404/419/429/500/503 handling.
- Queue, scheduler, cache configuration interfaces.
- Health and operational status.
- Backup/restore runbook and status.
- Error-monitoring integration boundary.
- Company, branch, store, drawer, tax, payment, numbering, printer, and template masters.
- Users, roles, permissions, scopes.
- Approval, audit, attachment, and immutability foundations.

### 3.2 Explicitly Not Assumed

- A production hosting provider.
- A specific production database engine.
- Redis availability.
- A specific monitoring vendor.
- A specific backup vendor.
- A specific printer driver or local hardware bridge.
- MFA until approved.
- Public attachment storage.
- A separate SPA or public API for ordinary screens.

## 4. Environment Model

| Environment | Purpose | Data | Debug | External integrations |
|---|---|---|---|---|
| Local | Development and manual browser verification | Synthetic/local | Allowed locally | Disabled/mock/configurable |
| Staging/UAT | Client workflow verification | Approved test data | Disabled | Controlled |
| Production | Live operations | Real | Disabled | Approved only |

Required configuration groups:

- Application URL, locale, timezone.
- Database.
- Session and cookies.
- Queue.
- Cache.
- Scheduler.
- Mail/notification.
- Storage disks.
- Monitoring.
- Backup.
- PWA/offline flags.
- Print and local-device configuration.
- Security headers.
- Feature flags for unresolved production capabilities.

No secrets are displayed in the UI or committed to source control.

## 5. Request and Error Baseline

Every request shall:

1. Receive a server-generated request UUID.
2. Place it in Laravel Context.
3. Return it as `X-Request-ID`.
4. Include it in safe error pages and operational logs.
5. Preserve it in audit records for sensitive actions.

Required safe pages:

- 403 denied.
- 404 not found.
- 419 expired session/CSRF.
- 429 rate limited.
- 500 unexpected error.
- 503 maintenance/unavailable.

Error pages shall be bilingual, responsive, free of stack traces/secrets, and include safe retry/navigation actions.

## 6. Authentication and Session Contract

Required capabilities:

- Login.
- Logout.
- Forgot/reset password.
- Session regeneration.
- Reset-token expiry and single use.
- Account active/locked state.
- Rate limiting.
- Session revocation capability.
- Locale/profile basics.
- Direct-route denial.

Configurable production values:

- Password policy.
- Session lifetime.
- Lockout threshold/duration.
- MFA.
- Concurrent session/device policy.
- Verification policy.

## 7. PWA Shell

### 7.1 Installable Shell

- Manifest with localized name, icons, theme/background colors.
- Service worker limited to approved shell/static assets.
- Versioned assets.
- Install availability indicator.
- Update-available flow.
- Connectivity badge.

### 7.2 Cache Boundary

Allowed:

- Versioned static assets.
- Minimal approved reference data if explicitly configured.

Prohibited:

- Arbitrary authenticated HTML.
- Customer, payment, wallet, audit, or sensitive responses.
- Unbounded transactional data.

### 7.3 Offline Boundary

The platform shell may remain usable offline, but transactional offline POS is governed separately. Logout, revocation, expiry, schema changes, and device loss must clear or invalidate protected local data.

## 8. Queue, Scheduler, and Cache

### Queue candidates

- Imports/exports.
- PDF batches.
- Label jobs.
- Notifications.
- Image processing.
- Sync/conflict processing.
- Backups.
- Monitoring signals.

### Scheduler candidates

- Expiry.
- Alert generation.
- Cleanup.
- Reconciliation checks.
- Backup scheduling.
- Stale temporary upload cleanup.

### Rules

- Core financial/stock integrity posting remains synchronous and atomic.
- Queue jobs are idempotent.
- Failed jobs are observable.
- Cache is never the authority for stock, price, wallet, loyalty, or shift settlement.
- Exact drivers remain configurable.

## 9. Health and Operational Status

Health checks shall cover, where configured:

- Application boot.
- Database connectivity.
- Storage read/write.
- Queue worker freshness.
- Scheduler heartbeat.
- Cache availability.
- Backup freshness.
- Monitoring connectivity.
- Disk/attachment capacity.
- Build/version identity.

Health access is permission-controlled. Public health output must not reveal infrastructure details or secrets.

States:

- Healthy.
- Degraded.
- Down.
- Unknown/not configured.

## 10. Backup and Restore

### Backup scope

- Database.
- Protected attachments.
- Required configuration metadata.
- Encryption keys only through separately approved secure key-management/runbook procedures.

### Required metadata

- Started/finished time.
- Scope.
- Destination.
- Size.
- Result.
- Error summary.
- Retention expiry.
- Verification status.

### Restore

- Restore only to approved isolated environment first.
- Record actor, reason, source backup, target, timestamps, and result.
- Verify application boot, data integrity, attachments, sequences, and permissions.
- Never claim backup readiness without an actual restore verification.

Production RPO/RTO, destination, encryption, and ownership remain pending.

## 11. Monitoring and Logging

Operational logs and audit logs are distinct.

Operational monitoring must support:

- Unhandled exceptions.
- Queue failures.
- Scheduler failure.
- Backup failure/staleness.
- Storage capacity.
- Repeated authorization failures.
- Sync conflicts/failures.
- Health degradation.

Secrets and protected customer/payment evidence must be redacted. Alert routing and vendor remain production decisions.

## 12. Company Settings

Company record supports:

- Legal/display names.
- Arabic/English names.
- Address/contact fields.
- Tax identity fields where applicable.
- Currency and precision.
- Logo/print identity.
- Default locale/timezone.
- Status/version/effective date where required.

Changes are authorized, validated, and audited. Historical documents snapshot required identity fields and do not change retroactively.

## 13. Branches and Stores

### Branch

- Unique code.
- Arabic/English name.
- Status.
- Address/contact.
- Timezone if different.
- Manager assignment where configured.
- Default selling store mapping.

### Store

- Unique code.
- Name.
- Type.
- Branch relationship where applicable.
- Status.
- Stock/selling/party eligibility flags.
- Scope and manager assignment.

### Selling-store mapping

- One effective selling store per branch at a time.
- Effective history preserved.
- Non-overlapping periods.
- Override requires permission, reason, and audit.
- POS sells only from assigned selling store unless authorized override applies.

## 14. Cash Drawer Masters

Fields:

- Unique code.
- Name.
- Branch.
- Store/context.
- Status.
- Assigned cashier where configured.
- Current assignment history.

Rules:

- No conflicting active assignment.
- No reassignment/deactivation with active shift.
- No opening balance invented at master level.
- Every change audited.

## 15. Payment Methods

Support at minimum:

- Cash.
- Manually recorded electronic payment.

Fields:

- Code/name.
- Type.
- Active status.
- Evidence required flag.
- Evidence purpose.
- Receipt/print label.
- Branch scope if applicable.
- Offline eligibility.
- Sort order.

No payment-gateway integration is introduced.

## 16. Tax Settings

- Tax is optional per invoice.
- Authorized user selects whether tax applies.
- Normal unified invoice sequence remains.
- Rates, rounding, inclusive/exclusive mode, and legal wording are configurable.
- Historical documents preserve applied tax snapshot.
- Unsafe deactivation is blocked when referenced by active workflows.

## 17. Numbering

Configurable sequence fields:

- Document type.
- Prefix/suffix.
- Branch/store scope.
- Reset period.
- Padding.
- Next value.
- Active/effective status.

Rules:

- Final numbers allocated transactionally.
- No duplicates under concurrency.
- Historical numbers immutable.
- Number override separately permissioned and audited.
- Drafts may use UUID/non-final references.

## 18. Printers and Templates

Printer configuration:

- Name.
- Branch/store.
- Purpose: thermal, A4, label.
- Connection/integration type.
- Paper size.
- Active/default status.
- Copies.
- Template association.

Template configuration:

- Document type.
- Locale/direction.
- Version.
- Effective status.
- Logo/header/footer.
- Required fields.

Production device drivers and exact hardware remain pending.

## 19. Authorization and Audit

Every platform action follows:

- Authentication.
- Module/action permission.
- Branch/store scope.
- State/dependency validation.
- Sensitive-field authorization.
- Reason/approval where required.
- Audit.

## 20. UI Screens

Minimum platform screens:

- Login/reset/profile/sessions.
- Admin shell.
- Operations shell.
- POS shell.
- Company settings.
- Branch list/detail/form.
- Store list/detail/form.
- Selling-store mapping/history.
- Drawer list/detail/form.
- Payment methods.
- Tax settings.
- Number sequences.
- Printer configurations/templates.
- Users/roles/scopes.
- Approval inbox.
- Audit list/detail.
- Health/backup status where approved.

## 21. Manual Browser Acceptance

Verify:

- RTL/LTR and desktop/mobile.
- Auth success/failure/rate-limit/logout/reset.
- Direct denied routes.
- Company/branch/store/drawer CRUD and dependency guards.
- Selling-store mapping history.
- Payment/tax/numbering/printer configuration.
- Concurrent sequence behavior through actual UI workflows when available.
- PWA install/update/connectivity.
- Safe errors and request IDs.
- Health authorization.
- Backup/restore evidence where environment permits.
- No console errors, unexpected failed requests, secret leakage, or page overflow.

No automated tests are created or executed under the current directive.
