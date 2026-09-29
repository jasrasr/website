# Revision: 1.0.0 — 2026-09-29. Check the tracked automated project series.
param([string]$RootPath = (Split-Path $PSScriptRoot -Parent))
$ErrorActionPreference = 'Stop'
$state = Get-Content (Join-Path $RootPath 'tools/wordpress-state.json') -Raw | ConvertFrom-Json
$failures = @()
foreach ($key in $state.posts.PSObject.Properties.Name) {
    $post = Get-Content (Join-Path $RootPath "posts/$key.json") -Raw | ConvertFrom-Json
    foreach ($field in @('title','excerpt','content_html')) {
        $text = [string]$post.$field
        $text = $text -replace '(?is)<(code|pre)\b[^>]*>.*?</\1>', ' '
        $text = [System.Net.WebUtility]::HtmlDecode(($text -replace '<[^>]*>', ' '))
        $text = $text -replace 'https?://\S+|\b[\w-]+\.(?:me|com|org|net)\b', ' '
        if ($text -match '(?i)\b(I|me|my|mine|myself|we|us|our|ours|ourselves)\b') {
            $failures += "$key ($field): $($Matches[0])"
        }
    }
    if ($post.cover.alt -match '(?i)\b(I|me|my|mine|myself|we|us|our|ours|ourselves)\b') {
        $failures += "$key (cover.alt): $($Matches[0])"
    }
}
if ($failures.Count) { throw ($failures -join "`n") }
Write-Host "PASS: $(@($state.posts.PSObject.Properties).Count) automated articles use neutral narration."
