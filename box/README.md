<!--
File Name: README.md
Author: Jason Lamb (with help from ChatGPT)
Description: QR Box Inventory setup, shared login, storage, and verification.
Revision: 1.0.2
Created Date: 2026-11-19
Modified Date: 2026-11-20
Changelog: 1.0.2 - Document Create Box layout verification.
  1.0.1 - Document the dashboard link and verification.
  1.0.0 - Document the public lookup page login link and verification.
-->
# QR Box Inventory

The repository directory is `box/`. It serves public QR lookup pages and a protected inventory dashboard. The live URL may have a different path; register the actual deployed path in User Management.

## Shared login

The dashboard uses the `box` project in `user-management`. Register that project with the URL path where this app is deployed, then grant users `viewer` or `member` access. A viewer can open their dashboard and print labels. A member can also create, edit, delete, and regenerate QR codes for their own boxes. Project admins have the same per-owner box scope; central account roles do not grant access to other owners' boxes.

Select **Manage boxes** on the public Box Lookup page to open the protected inventory dashboard. If signed out, you will be prompted to sign in. You can also select **Log in** to start shared sign-in. `admin/login.php` redirects to the central sign-in page. Signing out of the dashboard signs out of the shared session in that browser. The old `data/users.json` password file is no longer read by the app. Public QR lookup pages and generated QR images remain public; use codes only for information intended to be visible to anyone holding a sticker or link.

New boxes store the central immutable account ID in `owner` as `id:<central-id>`. The checked-in boxes and old login were demo data, so no legacy ownership migration is needed. A valid sample lives in `examples/boxes.example.json`; runtime `data/boxes.json` starts empty and is ignored by Git.

## Rollout

1. Deploy `box/`, `user-management/`, and `1-Framework/` together. The application can be mounted at `/box/` or `/boxes/`; register its actual path under project ID `box` in User Management.
2. Grant intended accounts `viewer` or `member` access. Sign in once to complete any required password change.
3. Confirm the dashboard starts empty, create a box, and verify QR generation, PDF labels, logout, and account disable/revocation. Public lookup remains available without login.

If a separate installation has real legacy boxes, do not deploy this version over it without first designing an explicit owner migration. Matching usernames do not transfer ownership.

## Storage and hosting

`box/data/.htaccess` denies direct HTTP access to the data directory on Apache/LiteSpeed. Confirm the live server returns 403 for a temporary test JSON file before storing box records there. The PHP built-in test server ignores `.htaccess`. On nginx, add an equivalent deny rule or keep data outside the web root. PHP must be able to write `boxes.json` and `qrcodes/`.

`box/data/*.json` is ignored by Git. Keep runtime box records out of deployment cleanup and back them up after the first real box is created. The old demo `users.json` file is no longer part of the project.

## Verification

Open the public lookup page while signed out and select **Log in**. Confirm that shared sign-in opens and returns you to the Box dashboard after signing in with an authorized account. Return to the lookup page, select **Manage boxes**, and confirm that the dashboard opens directly and offers **Create Box** for members.

Run `node box/tests/shared-auth.test.cjs` and PHP syntax checks before deployment. The isolated HTTP test exercises sign-in, project permissions, ownership, CSRF, old entry points, logout, and revocation using disposable data. It stubs QR rendering because the local PHP runtime may lack GD. Check actual QR generation and PDF output on the host.

With member access, open **Create New Box** on the dashboard. On desktop and mobile, check that the name field and submit button fit, that submitting returns you to the dashboard with the box listed, and that **Back to dashboard** exits without saving.

## Changelog

- Create Box page 1.2.1 (2026-11-20) Restyled the form to match the lookup and dashboard pages.
- 1.3.2 (2026-11-20) Added a Manage boxes link to the inventory dashboard.
- 1.3.1 (2026-11-20) Added a login link to the public Box Lookup page.
