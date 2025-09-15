$ErrorActionPreference = "Stop"

Write-Host "[PHPStan] Preparing environment..."

# Ensure directories exist
New-Item -ItemType Directory -Force -Path "var" | Out-Null
New-Item -ItemType Directory -Force -Path "var/phpstan" | Out-Null
New-Item -ItemType Directory -Force -Path "var/cache/dev" | Out-Null

Write-Host "[PHPStan] Generating Symfony container XML..."

try {
    $xml = & php bin/console --env=dev --no-ansi debug:container --format=xml
    $xml | Set-Content -Path "var/cache/dev/phpstan-container.xml" -Encoding utf8
} catch {
    Write-Error "Failed to generate phpstan-container.xml: $($_.Exception.Message)"
    exit 1
}

Write-Host "[PHPStan] Running analysis (composer stan)..."

& composer stan

if ($LASTEXITCODE -ne 0) {
    Write-Error "PHPStan finished with errors. Exit code: $LASTEXITCODE"
    exit $LASTEXITCODE
}

Write-Host "[PHPStan] Analysis finished successfully. No errors found."
exit 0

