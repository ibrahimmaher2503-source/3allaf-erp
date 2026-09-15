Master Change Request — Client Feedback Remediation & Setup UX Overhaul

You are working on an existing Laravel modular ERP/POS application.

The following requirements are based on direct client UAT feedback.

Treat them as a combination of:

confirmed defects,
missing business requirements,
data consistency issues,
UX/UI defects,
confusing terminology,
missing validations,
missing master-data flows,
configuration inheritance problems,
navigation problems,
incomplete workflow design.

Do not implement superficial visual patches.

Your job is to inspect the existing PRD, user stories, database schema, models, migrations, seeders, policies, routes, Livewire/Flux/Blade components, controllers/actions, services, tests, localization files, navigation configuration, audit logic, existing master-data relationships, and current implementation before modifying anything.

Preserve existing valid business logic unless these requirements explicitly supersede it.

0. Primary Goal

The client is currently in the system-definition / initial-setup stage.

They explicitly do not want to start operational transactions yet.

The immediate goal is therefore:

Make the entire initial configuration and master-data setup flow understandable, consistent, reliable, fully persistent, correctly linked, and ready for multi-branch operation before purchasing, sales, inventory movements, parties, or other operational transactions begin.

The customer currently operates approximately 4 branches and expects to reach 6 branches shortly.

Therefore all setup architecture must support multi-branch operation correctly.

Reliability is critical.

No silent failures, stale data, duplicated configuration screens, confusing relationships, broken navigation, or ambiguous master data should remain.

1. Mandatory Discovery Before Coding

Before making changes:

Read the PRD and all relevant user stories.
Map every requirement below to:
existing module,
route,
UI screen,
database table,
model,
service/action,
policy,
test.
Identify whether each issue is:
Bug,
Missing Requirement,
UX Problem,
UI Problem,
Data Integrity Problem,
Naming/Localization Problem,
Architecture Problem,
Missing prerequisite/configuration.
Determine the root cause.
Reuse existing components and domain concepts where correct.
Do not create duplicate tables/modules when an equivalent entity already exists.
Add migrations only when the existing schema cannot represent the required business behavior safely.
Preserve auditability and historical records.

Create a remediation matrix before implementation:

ID	Client Issue	Classification	Root Cause	Existing Area	Proposed Fix	Backend	UI	DB	Test

Then implement.

2. Company Settings — Remove Duplicate Configuration Areas
Problem

The client currently sees apparently identical configuration screens under multiple navigation locations.

For example:

Company Settings
Payments & Taxes
Numbering & Printing

They expose the same tabs:

Company Identity
Payment Methods
Tax Settings
Document Sequences
Printing & Templates
Settings Change Log

This creates duplicate navigation with no clear distinction.

Required Change

Audit all routes and navigation entries that point to these screens.

There must be one canonical settings workspace.

Recommended structure:

System Setup
Company Identity
Branches
Warehouses
Cash Drawers
Payment Methods
Taxes
Document Sequences
Printers
Print Templates
Audit / Configuration History

Alternative navigation entries may deep-link directly to a specific tab, but they must not appear to be separate duplicated modules.

Example:

/settings/company?section=payments

and

/settings/company?section=taxes

may reuse the same settings shell.

UX Requirement

The user must always understand:

where they are,
which configuration section they are editing,
why the section exists.

Highlight the active subsection correctly.

Acceptance

No two navigation items should appear to open an indistinguishable duplicate settings page without context.

3. Company Identity — Fix Persistence Completely
Confirmed Client Problem

The client entered:

Company Name
Arabic Company Name
Legal Name
Commercial Registration
Tax Number
Address

and Save appeared to succeed, but the data was not retained.

Required Investigation

Trace the complete write path:

UI → validation → DTO/form → action/service → model → DB → hydration/reload.

Check for:

incorrect field names,
missing $fillable,
casts,
wrong settings keys,
transaction rollback,
tenant/company scope mismatch,
cache returning stale values,
component state not refreshing,
save happening to a different company record,
validation silently failing,
JSON settings overwriting unrelated keys.
Required Fix

All company identity fields must:

validate clearly,
persist atomically,
reload immediately after Save,
survive browser refresh,
survive logout/login,
remain associated with the correct company,
create an auditable configuration-change record.

Never display a successful Save toast unless persistence actually succeeded.

UX

After save:

show a clear localized success state,
disable Save while request is in-flight,
retain validation errors beside fields,
warn on unsaved changes when navigating away.
4. Branch Master Data — Single Source of Truth
Problem

The client edited:

Branch Code
Arabic branch/store name

but another screen showed stale/different values.

Requirement

There must be a clearly defined canonical source of branch data.

Do not duplicate branch identity values into unrelated tables unless deliberately denormalized.

Any UI representing a branch must use the same authoritative branch record.

Acceptance

Editing a branch name/code must propagate everywhere the same branch is represented after save/reload.

5. Branch Creation — Fix Third Branch Failure
Problem

Creating the third branch fails/stops unexpectedly.

Required Work

Reproduce creation of:

Branch 1
Branch 2
Branch 3
at least Branch 6

in a disposable test database.

Check:

unique constraints,
generated codes,
sequence collisions,
hidden limits,
branch/store automatic provisioning,
default relationships,
validation,
tenant/company scoping.
Acceptance

At least six branches can be created legitimately without manual DB modification.

6. Replace User-Facing “Store” Terminology With “Warehouse / مخزن”

The client strongly prefers:

مخزن

instead of:

متجر

for inventory storage locations.

Required Rule

Do not blindly rename the internal domain model if Store is deeply used technically.

User-facing terminology should become:

Arabic:

مخزن

English:

Warehouse

unless a particular entity is genuinely a retail Store.

Perform a domain audit first.

Do not accidentally merge:

branch,
warehouse,
POS location,
physical retail store,
cash drawer.

These remain distinct domain concepts.

7. Clarify Branch → Warehouse → POS → Cash Drawer Relationships

The current UI makes these relationships unclear.

The user must be able to understand and configure the hierarchy.

Target mental model:

Company
→ Branch
→ Warehouse(s)
→ POS Location / Terminal Context
→ Cash Drawer(s)

A warehouse may serve inventory.

A POS may consume inventory from an assigned warehouse.

A cash drawer belongs to a valid branch/POS context.

UI Requirement

Whenever selecting related entities, show human-readable context.

Example:

Palm Hills — Main Warehouse

instead of ambiguous duplicate names.

Never show orphaned/unexplained options such as:

Dokki Branch
Dokki Branch 1

without enough information to distinguish them.

8. Cash Drawer — Branch/Context Must Not Be Ambiguous
Current Problem

The linked Store/Warehouse/Branch appears optional or has no valid branch options.

The client expects a cash drawer to belong to a meaningful operating context.

Requirement

Define the domain rule explicitly.

At minimum each operational drawer must belong to:

company,
branch,

and where relevant:

POS terminal/location.

If a drawer truly may exist without a warehouse, do not misleadingly ask for a warehouse.

Use the correct domain label.

Validation

A drawer should not become operational if its required branch/POS relationship is missing.

9. Fix Branch Dropdown Population

Anywhere a branch selector exists:

all active branches within the current company/tenant must appear,
unauthorized branches must not appear,
deleted/inactive branches should be handled correctly,
current selection must reload correctly,
labels must be unambiguous.

Add automated tests.

10. Warehouse Counts Must Be Correct
Problem

Client sees contradictory counts such as:

2 Warehouses
then 0 Warehouses

for the same context.

Requirement

Find every count displayed on branch cards, tables, summaries, dashboards.

Use one authoritative scoped relationship.

Check:

tenant filtering,
active/deleted filtering,
relationship names,
cached counts,
warehouse types,
POS-specific counts.
Acceptance

For every branch:

displayed count = actual valid warehouse records belonging to that branch under the documented counting rule.

11. Explain and Fix “Link POS” Workflow

The current link icon/arrows/clock have no understandable behavior.

Requirement

Do not rely on unexplained icons.

The UI must explicitly show:

what entity is being linked,
to what entity,
why the link exists,
whether the link is active,
effective date if applicable,
current assignment,
previous assignment/history if tracked.

Examples:

Assign POS location to warehouse

or

Link POS terminal to branch

depending on the actual domain rule.

Use tooltips plus text labels where the action is not obvious.

Empty dropdowns must explain why they are empty and how to create prerequisites.

12. Warehouse Type Taxonomy Must Be Reviewed

The client is confused by types such as:

POS
Main Warehouse
Service Center
Damaged/Defective Stock
In Transit Stock
Required Domain Review

Determine which of these are:

A. physical warehouse roles,

versus:

B. inventory statuses / virtual stock locations.

Avoid mixing both concepts in one unclear dropdown.

Potential model:

Physical location types
Sales Warehouse
Main Warehouse
Service Warehouse
System/virtual inventory locations
Damaged
In Transit
Quarantine

If Damaged or In Transit are system-controlled virtual locations, communicate that clearly and prevent inappropriate manual use.

Do not implement this suggested taxonomy blindly; reconcile it with existing PRD/domain logic first.

13. Delete Warehouse — Define Safe Deletion Behavior

Client cannot delete warehouses.

This must not simply be “made deletable”.

Required Behavior

Determine whether the warehouse has:

inventory,
stock movements,
transactions,
POS links,
open counts,
transfers,
drawers,
historical references.

If unused:

allow deletion.

If referenced historically:

prevent destructive deletion and offer:

Deactivate / Archive

with a clear reason.

Never fail silently.

14. Timezone Inheritance

The company timezone is set to:

Africa/Cairo

Yet branch screens default to UTC.

Required Architecture

Establish cascading defaults:

Company timezone
→ branch default timezone
→ downstream operational context

When creating a branch:

default timezone = company timezone.

Allow branch override only if the domain requires it.

UI

Use a searchable timezone dropdown.

At minimum support:

Africa/Cairo
Asia/Riyadh
UTC

Prefer the standard IANA timezone database.

Do not require manual text entry or copy/paste.

Acceptance

A company configured as Africa/Cairo must produce new branches defaulting to Africa/Cairo.

15. Setup Dashboard Must Become Actionable

The setup dashboard currently contains items such as:

Operating Areas
Daily Operations
Products

with statuses like:

Available
Configured

but they are not actionable and their purpose is unclear.

Required UX Redesign

Convert the initial setup dashboard into a real onboarding/setup checklist.

Example:

Initial Setup Progress
Company Identity
Branches
Warehouses
Cash Drawers
Payment Methods
Taxes
Document Sequences
Printers
Print Templates
Customer Groups
Supplier Groups
Categories
Product Masters
Opening Configuration

Each item should show:

Not Started
Incomplete
Ready
Blocked
Completed

and provide a clear CTA:

Configure

or:

Review

Where a prerequisite is missing, explain it.

Do not show fake/non-actionable “Configured” badges.

16. Separate Setup From Operations

Client explicitly wants:

Definitions first, transactions later.

Therefore navigation and onboarding should distinguish:

Setup / Master Data

from

Daily Operations / Transactions

The user should be able to finish master data without being pushed into:

Sales
Purchase Orders
Inventory Movements
Parties
Settlements
Returns
17. Manual Entry + Excel Import Strategy

For master data where bulk creation makes sense, support:

Manual Entry
Excel Import

Candidate masters include, where appropriate:

Products
Categories
Customers
Suppliers
branches/warehouses if safely supported
Template Requirement

Every import workflow must provide:

Download Excel Template

The template must contain:

approved columns,
required vs optional indicators,
examples where useful,
stable machine-readable headers,
documented validation expectations.

Import must support:

Upload → Validate → Staging Preview → Errors → Confirm/Approve → Import Result.

Never directly mutate production master data before validation.

18. “Complete Account Setup” / Username and Password Screen

The current onboarding leads the client to a username/password screen whose purpose is unclear.

Audit whether this represents:

administrator account setup,
staff account setup,
company-owner setup,
authentication settings.

Rename and reposition it according to its actual purpose.

Provide explanatory text.

Do not mix account security setup with business configuration without context.

19. “Policy Notes” and “Company Baseline” Terminology

The client does not understand:

Policy Notes
Baseline
Company Baseline
similar technical/internal wording.
Requirement

Audit whether these are genuinely business-facing concepts.

If internal implementation metadata:

hide them from ordinary business users.

If required:

rename them into clear business language and add inline help.

Avoid exposing engineering/governance vocabulary that the shop owner does not need.

20. Payment Method Model — Redesign for Business Clarity

Current types include:

Cash
Card
POS
Bank Transfer
Manual
Other

The client cannot understand the distinction between Card and POS.

They also need:

InstaPay
Vodafone Cash
Electronic Wallets
Bank transfer
Cash
Card
Requirement

Separate:

Payment Method

from

Processing Channel / Instrument Type

Possible example:

Payment Method:

Cash
Visa CIB POS
InstaPay
Vodafone Cash
Bank Transfer
Cheque

Underlying Type:

Cash
Card
Bank Transfer
Digital Wallet
Cheque
Other

Gateway/Terminal:
optional relation if applicable.

Do not expose technical categories unless they affect accounting or workflow.

21. Supplier Payment Terms

Supplier records should support preferred/default payment arrangements.

Possible business cases:

Cash
Trust/Deposit arrangement
Installments
Cheques
per-order payment method

Implement according to the domain model.

Prefer:

default supplier payment terms/method,
but allow purchase orders to override when authorized.
22. “Requires Proof” Payment Option

There is a setting approximately named:

Requires proof

The client does not understand it.

Requirement

Clarify wording according to actual behavior.

For example:

Requires Payment Evidence

Help text:

When enabled, users must attach or reference payment evidence before the payment can be approved.

Only keep this feature if the backend actually enforces it.

If enabled, enforce it server-side, not only visually.

23. “Offline POS Eligible/Restricted” Wording

Current wording is unintelligible.

Replace internal technical language with a business-facing label.

Possible concept:

Available for approved offline POS transactions

Add help text explaining:

when the method can be used offline,
whether it requires prior authorization,
offline limits,
synchronization behavior.

Only expose this setting where offline POS is actually supported.

If offline selling is unavailable, do not expose misleading active configuration.

24. Tax Configuration — Define Defaults and Invoice Override

The client expects:

default tax configuration,
usually prices tax-inclusive,
ability to handle zero-rated scenarios,
specific transactions that may require different treatment.
Required Model

Review whether tax is controlled at:

company,
branch,
product,
service,
customer,
invoice,
invoice line.

Document precedence clearly.

Recommended precedence must be derived from the PRD.

Example concept:

Company default
→ product/category tax default
→ invoice derived default
→ authorized invoice override

Critical Accounting Rule

Do not allow arbitrary users to “remove VAT” merely to change the agreed commercial price.

Tax-exclusive and tax-inclusive pricing must be mathematically and legally distinct.

Any override must be:

permission-controlled,
auditable,
valid under configured tax rules.
UI

At invoice level, clearly show:

Tax Inclusive / Exclusive calculation mode
Tax Code
Tax Rate
Net
Tax
Gross

where applicable.

25. Zero Tax Must Be Clear

A zero-rate tax should not ambiguously mean “tax disabled”.

Differentiate where the accounting model requires it:

Zero Rated
Exempt
Out of Scope

Do not collapse legally/accountingly different states unless the PRD intentionally does so.

26. Document Sequences — Add Daily Reset

Current reset rules:

Continuous
Annual
Monthly

Add:

Daily

if compatible with numbering requirements.

27. Document Sequences — Add Scope

The client needs document numbering configurable by operational scope.

Review support for:

company-wide,
branch,
POS,
cash drawer,
warehouse,

depending on document type.

Do not allow arbitrary scope combinations that create duplicate numbers.

Design a deterministic uniqueness model.

Example:

{branch}-{documentType}-{date}-{sequence}

where configured.

28. Prefix and Suffix UX

Explain:

Prefix = text before sequence
Suffix = text after sequence

Show a live preview.

Example:

Prefix: INV-
Padding: 6
Next: 42
Suffix: -DK

Preview:

INV-000042-DK

29. Sequence Override — Redesign UX

Current fields such as:

Current Next Value
New Next Value
Override Reason
Audited
Requires Dedicated Permission

are confusing.

Requirement

Separate normal configuration from dangerous administrative override.

Normal Sequence Settings
Prefix
Suffix
Padding
Reset Rule
Scope
Current Next Number — read-only
Administrative Override

A dedicated action:

Change Next Number

Require:

dedicated permission,
new next value,
mandatory reason,
confirmation,
audit record,
concurrency-safe transaction.

Avoid showing two editable “next value” fields simultaneously.

30. Sequence Version / V1 Confusion

The UI exposes V1, while another version value elsewhere appears different.

Determine exactly what each version represents.

Potential concepts:

sequence configuration version,
document version,
optimistic-lock version,
application version.

Rename labels accordingly.

Never display a bare ambiguous V1 if it represents an internal locking/configuration version.

If not useful to business users, hide it and retain it only in audit details.

31. Printers and Print Templates Must Be Separate Concepts

Current “Printing & Templates” screen mostly appears to define printers.

The client expects actual templates.

Required Architecture

Separate:

Printers

Fields may include:

Printer Name
Connection Type
IP / Host
Port
Paper Size
Branch
Default flag
Active
Print Templates

Templates may include:

Template Name
Document Type
Layout
Paper Size
Language
Version
Active
Default
Printer ↔ Template Assignment

Support valid many-to-many or rule-based mappings as appropriate.

Example:

Receipt Printer
→ POS Receipt Arabic 80mm
→ POS Receipt English 80mm

A4 Office Printer
→ Tax Invoice A4

32. Runtime Print Selection

At print time, where business rules allow:

user should be able to:

use default printer,
choose another authorized printer,
choose an alternative compatible template.

Do not allow cross-branch printer use accidentally.

If cross-branch IP printing is intentionally allowed, enforce explicit permissions/network configuration.

Display printer branch/location to prevent mistakes.

33. Settings Change Log Is Not a Settings Form

The client correctly identifies that the Settings Change Log is a history/audit screen.

Move or label it accordingly.

Preferred:

Configuration Change History

It should not display:

New,
Add,
Save

unless an actual audit-management workflow exists.

It should show:

Date/time
User
Configuration area
Field/key
Old value
New value
Reason if applicable
Branch/company scope
correlation/request id when useful.

This should be read-only for normal admins.

34. Fix Sidebar Navigation Logic
Critical UX Defect

The client clicks:

Administration

and selection/scrolling jumps to unrelated sections such as:

Sales
Reports

or highlights the wrong parent.

Required Investigation

Inspect:

active route matching,
nested menu state,
duplicate route prefixes,
Alpine/Livewire keys,
browser scroll restoration,
collapse state persistence,
RTL ordering,
DOM IDs,
anchor collisions.
Required Behavior

Clicking a parent:

expands/collapses only that section,
does not navigate unless intentionally configured,
does not alter another parent,
does not unexpectedly jump scroll position.

Clicking a child:

opens the correct page,
highlights child,
keeps correct parent expanded,
preserves navigation state after reload.

Test desktop and mobile.

Test Arabic RTL and English LTR.

35. Improve Bilingual UI

Multiple pages mix Arabic and English mid-sentence, producing unreadable messages.

Examples include registration blocked/configuration messages.

Requirement

No raw untranslated backend message should be shown to the user.

Use localization keys.

Arabic page:

complete Arabic sentence.

English page:

complete English sentence.

Technical identifiers may remain English where necessary, but surrounding grammar must be localized correctly.

Test RTL rendering of:

validation messages,
badges,
empty states,
modal titles,
dropdown options,
mixed numeric values.
36. Category English Name Must Be Optional

The client accepts English names for web use but does not want them mandatory for every ERP customer.

Requirement

Arabic-only business users must be able to create categories without English text unless an external storefront/channel specifically requires it.

Recommended:

Arabic Name: required in Arabic-primary deployment.
English Name: optional.

If website publishing requires English:

validate it at the publishing/channel boundary, not during generic category creation.

37. Category Hierarchy Display Is Broken
Problem

Example:

Parent:

Boys Toys

Child:

Guns

The child appears as a flat third category instead of nested beneath its parent.

Display order also behaves inconsistently.

Required Fix

Support true hierarchical visualization.

Examples:

Boys Toys
　└─ Guns

Girls Toys

or tree/table indentation.

Ordering Rule

Ordering must be deterministic.

Define whether display_order applies:

globally,
among siblings.

Preferred hierarchy behavior:

order siblings within the same parent.

Root categories have their own sibling order.

Children have their own sibling order.

Changing child order must not cause it to escape its parent hierarchy.

Validation

Prevent:

category being its own parent,
recursive cycles,
invalid cross-company parent references.
38. Customer Registration Configuration Blocker

The customer page currently displays a message similar to:

Registration is blocked until customer purpose is configured

Requirement

Identify what “customer purpose” means in the domain.

If a legitimate required configuration is missing:

provide a clear localized message,
link authorized admins directly to the missing configuration,
explain why it is required.

Example:

Customer creation is unavailable because Customer Classification has not been configured. Configure it now.

Do not expose opaque internal terminology.

39. Customer Name Structure

Client requests at least:

First Name
Last Name

rather than allowing a single ambiguous name.

Review bilingual needs.

Possible structure:

Arabic First Name
Arabic Last Name
English First Name
English Last Name

Do not overcomplicate if the PRD has an existing person-name model.

At minimum, enforce sufficient identity quality to reduce duplicates.

40. Customer Duplicate Detection

Do not depend only on names.

Review duplicate detection using combinations such as:

normalized phone,
email,
first + last name,
date of birth where appropriate.

Never automatically merge customers only because names match.

Potential duplicates should be surfaced safely.

41. Privacy and Consent UX

The client does not understand the “Privacy and Consent” control.

Audit what it actually represents.

Separate explicit consent types if required:

marketing communication,
data processing,
WhatsApp communication,
SMS,
email.

Do not represent consent as a vague blue control.

Each consent must show:

purpose,
state,
capture timestamp,
captured by/source where required.
42. Customer Grouping Hierarchy

Client requires hierarchical customer grouping.

Example:

Schools
→ School A
→ Customer

Schools
→ School B
→ Customer

Other roots:

Companies
Clubs
Regular Customers
Requirement

Support:

Customer Group
→ optional Parent Group
→ Customers

Allow nested hierarchy at least to the level required by the business.

Use a hierarchical selector/tree.

Add group filtering to customer search/list.

43. Child Profiles — Multiple Children Per Customer/Family

A customer/guardian may have multiple child profiles.

Requirement

Relationship:

Customer / Guardian
→ many Child Profiles

Each child may contain:

Arabic Name
English Name where applicable
Date of Birth
other fields already defined in PRD

Customer profile UI should clearly show:

Children
Child 1
Child 2
Child 3

with:

Add Child

Do not limit the customer to one child.

Explain any unusual child-profile fields using help text.

44. Loyalty & Points — Wrong CTA

Current Loyalty & Points section contains:

New Customer

which routes to generic customer creation.

This is a clear navigation/connection defect.

Required Fix

The loyalty module should expose loyalty-relevant actions.

Depending on existing PRD:

Loyalty Programs
Earning Rules
Redemption Rules
Expiry Rules
Customer Loyalty Ledger
Adjustments with authorization

Remove/repair unrelated New Customer action.

45. Product Wallet — Configuration UX

The Product Wallet area shows an unclear mixed-language not-configured state.

Requirement

Clarify:

what Product Wallet means,
why it exists,
required prerequisites,
where it is configured.

When unavailable:

show a meaningful empty state with CTA if the user is authorized.

Example:

Product Wallet is not configured yet. Configure wallet rules before using customer product credits.

Only use terminology consistent with the PRD.

46. Supplier Groups

Add supplier grouping similar to customer grouping where required.

At minimum:

Supplier Group
→ Suppliers

If hierarchical parent groups are needed by the existing requirements, support:

Parent Supplier Group
→ Supplier Group
→ Supplier

Add filtering/search by group.

47. Supplier Contact Model Is Incomplete

Supplier must support structured contacts.

At minimum consider:

Company/Owner Contact
Name
Phone
Email
Sales / Account Representative
Name
Phone
Email
Order Contact
Name
Email
Phone / WhatsApp where appropriate
Accounting Contact
Name
Email
Phone

Avoid hard-coding Purchase Orders to the company owner's email.

48. Supplier Communication Preferences

Supplier should specify destinations for:

Purchase Orders
Accounting correspondence
General communication

Where appropriate support channel preference:

Email
WhatsApp
Phone

Store separate destinations where needed.

This prepares the domain for future automation.

Do not implement automated messaging unless already in scope.

Implement the structured data and selection rules first.

49. Supplier Order Recipient

When sending a Purchase Order in future:

the system must be able to resolve the designated order recipient.

Fallback logic, if allowed, must be explicit.

Example:

Order Contact
→ Representative
→ General Supplier Email

Never silently choose the company owner's personal email without a business rule.

50. Phone Input Error

The client received an error merely after entering a phone number for Branch 1 / Dokki.

Required Investigation

Reproduce using Egyptian numbers.

Review:

normalization,
country code,
formatting,
DB length,
uniqueness scope,
regex,
nullable behavior,
Arabic numerals,
whitespace,
+20,
leading zero.
UX Requirement

Use a clear phone control.

For Egypt, accept valid common input representations and normalize them consistently.

Do not show a generic server error.

Example normalization:

01012345678

and

+201012345678

should be handled according to a documented canonical format.

51. Make All Validation Actionable

Every form must have:

field-level errors,
human-readable localized messages,
no raw SQL exceptions,
no stack traces,
no silent failures.

On server failure:

retain entered user data where safe.

52. Master Data Dependency UX

If a master cannot be created due to missing prerequisite:

do not simply disable the screen.

Show:

What is missing.
Why it is needed.
Who can configure it.
A direct Configure button if authorized.

Example:

A warehouse cannot be created until at least one branch exists.

[Create Branch]

53. Prevent Fake “Ready / Configured” States

Setup status must come from actual business data/configuration.

Examples:

Company Identity complete only when mandatory company fields are valid.

Branch setup complete only when at least the required operating structure exists.

Printer setup must not be considered complete merely because the screen loads.

54. Multi-Branch Readiness

Because the client expects 4 → 6 branches soon, verify every master against branch scope.

Specifically audit:

branches,
warehouses,
POS,
cash drawers,
printers,
sequences,
timezone,
taxes,
payment methods,
categories,
customers,
suppliers.

Determine explicitly whether each master is:

Global Company Master
or
Branch Scoped
or
Both with override.

Avoid accidental duplication across branches.

55. Configuration Inheritance Pattern

Where sensible implement:

Company Default
→ Branch Override
→ Device/POS Override

Examples:

Timezone
Printer
Tax defaults
document sequence policy

Clearly indicate in UI when a value is:

inherited,
overridden.

Example:

Africa/Cairo — inherited from company

56. Empty States

Every empty dropdown/table must distinguish:

no records exist,
no records match filter,
you do not have permission,
prerequisite missing.

Do not simply show an empty list.

57. Destructive Action UX

For Delete/Archive actions:

explain dependencies,
use confirmation,
show exact entity name,
state whether historical data will remain.

Never provide non-functional Delete buttons.

58. Help Text for Business Terms

Add short contextual help/tooltips for terms including:

Tax Inclusive
Prefix
Suffix
Reset Rule
Sequence Override
Payment Evidence
Offline POS Eligibility
Warehouse Type
Child Profile
Customer Group
Supplier Group
Default Printer
Print Template

Keep help business-oriented, not implementation-oriented.

59. UI Consistency

All master-data forms should follow the same interaction model.

Recommended:

List
→ New
→ Form
→ Save
→ Success
→ Details/Edit

Use consistent positioning for:

New
Save
Cancel
Delete/Archive
Audit History.
60. Save Buttons Must Reflect Dirty State

Where practical:

Save button should be disabled when there are no changes.

On change:

enable Save.

While saving:

show loading state and prevent duplicate submission.

After successful persistence:

return to clean state.

61. Audit Requirements

Configuration changes to sensitive business setup should include:

actor,
timestamp,
scope,
entity,
before,
after,
reason for privileged overrides,
request/correlation ID where available.

Sensitive areas include:

tax configuration,
document sequences,
payment configuration,
branch configuration,
printer defaults,
sequence override.
62. Authorization

Do not assume every admin can change every configuration.

Review permissions for:

company settings,
branch management,
warehouse management,
taxes,
payment methods,
sequences,
sequence override,
printers/templates,
customer/supplier groups.

Dangerous actions should have granular permission.

63. Concurrent Update Safety

For settings that affect transaction integrity:

document numbering,
branch configuration,
sequence override,

use transactions / locks / optimistic versioning where required.

Never allow two users to produce duplicate document sequence numbers.

64. Existing UI Regression Defects Must Also Be Rechecked

While implementing this change request, do not ignore known UI failures in the current application.

Specifically retest existing failures around:

Product Add action,
POS operating context,
Page Guide,
Appearance Customizer.

Do not mark the overall remediation complete while major canonical user-story defects remain.

65. Test Strategy

Use the existing project testing infrastructure.

Do not replace the existing QA system.

Run:

Backend
targeted Pest/Feature tests,
affected module tests,
authorization tests,
validation tests,
persistence tests.
Database

Use MariaDB.

Do not use SQLite as a substitute if production behavior depends on MariaDB.

UI

Use visible headed Chromium / Playwright.

Run in English/LTR by default.

Perform explicit Arabic/RTL checks for localization and layout.

Test desktop and mobile.

66. Minimum New E2E Scenarios

Create/reuse automated UI coverage for at least:

Company
Edit company identity.
Save.
Reload.
Verify persistence.
Branches
Create Branch 1.
Create Branch 2.
Create Branch 3.
Create until Branch 6.
Verify timezone inheritance.
Warehouse
Create warehouses under different branches.
Verify correct counts.
Verify branch dropdown.
Archive/delete unused warehouse.
Attempt removal of used warehouse and verify safe denial.
Drawer
Create drawer.
Link correct branch/POS.
Reload.
Verify relationship.
Category
Create parent.
Create child.
Verify visual nesting.
Reorder sibling.
Reload and verify hierarchy.
Customer
Create customer.
Add customer group.
Add multiple children.
Reload.
Supplier
Create group.
Add owner contact.
Add representative.
Add order email.
Add accounting email.
Reload.
Printer
Create printer.
Create template.
Assign template.
Set default.
Verify branch context.
Sequence
Configure daily reset.
Configure branch scope.
Preview number.
Perform authorized override.
Verify audit record.
67. UX Review Requirement

For every affected page ask:

Could a retail business owner understand what to do here without a developer explaining the internal architecture?

If not, improve the UI.

Do not solve confusion only by adding giant help paragraphs.

Prefer:

better naming,
better grouping,
hierarchy,
defaults,
progressive disclosure,
contextual help,
clear empty states.
68. Do Not Invent Business Behavior

Where client feedback asks “what does this mean?”:

first inspect the actual existing business rule.

If no meaningful rule exists, classify it as obsolete/incomplete configuration and simplify/remove it.

Do not fabricate semantics merely to preserve an existing field.

69. Migration Safety

All database changes must:

preserve existing data,
be backward-safe,
avoid dropping historical records,
have rollback strategy where practical.

If converting terminology only at UI level, do not rename DB tables unnecessarily.

70. Seeders / Demo Data

Update deterministic test/demo seeders so the complete setup flow can be exercised.

Seed:

company,
multiple branches,
warehouses,
valid POS relationships,
drawers,
payment methods,
tax profiles,
sequences,
customer groups,
supplier groups,
printers,
templates,

where appropriate.

Avoid fixtures that hide broken configuration.

71. Definition of Done

This change request is NOT complete merely because screens render.

For every item provide:

root cause,
implementation,
test evidence,
screenshots for UI changes,
remaining risk.

The setup phase must support a coherent journey:

Company
→ Branches
→ Warehouses
→ POS / Drawers
→ Payments
→ Taxes
→ Sequences
→ Printers/Templates
→ Categories
→ Customers/Groups/Children
→ Suppliers/Groups/Contacts
→ Products/import prerequisites

without unexplained blockers.

72. Final Deliverable

At completion produce:

A. Client Feedback Remediation Matrix

Columns:

ID
Requirement
Classification
Root Cause
Changed Files
Database Change
Backend Change
UI Change
Automated Test
Result
B. UX Before/After Summary

Explain the main workflow improvements.

C. Remaining Blockers

List only real unresolved blockers.

D. Test Evidence

Provide:

Pest results,
Playwright results,
headed-browser screenshots,
relevant traces,
affected user-story status.
E. Final Verdict

Choose only:

PASS — Setup phase ready for client UAT
PARTIAL — Specific documented items remain
FAIL — Setup phase still unsafe/incomplete

Do not declare PASS if saving, relationships, navigation, master-data hierarchy, or multi-branch setup are still unreliable.

Priority Order

Implement in this order:

P0 — Blocks client from continuing setup
Company identity not saving.
Branch creation failure.
Incorrect/stale branch data.
Branch/warehouse/POS linkage.
Cash drawer association.
Warehouse dropdown/count issues.
Phone-number error.
Broken sidebar navigation.
Warehouse delete/archive behavior.
P1 — Setup architecture/business configuration
Remove duplicate settings navigation.
Timezone inheritance.
Payment method model.
Taxes and invoice override model.
Document sequences.
Printers vs templates.
Setup dashboard.
Manual vs Excel definition workflows.
P1 — Master Data
Category hierarchy/order.
Optional English category name.
Customer groups.
Multiple child profiles.
Customer registration prerequisite UX.
Supplier groups.
Supplier contacts/communications/payment terms.
P2 — Clarity and Polish
Settings Audit terminology.
Policy/Baseline terminology.
Loyalty wrong CTA.
Product Wallet empty state.
Warehouse type terminology.
Privacy/Consent UX.
Help text.
Arabic/English localization quality.
Execution Rule

Do not stop after identifying the defects.

For each safe in-scope issue:

inspect → reproduce → identify root cause → implement → test → visually verify → regress affected flows.

If the existing implementation contradicts the PRD, explicitly report the contradiction and follow the authoritative requirement rather than layering another workaround on top