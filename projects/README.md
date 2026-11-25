<!--
File Name     : projects/README.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Deployment, maintenance, and validation for the project directory.
Revision      : 1.1.0
Created Date  : 2026-11-25
Modified Date : 2026-11-25
Changelog     :
  Rev 1.1.0 - Document project metadata and automated index updates.
  Rev 1.0.0 - Add the Jasr.me project directory.
-->

# Project Navigation

Hostinger serves this static page at [jasr.me/github/projects/](https://jasr.me/github/projects/) after the repository sync. The layout follows the [GitHub Pages navigation](https://jasrasr.github.io/). It requires HTML, CSS, and optional browser JavaScript. No host configuration or runtime files are required.

## Register a project

Copy [Refill Tracker’s `project.json`](../refill-tracker/project.json) into the new top-level app folder. Update the `_header` fields, name, category, and description. The generator adds a source link, discovers an app README when present, and uses `https://jasr.me/github/<folder>/` by default.

| Field | Purpose |
| --- | --- |
| `_header` | File metadata and revision history required by the repository. |
| `name`, `description` | Display name and one-line README summary. |
| `category` | `games`, `trackers`, or `tools`. |
| `url` | Optional HTTPS override. Use `null` for an entry without a confirmed site URL. |
| `readme` | Set to `false` to omit the README row. Defaults to `true`. |
| `navigation` | Defaults to off. Set to `true` to publish a tile, or use an object to set `name`, `category`, `href`, `access`, or `order` for the tile. |
| `order` | Optional README sort position. Entries without a position sort alphabetically after the seeded order. |
| `site_note`, `link_title` | Optional custom site label and clickable README title. |

List visitor-facing entry points on the Projects page. The access label describes the app’s existing requirements. Keep credentials and other private values out of metadata.

Before committing a new or updated project, run from the repository root:

```sh
python3 .github/scripts/project-index.py
```

Commit the metadata and updated `README.md` and `projects/index.html` in the same PR. The Project Index workflow checks those files on PRs to `main` and warns when a new top-level app entry point lacks metadata. If a push to `main` leaves generated files stale, the workflow prepares a sync PR. You can also run it manually from `main`. To let GitHub Actions open that PR, enable **Allow GitHub Actions to create and approve pull requests** under the repository’s **Settings → Actions → General → Workflow permissions**. The workflow opens or updates the sync branch and PR for review.

Store metadata outside the generated blocks. Edit existing app code and app-level documentation where appropriate.

## Manual validation

- Open `/github/projects/` on desktop and a narrow phone viewport. Check that the tiles fit and keyboard focus is visible.
- Jump to a category and search for `tic-tac`, `trackers`, and a term with no matches. Clear the field and confirm all categories return.
- Disable JavaScript and confirm the tiles and category links remain available.
- Follow a game, a sign-in-required tracker, and an external link. Verify the deployed app opens or presents its expected access screen.
- After Hostinger sync, confirm the displayed revision matches this changelog and that `/` still opens the existing Jasr.me homepage.

## Changelog

### 1.1.0 · 2026-11-25

- Generate project tiles and the main README project tables from folder metadata.

### 1.0.0 · 2026-11-25

- Added a mobile-friendly directory with colorful project tiles, category shortcuts, and in-page search.
