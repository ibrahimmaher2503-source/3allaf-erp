# 57 — UI Interaction and Data Entry Standard

**Product:** TOY & JOY
**Phase:** Phase 1
**Status:** Derived implementation standard — team-adopted defaults, owner approval outstanding
**Authority:** POS-01, NFR-02, NFR-03, NFR-05, NFR-06
**Companion to:** `docs/20` (design system: tokens, layout, typography), `docs/37` (per-screen specifications), `docs/49` (validation and error contracts), `docs/56` (tables at scale), `.ai/UI_SCREENS.md` (screen registry)

**Boundary:** `docs/20` defines what the interface looks like. `docs/37` defines what each screen contains. This document defines how the interface **behaves under a working operator** — focus, keyboard, data entry, error prevention, and locale behaviour beyond direction. It adds no tokens, no layouts, and no screens.

---

## 1. Why This Layer Exists

The people using this system are not browsing. A warehouse clerk enters 200 lines against a delivery. A cashier serves a queue. Both work at speed, mostly by keyboard and scanner, on the same three screens all day.

At that usage pattern, two seconds of friction per line is not a polish issue — it is hours per week. Most of what follows costs nothing to build correctly the first time and is expensive to retrofit once the screens exist.

---

## 2. Keyboard and Scanner First

Line-entry screens must be completable without a mouse. This applies to purchase invoice lines, transfer dispatch and receipt, count entry, adjustments, and POS.

| Key | Behaviour |
|---|---|
| `Enter` | Commit the current line and return focus to the entry field |
| `Esc` | Cancel the current line, keeping already-entered lines |
| `Tab` / `Shift+Tab` | Move within the line in visual order — which mirrors in RTL |
| Arrow up/down | Move between committed lines |
| `/` | Focus the search or barcode field from anywhere on the screen |
| `Ctrl+Enter` | Submit the document, subject to confirmation rules in §6 |

Rules:

1. **The focus loop is the design.** After a line is committed, focus returns to the barcode or search field automatically. Missing this costs two seconds per line, every line.
2. **Scanner input is keyboard input** that arrives fast and ends with a terminator. The field must tolerate an entire code arriving between two renders, and must not fire a live search per character. Debounce alone is insufficient; treat the terminator as the commit signal.
3. **Visible focus at all times.** An operator working by keyboard who cannot see focus is lost.
4. **No keyboard trap.** Every dialog returns focus to its trigger on close (`docs/20` §11).
5. **Shortcuts are discoverable** — a `?` overlay listing them, not tribal knowledge.

---

## 3. Data Entry Behaviour

### 3.1 Drafts

Long documents autosave as drafts. A connection drop halfway through an 80-line invoice must not discard the work.

- Autosave on line commit, not on a timer mid-typing.
- Draft state is visible: "saved a moment ago", not silent.
- Reopening a draft restores the lines and the entry position.

### 3.2 Duplicate submission

Approval and submit actions carry both protections:

1. The control disables on first activation and shows progress.
2. The request carries an idempotency key, so a double click, a refresh, or a retry cannot post twice.

The first is courtesy. The second is the actual protection, because the client cannot be trusted (NFR-03, `docs/47` §5).

### 3.3 Numeric input

- Quantity fields reject non-numeric input rather than accepting and failing on save.
- Fractional entry is permitted only where the product allows it (INV-06); otherwise the field constrains to whole numbers at entry time, not at validation time.
- Money fields display grouped, store unrounded, and never use a spinner control — spinners are a source of accidental single-unit errors.

### 3.4 Selection

- Product and supplier selection uses a combobox with server-side search, not a full dropdown. A select element with 50,000 options is unusable and slow.
- Recently used values appear first where the workflow repeats.
- The selection displays enough to disambiguate: code, name, and where relevant the current balance.

---

## 4. Feedback and Perceived Speed

| Rule | Reason |
|---|---|
| Skeleton matching the final shape, not a spinner | Reduces perceived wait and prevents layout shift |
| `wire:loading.delay` on operations under 200ms | Prevents flicker that reads as instability |
| Optimistic UI only for reversible, non-financial actions | Never for anything that moves stock or money |
| Success confirms **what** happened, with the resulting document number | "Saved" tells the operator nothing they can act on |
| Progress for anything above a few seconds, with a cancel where safe | Silence reads as failure |

Optimistic UI is explicitly prohibited on posting actions. Showing a stock movement as complete before the server confirms it, then reverting, is worse than a two-second wait.

---

## 5. Error Prevention

Preventing an error is worth more than reporting one.

1. **Show the impact before the action.** Before approval: "This will add 120 items to Obour warehouse and update the average cost of 34 products." An operator catches a wrong-store selection here, not after posting.
2. **Confirmation proportional to consequence.** A dismissible dialog for ordinary actions; typing the document number for irreversible ones such as reversing an approved invoice. "Are you sure?" is clicked through without reading.
3. **Locked fields explain themselves.** A greyed field with no reason reads as a bug. "Locked after approval" or "Locked at cutover on 12 May" reads as a rule (`docs/46` §4).
4. **Unsaved-change warning** on navigation away from a dirty long form.
5. **Destructive actions are never adjacent to routine ones.** Delete does not sit beside Save in the same visual group.

---

## 6. Error Messages

Per `docs/49`, messages resolve from `lang/ar.json` and `lang/en.json` — never hard-coded in a PHP action.

Content rules:

- **State the resolution, not just the failure.** "This store is outside your scope — ask your branch manager for access" beats "Unauthorized".
- **Never reveal record existence** in an authorization denial (`docs/49` §3).
- **Show the request ID** on system errors so a support report maps to an audit row.
- **Preserve safe input** on a recoverable error. Losing 80 entered lines to a validation failure is a defect.
- **Field errors appear at the field**, with a summary only when errors are off-screen.

---

## 7. Arabic and Bilingual Behaviour

Direction is covered in `docs/20` §3. These are the behaviours that direction alone does not solve:

1. **Numbers stay LTR inside Arabic text.** Invoice numbers, barcodes, item codes, and quantities do not reverse.
2. **Mixed-content fields use `dir="auto"`** at field level, so a product name containing both scripts renders correctly.
3. **Directional icons mirror; meaning icons do not.** Next, previous, and arrows mirror. Printer, clock, and warning do not.
4. **Text expansion of 20–30%** — Arabic labels run longer than English. Fixed-width controls clip; controls size to content.
5. **Locale number and date formatting**, applied consistently on screen, in print, and in export.
6. **Both locale files stay complete.** A missing key that falls back to the other language is a defect, not a cosmetic gap.
7. **Sort order for Arabic text** uses the correct collation (`docs/56` §2.1), or lists appear randomly ordered to an Arabic reader.

---

## 8. Role-Aware Interface

`docs/04` requires server-side enforcement, and UI visibility mirrors rights without replacing them (NFR-03).

1. A capability the user does not hold is **absent**, not present and disabled. A disabled control implies "ask for it"; absence is correct where the role will never hold it.
2. A control that is disabled **by state** rather than by permission stays visible with its reason — "Cannot edit after approval".
3. Cost and margin fields are absent for roles without that grant, not blanked. A blank is indistinguishable from a real zero (`docs/50` §2, `docs/55` §2).
4. Denials render the shared denied state without leaking record existence (`docs/49` §7).

---

## 9. POS-Specific Behaviour

Extends `docs/20` §15.

1. Search holds focus by default; scanning never requires a click first.
2. Cart, totals, and customer context stay visible at every viewport (screen contract, `.ai/UI_SCREENS.md`).
3. Payment entry is keyboard-completable, including split payments.
4. Change due is prominent and unambiguous.
5. Offline state is persistently visible, and provisional records are marked as such (`docs/51` §5).
6. Expected cash values are never present in the page, including in the source, before shift submission (CSH-02).

---

## 10. What Not To Do

- No optimistic UI on posting.
- No client-only validation for a business rule.
- No permission enforced by hiding a control alone.
- No hard-coded user-facing string in PHP.
- No modal inside a modal.
- No irreversible action behind a single unconfirmed click.
- No spinner where a skeleton is possible.
- No unbounded select element.

---

## 11. Manual Browser Verification

Verify: a 20-line document is completable entirely by keyboard, with focus returning to the entry field after every line; a scanned code arriving in one burst is handled without a per-character search; a draft survives a mid-entry connection loss and restores the entry position; a double-click on approve posts once; a whole-quantity product rejects fractional entry at the field; the impact preview shows the correct store and product count before approval; a locked field states its reason; an authorization denial reveals no record existence; a validation failure preserves entered lines; both locale files resolve every visible string; numbers and barcodes stay LTR in Arabic; directional icons mirror and meaning icons do not; Arabic sort order is correct; a role without margin sees no margin field anywhere including page source; expected cash is unreachable from a cashier session before submission; every dialog returns focus on close.

No automated tests are created or executed.
