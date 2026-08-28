# Admin User Import and Guided Matching Design

Date: 2026-07-02
Repo: tv-binge-board
Status: Approved for spec review

## Goal

Add an admin import workflow for a selected user, improve import template quality, and make unresolved imports easier to fix before anything is written. The workflow should support CSV and JSON, skip duplicates by default, optionally overwrite existing items, and help resolve weak title matches when `tmdb_id` is missing.

## Scope

In scope:
- Add per-user import entry point from `admin/users.php`
- Reuse staged import review for both normal users and admin-targeted imports
- Add downloadable sample/template CSV
- Include `type` and optional `tmdb_id` in the template
- Include a JAG sample row using TMDB ID `4376`
- Add automatic TMDB suggestion when `tmdb_id` is missing
- Add inline match correction for unresolved rows on the review page
- Default duplicate handling to skip existing items
- Add optional overwrite mode that updates only fields supplied by the uploaded row

Out of scope:
- Native `.xlsx` upload support in this change
- LLM-based parsing or fuzzy AI service integration
- Fully automatic merge conflict resolution across notes and watch history beyond field-presence rules

## Current Behavior and Root Cause

Current import behavior is limited:
- Normal users can import their own CSV or JSON from `import.php`
- Admin accounts are explicitly blocked from import
- Imported rows are normalized directly without TMDB-assisted matching
- There is no sample import file to show expected column names
- Missing `type` data makes title-only imports weak and inconsistent

That is why spreadsheet imports with only `name`, `season`, and `episode` do not resolve cleanly.

## Recommended Approach

Use a hybrid import pipeline:

1. Parse uploaded CSV or JSON into structured rows.
2. If `tmdb_id` is present, trust it and build the item from TMDB details.
3. If `tmdb_id` is absent, use structured TMDB search with `title + type + year` when available.
4. Auto-attach a suggested match when the result is strong enough.
5. Mark low-confidence rows as `Needs match` and let admin fix them inline on the review page.
6. Confirm import only after review.

This gives users a practical helper without depending on an external AI model.

## Data Model

Extend staged review items with import metadata such as:
- `source_row`
- `duplicate`
- `match_status` (`exact`, `suggested`, `needs_match`, `manual`, `unmatched`)
- `match_query`
- `matched_tmdb_id`
- `matched_type`
- `match_candidates` (trimmed list for inline review)
- `supplied_fields` (fields actually present in the uploaded row)

The confirmed library item should stay in the existing library shape. Match-review metadata belongs only in the staged review file.

## Template File

Add a downloadable sample CSV from the import screen.

Columns:
- `type`
- `title`
- `year`
- `tmdb_id`
- `status`
- `rating`
- `season`
- `episode`
- `notes`

Rules:
- `type` should be `tv` or `movie`
- `tmdb_id` is optional but preferred when known
- `season` and `episode` are optional and relevant mostly for TV

Include a sample row for JAG:
- `type`: `tv`
- `title`: `JAG`
- `tmdb_id`: `4376`

## Admin Import Entry Point

Add an `Import` action next to each normal user in `admin/users.php`.

The admin entry point should open the same import screen but target the selected user explicitly, for example with a user query parameter or hidden form target.

Admin should be able to:
- upload for review
- review staged rows
- confirm import into that selected user's library

## Review Page UX

Use inline review on a single page.

For each row show:
- parsed title/type/year
- duplicate status
- current suggested match
- match status badge
- quick TMDB result summary if matched

For unresolved rows provide inline controls:
- search input seeded from title
- search button
- candidate result list
- select/apply match button
- clear match button

This is better than a per-row screen because imports are usually batch work.

## Duplicate Handling

Default behavior:
- skip duplicates

Optional behavior:
- `Overwrite matching items`

Overwrite rule:
- update only fields explicitly supplied in the uploaded row
- preserve fields not present in the import
- preserve notes/watch history unless the import explicitly supplies those fields
- if TMDB match is selected, refresh matched metadata but do not erase user-owned fields not present in the import payload

## Matching Strategy

Priority order:
1. `tmdb_id` from file
2. exact normalized `type + title`
3. TMDB search using `title + type + year`
4. manual inline correction

Confidence rule:
- auto-accept only when type aligns and the top TMDB result is clearly dominant
- otherwise mark `Needs match`

The implementation should stay deterministic and explainable, not opaque.

## Error Handling

- Invalid file type: reject with clear error
- Missing required title: stage row as invalid and skip confirmation until fixed or excluded
- Missing `type`: stage row as `Needs match` and encourage correction
- Bad `tmdb_id`: stage row as unresolved instead of silently failing
- TMDB outage: keep staging possible, but unresolved matching actions should show a clear error

## Testing

Add focused regression checks for:
- admin users page exposes an import entry point per non-admin user
- import page allows admin-targeted import for a selected user
- sample CSV download exists and includes `type`, `tmdb_id`, and JAG sample row
- staged import review supports duplicate-skip default and overwrite option
- staged review includes unresolved-match affordances
- import normalization tracks supplied fields for partial overwrite behavior

Verification should include PHP lint on edited PHP files and focused static tests.

## Files Expected To Change

- `admin/users.php`
- `import.php`
- `includes/functions.php`
- `includes/tmdb.php` or helper wiring that supports row suggestion
- possibly one small new endpoint for inline row match search/apply
- tests under `tests/`
- sample/template download artifact or generator endpoint

## Risks and Mitigations

### Risk: false-positive TMDB suggestions
Mitigation: only auto-accept strong matches; otherwise require inline review.

### Risk: overwrite surprises
Mitigation: overwrite is opt-in and only updates fields actually supplied by the import row.

### Risk: admin imports into wrong user
Mitigation: keep selected target user visible throughout upload, review, and confirm steps.
