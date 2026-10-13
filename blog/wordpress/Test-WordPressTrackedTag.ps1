# Revision: 1.0.0 — 2026-10-13. Isolated API mocks; never contacts WordPress.
$ErrorActionPreference = 'Stop'
function Assert($Condition, [string]$Message) { if (-not $Condition) { throw $Message } }

$temp = Join-Path ([IO.Path]::GetTempPath()) ('wp-tag-test-' + [guid]::NewGuid())
New-Item -ItemType Directory -Path $temp -Force | Out-Null
$scriptPath = Join-Path $PSScriptRoot 'Add-WordPressTagToTracked.ps1'
$statePath = Join-Path $temp 'state.json'

$fixtureState = @{
    posts = @{
        'published-post' = @{ wordpress_post_id = 1 }
        'scheduled-post' = @{ wordpress_post_id = 2 }
        'already-tagged' = @{ wordpress_post_id = 3 }
        'trash-post' = @{ wordpress_post_id = 4 }
        'slug-mismatch' = @{ wordpress_post_id = 5 }
        'visual-ping-webhook-project-sketch' = @{ wordpress_post_id = 6 }
    }
}
$fixtureState | ConvertTo-Json -Depth 10 | Set-Content $statePath

function Reset-Mock {
    $global:WordPressTagTestRequests = @()
    $global:WordPressTagTestTag = [pscustomobject]@{ id = 50; name = 'Jason AI Automation'; slug = 'jason-ai-automation' }
    $global:WordPressTagTestRemote = @{
        1 = [pscustomobject]@{ id=1; slug='published-post'; status='publish'; title=[pscustomobject]@{rendered='Published Post'}; tags=@(7) }
        2 = [pscustomobject]@{ id=2; slug='scheduled-post'; status='future'; title=[pscustomobject]@{rendered='Scheduled Post'}; tags=@(8,9) }
        3 = [pscustomobject]@{ id=3; slug='already-tagged'; status='publish'; title=[pscustomobject]@{rendered='Already Tagged'}; tags=@(50,10) }
        4 = [pscustomobject]@{ id=4; slug='trash-post'; status='trash'; title=[pscustomobject]@{rendered='Trash Post'}; tags=@(11) }
        5 = [pscustomobject]@{ id=5; slug='other-slug'; status='publish'; title=[pscustomobject]@{rendered='Other Slug'}; tags=@(12) }
        6 = [pscustomobject]@{ id=6; slug='visual-ping-webhook-project-sketch'; status='trash'; title=[pscustomobject]@{rendered='Excluded Post'}; tags=@(13) }
    }
}

function Invoke-RestMethod {
    param($Method,$Uri,$Headers,$MaximumRedirection,$ContentType,$Body)

    Assert ($MaximumRedirection -eq 0) 'Authenticated redirects must be disabled'
    $global:WordPressTagTestRequests += [pscustomobject]@{ Method=$Method; Uri=$Uri; Body=$Body }

    if ($Uri -eq 'https://example.invalid/wp-json/wp/v2/users/me?context=edit&_fields=id,name') {
        return [pscustomobject]@{ id=77; name='Automation User' }
    }

    if ($Uri -eq 'https://example.invalid/wp-json/wp/v2/tags?slug=jason-ai-automation&per_page=100&_fields=id,name,slug') {
        return @($global:WordPressTagTestTag)
    }

    if ($Uri -match '^https://example\.invalid/wp-json/wp/v2/posts/([1-6])\?context=edit&_fields=id,slug,status,title,tags,link$') {
        return $global:WordPressTagTestRemote[[int]$Matches[1]] | ConvertTo-Json -Depth 10 | ConvertFrom-Json
    }

    if ($Method -eq 'Post' -and $Uri -match '^https://example\.invalid/wp-json/wp/v2/posts/([1-6])$') {
        $id = [int]$Matches[1]
        $payload = $Body | ConvertFrom-Json
        $global:WordPressTagTestRemote[$id].tags = @($payload.tags | ForEach-Object { [int]$_ })
        return $global:WordPressTagTestRemote[$id] | ConvertTo-Json -Depth 10 | ConvertFrom-Json
    }

    throw "Unexpected API request: $Uri"
}

try {
    $common = @{
        SiteUrl='https://example.invalid'
        Username='mock'
        AppPassword='mock'
        StatePath=$statePath
    }

    Reset-Mock
    & $scriptPath @common
    Assert (@($global:WordPressTagTestRequests | Where-Object Method -eq 'Post').Count -eq 0) 'Dry-run made a write'
    Assert ($global:WordPressTagTestRemote[1].tags -notcontains 50) 'Dry-run changed published tags'
    Assert (-not ($global:WordPressTagTestRequests.Uri -match '/posts/6\?')) 'Excluded trashed slug was read'

    Reset-Mock
    & $scriptPath @common -Commit
    Assert (@($global:WordPressTagTestRequests | Where-Object { $_.Method -eq 'Post' -and $_.Uri -match '/posts/[12]$' }).Count -eq 2) 'Expected two post tag updates'
    Assert (($global:WordPressTagTestRemote[1].tags -join ',') -eq '7,50') 'Published post did not preserve and append tags'
    Assert (($global:WordPressTagTestRemote[2].tags -join ',') -eq '8,9,50') 'Scheduled post did not preserve and append tags'
    Assert (($global:WordPressTagTestRemote[3].tags -join ',') -eq '50,10') 'Already-tagged post changed'
    Assert ($global:WordPressTagTestRemote[4].tags -notcontains 50) 'Trash post was updated'
    Assert ($global:WordPressTagTestRemote[5].tags -notcontains 50) 'Slug mismatch was updated'
    Write-Host 'PASS: tracked tag dry-run, published/scheduled updates, existing tags preserved, already-tagged no-op, excluded/trashed/mismatched posts skipped.'
}
finally {
    Remove-Item -LiteralPath $temp -Recurse -Force
    Remove-Variable WordPressTagTestRequests,WordPressTagTestRemote,WordPressTagTestTag -Scope Global -ErrorAction SilentlyContinue
}
