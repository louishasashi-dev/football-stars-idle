<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$userId = getCurrentUserId();
$tournamentId = (int)($_POST['tournament_id'] ?? 0);
$pdo = getDB();

// Cek partisipasi
$stmt = $pdo->prepare('
    SELECT * FROM tournament_participants 
    WHERE tournament_id = ? AND user_id = ? AND prize_claimed = 0
');
$stmt->execute([$tournamentId, $userId]);
$participant = $stmt->fetch();

if (!$participant) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada hadiah yang bisa diklaim.']);
    exit;
}

if ($participant['position'] === null) {
    echo json_encode(['success' => false, 'message' => 'Posisi belum ditentukan.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Ambil inventory
    $inventory = getInventory($userId);
    
    // Update inventory
    $newEuro = (int)$inventory['euro'] + (int)$participant['prize_euro'];
    $newGems = (int)$inventory['gems'] + (int)$participant['prize_gems'];
    $newTr = (int)$inventory['tr_token'] + (int)$participant['prize_tr_token'];
    $newPr = (int)$inventory['pr_token'] + (int)$participant['prize_pr_token'];
    
    $pdo->prepare('
        UPDATE inventory_players 
        SET euro = ?, gems = ?, tr_token = ?, pr_token = ?
        WHERE user_id = ?
    ')->execute([$newEuro, $newGems, $newTr, $newPr, $userId]);
    
    // Assign prize player jika ada
    $playerId = null;
    if ($participant['position'] == 1 && $participant['prize_player_id']) {
        // Cari pemain dengan owner_id NULL
        $playerStmt = $pdo->prepare('
            SELECT id FROM football_players 
            WHERE owner_id IS NULL AND tier_id IN (6,7,9)
            ORDER BY RAND() LIMIT 1
        ');
        $playerStmt->execute();
        $player = $playerStmt->fetch();
        
        if ($player) {
            $pdo->prepare('UPDATE football_players SET owner_id = ? WHERE id = ?')
                ->execute([$userId, $player['id']]);
            $playerId = $player['id'];
            
            // Masukkan ke tim
            $team = getUserTeam($userId);
            $pdo->prepare('INSERT INTO team_members (team_id, player_id) VALUES (?, ?)')
                ->execute([$team['id'], $player['id']]);
        }
    }
    
    // Tandai sudah diklaim
    $pdo->prepare('UPDATE tournament_participants SET prize_claimed = 1 WHERE id = ?')
        ->execute([$participant['id']]);
    
    $pdo->commit();
    
    $msg = "Hadiah berhasil diklaim!";
    if ($playerId) {
        $playerStmt = $pdo->prepare('SELECT player_name FROM football_players WHERE id = ?');
        $playerStmt->execute([$playerId]);
        $player = $playerStmt->fetch();
        $msg .= " Kamu mendapatkan pemain eksklusif: " . $player['player_name'];
    }
    
    echo json_encode([
        'success' => true,
        'message' => $msg,
        'inventory' => getInventory($userId)
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
}