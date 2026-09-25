<?php
declare(strict_types=1);
require_once __DIR__ . '/linking-bootstrap.php';
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$error = ''; $preview = null;
try {
    $auth = jasr_users_auth(); $actor = $auth->user();
    if (!$actor) { header('Location: ' . $auth->portal(), true, 303); exit; }
    if (!$actor['admin'] || $actor['mustChangePassword']) { http_response_code(403); exit('Site administrator access required.'); }
    $registry = jasr_linking_adapters();
    $project = $_POST['project'] ?? $_GET['project'] ?? 'finances';
    if (!is_string($project) || !isset($registry[$project])) { http_response_code(404); exit('Unknown linking adapter.'); }
    $links = $registry[$project];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$auth->validCsrf($_POST['csrf'] ?? null)) { http_response_code(403); exit('Invalid form token.'); }
        foreach ($_POST as $value) if (!is_string($value)) throw new InvalidArgumentException('Invalid form data.');
        $action = $_POST['action'] ?? '';
        if ($action === 'preview') {
            unset($_SESSION['link_preview']);
            $preview = $links->preview($actor['id'], $actor['version'], $_POST['legacy_id'] ?? '', $_POST['target_id'] ?? '', $_POST['reviewed_role'] ?? '');
            $_SESSION['link_preview'] = ['plan' => $preview, 'actorId' => $actor['id'], 'version' => $actor['version'],
                'expires' => time() + 600, 'nonce' => bin2hex(random_bytes(24))];
        } elseif ($action === 'apply') {
            $pending = $_SESSION['link_preview'] ?? null;
            unset($_SESSION['link_preview']); // One attempt, including failures: preview again.
            if (!$pending || $pending['expires'] < time() || $pending['actorId'] !== $actor['id'] ||
                $pending['version'] !== $actor['version'] || $pending['plan']['project'] !== $project ||
                !hash_equals($pending['nonce'], $_POST['nonce'] ?? '') || ($_POST['confirm'] ?? '') !== 'yes') {
                throw new InvalidArgumentException('Preview expired or confirmation missing. Run preview again.');
            }
            $links->apply($actor['id'], $actor['version'], $pending['plan']);
            $_SESSION['link_notice'] = 'Account linked. Existing data and passwords were not changed. A private backup was saved.';
            header('Location: ' . $auth->portal() . 'links.php', true, 303); exit;
        } elseif ($action !== 'validate') throw new InvalidArgumentException('Unknown action.');
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400); $error = $e->getMessage();
} catch (Throwable $e) {
    error_log('Account linking: ' . $e->getMessage()); http_response_code(503);
    exit('Linking is unavailable. No migration should be attempted until configuration and storage are checked.');
}
try {
    $rows = $links->validate($actor['id'], $actor['version']);
    $state = $auth->directory->read();
    $notice = $_SESSION['link_notice'] ?? ''; unset($_SESSION['link_notice']);
} catch (Throwable $e) {
    error_log('Account validation: ' . $e->getMessage()); http_response_code(503); exit('Cannot validate account links. Check private storage and configuration.');
}
function form(string $action): void
{
    global $auth, $project;
    echo '<form method="post" action="' . e($auth->portal()) . 'links.php"><input type="hidden" name="csrf" value="' . e($auth->csrf()) . '"><input type="hidden" name="project" value="' . e($project) . '"><input type="hidden" name="action" value="' . e($action) . '">';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Link existing accounts</title><link rel="stylesheet" href="<?= e($auth->portal()) ?>style.css"></head><body><main>
<header><p class="eyebrow">JASR · Account migration</p><h1>Link existing accounts</h1><p><a href="<?= e($auth->portal()) ?>">Back to User Management</a></p></header>
<?php if ($error): ?><p class="notice" role="alert"><?= e($error) ?></p><?php endif ?>
<?php if ($notice): ?><p class="notice" role="status"><?= e($notice) ?></p><?php endif ?>
<section><h2>Finances pilot</h2><p>Connect one existing budget account to one central account. Review the person and account carefully: matching names are not proof of ownership.</p><p>Preview and validation do not change budgets or mappings. Applying a link saves a private backup first. Existing filenames and contents stay in place.</p><p>Register <code>finances</code> in User Management first. Linking adds missing project access at the reviewed permission. Existing lower permissions are preserved. Login mode is changed separately.</p></section>
<section><h2>Dry-run validation</h2><p>Review every account before switching Finances to shared login. “Not linked” accounts will be inaccessible through shared login.</p>
<?php form('validate'); ?><button>Refresh dry-run report</button></form>
<div class="table-scroll"><table><thead><tr><th>Existing account</th><th>Central account</th><th>Status</th><th>Details</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= e($row['legacyId']) ?></td><td><?php foreach ($row['targets'] as $id): ?><?= e($state['users'][$id]['username'] ?? $id) ?><br><?php endforeach ?></td><td><?= e($row['status']) ?></td><td><?= e(implode(' ', $row['errors'])) ?></td></tr><?php endforeach ?>
</tbody></table></div></section>
<section><h2>Preview an account link</h2><?php form('preview'); ?><label>Existing Finances account <select name="legacy_id" required><option value="">Choose explicitly</option><?php foreach ($rows as $row): if ($row['status'] !== 'Not linked') continue; ?><option value="<?= e($row['legacyId']) ?>"><?= e($row['legacyId']) ?></option><?php endforeach ?></select></label><label>Central user <select name="target_id" required><option value="">Choose explicitly</option><?php foreach ($state['users'] as $u): if (!$u['active']) continue; ?><option value="<?= e($u['id']) ?>"><?= e($u['name'] . ' (@' . $u['username'] . ')') ?></option><?php endforeach ?></select></label><label>Permission review (Finances has no legacy role)<select name="reviewed_role" required><option value="">Check permissions and choose</option><option value="viewer">Read-only (viewer)</option><option value="member">Edit own budget (member)</option></select></label><button>Preview only — no changes</button></form></section>
<?php if ($preview): ?><section><h2>Confirm this mapping</h2><p>Existing account: <strong><?= e($preview['legacyId']) ?></strong></p><p>Central user: <strong><?= e($preview['targetName'] . ' (@' . $preview['targetUsername'] . ')') ?></strong></p><p>Central ID: <code><?= e($preview['targetId']) ?></code></p><p>Existing central permission: <strong><?= e($preview['existingRole'] ?? 'No access') ?></strong>. Linked permission: <strong><?= e($preview['effectiveRole']) ?></strong>. The link limits access even for a super administrator.</p><p>This central account will use the existing budget when shared login is enabled. A changed budget or account invalidates this preview. Preview expires in 10 minutes.</p><?php form('apply'); ?><input type="hidden" name="nonce" value="<?= e($_SESSION['link_preview']['nonce']) ?>"><label class="check"><input type="checkbox" name="confirm" value="yes" required> I verified that this person should own this existing account.</label><button>Back up and apply this link</button></form></section><?php endif ?>
<footer>Account linking · Finances pilot · No automatic matching</footer></main></body></html>
