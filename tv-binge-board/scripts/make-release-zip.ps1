<#
.SYNOPSIS
    Creates a clean TV Binge Board release ZIP.

.DESCRIPTION
    Packages the tv-binge-board folder for manual upload to shared hosting. Runtime data,
    local secret config, cache files, logs, and editor noise are left out of the ZIP.

.NOTES
    File: scripts/make-release-zip.ps1
    Project: TV Binge Board
    Author: Jason Lamb / ChatGPT
    Created: 2026-07-03
    Modified: 2026-07-03
    Revision: 1.0.0
#>
[CmdletBinding()]
param(
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path,
    [string]$OutputPath,
    [switch]$IncludePlaceholders,
    [switch]$OpenFolder
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Test-ExcludedReleaseFile {
    param([string]$RelativePath, [bool]$KeepPlaceholders)

    $path = $RelativePath -replace '\\', '/'
    $leaf = Split-Path $path -Leaf

    if ($leaf -in @('.DS_Store', 'Thumbs.db')) { return $true }
    if ($path -like 'dist/*') { return $true }
    if ($path -eq 'includes/config.local.php') { return $true }
    if ($path -like '*.log') { return $true }

    if ($path -like 'data/*') {
        return -not ($KeepPlaceholders -and ($leaf -eq '.placeholder' -or $leaf -eq '.htaccess'))
    }

    if ($path -like 'public-cache/*') {
        return -not ($KeepPlaceholders -and ($leaf -eq '.placeholder' -or $leaf -eq '.htaccess'))
    }

    return $false
}

$project = Resolve-Path $ProjectRoot
if (-not (Test-Path (Join-Path $project 'index.php'))) {
    throw "ProjectRoot does not look like the tv-binge-board folder: $project"
}

if ([string]::IsNullOrWhiteSpace($OutputPath)) {
    $dist = Join-Path $project 'dist'
    New-Item -ItemType Directory -Path $dist -Force | Out-Null
    $OutputPath = Join-Path $dist ('tv-binge-board-release-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.zip')
}

$tempRoot = Join-Path ([System.IO.Path]::GetTempPath()) ('tvbb-release-' + [guid]::NewGuid().ToString('N'))
$tempProject = Join-Path $tempRoot 'tv-binge-board'
New-Item -ItemType Directory -Path $tempProject -Force | Out-Null

try {
    $files = Get-ChildItem -Path $project -File -Recurse | Where-Object {
        $relative = [System.IO.Path]::GetRelativePath($project, $_.FullName)
        -not (Test-ExcludedReleaseFile -RelativePath $relative -KeepPlaceholders:$IncludePlaceholders.IsPresent)
    }

    foreach ($file in $files) {
        $relative = [System.IO.Path]::GetRelativePath($project, $file.FullName)
        $target = Join-Path $tempProject $relative
        New-Item -ItemType Directory -Path (Split-Path -Parent $target) -Force | Out-Null
        Copy-Item -Path $file.FullName -Destination $target -Force
    }

    if (Test-Path $OutputPath) { Remove-Item $OutputPath -Force }
    Compress-Archive -Path (Join-Path $tempRoot 'tv-binge-board') -DestinationPath $OutputPath -Force

    $outputItem = Get-Item $OutputPath
    [pscustomobject]@{
        OutputPath = $outputItem.FullName
        SizeMB = [math]::Round($outputItem.Length / 1MB, 2)
        FileCount = $files.Count
        IncludePlaceholders = [bool]$IncludePlaceholders
    } | Format-List

    Write-Host 'Upload the tv-binge-board folder inside the ZIP to the target hosting path.' -ForegroundColor Cyan
    Write-Host 'Keep the server config.local.php and live data folder in place.' -ForegroundColor Yellow

    if ($OpenFolder) {
        Start-Process explorer.exe -ArgumentList "/select,`"$($outputItem.FullName)`""
    }
}
finally {
    if (Test-Path $tempRoot) { Remove-Item $tempRoot -Recurse -Force }
}
