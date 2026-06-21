<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/auth/session.php';

if (isLoggedIn()) {
    header('Location: ' . url('/pages/dashboard.php'));
} else {
    header('Location: ' . url('/auth/login_page.php'));
}
exit;