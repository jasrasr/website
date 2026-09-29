# Revision: 1.0.0 — 2026-09-29. Isolated API mocks; never contacts WordPress.
$ErrorActionPreference = 'Stop'
function Assert($Condition, [string]$Message) { if (-not $Condition) { throw $Message } }
$temp = Join-Path ([IO.Path]::GetTempPath()) ('wp-content-test-' + [guid]::NewGuid())
New-Item -ItemType Directory -Path (Join-Path $temp 'posts') -Force | Out-Null
$sync = Join-Path $PSScriptRoot 'Sync-WordPressContent.ps1'
$argsForSync = @{
    SiteUrl='https://example.invalid'; Username='mock'; AppPassword='mock'
    PostsPath=(Join-Path $temp 'posts'); StatePath=(Join-Path $temp 'state.json')
    BackupPath=(Join-Path $temp 'backups')
}
$fixtureState = @{ posts=@{} }
foreach ($id in @(1,2)) {
    $slug = "article-$id"
    @{ slug=$slug; title="Article $id"; excerpt='Neutral excerpt'; content_html='<figure class="post-cover"><img src="media/cover.jpg"></figure><p>The project helps users.</p>' } |
        ConvertTo-Json | Set-Content (Join-Path $temp "posts/$slug.json")
    $fixtureState.posts[$slug] = @{ wordpress_post_id=$id; wordpress_url="https://example.invalid/?p=$id" }
}
$fixtureState | ConvertTo-Json -Depth 10 | Set-Content $argsForSync.StatePath
function Reset-Mock {
    $global:WordPressSyncTestRequests = @()
    $global:WordPressSyncTestRemote = @{}
    $global:WordPressSyncTestConflict = $false
    foreach ($id in @(1,2)) {
        $global:WordPressSyncTestRemote[$id] = [pscustomobject]@{
            id=$id; slug="article-$id"; status=$(if ($id -eq 1) {'publish'} else {'future'}); author=77
            date='2026-10-02T09:00:00'; date_gmt='2026-10-02T13:00:00'; modified_gmt='2026-09-18T12:00:00'
            featured_media=90; tags=@(5); categories=@(6)
            title=[pscustomobject]@{raw='Old title'}; excerpt=[pscustomobject]@{raw='Old excerpt'}; content=[pscustomobject]@{raw='<p>I like this project.</p>'}
        }
    }
}
function Invoke-RestMethod {
    param($Method,$Uri,$Headers,$MaximumRedirection,$ContentType,$Body)
    if ($Uri -notmatch '^https://example\.invalid/wp-json/wp/v2/posts/([12])(?:\?context=edit)?$') { throw "Unexpected API request: $Uri" }
    $id = [int]$Matches[1]
    Assert ($MaximumRedirection -eq 0) 'Authenticated redirects must be disabled'
    $global:WordPressSyncTestRequests += [pscustomobject]@{Method=$Method; Id=$id; Body=$Body}
    if ($Method -eq 'Get') {
        if ($global:WordPressSyncTestConflict -and $global:WordPressSyncTestRequests.Count -gt 2) { $global:WordPressSyncTestRemote[$id].modified_gmt='2026-09-29T12:00:00' }
    }
    elseif ($Method -eq 'Post') {
        Assert ((Test-Path $argsForSync.BackupPath)) 'Backup must precede the first update'
        $payload = $Body | ConvertFrom-Json
        Assert ((@($payload.PSObject.Properties.Name | Sort-Object) -join ',') -eq 'content,excerpt,title') 'Update contains fields beyond text'
        Assert ($payload.content -notmatch 'post-cover') 'Static cover must not be duplicated'
        foreach ($key in @('title','excerpt','content')) { $global:WordPressSyncTestRemote[$id].$key.raw=$payload.$key }
    }
    else { throw "Unexpected method: $Method" }
    # Return detached objects, like a real REST round trip.
    $global:WordPressSyncTestRemote[$id] | ConvertTo-Json -Depth 20 | ConvertFrom-Json
}
function Expect-Failure([string]$Pattern, [scriptblock]$Action) {
    $failed=$false
    try { & $Action } catch { $failed=$true; Assert ($_.Exception.Message -match $Pattern) "Unexpected error: $_" }
    Assert $failed "Expected failure: $Pattern"
    Assert (@($global:WordPressSyncTestRequests | Where-Object Method -eq Post).Count -eq 0) 'Preflight failure made a write'
}
try {
    Reset-Mock
    & $sync @argsForSync
    Assert ($global:WordPressSyncTestRequests.Count -eq 2) 'Dry-run should read both posts'
    Assert (@($global:WordPressSyncTestRequests | Where-Object Method -eq Post).Count -eq 0) 'Dry-run made a write'
    Assert (-not (Test-Path $argsForSync.BackupPath)) 'Dry-run created a backup directory'
    & $sync @argsForSync -Commit
    Assert (@($global:WordPressSyncTestRequests | Where-Object Method -eq Post).Count -eq 2) 'Expected exactly two existing-post updates'
    Assert ($global:WordPressSyncTestRemote[1].status -eq 'publish' -and $global:WordPressSyncTestRemote[2].status -eq 'future') 'Status changed'
    $backup = Get-ChildItem $argsForSync.BackupPath -Filter '*.json' | Select-Object -First 1
    $saved = Get-Content $backup.FullName -Raw | ConvertFrom-Json
    Assert ($saved.posts.Count -eq 2 -and $saved.posts[0].content.raw -eq '<p>I like this project.</p>') 'Backup lost original raw text'
    $global:WordPressSyncTestRequests=@()
    & $sync @argsForSync -Commit
    Assert (@($global:WordPressSyncTestRequests | Where-Object Method -eq Post).Count -eq 0) 'Second run should be a no-op'
    Reset-Mock
    $global:WordPressSyncTestRemote[2].slug='wrong-post'
    Expect-Failure 'ID/slug mismatch' { & $sync @argsForSync -Commit }
    Reset-Mock
    $global:WordPressSyncTestRemote[2].status='trash'
    Expect-Failure 'Unsupported post status' { & $sync @argsForSync -Commit }
    Reset-Mock
    $global:WordPressSyncTestConflict=$true
    Expect-Failure 'changed since preflight' { & $sync @argsForSync -Commit }
    Reset-Mock
    Expect-Failure 'Untracked slug' { & $sync @argsForSync -Slug unknown -Commit }
    Reset-Mock
    $otherSite=$argsForSync.Clone(); $otherSite.SiteUrl='https://other.invalid'
    Expect-Failure 'different WordPress site' { & $sync @otherSite -Commit }
    Reset-Mock
    & $sync @argsForSync -Slug article-1 -Commit
    Assert (@($global:WordPressSyncTestRequests | Where-Object Method -eq Post).Count -eq 1) 'Slug filter should update only one post'
    Write-Host 'PASS: dry-run, content-only updates, published/scheduled preservation, backup, repeat-run, slug/status/site guards, concurrent-edit guard and single-post selection.'
} finally {
    Remove-Item -LiteralPath $temp -Recurse -Force
    Remove-Variable WordPressSyncTestRequests,WordPressSyncTestRemote,WordPressSyncTestConflict -Scope Global -ErrorAction SilentlyContinue
}
