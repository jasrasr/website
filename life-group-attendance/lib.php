<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function json_file(string $name): string { return DATA_DIR . '/' . $name . '.json'; }

function read_store(string $name): array {
    $path = json_file($name);
    if (!is_file($path)) return [];
    $raw = file_get_contents($path);
    $data = $raw === false ? null : json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function write_store(string $name, array $data): void {
    if (!is_dir(DATA_DIR) && !mkdir(DATA_DIR, 0750, true) && !is_dir(DATA_DIR)) {
        throw new RuntimeException('Unable to create data directory.');
    }
    $path = json_file($name);
    $tmp = $path . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to save data.');
    }
}

function uuid(): string { return bin2hex(random_bytes(16)); }
function now_iso(): string { return date(DATE_ATOM); }
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}
function user(): ?array { return $_SESSION['user'] ?? null; }
function require_user(): array {
    $u = user();
    if (!$u) json_out(['error' => 'Authentication required.'], 401);
    return $u;
}
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function require_csrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) json_out(['error' => 'Session token expired. Refresh and try again.'], 419);
}
function json_out(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}
function audit(string $action, string $entity, string $entityId, array $detail = []): void {
    $rows = read_store('audit');
    $rows[] = ['id'=>uuid(),'at'=>now_iso(),'userId'=>user()['id'] ?? null,'action'=>$action,'entity'=>$entity,'entityId'=>$entityId,'detail'=>$detail];
    if (count($rows) > 5000) $rows = array_slice($rows, -5000);
    write_store('audit', $rows);
}
function public_student(array $s): array {
    unset($s['notes']);
    return $s;
}

