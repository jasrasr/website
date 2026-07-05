<!--
File: CHANGELOG.md
Project: TV Binge Board
Description: Human-readable release history rendered by changelog.php.
Author: Jason Lamb / ChatGPT
Created: 2026-07-02
Modified: 2026-07-05
Revision: 1.5.15
-->

# Changelog

## rev 1.5.15 - 2026-07-05

- Verified the remaining Matt feedback items against the current implementation.
- Added explicit episode clear support through `op=ec` so watched episodes are deliberately unmarked instead of relying on implicit toggle behavior.
- Renamed the season clear button to `Unmark season watched` for clearer intent.
- Added a prompt when marking a later unwatched episode: users can mark all prior episodes/seasons too, or only mark the selected episode.
- Added a prompt when marking a later season watched: users can mark prior seasons too, or only mark the selected season.
- The item detail page now auto-checks the current TMDB-linked show when its metadata is stale, instead of only relying on Dashboard/My List lazy checks.
- Removed the 30-season display limit so long-running shows such as Survivor can list seasons beyond season 30.
- Bumped the visible project revision and service worker cache to 1.5.15.

## rev 1.5.14 - 2026-07-05

- Added visible watched episode checkmarks.
- Watched episode buttons now show a checkmark before the episode code and a watched-status line.
- Added an episode-grid legend explaining watched versus unwatched tiles.

## rev 1.5.13 - 2026-07-04

- Selecting an unwatched later episode marks prior episodes and prior seasons as watched.
- Selecting an already watched episode clears only that episode.

## rev 1.5.12 - 2026-07-04

- Added `watch-progress.php` as a root-level watch progress endpoint to avoid host-level 403 blocks.
- Updated episode and season progress forms to use the new endpoint.

## rev 1.5.11 - 2026-07-03

- Added persistent Remember Me login support.
- Login now includes a checked-by-default `Keep me signed in on this device` option.
- Added a secure long-lived remember-me cookie valid for up to one year.
- Remember tokens are stored server-side only as hashed validators in `data/remember-tokens.json`.
- Remember tokens rotate when they are used to restore a session.
- Logout revokes the current remember token.
- Password changes and admin password resets revoke saved remember tokens for that user.
- Bumped the visible project revision and service worker cache to 1.5.11.

## rev 1.5.10 - 2026-07-03

- Fixed the season-level Mark season watched / Clear season watched buttons returning a host-level 403.
- Changed season button POST fields to neutral `mode=season_watch` and `mode=season_clear` values.
- Kept backward compatibility for the earlier season action values in `api/toggle-episode.php`.
- Added basic redirect validation before returning to the item page.
- Bumped the visible project revision and service worker cache to 1.5.10.

## rev 1.5.9 - 2026-07-03

- Added automatic checks for tracked TMDB-linked TV shows.
- Added `includes/auto-refresh.php` with lazy metadata refresh helpers.
- Dashboard and My List now check a limited number of stale tracked TV shows when the user loads the page.
- Saved TMDB series metadata is refreshed so new seasons and newly available episodes appear in next-up tracking and the episode grid.
- Affected season metadata caches are refreshed when season counts or airing metadata changes.
- Completed/caught-up shows move back to Watching when an unwatched aired episode becomes available.
- Watched episode records are preserved; new episodes are made available to watch but are not marked watched automatically.
- Bumped the visible project revision and service worker cache to 1.5.9.

Older entries are available in Git history before rev 1.5.9.
