<#
.SYNOPSIS
    Adds a short public revision note to an existing tracked project article.
.DESCRIPTION
    Revision: 1.0.0 — 2026-09-29. Edits local JSON only; no WordPress calls.
    Include this source change in the same PR as the relevant project change.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)][ValidatePattern('^[a-z0-9]+(?:-[a-z0-9]+)*$')][string]$Slug,
    [Parameter(Mandatory)][string]$Id,
    [Parameter(Mandatory)][string]$Summary,
    [Parameter(Mandatory)][string]$SourceUrl,
    [string]$Date = ([TimeZoneInfo]::ConvertTimeBySystemTimeZoneId([datetimeoffset]::UtcNow, 'America/New_York').ToString('yyyy-MM-dd')),
    [string]$RootPath = (Split-Path $PSScriptRoot -Parent)
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'Blog-Revisions.ps1')
$state = Get-Content (Join-Path $RootPath 'tools/wordpress-state.json') -Raw | ConvertFrom-Json
if ($Slug -cnotin @($state.posts.PSObject.Properties.Name)) { throw "Untracked article: $Slug" }
$path = Join-Path $RootPath "posts/$Slug.json"
$post = Get-Content -LiteralPath $path -Raw | ConvertFrom-Json
$history = @()
if ($post.PSObject.Properties['revision_history']) { $history = @($post.revision_history) }
$new = [pscustomobject][ordered]@{id=$Id; date=$Date; summary=$Summary; source_url=$SourceUrl}
$existing = @($history | Where-Object id -eq $Id)
if ($existing.Count) {
    foreach ($key in @('id','date','summary','source_url')) {
        if ($existing[0].$key -cne $new.$key) { throw "Revision ID '$Id' already exists with different content. Add a new correction entry instead." }
    }
    Write-Host "Revision already present: $Id"
    return
}
$post | Add-Member -NotePropertyName revision_history -NotePropertyValue @($history + $new) -Force
$post.content_html = Get-BlogRevisionContent $post
$post.updated = (@($post.revision_history.date) | Sort-Object -Descending | Select-Object -First 1)
$post._meta.modified_date = $Date
$post | ConvertTo-Json -Depth 30 | Set-Content -LiteralPath $path -Encoding utf8
Write-Host "Added $Id to $Slug. Commit the JSON with the project change; main-branch automation syncs the existing WordPress post."
