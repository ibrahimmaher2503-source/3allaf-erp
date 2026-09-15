# Client Feedback Remediation Checklist

## CLIENT FIX QUEUE

- [x] CF-01–CF-05 — Earlier client-fix queue closures — **DONE** (historical evidence is preserved in `.ai/PROGRESS.md` and `.ai/SESSION_SUMMARY.md`).
- [x] CF-06 — POS linkage to branch and selling warehouse — **DONE**.
- [x] CF-07 — Cash drawer association — **DONE**.
- [ ] CF-08 — Archive safety — **PARTIAL**: post-fix TDD was RED for malformed `onclick` then GREEN at **2 tests / 22 assertions**. Category and Supplier native Arabic dialogs explicitly dismissed; Store archive modal `Cancel` passed without approval. The Drawer dialog emitted but the browser bridge auto-dismissed before explicit cancel, the Branch button was outside the visible surface, and Store Deactivate confirmation plus an independent approval decision remain open.
- [x] CF-09 — Egyptian phone UX — **DONE**.
- [x] CF-10 — Sidebar active state — **DONE**.
- [x] CF-11 — Settings navigation clarity — **DONE**.
- [x] CF-12 — Payment-method setup meanings — **DONE (local evidence only)**.
- [ ] CF-13 — **PARTIAL**: local settings evidence exists; full requirement closure, owner decision evidence, UAT, and release evidence remain open.
- [ ] CF-14 — **PARTIAL**: local settings evidence exists; full requirement closure, owner decision evidence, UAT, and release evidence remain open.
- [x] CF-15 — Printer/template UX — **DONE**.

## EXPANDED MASTER-REQUEST AUTHORIZATION — 2026-08-19

The owner directed completion of **all remediable notes** in `docs/Master Change Request — Client Feedback Remediation & Setup UX Overhaul.md` (requirements 0–72, acceptance/test obligations, and the document's P0/P1/P2 priority order). This supersedes the narrow CF queue as the implementation-planning boundary. The queue remains the factual progress ledger; checked items below are not completion claims.

### Wave 0 / P0 — setup blockers

Eight expanded groups below have recorded Local/Dev slices. They are **requirement-level PARTIAL**, not closed master-request groups; the other seven groups remain open.

- [ ] Initial-setup-only goal, mandatory discovery, requirement classification, and complete route/model/policy/test mapping (master §§0–1).
- [x] Canonical settings and company identity persistence/audit/dirty-state behavior (master §§2–3) — **LOCAL SLICE EVIDENCED; REQUIREMENT-LEVEL PARTIAL**.
- [ ] Branch source of truth, six-branch creation, terminology, branch→warehouse→POS→drawer relationships, branch dropdowns, counts, POS linkage, drawer context, and safe warehouse archive/delete (master §§4–13).
- [ ] Phone normalization/error handling, actionable validation, prerequisite/empty states, and sidebar/navigation corrections (master §§34, 50–57).

### Wave 1 / P1 — setup architecture and business configuration

- [ ] Timezone inheritance and multi-branch setup checklist/readiness (master §§14–16, 53–55).
- [x] Manual/Excel staged master-data workflows and account/setup terminology (master §§17–19) — **LOCAL SLICE EVIDENCED; REQUIREMENT-LEVEL PARTIAL**.
- [x] Payment methods, payment evidence, offline wording, tax/zero-tax/override rules (master §§20–25) — **LOCAL SLICE EVIDENCED; REQUIREMENT-LEVEL PARTIAL; OWNER VALUES/APPROVAL REMAIN OPEN**.
- [x] Daily/scoped document sequences, preview, override safety, and version terminology (master §§26–30) — **LOCAL SLICE EVIDENCED; REQUIREMENT-LEVEL PARTIAL; OWNER NUMBERING VALUES REMAIN OPEN**.
- [x] Printers, templates, assignment, runtime selection, and configuration-history UX (master §§31–33) — **LOCAL SLICE EVIDENCED; REQUIREMENT-LEVEL PARTIAL; PHYSICAL HARDWARE/UAT REMAIN OPEN**.

### Wave 2 / P1 — master data

- [x] Category optional English name and hierarchical ordering (master §§36–37) — **LOCAL SLICE EVIDENCED; REQUIREMENT-LEVEL PARTIAL**.
- [x] Customer registration prerequisite, name/duplicate/consent UX, grouping, child profiles, loyalty CTA, and Product Wallet state (master §§38–45) — **LOCAL SLICE EVIDENCED; REQUIREMENT-LEVEL PARTIAL; OWNER POLICY/DATA REMAIN OPEN**.
- [x] Supplier groups, structured contacts, communication destinations, payment terms, and order-recipient resolution (master §§46–49) — **LOCAL SLICE EVIDENCED; REQUIREMENT-LEVEL PARTIAL; OWNER POLICY/DATA REMAIN OPEN**.

### Wave 3 / P2 + cross-cutting closure

- [ ] Bilingual quality, warehouse taxonomy, business help, UI consistency, and dirty-state feedback (master §§6, 12, 19, 35, 58–60).
- [ ] Multi-branch scope/inheritance audit, authorization, auditability, concurrency, migration safety, deterministic fixtures, and existing-regression rechecks (master §§54–55, 61–70).
- [ ] Requirement-by-requirement remediation matrix, before/after summary, real blockers, test/browser evidence, affected-story status, and final verdict (master §§64–72).

Parallel work is permitted for independent files/workstreams; shared-file edits must be coordinated. Authorization alone did not close any item; the eight checked expanded groups above have local-slice evidence but remain requirement-level PARTIAL, while seven groups remain open. No checkmark represents owner approval, physical-device acceptance, UAT, Production, release, commit, or push.

## 2026-08-19 — Wave 1/2 local implementation checkpoint (static-only)

The following records the earlier static checkpoint. At that time the Wave 1/2 items were unchecked; the later final local evidence section and current checkboxes above supersede that status.

- **Req 15 — Setup dashboard:** `InitialSetupStatus` and the setup/dashboard views expose persisted setup steps with explicit `Not started`, `Incomplete`, `Ready`, `Blocked`, and `Completed` states, record counts/reasons, and `Configure`/`Review` CTAs. The checklist includes company identity, branches, warehouses, drawers, payments, taxes, sequences, printers/templates, customer/supplier groups, categories, products, and opening configuration; prerequisites can explain a blocked step.
- **Req 16 — Setup versus operations:** the local dashboard and sidebar label `Setup / Master Data` separately from `Daily Operations / Transactions`, explain the definitions-first workflow, and link the initial setup checklist independently from operational workspaces.
- **Req 38 — Customer registration clarity:** the customer form now names the missing prerequisite as the customer consent-purpose scope, explains why it is required, links an authorized settings administrator to the configuration screen, and gives a localized administrator/non-administrator fallback. The form also exposes the selected consent purpose and keeps child-profile configuration guidance separate.
- **Req 42 — Customer grouping:** company-scoped customer groups have bilingual names, optional parent selection, hierarchy-aware display/assignment, search/list management, active/inactive state, duplicate and cycle guards, and audit-aware action boundaries. Customer creation can assign an optional hierarchical group.
- **Reqs 46–48 — Supplier master structure:** supplier groups support company-scoped parent/child hierarchy, bilingual labels, active/inactive state, search/filter/count context, and guarded create/edit actions. Supplier detail supports structured owner/representative/order/accounting/general contacts with name, email, phone/WhatsApp, primary status, and structured communication destinations for purchase orders, accounting correspondence, and general communication across email, WhatsApp, and phone channels.

**Verification boundary:** this static-only checkpoint is superseded by the Batch B and final local evidence recorded below. Current Wave status is represented only by the checkboxes above; owner/release boundaries remain open independently.

## 2026-08-19 — Wave 2 local UX checkpoint (static-only)

This is a historical static-only implementation record. Later focused database/browser evidence supersedes its Wave-status limitation.

- **Reqs 17/52/56/59/60 — Product and import UX:** Product Masters now separates `Manual entry` from `Excel import`; the import surface provides a downloadable spreadsheet template, staged review/approval wording, permission-aware update mode, loading/duplicate-submit protection, pagination, and a useful empty state. Product creation and import explain the active-category prerequisite with an authorized `Configure categories` path; optional brand guidance remains distinct. Forms expose unsaved/clean and saving feedback.
- **Reqs 18/19/30/33/35/58 — Settings terminology, history, and help:** account creation is described as authentication-only and separated from business setup; local policy/baseline notes are framed as workspace context rather than approval; sequence labels explain prefix, suffix, reset rule, read-only current number, and the separate authorized override instead of exposing an ambiguous business-facing V1; printer/template wording distinguishes destination from layout; Settings Change Log is presented as read-only `Configuration Change History`; and bilingual localization/help copy was added for the affected terms and states.
- **MR64 static inspection:** the existing Product Add, POS operating context, Page Guide, and Appearance Customizer surfaces were inspected statically. No new direct defect was confirmed from source inspection. This does not replace the required runtime recheck and does not clear the previously recorded browser/story evidence boundary.

**Verification boundary:** PHP syntax, Blade view compilation, route discovery, and `git diff --check` were the only recorded checks for this checkpoint. No MariaDB/database or migration run, local server run, PHPUnit/Pest/other automated test, headed-browser check, physical-device check, UAT, commit, or push occurred. Runtime, authorization, persistence, and browser evidence remain required before any related Wave 2 or cross-cutting item can be closed.

## 2026-08-20 — Batch B local checkpoint

- CF-13 and CF-14 remain **PARTIAL**: the recorded disposable Local/Dev settings evidence exists, but the owner boundary remains open. Serial MariaDB 3307 verification recorded **5/5 tests with 25 assertions**; PHP/Blade/route/diff gates were recorded as passing for the changed local work.
- Authenticated headed-browser verification passed all six `/admin/settings` tabs in Arabic RTL at 390px with no horizontal overflow and no console errors. The settings 500/Blade parse/Livewire root/Alpine dirty-state chain is fixed for this local scope.
- Browser validation and modal checks passed for supplier, branch, store, and customer groups; the cash-drawer modal opened correctly. POS mobile at 375px had no overflow. Product-import evidence covered staged mapping, independent reviewer approval, and requester self-approval rejection. The §49 supplier order-recipient resolver passed **2 tests / 5 assertions**.
- Requirements 36/37 category-code behavior remains a partial implementation/evidence item in the expanded matrix; it is not promoted to a checked Wave item by this Batch B closure.

## 2026-08-20 — Local MariaDB recovery record

- The shared XAMPP MariaDB data directory was copied exactly to `C:\xampp\mysql\data-recovery-copy-20260820-004101` before recovery. Read-only forced-recovery checks on the disposable clone reported **121/121 tables** `CHECK TABLE QUICK OK` and **121/121** readable table-count queries.
- A clean XAMPP data directory was restored from the bundled backup, the corrupt active directory was preserved at `C:\xampp\mysql\data-corrupt-active-20260820-004101`, and MariaDB returned to port 3306. The recovered `toyjoy_local` dump restored successfully: 121 tables, one company, one branch, ten users, and four pending migrations subsequently completed.
- Recovery copies and work directories are retained. No Production database, owner business data, physical device, UAT, release, commit, or push claim is made.

## 2026-08-19 — Batch A closure; Batch B activation

- Batch A CF-09/10/11/12/15 is **DONE**. Changed phone save/invalid-retention/detail UX, sidebar active state/settings navigation/theme layout, payment-method setup clarity, and printer/template UX. Focused backend result **2/2, 12 assertions** (wrapper exit anomaly recorded); static gates passed. UI evidence: CF-09 customer phone save, invalid retained input, and detail observed; CF-10/11 active settings link/theme layout visually verified; CF-12 payment UI clear, with temporary test stopped only at a `role=switch` selector defect; CF-15 headed Chromium **PASS** with zero console/page/request failures. **Sol: PASS. Next:** CF-13/14 as the active high-risk settings batch.

## 2026-08-19 — CF-08 archive safety closure; Batch A activation (historical status, superseded)

- Historical record: CF-08 was recorded **DONE** with backend focused **1/1 test, 9 assertions** and core archive UI evidence. This status is superseded by the current **PARTIAL** evidence boundary above; the full scenario and independent approval decision were not completed.

## 2026-08-19 — CF-07 cash drawer association closure

- Changed canonical branch→POS cash-drawer association and verified create/edit/reload context with POS/shift headers; focused tests **2/2, 10 assertions**; Browser **PASS**, zero errors; **Sol PASS**; **Next:** CF-08.

## 2026-08-19 — CF-06 POS linkage closure

- Changed mapping authority and clear admin/POS UX; focused tests **3/3, 20 assertions**; Browser **PASS 1/1**, zero errors; **Sol PASS**; **Next:** CF-07.

## 2026-08-20 — Wave 3 local identity/taxonomy checkpoint (static-only)

This records the earlier local implementation checkpoint for Master Change Request Requirements **39–41, 12, 13, and 57**. Later focused evidence supersedes the customer requirement statuses; warehouse taxonomy remains an owner decision.

- **Req 39 — Customer name structure:** Customer create/edit/detail copy now labels Arabic and English **full names**, explains that the current model stores one bilingual full-name value per language, and avoids claiming unsupported first/last database fields. The identity guidance is explicit enough for consistent entry while preserving the existing bilingual model.
- **Req 40 — Duplicate detection:** Customer create/update paths normalize the primary and secondary phone values, check the normalized primary phone inside the transaction, and surface a safe matching-profile warning with a review link. Name similarity is not an automatic merge, and the UI does not silently overwrite an existing profile.
- **Req 41 — Privacy and consent UX:** Consent is presented as a configured purpose plus an explicit response (`Granted`, `Withdrawn`, or `Denied`), with explanatory copy. The profile history exposes purpose, state, capture time, actor/source, wording version, and scope; missing customer-purpose policy remains an actionable prerequisite rather than an opaque control.
- **Reqs 12/13/57 — Warehouse taxonomy and destructive actions:** The local Locations screen distinguishes location context from POS, labels the warehouse role as physical inventory, and labels Damaged/In Transit as inventory-routing locations. Archive requests show the exact location context, preserve history, require independent approval, and report dependency categories; reversible deactivation remains distinct from archive and hard deletion.
- **Owner decision checkpoint — Damaged/In Transit:** Existing DEC-069 terminology remains the local UI wording. The physical-versus-virtual/system-controlled meaning and whether manual use is permitted remain **Requires Owner Decision**; the local help text warns against assuming system control and does not invent a taxonomy.

**Verification boundary:** Targeted PHP lint passed for the changed customer/platform action and model files, and `git diff --check` passed. A Blade-cache command was attempted but produced no usable completion output in this checkpoint, so no Blade/runtime result is claimed. No MariaDB/database or migration run, PHPUnit/Pest/other automated test, headed-browser check, physical-device check, UAT, commit, or push occurred. Runtime, persistence, authorization, concurrency, and cross-branch evidence remain required before closure.

## 2026-08-20 — Wave 4 local scope/loyalty/wallet checkpoint (static-only)

This is the earlier static-only record for Master Change Request Requirements **53–55 and 44–45**. Later database/browser evidence supersedes its local status; inheritance provenance remains an owner decision.

- **Reqs 53–55 — readiness, multi-branch scope, and inheritance clarity:** Platform Settings now exposes a read-only Company/Branch/Device scope map from persisted records, with explicit classifications for timezone, branch/store/drawer relationships, enrolled devices, company-level settings, and company-wide versus branch-specific numbering. Setup readiness continues to derive from persisted business records and readiness rules rather than screen load. Branch Masters explains company timezone matching versus an explicit branch override. A matching value is deliberately not labelled “inherited” because the current schema has no source/provenance marker; no schema change was introduced.
- **Req 44 — Loyalty CTA:** The Loyalty & Points surface now presents loyalty-relevant actions (policy settings, reports, and customer-scoped loyalty ledger) instead of an unrelated generic `New customer` CTA. The customer profile links to the ledger, whose existing permission and approval boundaries remain authoritative.
- **Req 45 — Product Wallet configuration state:** Product Wallet now explains that it is a separate customer product-credit ledger, names the company-currency and wallet-policy prerequisites plus the approved retail source requirement, and provides an authorized `Configure wallet policy` path when unavailable. Product and Party Wallet remain separate; no mixed-language “not configured” placeholder is treated as readiness.

**Verification boundary:** This state-recording step ran no MariaDB/database or migration, local server, PHPUnit/Pest/other automated test, headed-browser, physical-device, UAT, commit, or push action. Runtime persistence, authorization, RTL/LTR, multi-branch, and wallet/loyalty evidence remain required. Full inheritance provenance remains blocked on an owner-approved source marker or nullable override model; this checkpoint does not infer one.

## 2026-08-20 — Browser verification attempt blocked by local MySQL

The requested full browser pass was initially blocked because the local `.env` required MySQL/MariaDB at `127.0.0.1:3306` while no listener was available. This historical attempt is superseded by the recovery and authenticated Batch B evidence above.
- Queue is **12 DONE / 3 PARTIAL / 0 ACTIVE** (CF-01–CF-15). Expanded groups are **8 with Local/Dev slices and requirement-level PARTIAL evidence / 7 open**. The matrix below separates local verification from owner approval, physical-device acceptance, UAT, Production, and release.

## 2026-08-20 — Final local evidence consolidation

- **Customer/master data:** structured Arabic first/last names with optional English, legacy snapshot compatibility, multiple child profiles, edit/deactivate, normalized phone and case-insensitive duplicate-email warning/no-auto-merge, category hierarchy/optional English, customer/supplier group scope, and supplier contact/destination IDOR checks passed focused MariaDB verification. Customer/child authenticated QA covered create, consent, duplicate warning, add, and edit; child deactivation is covered by focused feature tests.
- **Supplier and purchase orders:** supplier default payment terms auto-fill untouched drafts, an explicit override survives supplier reselection, action-level authorization is enforced, and PO draft persistence/print reload passed authenticated RTL QA. The designated purchase-order recipient resolver remains fail-closed and passed focused verification.
- **Settings:** payment/tax acceptance passed **14 tests / 63 assertions** across the feature and calculation filters; sequence acceptance passed **5 tests / 28 assertions** plus the narrower **3 tests / 16 assertions**; settings audit/authorization passed **6 tests / 38 assertions**. Printer profiles support **Global → Branch → Location** scope with safe same-scope defaults, cross-branch denial, preview, and runtime resolution; scoped printer verification passed **3 tests / 9 assertions**, with runtime precedence also included in the §66 PASS.
- **Migration/seeder safety:** 75 migrations completed on isolated MariaDB; the final printer-scope batch rolled back after correcting foreign-key/index order, then migrated forward again. `CanonicalAuthorizationSeeder` passed twice with stable 9-role/400-permission invariants and no company/owner business data.
- **Cross-cutting acceptance:** the multi-branch batch passed **20 tests / 104 assertions**. The §66 minimum scenario matrix passed **15 tests / 118 assertions**, covering company, six branches/timezone, warehouse/POS/drawer, payment/tax, numbering, printer scope, categories, import maker-checker, customer/children, and supplier recipient/terms.
- **Browser:** authenticated Arabic RTL and English LTR desktop/mobile batches passed the setup/master/settings/forms surfaces with no 500/error pages, no console warnings/errors, and no horizontal overflow at the tested 390px/CSS-375 viewport. Focused QA exercised customer/child and supplier/PO paths. The Initial Setup owner-decision matrix exposes permission-aware CTAs for every unresolved policy without persisting fictitious approval.
- **External boundary:** owner policy/value approval, genuine owner data, child/customer operating policy, warehouse taxonomy and inheritance provenance, physical printers/devices, human UAT, Production, release approval, commit, and push remain outside local completion.

## 2026-08-20 — Current in-app re-verification boundary

- Real in-app Chromium was exercised against disposable MariaDB `toyjoy_ui_reverify_20260820` on port `3307`.
- CF-08 current evidence is limited to the post-fix **2 tests / 22 assertions** and the dialog outcomes recorded in the queue: Category/Supplier explicit native-dialog dismiss PASS; Store archive Cancel/no approval PASS; Drawer explicit cancel was not observed because the bridge auto-dismissed; Branch control was not visible; Store Deactivate confirmation and an independent approval decision were not exercised. CF-12 remains locally evidenced.
- Runtime translation now renders correctly in the reached checks; this is not a complete bilingual strategy or owner-UAT result. Full browser story coverage and screenshots/traces remain open.
- This current boundary supersedes earlier CF-08/CF-13/CF-14 `DONE` or `PASS` wording in this historical ledger: CF-08, CF-13, and CF-14 are **PARTIAL**, and CF-12 remains **DONE (local evidence only)**.

## 2026-08-20 — P0 forged scope-path evidence

- **Master mutations:** RED showed that master delete/archive/`openEdit` accepted **6 foreign final IDs** and disclosed a foreign cash drawer. GREEN: `BranchStoreDrawerMutationScopeTest` passed **7 tests / 31 assertions** on disposable MariaDB `toyjoy_scope_delete_p0_20260820`.
- **Sequence mutations:** RED showed foreign create/override access; the focused GREEN passed **4 tests / 8 assertions** on disposable MariaDB `toyjoy_p0_sequence_scope_20260820`. The broader class result was **10/11** because of an unrelated existing printer-list assertion failure; it is not claimed green as a full-class result.
- **Master approval execution:** RED covered approval metadata, direct-delete bypass, sequence/target mismatch, rejection, inactive-target reactivation, and terminal idempotency. Final GREEN `PlatformMasterApprovalExecutionTest` passed **11 tests / 106 assertions** on disposable MariaDB at `127.0.0.1:3307`; approved internal execution derives the authoritative target/scope and direct actions remain gated/scoped.
- **Non-master approval execution:** RED covered forged/empty snapshots, stale targets, approval-only reviewers, scope moves, and terminal replay. Final GREEN `PlatformSettingsApprovalExecutionTest` passed **6 tests / 46 assertions**; generic terminal replay passed **1 test / 4 assertions** and focused store-mapping modal scope passed **1 test / 5 assertions**.
- **Scope-class boundary:** `PlatformSettingsScopeIdorTest` reached **10/11**; its only failure is an unrelated existing printer rendered-text assertion, so no full-class green is claimed. Sol's scoped code gate found no remaining P0/P1 issue. CF-08 destructive UI remains **2/22** with the recorded partial browser evidence, not a full UI closure.
- These are P0 local scope fixes only. §62 remains PARTIAL: broader multi-branch review, owner decisions, current UI limitations, UAT, Production, release, commit, and push remain open.

## Compact remediation matrix — Master §§0–72 (2026-08-20)

This is the Master-request matrix required by §72. Every result is limited to recorded local/disposable evidence; `PASS local slice` is not owner approval, UAT, Production, release, commit, or push. `PARTIAL` retains the stated boundary. “Mapped” refers to the live-surface index in `docs/59-requirement-route-model-policy-test-mapping.md`, not to newly asserted implementation.

| ID | Requirement | Classification | Root Cause | Changed Files | Database Change | Backend Change | UI Change | Automated Test | Result |
|---:|---|---|---|---|---|---|---|---|---|
| 0 | Initial setup before operations | Missing requirement | Setup and operations were not sufficiently separated | Setup/settings mapped surfaces | None separately recorded | `InitialSetupStatus` readiness boundary | Initial Setup journey | §66 setup scenario | PARTIAL — operations remain open |
| 1 | Discovery and remediation matrix | Process/documentation gap | No complete requirement traceability | This checklist; `docs/59-…mapping.md` | None | Route/model/policy mapping | Matrix only | None newly run | PARTIAL — full closure open |
| 2 | One canonical settings workspace | UX/navigation problem | Duplicate-looking settings entry points | `routes/settings.php` | None separately recorded | Settings authorization | Six-tab settings shell | Current six-tab structural pass | PASS local slice |
| 3 | Company identity persistence | Bug/data integrity | Save/hydration/audit path defects | `routes/settings.php`; mapped settings components | Existing company/settings persistence | Atomic persistence and audit boundary | Save, reload, dirty feedback | §66 company scenario | PASS local slice |
| 4 | Branch single source of truth | Data integrity problem | Stale/different branch representation | `routes/branches.php`; `routes/stores.php` | Existing branch record | Scoped branch source | Reloaded branch labels | Multi-branch evidence | PASS local slice |
| 5 | Create six branches | Bug/data integrity | Branch provisioning/constraint failure risk | `routes/branches.php` | Disposable MariaDB branch records | Branch provisioning/validation | Branch creation flow | Multi-branch 20/104; §66 | PASS local slice |
| 6 | Warehouse versus store terminology | Naming/localization problem | Technical/store wording confused inventory role | `routes/stores.php`; `routes/locations.php` | No DB rename recorded | Existing location boundary | Warehouse/location copy | Browser rechecks | PASS local slice |
| 7 | Branch→warehouse→POS→drawer context | UX/problem definition | Related-entity context was unclear | Branch/store/location/drawer routes | Existing relationships | Scoped relationship boundary | Human-readable selectors | Multi-branch; §66 | PASS local slice |
| 8 | Drawer operating context | Missing requirement | Drawer could be ambiguous | `routes/cash-drawers.php` | Existing drawer relation | Branch/POS validation | Drawer modal/context | Multi-branch; browser | PASS local slice |
| 9 | Branch dropdown population | Bug/authorization | Scope/active-state selection risk | Branch/store/location routes | None separately recorded | `visibleTo`/scope authorization | Active branch selectors | Multi-branch 20/104 | PASS local slice |
| 10 | Correct warehouse counts | Data integrity problem | Non-authoritative scoped counts | `routes/branches.php`; `routes/locations.php` | Existing location relation | Scoped count relationship | Branch count display | CF-05 evidence | PASS local slice |
| 11 | Explain POS linkage | UX problem | Icon-only/link state unclear | `routes/stores.php`; `routes/cash-drawers.php` | Existing mapping/history | Effective-date and prerequisite boundary | Textual mapping/context | Multi-branch; §66 | PASS local slice |
| 12 | Warehouse-type taxonomy | Missing prerequisite/configuration | Physical versus virtual/manual meaning unresolved | `routes/locations.php` | No taxonomy migration recorded | DEC-069 boundary | Location help/copy | No independent acceptance | Owner decision |
| 13 | Safe warehouse deletion/archive | Data integrity/UX problem | Destructive path must preserve history | `routes/locations.php` | Existing archive/history state | Archive request/dependency boundary | Archive request and pending inbox | Focused 1/9; current core pass | PARTIAL — independent decision not exercised |
| 14 | Timezone inheritance | Architecture/configuration | Company default/override policy boundary | `routes/branches.php`; settings route | Existing company/branch timezone fields | Default/override boundary | Timezone selector/copy | Multi-branch and §66 local evidence | PARTIAL — owner boundary |
| 15 | Actionable setup dashboard | UX problem | Statuses lacked actionable prerequisites | Setup/settings mapped surfaces | Persisted readiness records | `InitialSetupStatus` | Configure/Review/status cards | Setup/browser evidence | PASS local slice |
| 16 | Separate setup from operations | UX/navigation problem | Definitions-first journey obscured | Setup/sidebar mapped surfaces | None | Existing route authorization | Setup/Master Data navigation | Browser navigation batches | PASS local slice |
| 17 | Manual entry plus staged Excel import | Missing requirement | Import required safe staged review | `routes/catalog.php` | Import batch/review records | Maker-checker boundary | Template/staging/review | §66 import scenario | PASS local slice |
| 18 | Account setup terminology | Naming/UX problem | Authentication mixed with business setup | Settings/account mapped surfaces | None | Existing auth boundary | Contextual account wording | Bilingual browser batches | PASS local slice |
| 19 | Policy/baseline terminology | Naming/UX problem | Internal wording exposed | `routes/settings.php` | None | Existing settings boundary | Contextual help/copy | Bilingual browser batches | PASS local slice |
| 20 | Payment method model clarity | UX/business rule | Method/channel meanings unclear | `routes/settings.php` | Existing payment settings | Validation/audit boundary | Payment method/channel UI | Payment/tax 14/63 | PASS local slice; owner values open |
| 21 | Supplier payment terms | Missing requirement | Defaults and authorized PO override needed | `routes/catalog.php`; `routes/purchasing.php` | Existing supplier/PO fields | Default/override authorization | Supplier/PO terms UI | Supplier/PO QA | PASS local slice; owner values open |
| 22 | Payment evidence wording/enforcement | UX/business rule | Meaning unclear without server rule | `routes/settings.php` | None separately recorded | Payment enforcement boundary | Evidence help text | Payment/settings evidence | PASS local slice; owner policy open |
| 23 | Offline POS wording | Naming/business rule | Internal eligibility language unclear | Settings/POS mapped surfaces | None | Allowlist/enforcement boundary | Business-facing offline copy | Focused payment/POS checks | PASS local slice; owner limits open |
| 24 | Tax defaults and override | Business/data integrity | Precedence and override controls required | `routes/settings.php` | Existing tax settings | `PosCalculationService`; audit/auth | Tax calculation settings | Payment/tax 14/63 | PARTIAL — focused calculation/settings evidence; final tax values, legal treatment, override acceptance, and owner approval remain open |
| 25 | Distinguish zero tax | Business/accounting rule | Different tax treatments could collapse | `routes/settings.php` | Existing tax treatment data | Calculation boundary | Zero/exempt/out-of-scope copy | Tax/calculation tests | PARTIAL — local distinction coverage exists; final classification/legal treatment and owner approval remain open |
| 26 | Daily sequence reset | Missing requirement | Daily boundary absent | `routes/settings.php` | Existing sequence records | Sequence reset logic | Reset rule UI | Sequence 5/28 | PASS local slice |
| 27 | Sequence scope | Data integrity requirement | Scope could create duplicates | `routes/settings.php` | Existing counters/scopes | Uniqueness and scope boundary | Scope selection | Sequence focused checks | PASS local slice |
| 28 | Prefix/suffix preview | UX problem | Numbering terms were unexplained | `routes/settings.php` | None | Existing sequence preview | Prefix/suffix/padding preview | Settings UI evidence | PASS local slice |
| 29 | Safe sequence override | Data integrity/authorization | Dangerous override lacked clear separation | `routes/settings.php` | Existing audit/counter data | Permission, reason, lock boundary | Separate override action | Sequence 3/16; 5/28 | PASS local slice; owner values open |
| 30 | Hide/clarify version semantics | Naming/UX problem | Internal V1 wording confused users | `routes/settings.php` | None | Existing settings boundary | Business-facing labels | Settings browser evidence | PASS local slice |
| 31 | Separate printers/templates | UX/problem definition | Destination and layout conflated | `routes/settings.php` | Existing printer configuration | Scoped assignment boundary | Printer/template UI | Scoped printer 3/9 | PASS local slice |
| 32 | Runtime print selection | Architecture/authorization | Scope precedence and branch safety required | `routes/settings.php` | Existing printer configuration | Location→Branch→Global resolver | Runtime configuration UI | Scoped printer; §66 | PASS local slice; hardware/UAT open |
| 33 | Read-only change history | UX problem | History presented as a form | `routes/settings.php` | Existing audit data | Audit read boundary | Configuration Change History | Settings audit 6/38 | PASS local slice |
| 34 | Sidebar navigation | UI bug | Active state/navigation was incorrect | Sidebar mapped surface | None | Existing navigation boundary | Active-state correction | Client browser evidence | PASS local slice |
| 35 | Bilingual UI | Localization/UI problem | Arabic/English quality inconsistencies | Affected mapped Blade/Livewire surfaces | None | Locale/direction boundary | RTL/LTR copy/layout | Current Chromium recheck | PARTIAL — Arabic mixed copy failed |
| 36 | Optional category English | Validation/localization | English was required unnecessarily | `routes/catalog.php` | Existing category fields | Category validation | Optional-English form | Category tests/browser | PASS local slice |
| 37 | Category hierarchy display | UI/data integrity problem | Hierarchy/order/guards needed | `routes/catalog.php` | Existing category hierarchy | Parent/cycle guards | Nested category UI | Category tests/browser | PASS local slice |
| 38 | Customer registration prerequisite | Missing prerequisite UX | Consent-purpose prerequisite was opaque | `routes/customers.php` | Existing consent configuration | Permission-aware prerequisite | Configure guidance | Customer QA | PASS local slice |
| 39 | Customer name structure | Data/naming problem | Bilingual identity guidance needed | `routes/customers.php` | Existing bilingual full-name model | Legacy snapshot compatibility | Arabic/English name fields | Customer QA/tests | PASS local slice |
| 40 | Duplicate detection | Data integrity problem | Phone/email duplicates needed safe warning | `routes/customers.php` | Existing customer records | Normalize/no-auto-merge boundary | Matching-profile warning | Customer QA/tests | PASS local slice |
| 41 | Privacy and consent UX | Missing requirement | Consent state/history lacked clear journey | `routes/customers.php` | Existing consent/history records | Registry-key submission boundary | Purpose/state/capture UI | Customer QA | PASS local slice; owner purposes open |
| 42 | Customer group hierarchy | Missing requirement | Groups needed company scope/guards | `routes/customers.php` | Existing group hierarchy | Scope/cycle validation | Group management UI | Focused/browser evidence | PASS local slice |
| 43 | Multiple child profiles | Missing requirement/authorization | Child identity and object scope needed | `routes/customers.php` | Existing child records | IDOR/audit/deactivate boundary | Add/edit/deactivate UI | Focused tests; QA | PASS local slice; owner policy open |
| 44 | Loyalty CTA | UX problem | Generic CTA did not match loyalty task | `routes/customers.php` | No change separately recorded | Existing ledger/policy boundary | Policy/report/ledger CTAs | Browser regression | PASS local slice; owner policy open |
| 45 | Product Wallet configuration UX | UX/problem definition | Separate ledger prerequisites were unclear | `routes/customers.php` | No change separately recorded | Separate wallet boundary | Prerequisite/configure CTA | Browser checks | PASS local slice; owner policy open |
| 46 | Supplier groups | Missing requirement/authorization | Group hierarchy and company scope needed | `routes/catalog.php` | Existing supplier-group records | Scope/validation boundary | Supplier group UI | Focused/browser evidence | PASS local slice |
| 47 | Supplier contacts | Missing requirement | Contact roles and scope incomplete | `routes/catalog.php` | Existing supplier contacts | Cross-supplier denial | Structured contact UI | Focused/browser evidence | PASS local slice; owner data open |
| 48 | Supplier communication preferences | Missing requirement | Purpose/channel destinations needed clarity | `routes/catalog.php` | Existing destinations | Cross-supplier scope boundary | Destination/reload UI | Local verification | PASS local slice; messaging out of scope |
| 49 | Supplier order recipient | Data integrity/authorization | Recipient needed fail-closed resolution | `routes/purchasing.php` | Existing contact/destination data | Designated-recipient resolver | PO recipient UI | 2 tests / 5 assertions | PASS local slice |
| 50 | Egyptian phone error | Validation/localization | Input normalization/error UX needed | `routes/customers.php` | Existing customer phones | Phone normalizer | Retained localized error UI | CF-09 evidence | PASS local slice |
| 51 | Actionable validation | UX/validation problem | Errors/prerequisites were not actionable | Master-data mapped routes | None | Server validation boundary | Inline errors/retained input | Arabic/English checks | PASS local slice |
| 52 | Dependency UX | Missing prerequisite UX | Empty prerequisites needed a route forward | Setup/master-data mapped routes | None | Permission-aware CTA boundary | Explanation/Configure paths | Browser batches | PASS local slice |
| 53 | Prevent fake ready states | UX/data integrity problem | Readiness could derive from page availability | Setup/settings mapped surfaces | Persisted business records | Readiness rules | Real status cards | Setup/browser evidence | PARTIAL — persisted readiness/CTA slice evidenced; complete real-source criteria and owner/UAT acceptance remain open |
| 54 | Multi-branch readiness | Architecture/authorization | Scope needed consistent enforcement | Branch/store/location/settings routes | Existing scoped records | Company/branch/location scope queries | Scope map/context | Multi-branch 20/104 | PASS local slice |
| 55 | Configuration inheritance pattern | Architecture/owner decision | No provenance/source marker | Branch/settings mapped surfaces | No provenance migration recorded | Source-marker boundary | Matching-value explanation | No independent acceptance | Owner decision |
| 56 | Empty states | UX problem | No-record/prerequisite states needed guidance | Master-data mapped routes | None | Existing permission boundary | Empty/CTA states | Bilingual browser batches | PASS local slice |
| 57 | Destructive-action UX | UX/data integrity problem | Confirmation/dependency/history needed | `routes/locations.php` | Existing archive/history state | Archive/deactivate boundary | Confirmation/dependency wording | Post-fix RED malformed `onclick`, then GREEN 2/22; targeted dialogs | PARTIAL — Category/Supplier explicit native-dialog dismiss PASS and Store archive Cancel/no approval PASS; Drawer bridge auto-dismissed before explicit cancel, Branch control was off visible surface, and Store Deactivate confirmation plus independent approval remain open |
| 58 | Help for business terms | UX/naming problem | Terms lacked contextual explanation | Settings/setup/master mapped surfaces | None | None separately recorded | Contextual help | Bilingual review; Arabic issue noted | PARTIAL — mixed Arabic copy failed |
| 59 | UI consistency | UI problem | Inconsistent list/form/action patterns | Master-data mapped routes | None | Existing route boundaries | Consistent list/form/detail actions | Browser batches | PASS local slice |
| 60 | Dirty-state save feedback | UX/data integrity problem | Save state/duplicate submit feedback needed | Settings and mapped forms | None | Dirty/duplicate-submit boundary | Loading/disabled/unsaved UI | Focused local checks | PARTIAL — affected local settings/form slice evidenced; complete mapped-form and full-story unsaved/duplicate-submit coverage remain open |
| 61 | Audit requirements | Authorization/audit requirement | Changes needed traceability | Settings/customer/supplier/purchasing routes | Existing audit records | Actor/scope/reason boundary | Read-only audit presentation | Settings audit 6/38 | PASS local slice |
| 62 | Authorization | Authorization requirement | Object/company scope needed enforcement | Mapped settings/customer/supplier/PO routes | None | Policies/gates/scope filters | Permission-aware controls | Master approval 11/106; non-master approval 6/46; terminal replay 1/4; mapping-modal scope 1/5; `PlatformSettingsScopeIdorTest` 10/11 (unrelated printer rendered-text failure) | PARTIAL — forged metadata/direct-delete/sequence/reject/inactive/idempotency and non-master snapshot/stale/reviewer/scope-move/replay paths are fixed locally; broader multi-branch review, current UI limits, owner decisions, UAT, Production, and release remain open |
| 63 | Concurrent update safety | Data integrity requirement | Races/stale writes could corrupt records | Settings/scoped mapped routes | Existing counters/relations | Transactions, locks, uniqueness | Safe error feedback | Focused MariaDB verification | PASS local slice |
| 64 | Recheck existing UI regressions | Regression requirement | Known Product/POS/Guide/Appearance defects | Product, POS, Page Guide, Appearance surfaces | None | Existing route/domain actions | In-app surface rechecks | Four surface rechecks | PARTIAL — Product, POS, Page Guide, and Appearance were rechecked; full user-story coverage and screenshots/traces remain open |
| 65 | Test strategy | Verification requirement | Required local database/UI coverage | This checklist; mapped test surfaces | Disposable MariaDB only | Existing focused test infrastructure | In-app Chromium recheck | Recorded focused tests; limited current browser evidence | PARTIAL — runtime bilingual rendering is currently correct in reached checks; complete test strategy, full browser run, and screenshots/traces remain open |
| 66 | Minimum E2E scenarios | Verification requirement | Critical setup journeys needed evidence | Mapped company/branch/etc. surfaces | Disposable MariaDB scenario data | Existing domain actions | Recorded scenario flows | 15 tests / 118 assertions | PASS local automated slice |
| 67 | UX review | UX requirement | Owner clarity required across affected screens | Setup/master/settings mapped surfaces | None | Existing CTA boundaries | Grouping/help/empty states | Limited current browser evidence | PARTIAL — runtime bilingual rendering is currently correct in reached checks; full bilingual strategy, owner UAT, and screenshots/traces remain open |
| 68 | Do not invent business behavior | Business-rule governance | Unknown semantics require explicit decisions | Setup/settings mapped surfaces | No fictitious data recorded | Owner-decision boundary | Decision cards/CTAs | Owner-decision smoke | PASS local slice; owner decisions open |
| 69 | Migration safety | Data integrity requirement | Safe forward/rollback ordering required | Migration execution paths | 75 recorded migrations | Migration ordering boundary | None | Recorded forward/rollback/forward | PASS local slice |
| 70 | Seeders/demo data | Fixture requirement | Complete deterministic setup coverage incomplete | Seeder execution paths | Deterministic disposable fixtures | `CanonicalAuthorizationSeeder` boundary | None | Two stable seeder runs | PARTIAL — fixture coverage open |
| 71 | Definition of done | Closure requirement | Coherent journey/risk evidence incomplete | This checklist; `docs/59-…mapping.md` | None | Evidence ledger boundary | Documented local slices | Recorded evidence only | PARTIAL — full journey/UAT open |
| 72 | Final deliverable | Documentation/closure requirement | Final owner/UAT/release verdict unavailable | This checklist; `docs/59-…mapping.md` | None | No new implementation claim | Matrix and evidence summary | Documentation structural check and scoped `git diff --check` passed for this update | PARTIAL — requirement matrix is current, but complete evidence, owner verdict, UAT, Production, and release closure remain open |
| Translation overrides | Local/Dev cross-cutting localization | Localization/control boundary | Owner needs safe wording correction without source-file edits | `/admin/translations`; mapped translation components | Existing `translation_overrides` table | Catalog + Laravel FileLoader + audited action | UI-ADM-014 bilingual inline editor | Focused MariaDB **5 tests / 31 assertions**; browser not fully run | PARTIAL — Local/Dev feature evidence only; full browser, UAT, Production, and release closure remain open |

## 2026-08-20 — Excel-import/UI QA evidence update

- Disposable MariaDB focused GREEN evidence: supplier import **4 tests / 14 assertions**; catalog-reference import RED **3/3** (two intended exact-header errors) then GREEN **3/9**; customer import **8/10**; product editor/report **2/11**; Initial Setup **14/9,985**. The customer import application-shell/RTL correction separately passed **9/15** after its expected RED.
- Migration `000093` was applied successfully to `toyjoy_ui_qa_20260820` on MariaDB `127.0.0.1:3307` after removing only the two empty partial import tables left by the prior failed migration; the repaired short identifiers satisfy MariaDB limits.
- Authenticated Administrator browser render checks passed for supplier/reference/customer/product imports, product creation, reports, and inventory reports: no 500 response, console warning/error, or desktop overflow. At CSS 375, supplier/reference/product import and product creation had no horizontal overflow.
- The browser’s file-chooser automation did not emit a chooser. Therefore this checkpoint does **not** claim UI file selection, staging, approval, imported-row persistence, or template-download events. It does not change the matrix status or claim owner/UAT/Production/physical-device/release/commit/push completion.

## 2026-08-20 — Import-review notification evidence update

- Native database notifications now alert active users who hold the relevant import-approval permission when a product, supplier, customer, or catalog-reference batch becomes `ready_for_review`. The requester is excluded; each alert includes the original filename and a direct, authorization-scoped review URL. The Notifications page renders only the signed-in user's delivered alerts.
- Focused MariaDB RED was **2 tests / 2 failures**: the Administrator had zero alerts and the notification page showed its empty state. GREEN passed **2 tests / 18 assertions** on `toyjoy_client_feedback_20260819` at `127.0.0.1:3307`, covering all four batch transitions, Administrator delivery, requester/unauthorized exclusion, rendered alert link, and authorized versus unauthorized product-batch access.
- No browser file-chooser, full browser upload, UAT, Production, commit, or push claim is made by this focused backend/UI-render evidence.
