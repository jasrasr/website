<#
.SYNOPSIS
    Adds one WordPress tag to tracked project posts.

.DESCRIPTION
    Revision: 1.0.0
    Dry-run is the default. Add -Commit to update tracked WordPress posts.
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [ValidatePattern('^https://')]
    [string]$SiteUrl,

    [string]$TagName = 'Jason AI Automation',
    [string]$TagSlug = 'jason-ai-automation',

    [string]$Username = $env:WORDPRESS_USERNAME,
    [string]$AppPassword = $env:WORDPRESS_APP_PASSWORD,
    [string]$StatePath = (Join-Path $PSScriptRoot 'wordpress-state.json'),
    [string[]]$ExcludeSlug = @('visual-ping-webhook-project-sketch'),

    [switch]$CreateMissingTag,
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
        Method             = $Method
        Uri                = "$script:WpBase/wp-json/wp/v2/$($Path.TrimStart('/'))"
        Headers            = $script:AuthHeaders
        MaximumRedirection = 0
        ErrorAction        = 'Stop'
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

function Resolve-WpTag {
    if (-not [string]::IsNullOrWhiteSpace($TagSlug)) {
        $encodedSlug = [uri]::EscapeDataString($TagSlug)
        $bySlug = @(Invoke-WpJson -Method Get -Path "tags?slug=$encodedSlug&per_page=100&_fields=id,name,slug" | ForEach-Object { $_ })
        $exactSlug = $bySlug | Where-Object { $_.slug -ieq $TagSlug } | Select-Object -First 1
        if ($exactSlug) { return $exactSlug }
    }

    $encodedName = [uri]::EscapeDataString($TagName)
    $matches = @(Invoke-WpJson -Method Get -Path "tags?search=$encodedName&per_page=100&_fields=id,name,slug" | ForEach-Object { $_ })
    $exactName = $matches | Where-Object { $_.name -ieq $TagName } | Select-Object -First 1
    if ($exactName) { return $exactName }

    if ($CreateMissingTag -and $Commit) {
        $body = [ordered]@{ name = $TagName }
        if (-not [string]::IsNullOrWhiteSpace($TagSlug)) { $body.slug = $TagSlug }
        return Invoke-WpJson -Method Post -Path 'tags' -Body $body
    }

    if ($CreateMissingTag) {
        Write-Warning "WordPress tag '$TagName' was not found and would be created in commit mode."
        return [pscustomobject]@{ id = 0; name = $TagName; slug = $TagSlug }
    }

    throw "WordPress tag '$TagName' was not found. Re-run with -CreateMissingTag only if creating it is intentional."
}

function Get-TrackedPosts {
    if (-not (Test-Path -LiteralPath $StatePath -PathType Leaf)) {
        throw "State file not found: $StatePath"
    }

    $state = Get-Content -LiteralPath $StatePath -Raw | ConvertFrom-Json
    if ($state._meta.PSObject.Properties['site_url'] -and $state._meta.site_url -and $state._meta.site_url.TrimEnd('/') -ne $script:WpBase) {
        throw "State file belongs to a different WordPress site: $($state._meta.site_url)"
    }

    foreach ($property in $state.posts.PSObject.Properties) {
        $slug = [string]$property.Name
        $entry = $property.Value
        if (Test-ExcludedSlug -Slug $slug) { continue }
        if (-not $entry.PSObject.Properties['wordpress_post_id'] -or -not $entry.wordpress_post_id) { continue }
        [pscustomobject]@{
            Slug = $slug
            Id   = [int]$entry.wordpress_post_id
        }
    }
}

if ([string]::IsNullOrWhiteSpace($Username) -or [string]::IsNullOrWhiteSpace($AppPassword)) {
    throw 'A WordPress username and application password are required.'
}

$script:WpBase = $SiteUrl.TrimEnd('/')
$script:AuthHeaders = New-AuthorizationHeader -User $Username -Password $AppPassword

try {
    $me = Invoke-WpJson -Method Get -Path 'users/me?context=edit&_fields=id,name'
    Write-Host "WordPress API user: $($me.name) (ID $($me.id))"
}
catch {
    throw "WordPress authentication failed: $($_.Exception.Message)"
}

$tag = Resolve-WpTag
$tagId = [int]$tag.id
$tracked = @(Get-TrackedPosts)
$planned = @()

foreach ($trackedPost in $tracked) {
    try {
        $remote = Invoke-WpJson -Method Get -Path "posts/$($trackedPost.Id)?context=edit&_fields=id,slug,status,title,tags,link"
    }
    catch {
        $planned += [pscustomobject]@{
            Title = ''
            Slug = $trackedPost.Slug
            Status = 'unreadable'
            CurrentTagIds = ''
            Result = "Skip: $($_.Exception.Message)"
            PostId = $trackedPost.Id
            NewTagIds = @()
        }
        continue
    }

    if ([string]$remote.slug -ne $trackedPost.Slug) {
        $planned += [pscustomobject]@{
            Title = Get-PlainText -Value $remote.title
            Slug = $trackedPost.Slug
            Status = [string]$remote.status
            CurrentTagIds = (@($remote.tags) -join ', ')
            Result = "Skip: remote slug is '$($remote.slug)'"
            PostId = $trackedPost.Id
            NewTagIds = @()
        }
        continue
    }

    if ([string]$remote.status -notin @('publish','future','draft','pending')) {
        $planned += [pscustomobject]@{
            Title = Get-PlainText -Value $remote.title
            Slug = $trackedPost.Slug
            Status = [string]$remote.status
            CurrentTagIds = (@($remote.tags) -join ', ')
            Result = "Skip: unsupported status '$($remote.status)'"
            PostId = $trackedPost.Id
            NewTagIds = @()
        }
        continue
    }

    $currentIds = @($remote.tags | ForEach-Object { [int]$_ })
    $hasTag = $currentIds -contains $tagId
    $newIds = @($currentIds)
    if (-not $hasTag) { $newIds += $tagId }

    $planned += [pscustomobject]@{
        Title = Get-PlainText -Value $remote.title
        Slug = [string]$remote.slug
        Status = [string]$remote.status
        CurrentTagIds = ($currentIds -join ', ')
        Result = if ($hasTag) { 'Already has tag' } elseif ($Commit) { 'Will update' } else { 'Would update' }
        PostId = [int]$remote.id
        NewTagIds = @($newIds)
    }
}

Write-Host ''
Write-Host ('Tracked posts checked: {0}' -f $planned.Count)
Write-Host ('Tag to add          : {0} (slug: {1}, ID {2})' -f $tag.name, $tag.slug, $tagId)

if ($planned.Count -gt 0) {
    Write-Host ''
    $planned |
        Sort-Object Status, Title |
        Select-Object Title, Slug, Status, CurrentTagIds, Result |
        Format-Table -AutoSize -Wrap |
        Out-Host
}

if (-not $Commit) {
    Write-Host ''
    Write-Host 'DRY RUN ONLY. Re-run with -Commit after reviewing the tracked-post tag updates.'
    return
}

foreach ($item in @($planned | Where-Object { $_.Result -eq 'Will update' } | Sort-Object Status, Title)) {
    try {
        Invoke-WpJson -Method Post -Path "posts/$($item.PostId)" -Body @{ tags = @($item.NewTagIds) } | Out-Null
        Write-Host "Updated: $($item.Title)"
    }
    catch {
        Write-Error "Failed to update '$($item.Slug)'. $($_.Exception.Message)"
    }
}

Write-Host ''
Write-Host 'Finished tracked-post tag update.'
