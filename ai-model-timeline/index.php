<?php
$version = '0.1.0';
$created = '2026-11-23';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>AI Model Timeline</title><link rel="stylesheet" href="assets/css/timeline.css"></head>
<body><main><header><h1>AI Model Timeline</h1><p>One dataset. One table. One graphic.</p>
<div class="meta">v<?=htmlspecialchars($version)?> · Updated <?=htmlspecialchars($created)?></div></header>
<section class="actions"><button id="svgBtn">Download SVG</button><button id="pngBtn">Download PNG</button></section>
<section><h2>Timeline</h2><div id="timeline"></div></section>
<section><h2>Model data</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>Platform</th><th>Model</th><th>Status</th><th>Access</th><th>Source</th></tr></thead><tbody id="modelRows"></tbody></table></div></section>
</main><script>window.TIMELINE_VERSION=<?=json_encode($version)?>;window.TIMELINE_DATE=<?=json_encode($created)?>;</script><script src="assets/js/timeline.js"></script></body></html>