# v0.1.21 UAT Coverage

| Setup step / module | Marked records | Route | Expected result |
|---|---|---|---|
| Company | Selected existing active company; no owner data altered | `/initial-setup` | Company boundary established |
| Branches / stores / warehouses / registers | TEST branch, receiving warehouse, warehouse, outlet, mapping, register | `/admin/branches`, `/admin/stores`, `/admin/cash-drawers` | Active company-scoped location chain |
| Users / permissions | Generated TEST UAT administrator and explicit scopes | `/admin/users` | Credentials printed only by real seed |
| Settings | Existing company settings plus marked coverage record | `/admin/settings` | No real setting overwritten |
| Catalog / suppliers | TEST category, brand, supplier, supplier link, product | `/catalog/products`, `/suppliers` | Searchable marked Product Card |
| Barcodes | TEST international and supplier-local identities | `/catalog/products` | Scanner/manual/autocomplete coverage |
| Pricing | Existing List 0, marked percentage list, outlet assignment | `/pricing/lists` | Destination snapshot source available |
| Purchase invoices | TEST Draft, Awaiting Distribution, Approved, Reversed | `/purchasing/invoices` | State progression and resume coverage |
| Distribution / inventory | Marked allocations, movements, isolated balances | `/inventory/movements`, `/inventory/balances` | Full distribution; receiving remainder zero |
| Labels | Approved destination allocation and price snapshot | destination-label route | Barcode and destination price render |
| Other accessible modules | UAT administrator access; no unsupported workflow fabricated | `/dashboard` | Existing routes can be reviewed with marked prerequisites |

Confirmed defects corrected in this slice: unbounded 1,000-product invoice render, ambiguous scanner/Add control, missing unknown-scan prefill, missing duplicate-line focus event, ambiguous draft progression, unsupported camera fallback messaging, and a stale approval version after Stage 2 distribution prevented an otherwise valid invoice from being approved.
