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
API snapshots include anonymous aggregate charts for status, category, priority, ticket age, and requester ticket-count distribution. Raw requester IDs remain only in protected state; categories with fewer than three tickets are grouped into `Other`.

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

`config.local.php`, `storage/api-state.json`, and `storage/api-snapshots.json` are ignored by Git and denied to web requests. The private state contains only ticket IDs, statuses, assignee IDs, and timestamps—no subjects, descriptions, requester names, or conversations. API snapshots contain aggregate counts only. Keeping runtime snapshots out of tracked files prevents deployment conflicts.

## How calculation works

```text
ending unresolved = starting unresolved + entered unresolved - exited unresolved
```

Because Freshservice only provides the current ticket state in list results, the collector compares each run with its private previous-state file. More frequent runs produce better transition coverage.
