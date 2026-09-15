# 51 — Offline POS Operating Policy

**Product:** TOY & JOY
**Phase:** Phase 1
**Status:** Derived implementation policy — team-adopted defaults, owner approval outstanding
**Authority:** POS-01, POS-03, Offline Cashier Mode, NFR-04
**Blockers:** BLK-004 (Open) — enabled branches/devices, limits, evidence rules, price age, expiry, retry, review ownership, conflict disposition

---

## 1. Purpose

The PRD's Offline Cashier Mode paragraph states what offline may and may not do. BLK-004 records that every operating value behind it is missing. This document supplies defaults so TSK-026 can be built, and marks each one as owner-configurable.

The governing posture from `docs/02`: offline is **restricted**, and on reconnection **server values prevail**.

---

## 2. Requirement Classification

| Item | Classification |
|---|---|
| Offline accepts only cash or manually recorded electronic payment | PRD Requirement |
| Offline blocks credit sales, wallets, loyalty redemption, special discounts | PRD Requirement |
| Offline blocks any operation likely to create a balance or price conflict | PRD Requirement |
| Server values prevail on reconnection for stock, price, wallet, loyalty | PRD Requirement |
| Every conflict queued for review | PRD Requirement |
| Safe synchronisation after offline operation | PRD Requirement (NFR-04) |
| Enabled branches and devices | **PENDING — OFF-01** |
| Duration, amount, and transaction limits | **PENDING — OFF-02** |
| Permitted price age | **PENDING — OFF-03** |
| Queue expiry and retry | **PENDING — OFF-04** |
| Conflict review ownership and disposition | **PENDING — OFF-05** |

---

## 3. Permitted and Blocked Offline

| Permitted | Blocked |
|---|---|
| Cash sale | Credit sale |
| Manually recorded electronic payment with evidence | Product Wallet or Party Wallet use |
| Standard approved price | Loyalty redemption |
| Item discount within configured limit | Special or approval-requiring discount |
| Product search from the local cache | Open price |
| Suspended sale held locally | Return or exchange |
| | Negative-stock override |
| | Any party operation |

Loyalty **earning** is deferred rather than blocked: the sale records its eligibility, and points post on synchronisation. Redemption is blocked because it needs a balance the device cannot trust.

---

## 4. Operating Limits

Owner-configurable via `docs/46`; proposed defaults:

| Limit | Default | Key |
|---|---|---|
| Enabled scope | Explicit opt-in per branch and per device; off by default | OFF-01 |
| Maximum offline duration | 4 hours, then the device blocks new sales | OFF-02 |
| Maximum queued transactions | 50 | OFF-02 |
| Maximum value per offline transaction | 5,000 EGP | OFF-02 |
| Maximum cumulative offline value | 25,000 EGP | OFF-02 |
| Maximum price cache age | 24 hours; older cache blocks selling | OFF-03 |
| Queue expiry | 72 hours; expired entries go to review, never auto-post | OFF-04 |

When any limit is reached the POS stops accepting new offline sales and displays the reason. It does not degrade silently.

---

## 5. Local Data

1. Local records are **provisional** and visibly marked as such until confirmed by the server.
2. A provisional record has no approved document number. Numbering is allocated server-side on synchronisation (NFR-06), because a device cannot guarantee sequence uniqueness.
3. The local receipt is marked provisional and carries the device reference, not a document number.
4. Payment evidence images queue locally and upload through `docs/18` private storage on reconnection.
5. No cost, margin, wallet balance, or expected-cash figure is cached on the device.

---

## 6. Synchronisation

```
reconnect → authenticate → replay queue in original order
         → server validates each entry independently
         → accepted: allocate number, post stock and payments, audit
         → conflicted: reject, queue for review, keep the local record intact
```

Replay is idempotent by device transaction key. A repeated sync never posts twice.

Server truth prevails for stock, price, wallet, and loyalty. A local sale at a stale price is not re-priced automatically — it goes to review, because silently changing what a customer already paid is worse than an exception queue.

---

## 7. Conflict Review

Every conflict records: device, cashier, local timestamp, server timestamp, conflicting field, local value, server value.

Dispositions: accept as posted, accept with correction document, or reject with reason. All three write audit events. Ownership defaults to Branch Manager and is owner-configurable (OFF-05).

A conflict is never auto-resolved. `docs/04` already scopes Offline Queue & Conflicts with disposition as an `R` capability.

---

## 8. Manual Browser Verification

Verify: offline disabled by default on a new device; each blocked operation refuses with a clear reason; each limit stops new sales at its boundary; stale price cache blocks selling; provisional records are visibly marked and carry no document number; numbers allocate only on sync and stay unique under a concurrent sync; repeated sync posts once; a stale-price sale reaches review rather than re-pricing; conflict disposition requires permission and reason; expired queue entries never auto-post; RTL/LTR and mobile clean.

No automated tests are created or executed.
