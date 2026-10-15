$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'Get-WordPressScheduleStartDate.ps1')

$now = [datetime]::new(2026, 10, 15, 16, 0, 0)
$empty = Get-WordPressScheduleStartDate -Now $now -TrackedPosts @()
if ($empty -ne [datetime]::new(2026, 10, 16)) { throw "Expected next weekday start; got $empty" }

$tracked = @(
    [pscustomobject]@{status='future';scheduled_date_eastern='10/16/2026 14:11:00'},
    [pscustomobject]@{status='future';scheduled_date_eastern='2026-10-20T13:19:00'},
    [pscustomobject]@{status='publish';scheduled_date_eastern='2026-10-22T13:00:00'},
    [pscustomobject]@{status='future';scheduled_date_eastern='2026-10-14T09:00:00'}
)
$afterQueue = Get-WordPressScheduleStartDate -Now $now -TrackedPosts $tracked
if ($afterQueue -ne [datetime]::new(2026, 10, 21)) { throw "Expected date after the future queue; got $afterQueue" }

$weekendQueue = @([pscustomobject]@{status='future';scheduled_date_eastern='2026-10-16T09:00:00'})
$afterFriday = Get-WordPressScheduleStartDate -Now $now -TrackedPosts $weekendQueue
if ($afterFriday -ne [datetime]::new(2026, 10, 17)) { throw "Expected Saturday as raw start (weekday allocator skips to Monday); got $afterFriday" }

Write-Host 'PASS: schedule starts tomorrow when queue is empty, follows latest future post, ignores past/published posts, and lets weekday allocation skip weekends.'
