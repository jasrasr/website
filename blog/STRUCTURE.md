<!--
# filename: STRUCTURE.md
# author: Jason Lamb (with help from ChatGPT)
# created date: 2026-02-04
# modified date: 2026-02-04
# revision: 1.1
# changelog:
# - 1.0: Full folder layout + what each file does
# - 1.1: Corrected asset/log filenames
-->
# Project structure

This is the “where is what?” map for the blog.

## Top-level pages

- `index.html` — post list + search UI
- `post.html` — single-post renderer (loads a JSON post)
- `about.html` — static page
- `rss.xml` — generated feed (rebuilt by `tools/Build-Blog.ps1`)
- `sitemap.xml` — generated sitemap (rebuilt by `tools/Build-Blog.ps1`)
- `log-search.php` — optional server endpoint that logs searches into `logs/search-log.ndjson`

## `assets/`

- `assets/css/style.css` — site styling
- `assets/js/blog.js` — fetches `posts/index.json`, handles search, renders posts

## `posts/`

- `posts/*.json` — each post is a JSON object
- `posts/_template.json` — starter template (includes example `srcset` snippet)
- `posts/index.json` — generated index (includes `_searchText` for full-text search)

## `media/`

Public images.

- `media/originals/YYYY/MM/` — re-encoded originals (JPEG)
- `media/derivatives/YYYY/MM/` — resized images for `srcset` (320/640/960/1600)
- `media/_inbox/` — optional local drop folder used by `tools/Build-Blog.ps1 -ProcessMedia`

## `admin/` (protected)

- `admin/.htaccess` — HTTP Basic Auth + no directory listing
- `admin/upload-media.php` — upload + generate derivatives + update manifest + show copy/paste snippet
- `admin/view-media.php` — dashboard (unused media, missing alt, missing files, “manifest drift”)
- `admin/media-manifest.json` — **private** index of all media
- `admin/backups/` — automatic backups of the manifest before writes

## `tools/`

- `tools/Build-Blog.ps1` — rebuilds `posts/index.json`, `rss.xml`, `sitemap.xml` and refreshes manifest usage
- `tools/Build-Media.ps1` — local-only helper: takes an image, generates originals/derivatives, returns info
- `tools/Restore-Manifest.ps1` — restore `admin/media-manifest.json` from a backup file
