<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for ai-writing-tool.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# AI Writing Tool TODO

## Code-informed follow-up

- [ ] Handle `localStorage` read/write failures and quota limits in `assets/app.js`; show a visible save failure so users do not assume a draft was saved.
- [ ] Add a manual draft export/import path for moving browser-local writing between devices, with clear overwrite confirmation.
- [ ] Add regression checks for API failure, rate limiting, and local draft recovery before changing the editor or proxy.
