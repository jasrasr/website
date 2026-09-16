<#
# filename: Build-Blog.ps1
# author: Jason Lamb (with help from ChatGPT)
# created date: 2026-02-03
# modified date: 2026-09-16
# revision: 1.3.1
# changelog:
# - 1.3.1: Supports blog-relative media, extracts derivative widths, and fixes sitemap array construction.
# - 1.3.0: Preserves cover metadata in the generated index, fixes RSS/sitemap URL formatting, decodes HTML entities for search text, and creates manifest records for committed derivative images.
# - 1.2: Adds optional media processing + manifest maintenance (usage scan, backups, integrity + alt warnings); generates index/rss/sitemap with embedded _meta headers
# - 1.1: Adds build-time full-text search indexing (_searchText), and regenerates rss.xml + sitemap.xml with jasr.me/blog base URLs
# - 1.0: Generate posts/index.json from individual post JSON files
#>

param(
    [Parameter(Mandatory = $false)]
    [string]$RootPath = (Resolve-Path ".").Path,

    [Parameter(Mandatory = $false)]
    [string]$SiteBaseUrl = "https://jasr.me/blog",

    [Parameter(Mandatory = $false)]
    [switch]$ProcessMedia,

    [Parameter(Mandatory = $false)]
    [string]$MediaInboxRelative = "media/_inbox",

    [Parameter(Mandatory = $false)]
    [string]$ManifestRelative = "admin/media-manifest.json"
)

$postsFolder   = Join-Path $RootPath "posts"
$indexPath     = Join-Path $postsFolder "index.json"

$rssPath       = Join-Path $RootPath "rss.xml"
$sitemapPath   = Join-Path $RootPath "sitemap.xml"

$manifestPath  = Join-Path $RootPath $ManifestRelative
$backupsFolder = Join-Path (Split-Path $manifestPath -Parent) "backups"

if (-not (Test-Path $postsFolder)) {
    throw ("Posts folder not found: {0}" -f $postsFolder)
}

function Convert-HtmlToText {
    param([string]$Html)

    if (-not $Html) { return "" }

    $text = $Html -replace '<script[\s\S]*?</script>', ' '
    $text = $text -replace '<style[\s\S]*?</style>', ' '
    $text = $text -replace '<[^>]*>', ' '
    $text = [System.Net.WebUtility]::HtmlDecode($text)
    $text = $text -replace '\s+', ' '
    return $text.Trim()
}

function XmlEscape {
    param([string]$s)
    if ($null -eq $s) { return "" }
    return $s.Replace('&','&amp;').Replace('<','&lt;').Replace('>','&gt;').Replace('"','&quot;').Replace("'","&apos;")
}

function Get-SafeKey {
    param([string]$s)
    $s = ($s ?? "").Trim().ToLower()
    $s = ($s -replace '[^a-z0-9_-]', '-')
    $s = ($s -replace '-{2,}', '-').Trim('-')
    return $s
}

function WebPathToDisk {
    param([string]$WebPath)
    if (-not $WebPath) { return "" }
    $p = $WebPath.Trim()
    if ($p.StartsWith("/")) {
        $p = $p.TrimStart("/").Replace("/", [IO.Path]::DirectorySeparatorChar)
        return Join-Path $RootPath $p
    }
    return Join-Path $RootPath $p
}

function Load-Manifest {
    if (Test-Path $manifestPath) {
        try {
            return (Get-Content -Path $manifestPath -Raw | ConvertFrom-Json -ErrorAction Stop)
        } catch {
            Write-Host ("Manifest JSON invalid; starting fresh: {0}" -f $manifestPath)
        }
    }

    # Minimal skeleton (data file; not strict code header)
    return [pscustomobject]@{
        _meta = [pscustomobject]@{
            filename      = "media-manifest.json"
            author        = "Jason Lamb (with help from ChatGPT)"
            created_date  = "2026-02-04"
            modified_date = (Get-Date -Format "yyyy-MM-dd")
            revision      = "1.1.0"
            changelog     = @(
                "1.1.0: Adds committed derivative image manifest records from post cover references",
                "1.0: Initial admin-protected media manifest (non-public unless authenticated)"
            )
        }
        items = [pscustomobject]@{}
    }
}

function Save-Manifest {
    param([object]$Manifest)

    if (-not (Test-Path $backupsFolder)) {
        New-Item -ItemType Directory -Path $backupsFolder -Force | Out-Null
    }

    if (Test-Path $manifestPath) {
        $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
        $backup = Join-Path $backupsFolder ("media-manifest-{0}.json" -f $stamp)
        Copy-Item -Path $manifestPath -Destination $backup -Force
        Write-Host ("Backed up manifest: {0}" -f $backup)
    }

    if ($Manifest._meta) {
        $Manifest._meta.modified_date = (Get-Date -Format "yyyy-MM-dd")
        $Manifest._meta.revision = "1.1.0"
        $Manifest._meta.changelog = @(
            "1.1.0: Adds committed derivative image manifest records from post cover references",
            "1.0: Initial admin-protected media manifest (non-public unless authenticated)"
        )
    }

    $json = $Manifest | ConvertTo-Json -Depth 10
    $json | Set-Content -Path $manifestPath -Encoding UTF8
    Write-Host ("Wrote manifest: {0}" -f $manifestPath)
}

function Ensure-MediaManifestEntry {
    param(
        [Parameter(Mandatory = $true)]
        [object]$Manifest,

        [Parameter(Mandatory = $true)]
        [string]$Key,

        [Parameter(Mandatory = $true)]
        [string]$WebPath,

        [Parameter(Mandatory = $true)]
        [string]$Slug,

        [Parameter(Mandatory = $true)]
        [int]$Width,

        [Parameter(Mandatory = $false)]
        [string]$Alt = ""
    )

    $entry = $null
    try { $entry = $Manifest.items.$Key } catch { $entry = $null }

    if (-not $entry) {
        $Manifest.items | Add-Member -MemberType NoteProperty -Name $Key -Value ([pscustomobject]@{
            original    = ""
            derivatives = [pscustomobject]@{
                ([string]$Width) = $WebPath
            }
            uploaded    = ""
            used_in     = @()
            alt         = $Alt
            credit      = ""
            source      = "committed-derivative"
        }) -Force

        $entry = $Manifest.items.$Key
    }

    if (-not ($entry.PSObject.Properties.Name -contains "used_in")) {
        $entry | Add-Member -MemberType NoteProperty -Name used_in -Value @() -Force
    }

    if (-not ($entry.used_in -contains $Slug)) {
        $entry.used_in += $Slug
    }

    if (($entry.PSObject.Properties.Name -contains "alt") -and [string]::IsNullOrWhiteSpace([string]$entry.alt) -and -not [string]::IsNullOrWhiteSpace($Alt)) {
        $entry.alt = $Alt
    }

    if (-not ($entry.PSObject.Properties.Name -contains "derivatives") -or -not $entry.derivatives) {
        $entry | Add-Member -MemberType NoteProperty -Name derivatives -Value ([pscustomobject]@{}) -Force
    }

    if (-not ($entry.derivatives.PSObject.Properties.Name -contains ([string]$Width))) {
        $entry.derivatives | Add-Member -MemberType NoteProperty -Name ([string]$Width) -Value $WebPath -Force
    }
}

# ------------------------------------------------------------
# Media processing (optional): turn media/_inbox into originals + derivatives
# ------------------------------------------------------------
$manifest = Load-Manifest

# Ensure items is a hashtable-like object we can index into
if (-not $manifest.items) {
    $manifest | Add-Member -MemberType NoteProperty -Name items -Value ([pscustomobject]@{}) -Force
}

if ($ProcessMedia) {
    $inbox = Join-Path $RootPath $MediaInboxRelative
    $processor = Join-Path (Join-Path $RootPath "tools") "Build-Media.ps1"

    if (-not (Test-Path $inbox)) {
        Write-Host ("Media inbox not found (skipping): {0}" -f $inbox)
    } elseif (-not (Test-Path $processor)) {
        Write-Host ("Build-Media.ps1 not found (skipping): {0}" -f $processor)
    } else {
        $files = Get-ChildItem -Path $inbox -File | Where-Object { $_.Name -ne ".gitkeep" }
        if ($files.Count -eq 0) {
            Write-Host ("No files in inbox: {0}" -f $inbox)
        } else {
            $processedDir = Join-Path $inbox "_processed"
            if (-not (Test-Path $processedDir)) { New-Item -ItemType Directory -Path $processedDir -Force | Out-Null }

            foreach ($f in $files) {
                Write-Host ("Processing media file: {0}" -f $f.FullName)
                try {
                    $res = & $processor -InputPath $f.FullName -RootPath $RootPath
                    if ($res -and $res.key) {
                        $k = [string]$res.key

                        # Preserve existing used_in list if present
                        $existing = $null
                        try { $existing = $manifest.items.$k } catch { $existing = $null }

                        $used = @()
                        if ($existing -and $existing.used_in) { $used = @($existing.used_in) }

                        $manifest.items | Add-Member -MemberType NoteProperty -Name $k -Value ([pscustomobject]@{
                            original    = [string]$res.original
                            derivatives = $res.derivatives
                            uploaded    = (Get-Date).ToString("o")
                            used_in     = $used
                            alt         = ""
                            credit      = ""
                        }) -Force

                        $dest = Join-Path $processedDir $f.Name
                        Move-Item -Path $f.FullName -Destination $dest -Force
                        Write-Host ("Moved inbox file to: {0}" -f $dest)
                    }
                } catch {
                    Write-Host ("Media processing failed for {0}" -f $f.FullName)
                }
            }
        }
    }
}

# ------------------------------------------------------------
# Build posts index + usage scan (and full-text search)
# ------------------------------------------------------------
$postFiles = Get-ChildItem -Path $postsFolder -Filter "*.json" -File |
    Where-Object {
        $_.Name -ne "index.json" -and $_.Name -ne "_template.json"
    }

# Reset used_in for all manifest entries (we rebuild usage from posts)
# Note: if you don't want this behavior, remove this block.
try {
    foreach ($p in $manifest.items.PSObject.Properties) {
        if ($p.Value -and $p.Value.used_in -ne $null) {
            $p.Value.used_in = @()
        }
    }
} catch {}

$mediaRefRegex = '/?media/derivatives/\d{4}/\d{2}/(?<key>[a-zA-Z0-9_-]+)_(?<width>\d+)w\.jpg'

$posts = foreach ($f in $postFiles) {
    try {
        $raw = Get-Content -Path $f.FullName -Raw
        $p = $raw | ConvertFrom-Json

        if (-not $p.slug) { continue }
        if (-not $p.title) { $p.title = $p.slug }

        $excerpt = $p.excerpt
        $bodyText = Convert-HtmlToText -Html $p.content_html

        if (-not $excerpt) {
            $text = $bodyText
            if ($text.Length -gt 160) { $text = $text.Substring(0, 160) + "…" }
            $excerpt = $text
        }

        # Update media usage from content_html and create manifest entries for
        # committed derivatives that were added directly to the repository.
        if ($p.content_html) {
            $matches = [regex]::Matches([string]$p.content_html, $mediaRefRegex)
            foreach ($m in $matches) {
                $k = Get-SafeKey $m.Groups["key"].Value
                if (-not $k) { continue }

                $alt = ""
                if ($p.cover -and [string]$p.cover.alt) {
                    $alt = [string]$p.cover.alt
                }

                Ensure-MediaManifestEntry -Manifest $manifest -Key $k -WebPath $m.Value -Width ([int]$m.Groups["width"].Value) -Slug ([string]$p.slug) -Alt $alt
            }
        }

        $searchText = ("{0} {1} {2} {3}" -f $p.title, ($p.tags -join ' '), $excerpt, $bodyText).ToLower()

        $postEntry = [ordered]@{
            slug        = [string]$p.slug
            title       = [string]$p.title
            date        = [string]$p.date
            tags        = @($p.tags)
            excerpt     = [string]$excerpt
        }

        if ($p.cover -and $p.cover.src) {
            $coverAlt = [string]$p.cover.alt
            if ([string]::IsNullOrWhiteSpace($coverAlt)) {
                $coverAlt = "{0} cover image" -f $p.title
            }
            $postEntry['cover'] = [pscustomobject]@{
                src = [string]$p.cover.src
                alt = $coverAlt
            }
        }

        $postEntry['_searchText'] = [string]$searchText
        [pscustomobject]$postEntry
    } catch {
        Write-Host ("Skipping invalid JSON: {0}" -f $f.FullName)
    }
}

$postsSorted = $posts | Sort-Object -Property date -Descending

$indexObj = [pscustomobject]@{
    _meta = [pscustomobject]@{
        filename      = "posts/index.json"
        author        = "Jason Lamb (with help from ChatGPT)"
        created_date  = "2026-02-03"
        modified_date = (Get-Date -Format "yyyy-MM-dd")
        revision      = "1.3.1"
        changelog     = @(
            "1.3.1: Preserves cover metadata and fallback excerpts during generated index rebuilds",
            "1.3.0: Added 43 project articles and cover metadata.",
            "1.2: Adds embedded _meta header; build still uses posts array with _searchText for deterministic full-text search"
        )
    }
    blog_title = "Jason’s Flat-File Blog"
    generated  = (Get-Date).ToUniversalTime().ToString("o")
    posts      = @($postsSorted)
}

$indexJson = $indexObj | ConvertTo-Json -Depth 8
$indexJson | Set-Content -Path $indexPath -Encoding UTF8
Write-Host ("Wrote index: {0}" -f $indexPath)

# ------------------------------------------------------------
# Manifest integrity + warnings (written into manifest items as health)
# ------------------------------------------------------------
try {
    foreach ($prop in $manifest.items.PSObject.Properties) {
        $k = $prop.Name
        $v = $prop.Value
        if (-not $v) { continue }

        $missing = @()

        # Original
        if ($v.original) {
            $disk = WebPathToDisk -WebPath ([string]$v.original)
            if (-not (Test-Path $disk)) { $missing += [string]$v.original }
        }

        # Derivatives
        if ($v.derivatives) {
            foreach ($d in $v.derivatives.PSObject.Properties) {
                $wp = [string]$d.Value
                $disk2 = WebPathToDisk -WebPath $wp
                if (-not (Test-Path $disk2)) { $missing += $wp }
            }
        }

        $altMissing = (-not $v.alt -or ([string]$v.alt).Trim().Length -eq 0)
        $unused = (-not $v.used_in -or @($v.used_in).Count -eq 0)

        $v | Add-Member -MemberType NoteProperty -Name health -Value ([pscustomobject]@{
            checked      = (Get-Date).ToString("o")
            missing      = $missing
            missing_alt  = $altMissing
            unused       = $unused
        }) -Force
    }
} catch {}

Save-Manifest -Manifest $manifest

# ------------------------------------------------------------
# Generate rss.xml (with header comment)
# ------------------------------------------------------------
$itemsXml = foreach ($p in $postsSorted) {
    $link = "{0}/post.html?p={1}" -f $SiteBaseUrl.TrimEnd('/'), $p.slug

    $pub = $p.date
    try {
        $dt = [datetime]::ParseExact($p.date, 'yyyy-MM-dd', $null)
        $pub = $dt.ToUniversalTime().ToString('ddd, dd MMM yyyy 00:00:00 +0000')
    } catch {}

@"
    <item>
      <title>$(XmlEscape $p.title)</title>
      <link>$(XmlEscape $link)</link>
      <guid>$(XmlEscape $link)</guid>
      <pubDate>$(XmlEscape $pub)</pubDate>
      <description>$(XmlEscape $p.excerpt)</description>
    </item>
"@
}

$rss = @"
<?xml version="1.0" encoding="UTF-8"?>
<!--
# filename: rss.xml
# author: Jason Lamb (with help from ChatGPT)
# created date: 2026-02-03
# modified date: 2026-09-16
# revision: 1.3.0
# changelog:
# - 1.3.0: Regenerates RSS links with correct PowerShell format placeholders
# - 1.2: Preserves header metadata while generating RSS items from post JSON
# - 1.1: Updated base links to https://jasr.me/blog and added items for posts
# - 1.0: Basic RSS shell
-->
<rss version="2.0">
  <channel>
    <title>Jason’s Flat-File Blog</title>
    <link>$($SiteBaseUrl.TrimEnd('/'))/</link>
    <description>Database-free blog powered by JSON files.</description>
    <language>en-us</language>
$($itemsXml -join "`n")
  </channel>
</rss>
"@

$rss | Set-Content -Path $rssPath -Encoding UTF8
Write-Host ("Wrote RSS: {0}" -f $rssPath)

# ------------------------------------------------------------
# Generate sitemap.xml (with header comment)
# ------------------------------------------------------------
$urls = @(
    ("{0}/index.html" -f $SiteBaseUrl.TrimEnd('/'))
    ("{0}/about.html" -f $SiteBaseUrl.TrimEnd('/'))
    ("{0}/rss.xml" -f $SiteBaseUrl.TrimEnd('/'))
)

foreach ($p in $postsSorted) {
    $urls += ("{0}/post.html?p={1}" -f $SiteBaseUrl.TrimEnd('/'), $p.slug)
}

$urlNodes = $urls | ForEach-Object { "  <url><loc>$(XmlEscape $_)</loc></url>" }

$sitemap = @"
<?xml version="1.0" encoding="UTF-8"?>
<!--
# filename: sitemap.xml
# author: Jason Lamb (with help from ChatGPT)
# created date: 2026-02-03
# modified date: 2026-09-16
# revision: 1.3.0
# changelog:
# - 1.3.0: Regenerates sitemap links with correct PowerShell format placeholders
# - 1.2: Preserves header metadata while generating sitemap URLs from post JSON
# - 1.1: Updated base links to https://jasr.me/blog and added post URLs
# - 1.0: Basic sitemap shell
-->
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
$($urlNodes -join "`n")
</urlset>
"@

$sitemap | Set-Content -Path $sitemapPath -Encoding UTF8
Write-Host ("Wrote Sitemap: {0}" -f $sitemapPath)

<#
EXAMPLE USAGE:

# Build posts index + RSS + sitemap (repo root):
# .\tools\Build-Blog.ps1

# Build explicitly:
# .\tools\Build-Blog.ps1 -RootPath "." -SiteBaseUrl "https://jasr.me/blog"

# Also process images dropped into media/_inbox:
# .\tools\Build-Blog.ps1 -ProcessMedia

# Notes:
# - The admin uploader (admin/upload-media.php) updates media-manifest.json on the server.
# - The build script additionally refreshes "used_in" links by scanning post content_html for /media/derivatives/... references.

#>
