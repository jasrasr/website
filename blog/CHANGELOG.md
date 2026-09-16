<!--
# filename: CHANGELOG.md
# author: Jason Lamb (with help from ChatGPT)
# created date: 2026-02-03
# modified date: 2026-09-16
# revision: 1.4.0
# changelog:
# - 1.4.0: Documents project-series integration and review fixes.
# - 1.1: Added v1.2 release notes (admin media workflow + manifest)
# - 1.2: Minor doc corrections (asset/log filenames)
# - 1.3: Minor README fix (style.css filename)
# - 1.0: Initial changelog
-->
# Changelog

## v1.4.0 — 2026-09-16

- Add 43 project articles and cover images alongside the three existing posts.
- Resolve images relative to the blog directory and correct the Screen Size and Time Clock Kiosk covers.
- Rebuild index, RSS, sitemap, and media usage from source posts; retain excerpts and cover metadata.
- Detect derivative widths from filenames and fix the sitemap base-page array.


## v1.2 — 2026-02-04

Media + admin system added:

- Added `/admin` (HTTP Basic Auth) with:
  - `upload-media.php` — upload image, re-encode, create derivatives, update manifest, output srcset snippet
  - `view-media.php` — view manifest, copy snippet buttons, highlight unused/alt-missing/missing-file entries
  - `media-manifest.json` — private media index (protected)
  - `backups/` — auto backups of manifest before writes
- Added `/media` folder layout:
  - `originals/YYYY/MM/`
  - `derivatives/YYYY/MM/`
  - `_inbox/` (optional local workflow)
- Added tools:
  - `tools/Build-Media.ps1`
  - `tools/Restore-Manifest.ps1`
- Updated `tools/Build-Blog.ps1`:
  - optional `-ProcessMedia`
  - refreshes `used_in` by scanning post `content_html`
  - writes `posts/index.json`, `rss.xml`, `sitemap.xml` with embedded header metadata
- Updated docs:
  - `README.md` + `STRUCTURE.md`

## v1.1 — 2026-02-03

- Added full-text search index field (`_searchText`) to `posts/index.json`
- Added `log-search.php` + `logs/search-log.json` collection point
- Regenerated `rss.xml` and `sitemap.xml`
- Cleaned up UI and began standardizing canonical URLs
