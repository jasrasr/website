<!--
File: CHANGELOG.md
Project: TV Binge Board
Description: Human-readable release history rendered by changelog.php.
Author: Jason Lamb / ChatGPT
Created: 2026-07-02
Modified: 2026-07-05
Revision: 1.5.18
-->

# Changelog

## rev 1.5.18 - 2026-07-05

- Added `compare.php` for list comparison between the signed-in user and a visible public or connected user.
- Comparison shows titles on both lists, only yours, only theirs, and titles worth checking from the other user.
- Shared-title rows compare each person's status, rating, and TV episode progress.
- Bumped the visible project revision to 1.5.18.

## rev 1.5.17 - 2026-07-05

- Reduced the Home screen notice footprint with compact dashboard notice styling.
- Added a manual `Reload latest app files` action in Settings for refreshing the cached PWA shell when needed.
- Made the Home screen `Use it like an app` prompt dismissible per user.
- Added a Settings checkbox to show or hide the Add to Home Screen reminder on Home.
- Added a persistent link to the install instructions from Settings.
- Bumped the visible project revision and service worker cache to 1.5.17.

## rev 1.5.16 - 2026-07-05

- Added a Friend activity feed to the Connections page.
- The feed shows recent visible activity from connected users and users who share their lists publicly.
- Activity summaries include added/updated titles, status updates, episode progress, and season progress.
- The feed respects existing list visibility rules and links back to each visible user's public/shared list.
- Bumped the visible project revision and service worker cache to 1.5.16.

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

Older entries are available in Git history before rev 1.5.12.
