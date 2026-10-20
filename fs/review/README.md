# Freshservice ticket review

Private companion to the aggregate `fs` tracker. The repo has `ticketing/` (a separate local help desk), not `tickets/`. This workspace uses Freshservice as the source of truth and does not import work tickets into that standalone app.

## Deployment

Requires PHP 8.1+ and cURL. Deploy source changes without replacing the existing `fs/config.local.php` or runtime storage. Open `/fs/review/` relative to your installation (for example `/github/fs/review/`).

Add these keys to your existing `fs/config.local.php` array:

```php
'review_token' => 'YOUR_SEPARATE_RANDOM_TOKEN_AT_LEAST_32_CHARACTERS',
'review_ai_enabled' => false,
'review_openai_key' => '',
'review_model' => '',
```

Generate a token with `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`. The review token grants access to the configured agent's ticket text and private notes, unlike the collector token. Keep it private. Set an OpenAI API key and a Structured Outputs-compatible model available to that API project, then explicitly set `review_ai_enabled` to `true` when the API account is approved for this content. Keep credentials on the server; never put them in Git or browser JavaScript. Use HTTPS. The page asks for only the review token and keeps it in tab memory, with no localStorage or sessionStorage. Refreshing the page or pressing Lock clears the review session.

The integration reuses `domain`, `api_key`, `agent_id`, `workspace_id`, and `resolved_status_ids`. Configure all terminal status IDs for your tenant; defaults are 4 and 5. Custom nonterminal statuses remain included. Domain must be the exact `tenant.freshservice.com` hostname. The API key needs permission to read tickets and private conversations in the intended workspace(s). The tool can only review records that key is permitted to see.

On Apache/Hostinger, ship the `.htaccess` files. Other servers need equivalent config access denial, directory listing denial and TLS. Do not connect this page to `ticketing`'s password-free test-mode login. A separate long random token protects every data request; there are no public ticket-data endpoints or CORS access grants.

## Workflow

1. Load your unresolved tickets. Server-side assignment, workspace, deleted/spam and terminal-status checks define scope.
2. Use **Open all ticket tabs** to open every ticket in the loaded unresolved queue, regardless of the current search or recommendation filter. The page opens tabs in the browser currently displaying it; to use Chrome, open the review page in Chrome first. Browsers may require pop-ups and redirects to be allowed for the site.
3. Sort by attention score, priority, age or idle days. Attention score uses priority (10/25/60/100), overdue SLA (+50), age (up to +30), and days since ticket update (up to +20). Idle is time since **any ticket update**, not necessarily the last human reply. Age is never closure evidence.
4. Open a ticket to fetch its full description and all API-visible conversations, including private notes. The dedicated conversations endpoint is paginated; embedded ticket conversations are not used because that embed only returns up to ten. Source text is rendered as escaped text, never ticket-supplied HTML.
5. Choose Analyze with AI for one ticket, or Review unreviewed tickets for sequential analysis of the complete queue. Each analysis re-fetches current history. Stop takes effect after the current ticket. A failure stops the batch and keeps previously completed reviews in tab memory. Resuming skips completed reviews. Refresh queue discards old recommendations.
6. Filter by resolution candidate, follow up, investigate, blocked or escalate. Each review includes confidence, evidence quotes, blockers, closure checks, next step, and editable internal/requester drafts. Evidence source IDs and exact quotes are checked against retrieved text; this validates quotations, not the correctness of the model's judgment.
7. Open Freshservice to verify the latest state, edit/paste a draft, and resolve or close. This version never sends messages, creates notes or changes ticket status. Resolution candidates are suggestions, not an assertion that closure is safe.

## Data and limitations

Ticket text is not written to application files, public aggregate snapshots, Git, or browser persistent storage. When AI is requested, the fetched description and conversations **including private notes** are transmitted to OpenAI's Responses API with `store: false`. That setting is not a promise of zero provider retention; applicable API account data policies still govern. Drafts are untrusted AI text: inspect public replies for accidental disclosure of internal information and verify proposed troubleshooting.

Attachment content, task completion, service-request approvals, linked problems/changes and related records are **not fetched**. Their absence is always displayed and the model must list checks before closure. This is not a complete Freshservice audit log or all related work. There is no tenant-specific no-response closure policy configured; silence alone must not justify resolution.

Pagination, malformed responses, changing ticket snapshots, API failures, missing permissions, and rate limits fail visibly instead of claiming a complete review. The current filter scans the configured agent's history and rejects totals at/above the 10,000-result ceiling; large histories require a future date-partitioned collector. At most 100 conversation pages are allowed; oversized histories fail rather than silently truncate. AI input is limited to 180 KB of serialized content and output to 4,000 tokens; incomplete output is rejected. Shared-host request timeouts may interrupt unusually large queues; no partial queue is presented as complete. Sequential AI review can incur one paid API request per ticket.

## Verification

Run `php fs/tests/review-test.php` and `node --check fs/review/review.js`. Regression checks use synthetic data and cover pagination, assignment/workspace scope, private notes, incomplete histories, snapshot changes, fabricated evidence, missing fields and blocked resolution candidates. These tests do not establish live AI accuracy or tenant permissions.

Before production use, compare fetched ticket and conversation counts against Freshservice; test a ticket with more than 30 notes; test 401/403/429 behavior; and evaluate recommendations on known resolved, waiting-on-user, waiting-on-vendor and contradictory-history examples.

API references: [Freshservice API](https://api.freshservice.com/) and [OpenAI Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs).

## Troubleshooting unreadable responses

The client reports the operation, ticket ID, HTTP status and elapsed seconds for non-JSON responses without displaying the raw server body. Open the failing ticket first: if history loads but Analyze fails, the problem occurs in the analysis request. JSON failures include a stage and reference ID for correlation with the hosting PHP error log. Logs from this handler contain only the reference, stage and exception class/fatal type, not exception text, secrets or ticket content.

The endpoint requests a 120-second PHP execution allowance where supported; host/proxy limits can still override it. AI HTTPS calls stop after 40 seconds with an explicit error. This is timeout/error hardening, not a background job system or proof of the cause of an existing hosting failure.
