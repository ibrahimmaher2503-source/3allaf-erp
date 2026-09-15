# 04 — Roles and Permissions

## Permission Status Legend

| Code | Status | Meaning |
|---|---|---|
| A | Approved | Explicitly supported by the PRD for the role/capability. |
| P | Proposed | Reasonable implementation mapping that requires validation in the final matrix. |
| R | Requires Owner Approval | Sensitive capability, scope, limit, or role assignment is unresolved. |
| N | Not Allowed | Explicitly prohibited or incompatible with the role boundary. |

All permissions must be enforced through server-side policies/gates and scoped queries. UI visibility mirrors authorization but never replaces it. A final owner-approved role matrix is a blocker; `P` and `R` entries are not production grants.

## Canonical Roles

| Role | Source capability | Default scope | Sensitive boundary |
|---|---|---|---|
| System Administrator | Company settings, users, roles, policies, global review, audit. | Global, subject to policy. | Operational approve/override rights are R, not automatic. |
| Branch Manager | Branch monitoring, cash control, store override, shift review, return/adjustment approvals. | Assigned branches/stores. | No global configuration unless separately granted. |
| Cashier | POS, own shift, payment evidence, suspended sales, receipts, availability search. | Assigned active branch/store/drawer; own shift. | Party Wallet is N; expected close before submission is N. |
| Purchasing Officer | Suppliers, purchase orders/invoices, supplier returns. | Delegated branches/stores/suppliers. | Price approval and stock adjustment approval are N unless separate permission. |
| Warehouse Manager | Receiving, transfers, stock documents/count review/adjustments/reorder. | Assigned stores/branches. | Cannot sell other-branch stock from POS. |
| Pricing Officer | Proposals, versions, approvals, label queues. | Delegated price lists/locations. | Open-price selling is separately R. |
| Party Manager | Bookings, party invoices/payments, orders, consumables, assets, inspections. | Assigned party operations/stores. | Product Wallet balance/debt is N. |
| Stock Counter | Count sessions and draft count reports. | Assigned count/store. | Reconciliation approval is N. |
| Accountant / Reviewer | Read, export, reconcile, and report in delegated scope. | Assigned branches/stores/modules. | Unauthorized operational edit is N. |

Implementation Plan aliases (`Owner`, `Warehouse Officer`, `Party Officer`, and `Accountant/Auditor`) require owner reconciliation under DEC-020.

## Module Permission Matrix

Cell values name the permitted roles followed by status code. `—` means no role is granted by this document. `R` always requires an explicit scoped approval permission.

| Module | View | Create | Edit | Logical Delete | Print | Approve | Export | Reverse | Cancel | Override | Scope / sensitive access |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Company Settings | Administrator A; Reviewer P | Administrator A | Administrator A | Administrator R | Administrator P | Administrator R | Administrator R; Reviewer R | — | — | Administrator R | Global; secrets never displayed. |
| Branches & Stores | Administrator A; Branch Manager A; Warehouse Manager P; Reviewer P | Administrator A | Administrator A; Branch Manager R | Administrator R | Administrator P | Administrator R | Administrator R; Reviewer R | — | Administrator R | Branch Manager R | Branch/store-scoped; selling-store override fully audited. |
| Drawers, Payments, Tax, Numbering, Printers | Administrator A; Branch Manager P; Cashier limited A; Reviewer P | Administrator A | Administrator A; Branch Manager R | Administrator R | Administrator/Branch Manager P | Administrator R | Administrator/Reviewer R | — | Administrator R | Branch Manager R | Cashier sees assigned drawer/methods only; no numbering override. |
| Users, Roles & Permissions | Administrator A; Reviewer R | Administrator A | Administrator A | Administrator R | Administrator P | Administrator R | Administrator R; Reviewer R | — | Administrator R | Administrator R | Branch/store scope and sensitive access require explicit grants. |
| Products, Categories & Brands | Administrator A; Pricing/Purchasing/Warehouse A; Cashier limited A; Reviewer A | Pricing/Purchasing P | Pricing/Purchasing P | Administrator/Pricing R | Pricing/Warehouse P | Pricing R for imports/status | Pricing/Purchasing/Reviewer R | — | Import batch R | Pricing R | Cost fields hidden from Cashier unless approved. |
| Suppliers | Administrator/Purchasing/Warehouse/Reviewer A | Purchasing A | Purchasing A | Purchasing R | Purchasing P | Purchasing R | Purchasing/Reviewer R | — | Purchasing R | Preferred supplier change R | Actual supplier history immutable and audited. |
| Purchase Orders | Purchasing/Warehouse/Reviewer A | Purchasing A | Purchasing A in Draft | Purchasing R in Draft | Purchasing A | Purchasing/Manager R | Purchasing/Reviewer R | Purchasing R after approval | Purchasing R by state | — | Delegated supplier/store; approved history immutable. |
| Purchase Invoices & Supplier Returns | Purchasing/Warehouse/Reviewer A | Purchasing A | Purchasing A before approval | Purchasing R in Draft | Purchasing A | Warehouse/Purchasing R | Purchasing/Reviewer R | Purchasing/Manager R | Purchasing R by state | Cost/receipt R | Receiving-store scope; approval creates stock movement. |
| Pricing & Labels | Pricing/Administrator/Branch Manager/Reviewer A; Cashier approved price A | Pricing A | Pricing A before approval | Pricing R in Draft | Pricing/Warehouse A | Pricing A when configured | Pricing/Reviewer R | Pricing R | Pricing R | Open price/branch exception R | Store/price-list scope; label printing audited. |
| Inventory & Stock Card | Warehouse/Branch Manager/Reviewer A; Cashier availability A | Warehouse A for documents | Warehouse A before approval | Warehouse R in Draft | Warehouse/Reviewer A | Warehouse/Manager R | Warehouse/Reviewer R | Warehouse/Manager R | Warehouse R | Negative stock R | Assigned stores; Cashier may search other branches but cannot sell. |
| Transfers | Warehouse/Branch Manager/Reviewer A | Warehouse A | Warehouse A by state | Warehouse R in Draft | Warehouse A | Warehouse/Manager R | Warehouse/Reviewer R | Warehouse/Manager R | Warehouse R | Difference disposition R | Source/destination scope and separation of dispatch/receipt duties P. |
| Stock Counts | Warehouse/Stock Counter/Reviewer A | Stock Counter/Warehouse A | Stock Counter A in count states | Stock Counter R in Draft | Stock Counter/Warehouse A | Warehouse Manager A; Stock Counter N | Warehouse/Reviewer R | Warehouse/Manager R | Warehouse R | Reconciliation R | Counter assigned session/store; uncounted items require review. |
| POS Sales | Cashier/Branch Manager/Reviewer A | Cashier A with active shift | Cashier A before approval | Cashier N | Cashier A | Cashier/Manager R by policy | Manager/Reviewer R | Manager R through reference document | Manager R | Quantity/open price/negative stock R | Own active branch/store/drawer/shift; party activity N. |
| Suspended Sales | Cashier own A; Manager/Reviewer P | Cashier A | Cashier own A | Cashier/Manager R | Cashier P | Manager R | Manager/Reviewer R | — | Cashier/Manager R | Access another cashier's hold R | Own records only by default. |
| Shifts & Cash Movements | Cashier own A; Manager/Reviewer A | Cashier A to open; permitted cash movement P | Cashier own before close | N | Cashier/Manager A | Manager A for variance | Manager/Reviewer R | Manager R | Manager R | Variance settlement R | Cashier never sees expected values before blind submission. |
| Customers & Children | Cashier/Party Manager/Manager/Reviewer A within purpose | Cashier/Party Manager A | Authorized actor A | Administrator R | Authorized actor P | — | Manager/Reviewer R | — | — | Merge/duplicate resolution R | Unique phone; consent and sensitive fields purpose-scoped. |
| Loyalty | Authorized retail/party actors A; Reviewer A | System/source transaction A | Rule admin R; no ledger edit | N | Authorized actor P | Redemption/adjustment R | Reviewer R | Referenced correction R | — | Manual adjustment R | Shared balance; activity-specific rules. |
| Product Wallet | Cashier/retail manager/Reviewer A; Party Manager N | Source transaction A | N | N | Retail actor P | Settlement/adjustment R | Reviewer R | Referenced correction R | — | Manual adjustment R | Retail-only visibility and ledger. |
| Party Wallet | Party Manager/authorized Reviewer A; Cashier N | Source transaction A | N | N | Party actor P | Settlement/adjustment R | Reviewer R | Referenced correction R | — | Manual adjustment R | Party-only visibility and ledger. |
| Returns, Exchanges & Gift Instruments | Cashier/Manager/Reviewer A; Party roles only if separately relevant | Cashier/Manager A by policy | Before approval P | N | Cashier/Manager A | Manager R for exceptions | Manager/Reviewer R | Manager R through reference | Manager R | Condition/refund exception R | Original reference and condition required; party/retail boundary. |
| Party Bookings & Invoices | Party Manager/Reviewer A | Party Manager A | Party Manager A until final close | Party Manager R in Draft | Party Manager A | Party Manager/Manager R | Party Manager/Reviewer R | Party Manager/Manager R | Party Manager R | Post-close N; additions/removals R by state | Party stores only; Product Wallet N. |
| Party Operating Orders & Consumables | Party Manager/Warehouse/Reviewer A | Party Manager A | Party Manager A by state | N | Party Manager/Warehouse A | Party/Manager R | Reviewer R | Reference return/adjustment R | Party Manager R | Quantity/disposition R | Party inventory only; movements auditable. |
| Rental Assets | Party Manager/Reviewer A | Party Manager A | Party Manager A by state | N for historical assets | Party Manager A | Damage/depreciation/retire R | Reviewer R | Reference correction R | Reservation R | Conflict/status/cost R | Reservation interval/location scope; no double booking. |
| Quotations | Authorized retail/party roles A | Authorized actor A | Authorized actor A before expiry/closure | Authorized actor R in Draft | Authorized actor A | Manager R where configured | Reviewer/authorized actor R | — | Authorized actor R | — | Quotation activity type determines product vs party visibility; no effects. |
| Dashboard & Reports | All roles A within scope | — | — | N | Authorized actor P | — | Reviewer/authorized actor R | — | — | Cross-scope N | Every query and export is role/branch/store/activity scoped. |
| Audit Logs | Administrator A; Reviewer A; Managers limited P | System only A | N | N | Administrator/Reviewer P | — | Administrator/Reviewer R | N | N | N | Append-only; sensitive before/after values redacted as needed. |
| Offline Queue & Conflicts | Cashier own limited A; Manager/Administrator/Reviewer A | System/device A | N | N | Manager P | Manager R for disposition | Reviewer R | Reference correction only | Queue cancel R | Conflict resolution R | Device/user/branch scoped; server truth prevails. |

## Permission Evaluation Order

1. Authenticated and active user/session/device.
2. Role has the module/action capability.
3. User is assigned to the required branch and store.
4. Document type, activity type, status, and ownership allow the action.
5. Required approval/override permission and configured amount/quantity limits are satisfied.
6. Sensitive customer, cost, wallet, payment, audit, or export fields are purpose-authorized.
7. State transition, validation, separation, immutability, and concurrency rules pass.
8. The action and reason are audited where required.

## Explicit Boundaries

- Cashier: no Party Wallet balance/debt, no other store sale, no expected close before submission, no unapproved price, and no credit/wallet/loyalty/special discount while offline.
- Party Manager: no Product Wallet balance/debt and no retail line on a party document.
- Stock Counter: no reconciliation approval.
- Reviewer: no operational edits unless a distinct, explicit operational role grants them.
- No role directly edits an approved document or ledger entry.
- Logical delete never removes approved history; exports and audit access are separately permissioned.

## Owner Approval Required

The owner must approve exact role aliases, branch/store assignments, own-record rules, approval separation, amount/quantity limits, override reasons, cross-branch visibility, cost visibility, customer-sensitive-field access, export limits, audit redaction, and every `P`/`R` cell before production use.
