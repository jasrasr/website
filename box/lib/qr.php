<?php
/*
===========================================================
 File: qr.php
 Author: Jason Lamb (with help from AI)
 Created: 2026-01-18
 Modified: 2026-01-18
 Revision: 1.1

 Changes:
   Rev 1.0 - Initial QR helper (composer-based)
   Rev 1.1 - Switched to phpqrcode (no composer dependency)
===========================================================
*/

require_once __DIR__ . '/qrlib.php';

define('QR_OUTPUT_DIR', __DIR__ . '/../qrcodes/');

function generateBoxQR(string $boxCode): string {

    // Follow the deployed app path, whether it is /box/, /boxes/, or nested.
    $adminPath = $_SERVER['SCRIPT_NAME'] ?? '/box/admin/create_box.php';
    $appPath = rtrim(dirname(dirname($adminPath)), '/');
    $url = 'https://jasr.me' . $appPath . '/' . rawurlencode($boxCode);


    if (!is_dir(QR_OUTPUT_DIR)) {
        mkdir(QR_OUTPUT_DIR, 0755, true);
    }

    $filePath = QR_OUTPUT_DIR . $boxCode . '.png';

    // Generate QR
    QRcode::png(
        $url,
        $filePath,
        QR_ECLEVEL_M,
        6
    );

    return $filePath;
}

function qrFilePath(string $boxCode): string {
    return QR_OUTPUT_DIR . $boxCode . '.png';
}

function qrExists(string $boxCode): bool {
    return file_exists(qrFilePath($boxCode));
}

function deleteBoxQR(string $boxCode): void {
    $file = qrFilePath($boxCode);
    if (file_exists($file)) {
        unlink($file);
    }
}
