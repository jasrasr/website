<#
# filename: Build-Media.ps1
# author: Jason Lamb (with help from ChatGPT)
# created date: 2026-02-04
# modified date: 2026-02-04
# revision: 1.0
# changelog:
# - 1.0: Create WordPress-ish media folders (YYYY/MM), re-encode originals, generate derivatives, and return srcset helper data
#>

param(
    [Parameter(Mandatory = $true)]
    [string]$InputPath,

    [Parameter(Mandatory = $false)]
    [string]$RootPath = (Resolve-Path ".").Path,

    [Parameter(Mandatory = $false)]
    [int[]]$Sizes = @(320, 640, 960, 1600),

    [Parameter(Mandatory = $false)]
    [string]$Key = "",

    [Parameter(Mandatory = $false)]
    [string]$Alt = ""
)

function Get-SafeKey {
    param([string]$s)
    $s = ($s ?? "").Trim().ToLower()
    $s = ($s -replace '[^a-z0-9_-]', '-')
    $s = ($s -replace '-{2,}', '-').Trim('-')
    return $s
}

if (-not (Test-Path $InputPath)) {
    throw ("InputPath not found: 0" -f $InputPath)
}

$now = Get-Date
$year = $now.ToString("yyyy")
$month = $now.ToString("MM")

$mediaRoot = Join-Path $RootPath "media"
$origDir = Join-Path $mediaRoot (Join-Path "originals" (Join-Path $year $month))
$derDir  = Join-Path $mediaRoot (Join-Path "derivatives" (Join-Path $year $month))

if (-not (Test-Path $origDir)) { New-Item -ItemType Directory -Path $origDir -Force | Out-Null }
if (-not (Test-Path $derDir))  { New-Item -ItemType Directory -Path $derDir -Force | Out-Null }

if (-not $Key) {
    $Key = Get-SafeKey ([IO.Path]::GetFileNameWithoutExtension($InputPath))
} else {
    $Key = Get-SafeKey $Key
}

if (-not $Key) {
    $Key = "image-0" -f $now.ToString("yyyyMMdd-HHmmss")
}

$origDisk = Join-Path $origDir ("0.jpg" -f $Key)

# Copy input to original path then re-encode as safe JPEG
Copy-Item -Path $InputPath -Destination $origDisk -Force

$cmd = @(
    "magick",
    "`"0`"" -f $origDisk,
    "-strip",
    "-auto-orient",
    "-quality", "90",
    "`"0`"" -f $origDisk
) -join " "

Write-Host ("Re-encoding original: 0" -f $origDisk)
cmd /c $cmd | Out-Null

$derivatives = @{}
foreach ($w in $Sizes) {
    $derDisk = Join-Path $derDir ("0_1w.jpg" -f $Key, $w)

    $cmd2 = @(
        "magick",
        "`"0`"" -f $origDisk,
        "-strip",
        "-auto-orient",
        "-resize", "0x" -f $w,
        "-quality", "85",
        "`"0`"" -f $derDisk
    ) -join " "

    Write-Host ("Creating derivative 0w: 1" -f $w, $derDisk)
    cmd /c $cmd2 | Out-Null

    $derivatives["$w"] = "/media/derivatives/$year/$month/0_1w.jpg" -f $Key, $w
}

$result = [pscustomobject]@{
    key        = $Key
    original   = "/media/originals/$year/$month/0.jpg" -f $Key
    derivatives= $derivatives
    uploaded   = $now.ToString("o")
    alt        = $Alt
}

$result

<#
EXAMPLE USAGE:

# From repo root:
# .\tools\Build-Media.ps1 -InputPath ".\media\_inbox\photo.png" -Alt "Example image"

# Return values can be captured:
# $r = .\tools\Build-Media.ps1 -InputPath ".\media\_inbox\photo.png"
# $r.key
# $r.derivatives["640"]

#>
