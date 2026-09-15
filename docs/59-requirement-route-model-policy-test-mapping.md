# Requirement → Route / Model / Policy / Test Mapping

**Scope:** Master Change Request requirements §0–72, as recorded in the compact matrix in `docs/client-feedback-remediation-checklist.md` (2026-08-20).  
**Purpose:** provide a review index for the currently live Laravel surfaces. This appendix is traceability only; it does not claim requirement, wave, UAT, Production, or release closure.

## Reading rules

- Requirement status is inherited from the §0–72 matrix; it is not re-scored here.
- Routes are the live route files and named surfaces currently present in the repository. Models/policies are the domain objects or authorization boundaries used by those surfaces.
- “Focused evidence” means the tests and browser evidence already cited by the matrix and current status files. No new test was created or run for this appendix.
- Owner decision, physical-device, human-UAT, Production, and release boundaries remain external wherever the matrix says so.

| Requirements | Live route / component surface | Models / policy boundary | Existing evidence index | Boundary |
|---|---|---|---|---|
| §0–1 | Initial Setup and client-feedback checklist; `/admin/settings` and setup navigation | `InitialSetupStatus`; settings authorization and readiness prerequisites | Matrix §0–1; setup/browser consolidation | Partial: complete Master verdict remains open. |
| §2–3 | `routes/settings.php`; `/admin/settings` tabs | Company/settings models; settings permissions, audit and dirty-state guards | Company identity, settings audit/authorization, bilingual browser evidence | Owner values and UAT remain open. |
| §4–11, §13–16 | `routes/branches.php`, `routes/stores.php`, `routes/locations.php`, `routes/cash-drawers.php`; setup navigation | Branch, warehouse/store, selling-store mapping and cash-drawer models; `visibleTo`/branch-scope authorization | Multi-branch **20 tests / 104 assertions**, §66 **15 tests / 118 assertions**, browser batches | §12 taxonomy and §55 inheritance provenance remain owner decisions. |
| §17–19 | Catalog import/supplier and setup/account routes in `routes/catalog.php` and `routes/settings.php` | Import batch/review models; maker-checker authorization; authentication/account terminology | Staged import/reviewer/self-approval evidence; bilingual browser batches | No direct unvalidated write; owner policy language remains contextual. |
| §20–25 | `routes/settings.php`; payment/tax settings and POS calculation surfaces | Payment methods, tax settings, `PosCalculationService`; settings permissions/audit | Payment/tax **14 tests / 63 assertions**; focused calculation checks | Enabled methods, evidence policy, accounting/legal values require owner approval. |
| §26–30 | `routes/settings.php`; document-sequence settings and preview/override actions | Document sequence model; dedicated override authorization, reason, audit, locking | Sequence **5/28** plus **3/16** focused checks | Numbering values remain owner inputs. |
| §31–33 | `routes/settings.php?tab=printers`; printer configuration/preview surfaces | Printer configuration model; Global → Branch → Location resolver and scope authorization | Scoped printer checks and §66 printer scenario | Physical printers/devices and human UAT are external. |
| §34–37 | Sidebar/settings navigation; category routes in `routes/catalog.php` | Category hierarchy model and parent/cycle guards | Navigation browser evidence; category focused tests/browser checks | No additional external boundary recorded. |
| §38–45 | `routes/customers.php` (`customers.*`, groups, children, consent, loyalty, wallets) | Customer, child, consent, group, loyalty and separate Product/Party Wallet models; customer-sensitive/object-scope permissions | Customer/child/duplicate/group focused checks; authenticated QA; §66 customer scenario | Customer/child/consent/wallet policy and genuine owner data remain open. |
| §46–49 | `routes/catalog.php` supplier routes; `routes/purchasing.php` purchase-order routes | Supplier/group/contact/destination models; purchase-order authorization and recipient resolver | Supplier/recipient **2 tests / 5 assertions**; authenticated supplier/PO QA | Genuine supplier contacts and policy values remain owner data. |
| §50–53 | Customer, branch/store/drawer, and setup routes listed above | Phone normalizer; validation/readiness prerequisites; permission-aware CTA boundaries | Arabic/English validation and prerequisite browser batches | Readiness is local persisted-state evidence, not Production readiness. |
| §54–55 | Branch/store/location/settings routes; scoped customer/category/printer/sequence paths | Company/branch/location scope queries; inheritance/source-marker boundary | Multi-branch **20/104** and §66 scenario evidence | Inheritance provenance (§55) remains unresolved. |
| §56–60 | Master-data list/create/edit routes; setup/settings surfaces | Validation, archive/deactivation, dirty-state and duplicate-submit boundaries | Bilingual browser batches covering empty, validation, destructive, loading and dirty states | Physical output and broad UAT remain external where applicable. |
| §61–63 | Settings, customer, supplier, purchasing, and scoped branch routes | Audit event recording; policies/gates; transactions, locks, uniqueness and scope filters | Settings audit/authorization **6 tests / 38 assertions**; focused IDOR/concurrency/scope checks | Evidence is local/disposable only. |
| §64–66 | Product, POS, Page Guide, Appearance Customizer, settings, and master-data routes | Existing route authorization and domain actions; no new abstraction implied | Browser regression batches; §66 **15/118** minimum scenario matrix | No full-suite or final client/UAT claim. |
| §67–68 | Initial Setup owner-decision cards and affected bilingual setup/master screens | Permission-aware CTAs; no fictitious owner-value persistence | Owner-decision CTA browser smoke and bilingual review | Owner approval is intentionally pending. |
| §69–70 | Migration/seeder execution paths; deterministic disposable fixtures | Migration ordering; canonical authorization seeder; fixture/database boundary | 75 forward migrations, corrected rollback, second forward, two stable seeder runs | Complete setup fixture coverage and owner-data rules remain partial. |
| §71–72 | This checklist, this appendix, and current `.ai/` status records | Evidence ledger and closure boundary; no implementation claim added | Matrix/evidence synchronization and `git diff --check` | Final deliverable, owner approval, UAT, Production, release verdict, commit and push remain open. |

## Source boundaries

This appendix intentionally points to existing route/model/policy surfaces and recorded evidence; it does not infer missing routes, models, policies, tests, or approvals. Where a requirement is marked `Owner decision`, `Partial`, or externally bounded in the compact matrix, that status remains unchanged.

## 2026-08-20 — Excel-import/UI QA evidence update

- Focused disposable-MariaDB results: supplier staged import **4 tests / 14 assertions**; catalog-reference staged import RED **3/3** (two intended exact-header errors) then GREEN **3/9**; customer staged import **8/10**; product editor/report rendering **2/11**; Initial Setup **14/9,985**. The customer import shell/RTL correction separately passed **9/15** after an expected RED.
- Migration `2026_08_20_000093_create_catalog_reference_import_tables` was applied successfully to disposable `toyjoy_ui_qa_20260820` on MariaDB port 3307 after removing only the two empty partial tables left by the earlier failed attempt; its MariaDB identifier names are now within the limit.
- Authenticated Administrator UI rendered supplier, catalog-reference, customer, and product import screens, product creation, reports, and inventory reports without HTTP 500, console warnings/errors, or desktop overflow. Supplier/reference/product import and product creation also had no horizontal overflow at CSS 375.
- Browser file-chooser automation did not emit a chooser, so no actual UI file selection, stage submission, approval, imported-data persistence, or template-download event is claimed. Owner data, UAT, Production, physical devices, release, commit, and push remain external.

## 2026-08-20 — Import-review notification mapping update

- Product, supplier, customer, and catalog-reference readiness transitions now use native Laravel database notifications. Each alert stores its file, batch type, and direct review route; recipients are active users authorized for that exact review permission, excluding the requester.
- Focused MariaDB RED was **2/2 failed** because no alerts were delivered and the notification page rendered empty. GREEN passed **2 tests / 18 assertions** on `toyjoy_client_feedback_20260819` at `127.0.0.1:3307`: all four readiness transitions, Administrator delivery, requester/unauthorized exclusion, alert rendering, and authorization-scoped product review access.
- This evidence does not claim browser file selection/upload, human UAT, Production, external delivery, release, commit, or push.
