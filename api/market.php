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

$userId = getCurrentUserId();
$action = $_POST['action'] ?? '';
$pdo    = getDB();

// ── JUAL PLAYER ──────────────────────────────────────────
if ($action === 'sell') {
    $playerId = (int) ($_POST['player_id'] ?? 0);
    $price    = (int) ($_POST['price'] ?? 0);

    if (!$playerId || $price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid.']);
        exit;
    }

    // Cek player milik user & kasta eksklusif
    $stmt = $pdo->prepare('
        SELECT fp.*, fpt.tier_name, fpt.is_exclusive
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

    if (!$player['is_exclusive']) {
        echo json_encode(['success' => false, 'message' => 'Hanya player Expert, Legendary, dan Goat yang bisa dijual di market.']);
        exit;
    }

    // Cek sudah ada di market
    $checkStmt = $pdo->prepare('SELECT id FROM market_listings WHERE player_id = ?');
    $checkStmt->execute([$playerId]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Player sudah ada di market.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Reset level player ke 1, reset stats ke base
        $tierStmt = $pdo->prepare('
            SELECT fpt.rating_min, fpt.base_offence, fpt.base_defence, fpt.base_teamwork
            FROM football_players fp
            JOIN f_player_tier fpt ON fp.tier_id = fpt.id
            WHERE fp.id = ?
        ');
        $tierStmt->execute([$playerId]);
        $tierData = $tierStmt->fetch();

        $pdo->prepare('
            UPDATE football_players
            SET current_level = 1,
                rating        = ?,
                offence       = ?,
                defence       = ?,
                teamwork      = ?
            WHERE id = ?
        ')->execute([
            $tierData['rating_min'],
            $tierData['base_offence'],
            $tierData['base_defence'],
            $tierData['base_teamwork'],
            $playerId
        ]);

        // Keluarkan dari tim
        $team = getUserTeam($userId);
        $pdo->prepare('DELETE FROM team_members WHERE team_id = ? AND player_id = ?')
            ->execute([$team['id'], $playerId]);

        // Tambah ke market
        $pdo->prepare('INSERT INTO market_listings (seller_id, player_id, price) VALUES (?, ?, ?)')
            ->execute([$userId, $playerId, $price]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Player berhasil didaftarkan ke market.']);

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal menjual: ' . $e->getMessage()]);
    }

// ── BELI PLAYER ──────────────────────────────────────────
} elseif ($action === 'buy') {
    $listingId = (int) ($_POST['listing_id'] ?? 0);

    if (!$listingId) {
        echo json_encode(['success' => false, 'message' => 'Listing tidak valid.']);
        exit;
    }

    $stmt = $pdo->prepare('
        SELECT ml.*, fp.player_name, fp.is_exclusive
        FROM market_listings ml
        JOIN football_players fp ON ml.player_id = fp.id
        WHERE ml.id = ?
    ');
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch();

    if (!$listing) {
        echo json_encode(['success' => false, 'message' => 'Listing tidak ditemukan.']);
        exit;
    }

    if ((int)$listing['seller_id'] === $userId) {
        echo json_encode(['success' => false, 'message' => 'Anda tidak bisa membeli player milik sendiri.']);
        exit;
    }

    $inventory = getInventory($userId);
    if ((int)$inventory['euro'] < (int)$listing['price']) {
        echo json_encode(['success' => false, 'message' => 'Euro tidak cukup.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Kurangi euro pembeli
        $pdo->prepare('UPDATE inventory_players SET euro = euro - ? WHERE user_id = ?')
            ->execute([$listing['price'], $userId]);

        // Tambah euro penjual
        $pdo->prepare('UPDATE inventory_players SET euro = euro + ? WHERE user_id = ?')
            ->execute([$listing['price'], $listing['seller_id']]);

        // Update owner player
        $pdo->prepare('UPDATE football_players SET owner_id = ? WHERE id = ?')
            ->execute([$userId, $listing['player_id']]);

        // Tambah ke tim pembeli
        $team = getUserTeam($userId);
        $pdo->prepare('INSERT IGNORE INTO team_members (team_id, player_id) VALUES (?, ?)')
            ->execute([$team['id'], $listing['player_id']]);

        // Hapus listing
        $pdo->prepare('DELETE FROM market_listings WHERE id = ?')
            ->execute([$listingId]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Berhasil membeli ' . $listing['player_name'] . '!']);

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal membeli: ' . $e->getMessage()]);
    }

// ── TARIK LISTING ─────────────────────────────────────────
} elseif ($action === 'cancel') {
    $listingId = (int) ($_POST['listing_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM market_listings WHERE id = ? AND seller_id = ?');
    $stmt->execute([$listingId, $userId]);
    $listing = $stmt->fetch();

    if (!$listing) {
        echo json_encode(['success' => false, 'message' => 'Listing tidak ditemukan.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Kembalikan ke tim (level tidak direset karena belum terjual)
        $team = getUserTeam($userId);
        $pdo->prepare('INSERT IGNORE INTO team_members (team_id, player_id) VALUES (?, ?)')
            ->execute([$team['id'], $listing['player_id']]);

        // Hapus listing
        $pdo->prepare('DELETE FROM market_listings WHERE id = ?')
            ->execute([$listingId]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Listing berhasil dibatalkan. Player kembali ke tim Anda.']);

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal membatalkan: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);
}