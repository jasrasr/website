# Exercises the publisher's real dry-run scheduler without contacting WordPress.
$ErrorActionPreference = 'Stop'
function Assert($Condition, [string]$Message) { if (-not $Condition) { throw $Message } }
$temp = Join-Path ([IO.Path]::GetTempPath()) ('wp-weekday-schedule-' + [guid]::NewGuid())
$postsPath = Join-Path $temp 'posts'
$mediaPath = Join-Path $temp 'media'
$statePath = Join-Path $temp 'state.json'
try {
    New-Item -ItemType Directory -Path $postsPath,$mediaPath -Force | Out-Null
    New-Item -ItemType File -Path (Join-Path $mediaPath 'cover.jpg') | Out-Null
    for ($i = 1; $i -le 7; $i++) {
        @{
            slug = "schedule-test-$i"
            title = "Schedule test $i"
            date = '2026-11-01'
            excerpt = 'Scheduler fixture.'
            content_html = '<p>Scheduler fixture.</p>'
            cover = @{src='media/cover.jpg';alt='Scheduler fixture cover'}
        } | ConvertTo-Json -Depth 10 | Set-Content (Join-Path $postsPath "schedule-test-$i.json")
    }

    $transcriptPath = Join-Path $temp 'preview.txt'
    Start-Transcript -LiteralPath $transcriptPath | Out-Null
    try {
        & (Join-Path $PSScriptRoot 'Publish-WordPress.ps1') -SiteUrl 'https://example.invalid' -Username '' -AppPassword '' -PostsPath $postsPath -StatePath $statePath -PreservePostOrder
    }
    finally {
        Stop-Transcript | Out-Null
    }
    $preview = Get-Content -LiteralPath $transcriptPath -Raw
    $rows = @([regex]::Matches($preview, '(?m)^\s*(\d{4}-\d{2}-\d{2})\s+12:00\s+PM\s+'))
    Assert ($rows.Count -eq 7) "Expected seven scheduled post rows; found $($rows.Count)."
    $eastern = foreach ($id in @('America/New_York','Eastern Standard Time')) {
        try { [TimeZoneInfo]::FindSystemTimeZoneById($id); break } catch { }
    }
    $expected = [TimeZoneInfo]::ConvertTimeFromUtc([datetime]::UtcNow, $eastern).Date.AddDays(1)
    foreach ($row in $rows) {
        while ($expected.DayOfWeek -in @([DayOfWeek]::Saturday,[DayOfWeek]::Sunday)) {
            $expected = $expected.AddDays(1)
        }
        Assert ($row.Groups[1].Value -eq $expected.ToString('yyyy-MM-dd')) "Expected one post on $($expected.ToString('yyyy-MM-dd')); got $($row.Groups[1].Value)."
        $expected = $expected.AddDays(1)
    }
    Assert (-not (Test-Path -LiteralPath $statePath)) 'Dry run wrote a state file.'
    Write-Host 'PASS: one post at noon Eastern on each consecutive weekday, weekends skipped, dry run unchanged.'
}
finally {
    Remove-Item -LiteralPath $temp -Recurse -Force
}
