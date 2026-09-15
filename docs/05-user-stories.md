# 05 — User Stories

## Derived Platform Story — US-046

TSK-004B adds US-046, Customize and Learn the Application Interface. The authenticated user can persist approved UI preferences and receive localized, permission-aware, documentation-backed guidance for registered screens. See `docs/40-contextual-page-guide-specification.md`; no business workflow or authorization grant changes.

All stories are `Not Started`. Alternate/failure paths are mandatory acceptance behavior, not optional enhancements. IDs in the Relationships line link the story to requirements, screens, flows, tasks, and acceptance criteria.

## US-001 — Govern Company and Operating Masters

- **Actor:** System Administrator; **Goal/Title:** configure company, numbering, tax, payments, printers, branches, stores, drawers, users, roles, price lists, and customer policies; **Business value:** one governed operating baseline.
- **Preconditions:** authenticated, scoped administrator; approved master data; **Trigger:** a setup item must be added or changed.
- **Main flow:** open the relevant settings screen, enter validated data, preview effect, save/approve as permitted, and receive success feedback.
- **Alternate flow:** save an eligible draft or cancel without effect; reuse an existing active master record.
- **Failure flow:** duplicate code/sequence, invalid mapping, unauthorized scope, or unsafe delete is blocked with field/action feedback.
- **Permissions:** module/action/branch/store policy; sensitive approval for delete, numbering, tax, and override.
- **Audit:** create/change/status/delete/approval with actor, scope, reason, and before/after values.
- **Relationships:** PRD MD-01, NFR-01–NFR-03, NFR-06; UI UI-ADM-002–UI-ADM-012; flows FLW-ADM-01–FLW-ADM-05; tasks TSK-005–TSK-009; acceptance AC-MD-01, AC-NFR-01, AC-NFR-02, AC-NFR-03, AC-NFR-06.

## US-002 — Maintain Stable Product Identity

- **Actor:** Pricing Officer or Purchasing Officer; **Goal/Title:** create a bilingual product with stable item identity, barcode, attributes, images, supplier, and stock/search metadata; **Business value:** consistent catalog and traceability.
- **Preconditions:** permitted user; categories/brands/suppliers exist; **Trigger:** new or changed merchandise/service master.
- **Main flow:** assign immutable internal item code, capture required bilingual fields and product type, add supplier/international or local barcode, add images/attributes, validate, and save.
- **Alternate flow:** change preferred supplier without changing item code or historical actual suppliers; use searchable attributes without variants.
- **Failure flow:** duplicate code/barcode, invalid local format, more than permitted images, missing required fields, or unauthorized cost access is blocked.
- **Permissions:** product create/edit and sensitive cost/status/image rights; logical delete only where safe.
- **Audit:** code/barcode, preferred supplier, status, image, cost-related, and attribute changes.
- **Relationships:** PRD MD-02–MD-05, NFR-01; UI UI-CAT-001–UI-CAT-007; flows FLW-CAT-01; tasks TSK-010–TSK-012; acceptance AC-MD-02–AC-MD-05.

## US-003 — Maintain a Unique Customer Profile

- **Actor:** Cashier or Party Manager; **Goal/Title:** register/update one customer by unique phone with consent, contacts, children/birthdays, activity history, gift activity, loyalty, and separated wallets; **Business value:** reliable service without duplicate or leaked balances.
- **Preconditions:** purpose-authorized user; consent policy available; **Trigger:** customer lookup fails or profile data changes.
- **Main flow:** search phone, capture validated consent/contact/child data, save, and display role-authorized history and balances.
- **Alternate flow:** attach a sale/booking to an existing record or request authorized duplicate review.
- **Failure flow:** duplicate phone, invalid consent/contact, unauthorized wallet/history access, or unsafe merge is blocked.
- **Permissions:** purpose, branch/activity, sensitive field, Product/Party Wallet isolation.
- **Audit:** create/edit/consent/duplicate-resolution and sensitive access where required.
- **Relationships:** PRD MD-06, CUS-01–CUS-04; UI UI-CUS-001–UI-CUS-005; flows FLW-CUS-01, FLW-CUS-04–FLW-CUS-05; tasks TSK-027–TSK-028; acceptance AC-MD-06, AC-CUS-01–AC-CUS-04.

## US-004 — Import Products Safely

- **Actor:** Authorized catalog user; **Goal/Title:** import products from Excel with mapping, validation, error report, and explicit create/update mode; **Business value:** efficient bulk setup without corrupt rows.
- **Preconditions:** approved template/file limits; referenced masters exist; **Trigger:** user uploads a catalog file.
- **Main flow:** upload, map columns, choose `Create Only` or `Update Existing`, validate all rows, review summary/errors, approve valid write, and download rejected-row report.
- **Alternate flow:** correct mapping/file and revalidate; abandon batch without writes.
- **Failure flow:** invalid rows are not written; unsafe type/formula/file, duplicates, missing references, or unauthorized update mode is blocked.
- **Permissions:** import/create/update/approve/export errors separately checked.
- **Audit:** upload metadata, mapping, mode, counts, approval, and row-level outcome references.
- **Relationships:** PRD PRC-01, NFR-04; UI UI-CAT-004–UI-CAT-005; flows FLW-CAT-02; task TSK-012; acceptance AC-PRC-01.

## US-005 — Configure Product Types

- **Actor:** Authorized catalog user; **Goal/Title:** maintain standard, composite, and service products only where approved; **Business value:** catalog matches operating needs without inventing variants.
- **Preconditions:** approved product setup defines permitted types; **Trigger:** product type is chosen/changed.
- **Main flow:** select type, display its applicable fields, validate composition/service data, and save.
- **Alternate flow:** retain standard product where no composite/service requirement exists.
- **Failure flow:** unsupported transition, missing component data, stock-bearing service, or unapproved type is blocked.
- **Permissions:** product-type configure/edit; post-transaction type change requires approval or is blocked.
- **Audit:** type and composition changes.
- **Relationships:** PRD PRC-02; UI UI-CAT-003; flow FLW-CAT-01; task TSK-011; acceptance AC-PRC-02.

## US-006 — Capture a Sale Price Without Cost Coupling

- **Actor:** Pricing Officer; **Goal/Title:** propose location prices from product, import, or purchase context without automatic cost-to-price changes; **Business value:** controlled commercial pricing.
- **Preconditions:** product/location/price list exist; **Trigger:** a price requires creation or review.
- **Main flow:** choose source and location, enter proposed price/effective context, validate, and submit into version workflow.
- **Alternate flow:** a purchase-cost change creates a pricing review alert but leaves approved sale price unchanged.
- **Failure flow:** invalid/negative price, ambiguous location, unauthorized import, or direct active-price overwrite is blocked.
- **Permissions:** pricing create/edit/import; approval is separate where configured.
- **Audit:** source, cost context, proposed value, location, and actor.
- **Relationships:** PRD PRC-03; UI UI-PRC-001; flows FLW-CAT-03; tasks TSK-017; acceptance AC-PRC-03.

## US-007 — Approve Versioned Prices and Labels

- **Actor:** Pricing Officer; **Goal/Title:** approve an immutable price version, activate one shelf price per item/location, preserve history, and create location label quantities; **Business value:** accurate shelves and future sales.
- **Preconditions:** valid proposal and configured approval; remaining stock known; **Trigger:** version submitted for approval.
- **Main flow:** review differences, approve atomically, end prior active price, activate new price for future transactions/remaining balance, queue one label per remaining unit/location, and notify users.
- **Alternate flow:** reject/return proposal; branch exception uses its authorized approved price.
- **Failure flow:** stale version, concurrent approval, duplicate active price, no approved price, invalid stock/location, or unauthorized label print is blocked.
- **Permissions:** pricing approve, label view/print/reprint, branch exception; explicit reason for reprint/override.
- **Audit:** version state, before/after price, approval, effective time, label quantity, print/reprint.
- **Relationships:** PRD PRC-04–PRC-07, NFR-01; UI UI-PRC-001–UI-PRC-003; flows FLW-CAT-04–FLW-CAT-05; tasks TSK-017–TSK-018; acceptance AC-PRC-04–AC-PRC-07.

## US-008 — Perform Authorized Open-Price Sale

- **Actor:** Cashier with explicit open-price right; **Goal/Title:** sell within approved reference/min/max bounds and capture required reason; **Business value:** controlled exception without losing auditability.
- **Preconditions:** product/location policy enables open price; active shift; **Trigger:** cashier invokes price override.
- **Main flow:** show reference and limits, enter price/reason, authorize, recalculate totals, and record override.
- **Alternate flow:** manager provides approval where role alone is insufficient.
- **Failure flow:** missing reason, out-of-range price, offline use, unauthorized actor, or stale policy is blocked without losing cart.
- **Permissions:** explicit Open Price and possibly Approve; no implied right from POS access.
- **Audit:** reference/min/max/entered price, reason, approver, sale, device, branch/store.
- **Relationships:** PRD PRC-08, NFR-01; UI UI-POS-001; flows FLW-POS-01; task TSK-024; acceptance AC-PRC-08.

## US-009 — Maintain Supplier History

- **Actor:** Purchasing Officer; **Goal/Title:** maintain supplier contact/status/terms, preferred-product links, invoices, returns, and last prices while preserving actual historical suppliers; **Business value:** trustworthy purchasing decisions.
- **Preconditions:** permitted purchasing scope; **Trigger:** supplier or preference changes.
- **Main flow:** create/update supplier, associate preferred products, and review immutable transaction/price history.
- **Alternate flow:** use a non-preferred actual supplier on a purchase invoice without rewriting preference history.
- **Failure flow:** duplicate supplier, invalid status/terms, unsafe deletion, or history rewrite is blocked.
- **Permissions:** supplier view/create/edit; preferred change and logical delete sensitive.
- **Audit:** supplier status/terms and preferred-supplier changes with before/after values.
- **Relationships:** PRD PUR-01–PUR-02, NFR-01; UI UI-CAT-008; flows FLW-PUR-01–FLW-PUR-03; task TSK-013; acceptance AC-PUR-01–AC-PUR-02.

## US-010 — Manage Purchase Orders

- **Actor:** Purchasing Officer; **Goal/Title:** create and progress a purchase order through required states; **Business value:** controlled procurement and receipt visibility.
- **Preconditions:** active supplier/store/products; **Trigger:** replenishment is approved for ordering.
- **Main flow:** draft lines/terms, submit, receive partially or fully through referenced receipts, then close.
- **Alternate flow:** cancel under authority before disallowed states; amend draft.
- **Failure flow:** invalid transition, inactive supplier/product, non-positive quantity, over-receipt without policy, or unauthorized cancellation is blocked.
- **Permissions:** create/edit draft, submit, receive, cancel, close by role/scope.
- **Audit:** state transitions, line changes, receipt links, cancellation reason.
- **Relationships:** PRD PUR-03; UI UI-PUR-001; flow FLW-PUR-01; task TSK-014; acceptance AC-PUR-03.

## US-011 — Receive and Approve a Purchase Invoice

- **Actor:** Purchasing Officer and Warehouse Manager; **Goal/Title:** enter/import a purchase invoice, approve receipt into a selected store, and update weighted-average cost without changing sale price; **Business value:** accurate stock and cost.
- **Preconditions:** supplier/products/store active; approval separation configured; **Trigger:** goods/invoice arrive.
- **Main flow:** enter/import quantities, unit costs, discounts/tax, validate, review receipt, approve atomically, create stock movements/balances, calculate weighted-average cost, and retain source links.
- **Alternate flow:** partial receipt/purchase-order link or pricing-review notification without automatic price activation.
- **Failure flow:** invalid row, duplicate invoice, closed period/state, insufficient permission, concurrency conflict, or ambiguous store is blocked with no partial ledger effect.
- **Permissions:** purchasing entry/import; warehouse/purchasing approval per matrix; cost access scoped.
- **Audit:** source file/document, supplier, store, approval, costs, formula inputs/result, stock movements.
- **Relationships:** PRD PUR-04–PUR-05, PRC-03; UI UI-PUR-002; flow FLW-PUR-02; tasks TSK-015–TSK-016; acceptance AC-PUR-04–AC-PUR-05.

## US-012 — Return Stock to a Supplier

- **Actor:** Purchasing Officer; **Goal/Title:** issue an approved supplier return linked to an approved purchase-invoice line; **Business value:** accurate stock/cost/history correction.
- **Preconditions:** eligible stock and an approved purchase-invoice-line reference; **Trigger:** supplier return is authorized.
- **Main flow:** choose supplier/original approved invoice, select eligible quantities/reasons/store, validate available stock, approve, reduce stock through referenced movements at the source line cost, and print.
- **Alternate flow:** no unreferenced supplier-return path exists in Phase 1. Legacy/unknown stock must use a separately approved explicit-cost `adjustment_out` document.
- **Failure flow:** insufficient stock, wrong supplier/store, duplicate return, invalid state, missing approved source line, or direct stock edit is blocked.
- **Permissions:** create/approve/reverse/print within purchasing/warehouse scope.
- **Audit:** reference, quantities, cost basis, reason, approver, movement IDs.
- **Relationships:** PRD PUR-06; UI UI-PUR-003; flow FLW-PUR-03; task TSK-016; acceptance AC-PUR-06.

## US-013 — View Location Inventory Safely

- **Actor:** Warehouse Manager, Branch Manager, Cashier, or Reviewer; **Goal/Title:** view on-hand, available, in-transit, reserved, reorder, and movement history by item/store while enforcing sale-store rules; **Business value:** reliable service and replenishment.
- **Preconditions:** scoped access; **Trigger:** inventory/availability lookup.
- **Main flow:** search/filter product/location, show balances and movements, and identify branch selling store.
- **Alternate flow:** Cashier searches another branch for customer service without adding that stock to the cart.
- **Failure flow:** unauthorized store/cost field, unbounded query, or sale from non-assigned store is blocked.
- **Permissions:** role/branch/store/data-field scoped view/export.
- **Audit:** sensitive export and selling-store override/search as configured.
- **Relationships:** PRD INV-01–INV-02, NFR-05; UI UI-INV-001–UI-INV-003, UI-POS-001; flow FLW-INV-01; task TSK-019; acceptance AC-INV-01–AC-INV-02.

## US-014 — Transfer Stock Between Stores

- **Actor:** Warehouse Manager; **Goal/Title:** progress a transfer from request through dispatch and destination receipt with shortages/damage/refusal review; **Business value:** accountable in-transit inventory.
- **Preconditions:** authorized source/destination and available stock; **Trigger:** stock relocation is needed.
- **Main flow:** draft, submit, approve, dispatch atomically to in-transit, receive actual quantities, and close or open difference review.
- **Alternate flow:** partial receipt; separately record shortage, damage, or refusal and disposition.
- **Failure flow:** invalid transition, same source/destination, insufficient stock, duplicate dispatch/receipt, or unauthorized cross-scope action is blocked.
- **Permissions:** create/submit/approve/dispatch/receive/difference/cancel separated by scope.
- **Audit:** every state/quantity/location/difference/reason and source/destination actor.
- **Relationships:** PRD INV-03, NFR-01; UI UI-INV-004–UI-INV-007; flow FLW-INV-02; task TSK-020; acceptance AC-INV-03.

## US-015 — Record Controlled Inventory Documents

- **Actor:** Warehouse Manager; **Goal/Title:** create entry, exit, exchange/adjustment with reasons, quantities, fractional and negative-stock controls; **Business value:** traceable non-purchase/non-sale movements.
- **Preconditions:** active store/product and policy; **Trigger:** approved operational stock correction/movement.
- **Main flow:** choose document type/store/reason/items, validate quantity precision and balance, submit/approve, and create immutable movements.
- **Alternate flow:** authorized highlighted negative-stock override with mandatory reason where policy allows.
- **Failure flow:** missing reason, fractional quantity on disallowed product, negative stock without approval, stale balance, or direct balance edit is blocked.
- **Permissions:** create/edit draft/approve/reverse/override by store and limit.
- **Audit:** before/after balance, document, reason, override, approver, movement.
- **Relationships:** PRD INV-04–INV-06, NFR-01–NFR-02; UI UI-INV-011; flows FLW-INV-03–FLW-INV-05; task TSK-021; acceptance AC-INV-04–AC-INV-06.

## US-016 — Count Stock While Selling Continues

- **Actor:** Stock Counter and Warehouse Manager; **Goal/Title:** execute full/partial counts, repeated scans/manual counts, reconcile against movements during count, and review uncounted items without zeroing them; **Business value:** correct stock without closing operations.
- **Preconditions:** approved count scope and assigned counter; **Trigger:** count session starts.
- **Main flow:** capture reference balances, count by scan/manual input, record subsequent movements, repeat/verify discrepancies, submit, manager reconciles verified quantity against activity, and creates controlled adjustments.
- **Alternate flow:** partial scope by category/supplier/store; uncounted items move to review list requiring Counter and Warehouse Manager approval.
- **Failure flow:** counter approves own reconciliation, uncounted item becomes zero, stale movement snapshot, duplicate line, or unapproved adjustment is blocked.
- **Permissions:** Counter create/count/submit; Warehouse Manager review/approve; scope assignment enforced.
- **Audit:** scope, reference time/balance, every edit/recount, movements, discrepancy, approvals, adjustments.
- **Relationships:** PRD INV-07–INV-09, NFR-01; UI UI-INV-008–UI-INV-010; flows FLW-INV-06–FLW-INV-07; task TSK-022; acceptance AC-INV-07–AC-INV-09.

## US-017 — Complete a Branch POS Sale

- **Actor:** Cashier; **Goal/Title:** search/scan products, manage an authorized cart/customer, suspend/retrieve, pay, approve, and print from the assigned selling store; **Business value:** fast, controlled checkout.
- **Preconditions:** active authenticated shift, assigned branch/store/drawer, sellable priced stock; **Trigger:** customer presents items.
- **Main flow:** scan/search by barcode/code/name, add/adjust permitted quantities, select/register customer if needed, review totals, settle, approve once, create payment/stock movements, and print thermal/A4.
- **Alternate flow:** suspend and retrieve an own/authorized sale; search other-branch availability without selling it.
- **Failure flow:** unpriced item, wrong store, insufficient stock, unauthorized quantity, inactive shift, duplicate submit, or stale price is blocked without losing cart.
- **Permissions:** POS/create/quantity/customer/suspend/print and every exception separately enforced.
- **Audit:** cashier, branch, store, drawer, shift, customer, device, document number, payment, movement, and exceptions.
- **Relationships:** PRD POS-01–POS-02, INV-02, PRC-07; UI UI-POS-001–UI-POS-002, UI-POS-006–UI-POS-007; flows FLW-POS-01–FLW-POS-02; task TSK-023; acceptance AC-POS-01–AC-POS-02.

## US-018 — Apply Payment, Tax, Discount, and Print Rules

- **Actor:** Cashier; **Goal/Title:** record cash/manual electronic payment evidence, optionally apply invoice tax under authority, enforce one discount type, and print the required breakdown; **Business value:** accurate, reviewable settlement.
- **Preconditions:** valid cart and active shift; approved methods/tax/discount policy; **Trigger:** checkout settlement.
- **Main flow:** choose cash/electronic split as permitted, attach evidence for electronic payment, select tax if authorized, apply one eligible discount type, verify gross/discount/net/tax/final, and approve/print.
- **Alternate flow:** replace an existing discount after explicit confirmation; retry safe evidence upload before approval.
- **Failure flow:** stacked discount, missing/invalid evidence, unauthorized tax/discount, totals mismatch, or duplicate payment is blocked without losing cart.
- **Permissions:** payment method, tax, discount/override, evidence, approval, and print rights.
- **Audit:** methods/amounts, attachment, tax selection, discount source/value/replacement, totals, approver.
- **Relationships:** PRD POS-03–POS-06; UI UI-POS-001, UI-POS-007; flow FLW-POS-03; task TSK-024; acceptance AC-POS-03–AC-POS-06.

## US-019 — Issue and Use a Gift Receipt

- **Actor:** Cashier or authorized returns operator; **Goal/Title:** issue a price-free Gift Receipt and later identify its original sale without disclosing prices; **Business value:** gift-friendly returns with privacy and traceability.
- **Preconditions:** approved sale; **Trigger:** purchaser requests Gift Receipt or recipient presents one.
- **Main flow:** select eligible sale/lines, generate unique reference without price, print, and validate reference in return/exchange flow.
- **Alternate flow:** authorized reprint is visibly marked and audited.
- **Failure flow:** invalid/void/used/ineligible reference, price exposure, or unauthorized reprint is blocked.
- **Permissions:** issue/print/reprint/validate scoped to sale and return policy.
- **Audit:** issue/reprint/use, source sale/lines, operator, recipient-free metadata.
- **Relationships:** PRD POS-07, RET-01; UI UI-POS-010; flow FLW-POS-04; tasks TSK-029–TSK-030; acceptance AC-POS-07, AC-RET-01.

## US-020 — Return or Exchange Inspected Products

- **Actor:** Cashier and approving Branch Manager; **Goal/Title:** validate original invoice/Gift Receipt, inspect condition, then exchange same/different item, refund cash, or issue Gift Card; **Business value:** controlled customer resolution and stock integrity.
- **Preconditions:** valid reference, policy eligibility, active operator scope; **Trigger:** customer requests return/exchange.
- **Main flow:** validate source/eligible value, capture condition, obtain exception approval, choose settlement, create referenced return/exchange documents, update sellable/non-saleable stock, and print.
- **Alternate flow:** different-item exchange settles difference; rejected/damaged item follows separate disposition.
- **Failure flow:** invalid reference, over-return, prohibited condition, wrong settlement, insufficient approval, or direct original-sale edit is blocked.
- **Permissions:** create/inspect/refund/exchange/approve/reverse/print by limits.
- **Audit:** source, lines/value, condition/photos if policy, reason, settlement, approval, stock movements.
- **Relationships:** PRD RET-01–RET-03, NFR-02; UI UI-POS-008–UI-POS-009; flows FLW-RET-01–FLW-RET-02; task TSK-030; acceptance AC-RET-01–AC-RET-03.

## US-021 — Govern Gift Cards

- **Actor:** Cashier or authorized manager; **Goal/Title:** issue, check, redeem, and void a uniquely identified Gift Card with value/balance/validity/history; **Business value:** traceable non-cash settlement.
- **Preconditions:** approved gift-card policy and eligible source/use; **Trigger:** issue from return or other authorized source, or present for settlement.
- **Main flow:** generate unique identifier, set value/validity/holder/reference, post immutable ledger, redeem within balance, update balance atomically, and issue evidence.
- **Alternate flow:** partial redemption or authorized void of unused balance.
- **Failure flow:** duplicate ID, expired/void card, over-redemption, concurrent use, unauthorized issue/use/void, or direct balance edit is blocked.
- **Permissions:** issue/redeem/void/balance/history/print separately scoped.
- **Audit:** every ledger movement, source document, value, balance before/after, actor, approval.
- **Relationships:** PRD RET-04; UI UI-POS-011; flow FLW-RET-03; task TSK-029; acceptance AC-RET-04.

## US-022 — View Unified History With Separated Wallets

- **Actor:** Authorized customer-service, retail, party, or reviewer user; **Goal/Title:** view one customer identity/history while exposing only activity-authorized financial data; **Business value:** joined service without privacy or balance leakage.
- **Preconditions:** existing customer and purpose/scoped permission; **Trigger:** profile/history lookup.
- **Main flow:** show profile, source-linked sales/parties/payments/returns/gifts/points, then show Product or Party Wallet only to the permitted activity role.
- **Alternate flow:** Reviewer sees both only if explicitly delegated; restricted actor sees redacted tabs/fields.
- **Failure flow:** cross-wallet query/export, unsourced ledger row, unbounded history, or unauthorized sensitive field is blocked.
- **Permissions:** purpose, activity, branch, export, sensitive field, and wallet-specific policy.
- **Audit:** sensitive access/export and all wallet ledger activity.
- **Relationships:** PRD CUS-01–CUS-02, CUS-04, NFR-03; UI UI-CUS-002, UI-CUS-004–UI-CUS-005; flows FLW-CUS-04–FLW-CUS-05; task TSK-028; acceptance AC-CUS-01–AC-CUS-02, AC-CUS-04.

## US-023 — Earn and Redeem Shared Loyalty

- **Actor:** Authorized retail/party operator; **Goal/Title:** post shared loyalty earn/redeem/expiry movements using activity-specific rules and approval; **Business value:** one customer benefit across both activities.
- **Preconditions:** customer and active approved rules; eligible source document; **Trigger:** qualifying sale/party or redemption request.
- **Main flow:** calculate applicable activity rule, preview points, approve source, post referenced earn/redeem, and show resulting balance/expiry.
- **Alternate flow:** different retail/party earn rates; authorized correction through reference movement.
- **Failure flow:** offline redemption, insufficient/expired points, duplicate source, concurrent redemption, unauthorized adjustment, or direct balance edit is blocked.
- **Permissions:** view/earn/redeem/approve/adjust/export as separate rights.
- **Audit:** rule/version, source, points before/after, expiry, actor, approver, correction reference.
- **Relationships:** PRD CUS-03–CUS-04; UI UI-CUS-003; flows FLW-CUS-02–FLW-CUS-03; task TSK-027; acceptance AC-CUS-03–AC-CUS-04.

## US-024 — Open, Operate, and Blind-Close a Shift

- **Actor:** Cashier and Branch Manager/Reviewer; **Goal/Title:** open a drawer shift, link every POS/cash movement, submit unseen actual totals, calculate variance, review, and print closing outputs; **Business value:** accountable daily cash control.
- **Preconditions:** assigned available drawer and no conflicting shift; **Trigger:** workday start/end or permitted cash movement.
- **Main flow:** open with float, transact with automatic linkage, enter actual cash/electronic totals without expected values, submit, calculate variance, manager reviews/approves, print thermal and A4 reports.
- **Alternate flow:** authorized cash movement or variance escalation/recount before final approval.
- **Failure flow:** multiple active shifts/drawer conflicts, missing evidence, expected total leaked before submit, repeated close, mismatch without review, or unauthorized settlement is blocked.
- **Permissions:** own open/close and movements; expected/variance/review/export limited to manager/reviewer.
- **Audit:** drawer/shift/users, opening, transactions, actual/expected after submission, variance, review, closing outputs.
- **Relationships:** PRD CSH-01–CSH-04, NFR-01; UI UI-POS-003–UI-POS-005; flows FLW-CSH-01–FLW-CSH-03; task TSK-025; acceptance AC-CSH-01–AC-CSH-04.

## US-025 — Book a Party and Maintain Its Working Invoice

- **Actor:** Party Manager; **Goal/Title:** create a separate party booking and editable working invoice with customer/child, schedule/location, services, consumables, rentals, notes, and responsibilities; **Business value:** organized delivery without retail mixing.
- **Preconditions:** party masters/stores and customer available; **Trigger:** customer requests a party.
- **Main flow:** capture booking, check time/assets, add party-only lines, assign responsibilities, save working invoice, and amend under authority before close.
- **Alternate flow:** reschedule or adjust planned lines with conflict recheck and audit.
- **Failure flow:** retail line, wrong store, missing contact/time/location, asset conflict, unauthorized amendment, or post-close edit is blocked.
- **Permissions:** booking/working-invoice create/edit/reschedule/cancel/print by party scope.
- **Audit:** schedule, location, lines, responsibilities, state, and every change/reason.
- **Relationships:** PRD PTY-01–PTY-03; UI UI-PTY-001–UI-PTY-003; flows FLW-PTY-01–FLW-PTY-02; task TSK-031; acceptance AC-PTY-01–AC-PTY-03.

## US-026 — Record Party Payments and Final Settlement

- **Actor:** Party Manager; **Goal/Title:** record multiple payments on account with individual required receipts, then reconcile the final invoice, Party Wallet, balance/credit, and final receipt; **Business value:** accurate party finance independent of retail.
- **Preconditions:** open party invoice and approved payment/Party Wallet policy; **Trigger:** payment arrives or party is ready to close.
- **Main flow:** post each payment to the party invoice and print `Payment on Account for Party Invoice No. [number]`; at close validate operations, finalize invoice, reconcile payments, settle Party Wallet if authorized, collect/record remaining amount or credit, and print final receipt.
- **Alternate flow:** multiple methods/payments; credit remains in Party Wallet only when policy permits.
- **Failure flow:** Product Wallet access, over/duplicate payment, missing receipt, unreturned assets, open operational items, concurrency conflict, or post-close edit is blocked.
- **Permissions:** party payment/create/print/settle/final-close with approval limits.
- **Audit:** every payment, receipt, invoice values, Party Wallet movement, final balance/credit, approver.
- **Relationships:** PRD PTY-04, PTY-06, CUS-02; UI UI-PTY-004, UI-PTY-015; flows FLW-PTY-03, FLW-PTY-11; tasks TSK-032, TSK-036; acceptance AC-PTY-04, AC-PTY-06.

## US-027 — Execute a Party Operating Order

- **Actor:** Party Manager; **Goal/Title:** create an operating order, reserve services/assets, issue consumables, and control additions/removals until completion; **Business value:** accountable plan-to-actual party execution.
- **Preconditions:** confirmed booking/working invoice and available resources; **Trigger:** party preparation begins.
- **Main flow:** create referenced order, assign tasks/resources, reserve assets, issue party-store consumables, record controlled changes/actuals, and complete operations.
- **Alternate flow:** authorized addition/removal updates invoice/availability where applicable and records reason.
- **Failure flow:** retail stock, wrong party store, reservation conflict, insufficient consumables, post-completion edit, or unsourced movement is blocked.
- **Permissions:** create/edit/issue/return/complete/override by party/store scope.
- **Audit:** order version, line/resource changes, issue/return movements, assignments, reasons, states.
- **Relationships:** PRD PTY-05, AST-05; UI UI-PTY-005–UI-PTY-006; flows FLW-PTY-04–FLW-PTY-05; task TSK-033; acceptance AC-PTY-05, AC-AST-05.

## US-028 — Govern Rental Asset Lifecycle

- **Actor:** Party Manager; **Goal/Title:** maintain assets separately from consumables, reserve without overlap, check out/return, inspect, and record required states and history; **Business value:** available, accountable rental equipment.
- **Preconditions:** asset register/location/condition policies; party time interval; **Trigger:** asset setup or party allocation/return.
- **Main flow:** maintain unique asset, search calendar, reserve interval, record pre-condition/check-out, return into inspection, record post-condition, and transition to Available or exception state.
- **Alternate flow:** reschedule reservation, send to maintenance, retire, or record loss through approval.
- **Failure flow:** duplicate code, overlapping reservation, invalid state transition, wrong location, missing condition, or direct history edit is blocked.
- **Permissions:** asset master/reserve/checkout/return/inspect/status/override separately scoped.
- **Audit:** interval, party, locations, conditions, states, responsible user, approvals.
- **Relationships:** PRD AST-01–AST-03; UI UI-PTY-007–UI-PTY-012; flows FLW-PTY-06–FLW-PTY-08; task TSK-034; acceptance AC-AST-01–AC-AST-03.

## US-029 — Assess Asset Damage and Depreciation

- **Actor:** Party Manager and authorized approver; **Goal/Title:** record damage/depreciation/loss event, party reference, assessment, responsible user, optional cost impact, approval, and final asset state; **Business value:** accountable asset condition and financial history.
- **Preconditions:** returned/inspected asset or authorized event; **Trigger:** damage/depreciation/loss identified.
- **Main flow:** capture event/evidence/assessment/responsibility/cost where entered, submit, approve, post immutable history, and set final status.
- **Alternate flow:** route to maintenance, return to Available after approval, retire, or mark Lost.
- **Failure flow:** missing source/assessment, unauthorized cost/status, invalid transition, duplicate event, or deletion is blocked.
- **Permissions:** assess, cost-view/edit, approve, status transition, print/export separately scoped.
- **Audit:** before/after condition/status, event, party, evidence, cost, responsibility, approval.
- **Relationships:** PRD AST-04; UI UI-PTY-013–UI-PTY-014; flows FLW-PTY-09–FLW-PTY-10; task TSK-035; acceptance AC-AST-04.

## US-030 — Create a Non-Posting Quotation

- **Actor:** Authorized retail or party user; **Goal/Title:** create/print/share a typed quotation with customer, lines, prices, terms, notes, validity, and status without operational effect; **Business value:** communicate offers safely and preserve future conversion identity.
- **Preconditions:** authorized scope and valid customer/items; **Trigger:** customer requests an offer.
- **Main flow:** choose retail or party type, add compatible lines/terms/validity, save, issue, and print/share.
- **Alternate flow:** revise draft, expire, reject, or accept status without conversion.
- **Failure flow:** mixed activity lines, attempt to reserve/post stock/payment/wallet/accounting, unauthorized price, or Phase 1 conversion is blocked.
- **Permissions:** create/edit/issue/print/share/cancel by activity and scope.
- **Audit:** identity, type, version/status, terms, print/share, source references.
- **Relationships:** PRD QTN-01–QTN-03; UI UI-QTN-001; flow FLW-QTN-01; task TSK-037; acceptance AC-QTN-01–AC-QTN-03.

## US-031 — Review Dashboards, Alerts, Reports, and Exports

- **Actor:** Authorized manager, operational user, or Reviewer; **Goal/Title:** filter role-scoped KPIs/alerts/reports and export permitted PDF/Excel results; **Business value:** timely, reconciled management decisions.
- **Preconditions:** source transactions and report permission; **Trigger:** user opens dashboard/report or alert.
- **Main flow:** apply date/branch/store/user and module filters, view reconciled summaries/details, follow alert to source, and request permissioned export.
- **Alternate flow:** empty state explains filters/no data; long export is queued with status/expiry.
- **Failure flow:** unauthorized scope/field/export, stale/mismatched totals, formula ambiguity, excessive range, or unsafe spreadsheet content is blocked/reported.
- **Permissions:** report group, field, branch/store, margin/cost, export format, and audit independently checked.
- **Audit:** sensitive report/export request, filters, row count, artifact access/expiry.
- **Relationships:** PRD RPT-01–RPT-03, NFR-05; UI UI-ADM-001, UI-RPT-001–UI-RPT-002; flows FLW-RPT-01–FLW-RPT-02; tasks TSK-038–TSK-040; acceptance AC-RPT-01–AC-RPT-03.

## US-032 — Preserve Security, Audit, Integrity, and Safe Offline History

- **Actor:** System Administrator, Reviewer, and all transactional actors; **Goal/Title:** enforce server authorization, immutable history, rich audit, pagination, unique numbering, modular boundaries, secured attachments, and safe offline synchronization; **Business value:** trustworthy operations under concurrency and connectivity loss.
- **Preconditions:** configured security, scope, numbering, state, and offline policy; **Trigger:** sensitive/read/write/approve/offline/sync action.
- **Main flow:** authenticate, authorize, validate, lock/idempotently reserve as needed, perform atomic state/ledger changes, assign unique number, store authorized evidence, append audit, and return role-safe UI feedback.
- **Alternate flow:** queue permitted offline sale then revalidate/sync; create a conflict record where server truth differs; use referenced correction instead of edit.
- **Failure flow:** unauthorized scope, invalid state, duplicate request/number, race, unsafe attachment, expired offline session, prohibited offline operation, or unbounded query is blocked and logged.
- **Permissions:** server-side module/action/branch/store/activity/document/field/approval/override checks.
- **Audit:** actor/time/session/device/scope/source/reason/before-after and conflict/disposition.
- **Relationships:** PRD NFR-01–NFR-07 and offline cashier mode; UI UI-AUD-001, UI-OFF-001–UI-OFF-003, UI-SYS-001–UI-SYS-010; flows FLW-AUTH-01, FLW-RPT-03, FLW-OFF-01–FLW-OFF-03; tasks TSK-001–TSK-004, TSK-009, TSK-026, TSK-040; acceptance AC-NFR-01–AC-NFR-07.
