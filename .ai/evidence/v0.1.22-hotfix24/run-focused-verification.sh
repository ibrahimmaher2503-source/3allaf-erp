#!/usr/bin/env bash
set -Eeuo pipefail

readonly PHP_BIN='/opt/cpanel/ea-php84/root/usr/bin/php'
readonly NODE_BIN='/opt/cpanel/ea-nodejs20/bin/node'
readonly BASELINE='ac812449e446f69301a0202cb540c625f71eb9d8'
readonly EVIDENCE='.ai/evidence/v0.1.22-hotfix24'
readonly PROMOTION_SCRIPT="${1:?promotion script path required}"
readonly ACTIVATION_SCRIPT="${2:?activation script path required}"
readonly COMPILED_VIEWS="$PWD/storage/framework/views"

[[ "$(git merge-base HEAD "$BASELINE")" == "$BASELINE" ]]
[[ "$(git branch --show-current)" == 'hotfix/v0.1.22-hotfix24-product-quick-create-compact-ui' ]]
[[ -z "$(git status --porcelain)" ]]
[[ -z "$(git diff "$BASELINE"..HEAD --name-only -- database/migrations)" ]]

"$NODE_BIN" "$EVIDENCE/verify-source-contracts.mjs"
"$PHP_BIN" .ai/evidence/v0.1.22-hotfix23/verify-layout-runtime.php --fixture
"$NODE_BIN" .ai/evidence/v0.1.22-hotfix23/verify-layout-contract.mjs
"$PHP_BIN" .ai/evidence/v0.1.22-hotfix21/verify-payment-contract.php

for file in app/Support/ApplicationVersion.php app/Modules/Catalog/Actions/SaveProductAction.php app/Modules/Purchasing/Actions/SavePurchaseInvoiceAction.php; do
  "$PHP_BIN" -l "$file" >/dev/null
done
echo 'AFFECTED_PHP_SYNTAX=PASS'

APP_ENV=production CACHE_STORE=array SESSION_DRIVER=array VIEW_COMPILED_PATH="$COMPILED_VIEWS" "$PHP_BIN" artisan view:clear --no-interaction >/dev/null
APP_ENV=production CACHE_STORE=array SESSION_DRIVER=array VIEW_COMPILED_PATH="$COMPILED_VIEWS" "$PHP_BIN" artisan view:cache --no-interaction >/dev/null
compiled_count="$(find "$COMPILED_VIEWS" -type f -name '*.php' | wc -l)"
[[ "$compiled_count" -gt 0 ]]
while IFS= read -r -d '' file; do "$PHP_BIN" -l "$file" >/dev/null; done < <(find "$COMPILED_VIEWS" -type f -name '*.php' -print0)
echo "BLADE_COMPILE_AND_COMPILED_SYNTAX=PASS count=$compiled_count"

"$NODE_BIN" - <<'NODE'
const fs = require('fs');
const crypto = require('crypto');
const path = require('path');
const ar = JSON.parse(fs.readFileSync('lang/ar.json'));
JSON.parse(fs.readFileSync('lang/en.json'));
if (Object.values(ar).includes('متوسط تكلفة المخزون')) throw new Error('obsolete Arabic cost label remains');
const reports = fs.readFileSync('resources/views/pages/reports/index.blade.php', 'utf8');
const keys = new Set();
for (const expression of [/__\(\s*'((?:[^'\\]|\\.)*)'/g, /trans_choice\(\s*'((?:[^'\\]|\\.)*)'/g]) {
  let match;
  while ((match = expression.exec(reports))) keys.add(match[1].replace(/\\'/g, "'"));
}
for (const key of keys) if ((!ar[key] || ar[key] === key) && !['PDF'].includes(key)) throw new Error(`Arabic report key missing: ${key}`);
const manifest = JSON.parse(fs.readFileSync('public/build/manifest.json'));
const references = new Set();
for (const entry of Object.values(manifest)) {
  if (entry.file) references.add(entry.file);
  for (const field of ['css', 'assets']) for (const ref of entry[field] || []) references.add(ref);
  for (const ref of [...(entry.imports || []), ...(entry.dynamicImports || [])]) if (!manifest[ref]) throw new Error(`missing manifest import ${ref}`);
}
for (const ref of references) {
  const file = path.resolve('public/build', ref);
  if (!file.startsWith(path.resolve('public/build') + path.sep) || !fs.statSync(file).isFile() || fs.statSync(file).size === 0) throw new Error(`invalid asset ${ref}`);
}
const sha = file => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
console.log(`LOCALE_JSON_AND_ARABIC_REPORTS=PASS keys=${keys.size}`);
console.log(`VITE_ASSET_INTEGRITY=PASS manifest_sha256=${sha('public/build/manifest.json')}`);
NODE

git diff "$BASELINE"..HEAD --check
bash -n "$EVIDENCE/run-focused-verification.sh"
bash -n "$PROMOTION_SCRIPT"
bash -n "$ACTIVATION_SCRIPT"
git diff --quiet "$BASELINE"..HEAD -- \
  resources/js/sidebar-navigation-state.js resources/js/pos-payment-calculator.js \
  resources/views/layouts/app/sidebar.blade.php resources/views/layouts/pos.blade.php \
  resources/views/livewire/pos app/Livewire/Pos app/Modules/Retail app/Modules/Inventory
echo 'POS_CASH_REFUND_INVENTORY_SOURCE_REGRESSION=PASS'
echo 'GIT_DIFF_AND_OPERATOR_BASH_SYNTAX=PASS'
echo 'HOTFIX24_FOCUSED_FINAL_VERIFICATION=PASS migrations_run=none browser=not_used'
