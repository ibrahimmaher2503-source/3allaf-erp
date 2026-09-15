# 27 — Customer, Loyalty, Wallet, and Gift Instrument Policy

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Derived implementation policy based on the approved PRD  
**Authority:** MD-06, CUS-01–CUS-04, RET-04  
**Owner-configurable values:** Consent wording, loyalty rates, expiry, rounding, wallet limits, and Gift Card validity  
**Production decision pending:** Legal consent/retention wording and final financial rules

---

## 1. Purpose

This policy defines local implementation conventions for customer identity, consent, shared loyalty, separated wallets, and Gift Cards.

---

## 2. Requirement Classification

| Item | Classification |
|---|---|
| Unique customer phone | PRD Requirement |
| Unified customer history | PRD Requirement |
| Separate Product Wallet and Party Wallet | PRD Requirement |
| Shared loyalty balance across activities | PRD Requirement |
| Activity-specific loyalty rules | PRD Requirement |
| Gift Card issue/use/void/balance/history | PRD Requirement |
| Exact loyalty rates | Owner-Configurable Value |
| Exact expiry/rounding | Owner-Configurable Value |
| Exact consent wording | Production Decision Pending |
| Wallet credit/debt limits | Owner-Configurable Value |

---

## 3. Customer Identity

1. One normalized unique phone number per customer.
2. Duplicate review is controlled.
3. Profile and retail/party history are unified.
4. Financial balances remain separated.
5. Children and birthday data are purpose-scoped.
6. Sensitive access is permission-controlled.

---

## 4. Consent

Consent fields are stored with:

- Consent status.
- Purpose.
- Captured time.
- Actor/source.
- Wording/version where applicable.

Final legal wording and retention require owner/legal approval.

---

## 5. Loyalty

1. One shared loyalty ledger.
2. Retail and party may use different configurable earn/redeem rules.
3. Manual ledger editing is prohibited.
4. Adjustments use referenced entries.
5. Expiry and rounding are configurable.
6. Insufficient balance is blocked.
7. Duplicate source posting is blocked.

---

## 6. Product Wallet

- Retail activity only.
- Hidden from Party Manager.
- Append-only ledger.
- Settlement and adjustments require permission.
- No transfer to Party Wallet.

---

## 7. Party Wallet

- Party activity only.
- Hidden from Cashier.
- Append-only ledger.
- Settlement and adjustments require permission.
- No transfer to Product Wallet.

---

## 8. Gift Cards

Gift Cards support:

- Unique identifier.
- Issue value.
- Current balance.
- Partial redemption.
- Full redemption.
- Use history.
- Void history.
- Validity period.
- Holder/reference where applicable.
- Permission-controlled use.

Overuse, duplicate use, expired use, and voided use are blocked.

---

## 9. Verification boundary

Verify:

1. Unique phone and duplicate handling.
2. Sensitive profile access.
3. Shared loyalty history.
4. Retail versus party loyalty rules.
5. Insufficient loyalty block.
6. Product Wallet/Party Wallet isolation.
7. No cross-wallet transfer.
8. Gift Card partial/full/expired/void behavior.
9. Source reconciliation.
10. RTL/LTR, responsive, console, and network.

TSK-027 closure evidence includes SQLite and MariaDB feature coverage, real MariaDB concurrency workers, direct HTTP scope/RBAC checks, and Chromium/Firefox/WebKit browser checks. Party history/payment/return/gift/wallet scenarios remain downstream tasks and are not claimed here.
