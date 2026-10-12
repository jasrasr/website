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
    [switch]$CreateMissingCategories,
    [switch]$AddGitHubTag,
    [switch]$UpdateExisting,
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
        # Force REST collection responses through the pipeline so PowerShell
        # does not retain the response array as one nested item.
        $matches = @(Invoke-WpJson -Method Get -Path "tags?search=$encoded&per_page=100&_fields=id,name" | ForEach-Object { $_ })
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

function Resolve-WpCategoryIds {
    param([string[]]$CategoryNames)

    $ids = @()
    foreach ($categoryName in @($CategoryNames)) {
        if ([string]::IsNullOrWhiteSpace($categoryName)) { continue }

        $encoded = [uri]::EscapeDataString($categoryName)
        $matches = @(Invoke-WpJson -Method Get -Path "categories?search=$encoded&per_page=100&_fields=id,name" | ForEach-Object { $_ })
        $exact = $matches | Where-Object { $_.name -ieq $categoryName } | Select-Object -First 1

        if (-not $exact -and $CreateMissingCategories) {
            try {
                $exact = Invoke-WpJson -Method Post -Path 'categories' -Body @{ name = $categoryName }
            }
            catch {
                Write-Warning "Could not create WordPress category '$categoryName': $($_.Exception.Message)"
            }
        }

        if ($exact) {
            $ids += [int]$exact.id
        }
        else {
            Write-Warning "WordPress category '$categoryName' was not found and will be omitted."
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

function Get-PostTagNames {
    param([object]$Post)

    $tagNames = @()
    if ($Post.PSObject.Properties['tags'] -and $Post.tags) { $tagNames = @($Post.tags) }
    if ($AddGitHubTag -and $tagNames -notcontains 'GitHub') { $tagNames += 'GitHub' }
    @($tagNames)
}

function Get-PostCategoryNames {
    param([object]$Post)

    if ($Post.PSObject.Properties['categories'] -and $Post.categories) {
        return @($Post.categories)
    }
    @()
}

function Format-ScheduledEastern {
    param([object]$DateValue)

    if ($null -eq $DateValue -or [string]::IsNullOrWhiteSpace([string]$DateValue)) { return '' }
    try {
        return ([datetime]$DateValue).ToString('yyyy-MM-dd h:mm tt')
    }
    catch {
        return [string]$DateValue
    }
}

function Get-TrackedScheduledEastern {
    param([string]$Slug)

    if ($script:StatePosts.ContainsKey($Slug)) {
        return $script:StatePosts[$Slug].scheduled_date_eastern
    }
    $null
}

function New-PostSummaryRow {
    param(
        [object]$Post,
        [object]$ScheduledEastern
    )

    [pscustomobject]@{
        ScheduledEastern = Format-ScheduledEastern -DateValue $ScheduledEastern
        Title            = [string]$Post.title
        Slug             = [string]$Post.slug
        Categories       = ((Get-PostCategoryNames -Post $Post) -join ', ')
        Tags             = ((Get-PostTagNames -Post $Post) -join ', ')
    }
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
$existingUpdates = @()

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
        if ($UpdateExisting) {
            $ready += [pscustomobject]@{
                Data            = $post
                File            = $file
                CoverPath       = $coverPath
                CoverAlt        = $coverAlt
                ExistingPostId  = [int]$script:StatePosts[[string]$post.slug].wordpress_post_id
            }
        }
        else {
            $alreadyTracked += [pscustomobject]@{
                Slug        = $post.slug
                WordPressId = $script:StatePosts[[string]$post.slug].wordpress_post_id
                Source      = $file.Name
            }
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

if (($Commit -or $UpdateExisting) -and ([string]::IsNullOrWhiteSpace($Username) -or [string]::IsNullOrWhiteSpace($AppPassword))) {
    throw 'Commit or update-existing mode requires a WordPress username and application password.'
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
        # WordPress returns a JSON array for this endpoint.  Enumerate it before
        # wrapping it so $found[0] is a post object, not an Object[] container.
        $found = @(Invoke-WpJson -Method Get -Path "posts?slug=$encodedSlug&status=any&per_page=1&_fields=id,slug,status,link,date" | ForEach-Object { $_ })

        if ($found.Count -gt 0) {
            $status = [string]$found[0].status
            if ($UpdateExisting -and $status -in @('future','draft','pending')) {
                $item | Add-Member -NotePropertyName ExistingPostId -NotePropertyValue ([int]$found[0].id) -Force
                $existingUpdates += $item
                $script:StatePosts[[string]$item.Data.slug] = [ordered]@{
                    source_file            = $item.File.Name
                    wordpress_post_id      = [int]$found[0].id
                    wordpress_media_id     = $null
                    scheduled_date_eastern = [string]$found[0].date
                    status                 = $status
                    wordpress_url          = [string]$found[0].link
                    last_sync              = (Get-Date).ToString('o')
                    note                   = 'Found for existing-post update.'
                }
            }
            else {
                $serverExistingSlugs += [string]$item.Data.slug
                $script:StatePosts[[string]$item.Data.slug] = [ordered]@{
                    source_file            = $item.File.Name
                    wordpress_post_id      = [int]$found[0].id
                    wordpress_media_id     = $null
                    scheduled_date_eastern = [string]$found[0].date
                    status                 = $status
                    wordpress_url          = [string]$found[0].link
                    last_sync              = (Get-Date).ToString('o')
                    note                   = 'Discovered on WordPress by slug; duplicate creation skipped.'
                }
            }
        }
    }

    if ($UpdateExisting) {
        $ready = @()
    }
    elseif ($serverExistingSlugs.Count -gt 0) {
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
Write-Host ('  Existing to update: {0}' -f $existingUpdates.Count)
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
        New-PostSummaryRow -Post $_.Post.Data -ScheduledEastern $_.Scheduled
    } | Format-Table -AutoSize -Wrap | Out-Host
}

if ($existingUpdates.Count -gt 0) {
    Write-Host ''
    Write-Host 'Existing posts to update:'
    $existingUpdates | Sort-Object {
        $scheduledEastern = Get-TrackedScheduledEastern -Slug ([string]$_.Data.slug)
        if ($scheduledEastern) { [datetime]$scheduledEastern } else { [datetime]::MaxValue }
    }, { [string]$_.Data.title } | ForEach-Object {
        $slug = [string]$_.Data.slug
        $scheduledEastern = Get-TrackedScheduledEastern -Slug $slug
        New-PostSummaryRow -Post $_.Data -ScheduledEastern $scheduledEastern
    } | Format-Table -AutoSize -Wrap | Out-Host
}

if (-not $Commit) {
    Write-Host ''
    Write-Host 'DRY RUN ONLY. Re-run with -Commit after reviewing the schedule.'
    return
}

foreach ($item in @($existingUpdates | Sort-Object {
    $scheduledEastern = Get-TrackedScheduledEastern -Slug ([string]$_.Data.slug)
    if ($scheduledEastern) { [datetime]$scheduledEastern } else { [datetime]::MaxValue }
}, { [string]$_.Data.title })) {
    $post = $item.Data
    $slug = [string]$post.slug
    Write-Host "Updating existing post: $($post.title)"

    $tagNames = @(Get-PostTagNames -Post $post)
    $tagIds = @(Resolve-WpTagIds -TagNames $tagNames)

    $body = [ordered]@{ tags = $tagIds }
    if ($post.PSObject.Properties['categories'] -and $post.categories) {
        $body.categories = @(Resolve-WpCategoryIds -CategoryNames @($post.categories))
    }

    try {
        $updated = Invoke-WpJson -Method Post -Path "posts/$([int]$item.ExistingPostId)" -Body $body
        $script:StatePosts[$slug] = [ordered]@{
            source_file            = $item.File.Name
            wordpress_post_id      = [int]$updated.id
            wordpress_media_id     = if ($script:StatePosts.ContainsKey($slug)) { $script:StatePosts[$slug].wordpress_media_id } else { $null }
            scheduled_date_eastern = [string]$updated.date
            status                 = [string]$updated.status
            wordpress_url          = [string]$updated.link
            last_sync              = (Get-Date).ToString('o')
            note                   = 'Updated by Publish-WordPress.ps1'
        }
        Save-State
        Write-Host "  Updated post ID $($updated.id)."
    }
    catch {
        Write-Error "Failed to update '$slug'. $($_.Exception.Message)"
    }
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

    $tagNames = @(Get-PostTagNames -Post $post)
    $tagIds = @(Resolve-WpTagIds -TagNames $tagNames)

    $categoryIds = @()
    if ($post.PSObject.Properties['categories'] -and $post.categories) {
        $categoryIds = @(Resolve-WpCategoryIds -CategoryNames @($post.categories))
    }

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
    if ($categoryIds.Count -gt 0) { $body.categories = $categoryIds }

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
