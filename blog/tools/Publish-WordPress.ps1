<#
.SYNOPSIS
    Schedules the JSON blog backlog on WordPress through the REST API.

.DESCRIPTION
    Revision: 1.0.0
    Dry-run is the default. Add -Commit to upload media and create WordPress posts.
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [ValidatePattern('^https://')]
    [string]$SiteUrl,

    [string]$Username = $env:WORDPRESS_USERNAME,
    [string]$AppPassword = $env:WORDPRESS_APP_PASSWORD,
    [string]$PostsPath = (Join-Path $PSScriptRoot '..\posts'),
    [string]$StatePath = (Join-Path $PSScriptRoot 'wordpress-state.json'),

    [ValidateRange(1, 5)]
    [int]$MaxPostsPerDay = 2,

    [ValidateRange(0, 540)]
    [int]$MinSpacingMinutes = 120,

    [switch]$PreservePostOrder,
    [switch]$CreateMissingTags,
    [switch]$KeepCoverInContent,
    [switch]$Commit
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-EasternTimeZone {
    foreach ($id in @('America/New_York', 'Eastern Standard Time')) {
        try { return [TimeZoneInfo]::FindSystemTimeZoneById($id) } catch { }
    }
    throw 'Could not resolve the America/New_York time zone.'
}

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

function Save-State {
    $stateObject = [ordered]@{
        _meta = [ordered]@{
            schema_version = '1.0.0'
            purpose = 'WordPress-only publication state for blog/posts JSON sources. This file is not used by the static blog build.'
            modified_date = (Get-Date).ToString('yyyy-MM-dd')
        }
        posts = [ordered]@{}
    }

    foreach ($key in ($script:StatePosts.Keys | Sort-Object)) {
        $stateObject.posts[$key] = $script:StatePosts[$key]
    }

    $stateObject | ConvertTo-Json -Depth 20 | Set-Content -LiteralPath $StatePath -Encoding utf8
}

function Get-MimeType {
    param([string]$Path)
    switch ([IO.Path]::GetExtension($Path).ToLowerInvariant()) {
        '.jpg'  { 'image/jpeg' }
        '.jpeg' { 'image/jpeg' }
        '.png'  { 'image/png' }
        '.gif'  { 'image/gif' }
        '.webp' { 'image/webp' }
        default { throw "Unsupported featured image type: $Path" }
    }
}

function Upload-WpMedia {
    param([string]$ImagePath, [string]$AltText)

    $fileName = [IO.Path]::GetFileName($ImagePath)
    $headers = @{}
    foreach ($key in $script:AuthHeaders.Keys) {
        $headers[$key] = $script:AuthHeaders[$key]
    }
    $headers['Content-Disposition'] = "attachment; filename=`"$fileName`""

    $params = @{
        Method      = 'Post'
        Uri         = "$script:WpBase/wp-json/wp/v2/media"
        Headers     = $headers
        ContentType = (Get-MimeType -Path $ImagePath)
        InFile      = $ImagePath
        ErrorAction = 'Stop'
    }
    $media = Invoke-RestMethod @params

    if (-not [string]::IsNullOrWhiteSpace($AltText)) {
        Invoke-WpJson -Method Post -Path "media/$($media.id)" -Body @{ alt_text = $AltText } | Out-Null
    }

    $media
}

function Resolve-WpTagIds {
    param([string[]]$TagNames)

    $ids = @()
    foreach ($tagName in @($TagNames)) {
        if ([string]::IsNullOrWhiteSpace($tagName)) { continue }

        $encoded = [uri]::EscapeDataString($tagName)
        $matches = @(Invoke-WpJson -Method Get -Path "tags?search=$encoded&per_page=100&_fields=id,name")
        $exact = $matches | Where-Object { $_.name -ieq $tagName } | Select-Object -First 1

        if (-not $exact -and $CreateMissingTags) {
            try {
                $exact = Invoke-WpJson -Method Post -Path 'tags' -Body @{ name = $tagName }
            }
            catch {
                Write-Warning "Could not create WordPress tag '$tagName': $($_.Exception.Message)"
            }
        }

        if ($exact) {
            $ids += [int]$exact.id
        }
        else {
            Write-Warning "WordPress tag '$tagName' was not found and will be omitted."
        }
    }

    @($ids)
}

function Remove-CoverFigure {
    param([string]$Html)
    if ($KeepCoverInContent) { return $Html }

    [regex]::Replace(
        $Html,
        '(?is)^\s*<figure\b[^>]*class=["''][^"'']*\bpost-cover\b[^"'']*["''][^>]*>.*?</figure>\s*',
        '',
        1
    )
}

function Resolve-CoverPath {
    param([string]$CoverSrc, [string]$BlogRoot)
    if ([string]::IsNullOrWhiteSpace($CoverSrc)) { return $null }
    if ($CoverSrc -match '^https?://') { return $null }

    $relative = $CoverSrc.TrimStart('/', '\').Replace('/', [IO.Path]::DirectorySeparatorChar)
    [IO.Path]::GetFullPath((Join-Path $BlogRoot $relative))
}

function Get-Weekdays {
    param([datetime]$StartDate, [int]$Count)

    $result = @()
    $cursor = $StartDate.Date
    while ($result.Count -lt $Count) {
        if ($cursor.DayOfWeek -notin @([DayOfWeek]::Saturday, [DayOfWeek]::Sunday)) {
            $result += $cursor
        }
        $cursor = $cursor.AddDays(1)
    }
    @($result)
}

function Get-RandomTimesForDay {
    param([datetime]$Date, [int]$Count)

    if ($Count -eq 1) {
        return @($Date.AddHours(8).AddMinutes((Get-Random -Minimum 0 -Maximum 541)))
    }

    for ($attempt = 0; $attempt -lt 5000; $attempt++) {
        $minutes = @(1..$Count | ForEach-Object { Get-Random -Minimum 0 -Maximum 541 } | Sort-Object)
        $valid = $true

        for ($i = 1; $i -lt $minutes.Count; $i++) {
            if (($minutes[$i] - $minutes[$i - 1]) -lt $MinSpacingMinutes) {
                $valid = $false
                break
            }
        }

        if ($valid) {
            return @($minutes | ForEach-Object { $Date.AddHours(8).AddMinutes($_) })
        }
    }

    throw "Cannot fit $Count posts between 8:00 AM and 5:00 PM with $MinSpacingMinutes-minute minimum spacing."
}

function New-PostSchedule {
    param([object[]]$Posts, [datetime]$StartDate)

    if ($Posts.Count -eq 0) { return @() }

    $dayCount = [int][Math]::Ceiling($Posts.Count / [double]$MaxPostsPerDay)
    $days = @(Get-Weekdays -StartDate $StartDate -Count $dayCount)
    $counts = @{}

    foreach ($day in $days) {
        $counts[$day.ToString('yyyy-MM-dd')] = 1
    }

    $remaining = $Posts.Count - $days.Count
    while ($remaining -gt 0) {
        $available = @($days | Where-Object { $counts[$_.ToString('yyyy-MM-dd')] -lt $MaxPostsPerDay })
        if ($available.Count -eq 0) { throw 'Schedule allocation exceeded MaxPostsPerDay.' }
        $chosen = Get-Random -InputObject $available
        $counts[$chosen.ToString('yyyy-MM-dd')]++
        $remaining--
    }

    $slots = @()
    foreach ($day in $days) {
        $slots += Get-RandomTimesForDay -Date $day -Count $counts[$day.ToString('yyyy-MM-dd')]
    }
    $slots = @($slots | Sort-Object)

    if ($PreservePostOrder) {
        $orderedPosts = @($Posts | Sort-Object { $_.Data.date }, { $_.Data.slug })
    }
    else {
        $orderedPosts = @(Get-Random -InputObject $Posts -Count $Posts.Count)
    }

    $result = @()
    for ($i = 0; $i -lt $orderedPosts.Count; $i++) {
        $result += [pscustomobject]@{
            Post      = $orderedPosts[$i]
            Scheduled = $slots[$i]
        }
    }
    @($result)
}

$script:WpBase = $SiteUrl.TrimEnd('/')
$timeZone = Get-EasternTimeZone
$nowEastern = [TimeZoneInfo]::ConvertTime([DateTimeOffset]::UtcNow, $timeZone)
$startDate = $nowEastern.Date.AddDays(1)

if (-not (Test-Path -LiteralPath $PostsPath -PathType Container)) {
    throw "Posts folder not found: $PostsPath"
}

$blogRoot = [IO.Path]::GetFullPath((Join-Path $PostsPath '..'))
$script:StatePosts = @{}

if (Test-Path -LiteralPath $StatePath -PathType Leaf) {
    $existingState = Get-Content -LiteralPath $StatePath -Raw | ConvertFrom-Json
    if ($existingState.PSObject.Properties['posts'] -and $existingState.posts) {
        foreach ($property in $existingState.posts.PSObject.Properties) {
            $script:StatePosts[$property.Name] = $property.Value
        }
    }
}

$ready = @()
$invalid = @()
$alreadyTracked = @()

foreach ($file in Get-ChildItem -LiteralPath $PostsPath -Filter '*.json' -File | Where-Object { $_.Name -notlike '_*' }) {
    try {
        $post = Get-Content -LiteralPath $file.FullName -Raw | ConvertFrom-Json
    }
    catch {
        $invalid += [pscustomobject]@{ File = $file.Name; Problem = "Invalid JSON: $($_.Exception.Message)" }
        continue
    }

    $missing = @()
    foreach ($field in @('slug','title','excerpt','content_html')) {
        if (-not $post.PSObject.Properties[$field] -or [string]::IsNullOrWhiteSpace([string]$post.$field)) {
            $missing += $field
        }
    }

    $coverSrc = $null
    $coverAlt = $null
    if ($post.PSObject.Properties['cover'] -and $post.cover) {
        if ($post.cover.PSObject.Properties['src']) { $coverSrc = [string]$post.cover.src }
        if ($post.cover.PSObject.Properties['alt']) { $coverAlt = [string]$post.cover.alt }
    }
    if ([string]::IsNullOrWhiteSpace($coverSrc)) { $missing += 'cover.src' }

    $coverPath = Resolve-CoverPath -CoverSrc $coverSrc -BlogRoot $blogRoot
    if (-not $coverPath -or -not (Test-Path -LiteralPath $coverPath -PathType Leaf)) {
        $missing += 'cover image file'
    }

    if ($missing.Count -gt 0) {
        $invalid += [pscustomobject]@{ File = $file.Name; Problem = 'Missing/invalid: ' + ($missing -join ', ') }
        continue
    }

    if ($script:StatePosts.ContainsKey([string]$post.slug) -and $script:StatePosts[[string]$post.slug].wordpress_post_id) {
        $alreadyTracked += [pscustomobject]@{
            Slug        = $post.slug
            WordPressId = $script:StatePosts[[string]$post.slug].wordpress_post_id
            Source      = $file.Name
        }
        continue
    }

    $ready += [pscustomobject]@{
        Data      = $post
        File      = $file
        CoverPath = $coverPath
        CoverAlt  = $coverAlt
    }
}

if ($Commit -and ([string]::IsNullOrWhiteSpace($Username) -or [string]::IsNullOrWhiteSpace($AppPassword))) {
    throw 'Commit mode requires a WordPress username and application password.'
}

$script:AuthHeaders = @{}
if (-not [string]::IsNullOrWhiteSpace($Username) -and -not [string]::IsNullOrWhiteSpace($AppPassword)) {
    $script:AuthHeaders = New-AuthorizationHeader -User $Username -Password $AppPassword

    try {
        $me = Invoke-WpJson -Method Get -Path 'users/me?context=edit&_fields=id,name,slug'
        Write-Host "WordPress API user: $($me.name) (ID $($me.id))"
    }
    catch {
        throw "WordPress authentication failed: $($_.Exception.Message)"
    }

    $serverExistingSlugs = @()
    foreach ($item in @($ready)) {
        $encodedSlug = [uri]::EscapeDataString([string]$item.Data.slug)
        $found = @(Invoke-WpJson -Method Get -Path "posts?slug=$encodedSlug&status=any&per_page=1&_fields=id,slug,status,link,date")

        if ($found.Count -gt 0) {
            $serverExistingSlugs += [string]$item.Data.slug
            $script:StatePosts[[string]$item.Data.slug] = [ordered]@{
                source_file            = $item.File.Name
                wordpress_post_id      = [int]$found[0].id
                wordpress_media_id     = $null
                scheduled_date_eastern = [string]$found[0].date
                status                 = [string]$found[0].status
                wordpress_url          = [string]$found[0].link
                last_sync              = (Get-Date).ToString('o')
                note                   = 'Discovered on WordPress by slug; duplicate creation skipped.'
            }
        }
    }

    if ($serverExistingSlugs.Count -gt 0) {
        $ready = @($ready | Where-Object { [string]$_.Data.slug -notin $serverExistingSlugs })
        if ($Commit) { Save-State }
    }
}
else {
    Write-Warning 'No WordPress credentials supplied. Dry-run duplicate detection is state-file-only.'
}

$schedule = @(New-PostSchedule -Posts @($ready) -StartDate $startDate)

Write-Host ''
Write-Host 'WordPress scheduling preflight'
Write-Host ('  Ready to schedule : {0}' -f $schedule.Count)
Write-Host ('  Already tracked   : {0}' -f $alreadyTracked.Count)
Write-Host ('  Invalid/skipped   : {0}' -f $invalid.Count)

if ($schedule.Count -gt 0) {
    Write-Host ('  First scheduled   : {0}' -f $schedule[0].Scheduled.ToString('ddd yyyy-MM-dd h:mm tt'))
    Write-Host ('  Last scheduled    : {0}' -f $schedule[-1].Scheduled.ToString('ddd yyyy-MM-dd h:mm tt'))
}

if ($invalid.Count -gt 0) {
    Write-Host ''
    Write-Warning 'Posts failing validation:'
    $invalid | Format-Table -AutoSize | Out-Host
}

if ($schedule.Count -gt 0) {
    Write-Host ''
    $schedule | ForEach-Object {
        [pscustomobject]@{
            ScheduledEastern = $_.Scheduled.ToString('yyyy-MM-dd h:mm tt')
            Slug             = $_.Post.Data.slug
            Title            = $_.Post.Data.title
        }
    } | Format-Table -AutoSize | Out-Host
}

if (-not $Commit) {
    Write-Host ''
    Write-Host 'DRY RUN ONLY. Re-run with -Commit after reviewing the schedule.'
    return
}

foreach ($entry in $schedule) {
    $item = $entry.Post
    $post = $item.Data
    $slug = [string]$post.slug
    Write-Host "Scheduling: $($post.title)"

    $mediaId = $null
    if ($script:StatePosts.ContainsKey($slug) -and $script:StatePosts[$slug].wordpress_media_id) {
        $mediaId = [int]$script:StatePosts[$slug].wordpress_media_id
    }
    else {
        $media = Upload-WpMedia -ImagePath $item.CoverPath -AltText $item.CoverAlt
        $mediaId = [int]$media.id

        $script:StatePosts[$slug] = [ordered]@{
            source_file            = $item.File.Name
            wordpress_post_id      = $null
            wordpress_media_id     = $mediaId
            scheduled_date_eastern = $entry.Scheduled.ToString('yyyy-MM-ddTHH:mm:ss')
            status                 = 'media-uploaded'
            wordpress_url          = $null
            last_sync              = (Get-Date).ToString('o')
            note                   = 'Media uploaded; post creation pending.'
        }
        Save-State
    }

    $tagNames = @()
    if ($post.PSObject.Properties['tags'] -and $post.tags) { $tagNames = @($post.tags) }
    $tagIds = @(Resolve-WpTagIds -TagNames $tagNames)

    $unspecified = [DateTime]::SpecifyKind($entry.Scheduled, [DateTimeKind]::Unspecified)
    $utc = [TimeZoneInfo]::ConvertTimeToUtc($unspecified, $timeZone)

    $body = [ordered]@{
        title          = [string]$post.title
        slug           = $slug
        content        = (Remove-CoverFigure -Html ([string]$post.content_html))
        excerpt        = [string]$post.excerpt
        status         = 'future'
        date           = $entry.Scheduled.ToString('yyyy-MM-ddTHH:mm:ss')
        date_gmt       = $utc.ToString('yyyy-MM-ddTHH:mm:ss')
        featured_media = $mediaId
    }
    if ($tagIds.Count -gt 0) { $body.tags = $tagIds }

    try {
        $created = Invoke-WpJson -Method Post -Path 'posts' -Body $body
        $script:StatePosts[$slug] = [ordered]@{
            source_file            = $item.File.Name
            wordpress_post_id      = [int]$created.id
            wordpress_media_id     = $mediaId
            scheduled_date_eastern = $entry.Scheduled.ToString('yyyy-MM-ddTHH:mm:ss')
            status                 = [string]$created.status
            wordpress_url          = [string]$created.link
            last_sync              = (Get-Date).ToString('o')
            note                   = 'Created by Publish-WordPress.ps1'
        }
        Save-State
        Write-Host "  Post ID $($created.id) scheduled for $($entry.Scheduled.ToString('ddd yyyy-MM-dd h:mm tt')) Eastern."
    }
    catch {
        Write-Error "Failed to create '$slug' after media upload. State was preserved so the media item can be reused. $($_.Exception.Message)"
    }
}

Write-Host ''
Write-Host "Finished. Commit the updated state file: $StatePath"
