<!--
File Name: CHANGELOG.md
Revision: 2.0.0
Description: Fake Chatbot project version history.
Author: Jason Lamb (with help from ChatGPT)
Created Date: 2026-11-20
Modified Date: 2026-11-20
Changelog: 2.0.0 - Add persistent ranked PHP joke engine and shared account integration. 1.0.0 - Initial browser demo and PowerShell archive.
-->
# Changelog

## 2.0.0 — 2026-11-20

- Replace the static browser demo with a PHP-based joke chatbot that does not call any AI model or API.
- Rank exact question, contiguous phrase, all-required-word and single-word keyword matches, preserving precise matches over broad ones.
- Seed 107 human-written categories and 143 funny answers, including potato salad versus Mr. Potato Head versus generic potato.
- Record submitted anonymous questions, chosen replies, rule match and score in private framework JsonStore storage.
- Add optional explicit account-history linking through existing User Management identities; never auto-link because a visitor signed in.
- Add secured admin rules CRUD, a best-match test panel, and a review queue of captured/unmatched questions.
- Add server-side throttling, anonymous expiry, personal history deletion and unlink controls.
- Add ignored host-only storage, HTTP deny rules and tracked folder placeholder.
- Add PHP regression tests, GitHub Actions validation and operational Hostinger instructions.
- Preserve the original PowerShell v1.1 script and prompt files under legacy/.

## 1.0.0 — 2026-11-20

- Copied the original PowerShell chatbot and its TXT/JSON prompt files without modifying the PowerShell repo.
- Created initial static browser demonstration, superseded by v2.0.0.
