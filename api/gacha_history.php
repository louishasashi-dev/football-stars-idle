<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';

header('Content-Type: application/json');
requireLogin();

$userId = getCurrentUserId();
$pdo    = getDB();

$stmt = $pdo->prepare('
    SELECT
        gl.tier_result,
        gl.pity_count,
        gl.spun_at,
        fp.player_name,
        fp.rating,
        fp.country
    FROM gacha_log gl
    JOIN football_players fp ON fp.id = gl.player_id
    WHERE gl.user_id = ?
    ORDER BY gl.spun_at DESC
    LIMIT 50
');
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'history' => $history,
]);