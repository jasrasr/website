<?php
declare(strict_types=1);

/** Refill Tracker, revision 1.0.0. */
use Jasr\Framework\JsonStore;
use Jasr\Framework\SharedIdentity;

require_once __DIR__ . '/bootstrap.php';

function refill_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function refill_text(string $field, int $maxBytes): string
{
    $value = $_POST[$field] ?? null;
    $value = is_string($value) ? trim($value) : '';
    if ($value === '' || strlen($value) > $maxBytes || preg_match('/^[^\p{C}]+$/uD', $value) !== 1) {
        throw new InvalidArgumentException('Enter an item name and a unit within the allowed lengths.');
    }
    return $value;
}

function refill_count(string $field): int
{
    $value = filter_var($_POST[$field] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => 9999],
    ]);
    if ($value === false) {
        throw new InvalidArgumentException('Enter a whole number from 0 to 9,999.');
    }
    return $value;
}

function refill_change(array $records): array
{
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $records[] = [
            'id' => bin2hex(random_bytes(16)),
            'name' => refill_text('name', 120),
            'unit' => refill_text('unit', 40),
            'quantity' => refill_count('quantity'),
            'restockAt' => refill_count('restockAt'),
        ];
        return $records;
    }

    foreach ($records as $index => $item) {
        if ($item['id'] !== ($_POST['id'] ?? null)) {
            continue;
        }
        switch ($action) {
            case 'decrease':
                $item['quantity'] = max(0, $item['quantity'] - 1);
                break;
            case 'increase':
                $item['quantity'] = min(9999, $item['quantity'] + 1);
                break;
            case 'edit':
                $item['name'] = refill_text('name', 120);
                $item['unit'] = refill_text('unit', 40);
                $item['restockAt'] = refill_count('restockAt');
                break;
            default:
                throw new InvalidArgumentException('Choose a valid inventory action.');
        }
        $records[$index] = $item;
        return $records;
    }

    throw new InvalidArgumentException('That inventory item could not be found.');
}

header('Cache-Control: no-store');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    http_response_code(405);
    exit('Method not allowed.');
}

try {
    $auth = SharedIdentity::connect();
    $user = $auth->requireProject('refill-tracker', $method === 'POST' ? 'member' : 'viewer');
    if ($method === 'POST' && !$auth->validCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('Invalid form token.');
    }
    $canEdit = $auth->can('refill-tracker', 'member');
    $csrf = $canEdit ? refill_escape($auth->csrf()) : '';
    $path = refill_inventory_path((string) $user['id'], $_SERVER['DOCUMENT_ROOT'] ?? '');
    $error = null;
    if ($method === 'POST') {
        try {
            JsonStore::update($path, 'refill_change');
            header('Location: index.php', true, 303);
            exit;
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            $error = $exception->getMessage();
        }
    }
    $items = JsonStore::read($path)['records'];
    $restock = array_filter($items, static fn (array $item): bool => $item['quantity'] <= $item['restockAt']);
} catch (Throwable $exception) {
    error_log('Refill Tracker: ' . $exception->getMessage());
    http_response_code(503);
    exit('Refill Tracker is temporarily unavailable.');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Refill Tracker</title>
    <link rel="stylesheet" href="assets/css/app.css?v=1.0.0">
</head>
<body>
<main>
    <h1>Refill Tracker</h1>
    <?php if ($error !== null): ?>
        <p role="alert"><?= refill_escape($error) ?></p>
    <?php endif; ?>
    <section aria-labelledby="restock">
        <h2 id="restock">Restock</h2>
        <?php if (!$restock): ?>
            <p><?= $items ? 'Nothing needs restocking.' : 'Add supplies to start a restock list.' ?></p>
        <?php else: ?>
            <ul>
                <?php foreach ($restock as $item): ?>
                    <li><strong><?= refill_escape($item['name']) ?></strong> · <?= $item['quantity'] ?> on hand</li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <section aria-labelledby="inventory">
        <h2 id="inventory">Inventory</h2>
        <?php if ($canEdit): ?>
            <details>
                <summary>Add item</summary>
                <form method="post" class="fields">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                    <label>Item name <input name="name" required maxlength="120" placeholder="Toothpaste"></label>
                    <label>Unit <input name="unit" required maxlength="40" placeholder="tube"></label>
                    <label>Unopened spares <input name="quantity" type="number" min="0" max="9999" value="0" required></label>
                    <label>Restock at <input name="restockAt" type="number" min="0" max="9999" value="1" required></label>
                    <button name="action" value="add">Add item</button>
                </form>
            </details>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <?php $name = refill_escape($item['name']); $id = refill_escape($item['id']); ?>
            <article>
                <h3><?= $name ?></h3>
                <p><?= $item['quantity'] ?> in stock · unit: <?= refill_escape($item['unit']) ?> · restock at <?= $item['restockAt'] ?></p>
                <?php if ($canEdit): ?>
                    <form method="post" class="quantity">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button name="action" value="decrease" aria-label="Use one spare of <?= $name ?>" <?= $item['quantity'] === 0 ? 'disabled' : '' ?>>−1</button>
                        <button name="action" value="increase" aria-label="Add one spare of <?= $name ?>" <?= $item['quantity'] === 9999 ? 'disabled' : '' ?>>+1</button>
                    </form>
                    <details>
                        <summary>Edit <?= $name ?></summary>
                        <form method="post" class="fields">
                            <input type="hidden" name="csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <label>Item name <input name="name" required maxlength="120" value="<?= $name ?>"></label>
                            <label>Unit <input name="unit" required maxlength="40" value="<?= refill_escape($item['unit']) ?>"></label>
                            <label>Restock at <input name="restockAt" type="number" min="0" max="9999" required value="<?= $item['restockAt'] ?>"></label>
                            <button name="action" value="edit">Save changes</button>
                        </form>
                    </details>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
    <footer>Revision 1.0.0</footer>
</main>
</body>
</html>
