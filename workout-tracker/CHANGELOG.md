<!--
File Name: CHANGELOG.md
Author: Jason Lamb (with help from ChatGPT)
Description: Cumulative release history for private Workout Tracker.
Revision: 1.0.0
Created Date: 2026-10-21
Modified Date: 2026-10-21
Changelog: 1.0.0 - Initial release history including v1.0.0 and v1.1.0.
-->
# Workout Tracker changelog

## v1.1.0 — 2026-10-21

- Built-in PHP description parser; no external AI API key required for common workouts.
- Supports multiple exercises in one sentence with a single atomic save and shared session ID.
- Defaults workout date and time to browser local values; understands yesterday, last Monday and numeric dates.
- Replaced the long required form with compact Review workout → Save workout flow, optional per-exercise correction controls and collapsed manual form.
- Separate exercise trends, mobile dashboard, private optional image uploads, and test cases for language extraction.
- Continued shared user-management authorization, CSRF protection, private JSON storage and framework JsonStore.
- Added repository project-index entry.

## v1.0.0 — 2026-10-21

- Initial private workout tracker with per-exercise chart, manual logging, optional external AI extraction and photo storage.
- Merged via PR #122; initial parser required optional configured service and recognized one exercise per extraction.
