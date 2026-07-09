<!--
Project: Family GPS Tracker
File: todo.md
Revision: 1.4.2
Project Revision Reference: 1.4.2
Description: Feature backlog and improvement ideas for the PHP/JSON family tracker.
Author: Jason Lamb / ChatGPT scaffold
Created: 2026-07-06
Modified: 2026-07-09
-->

# Family GPS Tracker TODO

Current App Revision Context: **1.4.2**

This file is the feature backlog for future improvements. Checked items are implemented or materially covered in the current app.

## Completed in Rev 1.4.2

- [x] Move display-name editing into the main app layout with cleaner styling.
- [x] Add a family/group-name edit option for owners so the active group name can be changed separately from the owner display name.
- [x] Add a visible account ID or username under each member for troubleshooting duplicate names.
- [x] Add a manual **Refresh Location Labels** button for closest-city lookups.
- [x] Add a graceful fallback when closest-city lookup fails, using rounded coordinates.
- [x] Add a setting to choose member-list location format: closest city, rounded GPS, or both.
- [x] Add a visible timestamp beside the app revision so it is obvious when Hostinger has deployed the latest code.
- [x] Add a simple diagnostics panel that shows browser GPS permission state, session state, and last API response time.
- [x] Add a setup/health-check page that verifies folder write permissions and direct-access protection.
- [x] Add a support/debug page that can show sanitized JSON/runtime metadata without exposing secrets.

## Priority 1 - Practical fixes and polish

- [ ] Add a dedicated account/profile page in addition to the inline Account & Group settings card.
- [x] Move display-name editing into the main app layout with cleaner styling.
- [x] Add a family/group-name edit option for owners so the group name can be changed separately from the owner display name.
- [x] Add a visible account ID or username under each member for troubleshooting duplicate names.
- [x] Add a manual **Refresh Location Labels** button for closest-city lookups.
- [x] Add a graceful fallback when closest-city lookup fails, such as showing rounded coordinates only when needed.
- [x] Add a setting to choose member-list location format: closest city, rounded GPS, or both.
- [x] Add a visible timestamp beside the app revision so it is obvious when Hostinger has deployed the latest code.
- [x] Add a simple diagnostics panel that shows browser GPS permission state, session state, and last API response time.

## Priority 2 - Family and member management

- [ ] Add an owner dashboard for managing family members.
- [ ] Let owners deactivate or remove a member from the family.
- [ ] Let owners transfer ownership to another member.
- [ ] Add member nicknames separate from login username and display name.
- [ ] Add optional member colors or icons for map labels.
- [ ] Add relationship labels such as Dad, Mom, Child, Grandparent, Friend, or Other.
- [ ] Add a member detail page showing current status, last update, trail summary, and map links.
- [ ] Add a visible **Joined At** date for each family member.
- [ ] Add a warning if two members have the same display name.

## Priority 3 - Location and map features

- [ ] Add a map mode toggle: embedded map, static fallback, and external map links.
- [ ] Add a button to center the map on the signed-in user.
- [ ] Add a button to center the map on each family member.
- [ ] Show all live family members on the mobile fallback map when possible.
- [ ] Add a list of stale members separate from live members.
- [ ] Add a **last known location** panel for each member.
- [ ] Add a trail-history preview on the main page.
- [ ] Add configurable trail retention, such as 24 hours, 7 days, or manual cleanup.
- [ ] Add an owner-only option to clear old trails for the whole family.
- [ ] Add optional geofence zones, such as Home, School, Work, Church, or Grandma's House.
- [ ] Add arrival/departure notices for geofence zones.

## Priority 4 - Notifications and alerts

- [ ] Add notices when a member's location becomes stale.
- [ ] Add notices when a member starts sharing again after being stale.
- [ ] Add notices when the invite code is regenerated.
- [ ] Add notices when a display name changes.
- [ ] Add optional email alerts for owner-only administrative events.
- [ ] Add browser notification support for supported devices.
- [ ] Add a daily or weekly summary of member activity.
- [ ] Add a muted-notices setting per user.

## Priority 5 - Authentication and security

- [ ] Add login throttling by username and IP hash.
- [ ] Add a password-change form.
- [ ] Add a password-reset flow.
- [ ] Add optional TOTP/MFA for owner accounts.
- [ ] Add per-device persistent-login management so users can revoke remembered devices.
- [ ] Add a force logout for all sessions on password change.
- [ ] Add stronger audit logging for account changes, invite changes, and member removals.
- [ ] Move `data/` outside the public web root for production.
- [x] Add a setup/health-check page that verifies folder write permissions and `.htaccess` protection.
- [ ] Add Content Security Policy headers after CDN and iframe behavior is tested.

## Priority 6 - Data management and privacy

- [ ] Add export-my-data for a signed-in user.
- [ ] Add delete-my-account with confirmation.
- [ ] Add owner-controlled export for family data.
- [ ] Add data-retention settings for location history.
- [ ] Add a cleanup task for expired persistent-login records.
- [ ] Add a cleanup task for old audit logs.
- [ ] Add privacy text explaining what is stored and what is not stored.
- [ ] Add a consent review screen users must accept after major privacy-related changes.
- [ ] Add a clear indication that browser background tracking is not guaranteed.

## Priority 7 - Progressive Web App improvements

- [ ] Add a web app manifest.
- [ ] Add home-screen icons.
- [ ] Add install instructions for iPhone and Android.
- [ ] Add a service worker for app shell caching.
- [ ] Add an offline status banner.
- [ ] Add a queued-location update attempt when the connection comes back online.
- [ ] Add a Wake Lock experiment where supported, with clear battery warnings.

## Priority 8 - UI cleanup

- [ ] Consolidate JavaScript files so profile, badge, and location-label features are not split awkwardly.
- [ ] Finish moving all injected CSS into `assets/css/style.css`.
- [ ] Reduce card height on mobile for the account, invite, notices, and member sections.
- [ ] Add a compact mode for small screens.
- [ ] Add a high-contrast mode.
- [ ] Add a light mode option.
- [ ] Add loading skeletons for members and map.
- [ ] Add clearer success/error banners instead of using only the sticky status card.

## Priority 9 - Admin/platform features

- [ ] Add a first-run setup page instead of relying only on Create Family.
- [ ] Add a platform-admin role separate from family owner.
- [ ] Add a platform admin dashboard for viewing families and account counts.
- [ ] Add owner impersonation prevention and admin audit rules if platform admin is added.
- [x] Add a support/debug page that can show sanitized JSON metadata without exposing secrets.

## Priority 10 - Longer-term ideas

- [ ] Build a small native wrapper or companion app if reliable background location is required.
- [ ] Add push notifications through a proper provider.
- [ ] Add trip mode with ETA sharing.
- [ ] Add battery-level sharing if a native app is ever added.
- [ ] Add emergency contact cards per member.
- [ ] Add check-in buttons such as **I'm OK**, **Need Help**, or **On My Way**.
- [ ] Add temporary sharing links with expiration.
- [x] Add family groups or subgroups for large families.
- [ ] Add import/export compatibility with other family-location tools if feasible.

## Implementation notes

- Keep revision numbers in the 1.x+ pattern; do not use 0.x.x versions.
- Any new live-data directory must include a `.placeholder` file.
- Any new PHP, JS, CSS, or markdown file should include a file header with a revision number.
- Avoid hardcoded default admin credentials.
- Prefer server-side storage for persistent user-visible notices, and local browser storage only for per-device UI preferences.
- Treat iPhone Safari map behavior as fragile; keep the mobile fallback path working even if desktop Leaflet remains enabled.
