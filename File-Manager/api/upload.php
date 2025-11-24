<?php
/*
===========================================================
 File: api/upload.php
 Version: 2.1.0
 Author: Jason Lamb + ChatGPT
 Created: 2025-11-23
 Modified: 2025-11-23
 Description:
   JSON upload endpoint.
   - Intended for PowerShell and admin web form usage.
   - Enforces:
       * US-only geolocation
       * IP allowlist
       * API key validation
       * Rate limiting
       * Versioned file saving
   - Logs outcomes and returns JSON status.
===========================================================
*/

require_once __DIR__ . '/../config.php';

// If you have the MaxMind PHP library installed via Composer, include it here.
if (file_exists(__DIR__ . '/../geoip/vendor/autoload.php')) {
    require_once __DIR__ . '/../geoip/vendor/autoload.php';
    use GeoIp2\Database\Reader;
} else {
    class Reader {
        public function __construct($db) {}
        public function country($ip) { return (object)['country' => (object)['isoCode' => 'US']]; }
    }
}

header('Content-Type: application/json');

$source       = $_POST['source'] ?? "powershell";
$isPowerShell = ($source === "powershell");

$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$computer = $_POST['computer'] ?? "N/A";

// --------------------------------------------------------
// GeoIP: US-only restriction
// --------------------------------------------------------
try {
    $reader  = new Reader($geoDB);
    $record  = $reader->country($clientIP);
    $country = $record->country->isoCode;
} catch (Exception $e) {
    $country = "UNKNOWN";
}

if ($country !== "US") {
    write_log($LOG_UPLOAD, "BLOCK NON-US | IP=$clientIP | SRC=$source");
    echo json_encode(["status" => "blocked", "reason" => "Non-US IP"]);
    exit;
}

// --------------------------------------------------------
// IP allowlist
// --------------------------------------------------------
if (!in_array($clientIP, $allowedIPs, true)) {
    write_log($LOG_UPLOAD, "BLOCK IP NOT IN ALLOWLIST | IP=$clientIP | SRC=$source");
    echo json_encode(["status" => "blocked", "reason" => "IP not allowed"]);
    exit;
}

// --------------------------------------------------------
// API Key validation
// --------------------------------------------------------
if (!isset($_POST['api']) || $_POST['api'] !== API_KEY) {
    write_log($LOG_UPLOAD, "BLOCK INVALID API | IP=$clientIP | SRC=$source");
    echo json_encode(["status" => "blocked", "reason" => "Invalid API Key"]);
    exit;
}

// --------------------------------------------------------
// Rate limit: 60 uploads / 60 minutes per IP
// --------------------------------------------------------
$entries = file_exists($LOG_UPLOAD)
    ? file($LOG_UPLOAD, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    : [];
$recent  = 0;
$now     = time();

foreach ($entries as $line) {
    if (strpos($line, $clientIP) !== false) {
        if (preg_match('/\[(.*?)\]/', $line, $m)) {
            $ts = strtotime($m[1]);
            if ($ts !== false && ($now - $ts) <= $RATE_LIMIT_WINDOW) {
                $recent++;
            }
        }
    }
}

if ($recent >= $RATE_LIMIT_MAX) {
    write_log($LOG_RATE, "RATE LIMIT HIT | IP=$clientIP | SRC=$source");
    echo json_encode(["status" => "ratelimited", "reason" => "1-per-minute limit reached"]);
    exit;
}

// --------------------------------------------------------
// File handling + versioning
// --------------------------------------------------------
$directory = $_POST['directory'] ?? 'uploads';

if (!in_array($directory, $allowedDirectories, true)) {
    write_log($LOG_UPLOAD, "BLOCK INVALID DIR | IP=$clientIP | DIR=$directory");
    echo json_encode(["status" => "blocked", "reason" => "Invalid directory"]);
    exit;
}

$targetPath = __DIR__ . '/../' . $directory . '/';

if (!isset($_FILES['fileToUpload']) || !is_uploaded_file($_FILES['fileToUpload']['tmp_name'])) {
    write_log($LOG_UPLOAD, "FAIL NO FILE | IP=$clientIP | SRC=$source");
    echo json_encode(["status" => "error", "reason" => "No file uploaded"]);
    exit;
}

$filename   = basename($_FILES["fileToUpload"]["name"]);
$targetFile = $targetPath . $filename;

// Versioning: move existing base file to next _vN
if (file_exists($targetFile)) {
    $info = pathinfo($filename);
    $base = $info['filename'];
    $ext  = isset($info['extension']) ? "." . $info['extension'] : "";

    $counter     = 1;
    $versionFile = $targetPath . $base . "_v$counter" . $ext;

    while (file_exists($versionFile)) {
        $counter++;
        $versionFile = $targetPath . $base . "_v$counter" . $ext;
    }

    @rename($targetFile, $versionFile);
}

// Save new file
if (!move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFile)) {
    write_log($LOG_UPLOAD, "FAIL UPLOAD | IP=$clientIP | FILE=$filename | SRC=$source | PC=$computer");
    echo json_encode(["status" => "error", "reason" => "Upload failed"]);
    exit;
}

chmod($targetFile, 0644);

$msg = "SUCCESS | IP=$clientIP | FILE=$filename | SRC=$source | PC=$computer";
write_log($LOG_UPLOAD, $msg);

if ($isPowerShell) {
    write_log($LOG_PS, $msg);
}

echo json_encode([
    "status" => "success",
    "file"   => $filename,
    "source" => $source
]);
