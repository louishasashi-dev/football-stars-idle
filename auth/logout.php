<?php
require_once __DIR__ . '/session.php';
destroySession();
header('Location: ' . url('/auth/login_page.php'));
exit;