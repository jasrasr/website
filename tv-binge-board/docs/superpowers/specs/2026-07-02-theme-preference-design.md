# Theme Preference and Login Error Contrast Design

Date: 2026-07-02
Repo: tv-binge-board
Status: Approved for spec review

## Goal

Keep the existing dark blue theme as the default, add a per-user light mode setting in Settings, persist that preference per user, and carry the selected theme over to logged-out pages such as login and register on the same browser. Also improve danger-alert contrast so login errors are readable on the dark theme.

## Scope

In scope:
- Add an Appearance control in user settings
- Save `theme_preference` in each user's profile JSON
- Apply the signed-in user's preferred theme in the shared page shell
- Mirror the current theme into browser storage for logged-out pages
- Read the stored browser theme on logged-out pages
- Add CSS light-theme overrides
- Improve `.alert.danger` readability on dark theme

Out of scope:
- Additional themes beyond dark and light
- Admin-enforced global theme policy
- OS-level auto theme switching

## Recommended Approach

Use a combined server-side and browser-side approach.

1. Server-side profile preference remains the source of truth for signed-in users.
2. A small browser-storage value carries the last chosen theme onto logged-out pages.
3. Dark remains the fallback default whenever no explicit preference exists.

This avoids the weakness of browser-only storage in shared-browser cases while still satisfying the carry-over requirement after sign-out.

## Data Model

Extend the existing user profile data with:

- `theme_preference`: `dark` or `light`

Behavior:
- Missing or invalid values resolve to `dark`
- Existing users require no migration because the default is implicit

## UI Changes

### Settings Page

Add an `Appearance` section to `settings.php` with a simple two-option control:
- Dark blue
- Light

The control should be part of the existing profile settings save flow.

### Login and Register Pages

No explicit toggle is required there.

Those pages should render using the browser-stored theme if present; otherwise they fall back to dark.

## Rendering and Theme Resolution

### Signed-in pages

Theme resolution order:
1. Current user's `theme_preference`
2. Default `dark`

When a signed-in page renders, the page shell should emit the chosen theme and a small script should persist the same value into browser storage.

### Logged-out pages

Theme resolution order:
1. Browser-stored theme value
2. Default `dark`

The logged-out shell should apply the stored theme as early as possible to reduce visible theme flashing.

## CSS Plan

Keep the current dark palette as the base stylesheet.

Add light theme overrides under a root selector such as:
- `:root[data-theme="light"]`

Override the shared color tokens rather than restyling each component independently.

Also update danger alerts so they have readable contrast in dark mode:
- keep the red border
- add a subtle red-tinted background
- use a light foreground color for the alert text

## JS Plan

Add a small shared theme helper script.

Responsibilities:
- read browser storage on logged-out pages
- apply the resolved theme to the document root
- persist the theme when the server indicates the signed-in user's chosen theme

Storage key should be repo-specific and stable.

## Error Handling

- Invalid posted theme values are normalized to `dark`
- Missing profile theme values are treated as `dark`
- Missing browser storage values are treated as `dark`
- If JS is unavailable, signed-in pages still render the correct server-selected theme; logged-out pages fall back to dark

## Testing

Add focused regression checks for:
- settings page includes the theme control
- profile save flow accepts and persists `theme_preference`
- page shell exposes the active theme hook
- CSS includes light-theme token overrides
- danger alert styling includes readable dark-theme contrast updates

Verification should include PHP lint on edited PHP files and the relevant static regression tests.

## Files Expected To Change

- `settings.php`
- `includes/functions.php`
- `assets/css/app.css`
- `assets/js/app.js` or a new small theme helper script
- one or more tests under `tests/`

## Risks and Mitigations

### Risk: Theme flash on logged-out pages
Mitigation: apply the browser-stored theme before the main UI paints where practical.

### Risk: Multiple users sharing one browser
Mitigation: signed-in pages always follow the signed-in profile preference, which resets the browser-stored value to match that user.

### Risk: Inconsistent component colors in light mode
Mitigation: theme through shared CSS variables first, then add narrow component fixes only where needed.
