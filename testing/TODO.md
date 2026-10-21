<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Verified future work for the testing redirect project.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Recorded code review suggestions for the CVC redirect.
-->

# Testing TODO

## Code review suggestions

- [ ] Decide whether `CVC-Youth-Scoreboard/index.php` still needs redirect-hit logging; if it does, move the log outside the public web root or deny direct HTTP access and set a retention policy. The current script appends request metadata to a file beside the public redirect.
- [ ] Document the redirect's purpose and retirement criteria; this folder currently has no project-level README, and the child README contains temporary notes.
