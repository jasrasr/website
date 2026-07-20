<?php
$config = require __DIR__ . '/config.php';
$versionPath = __DIR__ . '/VERSION.txt';
$revision = is_file($versionPath) ? trim((string)file_get_contents($versionPath)) : 'unknown';
$modifiedDate = 'Jul 20, 2026 6:16:32 PM EDT';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=htmlspecialchars($config['app_name'])?></title><link rel="stylesheet" href="assets/app.css"></head>
<body><main class="shell"><div class="card"><div class="brand"><?=htmlspecialchars($config['app_name'])?></div><p class="muted">Live team trivia with synchronized questions, countdowns, and speed-based scoring.</p></div>
<div class="grid two"><section class="card"><h2>Join a game</h2><form id="join"><label>Invite code<input name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" required autocomplete="one-time-code" placeholder="123456"></label><br><label>Team name<input name="team_name" maxlength="30" required></label><br><label>Team color<input name="color" type="color" value="#3366ff"></label><br><button>Join game</button></form><p id="error" class="status wrong hidden"></p></section>
<section class="card"><h2>Host controls</h2><p>Create reusable quizzes, launch rounds, reveal answers, and control the shared display.</p><a class="button" href="admin.php">Open admin</a></section></div>
<p class="muted" style="text-align:center;margin-top:1rem">Rev <?=htmlspecialchars($revision)?> • Modified <?=htmlspecialchars($modifiedDate)?></p></main>
<script src="assets/app.js"></script><script>
document.getElementById('join').addEventListener('submit',async e=>{e.preventDefault();const d=Object.fromEntries(new FormData(e.target));try{await api('join',d);location.href='play.php?code='+encodeURIComponent(d.code)}catch(err){const el=document.getElementById('error');el.textContent=err.message;el.classList.remove('hidden')}});
</script></body></html>