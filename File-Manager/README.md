# Secure Upload & File Manager (PHP + PowerShell)
Version: 2.1.0  
Author: Jason Lamb (requirements, architecture) + ChatGPT (implementation)

This project provides a secure, admin-only **file management system** with:

- Web-based **Admin Portal** (behind `.htaccess` & MFA)
- **PowerShell-friendly API** endpoint for uploads
- File **versioning** (`file_v1.ext`, `file_v2.ext`, ...)
- **Text file creation** from the admin UI (any extension, any subfolder under `/uploads`)
- Dynamic **IP allowlisting** with auto-whitelist for admin logins
- Optional **self-whitelist page** (with password + rate limit)
- **US-only** IP geolocation enforcement (MaxMind GeoLite2)
- **Rate limiting** for uploads & whitelisting
- Extensive **logging**

You intend to deploy this under:

`https://jasr.me/file-manager/`
and this layout matches that.

---

## Folder Structure

```
file-manager/
├── config.php
├── README.md
├── CHANGELOG.md
│
├── admin/
│   ├── admin.php          # Main admin UI (MFA-gated actions, MFA status, file creation)
│   ├── upload_form.php    # Web upload form (uses API endpoint)
│   ├── whitelistme.php    # Whitelist-your-current-IP page (password + rate limit)
│   ├── mfa_lib.php        # MFA helper (TOTP generator/validator)
│   ├── mfa_setup.php      # One-time MFA enrollment (QR + code check)
│   └── mfa_verify.php     # MFA code prompt for dangerous actions
│
├── api/
│   └── upload.php         # JSON upload endpoint (PowerShell + web form)
│
├── uploads/               # All uploaded / created files (can include subfolders)
├── logs/                  # All log files (created automatically)
└── data/                  # MFA secret, allowed IP store, etc.
```

> Note: The **MaxMind GeoLite2** database is expected at:  
> `file-manager/geoip/GeoLite2-Country.mmdb` (you must download it yourself).

---

## Security Layers

This system is intentionally overbuilt for security:

1. **.htaccess Basic Auth**  
   Protects everything in `/admin/` (including `admin.php`, `mfa_*`, and `whitelistme.php`).

2. **IP Allowlist (Dynamic + Auto)**  
   - Stored in `data/allowed_ips.json` (plus base IPs in `config.php`)  
   - Any successful visit to `admin/admin.php` auto-whitelists that IP.  
   - `admin/whitelistme.php` can also add your current IP after entering a password.

3. **Country Restriction (US-only)**  
   - `api/upload.php` uses MaxMind GeoLite2 to block non-US IPs.

4. **API Key**  
   - Required for all uploads.  
   - Used both by the web form and PowerShell.  
   - Validated server-side in `api/upload.php` (never exposed in public JS).

5. **Rate Limiting**
   - Upload API: 60 uploads per 60 minutes per IP (≈1/minute).  
   - Whitelist page: 1 whitelist action per IP per hour.  
   - Logged to `logs/rate_limit.log` and `logs/whitelist.log`.

6. **MFA (TOTP, Google Authenticator-Style)**
   - Plain JSON storage in `data/mfa_secret.json` (can be changed to encrypted later).  
   - Setup via `admin/mfa_setup.php` (QR code + first code verification).  
   - Enforced via `mfa_require_or_redirect()` in `admin.php` for **dangerous actions**:
     - Restore version
     - Delete version
     - Create new file

7. **Logging**
   - `logs/upload.log`      – All uploads, sources, and outcomes.  
   - `logs/powershell.log`  – Uploads specifically from PowerShell.  
   - `logs/whitelist.log`   – All whitelist attempts + rate-limit hits.  
   - `logs/security.log`    – MFA setup/verify success/fail, admin-related security events.  
   - `logs/rate_limit.log`  – Upload API rate-limit hits.

---

## Admin Portal (`admin/admin.php`)

The main Admin UI provides:

- A table of **files grouped by base name** and version (`file.ext`, `file_v1.ext`, `file_v2.ext`, ...).  
- **Restore** buttons to promote any `_vN` file to become the current base file.  
  - The previous base file is re-versioned to the next `_vN`.  
- **Delete** buttons for each versioned file (`_vN`) only.  
  - If the file was removed externally (FTP, etc.), the admin page logs it and cleans up the UI.  
- A **MFA status indicator**:
  - `MFA Status: Verified` (green) if the current session is MFA-verified.
  - `MFA Status: Not Verified` (red) otherwise.
- A **Create New File** section:
  - Choose a top-level directory (currently `uploads`).  
  - Optional subfolder (relative, like `blog`, `notes/2025`, etc.).  
  - Enter any filename with extension (`index.html`, `config.json`, `readme.txt`, etc.).  
  - Enter file content (text/HTML/JSON/etc.).  
  - If the file already exists, it is versioned similar to uploads:
    - Existing base file → `_v1`, `_v2`, etc.
    - New content becomes the base file.

All POST actions in `admin.php` (restore, delete, create) are **MFA-gated** via `mfa_require_or_redirect()`.

---

## Web Upload Form (`admin/upload_form.php`)

- Accessible only after `.htaccess` admin login.  
- Submits to `api/upload.php`.  
- Lets you pick the destination directory (currently `uploads`) from `$allowedDirectories`.  
- Adds `source=web` to the upload so logs can distinguish it from `source=powershell`.  
- Includes the API key as a hidden field (server-side controlled; do **not** expose it publicly).

---

## Upload API (`api/upload.php`)

This endpoint:

- Accepts `multipart/form-data` from **web form** or **PowerShell**.  
- Validates:
  - Country (US only, via GeoLite2)
  - IP allowlist
  - API key
  - Upload rate limit
- Performs versioning:
  - If `filename.ext` exists in the target directory:
    - Renames existing file to `filename_v1.ext`, `filename_v2.ext`, …
- Logs success/failure with IP, filename, source, and (for PowerShell) computer name.
- Returns JSON like:
  ```json
  { "status": "success", "file": "index.html", "source": "powershell" }
  ```
  or with error/reason keys.

### PowerShell Example

```powershell
$FilePath   = "C:\path\to\file.html"
$Computer   = $env:COMPUTERNAME
$FormUrl    = "https://jasr.me/file-manager/api/upload.php"
$Cred       = Get-Credential  # .htaccess or HTTP Basic, if needed

$response = Invoke-WebRequest -Uri $FormUrl -Method POST -Credential $Cred `
    -Form @{
        directory    = "uploads"
        api          = "YourSuperSecretKey123"
        source       = "powershell"
        computer     = $Computer
        fileToUpload = Get-Item $FilePath
    } -UseBasicParsing

$response.Content  # JSON status
```

You can additionally log response JSON locally to your normal PowerShell log folder.

---

## Whitelist Page (`admin/whitelistme.php`)

- Shows your current IP.  
- Requires a shared password (`WHITELIST_PASSWORD` from `config.php`).  
- On success:
  - Adds your IP to `data/allowed_ips.json`.  
  - Logs event to `logs/whitelist.log`.  
  - Enforces 1 action/hour per IP.

Note: Admin logins to `admin.php` also auto-whitelist the IP with `SOURCE=admin`.

---

## MFA Flow

1. Navigate to `admin/mfa_setup.php` (after `.htaccess` auth).  
2. Page generates a Base32 secret and a QR code via an external QR service.  
3. Scan the code with your Authenticator app (Google Auth, Microsoft Auth, etc.).  
4. Enter the 6-digit code once to confirm.  
5. Secret is saved to `data/mfa_secret.json` (plain JSON).  
6. From then on, any dangerous admin action that triggers a POST:
   - If MFA not configured → redirect to setup.  
   - If MFA not verified (or expired) → redirect to `mfa_verify.php`.  
   - After correct code, `$_SESSION['mfa_passed']` is set with timestamp.  

`mfa_lib.php` handles:

- Secret storage  
- Base32 decoding  
- TOTP generation  
- Time window tolerance (±1 time step)  
- Session-based “MFA verified” state

You can later swap the storage mechanism for encrypted without changing the rest of the code.

---

## Requirements

- PHP 7.4+
- Apache with `.htaccess` enabled
- Composer (for MaxMind PHP library in `/geoip/vendor`, if you use that library)
- MaxMind GeoLite2 Country Database (manually downloaded)

---

## Logs

All logs are simple text files with `[YYYY-MM-DD HH:MM:SS]` prefixes.

- `logs/upload.log`      – Successes and failures for uploads.  
- `logs/powershell.log`  – All uploads where `source=powershell`.  
- `logs/whitelist.log`   – Whitelist attempts and rate limits.  
- `logs/security.log`    – MFA setup/verify, admin security events.  
- `logs/rate_limit.log`  – Upload API rate limit hits.

---

## Versioning & History

See `CHANGELOG.md` for detailed changes by version.
