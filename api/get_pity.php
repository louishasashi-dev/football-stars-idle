<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';

header('Content-Type: application/json');
requireLogin();

$userId = getCurrentUserId();
$pdo    = getDB();

$stmt = $pdo->prepare('SELECT spin_count_since_pity FROM pity_tracker WHERE user_id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();

echo json_encode([
    'success'    => true,
    'pity_count' => (int)($row['spin_count_since_pity'] ?? 0),
]);