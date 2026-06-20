# Runtime Data

This folder is writable runtime storage on the web server.

Live files such as `scores.json`, `users.json`, `audit.json`, and `first-run-credentials.txt` must not be committed. Git deploys only the `*.sample.json` templates. Existing live files are left unchanged.

For authentication, `users-seed.sample.json` defines the first-run usernames, roles, and scoreboard access. The application creates `users.json` from that seed only when `users.json` does not already exist. Password hashes, user IDs, and timestamps are generated at runtime. The temporary `admin` and `scorer` password is `password`, and both accounts must change it before continuing.

`users.sample.json` remains a public-safe example of the persisted live-file schema. First-run credential lines are removed after each user changes their password.

The committed `.htaccess` blocks direct public web access to this folder on Apache-compatible hosting.
