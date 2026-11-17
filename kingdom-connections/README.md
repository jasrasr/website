# Kingdom Connections

Revision 1.0.0 · November 17, 2026. Working title for a Christian business directory, built on `1-Framework` with PHP 8.1+ and JSON storage. No database, build process, external API, or new account store required.

## Included

- Public search by business name, description, location and skill, with skill filters.
- A company can have multiple skills and appears under each relevant tag. Search and skill filters combine.
- Shareable profiles at `?id=LISTING_ID`, with About, Christian testimony, employee name/photo, company logo, address/service area, phone, email and website.
- Images use administrator-supplied public HTTP(S) URLs; binary image uploads are not included.
- Average 1–5-star ratings, counts, and individual feedback. Unrated businesses display “Not yet rated”. Ratings start empty; no fictional companies or fabricated reviews are seeded.
- Shared-user-management project admins can add/edit listings and record feedback. Feedback is publicly labeled **admin-recorded**, with no claim of completed-service verification. Public visitors cannot submit ratings in this release.
- Responsive layouts, accessible labeled forms, server-side validation, CSRF protection, locked atomic writes, and private ignored runtime files.

## Hostinger setup

1. Deploy alongside `1-Framework` and `user-management` under the same PHP host. Open `/kingdom-connections/` for the public directory.
2. Register project ID **kingdom-connections**, name **Kingdom Connections**, path matching the actual installation, e.g. **/github/kingdom-connections/** (or **/kingdom-connections/** when installed at the domain root) in central user management. Grant project **admin** access to the people who should manage listings. No accounts, grants or production data are modified by this change. Admin login returns through the registered project path.
3. Defaults require no private config. To relocate storage, manually copy `config.local.example.php` to ignored `config.local.php`, and set `storage_path` to an existing PHP-writable private directory. Do not commit the real config.
4. Default `storage/` is retained by `.placeholder`; first save creates ignored `directory.json` and `directory.json.lock`. Apache/LiteSpeed must honor the deny rule. Before adding real records, request a temporary file under `storage/` and confirm HTTP 403, then remove it. For nginx configure equivalent denial, or use private storage outside web root. Verify HTTPS, PHP write permissions, and disable production display_errors.
5. Open `admin.php` and add the first business. Contact details, employee pictures and testimonies entered here are public. Normal deployment must preserve ignored settings and runtime data; never run cleanup that deletes them. Back up storage independently.

## Storage

Uses framework `JsonStore`, schemaVersion 1, with `records` keyed by random immutable company ID. Each listing includes skills, company fields, UTC timestamps, last editor's stable central ID, and a ratings array. Rating records contain random ID, integer stars 1–5, comment, UTC date, source `admin-recorded`, and recorder's central ID. Internal IDs for administrators are never rendered publicly. Updates preserve ratings under the same storage lock. No deletion/reset action is shipped.

## Validation

Run `php kingdom-connections/tests/directory.test.php` and PHP syntax checks. Manual HTTP checks:

1. Empty public directory renders without authentication; public visitor cannot access admin actions.
2. Register project and grant admin access; viewer/member roles cannot edit. Missing CSRF returns 403 and no write. Valid saves redirect (303), so refreshing does not resubmit.
3. Add a test company with plumbing and electrical skills. Both filters find it; combine a search with a skill. Unknown profile IDs return 404. Clear/reset restores all results.
4. Edit contact fields/testimony; existing ratings survive. Add 1 and 5 star feedback: average becomes 3.0 with two ratings. Check public feedback source labels.
5. Reject ratings outside 1–5, malformed emails, script/data URLs and oversized fields. HTML in names/testimonies renders as text. Verify phone-width layout and keyboard navigation.
6. Confirm storage/config HTTP denial on Hostinger and runtime preservation on redeploy. Remove test data using an isolated deployment rather than resetting production files.

## Next phase

Business/customer account ownership, moderated business submissions, customer-to-business service requests, service completion records and invitations to review completed jobs. These are planned; this release does not implement messaging, booking, public review submission, or verification of a business's faith statement.
