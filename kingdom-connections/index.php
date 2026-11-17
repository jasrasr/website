<?php
/** Filename: index.php | Revision: 1.0.0 | Author: Jason Lamb with ChatGPT
 * Created: 2026-11-17 | Modified: 2026-11-17 | Description: Public Christian business directory. */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
try { $businesses = $directory->all(); } catch (Throwable $e) { http_response_code(503); exit('The directory is temporarily unavailable. Please try again later.'); }
$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$skill = is_string($_GET['skill'] ?? null) ? $_GET['skill'] : '';
$id = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
$skills = []; foreach ($businesses as $b) $skills = array_merge($skills, $b['skills']);
$skills = array_unique($skills); sort($skills);
$results = array_filter($businesses, static fn(array $b): bool => ($skill === '' || in_array($skill, $b['skills'], true)) && ($q === '' || stripos(implode(' ', [$b['name'], $b['about'], $b['address'], implode(' ', $b['skills'])]), $q) !== false) && ($id === '' || $id === $b['id']));
if ($id !== '' && !$results) http_response_code(404);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><title>Kingdom Connections — Christian Business Directory</title><link rel="stylesheet" href="assets/css/app.css"></head><body>
<header><a class="brand" href="./">✦ Kingdom Connections</a><a href="admin.php">Manage directory ↗</a></header>
<main><section class="hero"><p class="eyebrow">FAITH • CRAFT • COMMUNITY</p><h1>Good work.<br>Shared faith.<br><em>Local connections.</em></h1><p>Discover Christian-based businesses by the skills you need. Meet the people, read their testimony, and connect directly.</p></section>
<section class="search-panel" aria-label="Search businesses"><form method="get"><label>What do you need?<input name="q" value="<?= h($q) ?>" placeholder="Business, skill, or location"></label><label>Browse a skill<select name="skill"><option value="">All skills</option><?php foreach ($skills as $tag): ?><option <?= $skill === $tag ? 'selected' : '' ?> value="<?= h($tag) ?>"><?= h(ucwords($tag)) ?></option><?php endforeach ?></select></label><button>Find a business</button><a href="./">Reset</a></form></section>
<div class="section-heading"><h2><?= $id ? 'Business profile' : 'Explore the directory' ?></h2><span><?= count($results) ?> business<?= count($results) === 1 ? '' : 'es' ?></span></div>
<div class="skills"><?php foreach ($skills as $tag): ?><a class="tag <?= $tag === $skill ? 'active' : '' ?>" href="?<?= h(http_build_query(['skill'=>$tag, 'q'=>$q])) ?>"><?= h(ucwords($tag)) ?></a><?php endforeach ?></div>
<?php if (!$results): ?><section class="empty"><h2><?= $businesses ? 'No matching businesses' : 'A community starts with a connection.' ?></h2><p><?= $businesses ? 'Try another skill or clear your search.' : 'The directory is ready for its first business. An administrator can add a listing to get started.' ?></p><a href="<?= $businesses ? './' : 'admin.php' ?>"><?= $businesses ? 'Show all businesses' : 'Add the first business →' ?></a></section><?php endif ?>
<div class="grid"><?php foreach ($results as $b): ?><article class="card">
<div class="company-heading"><?php if ($b['logo']): ?><img class="logo" src="<?= h($b['logo']) ?>" alt="<?= h($b['name']) ?> logo" loading="lazy"><?php else: ?><span class="logo monogram" aria-hidden="true"><?= h(strtoupper(substr($b['name'], 0, 1))) ?></span><?php endif ?><div><h2><a href="?id=<?= h($b['id']) ?>"><?= h($b['name']) ?></a></h2><p class="rating"><span aria-hidden="true">★</span> <?= h(ratingLabel($b)) ?></p></div></div>
<div class="skills"><?php foreach ($b['skills'] as $tag): ?><a class="tag" href="?skill=<?= h(rawurlencode($tag)) ?>"><?= h(ucwords($tag)) ?></a><?php endforeach ?></div>
<?php if ($b['about']): ?><h3>About the business</h3><p class="prose"><?= h($b['about']) ?></p><?php endif ?>
<div class="testimony"><h3>Our Christian testimony</h3><p class="prose"><?= h($b['testimony']) ?></p></div>
<?php if ($b['picture'] || $b['employee']): ?><div class="person"><?php if ($b['picture']): ?><img src="<?= h($b['picture']) ?>" alt="<?= h($b['employee'] ?: 'Business representative') ?>" loading="lazy"><?php endif ?><span><?= h($b['employee']) ?></span></div><?php endif ?>
<address><?php foreach (['address', 'phone'] as $field): if ($b[$field]): ?><p><?= h($b[$field]) ?></p><?php endif; endforeach ?><?php if ($b['email']): ?><p><a href="mailto:<?= h($b['email']) ?>"><?= h($b['email']) ?></a></p><?php endif ?><?php if ($b['website']): ?><a class="button secondary" href="<?= h($b['website']) ?>" target="_blank" rel="noopener noreferrer">Visit website ↗</a><?php endif ?></address>
<?php if ($b['ratings']): ?><details><summary>Read ratings (<?= count($b['ratings']) ?>)</summary><p class="muted">Admin-recorded feedback; completed services have not been independently verified.</p><?php foreach ($b['ratings'] as $r): ?><blockquote><strong><?= h($r['stars']) ?>/5 stars</strong><p class="prose"><?= h($r['comment']) ?></p><small><?= h(substr($r['createdAt'], 0, 10)) ?> · Admin-recorded</small></blockquote><?php endforeach ?></details><?php endif ?>
</article><?php endforeach ?></div><aside class="note">Faith statements are supplied by each business. Customer connection requests and reviews linked to completed services are planned for a future release.</aside></main><footer>Kingdom Connections · A working name, a growing community.<span>Revision 1.0.0 · November 17, 2026</span></footer></body></html>
