<#
.SYNOPSIS
    Adds one WordPress category to existing published posts.

.DESCRIPTION
    Revision: 1.0.0
    Dry-run is the default. Add -Commit to update published WordPress posts.
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [ValidatePattern('^https://')]
    [string]$SiteUrl,

    [Parameter(Mandatory)]
    [string]$CategoryName,

    [string]$Username = $env:WORDPRESS_USERNAME,
    [string]$AppPassword = $env:WORDPRESS_APP_PASSWORD,
    [string]$PostsPath = (Join-Path $PSScriptRoot '..\posts'),
    [string[]]$ExcludeSlug = @(),

    [switch]$CreateMissingCategory,
    [switch]$AllPublished,
    [switch]$Commit
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function New-AuthorizationHeader {
    param([string]$User, [string]$Password)

    $raw = [Text.Encoding]::UTF8.GetBytes("$User`:$Password")
    @{ Authorization = 'Basic ' + [Convert]::ToBase64String($raw) }
}

function Invoke-WpJson {
    param(
        [Parameter(Mandatory)][ValidateSet('Get','Post')][string]$Method,
        [Parameter(Mandatory)][string]$Path,
        [object]$Body
    )

    $params = @{
        Method      = $Method
        Uri         = "$script:WpBase/wp-json/wp/v2/$($Path.TrimStart('/'))"
        Headers     = $script:AuthHeaders
        ErrorAction = 'Stop'
    }

    if ($PSBoundParameters.ContainsKey('Body')) {
        $params.ContentType = 'application/json; charset=utf-8'
        $params.Body = $Body | ConvertTo-Json -Depth 20 -Compress
    }

    Invoke-RestMethod @params
}

function Get-PlainText {
    param([object]$Value)

    if ($null -eq $Value) { return '' }
    $text = if ($Value.PSObject.Properties['rendered']) { [string]$Value.rendered } else { [string]$Value }
    [Net.WebUtility]::HtmlDecode(($text -replace '<[^>]+>', '')).Trim()
}

function Test-ExcludedSlug {
    param([string]$Slug)

    foreach ($excluded in @($ExcludeSlug)) {
        if ($Slug -ieq $excluded) { return $true }
    }
    $false
}

function Resolve-WpCategory {
    param([string]$Name)

    $encoded = [uri]::EscapeDataString($Name)
    $matches = @(Invoke-WpJson -Method Get -Path "categories?search=$encoded&per_page=100&_fields=id,name" | ForEach-Object { $_ })
    $exact = $matches | Where-Object { $_.name -ieq $Name } | Select-Object -First 1

    if (-not $exact -and $CreateMissingCategory) {
        $exact = Invoke-WpJson -Method Post -Path 'categories' -Body @{ name = $Name }
    }

    if (-not $exact) {
        throw "WordPress category '$Name' was not found. Re-run with -CreateMissingCategory only if creating it is intentional."
    }

    $exact
}

function Get-WpCategoryNameMap {
    $map = @{}
    $page = 1
    do {
        $items = @(Invoke-WpJson -Method Get -Path "categories?per_page=100&page=$page&_fields=id,name" | ForEach-Object { $_ })
        foreach ($item in $items) {
            $map[[int]$item.id] = [string]$item.name
        }
        $page++
    } while ($items.Count -eq 100)

    $map
}

function Get-LocalPostSlugs {
    if (-not (Test-Path -LiteralPath $PostsPath -PathType Container)) {
        throw "Posts folder not found: $PostsPath"
    }

    foreach ($file in Get-ChildItem -LiteralPath $PostsPath -Filter '*.json' -File | Where-Object { $_.Name -notlike '_*' -and $_.Name -ne 'index.json' }) {
        try {
            $post = Get-Content -LiteralPath $file.FullName -Raw | ConvertFrom-Json
            if ($post.PSObject.Properties['slug'] -and -not [string]::IsNullOrWhiteSpace([string]$post.slug)) {
                if (Test-ExcludedSlug -Slug ([string]$post.slug)) { continue }
                [pscustomobject]@{
                    Slug  = [string]$post.slug
                    Title = if ($post.PSObject.Properties['title']) { [string]$post.title } else { '' }
                    File  = $file.Name
                }
            }
        }
        catch {
            Write-Warning "Skipping '$($file.Name)': $($_.Exception.Message)"
        }
    }
}

function Get-PublishedPostsByLocalSlug {
    foreach ($source in @(Get-LocalPostSlugs)) {
        $encodedSlug = [uri]::EscapeDataString($source.Slug)
        $found = @(Invoke-WpJson -Method Get -Path "posts?slug=$encodedSlug&status=publish&per_page=1&_fields=id,slug,title,date,categories,link" | ForEach-Object { $_ })
        if ($found.Count -gt 0) {
            $found[0]
        }
    }
}

function Get-AllPublishedPosts {
    $page = 1
    do {
        $items = @(Invoke-WpJson -Method Get -Path "posts?status=publish&per_page=100&page=$page&_fields=id,slug,title,date,categories,link" | ForEach-Object { $_ })
        foreach ($item in $items) {
            if (-not (Test-ExcludedSlug -Slug ([string]$item.slug))) {
                $item
            }
        }
        $page++
    } while ($items.Count -eq 100)
}

function Get-CategoryNamesForIds {
    param([int[]]$CategoryIds)

    $names = @()
    foreach ($id in @($CategoryIds)) {
        if ($script:CategoryNameMap.ContainsKey($id)) {
            $names += $script:CategoryNameMap[$id]
        }
        else {
            $names += "#$id"
        }
    }
    @($names)
}

if ([string]::IsNullOrWhiteSpace($Username) -or [string]::IsNullOrWhiteSpace($AppPassword)) {
    throw 'A WordPress username and application password are required.'
}

$script:WpBase = $SiteUrl.TrimEnd('/')
$script:AuthHeaders = New-AuthorizationHeader -User $Username -Password $AppPassword

try {
    $me = Invoke-WpJson -Method Get -Path 'users/me?context=edit&_fields=id,name,slug'
    Write-Host "WordPress API user: $($me.name) (ID $($me.id))"
}
catch {
    throw "WordPress authentication failed: $($_.Exception.Message)"
}

$category = Resolve-WpCategory -Name $CategoryName
$categoryId = [int]$category.id
$script:CategoryNameMap = Get-WpCategoryNameMap
$script:CategoryNameMap[$categoryId] = [string]$category.name

$posts = if ($AllPublished) {
    @(Get-AllPublishedPosts)
}
else {
    @(Get-PublishedPostsByLocalSlug)
}

$planned = @()
foreach ($post in @($posts)) {
    $currentIds = @($post.categories | ForEach-Object { [int]$_ })
    $hasCategory = $currentIds -contains $categoryId
    $newIds = @($currentIds)
    if (-not $hasCategory) {
        $newIds += $categoryId
    }

    $planned += [pscustomobject]@{
        Date              = ([datetime]$post.date).ToString('yyyy-MM-dd h:mm tt')
        Title             = Get-PlainText -Value $post.title
        Slug              = [string]$post.slug
        CurrentCategories = ((Get-CategoryNamesForIds -CategoryIds $currentIds) -join ', ')
        AddCategory       = [string]$category.name
        Result            = if ($hasCategory) { 'Already has category' } elseif ($Commit) { 'Will update' } else { 'Would update' }
        PostId            = [int]$post.id
        NewCategoryIds    = @($newIds)
    }
}

Write-Host ''
Write-Host ('Published posts found: {0}' -f $planned.Count)
Write-Host ('Category to add    : {0} (ID {1})' -f $category.name, $categoryId)
Write-Host ('Scope              : {0}' -f $(if ($AllPublished) { 'all published WordPress posts' } else { 'published posts matching local JSON slugs' }))

if ($planned.Count -gt 0) {
    Write-Host ''
    $planned |
        Sort-Object { [datetime]$_.Date }, Title |
        Select-Object Date, Title, Slug, CurrentCategories, AddCategory, Result |
        Format-Table -AutoSize -Wrap |
        Out-Host
}

if (-not $Commit) {
    Write-Host ''
    Write-Host 'DRY RUN ONLY. Re-run with -Commit after reviewing the published-post category updates.'
    return
}

foreach ($item in @($planned | Where-Object { $_.Result -eq 'Will update' } | Sort-Object { [datetime]$_.Date }, Title)) {
    try {
        Invoke-WpJson -Method Post -Path "posts/$($item.PostId)" -Body @{ categories = @($item.NewCategoryIds) } | Out-Null
        Write-Host "Updated: $($item.Title)"
    }
    catch {
        Write-Error "Failed to update '$($item.Slug)'. $($_.Exception.Message)"
    }
}

Write-Host ''
Write-Host 'Finished published-post category update.'
