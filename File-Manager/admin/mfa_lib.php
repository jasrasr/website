<?php
/*
===========================================================
 File: admin/mfa_lib.php
 Version: 2.1.0
 Author: Jason Lamb + ChatGPT
 Created: 2025-11-23
 Modified: 2025-11-23
 Description:
   MFA helper library for Google-Authenticator-style TOTP.
   - Plain JSON secret storage in MFA_SECRET_FILE.
   - TOTP code generation and verification.
   - Session-based tracking of MFA verification.
===========================================================
*/

require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------- MFA Secret Helpers ----------

function mfa_get_secret(): ?string {
    if (!file_exists(MFA_SECRET_FILE)) {
        return null;
    }
    $json = file_get_contents(MFA_SECRET_FILE);
    $data = json_decode($json, true);
    if (!is_array($data) || empty($data['secret'])) {
        return null;
    }
    return $data['secret'];
}

function mfa_save_secret(string $secret): void {
    $payload = [
        'secret'  => $secret,
        'created' => date('Y-m-d H:i:s')
    ];
    file_put_contents(MFA_SECRET_FILE, json_encode($payload, JSON_PRETTY_PRINT));
}

function mfa_is_configured(): bool {
    return mfa_get_secret() !== null;
}

// ---------- Base32 Decode ----------

function mfa_base32_decode(string $secret): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret   = strtoupper($secret);
    $secret   = preg_replace('/[^A-Z2-7]/', '', $secret);

    $bits = '';
    for ($i = 0; $i < strlen($secret); $i++) {
        $val  = strpos($alphabet, $secret[$i]);
        if ($val === false) continue;
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }

    $output = '';
    for ($i = 0; i + 8 <= strlen($bits); $i += 8) {
        $byte   = substr($bits, $i, 8);
        $output .= chr(bindec($byte));
    }
    return $output;
}

// ---------- TOTP Generation & Verification ----------

function mfa_generate_totp(string $secret, int $timeSlice = null): int {
    if ($timeSlice === null) {
        $timeSlice = floor(time() / 30);
    }

    $key = mfa_base32_decode($secret);

    $time = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $time, $key, true);

    $offset  = ord(substr($hash, -1)) & 0x0F;
    $binary  = (ord($hash[$offset]) & 0x7F) << 24 |
               (ord($hash[$offset + 1]) & 0xFF) << 16 |
               (ord($hash[$offset + 2]) & 0xFF) << 8 |
               (ord($hash[$offset + 3]) & 0xFF);

    $otp = $binary % 1000000;
    return $otp;
}

function mfa_verify_code(string $secret, string $code, int $window = 1): bool {
    $code = trim($code);
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }

    $timeSlice = floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        $calc = mfa_generate_totp($secret, $timeSlice + $i);
        if (str_pad((string)$calc, 6, '0', STR_PAD_LEFT) === $code) {
            return true;
        }
    }
    return false;
}

// ---------- Session / Middleware Helpers ----------

function mfa_is_verified(): bool {
    if (!mfa_is_configured()) {
        // If not configured yet, treat as not verified but allow setup
        return false;
    }

    if (!isset($_SESSION['mfa_passed'])) {
        return false;
    }

    // Optionally expire MFA after e.g., 1 hour
    $maxAge = 3600;
    if ((time() - (int)$_SESSION['mfa_passed']) > $maxAge) {
        unset($_SESSION['mfa_passed']);
        return false;
    }

    return true;
}

function mfa_require_or_redirect(string $redirectTarget = null): void {
    if (!mfa_is_configured()) {
        header('Location: mfa_setup.php');
        exit;
    }

    if (!mfa_is_verified()) {
        if ($redirectTarget === null) {
            $redirectTarget = $_SERVER['REQUEST_URI'] ?? 'admin.php';
        }
        header('Location: mfa_verify.php?redirect=' . urlencode($redirectTarget));
        exit;
    }
}
