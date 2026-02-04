<?php
/*
# filename: upload-media.php
# author: Jason Lamb (with help from ChatGPT)
# created date: 2026-02-04
# modified date: 2026-02-04
# revision: 1.0
# changelog:
# - 1.0: Admin media uploader; server-side validation; ImageMagick derivatives; updates media-manifest.json; outputs srcset snippet
*/

date_default_timezone_set('America/New_York');

session_start(); // required for per-session rate limiting

$now  = time();
$last = $_SESSION['last_upload'] ?? 0;

// Allow one upload every 5 seconds per browser session
if (($now - $last) < 5) {
    http_response_code(429);
    exit("Slow down.");
}
$_SESSION['last_upload'] = $now;

function esc($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES);
}

function safe_key($s) {
    $s = strtolower(trim((string)$s));
    $s = preg_replace('/[^a-z0-9_-]+/', '-', $s);
    $s = preg_replace('/-+/', '-', $s);
    return trim($s, '-');
}

$blogRoot = dirname(__DIR__);
$manifestPath = __DIR__ . "/media-manifest.json";

$maxBytes = 10 * 1024 * 1024; // 10MB

$allowedMime = [
    "image/jpeg",
    "image/png",
    "image/webp"
];

$finfo = new finfo(FILEINFO_MIME_TYPE);

$message = "";
$snippet = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["image"])) {
    $tmp = $_FILES["image"]["tmp_name"] ?? "";
    $err = $_FILES["image"]["error"] ?? UPLOAD_ERR_NO_FILE;

    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
        $message = "Upload failed (PHP upload error).";
    } else if (($_FILES["image"]["size"] ?? 0) > $maxBytes) {
        $message = "Upload rejected (file too large).";
        http_response_code(413);
    } else {
        $mime = $finfo->file($tmp);
        if (!in_array($mime, $allowedMime, true)) {
            $message = "Upload rejected (invalid file type).";
        } else {
            $alt    = trim($_POST["alt"] ?? "");
            $credit = trim($_POST["credit"] ?? "");
            $keyRaw = trim($_POST["key"] ?? "");

            $origName = $_FILES["image"]["name"] ?? "upload";
            $baseFromName = pathinfo($origName, PATHINFO_FILENAME);

            $key = safe_key($keyRaw ?: $baseFromName);
            if (!$key) {
                $key = "image-" . date("Ymd-His");
            }

            $year  = date("Y");
            $month = date("m");

            $origDir = $blogRoot . "/media/originals/$year/$month";
            $derDir  = $blogRoot . "/media/derivatives/$year/$month";

            if (!is_dir($origDir)) { mkdir($origDir, 0755, true); }
            if (!is_dir($derDir))  { mkdir($derDir, 0755, true); }

            $origDisk = $origDir . "/" . $key . ".jpg";

            // Move upload to original location first (we will re-encode)
            if (!move_uploaded_file($tmp, $origDisk)) {
                $message = "Upload failed (cannot move file).";
            } else {
                // Re-encode original to a safe, stripped JPEG
                $cmd = "magick " . escapeshellarg($origDisk) .
                       " -strip -auto-orient -quality 90 " . escapeshellarg($origDisk);
                exec($cmd, $out, $rc);
                if ($rc !== 0) {
                    $message = "ImageMagick failed to re-encode original.";
                } else {
                    $sizes = [320, 640, 960, 1600];
                    $derivatives = [];

                    foreach ($sizes as $w) {
                        $derDisk = $derDir . "/" . $key . "_" . $w . "w.jpg";
                        $cmd2 = "magick " . escapeshellarg($origDisk) .
                                " -strip -auto-orient -resize " . escapeshellarg($w . "x") .
                                " -quality 85 " . escapeshellarg($derDisk);
                        exec($cmd2, $o2, $r2);
                        if ($r2 !== 0) {
                            $message = "ImageMagick failed creating derivative: " . $w . "w";
                            break;
                        }
                        $derivatives[(string)$w] = "/media/derivatives/$year/$month/" . $key . "_" . $w . "w.jpg";
                    }

                    if (!$message) {
                        // Load manifest
                        $manifest = ["_meta" => [], "items" => []];
                        if (file_exists($manifestPath)) {
                            $manifest = json_decode(file_get_contents($manifestPath), true) ?: $manifest;
                        }
                        if (!isset($manifest["items"]) || !is_array($manifest["items"])) {
                            $manifest["items"] = [];
                        }

                        $manifest["items"][$key] = [
                            "original"   => "/media/originals/$year/$month/" . $key . ".jpg",
                            "derivatives"=> $derivatives,
                            "uploaded"   => date("c"),
                            "used_in"    => $manifest["items"][$key]["used_in"] ?? [],
                            "alt"        => $alt,
                            "credit"     => $credit
                        ];

                        // Update meta modified date (if present)
                        if (isset($manifest["_meta"]) && is_array($manifest["_meta"])) {
                            $manifest["_meta"]["modified_date"] = "2026-02-04";
                        }

                        file_put_contents(
                            $manifestPath,
                            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                            LOCK_EX
                        );

                        // Build a copy/paste srcset snippet
                        $src = $derivatives["640"] ?? reset($derivatives);
                        $srcset = [];
                        foreach ($derivatives as $w => $p) {
                            $srcset[] = $p . " " . $w . "w";
                        }

                        $snippet = "<figure>\n" .
                                   "  <img\n" .
                                   "    src=\"" . $src . "\"\n" .
                                   "    srcset=\"" . implode(",\n      ", $srcset) . "\"\n" .
                                   "    sizes=\"(max-width: 720px) 90vw, 720px\"\n" .
                                   "    alt=\"" . str_replace("\"", "&quot;", $alt ?: $key) . "\"\n" .
                                   "    loading=\"lazy\"\n" .
                                   "    decoding=\"async\"\n" .
                                   "  />\n" .
                                   "</figure>";

                        $message = "Upload complete: " . $key;
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Blog Admin - Upload Media</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; padding: 2rem; }
    .wrap { max-width: 900px; margin: 0 auto; }
    label { display:block; margin-top: 1rem; font-weight: 600; }
    input[type="text"] { width: 100%; padding: .6rem; }
    textarea { width: 100%; min-height: 180px; padding: .6rem; }
    .row { display:flex; gap: 1rem; flex-wrap: wrap; }
    .row > div { flex: 1 1 260px; }
    .btn { padding: .6rem 1rem; cursor:pointer; }
    .msg { padding: .75rem 1rem; background: #f3f3f3; margin: 1rem 0; border-left: 4px solid #444; }
    .muted { color: #666; }
  </style>
</head>
<body>
<div class="wrap">
  <h1>Upload Media</h1>
  <p class="muted">This page is protected by <code>.htaccess</code> HTTP auth. Images will be re-encoded and resized with ImageMagick.</p>
  <p><a href="view-media.php">View media manifest</a></p>

  <?php if ($message): ?>
    <div class="msg"><?php echo esc($message); ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <label>Image file</label>
    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required />

    <div class="row">
      <div>
        <label>Key (optional)</label>
        <input type="text" name="key" placeholder="e.g. notepadpp-supply-chain" />
        <div class="muted">If blank, the filename is used. Only a-z 0-9 _ - are kept.</div>
      </div>
      <div>
        <label>Alt text (recommended)</label>
        <input type="text" name="alt" placeholder="Describe the image for readers using screen readers." />
      </div>
    </div>

    <label>Credit (optional)</label>
    <input type="text" name="credit" placeholder="e.g. Screenshot by Jason Lamb" />

    <p style="margin-top:1rem;">
      <button class="btn" type="submit">Upload + Generate</button>
    </p>
  </form>

  <?php if ($snippet): ?>
    <h2>srcset snippet</h2>
    <p class="muted">Copy/paste into your post JSON <code>content_html</code>.</p>
    <textarea id="snippet" readonly><?php echo esc($snippet); ?></textarea>
    <p>
      <button class="btn" id="copyBtn" type="button">Copy snippet</button>
    </p>

    <script>
      document.getElementById("copyBtn").addEventListener("click", async () => {
        const ta = document.getElementById("snippet");
        ta.select();
        ta.setSelectionRange(0, ta.value.length);
        try {
          await navigator.clipboard.writeText(ta.value);
          alert("Copied.");
        } catch (e) {
          document.execCommand("copy");
          alert("Copied (fallback).");
        }
      });
    </script>
  <?php endif; ?>
</div>
</body>
</html>
