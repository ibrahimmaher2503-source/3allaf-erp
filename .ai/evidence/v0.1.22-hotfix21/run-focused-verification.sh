#!/usr/bin/env bash
set -Eeuo pipefail

readonly PHP_BIN='/opt/cpanel/ea-php84/root/usr/bin/php'
readonly NODE_BIN='/opt/cpanel/ea-nodejs20/bin/node'
readonly EVIDENCE='.ai/evidence/v0.1.22-hotfix21'

"$PHP_BIN" "$EVIDENCE/verify-payment-contract.php"
"$NODE_BIN" "$EVIDENCE/verify-browser-contracts.mjs"

"$PHP_BIN" -r 'foreach(["lang/ar.json","lang/en.json"] as $file){json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);} echo "LOCALE_JSON=PASS\n";'
for file in app/Modules/Retail/Actions/RetailSaleAction.php app/Support/ApplicationVersion.php routes/pos-hotfix16.php; do
    "$PHP_BIN" -l "$file" >/dev/null
done
echo 'AFFECTED_PHP_SYNTAX=PASS'

APP_ENV=production CACHE_STORE=array SESSION_DRIVER=array VIEW_COMPILED_PATH="$PWD/storage/framework/views" "$PHP_BIN" artisan view:clear --no-interaction >/dev/null
APP_ENV=production CACHE_STORE=array SESSION_DRIVER=array VIEW_COMPILED_PATH="$PWD/storage/framework/views" "$PHP_BIN" artisan view:cache --no-interaction >/dev/null
compiled_count="$(find storage/framework/views -type f -name '*.php' | wc -l)"
[[ "$compiled_count" -gt 0 ]]
while IFS= read -r -d '' file; do "$PHP_BIN" -l "$file" >/dev/null; done < <(find storage/framework/views -type f -name '*.php' -print0)
echo "BLADE_COMPILE_AND_COMPILED_SYNTAX=PASS count=$compiled_count"

"$PHP_BIN" -r '$source=file_get_contents("resources/views/livewire/pos/checkout-panel.blade.php");$withoutPhp=preg_replace("/@php.*?@endphp/s","",$source,1);$trim=trim($withoutPhp);if(!str_starts_with($trim,"<section ")||!str_ends_with($trim,"</section>")){exit(1);}if(substr_count($source,"wire:key=\"pos-checkout-dialog-")!==1){exit(1);}echo "AFFECTED_LIVEWIRE_ROOT_AND_REFRESH_CONTRACT=PASS\n";'

"$NODE_BIN" - <<'NODE'
const fs = require('fs');
const crypto = require('crypto');
const path = require('path');
const manifest = JSON.parse(fs.readFileSync('public/build/manifest.json'));
const refs = new Set();
for (const entry of Object.values(manifest)) {
  if (entry.file) refs.add(entry.file);
  for (const field of ['css', 'assets']) for (const ref of entry[field] || []) refs.add(ref);
  for (const ref of [...(entry.imports || []), ...(entry.dynamicImports || [])]) if (!manifest[ref]) throw new Error(`missing manifest import ${ref}`);
}
for (const ref of refs) {
  const file = path.resolve('public/build', ref);
  if (!file.startsWith(path.resolve('public/build') + path.sep) || !fs.statSync(file).isFile() || fs.statSync(file).size === 0) throw new Error(`invalid asset ${ref}`);
}
const app = manifest['resources/js/app.js'];
const css = manifest['resources/css/app.css'];
if (!app?.file || !css?.file) throw new Error('app entries incomplete');
const sha = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
console.log(`VITE_ASSET_INTEGRITY=PASS manifest_sha256=${sha('public/build/manifest.json')} app_js=${app.file} app_js_sha256=${sha(`public/build/${app.file}`)} app_css=${css.file} app_css_sha256=${sha(`public/build/${css.file}`)}`);
NODE

grep -Fq 'position:fixed!important' resources/css/app.css
grep -Fq 'overflow-y:auto!important' resources/css/app.css
grep -Fq "distinct:strict" routes/pos-hotfix16.php
grep -Fq 'validatedTenderAllocation' app/Modules/Retail/Actions/RetailSaleAction.php
git diff --check
echo 'STATIC_SAFETY_AND_GIT_DIFF=PASS'
