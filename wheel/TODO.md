<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Verified future work for the Random Wheel.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Recorded focused usability and accessibility improvements.
-->

# Random Wheel TODO

## Code review suggestions

- [ ] Persist the choice list separately from winner history so a reload does not reset edited entries to the four hard-coded defaults. `index.html` currently stores only the last ten winners.
- [ ] Provide a reduced-motion path for the five-second wheel spin itself; current reduced-motion CSS only disables the winner celebration.
- [ ] Add keyboard and screen-reader verification for spin status and long choice lists before changing the canvas interaction.
