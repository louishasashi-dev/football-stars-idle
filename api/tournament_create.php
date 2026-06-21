<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$userId = getCurrentUserId();
$pdo = getDB();

$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$maxParticipants = (int)($_POST['max_participants'] ?? 8);
$entryFee = (int)($_POST['entry_fee'] ?? 0);
$prizePool = (int)($_POST['prize_pool'] ?? 100);

if (strlen($name) < 3) {
    echo json_encode(['success' => false, 'message' => 'Nama turnamen minimal 3 karakter.']);
    exit;
}

if (!in_array($maxParticipants, [4, 8, 16, 32])) {
    echo json_encode(['success' => false, 'message' => 'Max peserta tidak valid.']);
    exit;
}

if ($entryFee < 0 || $prizePool < 0) {
    echo json_encode(['success' => false, 'message' => 'Biaya masuk dan hadiah tidak boleh negatif.']);
    exit;
}

$inventory = getInventory($userId);
if ($entryFee > 0 && (int)$inventory['gems'] < $entryFee) {
    echo json_encode(['success' => false, 'message' => 'Gems tidak cukup untuk biaya masuk.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Buat turnamen
    $pdo->prepare('
        INSERT INTO tournaments (creator_id, name, description, max_participants, entry_fee, prize_pool)
        VALUES (?, ?, ?, ?, ?, ?)
    ')->execute([$userId, $name, $description, $maxParticipants, $entryFee, $prizePool]);

    $tournamentId = $pdo->lastInsertId();

    // Kurangi biaya masuk
    if ($entryFee > 0) {
        $pdo->prepare('UPDATE inventory_players SET gems = gems - ? WHERE user_id = ?')
            ->execute([$entryFee, $userId]);
    }

    // Tambah creator sebagai peserta
    $pdo->prepare('
        INSERT INTO tournament_participants (tournament_id, user_id)
        VALUES (?, ?)
    ')->execute([$tournamentId, $userId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Turnamen '$name' berhasil dibuat!",
        'tournament_id' => $tournamentId
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
}