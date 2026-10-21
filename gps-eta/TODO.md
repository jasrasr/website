<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Verified follow-up work for the GPS and ETA tracker.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Captured open on-page requests and a stale note.
-->
# GPS ETA TODO

## Documented possibilities

- [ ] Add saved minute-offset presets and trip comparison if useful. `index-secure.php` lists both as remaining non-map enhancements.
- [ ] Evaluate route-distance and traffic-aware ETA only with a chosen directions provider; the current tracker explicitly uses GPS distance without road routing (`README.md`, `index-secure.php`).

## Documentation correction

- [ ] Reconcile the old future-calculations note in `index.php` and `index-secure.php` with `CHANGELOG.md`: pause count and last GPS step already exist, while the note still lists them as future calculations.
