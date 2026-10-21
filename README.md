<!--
File Name     : README.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Repository overview, development conventions, project index, and deployment guidance.
Revision      : 1.0.4
Created Date  : 2025-02-14
Modified Date : 2026-10-21
Changelog     :
  Rev 1.0.4 - Indexed Workout Tracker with key-free quick entry.
  Rev 1.0.3 - Linked the compiled project TODO list.
  Rev 1.0.2 - Added Build Signal source, revision notes, and illustration manifest for Codex CLI visual iteration.
  Rev 1.0.1 - Listed Khootish and Quiz Web Interactive as separate projects; marked the unavailable Quiz Web Interactive source path without substituting another app.
  Rev 1.0.0 - Added framework-first development requirements and complete folder inventory; corrected the stale quiz path. Earlier unversioned history remains in Git.
-->
# Website Stuff

This repo is where I keep HTML, CSS, JavaScript, PHP scripts, and small hosted web projects. I started my own website back in 2004 and have created a ton of code since then. It is all somewhere on my website, which is exactly how personal web projects reproduce in the wild.

I currently use Hostinger for hosting. I have used GoDaddy, billable through LuckyRegister, and Powweb before that. I currently maintain several domains, but I mainly publish on [jasonlamb.me](https://jasonlamb.me). I use [jasr.me](https://jasr.me) for tools, tests, redirects, and hosted side projects.

Important hosting note: this repo is used as source storage and syncs to my web host. PHP projects in this repo are intended to run from the hosted website, not from GitHub Pages.

## Development conventions — read before changing files

Before creating or changing a project, read [the framework overview](1-Framework/README.md), [AI development instructions](1-Framework/AI-INSTRUCTIONS.md), [framework roadmap](1-Framework/ROADMAP.md), and the relevant project/module documentation. Inspect existing components before building another implementation.

These repository-wide requirements apply to new work and updates:

- Put a native file-type comment header at the top of every new or modified source/document file. Include **File Name, Author, Description, Revision, Created Date, Modified Date, and Changelog**. Use **Jason Lamb (with help from ChatGPT)** as the author and `YYYY-MM-DD` dates.
- Give each file an independent revision starting at `1.0.0`. Increment only changed files, preserve the original created date, update the modified date, and retain prior file changelog entries.
- Keep a cumulative project changelog, a project revision, and a visible revision/updated date on the application's main page. Record functional changes when they are made, rather than adding this documentation afterward.
- Keep strict data formats valid: JSON uses a leading `_header` metadata object instead of comments; binary assets use supported native metadata or an accompanying documented manifest.
- Include configuration examples without secrets, input/failure handling, and appropriate tests or a concrete manual validation procedure for functional changes.
- Reuse applicable framework components. New account-based PHP projects use the shared user-management provider through `SharedIdentity::connect()`; introducing another account store requires an explicit design decision.
- Preserve existing saves, permissions, ignored runtime data, and deployment behavior. Document compatibility or migration needs. For Jason-requested PR changes, prepare the result for review and obtain explicit confirmation before merging.
- Update this project index when folders are added, renamed, or removed. Verify paths against the repository; identify placeholders and test/support folders, and do not imply an unverified deployment is live.

Apply framework components according to the project's runtime. Browser-only/offline projects such as Oregon Trail follow the documentation, revision, validation, and compatibility requirements without adding a PHP dependency solely to match the server-side framework. Its browser-local saves remain separate from server-side JSON storage.

## Primary sites

- Personal blog/site: [jasonlamb.me](https://jasonlamb.me)
- Tools, tests, redirects, and projects: [jasr.me](https://jasr.me)

## URL convention

Folders in this repo sync under `https://jasr.me/github/<folder>/`, which is the canonical URL for each project. Short vanity paths like `jasr.me/mpg` or `jasr.me/time-clock` are **redirects** to the matching `jasr.me/github/<folder>/` location, so the tables below list the canonical `/github/` URLs. Canonical `/github/` links open in a new tab.

## Project index

The entries below describe source present in the repository. Canonical URLs are live or target locations under the convention above; this inventory does not verify every deployed endpoint.

The [repository-wide TODO list](ALL-TODOS.md) collects the TODO files kept in each project folder.

| Project | Repo path / docs | Live or target URL | Notes |
|---|---|---|---|\n| Build Signal walkthrough | [build-signal/](build-signal/) / [README](build-signal/README.md) | [Private ChatGPT Site](https://jason-ai-build-flow.jasrasr.chatgpt.site) | Illustrated, narrated AI development workflow. Source is here for local Codex CLI visual refinement; the linked Site remains owner-only. |
| Workout Tracker | [workout-tracker/](workout-tracker/) / [README](workout-tracker/README.md) | <a href="https://jasr.me/github/workout-tracker/" target="_new">jasr.me/github/workout-tracker</a> | Private, no-key natural-language fitness logging, multiple exercises per session, mobile dashboard and separate trend charts. Requires central project access. |
| Game Buzzer | [game-buzzer/](game-buzzer/) / [README](game-buzzer/README.md) | <a href="https://jasr.me/github/game-buzzer/" target="_new">jasr.me/github/game-buzzer</a> | Six-team phone buzzer and arrival-survey Majority Rules game, with host controls and projector scoreboard. Requires private host setup. |
| Webstats | [webstats/](webstats/) / [README](webstats/README.md) | <a href="https://jasr.me/github/webstats/" target="_new">jasr.me/github/webstats</a> | Custom multi-site page views and clicks with a private dashboard. Requires setup and tracker installation. |
| Availability Poll | [availability/](availability/) / [README](availability/README.md) | <a href="https://jasr.me/github/availability/" target="_new">jasr.me/github/availability</a> | Named availability polls with admin-selected dates, private admin links, and live group results. |
| YOURLS link shortener and tracker | External app | [jasr.me](https://jasr.me) | Fork of [YOURLS](https://github.com/YOURLS/YOURLS). |
| Secure text sender | External app | [jasr.me/secure](https://jasr.me/secure) | Fork of [PrivateBin](https://github.com/PrivateBin/PrivateBin). |
| Random Password Generator | [random-password-generator/](random-password-generator/) | [jasr.me/pw](https://jasr.me/pw) | Client-side password generator experiments and working copies. Deployed at `/pw`; the `/github/` path is access-restricted. |
| Text Copy / scratch pad | [text/](text/) | <a href="https://jasr.me/github/text/" target="_new">jasr.me/github/text</a> | Server-backed scratch pad for editing text and retrieving it from another device. |
| MPG Fuel Log Tracker | [mpg/](mpg/) / [README](mpg/README.md) | <a href="https://jasr.me/github/mpg/" target="_new">jasr.me/github/mpg</a> | PHP fuel log with MPG calculations, CSV export, admin dashboard, and trend chart. |
| Time Clock Kiosk Display | [time-clock/](time-clock/) / [README](time-clock/README.md) | <a href="https://jasr.me/github/time-clock/" target="_new">jasr.me/github/time-clock</a> | Full-screen clock, weather, and scrolling alerts for a Raspberry Pi CM4 kiosk. |
| Trip ETA Tracker | [gps-eta/](gps-eta/) / [README](gps-eta/README.md) | <a href="https://jasr.me/github/gps-eta/" target="_new">jasr.me/github/gps-eta</a> | Mobile GPS speed, ETA, compass heading, trip sessions, and server-side history. |
| Family GPS Tracker | [family-tracker/](family-tracker/) / [README](family-tracker/README.md) | <a href="https://jasr.me/github/family-tracker/" target="_new">jasr.me/github/family-tracker</a> | Consent-based PHP + JSON family/friend location sharing with groups, check-ins, trip sharing, geofences, and installable PWA support. |
| TV Binge Board | [tv-binge-board/](tv-binge-board/) / [README](tv-binge-board/README.md) | <a href="https://jasr.me/github/tv-binge-board/" target="_new">jasr.me/github/tv-binge-board</a> | Mobile-first PHP/JSON tracker for what to watch, watching, and completed TV/movies, with import, sharing, and friend activity. Does not stream. |
| Live Game Scoreboard | [scoreboard/](scoreboard/) / [README](scoreboard/README.md) | <a href="https://jasr.me/github/scoreboard/" target="_new">jasr.me/github/scoreboard</a> | PHP scoreboard app with default, Collide, Youth, and Frontlines instances. |
| Budget Tracker | [finances/](finances/) / [README](finances/README.md) | <a href="https://jasr.me/github/finances/" target="_new">jasr.me/github/finances</a> | Private PHP budget tracker using per-user JSON files. |
| Weather Dashboard | [weather/](weather/) / [README](weather/README.md) | <a href="https://jasr.me/github/weather/" target="_new">jasr.me/github/weather</a> | Mobile-friendly PHP weather dashboard using OpenWeather data. |
| QR Box Inventory System | [box/](box/) / [README](box/README.md) | <a href="https://jasr.me/github/box/" target="_new">jasr.me/github/box</a> | Database-free QR inventory system for physical storage boxes. |
| AI Writing Tool | [ai-writing-tool/](ai-writing-tool/) / [README](ai-writing-tool/README.md) | <a href="https://jasr.me/github/ai-writing-tool/" target="_new">jasr.me/github/ai-writing-tool</a> | Two-pane browser editor with local autosave and AI suggestions through a PHP proxy. |
| Secure Upload & File Manager | [file-manager/](file-manager/) / [README](file-manager/README.md) | <a href="https://jasr.me/github/file-manager/" target="_new">jasr.me/github/file-manager</a> | Admin-only file manager with PowerShell-friendly upload API, MFA, allowlisting, and versioning. |
| PSNotify | [psnotify/](psnotify/) / [README](psnotify/README.md) | <a href="https://jasr.me/github/psnotify/" target="_new">jasr.me/github/psnotify</a> | Self-hosted notification endpoint and viewer for long-running PowerShell jobs. |
| License Plate Photo Logger | [license-plate/](license-plate/) / [README](license-plate/README.md) | <a href="https://jasr.me/github/license-plate/" target="_new">jasr.me/github/license-plate</a> | PHP app for uploading plate photos, extracting plate text, and logging duplicates. |
| Khootish Multiplayer Quiz | [khootish/](khootish/) / [README](khootish/README.md) | <a href="https://jasr.me/github/khootish/" target="_new">jasr.me/github/khootish</a> | Reusable quizzes, invite codes, team play, projector display, question bank, and completed-game history. |
| Quiz Web Interactive | Expected folder: `quiz-web-interactive/` (not present on `main` at this review) | Target: `https://jasr.me/github/quiz-web-interactive/` (deployment not verified) | Separate project from Khootish. Its source location needs confirmation; do not rename, merge, or replace it with Khootish. |
| Timeclock Photo Logger | [how-much-time-worked/](how-much-time-worked/) / [README](how-much-time-worked/README.md) | Target folder: `/timeclock/` | Photo/manual employee hour logger with OCR options and JSON stats. |
| Computer Heartbeat Dashboard | [computers/](computers/) / [README](computers/readme.md) | Target folder: `/computers/` | PowerShell-to-PHP heartbeat system for Windows device status tracking. |
| JSON Flat-File Blog | [blog/](blog/) / [README](blog/README.md) | <a href="https://jasr.me/github/blog/" target="_new">jasr.me/github/blog</a> | Static-ish JSON blog with PowerShell builder, RSS, sitemap, and admin-protected media workflow. |
| Robot or Human? Verification Gate | [human-proof/](human-proof/) / [README](human-proof/README.md) | <a href="https://jasr.me/github/human-proof/" target="_new">jasr.me/github/human-proof</a> | Press-and-hold "prove you're human" gate with a comic racing robot, plus optional PHP server-side gating so the next page can't be reached from the source. |
| Odometer / Speedometer | [odometer/](odometer/) | <a href="https://jasr.me/github/odometer/" target="_new">jasr.me/github/odometer</a> | Browser odometer/speedometer display (HTML/CSS/JS). |
| Countdown to Cooper Integration | [countdown/](countdown/) | <a href="https://jasr.me/github/countdown/" target="_new">jasr.me/github/countdown</a> | Branded countdown timer page with Cooper and Altronic logos. |
| Random URL / QR Targets | [random-url/](random-url/) | <a href="https://jasr.me/github/random-url/" target="_new">jasr.me/github/random-url</a> | Numbered static landing pages (`0.html`-`9.html`) used as QR-code targets, generated by a PowerShell script. |
| Visual Ping Webhook API | [visual-ping-webhook-api/](visual-ping-webhook-api/) / [README](visual-ping-webhook-api/README.md) | <a href="https://jasr.me/github/visual-ping-webhook-api/" target="_new">jasr.me/github/visual-ping-webhook-api</a> | Webhook API for Visual Ping. Early/placeholder. |
| Oregon Trail | [oregon-trail/](oregon-trail/) / [README](oregon-trail/README.md) | <a href="https://jasr.me/github/oregon-trail/" target="_new">jasr.me/github/oregon-trail</a> | Browser trail game with separate journey saves, autosaves, configurable stops/actions/events, and cached offline play. |
| Daily Babe Photo Tracker | [daily-babe/](daily-babe/) / [README](daily-babe/README.md) | <a href="https://jasr.me/github/daily-babe/" target="_new">jasr.me/github/daily-babe</a> | Private daily baby photos, multiple galleries, collage/GIF exports, and settings. Requires PHP and protected writable storage. |
| Debt Payoff Planner | [debt-payoff/](debt-payoff/) / [README](debt-payoff/README.md) | <a href="https://jasr.me/github/debt-payoff/" target="_new">jasr.me/github/debt-payoff</a> | Per-user loan tracking, amortization, snowball/avalanche comparisons, sharing, and backups. |
| Dell Service Tag Lookup | [dell-service-tag/](dell-service-tag/) | <a href="https://jasr.me/github/dell-service-tag/" target="_new">jasr.me/github/dell-service-tag</a> | Service-tag entry page that opens the matching Dell Support page. |
| Freshservice Ticket Tracker | [fs/](fs/) / [README](fs/README.md) | <a href="https://jasr.me/github/fs/" target="_new">jasr.me/github/fs</a> | Aggregate backlog and ticket activity charts with server-side API collection and ticket review tools. Requires private configuration. |
| Life Group Attendance | [life-group-attendance/](life-group-attendance/) / [README](life-group-attendance/README.md) | <a href="https://jasr.me/github/life-group-attendance/" target="_new">jasr.me/github/life-group-attendance</a> | Authenticated multi-group attendance, student profiles, leader approvals, and history. |
| Magic Word Terminal | [magic-word/](magic-word/) | <a href="https://jasr.me/github/magic-word/" target="_new">jasr.me/github/magic-word</a> | Jurassic Park-inspired interactive access-denied terminal with command entry and sound. |
| Random Wheel | [wheel/](wheel/) | <a href="https://jasr.me/github/wheel/" target="_new">jasr.me/github/wheel</a> | Editable random-choice wheel with a winner display and recent results. |
| Construction Sign Generator | [construction-sign/](construction-sign/) / [README](construction-sign/README.md) | <a href="https://jasr.me/github/construction-sign/" target="_new">jasr.me/github/construction-sign</a> | Client-side LED message-board preview with adjustable appearance and image/embed export. |
| Jeremy's Trip to Cleveland | [jeremy/](jeremy/) | <a href="https://jasr.me/github/jeremy/" target="_new">jasr.me/github/jeremy</a> | Trip progress and map pages for the California-to-Cleveland journey. |
| Ticketing | [ticketing/](ticketing/) / [README](ticketing/README.md) | <a href="https://jasr.me/github/ticketing/" target="_new">jasr.me/github/ticketing</a> | PHP/JSON requester and agent help-desk portals, categories, and CSV tools; preserve instance-local runtime data. |
| Warranty Tracker | [warranty-tracker/](warranty-tracker/) / [README](warranty-tracker/README.md) | <a href="https://jasr.me/github/warranty-tracker/" target="_new">jasr.me/github/warranty-tracker</a> | Private/shared household warranty records, expiration tracking, and account setup; uses the sibling framework. |
| Shared User Management | [user-management/](user-management/) / [README](user-management/README.md) | <a href="https://jasr.me/github/user-management/" target="_new">jasr.me/github/user-management</a> | Central accounts, same-host sessions, project roles, and an explicit per-application migration process. |

## Repo support tools and standalone files

| Tool / file | Repo path / docs | Purpose |
|---|---|---|
| Smart 404 | [smart-404/](smart-404/) / [README](smart-404/README.md) | YOURLS-aware 404 handling, request logging, manual mappings, and conservative fuzzy matching. |
| Custom Directory Browser | [custom-directory/](custom-directory/) / [README](custom-directory/README.md) | Central path-aware PHP file browser using thin `directory.php` wrappers. |
| Standalone authenticated directory viewer | [Custom-HTML-Directory-Viewer.php](Custom-HTML-Directory-Viewer.php) | Generic authenticated file browser with favorites, telemetry, sorting, and download tracking. |
| Impossible Click | [impossible-click.html](impossible-click.html) | Standalone browser experiment/game. |
| Countdown (alt) | [countdown1.html](countdown1.html) | Standalone "Count to Integration with Cooper" countdown page. |
| Links | [links.html](links.html) | Standalone links page. |
| Old Phone Dialer | [phone-dialer.html](phone-dialer.html) | Standalone old-phone DTMF tone number pad experiment. |
| Screen Size | [screensize.php](screensize.php) | Live display of the current browser viewport size, updating on resize/zoom. |
| Shared Framework | [1-Framework/](1-Framework/) / [README](1-Framework/README.md) / [AI instructions](1-Framework/AI-INSTRUCTIONS.md) | Shared PHP/JSON infrastructure and development conventions; review before starting work. |
| Repository automation | [.github/](.github/) | GitHub Actions workflows for validation, publishing, and deployment notifications. |
| Demo screenshots | [demo/](demo/) / [README](demo/README.md) | Captured project screenshots; supporting assets rather than a separate app. |
| Webstats demo | [webstats-demo/](webstats-demo/) | Example pages for exercising the Webstats tracker. |
| Test projects | [testing/](testing/) / [Youth Scoreboard test docs](testing/CVC-Youth-Scoreboard/README.md) | Experimental/test copies; do not assume production status. |
| Linktree placeholder | [linktree/](linktree/) / [README](linktree/README.md) | Documentation-only placeholder; planned implementation files are not present. |

## Deployment notes

- GitHub stores source files.
- Hostinger runs the PHP projects.
- Short paths like `jasr.me/<project>` redirect to the canonical `jasr.me/github/<folder>/`.
- Runtime files such as logs, private config, API keys, uploaded files, and generated JSON data should stay out of Git unless they are public-safe samples.
- Project-specific setup instructions live in the project README files linked above.


## Shared user management

[`user-management`](user-management/README.md) provides central accounts, same-host login and per-project access roles. [`1-Framework`](1-Framework/README.md) now exposes its configured integration through `SharedIdentity::connect()`. Existing projects can adopt it individually after mapping their users and protecting their PHP endpoints.

Track shared-login adoption in the [User Management migration tracker](user-management/MIGRATION-TRACKER.md). Each application has its own migration checklist; completion requires verified data preservation and live access checks.
