<!--
# filename: CHANGELOG.md
# author: Jason Lamb (with help from ChatGPT)
# created date: 2026-02-03
# modified date: 2026-09-29
# revision: 1.6.0
# changelog:
# - 1.6.0: Neutral automated article voice and safe existing-post content sync.
# - 1.5.0: Adds a dry-run-first WordPress category and tag maintenance tool.
# - 1.4.0: Documents project-series integration and review fixes.
# - 1.1: Added v1.2 release notes (admin media workflow + manifest)
# - 1.2: Minor doc corrections (asset/log filenames)
# - 1.3: Minor README fix (style.css filename)
# - 1.0: Initial changelog
-->
# Changelog

## v1.6.0 — 2026-09-29

- Rewrite all 43 automated project articles in neutral third person, including titles, excerpts and cover alt text, without attributing invented opinions to Jason.
- Preserve article slugs, links and original dates; refresh generated index and RSS text.
- Add an editorial policy and automated-series voice check.
- Add a dry-run-first content sync for existing WordPress posts, with ID/slug/site checks, original-text backups and concurrent-edit detection. Preserve live authors and schedules.
- Add isolated API-mock tests and blog validation CI.
- Add append-only public article revision records with unique IDs, dates, summaries and GitHub links; render a single deterministic history footer in the static blog and WordPress.
- Add a local revision-note helper and validation requiring a new note when article text changes.
- Automatically sync existing posts after relevant article changes reach `main`, with serialized latest-main runs, manual preview/retry, unchanged-post skipping and encrypted recovery artifacts.

## v1.5.0 — 2026-09-24

- Add a WordPress taxonomy tool for listing, renaming, merging, and deleting categories and tags.
- Require explicit `-Commit` for mutations and preview affected posts before merges or deletes.
- Allow term selection by numeric ID when WordPress contains duplicate names.

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
