<?php
/** Filename: admin.php | Revision: 1.0.0 | Author: Jason Lamb with ChatGPT
 * Created: 2026-11-17 | Modified: 2026-11-17 | Description: Shared-identity protected directory administration. */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
try { $auth = \Jasr\Framework\SharedIdentity::connect(); $user = $auth->requireProject('kingdom-connections', 'admin'); }
catch (Throwable $e) { http_response_code(503); exit('Directory administration is unavailable. Check shared user-management configuration.'); }
header('Cache-Control: no-store');
$error = ''; $values = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validCsrf($_POST['csrf'] ?? null)) { http_response_code(403); $error = 'Your form expired. Reload this page before saving.'; }
    else {
        try {
            if (($_POST['action'] ?? '') === 'save') $id = $directory->save($_POST, $user['id']);
            elseif (($_POST['action'] ?? '') === 'rate') { $directory->rate($_POST, $user['id']); $id = KingdomDirectory::text($_POST, 'id', 32, true); }
            else throw new InvalidArgumentException('Unknown action.');
            header('Location: admin.php?' . http_build_query(['edit'=>$id, 'saved'=>'1']), true, 303); exit;
        } catch (InvalidArgumentException $e) { http_response_code(422); $error = $e->getMessage(); $values = $_POST; }
        catch (Throwable $e) { http_response_code(503); $error = 'Unable to save. Please try again; contact the administrator if this continues.'; $values = $_POST; }
    }
}
try { $businesses = $directory->all(); } catch (Throwable $e) { http_response_code(503); exit('Directory storage is unavailable. No data has been changed.'); }
$edit = is_string($_GET['edit'] ?? null) ? $_GET['edit'] : '';
$existing = null; foreach ($businesses as $b) if ($b['id'] === $edit) $existing = $b;
if (!$values && $existing) { $values = $existing; $values['skills'] = implode(', ', $existing['skills']); }
if ($edit && !$existing) { http_response_code(404); $error = 'Listing not found.'; }
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Manage Kingdom Connections</title><link rel="stylesheet" href="assets/css/app.css"></head><body><header><a class="brand" href="./">✦ Kingdom Connections</a><a href="./">View directory ↗</a></header><main><p class="eyebrow">DIRECTORY ADMINISTRATION</p><h1>Build the community.</h1><p>Published listings and recorded feedback are visible to everyone.</p>
<?php if ($error): ?><p class="alert" role="alert"><?= h($error) ?></p><?php elseif (isset($_GET['saved'])): ?><p class="notice" role="status">Saved successfully.</p><?php endif ?>
<div class="admin-layout"><nav class="card" aria-label="Manage listings"><h2>Businesses</h2><a class="button secondary" href="admin.php">+ New business</a><?php foreach ($businesses as $b): ?><p><a href="?edit=<?= h($b['id']) ?>"><?= h($b['name']) ?></a></p><?php endforeach ?></nav><section class="card"><h2><?= $existing ? 'Edit business' : 'Add a business' ?></h2><form method="post" class="editor"><input type="hidden" name="csrf" value="<?= h($auth->csrf()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= h($values['id'] ?? '') ?>">
<?php foreach (['name'=>['Company name',160,true], 'skills'=>['Skills (comma separated, e.g. plumbing, electrical)',1000,true], 'employee'=>['Employee / representative name',160,false], 'address'=>['Company address / service area',500,false], 'phone'=>['Phone',80,false], 'email'=>['Email',254,false], 'website'=>['Company website URL',2048,false], 'logo'=>['Company logo URL',2048,false], 'picture'=>['Employee picture URL',2048,false]] as $field=>$spec): ?><label><?= h($spec[0]) ?><input name="<?= h($field) ?>" maxlength="<?= $spec[1] ?>" <?= $spec[2] ? 'required' : '' ?> type="<?= in_array($field, ['website','logo','picture'], true) ? 'url' : ($field === 'email' ? 'email' : 'text') ?>" value="<?= h(is_string($values[$field] ?? null) ? $values[$field] : '') ?>"></label><?php endforeach ?>
<label>About the business<textarea name="about" rows="4" maxlength="6000"><?= h(is_string($values['about'] ?? null) ? $values['about'] : '') ?></textarea></label><label>Christian testimony (required)<textarea name="testimony" rows="6" maxlength="6000" required><?= h(is_string($values['testimony'] ?? null) ? $values['testimony'] : '') ?></textarea></label><p class="muted">Use public HTTP(S) image URLs for photos and logos. Obtain permission before publishing contact details, images, and testimonies.</p><button>Save business</button></form>
<?php if ($existing): ?><hr><h2>Record service feedback</h2><p>Ratings are labeled admin-recorded. Customer submissions and completed-service verification are planned for a future release.</p><p class="rating"><?= h(ratingLabel($existing)) ?></p><form method="post" class="editor"><input type="hidden" name="csrf" value="<?= h($auth->csrf()) ?>"><input type="hidden" name="action" value="rate"><input type="hidden" name="id" value="<?= h($existing['id']) ?>"><label>Star rating<select name="stars" required><option value="">Choose a rating</option><?php for ($n=1;$n<=5;$n++): ?><option value="<?= $n ?>"><?= $n ?> <?= str_repeat('★', $n) ?></option><?php endfor ?></select></label><label>Feedback<textarea name="comment" rows="3" maxlength="2000" required></textarea></label><button>Record rating</button></form><?php endif ?></section></div></main><footer>Kingdom Connections<span>Revision 1.0.0 · November 17, 2026</span></footer></body></html>
