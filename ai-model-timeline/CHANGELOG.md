# AI Model Timeline — Graphic and project changelog

## [1.1.0] — 2026-11-25 (graphic release)

**Why:** Add missing OpenAI cybersecurity-specialized model releases

**Changes:** Add GPT-5.5 Cyber, GPT-5.6 Cyber and GPT-6 Cyber as restricted access; Daybreak Blue and Red are access programs, not separate models. Retain all 122 existing records.

**Graphic:** New archive `archive/v1.1.0/ai-model-timeline-v1.1.0-2026-11-25.svg`

**Data snapshot:** `archive/v1.1.0/models-v1.1.0-2026-11-25.json`

Every new graphic must receive a unique project version, creation date, immutable archive path, and explanation.

## [1.0.3] - 2026-11-23 (project-only; graphic unchanged)
- Added on-demand download buttons for the last archived graphic in SVG and PNG formats.
- Added per-version SVG/PNG download buttons to the historical graphics section.
- Downloads use the archived SVG bytes; PNG conversion happens entirely in the browser without creating a new repository file or graphic release.
- Kept graphic v1.0.0 and its 2026-11-23 creation date and filename unchanged.
- Existing 122 model records and historical data snapshots remain untouched.

## [1.0.2] - 2026-11-23 (project-only; graphic unchanged)
- Separated `project_version` from `graphic_version` and `graphic_created`.
- Updated the page header, footer, archive link, and SVG/PNG export filenames to use the correct graphic metadata.
- Added SHA-256 comparison of the rendered SVG to the immutable archived graphic. UI-only and source-only changes do not generate a new image.
- Updated the release builder to create new archived graphics only if the rendered SVG changed; validation supports the separate versions and checks the archived snapshot.
- Added dynamic year support for future model launches, without changing the existing 2023–2026 graphic.
- **Graphic remains v1.0.0, created 2026-11-23.** No model records or historical graphics were changed.

## [1.0.1] - 2026-11-23 (UI-only)
- Added keyboard-accessible sorting on every release-table column header.
- Repeated clicks toggle ascending/descending; release dates sort chronologically, even with month-only precision.
- Added visible sort indicators and `aria-sort` state for screen readers.
- The model dataset and SVG archive remain unchanged, so **no new graphic** was created. v1.0.0 remains the canonical release-graphic version.

## [1.0.0] - 2026-11-23

**Graphic:** `archive/v1.0.0/ai-model-timeline-v1.0.0-2026-11-23.svg`

**Data snapshot:** `archive/v1.0.0/models-v1.0.0-2026-11-23.json`

**Why created:** First reproducible canonical baseline replaces earlier manually generated and inconsistent graphics. Table and SVG now derive from one dataset.

**Included:** 122 distinct named model entries across OpenAI, Anthropic, Google DeepMind, xAI, Meta, Mistral, DeepSeek, Microsoft AI / MAI and Moonshot AI / Kimi, spanning 2023–2026.

**Corrected from earlier graphics:** Removed duplicate/misdated OpenAI milestones; separated GPT-6 Sol and Luna, GPT-5.6 variants, GPT-6.2 variants; retained limited-access GPT-6 Rosalind; included recent Gemini 4 Neon, MAI Code 2 family, Kimi K2.8 Preview; represented Mistral Large 4 as one model (preview October 6, GA/open weights November 5).

**Verification caveat:** 23 model-specific first-party links; 99 records link to broader first-party catalogs and are candidates for further per-model date verification. Month-level dates remain month-level.

**Exports:** SVG archived in GitHub. PNG export available in-browser, with the same version and date filename; no separate PNG artifact committed in v1.0.0.

## [0.1.0] - 2026-11-23
Project scaffolding, source-of-truth JSON structure, renderer prototype, versioned archival conventions. No canonical graphic was published for v0.1.0.
