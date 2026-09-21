# Changelog

## 1.1.0 — 2026-09-21

- Default to protected webstats/data runtime storage inside the checkout, ignored by Git.
- Automatically initialize an admin account and generated secret on fresh installations.
- Require a password change before showing any reports; invalidate older sessions.
- Preserve saved credentials across normal source updates and retain existing external configurations.
- Add first-run/reset/persistence tests and real Apache runtime-file access tests.

## 1.0.1 — 2026-09-20

- Added HTML and PHP demo pages with named clicks, excluded controls and URL privacy checks.
- Reject runtime storage inside the source checkout, including CLI and symlink paths.
- Setup honors an external config path via JASR_WEBSTATS_CONFIG.
- Added storage persistence and sample-page regression checks.

## 1.0.0 — 2026-09-20

- Added custom cross-site page-view and link/button tracking with no StatCounter dependency.
- Added private mobile-friendly reports with Eastern date/site filters and daily activity.
- Added JSON persistence, bounded collection, secure login, privacy filtering, and retention CLI.
- Integrated the shared framework bootstrap and extracted reusable JSON, response and password/session components.
- Added WordPress integration, HTML/PHP installation helper, setup documentation and automated validation.
