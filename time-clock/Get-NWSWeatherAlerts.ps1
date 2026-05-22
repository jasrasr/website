# Filename: Get-NWSWeatherAlerts.ps1
# Revision : 1.0.0
# Description : Fetches live NWS weather alerts for a given zone, saves raw JSON, generates an HTML demo, and opens it in the browser
# Author : Jason Lamb (with help from Claude Code CLI)
# Created Date : 2026-05-22
# Modified Date : 2026-05-22
# Changelog :
# 1.0.0 initial release

param (
    [string]$Zone     = "LAC103",
    [string]$AreaName = "St. Tammany Parish, LA",
    [switch]$NoBrowser
)

$exportBase = "C:\Users\Jason.Lamb\OneDrive - Cooper Machinery Services\powershell-exports"
$timestamp  = Get-Date -Format "yyyyMMdd_HHmmss"
$jsonFile   = "$exportBase\NWS_Alerts_${Zone}_$timestamp.json"
$htmlFile   = "$exportBase\NWS_Alerts_${Zone}_$timestamp.html"

# --- Fetch ---
Write-Host "Fetching NWS alerts for zone $Zone ($AreaName)..." -ForegroundColor Cyan
try {
    $response = Invoke-RestMethod `
        -Uri "https://api.weather.gov/alerts/active?zone=$Zone" `
        -Headers @{ 'User-Agent' = 'NWSAlertViewer/1.0 jason.lamb@cooperservices.com'; 'Accept' = 'application/geo+json' } `
        -ErrorAction Stop
} catch {
    Write-Host "ERROR: Failed to fetch NWS data — $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

# --- Save JSON ---
$response | ConvertTo-Json -Depth 20 | Out-File $jsonFile -Encoding UTF8
Write-Host "JSON saved: $jsonFile" -ForegroundColor Green
Write-Host "Alerts found: $($response.features.Count)" -ForegroundColor $(if ($response.features.Count -gt 0) { 'Yellow' } else { 'Green' })

# --- Build HTML ---
function Get-SeverityColor($severity, $event) {
    switch -Wildcard ($event) {
        "*Tornado Warning*"             { return "#8B0000" }
        "*Flash Flood Emergency*"       { return "#8B0000" }
        "*Severe Thunderstorm Warning*" { return "#CC0000" }
        "*Flash Flood Warning*"         { return "#9B0000" }
        "*Tornado Watch*"               { return "#FF6600" }
        "*Severe Thunderstorm Watch*"   { return "#DB7B00" }
    }
    switch ($severity) {
        "Extreme"  { return "#8B0000" }
        "Severe"   { return "#CC0000" }
        "Moderate" { return "#E65C00" }
        "Minor"    { return "#9B7800" }
        default    { return "#555555" }
    }
}

function Format-NWSTime($isoString) {
    if ([string]::IsNullOrWhiteSpace($isoString)) { return "N/A" }
    try {
        $dt = [System.DateTimeOffset]::Parse($isoString)
        return $dt.ToLocalTime().ToString("MMM d 'at' h:mm tt") + " CDT"
    } catch { return $isoString }
}

function Escape-Html($text) {
    if ([string]::IsNullOrWhiteSpace($text)) { return "" }
    $text -replace '&','&amp;' -replace '<','&lt;' -replace '>','&gt;' -replace '"','&quot;'
}

$fetchedAt = Get-Date -Format "MMMM d, yyyy h:mm tt 'CDT'"
$alertCards = ""

if ($response.features.Count -eq 0) {
    $alertCards = @"
<div class="no-alerts">
  <span>&#10003;</span>
  <p>No active weather alerts for $AreaName at this time.</p>
</div>
"@
} else {
    foreach ($feature in $response.features) {
        $p         = $feature.properties
        $color     = Get-SeverityColor $p.severity $p.event
        $event     = Escape-Html $p.event
        $headline  = Escape-Html $p.headline
        $nwsHead   = Escape-Html ($p.parameters.NWSheadline -join " ")
        $desc      = Escape-Html $p.description
        $instr     = Escape-Html $p.instruction
        $areas     = ($p.areaDesc -split ";") | ForEach-Object { $_.Trim() } | Where-Object { $_ }
        $issued    = Format-NWSTime $p.sent
        $expires   = Format-NWSTime $p.expires
        $severity  = Escape-Html $p.severity
        $certainty = Escape-Html $p.certainty
        $sender    = Escape-Html $p.senderName

        $hazards = @()
        if ($p.parameters.maxWindGust)  { $hazards += "Wind Gusts: $($p.parameters.maxWindGust -join ', ')" }
        if ($p.parameters.maxHailSize)  { $hazards += "Hail: $($p.parameters.maxHailSize -join ', ')&quot;" }

        $areaTags = ($areas | ForEach-Object {
            $cls = if ($_ -match "St\. Tammany|$([regex]::Escape($AreaName.Split(',')[0]))") { 'tag highlight' } else { 'tag' }
            "<span class='$cls'>$(Escape-Html $_)</span>"
        }) -join "`n      "

        $hazardTags = if ($hazards.Count -gt 0) {
            "<div class='section-title'>Hazards</div><div class='tag-row'>" +
            (($hazards | ForEach-Object { "<span class='tag highlight'>$_</span>" }) -join "`n      ") +
            "</div>"
        } else { "" }

        $instrBlock = if (-not [string]::IsNullOrWhiteSpace($p.instruction)) {
            "<div class='section-title'>Instructions</div><div class='section-value'>$instr</div>"
        } else { "" }

        $alertCards += @"
<div class="alert-card">
  <div class="alert-banner" style="background:$color">
    <div class="event-type">$event</div>
    <div class="headline">$headline</div>
    <div class="issuer">&#9651; $sender</div>
  </div>
  <div class="expires-bar">
    <span>Issued: <span class="val">$issued</span></span>
    <span>Expires: <span class="val">$expires</span></span>
    <span>Severity: <span class="val">$severity</span> &nbsp;|&nbsp; Certainty: <span class="val">$certainty</span></span>
  </div>
  <div class="alert-body">
    $(if ($nwsHead) { "<div class='section-title'>NWS Headline</div><div class='section-value'>$nwsHead</div>" })
    <div class="section-title">Areas Affected</div>
    <div class="tag-row">
      $areaTags
    </div>
    $hazardTags
    <hr class="divider" style="margin:16px 0">
    <div class="section-title">Description</div>
    <div class="section-value">$desc</div>
    $instrBlock
  </div>
</div>
"@
    }
}

$html = @"
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NWS Alerts – $AreaName</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #1a1a1a; color: #fff; min-height: 100vh; padding: 20px; }
  h1.page-title { text-align: center; font-size: 1rem; color: #aaa; margin-bottom: 10px; letter-spacing: .05em; text-transform: uppercase; }
  .meta { text-align: center; font-size: .75rem; color: #777; margin-bottom: 24px; }
  .json-link { display: block; text-align: center; margin: 0 auto 24px; font-size: .8rem; color: #5b9bd5; text-decoration: none; }
  .json-link:hover { text-decoration: underline; }
  .alert-card { border-radius: 8px; margin-bottom: 24px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,.5); }
  .alert-banner { padding: 32px 40px; text-align: center; }
  .alert-banner .event-type { font-size: 2.8rem; font-weight: 900; text-transform: uppercase; line-height: 1.1; letter-spacing: .02em; text-shadow: 0 2px 6px rgba(0,0,0,.3); }
  .alert-banner .headline { margin-top: 14px; font-size: 1rem; font-weight: 600; opacity: .95; }
  .alert-banner .issuer { margin-top: 6px; font-size: .8rem; opacity: .7; letter-spacing: .08em; text-transform: uppercase; }
  .expires-bar { background: #1a1a1a; padding: 10px 32px; font-size: .78rem; color: #aaa; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px; }
  .expires-bar span.val { color: #fff; font-weight: 600; }
  .alert-body { background: #2a2a2a; padding: 24px 32px; }
  .section-title { font-size: .7rem; text-transform: uppercase; letter-spacing: .12em; color: #888; margin-bottom: 6px; margin-top: 18px; }
  .section-title:first-child { margin-top: 0; }
  .section-value { font-size: .92rem; color: #e0e0e0; line-height: 1.6; white-space: pre-wrap; }
  .tag-row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
  .tag { background: #3a3a3a; border-radius: 4px; padding: 3px 10px; font-size: .78rem; color: #ccc; }
  .tag.highlight { background: #4a3000; color: #ffbf47; font-weight: 600; }
  hr.divider { border: none; border-top: 1px solid #3a3a3a; }
  .no-alerts { text-align: center; padding: 60px 20px; color: #5a5; font-size: 1.2rem; }
  .no-alerts span { font-size: 3rem; display: block; margin-bottom: 12px; }
  footer { text-align: center; font-size: .72rem; color: #555; margin-top: 32px; }
</style>
</head>
<body>
<h1 class="page-title">&#9888; National Weather Service – Active Alerts</h1>
<p class="meta">$AreaName &nbsp;|&nbsp; Zone: $Zone &nbsp;|&nbsp; Fetched: $fetchedAt</p>
<a class="json-link" href="$(Split-Path $jsonFile -Leaf)" target="_blank">&#128196; View raw JSON &rarr;</a>
$alertCards
<footer>Source: api.weather.gov &nbsp;|&nbsp; Zone $Zone &nbsp;|&nbsp; Data fetched live $fetchedAt</footer>
</body>
</html>
"@

$html | Out-File $htmlFile -Encoding UTF8
Write-Host "HTML saved: $htmlFile" -ForegroundColor Green

if (-not $NoBrowser) {
    Start-Process $htmlFile
    Write-Host "Opened in browser." -ForegroundColor Cyan
}

# Example Usage:
#   .\Get-NWSWeatherAlerts.ps1
#   .\Get-NWSWeatherAlerts.ps1 -Zone "LAC103" -AreaName "St. Tammany Parish, LA"
#   .\Get-NWSWeatherAlerts.ps1 -Zone "TXC113" -AreaName "Dallas County, TX"
#   .\Get-NWSWeatherAlerts.ps1 -Zone "LAC103" -NoBrowser
