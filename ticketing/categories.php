<?php
/**
 * Ticketing - categories.php
 * File Revision: 1.0.0
 * Modified: 2026-09-14
 *
 * Revision History:
 * 1.0.0 - Added agent-only web category management with add, rename, move, enable/disable, and ordering controls.
 */

declare(strict_types=1);
session_start();

const CATEGORIES_FILE = __DIR__ . '/data/categories.json';
const CATEGORIES_SAMPLE_FILE = __DIR__ . '/data/categories.json.sample';
const PROJECT_REVISION = '1.6.0';

function currentUser(): ?array
{
    return isset($_SESSION['ticketing_user']) && is_array($_SESSION['ticketing_user'])
        ? $_SESSION['ticketing_user']
        : null;
}

function requireAgent(): array
{
    $user = currentUser();
    if ($user === null) {
        header('Location: index.php');
        exit;
    }
    if (($user['role'] ?? '') !== 'agent') {
        http_response_code(403);
        echo 'Agent access required.';
        exit;
    }
    return $user;
}

function readJsonArray(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }
    $decoded = json_decode(file_get_contents($path) ?: '[]', true);
    return is_array($decoded) ? $decoded : [];
}

function writeCategories(array $categories): bool
{
    $dir = dirname(CATEGORIES_FILE);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }
    $handle = fopen(CATEGORIES_FILE, 'c+');
    if ($handle === false) {
        return false;
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            return false;
        }
        $json = json_encode(array_values($categories), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }
        ftruncate($handle, 0);
        rewind($handle);
        if (fwrite($handle, $json . PHP_EOL) === false) {
            return false;
        }
        fflush($handle);
        flock($handle, LOCK_UN);
        return true;
    } finally {
        fclose($handle);
    }
}

function ensureRuntimeCategories(): array
{
    $live = readJsonArray(CATEGORIES_FILE);
    if ($live !== []) {
        return $live;
    }

    $sample = readJsonArray(CATEGORIES_SAMPLE_FILE);
    if ($sample !== []) {
        writeCategories($sample);
        return $sample;
    }

    $fallback = [[
        'id' => 'general',
        'name' => 'General',
        'active' => true,
        'children' => [],
    ]];
    writeCategories($fallback);
    return $fallback;
}

function flattenCategories(array $nodes, string $parentId = '', string $prefix = '', int $depth = 0): array
{
    $flat = [];
    foreach ($nodes as $index => $node) {
        if (!is_array($node)) {
            continue;
        }
        $name = trim((string)($node['name'] ?? ''));
        $id = trim((string)($node['id'] ?? ''));
        if ($id === '' || $name === '') {
            continue;
        }
        $path = $prefix === '' ? $name : $prefix . ' > ' . $name;
        $flat[] = [
            'id' => $id,
            'name' => $name,
            'active' => (bool)($node['active'] ?? true),
            'parentId' => $parentId,
            'path' => $path,
            'depth' => $depth,
            'index' => $index,
            'childrenCount' => is_array($node['children'] ?? null) ? count($node['children']) : 0,
        ];
        if (!empty($node['children']) && is_array($node['children'])) {
            $flat = array_merge($flat, flattenCategories($node['children'], $id, $path, $depth + 1));
        }
    }
    return $flat;
}

function slugify(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-');
}

function categoryIdExists(array $nodes, string $id): bool
{
    foreach ($nodes as $node) {
        if (($node['id'] ?? '') === $id) {
            return true;
        }
        if (!empty($node['children']) && is_array($node['children']) && categoryIdExists($node['children'], $id)) {
            return true;
        }
    }
    return false;
}

function makeCategoryId(array $categories, string $name): string
{
    $base = slugify($name);
    if ($base === '') {
        $base = 'category';
    }
    $candidate = $base;
    if (!categoryIdExists($categories, $candidate)) {
        return $candidate;
    }
    do {
        $candidate = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
    } while (categoryIdExists($categories, $candidate));
    return $candidate;
}

function appendUnderParent(array &$nodes, string $parentId, array $newNode): bool
{
    if ($parentId === '') {
        $nodes[] = $newNode;
        return true;
    }
    foreach ($nodes as &$node) {
        if (($node['id'] ?? '') === $parentId) {
            if (!isset($node['children']) || !is_array($node['children'])) {
                $node['children'] = [];
            }
            $node['children'][] = $newNode;
            return true;
        }
        if (!empty($node['children']) && is_array($node['children']) && appendUnderParent($node['children'], $parentId, $newNode)) {
            return true;
        }
    }
    unset($node);
    return false;
}

function updateCategory(array &$nodes, string $id, ?string $name = null, ?bool $active = null): bool
{
    foreach ($nodes as &$node) {
        if (($node['id'] ?? '') === $id) {
            if ($name !== null) {
                $node['name'] = $name;
            }
            if ($active !== null) {
                $node['active'] = $active;
            }
            return true;
        }
        if (!empty($node['children']) && is_array($node['children']) && updateCategory($node['children'], $id, $name, $active)) {
            return true;
        }
    }
    unset($node);
    return false;
}

function collectDescendantIds(array $node): array
{
    $ids = [];
    foreach (($node['children'] ?? []) as $child) {
        if (!is_array($child)) {
            continue;
        }
        $ids[] = (string)($child['id'] ?? '');
        $ids = array_merge($ids, collectDescendantIds($child));
    }
    return array_values(array_filter($ids, static fn(string $id): bool => $id !== ''));
}

function removeCategoryNode(array &$nodes, string $id, ?array &$removed = null): bool
{
    foreach ($nodes as $index => &$node) {
        if (($node['id'] ?? '') === $id) {
            $removed = $node;
            array_splice($nodes, $index, 1);
            return true;
        }
        if (!empty($node['children']) && is_array($node['children']) && removeCategoryNode($node['children'], $id, $removed)) {
            return true;
        }
    }
    unset($node);
    return false;
}

function reorderCategory(array &$nodes, string $id, string $direction): bool
{
    foreach ($nodes as $index => &$node) {
        if (($node['id'] ?? '') === $id) {
            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if ($target < 0 || $target >= count($nodes)) {
                return false;
            }
            $tmp = $nodes[$target];
            $nodes[$target] = $nodes[$index];
            $nodes[$index] = $tmp;
            return true;
        }
        if (!empty($node['children']) && is_array($node['children']) && reorderCategory($node['children'], $id, $direction)) {
            return true;
        }
    }
    unset($node);
    return false;
}

function categoryById(array $nodes, string $id): ?array
{
    foreach ($nodes as $node) {
        if (($node['id'] ?? '') === $id) {
            return $node;
        }
        if (!empty($node['children']) && is_array($node['children'])) {
            $found = categoryById($node['children'], $id);
            if ($found !== null) {
                return $found;
            }
        }
    }
    return null;
}

$user = requireAgent();
$categories = ensureRuntimeCategories();

if (!isset($_SESSION['ticketing_categories_csrf'])) {
    $_SESSION['ticketing_categories_csrf'] = bin2hex(random_bytes(24));
}

$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals((string)$_SESSION['ticketing_categories_csrf'], $token)) {
        $error = 'Invalid form token. Refresh and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id = trim((string)($_POST['id'] ?? ''));

        if ($action === 'add') {
            $name = trim((string)($_POST['name'] ?? ''));
            $parentId = trim((string)($_POST['parentId'] ?? ''));
            if ($name === '') {
                $error = 'Category name is required.';
            } else {
                $newId = makeCategoryId($categories, $name);
                $node = ['id' => $newId, 'name' => $name, 'active' => true, 'children' => []];
                if (!appendUnderParent($categories, $parentId, $node)) {
                    $error = 'Parent category was not found.';
                } elseif (!writeCategories($categories)) {
                    $error = 'Could not save categories.';
                } else {
                    $message = 'Category added.';
                }
            }
        } elseif ($action === 'rename') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($id === '' || $name === '') {
                $error = 'Category and name are required.';
            } elseif (!updateCategory($categories, $id, $name, null)) {
                $error = 'Category was not found.';
            } elseif (!writeCategories($categories)) {
                $error = 'Could not save categories.';
            } else {
                $message = 'Category renamed. Its stable ID did not change.';
            }
        } elseif ($action === 'toggle') {
            $node = categoryById($categories, $id);
            if ($node === null) {
                $error = 'Category was not found.';
            } else {
                $newActive = !(bool)($node['active'] ?? true);
                if (!updateCategory($categories, $id, null, $newActive) || !writeCategories($categories)) {
                    $error = 'Could not update category.';
                } else {
                    $message = $newActive ? 'Category enabled.' : 'Category disabled.';
                }
            }
        } elseif ($action === 'move') {
            $newParentId = trim((string)($_POST['parentId'] ?? ''));
            if ($id === '') {
                $error = 'Category is required.';
            } elseif ($id === $newParentId) {
                $error = 'A category cannot be its own parent.';
            } else {
                $node = categoryById($categories, $id);
                if ($node === null) {
                    $error = 'Category was not found.';
                } elseif ($newParentId !== '' && in_array($newParentId, collectDescendantIds($node), true)) {
                    $error = 'A category cannot be moved beneath one of its descendants.';
                } else {
                    $removed = null;
                    if (!removeCategoryNode($categories, $id, $removed) || $removed === null) {
                        $error = 'Category could not be removed from its current location.';
                    } elseif (!appendUnderParent($categories, $newParentId, $removed)) {
                        $error = 'New parent category was not found.';
                        appendUnderParent($categories, '', $removed);
                    } elseif (!writeCategories($categories)) {
                        $error = 'Could not save categories.';
                    } else {
                        $message = 'Category moved. Its stable ID did not change.';
                    }
                }
            }
        } elseif ($action === 'reorder') {
            $direction = (string)($_POST['direction'] ?? '');
            if (!in_array($direction, ['up', 'down'], true)) {
                $error = 'Invalid reorder direction.';
            } elseif (!reorderCategory($categories, $id, $direction)) {
                $error = 'Category is already at that edge of its group.';
            } elseif (!writeCategories($categories)) {
                $error = 'Could not save categories.';
            } else {
                $message = 'Category order updated.';
            }
        }
    }
    $categories = ensureRuntimeCategories();
}

$flat = flattenCategories($categories);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Manage Categories</title>
    <link rel="stylesheet" href="styles.css?v=1.2.0">
    <style>
        .category-shell{max-width:1100px;margin:0 auto;padding:24px}.category-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.category-grid{display:grid;grid-template-columns:1fr;gap:10px;margin-top:16px}.category-row{display:grid;grid-template-columns:minmax(220px,1.6fr) minmax(220px,1.1fr) auto;gap:12px;align-items:center;padding:12px;border:1px solid var(--border);border-radius:10px;background:var(--surface)}.category-name{font-weight:700}.category-path{color:var(--muted);font-size:.78rem;margin-top:3px;overflow-wrap:anywhere}.category-id{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:var(--muted);font-size:.72rem;margin-top:4px}.category-actions{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}.category-actions form{display:inline}.category-actions button{padding:7px 9px}.category-editor{display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end}.category-editor label{display:grid;gap:6px}.status-off{opacity:.55}.indent{display:inline-block;width:calc(var(--depth) * 18px)}.flash{padding:12px;border-radius:9px;margin:12px 0}.flash.ok{border:1px solid var(--success);background:rgba(34,197,94,.08)}.flash.err{border:1px solid var(--danger);background:rgba(239,68,68,.08)}.note{padding:12px;border:1px solid var(--border);border-radius:9px;background:rgba(56,189,248,.05);color:var(--muted);margin-top:14px}.row-edit{display:grid;grid-template-columns:minmax(150px,1fr) minmax(180px,1fr);gap:8px}.row-edit form{display:flex;gap:6px}.row-edit input,.row-edit select{min-width:0}.row-edit button{white-space:nowrap}@media(max-width:760px){.category-shell{padding:14px}.category-row{grid-template-columns:1fr}.category-actions{justify-content:flex-start}.category-editor{grid-template-columns:1fr}.row-edit{grid-template-columns:1fr}.topbar{align-items:flex-start}.indent{width:calc(var(--depth) * 10px)}}
    </style>
</head>
<body>
<main class="category-shell">
    <div class="topbar">
        <div>
            <h1>Manage Categories</h1>
            <p class="muted">Agent tools · Project rev <?= htmlspecialchars(PROJECT_REVISION) ?></p>
        </div>
        <a class="button secondary" href="index.php">Back to Ticketing</a>
    </div>

    <?php if ($message !== ''): ?><div class="flash ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="flash err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <section class="panel" style="padding:18px">
        <h2>Add Category</h2>
        <form method="post" class="category-editor">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['ticketing_categories_csrf']) ?>">
            <input type="hidden" name="action" value="add">
            <label>Name<input name="name" required placeholder="Example: Adobe Acrobat"></label>
            <label>Parent<select name="parentId"><option value="">Top level</option><?php foreach ($flat as $cat): ?><option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['path']) ?></option><?php endforeach; ?></select></label>
            <button class="button primary" type="submit">Add Category</button>
        </form>
        <div class="note">Category IDs are permanent references. New IDs are created from the category name for readability, but moving or renaming a category does not change its ID.</div>
    </section>

    <section class="category-grid">
        <?php foreach ($flat as $cat): ?>
            <article class="category-row <?= $cat['active'] ? '' : 'status-off' ?>">
                <div>
                    <div class="category-name"><span class="indent" style="--depth:<?= (int)$cat['depth'] ?>"></span><?= htmlspecialchars($cat['name']) ?> <?= $cat['active'] ? '' : '<span class="muted small">(disabled)</span>' ?></div>
                    <div class="category-path"><?= htmlspecialchars($cat['path']) ?></div>
                    <div class="category-id">ID: <?= htmlspecialchars($cat['id']) ?></div>
                </div>
                <div class="row-edit">
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['ticketing_categories_csrf']) ?>">
                        <input type="hidden" name="action" value="rename">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($cat['id']) ?>">
                        <input name="name" value="<?= htmlspecialchars($cat['name']) ?>" required>
                        <button class="button" type="submit">Rename</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['ticketing_categories_csrf']) ?>">
                        <input type="hidden" name="action" value="move">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($cat['id']) ?>">
                        <select name="parentId">
                            <option value="">Top level</option>
                            <?php foreach ($flat as $parent): if ($parent['id'] === $cat['id']) continue; ?>
                                <option value="<?= htmlspecialchars($parent['id']) ?>" <?= $parent['id'] === $cat['parentId'] ? 'selected' : '' ?>><?= htmlspecialchars($parent['path']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button" type="submit">Move</button>
                    </form>
                </div>
                <div class="category-actions">
                    <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['ticketing_categories_csrf']) ?>"><input type="hidden" name="action" value="reorder"><input type="hidden" name="id" value="<?= htmlspecialchars($cat['id']) ?>"><input type="hidden" name="direction" value="up"><button class="button" type="submit" title="Move up">↑</button></form>
                    <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['ticketing_categories_csrf']) ?>"><input type="hidden" name="action" value="reorder"><input type="hidden" name="id" value="<?= htmlspecialchars($cat['id']) ?>"><input type="hidden" name="direction" value="down"><button class="button" type="submit" title="Move down">↓</button></form>
                    <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['ticketing_categories_csrf']) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= htmlspecialchars($cat['id']) ?>"><button class="button" type="submit"><?= $cat['active'] ? 'Disable' : 'Enable' ?></button></form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>
