# CVC Scoreboard Security TODO

Created: 2026-06-02

## Findings

1. High: default credentials are exposed in Git history.

   Older `auth.php` committed real default passwords:

   - `jason / cvc-admin`
   - `tate / cvc-tate`
   - `dahlia / cvc-dahlia`
   - `joe / cvc-joe`
   - `james / cvc-james`

   Current code previously created predictable defaults like `cvc-jason`, `cvc-tate`, etc. in `CVC-Youth-Scoreboard/auth.php`. If production ever used these and passwords were not changed, admin/scorer access is compromised.

2. High: runtime `data/*.json` may be web-accessible on the server.

   The app stores `users.json`, `audit.json`, and `scores.json` under web-root `data/` folders. Git ignore rules do not protect deployed web files. If the server allows direct access to `/data/users.json`, attackers could download password hashes.

3. Medium: some runtime score files are already tracked.

   These are not passwords, but they are runtime/live data and conflict with the intended server-side-only rule.

4. No current tracked `users.json`, `audit.json`, real `first-run-credentials.txt`, API keys, bearer tokens, private keys, or `.env` files were found.

   Git history was also checked for `users.json`, `audit.json`, and `first-run-credentials.txt`; those specific files did not appear as tracked files.

## Recommended Fixes

- [x] Change default user creation back to random one-time passwords, not `cvc-[username]`.
- [ ] Force-change production passwords immediately.
- [ ] Add real web-server protection for `data/` directories, or move runtime data outside web root.
- [ ] Stop tracking the committed `scores.json` files if they are meant to be runtime-only.
- [ ] Treat GitHub history exposure as permanent for old default passwords; do not rely on any password that ever appeared there.

