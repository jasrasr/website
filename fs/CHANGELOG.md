# Changelog

All notable changes to this project are documented here. Format is loosely based on [Keep a Changelog](https://keepachangelog.com/). Entries through 2026-09-09 are backfilled from `git log`; the dashboard footer's Rev/Updated values track `lib/version.php`, bumped alongside new entries here.

## Unreleased

## 2026-09-10
- Added `index-simple.php`: a lightweight, read-only dashboard showing one chart of daily unresolved, new, and closed/resolved ticket counts. Does not modify or depend on `index.php`.
- Added `lib/version.php`: shared `APP_REVISION`/`APP_UPDATED` constants, surfaced in the footer of both dashboards.
- Added this changelog.
- Added per-point value labels to the simple dashboard's chart, colored to match each series, so exact numbers are readable next to each date. (Rev 1.1)
- Linked the simple dashboard from the main dashboard's header. (Rev 1.2)

## 2026-09-09
- Improved chart x-axis date label contrast for readability, with test coverage.
- Defaulted activity cards to the latest completed workday instead of the in-progress current day, with test coverage.

## 2026-09-08
- Added 7, 14, and 30-day trend analysis cards with a least-squares tickets-per-day slope, documented the calculation, and added tests.
- Distinguished missing trend activity from a measured zero instead of conflating the two, with tests.

## 2026-09-06
- Added hierarchical ticket classification analytics (category › subcategory › item), with documentation and tests.
- Collapsed the lengthy chart explanation into a closed `<details>` section by default.
- Added dual chart axes and unresolved bars, then pinned both axes so they stay visible while only the plot scrolls horizontally, with documentation and tests.
- Moved the headline unresolved/change count cards above the chart and required both axes to remain visible, with tests.
- Visually emphasized the unresolved series over the new/completed activity series, with tests.

## 2026-09-04
- Combined the daily unresolved line, new/completed activity lines, and bars into one overlay chart with per-point data labels, with tests.

## 2026-09-03
- Displayed all tracker timestamps in Eastern time with a single page-wide note instead of a per-row timezone suffix, with tests.
- Omitted unrecorded dates from the daily chart axis instead of showing gaps, with tests.
- Iterated the daily chart from combined unresolved totals + activity bars, to new/completed rendered as lines, to a dual chart view with labels for every known series value, with tests at each step.

## 2026-09-01
- Switched the trend chart to plot the latest daily ticket count per calendar day (evenly spaced by day) instead of every raw snapshot, with tests.

## 2026-08-31
- Made the trend chart time-aware, spacing points by real elapsed time (#61).
- Marked missing trend data explicitly instead of implying a zero reading.
- Switched to ordinal day spacing for the trend chart and labeled the latest trend timestamp.
- Showed exact requester ticket-load counts instead of rounded buckets (#62).

## 2026-08-29
- Added an adaptive collection schedule (hourly on weekday days, less frequent nights/weekends) with status labels, exposed in the protected diagnostic endpoint.
- Added automatic hourly collection triggered by page load, gated server-wide to avoid duplicate pulls.
- Simplified the ticket summary cards.
- Fixed analytics label wrapping on mobile.

## 2026-08-28
- Recorded Aug 27-28 unresolved ticket counts manually.
- Added the Freshservice API diagnostic endpoint (`api/test.php`) and a ticket assignment diagnostic.
- Logged every collector pull attempt (success or failure) on the dashboard.
- Added automatic cleanup of zero-count API snapshots created by invalid configuration.
- Added a dismissible celebration banner with accessible fireworks when the unresolved queue reaches zero.
- Added anonymous queue analytics (status, category, priority, age).

## 2026-08-26
- Recorded 120 unresolved tickets manually.

## 2026-08-25
- Renamed the Freshservice tracker folder to lowercase `fs`.
- Added the manual "Pull tickets now" button.
- Improved Freshservice API diagnostics and recorded 129 unresolved tickets.
