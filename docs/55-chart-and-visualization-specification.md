# 55 — Chart and Visualization Specification

**Product:** TOY & JOY
**Phase:** Phase 1
**Status:** Derived implementation specification — team-adopted defaults, owner approval outstanding
**Authority:** RPT-01, RPT-02, RPT-03, NFR-03, NFR-05
**Blockers:** BLK-017 (formulas, margin access, report layouts)
**Companion to:** `docs/50` (formula catalog), `docs/34` (dashboard/reporting specification), `docs/20` (design system)

---

## 1. Purpose

`docs/50` defines what each number means. This document defines which of those numbers are worth drawing, in what form, and under what constraints.

The default failure mode of ERP dashboards is decoration: many charts, no decisions. Every chart below earns its place by answering a question an operator actually asks.

---

## 2. Governing Rules

1. **Every chart is clickable.** A data point opens the filtered report behind it, carrying the same scope. A chart without drilldown is looked at once and ignored afterward.
2. **Three to four charts maximum per screen.** Everything else belongs in reports.
3. **Comparison over absolute value.** A figure without a prior-period reference is not information.
4. **Cost and margin charts are separately permissioned** (`docs/22` §247, `docs/04`). A role without margin access sees no margin chart — not a blanked one.
5. **Every chart reads an approved formula** from `docs/50`. An undefined figure is not charted.
6. **Bounded queries only** (NFR-05). A chart never triggers an unbounded scan; the date range is always constrained.
7. **Reuse before adding.** Try Flux Chart first. `docs/21` prohibits introducing a package that duplicates an existing capability; only if Flux and native rendering cannot serve the need does one mature chart library get evaluated.

---

## 3. Chart Catalog

Ordered by operational value, not visual appeal.

### 3.1 Sales trend
**Type:** line — daily or monthly
**Series:** current period + prior period comparison
**Source:** `docs/50` §3 net sales
**Question:** are we ahead or behind?
Without the comparison series this chart is decoration. The comparison is not optional.

### 3.2 Sales versus margin
**Type:** dual-axis column or grouped column
**Series:** net sales, gross margin
**Source:** `docs/50` §3, §4c
**Permission:** margin
**Question:** did we sell more and earn less?
The most valuable chart in the system. Divergence between the two series is invisible in any single-series view and is exactly the condition worth catching early.

### 3.3 Inventory ageing
**Type:** stacked column, **by value**
**Series:** 0–90, 91–180, 181–360, over 360 days
**Source:** `docs/50` §4b
**Permission:** cost
**Question:** how much capital is buried in stock that is not moving?
Plotted by value, never by quantity. Quantity does not hurt; money does.

### 3.4 Top products — revenue versus margin
**Type:** grouped horizontal bar, top 10
**Series:** rank by net sales, rank by gross margin
**Source:** `docs/50` §4c
**Permission:** margin
**Question:** which products should we restock?
The two rankings routinely differ. Presenting only the revenue ranking leads directly to restocking the products that sell most and earn least.

### 3.5 Payment method mix
**Type:** horizontal bar or donut
**Series:** cash, electronic by method
**Source:** `docs/50` §3, §6
**Question:** what is the cash-versus-electronic split for liquidity and settlement planning?

### 3.6 Branch performance
**Type:** horizontal bar
**Series:** net sales and margin per branch in one chart
**Source:** `docs/50` §3, §4c
**Permission:** margin series requires margin access; the sales series does not
**Question:** which branch is carrying the business?

### 3.7 Inventory value over time
**Type:** line
**Series:** stock valuation per month, optionally with net sales overlaid
**Source:** `docs/47` §6, `docs/50` §4
**Permission:** cost
**Question:** is working capital accumulating?
Flat sales with rising inventory value means cash is being trapped. That pattern is only visible over time.

### 3.8 Shift variance
**Type:** scatter or column, one mark per shift
**Series:** variance amount, coloured by threshold breach
**Source:** `docs/50` §6
**Permission:** manager/reviewer only — CSH-02 forbids exposing expected values to a cashier
**Question:** is a variance pattern concentrated on one cashier or drawer?
A scatter makes a recurring pattern obvious in a way a table of numbers does not.

---

## 4. Placement

| Surface | Charts |
|---|---|
| `UI-ADM-001` Dashboard | 3.1, 3.2 (if permitted), 3.3 (if permitted), plus one role-specific chart |
| `UI-RPT-001` Reports | All charts, as the visual header of their report group |
| Alerts | None — RPT-02 alerts are lists requiring action, not trends |

A dashboard chart is a summary with a link. The full-fidelity version lives on the report.

---

## 5. RTL Requirements

Arabic is the primary locale (`docs/20`). Chart libraries default to left-origin axes and Latin numerals.

1. Category axis reads right to left in Arabic; the axis origin mirrors.
2. Legends, tooltips, and axis labels follow the locale's direction.
3. Numbers and dates use locale formatting.
4. Sequential colour scales mirror with the axis so that "later" stays on the reading-end side.
5. Every chart is verified in both directions. An LTR-only chart in an Arabic-first product is a defect, not a cosmetic issue.

---

## 6. Accessibility and Fallback

1. Colour is never the sole carrier of meaning — pair it with labels, patterns, or ordering.
2. Every chart has an equivalent data table reachable from the same screen, which is also the print and export path (`docs/38`).
3. Empty, loading, error, and permission-denied states use the shared components (`docs/49` §7). A chart with no data renders the empty state with guidance, not an empty grid.
4. Charts are not printed as images where a table conveys the same information more legibly.

---

## 7. Performance

1. Aggregation happens in the database, never by loading rows into PHP and summing them.
2. Series length is capped; a daily series over a multi-year range rolls up to monthly automatically.
3. Every chart query is scoped and indexed on the same columns the underlying report uses.
4. A chart that cannot render inside the screen's loading budget is moved to the report and replaced on the dashboard with a summary figure.

---

## 8. Manual Browser Verification

Verify: every chart's values match the `docs/50` formula recomputed by hand; drilldown opens the filtered report with identical scope; a role without margin access sees no margin chart anywhere including page source; ageing renders by value not quantity; revenue and margin top-10 rankings differ where expected; shift variance is unreachable from a cashier session; every chart renders correctly in Arabic RTL and English LTR with mirrored axes and localised numerals; empty and error states render; a wide date range rolls up rather than rendering thousands of points; the equivalent data table is reachable and exports correctly.

No automated tests are created or executed.
