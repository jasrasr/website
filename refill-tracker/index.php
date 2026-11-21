<?php
declare(strict_types=1);

/** Refill Tracker, revision 1.5.1. */
use Jasr\Framework\JsonStore;
use Jasr\Framework\SharedIdentity;

require_once __DIR__ . '/bootstrap.php';

function refill_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function refill_text(string $field, int $maxBytes, bool $optional = false): string
{
    $value = $_POST[$field] ?? null;
    if (!is_string($value)) {
        throw new InvalidArgumentException('Enter valid text in the form.');
    }
    $value = trim($value);
    if ($optional && $value === '') {
        return '';
    }
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

function refill_purchase(int $packs): array
{
    $unitsPerPack = ($_POST['unitsPerPack'] ?? '') === '' ? 1 : refill_count('unitsPerPack');
    if ($unitsPerPack < 1) {
        throw new InvalidArgumentException('Enter at least one unit per pack.');
    }

    $seller = isset($_POST['seller']) ? refill_text('seller', 120, true) : '';
    $cost = $_POST['totalCost'] ?? '';
    $cost = is_string($cost) ? trim($cost) : null;
    if ($cost !== '' && (!is_string($cost) || preg_match('/^([0-9]{1,7})(?:\.([0-9]{1,2}))?$/D', $cost, $parts) !== 1)) {
        throw new InvalidArgumentException('Enter a total price in dollars, such as 12.99.');
    }

    return [
        'seller' => $seller,
        'packs' => $packs,
        'unitsPerPack' => $unitsPerPack,
        'totalCostCents' => $cost === '' ? null : (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0'),
    ];
}

function refill_stock(array $item): array
{
    return $item['stock'] ?? ['Unassigned' => $item['quantity']];
}

function refill_pace(array $openings): float
{
    $count = count($openings);
    return $count < 2 ? 0.0 : ($openings[$count - 1]['at'] - $openings[0]['at']) / ($count - 1);
}

function refill_usage(array $openings): string
{
    $count = count($openings);
    if ($count < 2) {
        return 'Log at least two openings to estimate usage.';
    }
    $seconds = refill_pace($openings);
    if ($seconds <= 0) {
        return 'Log openings at different times to estimate usage.';
    }
    return 'About ' . number_format($seconds / 86400, 1) . ' days between openings';
}

function refill_details(): string
{
    return isset($_POST['details']) ? refill_text('details', 240, true) : '';
}

function refill_add_starters(array $records): array
{
    $starters = [
        '2% milk' => 'carton',
        '4% milk' => 'carton',
        'Toilet paper' => 'roll',
        'Toothpaste' => 'tube',
        'Kleenex / facial tissues' => 'box',
        'Paper towels' => 'roll',
        'Contact solution' => 'bottle',
        'Toothbrush' => 'brush',
        'Deodorant' => 'stick',
        'Dish soap' => 'bottle',
    ];
    $names = array_map('strtolower', array_column($records, 'name'));
    $added = array_column($records, 'starter');
    foreach ($starters as $name => $unit) {
        if (in_array(strtolower($name), $names, true) || in_array($name, $added, true)) {
            continue;
        }
        $records[] = [
            'id' => bin2hex(random_bytes(16)),
            'name' => $name,
            'unit' => $unit,
            'details' => '',
            'quantity' => 0,
            'restockAt' => 0,
            'stock' => [],
            'starter' => $name,
        ];
    }
    return $records;
}

function refill_receipt_number(mixed $value, int $wholeDigits, string $message): int
{
    if (!is_string($value)
        || preg_match('/^([0-9]{1,' . $wholeDigits . '})(?:\.([0-9]{1,2}))?$/D', trim($value), $parts) !== 1) {
        throw new InvalidArgumentException($message);
    }
    return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
}

function refill_receipt_units(mixed $value): int
{
    if (!is_string($value) || preg_match('/^[1-9][0-9]{0,3}$/D', $value) !== 1) {
        throw new InvalidArgumentException('Enter 1 to 9,999 packs and units per pack.');
    }
    return (int) $value;
}

function refill_add_receipt(array $records): array
{
    $seller = refill_text('seller', 120);
    $person = refill_text('person', 80);
    $rate = refill_receipt_number($_POST['taxRate'] ?? null, 3, 'Enter a tax rate from 0 to 100 with up to two decimal places.');
    $lines = $_POST['lines'] ?? null;
    if ($rate > 10000 || !is_array($lines) || count($lines) > 50) {
        throw new InvalidArgumentException('Use a tax rate from 0 to 100 and up to 50 receipt lines.');
    }
    $itemsById = array_column($records, null, 'id');
    $receiptId = bin2hex(random_bytes(16));
    $now = time();
    $subtotal = $taxableSubtotal = $tax = 0;
    $savedLines = [];
    foreach ($lines as $line) {
        if (is_array($line) && ($line['item'] ?? null) === '') {
            if (($line['location'] ?? '') !== '' || ($line['price'] ?? '') !== '' || ($line['packs'] ?? '1') !== '1' || ($line['unitsPerPack'] ?? '1') !== '1') {
                throw new InvalidArgumentException('Select an inventory item for each filled receipt line.');
            }
            continue;
        }
        $id = is_array($line) ? ($line['item'] ?? null) : null;
        if (!is_string($id) || !isset($itemsById[$id])) {
            throw new InvalidArgumentException('Select an inventory item for each receipt line.');
        }
        $location = is_string($line['location'] ?? null) ? trim($line['location']) : '';
        if ($location === '' || strlen($location) > 80 || preg_match('/^[^\p{C}]+$/uD', $location) !== 1) {
            throw new InvalidArgumentException('Enter a storage location for every receipt item.');
        }
        $packs = refill_receipt_units($line['packs'] ?? null);
        $unitsPerPack = refill_receipt_units($line['unitsPerPack'] ?? null);
        $units = $packs * $unitsPerPack;
        $price = refill_receipt_number($line['price'] ?? null, 7, 'Enter a valid pre-tax line total, such as 12.99.');
        $item = $itemsById[$id];
        $stock = refill_stock($item);
        if (array_sum($stock) + $units > 9999) {
            throw new InvalidArgumentException('A receipt item would exceed the limit of 9,999 spares.');
        }

        $taxable = ($line['taxable'] ?? '') === '1';
        $subtotal += $price;
        $taxableSubtotal += $taxable ? $price : 0;
        $nextTax = intdiv($taxableSubtotal * $rate + 5000, 10000);
        $lineTax = $nextTax - $tax;
        $tax = $nextTax;
        $stock[$location] = ($stock[$location] ?? 0) + $units;
        $item['stock'] = $stock;
        $item['quantity'] = array_sum($stock);
        $item['refills'][] = [
            'at' => $now,
            'storage' => $location,
            'quantity' => $units,
            'person' => $person,
            'purchase' => [
                'seller' => $seller,
                'packs' => $packs,
                'unitsPerPack' => $unitsPerPack,
                'totalCostCents' => $price + $lineTax,
                'receiptId' => $receiptId,
                'subtotalCents' => $price,
                'taxCents' => $lineTax,
                'taxable' => $taxable,
                'taxRate' => $rate,
            ],
        ];
        $itemsById[$id] = $item;
        $savedLines[] = [$id, array_key_last($item['refills'])];
    }
    if (!$savedLines) {
        throw new InvalidArgumentException('Add at least one receipt item.');
    }
    foreach ($savedLines as [$id, $index]) {
        $itemsById[$id]['refills'][$index]['purchase']['receiptTotalCents'] = $subtotal + $tax;
        $itemsById[$id]['refills'][$index]['purchase']['receiptTaxCents'] = $tax;
    }
    return array_values($itemsById);
}

function refill_change(array $records): array
{
    $action = $_POST['action'] ?? '';
    if ($action === 'add-receipt') {
        return refill_add_receipt($records);
    }
    if ($action === 'add-starters') {
        return refill_add_starters($records);
    }
    if ($action === 'add') {
        $quantity = refill_count('quantity');
        $records[] = [
            'id' => bin2hex(random_bytes(16)),
            'name' => refill_text('name', 120),
            'unit' => refill_text('unit', 40),
            'details' => refill_details(),
            'quantity' => $quantity,
            'restockAt' => refill_count('restockAt'),
            'stock' => [refill_text('location', 80) => $quantity],
            'openings' => [],
            'refills' => [],
        ];
        return $records;
    }

    foreach ($records as $index => $item) {
        if ($item['id'] !== ($_POST['id'] ?? null)) {
            continue;
        }
        $stock = refill_stock($item);
        switch ($action) {
            case 'increase':
            case 'refill':
                $location = refill_text('location', 80);
                $amount = refill_count('amount');
                if ($amount < 1) {
                    throw new InvalidArgumentException('Enter at least one spare or pack.');
                }
                $purchase = $action === 'refill' ? refill_purchase($amount) : null;
                $units = $amount * ($purchase['unitsPerPack'] ?? 1);
                if (array_sum($stock) + $units > 9999) {
                    throw new InvalidArgumentException('Inventory cannot exceed 9,999 spares.');
                }
                $stock[$location] = ($stock[$location] ?? 0) + $units;
                if ($action === 'refill') {
                    $item['refills'][] = [
                        'at' => time(),
                        'storage' => $location,
                        'quantity' => $units,
                        'person' => refill_text('person', 80),
                    ];
                    if ($purchase['seller'] !== '' || $purchase['totalCostCents'] !== null
                        || $purchase['unitsPerPack'] !== 1) {
                        $lastRefill = array_key_last($item['refills']);
                        $item['refills'][$lastRefill]['purchase'] = $purchase;
                    }
                }
                break;
            case 'decrease':
            case 'open':
            case 'move':
                $location = refill_text('location', 80);
                if (($stock[$location] ?? 0) < 1) {
                    throw new InvalidArgumentException('That storage location has no spares.');
                }
                if ($action === 'move') {
                    $destination = refill_text('destination', 80);
                    $amount = refill_count('amount');
                    if ($destination === $location || $amount < 1 || $amount > $stock[$location]) {
                        throw new InvalidArgumentException('Choose a different destination and an amount in stock.');
                    }
                    $stock[$destination] = ($stock[$destination] ?? 0) + $amount;
                    $stock[$location] -= $amount;
                } else {
                    $stock[$location]--;
                }
                if ($action === 'open') {
                    $item['openings'][] = [
                        'at' => time(),
                        'storage' => $location,
                        'room' => refill_text('room', 80),
                        'person' => refill_text('person', 80),
                    ];
                }
                break;
            case 'edit':
                $item['name'] = refill_text('name', 120);
                $item['unit'] = refill_text('unit', 40);
                $item['details'] = refill_details();
                $item['restockAt'] = refill_count('restockAt');
                break;
            default:
                throw new InvalidArgumentException('Choose a valid inventory action.');
        }
        $item['stock'] = $stock;
        $item['quantity'] = array_sum($stock);
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
    if ($method !== 'POST') {
        $identity = $auth->user();
        if ($identity === null || $identity['mustChangePassword']) {
            $loginUrl = $auth->loginUrl($_SERVER['SCRIPT_NAME'] ?? '');
            $registrationUrl = rtrim($auth->portal(), '/') . '/register.php';
            require __DIR__ . '/landing.php';
            exit;
        }
    }
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
    $storageLocations = [];
    $rooms = [];
    $people = [];
    foreach ($items as $item) {
        $storageLocations = array_merge($storageLocations, array_map('strval', array_keys(refill_stock($item))));
        $rooms = array_merge($rooms, array_column($item['openings'] ?? [], 'room'));
        $people = array_merge(
            $people,
            array_column($item['openings'] ?? [], 'person'),
            array_column($item['refills'] ?? [], 'person')
        );
    }
    $restock = array_filter($items, static fn (array $item): bool => refill_stock($item) !== [] && $item['quantity'] <= $item['restockAt']);
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
    <link rel="stylesheet" href="assets/css/app.css?v=1.5.1">
</head>
<body>
<main>
    <datalist id="storage-locations">
        <?php foreach (array_unique($storageLocations) as $location): ?>
            <option value="<?= refill_escape($location) ?>"></option>
        <?php endforeach; ?>
    </datalist>
    <datalist id="usage-rooms">
        <?php foreach (array_unique($rooms) as $room): ?>
            <option value="<?= refill_escape($room) ?>"></option>
        <?php endforeach; ?>
    </datalist>
    <datalist id="household-people">
        <?php foreach (array_unique($people) as $person): ?>
            <option value="<?= refill_escape($person) ?>"></option>
        <?php endforeach; ?>
    </datalist>
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
            <?php if ($items): ?>
                <details>
                    <summary>Add items from a receipt</summary>
                    <form method="post" class="fields" data-receipt>
                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                        <label>Purchased from <input name="seller" maxlength="120" required></label>
                        <label>Who refilled it <input name="person" list="household-people" maxlength="80" required></label>
                        <label>Sales tax rate (%) <input name="taxRate" type="number" min="0" max="100" step="0.01" value="8" required></label>
                        <p>Enter totals before tax. Clear Taxable for exempt lines.</p>
                        <div data-receipt-lines>
                            <?php for ($lineNumber = 0; $lineNumber < 2; $lineNumber++): ?>
                                <fieldset data-receipt-line>
                                    <legend>Receipt item</legend>
                                    <label>Inventory item <select name="lines[<?= $lineNumber ?>][item]">
                                        <option value="">Choose an item</option>
                                        <?php foreach ($items as $receiptItem): ?>
                                            <option value="<?= refill_escape($receiptItem['id']) ?>"><?= refill_escape($receiptItem['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select></label>
                                    <label>Storage location <input name="lines[<?= $lineNumber ?>][location]" list="storage-locations" maxlength="80"></label>
                                    <label>Packs <input name="lines[<?= $lineNumber ?>][packs]" type="number" min="1" max="9999" value="1"></label>
                                    <label>Units per pack <input name="lines[<?= $lineNumber ?>][unitsPerPack]" type="number" min="1" max="9999" value="1"></label>
                                    <label>Pre-tax total for this line (USD) <input name="lines[<?= $lineNumber ?>][price]" inputmode="decimal" maxlength="10"></label>
                                    <label><span><input type="checkbox" name="lines[<?= $lineNumber ?>][taxable]" value="1" checked> Taxable</span></label>
                                </fieldset>
                            <?php endfor; ?>
                        </div>
                        <button type="button" data-add-receipt-line hidden>Add another receipt line</button>
                        <button name="action" value="add-receipt">Save receipt</button>
                    </form>
                </details>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <button name="action" value="add-starters">Add starter items</button>
            </form>
            <details>
                <summary>Add item</summary>
                <form method="post" class="fields">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                    <label>Item name <input name="name" required maxlength="120" placeholder="Toothpaste"></label>
                    <label>Details <input name="details" maxlength="240" placeholder="Brand, size, or preferred variety"></label>
                    <label>Unit <input name="unit" required maxlength="40" placeholder="tube"></label>
                    <label>Storage location <input name="location" list="storage-locations" required maxlength="80" placeholder="Hall closet"></label>
                    <label>Unopened spares <input name="quantity" type="number" min="0" max="9999" value="0" required></label>
                    <label>Restock at <input name="restockAt" type="number" min="0" max="9999" value="1" required></label>
                    <button name="action" value="add">Add item</button>
                </form>
            </details>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <?php $name = refill_escape($item['name']); $id = refill_escape($item['id']); ?>
            <?php
            $stock = refill_stock($item);
            $openings = $item['openings'] ?? [];
            $last = $openings ? $openings[array_key_last($openings)] : null;
            $pace = refill_pace($openings);
            ?>
            <article>
                <h3><?= $name ?></h3>
                <?php if (!empty($item['details'])): ?>
                    <p><?= refill_escape($item['details']) ?></p>
                <?php endif; ?>
                <?php if ($stock === []): ?>
                    <p>Add your first stock count to start tracking restock status.</p>
                <?php endif; ?>
                <p><?= $item['quantity'] ?> in stock · unit: <?= refill_escape($item['unit']) ?> · restock at <?= $item['restockAt'] ?></p>
                <ul>
                    <?php foreach ($stock as $location => $quantity): ?>
                        <li><?= refill_escape((string) $location) ?> · <?= $quantity ?> spares</li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($last): ?>
                    <p>Last opened <time data-local datetime="<?= gmdate('c', $last['at']) ?>"><?= gmdate('M j, Y H:i', $last['at']) ?> UTC</time> in <?= refill_escape($last['room']) ?><?php if (isset($last['person'])): ?> by <?= refill_escape($last['person']) ?><?php endif; ?>.</p>
                    <p><?= refill_escape(refill_usage($openings)) ?> across the household.</p>
                    <?php if ($pace > 0): ?>
                        <?php $orderBy = max(time(), (int) ($last['at'] + $pace * $item['quantity'] - 3 * 86400)); ?>
                        <p>Suggested order date <time data-local data-date-only datetime="<?= gmdate('c', $orderBy) ?>"><?= gmdate('M j, Y', $orderBy) ?> UTC</time>. Estimate uses a three-day lead time before the spares are expected to reach zero.</p>
                    <?php endif; ?>
                    <details>
                        <summary>Opening history</summary>
                        <?php foreach (array_unique(array_column($openings, 'room')) as $room): ?>
                            <?php $roomOpenings = array_values(array_filter($openings, static fn (array $entry): bool => $entry['room'] === $room)); ?>
                            <h4><?= refill_escape($room) ?></h4>
                            <p><?= refill_escape(refill_usage($roomOpenings)) ?></p>
                            <ul>
                                <?php foreach (array_reverse($roomOpenings) as $entry): ?>
                                    <li><time data-local datetime="<?= gmdate('c', $entry['at']) ?>"><?= gmdate('M j, Y H:i', $entry['at']) ?> UTC</time> · from <?= refill_escape($entry['storage']) ?> · <?= refill_escape($entry['person'] ?? 'Person not recorded') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endforeach; ?>
                    </details>
                <?php endif; ?>
                <?php if (!empty($item['refills'])): ?>
                    <details>
                        <summary>Refill history</summary>
                        <ul>
                            <?php foreach (array_reverse($item['refills']) as $entry): ?>
                                <li><time data-local datetime="<?= gmdate('c', $entry['at']) ?>"><?= gmdate('M j, Y H:i', $entry['at']) ?> UTC</time>
                                    · <?= refill_escape($entry['person']) ?> added <?= $entry['quantity'] ?> to <?= refill_escape($entry['storage']) ?>
                                    <?php if (isset($entry['purchase'])): ?>
                                        <?php $purchase = $entry['purchase']; ?>
                                        <div>
                                            <?= $purchase['packs'] ?> × <?= $purchase['unitsPerPack'] ?> units
                                            <?php if ($purchase['seller'] !== ''): ?>
                                                · <?= refill_escape($purchase['seller']) ?>
                                            <?php endif; ?>
                                            <?php if ($purchase['totalCostCents'] !== null): ?>
                                                · $<?= number_format($purchase['totalCostCents'] / 100, 2) ?> total
                                                ($<?= number_format($purchase['totalCostCents'] / 100 / $entry['quantity'], 2) ?> per unit)
                                            <?php endif; ?>
                                        </div>
                                        <?php if (isset($purchase['receiptId'])): ?>
                                            <div>Line tax: $<?= number_format($purchase['taxCents'] / 100, 2) ?>
                                                <?= $purchase['taxable'] ? '(' . number_format($purchase['taxRate'] / 100, 2) . '%)' : '(exempt)' ?>
                                                · Receipt total: $<?= number_format($purchase['receiptTotalCents'] / 100, 2) ?>
                                                ($<?= number_format($purchase['receiptTaxCents'] / 100, 2) ?> tax)
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
                <?php if ($canEdit): ?>
                    <?php if ($item['quantity'] > 0): ?>
                        <details>
                            <summary>Opened or replaced one</summary>
                            <form method="post" class="fields">
                                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <label>From storage <select name="location" required>
                                    <?php foreach (array_keys(array_filter($stock)) as $location): ?>
                                        <option><?= refill_escape((string) $location) ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                                <label>Used in <input name="room" list="usage-rooms" maxlength="80" required placeholder="Upstairs bathroom"></label>
                                <label>Who used it <input name="person" list="household-people" maxlength="80" required></label>
                                <button name="action" value="open">Log use</button>
                            </form>
                        </details>
                    <?php endif; ?>
                    <details>
                        <summary>Refill stock or add to a count</summary>
                        <form method="post" class="fields">
                            <input type="hidden" name="csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <label>Storage location <input name="location" list="storage-locations" maxlength="80" required></label>
                            <label>Quantity (packs if pack size is entered) <input name="amount" type="number" min="1" max="9999" value="1" required></label>
                            <details>
                                <summary>Purchase details (optional)</summary>
                                <label>Purchased from <input name="seller" maxlength="120" placeholder="Amazon or Costco"></label>
                                <label>Units per pack <input name="unitsPerPack" type="number" min="1" max="9999" placeholder="1"></label>
                                <label>Total paid (USD) <input name="totalCost" type="text" inputmode="decimal" maxlength="10" placeholder="12.99"></label>
                            </details>
                            <label>Who refilled it <input name="person" list="household-people" maxlength="80" aria-describedby="refill-person-<?= $id ?>"></label>
                            <p id="refill-person-<?= $id ?>">Enter a name to log a refill. Correct count uses quantity as individual units and skips purchase details.</p>
                            <button name="action" value="refill">Log refill</button>
                            <button name="action" value="increase">Correct count</button>
                        </form>
                    </details>
                    <?php if ($item['quantity'] > 0): ?>
                        <details>
                            <summary>Move spares or correct a count</summary>
                            <form method="post" class="fields">
                                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <label>From storage <select name="location" required>
                                    <?php foreach (array_keys(array_filter($stock)) as $location): ?>
                                        <option><?= refill_escape((string) $location) ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                                <button name="action" value="decrease">Remove one from count</button>
                                <label>Move to <input name="destination" list="storage-locations" maxlength="80"></label>
                                <label>Amount to move <input name="amount" type="number" min="1" max="9999" value="1"></label>
                                <button name="action" value="move">Move spares</button>
                            </form>
                        </details>
                    <?php endif; ?>
                    <details>
                        <summary>Edit <?= $name ?></summary>
                        <form method="post" class="fields">
                            <input type="hidden" name="csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <label>Item name <input name="name" required maxlength="120" value="<?= $name ?>"></label>
                            <label>Details <input name="details" maxlength="240" value="<?= refill_escape($item['details'] ?? '') ?>"></label>
                            <label>Unit <input name="unit" required maxlength="40" value="<?= refill_escape($item['unit']) ?>"></label>
                            <label>Restock at <input name="restockAt" type="number" min="0" max="9999" required value="<?= $item['restockAt'] ?>"></label>
                            <button name="action" value="edit">Save changes</button>
                        </form>
                    </details>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
    <footer>Revision 1.5.1</footer>
</main>
<script src="assets/js/dates.js?v=1.5.1"></script>
<script src="assets/js/receipt.js?v=1.5.1"></script>
</body>
</html>
