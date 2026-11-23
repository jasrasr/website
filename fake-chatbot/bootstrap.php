<?php
/*
File Name: bootstrap.php
Revision: 2.0.2
Description: Framework-backed fake chatbot rule matching, private history and opt-in account linking.
Author: Jason Lamb (with help from ChatGPT)
Created Date: 2026-11-20
Modified Date: 2026-11-22
Changelog: 2.0.2 - Find existing rules with equivalent triggers. 2.0.1 - Enforce distinct multiword all/phrase rule tiers and preserve empty edited rule libraries. 2.0.0 - Add ranked matching, JsonStore persistence, privacy controls, and shared-identity helpers.
*/
declare(strict_types=1);
require_once dirname(__DIR__) . '/1-Framework/bootstrap.php';

const FC_PROJECT = 'fake-chatbot';
const FC_COOKIE = 'JASR_FAKE_CHAT';
const FC_MAX_QUESTION_BYTES = 1200;
const FC_MAX_HISTORY = 20000;

function fc_config(): array
{
    static $config;
    if (isset($config)) return $config;
    $config = require __DIR__ . '/config.local.example.php';
    if (is_file(__DIR__ . '/config.local.php')) {
        $private = require __DIR__ . '/config.local.php';
        if (!is_array($private)) throw new RuntimeException('Invalid chatbot configuration.');
        $config = array_replace($config, $private);
    }
    return $config;
}
function fc_store(string $file): string
{
    if (!in_array($file, ['rules.json','history.json','links.json','limits.json'], true)) {
        throw new InvalidArgumentException('Invalid storage key.');
    }
    $root = (string) fc_config()['storage_path'];
    if (!is_dir($root) || !is_writable($root)) {
        throw new RuntimeException('Private chatbot storage is unavailable.');
    }
    return rtrim($root, '/\\') . '/' . $file;
}
function fc_escape(mixed $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function fc_browser_hash(): string
{
    $token = $_COOKIE[FC_COOKIE] ?? '';
    if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
        $token = bin2hex(random_bytes(32));
        $base = (string) fc_config()['base_path'];
        if ($base === '' || $base[0] !== '/' || !str_ends_with($base, '/')) {
            throw new RuntimeException('Invalid chatbot base path.');
        }
        setcookie(FC_COOKIE, $token, [
            'expires' => time() + 90 * 86400,
            'path' => $base,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[FC_COOKIE] = $token;
    }
    return hash('sha256', $token);
}
function fc_normalize(string $s): string
{
    $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    $clean = preg_replace('/[^\p{L}\p{N}]+/u', ' ', trim($s));
    return preg_replace('/\s+/u', ' ', trim($clean ?? '')) ?? '';
}
function fc_existing_trigger_rule_id(array $rules, string $type, string $text): ?string
{
    $normalized = fc_normalize($text);
    if ($normalized === '') return null;
    foreach ($rules as $rule) {
        if (!is_array($rule) || !is_string($rule['id'] ?? null)) continue;
        foreach ($rule['triggers'] ?? [] as $trigger) {
            if (is_array($trigger) && ($trigger['type'] ?? null) === $type &&
                is_string($trigger['text'] ?? null) && fc_normalize($trigger['text']) === $normalized) {
                return $rule['id'];
            }
        }
    }
    return null;
}
function fc_rules(): array
{
    $path = fc_store('rules.json');
    if (is_file($path)) return \Jasr\Framework\JsonStore::read($path)['records'];
    $seed = json_decode((string)file_get_contents(__DIR__ . '/data/prompts.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($seed) || !is_array($seed['rules'] ?? null)) throw new RuntimeException('Rule seed is invalid.');
    return $seed['rules'];
}
function fc_update_rules(callable $change): void
{
    $path = fc_store('rules.json');
    $exists = is_file($path);
    $seed = $exists ? [] : fc_rules();
    \Jasr\Framework\JsonStore::update($path, static function (array $rules) use ($change, $seed, $exists): array {
        return $change($exists || $rules !== [] ? $rules : $seed);
    });
}
function fc_trigger_score(string $question, string $kind, string $raw, int $priority): ?int
{
    $needle = fc_normalize($raw);
    if ($needle === '' || $question === '') return null;
    $words = array_values(array_unique(array_filter(explode(' ', $needle), 'strlen')));
    $boundaryHay = ' ' . $question . ' ';
    $whole = str_contains($boundaryHay, ' ' . $needle . ' ');
    $tier = match ($kind) {
        'exact' => $question === $needle ? 4 : null,
        'phrase' => $whole ? 3 : null,
        'all' => count($words) > 1 && count(array_filter($words, static fn(string $word): bool => str_contains($boundaryHay, ' ' . $word . ' '))) === count($words) ? 2 : null,
        'keyword' => count($words) === 1 && $whole ? 1 : null,
        default => null
    };
    if ($tier === null) return null;
    return $tier * 100000 + (min(300, count($words) * 35) + min(99, strlen($needle))) * 41 + max(-20, min(20, $priority));
}
function fc_match(string $question, array $rules): ?array
{
    $q = fc_normalize($question);
    $best = null;
    foreach ($rules as $rule) {
        if (!is_array($rule) || empty($rule['enabled']) || !is_array($rule['triggers'] ?? null) || !is_array($rule['replies'] ?? null) || empty($rule['replies'])) continue;
        foreach ($rule['triggers'] as $trigger) {
            if (!is_array($trigger) || !is_string($trigger['text'] ?? null) || !is_string($trigger['type'] ?? null)) continue;
            $score = fc_trigger_score($q, $trigger['type'], $trigger['text'], (int)($rule['priority'] ?? 0));
            if ($score === null) continue;
            $candidate = ['rule' => $rule, 'score' => $score, 'trigger' => $trigger];
            if ($best === null || $score > $best['score'] ||
                ($score === $best['score'] && strcmp((string)$rule['id'], (string)$best['rule']['id']) < 0)) {
                $best = $candidate;
            }
        }
    }
    return $best;
}
function fc_expand(string $reply): string
{
    $now = new DateTimeImmutable('now', new DateTimeZone('America/New_York'));
    return strtr($reply, ['{{time}}' => $now->format('g:i A T'), '{{date}}' => $now->format('F j, Y')]);
}
function fc_answer(string $question, array $rules): array
{
    $matched = fc_match($question, $rules);
    if ($matched !== null) {
        $rule = $matched['rule'];
        $replies = array_values(array_filter($rule['replies'], 'is_string'));
        $text = $replies[random_int(0, count($replies) - 1)];
        return ['answer' => fc_expand($text), 'ruleId' => $rule['id'], 'rule' => $rule['name'], 'score' => $matched['score'], 'trigger' => $matched['trigger']];
    }
    $fallback = [
        'My imaginary neurons held a meeting and have decided to circle back.',
        'I searched my entirely fictional neural network. It was on lunch break.',
        'That question exceeded my fake intelligence budget of zero dollars.',
        'After considering your question, I have determined that I should not be in charge.',
        'The answer is probably in a manual. I have chosen not to read it.'
    ];
    return ['answer' => $fallback[random_int(0, count($fallback) - 1)], 'ruleId' => null, 'rule' => null, 'score' => 0, 'trigger' => null];
}
function fc_validate_rule(array $input, ?string $existingId = null): array
{
    $name = trim((string)($input['name'] ?? ''));
    if ($name === '' || strlen($name) > 100) throw new InvalidArgumentException('Give the rule a name (100 bytes maximum).');
    $triggerText = (string)($input['triggers'] ?? '');
    $lines = preg_split('/\r?\n/', $triggerText) ?: [];
    $triggers = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (!preg_match('/^(exact|phrase|all|keyword)\s*:\s*(.+)$/iD', $line, $m)) {
            throw new InvalidArgumentException('Each trigger must start with exact:, phrase:, all:, or keyword:.');
        }
        $type = strtolower($m[1]);
        $text = trim($m[2]);
        if (strlen($text) > 120 || fc_normalize($text) === '') throw new InvalidArgumentException('Trigger text must be 1 to 120 bytes.');
        if ($type === 'keyword' && count(explode(' ', fc_normalize($text))) !== 1) throw new InvalidArgumentException('keyword: must contain one word.');
        if ($type === 'phrase' && count(explode(' ', fc_normalize($text))) < 2) throw new InvalidArgumentException('phrase: must contain at least two words.');
        if ($type === 'all' && count(array_unique(explode(' ', fc_normalize($text)))) < 2) throw new InvalidArgumentException('all: must include at least two distinct words.');
        $triggers[] = ['type' => $type, 'text' => $text];
    }
    if (count($triggers) < 1 || count($triggers) > 15) throw new InvalidArgumentException('Provide between 1 and 15 triggers.');
    $replies = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string)($input['replies'] ?? '')) ?: []), static fn(string $s): bool => $s !== ''));
    if (count($replies) < 1 || count($replies) > 8) throw new InvalidArgumentException('Provide between 1 and 8 answers, one per line.');
    foreach ($replies as $reply) if (strlen($reply) > 750) throw new InvalidArgumentException('Each answer must be at most 750 bytes.');
    $priority = filter_var($input['priority'] ?? 0, FILTER_VALIDATE_INT);
    if ($priority === false || $priority < -20 || $priority > 20) throw new InvalidArgumentException('Priority must be from -20 to 20.');
    return [
        'id' => $existingId ?? ('rule-' . bin2hex(random_bytes(6))),
        'name' => $name,
        'triggers' => $triggers,
        'replies' => $replies,
        'priority' => $priority,
        'enabled' => !empty($input['enabled']),
    ];
}
function fc_recent(array $records, string $session, ?string $userId = null): array
{
    $cutoff = time() - 90 * 86400;
    $result = array_values(array_filter($records, static function ($r) use ($session, $userId, $cutoff): bool {
        if (!is_array($r)) return false;
        return ($userId !== null && ($r['userId'] ?? null) === $userId) ||
            (($r['session'] ?? '') === $session && empty($r['userId']) && (int)($r['timestamp'] ?? 0) >= $cutoff);
    }));
    return array_slice(array_reverse($result), 0, 100);
}
function fc_history_for_browser(string $session): array
{
    return fc_recent(\Jasr\Framework\JsonStore::read(fc_store('history.json'))['records'], $session);
}
function fc_rate_limit(string $session): bool
{
    $allowed = true;
    $now = time();
    $config = fc_config();
    \Jasr\Framework\JsonStore::update(fc_store('limits.json'), static function (array $r) use ($session, $now, $config, &$allowed): array {
        foreach ($r as $key => $item) {
            if (!is_array($item)) {unset($r[$key]); continue;}
            $item = array_values(array_filter($item, static fn($t): bool => is_int($t) && $t > $now - 86400));
            if ($item === []) unset($r[$key]);
            else $r[$key] = $item;
        }
        foreach ([$session, '_global'] as $key) {
            $values = $r[$key] ?? [];
            $perMinute = count(array_filter($values, static fn(int $t): bool => $t > $now - 60));
            $daily = count($values);
            $minuteMax = $key === '_global' ? 120 : (int)$config['per_minute'];
            $dailyMax = $key === '_global' ? 6000 : (int)$config['per_day'];
            if ($perMinute >= $minuteMax || $daily >= $dailyMax) $allowed = false;
        }
        if ($allowed) foreach ([$session, '_global'] as $key) $r[$key][] = $now;
        return $r;
    });
    return $allowed;
}
function fc_record(string $session, string $question, array $result, ?string $userId = null): void
{
    $now = time();
    $anonymousCutoff = $now - 90 * 86400;
    \Jasr\Framework\JsonStore::update(fc_store('history.json'), static function (array $r) use ($session, $question, $result, $userId, $anonymousCutoff): array {
        $r = array_values(array_filter($r, static fn($row): bool =>
            is_array($row) && (!empty($row['userId']) || (int)($row['timestamp'] ?? 0) >= $anonymousCutoff)
        ));
        $r[] = [
            'id' => bin2hex(random_bytes(12)),
            'session' => $session,
            'userId' => $userId,
            'timestamp' => time(),
            'question' => $question,
            'answer' => $result['answer'],
            'ruleId' => $result['ruleId'],
            'score' => $result['score']
        ];
        return count($r) > FC_MAX_HISTORY ? array_slice($r, -FC_MAX_HISTORY) : $r;
    });
}
function fc_linked_user(string $session): ?string
{
    $links = \Jasr\Framework\JsonStore::read(fc_store('links.json'))['records'];
    $linked = $links[$session]['userId'] ?? null;
    if (!is_string($linked) || $linked === '') return null;
    // Anonymous chatting works even if the central identity provider is temporarily down.
    try {
        $auth = \Jasr\Framework\SharedIdentity::connect();
        $user = $auth->user();
        if ($user !== null && empty($user['mustChangePassword']) && $linked === ($user['id'] ?? null)) return $linked;
    } catch (Throwable $exception) {
        error_log('Fake Chatbot: optional linked identity unavailable.');
    }
    return null;
}
function fc_link_history_records(array $records, string $session, string $uid, int $cutoff): array
{
    $count = 0;
    $records = array_values(array_filter($records, static fn($r): bool =>
        is_array($r) && (!empty($r['userId']) || (int)($r['timestamp'] ?? 0) >= $cutoff)
    ));
    foreach ($records as &$r) {
        if (($r['session'] ?? null) === $session && empty($r['userId'])) {
            $r['userId'] = $uid;
            $count++;
        }
    }
    unset($r);
    return ['records' => $records, 'count' => $count];
}
function fc_link_history(string $session, string $uid): int
{
    $count = 0;
    $cutoff = time() - 90 * 86400;
    \Jasr\Framework\JsonStore::update(fc_store('history.json'), static function (array $records) use ($session, $uid, $cutoff, &$count): array {
        $result = fc_link_history_records($records, $session, $uid, $cutoff);
        $count = $result['count'];
        return $result['records'];
    });
    \Jasr\Framework\JsonStore::update(fc_store('links.json'), static function (array $records) use ($session, $uid): array {
        $records[$session] = ['userId' => $uid, 'linkedAt' => gmdate(DATE_ATOM)];
        return $records;
    });
    return $count;
}
function fc_unlink_history(string $session): void
{
    \Jasr\Framework\JsonStore::update(fc_store('links.json'), static function (array $records) use ($session): array {
        unset($records[$session]);
        return $records;
    });
}
function fc_delete_owned_history(string $session, string $uid): int
{
    $removed = 0;
    \Jasr\Framework\JsonStore::update(fc_store('history.json'), static function (array $records) use ($uid, &$removed): array {
        $new = array_values(array_filter($records, static fn($r): bool => ($r['userId'] ?? null) !== $uid));
        $removed = count($records) - count($new);
        return $new;
    });
    fc_unlink_history($session);
    return $removed;
}
function fc_same_origin(): bool
{
    $site = (string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
    if ($site === 'cross-site') return false;
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!is_string($origin) || $origin === '') return true;
    $source = parse_url($origin);
    $host = parse_url('http://' . (string)($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    return is_array($source) && strtolower((string)($source['host'] ?? '')) === strtolower((string)$host)
        && in_array((string)($source['scheme'] ?? ''), ['https','http'], true);
}
function fc_json(int $status, array $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}
