#!/usr/bin/env bash
set -Eeuo pipefail

readonly PHP_BIN='/opt/cpanel/ea-php84/root/usr/bin/php'
readonly NODE_BIN='/opt/cpanel/ea-nodejs20/bin/node'
readonly EVIDENCE='.ai/evidence/v0.1.22-hotfix22'
readonly COMPILED_VIEWS="$PWD/storage/framework/views"

"$PHP_BIN" "$EVIDENCE/verify-navigation-runtime.php" --fixture
"$PHP_BIN" .ai/evidence/v0.1.22-hotfix21/verify-payment-contract.php
"$NODE_BIN" .ai/evidence/v0.1.22-hotfix21/verify-browser-contracts.mjs

for file in \
    app/Modules/Platform/Support/ApplicationNavigation.php \
    app/Support/ApplicationVersion.php \
    "$EVIDENCE/verify-navigation-runtime.php"; do
    "$PHP_BIN" -l "$file" >/dev/null
done
echo 'AFFECTED_PHP_SYNTAX=PASS'

APP_ENV=production CACHE_STORE=array SESSION_DRIVER=array VIEW_COMPILED_PATH="$COMPILED_VIEWS" "$PHP_BIN" artisan view:clear --no-interaction >/dev/null
APP_ENV=production CACHE_STORE=array SESSION_DRIVER=array VIEW_COMPILED_PATH="$COMPILED_VIEWS" "$PHP_BIN" artisan view:cache --no-interaction >/dev/null
compiled_count="$(find "$COMPILED_VIEWS" -type f -name '*.php' | wc -l)"
[[ "$compiled_count" -gt 0 ]]
while IFS= read -r -d '' file; do "$PHP_BIN" -l "$file" >/dev/null; done < <(find "$COMPILED_VIEWS" -type f -name '*.php' -print0)
echo "BLADE_COMPILE_AND_COMPILED_SYNTAX=PASS count=$compiled_count"

"$PHP_BIN" -r '$checkout=file_get_contents("resources/views/livewire/pos/checkout-panel.blade.php");$refund=file_get_contents("resources/views/livewire/pos/refund-wizard.blade.php");foreach([$checkout=>"<section ",$refund=>"<div "] as $source=>$root){$withoutPhp=preg_replace("/@php.*?@endphp/s","",$source,1);$trim=trim($withoutPhp);if(!str_starts_with($trim,$root)){exit(1);}}if(substr_count($checkout,"wire:key=\"pos-checkout-dialog-")!==1||substr_count($refund,"data-pos-refund-wizard")!==1){exit(1);}echo "AFFECTED_LIVEWIRE_ROOT_CONTRACTS=PASS\n";'

"$PHP_BIN" -r 'foreach(["lang/ar.json","lang/en.json"] as $file){json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);} echo "LOCALE_JSON=PASS\n";'

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

"$PHP_BIN" -r '$navigation=file_get_contents("resources/views/components/app-navigation.blade.php");$service=file_get_contents("app/Modules/Platform/Support/ApplicationNavigation.php");if(strpos($navigation,"normalizeForRendering")===false||strpos($service,"navigationKey")===false||strpos($service,"JSON_THROW_ON_ERROR")===false){exit(1);}echo "NAVIGATION_NORMALIZATION_STATIC_CONTRACT=PASS\n";'

echo 'HOTFIX22_FOCUSED_VERIFICATION=PASS'
