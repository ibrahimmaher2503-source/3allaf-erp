# v0.1.22-hotfix21 Verification Evidence

**Date:** 2026-09-08
**Source baseline:** exact production Hotfix20 commit `04d09977abd9b3d56bc83fd067a78642f5127ca7`
**Branch:** `hotfix/v0.1.22-hotfix21-cash-sidebar`
**Result:** PASS for the focused database-free implementation and asset checks

Completed evidence:

- Server-authoritative BC Math allocation passed the required cash cases: due 25 / received 20 is rejected as underpayment; 25 / 25 settles exactly; 25 / 1000 records 975 change. Split cash/non-cash, exact electronic split, zero, negative, malformed, and overallocated portions were also checked.
- Browser-side cent arithmetic returned remaining `5.00` and zero change for 20 received, zero remaining/change for 25, and change `975.00` for 1000. Duplicate methods and invalid parts were rejected.
- The deterministic navigation-state contract preserved a lower sidebar position across two page replacements, restored expanded group state, retained the active ancestry, and did not scroll an already-visible active item. The source contract retains logical RTL positioning, collapsed desktop width, and the unchanged mobile drawer media boundary.
- Laravel compiled the complete Blade/Flux/Livewire view set to 886 compiled PHP files and all compiled PHP passed syntax. The affected checkout Livewire template retained its permanent outer root and keyed total refresh contract.
- Exactly one Vite 8.2.0 production build ran with Node 20.20.2. Manifest SHA-256: `1618bfbf5e4ebe3830db4f18bc435648d5f067cb03a24a1c4fe6b7ccab67d71c`; app JS `assets/app-ST3E3GyZ.js`: `7173aa54896453aa9eb94569c2e60d07d93701fc447d8f9dbb60affa1aa523e1`; app CSS `assets/app-DYt6CKLa.css`: `bd7cfb054cd76cec89861bce602db64d6beaa670122149ea31337ab6520ba24a`. Every manifest reference exists and is non-empty.
- Arabic and English locale JSON, affected PHP syntax, fixed-sidebar/static payment safety assertions, and Git diff hygiene passed.

The first sidebar evidence run failed because its mock returned closed elements for a `[open]` selector; only that mock was corrected and the affected contract resumed. The initial asset verifier assumed Vite attached CSS to the JS entry, while this project has a separate CSS entry; only that verifier assumption was corrected. Neither correction changed application behavior or triggered another build.

No migration was added or run. Migration 000111 remains required to already be Ran. No database, production path, deployment, activation, service, sudo, tag, push, remote service, Composer operation, or broad test suite was accessed or run. No headed browser executable was available, so the sidebar result is a deterministic DOM/state contract plus compiled CSS/source verification, not a headed visual claim.
