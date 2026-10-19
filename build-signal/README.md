<!--
File Name     : README.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Source and operating notes for the illustrated, narrated Build Signal page.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Published a GitHub-ready copy of the illustrated walkthrough for local Codex CLI refinement.
-->
# Build Signal

A third-person, narrated walkthrough of Jason Lamb's AI-assisted web workflow: Claude Code and Codex, GitHub, Hostinger sync, and his websites. The single-file page uses inline CSS and JavaScript, plus three generated illustrations. There are no build dependencies or third-party runtime scripts.

## Run locally

Open `index.html` in a current browser, or serve this directory with a static web server. Browser speech uses the voices installed in the visitor's browser or operating system. The voice selector is on the top bar; no ElevenLabs API key or voice ID is needed for the built-in voice option.

## Visual iteration with Codex CLI

Start Codex from this folder, install the OpenAI-curated **Build Web Apps** plugin, and use its Playwright workflow to inspect desktop and mobile layouts. Keep the page's third-person copy, section-level narration, visible voice selector, and documented distinctions between deployment and monitoring. Review screenshots and the diff before publishing changes.

## Content and accuracy

This page describes repository documentation and workflow files. It does not connect to GitHub, Hostinger, or a live webhook. The repository's GitHub Actions workflow checks two URLs and emails results; it does not transfer files. The active Hostinger webhook and file-preservation configuration must be verified in the Hostinger control panel.

## Files

- `index.html` — complete responsive page, styles, interactive signal map, and browser speech controls.
- `build-signal-workbench.webp` — illustrated AI coding workbench.
- `build-signal-github.webp` — illustrated Git history and review.
- `build-signal-hosting.webp` — illustrated hosting handoff and health check.
- `ASSETS.md` — generated illustration descriptions and alternative text.

See [CHANGELOG.md](CHANGELOG.md) for project revisions.
