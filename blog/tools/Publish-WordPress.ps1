<#
.SYNOPSIS
    Schedules JSON blog posts on WordPress through the REST API.

.DESCRIPTION
    Revision: 1.0.0
    Source of truth: blog/posts/*.json
    WordPress-only state: blog/tools/wordpress-state.json

    Dry-run is the default. Use -Commit to create scheduled WordPress posts.
    Posts are scheduled Monday-Friday beginning tomorrow, between 08:00 and
    17:00 America/New_York. Every used weekday receives at least one post.
    With the default MaxPostsPerDay=2, roughly 40 posts span about four weeks.

    Authentication uses a dedicated WordPress username and application password.
    Prefer environment variables WORDPRESS_USERNAME and WORDPRESS_APP_PASSWORD.
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

    [ValidateRange(1, 10)]
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
    throw 'Unable to resolve the America/New_York time zone on this system.'
}

function Get-AuthHeader {
    param([string]$User, [string]$Password)
    $token = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("$User`:$Password"))
    return @{ Authorization = "Basic $token" }
}

function Invoke-WpJson {
    param(
        [Parameter(Mandatory)][ValidateSet('Get','Post')][string]$Method,
        [Parameter(Mandatory)][string]$Path,
        [object]$Body
    )

    $uri = '{0}/wp-json/wp/v2/{1}' -f $script:WpBase, $Path.TrimStart('/')
    $params = @{
        Method      = $Method
        Uri         = $uri
        Headers     = $script:AuthHeaders
        ErrorAction = 'Stop'
    }
    if ($PSBoundParameters.ContainsKey('Body')) {
        $params.ContentType = 'application/json; charset=utf-8'
        $params.Body = ($Body | ConvertTo-Json -Depth 20 -Compress)
    }
    Invoke-RestMethod @params
}

function Save-State {
    $script:State._meta.modified_date = (Get-Date).ToString('yyyy-MM-dd')
    $script:State | ConvertTo-Json -Depth 20 | Set-Content -LiteralPath $StatePath -Encoding utf8
}

function Get-MimeType {
    param([string]$Path)
    switch ([IO.Path]::GetExtension($Path).ToLowerInvariant()) {
        '.jpg'  { 'image/jpeg' }
        '.jpeg' { 'image/jpeg' }
        '.png'  { 'image/png' }
        '.gif'  { 'image/gif' }
        '.webp' { 'image/webp' }
        default { throw "Unsupported featured-image type: $Path" }
    }
}

function Upload-WpMedia {
    param([string]$ImagePath, [string]$AltText)

    $fileName = [IO.Path]::GetFileName($ImagePath)
    $headers = @{}
    foreach ($key in $script:AuthHeaders.Keys) { $headers[$key] = $script:AuthHeaders[$key] }
    $headers['Content-Disposition'] = "attachment; filename=`"$fileName`""

    $media = Invoke-RestMethod -Method Post \
        -Uri "$script:WpBase/wp-json/wp/v2/media" \
        -Headers $headers \
        -ContentType (Get-MimeType -Path $ImagePath) \
        -InFile $ImagePath \
        -ErrorAction Stop

    if ($AltText) {
        Invoke-WpJson -Method Post -Path "media/$($media.id)" -Body @{ alt_text = $AltText } | Out-Null
    }
    return $media
}

function Resolve-WpTagIds {
    param([string[]]$TagNames)

    $ids = [Collections.Generic.List[int]]::new()
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
            $ids.Add([int]$exact.id)
        }
        else {
            Write-Warning "WordPress tag '$tagName' does not exist; it will be omitted. Use -CreateMissingTags if this API user can create tags."
        }
    }
    return @($ids)
}

function Remove-CoverFigure {
    param([string]$Html)
    if ($KeepCoverInContent) { return $Html }
    return [regex]::Replace(
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
    return [IO.Path]::GetFullPath((Join-Path $BlogRoot $relative))
}

function Get-EligibleWeekdays {
    param([datetime]$StartDate, [int]$Count)
    $days = [Collections.Generic.List[datetime]]::new()
    $cursor = $StartDate.Date
    while ($days.Count -lt $Count) {
        if ($cursor.DayOfWeek -notin @([DayOfWeek]::Saturday, [DayOfWeek]::Sunday)) {
            $days.Add($cursor)
        }
        $cursor = $cursor.AddDays(1)
    }
    return @($days)
}

function New-RandomTimesForDay {
    param([datetime]$Date, [int]$Count)

    if ($Count -eq 1) {
        return @($Date.AddHours(8).AddMinutes((Get-Random -Minimum 0 -Maximum 541)))
    }

    for ($attempt = 0; $attempt -lt 5000; $attempt++) {
        $mins = @(1..$Count | ForEach-Object { Get-Random -Minimum 0 -Maximum 541 } | Sort-Object)
        $ok = $true
        for ($i = 1; $i -lt $mins.Count; $i++) {
            if (($mins[$i] - $mins[$i - 1]) -lt $MinSpacingMinutes) { $ok = $false; break }
        }
        if ($ok) { return @($mins | ForEach-Object { $Date.AddHours(8).AddMinutes($_) }) }
    }
    throw "Unable to place $Count posts between 08:00 and 17:00 with $MinSpacingMinutes-minute spacing. Reduce MaxPostsPerDay or MinSpacingMinutes."
}

function New-PostSchedule {
    param([object[]]$Posts, [datetime]$StartDate)

    if (-not $Posts.Count) { return @() }
    $dayCount = [Math]::Ceiling($Posts.Count / [double]$MaxPostsPerDay)
    $days = @(Get-EligibleWeekdays -StartDate $StartDate -Count $dayCount)

    $counts = @{}
    foreach ($d in $days) { $counts[$d.ToString('yyyy-MM-dd')] = 1 }
    $remaining = $Posts.Count - $days.Count

    while ($remaining -gt 0) {
        $available = @($days | Where-Object { $counts[$_.ToString('yyyy-MM-dd')] -lt $MaxPostsPerDay })
        if (-not $available.Count) { throw 'Schedule allocation exceeded MaxPostsPerDay.' }
        $chosen = Get-Random -InputObject $available
        $counts[$chosen.ToString('yyyy-MM-dd')]++
        $remaining--
    }

    $slots = [Collections.Generic.List[datetime]]::new()
    foreach ($day in $days) {
        foreach ($time in (New-RandomTimesForDay -Date $day -Count $counts[$day.ToString('yyyy-MM-dd')])) {
            $slots.Add($time)
        }
    }
    $slots = @($slots | Sort-Object)

    $orderedPosts = if ($PreservePostOrder) { @($Posts | Sort-Object { $_.data.date }, { $_.data.slug }) } else { @(Get-Random -InputObject $Posts -Count $Posts.Count) }
    for ($i = 0; $i -lt $orderedPosts.Count; $i++) {
        [pscustomobject]@{ Post = $orderedPosts[$i]; Scheduled = $slots[$i] }
    }
}

$script:WpBase = $SiteUrl.TrimEnd('/')
$tz = Get-EasternTimeZone
$nowEastern = [TimeZoneInfo]::ConvertTime([DateTimeOffset]::UtcNow, $tz)
$startDate = $nowEastern.Date.AddDays(1)
$blogRoot = [IO.Path]::GetFullPath((Join-Path $PostsPath '..'))

if (-not (Test-Path -LiteralPath $PostsPath -PathType Container)) {
    throw "Posts folder not found: $PostsPath"
}

if (Test-Path -LiteralPath $StatePath) {
    $script:State = Get-Content -LiteralPath $StatePath -Raw | ConvertFrom-Json
}
else {
    $script:State = [pscustomobject]@{
        _meta = [pscustomobject]@{ schema_version='1.0.0'; modified_date=(Get-Date).ToString('yyyy-MM-dd') }
        posts = [pscustomobject]@{}
    }
}

$statePosts = @{}
foreach ($p in $script:State.posts.PSObject.Properties) { $statePosts[$p.Name] = $p.Value }

$ready = [Collections.Generic.List[object]]::new()
$invalid = [Collections.Generic.List[object]]::new()
$alreadyTracked = [Collections.Generic.List[object]]::new()

foreach ($file in Get-ChildItem -LiteralPath $PostsPath -Filter '*.json' -File | Where-Object { $_.Name -notlike '_*' }) {
    try { $post = Get-Content -LiteralPath $file.FullName -Raw | ConvertFrom-Json }
    catch {
        $invalid.Add([pscustomobject]@{ File=$file.Name; Problem="Invalid JSON: $($_.Exception.Message)" })
        continue
    }

    $missing = @()
    foreach ($field in @('slug','title','excerpt','content_html')) {
        if (-not $post.PSObject.Properties[$field] -or [string]::IsNullOrWhiteSpace([string]$post.$field)) { $missing += $field }
    }
    if (-not $post.PSObject.Properties['cover'] -or -not $post.cover.src) { $missing += 'cover.src' }

    $coverPath = if ($post.cover.src) { Resolve-CoverPath -CoverSrc ([string]$post.cover.src) -BlogRoot $blogRoot } else { $null }
    if (-not $coverPath -or -not (Test-Path -LiteralPath $coverPath -PathType Leaf)) { $missing += 'cover image file' }

    if ($missing.Count) {
        $invalid.Add([pscustomobject]@{ File=$file.Name; Problem=('Missing/invalid: ' + ($missing -join ', ')) })
        continue
    }

    if ($statePosts.ContainsKey([string]$post.slug) -and $statePosts[[string]$post.slug].wordpress_post_id) {
        $alreadyTracked.Add([pscustomobject]@{ Slug=$post.slug; WordPressId=$statePosts[[string]$post.slug].wordpress_post_id; Source=$file.Name })
        continue
    }

    $ready.Add([pscustomobject]@{ data=$post; file=$file; coverPath=$coverPath })
}

if ($Commit -and ([string]::IsNullOrWhiteSpace($Username) -or [string]::IsNullOrWhiteSpace($AppPassword))) {
    throw 'Commit mode requires -Username/-AppPassword or WORDPRESS_USERNAME/WORDPRESS_APP_PASSWORD environment variables.'
}

if ($Username -and $AppPassword) {
    $script:AuthHeaders = Get-AuthHeader -User $Username -Password $AppPassword
    try {
        $me = Invoke-WpJson -Method Get -Path 'users/me?context=edit&_fields=id,name,slug'
        Write-Host "WordPress API user: $($me.name) (ID $($me.id))"
    }
    catch {
        throw "WordPress authentication failed: $($_.Exception.Message)"
    }

    $serverExisting = [Collections.Generic.List[object]]::new()
    foreach ($item in @($ready)) {
        $slugEncoded = [uri]::EscapeDataString([string]$item.data.slug)
        $found = @(Invoke-WpJson -Method Get -Path "posts?slug=$slugEncoded&status=any&per_page=1&_fields=id,slug,status,link,date")
        if ($found.Count) {
            $serverExisting.Add($item)
            $statePosts[[string]$item.data.slug] = [pscustomobject]@{
                source_file = $item.file.Name
                wordpress_post_id = [int]$found[0].id
                wordpress_media_id = $null
                scheduled_date_eastern = [string]$found[0].date
                status = [string]$found[0].status
                wordpress_url = [string]$found[0].link
                last_sync = (Get-Date).ToString('o')
                note = 'Discovered on WordPress by slug; no duplicate created.'
            }
        }
    }
    if ($serverExisting.Count) {
        $ready = [Collections.Generic.List[object]]@($ready | Where-Object { $_.data.slug -notin $serverExisting.data.slug })
    }
}
else {
    $script:AuthHeaders = @{}
    Write-Warning 'No WordPress credentials supplied. Dry-run duplicate detection is state-file-only.'
}

$script:State.posts = [pscustomobject]$statePosts
if ($Commit -and $Username -and $AppPassword) { Save-State }

$schedule = @(New-PostSchedule -Posts @($ready) -StartDate $startDate)

Write-Host ''
Write-Host 'WordPress scheduling preflight'
Write-Host ('  Ready to schedule : {0}' -f $schedule.Count)
Write-Host ('  Already tracked   : {0}' -f $alreadyTracked.Count)
Write-Host ('  Invalid/skipped   : {0}' -f $invalid.Count)
if ($schedule.Count) {
    Write-Host ('  First scheduled   : {0}' -f $schedule[0].Scheduled.ToString('ddd yyyy-MM-dd h:mm tt'))
    Write-Host ('  Last scheduled    : {0}' -f $schedule[-1].Scheduled.ToString('ddd yyyy-MM-dd h:mm tt'))
}

if ($invalid.Count) {
    Write-Host ''
    Write-Warning 'Posts failing validation:'
    $invalid | Format-Table -AutoSize | Out-Host
}

if ($schedule.Count) {
    Write-Host ''
    $schedule | ForEach-Object {
        [pscustomobject]@{
            ScheduledEastern = $_.Scheduled.ToString('yyyy-MM-dd h:mm tt')
            Slug = $_.Post.data.slug
            Title = $_.Post.data.title
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
    $post = $item.data
    Write-Host "Scheduling: $($post.title)"

    $mediaId = $null
    if ($statePosts.ContainsKey([string]$post.slug) -and $statePosts[[string]$post.slug].wordpress_media_id) {
        $mediaId = [int]$statePosts[[string]$post.slug].wordpress_media_id
    }
    else {
        $media = Upload-WpMedia -ImagePath $item.coverPath -AltText ([string]$post.cover.alt)
        $mediaId = [int]$media.id
        $statePosts[[string]$post.slug] = [pscustomobject]@{
            source_file = $item.file.Name
            wordpress_post_id = $null
            wordpress_media_id = $mediaId
            scheduled_date_eastern = $entry.Scheduled.ToString('yyyy-MM-ddTHH:mm:ss')
            status = 'media-uploaded'
            wordpress_url = $null
            last_sync = (Get-Date).ToString('o')
            note = 'Media uploaded; post creation pending.'
        }
        $script:State.posts = [pscustomobject]$statePosts
        Save-State
    }

    $tagIds = @(Resolve-WpTagIds -TagNames @($post.tags))
    $unspecified = [DateTime]::SpecifyKind($entry.Scheduled, [DateTimeKind]::Unspecified)
    $utc = [TimeZoneInfo]::ConvertTimeToUtc($unspecified, $tz)

    $body = @{
        title = [string]$post.title
        slug = [string]$post.slug
        content = (Remove-CoverFigure -Html ([string]$post.content_html))
        excerpt = [string]$post.excerpt
        status = 'future'
        date = $entry.Scheduled.ToString('yyyy-MM-ddTHH:mm:ss')
        date_gmt = $utc.ToString('yyyy-MM-ddTHH:mm:ss')
        featured_media = $mediaId
    }
    if ($tagIds.Count) { $body.tags = $tagIds }

    try {
        $created = Invoke-WpJson -Method Post -Path 'posts' -Body $body
        $statePosts[[string]$post.slug] = [pscustomobject]@{
            source_file = $item.file.Name
            wordpress_post_id = [int]$created.id
            wordpress_media_id = $mediaId
            scheduled_date_eastern = $entry.Scheduled.ToString('yyyy-MM-ddTHH:mm:ss')
            status = [string]$created.status
            wordpress_url = [string]$created.link
            last_sync = (Get-Date).ToString('o')
            note = 'Created by Publish-WordPress.ps1'
        }
        $script:State.posts = [pscustomobject]$statePosts
        Save-State
        Write-Host "  WordPress post ID $($created.id) scheduled for $($entry.Scheduled.ToString('ddd yyyy-MM-dd h:mm tt')) Eastern."
    }
    catch {
        Write-Error "Failed to create '$($post.slug)' after media upload. State was preserved so the image will not be re-uploaded. $($_.Exception.Message)"
    }
}

Write-Host ''
Write-Host "Finished. Commit the updated state file to the repository: $StatePath"
