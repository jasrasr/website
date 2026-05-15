# Heartbeat System — CHANGELOG
All notable changes to this project are documented here.

Format:
- NEW = new feature
- FIX = corrected behavior or bugfix
- IMPROVED = enhancement or refactor

---

## 🔥 2025-11-13

### dashboard.php (Rev 1.6)
- NEW: Online/Offline device counters (5-minute threshold)
- IMPROVED: LastSeen now displayed as "relative + EST"
- IMPROVED: Model + Logged-in User columns
- NEW: LogCount column
- NEW: Search bar with live filtering
- NEW: Table sorting (Tablesort JS)

### view.php (Rev 1.4)
- IMPROVED: Time displayed as relative + EST
- NEW: Summary at top ("Last User Seen")
- IMPROVED: Table reorganized for clarity
- NEW: Sortable table

### save_log.php (Rev 2.2)
- NEW: Auto-create `logs/` and `ratelimit/` directories
- NEW: Rate-limited POST requests per device
- NEW: Server-side timestamp added: `ServerReceived`
- NEW: Safe JSON append w/ file locking
- NEW: Secret key security validation
- NEW: Whitelisted IP logging & sanitization

### heartbeat.ps1 (Rev 1.6)
- NEW: Hybrid username detection using explorer.exe owner
- NEW: Local JSON logging to `C:\ProgramData\Heartbeat\local-logs`
- NEW: Debug logging ("Start"/"Posted")
- IMPROVED: Better model, OS, IP, and uptime reporting

### Heartbeat-Task.xml (Rev 1.3)
- NEW: Revision header
- IMPROVED: 1-minute interval for testing
- IMPROVED: Explicit execution policy & highest run level

---

## ⚙ Initial Release (2025-11-12)
- Device JSON logging (cloud)
- Initial heartbeat sender
- Base dashboard + view page


# Heartbeat System – CHANGELOG
All notable changes to this project are documented here.
Format: YYYY-MM-DD — Description

---

## 2025-11-13 — Major Dashboard + View Improvements (Revision: Dashboard 1.6 / View 1.6)
### Dashboard
- Added live search filter.
- Added “Last User Seen” summary per device.
- Restored clickable column sorting.
- Added entry count column.
- Added OS, Model, Username improvements.
- Added online/offline colored status dots.
- Improved timestamp formatting (ServerReceived EST + friendly relative).
- Added internal revision header.

### View.php
- Added revision header + history section.
- Added full sorting on all columns.
- Added entry numbering.
- Added Model and Username headers.
- Added relative timestamps and EST ServerReceived time.
- Added online/offline indicators per entry.
- Improved layout, table spacing, and fallback logic.
- Fixed missing model/username issue.
- Ensured JSON schema unchanged (no format breaks).

### Backend
- No JSON file format changes.
- save_log.php updated to store model & user from heartbeat script.
- Updated local logging and server logging parity.

---

## 2025-11-13 — Heartbeat Script Updates (Revision 3.0)
- Added hybrid logged-in user detection (Explorer.exe → fallback WMI → fallback $env).
- Added model reporting (Win32_ComputerSystem).
- Added local JSON logging to match server output.
- Improved debug.log: added Start/Posted/error lines.
- Added revision header to heartbeat.ps1.
- Fixed machine account usernames (now shows real user).

---

## 2025-11-12 — Initial Dashboard JSON Parser Fix (Revision 1.3)
- Fixed missing model/user fields.
- Improved error handling for malformed JSON.
- Added device grouping logic.

---

## 2025-11-11 — Added local ratelimit folder + server safety checks
- Added ratelimit folder creation.
- Added per-IP and per-device limiting.
- Improved error output for debugging.

---

## 2025-11-10 — Base heartbeat system created
- Added heartbeat.ps1 (rev 1.0).
- Added save_log.php.
- Added dashboard.php.
- Added view.php.
- Added logs folder structure.


# Heartbeat Client – Change Log
All notable changes to this project will be documented in this file.

This project includes:
- `heartbeat.ps1` (Windows PowerShell heartbeat client)
- `Heartbeat-Task.xml` (Scheduled Task definition)
- `save_log.php` (server-side receiver)
- `dashboard.php` & `view.php` (web dashboard)
- Local logging + rate limiting + model/user reporting

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased]
### Planned
- Dashboard entry filtering/search
- Daily/weekly device status report
- Optional email alerting for stale devices
- Local log rotation/compression
- Changelog auto-update via GitHub Actions
- Add endpoints for device groups / tagging
- Optional encryption for heartbeat payload
- MSI/EXE installer for easy distribution

---

## [2.2] – 2025-11-13
### Added
- **Hybrid session-aware username detection**, resolving real logged-in user even under SYSTEM.
- **Model detection** (`Win32_ComputerSystem.Model`) sent and displayed on dashboard.
- **Local JSON logging** to `C:\ProgramData\Heartbeat\local-logs\<device>.json`.
- **Improved uptime calculation** using accurate `TotalMinutes`.
- **Debug entries** (`Start`, `Posting`, `Posted OK`, `Failed`) in `debug.log`.

### Changed
- Heartbeat script reorganized for clarity and maintainability.
- Task now reliably attaches to logged-in user session (Hybrid mode).
- Full logging separation between local and remote.

### Fixed
- Username incorrectly sending `COMPUTERNAME$` when running under SYSTEM.
- Missing model on dashboard and view.php pages.

---

## [2.1] – 2025-11-13
### Added
- Local JSON logging placeholder structure.
- Initial integration of username improvements.

### Fixed
- Debug logging only showing `Start:` with no posted entries.

---

## [2.0] – 2025-11-13
### Added
- Enterprise-grade logged-in user detection (Explorer → WMI → TS → Registry fallback).
- Full debug logging in `C:\ProgramData\Heartbeat\debug.log`.

### Fixed
- Missing POST logging causing silent failures.

---

## [1.0] – 2025-11-12
### Added
- Initial heartbeat client design.
- Server logging using PHP JSON files.
- Basic dashboard for devices.
- Device history view (view.php).
- Rate limiting + timestamping on server side.

---

