<!-- File Name: README.md
Author: Jason Lamb (with help from ChatGPT)
Description: Coin Flip setup, validation, data handling, and rollback.
Revision: 1.1.0
Created Date: 2026-10-27
Modified Date: 2026-11-01
Changelog: 1.1.0 - Document automatic flips, server history, and visitor metadata. 1.0.4 - Hide inactive face after landing to avoid mobile label bleed-through. 1.0.3 - Document face and cross artwork. 1.0.2 - Document clickable coin. 1.0.1 - Document viewport fitting. 1.0.0 - Document standalone coin flip.
-->
# Coin Flip

Version 1.1.0. Open `index.html` in a PHP-enabled web host or serve this directory as static files with PHP support. Page entry point: `coin-flip/index.html`, served at https://jasr.me/github/coin-flip/. PHP 7.3+ is required for `api.php`. The web server must be able to create and write `coin-flip/data/history.jsonl`; `data/.htaccess` denies direct web access, and `data/.gitignore` keeps the runtime log out of Git.

Every page load automatically flips and records the result. Click or tap the coin, click **Flip coin**, or press Space to flip again. The coin supports Tab focus and Enter activation. A browser cryptographic random bit chooses heads or tails with equal probability on every flip. Outcomes are independent. A smiling face represents heads and a cross represents tails. The collapsible shared-history panel shows lifetime counts, percentages, and the 50 most recent results.

**Data collection:** Each saved flip includes its UTC timestamp, outcome, visitor IP address, server-received user-agent and accepted-language headers, plus browser language, platform, time zone, and screen dimensions. The IP address and browser metadata are stored privately in the JSON Lines history file and are not returned by the public history endpoint. The page displays only totals and recent outcomes.

Both flip controls stay disabled during a toss. Reduced-motion preferences replace the spin with a quick result and suppress confetti. Results are announced to assistive technology. If the history service is unavailable, the coin still flips and the page reports that the result could not be saved.

Manual validation: check desktop and mobile layout; load the page and confirm a flip is animated and added to shared history; flip repeatedly; verify the visible coin, result, counts, percentages, and ordering; test Space and focused-button Enter; enable reduced motion; confirm rapid clicking cannot start overlapping tosses; confirm `/data/history.jsonl` cannot be fetched directly. Rollback: revert the coin-flip implementation commits; no migration is needed.
