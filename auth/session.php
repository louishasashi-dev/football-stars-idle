<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('isLoggedIn')) {

    require_once __DIR__ . '/../config/app.php';

    function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    function requireLogin(): void {
        if (!isLoggedIn()) {
            header('Location: ' . url('/auth/login_page.php'));
            exit;
        }
    }

    function getCurrentUserId(): int {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    function getCurrentUsername(): string {
        return $_SESSION['username'] ?? '';
    }

    function setUserSession(array $user): void {
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];
    }

    function destroySession(): void {
        session_unset();
        session_destroy();
    }
}