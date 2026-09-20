# Webstats 1.0.0

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

- PHP **8.1+**, HTTPS, PHP sessions, a writable private directory outside **every domain's public document root**, and local filesystem support for `flock()` and atomic rename.
- Deploy `webstats/` and updated `1-Framework/` together under the same parent directory.
- Apache/LiteSpeed supports the included `.htaccess`. On other servers, disable directory listings and deny HTTP access to config, internal helpers, CLI scripts and tests. Storage must remain outside public directories regardless of `.htaccess` support.
- If HTTPS terminates at a proxy, configure the host to set PHP's `HTTPS` server variable correctly; this application intentionally does not trust arbitrary forwarded headers.

## Initial setup (SSH/terminal)

1. Choose a private storage location outside all hosted sites, e.g. `/home/ACCOUNT/private/webstats-data`. The actual account path depends on your hosting account.
2. From the deployed `webstats` directory, run the following in Bash. The password is read silently, passed on stdin, and never included in command history or PHP process arguments:

   ```bash
   read -r -s -p 'Dashboard password (12–72 bytes): ' webstats_password
   printf '\n'
   printf '%s\n' "$webstats_password" | php setup.php /home/ACCOUNT/private/webstats-data YOUR_USERNAME
   unset webstats_password
   ```

3. Review the generated **private** `config.local.php`. Default origin examples cover `jasr.me`, `jasonlamb.me`, `justjason.fyi`, their `www` aliases, and `jasrasr.github.io`. Remove domains you do not use; add any other sites explicitly. Each exact HTTPS origin maps to a dashboard site label. No wildcard origins.
4. Review `excluded_paths`. The dashboard, WordPress admin, and WordPress login are excluded by default. Add other private application paths when desired.
5. Visit the HTTPS dashboard and sign in. Empty totals are expected until you install the tracker.

No SSH? Generate `config.local.php` by running setup locally with PHP, then upload it privately and edit `storage` to the server's actual private directory. Create that directory through the host's file manager. Never commit generated config. Alternatively set `JASR_WEBSTATS_CONFIG` to an absolute private config file path.

To change the password, generate a replacement hash privately using PHP's `password_hash()` and update `password_hash`; remove active `jasr_webstats` session files through your hosting tools if you need immediate revocation. There is no public registration or default password.

## Test the dashboard with the sample pages

After deployment and initial setup, open **https://jasr.me/github/webstats-demo/**.
It loads the real tracker and provides named buttons, an excluded button, links between HTML and PHP samples, and a query-string privacy test. The PHP version is at **https://jasr.me/github/webstats-demo/sample.php**. Both use relative script URLs and share the same test controls.

1. Open the sample page to generate a page view.
2. Click each named button once and try the HTML/PHP links.
3. Use “Open Webstats in a new tab,” sign in, choose the current site and today, and refresh the report.
4. Look for the demo paths in Top pages / Recent activity and named actions in Clicked destinations & actions. The excluded button must not create an event; query strings and fragments must not appear.

These are real test events, not fabricated history. They count in totals and remain until normal retention. Loading the tracker alone does not confirm receipt: check the dashboard. Existing privacy settings and owner opt-out are respected. The sample folder intentionally sits outside the excluded dashboard path; no exclusion settings need changing.

## Keep stats safe during GitHub updates

Runtime stats **must remain outside both the source/deployment directory and every public web root**. The collector, dashboard and CLI reject storage inside the source checkout, including symlinks pointing back into it. Setup also rejects such storage before writing the config.

Example layout (replace ACCOUNT with your actual hosting account):

| Purpose | Location | Part of GitHub deployment? |
|---|---|---|
| Source and dashboard | /home/ACCOUNT/domains/jasr.me/public_html/github/webstats/ | Yes |
| Sample pages | /home/ACCOUNT/domains/jasr.me/public_html/github/webstats-demo/ | Yes |
| Stats, rate limits and locks | /home/ACCOUNT/private/webstats-data/ | No |
| Optional external config | /home/ACCOUNT/private/webstats-config.php | No |

Keep the configured `storage` path the same across releases. Deploy only the source checkout; do not include the private parent directory in upload/delete/sync jobs. A code update never initializes or clears the event store. The automated regression test replaces a disposable deployment directory and verifies that externally stored events survive and remain readable.

For configuration that also survives clean deployments, set `JASR_WEBSTATS_CONFIG` to the external config path in the hosting PHP environment. Setup now honors this variable when generating config; the destination directory must already exist. Set the same variable for the retention cron job. An environment variable set only in your SSH shell does not automatically apply to web requests. Existing local config remains supported, but a deployment tool using deletion can remove ignored files: preserve `config.local.php` explicitly if you keep that option. Missing configuration fails closed; it does not erase stats.

There is no automatic migration: if you already have stats, back them up and retain their configured external path. Moving to a different private path requires copying the existing event files while collection is stopped. Retention still deliberately deletes expired events; backups are separate from GitHub updates.

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
- Private daily UTC storage: `events-YYYY-MM-DD.json`, schema `{schemaVersion:1, updatedAt, records:{site:eventId: event}}`. Event fields: `id`, `occurred` (server Unix seconds), `site`, `kind`, `page`, `target`, `label`, `referrer`, `session` (site/day-scoped HMAC). Same-site retries with the same event ID are deduplicated within a UTC storage day.
- `limits.json` holds HMAC keys with counts and expiries for rate limits. Raw connection addresses are not stored. Expired limiter records are removed on subsequent updates. Dedicated `.lock` files coordinate atomic same-directory replacement; invalid JSON fails closed instead of resetting data.
- Default limit: 120 events/minute/connection address and 10,000 events/UTC day across all sites. Login limits persist independently of session cookies. With a proxy/CDN, `REMOTE_ADDR` may be shared: tune the limit or configure trusted real-IP handling at the server, not from untrusted request headers.
- Origin allowlisting and validation reduce accidental misuse; public browser analytics endpoints can still be spoofed by non-browser clients. These counts are not billing-grade, bot-proof or fraud-proof. Ad blockers, disabled JavaScript, offline requests and interrupted navigation can undercount. Failed events are not retried.
- JSON is intended for modest personal-site traffic: each accepted event rewrites one bounded daily file. Reports allow up to 90 days. For sustained high traffic, migrate the storage adapter rather than raising limits indefinitely.
- Configure a daily hosting cron job: `php /ABSOLUTE/PATH/github/webstats/prune.php`. It removes daily files older than `retention_days` (180 by default); without the cron job nothing is automatically deleted. Back up private config and storage independently of Git, with the same retention policy.
- Authentication uses password hashing, strict scoped HttpOnly/Secure cookies in production, CSRF tokens, ID rotation, and 30-minute inactivity expiry. The dashboard has no public reporting API. Changing credentials does not automatically revoke already-active sessions.

## Verification and rollout

```bash
node --test webstats/tests/tracker.test.cjs
node --test webstats/tests/server.test.cjs
```

The server suite requires PHP on PATH and uses temporary config/storage, a local PHP server on port 18765, and test-only credentials. GitHub Actions runs both suites plus PHP syntax checks.

Before broad deployment:
1. Install on one HTML page and one PHP page; verify a page view and click appear under the correct site.
2. Test another domain and WordPress while logged out. Check the collector network request succeeds with HTTP 200 and `success: true`.
3. Open the dashboard on a phone; check site/date filters, top pages, recent activity and zero-data dates.
4. Confirm query strings/fragments never appear in reports; try a named button and an excluded element.
5. Confirm the dashboard requires login, direct storage URLs are impossible, and HTTPS/session cookies are correct.
6. Inspect the installer diff, deploy remaining pages, and schedule retention. Roll back tracking by removing the script/plugin; stored data remains private.

See `CHANGELOG.md` and `ROADMAP.md` for scope and next steps.
