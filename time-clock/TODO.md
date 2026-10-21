<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Verified future work for the Time Clock kiosk.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Consolidated documented kiosk and access-control work.
-->

# Time Clock TODO

## Documented work

- [ ] Replace the browser PIN/session-storage gate with server-side write authorization and CSRF for alert and alarm endpoints; keep the display readable as intended. Source: `user-management/MIGRATION-TRACKER.md`. `admin.html` currently gates the UI in the browser.
- [ ] Disable screen blanking in the Wayfire idle configuration and verify a full kiosk reboot. Source: `rpi/NOTES.md`.
- [ ] Implement and verify the custom Plymouth boot splash. Sources: `README.md`, `rpi/NOTES.md`.

## Code review suggestion

- [ ] Add a safe local validation path for weather, alert, and alarm polling so UI changes can be checked without touching live emergency endpoints.
