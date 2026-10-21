<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for password generator prototypes.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a code-informed project backlog.
-->
# Random Password Generator TODO

## Code-informed improvements

- [ ] Select and document one maintained entry point. `testing/` has multiple numbered prototypes and `working/` has another page; the folder has no root index or README.
- [ ] Remove or clearly label insecure `Math.random()` generator prototypes before publishing a canonical password generator. `testing/pw-generator.html` and `testing/pw-generator1.html` use `Math.random()` while later versions use `crypto.getRandomValues()`.
- [ ] Test required-character minimums, impossible settings, clipboard copy, and zero/maximum lengths in the selected implementation.
