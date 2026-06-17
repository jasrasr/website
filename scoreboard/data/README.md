# Runtime Data

This folder is writable runtime storage on the web server.

Live files such as `scores.json`, `users.json`, `audit.json`, and `first-run-credentials.txt` must not be committed. Use the `*.sample.json` files as templates when duplicating the app. If `users.json` is missing, the app creates temporary `admin` and `scorer` users with password `password`; both require a password change before the user can continue. First-run credential lines are removed after each user changes their password.

Recommended deployment hardening: block direct public web access to this folder, or move runtime data outside the web root.

