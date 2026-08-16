# Changelog

## 1.10.0 - 2026-08-16
- Added **Edit** controls beside saved quizzes in the host quiz library.
- Existing quizzes can now be loaded into the quiz builder, including title, questions, answer choices, and correct-answer selections.
- Saving an edited quiz updates the existing quiz ID instead of creating a duplicate.
- Existing Question Bank source references are preserved while editing.

## 1.9.1 - 2026-08-16
- Changed the temporary fallback host password to `changemenow`.
- Added a subtle go-live reminder on the host login page to replace the temporary password before production use.

## 1.9.0 - 2026-08-16
- Added an **Active Games** dashboard to the host/admin console.
- Shows every launched active game with its six-digit code, phase, team count, question progress, answer count, and remaining lifetime.
- Added a dedicated host console that can reopen and manage any active game.
- Added direct shared-display links for each active game.
- Added a 24-hour maximum game lifetime.
- Forgotten games are automatically closed and archived after 24 hours during normal site activity; no cron job is required.
- Expired archives are marked with an `expired_24_hours` close reason.

## 1.8.1 - 2026-08-16
- Renamed the project folder from `/quiz-web-interactive` to `/khootish`.
- Renamed the visible application name to **Khootish** across the player, admin, and shared display screens.
- Updated project documentation and added tracked storage placeholders for Question Bank and game-history data.

## 1.1.0 - 2026-07-19
- Added reusable quiz definitions and repeatable game launches.
- Added dedicated lock files and atomic JSON replacement.
- Closed timer-expiration and invite-code allocation race windows.
- Added duplicate-answer protection inside the scoring transaction.
- Added configurable 100-player session limit.

## 1.0.0 - 2026-07-19
- Initial multiplayer quiz MVP.
