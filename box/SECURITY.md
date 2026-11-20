# QR Box Inventory security

Admin routes use the shared `user-management` session and the `box` project permission. `viewer` can see their dashboard and print their labels; `member` can also change their own boxes. Every write requires POST and a shared-session CSRF token. Logout is POST with CSRF and ends the shared browser session.

New boxes use immutable central account IDs. An administrator must explicitly link each legacy owner string in the ignored `data/owner-links.json`; matching usernames do not grant ownership. The app fails closed on malformed or duplicate links. Project administrators cannot automatically access another person's boxes.

Public box pages and QR images intentionally remain readable without login so a scanned sticker works. Do not put confidential information in a box record if possession of the QR code should not reveal it.

`data/.htaccess` denies direct HTTP access to runtime JSON on Apache/LiteSpeed. Verify that denial on the live host before deployment; the PHP built-in server does not apply `.htaccess`. Back up and preserve production `boxes.json`, legacy `users.json`, QR images, and owner links during upgrades. Check that account disable and permission revocation immediately remove dashboard access.
