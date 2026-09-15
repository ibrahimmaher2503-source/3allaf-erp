# 15 — Security Checklist

Status values: `Not Started`, `Pass`, `Fail`, `Blocked`, or `Not Applicable` with reason. Every item starts `Not Started`.

## Identity, Session, and Request Security

| ID | Control | Status |
|---|---|---|
| SEC-001 | Use Laravel's maintained authentication/session mechanisms; no custom password/reset/verification system when framework capability applies. | Not Started |
| SEC-002 | Enforce owner-approved password policy, secure hashing, reset-token expiry/single use, generic account-recovery errors, and optional MFA only if approved. | Not Started |
| SEC-003 | Secure cookies (`Secure`, `HttpOnly`, appropriate `SameSite`), HTTPS, session ID regeneration, idle/absolute expiry, logout/revocation, and no session fixation. | Not Started |
| SEC-004 | Apply CSRF protection to state-changing browser requests; service-worker/sync endpoints use an explicitly safe authenticated anti-replay design. | Not Started |
| SEC-005 | Rate-limit login/reset and abuse-prone search/export/upload/sync actions; lockout/alert policy avoids account enumeration and denial-of-service abuse. | Not Started |

## Input, Output, and Data Access

| ID | Control | Status |
|---|---|---|
| SEC-006 | Validate all input on server with allow-lists/ranges/types/state/context; client validation is UX only. | Not Started |
| SEC-007 | Escape Blade output by default, sanitize approved rich content, use safe URLs/headers, and prevent stored/reflected XSS. | Not Started |
| SEC-008 | Use Eloquent/query binding; allow-list filters/sorts/columns; no interpolated SQL; review raw expressions for injection. | Not Started |
| SEC-009 | Protect against mass assignment with explicit fillable/data mapping; never bind role, scope, approval, totals, price, balance, or status from untrusted payloads. | Not Started |
| SEC-010 | Minimize and purpose-limit customer/child/consent data; apply retention, redaction, export, log, and support-access controls. | Not Started |

## Authorization and Isolation

| ID | Control | Status |
|---|---|---|
| SEC-011 | Enforce policies/gates server-side for module/action/document/state/ownership and direct requests; hidden UI is not authorization. | Not Started |
| SEC-012 | Scope every query/action/export/download to authorized branches and stores before lookup/aggregation; avoid record-existence leakage. | Not Started |
| SEC-013 | Enforce retail/party activity separation and block mixed order/invoice/resource types. | Not Started |
| SEC-014 | Use distinct policies/actions/models/tables/screens for Product Wallet and Party Wallet; deny Cashier Party Wallet and Party Manager Product Wallet. | Not Started |
| SEC-015 | Require explicit approval/override/export/logical-delete/reverse/cancel/open-price/negative-stock/variance rights and configured limits/reasons. | Not Started |
| SEC-016 | Apply field-level controls to cost, margin, expected shift values, wallets, evidence, customer sensitivity, audit before/after, and export columns. | Not Started |

## Transaction and Document Integrity

| ID | Control | Status |
|---|---|---|
| SEC-017 | Approved documents/lines/totals and ledgers are immutable; eligible masters/drafts use logical delete; corrections are source-linked documents. | Not Started |
| SEC-018 | Generate unique sequential document numbers atomically under configured scope; prohibit unsafe `max + 1`; handle retry/idempotency. | Not Started |
| SEC-019 | Use transactions, locks/unique constraints/version checks for stock, price, shifts/drawers, wallet/loyalty/Gift Card, asset intervals, finalization, imports and sync. | Not Started |
| SEC-020 | Prevent duplicate submission/replay with server idempotency and UI loading/disabled state; verify source event uniqueness. | Not Started |
| SEC-021 | Reconcile stock and value ledgers to summaries; never expose direct balance editing; raise controlled incidents for mismatches. | Not Started |

## Files, Imports, Exports, and Attachments

| ID | Control | Status |
|---|---|---|
| SEC-022 | Validate upload size, MIME and signature, extension, image dimensions/purpose; reject executable/polyglot/unsafe content and apply malware scanning if infrastructure approves. | Not Started |
| SEC-023 | Generate safe storage names outside executable/public paths, use least-privilege disks/keys, encrypt as approved, and never expose raw paths. | Not Started |
| SEC-024 | Authorize every attachment/export/error-file access, use short-lived protected delivery where appropriate, and audit sensitive access/reprint/replacement. | Not Started |
| SEC-025 | Stage Excel imports; validate references/duplicates/formulas; never write invalid rows; sanitize error files and prevent spreadsheet formula injection in exports. | Not Started |
| SEC-026 | Apply export row/range/field limits, queue/expire large artifacts, authorize download on every request, and prevent cache/signed-link scope leakage. | Not Started |

## Audit, Secrets, Runtime, and Recovery

| ID | Control | Status |
|---|---|---|
| SEC-027 | Append tamper-resistant audit for required events with actor/time/session/device/scope/source/reason/protected before-after; no edit/delete. | Not Started |
| SEC-028 | Keep environment secrets outside code/browser/logs/backups as appropriate; least-privilege database/storage/service accounts; rotate and document ownership. | Not Started |
| SEC-029 | Production debug is off; errors are generic/correlated; logs/monitoring redact secrets, tokens, evidence, wallet/customer/offline payload data and have retention/access controls. | Not Started |
| SEC-030 | Backups are encrypted, monitored, retained off-host as approved, access-controlled, include required attachments, and are restore-tested to RPO/RTO in isolation. | Not Started |
| SEC-031 | Deployment uses supported runtime/dependencies, lockfile integrity, vulnerability/license review, HTTPS/security headers, controlled migrations, worker/scheduler supervision and rollback/recovery. | Not Started |

## Offline and Synchronization

| ID | Control | Status |
|---|---|---|
| SEC-032 | Enroll and bind approved device/user/branch/shift; minimize IndexedDB data, encrypt as platform/policy allows, version schema, expire data, and clear on logout/revocation/device loss. | Not Started |
| SEC-033 | Service worker caches only approved versioned shell/static/reference data; never caches arbitrary authenticated pages, attachment evidence, wallet/customer or broad report responses. | Not Started |
| SEC-034 | Enforce offline amount/count/duration/price-age/evidence limits and block credit, wallets, loyalty redemption, special discounts, unpriced/stale/conflict-prone actions. | Not Started |
| SEC-035 | Sync reauthenticates/reauthorizes, validates payload/schema/hash/time/policy, uses idempotency/anti-replay, and assigns final number/effects only on atomic server acceptance. | Not Started |
| SEC-036 | Server truth prevails for stock/price/wallet/loyalty; every reject/conflict has protected comparison, owner, reason, disposition, source correction and immutable history. | Not Started |

## Reports and Operational Administration

| ID | Control | Status |
|---|---|---|
| SEC-037 | Report/dashboard queries scope before aggregation, prevent unauthorized cost/margin/customer/wallet disclosure, bound ranges, and avoid cross-user cache contamination. | Not Started |
| SEC-038 | Printer/scanner/camera/local-device integrations expose minimum data, fail safely, do not execute untrusted content, and are verified on approved managed devices. | Not Started |
| SEC-039 | Administrative changes to roles, scopes, tax, methods, numbering, price, store mapping, printer, backup and offline policies require explicit rights, confirmation, and audit. | Not Started |
| SEC-040 | Security incidents and critical integrity mismatches have named owners, containment/evidence/recovery/escalation procedures, and cannot be hidden by manual data edits. | Not Started |
