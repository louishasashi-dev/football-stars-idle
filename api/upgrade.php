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

$userId      = getCurrentUserId();
$type        = $_POST['type']    ?? '';   // 'power' atau 'income'
$paymentMode = $_POST['payment'] ?? '';   // 'euro' atau 'wintoken'
$pdo         = getDB();

if (!in_array($type, ['power', 'income']) || !in_array($paymentMode, ['euro', 'wintoken'])) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid.']);
    exit;
}

$team      = getUserTeam($userId);
$inventory = getInventory($userId);

if (!$team || !$inventory) {
    echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan.']);
    exit;
}

// ── TENTUKAN KOLOM DAN BIAYA ──────────────────────────────
$isEuro = ($paymentMode === 'euro');

if ($type === 'power') {
    $levelCol = $isEuro ? 'power_level_euro' : 'power_level_wintoken';
    $costCol  = $isEuro ? 'power_upgrade_cost_euro' : 'power_upgrade_cost_wintoken';
} else {
    $levelCol = $isEuro ? 'income_level_euro' : 'income_level_wintoken';
    $costCol  = $isEuro ? 'income_upgrade_cost_euro' : 'income_upgrade_cost_wintoken';
}

$currentLevel = (int) $team[$levelCol];
$currentCost  = (int) $team[$costCol];
$currencyCol  = $isEuro ? 'euro' : 'win_token';
$currencyName = $isEuro ? 'Euro' : 'Win Token';

// ── CEK SALDO ──────────────────────────────────────────────
if ((int)$inventory[$currencyCol] < $currentCost) {
    echo json_encode([
        'success' => false,
        'message' => "Saldo $currencyName tidak cukup. Butuh: " . number_format($currentCost)
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    // Kurangi saldo
    $pdo->prepare("UPDATE inventory_players SET $currencyCol = $currencyCol - ? WHERE user_id = ?")
        ->execute([$currentCost, $userId]);

    // Naikkan level
    $newLevel = $currentLevel + 1;

    // Hitung biaya berikutnya
    if ($isEuro) {
        $nextCost = (int)($currentCost * 1.2);
        if ($nextCost < $currentCost + 1) $nextCost = $currentCost + 1;
    } else {
        $nextCost = $currentCost + 2;
    }

    $pdo->prepare("UPDATE user_teams SET $levelCol = ?, $costCol = ? WHERE user_id = ?")
        ->execute([$newLevel, $nextCost, $userId]);

    // Recalculate team power jika upgrade power
    if ($type === 'power') {
        $pdo->prepare("UPDATE user_teams SET team_power = team_power + 50 WHERE user_id = ?")
            ->execute([$userId]);
    }

    $pdo->commit();

    // ── AMBIL DATA TERBARU ──────────────────────────────────
    $newInventory = getInventory($userId);
    $newTeam      = getUserTeam($userId);
    $newIncome    = calculateBaseIncome(
        (int) $newTeam['income_level_euro'],
        (int) $newTeam['income_level_wintoken']
    );

    echo json_encode([
        'success'    => true,
        'message'    => ucfirst($type) . " berhasil di-upgrade ke level $newLevel!",
        'new_level'  => $newLevel,
        'next_cost'  => $nextCost,
        'inventory'  => $newInventory,
        'base_income'=> $newIncome,
        'new_power'  => (int)$newTeam['team_power'],
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Gagal upgrade: ' . $e->getMessage()
    ]);
}