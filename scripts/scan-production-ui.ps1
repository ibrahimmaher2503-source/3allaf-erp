[CmdletBinding()]
param(
    [string[]] $Path = @('resources/views', 'lang'),
    [switch] $FailOnMatch
)

# Lightweight guard for user-facing source. Documentation, tests, and internal
# tutorial IDs are intentionally outside the default scan scope.
$forbidden = @(
    'TSK-', '\bDM\s*\d', 'BLK-', 'Local/Dev', '\bLocal\b', '\bDemo\b',
    '\bFixture\b', '\bSeed\b', '\bStaging\b', '\bReadiness\b',
    '\bBoundary\b', '\bImplementation\b', '\bMilestone\b',
    'Pending implementation', '\bDevelopment\b', '\bDeveloper\b',
    '\bDiagnostic\b', 'Test data', 'Test fixture', 'Acceptance review',
    'Production blocker', 'Configuration blocker', 'Owner decision',
    'Scoped ledger', '\bBaseline\b', '\bProof\b', '\bTBD\b', '\bTODO\b',
    'Coming in TSK', 'Not implemented', 'Implementation boundary',
    'under development', 'not available in this slice', 'future capability',
    'readiness only'
)

$regex = [regex]::new(($forbidden -join '|'), [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
$matchesFound = [System.Collections.Generic.List[object]]::new()

foreach ($root in $Path) {
    if (-not (Test-Path -LiteralPath $root)) { continue }

    Get-ChildItem -LiteralPath $root -Recurse -File -Include *.blade.php,*.json | ForEach-Object {
        $file = $_
        $lineNumber = 0
        Get-Content -LiteralPath $file.FullName -Encoding utf8 | ForEach-Object {
            $lineNumber++
            $line = $_
            $trimmed = $line.Trim()

            if ($trimmed.StartsWith('{{--') -or $trimmed.StartsWith('//') -or $trimmed.StartsWith('*')) { return }

            # A translation key can legitimately be an internal identifier; inspect
            # the value side of JSON entries and all Blade markup/text.
            if ($file.Extension -eq '.json' -and $line -match '^\s*"[^"\\]*(?:\\.[^"\\]*)*"\s*:') {
                $valueStart = $line.IndexOf(':') + 1
                $scanText = if ($valueStart -gt 0) { $line.Substring($valueStart) } else { $line }
            } else {
                $scanText = $line
            }

            $hit = $regex.Match($scanText)
            if ($hit.Success) {
                $matchesFound.Add([pscustomobject]@{
                    File = $file.FullName
                    Line = $lineNumber
                    Match = $hit.Value
                    Text = $trimmed
                })
            }
        }
    }
}

if ($matchesFound.Count -eq 0) {
    Write-Output 'Production UI text scan passed.'
    exit 0
}

$matchesFound | Format-Table -AutoSize | Out-String | Write-Output
Write-Output ("Production UI text scan found {0} candidate(s)." -f $matchesFound.Count)
if ($FailOnMatch) { exit 1 }
