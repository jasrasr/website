# QR Box Inventory

The repository directory is `box/`. It serves public QR lookup pages and a protected inventory dashboard. The live URL may have a different path; register the actual deployed path in User Management.

## Shared login

The dashboard uses the `box` project in `user-management`. Register that project with the URL path where this app is deployed, then grant users `viewer` or `member` access. A viewer can open their dashboard and print labels. A member can also create, edit, delete, and regenerate QR codes for their own boxes. Project admins have the same per-owner box scope; central account roles do not grant access to other owners' boxes.

`admin/login.php` redirects to the central sign-in page. Signing out of the dashboard signs out of the shared session in that browser. The old `data/users.json` password file is no longer read by the app. Public QR lookup pages and generated QR images remain public; use codes only for information intended to be visible to anyone holding a sticker or link.

New boxes store the central immutable account ID in `owner` as `id:<central-id>`. Existing boxes keep their legacy owner strings. They are invisible to central accounts until an administrator explicitly links them. Matching usernames do not create a link.

## Link existing boxes

1. Back up the live `box/data/` directory and `box/qrcodes/` before deployment. Keep the live `boxes.json` and legacy `users.json`; a Git checkout of repository examples must not replace them.
2. Register `box` and grant the intended accounts access in User Management. Sign in once to complete any required password change.
3. Inventory the distinct `owner` values in the live `boxes.json`. Confirm each person's ownership outside the app. Find the target account's immutable `id` in the private User Management directory or admin interface.
4. Create a private `box/data/owner-links.json` from `owner-links.example.json`. Under `links`, map each central account ID to its approved legacy owner string. One legacy owner may be mapped only once. Do not commit the live mapping or account directory. The app validates the whole file and fails closed if it is malformed or duplicated.
5. Verify each linked account sees only its own existing boxes, can edit them, and cannot view another account's boxes. Then verify a new box, QR regeneration, PDF labels, logout, and account disable/revocation.

The existing boxes are not rewritten during linking. To roll back the app code, restore the code version and live data backup together; any boxes created with `id:<central-id>` ownership need an explicit owner migration for the old login system.

## Storage and hosting

`box/data/.htaccess` denies direct HTTP access to the data directory on Apache/LiteSpeed. Confirm the live server returns 403 for `boxes.json`, `users.json`, and `owner-links.json` before placing private mapping data there. The PHP built-in test server ignores `.htaccess`. On nginx, add an equivalent deny rule or keep data outside the web root. PHP must be able to write `boxes.json` and `qrcodes/`.

`box/data/boxes.json` and `box/data/users.json` are tracked historical examples. Do not allow deployment tooling to overwrite live runtime data with those files. The production data and owner links are not part of Git.

## Verification

Run `node box/tests/shared-auth.test.cjs` and PHP syntax checks before deployment. The isolated HTTP test exercises sign-in, project permissions, ownership, CSRF, old entry points, logout, and revocation using disposable data. It stubs QR rendering because the local PHP runtime may lack GD. Check actual QR generation and PDF output on the host.
