# Freshservice Ticket Tracker

Small PHP/JSON dashboard for tracking Jason's aggregate unresolved Freshservice ticket count toward a goal of zero.

## Tracking methods

Screenshots can still be recorded manually. The preferred method is the server-side API collector in `api/collect.php`. It compares consecutive ticket states and records:

- new tickets
- tickets assigned to the configured agent
- reopened tickets
- resolved and closed tickets
- tickets reassigned away
- entries to and exits from the unresolved queue
- starting count, ending count, and net change

The dashboard merges the Git-tracked manual history with API snapshots stored on the server and displays both the queue balance and daily activity.
Each authenticated collector attempt is also recorded in `storage/pull-log.json` and displayed in the dashboard's API pull log. Successful and failed pulls are retained without credentials or ticket content.
When the latest unresolved count reaches zero, the dashboard displays a dismissible applause banner with an accessible fireworks celebration.
API snapshots include anonymous aggregate charts for status, category, category › subcategory, category › subcategory › item, priority, ticket age, and requester ticket-count distribution. Hierarchical labels keep duplicate subcategory or item names under the correct parent. Raw requester IDs remain only in protected state; category combinations with fewer than three tickets are grouped into `Other`.
The combined daily chart appears above the summary cards, spaces recorded days evenly, and omits dates without snapshots. Unresolved uses the fixed left vertical scale; New and Resolved/Closed share the fixed right activity scale. Only the plot scrolls horizontally, so both axes remain visible. All three series render as labeled lines with translucent bars, while skipped dates use dashed connectors.
The dashboard also calculates 7, 14, and 30-calendar-day trend cards from the daily series. Each card shows unresolved change and percentage, a least-squares tickets-per-day slope using real calendar spacing, observed New and Resolved/Closed totals, coverage, and a guarded estimate to zero for sufficiently sampled declining trends.

## Setup

1. Copy `config.local.example.php` to `config.local.php` on the server.
2. Set `domain` to the Freshservice tenant hostname, such as `company.freshservice.com`. A pasted full portal URL is normalized to its hostname.
3. Paste the API key into `api_key`.
4. Set `agent_id` to the Freshservice agent ID whose **My Unresolved** queue is being tracked.
5. Use a positive `workspace_id` for one workspace, or leave it as `0` to request all accessible workspaces.
6. Replace `collector_token` with a long random secret.
7. Use **Pull tickets now** on the dashboard or run the collector from cron. The first run creates the API baseline; the second and later runs calculate activity.

Recommended cron request:

```bash
curl -fsS -H "Authorization: Bearer YOUR_COLLECTOR_TOKEN" https://jasr.me/github/fs/api/collect.php
```

For adaptive background collection, configure one Hostinger cron job to run hourly (`0 * * * *`) with:

```bash
curl -fsS -H "Authorization: Bearer YOUR_COLLECTOR_TOKEN" https://jasr.me/github/fs/api/scheduled.php
```

The scheduler pulls hourly on weekdays from 6:00 AM through 5:59 PM, every four hours on weekday nights, and every twelve hours on Saturdays and Sundays. All decisions use the configured timezone.

### By-organization report (optional, separate from the main tracker)

`index-org.php` charts new tickets per day by requester email domain. It is not linked from the main dashboard and is only reachable by its direct URL. It reads its own collector, `api/collect-org.php`, which pulls tickets for a configurable whitelist of agents (`org_agent_ids` in `config.local.php`) instead of the single `agent_id` the main dashboard tracks—so it can cover a small team without changing what the main dashboard's unresolved/goal numbers measure. Set `org_agent_ids` to the list of Freshservice agent IDs to include, then run it the same way as the main collector:

```bash
curl -fsS -H "Authorization: Bearer YOUR_COLLECTOR_TOKEN" https://jasr.me/github/fs/api/collect-org.php
```

It keeps its own state and snapshot files (`storage/org-api-state.json`, `storage/org-api-snapshots.json`) and pull log (`storage/org-pull-log.json`), independent of the main tracker's files.

To verify API access without changing tracker state, call the protected diagnostic endpoint with the same token:

```bash
curl -fsS -H "Authorization: Bearer YOUR_COLLECTOR_TOKEN" https://jasr.me/github/fs/api/test.php
```

The diagnostic checks the configured agent, general ticket access, and the exact agent filter. It returns only IDs, statuses, and counts—never the API key or ticket text.
Append `?ticket_id=12345` to safely inspect the status and assignment IDs of a known ticket.

The protected `api/cleanup.php` maintenance endpoint accepts authenticated POST requests, backs up API snapshots, and removes zero-count API snapshots created by an invalid configuration.

Running hourly captures tickets that enter and leave the queue during the same day more reliably than one end-of-day snapshot.

Every page load calls the public `api/auto.php` gate. It performs a server-wide pull only when the last successful pull was at least one hour ago, regardless of which computer opens the site. A filesystem lock prevents simultaneous visitors from starting duplicate pulls. The automatic request does not use or expose the collector token. **Pull tickets now** remains token-protected for forced refreshes.

During the folder rename, the collector can temporarily read an existing `FS/config.local.php`. Move that file to `fs/config.local.php` when convenient so all runtime files live under the lowercase folder.

## Privacy and security

`config.local.php` and everything under `storage/` (including the by-organization report's `org-api-state.json` and `org-api-snapshots.json`) are ignored by Git and denied to web requests. The private state contains only ticket IDs, statuses, assignee IDs, requester email domains (never the full address or name), and timestamps—no subjects, descriptions, requester names, or conversations. API snapshots contain aggregate counts only, including new-ticket counts grouped by requester email domain (capped to the top 8 domains plus Other). Keeping runtime snapshots out of tracked files prevents deployment conflicts.

## How calculation works

```text
ending unresolved = starting unresolved + entered unresolved - exited unresolved
```

Because Freshservice only provides the current ticket state in list results, the collector compares each run with its private previous-state file. More frequent runs produce better transition coverage.
