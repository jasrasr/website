# Website Stuff

This repo is where I keep HTML, CSS, JavaScript, PHP scripts, and small hosted web projects. I started my own website back in 2004 and have created a ton of code since then. It is all somewhere on my website, which is exactly how personal web projects reproduce in the wild.

I currently use Hostinger for hosting. I have used GoDaddy, billable through LuckyRegister, and Powweb before that. I currently maintain several domains, but I mainly publish on [jasonlamb.me](https://jasonlamb.me). I use [jasr.me](https://jasr.me) for tools, tests, redirects, and hosted side projects.

Important hosting note: this repo is used as source storage and syncs to my web host. PHP projects in this repo are intended to run from the hosted website, not from GitHub Pages.

## Primary sites

- Personal blog/site: [jasonlamb.me](https://jasonlamb.me)
- Tools, tests, redirects, and projects: [jasr.me](https://jasr.me)

## URL convention

Folders in this repo sync under `https://jasr.me/github/<folder>/`, which is the canonical URL for each project. Short vanity paths like `jasr.me/mpg` or `jasr.me/time-clock` are **redirects** to the matching `jasr.me/github/<folder>/` location, so the tables below list the canonical `/github/` URLs.

## Project index

| Project | Repo path / docs | Live or target URL | Notes |
|---|---|---|---|
| YOURLS link shortener and tracker | External app | [jasr.me](https://jasr.me) | Fork of [YOURLS](https://github.com/YOURLS/YOURLS). |
| Secure text sender | External app | [jasr.me/secure](https://jasr.me/secure) | Fork of [PrivateBin](https://github.com/PrivateBin/PrivateBin). |
| Random Password Generator | [Random-Password-Generator/](Random-Password-Generator/) | [jasr.me/pw](https://jasr.me/pw) | Client-side password generator experiments and working copies. Deployed at `/pw`; the `/github/` path is access-restricted. |
| Text Copy / scratch pad | [text/](text/) | [jasr.me/github/text](https://jasr.me/github/text/) | Server-backed scratch pad for editing text and retrieving it from another device. |
| MPG Fuel Log Tracker | [mpg/](mpg/) / [README](mpg/README.md) | [jasr.me/github/mpg](https://jasr.me/github/mpg/) | PHP fuel log with MPG calculations, CSV export, admin dashboard, and trend chart. |
| Time Clock Kiosk Display | [time-clock/](time-clock/) / [README](time-clock/README.md) | [jasr.me/github/time-clock](https://jasr.me/github/time-clock/) | Full-screen clock, weather, and scrolling alerts for a Raspberry Pi CM4 kiosk. |
| Trip ETA Tracker | [gps-eta/](gps-eta/) / [README](gps-eta/README.md) | [jasr.me/github/gps-eta](https://jasr.me/github/gps-eta/) | Mobile GPS speed, ETA, compass heading, trip sessions, and server-side history. |
| Family GPS Tracker | [family-tracker/](family-tracker/) / [README](family-tracker/README.md) | [jasr.me/github/family-tracker](https://jasr.me/github/family-tracker/) | Consent-based PHP + JSON family/friend location sharing with groups, check-ins, trip sharing, geofences, and installable PWA support. |
| TV Binge Board | [tv-binge-board/](tv-binge-board/) / [README](tv-binge-board/README.md) | [jasr.me/github/tv-binge-board](https://jasr.me/github/tv-binge-board/) | Mobile-first PHP/JSON tracker for what to watch, watching, and completed TV/movies, with import, sharing, and friend activity. Does not stream. |
| CVC Youth Scoreboard | [scoreboard/](scoreboard/) / [README](scoreboard/README.md) | [jasr.me/github/scoreboard](https://jasr.me/github/scoreboard/) | PHP scoreboard app with default, Collide, Youth, and Frontlines instances. |
| CVC Youth Scoreboard cache-fix folder | [CVC-Youth-Scoreboard/](CVC-Youth-Scoreboard/) | [jasr.me/github/CVC-Youth-Scoreboard](https://jasr.me/github/CVC-Youth-Scoreboard/) | Temporary folder kept for compatibility/cache-fix work. Main docs live under `scoreboard/`. |
| Budget Tracker | [finances/](finances/) / [README](finances/README.md) | [jasr.me/github/finances](https://jasr.me/github/finances/) | Private PHP budget tracker using per-user JSON files. |
| Weather Dashboard | [weather/](weather/) / [README](weather/README.md) | [jasr.me/github/weather](https://jasr.me/github/weather/) | Mobile-friendly PHP weather dashboard using OpenWeather data. |
| QR Box Inventory System | [box/](box/) / [README](box/README.md) | [jasr.me/github/box](https://jasr.me/github/box/) | Database-free QR inventory system for physical storage boxes. |
| AI Writing Tool | [ai-writing-tool/](ai-writing-tool/) / [README](ai-writing-tool/README.md) | [jasr.me/github/ai-writing-tool](https://jasr.me/github/ai-writing-tool/) | Two-pane browser editor with local autosave and AI suggestions through a PHP proxy. |
| Secure Upload & File Manager | [File-Manager/](File-Manager/) / [README](File-Manager/README.md) | [jasr.me/github/File-Manager](https://jasr.me/github/File-Manager/) | Admin-only file manager with PowerShell-friendly upload API, MFA, allowlisting, and versioning. |
| PSNotify | [psnotify/](psnotify/) / [README](psnotify/README.md) | [jasr.me/github/psnotify](https://jasr.me/github/psnotify/) | Self-hosted notification endpoint and viewer for long-running PowerShell jobs. |
| License Plate Photo Logger | [license-plate/](license-plate/) / [README](license-plate/README.md) | [jasr.me/github/license-plate](https://jasr.me/github/license-plate/) | PHP app for uploading plate photos, extracting plate text, and logging duplicates. |
| License Plate Collection | [license-plates/](license-plates/) / [README](license-plates/README.md) | [jasr.me/github/license-plates](https://jasr.me/github/license-plates/) | A collection of license plates with a searchable index database. Early/work in progress. |
| Timeclock Photo Logger | [how-much-time-worked/](how-much-time-worked/) / [README](how-much-time-worked/README.md) | Target folder: `/timeclock/` | Photo/manual employee hour logger with OCR options and JSON stats. |
| Computer Heartbeat Dashboard | [computers/](computers/) / [README](computers/readme.md) | Target folder: `/computers/` | PowerShell-to-PHP heartbeat system for Windows device status tracking. |
| JSON Flat-File Blog | [blog/](blog/) / [README](blog/README.md) | [jasr.me/github/blog](https://jasr.me/github/blog/) | Static-ish JSON blog with PowerShell builder, RSS, sitemap, and admin-protected media workflow. |
| Robot or Human? Verification Gate | [human-proof/](human-proof/) / [README](human-proof/README.md) | [jasr.me/github/human-proof](https://jasr.me/github/human-proof/) | Press-and-hold "prove you're human" gate with a comic racing robot, plus optional PHP server-side gating so the next page can't be reached from the source. |
| Odometer / Speedometer | [odometer/](odometer/) | [jasr.me/github/odometer](https://jasr.me/github/odometer/) | Browser odometer/speedometer display (HTML/CSS/JS). |
| Countdown to Cooper Integration | [countdown/](countdown/) | [jasr.me/github/countdown](https://jasr.me/github/countdown/) | Branded countdown timer page with Cooper and Altronic logos. |
| Random URL / QR Targets | [Random-URL/](Random-URL/) | [jasr.me/github/Random-URL](https://jasr.me/github/Random-URL/) | Numbered static landing pages (`0.html`–`9.html`) used as QR-code targets, generated by a PowerShell script. |
| Just Jason (personal site) | [just-jason/](just-jason/) | [jasr.me/github/just-jason](https://jasr.me/github/just-jason/) | Personal "just for Jason" site. Only root files are live; `rev*/` subfolders are frozen revision snapshots. |
| Visual Ping Webhook API | [visual-ping-webhook-api/](visual-ping-webhook-api/) / [README](visual-ping-webhook-api/README.md) | [jasr.me/github/visual-ping-webhook-api](https://jasr.me/github/visual-ping-webhook-api/) | Webhook API for Visual Ping. Early/placeholder. |

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

## Deployment notes

- GitHub stores source files.
- Hostinger runs the PHP projects.
- Short paths like `jasr.me/<project>` redirect to the canonical `jasr.me/github/<folder>/`.
- Runtime files such as logs, private config, API keys, uploaded files, and generated JSON data should stay out of Git unless they are public-safe samples.
- Project-specific setup instructions live in the project README files linked above.
