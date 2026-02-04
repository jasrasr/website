<!--
# filename: README.md
# author: Jason Lamb (with help from ChatGPT)
# created date: 2026-02-04
# modified date: 2026-02-04
# revision: 1.0
# changelog:
# - 1.0: Media folder documentation (originals, derivatives, inbox)
-->
# Media folder

This blog uses a WordPress-ish media layout, without WordPress.

## Folders

- `originals/` — re-encoded originals (JPEG) placed under `YYYY/MM/`
- `derivatives/` — resized versions for `srcset` under `YYYY/MM/`
- `_inbox/` — optional local “drop folder” for `tools/Build-Blog.ps1 -ProcessMedia`

## Notes

- Images are served publicly from `/media/...`
- **The manifest lives in `/admin/media-manifest.json`** and is protected by `.htaccess`.
