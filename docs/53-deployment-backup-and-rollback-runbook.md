# 53 — Deployment, Backup, Restore, and Rollback Runbook

**Product:** TOY & JOY
**Phase:** Phase 1
**Status:** Derived operational specification — team-adopted defaults, infrastructure decisions outstanding
**Authority:** NFR-04, NFR-01
**Blockers:** BLK-001 (hosting, database, queue, scheduler, cache), BLK-002 (backup destination, RPO/RTO, restore owner, monitoring). Both recorded as retaining a specific gap for **the actual backup/restore capability and the setup/run/recovery deployment and rollback runbooks**.

For the normal installation path, `DatabaseSeeder` creates the idempotent authorization catalog, bootstrap administrator, baseline company/location/cash configuration, and document sequences. It does not create products, customers, stock, prices, bookings, sales, or other transactions.

---

## 1. Purpose

BLK-001 and BLK-002 name missing runbooks explicitly. This document supplies the procedures. It does **not** choose the hosting provider, database engine, or backup destination — those remain owner and technical-owner decisions.

The procedures below are written so that filling in the provider later does not change the steps.

---

## 2. Environments

| Environment | Purpose | Data |
|---|---|---|
| Local | Development | Developer-entered only; never production data |
| Staging | UAT and release rehearsal | Anonymised or synthetic |
| Production | Live operation | Real |

Production data is never copied to local or staging without anonymisation. Customer phone numbers, wallet balances, and payment evidence are the sensitive fields (`docs/15`).

---

## 3. Pre-Deployment Checklist

1. Target release tagged and its commit recorded.
2. `docs/39` release gates passed.
3. Migrations reviewed for irreversibility. Any migration that drops or narrows a column carrying posted financial data is refused (`DEC-043` A-04).
4. Backup taken and **restore verified**, not merely taken.
5. Rollback path confirmed for this specific release.
6. Maintenance window agreed with operations if the release blocks POS.

Step 4 is the one most often skipped. An unverified backup is not a backup.

---

## 4. Deployment Procedure

### Release preparation and runtime ownership guard

Prepare each immutable release from the approved Git commit, not from the working tree. Extract tracked source while excluding the source `storage` tree, then link the approved shared environment and storage paths. Reuse only separately validated locked dependencies and built frontend assets. Never copy generated source `bootstrap/cache/*.php` files into a release; the prepared `bootstrap/cache` directory must contain only its tracked `.gitignore` until runtime cache generation.

All Laravel cache generation, cache clearing, maintenance, queue, and scheduled Artisan commands must execute as the runtime user `rajeh`. The scheduler entry and any deployment automation must invoke Artisan as `rajeh`, never as `root` or the release-preparation account. This keeps generated `bootstrap/cache` files writable by the serving runtime and prevents recurring permission failures.

```
1. Announce maintenance window
2. Verified backup (§5)
3. Enable maintenance mode
4. Deploy code to the release path
5. Install dependencies with locked versions
6. Run migrations
7. Run `php artisan db:seed --force`; change the documented bootstrap administrator password and enroll MFA immediately after the first sign-in
8. Rebuild caches: config, route, view
9. Build frontend assets
10. Restart queue workers and scheduler
11. Smoke checks (§7)
12. Disable maintenance mode
13. Post-deploy monitoring window
```

The seed step installs only canonical authorization/reference data and the deployment-supplied initial administrator. It must not be used for branches, stores, products, customers, stock, prices, bookings, sales, or policy values; those follow the approved migration/reconciliation stages in `docs/54`. Queue workers must be restarted at step 10 — a running worker holds the previous release's code in memory and will process new jobs with old logic.

---

## 5. Backup

| Item | Requirement | Owner decision |
|---|---|---|
| Scope | Database + private attachment storage (`docs/18`) | — |
| Frequency | At minimum daily, plus before every deployment | RPO — BLK-002 |
| Retention | Defined retention with an explicit deletion policy | BLK-002 |
| Destination | Off-host | BLK-002 |
| Encryption | At rest and in transit | BLK-002 |
| Restore verification | Scheduled test restore proving recoverability | BLK-002 |
| Restore owner | A named person, not a role in the abstract | BLK-002 |

Attachments and database must be backed up as a consistent pair. A database referencing attachment files that no longer exist is a partial restore, and payment evidence (POS-03) is exactly the data that goes missing.

---

## 6. Restore Procedure

```
1. Declare the incident and stop writes
2. Identify the target recovery point
3. Restore database to an isolated instance first — never straight over production
4. Restore attachment storage to the matching point
5. Verify integrity (§7) on the isolated instance
6. Reconcile: rebuild stock balances from movements and compare (docs/47 §5)
7. Cut over
8. Record the recovery point, data loss window, and every affected document
```

Step 6 is specific to this system. Because `stock_balances` is derived state, a restore must prove that balances still reconcile to the movement ledger before the system is trusted with new postings.

Step 8 matters legally: documents approved after the recovery point are gone, and operations must know which ones.

---

## 7. Verification Checks

Applied after deployment and after restore:

- Migrations report all as run.
- Routes resolve; guest requests redirect to login with a request ID.
- A login succeeds and an authorised screen renders.
- An audit event writes and appears.
- Stock balances rebuilt from movements match stored balances.
- Document sequences show no duplicate approved numbers (NFR-06).
- Private attachment delivery works and public access is refused.
- Arabic RTL and English LTR render.

---

## 8. Rollback

Code rollback is straightforward. Database rollback usually is not.

| Situation | Action |
|---|---|
| Code-only defect, no migration | Redeploy previous release; rebuild caches; restart workers |
| Additive migration, no data loss | Roll code back, leave schema; the added column is unused |
| Destructive migration | **Restore from backup** (§6). Do not attempt a reverse migration on posted financial data |
| Bad data posted by a defect | Do not delete. Post referenced correction documents (NFR-02) |

The last row is the rule that protects the audit trail. Approved documents are immutable; a defect that produced bad postings is corrected forward, never erased.

---

## 9. Monitoring

Minimum signals: application errors with request ID, failed queue jobs, scheduler last-run, backup success/failure, disk usage on attachment storage, and failed login rate.

Alert recipients and the support contact are owner decisions under BLK-002.

---

## 10. Manual Verification

Perform a full rehearsal on staging before the first production deployment: deploy, verify, restore from backup, reconcile balances, and roll back. Record the elapsed time for each — that measurement is the input to the owner's RPO and RTO decision, which cannot be made honestly without it.
