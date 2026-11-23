# AI Model Timeline

Single-source timeline of notable AI model releases.

## Source of truth
`data/models.json` is the only editable model dataset. The table and SVG timeline are both rendered from it.

## Versioned graphics
Every published graphic is immutable and archived under `archive/<version>/`.

Naming:
- `ai-model-timeline-vX.Y.Z-YYYY-MM-DD.svg`
- `ai-model-timeline-vX.Y.Z-YYYY-MM-DD.png` (when exported)
- `models-vX.Y.Z-YYYY-MM-DD.json`

Never overwrite an archived graphic. A data correction or model addition requires a new project version and a new archived graphic.

## Changelog
Every graphic release must add a CHANGELOG entry containing:
- version and creation date
- graphic filename
- reason a new graphic was created
- models/statuses/dates added, corrected, or removed
- source-verification notes

## Status rules
Released, announced/pending, restricted-access, and retired are distinct statuses. UI modes, effort settings, org defaults, wrappers, and aliases are excluded unless they are distinct documented models.

## Accuracy
Prefer first-party release notes, model cards, API changelogs, and official announcements. Each record supports a source URL and verification date.
