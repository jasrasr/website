# Revision: 1.0.0 — 2026-09-29. Shared, deterministic public revision rendering.
function Get-BlogRevisionContent {
    param([Parameter(Mandatory)]$Post)
    $start = '<!-- blog-revision-history:start -->'
    $end = '<!-- blog-revision-history:end -->'
    $content = [string]$Post.content_html
    $starts = [regex]::Matches($content, [regex]::Escape($start)).Count
    $ends = [regex]::Matches($content, [regex]::Escape($end)).Count
    if ($starts -ne $ends -or $starts -gt 1) { throw 'Invalid revision-history markers.' }
    if ($starts -eq 1) {
        $content = [regex]::Replace($content, '(?s)\s*<!-- blog-revision-history:start -->.*?<!-- blog-revision-history:end -->\s*$', '')
        if ($content.Contains($start)) { throw 'Revision history must be the final article section.' }
    }
    if (-not $Post.PSObject.Properties['revision_history'] -or @($Post.revision_history).Count -eq 0) {
        if ($starts) { throw 'History block exists without revision records.' }
        return $content
    }
    $seen = @{}
    foreach ($entry in $Post.revision_history) {
        if ($entry.id -cnotmatch '^[a-z0-9]+(?:-[a-z0-9]+)*$' -or $seen.ContainsKey($entry.id)) {
            throw "Invalid or duplicate revision ID: $($entry.id)"
        }
        $seen[$entry.id] = $true
        $parsed = [datetime]::ParseExact([string]$entry.date, 'yyyy-MM-dd', [Globalization.CultureInfo]::InvariantCulture)
        if ([string]::IsNullOrWhiteSpace([string]$entry.summary) -or $entry.summary.Length -gt 600) { throw 'Revision summary must contain 1–600 characters.' }
        if ($entry.summary -match '(?i)\b(I|me|my|mine|myself|we|us|our|ours|ourselves)\b') { throw 'Revision summaries must use neutral third-person narration.' }
        if ($entry.source_url -cnotmatch '^https://github\.com/jasrasr/website/(pull/[1-9][0-9]*|commit/[0-9a-f]{40})$') {
            throw 'Revision source must be a jasrasr/website PR or full commit URL.'
        }
    }
    $ordered = @($Post.revision_history | Sort-Object -Property @{Expression='date'; Descending=$true}, @{Expression='id'; Descending=$false})
    $items = foreach ($entry in $ordered) {
        $summary = [Net.WebUtility]::HtmlEncode([string]$entry.summary)
        $url = [Net.WebUtility]::HtmlEncode([string]$entry.source_url)
        '<li id="revision-{0}"><time datetime="{1}">{1}</time> — {2} <a href="{3}">GitHub source</a></li>' -f $entry.id, $entry.date, $summary, $url
    }
    $block = @($start, '<section class="revision-history" aria-label="Article update history">', '<h2>Update history</h2>',
        '<p>These notes record article updates associated with repository changes. The original publication date is retained.</p>',
        '<ul>', ($items -join "`n"), '</ul>', '</section>', $end) -join "`n"
    $content.TrimEnd() + "`n`n" + $block + "`n"
}
