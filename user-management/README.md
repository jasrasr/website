# User Management

Shared PHP/JSON accounts for projects in this repository. PHP 8.1+, no database or Composer dependencies. Uses the framework's `JsonStore` and `PasswordSession`; the framework exposes the configured provider through `SharedIdentity::connect()`.

## Features

- One same-host login/session for integrated projects.
- Central account creation, disable/enable, temporary password reset and password change.
- Site administrators manage all registered projects; other users receive explicit per-project Viewer, Member or Admin grants.
- Server-side permission checks, CSRF tokens, secure cookies, session rotation, 30-minute idle expiry and persisted account/IP login limits.
- Password/account/access changes revoke the affected user's sessions; required password changes block project access.
- Last active administrator protection. Users are disabled instead of deleted so project ownership remains stable.
- Runtime files stay in ignored storage; normal Git updates do not overwrite accounts.

## Install on Hostinger

1. Deploy `user-management` and `1-Framework` beside the apps. Use HTTPS.
2. Copy `config.example.php` to **ignored** `config.local.php`. Defaults work at `/user-management/` on the domain root. If deploying under a prefix, configure `base_path` and `cookie_path` consistently for all consumers. Choose a unique cookie name for separate installations on the same host.
3. Storage defaults to `user-management/data/`. Apache/LiteSpeed must honor its `.htaccess`. Before adding accounts, request `/user-management/data/.htaccess` and a temporary test file under that directory and verify HTTP 403. Remove the test file. On nginx, configure a deny rule or set `data_path` to a private directory outside the web root. The configured directory must already exist and be writable only by the PHP/deployment user. Never use 0777. Disable `display_errors` in production.
4. Create the first administrator using either method:
   - **Browser:** generate a random setup key (for example `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`), put it in `config.local.php` as `setup_key`, open `/user-management/`, then enter the key and your own username/password. Remove the setup key from configuration afterward. Setup rejects all further attempts once any account exists.
   - **SSH/CLI:** store a password in a private temporary file, then run `php user-management/setup.php admin "Jason Lamb" < /private/path/password.txt`. Delete the temporary file. The script only reads stdin; do not put passwords in command-line arguments. Browser setup can stay disabled.
5. Sign in, register project IDs/paths, create users, and grant access. New users must change temporary passwords on first login.
6. Install the integration below in each adopting project. Registering a project alone does **not** protect it or replace its old authentication.

There is no shipped default account or password. Lost passwords are reset by another site administrator. Keep a second administrator and private backups; this release does not provide email recovery or emergency web bypasses.

## Connect a PHP project

At the top of a protected route in a sibling project (before output **and before any existing `session_start()`**):

```php
require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';
$auth = \Jasr\Framework\SharedIdentity::connect();
$user = $auth->requireProject('mpg', 'viewer');
```

Register `mpg` in the portal first. For a JSON endpoint:

```php
$user = $auth->requireProject('mpg', 'member', true);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$auth->validCsrf($_POST['csrf'] ?? null)) {
    \Jasr\Framework\Response::json(403, 'Invalid form token.');
}
```

Render `<input type="hidden" name="csrf" value="...">` with the HTML-escaped `$auth->csrf()` in state-changing forms. Require POST and CSRF for writes and logout. JSON bodies need their own parsing; pass their token to `validCsrf`. Use `requireProject('mpg', 'admin')` for administrative routes. `can()` checks access without redirecting; `user()` returns safe identity fields without the password hash. `portal()` gives the login/account URL.

| Role | Intended access | Gate |
| --- | --- | --- |
| Viewer | Read | `viewer` |
| Member | Read and ordinary writes | `member` |
| Project admin | Project administration | `admin` |
| Site administrator | All registered projects and account administration | Central `admin` flag |

The provider enforces role hierarchy; each project must place gates on **every** protected page, API, download and write handler. Unknown projects/roles deny access even for site admins. Object ownership remains the project's responsibility. Store `$user['id']` as the stable ownership key; map legacy owners explicitly before removing old login code.

Unauthenticated HTML requests go to the central portal; after login use the project cards to return. JSON routes return the framework envelope with 401/403. There are no arbitrary return URLs. Changing a password signs out all browsers. Logging out signs out all integrated projects **in that browser**.

## Adoption and limits

The framework is wired to this provider by default, but connection is explicit and lazy. Existing `PasswordSession` callers (including Webstats) keep working. No existing application's users or data are automatically migrated, and no production account is created by deployment. Use a staging copy to map ownership, replace the previous auth/session initialization, and gate all endpoints before moving each app over. Do not start both session systems in the same request.

This is same-host, same-PHP-session-storage integration, not OAuth/OIDC or cross-domain SSO. `jasr.me` and `justjason.fyi` have separate cookies/accounts unless a future identity protocol is implemented. Static HTML must be served through a protected PHP route if access control is needed; a JavaScript login check cannot protect a file. Shared-host apps run in one trust boundary and can access the shared cookie/session and storage: only integrate trusted server code.

## Storage / backup

`data/directory.json`: framework schemaVersion 1 envelope, records `{users: {id: user}, projects: {slug: {name,path}}}`. Users contain immutable random `id`, normalized ASCII `username`, Unicode display `name`, password `hash`, `active`, site `admin`, `mustChangePassword`, integer session `version`, project-role map and UTC creation time. Usernames are deliberately ASCII; display names are not. Passwords are 12–72 bytes to avoid bcrypt truncation.

`data/attempts.json`: expiring hash-keyed account and direct peer-IP counters; 10 account attempts / 60 IP attempts per 15 minutes. Successful login clears only its account counter. No forwarded IP headers are trusted. A reverse proxy may group users under its peer IP; configure trusted proxy handling separately before high-volume use.

Writes use stable locks and atomic replacement. Back up the data directory and private configuration securely. Never use `git clean -fdx` or deployment cleanup that deletes ignored data. On restore, also clear server-side identity sessions, especially if an older credential/version is restored. JSON is intended for small trusted-host deployments, not a high-volume public identity service.

## Validation

```sh
find user-management 1-Framework -name '*.php' -print0 | xargs -0 -n1 php -l
node --test user-management/tests/auth.test.cjs
node --test webstats/tests/server.test.cjs webstats/tests/first-run.test.cjs
```

HTTP tests create isolated deployments and credentials; cover setup, shared login, password changes, permission isolation, CSRF, revocation, admin safeguards, throttling, and storage persistence. Manually verify HTTPS cookie flags, mobile layout, storage denial on the actual hosting server, and 30-minute idle expiry before rollout.

## Link existing logins to their data

Use the [account-linking guide](ACCOUNT-LINKING.md) and **Open account linking — Finances pilot** in the administrator portal. It provides a dry-run report, explicit preview, administrator-approved one-to-one mappings and private backups. Finances can then use central login while retaining its existing local account IDs and budget files. Deployment does not enable shared login automatically. Other project adapters and self-service linking are future work.
