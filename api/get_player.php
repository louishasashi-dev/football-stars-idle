<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$userId   = getCurrentUserId();
$playerId = (int)($_POST['player_id'] ?? 0);
$pdo      = getDB();

$stmt = $pdo->prepare('
    SELECT fp.*, fpt.tier_name, fpt.card_color, fpt.upgrade_base_cost, fpt.upgrade_stat_per_level
    FROM football_players fp
    JOIN f_player_tier fpt ON fp.tier_id = fpt.id
    WHERE fp.id = ? AND fp.owner_id = ?
');
$stmt->execute([$playerId, $userId]);
$player = $stmt->fetch();

if (!$player) {
    echo json_encode(['success' => false, 'message' => 'Player tidak ditemukan.']);
    exit;
}

$skills    = getPlayerSkills($playerId);
$nextCost  = playerUpgradeCost((int)$player['upgrade_base_cost'], (int)$player['current_level']);

echo json_encode([
    'success'           => true,
    'player'            => $player,
    'skills'            => $skills,
    'next_upgrade_cost' => $nextCost,
]);