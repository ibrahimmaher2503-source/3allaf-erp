# v0.1.14 Navigation, Table, and Action System

- Active location is derived from authorized route-pattern mappings on every request.
- Desktop preference storage is versioned and malformed/legacy values safely recover.
- Setup uses readiness-derived primary context plus a Settings-only secondary sequence.
- `x-actions.button` provides one icon/semantic/accessibility contract; shared table classes provide density, focus, identity, bidi isolation, action layout, and mobile cards.
- Exact Livewire targets isolate loading state to the executing action.

## Semantic actions

| Meaning | Tone | Default icon |
|---|---|---|
| View/open | Blue | eye |
| Edit | Teal | pencil-square |
| Create/link/assign/restore | Green | plus/link/user-plus/arrow-path |
| Approve/confirm/receive/complete | Emerald | check/inbox/check-badge |
| Print/export/download | Violet | printer/arrow-down-tray |
| History/audit | Slate | clock |
| Pause/disable/unavailable | Amber | pause/no-symbol/wrench |
| Reject/cancel/delete/archive | Red | x/trash/archive |
