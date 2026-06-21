<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$userId = getCurrentUserId();
$tournamentId = (int)($_POST['tournament_id'] ?? 0);
$pdo = getDB();

// Cek turnamen
$stmt = $pdo->prepare('SELECT * FROM tournaments WHERE id = ? AND status = "open"');
$stmt->execute([$tournamentId]);
$tournament = $stmt->fetch();

if (!$tournament) {
    echo json_encode(['success' => false, 'message' => 'Turnamen tidak ditemukan atau sudah dimulai.']);
    exit;
}

// Cek sudah join
$check = $pdo->prepare('SELECT id FROM tournament_participants WHERE tournament_id = ? AND user_id = ?');
$check->execute([$tournamentId, $userId]);
if ($check->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Anda sudah bergabung.']);
    exit;
}

// Cek kuota
$count = $pdo->prepare('SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = ?');
$count->execute([$tournamentId]);
$participants = (int)$count->fetchColumn();

if ($participants >= $tournament['max_participants']) {
    echo json_encode(['success' => false, 'message' => 'Kuota peserta penuh.']);
    exit;
}

// Cek biaya masuk
$inventory = getInventory($userId);
if ($tournament['entry_fee'] > 0 && (int)$inventory['gems'] < $tournament['entry_fee']) {
    echo json_encode(['success' => false, 'message' => 'Gems tidak cukup untuk biaya masuk.']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($tournament['entry_fee'] > 0) {
        $pdo->prepare('UPDATE inventory_players SET gems = gems - ? WHERE user_id = ?')
            ->execute([$tournament['entry_fee'], $userId]);
    }

    $pdo->prepare('INSERT INTO tournament_participants (tournament_id, user_id) VALUES (?, ?)')
        ->execute([$tournamentId, $userId]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Berhasil bergabung!']);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
}