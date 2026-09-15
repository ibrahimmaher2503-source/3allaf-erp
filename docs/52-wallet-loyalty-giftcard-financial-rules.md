# 52 — Wallet, Loyalty, and Gift Card Financial Rules

**Product:** TOY & JOY
**Phase:** Phase 1
**Status:** Derived implementation policy — team-adopted defaults, owner approval outstanding
**Authority:** CUS-01–CUS-04, RET-02, RET-04, NFR-01, NFR-02
**Blockers:** BLK-014 — legal consent/retention wording, loyalty rates, expiry/rounding, wallet limits, gift card financial values
**Companion to:** `docs/27` (customer, loyalty, wallet, gift policy)

---

## 1. Purpose

`docs/27` defines behaviour and separation. This document defines the arithmetic: how points round, how expiry consumes, how a wallet settles, and how a gift card's outstanding value is tracked.

These are money-like ledgers. The same append-only discipline as `stock_movements` applies.

---

## 2. Non-Negotiable Separation

CUS-02 is absolute: Product Wallet and Party Wallet are stored, displayed, authorised, settled, and reported separately. A retail cashier must not view Party Wallet balance or debt, and a Party Manager must not view Product Wallet balance or debt.

Implementation consequences:

1. Two ledgers, never one table with an activity column that a query might forget to filter.
2. No combined total exists anywhere — not on a dashboard, not in an export column, not in an API response.
3. Authorization is checked per ledger at the action layer (NFR-03).
4. Loyalty is the **only** shared balance (CUS-03), and it is points, not money.

---

## 3. Loyalty Points

| Rule | Proposed default | Key |
|---|---|---|
| Earn basis | Net sales after discount, before tax | LY-01 |
| Earn rate | Configurable per activity; retail and party may differ (CUS-03) | LY-02 |
| Point rounding on earn | Round down to whole points | LY-03 |
| Redemption value | Configurable points-to-currency rate | LY-04 |
| Rounding on redeem | Round down in the customer's favour on value, never up on points consumed | LY-05 |
| Expiry | Configurable; FIFO — oldest points expire first | LY-06 |
| Redemption consumption order | FIFO, matching expiry order | LY-06 |
| Redemption on a returned sale | Points earned are reversed; points already redeemed are not clawed back automatically — the case goes to approval | LY-07 |
| Manual adjustment | Requires permission, reason, and audit (`docs/04`) | LY-08 |

Rounding direction matters: rounding earn up and redeem up creates points from nothing across thousands of transactions. Round down on both.

The points ledger is append-only. A balance is the sum of its entries and must be reproducible from them.

---

## 4. Wallets

Each wallet is an append-only ledger of entries carrying: source document, direction, amount, actor, timestamp, and reason where required.

| Rule | Proposed default |
|---|---|
| Entry sources | Sale settlement, refund, party payment on account, approved manual adjustment |
| Negative balance (debt) | Permitted only where the owner enables credit; blocked by default |
| Credit limit | Owner-configurable per customer; zero by default |
| Settlement | Explicit document; never an implicit balance edit |
| Offline | Blocked entirely (`docs/51` §3) |
| Manual adjustment | Permission, reason, approval, audit — never a direct edit (`docs/04`) |

A wallet balance is never stored as an editable field. It is derived from the ledger, and a reconciliation must be able to prove the derived balance matches.

---

## 5. Gift Cards

RET-04 requires unique identifier, value, balance, issue/use/void history, validity period, holder or reference where applicable, and permission-controlled use.

| Rule | Proposed default |
|---|---|
| Issue sources | Retail sale of a card, or return settlement per RET-02 |
| Return-issued value | Equal to the eligible return value, computed per `docs/26` |
| Partial redemption | Permitted; remaining balance stays on the card |
| Expiry | Configurable validity period; expiry writes a ledger entry, never a silent zeroing |
| Void | Requires permission and reason; preserves history (NFR-02) |
| Outstanding liability | Σ unexpired unredeemed balances — reported as a distinct figure |
| Offline use | Blocked |
| Reissue | Prohibited. A lost card is voided and a new one issued, both referenced |

A gift card issued as return settlement is not revenue. It is an outstanding obligation, and the liability figure in §4 of `docs/50` must reflect it.

---

## 6. Audit

NFR-01 lists wallet and loyalty use among mandatory audit events. Every entry in all three ledgers writes an audit record in the same transaction, carrying before/after balance, source document, actor, and reason where required.

No role edits a ledger entry directly (`docs/04`, Explicit Boundaries).

---

## 7. Verification boundary

Verify: a retail cashier cannot reach Party Wallet balance by any route including direct URL and export; a Party Manager cannot reach Product Wallet balance; no screen or export shows a combined wallet total; points earn and redeem round down; expiry consumes oldest first; a returned sale reverses earned points and routes already-redeemed points to approval; a wallet balance derived from entries matches the displayed balance; credit blocked when disabled; gift card partial redemption leaves the correct remainder; expiry writes an entry rather than zeroing; void preserves history; outstanding liability reconciles to unredeemed balances; every operation audited once; RTL/LTR and mobile clean.

TSK-027 closure evidence covers the customer and retail-loyalty rows in this document. Wallet, gift-card, return, party-payment, and production reconciliation rows remain outside TSK-027 and are not claimed as implemented.
