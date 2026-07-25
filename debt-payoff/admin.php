<?php
/*
    Debt Payoff Planner
    Revision: 1.0.1
    Description: Admin-only user management for adding, promoting, demoting, deleting, and resetting users while exposing only storage usage and account metadata.
*/

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$adminAccount = requireAdmin();
$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'admin_add_user') {
        $result = registerUser((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
        if ($result['ok']) {
            if ((string)($_POST['role'] ?? 'user') === 'admin') {
                updateAccount((string)($_POST['username'] ?? ''), (string)($_POST['username'] ?? ''), 'admin');
            }
            $flash = 'User added.';
        } else {
            $error = $result['error'] ?? 'Unable to add user.';
        }
    } elseif ($action === 'admin_edit_user') {
        $result = updateAccount(
            (string)($_POST['existing_username'] ?? ''),
            (string)($_POST['username'] ?? ''),
            (string)($_POST['role'] ?? 'user')
        );
        if ($result['ok']) {
            $flash = 'User updated.';
        } else {
            $error = $result['error'] ?? 'Unable to update user.';
        }
    } elseif ($action === 'admin_reset_password') {
        $result = resetUserPassword((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
        if ($result['ok']) {
            $flash = 'Password reset.';
        } else {
            $error = $result['error'] ?? 'Unable to reset password.';
        }
    } elseif ($action === 'admin_delete_user') {
        $username = (string)($_POST['username'] ?? '');
        if ($username === (string)($adminAccount['username'] ?? '')) {
            $error = 'You cannot delete the account you are currently using.';
        } else {
            $result = deleteUserAccount($username);
            if ($result['ok']) {
                $flash = 'User deleted.';
            } else {
                $error = $result['error'] ?? 'Unable to delete user.';
            }
        }
    } elseif ($action === 'admin_toggle_role') {
        $username = (string)($_POST['username'] ?? '');
        $account = findAccount($username);
        if ($account === null) {
            $error = 'User not found.';
        } else {
            $newRole = ($account['role'] ?? 'user') === 'admin' ? 'user' : 'admin';
            $result = updateAccount($username, $username, $newRole);
            if ($result['ok']) {
                $flash = $newRole === 'admin' ? 'User promoted to admin.' : 'User demoted to user.';
            } else {
                $error = $result['error'] ?? 'Unable to change role.';
            }
        }
    }
}

$accounts = readAccounts()['users'] ?? [];
$projectRevision = readProjectRevision();
$projectModifiedAt = readProjectModifiedAt();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container container-wide">
    <div class="topbar">
        <nav class="nav">
            <a href="index.php">Dashboard</a>
            <a href="admin.php">Admin</a>
            <a href="changelog.php">Changelog</a>
            <a href="todo.php">Todo</a>
            <a href="index.php?logout=1" class="nav-button">Logout</a>
        </nav>
        <div class="top-meta">
            <span><strong>Rev:</strong> <?= h($projectRevision) ?></span>
            <span><strong>Modified:</strong> <?= h($projectModifiedAt) ?></span>
        </div>
    </div>

    <header class="page-header">
        <div>
            <h1>Admin</h1>
            <p class="small">This page manages accounts only. It does not expose any user loan contents.</p>
        </div>
        <div class="status-box">
            <strong>Accounts:</strong> <?= count($accounts) ?><br>
            <strong>Signed in:</strong> <?= h((string)$adminAccount['username']) ?><br>
            <strong>Role:</strong> <?= h((string)$adminAccount['role']) ?>
        </div>
    </header>

    <?php if ($error !== ''): ?>
    <section class="card alert alert-error"><?= h($error) ?></section>
    <?php endif; ?>
    <?php if ($flash !== ''): ?>
    <section class="card alert alert-success"><?= h($flash) ?></section>
    <?php endif; ?>

    <section class="card">
        <h2>Add User</h2>
        <form method="post" class="loan-form-grid">
            <input type="hidden" name="action" value="admin_add_user">
            <label>Username
                <input type="text" name="username" required minlength="3">
            </label>
            <label>Password
                <input type="password" name="password" required minlength="4">
            </label>
            <label>Role
                <select name="role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </label>
            <div class="actions full-width">
                <button type="submit">Add User</button>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Users</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th>Storage Used</th>
                        <th>Edit</th>
                        <th>Role</th>
                        <th>Password Reset</th>
                        <th>Delete</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td><?= h((string)$account['username']) ?></td>
                        <td><?= h((string)$account['role']) ?></td>
                        <td><?= h(formatTimestamp((string)($account['created_at'] ?? ''))) ?></td>
                        <td><?= h(formatTimestamp((string)($account['updated_at'] ?? ''))) ?></td>
                        <td><?= h(humanBytes(userStorageSize((string)$account['username']))) ?></td>
                        <td>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="action" value="admin_edit_user">
                                <input type="hidden" name="existing_username" value="<?= h((string)$account['username']) ?>">
                                <input type="text" name="username" value="<?= h((string)$account['username']) ?>" required>
                                <select name="role">
                                    <option value="user"<?= ($account['role'] ?? '') === 'user' ? ' selected' : '' ?>>User</option>
                                    <option value="admin"<?= ($account['role'] ?? '') === 'admin' ? ' selected' : '' ?>>Admin</option>
                                </select>
                                <button type="submit">Save</button>
                            </form>
                        </td>
                        <td>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="action" value="admin_toggle_role">
                                <input type="hidden" name="username" value="<?= h((string)$account['username']) ?>">
                                <button type="submit"><?= ($account['role'] ?? 'user') === 'admin' ? 'Demote' : 'Promote' ?></button>
                            </form>
                        </td>
                        <td>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="action" value="admin_reset_password">
                                <input type="hidden" name="username" value="<?= h((string)$account['username']) ?>">
                                <input type="password" name="password" placeholder="New password" required minlength="4">
                                <button type="submit">Reset</button>
                            </form>
                        </td>
                        <td>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="action" value="admin_delete_user">
                                <input type="hidden" name="username" value="<?= h((string)$account['username']) ?>">
                                <button type="submit" class="danger" onclick="return confirm('Delete this user and private data file?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
