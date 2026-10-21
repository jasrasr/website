<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for static redirect/QR pages.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a code-informed project backlog.
-->
# Random URL TODO

## Code-informed improvements

- [ ] Document the purpose and destination behavior of the numbered static pages before publishing or regenerating them. `0.html` through `9.html` and `create-qr-files.ps1` have no README.
- [ ] Make the generator explicit about its output directory and avoid overwriting existing numbered pages without confirmation. `create-qr-files.ps1` currently runs `Set-Content` for pages 3 through 9.
