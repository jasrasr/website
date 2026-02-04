<#
# filename: Restore-Manifest.ps1
# author: Jason Lamb (with help from ChatGPT)
# created date: 2026-02-04
# modified date: 2026-02-04
# revision: 1.0
# changelog:
# - 1.0: Restore admin/media-manifest.json from a selected backup file
#>

param(
    [Parameter(Mandatory = $true)]
    [string]$BackupPath,

    [Parameter(Mandatory = $false)]
    [string]$RootPath = (Resolve-Path ".").Path
)

$adminDir = Join-Path $RootPath "admin"
$manifestPath = Join-Path $adminDir "media-manifest.json"
$backupsDir = Join-Path $adminDir "backups"

if (-not (Test-Path $BackupPath)) {
    throw ("BackupPath not found: 0" -f $BackupPath)
}

if (-not (Test-Path $adminDir)) {
    throw ("Admin folder not found: 0" -f $adminDir)
}

if (-not (Test-Path $backupsDir)) {
    New-Item -ItemType Directory -Path $backupsDir -Force | Out-Null
}

# Backup current manifest before restore (if present)
if (Test-Path $manifestPath) {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $pre = Join-Path $backupsDir ("media-manifest-pre-restore-0.json" -f $stamp)
    Copy-Item -Path $manifestPath -Destination $pre -Force
    Write-Host ("Backed up current manifest to: 0" -f $pre)
}

Copy-Item -Path $BackupPath -Destination $manifestPath -Force
Write-Host ("Restored manifest from: 0" -f $BackupPath)
Write-Host ("Manifest path: 0" -f $manifestPath)

<#
EXAMPLE USAGE:

# Restore from a backup file:
# .\tools\Restore-Manifest.ps1 -BackupPath ".\admin\backups\media-manifest-20260204-101500.json"

#>
