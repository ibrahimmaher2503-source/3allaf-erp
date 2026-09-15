# 37 — UI Screen Specifications

## TSK-004B Shared Screen Guidance

Registered screens may expose a deterministic Page Guide with localized purpose, approved actions, flow references, permission filtering, safe fallback, and stable tour selectors. Actual route mappings and initial coverage are recorded in `docs/40-contextual-page-guide-specification.md` and `.ai/UI_SCREENS.md`.

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Detailed implementation specification derived from the approved PRD, Implementation Plan, Architecture, Roles/Permissions, and policies 17–29  
**Authority order:** PRD functional behavior → Implementation Plan sequencing/phase gates → approved decisions/policies → this specification  
**Important:** Exact production master data, legal wording, hardware models, provider choices, and final numeric limits remain configurable where the source documents do not define them.

---


## 1. Purpose

Define a screen-level contract for the full Phase 1 application. This supplements `.ai/UI_SCREENS.md`; existing canonical screen IDs remain authoritative and should be mapped rather than duplicated.

## 2. Screen Contract Template

Every screen entry shall define:

- Screen ID.
- Route/name.
- Module/layout.
- Purpose.
- Roles/permissions.
- Branch/store/activity scope.
- Data source.
- Page header/actions.
- Fields/columns.
- Filters/search/sort.
- Row/bulk actions.
- States.
- Validation.
- Dialogs/drawers.
- Empty/loading/error/denied.
- Responsive behavior.
- RTL/LTR.
- Print/export.
- Manual scenarios.

## 3. Global Layouts

### Auth Layout

Login, forgot/reset. Minimal, secure, no application data.

### Admin Layout

Settings, users, roles, branches, governance.

### Operations Layout

Catalog, purchasing, inventory, customers, parties, assets, reports, audit.

### POS Layout

Low-overhead scanner/cart/totals/payment shell.

### Print Layout

No navigation, dedicated pagination and direction.

## 4. Shared Components

- Sidebar/header.
- Context selector.
- Breadcrumb/page header.
- Filter bar.
- Server table/pagination.
- Form sections.
- Searchable select.
- Status badge.
- Timeline.
- Approval panel.
- Audit panel.
- Attachment uploader/preview.
- Confirmation dialog.
- Toast/alert.
- Loading/empty/error/denied.
- Print preview.

## 5. Platform Screens

- Login/reset/profile/sessions.
- Company settings.
- Branches list/create/edit/detail.
- Stores list/create/edit/detail.
- Selling-store mapping/history.
- Drawers list/create/edit/detail.
- Payment methods.
- Tax settings.
- Number sequences.
- Printers/templates.
- Users list/detail/authorization modal.
- Roles/permissions matrix.
- Approval inbox/detail.
- Audit list/detail.
- Health/backup status if enabled.

## 6. Catalog/Purchasing/Pricing Screens

- Product list.
- Product create/edit/detail.
- Category tree.
- Brand list.
- Barcode allocation/search.
- Product import stepper/error report.
- Supplier list/detail/form/history.
- Purchase order list/editor/detail.
- Purchase invoice manual/import/detail/approval.
- Supplier return editor/detail.
- Price proposal/list/version/diff/approval.
- Label queue/print/reprint.

## 7. Inventory Screens

- Inventory overview.
- Product stock card.
- Movement list.
- Transfer list/editor/dispatch/receipt/difference review.
- Adjustment list/editor/detail.
- Stock count list/setup/count entry/recount/reconciliation.
- Uncounted review.
- Low/zero/reorder/unpriced operational lists.

## 8. POS and Returns Screens

- POS checkout.
- Suspended sales drawer/list.
- Payment drawer.
- Approved sale detail.
- Thermal/A4 print.
- Gift Receipt issue/reprint.
- Return/exchange reference lookup.
- Inspection.
- Settlement.
- Return/exchange detail.

## 9. Cash Control Screens

- Open shift.
- Active shift summary.
- Cash movement entry/history.
- Blind close.
- Submitted confirmation.
- Manager variance review.
- Closed shift detail/print.

## 10. Customer Screens

- Customer list/search.
- Profile create/edit/detail.
- Children/consent tab.
- Unified history.
- Loyalty ledger.
- Product Wallet.
- Party Wallet.
- Gift Card list/detail/issue/use.

Sensitive tabs hidden and server-denied by role.

## 11. Party Screens

- Booking list/calendar.
- Booking editor/detail.
- Working invoice editor.
- Payments on account.
- Operating order editor/execution.
- Consumable issue/actual/return.
- Asset list/detail/calendar/reservation.
- Checkout/return/inspection.
- Damage/depreciation review.
- Final readiness.
- Final settlement.
- Final invoice/receipt.

## 12. Quotations

- Quotation list/editor/detail/print.
- Activity type determines retail/party line visibility.
- No conversion action in Phase 1.

## 13. Reporting Screens

- Dashboard.
- Alerts.
- Report catalog.
- Report result/detail.
- Export queue/download.
- Audit/report governance.

## 14. Standard List Behavior

- Scoped server query.
- Search.
- Relevant filters.
- Sort.
- Pagination.
- Column priority.
- Saved filters only if explicitly implemented.
- Empty and error states.
- Export permission separate.
- Row actions permission/state aware.

## 15. Standard Form Behavior

- Visible labels.
- Localized validation.
- Required indicators.
- Preserve input on error.
- Bilingual fields grouped.
- Permission/state disabled explanation.
- Unsaved change warning for long forms.
- Full page for complex documents.
- Confirmation for irreversible transitions.

## 16. Responsive Rules

Desktop:

- Sidebar persistent.
- Multi-column forms where related.
- Wide tables with priority columns.

Mobile:

- Sidebar drawer.
- Single-column forms.
- Actions reachable.
- Tables stack or use detail cards/drawers.
- No page-level horizontal overflow.
- Dialogs near full-screen where needed.

## 17. RTL/LTR

- Logical spacing.
- Correct direction per locale.
- Directional icons mirror where semantic.
- Codes/barcodes/numbers remain readable.
- Print verified in both directions.

## 18. Accessibility

- Keyboard navigation.
- Visible focus.
- Proper labels/headings.
- Dialog focus trap/return.
- Error associations.
- Contrast.
- No color-only status.
- Accessible icon names.
- Touch targets.

## 19. Manual Screen Review Matrix

For every implemented screen verify:

- Allowed role.
- Denied role/direct URL.
- Scope.
- Normal flow.
- Validation.
- Loading/empty/error.
- State actions.
- Stale/double action where applicable.
- Desktop/mobile.
- RTL/LTR.
- Console/network.
- Print/export where applicable.

No automated browser scripts under current directive.
