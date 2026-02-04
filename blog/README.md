<!--
# filename: README.md
# author: Jason Lamb (with help from ChatGPT)
# created date: 2026-02-03
# modified date: 2026-02-04
# revision: 1.3
# changelog:
# - 1.1: Added admin-protected media workflow (manifest + uploader) and a full directory map
# - 1.2: Corrected asset/log filenames in directory map
# - 1.3: Fixed README assets/css/style.css filename
# - 1.0: Initial project README
-->
# JSON Flat-File Blog (No Database)

This is a tiny, static-ish blog that runs without a database:

- Posts live as individual JSON files in `posts/`
- The site renders via vanilla HTML + `assets/js/blog.js`
- A PowerShell builder (`tools/Build-Blog.ps1`) generates:
  - `posts/index.json` (for listing + search)
  - `rss.xml`
  - `sitemap.xml`

Base URL assumed by the builder: **https://jasr.me/blog**

---

## Quick start (local)

1. Edit or add a post:
   - Copy `posts/_template.json` → `posts/my-post.json`
   - Fill in `slug`, `title`, `date`, and `content_html`
2. Rebuild indexes:
   - Run `tools/Build-Blog.ps1`
3. Upload to server:
   - Copy the entire folder to your hosting (keep paths intact)

---

## Media workflow (admin-protected)

This repo adds a WordPress-ish media system:

- Public image files live in:
  - `media/originals/YYYY/MM/` (re-encoded originals as JPEG)
  - `media/derivatives/YYYY/MM/` (320/640/960/1600 widths for `srcset`)
- A private manifest lives in:
  - `admin/media-manifest.json` (protected by HTTP auth)

### Server-side uploader

- `admin/upload-media.php`:
  - Validates uploads (type + size)
  - Re-encodes originals (strips metadata, auto-orients)
  - Generates derivatives
  - Updates the manifest
  - Outputs a copy/paste **srcset snippet** for `content_html`

### Manifest dashboard

- `admin/view-media.php`:
  - Shows all media entries
  - Highlights:
    - **Unused** media
    - Missing **alt text**
    - Missing files on disk (integrity)
    - Media referenced in posts but missing from manifest (drift)
  - Provides **Copy srcset** buttons

### Requirements

- Hosting needs:
  - **PHP** with `fileinfo` enabled
  - **ImageMagick CLI** available as `magick`
  - **Apache** (or equivalent) that supports `.htaccess` Basic Auth
- Put your `.htpasswd` file **outside** the web root (see `admin/.htaccess`).

---

## Directory map

(Also see `STRUCTURE.md`.)

```
json-flat-blog/
├─ index.html
├─ post.html
├─ about.html
├─ rss.xml
├─ sitemap.xml
├─ log-search.php
├─ logs/
│  ├─ .htaccess
│  └─ search-log.ndjson
├─ assets/
│  ├─ css/
│  │  └─ style.css
│  └─ js/
│     └─ blog.js
├─ posts/
│  ├─ index.json          (generated)
│  ├─ _template.json
│  └─ *.json              (your posts)
├─ media/
│  ├─ README.md
│  ├─ _inbox/             (optional; for local builds)
│  ├─ originals/
│  └─ derivatives/
├─ admin/                 (HTTP auth protected)
│  ├─ .htaccess
│  ├─ upload-media.php
│  ├─ view-media.php
│  ├─ media-manifest.json
│  └─ backups/
│     └─ media-manifest-*.json
└─ tools/
   ├─ Build-Blog.ps1
   ├─ Build-Media.ps1
   └─ Restore-Manifest.ps1
```

---

## Notes / opinions from the robot

- The manifest is intentionally “private”: it can contain credits/notes you don’t want publicly discoverable.
- The build script backs up the manifest before writing changes.
- If `admin/view-media.php` says “USAGE STALE”, run `tools/Build-Blog.ps1` to refresh `used_in`.
