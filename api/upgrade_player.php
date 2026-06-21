<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak valid.']);
    exit;
}

$userId   = getCurrentUserId();
$playerId = (int) ($_POST['player_id'] ?? 0);
$pdo      = getDB();

if (!$playerId) {
    echo json_encode(['success' => false, 'message' => 'Player tidak valid.']);
    exit;
}

// Ambil data player + tier
$stmt = $pdo->prepare('
    SELECT fp.*, fpt.upgrade_base_cost, fpt.upgrade_stat_per_level, fpt.tier_name
    FROM football_players fp
    JOIN f_player_tier fpt ON fp.tier_id = fpt.id
    WHERE fp.id = ? AND fp.owner_id = ?
');
$stmt->execute([$playerId, $userId]);
$player = $stmt->fetch();

if (!$player) {
    echo json_encode(['success' => false, 'message' => 'Player tidak ditemukan atau bukan milik Anda.']);
    exit;
}

if ((int)$player['current_level'] >= 20) {
    echo json_encode(['success' => false, 'message' => 'Player sudah mencapai level maksimal (20).']);
    exit;
}

$currentLevel = (int) $player['current_level'];
$cost         = playerUpgradeCost((int)$player['upgrade_base_cost'], $currentLevel);
$statUp       = (int) $player['upgrade_stat_per_level'];

$inventory = getInventory($userId);
if ((int)$inventory['tr_token'] < $cost) {
    echo json_encode([
        'success' => false,
        'message' => 'TR Token tidak cukup. Butuh: ' . $cost
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    // Kurangi TR Token
    $pdo->prepare('UPDATE inventory_players SET tr_token = tr_token - ? WHERE user_id = ?')
        ->execute([$cost, $userId]);

    // Naikkan stats dan level
    $pdo->prepare('
        UPDATE football_players
        SET current_level = current_level + 1,
            offence       = offence + ?,
            defence       = defence + ?,
            teamwork      = teamwork + ?,
            rating        = rating + ?
        WHERE id = ?
    ')->execute([$statUp, $statUp, $statUp, (int)ceil($statUp / 2), $playerId]);

    $pdo->commit();

    $newInventory = getInventory($userId);
    $updatedPlayer = $pdo->prepare('SELECT * FROM football_players WHERE id = ?');
    $updatedPlayer->execute([$playerId]);
    $playerData = $updatedPlayer->fetch();

    echo json_encode([
        'success'    => true,
        'message'    => 'Player berhasil di-upgrade ke level ' . ($currentLevel + 1) . '!',
        'new_level'  => $currentLevel + 1,
        'next_cost'  => playerUpgradeCost((int)$player['upgrade_base_cost'], $currentLevel + 1),
        'player'     => $playerData,
        'inventory'  => $newInventory,
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Gagal upgrade player: ' . $e->getMessage()]);
}