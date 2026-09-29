# Revision: 1.0.0 — 2026-09-29. Exercise local note commands and CI gates in an isolated Git repo.
$ErrorActionPreference = 'Stop'
function Assert($Condition, [string]$Message) { if (-not $Condition) { throw $Message } }
$root = Split-Path $PSScriptRoot -Parent
$temp = Join-Path ([IO.Path]::GetTempPath()) ('blog-note-test-' + [guid]::NewGuid())
$add = Join-Path $PSScriptRoot 'Add-BlogRevision.ps1'
$validate = Join-Path $PSScriptRoot 'Test-BlogRevisions.ps1'
$slug = 'personal-budget-tracker-php-json'
New-Item -ItemType Directory -Path (Join-Path $temp 'blog/posts'), (Join-Path $temp 'blog/tools') -Force | Out-Null
$path = Join-Path $temp "blog/posts/$slug.json"
Copy-Item (Join-Path $root "posts/$slug.json") $path
@{posts=@{$slug=@{wordpress_post_id=1}}} | ConvertTo-Json -Depth 10 | Set-Content (Join-Path $temp 'blog/tools/wordpress-state.json')
$original = Get-Content $path -Raw
function Expect-NoteFailure([string]$Pattern, [scriptblock]$Action) {
    $failed=$false
    try { & $Action } catch { $failed=$true; Assert ($_.Exception.Message -match $Pattern) "Unexpected failure: $_" }
    Assert $failed "Expected rejection: $Pattern"
}
Push-Location $temp
try {
    & git init -q
    & git add blog
    & git -c user.name=Test -c user.email=test@example.invalid commit -qm 'Fixture baseline'
    if ($LASTEXITCODE -ne 0) { throw 'Failed to create isolated Git fixture.' }
    $post = $original | ConvertFrom-Json
    $post.content_html = $post.content_html.Replace('A budget gets harder', 'A budget becomes harder')
    $post | ConvertTo-Json -Depth 30 | Set-Content $path
    Expect-NoteFailure 'without a new revision note' { & $validate -RootPath (Join-Path $temp 'blog') -BaseRef HEAD }
    $options = @{RootPath=(Join-Path $temp 'blog');Slug=$slug;Id='2026-09-30-fixture';Date='2026-09-30';Summary='Clarified the budget explanation.';SourceUrl='https://github.com/jasrasr/website/pull/90'}
    & $add @options
    & $validate -RootPath (Join-Path $temp 'blog') -BaseRef HEAD
    $before = Get-Content $path -Raw
    & $add @options
    Assert ((Get-Content $path -Raw) -ceq $before) 'Repeated note command changed the source'
    $options.Summary='Different summary.'
    Expect-NoteFailure 'already exists with different content' { & $add @options }
    $post = $before | ConvertFrom-Json
    $post.revision_history[0].summary='Replaced an old record.'
    . (Join-Path $PSScriptRoot 'Blog-Revisions.ps1')
    $post.content_html=Get-BlogRevisionContent $post
    $post | ConvertTo-Json -Depth 30 | Set-Content $path
    Expect-NoteFailure 'Edited revision' { & $validate -RootPath (Join-Path $temp 'blog') -BaseRef HEAD }
    $post = $before | ConvertFrom-Json
    $post.revision_history=@($post.revision_history | Where-Object id -eq '2026-09-30-fixture')
    $post.content_html=Get-BlogRevisionContent $post
    $post | ConvertTo-Json -Depth 30 | Set-Content $path
    Expect-NoteFailure 'Deleted revision' { & $validate -RootPath (Join-Path $temp 'blog') -BaseRef HEAD }
    Write-Host 'PASS: new-note requirement, note creation, repeated-command no-op, ID conflict and immutable history gates.'
} finally {
    Pop-Location
    Remove-Item -LiteralPath $temp -Recurse -Force
}
