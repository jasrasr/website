<?php
// AI Model Timeline — version and last modified are kept in sync with data/models.json.
$meta = json_decode((string) @file_get_contents(__DIR__ . '/data/models.json'), true) ?: [];
$version = (string) ($meta['project_version'] ?? 'unknown');
$created = (string) ($meta['updated'] ?? 'unknown');
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
<div class="meta">Version <?=htmlspecialchars($version)?> · Graphic created <?=htmlspecialchars($created)?> · <span id="count">Loading…</span></div></header>
<section class="actions" aria-label="Graphic downloads">
<button id="svgBtn" type="button">Download SVG</button><button id="pngBtn" type="button">Download PNG</button>
<a href="archive/v1.0.0/ai-model-timeline-v1.0.0-2026-11-23.svg">View archived v1.0.0</a>
<a href="CHANGELOG.md">Graphic changelog</a>
</section>
<section><h2>Timeline</h2><p class="meta">Scroll horizontally to inspect all release periods. Each capsule corresponds to exactly one row in the model table.</p><div id="timeline" role="region" aria-label="Scrollable model release timeline" tabindex="0"></div></section>
<section><h2>Release table</h2><p class="meta">Month-only dates intentionally show YYYY-MM; exact days are never inferred. Every model has a first-party source link. Some catalog-linked historical dates require more granular verification.</p><div class="table-wrap"><table><thead><tr><th>Release date</th><th>Platform</th><th>Model</th><th>Status</th><th>Access</th><th>Source</th></tr></thead><tbody id="modelRows"></tbody></table></div></section>
<footer><small>v<?=htmlspecialchars($version)?> · Modified <?=htmlspecialchars($created)?> · <a href="README.md">Methodology</a></small></footer>
</main><script src="assets/js/render-core.js"></script><script src="assets/js/timeline.js"></script></body></html>
