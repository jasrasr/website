<?php
/*
File Name: admin/index.php
Revision: 2.0.1
Description: Shared-identity protected admin console for editing fake chatbot rules and reviewing questions.
Author: Jason Lamb (with help from ChatGPT)
Created Date: 2026-11-20
Modified Date: 2026-11-22
Changelog: 2.0.1 - Remove punctuation from question-based rule suggestions. 2.0.0 - Add CRUD rules, ranked test preview and captured question review.
*/
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
$auth = \Jasr\Framework\SharedIdentity::connect();
$user = $auth->requireProject(FC_PROJECT, 'admin');
$error = '';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!fc_same_origin() || !$auth->validCsrf($_POST['csrf'] ?? null)) {
            throw new InvalidArgumentException('Invalid CSRF token.');
        }
        $action = (string)($_POST['action'] ?? '');
        $id = (string)($_POST['id'] ?? '');
        if ($action === 'save') {
            $newRule = fc_validate_rule($_POST, $id === '' ? null : $id);
            fc_update_rules(static function (array $rules) use ($newRule, $id): array {
                $found = false;
                foreach ($rules as $key => $rule) {
                    if (($rule['id'] ?? '') === $id && $id !== '') {
                        $rules[$key] = $newRule;
                        $found = true;
                    }
                }
                if ($id !== '' && !$found) throw new InvalidArgumentException('The rule was removed; reload the page.');
                if ($id === '') $rules[] = $newRule;
                return array_values($rules);
            });
            $notice = 'Rule saved. Best-match behavior changes immediately.';
        } elseif ($action === 'delete') {
            if ($id === '') throw new InvalidArgumentException('Select a rule.');
            fc_update_rules(static fn(array $rules): array =>
                array_values(array_filter($rules, static fn($rule): bool => ($rule['id'] ?? '') !== $id))
            );
            $notice = 'Rule deleted.';
        } else throw new InvalidArgumentException('Unsupported action.');
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Fake chatbot rule update failed: ' . $e->getMessage());
        $error = 'Unable to save rules; verify private storage and try again.';
    }
}
try {
    $rules = fc_rules();
    $history = \Jasr\Framework\JsonStore::read(fc_store('history.json'))['records'];
} catch (Throwable $e) {
    error_log('Fake chatbot admin read failed: ' . $e->getMessage());
    http_response_code(503);
    exit('Private chatbot data is unavailable.');
}
$recent = array_slice(array_reverse($history), 0, 120);
$unmatched = count(array_filter($recent, static fn($r): bool => empty($r['ruleId'])));
$target = null;
$edit = $_GET['edit'] ?? '';
if (is_string($edit)) foreach ($rules as $rule) {
    if (($rule['id'] ?? '') === $edit) {$target = $rule; break;}
}
$from = $_GET['from'] ?? '';
$initialQuestion = '';
if ($target === null && is_string($from) && $from !== '') foreach ($recent as $entry) {
    if (($entry['id'] ?? '') === $from) {
        $initialQuestion = (string)($entry['question'] ?? '');
        break;
    }
}
$normalizedQuestion = fc_normalize($initialQuestion);
$triggerValue = $target !== null ? implode("\n", array_map(static fn($t) => ($t['type'] ?? '') . ': ' . ($t['text'] ?? ''), $target['triggers'])) :
    ($normalizedQuestion !== '' ? 'exact: ' . $normalizedQuestion : '');
$replyValue = $target !== null ? implode("\n", $target['replies']) : '';
$preview = trim(is_string($_GET['test'] ?? null) ? $_GET['test'] : '');
$previewMatch = $preview === '' ? null : fc_match($preview, $rules);
usort($rules, static fn($a,$b): int => strcmp(strtolower((string)$a['name']), strtolower((string)$b['name'])));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fake AI Admin · JASR</title><link rel="stylesheet" href="../assets/style.css?v=2.0.0"></head>
<body><main class="app admin-app">
  <p><a href="../">Back to public portal</a> · <a href="../link-history.php">My account history</a></p>
  <h1>Fake AI Administration</h1>
  <p>Welcome, <?=fc_escape($user['name'] ?? 'administrator')?>. Control the illusion. There is no real AI, no model and no API key.</p>
  <?php if ($notice !== ''): ?><p class="notice" role="status"><?=fc_escape($notice)?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="error" role="alert"><?=fc_escape($error)?></p><?php endif; ?>
  <div class="stat-grid">
    <div class="stat"><strong><?=count($rules)?></strong><span>Response rules</span></div>
    <div class="stat"><strong><?=count($history)?></strong><span>Stored questions</span></div>
    <div class="stat"><strong><?=$unmatched?></strong><span>Unmatched in last <?=count($recent)?></span></div>
  </div>

  <section class="panel">
    <h2>Test your pretend brain</h2>
    <form method="get" class="stack">
      <label for="test">Question to rank</label>
      <input id="test" name="test" maxlength="500" value="<?=fc_escape($preview)?>" placeholder="Can you give me a potato salad recipe?">
      <button type="submit">Find best matching rule</button>
    </form>
    <?php if ($preview !== ''): ?>
      <p class="notice"><?php if ($previewMatch): ?>
        Best match: <strong><?=fc_escape($previewMatch['rule']['name'])?></strong>
        (<?=fc_escape($previewMatch['trigger']['type'])?>: <?=fc_escape($previewMatch['trigger']['text'])?>,
        score <?=fc_escape($previewMatch['score'])?>).
        Sample answer: <?=fc_escape($previewMatch['rule']['replies'][0] ?? '')?>
      <?php else: ?>No rule matched. The public portal will use a humorous fallback.<?php endif; ?></p>
    <?php endif; ?>
  </section>

  <section class="panel" id="editor">
    <h2><?=$target === null ? 'Add a ridiculous new answer' : 'Edit rule: ' . fc_escape($target['name'])?></h2>
    <p>Each trigger is one line prefixed by <code>exact:</code>, <code>phrase:</code>, <code>all:</code>, or <code>keyword:</code>. Exact beats phrase; phrase beats all-words; all-words beats a single keyword. Add multiple triggers to one rule for alternate phrasings. Each reply goes on a separate line; one is chosen randomly.</p>
    <form method="post" class="stack">
      <input type="hidden" name="csrf" value="<?=fc_escape($auth->csrf())?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?=fc_escape($target['id'] ?? '')?>">
      <label for="rule-name">Rule name</label>
      <input id="rule-name" name="name" maxlength="100" required value="<?=fc_escape($target['name'] ?? '')?>" placeholder="Potato salad / suspiciously excellent">
      <label for="rule-triggers">Matching triggers</label>
      <textarea id="rule-triggers" name="triggers" rows="5" required placeholder="phrase: potato salad&#10;all: potato salad&#10;exact: what is potato salad"><?=fc_escape($triggerValue)?></textarea>
      <label for="rule-replies">Comical answers (one per line)</label>
      <textarea id="rule-replies" name="replies" rows="5" required placeholder="One hilariously wrong answer per line."><?=fc_escape($replyValue)?></textarea>
      <label for="priority">Tie-break priority (-20 to 20; does not override match tier)</label>
      <input id="priority" name="priority" type="number" min="-20" max="20" value="<?=fc_escape($target['priority'] ?? 0)?>">
      <label class="check"><input name="enabled" value="1" type="checkbox" <?=($target === null || !empty($target['enabled'])) ? 'checked' : ''?>> Enable rule</label>
      <div class="actions"><button type="submit">Save rule</button><a class="buttonlink" href="index.php#editor">Add a different rule</a></div>
    </form>
  </section>

  <section class="panel">
    <h2>Active rule library</h2>
    <p>Rules are stored in a private JSON file using the shared framework. The tracked JSON seed is imported on first write and never overwrites your edits.</p>
    <div class="table-wrap"><table class="rules-table"><thead><tr><th>Rule</th><th>Triggers</th><th class="rule-status">Status</th><th>Actions</th></tr></thead><tbody>
      <?php foreach ($rules as $rule): ?>
        <tr><td><?=fc_escape($rule['name'])?></td>
        <td><small><?=fc_escape(implode(' · ', array_map(static fn($t) => $t['type'] . ': ' . $t['text'], $rule['triggers'])))?></small></td>
        <td class="rule-status"><?=!empty($rule['enabled']) ? 'Enabled' : 'Disabled'?></td>
        <td><a href="?edit=<?=rawurlencode((string)$rule['id'])?>#editor">Edit</a>
          <form method="post" class="inline-form">
            <input type="hidden" name="csrf" value="<?=fc_escape($auth->csrf())?>">
            <input type="hidden" name="id" value="<?=fc_escape($rule['id'])?>">
            <button type="submit" name="action" value="delete" class="small danger" data-confirm="Delete this response rule?">Delete</button>
          </form>
        </td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </section>
  <section class="panel">
    <h2>Recent visitor questions</h2>
    <p>Showing the latest 120 saved questions, anonymous and linked. Inspect unmatched ones to teach the bot a better response. Avoid sharing visitors' questions publicly.</p>
    <?php if ($recent === []): ?><p>No questions yet.</p><?php endif; ?>
    <div class="table-wrap"><table><thead><tr><th>When (UTC)</th><th>Question</th><th>Match at submission</th><th>Action</th></tr></thead><tbody>
      <?php foreach ($recent as $item): ?>
        <tr><td><small><?=fc_escape(gmdate('Y-m-d H:i', (int)($item['timestamp'] ?? 0)))?></small></td>
        <td><?=fc_escape($item['question'] ?? '')?></td>
        <td><?=empty($item['ruleId']) ? 'Unmatched' : fc_escape((string)$item['ruleId']) . ' (' . fc_escape($item['score'] ?? 0) . ')'?></td>
        <td><a href="?from=<?=rawurlencode((string)$item['id'])?>#editor">Create rule</a></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </section>
  <footer>Very Fake AI · Admin Rev 2.0.1 · <?=fc_escape(gmdate('Y-m-d H:i'))?> UTC</footer>
</main><script src="../assets/confirm.js?v=2.0.0" defer></script></body></html>
