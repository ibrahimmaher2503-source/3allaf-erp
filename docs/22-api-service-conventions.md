# 22 — Application, Service, and Integration Conventions

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Approved implementation baseline  
**Architecture:** Laravel modular monolith, Blade, full-page Livewire, Flux UI  
**Boundary:** No separate frontend or ordinary-screen public API

---

## 1. Purpose

This document standardizes how application behavior is organized so modules remain explicit, secure, traceable, and consistent without creating unnecessary abstraction.

---

## 2. Architectural Direction

1. One Laravel application and repository.
2. Modular-monolith boundaries inside the application.
3. Blade layouts/templates.
4. Full-page Livewire for interactive screens.
5. Flux UI first.
6. Minimal Alpine.js or TypeScript only for browser/device behavior.
7. No Inertia, Vue, React, or separate SPA.
8. No separate API for ordinary application screens.
9. Internal endpoints are allowed only for documented device/PWA/integration needs.
10. Business rules live on the server.

---

## 3. Request Flow

Preferred flow:

`Route → Middleware → Livewire/Controller → Form/Request Validation → Application Action → Policy/Gate → Domain/Model Operations → Audit → Response`

Complex mutation logic must not be embedded directly in Blade templates or scattered across UI event handlers.

---

## 4. Application Actions

Use focused action classes for business mutations such as:

- Submit document.
- Approve document.
- Reject document.
- Allocate document number.
- Post stock movement.
- Record payment.
- Assign role/scope.
- Upload protected evidence.
- Reverse approved record.

Action conventions:

- One clear purpose.
- Typed/validated input.
- Explicit authorization.
- Explicit transaction boundary.
- Idempotency where needed.
- Returns a clear result or domain record.
- Emits/audits only after successful validation.
- Does not depend on browser/UI details.

---

## 5. Services

Use a service only when behavior:

- Coordinates multiple actions or aggregates.
- Encapsulates external/provider integration.
- Provides a reusable calculation or read model.
- Owns a coherent domain capability.

Do not create `*Service` classes as generic dumping grounds.

Examples of valid service capabilities:

- Number allocation.
- Weighted-average cost calculation.
- Stock availability.
- Protected file delivery.
- Report query composition.
- Offline synchronization.
- Print/PDF rendering integration.

---

## 6. Repositories

Do not introduce a generic repository layer by default.

Use Eloquent models, query scopes, and focused query/read-model classes. A repository is justified only when it provides a real boundary, such as an external data source or materially different persistence implementation.

---

## 7. Data Transfer and Input Objects

Use arrays for small internal inputs when clear. Use typed DTO/data objects when:

- Input spans multiple layers.
- The action has many fields.
- The same validated payload is reused.
- Explicit typing materially improves correctness.
- Import/offline/integration payloads require versioning.

DTOs must not mirror every database row without purpose.

---

## 8. Validation

1. Validate server-side for every mutation.
2. Livewire validation is acceptable for UI feedback.
3. Critical domain invariants are enforced again inside the action/domain layer.
4. Use stable validation keys and localized messages.
5. Never trust hidden/disabled UI values.
6. Verify branch/store/document scope from server state.
7. File validation follows `18-attachment-media-policy.md`.
8. State transitions follow `17-approval-policy.md`.

---

## 9. Authorization

Evaluation order:

1. Authenticated and active user/session/device.
2. Role has module/action permission.
3. Required branch/store scope.
4. Document type, activity type, state, and ownership.
5. Approval/override permission and configured limits.
6. Sensitive field permission.
7. Validation, separation, immutability, and concurrency.
8. Audit requirement.

Use:

- Route middleware for coarse access.
- Gates/policies for actions and records.
- Scoped queries for data visibility.
- Action-level authorization for mutation defense.

UI hiding never replaces server checks.

---

## 10. Transactions and Locking

Use database transactions for operations that must remain atomic, including:

- Approval.
- Number allocation.
- Stock posting.
- Payment posting.
- Wallet/loyalty movements.
- Shift close.
- Final settlement.
- Import approval.
- Reversal/correction.

Use row locks or optimistic version checks where concurrent users may act on the same record.

Do not make network/provider calls inside a long database lock. Queue them after commit where applicable.

---

## 11. Idempotency

Idempotency is required for:

- Approval/submit actions that may be retried.
- Payments.
- Stock posting.
- Offline synchronization.
- Import approval.
- Printing/label queue generation where duplicates matter.
- External callbacks if introduced later.

Store or derive a stable idempotency key and return the existing result for safe retries.

---

## 12. Errors and Responses

### 12.1 Browser Screens

- Localized validation errors.
- Safe denied page.
- Conflict/stale message.
- Generic unexpected error with request ID.
- Preserve user input where safe.
- Do not expose stack traces or secrets.

### 12.2 JSON/Internal Endpoints

Use a consistent envelope when an internal endpoint is needed:

```json
{
  "ok": false,
  "code": "stale_record",
  "message": "The record changed. Reload and try again.",
  "request_id": "uuid",
  "errors": {}
}
```

Do not build a broad public API solely for Livewire screens.

---

## 13. Events and Jobs

Use events/jobs when they provide a real asynchronous or decoupling benefit:

- Heavy import processing.
- Export generation.
- Email/notification.
- Image processing.
- Backup.
- Large report generation.
- Offline conflict processing.
- Post-commit external integration.

Rules:

- Dispatch after successful commit where applicable.
- Jobs are idempotent.
- Queue failures are observable.
- Do not move core transactional validation into an unreliable background job.

---

## 14. Query Conventions

1. Scope every query by authorization.
2. Use explicit branch/store/activity filters.
3. Paginate high-volume views.
4. Avoid unbounded exports.
5. Prevent N+1 queries.
6. Select only required fields for sensitive/reporting views.
7. Use indexed filter columns when schema work reaches that module.
8. Keep cost, wallet, audit, and customer-sensitive fields separately permissioned.
9. Read models may be used for complex dashboards/reports.

---

## 15. Models

Models may contain:

- Relationships.
- Casts.
- Small invariant helpers.
- Query scopes.
- State predicates.
- Domain-safe value accessors.

Avoid:

- Large multi-step workflows.
- Browser/UI concerns.
- Hidden authorization side effects.
- Provider integration.
- Generic catch-all static helpers.

---

## 16. Module Boundaries

Each module should own:

- Routes/screens.
- Models and migrations.
- Actions/services.
- Policies/scopes.
- Validation.
- Audit event names.
- Print/export views.
- Documentation traceability.

Cross-module calls should use explicit actions/services rather than reaching into another module’s UI component.

Retail and party modules must preserve their data and workflow separation.

---

## 17. Naming

Use clear domain names:

- Singular model names.
- Plural table names.
- Verb-based action names.
- Stable permission keys such as `module.action`.
- Explicit state names.
- `*Policy` for record authorization.
- `*Action` for mutations.
- `*Query` or `*ReadModel` for complex reads.
- `*Data`/`*DTO` only when justified.

Avoid ambiguous names such as `Manager`, `Helper`, `Util`, or `Process` without domain context.

---

## 18. Logging and Audit

- Operational logs diagnose the system.
- Audit logs prove business/security events.
- They are not interchangeable.
- Every request carries a correlation/request ID.
- Sensitive business mutations follow `19-audit-immutability-policy.md`.
- Secrets and protected values are never logged.

---

## 19. Manual Browser Verification

For every implemented slice verify:

- Normal workflow.
- Validation failure.
- Unauthorized role.
- Direct URL/forged action.
- Branch/store scope.
- Stale/concurrent behavior where applicable.
- Retry/double-submit behavior.
- Audit result.
- RTL/LTR.
- Desktop/mobile.
- Browser console and network.

No automated tests are created or executed under the current project directive.
