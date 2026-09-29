<#
.SYNOPSIS
    Synchronizes reviewed article text to existing tracked WordPress posts.
.DESCRIPTION
    Revision: 1.0.0 (2026-09-29). Dry-run by default. Never creates posts.
    Only title, excerpt and content are sent in updates. Existing authors,
    slugs, dates, statuses, featured images and taxonomy are left untouched.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)][ValidatePattern('^https://')][string]$SiteUrl,
    [string]$Username = $env:WORDPRESS_USERNAME,
    [string]$AppPassword = $env:WORDPRESS_APP_PASSWORD,
    [string]$PostsPath = (Join-Path $PSScriptRoot '../posts'),
    [string]$StatePath = (Join-Path $PSScriptRoot 'wordpress-state.json'),
    [string[]]$Slug,
    [string]$BackupPath = (Join-Path ([Environment]::GetFolderPath('UserProfile')) '.wordpress-content-backups'),
    [switch]$Commit
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
if ([string]::IsNullOrWhiteSpace($Username) -or [string]::IsNullOrWhiteSpace($AppPassword)) {
    throw 'Read-only preflight requires WORDPRESS_USERNAME and WORDPRESS_APP_PASSWORD.'
}
$base = $SiteUrl.TrimEnd('/')
$target = [uri]$base
if ($target.UserInfo -or $target.Query -or $target.Fragment) { throw 'SiteUrl must be a clean HTTPS site URL.' }
$headers = @{ Authorization = 'Basic ' + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("$Username`:$AppPassword")) }
function Read-WpPost([int]$Id) {
    Invoke-RestMethod -Method Get -Uri "$base/wp-json/wp/v2/posts/$($Id)?context=edit" -Headers $headers -MaximumRedirection 0
}
function Get-PostSnapshot($Post) {
    # Detect content edits and other changes between preflight and the write.
    [ordered]@{
        id=$Post.id; slug=$Post.slug; status=$Post.status; author=$Post.author
        date=$Post.date; date_gmt=$Post.date_gmt; modified_gmt=$Post.modified_gmt
        featured_media=$Post.featured_media; tags=@($Post.tags); categories=@($Post.categories)
        title=$Post.title.raw; excerpt=$Post.excerpt.raw; content=$Post.content.raw
    } | ConvertTo-Json -Depth 20 -Compress
}
$state = Get-Content -LiteralPath $StatePath -Raw | ConvertFrom-Json
$keys = @($state.posts.PSObject.Properties.Name | Sort-Object)
if ($Slug) {
    foreach ($name in $Slug) { if ($name -cnotin $keys) { throw "Untracked slug: $name" } }
    $keys = @($keys | Where-Object { $_ -cin $Slug })
}
$plan = @()
$unchanged = 0
$ids = @{}
foreach ($key in $keys) {
    if ($key -notmatch '^[a-z0-9]+(?:-[a-z0-9]+)*$') { throw "Invalid tracked slug: $key" }
    $entry = $state.posts.$key
    $id = [int]$entry.wordpress_post_id
    if ($id -le 0 -or $ids.ContainsKey($id)) { throw "Missing or duplicate tracked post ID: $key" }
    $ids[$id] = $true
    $recordedUrl = [uri]$entry.wordpress_url
    if (-not $recordedUrl.IsAbsoluteUri -or $recordedUrl.Scheme -ne 'https' -or $recordedUrl.Authority -ne $target.Authority) {
        throw "State file belongs to a different WordPress site: $key"
    }
    $post = Get-Content -LiteralPath (Join-Path $PostsPath "$key.json") -Raw | ConvertFrom-Json
    if ($post.slug -cne $key) { throw "Source slug mismatch: $key" }
    foreach ($field in @('title','excerpt','content_html')) {
        if ([string]::IsNullOrWhiteSpace([string]$post.$field)) { throw "Missing $field in $key" }
    }
    $content = [regex]::Replace([string]$post.content_html,
        '(?is)^\s*<figure\b[^>]*class=["''][^"'']*\bpost-cover\b[^"'']*["''][^>]*>.*?</figure>\s*', '')
    if ([string]::IsNullOrWhiteSpace($content)) { throw "Empty WordPress body: $key" }
    $remote = Read-WpPost $id
    if ($remote.id -ne $id -or $remote.slug -cne $key) { throw "WordPress ID/slug mismatch: $key" }
    if ($remote.status -notin @('publish','future','draft','pending')) { throw "Unsupported post status for $key" }
    $body = [ordered]@{ title=[string]$post.title; excerpt=[string]$post.excerpt; content=$content }
    $changed = @('title','excerpt','content' | Where-Object { $body[$_] -cne $remote.$_.raw })
    if ($changed.Count -eq 0) { $unchanged++; continue }
    $plan += [pscustomobject]@{ Id=$id; Slug=$key; Status=$remote.status; Fields=($changed -join ', '); Body=$body; Before=$remote }
}
$plan | Select-Object Id,Slug,Status,Fields | Format-Table -AutoSize | Out-Host
Write-Host "Changed: $($plan.Count); unchanged: $unchanged."
if (-not $Commit) { Write-Host 'DRY RUN: GET requests only. No posts or backup files changed.'; return }
if ($plan.Count -eq 0) { return }
# Save all original raw content before any WordPress mutation. Backups stay outside
# the repository/web root by default, because they may contain draft content.
New-Item -ItemType Directory -Path $BackupPath -Force | Out-Null
$backupFile = Join-Path $BackupPath ("content-{0}-{1}.json" -f (Get-Date -Format 'yyyyMMdd-HHmmss'), [guid]::NewGuid())
[ordered]@{ site=$base; captured_utc=[DateTime]::UtcNow.ToString('o'); posts=@($plan | ForEach-Object { $_.Before }) } |
    ConvertTo-Json -Depth 30 | Set-Content -LiteralPath $backupFile -Encoding utf8
Write-Host "Original content backup: $backupFile"
foreach ($item in $plan) {
    $fresh = Read-WpPost $item.Id
    if ((Get-PostSnapshot $fresh) -cne (Get-PostSnapshot $item.Before)) {
        throw "Post changed since preflight; stopped before updating $($item.Slug). Rerun the preview. Earlier updates, if any, are already applied."
    }
    $result = Invoke-RestMethod -Method Post -Uri "$base/wp-json/wp/v2/posts/$($item.Id)" -Headers $headers -MaximumRedirection 0 `
        -ContentType 'application/json; charset=utf-8' -Body ($item.Body | ConvertTo-Json -Depth 10 -Compress)
    foreach ($field in @('id','slug','status','author','date','date_gmt','featured_media')) {
        if ([string]$result.$field -cne [string]$item.Before.$field) {
            throw "WordPress changed $field unexpectedly for $($item.Slug). Stopped; inspect the post and backup before continuing."
        }
    }
    Write-Host "Updated content: $($item.Slug) (ID $($item.Id))"
}
