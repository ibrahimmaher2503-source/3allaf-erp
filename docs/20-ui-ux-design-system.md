# 20 — UI/UX Design System

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Approved implementation baseline  
**Technology:** Blade, Livewire, Flux UI, Tailwind CSS, Vite  
**Direction:** Arabic-first, bilingual, RTL/LTR, responsive, operationally efficient

---

## 1. Product Experience Principles

1. Operational clarity before decoration.
2. Arabic-first without weakening English LTR.
3. Server-driven interfaces with minimal client-side behavior.
4. Flux UI first; custom components only for documented gaps.
5. Fast keyboard, scanner, touch, and mouse workflows.
6. Clear state, scope, permission, and consequence.
7. No hidden destructive or irreversible behavior.
8. Dense data remains readable through hierarchy, spacing, and responsive adaptation.
9. Every screen supports loading, empty, success, validation, denied, and error states.
10. Print output is treated as a separate layout, not a screenshot of the screen.

---

## 2. Layouts

Approved application layouts:

- Authentication layout.
- Admin layout.
- Operations layout.
- Lightweight POS layout.
- Print layout.

### 2.1 Desktop

- Persistent sidebar where space permits.
- Header contains page title, current context, locale, user menu, and connectivity state where relevant.
- Content uses a centered, fluid container with safe maximum width for forms and wider data pages.

### 2.2 Mobile

- Sidebar becomes a drawer.
- Primary actions remain reachable without horizontal scrolling.
- Tables adapt to stacked rows, selective columns, or controlled horizontal regions only when unavoidable.
- Dialogs use near-full-screen presentation where appropriate.

---

## 3. Direction and Localization

1. Document direction follows active locale.
2. Use logical CSS properties, not hard-coded left/right.
3. Icons that imply direction mirror where semantically required.
4. Numbers, money, codes, and barcodes remain readable in both locales.
5. Arabic labels are primary where the workflow is Arabic-first.
6. Validation and system messages are localized.
7. Avoid mixed-direction punctuation bugs.
8. Test long Arabic and English text expansion.

---

## 4. Typography

- Use the approved/system Arabic-capable font stack until brand fonts are supplied.
- Page title: clear and prominent, one per page.
- Section heading: concise and action-oriented.
- Body text: readable at normal browser zoom.
- Helper text: secondary but never too faint.
- Codes, UUID fragments, and technical references may use a monospace fallback.
- Do not use decorative display fonts in operational screens.

---

## 5. Color and Semantic Tokens

Use semantic tokens rather than hard-coded component colors:

- `surface`
- `surface-muted`
- `text`
- `text-muted`
- `border`
- `accent`
- `success`
- `warning`
- `danger`
- `info`
- `focus`

Color must not be the only carrier of meaning. Pair status colors with labels/icons.

The current teal-accent light-first foundation remains a local baseline until approved brand assets replace it.

---

## 6. Spacing and Density

- Use a consistent spacing scale.
- Forms group related fields into clear sections.
- Avoid excessive empty space in operational tables.
- Avoid cramped cards with multiple unrelated concepts.
- Critical actions have sufficient separation from neutral actions.
- Mobile touch targets must remain usable.
- Repeated patterns should align labels and controls consistently.

---

## 7. Page Anatomy

Standard full page:

1. Breadcrumbs where hierarchy benefits.
2. Page title and short context.
3. Primary action area.
4. Filters/search where relevant.
5. Main content.
6. Pagination or safe loading.
7. Empty/error states.
8. Contextual help only where needed.

---

## 8. Forms

1. Use Flux controls first.
2. Every input has a visible label.
3. Required state is clear.
4. Validation appears near the field and in a summary when the form is long.
5. Preserve entered values after validation failure.
6. Disable or explain fields unavailable due to state or permission.
7. Sensitive fields may be hidden entirely based on permission.
8. Use searchable selects/comboboxes for large master sets.
9. Use confirmation dialogs for irreversible state changes.
10. Unsaved-change protection is used for long/critical forms where practical.

---

## 9. Tables and Lists

All high-volume lists must provide:

- Server-side scope enforcement.
- Search.
- Relevant filters.
- Sort where meaningful.
- Pagination or approved safe loading.
- Clear empty state.
- Loading state.
- Row-level status.
- Permission-aware actions.
- Responsive behavior.

Avoid placing every possible field in one table. Use detail drawers/pages for secondary data.

---

## 10. Status and Workflow Presentation

Use canonical badges and timeline patterns.

Examples:

- Draft.
- Submitted.
- Approved.
- Rejected.
- Cancelled.
- Reversed.
- In transit.
- Partially received.
- Difference review.
- Active/inactive.
- Pending pricing.
- Online/offline/conflict.

State-changing actions must show their consequence before confirmation.

---

## 11. Dialogs, Drawers, and Notifications

- Dialog: confirmation or focused small form.
- Drawer: contextual detail or secondary workflow without losing list context.
- Full page: complex create/edit/approval workflows.
- Toast: brief success/info only.
- Alert: persistent warning/error/action requirement.
- Never rely on toast alone for critical failure.
- Trap focus correctly and return it on close.
- Escape/cancel behavior must not discard data silently.

---

## 12. Navigation and Permissions

1. Navigation is generated from current authorization.
2. Hidden navigation does not replace server-side denial.
3. Current branch/store/activity context is visible.
4. Retail and party operations remain visually and structurally separated.
5. Cashier, Party Manager, Reviewer, and Stock Counter boundaries must be obvious.
6. Direct denied routes show a safe localized denied screen.

---

## 13. Accessibility

Minimum expectations:

- Keyboard navigable.
- Visible focus.
- Semantic labels and headings.
- Sufficient contrast.
- Accessible dialog focus behavior.
- Errors associated with fields.
- Status not conveyed by color alone.
- Touch targets usable.
- Content works at browser zoom and text expansion.
- Screen-reader-friendly names for icon-only actions.

---

## 14. Loading, Empty, Error, and Offline States

Every interactive screen must define:

- Initial loading.
- Action loading.
- Empty result.
- No permission.
- Validation error.
- Server error.
- Stale/conflict state.
- Offline state where applicable.
- Retry behavior.
- Safe preservation of unsaved data where practical.

---

## 15. POS-Specific UX

- Dedicated lightweight layout.
- Search focused for scanner/keyboard.
- Immediate product feedback.
- Large, clear totals.
- Payment and confirmation separated.
- Stale price/stock conflicts shown clearly.
- Restricted offline limitations persistent and visible.
- Do not expose expected shift close before cashier submission.
- Thermal and A4 outputs use dedicated print templates.

---

## 16. Print Design

Supported families:

- Thermal receipt.
- A4 document/report.
- Barcode/label.
- PDF/Excel export summary.

Rules:

- Dedicated print CSS/template.
- No navigation or interactive controls.
- Correct Arabic shaping and RTL.
- Stable page breaks.
- Document number, date, source, and status visible.
- Draft/unapproved prints marked where applicable.
- Sensitive fields follow print permission.
- Browser print preview is manually verified.

---

## 17. Component Governance

Before creating a custom component:

1. Check Flux capability.
2. Check existing shared pattern.
3. Record why the existing capability is insufficient.
4. Build the smallest reusable extension.
5. Document states and accessibility.
6. Avoid duplicating third-party controls.

---

## 18. Manual Browser Verification Matrix

For each affected screen verify:

- Arabic RTL and English LTR.
- Desktop and mobile.
- Keyboard and touch where applicable.
- Loading, empty, validation, denied, error, and success states.
- Long text and data density.
- No horizontal page overflow.
- Correct focus behavior.
- Console has no unexpected errors.
- Network has no failed/unexpected requests.
- Print preview where applicable.

No automated tests are created or executed under the current project directive.
