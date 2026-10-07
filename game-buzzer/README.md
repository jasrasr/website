# Game Buzzer

Version 1.1.0 · PHP 8.1+ · No database, npm build, external service, or student accounts.

Target URL after deployment: https://jasr.me/github/game-buzzer/

Two games for six teams: 6th-grade boys/girls, 7th-grade boys/girls, and 8th-grade boys/girls. Designed for 5–10 students per team, one or more leader phones per team, and two projectors showing the same display link.

## First-time host setup through user-management

1. Deploy this folder alongside the existing user-management and 1-Framework folders. Shared identity must already be configured and available over HTTPS.
2. Open Game Buzzer and choose **Open user-management**. Sign in as the existing **jasrasr** Super Admin, completing any required password change. Return to the game tab and tap **Check sign-in**.
3. Choose **Enable Game Buzzer**. Only the active, non-demo jasrasr account with central directory Super Admin rights can perform this step. No account is created, promoted, reset, or renamed.
4. Setup registers the game-buzzer project at its actual deployment path without overwriting an existing conflicting registration. It creates storage and a private, PHP-guarded identity-enabled.php marker. Repeated setup is safe. No separate host password or manual config file is required.
5. Create a game. Shared login and CSRF protection authorize creation; the old creation-password route is disabled once shared login is enabled. Existing non-demo users with explicit Game Buzzer admin access (or existing all-project admin scope) can create games. Setup itself remains restricted to jasrasr.
6. Save the private host link. Share each team link only with its leaders; share the survey link with students. Open the projector link on both screens. Guests do not need accounts. Room host links remain bearer capabilities, including after central logout or revocation; central revocation stops NEW room creation, not already-issued room invitations.
7. Click **Enable chime** on ONE projector/host browser and **Full screen** if desired.

Existing game files and private config files are preserved. Setup does not provision any central accounts or automatically grant users higher permissions. New game registration honors the directory’s existing all-project scope. If setup cannot finish after registration (for example, storage is not writable), correct the reported issue and retry; registration and marker creation are idempotent. No public first-visitor account-claim flow exists.

The shared cookie must cover the Game Buzzer URL (the default cookie_path is /). If sign-in succeeds but the game still shows signed out, check cookie scope, hostname, HTTPS and the configured identity portal URL. If jasrasr is missing or lacks the required rights, use an existing central directory administrator to review the account; this application will not elevate it automatically.

### Optional existing/manual configuration

Existing password-configured installations continue working until an owner explicitly enables shared login. config.local.php stays private and untouched. It can still set data_dir to a PHP-writable directory outside public_html. The fallback data/ folder has an Apache/LiteSpeed deny rule; use equivalent denial on other servers. Both runtime data and local config are ignored by Git. Preserve ignored data during deployment.

## Standalone buzzer game

- Each leader opens their team invitation on their phone. Up to 12 phones may join each team; reloading retains the phone identity.
- The host chooses 10, 15, 30, or 60 seconds and starts a round. A three-second countdown allows connected clients to receive the opening time.
- Press the giant button when GO appears. The first accepted press per team counts. Additional phones on the same team cannot create extra places or overwrite its time.
- All six teams may buzz; the projector shows their order and milliseconds behind first. Presses before opening, after closing, or from an older round are rejected.
- Close the buzzer to finalize the order and automatically award **60, 50, 40, 30, 20, and 10 points** by buzz order. Every team that buzzes earns at least 10 points. The scoreboard’s −/+ controls allow deliberate corrections or custom scoring.
- Start a fresh round to clear the order while retaining scores. Majority Rules scores separately when the host reveals an answer.
- On the host desk, **Next** advances the normal sequence: close the arrival survey, start a buzzer round, end it and open the next question, reveal and score, then start the next buzzer round. Manual controls remain available under **Manual controls and overrides**.

## Arrival survey → Majority Rules (under 15 minutes)

Each new game shuffles the ten questions once. Every student and kiosk sees that same randomized order, which is also used for the host’s question reveals.

1. While students arrive, have each answer all ten A/B questions privately on the survey link. No name, grade, phone number, or email is collected. Alternatively, use the **Check-in kiosk** link on a shared tablet; the Next student button clears the form and issues a fresh ballot identity.
2. Supervise one submission per student. The normal flow locks one ballot per browser token; it is not identity verification and cannot prevent someone using another browser or clearing storage. Kiosk mode intentionally supports multiple students.
3. Close the arrival survey once everyone has answered. It cannot reopen, so later questions always use the same group’s locked answers.
4. After the host closes the arrival survey, teams discuss each question’s likely majority. After a buzzer round closes, the next unrevealed question opens automatically. A leader taps **Predict A** or **Predict B**. The first submitted prediction from either of a team’s phones locks that team’s answer. Other teams’ predictions and all vote totals remain hidden until reveal.
5. The host taps **Next** to reveal and score: matching the room’s majority earns +1. An exact tie awards +1 to each team that submitted. Teams without a prediction receive zero. Reveal is safe to repeat without adding points again.
6. Tap **Next** to start the following buzzer round and repeat. Select a question manually under **Manual controls and overrides** to interrupt the sequence. Budget about 60 seconds per question, plus instructions and the winner announcement. Both games share the scoreboard. Create separate rooms to keep scores separate.

Survey responses are stored only as anonymous arrays of A/B choices associated with random browser tokens. Public responses expose only total ballot count and the current question’s aggregate totals after reveal. Browser local storage contains capability links and random identifiers; use trusted host devices.

## Timing and latency: what this does and does not promise

The authority is the order PHP accepts requests under an exclusive per-room file lock. Each accepted press is stamped with the server clock inside that lock. This is **server acceptance order**, not a claim about which finger physically touched glass first or which packet reached the network card first. Browser timestamps are never trusted for ranking. Different Wi-Fi paths, cellular links, browser scheduling and server queues can change a close result.

Presses POST immediately on pointer-down. Polling (700 ms between completed requests, slower for hidden tabs) only refreshes screens; a press never waits for a poll. The displayed countdown uses an approximate server clock offset. It is informational, and the server enforces opening/closing. Measured request round-trip time is shown; it is not subtracted from scores or used to guess a one-way delay. A stale connection disables the phone’s controls until polling recovers. Host responses cannot be overwritten by an older in-flight state poll.

Finishers within 150 ms of first place are labeled CLOSE FINISH as a host cue. That is a convenience threshold, **not** a measured confidence interval or a promise that larger gaps are fair. Use the same reliable Wi-Fi, keep leader phones awake, avoid VPNs/cellular mixing, try a practice round with the actual phones, and replay disputed rounds. Maintain the same number of leader phones per team when possible.

This PHP version avoids assuming that shared hosting can run a persistent WebSocket service. WebSockets or a local server could reduce some overhead but cannot guarantee physical press ordering. For competition-grade precision, use dedicated hardware. This app is intended for friendly group games.

## Storage and operations

- Rooms expire after 24 hours. Expiration blocks access but does not delete files; periodically remove only expired 12-hex-character room `.php` and `.lock.php` files (never delete `identity-enabled.php`) from the configured data directory when games are no longer active. No automatic data-deleting migration runs.
- One room uses a stable lock file and atomic replace of its JSON-backed PHP data file. Shared storage must support reliable `flock` and same-directory rename. A single PHP host with local disk is the intended deployment.
- Room IDs, host capabilities, and team invitations are randomly generated. Host mutations require the host secret; phone mutations require a registered device token. Requests require JSON; no permissive CORS or cookie authentication is used.
- A room supports 300 ballots, 12 phones per team, and 3,000 host operations. It is a small-group app, not a public high-traffic service. Protect shared admin accounts (or the legacy creation password) and apply host-level request limiting for an internet-facing installation.
- Do not open multiple host controllers and operate them simultaneously. Use one host and any number of projector displays.
- No remote fonts, analytics, QR service, or CDN requests are needed. Links can be distributed in advance; creating QR codes is not built in.

## Verification

```sh
php game-buzzer/tests/game.test.php
php game-buzzer/tests/concurrency.test.php
node --check game-buzzer/app.js
node --test game-buzzer/tests/identity.test.cjs
```

Tests cover authorization, early/late/stale presses, two phones per team, hidden results, locked surveys and predictions, majority/tie scoring, repeated awards, idempotent host operations and file persistence. The concurrency test starts 12 independent PHP processes and checks that exactly six team positions survive. CI also checks PHP/JavaScript syntax. Test on the actual venue Wi-Fi before using it with the group; local tests cannot establish Hostinger/venue latency.
