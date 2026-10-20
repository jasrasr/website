<!--
File Name     : README.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Source and operating notes for the illustrated, narrated Build Signal page.
Revision      : 1.3.4
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.3.4 - Simplified the Studio worksheet to single-letter speaker prefixes without line numbers.
  Rev 1.3.3 - Added the chronological, prefixed ElevenLabs voice-assignment worksheet.
  Rev 1.3.2 - Assigned the long dialogue to four named characters and drafted Jason's corralling sign-off.
  Rev 1.3.1 - Added an upload-ready, approximately five-minute four-voice script and production guide.
  Rev 1.3.0 - Documented the four-character post-credits conversation.
  Rev 1.2.1 - Documented the local generated audio asset used for Jason's sign-off.
  Rev 1.2.0 - Explained chapter voice controls and the recorded sign-off asset.
  Rev 1.0.0 - Published a GitHub-ready copy of the illustrated walkthrough for local Codex CLI refinement.
-->
# Build Signal

A third-person, narrated walkthrough of Jason Lamb's AI-assisted web workflow: Claude Code and Codex, GitHub, Hostinger sync, and his websites. The single-file page uses inline CSS and JavaScript, plus three generated illustrations. There are no build dependencies or third-party runtime scripts.

## Run locally

Open `index.html` in a current browser, or serve this directory with a static web server. Browser speech uses the English voices installed in the visitor's browser or operating system. The voice selectors in the top bar and tour player change the current chapter's voice; each chapter remembers its own choice in that browser. The walkthrough scrolls to the first section and continues through the sections as narration finishes.

The closing conversation is a four-character post-credits scene about the viewer who stayed to the end. Each character uses a stable available English browser voice and a distinct delivery style; if the device has fewer than four English voices, some voices are reused. Jason's sign-off is a local `jason-signoff.mp3` asset generated with his ElevenLabs voice. The page attempts to play it after the conversation and also offers a direct playback button. The voice service credential is not included in the site. Future audio revisions must be generated outside browser code; never put an ElevenLabs API key in the page.

For a produced, approximately five-minute version of the four-character scene, use `four-voice-dialogue-ordered-for-studio.txt` as a chronological voice-assignment worksheet in ElevenCreative Studio. Its `J:`, `L:`, `C:`, and `R:` prefixes identify the first 36 turns; the final `J:` is Jason. Remove the initials before final generation so they are not spoken. The name-free `four-voice-dialogue-upload.txt` is the clean first-36-turn alternative. `four-voice-dialogue-by-speaker.md` groups the same lines by role; `FOUR-VOICE-UPLOAD.md` gives production steps. `jason-corralling-signoff.txt` is a new fifth-voice line. The exported audio is not yet wired into the webpage; the page continues to use its shorter browser-voice conversation and earlier sign-off recording.

## Visual iteration with Codex CLI

Start Codex from this folder, install the OpenAI-curated **Build Web Apps** plugin, and use its Playwright workflow to inspect desktop and mobile layouts. Keep the page's third-person copy, section-level narration, visible voice selector, and documented distinctions between deployment and monitoring. Review screenshots and the diff before publishing changes.

## Content and accuracy

This page describes repository documentation and workflow files. It does not connect to GitHub, Hostinger, or a live webhook. The repository's GitHub Actions workflow checks two URLs and emails results; it does not transfer files. The active Hostinger webhook and file-preservation configuration must be verified in the Hostinger control panel.

## Files

- `index.html` — complete responsive page, styles, interactive signal map, and browser speech controls.
- `build-signal-workbench.webp` — illustrated AI coding workbench.
- `build-signal-github.webp` — illustrated Git history and review.
- `build-signal-hosting.webp` — illustrated hosting handoff and health check.
- `jason-signoff.mp3` — Jason's generated, spoken closing line.
- `four-voice-dialogue-upload.txt` — 36-turn, approximately five-minute script for ElevenLabs Studio import.
- `four-voice-dialogue-ordered-for-studio.txt` — 37 initial-prefixed turns in chronological order for assigning five voices.
- `four-voice-dialogue-by-speaker.md` — all 36 lines grouped under their intended named speaker.
- `jason-corralling-signoff.txt` — new closing line for Jason to record with his cloned voice.
- `FOUR-VOICE-UPLOAD.md` — voice order, import steps, and timing notes.
- `ASSETS.md` — generated illustration descriptions and alternative text.

See [CHANGELOG.md](CHANGELOG.md) for project revisions.
