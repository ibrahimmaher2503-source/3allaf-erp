# 21 — Package Selection and Capability Register

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Approved package-selection process; package names/versions require actual project compatibility review  
**Technology baseline:** Laravel 13, PHP 8.4, Livewire 4, Flux UI 2, Tailwind CSS 4, Vite 8

---

## 1. Purpose

This document prevents duplicate, speculative, abandoned, or unsafe dependencies. It defines when a package may be introduced and how its capability is approved.

---

## 2. Core Rules

1. Laravel and Flux native capability first.
2. Do not build common infrastructure from scratch when an approved mature capability exists.
3. Do not install overlapping packages for the same concern.
4. Package selection happens when the implementing task genuinely needs it.
5. Exact name/version is approved only after inspecting the actual application.
6. Every package requires compatibility, maintenance, security, license, architecture, and exit review.
7. A package must not force a rejected architecture such as SPA, separate frontend, or generic workflow engine.
8. Prefer focused packages over large frameworks.
9. Package behavior must remain behind application-owned actions/services where practical.
10. Record the decision in the Decision Log.

---

## 3. Evaluation Checklist

Before installation confirm:

- Supports current PHP/Laravel versions.
- Supports Livewire/Blade usage where relevant.
- Active maintenance and recent releases.
- Acceptable open issues and security history.
- Compatible license.
- No unwanted telemetry.
- No hidden cloud dependency.
- No conflict with existing packages.
- No duplicate tables/components.
- Configurable storage/queue/cache.
- Test/dev dependencies do not violate project directives.
- Migration rollback/uninstall path.
- Data portability.
- Performance impact.
- Arabic/RTL/output compatibility where relevant.

---

## 4. Capability Register

| Capability | Default approach | Package status |
|---|---|---|
| Authentication | Laravel-native starter/session auth | Existing/native |
| Authorization | Current application permission foundation and Gates | Existing; no new engine unless justified |
| UI controls | Flux UI | Existing/approved |
| Styling | Tailwind CSS | Existing/approved |
| Browser interactivity | Livewire first; minimal Alpine/TypeScript | Existing/approved |
| Audit/activity | Application policy first; mature focused package may be evaluated | Not selected |
| Media/uploads | Laravel storage + focused media package only if it materially reduces risk | Not selected |
| Excel import/export | One mature Laravel Excel package | Not selected |
| PDF generation | One maintained HTML-to-PDF capability compatible with Arabic | Not selected |
| Barcode rendering | One focused renderer; allocation remains application-owned | Not selected |
| Backup | One maintained Laravel-compatible backup capability after production storage approval | Not selected |
| Error monitoring | Provider/capability after owner and hosting approval | Not selected |
| Charts | Small accessible chart capability only when dashboard task starts | Not selected |
| PWA/offline | Browser APIs plus focused helper only if needed | Not selected |
| Data tables | Livewire/Flux patterns first | No generic grid selected |
| Searchable selects | Flux capability first | No duplicate package |
| Calendar | Evaluate only for party/assets scheduling if native patterns are insufficient | Not selected |
| Image processing | Server-native/library/package based on actual deployment | Not selected |
| Malware scanning | Production-integrated scanner/provider | Not selected |

---

## 5. Approval Record Template

For each selected package record:

- Capability.
- Package name.
- Version.
- Repository/vendor.
- License.
- Supported PHP/Laravel versions.
- Maintenance status.
- Security review result.
- Why Laravel/Flux native was insufficient.
- Alternatives considered.
- Data/schema introduced.
- Runtime services required.
- Queue/cache/storage impact.
- Frontend assets introduced.
- Configuration and secrets.
- Upgrade plan.
- Removal/exit plan.
- Decision ID.
- Tasks using it.

---

## 6. Prohibited Package Patterns

Do not introduce:

- Filament.
- Inertia, Vue, React, or SPA stacks.
- Separate frontend/API frameworks for normal screens.
- Generic ERP/POS/inventory engines.
- Generic wallet engines.
- Generic approval/workflow engines that obscure explicit business rules.
- Duplicate permission engines.
- Duplicate media libraries.
- Abandoned packages.
- Packages requiring public protected-file URLs.
- Packages that silently modify production configuration.
- Packages that prevent data portability.

---

## 7. Package Introduction Workflow

1. Task identifies a real capability gap.
2. Confirm native/app capability is insufficient.
3. Evaluate up to three credible options.
4. Record compatibility/security/license findings.
5. Select one or keep native.
6. Add Decision Log entry.
7. Install with the narrowest configuration.
8. Document migrations/config/services.
9. Verify manually through affected browser workflows.
10. Update deployment and maintenance documentation.

---

## 8. Upgrade and Maintenance

- Pin compatible versions according to Composer/npm strategy.
- Review changelogs before upgrades.
- Do not combine major framework/package upgrades with unrelated feature tasks.
- Record breaking changes.
- Re-run applicable manual browser verification.
- Keep a package inventory in the handoff documentation.
- Remove unused packages and their configuration only through a documented change.

---

## 9. Current Decision

At this document’s creation, no new package is approved solely by this register. It approves the selection process and capability boundaries. Exact package choices remain task-specific and must be logged after actual compatibility review.
