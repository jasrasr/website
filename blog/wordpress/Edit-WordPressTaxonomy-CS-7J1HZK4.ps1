<#
.SYNOPSIS
    Lists, renames, merges, or deletes WordPress categories and tags.

.DESCRIPTION
    Revision: 1.0.0
    Rename, merge, and delete operations are dry runs unless -Commit is supplied.
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [ValidatePattern('^https://')]
    [string]$SiteUrl,

    [Parameter(Mandatory)]
    [ValidateSet('Category', 'Tag')]
    [string]$Taxonomy,

    [ValidateSet('List', 'Rename', 'Merge', 'Delete')]
    [string]$Operation = 'List',

    [string]$SourceName,
    [string]$TargetName,
    [int]$SourceId,
    [int]$TargetId,
    [string]$NewSlug,
    [string]$Username = $env:WORDPRESS_USERNAME,
    [string]$AppPassword = $env:WORDPRESS_APP_PASSWORD,
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
        [Parameter(Mandatory)][ValidateSet('Get', 'Post', 'Delete')][string]$Method,
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
        $params.Body = $Body | ConvertTo-Json -Depth 10 -Compress
    }

    Invoke-RestMethod @params
}

function Get-AllTerms {
    $result = @()
    $page = 1
    do {
        $items = @(Invoke-WpJson -Method Get -Path "$script:Endpoint`?hide_empty=false&per_page=100&page=$page&_fields=id,name,slug,count" | ForEach-Object { $_ })
        $result += $items
        $page++
    } while ($items.Count -eq 100)

    @($result)
}

function Find-ExactTerm {
    param([string]$Name, [int]$Id, [string]$ParameterName = 'SourceName')

    if ($Id -gt 0) {
        $byId = @($script:Terms | Where-Object { [int]$_.id -eq $Id })
        if ($byId.Count -eq 0) { throw "$Taxonomy ID $Id was not found." }
        return $byId[0]
    }

    Assert-Parameter -Name $ParameterName -Value $Name

    $matches = @($script:Terms | Where-Object { $_.name -ieq $Name })
    if ($matches.Count -eq 0) {
        throw "$Taxonomy '$Name' was not found."
    }
    if ($matches.Count -gt 1) {
        $details = ($matches | ForEach-Object { "ID $($_.id), slug '$($_.slug)'" }) -join '; '
        throw "More than one $($Taxonomy.ToLowerInvariant()) is named '$Name': $details. Rename the duplicate in WordPress first."
    }
    $matches[0]
}

function Get-AffectedPosts {
    param([Parameter(Mandatory)][int]$TermId)

    $result = @()
    $page = 1
    do {
        $items = @(Invoke-WpJson -Method Get -Path "posts?status=any&$script:PostFilter=$TermId&per_page=100&page=$page&_fields=id,slug,title,$script:PostField" | ForEach-Object { $_ })
        $result += $items
        $page++
    } while ($items.Count -eq 100)

    @($result)
}

function Get-PlainTitle {
    param([object]$Title)

    if ($null -eq $Title) { return '' }
    $text = if ($Title.PSObject.Properties['rendered']) { [string]$Title.rendered } else { [string]$Title }
    [Net.WebUtility]::HtmlDecode(($text -replace '<[^>]+>', '')).Trim()
}

function Assert-Parameter {
    param([string]$Name, [string]$Value)

    if ([string]::IsNullOrWhiteSpace($Value)) {
        throw "-$Name is required for the $Operation operation."
    }
}

if ([string]::IsNullOrWhiteSpace($Username) -or [string]::IsNullOrWhiteSpace($AppPassword)) {
    throw 'A WordPress username and application password are required.'
}

$script:WpBase = $SiteUrl.TrimEnd('/')
$script:AuthHeaders = New-AuthorizationHeader -User $Username -Password $AppPassword
$script:Endpoint = if ($Taxonomy -eq 'Category') { 'categories' } else { 'tags' }
$script:PostField = $script:Endpoint
$script:PostFilter = $script:Endpoint

try {
    $me = Invoke-WpJson -Method Get -Path 'users/me?context=edit&_fields=id,name,slug'
    Write-Host "WordPress API user: $($me.name) (ID $($me.id))"
}
catch {
    throw "WordPress authentication failed: $($_.Exception.Message)"
}

$script:Terms = @(Get-AllTerms)

if ($Operation -eq 'List') {
    Write-Host ''
    Write-Host ("{0} {1} found:" -f $script:Terms.Count, $script:Endpoint)
    $script:Terms |
        Sort-Object name, id |
        Select-Object id, name, slug, count |
        Format-Table -AutoSize |
        Out-Host
    return
}

$source = Find-ExactTerm -Name $SourceName -Id $SourceId

if ($Operation -eq 'Rename') {
    Assert-Parameter -Name TargetName -Value $TargetName
    $duplicate = @($script:Terms | Where-Object { $_.id -ne $source.id -and $_.name -ieq $TargetName })
    if ($duplicate.Count -gt 0) {
        throw "$Taxonomy '$TargetName' already exists. Use -Operation Merge to combine terms."
    }

    $body = @{ name = $TargetName }
    if (-not [string]::IsNullOrWhiteSpace($NewSlug)) { $body.slug = $NewSlug }
    Write-Host ''
    Write-Host ("{0}: '{1}' (ID {2}) -> '{3}'{4}" -f $(if ($Commit) { 'Renaming' } else { 'Would rename' }), $source.name, $source.id, $TargetName, $(if ($NewSlug) { " with slug '$NewSlug'" } else { '' }))

    if ($Commit) {
        Invoke-WpJson -Method Post -Path "$script:Endpoint/$($source.id)" -Body $body | Out-Null
        Write-Host 'Rename complete.'
    }
}
elseif ($Operation -eq 'Merge') {
    if ($TargetId -le 0) { Assert-Parameter -Name TargetName -Value $TargetName }
    $target = Find-ExactTerm -Name $TargetName -Id $TargetId -ParameterName TargetName
    if ([int]$source.id -eq [int]$target.id) { throw 'SourceName and TargetName resolve to the same term.' }

    $posts = @(Get-AffectedPosts -TermId ([int]$source.id))
    Write-Host ''
    Write-Host ("{0} '{1}' (ID {2}) into '{3}' (ID {4})." -f $(if ($Commit) { 'Merging' } else { 'Would merge' }), $source.name, $source.id, $target.name, $target.id)
    Write-Host ("Affected posts: {0}" -f $posts.Count)
    if ($posts.Count -gt 0) {
        $posts | ForEach-Object {
            [pscustomobject]@{ Id = $_.id; Slug = $_.slug; Title = Get-PlainTitle -Title $_.title }
        } | Format-Table -AutoSize -Wrap | Out-Host
    }

    if ($Commit) {
        foreach ($post in $posts) {
            $termIds = @($post.($script:PostField) | ForEach-Object { [int]$_ } | Where-Object { $_ -ne [int]$source.id })
            if ($termIds -notcontains [int]$target.id) { $termIds += [int]$target.id }
            Invoke-WpJson -Method Post -Path "posts/$($post.id)" -Body @{ $script:PostField = @($termIds) } | Out-Null
            Write-Host "Updated post: $(Get-PlainTitle -Title $post.title)"
        }
        Invoke-WpJson -Method Delete -Path "$script:Endpoint/$($source.id)?force=true" | Out-Null
        Write-Host 'Merge complete; the source term was deleted.'
    }
}
elseif ($Operation -eq 'Delete') {
    $posts = @(Get-AffectedPosts -TermId ([int]$source.id))
    Write-Host ''
    Write-Host ("{0} '{1}' (ID {2})." -f $(if ($Commit) { 'Deleting' } else { 'Would delete' }), $source.name, $source.id)
    Write-Host ("Affected posts: {0}" -f $posts.Count)
    if ($posts.Count -gt 0) {
        $posts | ForEach-Object {
            [pscustomobject]@{ Id = $_.id; Slug = $_.slug; Title = Get-PlainTitle -Title $_.title }
        } | Format-Table -AutoSize -Wrap | Out-Host
    }

    if ($Commit) {
        Invoke-WpJson -Method Delete -Path "$script:Endpoint/$($source.id)?force=true" | Out-Null
        Write-Host 'Delete complete.'
    }
}

if (-not $Commit) {
    Write-Host ''
    Write-Host 'DRY RUN ONLY. Re-run with -Commit after reviewing the proposed changes.'
}
