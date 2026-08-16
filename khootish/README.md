# Khootish

A reusable, Hostinger-friendly PHP/JSON multiplayer quiz engine inspired by classroom response games.

## Features

- Reusable quiz library: create a quiz once and launch it many times.
- Four choices per question with exactly one correct answer.
- Six-digit numeric invite codes and team registration.
- Team color selection; avatar support is reserved for a future release.
- Separate admin, projector/display, and mobile answer-pad screens.
- Server-authoritative synchronized 30-second timer.
- Correct answers start at 1,000 possible points and decline linearly with elapsed time.
- Wrong answers receive zero points.
- Results and leaderboard after each question.
- Host lobby with live team management.
- Completed-game history, rankings, statistics, and same-code replay.
- Reusable Question Bank searchable by question text or any answer.
- Supports at least 25 simultaneous contestants; configured maximum is 100.

## Concurrency and data integrity

- Each game and quiz uses a dedicated lock file.
- Joins, answers, scoring, and state transitions run inside exclusive locks.
- Writes use temporary files and atomic rename replacement.
- Duplicate-answer validation and score updates occur in one transaction.
- Timer expiration is finalized under the same game lock, preventing stale-state overwrites.
- Invite-code allocation and game-file creation occur under one allocation lock.
- Each active game has isolated JSON storage, reducing contention between games.

This design is appropriate for dozens of contestants on normal shared hosting. Hundreds or thousands of players should use a transactional database and Redis or another shared in-memory state service.

## Installation

1. Deploy this folder as `/khootish`.
2. Ensure PHP can write beneath `data/`.
3. Replace the default admin password in `config.php`, or define `QUIZ_ADMIN_PASSWORD` in the hosting environment.
4. Open `admin.php`, create a reusable quiz, and launch it.
5. Open the display link on the projector. Players join through `index.php`.

## Storage

- `data/quizzes` — reusable quiz definitions
- `data/questions` — reusable Question Bank entries
- `data/games` — active game sessions
- `data/history` — archived completed games and rankings
- `data/locks` — persistent lock files used for concurrency control
