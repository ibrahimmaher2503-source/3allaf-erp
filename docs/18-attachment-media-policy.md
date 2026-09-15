# 18 — Attachment and Media Policy

**Product:** TOY & JOY  
**Phase:** Phase 1  
**Status:** Approved local implementation baseline; production storage provider and retention infrastructure remain configurable  
**Authority:** PRD NFR-01, NFR-04, POS-03, security checklist, and owner direction  
**Applies to:** Payment evidence, product images, import files, exports, party evidence, asset-condition evidence, and future protected documents

---

## 1. Purpose

This policy defines how attachments and media are accepted, validated, stored, accessed, retained, redacted, and removed without exposing private or operational data.

---

## 2. Attachment Classes

| Class | Examples | Default visibility |
|---|---|---|
| `payment_evidence` | POS-terminal receipt image | Authorized cashier/manager/reviewer within scope |
| `product_image` | Main and additional product images | Authorized catalog users; public only if a later channel explicitly permits |
| `import_source` | Excel import file | Import actor and authorized reviewers |
| `import_error_export` | Rejected-row workbook | Import actor and authorized reviewers |
| `party_evidence` | Party documents or operational images | Party roles and authorized reviewers |
| `asset_condition` | Before/after asset photos | Party/asset roles and authorized reviewers |
| `approval_evidence` | Supporting approval document | Approver/reviewer roles within scope |
| `generated_document` | PDF/Excel/print artifact retained as file | Authorized users matching document permission |
| `system_support` | Diagnostic artifact without secrets | Administrator/support only |

Every attachment must have one declared purpose. Generic unclassified upload is not allowed.

---

## 3. Allowed File Types

### 3.1 Images

Allowed:

- JPEG: `image/jpeg`
- PNG: `image/png`
- WebP: `image/webp`

Optional later approval:

- HEIC/HEIF only if a compatible conversion capability is approved.

### 3.2 Documents

Allowed where the owning workflow requires them:

- PDF: `application/pdf`
- Excel Open XML: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
- CSV: `text/csv`

Legacy Excel, executable formats, archives, scripts, HTML, SVG, and macro-enabled Office files are denied by default.

---

## 4. Size and Count Limits

Default local limits:

| Purpose | Max file size | Max count |
|---|---:|---:|
| Payment evidence | 8 MB | 3 per payment |
| Product main image | 8 MB | 1 |
| Product additional images | 8 MB each | 4 |
| Import source | 25 MB | 1 per import batch |
| Import error export | 25 MB | System generated |
| Party evidence | 12 MB each | 10 per source record |
| Asset-condition evidence | 12 MB each | 12 per inspection |
| Approval evidence | 12 MB each | 5 per approval request |
| Generated PDF/Excel | 30 MB | System generated |

Production limits remain configurable based on hosting quotas and operational requirements.

---

## 5. Validation Requirements

Every upload must be validated server-side using:

1. Declared workflow purpose.
2. File extension.
3. Browser-provided MIME type.
4. Server-detected MIME/signature.
5. Maximum size.
6. Maximum count.
7. Image dimension limits where applicable.
8. Safe generated filename.
9. Content hash.
10. Duplicate policy for that workflow.

A renamed executable or mismatched signature must be rejected.

Image metadata that is not operationally needed should be stripped where supported, especially location metadata.

---

## 6. Storage Rules

1. Attachments are private by default.
2. Do not store protected files under a directly public web path.
3. Use generated UUID-based storage names; preserve the original filename only as metadata.
4. Organize storage by purpose and date, not by user-supplied path.
5. Store content hash, size, MIME, original name, storage disk, and path.
6. Storage disk/provider must be configurable.
7. No cloud provider is assumed until production infrastructure is approved.
8. Database blobs are not used for normal files.
9. Failed database transactions must not leave orphaned permanent files.
10. Temporary uploads must expire and be cleaned safely.

---

## 7. Access and Delivery

1. Download and preview require authentication and server-side authorization.
2. Authorization checks purpose, source record, role, branch/store scope, and sensitive-field permission.
3. Do not expose permanent public URLs for protected files.
4. Use controlled streamed responses or short-lived signed delivery.
5. Set safe `Content-Disposition`, `Content-Type`, and caching headers.
6. Inline preview is allowed only for safe image/PDF types.
7. Excel/CSV downloads use attachment disposition.
8. Access to sensitive attachments may itself be audited.
9. Cross-activity access is denied, including retail versus party boundaries.
10. Deleted or redacted files must not remain reachable through old URLs.

---

## 8. Retention

Default implementation baseline:

| Attachment class | Retention rule |
|---|---|
| Approved transaction evidence | Retain with the source transaction; do not delete independently |
| Product images | Retain while active and while referenced by historical records |
| Import sources | 180 days after completion unless a legal/operational hold applies |
| Import error exports | 90 days |
| Draft-only abandoned uploads | 30 days |
| Asset/party evidence tied to approved history | Retain with the source record |
| Temporary uploads | 24 hours |
| Generated exports | 7 days unless explicitly retained as evidence |
| Support diagnostics | 30 days unless linked to an incident hold |

Production retention periods remain configurable and require final owner approval before production deployment.

---

## 9. Deletion and Redaction

### 9.1 Physical Deletion

Physical deletion is permitted only for:

- Expired temporary files.
- Abandoned draft uploads with no approved source.
- Malware/unsafe files quarantined before use.
- Duplicate unreferenced uploads.
- Records whose deletion is explicitly permitted by approved retention policy.

Files attached to approved transactions are not physically deleted through normal UI.

### 9.2 Redaction

Redaction may hide:

- Payment references.
- Customer-sensitive values.
- Personal contact details.
- Internal notes.
- Cost values.
- Security or diagnostic details.

Redaction changes presentation and export output; it does not silently rewrite the preserved original evidence. The redaction event must be audited.

---

## 10. Malware and Content Safety

Until a production scanner is selected:

- Deny executable and archive formats.
- Enforce strict allowlists and signature checks.
- Never execute uploaded content.
- Do not render user HTML or SVG.
- Treat Office documents as downloads, not inline content.
- Quarantine files that fail validation.
- Keep scanner integration replaceable and configurable.

A production malware scanning capability must be selected before production acceptance for external uploads.

---

## 11. Attachment Record Requirements

Each attachment record must include:

- UUID.
- Purpose.
- Owner/source type and ID.
- Uploader.
- Branch/store scope where applicable.
- Original filename.
- Stored filename/path.
- Disk/provider.
- MIME type.
- Extension.
- Size.
- Hash.
- Width/height for images where relevant.
- Status: `temporary`, `active`, `quarantined`, `redacted`, `expired`, `deleted`.
- Retention-until date where applicable.
- Created/updated timestamps.
- Related audit event.
- Optional redaction metadata.

---

## 12. Manual Browser Verification

For every implemented upload flow verify:

1. Valid upload succeeds.
2. Wrong extension is rejected.
3. MIME/signature mismatch is rejected.
4. Oversized file is rejected.
5. Count limit is enforced.
6. Unauthorized user cannot preview/download.
7. Cross-branch/store/activity access is denied.
8. Old signed link or direct path does not bypass authorization.
9. Deleted/redacted file is not exposed.
10. Mobile upload, RTL/LTR, progress/error states, console, and network are clean.

No automated tests are created or executed under the current project directive.
