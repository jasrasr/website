# Filename: start-local-server.ps1
# Revision : 2.0.0
# Description : Starts the local PHP server for the License Plate Photo Logger and optionally opens it in Chrome.
# Created Date : 2026-07-15
# Modified Date : 2026-07-25
# Changelog :
# 2.0.0 adapt the copied HumidorHQ launcher for the flat license-plate PHP app
# 1.3.0 default runtime data to the repository data directory while retaining an optional override
# 1.2.0 require and validate an external HUMIDORHQ_DATA_ROOT before starting PHP
# 1.1.1 look for PHP in standard winget install folders when PATH has not refreshed yet
# 1.1.0 open the local HumidorHQ URL in Chrome after confirming the server is listening
# 1.0.1 use netstat for listener checks because Get-NetTCPConnection can hang on some Windows sessions
# 1.0.0 initial release

param(
    [int]$Port = 8000,
    [string]$HostName = '127.0.0.1',
    [switch]$NoBrowser
)

$ErrorActionPreference = 'Stop'

function Get-PhpCommand {
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCommand) {
        return $phpCommand
    }

    $candidatePaths = @(
        (Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.5_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'),
        (Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'),
        (Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'),
        (Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'),
        (Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'),
        'C:\php\php.exe'
    )

    $phpPath = $candidatePaths | Where-Object { $_ -and (Test-Path -LiteralPath $_) } | Select-Object -First 1
    if (-not $phpPath) {
        throw 'PHP was not found on PATH or in the standard winget install locations.'
    }

    return Get-Item -LiteralPath $phpPath
}

function Get-LocalListenerPid {
    param(
        [int]$Port,
        [string]$HostName
    )

    $pattern = [regex]::Escape("${HostName}:$Port")
    $line = netstat -ano | Select-String -Pattern $pattern | Where-Object { $_.Line -match 'LISTENING' } | Select-Object -First 1
    if (-not $line) {
        return $null
    }
    if ($line.Line -match '\s+(\d+)\s*$') {
        return [int]$matches[1]
    }
    return $null
}

function Open-LocalSiteInChrome {
    param(
        [string]$Url
    )

    $chromeCommand = Get-Command chrome.exe -ErrorAction SilentlyContinue
    $chromePaths = @(
        (Join-Path $env:ProgramFiles 'Google\Chrome\Application\chrome.exe'),
        (Join-Path ${env:ProgramFiles(x86)} 'Google\Chrome\Application\chrome.exe'),
        (Join-Path $env:LocalAppData 'Google\Chrome\Application\chrome.exe')
    )
    $chromePath = if ($chromeCommand) {
        $chromeCommand.Source
    } else {
        $chromePaths | Where-Object { $_ -and (Test-Path -LiteralPath $_) } | Select-Object -First 1
    }

    if (-not $chromePath) {
        Write-Warning "Google Chrome was not found. Open $Url manually."
        return
    }

    Start-Process -FilePath $chromePath -ArgumentList $Url
    Write-Host "Opened $Url in Chrome." -ForegroundColor Green
}

$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$php = Get-PhpCommand
$url = "http://${HostName}:$Port/"
$existingPid = Get-LocalListenerPid -Port $Port -HostName $HostName

if ($existingPid) {
    Write-Host "License Plate Photo Logger is already listening at $url on process $existingPid." -ForegroundColor Green
    if (-not $NoBrowser) {
        Open-LocalSiteInChrome -Url $url
    }
    return
}

$requiredFiles = @('index.php', 'view_log.php', 'process_upload.php', 'config.php')
foreach ($filename in $requiredFiles) {
    $path = Join-Path $repoRoot $filename
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        throw "Required application file is missing: $filename"
    }
}

$phpPath = if ($php.PSObject.Properties.Name -contains 'Source') { $php.Source } else { $php.FullName }
$args = @('-S', "${HostName}:$Port", '-t', $repoRoot)
$process = Start-Process -FilePath $phpPath -ArgumentList $args -WorkingDirectory $repoRoot -WindowStyle Hidden -PassThru

Start-Sleep -Milliseconds 700
$listenerPid = Get-LocalListenerPid -Port $Port -HostName $HostName
if (-not $listenerPid) {
    throw "PHP server did not start on $url. Check PHP output or port availability."
}

Write-Host "License Plate Photo Logger local server started at $url with process $($process.Id)." -ForegroundColor Green
Write-Host "Document root: $repoRoot" -ForegroundColor Green

if (-not $NoBrowser) {
    Open-LocalSiteInChrome -Url $url
}

# Example Usage:
#   .\start-local-server.ps1
#   .\start-local-server.ps1 -Port 8080
#   .\start-local-server.ps1 -HostName "127.0.0.1" -Port 8000
#   .\start-local-server.ps1 -NoBrowser
