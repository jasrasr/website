<!-- File Name: README.md
Author: Jason Lamb (with help from ChatGPT)
Description: Coin Flip setup, validation, and rollback.
Revision: 1.0.1
Created Date: 2026-10-27
Modified Date: 2026-10-27
Changelog: 1.0.1 - Document viewport fitting. 1.0.0 - Document standalone coin flip.
-->
# Coin Flip

Version 1.0.1. Open `index.html` in a modern browser or serve this directory as static files. No configuration, dependencies, accounts, storage, or external requests. Target: https://jasr.me/github/coin-flip/ (hosting sync requires verification). The coin scales to the remaining viewport height so the controls stay on screen; short landscape screens use compact spacing.

Click **Flip coin** or press Space. A browser cryptographic random bit chooses heads or tails with equal probability on every flip. Outcomes are independent. A star represents heads and a crescent represents tails. The button stays disabled until the toss finishes. Reduced-motion preferences replace the spin with a quick result and suppress confetti. Results are announced to assistive technology.

Manual validation: check desktop and mobile layout, flip repeatedly, verify the visible coin matches the announced result, test Space and focused-button Enter, and enable reduced motion. Confirm rapid clicking cannot start overlapping tosses. Rollback: revert the coin-flip implementation commit; no data migration is needed.
