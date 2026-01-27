<?php
/*
===========================================================
 File: lib/auth.php
 Author: Jason Lamb (with help from AI)
 Created: 2026-01-19
 Modified: 2026-01-19
 Revision: 1.0

 Description:
   Session-based authentication helper.
===========================================================
*/

date_default_timezone_set('America/New_York');
session_start();

define('USER_FILE', __DIR__ . '/../data/users.json');

function loadUsers(): array {
    if (!file_exists(USER_FILE)) {
        return [];
    }
    return json_decode(file_get_contents(USER_FILE), true)['users'] ?? [];
}

function isLoggedIn(): bool {
    return isset($_SESSION['user']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function login(string $username, string $password): bool {
    $users = loadUsers();

    if (!isset($users[$username])) {
        return false;
    }

    if (!password_verify($password, $users[$username]['password'])) {
        return false;
    }

    $_SESSION['user'] = $username;
    return true;
}

function logout(): void {
    session_destroy();
}
