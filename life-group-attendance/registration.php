<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

/** Store an inactive leader request. Never replace or activate an existing user. */
function register_leader(array $input, string $source): array {
    $name = trim((string)($input['name'] ?? ''));
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $password = (string)($input['password'] ?? '');
    if ($name === '' || strlen($name) > 120 || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter your name and a valid email address.');
    }
    if (strlen($password) < 12 || strlen($password) > 72) {
        throw new InvalidArgumentException('Use a password between 12 and 72 bytes long.');
    }
    if (!hash_equals($password, (string)($input['confirmPassword'] ?? ''))) {
        throw new InvalidArgumentException('The passwords do not match.');
    }
    if (!is_dir(DATA_DIR)) throw new RuntimeException('An administrator must set up the portal first.');
    $lock = fopen(DATA_DIR . '/users.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Registration is temporarily unavailable.');
    try {
        $users = read_store('users');
        if (!$users) throw new RuntimeException('An administrator must set up the portal first.');
        $sourceHash = hash('sha256', $source);
        $recent = 0; $pending = 0;
        foreach ($users as $u) {
            if (strcasecmp($u['email'], $email) === 0) {
                // Reveal status only after verifying the existing account password.
                if (!password_verify($password, $u['passwordHash'])) {
                    throw new InvalidArgumentException('Unable to register with these details. If you already have an account, sign in or contact your Super Admin. No account was changed.');
                }
                return ['status'=>($u['pendingRegistration']??false)?'pending':(($u['active']??true)?'active':'disabled'), 'id'=>$u['id'], 'created'=>false];
            }
            if ($u['pendingRegistration'] ?? false) $pending++;
            if (($u['registrationSource'] ?? '') === $sourceHash && strtotime($u['createdAt'] ?? '') > time() - 3600) $recent++;
        }
        if ($recent >= 5 || $pending >= 100) throw new RuntimeException('Registration is temporarily limited. Please contact a Super Admin.');
        $id = uuid();
        $users[] = ['id'=>$id, 'name'=>$name, 'email'=>$email, 'role'=>'attendance',
            'passwordHash'=>password_hash($password, PASSWORD_DEFAULT), 'active'=>false,
            'pendingRegistration'=>true, 'registrationSource'=>$sourceHash, 'createdAt'=>now_iso()];
        write_store('users', $users);
        // Do not show a saved receipt until the persisted row can be read back.
        $saved = null;
        foreach (read_store('users') as $row) if (($row['id']??'') === $id) { $saved=$row; break; }
        if (!$saved || !($saved['pendingRegistration']??false) || ($saved['active']??true) || $saved['email'] !== $email || !password_verify($password, $saved['passwordHash'])) {
            throw new RuntimeException('Unable to verify saved registration.');
        }
        return ['status'=>'pending', 'id'=>$id, 'created'=>true];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
