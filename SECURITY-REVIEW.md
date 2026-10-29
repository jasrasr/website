# Security review — 2026-10-29

## Summary

This is a static, risk-focused review of the checked-out website repository, which contains multiple independent PHP applications. The clearest issue is that the timeclock app reads and changes employee shift records without an application access check. The file manager also accepts arbitrary file extensions into a web directory once its IP and API-key checks pass. Live Apache configuration and deployment contents were not available, so server reachability and the local environment-file exposure remain unverified. Confirmed or conditional findings: **High 2, Medium 3, Low 0, Informational 0**.

## Findings

### 1. Employee shift records have no application authorization

- **Severity / confidence:** High / High for the code path; public reachability depends on deployment.
- **Location:** `how-much-time-worked/view.php:15-16`, `save_entry.php:14-20,54-69`, `upload.php:15-21,39-44`, `config.php:32-34`, `.htaccess:1-4`.
- **Impact:** A visitor who can reach the app can read all employees' hours and names, submit fabricated shifts, and upload clock-out slips. The `.htaccess` only denies `secrets.php`; it does not guard these pages or the `data` and `uploads` directories.
- **Minimal fix:** Require a server-side authenticated session or existing central-project permission at the start of every read/write endpoint, and keep payroll data outside the document root. For example:

```php
require_once __DIR__ . '/../user-management/bootstrap.php';
$user = $auth->requireProject('how-much-time-worked', 'member');
```

The exact bootstrap API must be checked before applying this example. Add a CSRF token check to authenticated POST routes.

### 2. File manager accepts executable uploads in a web directory

- **Severity / confidence:** High / High for the behavior; exploitability depends on server execution rules and possession of the API key plus an allowed IP.
- **Location:** `file-manager/api/upload.php:63-75,107-115,123-150`.
- **Impact:** The endpoint checks IP and API key, but applies no extension or MIME allowlist before saving the client filename under `file-manager/uploads/` (or another configured directory). If a valid client is compromised and PHP execution is enabled there, uploading a `.php` file can lead to server-side code execution.
- **Minimal fix:** Reject executable extensions, validate an explicit list of permitted file types, generate server-side filenames, and deny script execution in every upload directory. For example:

```php
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
    http_response_code(415);
    exit;
}
$filename = bin2hex(random_bytes(16)) . '.' . $ext;
$targetFile = $targetPath . $filename;
```

### 3. Local `.env` may be retrievable when deployed under the web root

- **Severity / confidence:** Medium / Medium; live deployment and parent Apache rules were not reviewed.
- **Location:** `mpg/login.php:12-24`; local `mpg/.env` exists, is ignored by Git, and `mpg/.htaccess` is not tracked.
- **Impact:** The check at line 12 only runs while serving `login.php`; it cannot block a direct HTTP request for `/.env`. The local file contains an API credential and a password hash. If copied into a publicly served directory without a server deny rule, those values can be downloaded. The credential value is intentionally omitted from this report.
- **Minimal fix:** Move secrets outside the document root and add a server-level deny rule for dotfiles. Example Apache rule:

```apache
<FilesMatch "^\.env$">
    Require all denied
</FilesMatch>
```

Verify the deployed URL returns 403 or 404. Rotate any key if the file was accessible.

### 4. User-controlled redirect after authenticated actions

- **Severity / confidence:** Medium / High.
- **Location:** `tv-binge-board/api/delete-media.php:31` (also `api/refresh-poster.php:37`, `api/refresh-metadata.php:64`, and other action handlers).
- **Impact:** An authenticated user can submit an arbitrary `redirect` form value and the server places it in `Location`. A crafted form or link sequence can send a user to an attacker-controlled site after a legitimate action. The CSRF token prevents an unrelated site from performing the action silently, but does not constrain a redirect value supplied by an authenticated client.
- **Minimal fix:** Accept only a fixed local return path or a validated application-relative path:

```php
$redirect = (string)($_POST['redirect'] ?? '../watchlist.php');
if (!preg_match('~^\.\./[a-z0-9/_?=&%-]+$~i', $redirect)) {
    $redirect = '../watchlist.php';
}
header('Location: ' . $redirect, true, 303);
```

Centralize the check for all action handlers and reject backslashes and URL-encoded authority tricks as well.

### 5. Debug errors are enabled in checked-in MPG pages

- **Severity / confidence:** Medium / High for configuration, Medium for exposure impact.
- **Location:** `mpg/manage_stations.php:9`, `mpg/view_stats.php:8`, `mpg/view_map.php:9`.
- **Impact:** These pages call `ini_set('display_errors', 1)` at runtime. Exceptions or warnings may reveal filesystem paths or internal values to a requester who can reach the pages. The local `mpg/php.ini` also enables display errors but is untracked, so its deployment status is unknown.
- **Minimal fix:** Remove the runtime display setting and log errors server-side:

```php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
```

## Things that look good

- `mpg/login.php:23-39` loads the admin hash from an ignored file and checks it with `password_verify()`; the hash is not hardcoded in that login route.
- `tv-binge-board/api/delete-media.php:15-18` requires login, verifies a CSRF token, and limits non-admin users to their own library.
- `how-much-time-worked/view.php:36-44` escapes stored values before inserting them into HTML.
- `how-much-time-worked/upload.php:40,49` generates a random upload basename and shell-quotes the OCR filename.

## Not reviewed / needs more information

- No live server request, deployment manifest, virtual-host configuration, or parent `.htaccess` was available. Actual access to `.env`, data directories, upload execution, HTTPS enforcement, headers, and directory listing must be verified on the host.
- `file-manager/config.php` and optional `file-manager/geoip/vendor/autoload.php` are referenced but absent from tracked source; their contents and the allowed directories were not reviewed.
- The local `mpg/.env`, `mpg/php.ini`, and `mpg/phpinfo.php` are untracked and may differ from production. The local `phpinfo.php` would expose runtime configuration if deployed and reachable.
- The repository has 434 PHP files across many apps. This pass used inventory and targeted source review; it is not a complete line-by-line audit of every app, JS file, dependency, SQL or deployment script. No Composer manifest or lockfile appeared in the inventory; package versions and PHP runtime version were not established.
- The Hindsight memory service was unavailable during this review. The root `.ai` project files were read.

## Top 5 fixes to do first

1. Put application authorization on every timeclock read and write route; then protect or relocate its stored slips and logs.
2. Restrict file-manager upload types and deny PHP/script execution in upload directories.
3. Verify the deployed `mpg/.env` is inaccessible; move secrets outside the document root and rotate exposed credentials if necessary.
4. Validate local redirect targets in TV Binge Board action handlers.
5. Turn off displayed PHP errors in MPG and verify production error handling.

## Suggested verification tools

- `composer audit` if a Composer lockfile is added or exists on the deployed host: checks advisories for installed PHP packages. No package CVE is asserted here.
- PHPStan or Psalm: catches type/data-flow mistakes, especially where request parameters enter file paths and API handlers.
- Semgrep PHP security rules: finds similar upload, redirect, injection, and missing output-escaping patterns in the other apps.
- OWASP ZAP against a disposable staging deployment: verifies actual auth barriers, redirects, sensitive static files, headers, HTTPS, and upload behavior that source inspection cannot prove.
