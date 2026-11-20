<!--
File Name: README.md
Author: Jason Lamb (with help from ChatGPT)
Description: Fake Chatbot browser demo and archived PowerShell implementation.
Revision: 1.0.0
Created Date: 2026-11-20
Modified Date: 2026-11-20
Changelog: 1.0.0 - Document initial web proof of concept and safe legacy migration.
-->
# Fake Chatbot (web prototype)

A static, browser-only adaptation of the [original PowerShell WinForms fake chatbot](https://github.com/jasrasr/powershell/blob/main/Miscellaneous/fake-chatbot.ps1). Responses come from a local JSON list of keywords and answers. **No generative AI, API key, account, server storage, or PHP is involved.** This is a simulation, not a real chatbot service.

## Web demo

Once the branch has been reviewed, merged, and deployed to Hostinger, the conventional website URL is:

https://jasr.me/github/fake-chatbot/

GitHub source: https://github.com/jasrasr/website/tree/main/fake-chatbot/

The repository path does not prove the hosted site is already deployed.

### Teach the bot

Edit `data/prompts.json`. Each entry has an ordered list of `keywords` and one `reply`. The first matched entry wins. Matching is case-insensitive and uses complete words or phrases, so `hi` does not accidentally match `this`. You can use `{{time}}` and `{{date}}` in replies; no other expressions are evaluated.

Example:

    {"keywords": ["joke", "tell me a joke"], "reply": "Why was the computer cold? It left its Windows open."}

Place the entry inside the existing `prompts` array with a separating comma. The `_header` metadata object should remain intact. The JSON file is public, so do not add passwords or private data.

Chat messages are never transmitted to a server or saved. The current browser conversation clears when the page reloads. The page shows an error and disables sending if the JSON file fails to load.

### Run locally

Host the project folder over HTTP, for example from inside `fake-chatbot/`:

    python -m http.server 8000

Then open http://localhost:8000/. Directly opening `index.html` as `file://` may block JSON loading due to browser restrictions.

### Tests

From the `fake-chatbot` folder:

    node --test tests/engine.test.cjs

No Node packages or build system are required.

### Manual smoke tests

1. On desktop and mobile, open the page and verify the status says seven responses are loaded.
2. Send `HELLO!`, `how are you?`, `what time is it?`, and an unknown phrase.
3. Verify the displayed time uses your device timezone, and unknown text is displayed as text only.
4. Verify `/clear` and **Clear chat** remove all prior messages.
5. Temporarily rename the JSON file in a development copy, reload, and confirm the UI displays a load error and disables Send.
6. Check network requests: only static project files should load; no AI API or telemetry calls.

## Original PowerShell files — retained unchanged

- `legacy/fake-chatbot.ps1` — original revision 1.1 WinForms chatbot.
- `legacy/chatbot-prompts.txt` — its original keyword/reply data.
- `legacy/chatbot-prompts.json` — alternate JSON prompt set.

The original Windows script assumes `C:\\temp\\chatbot-prompts.txt`; adjust that path if running the legacy app locally. **Security warning:** the old script uses `Invoke-Expression` on prompt-file content. Do not run it against untrusted data. The web prototype does not use or expose that execution feature.

## Design and planned evolution

This first version deliberately does not adopt the shared PHP framework or user-management because it has no users, persistence, or API. If you later add accounts, persist chat history, or integrate a real AI provider, use the repository's `1-Framework` guidance and shared user-management instead of inventing another login system. Put keys in private host-only configuration rather than committing them.

Potential next additions: admin prompt editor, import/export tools, randomized answer variants, optional typing animation, conversation history, and optional real AI mode clearly separated from simulated mode.

**Project revision:** 1.0.0 · **Updated:** 2026-11-20
