<?php declare(strict_types=1);
/**
 * Filename: admin-users.php
 * Revision : 1.1.0
 * Description : Admin-only page for managing scoreboard users and viewing audit logs.
 *               Supports add, edit (username/role/scoreboards), reset password, and delete.
 * Author : Jason Lamb (with help from Claude Code)
 * Created Date : 2026-04-13
 * Modified Date : 2026-06-12
 * Changelog :
 * 1.0.0 Initial release
 * 1.1.0 Fix Edit button (refactor inline onclick to data-attribute handler);
 *       allow editing username; track and display modified_at
 */

require __DIR__ . '/auth.php';
$currentUser = requireAdmin('./login.php');

$auditFiles = [
    'root'       => __DIR__ . '/data/audit.json',
    'youth'      => __DIR__ . '/youth/data/audit.json',
    'collide'    => __DIR__ . '/collide/data/audit.json',
    'frontlines' => __DIR__ . '/frontlines/data/audit.json',
];

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $users  = loadUsers();

    if ($action === 'add-user') {
        $username   = trim($_POST['username'] ?? '');
        $password   = $_POST['password'] ?? '';
        $role       = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'scorer';
        $scoreboards = array_values(array_intersect($_POST['scoreboards'] ?? [], ALL_SCOREBOARDS));

        if ($username === '' || $password === '') {
            $error = 'Username and password are required.';
        } elseif (count(array_filter($users, fn($u) => $u['username'] === $username)) > 0) {
            $error = "Username '{$username}' already exists.";
        } else {
            $users[] = makeUser($username, $password, $role, $scoreboards);
            saveUsers($users);
            $message = "User '{$username}' created.";
        }
    }

    if ($action === 'update-user') {
        $userId      = $_POST['user_id'] ?? '';
        $newUsername = trim($_POST['username'] ?? '');
        $role        = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'scorer';
        $scoreboards = array_values(array_intersect($_POST['scoreboards'] ?? [], ALL_SCOREBOARDS));

        if ($newUsername === '') {
            $error = 'Username cannot be empty.';
        } else {
            $conflict = false;
            foreach ($users as $u) {
                if ($u['id'] !== $userId && strcasecmp($u['username'] ?? '', $newUsername) === 0) {
                    $conflict = true;
                    break;
                }
            }
            if ($conflict) {
                $error = "Username '{$newUsername}' is already taken.";
            } else {
                foreach ($users as &$user) {
                    if ($user['id'] === $userId) {
                        $user['username']    = $newUsername;
                        $user['role']        = $role;
                        $user['scoreboards'] = $scoreboards;
                        $user['modified_at'] = gmdate('c');
                        if ($currentUser['id'] === $userId) {
                            $_SESSION[AUTH_SESSION]['username']    = $newUsername;
                            $_SESSION[AUTH_SESSION]['role']        = $role;
                            $_SESSION[AUTH_SESSION]['scoreboards'] = $scoreboards;
                        }
                        $message = "User '{$user['username']}' updated.";
                        break;
                    }
                }
                unset($user);
                saveUsers($users);
            }
        }
    }

    if ($action === 'reset-password') {
        $userId   = $_POST['user_id'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($password === '') {
            $error = 'Password cannot be empty.';
        } else {
            foreach ($users as &$user) {
                if ($user['id'] === $userId) {
                    $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    $user['modified_at']   = gmdate('c');
                    $message = "Password reset for '{$user['username']}'.";
                    break;
                }
            }
            unset($user);
            saveUsers($users);
        }
    }

    if ($action === 'delete-user') {
        $userId = $_POST['user_id'] ?? '';
        if ($userId === $currentUser['id']) {
            $error = 'You cannot delete your own account.';
        } else {
            $deleted = '';
            foreach ($users as $u) {
                if ($u['id'] === $userId) { $deleted = $u['username']; break; }
            }
            $users = array_filter($users, fn($u) => $u['id'] !== $userId);
            saveUsers($users);
            $message = "User '{$deleted}' deleted.";
        }
    }

    // Reload after save
    $users = loadUsers();
}

$users = loadUsers();

// Merge audit logs from all scoreboards
$auditEntries = [];
foreach ($auditFiles as $sb => $file) {
    if (is_file($file)) {
        $data = json_decode(file_get_contents($file) ?: '', true);
        if (is_array($data)) {
            foreach ($data as $entry) {
                $entry['scoreboard'] = $sb;
                $auditEntries[]      = $entry;
            }
        }
    }
}
usort($auditEntries, fn($a, $b) => strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? ''));
$auditEntries = array_slice($auditEntries, 0, 200);

function sbChecked(array $user, string $sb): string
{
    return in_array($sb, $user['scoreboards'] ?? [], true) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>CVC Scoreboard — User Admin</title>
    <link rel="stylesheet" href="./public/styles.css?v=<?= filemtime(__DIR__ . '/public/styles.css') ?>" />
  </head>
  <body>
    <div class="page-shell">

      <header class="page-header">
        <div>
          <p>Admin</p>
          <h2>User Management</h2>
          <p class="updated-at">Signed in as <?= htmlspecialchars($currentUser['username']) ?></p>
        </div>
        <div class="header-actions">
          <a class="au-btn" href="./enter-scores.php">← Scoreboard</a>
          <a class="au-btn" href="./logout.php">Sign Out</a>
        </div>
      </header>

      <?php if ($message !== ''): ?>
        <p class="status-text" style="color:var(--positive)"><?= htmlspecialchars($message) ?></p>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <p class="status-text" style="color:var(--negative)"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <!-- ── Users ── -->
      <section class="au-section">
        <h3 class="au-heading">Users</h3>
        <div class="au-table-wrap">
          <table class="au-table">
            <thead>
              <tr>
                <th>Username</th><th>Role</th><th>Scoreboards</th><th>Created</th><th>Modified</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <?= htmlspecialchars($u['username']) ?>
                  <?= $u['id'] === $currentUser['id'] ? ' <em style="opacity:.5">(you)</em>' : '' ?>
                </td>
                <td><?= htmlspecialchars($u['role']) ?></td>
                <td><?= htmlspecialchars(implode(', ', $u['scoreboards'] ?? [])) ?></td>
                <td><?= htmlspecialchars(substr($u['created_at'] ?? '', 0, 10)) ?></td>
                <td><?= htmlspecialchars(substr($u['modified_at'] ?? '', 0, 10)) ?></td>
                <td class="au-actions">
                  <button
                    type="button"
                    class="js-edit-user"
                    data-id="<?= htmlspecialchars($u['id'], ENT_QUOTES) ?>"
                    data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>"
                    data-role="<?= htmlspecialchars($u['role'], ENT_QUOTES) ?>"
                    data-scoreboards="<?= htmlspecialchars(json_encode($u['scoreboards'] ?? []), ENT_QUOTES) ?>"
                  >Edit</button>
                  <button
                    type="button"
                    class="js-reset-pw"
                    data-id="<?= htmlspecialchars($u['id'], ENT_QUOTES) ?>"
                    data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>"
                  >Reset PW</button>
                  <?php if ($u['id'] !== $currentUser['id']): ?>
                  <form method="POST" style="display:inline" onsubmit="return confirm('Delete <?= htmlspecialchars($u['username'], ENT_QUOTES) ?>?')">
                    <input type="hidden" name="action" value="delete-user" />
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>" />
                    <button class="negative" type="submit">Delete</button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- ── Add User ── -->
      <section class="au-section">
        <h3 class="au-heading">Add User</h3>
        <form method="POST" class="au-form">
          <input type="hidden" name="action" value="add-user" />
          <div class="au-row">
            <input type="text" name="username" placeholder="Username" autocapitalize="none" spellcheck="false" required />
            <input type="password" name="password" placeholder="Password" required />
            <select name="role">
              <option value="scorer">Scorer</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="au-row">
            <span style="opacity:.7">Scoreboards:</span>
            <?php foreach (ALL_SCOREBOARDS as $sb): ?>
              <label class="au-check">
                <input type="checkbox" name="scoreboards[]" value="<?= $sb ?>" checked />
                <?= ucfirst($sb) ?>
              </label>
            <?php endforeach; ?>
            <button class="positive" type="submit">Add User</button>
          </div>
        </form>
      </section>

      <!-- ── Audit Log ── -->
      <section class="au-section">
        <h3 class="au-heading">Recent Activity</h3>
        <?php if (empty($auditEntries)): ?>
          <p class="status-text">No activity recorded yet.</p>
        <?php else: ?>
        <div class="au-table-wrap">
          <table class="au-table au-audit">
            <thead>
              <tr>
                <th>Time (UTC)</th><th>User</th><th>Board</th><th>Action</th>
                <th>Team</th><th>Change</th><th>Score</th><th>IP</th><th>Device</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($auditEntries as $e): ?>
              <tr>
                <td><?= htmlspecialchars(substr($e['timestamp'] ?? '', 0, 19)) ?></td>
                <td><?= htmlspecialchars($e['username'] ?? '') ?></td>
                <td><?= htmlspecialchars($e['scoreboard'] ?? '') ?></td>
                <td><?= htmlspecialchars($e['action'] ?? '') ?></td>
                <td><?= htmlspecialchars($e['team_name'] ?? '—') ?></td>
                <td><?php
                  $amt = $e['amount'] ?? null;
                  if ($amt !== null) {
                      echo htmlspecialchars(($amt > 0 ? '+' : '') . $amt);
                  } else {
                      echo '—';
                  }
                ?></td>
                <td><?= htmlspecialchars(isset($e['new_score']) ? (string) $e['new_score'] : '—') ?></td>
                <td><?= htmlspecialchars($e['ip'] ?? '') ?></td>
                <td class="au-ua"><?= htmlspecialchars($e['user_agent'] ?? '') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </section>

    </div><!-- /page-shell -->

    <!-- Edit User Modal -->
    <div id="modal-edit" class="au-modal hidden">
      <div class="au-modal-card">
        <h3>Edit: <span id="edit-name"></span></h3>
        <form method="POST">
          <input type="hidden" name="action" value="update-user" />
          <input type="hidden" name="user_id" id="edit-id" />
          <div class="au-row">
            <label style="flex:1">Username:
              <input type="text" name="username" id="edit-username" autocapitalize="none" spellcheck="false" required />
            </label>
          </div>
          <div class="au-row">
            <label>Role:
              <select name="role" id="edit-role">
                <option value="scorer">Scorer</option>
                <option value="admin">Admin</option>
              </select>
            </label>
          </div>
          <div class="au-row">
            <span style="opacity:.7">Scoreboards:</span>
            <?php foreach (ALL_SCOREBOARDS as $sb): ?>
              <label class="au-check">
                <input type="checkbox" name="scoreboards[]" value="<?= $sb ?>" class="edit-sb" data-sb="<?= $sb ?>" />
                <?= ucfirst($sb) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="au-row" style="margin-top:.5rem">
            <button class="positive" type="submit">Save</button>
            <button type="button" class="js-modal-cancel">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="modal-pw" class="au-modal hidden">
      <div class="au-modal-card">
        <h3>Reset Password: <span id="pw-name"></span></h3>
        <form method="POST">
          <input type="hidden" name="action" value="reset-password" />
          <input type="hidden" name="user_id" id="pw-id" />
          <div class="au-row">
            <input type="password" name="password" placeholder="New password" required />
            <button class="positive" type="submit">Set Password</button>
            <button type="button" class="js-modal-cancel">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <script>
      function openEdit(id, username, role, scoreboards) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-name').textContent = username;
        document.getElementById('edit-username').value = username;
        document.getElementById('edit-role').value = role;
        document.querySelectorAll('.edit-sb').forEach(cb => {
          cb.checked = scoreboards.includes(cb.dataset.sb);
        });
        document.getElementById('modal-edit').classList.remove('hidden');
      }
      function openPwReset(id, username) {
        document.getElementById('pw-id').value = id;
        document.getElementById('pw-name').textContent = username;
        document.getElementById('modal-pw').classList.remove('hidden');
      }
      function closeModals() {
        document.getElementById('modal-edit').classList.add('hidden');
        document.getElementById('modal-pw').classList.add('hidden');
      }
      document.addEventListener('click', event => {
        const editBtn = event.target.closest('.js-edit-user');
        if (editBtn) {
          let scoreboards = [];
          try { scoreboards = JSON.parse(editBtn.dataset.scoreboards || '[]'); } catch {}
          openEdit(editBtn.dataset.id, editBtn.dataset.username, editBtn.dataset.role, scoreboards);
          return;
        }
        const pwBtn = event.target.closest('.js-reset-pw');
        if (pwBtn) {
          openPwReset(pwBtn.dataset.id, pwBtn.dataset.username);
          return;
        }
        if (event.target.classList.contains('js-modal-cancel')) {
          closeModals();
        }
      });
      document.querySelectorAll('.au-modal').forEach(m =>
        m.addEventListener('click', e => { if (e.target === m) closeModals(); })
      );
    </script>
  </body>
</html>
