<?php
/**
 * Ticketing - categories.php
 * File Revision: 1.1.0
 * Modified: 2026-09-15
 *
 * Revision History:
 * 1.1.0 - Reworked category management into compact rows with on-demand editing for mobile and desktop.
 * 1.0.0 - Added agent-only web category management with add, rename, move, enable/disable, and ordering controls.
 */

declare(strict_types=1);
session_start();

const CATEGORIES_FILE = __DIR__ . '/data/categories.json';
const CATEGORIES_SAMPLE_FILE = __DIR__ . '/data/categories.json.sample';
const PROJECT_REVISION = '1.7.1';

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
    <link rel="stylesheet" href="styles.css?v=1.2.1">
    <style>
        .category-shell{max-width:1100px;margin:0 auto;padding:20px}
        .category-grid{display:grid;gap:6px;margin-top:12px}
        .category-row{border:1px solid var(--border);border-radius:10px;background:var(--surface);overflow:hidden}
        .category-summary{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:center;padding:9px 10px 9px calc(10px + (var(--depth) * 14px));min-height:52px}
        .category-main{min-width:0}
        .category-name{font-weight:700;line-height:1.15;display:flex;gap:7px;align-items:center;min-width:0}
        .category-name-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .category-path{color:var(--muted);font-size:.72rem;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .category-tools{display:flex;gap:4px;align-items:center}
        .category-tools form{margin:0}
        .category-tools .button,.edit-toggle{min-width:34px;padding:6px 8px;font-size:.8rem;line-height:1.1}
        .edit-toggle{white-space:nowrap}
        .category-edit{display:none;padding:10px;border-top:1px solid var(--border);background:rgba(15,23,42,.35)}
        .category-row.editing .category-edit{display:grid;gap:8px}
        .category-edit form{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:6px;align-items:center}
        .category-edit input,.category-edit select{min-width:0}
        .category-id{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:var(--muted);font-size:.68rem;overflow-wrap:anywhere}
        .status-off{opacity:.55}
        .status-pill{font-size:.62rem;color:var(--muted);border:1px solid var(--border);border-radius:999px;padding:2px 5px;flex:0 0 auto}
        .flash{padding:10px;border-radius:9px;margin:10px 0}.flash.ok{border:1px solid var(--success);background:rgba(34,197,94,.08)}.flash.err{border:1px solid var(--danger);background:rgba(239,68,68,.08)}
        .add-panel{padding:12px}
        .add-panel summary{cursor:pointer;font-weight:700;list-style:none;display:flex;align-items:center;justify-content:space-between;gap:10px}
        .add-panel summary::-webkit-details-marker{display:none}
        .add-panel summary::after{content:'+';font-size:1.2rem;color:var(--accent)}
        .add-panel[open] summary::after{content:'−'}
        .category-editor{display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:end;margin-top:10px}
        .category-editor label{display:grid;gap:5px;font-size:.82rem;color:#cbd5e1}
        .note{color:var(--muted);font-size:.72rem;line-height:1.35;margin-top:8px}
        .compact-heading{margin-bottom:8px}
        @media(max-width:760px){
            .category-shell{padding:10px 10px 22px}
            .topbar{margin-bottom:10px}
            .topbar h1{font-size:1.3rem}
            .topbar .muted{font-size:.72rem;margin-bottom:0}
            .topbar>.button{padding:7px 9px;font-size:.76rem}
            .category-grid{gap:5px}
            .category-summary{padding-top:7px;padding-bottom:7px;min-height:46px;padding-left:calc(8px + (var(--depth) * 10px))}
            .category-path{font-size:.68rem}
            .category-tools .button,.edit-toggle{padding:5px 7px;min-width:31px;font-size:.76rem}
            .category-editor{grid-template-columns:1fr}
            .category-editor input,.category-editor select,.category-edit input,.category-edit select{font-size:16px}
            .category-edit{padding:8px}
            .category-edit form{grid-template-columns:1fr auto}
            .add-panel{padding:10px}
        }
        @media(max-width:390px){
            .category-path{max-width:210px}
            .category-tools{gap:3px}
            .category-tools .button,.edit-toggle{padding:5px 6px;min-width:29px}
        }
    </style>
</head>
<body>
<main class="category-shell">
    <div class="topbar compact-heading">
        <div>
            <h1>Manage Categories</h1>
            <p class="muted">Agent tools · Project rev <?= htmlspecialchars(PROJECT_REVISION) ?></p>
        </div>
        <a class="button secondary" href="index.php">Back</a>
    </div>

    <?php if ($message !== ''): ?><div class="flash ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="flash err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <details class="panel add-panel">
        <summary>Add Category</summary>
        <form method="post" class="category-editor">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['ticketing_categories_csrf']) ?>">
            <input type="hidden" name="action" value="add">
            <label>Name<input name="name" required placeholder="Example: Adobe Acrobat"></label>
            <label>Parent<select name="parentId"><option value="">Top level</option><?php foreach ($flat as $cat): ?><option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['path']) ?></option><?php endforeach; ?></select></label>
            <button class="button primary" type="submit">Add</button>
        </form>
        <div class="note">IDs remain permanent even when a category is renamed or moved.</div>
    </details>

    <section class="category-grid">
        <?php foreach ($flat as $cat): ?>
            <article class="category-row <?= $cat['active'] ? '' : 'status-off' ?>" style="--depth:<?= (int)$cat['depth'] ?>" data-category-row>
                <div class="category-summary">
                    <div class="category-main">
                        <div class="category-name">
                            <span class="category-name-text"><?= htmlspecialchars($cat['name']) ?></span>
                            <?php if (!$cat['active']): ?><span class="status-pill">Disabled</span><?php endif; ?>
                        </div>
                        <div class="category-path"><?= htmlspecialchars($cat['path']) ?></div>
                    </div>
                    <div class="category-tools">
                        <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['ticketing_categories_csrf']) ?>"><input type="hidden" name="action" value="reorder"><input type="hidden" name="id" value="<?= htmlspecialchars($cat['id']) ?>"><input type="hidden" name="direction" value="up"><button class="button" type="submit" title="Move up" aria-label="Move <?= htmlspecialchars($cat['name']) ?> up">↑</button></form>
                        <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['ticketing_categories_csrf']) ?>"><input type="hidden" name="action" value="reorder"><input type="hidden" name="id" value="<?= htmlspecialchars($cat['id']) ?>"><input type="hidden" name="direction" value="down"><button class="button" type="submit" title="Move down" aria-label="Move <?= htmlspecialchars($cat['name']) ?> down">↓</button></form>
                        <button class="button edit-toggle" type="button" data-edit-toggle aria-expanded="false">Edit</button>
                    </div>
                </div>
                <div class="category-edit">
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['ticketing_categories_csrf']) ?>">
                        <input type="hidden" name="action" value="rename">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($cat['id']) ?>">
                        <input name="name" value="<?= htmlspecialchars($cat['name']) ?>" required aria-label="Category name">
                        <button class="button" type="submit">Rename</button>
                    </form>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['ticketing_categories_csrf']) ?>">
                        <input type="hidden" name="action" value="move">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($cat['id']) ?>">
                        <select name="parentId" aria-label="Parent category">
                            <option value="">Top level</option>
                            <?php foreach ($flat as $parent): if ($parent['id'] === $cat['id']) continue; ?>
                                <option value="<?= htmlspecialchars($parent['id']) ?>" <?= $parent['id'] === $cat['parentId'] ? 'selected' : '' ?>><?= htmlspecialchars($parent['path']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button" type="submit">Move</button>
                    </form>
                    <div class="category-id">ID: <?= htmlspecialchars($cat['id']) ?></div>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['ticketing_categories_csrf']) ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($cat['id']) ?>">
                        <span></span>
                        <button class="button" type="submit"><?= $cat['active'] ? 'Disable Category' : 'Enable Category' ?></button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</main>
<script>
document.querySelectorAll('[data-edit-toggle]').forEach(button=>{
    button.addEventListener('click',()=>{
        const row=button.closest('[data-category-row]');
        const opening=!row.classList.contains('editing');
        document.querySelectorAll('[data-category-row].editing').forEach(other=>{
            if(other!==row){other.classList.remove('editing');const b=other.querySelector('[data-edit-toggle]');if(b){b.textContent='Edit';b.setAttribute('aria-expanded','false');}}
        });
        row.classList.toggle('editing',opening);
        button.textContent=opening?'Close':'Edit';
        button.setAttribute('aria-expanded',opening?'true':'false');
        if(opening){row.scrollIntoView({block:'nearest',behavior:'smooth'});}
    });
});
</script>
</body>
</html>
