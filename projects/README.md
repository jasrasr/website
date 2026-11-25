<!--
File Name     : projects/README.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Deployment, maintenance, and validation for the project directory.
Revision      : 1.0.0
Created Date  : 2026-11-25
Modified Date : 2026-11-25
Changelog     :
  Rev 1.0.0 - Add the Jasr.me project directory.
-->

# Project Navigation

Hostinger serves this static page at [jasr.me/github/projects/](https://jasr.me/github/projects/) after the repository sync. The layout follows the [GitHub Pages navigation](https://jasrasr.github.io/). It requires HTML, CSS, and optional browser JavaScript. No host configuration or runtime files are required.

Edit the tiles in `index.html` when adding or renaming a listed app. Use relative links for sibling projects under `/github/` and explicit links for site services. List visitor-facing entry points only. Preserve app-level authorization and label tiles where the visitor needs an account, access, or a shared link. Bump the visible revision and update this changelog when publishing changes.

## Manual validation

- Open `/github/projects/` on desktop and a narrow phone viewport. Check that the tiles fit and keyboard focus is visible.
- Jump to a category and search for `tic-tac`, `trackers`, and a term with no matches. Clear the field and confirm all categories return.
- Disable JavaScript and confirm the tiles and category links remain available.
- Follow a game, a sign-in-required tracker, and an external link. Verify the deployed app opens or presents its expected access screen.
- After Hostinger sync, confirm the displayed revision matches this changelog and that `/` still opens the existing Jasr.me homepage.

## Changelog

### 1.0.0 · 2026-11-25

- Added a mobile-friendly directory with colorful project tiles, category shortcuts, and in-page search.
