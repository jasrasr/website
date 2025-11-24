# CHANGELOG

## 2.1.0 — MFA + File Creation + API Split (2025-11-23)

### Added
- **MFA (TOTP) Support**
  - `admin/mfa_lib.php` – Core helper for TOTP-style MFA, secret storage, and verification.
  - `admin/mfa_setup.php` – One-time setup with QR code + initial code validation.
  - `admin/mfa_verify.php` – Code entry page when MFA is required for dangerous actions.
  - `data/mfa_secret.json` – Plain-JSON storage of the MFA secret.

- **Admin File Creation**
  - Extended `admin/admin.php` to support creation of new files:
    - Any filename with any extension.
    - Any subfolder under allowed directories (currently `uploads`).
    - Automatic versioning if the target file already exists.
  - All creation actions are MFA-gated and logged.

- **API/Folder Split**
  - Moved programmatic upload handler to `api/upload.php`.
  - Kept admin UI under `admin/` to cleanly separate browser UI and script/API endpoints.

- **MFA Status Indicator**
  - `admin/admin.php` now displays whether MFA is **Verified** or **Not Verified** for the current session.

### Updated
- **config.php**
  - Centralized/root config used by both `/admin` and `/api`.
  - Introduced:
    - `MFA_SECRET_FILE` for MFA secret location.
    - `ALLOWED_IPS_FILE` moved under `/data`.
    - `$SECURITY_LOG` path for MFA and sensitive admin events.
  - Ensures creation of:
    - `logs/` directory
    - `data/` directory
    - `uploads/` directory
    - Skeleton MFA/allowlist files.

- **admin/admin.php**
  - Now requires `../config.php` and `mfa_lib.php`.
  - Auto-whitelists IP on successful admin access (`SOURCE=admin`).
  - Wraps POST actions (`restore`, `delete`, `create`) with `mfa_require_or_redirect()`.
  - Displays MFA status banner (Verified / Not Verified).
  - Handles a new `create` action to write text-based files with versioning.

- **admin/whitelistme.php**
  - Updated to use root `config.php` and `/data/allowed_ips.json`.
  - Enforces 1 whitelist request per hour per IP.
  - Logs all whitelist attempts and password failures.

- **api/upload.php**
  - Updated to reference root `config.php` and `logs/`/`data/` directories.
  - Maintains:
    - US-only geolocation check.
    - IP allowlist enforcement.
    - API key validation.
    - Upload rate limiting.
    - File versioning behavior.
    - PowerShell vs Web source tagging.

### Documentation
- **README.md**
  - Rewritten to reflect:
    - `/admin` vs `/api` separation.
    - MFA setup and verification.
    - File creation from Admin panel.
    - Logging, versioning, and security layers.
    - PowerShell usage with `/api/upload.php`.

---

## 2.0.0 — Full Security + Admin Release (prior)
- Introduced:
  - Versioned upload handling.
  - Admin file manager with restore/delete version controls.
  - Dynamic IP allowlist and whitelist helper.
  - US-only access and rate-limited upload API.
  - Initial README and logging system.

## 1.0.0 — Initial Prototype
- Initial upload script with minimal security and basic versioning.
