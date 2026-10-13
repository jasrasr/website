<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header("Content-Security-Policy: default-src 'self'; connect-src 'self'; script-src 'self'; style-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Freshservice • Ticket review</title><link rel="stylesheet" href="review.css"></head>
<body><header><div><p class="eyebrow">FRESHSERVICE / WORKSPACE</p><h1>One ticket closer.</h1><p>A clear next step for every unresolved ticket.</p></div><a href="../">Ticket tracker ↗</a></header>
<main><section id="connect" class="panel"><h2>Open your private review queue</h2><p>Use the review token configured on your server. It stays in memory for this tab.</p><form id="connect-form"><label for="token">Review token</label><div class="row"><input id="token" type="password" required autocomplete="off" placeholder="Private review token"><button>Load my tickets</button></div></form></section>
<p id="message" role="status" aria-live="polite"></p>
<section id="workspace" hidden><div class="toolbar panel"><div><strong id="count"></strong><p id="freshness" class="muted"></p></div><div class="row"><button id="refresh" class="secondary">Refresh queue</button><button id="analyze-all">Review unreviewed tickets</button><button id="stop" class="secondary" hidden>Stop after current</button><button id="lock" class="secondary">Lock</button></div></div>
<p class="notice">AI recommendations need your review. Replies and notes are editable drafts. Open Freshservice to send a reply, resolve, or close.</p>
<div class="columns"><aside class="panel"><label for="search">Find a ticket</label><input id="search" type="search" placeholder="Ticket number or subject"><div class="filters"><label>Sort<select id="sort"><option value="score">Attention first</option><option value="age_days">Oldest first</option><option value="idle_days">Longest idle</option><option value="priority">Highest priority</option></select></label><label>Recommendation<select id="filter"><option value="">All tickets</option><option value="resolve_candidate">Resolution candidates</option><option value="follow_up">Follow up</option><option value="investigate">Investigate</option><option value="blocked">Blocked</option><option value="escalate">Escalate</option><option value="unreviewed">Unreviewed</option></select></label></div><p class="muted">Attention score = priority + overdue SLA + capped age and idle days. It is not a closure score.</p><div id="queue"></div></aside>
<article id="detail" class="panel"><div class="empty"><span>↖</span><h2>Pick a ticket. Find the next step.</h2><p>Read the full conversation, then ask AI for a recommendation grounded in the evidence.</p></div></article></div></section>
</main><footer>Ticket review • v1.0 • No automatic ticket changes</footer><script src="review.js"></script></body></html>
