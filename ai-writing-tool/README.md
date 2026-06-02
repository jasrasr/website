# AI Writing Tool

Revision : 1.0.0  
Updated : 2026-06-01

A lightweight browser-based writing tool for shared hosting. It provides a two-pane editor with local draft autosave, local change tracking, and AI-powered writing suggestions through a PHP proxy.

## What this project does

- Provides a browser editor in `index.php`
- Tracks draft text in browser `localStorage`
- Tracks a local edit/change log while writing
- Sends draft text to `api/suggest.php` for AI review
- Keeps the OpenAI API key server-side in `config/config.php`
- Includes basic per-IP rate limiting
- Includes `.htaccess` protections for config and runtime data folders

## File structure

```text
ai-writing-tool/
├─ index.php
├─ .htaccess
├─ .gitignore
├─ README.md
├─ SECURITY.md
├─ api/
│  ├─ health.php
│  └─ suggest.php
├─ assets/
│  ├─ app.js
│  └─ style.css
├─ config/
│  ├─ .htaccess
│  └─ config.example.php
└─ data/
   ├─ .gitkeep
   ├─ .htaccess
   ├─ drafts/
   │  └─ .gitkeep
   └─ rate-limit/
      └─ .gitkeep
```

## Setup

1. Upload the `ai-writing-tool` folder to your hosting account.
2. Copy this file:

```text
config/config.example.php
```

3. Rename the copy to:

```text
config/config.php
```

4. Edit `config/config.php` and add your OpenAI API key:

```php
'openai_api_key' => 'your-real-key-here',
```

5. Visit:

```text
https://yourdomain.com/ai-writing-tool/api/health.php
```

6. Confirm the health response shows:

```json
"ok": true
```

7. Open:

```text
https://yourdomain.com/ai-writing-tool/
```

## GitHub notes

Do commit:

- `config/config.example.php`
- `.gitignore`
- `.htaccess`
- `data/.gitkeep`
- `data/drafts/.gitkeep`
- `data/rate-limit/.gitkeep`

Do not commit:

- `config/config.php`
- runtime JSON files
- logs

The included `.gitignore` already excludes those.

## Browser compatibility

Built for current Chrome. It should also work in current Edge, Firefox, and Safari. Clipboard copy requires a secure context, meaning HTTPS or localhost. If copy buttons fail on plain HTTP, that is expected browser behavior, not haunted JavaScript.

## Server requirements

- PHP 8.0 or newer recommended
- PHP cURL extension enabled
- Apache-compatible `.htaccess` support recommended
- Writable `data/rate-limit/` folder for rate limiting

## Revision history

### 1.0.0 - 2026-06-01

Initial project release.

Included:

- Browser writing UI
- Local draft autosave
- Local change log
- AI suggestions pane
- PHP OpenAI proxy
- Basic rate limiting
- Config protection
- Runtime data protection
- Health check endpoint
