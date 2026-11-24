<?php
// AI Model Timeline — version and last modified are kept in sync with data/models.json.
$meta = json_decode((string) @file_get_contents(__DIR__ . '/data/models.json'), true) ?: [];
$version = (string) ($meta['project_version'] ?? 'unknown');
$created = (string) ($meta['updated'] ?? 'unknown');
$graphicVersion = (string) ($meta['graphic_version'] ?? 'unknown');
$graphicCreated = (string) ($meta['graphic_created'] ?? 'unknown');
$manifest = json_decode((string) @file_get_contents(__DIR__ . '/archive/manifest.json'), true) ?: [];
$graphicRelease = null;
foreach (($manifest['releases'] ?? []) as $release) {
    if (($release['version'] ?? '') === $graphicVersion) { $graphicRelease = $release; break; }
}
$graphicLink = is_array($graphicRelease) ? ($graphicRelease['graphic'] ?? '') : '';
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Source-linked history of major AI model releases from nine AI labs, 2023 to 2026.">
<title>Frontier AI Model Release Timeline</title>
<link rel="stylesheet" href="assets/css/timeline.css">
</head>
<body><main>
<header><h1>Frontier AI Model Release Timeline</h1><p>One dataset → a matching table and timeline.</p>
<div class="meta">Project v<?=htmlspecialchars($version)?> · Graphic v<?=htmlspecialchars($graphicVersion)?> (<?=htmlspecialchars($graphicCreated)?>) · Updated <?=htmlspecialchars($created)?> · <span id="count">Loading…</span></div></header>
<section class="actions" aria-label="Graphic downloads">
<button id="svgBtn" type="button">Download SVG</button><button id="pngBtn" type="button">Download PNG</button>
<?php if ($graphicLink): ?><a href="<?=htmlspecialchars($graphicLink, ENT_QUOTES)?>">View archived graphic v<?=htmlspecialchars($graphicVersion)?></a><?php endif; ?>
<a href="CHANGELOG.md">Graphic changelog</a>
</section>
<section><h2>Timeline</h2><p class="meta">Scroll horizontally to inspect all release periods. Each capsule corresponds to exactly one row in the model table.</p><div id="timeline" role="region" aria-label="Scrollable model release timeline" tabindex="0"></div></section>
<section><h2>Historical graphics</h2><p class="meta">Each version is archived with its original data snapshot and change reason.</p><div id="history"></div></section>
<section><h2>Release table</h2><p class="meta">Month-only dates intentionally show YYYY-MM; exact days are never inferred. Select any column header to sort ascending or descending. Every model has a first-party source link. Some catalog-linked historical dates require more granular verification.</p><div class="table-wrap"><table><thead><tr><th scope="col" aria-sort="ascending"><button type="button" class="sort-header" data-sort="release_date">Release date <span class="sort-arrow" aria-hidden="true">▲</span></button></th><th scope="col" aria-sort="none"><button type="button" class="sort-header" data-sort="platform">Platform <span class="sort-arrow" aria-hidden="true">↕</span></button></th><th scope="col" aria-sort="none"><button type="button" class="sort-header" data-sort="model">Model <span class="sort-arrow" aria-hidden="true">↕</span></button></th><th scope="col" aria-sort="none"><button type="button" class="sort-header" data-sort="status">Status <span class="sort-arrow" aria-hidden="true">↕</span></button></th><th scope="col" aria-sort="none"><button type="button" class="sort-header" data-sort="access">Access <span class="sort-arrow" aria-hidden="true">↕</span></button></th><th scope="col" aria-sort="none"><button type="button" class="sort-header" data-sort="source_url">Source <span class="sort-arrow" aria-hidden="true">↕</span></button></th></tr></thead><tbody id="modelRows"></tbody></table></div></section>
<footer><small>Project v<?=htmlspecialchars($version)?> · Graphic v<?=htmlspecialchars($graphicVersion)?> · Modified <?=htmlspecialchars($created)?> · <a href="README.md">Methodology</a></small></footer>
</main><script src="assets/js/render-core.js"></script><script src="assets/js/timeline.js"></script><script defer src="https://jasr.me/github/webstats/assets/js/tracker.js"></script></body></html>
