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
- `assets/js/render-core.js`: shared pure SVG renderer. `assets/js/timeline.js`: table and browser exports; all use the same models.
- `index.php`: read-only UI and version footer.
- `archive/manifest.json`: historical graphic release index, with separate `project_version` and `latest_graphic_version`.
- `archive/vX.Y.Z/`: immutable release graphic and its exact matching data snapshot.

## Archive / naming
- `ai-model-timeline-vX.Y.Z-YYYY-MM-DD.svg`
- `ai-model-timeline-vX.Y.Z-YYYY-MM-DD.png` (PNG is available through browser export)
- `models-vX.Y.Z-YYYY-MM-DD.json`

The archived SVG must be generated from its snapshot and the same renderer before release. Older releases must **never** be overwritten after merge. UI-only versions advance `project_version` without changing the latest `graphic_version`. Only when the SHA-256 of rendered SVG changes does the release command create a new archived graphic. Model source metadata updates that do not change the timeline are also project-only. Every project release updates `CHANGELOG.md`.

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

## Two independent versions

- `project_version`: increments for site UI, functionality, metadata, or model updates.
- `graphic_version` and `graphic_created`: identify the immutable historical graphic currently displayed and exported.
- `models_updated`: date on which model metadata was last changed; it does not rename the graphic.
- A v1.0.2 website can legitimately display and download v1.0.0 of the graphic.
- The newest archived graphic comes from `archive/manifest.json`, not a derived project-version path.
- The renderer now automatically extends the timeline when future years are added.

## Publishing future releases (one command)

Run from `ai-model-timeline/` with Node.js:

```sh
node scripts/new-release.cjs --version 1.0.3 --reason "Improve mobile table layout" --changes "Better header spacing"
node scripts/validate.cjs
```

The release command computes SHA-256 of the candidate SVG rendered from the current model data using the prior graphic's embedded version/date:

1. **Same SVG hash:** update the project version and changelog only; retain the existing graphic, date, filename, and archive.
2. **Different SVG hash:** make an immutable new SVG and matching JSON snapshot named with the new project version and creation date; update `graphic_version`, `graphic_created`, the manifest, and the changelog.
3. **Manual edits to JSON source URLs / verification metadata:** allowed as project changes; they only trigger a graphic if the displayed timeline actually changes.
4. **Unreleased visible model or SVG style changes:** CI rejects the mismatch until the release command archives a new graphic.

Do not edit the `archive/` contents of an existing version. Before committing, run `node scripts/validate.cjs` and check for `PASS`. The GitHub Actions integrity workflow runs that check on pull requests.

Exports are named for the **graphic's** version/date (not the current project version). The project can evolve independently while previously created graphics remain historically accurate.

## Download archived graphics on demand

The Download latest archived SVG / PNG buttons and the Historical graphics version cards download the existing SVG file under `archive/vX.Y.Z/`. PNG is converted locally in the browser from that exact archived SVG. Neither action creates a new image in the repository, modifies data, increments a graphic version, or changes the archived graphic creation date. Downloads are always named with the selected **graphic version and original creation date**, even if the website's project version has moved ahead.
