# Revision: 1.0.0 — 2026-09-29. Validate public history and optional append-only baseline.
param([string]$RootPath = (Split-Path $PSScriptRoot -Parent), [string]$BaseRef)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'Blog-Revisions.ps1')
$state = Get-Content (Join-Path $RootPath 'tools/wordpress-state.json') -Raw | ConvertFrom-Json
foreach ($slug in $state.posts.PSObject.Properties.Name) {
    $post = Get-Content (Join-Path $RootPath "posts/$slug.json") -Raw | ConvertFrom-Json
    if (-not $post.PSObject.Properties['revision_history'] -or @($post.revision_history).Count -eq 0) { throw "Missing public history: $slug" }
    if ((Get-BlogRevisionContent $post) -cne $post.content_html) { throw "Stale history HTML: $slug. Regenerate with Blog-Revisions.ps1." }
    if ($BaseRef) {
        $oldText = & git show "${BaseRef}:blog/posts/$slug.json" 2>$null
        if ($LASTEXITCODE -ne 0) { throw "Could not read baseline article: $slug" }
        $old = ($oldText -join "`n") | ConvertFrom-Json
        $prior = @()
        if ($old.PSObject.Properties['revision_history']) { $prior = @($old.revision_history) }
        foreach ($entry in $prior) {
            $current = @($post.revision_history | Where-Object id -eq $entry.id)
            if ($current.Count -ne 1) { throw "Deleted revision: $slug / $($entry.id)" }
            foreach ($key in @('date','summary','source_url')) {
                if ($current[0].$key -cne $entry.$key) { throw "Edited revision: $slug / $($entry.id). Append a correction instead." }
            }
        }
        $changed = @('title','excerpt','content_html' | Where-Object { $post.$_ -cne $old.$_ })
        if ($changed.Count -and @($post.revision_history).Count -le $prior.Count) { throw "Article text changed without a new revision note: $slug" }
    }
}
# Independent renderer regressions: deterministic output, escaping, duplicate IDs, bad URLs.
$fixture = [pscustomobject]@{ content_html='<p>Article body.</p>'; revision_history=@(
    [pscustomobject]@{id='fixture';date='2026-09-29';summary='Updated <script>alert(1)</script> & text.';source_url='https://github.com/jasrasr/website/pull/90'}
) }
$html = Get-BlogRevisionContent $fixture
if ($html.Contains('<script>') -or -not $html.Contains('&lt;script&gt;')) { throw 'Revision HTML escaping failed.' }
$fixture.content_html=$html
if ((Get-BlogRevisionContent $fixture) -cne $html) { throw 'Rendering is not idempotent.' }
$fixture.revision_history += $fixture.revision_history[0]
$caught=$false
try { Get-BlogRevisionContent $fixture | Out-Null } catch { $caught=$true }
if (-not $caught) { throw 'Duplicate revision IDs were accepted.' }
$fixture.revision_history=@($fixture.revision_history[0])
$fixture.revision_history[0].source_url='javascript:alert(1)'
$caught=$false
try { Get-BlogRevisionContent $fixture | Out-Null } catch { $caught=$true }
if (-not $caught) { throw 'Unsafe source URL was accepted.' }
Write-Host 'PASS: all tracked histories, deterministic rendering, duplicate protection and HTML/link safety.'
