# Isolated publisher test; never contacts WordPress.
$ErrorActionPreference = 'Stop'
function Assert($Condition, [string]$Message) { if (-not $Condition) { throw $Message } }
$temp = Join-Path ([IO.Path]::GetTempPath()) ('wp-publish-tags-' + [guid]::NewGuid())
$postsPath = Join-Path $temp 'posts'
$mediaPath = Join-Path $temp 'media'
New-Item -ItemType Directory -Path $postsPath,$mediaPath -Force | Out-Null
New-Item -ItemType File -Path (Join-Path $mediaPath 'cover.jpg') | Out-Null
$statePath = Join-Path $temp 'state.json'
$post = @{
    slug='example-project'; title='Example Project'; excerpt='An example project.'
    content_html='<p>An example project.</p>'
    tags=@('Finance','Web projects')
    cover=@{src='media/cover.jpg';alt='Example project cover'}
}
$post | ConvertTo-Json -Depth 10 | Set-Content (Join-Path $postsPath 'example-project.json')
$publishedPost = @{
    slug='published-project'; title='Published Project'; excerpt='A published project.'
    content_html='<p>A published project.</p>'
    tags=@('Games')
    cover=@{src='media/cover.jpg';alt='Published project cover'}
}
$publishedPost | ConvertTo-Json -Depth 10 | Set-Content (Join-Path $postsPath 'published-project.json')
$originalState = @{
    posts=@{
        'example-project'=@{
            source_file='example-project.json'; wordpress_post_id=123; wordpress_media_id=456
            scheduled_date_eastern='2026-11-27T12:00:00'; status='future'
            wordpress_url='https://example.invalid/?p=123'; last_sync='2026-11-19T10:00:00Z'
        }
        'published-project'=@{
            source_file='published-project.json'; wordpress_post_id=124; wordpress_media_id=789
            scheduled_date_eastern='2026-10-01T12:00:00'; status='publish'
            wordpress_url='https://example.invalid/?p=124'; last_sync='2026-10-01T13:00:00Z'
        }
    }
} | ConvertTo-Json -Depth 10
$argsForPublisher = @{
    SiteUrl='https://example.invalid'; Username='mock'; AppPassword='mock'
    PostsPath=$postsPath; StatePath=$statePath; UpdateExisting=$true
}
function Reset-Mock {
    $originalState | Set-Content $statePath
    $global:WordPressPublishTestRequests = @()
    $global:WordPressPublishTestTags = @{'Jason AI Automation'=50}
    $global:WordPressPublishTestFailCreation = $false
    $global:WordPressPublishTestNextTagId = 60
}
function Invoke-RestMethod {
    param($Method,$Uri,$Headers,$ContentType,$Body,$ErrorAction)
    $global:WordPressPublishTestRequests += [pscustomobject]@{Method=$Method;Uri=$Uri;Body=$Body}
    if ($Uri -like 'https://example.invalid/wp-json/wp/v2/users/me*') {
        return [pscustomobject]@{id=77;name='Automation User';slug='automation-user'}
    }
    if ($Uri -like 'https://example.invalid/wp-json/wp/v2/posts?slug=*') {
        if ($Uri -like '*slug=published-project*') {
            return [pscustomobject]@{id=124;slug='published-project';status='publish';link='https://example.invalid/?p=124';date='2026-10-01T12:00:00'}
        }
        return [pscustomobject]@{id=123;slug='example-project';status='future';link='https://example.invalid/?p=123';date='2026-11-27T12:00:00'}
    }
    if ($Method -eq 'Get' -and $Uri -like 'https://example.invalid/wp-json/wp/v2/tags?search=*') {
        $name = [uri]::UnescapeDataString((($Uri -split 'search=')[1] -split '&')[0])
        if ($global:WordPressPublishTestTags.ContainsKey($name)) {
            return [pscustomobject]@{id=$global:WordPressPublishTestTags[$name];name=$name}
        }
        return
    }
    if ($Method -eq 'Post' -and $Uri -eq 'https://example.invalid/wp-json/wp/v2/tags') {
        if ($global:WordPressPublishTestFailCreation) { throw 'Tag creation forbidden' }
        $name = ($Body | ConvertFrom-Json).name
        $id = $global:WordPressPublishTestNextTagId++
        $global:WordPressPublishTestTags[$name] = $id
        return [pscustomobject]@{id=$id;name=$name}
    }
    if ($Method -eq 'Post' -and $Uri -eq 'https://example.invalid/wp-json/wp/v2/posts/123') {
        $payload = $Body | ConvertFrom-Json
        Assert (@($payload.PSObject.Properties.Name) -join ',' -eq 'tags') 'Taxonomy update sent unrelated post fields'
        Assert (@($payload.tags | Sort-Object) -join ',' -eq '50,60,61') 'Missing source tags in WordPress update'
        return [pscustomobject]@{id=123;slug='example-project';status='future';link='https://example.invalid/?p=123';date='2026-11-27T12:00:00'}
    }
    throw "Unexpected API request: $Method $Uri"
}
try {
    Reset-Mock
    & (Join-Path $PSScriptRoot 'Publish-WordPress.ps1') @argsForPublisher | Out-Null
    Assert (@($global:WordPressPublishTestRequests | Where-Object Method -eq Post).Count -eq 0) 'Dry-run wrote to WordPress'
    & (Join-Path $PSScriptRoot 'Publish-WordPress.ps1') @argsForPublisher -Commit | Out-Null
    $created = @($global:WordPressPublishTestRequests | Where-Object { $_.Method -eq 'Post' -and $_.Uri -like '*/tags' })
    Assert ($created.Count -eq 2) 'Default commit did not create both missing tags'
    $stateJson = Get-Content $statePath -Raw
    $state = if ((Get-Command ConvertFrom-Json).Parameters.ContainsKey('DateKind')) {
        $stateJson | ConvertFrom-Json -DateKind String
    }
    else {
        $stateJson | ConvertFrom-Json
    }
    Assert ($state.posts.'example-project'.wordpress_media_id -eq 456) 'Existing media ID was lost'
    Assert ($state.posts.'example-project'.scheduled_date_eastern -eq '2026-11-27T12:00:00') 'Schedule changed'
    Assert ($state.posts.'published-project'.wordpress_media_id -eq 789) 'Published post media ID changed'
    Assert (([datetimeoffset]$state.posts.'published-project'.last_sync).ToUniversalTime() -eq [datetimeoffset]'2026-10-01T13:00:00Z') 'Ineligible published post state changed'
    Reset-Mock
    $global:WordPressPublishTestFailCreation = $true
    $failed = $false
    try { & (Join-Path $PSScriptRoot 'Publish-WordPress.ps1') @argsForPublisher -Commit | Out-Null }
    catch { $failed = $true; Assert ($_.Exception.Message -match 'Could not create WordPress tag') 'Unexpected tag creation error' }
    Assert $failed 'Failed tag creation did not stop the update'
    Assert (@($global:WordPressPublishTestRequests | Where-Object { $_.Method -eq 'Post' -and $_.Uri -like '*/posts/123' }).Count -eq 0) 'Post changed after tag creation failed'
    Write-Host 'PASS: default tag creation, complete taxonomy, preserved schedule/media, dry-run, and tag-creation failure.'
} finally {
    Remove-Item -LiteralPath $temp -Recurse -Force
    Remove-Variable WordPressPublishTestRequests,WordPressPublishTestTags,WordPressPublishTestFailCreation,WordPressPublishTestNextTagId -Scope Global -ErrorAction SilentlyContinue
}
