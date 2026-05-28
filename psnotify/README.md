# PSNotify

Revision: 1.3  
Author: Jason Lamb (with help from ChatGPT)

## What it does

PSNotify is a tiny self-hosted notification endpoint and viewer for long-running PowerShell jobs.
It accepts simple HTTP POST requests in an ntfy-style format and stores the messages in a JSON log.

## Package changelog

- 1.0 initial release
- 1.1 moved the messages to the top of the viewer, added manual refresh, added copy PowerShell example, and pushed setup controls below the message list
- 1.2 standardized script headers and changelogs across the PHP and Apache script files and aligned the publish example with the working direct `publish.php` endpoint
- 1.3 moved live secrets to ignored `config.local.php`, switched examples to header-based publish auth, added request limits, and stopped putting viewer keys in URLs

## Files

- `index.php` - mobile-friendly viewer
- `publish.php` - notification publish endpoint
- `fetch.php` - JSON feed for the viewer
- `common.php` - shared helper functions
- `config.php` - public-safe defaults and local config loader
- `config.sample.php` - template for private settings
- `config.local.php` - private settings file, ignored by Git
- `.htaccess` - topic routing so `/psnotify/mytopic` works when the host rewrite rules allow it
- `data/.htaccess` - blocks public access to the JSON storage
- `manifest.webmanifest` - lets the page behave more like an installable web app

## Upload target

Upload the contents of this folder to:

`public_html/psnotify/`

## Required first edits

Copy `config.sample.php` to `config.local.php`, then change at least:

- `PUBLISH_TOKEN`
- `VIEW_KEY`
- `DEFAULT_TOPIC`
- optional email settings if you want email forwarding

Do not commit `config.local.php`. The public `config.php` fails closed when no local config exists.

## Publish example from PowerShell

```powershell
$topic = 'jason-longjobs-83hd72'
$message = "Job finished on $env:COMPUTERNAME at $(Get-Date -Format 'yyyy-MM-dd hh:mm:ss tt')"

Invoke-RestMethod `
    -Uri 'https://jasr.me/psnotify/publish.php?topic=jason-longjobs-83hd72' `
    -Method Post `
    -Headers @{ 'X-PSNotify-Token' = 'REPLACE_WITH_PUBLISH_TOKEN'; Title = 'Job Complete'; Priority = 'high'; Tags = 'white_check_mark,computer' } `
    -Body $message
```

## View example

Open the viewer using your topic. Enter the view key in the page form:

`https://jasr.me/psnotify/?topic=jason-longjobs-83hd72`

## Notes

- Browser notifications only work when the page is open and notification permission is granted.
- Real background Web Push for iPhone is a later upgrade and is more complex than this starter version.
- Stored messages are capped by `MAX_ITEMS` in `config.php`.
- Message body size is capped by `MAX_MESSAGE_BYTES`.
- Publish requests are rate-limited by `RATE_LIMIT_SECONDS`.
- `manifest.webmanifest` stays comment-free on purpose because valid JSON does not allow comments.
