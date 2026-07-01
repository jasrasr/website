# Website Stuff

This repo is where I keep HTML, CSS, JavaScript, PHP scripts, and small hosted web projects. I started my own website back in 2004 and have created a ton of code since then. It is all somewhere on my website, which is exactly how personal web projects reproduce in the wild.

I currently use Hostinger for hosting. I have used GoDaddy, billable through LuckyRegister, and Powweb before that. I currently maintain several domains, but I mainly publish on [jasonlamb.me](https://jasonlamb.me). I use [jasr.me](https://jasr.me) for tools, tests, redirects, and hosted side projects.

Important hosting note: this repo is used as source storage and syncs to my web host. PHP projects in this repo are intended to run from the hosted website, not from GitHub Pages.

## Primary sites

- Personal blog/site: [jasonlamb.me](https://jasonlamb.me)
- Tools, tests, redirects, and projects: [jasr.me](https://jasr.me)

## Project index

Most folders in this repo sync under `https://jasr.me/github/<folder>/`. Some projects are deployed at cleaner root-level paths, noted below.

| Project | Repo path / docs | Live or target URL | Notes |
|---|---|---|---|
| YOURLS link shortener and tracker | External app | [jasr.me](https://jasr.me) | Fork of [YOURLS](https://github.com/YOURLS/YOURLS). |
| Secure text sender | External app | [jasr.me/secure](https://jasr.me/secure) | Fork of [PrivateBin](https://github.com/PrivateBin/PrivateBin). |
| Random Password Generator | [Random-Password-Generator/](Random-Password-Generator/) | [jasr.me/pw](https://jasr.me/pw) | Client-side password generator experiments and working copies. |
| Text Copy / scratch pad | [text/](text/) | [jasr.me/github/text](https://jasr.me/github/text/) | Server-backed scratch pad for editing text and retrieving it from another device. |
| MPG Fuel Log Tracker | [mpg/](mpg/) / [README](mpg/README.md) | [jasr.me/mpg](https://jasr.me/mpg) | PHP fuel log with MPG calculations, CSV export, admin dashboard, and trend chart. |
| Time Clock Kiosk Display | [time-clock/](time-clock/) / [README](time-clock/README.md) | [jasr.me/time-clock](https://jasr.me/time-clock) | Full-screen clock, weather, and scrolling alerts for a Raspberry Pi CM4 kiosk. |
| Trip ETA Tracker | [gps-eta/](gps-eta/) / [README](gps-eta/README.md) | [jasr.me/github/gps-eta](https://jasr.me/github/gps-eta/) | Mobile GPS speed, ETA, compass heading, trip sessions, and server-side history. |
| CVC Youth Scoreboard | [scoreboard/](scoreboard/) / [README](scoreboard/README.md) | [jasr.me/github/scoreboard](https://jasr.me/github/scoreboard/) | PHP scoreboard app with default, Collide, Youth, and Frontlines instances. |
| CVC Youth Scoreboard cache-fix folder | [CVC-Youth-Scoreboard/](CVC-Youth-Scoreboard/) | [jasr.me/github/CVC-Youth-Scoreboard](https://jasr.me/github/CVC-Youth-Scoreboard/) | Temporary folder kept for compatibility/cache-fix work. Main docs live under `scoreboard/`. |
| Budget Tracker | [finances/](finances/) / [README](finances/README.md) | [jasr.me/github/finances](https://jasr.me/github/finances/) | Private PHP budget tracker using per-user JSON files. |
| Weather Dashboard | [weather/](weather/) / [README](weather/README.md) | [jasr.me/github/weather](https://jasr.me/github/weather/) | Mobile-friendly PHP weather dashboard using OpenWeather data. |
| QR Box Inventory System | [box/](box/) / [README](box/README.md) | [jasr.me/box](https://jasr.me/box/) | Database-free QR inventory system for physical storage boxes. |
| AI Writing Tool | [ai-writing-tool/](ai-writing-tool/) / [README](ai-writing-tool/README.md) | [jasr.me/github/ai-writing-tool](https://jasr.me/github/ai-writing-tool/) | Two-pane browser editor with local autosave and AI suggestions through a PHP proxy. |
| Secure Upload & File Manager | [File-Manager/](File-Manager/) / [README](File-Manager/README.md) | [jasr.me/file-manager](https://jasr.me/file-manager/) | Admin-only file manager with PowerShell-friendly upload API, MFA, allowlisting, and versioning. |
| PSNotify | [psnotify/](psnotify/) / [README](psnotify/README.md) | [jasr.me/psnotify](https://jasr.me/psnotify/) | Self-hosted notification endpoint and viewer for long-running PowerShell jobs. |
| License Plate Photo Logger | [license-plate/](license-plate/) / [README](license-plate/README.md) | [jasr.me/license-plate](https://jasr.me/license-plate/) | PHP app for uploading plate photos, extracting plate text, and logging duplicates. |
| Timeclock Photo Logger | [how-much-time-worked/](how-much-time-worked/) / [README](how-much-time-worked/README.md) | Target folder: `/timeclock/` | Photo/manual employee hour logger with OCR options and JSON stats. |
| Computer Heartbeat Dashboard | [computers/](computers/) / [README](computers/readme.md) | Target folder: `/computers/` | PowerShell-to-PHP heartbeat system for Windows device status tracking. |
| JSON Flat-File Blog | [blog/](blog/) / [README](blog/README.md) | [jasr.me/blog](https://jasr.me/blog) | Static-ish JSON blog with PowerShell builder, RSS, sitemap, and admin-protected media workflow. |

## Repo support tools and standalone files

| Tool / file | Repo path / docs | Purpose |
|---|---|---|
| Smart 404 | [smart-404/](smart-404/) / [README](smart-404/README.md) | YOURLS-aware 404 handling, request logging, manual mappings, and conservative fuzzy matching. |
| Custom Directory Browser | [custom-directory/](custom-directory/) / [README](custom-directory/README.md) | Central path-aware PHP file browser using thin `directory.php` wrappers. |
| Standalone authenticated directory viewer | [Custom-HTML-Directory-Viewer.php](Custom-HTML-Directory-Viewer.php) | Generic authenticated file browser with favorites, telemetry, sorting, and download tracking. |
| Impossible Click | [impossible-click.html](impossible-click.html) | Standalone browser experiment/game. |

## Deployment notes

- GitHub stores source files.
- Hostinger runs the PHP projects.
- Runtime files such as logs, private config, API keys, uploaded files, and generated JSON data should stay out of Git unless they are public-safe samples.
- Project-specific setup instructions live in the project README files linked above.
