<?php
/*
# filename: view-media.php
# author: Jason Lamb (with help from ChatGPT)
# created date: 2026-02-04
# modified date: 2026-02-04
# revision: 1.0
# changelog:
# - 1.0: Admin dashboard for media-manifest.json (usage, unused warnings, alt-text warnings, integrity checks, copy srcset helper)
*/

date_default_timezone_set('America/New_York');

function esc($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES);
}

$blogRoot = dirname(__DIR__);
$postsDir = $blogRoot . "/posts";
$manifestPath = __DIR__ . "/media-manifest.json";
$backupsDir = __DIR__ . "/backups";

$manifest = ["_meta" => [], "items" => []];
if (file_exists($manifestPath)) {
    $manifest = json_decode(file_get_contents($manifestPath), true) ?: $manifest;
}
$items = $manifest["items"] ?? [];
if (!is_array($items)) { $items = []; }

// Scan posts for media references to detect missing manifest items and stale used_in
$refs = []; // key => [slugs...]
if (is_dir($postsDir)) {
    foreach (glob($postsDir . "/*.json") as $pf) {
        if (basename($pf) === "index.json" || basename($pf) === "_template.json") continue;
        $raw = file_get_contents($pf);
        $p = json_decode($raw, true);
        if (!$p) continue;
        $slug = $p["slug"] ?? basename($pf, ".json");
        $html = $p["content_html"] ?? "";
        if (!$html) continue;

        if (preg_match_all('#/media/derivatives/\d{4}/\d{2}/([a-zA-Z0-9_-]+)_\d+w\.jpg#', $html, $m)) {
            foreach ($m[1] as $key) {
                if (!isset($refs[$key])) $refs[$key] = [];
                if (!in_array($slug, $refs[$key], true)) $refs[$key][] = $slug;
            }
        }
    }
}

$missingInManifest = [];
foreach ($refs as $k => $slugs) {
    if (!array_key_exists($k, $items)) {
        $missingInManifest[$k] = $slugs;
    }
}

function disk_path($blogRoot, $webPath) {
    // Convert a web path like "/media/derivatives/..." to a filesystem path.
    if (!$webPath) return "";
    if (strpos($webPath, "/") !== 0) return $webPath;
    return $blogRoot . $webPath;
}

function build_srcset_snippet($itemKey, $item) {
    $alt = trim($item["alt"] ?? "") ?: $itemKey;
    $der = $item["derivatives"] ?? [];
    if (!is_array($der) || count($der) === 0) return "";

    // prefer 640 for src
    $src = $der["640"] ?? reset($der);

    // build sorted srcset
    $pairs = [];
    foreach ($der as $w => $p) {
        $pairs[] = [$w, $p];
    }
    usort($pairs, function($a,$b) {
        return intval($a[0]) <=> intval($b[0]);
    });

    $srcsetParts = [];
    foreach ($pairs as $pair) {
        $srcsetParts[] = $pair[1] . " " . $pair[0] . "w";
    }

    $snippet =
        "<figure>\n" .
        "  <img\n" .
        "    src=\"" . $src . "\"\n" .
        "    srcset=\"" . implode(",\n      ", $srcsetParts) . "\"\n" .
        "    sizes=\"(max-width: 720px) 90vw, 720px\"\n" .
        "    alt=\"" . str_replace("\"", "&quot;", $alt) . "\"\n" .
        "    loading=\"lazy\"\n" .
        "    decoding=\"async\"\n" .
        "  />\n" .
        "</figure>";
    return $snippet;
}

$total = count($items);
$unused = 0;
$missingAlt = 0;
$missingFiles = 0;

$rows = [];
foreach ($items as $key => $item) {
    $used_in = $item["used_in"] ?? [];
    if (!is_array($used_in)) $used_in = [];

    // If manifest usage is empty but refs show usage, note stale usage
    $computedUsed = $refs[$key] ?? [];

    $isUnused = (count($used_in) === 0);
    $isStale = ($isUnused && count($computedUsed) > 0);

    $alt = trim($item["alt"] ?? "");
    $isAltMissing = (strlen($alt) === 0);

    $origWeb = $item["original"] ?? "";
    $origDisk = disk_path($blogRoot, $origWeb);
    $origOk = ($origDisk && file_exists($origDisk));

    $der = $item["derivatives"] ?? [];
    $derOk = true;
    $thumb = "";
    if (is_array($der)) {
        $thumb = $der["320"] ?? ($der["640"] ?? "");
        foreach ($der as $w => $p) {
            $d = disk_path($blogRoot, $p);
            if (!$d || !file_exists($d)) {
                $derOk = false;
                break;
            }
        }
    } else {
        $derOk = false;
    }

    $isFilesMissing = (!$origOk || !$derOk);

    if ($isUnused) $unused++;
    if ($isAltMissing) $missingAlt++;
    if ($isFilesMissing) $missingFiles++;

    $rows[] = [
        "key" => $key,
        "item" => $item,
        "used_in" => $used_in,
        "computed_used" => $computedUsed,
        "stale" => $isStale,
        "unused" => $isUnused,
        "alt_missing" => $isAltMissing,
        "files_missing" => $isFilesMissing,
        "thumb" => $thumb,
        "snippet" => build_srcset_snippet($key, $item),
        "uploaded" => $item["uploaded"] ?? ""
    ];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Blog Admin - Media</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; padding: 2rem; }
    .wrap { max-width: 1200px; margin: 0 auto; }
    table { border-collapse: collapse; width: 100%; }
    th, td { padding: .5rem; border-bottom: 1px solid #ddd; vertical-align: top; }
    th { text-align: left; }
    .tag { display:inline-block; padding:2px 6px; border-radius: 999px; font-size: 12px; background:#f1f1f1; margin-right:6px; }
    .warn { background:#fff3cd; }
    .bad { background:#f8d7da; }
    .muted { color:#666; }
    code { background:#f4f4f4; padding:2px 4px; }
    img.thumb { width: 56px; height: 56px; object-fit: cover; border-radius: 6px; border:1px solid #ccc; }
    .btn { padding: .35rem .6rem; cursor:pointer; }
    textarea.snip { width:100%; min-height: 120px; }
    details > summary { cursor:pointer; }
  </style>
</head>
<body>
<div class="wrap">
  <h1>Media</h1>
  <p class="muted">Manifest: <code>admin/media-manifest.json</code> (protected by HTTP auth). Images live under <code>/media/</code>.</p>
  <p><a href="upload-media.php">Upload new media</a></p>

  <p>
    <span class="tag">Total: <?php echo esc($total); ?></span>
    <span class="tag">Unused: <?php echo esc($unused); ?></span>
    <span class="tag">Missing alt: <?php echo esc($missingAlt); ?></span>
    <span class="tag">Missing files: <?php echo esc($missingFiles); ?></span>
    <span class="tag">Refs missing in manifest: <?php echo esc(count($missingInManifest)); ?></span>
  </p>

  <?php if (count($missingInManifest) > 0): ?>
    <details open>
      <summary><strong>Referenced in posts but missing from manifest</strong> (run Build-Blog.ps1 or re-upload)</summary>
      <ul>
        <?php foreach ($missingInManifest as $k => $slugs): ?>
          <li><code><?php echo esc($k); ?></code> used in: <?php echo esc(implode(", ", $slugs)); ?></li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endif; ?>

  <table>
    <tr>
      <th>Thumb</th>
      <th>Key</th>
      <th>Uploaded</th>
      <th>Used in</th>
      <th>Status</th>
      <th>srcset helper</th>
    </tr>

    <?php foreach ($rows as $r): ?>
      <?php
        $cls = "";
        if ($r["files_missing"]) $cls = "bad";
        else if ($r["unused"] || $r["alt_missing"] || $r["stale"]) $cls = "warn";

        $status = [];
        if ($r["files_missing"]) $status[] = "MISSING FILES";
        if ($r["alt_missing"]) $status[] = "NO ALT";
        if ($r["unused"]) $status[] = "UNUSED";
        if ($r["stale"]) $status[] = "USAGE STALE (run build)";
      ?>
      <tr class="<?php echo esc($cls); ?>">
        <td>
          <?php if ($r["thumb"]): ?>
            <img class="thumb" src="<?php echo esc($r["thumb"]); ?>" alt="" />
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
        <td><code><?php echo esc($r["key"]); ?></code></td>
        <td><?php echo esc($r["uploaded"] ?: "—"); ?></td>
        <td>
          <?php
            $use = $r["used_in"];
            if (count($use) === 0 && count($r["computed_used"]) > 0) $use = $r["computed_used"];
          ?>
          <?php echo esc(count($use) ? implode(", ", $use) : "—"); ?>
        </td>
        <td><?php echo esc(count($status) ? implode(" • ", $status) : "OK"); ?></td>
        <td>
          <?php if ($r["snippet"]): ?>
            <textarea class="snip" id="snip-<?php echo esc($r["key"]); ?>" readonly><?php echo esc($r["snippet"]); ?></textarea>
            <button class="btn copyBtn" data-target="snip-<?php echo esc($r["key"]); ?>" type="button">Copy</button>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h2>Manifest backups</h2>
  <p class="muted">Backups are created by <code>tools/Build-Blog.ps1</code> before manifest writes.</p>
  <?php
    $backupFiles = [];
    if (is_dir($backupsDir)) {
      foreach (glob($backupsDir . "/media-manifest-*.json") as $bf) {
        $backupFiles[] = basename($bf);
      }
      rsort($backupFiles);
    }
  ?>
  <?php if (count($backupFiles) === 0): ?>
    <p>—</p>
  <?php else: ?>
    <ul>
      <?php foreach (array_slice($backupFiles, 0, 10) as $bf): ?>
        <li><a href="backups/<?php echo esc($bf); ?>"><?php echo esc($bf); ?></a></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

</div>

<script>
  document.querySelectorAll(".copyBtn").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const id = btn.getAttribute("data-target");
      const ta = document.getElementById(id);
      if (!ta) return;
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
  });
</script>
</body>
</html>
