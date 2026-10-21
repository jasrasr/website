<!--
File Name: TODO.md
Author: Jason Lamb (with help from ChatGPT)
Description: Verified future work for the Weather Dashboard.
Revision: 1.0.0
Created Date: 2026-10-20
Modified Date: 2026-10-20
Changelog: 1.0.0 - Added code review suggestions for cache and location handling.
-->

# Weather Dashboard TODO

## Code review suggestions

- [ ] Add fixture-based tests for latitude/longitude authority, city/state disambiguation, ZIP resolution, and API failure behavior. These are the accuracy guarantees in `README.md`, but this folder has no automated test suite.
- [ ] Document and test behavior when the upstream weather request fails while a cached payload exists; `weather_update.php` owns both fetching and cache/history writes.
