#!/usr/bin/env bash
set -Eeuo pipefail

activation_script=${1:?usage: activation-fail-closed-contract.sh /absolute/path/to/activation-script}
runtime_verifier=${2:-"$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/verify-runtime.php"}
php_bin=${PHP_BIN:-php}

[[ "$activation_script" == /* && -f "$activation_script" && -x "$activation_script" ]] || exit 1
[[ -x "$(command -v "$php_bin")" && -f "$runtime_verifier" ]] || exit 1

failure_log="$(mktemp)"
trap 'rm -f "$failure_log"' EXIT
set +e
"$php_bin" "$runtime_verifier" --force-failure >"$failure_log" 2>&1
verifier_status=$?
set -e
cat "$failure_log"
[[ $verifier_status -ne 0 ]]
grep -Fxq 'HOTFIX34_RUNTIME_VERIFICATION=FAIL Forced verifier failure for isolated fail-closed testing.' "$failure_log"
! grep -Fq 'HOTFIX34_RUNTIME_VERIFICATION=PASS' "$failure_log"
echo "HOTFIX34_EXPLICIT_VERIFIER_FAILURE_EXIT=PASS status=$verifier_status"

set +e
output="$($activation_script --isolated-failure-tests 2>&1)"
status=$?
set -e
printf '%s\n' "$output"
[[ $status -eq 0 ]]
grep -Fxq 'HOTFIX34_PRE_SWITCH_FORCED_FAILURE=PASS active_unchanged=hotfix32 success_absent=yes' <<<"$output"
grep -Fxq 'HOTFIX34_POST_SWITCH_FORCED_FAILURE=PASS restored=hotfix32 success_absent=yes' <<<"$output"
grep -Fxq 'HOTFIX34_ACTIVATION_FAIL_CLOSED=PASS isolated_paths=yes production_touched=no' <<<"$output"
! grep -Fq 'ACTIVATION=SUCCESS' <<<"$output"
echo 'HOTFIX34_ACTIVATION_NEGATIVE_CONTRACT=PASS'
