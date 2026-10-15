function Get-WordPressScheduleStartDate {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)][datetime]$Now,
        [object[]]$TrackedPosts = @()
    )

    $startDate = $Now.Date.AddDays(1)
    $latestFutureDate = $null
    foreach ($post in $TrackedPosts) {
        if ([string]$post.status -ne 'future') { continue }
        $scheduled = [datetime]::MinValue
        if (-not [datetime]::TryParse(
            [string]$post.scheduled_date_eastern,
            [Globalization.CultureInfo]::InvariantCulture,
            [Globalization.DateTimeStyles]::AllowWhiteSpaces,
            [ref]$scheduled
        )) { continue }
        if ($scheduled.Date -lt $startDate) { continue }
        if ($null -eq $latestFutureDate -or $scheduled.Date -gt $latestFutureDate) {
            $latestFutureDate = $scheduled.Date
        }
    }

    if ($null -ne $latestFutureDate) { return $latestFutureDate.AddDays(1) }
    return $startDate
}
