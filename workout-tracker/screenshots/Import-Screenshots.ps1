# File Name: Import-Screenshots.ps1
# Author: Jason Lamb (with help from ChatGPT)
# Description: Import the six supplied screenshot images from the downloadable archive.
# Revision: 1.0.0
# Created Date: 2026-10-21
# Modified Date: 2026-10-21
# Changelog: 1.0.0 - Safe screenshot import with filename verification.
[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [ValidateScript({ Test-Path -LiteralPath $_ -PathType Leaf })]
    [string]$ArchivePath
)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '../..')).Path
$expected = @(
    '01-dashboard-overview.webp',
    '02-natural-language-entry.webp',
    '03-push-ups-entry.webp',
    '04-recognized-review.webp',
    '05-progress-trends.webp',
    '06-manual-entry.webp'
)
$temp = Join-Path ([System.IO.Path]::GetTempPath()) ('workout-screenshots-' + [guid]::NewGuid().ToString('N'))
try {
    New-Item -ItemType Directory -Path $temp | Out-Null
    Expand-Archive -LiteralPath (Resolve-Path -LiteralPath $ArchivePath).Path -DestinationPath $temp
    $nested = Join-Path $temp 'workout-tracker/screenshots'
    foreach ($filename in $expected) {
        if (-not (Test-Path -LiteralPath (Join-Path $nested $filename) -PathType Leaf)) {
            throw "Missing screenshot in archive: $filename"
        }
        if (Test-Path -LiteralPath (Join-Path $PSScriptRoot $filename)) {
            throw "Target screenshot already exists; nothing overwritten: $filename"
        }
    }
    foreach ($filename in $expected) {
        Copy-Item -LiteralPath (Join-Path $nested $filename) -Destination (Join-Path $PSScriptRoot $filename)
    }
    Write-Host "Imported $($expected.Count) screenshot files. Review and commit on the PR branch."
    Write-Host "Repo: $repoRoot"
    Write-Host 'git add -- workout-tracker/screenshots/*.webp'
    Write-Host 'git commit -m "Add Workout Tracker mobile screenshots"'
    Write-Host 'git push'
} finally {
    if (Test-Path -LiteralPath $temp) {
        Remove-Item -LiteralPath $temp -Recurse -Force
    }
}
