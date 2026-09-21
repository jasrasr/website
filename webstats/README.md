# Webstats 1.1.0

Your own multi-site page-view and click dashboard. Source lives in `jasrasr/website/webstats`; the intended dashboard URL is **https://jasr.me/github/webstats/**. Deploying this folder does not automatically instrument other pages.

## What it does

- One small JavaScript tracker for rendered HTML, PHP, WordPress, and static hosts.
- Automatic page views and delegated link/button clicks, including dynamically added elements.
- Optional named actions, page/element exclusions, and owner opt-out.
- Protected, mobile-friendly dashboard: site/date filters, daily views/clicks, top pages, clicked destinations, referrers, site totals, and recent activity.
- Eastern calendar-day reports with DST-aware date boundaries and zero-filled missing days.
- Daily JSON files: no database server or third-party analytics account.
- Approximate **tab sessions per day**, not unique people. Session storage is local to a site/browser tab; identity resets by reporting day. No cross-domain identity stitching. Storage-blocked visits count as per-page sessions.

## Framework integration

Requires the sibling `1-Framework/` folder. Entry points load `webstats/bootstrap.php`, which loads the framework's primary bootstrap. Framework additions are narrowly scoped: `JsonStore` (locked atomic JSON writes), `Response` (standard JSON envelope), and `PasswordSession` (authentication extracted from `box/lib/auth.php`, strengthened and parameterized). No source application was modified. Existing framework consumers only gain class definitions, with no automatic session or storage side effects.

Frontend assets are in `assets/css/` and `assets/js/`. Project-specific configuration, statistics, and reporting remain here. See the framework module documentation for interfaces and dependencies.

## Hosting requirements

- PHP **8.1+**, HTTPS, PHP sessions, a writable, HTTP-protected `webstats/data/` directory (or existing private external storage), and local filesystem support for `flock()` and atomic rename.
- Deploy `webstats/` and updated `1-Framework/` together under the same parent directory.
- Apache/LiteSpeed supports the included `.htaccess`. On other servers, disable directory listings and deny HTTP access to config, internal helpers, CLI scripts and tests. The complete runtime data URL prefix must be denied by the web server.
- If HTTPS terminates at a proxy, configure the host to set PHP's `HTTPS` server variable correctly; this application intentionally does not trust arbitrary forwarded headers.

## First run — no terminal setup required

Deploy the updated `webstats/` folder, including `data/.htaccess`, alongside `1-Framework/`. Open the HTTPS dashboard and sign in as **admin** using the temporary password supplied privately with this installation. Its plaintext is not stored in the repository.

When no existing configuration is present, Webstats automatically saves its initial account and random application secret in `webstats/data/settings.json`. You must choose and confirm a new password before any reports are accessible. Use 12–72 bytes; the temporary password cannot be reused. After changing it, sign in again with your new password. Old temporary-password sessions are invalidated.

The new password is stored as a password hash in ignored `data/admin.json`. Normal updates do not replace it. Webstats does not overwrite existing settings or rerun first-time account creation on every visit. If settings disappear while events or a changed administrator exist, initialization stops instead of resetting the account. Restore the files from backup.

Existing `config.local.php` or `JASR_WEBSTATS_CONFIG` installations keep their configured credentials and storage. No existing records are moved or deleted automatically. To intentionally move existing external data into the new default, stop collection, back up and copy all runtime files into `webstats/data/` (preserve its `.htaccess`), then set your existing configuration's `storage` to `__DIR__ . '/data'`. Do not discard the old configuration or secret.

The directory must be writable by PHP, and the web server must honor its access rules. Apache/LiteSpeed uses the shipped `.htaccess`. Nginx requires an equivalent deny rule for the entire data URL prefix before deployment. PHP's built-in development server does not honor `.htaccess`; do not publicly serve this application with it.

Optional advanced configuration: create `config.local.php` from the example or use `JASR_WEBSTATS_CONFIG`. The CLI `setup.php` remains available for administrators who want custom credentials/storage. Default origins cover the previously configured example domains; customize the origin list if using another domain.

## Test the dashboard with the sample pages

After deployment and initial setup, open **https://jasr.me/github/webstats-demo/**.
It loads the real tracker and provides named buttons, an excluded button, links between HTML and PHP samples, and a query-string privacy test. The PHP version is at **https://jasr.me/github/webstats-demo/sample.php**. Both use relative script URLs and share the same test controls.

1. Open the sample page to generate a page view.
2. Click each named button once and try the HTML/PHP links.
3. Use “Open Webstats in a new tab,” sign in, choose the current site and today, and refresh the report.
4. Look for the demo paths in Top pages / Recent activity and named actions in Clicked destinations & actions. The excluded button must not create an event; query strings and fragments must not appear.

These are real test events, not fabricated history. They count in totals and remain until normal retention. Loading the tracker alone does not confirm receipt: check the dashboard. Existing privacy settings and owner opt-out are respected. The sample folder intentionally sits outside the excluded dashboard path; no exclusion settings need changing.

## Keep stats safe during GitHub updates

Runtime files now live **inside the deployed checkout at `webstats/data/`**, as requested. All contents are excluded by `webstats/.gitignore` except the tracked `data/.htaccess` protection file. Stats, settings, password changes, rate limits and locks are never tracked by Git.

| File or directory | Tracked by Git? | Purpose |
|---|---|---|
| `webstats/data/.htaccess` | Yes | Denies all direct HTTP access to runtime files |
| `webstats/data/settings.json` | No | Initial account and generated secret |
| `webstats/data/admin.json` | No | Changed password hash and reset state |
| `webstats/data/events-*.json` | No | Daily statistics |
| `webstats/data/limits.json`, lock and temporary files | No | Rate limits and atomic writes |

Normal Git checkout/pull updates preserve these ignored files. **A destructive deployment can still delete them.** Preserve `webstats/data/` in hosting sync/deployment rules; do not run `git clean -fdx`, replace the entire checkout, or use `rsync --delete-excluded` against it. For FTP mirror jobs or delete-before-upload deployments, add an explicit preserve/exclude rule for runtime files. Back up this directory independently of Git. `.gitignore` controls version tracking; it is not a backup or a deployment exclusion setting.

The tracked `.htaccess` must always be deployed, even when runtime files are excluded from upload. This app allows only that protected in-repo data directory; it still rejects arbitrary public storage paths. Existing private external storage remains supported. Retention deliberately removes expired event files, but never settings or admin records.

## Install on all your pages

For **HTML or PHP output**, include this once near `</body>` (or in a shared rendered header/footer):

```html
<script defer src="https://jasr.me/github/webstats/assets/js/tracker.js"></script>
```

The tracker discovers the collector URL relative to its own script URL. It does not care whether the page was generated by PHP or served as `.html`. Static HTML files need an actual script tag or a site build/template that adds one; a PHP include or `.user.ini` alone cannot instrument static HTML.

For repository pages, `install.php` can preview and apply insertion to literal standalone `</body>` lines in HTML and PHP output. Review the dry run first; files without a safe insertion point are reported as skipped. Run against a Git working tree and review the diff before syncing it to hosting:

```bash
php webstats/install.php /path/to/website https://jasr.me/github/webstats/assets/js/tracker.js
php webstats/install.php /path/to/website https://jasr.me/github/webstats/assets/js/tracker.js --write
```

The installer excludes `webstats`, `1-Framework`, version control, dependency, test, and runtime data directories. It does not edit PHP string literals. Shared templates may insert the script more than once; the tracker prevents duplicate initialization. Pages built entirely in PHP strings or JavaScript require a manual integration. Do not track admin pages or pages that expose personal information in their URL path without reviewing them first.

For **WordPress** (`jasonlamb.me`):

1. Copy `integrations/wordpress-webstats.php` into `wp-content/mu-plugins/` (create that directory if needed).
2. In `wp-config.php`, before the stop-editing line:
   ```php
   define('JASR_WEBSTATS_TRACKER_URL', 'https://jasr.me/github/webstats/assets/js/tracker.js');
   ```
3. The plugin loads the tracker on public theme pages and skips logged-in administrators. A conventional theme must call `wp_footer()`. Test logged out or in a private window.

For **GitHub Pages**, add the same script to the HTML or shared layout and allow the exact Pages/custom-domain origin in config. Each separate site's CSP must allow the script host in `script-src` and collector host in `connect-src`.

For **YOURLS redirects**, a JavaScript tracker cannot run during a pure HTTP redirect. Those short-link hits need a separate server-side YOURLS integration; this version tracks destination pages where the script executes.

## Named clicks and exclusions

```html
<button data-analytics-event="save-availability">Save my availability</button>
<a href="/private/" data-analytics-ignore>Do not track this click</a>
<!-- Put data-analytics-ignore on <html> to exclude the entire page. -->
```

Use stable, non-personal labels of 1–80 ASCII letters, digits, dots, underscores, colons or hyphens. Labels default to element type; the tracker does **not** read button text, DOM IDs or form values. Mailto/tel destinations are omitted. A click is an interaction, not confirmation a save or purchase succeeded. To record a successful action, call `JasrWebstats.event('availability-saved')` in its success handler instead.

Single-page apps can call `JasrWebstats.pageview()` after a route change; history changes are not automatically counted. Ordinary navigation loads are automatic.

To exclude your own browser, execute `JasrWebstats.exclude()` on each tracked origin. Undo with `JasrWebstats.exclude(false)`. This takes effect for subsequent events; earlier events remain. Do Not Track and Global Privacy Control prevent collection automatically.

## Data, limits, retention and security

- No form values, cookies, raw IP addresses, user-agent strings, query strings, URL credentials or fragments are saved in event data. Referrers retain only their origin. **URL paths and explicit labels are retained**, so keep sensitive names/tokens out of them or exclude those pages. The web host may keep its own access logs independently.
- HTTP-protected daily UTC storage: `events-YYYY-MM-DD.json`, schema `{schemaVersion:1, updatedAt, records:{site:eventId: event}}`. Event fields: `id`, `occurred` (server Unix seconds), `site`, `kind`, `page`, `target`, `label`, `referrer`, `session` (site/day-scoped HMAC). Same-site retries with the same event ID are deduplicated within a UTC storage day.
- `limits.json` holds HMAC keys with counts and expiries for rate limits. Raw connection addresses are not stored. Expired limiter records are removed on subsequent updates. Dedicated `.lock` files coordinate atomic same-directory replacement; invalid JSON fails closed instead of resetting data.
- Default limit: 120 events/minute/connection address and 10,000 events/UTC day across all sites. Login limits persist independently of session cookies. With a proxy/CDN, `REMOTE_ADDR` may be shared: tune the limit or configure trusted real-IP handling at the server, not from untrusted request headers.
- Origin allowlisting and validation reduce accidental misuse; public browser analytics endpoints can still be spoofed by non-browser clients. These counts are not billing-grade, bot-proof or fraud-proof. Ad blockers, disabled JavaScript, offline requests and interrupted navigation can undercount. Failed events are not retried.
- JSON is intended for modest personal-site traffic: each accepted event rewrites one bounded daily file. Reports allow up to 90 days. For sustained high traffic, migrate the storage adapter rather than raising limits indefinitely.
- Configure a daily hosting cron job: `php /ABSOLUTE/PATH/github/webstats/prune.php`. It removes daily files older than `retention_days` (180 by default); without the cron job nothing is automatically deleted. Back up private config and storage independently of Git, with the same retention policy.
- Authentication uses password hashing, strict scoped HttpOnly/Secure cookies in production, CSRF tokens, ID rotation, and 30-minute inactivity expiry. The dashboard has no public reporting API. Password changes invalidate sessions using the previous credential version.

## Verification and rollout

```bash
node --test webstats/tests/tracker.test.cjs
node --test webstats/tests/server.test.cjs
node --test webstats/tests/first-run.test.cjs
```

The server suite requires PHP on PATH and uses temporary config/storage, a local PHP server on port 18765, and test-only credentials. GitHub Actions runs the suites plus PHP syntax checks and an Apache HTTP test that confirms direct requests for settings, credentials, events, locks and temporary files are forbidden.

Before broad deployment:
1. Install on one HTML page and one PHP page; verify a page view and click appear under the correct site.
2. Test another domain and WordPress while logged out. Check the collector network request succeeds with HTTP 200 and `success: true`.
3. Open the dashboard on a phone; check site/date filters, top pages, recent activity and zero-data dates.
4. Confirm query strings/fragments never appear in reports; try a named button and an excluded element.
5. Confirm the dashboard requires login and the first password change, direct storage URLs return 403, and HTTPS/session cookies are correct.
6. Inspect the installer diff, deploy remaining pages, and schedule retention. Roll back tracking by removing the script/plugin; stored data remains private.

See `CHANGELOG.md` and `ROADMAP.md` for scope and next steps.
