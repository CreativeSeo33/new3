$ErrorActionPreference = "Stop"

New-Item -ItemType Directory -Force -Path "docs/runtime" | Out-Null

try {
  & php bin/console debug:router --format=json > docs/runtime/routes.json
} catch {
  Write-Warning ("routes.json not generated: {0}" -f $_.Exception.Message)
}

try {
  & php bin/console api:openapi:export --json > docs/runtime/openapi.json
} catch {
  Write-Warning "openapi.json not generated (API Platform export failed)"
}

& php tools/build-ai-index.php
if ($LASTEXITCODE -ne 0) {
  throw "AI indexer failed with exit code $LASTEXITCODE"
}

Write-Host "Artifacts:"
Get-ChildItem docs/runtime/ai-index.json, docs/runtime/routes.json, docs/runtime/openapi.json -ErrorAction SilentlyContinue | ForEach-Object {
  Write-Host (" - {0} {1:N0} bytes" -f $_.FullName, $_.Length)
}


