#[CmdletBinding()]
param()

$ErrorActionPreference = 'Continue'
Set-StrictMode -Version Latest

# Перейти в корень репозитория (скрипт находится в docs/)
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Push-Location $repoRoot

try {
    # Гарантированно создать каталоги
    $runtimeDir = Join-Path $PSScriptRoot 'runtime'
    $dbDir = Join-Path $PSScriptRoot 'db'
    New-Item -ItemType Directory -Force -Path $runtimeDir, $dbDir | Out-Null

    $script:results = New-Object System.Collections.Generic.List[object]

    function Invoke-Step {
        param(
            [string]$Name,
            [string]$Exe,
            [string[]]$CommandArgs,
            [string]$OutFile,
            [int]$TimeoutSec = 180
        )
        Write-Host "`n>> $Name"
        $sw = [System.Diagnostics.Stopwatch]::StartNew()
        try {
            $argLine = ($CommandArgs -join ' ')
            $errFile = ($OutFile + '.stderr')
            $proc = Start-Process -FilePath $Exe -ArgumentList $argLine -WorkingDirectory $repoRoot -NoNewWindow -PassThru -RedirectStandardOutput $OutFile -RedirectStandardError $errFile
            $exited = $proc.WaitForExit($TimeoutSec * 1000)
            if (-not $exited) {
                try { $proc.Kill() } catch {}
                $status = 'TIMEOUT'
                $exit = 124
            } else {
                $exit = $proc.ExitCode
                if ($exit -ne $null -and $exit -ne 0) { $status = 'FAIL' } else { $status = 'OK' }
            }
            if (Test-Path $errFile) {
                try { if ((Get-Item $errFile).Length -eq 0) { Remove-Item -Force $errFile -ErrorAction SilentlyContinue } } catch {}
            }
            $size = 0
            if (Test-Path $OutFile) { $size = (Get-Item $OutFile).Length }
            $script:results.Add([pscustomobject]@{
                Name=$Name; Path=$OutFile; Status=$status; ExitCode=$exit; Size=$size; Ms=$sw.ElapsedMilliseconds
            }) | Out-Null
        } catch {
            try { ("ERROR: {0}" -f $_.Exception.Message) | Out-File -FilePath $OutFile -Encoding utf8 } catch {}
            $size = 0
            if (Test-Path $OutFile) { $size = (Get-Item $OutFile).Length }
            $script:results.Add([pscustomobject]@{
                Name=$Name; Path=$OutFile; Status='ERROR'; ExitCode=-1; Size=$size; Ms=$sw.ElapsedMilliseconds; Error=$_.Exception.Message
            }) | Out-Null
        }
    }

    Invoke-Step -Name 'Composer dump-autoload' -Exe 'composer' -CommandArgs @('dump-autoload','-o') -OutFile (Join-Path $runtimeDir 'composer-dump.txt')
    Invoke-Step -Name 'DI Container'       -Exe 'php'      -CommandArgs @('bin/console','debug:container','--format=json','--no-interaction') -OutFile (Join-Path $runtimeDir 'container.json')
    Invoke-Step -Name 'Routes'              -Exe 'php'      -CommandArgs @('bin/console','debug:router','--format=json','--no-interaction')    -OutFile (Join-Path $runtimeDir 'routes.json')
    Invoke-Step -Name 'Composer deps'       -Exe 'composer' -CommandArgs @('show','-D','--format=json')                     -OutFile (Join-Path $runtimeDir 'composer-deps.json')
    Invoke-Step -Name 'DB Schema (diff)'    -Exe 'php'      -CommandArgs @('bin/console','doctrine:schema:update','--dump-sql','--no-interaction') -OutFile (Join-Path $dbDir 'schema.sql')
    Invoke-Step -Name 'Doctrine entities'   -Exe 'php'      -CommandArgs @('bin/console','doctrine:mapping:info','--no-interaction')           -OutFile (Join-Path $dbDir 'entities.txt')
    Invoke-Step -Name 'OpenAPI JSON'        -Exe 'php'      -CommandArgs @('bin/console','api:openapi:export','--no-interaction')             -OutFile (Join-Path $runtimeDir 'openapi.json')
    Invoke-Step -Name 'OpenAPI YAML'        -Exe 'php'      -CommandArgs @('bin/console','api:openapi:export','--yaml','--no-interaction')     -OutFile (Join-Path $runtimeDir 'openapi.yaml')

    Write-Host ''
    Write-Host 'Summary:'
    foreach ($r in $script:results) {
        $sizeStr = '-'
        if ($r.Size -gt 0) { $sizeStr = ('{0:N0} B' -f $r.Size) }
        Write-Host ("{0,-22} {1,-7} {2,6}ms {3,10} {4}" -f $r.Name, $r.Status, $r.Ms, $sizeStr, $r.Path)
    }
}
finally {
    Pop-Location
}

exit 0


