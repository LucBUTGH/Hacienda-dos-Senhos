<?php

define('ADMIN_LOGIN',    'admin');
define('ADMIN_PASSWORD', 'admin');

function sessionStart(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function requireAdmin(): void {
    sessionStart();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function requireClient(): void {
    sessionStart();
    if (($_SESSION['role'] ?? '') !== 'client') {
        header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function isLoggedIn(): bool {
    sessionStart();
    return !empty($_SESSION['role']);
}
