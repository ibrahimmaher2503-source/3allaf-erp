# v0.1.16 Shared Form-Action Inventory

Mechanical scan found 80 Blade views containing a form or `wire:submit`. Authentication, locale/context selectors, destructive confirmations, approvals, posting, receiving, returns, exports, POS checkout/cart, and print operations retain their exact operation-specific verbs.

## Persistence forms normalized in this milestone

- `resources/views/platform/admin/settings.blade.php`: Company, Payment Method, Tax, Document Sequence, and Printer Profile use `Save`; Previous/Next are non-submitting route controls and each loading state targets only the actual save method.
- `resources/views/catalog/product-form.blade.php`: create/edit persistence uses the shared exact-target `Save` control.
- `resources/views/catalog/suppliers.blade.php`: supplier create/edit persistence uses the shared exact-target `Save` control; link/group/contact/destination operations retain their specific existing verbs.
- `resources/views/pages/customers/create.blade.php`: persistence label is `Save`; the list action remains New/Create Customer.

## Deliberate boundaries

- Approve, Post, Receive, Complete, Reject, Cancel, Delete, Archive, Import, Export, Print, Login, security and POS transaction controls keep their truthful verbs.
- List-page actions keep object-specific labels.
- No action method, target, confirmation, policy, permission, validation rule, route, or financial workflow was renamed.
