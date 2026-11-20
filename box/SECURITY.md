# QR Box Inventory security

Admin routes use the shared `user-management` session and the `box` project permission. `viewer` can see their dashboard and print their labels; `member` can also change their own boxes. Every write requires POST and a shared-session CSRF token. Logout is POST with CSRF and ends the shared browser session.

Boxes use immutable central account IDs. The old checked-in boxes and user accounts were demo data, so no legacy mapping is performed. Project administrators cannot automatically access another person's boxes.

Public box pages and QR images intentionally remain readable without login so a scanned sticker works. Do not put confidential information in a box record if possession of the QR code should not reveal it.

`data/.htaccess` denies direct HTTP access to runtime JSON on Apache/LiteSpeed. Verify that denial on the live host before saving real boxes; the PHP built-in server does not apply `.htaccess`. Back up production `boxes.json` and QR images during upgrades. Check that account disable and permission revocation immediately remove dashboard access.
