<#
File: Build-Blog.ps1
Author: Jason Lamb (with help from ChatGPT)
Created: 2026-02-03
Modified: 2026-02-03
Revision: 1.0
Change Log:
- 1.0: Generate posts/index.json from individual post JSON files
#>

param(
    [Parameter(Mandatory = $false)]
    [string]$RootPath = (Resolve-Path ".").Path,

    [Parameter(Mandatory = $false)]
    [string]$SiteBaseUrl = "https://example.com"
)

$postsFolder = Join-Path $RootPath "posts"
$indexPath   = Join-Path $postsFolder "index.json"

if (-not (Test-Path $postsFolder)) {
    throw ("Posts folder not found: {0}" -f $postsFolder)
}

# Load all post JSON files except index and template
$postFiles = Get-ChildItem -Path $postsFolder -Filter "*.json" -File |
    Where-Object {
        $_.Name -ne "index.json" -and $_.Name -ne "_template.json"
    }

$posts = foreach ($f in $postFiles) {
    try {
        $raw = Get-Content -Path $f.FullName -Raw
        $p = $raw | ConvertFrom-Json

        if (-not $p.slug) { continue }
        if (-not $p.title) { $p.title = $p.slug }

        # Create excerpt from first ~140 chars of stripped HTML if missing
        $excerpt = $p.excerpt
        if (-not $excerpt) {
            $text = ($p.content_html -replace '<[^>]*>', ' ') -replace '\s+', ' '
            $text = $text.Trim()
            if ($text.Length -gt 140) { $text = $text.Substring(0, 140) + "…" }
            $excerpt = $text
        }

        [pscustomobject]@{
            slug    = [string]$p.slug
            title   = [string]$p.title
            date    = [string]$p.date
            tags    = @($p.tags)
            excerpt = [string]$excerpt
        }
    } catch {
        Write-Host ("Skipping invalid JSON: {0}" -f $f.FullName)
    }
}

# Sort newest first (string sort works for YYYY-MM-DD)
$postsSorted = $posts | Sort-Object -Property date -Descending

$indexObj = [pscustomobject]@{
    blog_title = "Jason’s Flat-File Blog"
    generated  = (Get-Date).ToUniversalTime().ToString("o")
    posts      = @($postsSorted)
}

$indexJson = $indexObj | ConvertTo-Json -Depth 6
$indexJson | Set-Content -Path $indexPath -Encoding UTF8

Write-Host ("Wrote index: {0}" -f $indexPath)

# (Optional) Extend this script to generate rss.xml and sitemap.xml
# using $SiteBaseUrl and the $postsSorted list.

<#
EXAMPLE USAGE:

# From the repository root:
# . .\tools\Build-Blog.ps1
# Build-Blog.ps1 -RootPath "." -SiteBaseUrl "https://jasr.me/blog"

#>
