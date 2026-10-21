<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the terminal demo.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a code-informed project backlog.
-->
# Magic Word TODO

## Code-informed improvements

- [ ] Add interaction checks for three failed commands, auto demo, reset, sound toggle, and dialog close. All behavior currently lives in the single `index.html` file without tests.
- [ ] Add focus management and Escape-key handling for the alert dialog. `index.html` uses `role="dialog"` and a close button, but does not currently move focus into the dialog or handle Escape.
