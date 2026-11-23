# Frontier AI Model Release Timeline

Live path: `/ai-model-timeline/` when deployed from `jasrasr/website`.

## Why this project exists
Earlier AI-generated infographic images introduced model-name, date, duplication and box-size errors. This project fixes that by producing both a release table and a scalable SVG graphic from one file: `data/models.json`.

## Project rules
- One record per distinct named model; unique immutable `id`, provider, model, release_date, status, access, primary source URL and date precision.
- Date precision is explicit: `YYYY-MM-DD` for day, `YYYY-MM` for month. Never invent the first day of a month.
- UI modes, default recommendations, organization settings, effort levels and pricing presets are not model releases.
- Different access tiers of the same underlying model can be annotated on one record (for example Fable/Mythos).
- A later general-availability or open-weight launch is an *access-status event*, not a duplicate model row.
- Provider catalog links are primary links but may not prove each exact model/date. They are labeled `provider_catalog`; direct release articles are labeled `model_announcement`.

## Release inventory
v1.0.0 (2026-11-23) is a 122-record initial inventory from nine providers. 23 records link directly to model announcements; 99 link to broader first-party catalogs that still warrant per-model source verification. Therefore v1.0.0 is a reproducible **baseline**, not a claim that 122 exact dates were independently corroborated.

## Source of truth / renderer
- `data/models.json`: current dataset.
- `assets/js/timeline.js`: shared pure SVG function `renderTimelineSvg()`; page view, SVG export, PNG export use the same records.
- `index.php`: read-only UI and version footer.
- `archive/manifest.json`: historical release index.
- `archive/vX.Y.Z/`: immutable release graphic and its exact matching data snapshot.

## Archive / naming
- `ai-model-timeline-vX.Y.Z-YYYY-MM-DD.svg`
- `ai-model-timeline-vX.Y.Z-YYYY-MM-DD.png` (PNG is available through browser export)
- `models-vX.Y.Z-YYYY-MM-DD.json`

The archived SVG must be generated from its snapshot and the same renderer before release. Older releases must **never** be overwritten after merge. For subsequent corrections, increment project version, create a new archive folder and record changes under `CHANGELOG.md`.

## Release requirements
Before publishing:
1. Validate JSON and reject repeated IDs / repeated provider-model combinations.
2. Check that the SVG has one model capsule per data row and that model labels and dates originate from the snapshot.
3. Confirm the on-page table and SVG are both rendered from the same dataset.
4. Verify sources and release dates, recording unresolved source gaps.
5. Record the version, date, filenames, reason, model changes and correction list in `CHANGELOG.md`.
6. Commit both the immutable SVG and JSON snapshot, then update `archive/manifest.json`.

## Safety / hosting
No database, login, writable server storage, API token, AI API or scheduled web crawler is required. The project is read-only and compatible with a basic PHP/static hosting plan. The user's existing weekly AI release check can provide candidate changes for manual review; do not automatically add unverified models to the canonical dataset.

## Publishing future releases (one command)
From the `ai-model-timeline` directory with Node.js installed:

```sh
node scripts/new-release.cjs --version 1.0.1 --reason "New publicly documented model releases" --changes "Added model X and corrected model Y date"
node scripts/validate.cjs
```

The release builder rejects an existing version, missing reason, empty dataset, invalid dates, missing source links and duplicated model IDs/names. It creates a new immutable SVG and matching JSON, updates the latest model metadata, prepends a changelog entry, and appends the manifest. **Review the source evidence and commit the generated files as one PR.** Do not run it directly on a live server, and do not retroactively modify archived releases.

Archive browsing is available on the project page. PNG is generated directly from the same SVG in the browser; it is named with the project version and graphic creation date.
