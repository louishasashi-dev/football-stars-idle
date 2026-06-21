<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$userId = getCurrentUserId();
$pdo = getDB();

$stmt = $pdo->prepare('
    SELECT * FROM inbox 
    WHERE user_id = ? 
    ORDER BY created_at DESC
');
$stmt->execute([$userId]);
$inbox = $stmt->fetchAll();

// Tandai sudah dibaca
$pdo->prepare('UPDATE inbox SET is_read = 1 WHERE user_id = ? AND is_read = 0')
    ->execute([$userId]);

echo json_encode([
    'success' => true,
    'inbox' => $inbox,
    'unread_count' => count(array_filter($inbox, fn($i) => !$i['is_read']))
]);