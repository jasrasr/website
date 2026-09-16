<#
# filename: Test-Blog.ps1
# author: Jason Lamb (with help from ChatGPT)
# created date: 2026-09-16
# modified date: 2026-09-16
# revision: 1.0.0
# changelog:
# - 1.0.0: Exercise build outputs, cover paths, excerpts, and multiple derivative widths in an isolated copy.
#>
param([string]$RootPath = (Split-Path $PSScriptRoot -Parent))
$ErrorActionPreference = 'Stop'
function Assert($Condition, [string]$Message) {
    if (-not $Condition) { throw $Message }
}
$temp = Join-Path ([IO.Path]::GetTempPath()) ('blog-validation-' + [guid]::NewGuid())
New-Item -ItemType Directory -Path $temp | Out-Null
try {
    Copy-Item (Join-Path $RootPath '*') $temp -Recurse -Force
    & (Join-Path $temp 'tools/Build-Blog.ps1') -RootPath $temp
    $index = Get-Content (Join-Path $temp 'posts/index.json') -Raw | ConvertFrom-Json
    $manifest = Get-Content (Join-Path $temp 'admin/media-manifest.json') -Raw | ConvertFrom-Json
    $sources = @(Get-ChildItem (Join-Path $temp 'posts/*.json') | Where-Object { $_.Name -notin @('index.json','_template.json') })
    Assert ($index.posts.Count -eq $sources.Count) 'Index lost posts during rebuild'
    foreach ($post in $index.posts) {
        Assert (-not [string]::IsNullOrWhiteSpace($post.excerpt)) "Missing excerpt: $($post.slug)"
        if ($post.cover) {
            $base = [uri]'https://example.invalid/nested/blog/post.html'
            $url = [uri]::new($base, $post.cover.src)
            Assert ($url.AbsolutePath.StartsWith('/nested/blog/media/')) "Cover escapes blog directory: $($post.slug)"
            Assert (Test-Path (Join-Path $temp $post.cover.src)) "Missing cover file: $($post.slug)"
            $entry = $manifest.items.($post.slug)
            Assert ($entry.used_in -contains $post.slug) "Missing media usage: $($post.slug)"
            Assert ($entry.health.missing.Count -eq 0) "Media health reports missing files: $($post.slug)"
        }
    }
    [xml]$rss = Get-Content (Join-Path $temp 'rss.xml') -Raw
    [xml]$sitemap = Get-Content (Join-Path $temp 'sitemap.xml') -Raw
    Assert (@($rss.rss.channel.item).Count -eq $sources.Count) 'RSS omitted posts'
    $locations = @($sitemap.urlset.url | ForEach-Object { [string]$_.loc })
    Assert ($locations.Count -eq ($sources.Count + 3)) 'Sitemap omitted posts or base pages'
    foreach ($page in @('index.html','about.html','rss.xml')) {
        Assert ($locations -contains "https://jasr.me/blog/$page") "Sitemap missing $page"
    }
    foreach ($post in $index.posts) {
        $url = "https://jasr.me/blog/post.html?p=$($post.slug)"
        Assert ($locations -contains $url) "Sitemap missing $($post.slug)"
        Assert (@($rss.rss.channel.item | Where-Object link -eq $url).Count -eq 1) "RSS missing $($post.slug)"
    }
    $fixture = @{
        slug='derivative-width-regression'; title='Width fixture'; date='2026-09-16'; tags=@('test')
        content_html='<p>Fish &amp; chips</p><img src="media/derivatives/2026/09/width-test_320w.jpg"><img src="media/derivatives/2026/09/width-test_1600w.jpg">'
    }
    $fixture | ConvertTo-Json | Set-Content (Join-Path $temp 'posts/derivative-width-regression.json')
    & (Join-Path $temp 'tools/Build-Blog.ps1') -RootPath $temp
    $manifest = Get-Content (Join-Path $temp 'admin/media-manifest.json') -Raw | ConvertFrom-Json
    $widths = $manifest.items.'width-test'.derivatives
    Assert ($widths.'320' -like '*_320w.jpg') '320-width derivative incorrectly recorded'
    Assert ($widths.'1600' -like '*_1600w.jpg') '1600-width derivative incorrectly recorded'
    Assert (-not $widths.'640') 'Builder invented a 640-width derivative'
    $index = Get-Content (Join-Path $temp 'posts/index.json') -Raw | ConvertFrom-Json
    $fixtureEntry = $index.posts | Where-Object slug -eq 'derivative-width-regression'
    Assert ($fixtureEntry.excerpt -eq 'Fish & chips') 'Fallback excerpt did not decode entities'
    Write-Host "PASS: $($sources.Count) posts, cover paths, media usage, RSS/sitemap, excerpts, and derivative widths."
} finally {
    Remove-Item -LiteralPath $temp -Recurse -Force
}
