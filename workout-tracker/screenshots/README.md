<!--
File Name: README.md
Author: Jason Lamb (with help from ChatGPT)
Description: Screenshot gallery filenames, privacy notes and import instructions.
Revision: 1.1.0
Created Date: 2026-10-21
Modified Date: 2026-10-21
Changelog: 1.1.0 - Explain screenshot import helper. 1.0.0 - Define six screenshot assets from the October 21 mobile review.
-->
# Workout Tracker mobile screenshots

Six screenshots were supplied from the mobile site on October 21, 2026. A prepared archive is provided separately in the related ChatGPT conversation because the currently connected GitHub text-content API does not accept the uploaded binary screenshot files directly.

**Expected repository file paths**, relative to the `workout-tracker` folder:

1. `screenshots/01-dashboard-overview.webp` — Keep moving header, sessions, totals.
2. `screenshots/02-natural-language-entry.webp` — workout description and Review workout button.
3. `screenshots/03-push-ups-entry.webp` — 100 push-ups natural-language entry.
4. `screenshots/04-recognized-review.webp` — recognized exercise, date, photo/time options.
5. `screenshots/05-progress-trends.webp` — treadmill progress chart and recent workouts.
6. `screenshots/06-manual-entry.webp` — optional manually entered workout fields.

The archive contains these six images under the matching `workout-tracker/screenshots/` paths.

From a local checkout of the PR branch, run the included PowerShell helper (substitute the archive location):

```powershell
pwsh -File ./workout-tracker/screenshots/Import-Screenshots.ps1 -ArchivePath "$HOME/Downloads/workout_tracker_repo_screenshots.zip"
```

The helper verifies all six names, refuses to overwrite existing images, copies them into the right folder, and prints `git add`, `git commit` and `git push` commands. It never alters the private workout storage or merges a branch. Extract at the **repository root**, verify no private API keys/user IDs are visible, and add the files to Git before merging the screenshot gallery as complete.

Do not link missing images in the main README. **The screenshots are not committed to the repository until the six binary files appear in this directory.** The existing `workout-tracker/storage/photos/` is for private user workout photos, not documentation images.
