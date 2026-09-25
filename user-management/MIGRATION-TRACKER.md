# Shared user-management migration tracker

Last updated: 2026-09-25 (America/New_York)

**Work one project at a time.** Each project gets its own ownership/access design, adapter, tests, rollout and completion evidence. Shared infrastructure can be reused; do not apply the Finances one-to-one account model to galleries, households, groups, guests or automation without a separate design.

## Current progress

- **21 migration candidates:** 15 existing login/password systems and 6 special cases.
- **1 implementation in review:** Finances, [PR #89](https://github.com/jasrasr/website/pull/89).
- **20 candidates not implemented.** Preliminary repository inventory is complete; detailed project migrations remain outstanding.
- **0 migrations verified complete.** A PR, merge, or deployment alone does not count as completion. Live status is unverified unless explicitly recorded below.
- Shared identity foundation: [PR #88](https://github.com/jasrasr/website/pull/88), merged. This is infrastructure, not a completed application migration.

The initial inventory reflects repository code at `da291d4`, not a live-host authentication audit. Update this tracker in every migration PR and after deployment verification. Keep production usernames, credentials, tokens, mappings, and personal records out of this file.

## Status rules

`Queued` → `Designing` → `Implementing` → `In review` → `Ready for rollout` → `Verifying live` → `Complete`

Use `Blocked` with the specific dependency, or `Deferred` with the reason. Mark Complete only when all applicable checks for that project are done and the completion record names the verification date and evidence. If a check does not apply, record why instead of silently checking it off.

## Migration queue

Sequence is a suggested working order, not authorization to switch live authentication. Ticketing and Time Clock deserve an early deployment review because the checked-in versions have weak sign-in controls.

| # | Project | Existing authentication / scope | Status | PR / principal dependency |
| --- | --- | --- | --- | --- |
| 1 | [finances](../finances/) | Configured users; private budget files | **In review** | [#89](https://github.com/jasrasr/website/pull/89); explicit owner mappings, server rollout pending |
| 2 | [webstats](../webstats/) | Admin login using framework helpers | Queued | Protect reporting; preserve public collection |
| 3 | [smart-404](../smart-404/) | Shared admin password | Queued | Keep 404 handling public |
| 4 | [text](../text/) | Save and raw-JSON passwords | Queued | Decide public reading versus private viewing |
| 5 | [box](../box/) | JSON user accounts and admin session | Queued | Map users and admin permissions |
| 6 | [ticketing](../ticketing/) | Requester/Agent accounts; test-mode password bypass | Queued | Preserve email-based ticket ownership and private notes |
| 7 | [time-clock](../time-clock/) | Client-side admin PIN | Queued | Add server-side write authorization; keep display behavior |
| 8 | [mpg](../mpg/) | Admin password and device/IP trust | Queued | Decide account access versus trusted-device behavior |
| 9 | [life-group-attendance](../life-group-attendance/) | Email login, leader approval, Attendance/Super Admin | Queued | Preserve approval workflow and project-level roles |
| 10 | [khootish](../khootish/) | Shared host/admin password; player game codes | Queued | Preserve guest play and player sessions |
| 11 | [scoreboard](../scoreboard/) | Users, Admin/Scorer roles, per-scoreboard access | Queued | Preserve individual scoreboard grants |
| 12 | [debt-payoff](../debt-payoff/) | Accounts, admins, shared datasets | Queued | Preserve owners, sharing, backups and audit history |
| 13 | [daily-babe](../daily-babe/) | Baby-name accounts and private galleries | Queued | Model people separately from galleries |
| 14 | [warranty-tracker](../warranty-tracker/) | Email accounts, household invitations | Queued | Preserve private/shared warranty visibility |
| 15 | [tv-binge-board](../tv-binge-board/) | Accounts, persistent login, social sharing | Queued | Preserve libraries, connections and remembered login |
| 16 | [family-tracker](../family-tracker/) | Accounts, remembered devices, group membership | Queued | Preserve consent, ownership and location privacy |
| 17 | [availability](../availability/) | Organizer/response links and event passwords | Queued | Central organizer identity plus guest capabilities |
| 18 | [psnotify](../psnotify/) | Viewer key and publishing token | Queued | Browser login and machine publishing stay separate |
| 19 | [fs](../fs/) | Collector/maintenance tokens | Queued | Separate interactive administration from scheduled collection |
| 20 | [file-manager](../file-manager/) | Documented Basic Auth, IP rules, API keys, MFA | Queued | Verify missing deployment config; preserve MFA/uploads |
| 21 | [blog/admin](../blog/admin/) | Documented HTTP Basic Auth | Queued | Verify host protection; keep public blog public |

## Shared account setup — separate from app completion

- [x] Implement User/Admin/Super Admin roles, separate project scope, and explicit central directory management.
- [x] Implement shared profiles and authenticated owner/demo provisioning with no shipped passwords.
- [ ] Deploy reviewed changes and provision the requested owner/demo accounts on the live server.
- [ ] Assign demos only to projects with isolated demo data as each project is migrated.

See [roles and accounts](ROLES-AND-ACCOUNTS.md). These foundation tasks do not mark any application migration complete.

## Per-project task checklists

### 1. Finances — in review

- [x] Implement explicit central-ID → legacy-account linking with preview, private backup and stale/conflict rejection.
- [x] Implement opt-in shared authentication without renaming existing budget files.
- [x] Pass local preservation, isolation, permissions, CSRF and rollback tests; document rollout in [ACCOUNT-LINKING.md](ACCOUNT-LINKING.md).
- [ ] Review and merge PR #89; record CI result and merged commit.
- [ ] Back up live data/configuration, verify nested storage protection, and copy current settings to ignored `config.private.php`.
- [ ] Register project access, explicitly approve every required live mapping, and resolve dry-run blockers.
- [ ] Enable shared login; verify existing budgets, edit/read-only roles, cross-user isolation, revocation and logout with actual test accounts.
- [ ] Record live verification and rollback readiness; mark Complete.

### 2. Webstats

- [ ] Inventory current admin configuration and credential/reset behavior; define central reporting roles.
- [ ] Replace dashboard/report authentication while retaining collector access and event storage.
- [ ] Test reports, password-change gates, logout/revocation, CSRF and uninterrupted tracking.
- [ ] Merge, back up, deploy, verify existing reports/events and record completion.

### 3. Smart-404

- [ ] Define central admin access and inventory existing mappings/logs.
- [ ] Replace the shared admin-password gate on all management actions.
- [ ] Test protected edits and public 404 behavior without losing mappings or logs.
- [ ] Merge, back up, deploy, verify and record completion.

### 4. Text

- [ ] Decide who can read shared text, save changes, and view raw JSON.
- [ ] Replace the two password gates with explicit permissions; include alternate revision entry points.
- [ ] Test existing-text preservation, read/write isolation, direct endpoint access and CSRF.
- [ ] Merge, back up, deploy, verify and record completion.

### 5. Box

- [ ] Inventory legacy users and actual admin capabilities; approve identity mappings.
- [ ] Replace shared login/session helpers and gate every admin action.
- [ ] Test existing records, permissions, direct endpoints and central revocation.
- [ ] Merge, back up, deploy, verify and record completion.

### 6. Ticketing

- [ ] Verify deployed test-mode status and design Requester/Agent roles plus central-ID → local email/account mapping.
- [ ] Integrate central login and remove the password-free sign-in path when migration is enabled.
- [ ] Test ticket ownership, agent-only actions, attachments/profile associations and private-note isolation.
- [ ] Merge, back up, deploy, verify existing tickets and record completion.

### 7. Time Clock

- [ ] Verify deployed protection and identify every alert/alarm write endpoint; decide display/read access.
- [ ] Replace browser-only PIN gating with server-side project permissions and CSRF.
- [ ] Test denied unauthenticated writes, authorized edits, read-only displays and alert history using non-emergency test data.
- [ ] Merge, back up, deploy, verify without triggering real alerts and record completion.

### 8. MPG

- [ ] Inventory password-admin access, trusted/blocked devices, IP rules and vehicle associations.
- [ ] Decide whether device trust is retained as convenience or an additional restriction; implement central roles accordingly.
- [ ] Test fuel entry, edits, exports, stations, photos/OCR and device administration against existing vehicle data.
- [ ] Merge, back up, deploy, verify and record completion.

### 9. Life Group Attendance

- [ ] Map local leader IDs and preserve registration approval; define Attendance and project-admin permissions.
- [ ] Integrate identity while keeping ministry/group/student access rules local; do not promote app admins to site admins automatically.
- [ ] Test pending/disabled leaders, attendance attribution, student access and administrative endpoints.
- [ ] Merge, back up, deploy, verify history and record completion.

### 10. Khootish

- [ ] Separate host/admin identity from guest player identity and game-code access.
- [ ] Integrate central host controls without requiring player accounts or mixing session initializers.
- [ ] Test quiz creation, hosting, team joining, scoring and existing game history.
- [ ] Merge, back up, deploy, verify a test game and record completion.

### 11. Scoreboard

- [ ] Map legacy users and retain per-scoreboard grants plus Admin/Scorer behavior.
- [ ] Integrate page/API guards for all scoreboard variants and audit attribution.
- [ ] Test authorized score entry and denial on unassigned scoreboards; preserve public viewing where intended.
- [ ] Merge, back up, deploy, verify scores/history and record completion.

### 12. Debt Payoff

- [ ] Inventory users, dataset owners, sharing rules, backups and audit references.
- [ ] Link central IDs to local accounts without rewriting ownership or sharing by display name.
- [ ] Test personal/shared datasets, read/edit restrictions, backup recovery and history preservation.
- [ ] Merge, back up, deploy, verify and record completion.

### 13. Daily Babe

- [ ] Design person-to-gallery memberships, including multiple adults and multiple babies.
- [ ] Link existing gallery/baby IDs without treating the baby's name as the adult's identity.
- [ ] Test image endpoints, uploads, settings, collage/GIF access and cross-gallery isolation; preserve all photos and metadata.
- [ ] Merge, back up uploads/data, deploy, verify and record completion.

### 14. Warranty Tracker

- [ ] Map local people and retain household membership/invitations plus private versus shared warranties.
- [ ] Implement central identity with project-owned household authorization.
- [ ] Test warranty/document ownership, invitation access and household privacy.
- [ ] Merge, back up, deploy, verify and record completion.

### 15. TV Binge Board

- [ ] Inventory personal libraries, account types, social connections and remember-me behavior.
- [ ] Decide persistent-login support; integrate identity without making old remember tokens a central-auth bypass.
- [ ] Test libraries, watch progress, imports, sharing, connections and session revocation.
- [ ] Merge, back up, deploy, verify and record completion.

### 16. Family Tracker

- [ ] Inventory group owners/members, consent state, remembered devices and location records.
- [ ] Design identity linking that preserves group boundaries and consent/deletion/ownership-transfer rules.
- [ ] Test maps, trails, invites, owner actions, disabled access and device revocation across separate groups.
- [ ] Merge, back up, deploy, verify privacy and record completion.

### 17. Availability

- [ ] Define organizer account ownership and decide which guest invitation/event-password behaviors remain.
- [ ] Integrate organizers while preserving existing event IDs and private response links.
- [ ] Test organizer-only contact details, attendee edits, guest access and old-link recovery.
- [ ] Merge, back up, deploy, verify existing events/responses and record completion.

### 18. PSNotify

- [ ] Define browser/topic viewing permissions separately from PowerShell publishing credentials.
- [ ] Replace browser viewer-key login as appropriate; retain protected machine publishing.
- [ ] Test viewer isolation, notification history and existing PowerShell publishers.
- [ ] Merge, back up, deploy, verify and record completion.

### 19. FS

- [ ] Decide dashboard visibility and manual refresh/maintenance roles; inventory scheduled collectors.
- [ ] Add central browser administration without substituting browser sessions for machine tokens.
- [ ] Test reports/history, manual actions, maintenance denial and scheduled collection continuity.
- [ ] Merge, back up, deploy, verify and record completion.

### 20. File Manager

- [ ] Inspect live Basic Auth/configuration and define per-user MFA, browser roles and automated-upload requirements.
- [ ] Integrate admin identity while preserving MFA and separate API/IP restrictions; review direct file access.
- [ ] Test upload, download, create, restore/delete versions, MFA and PowerShell automation.
- [ ] Merge, back up files/config, deploy, verify and record completion.

### 21. Blog administration

- [ ] Verify actual server-side protection; inventory media-upload, viewing and management routes.
- [ ] Apply central admin permissions without affecting public posts/media or WordPress publishing credentials.
- [ ] Test unauthorized uploads/actions, existing media manifests and public rendering.
- [ ] Merge, back up, deploy, verify and record completion.

## Remaining repository inventory — decide whether authentication is wanted

These are not confirmed existing-account migrations. Do not add mandatory login to public tools by default. If selected, create a separate design/checklist and move the project into the queue; adding authentication to a public utility is different from preserving existing user accounts.

| Project(s) | Disposition / decision |
| --- | --- |
| `custom-directory`, root `Custom-HTML-Directory-Viewer.php` | Comments describe authentication, but implementation was not verified; inspect hosting protection and desired file access first. |
| `ai-writing-tool`, `how-much-time-worked`, `license-plate` | Decide whether browser use of uploads/private records/paid API calls should require accounts. Service API keys are not user login systems. |
| `computers`, `dell-service-tag`, `gps-eta`, `odometer` | Review read/write and machine-versus-browser access requirements before proposing new authentication. |
| `visual-ping-webhook-api` | Review webhook purpose and machine authentication separately; do not require a browser session for an external webhook. |
| `weather` | Decide whether administrative utilities need protection; preserve intended public weather views. |
| `human-proof` | Human-verification demo, not a user identity system; no account migration currently planned. |
| `construction-sign`, `countdown`, `jeremy`, `linktree`, `random-password-generator`, `random-url`, `wheel` | No migration selected; retain intended public/demo use unless a concrete access requirement is approved. |
| `testing` | Experimental/legacy pages; determine whether to retain, protect, or retire separately. |
| `webstats-demo` | Public tracker validation page; no user-account migration planned. |
| `1-Framework`, `user-management` | Shared infrastructure; maintain separately, not legacy applications to migrate into themselves. |
| Root standalone pages/scripts (`countdown1.html`, `impossible-click.html`, `links.html`, `phone-dialer.html`, `screensize.php`, `test.ps1`, `test1.ps1`) | No account migration selected; assess individually if an access requirement is introduced. |
| `.github` | CI/deployment tooling, not a browser account system. |

## Required completion record

For each project, append a record when rollout starts and update it through verification. Keep exact production account mappings in protected runtime storage, not in this tracker.

```text
Project:
Design/role mapping:
PR and merged commit:
CI / migration-test evidence:
Backup and storage-protection verification date:
Live mapping validation (counts/status only):
Rollout date and deployed revision:
Existing-data preservation and isolation verified:
Logout, disabled-account and permission-revocation verified:
Guest/public/machine workflows verified, or N/A reason:
Rollback procedure verified / location:
Verified by:
Completion date:
Remaining issues:
```

No project completion records yet.

Linking permission rule: add missing project access only after permission review; retain the lower existing permission and enforce a per-link ceiling even for global super admins. Finances requires an explicit viewer/member choice because its legacy login has no enforced roles. Mapping and new membership commit together. See [ACCOUNT-LINKING.md](ACCOUNT-LINKING.md).

Hosting preservation: see [HOSTING-DATA-SAFETY.md](HOSTING-DATA-SAFETY.md) and [documentation-only examples](examples/README.md). Repository safeguards and isolated tests are implemented; verifying Hostinger deployment exclusions, taking a live backup, and checking each migrated account remain pending.
