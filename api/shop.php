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

// ── BELI GOAT ────────────────────────────────────────────
if ($action === 'buy_goat') {
    $playerId  = (int) ($_POST['player_id'] ?? 0);
    $inventory = getInventory($userId);

    if ((int)$inventory['gems'] < 1000) {
        echo json_encode(['success' => false, 'message' => 'Gems tidak cukup. Butuh 1000 gems.']);
        exit;
    }

    $stmt = $pdo->prepare('
        SELECT fp.*, fpt.tier_name FROM football_players fp
        JOIN f_player_tier fpt ON fp.tier_id = fpt.id
        WHERE fp.id = ? AND fpt.tier_name = "Goat" AND fp.owner_id IS NULL
    ');
    $stmt->execute([$playerId]);
    $player = $stmt->fetch();

    if (!$player) {
        echo json_encode(['success' => false, 'message' => 'Player Goat tidak tersedia atau sudah dimiliki orang lain.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare('UPDATE inventory_players SET gems = gems - 1000 WHERE user_id = ?')
            ->execute([$userId]);

        $pdo->prepare('UPDATE football_players SET owner_id = ? WHERE id = ?')
            ->execute([$userId, $playerId]);

        $team = getUserTeam($userId);
        $pdo->prepare('INSERT IGNORE INTO team_members (team_id, player_id) VALUES (?, ?)')
            ->execute([$team['id'], $playerId]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => $player['player_name'] . ' berhasil dibeli!']);

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal membeli: ' . $e->getMessage()]);
    }
    exit;
}

// ── BELI PR TOKEN ─────────────────────────────────────────
if ($action === 'buy_pr_token') {
    $qty       = (int) ($_POST['qty'] ?? 1);
    $costPerPR = 50;
    $total     = $costPerPR * $qty;
    $inventory = getInventory($userId);

    if ($qty < 1) {
        echo json_encode(['success' => false, 'message' => 'Jumlah tidak valid.']);
        exit;
    }

    if ((int)$inventory['gems'] < $total) {
        echo json_encode(['success' => false, 'message' => 'Gems tidak cukup. Butuh: ' . $total]);
        exit;
    }

    $pdo->prepare('
        UPDATE inventory_players
        SET gems = gems - ?, pr_token = pr_token + ?
        WHERE user_id = ?
    ')->execute([$total, $qty, $userId]);

    echo json_encode([
        'success'   => true,
        'message'   => $qty . ' PR Token berhasil dibeli!',
        'inventory' => getInventory($userId),
    ]);
    exit;
}

// ── TUKAR PLAYER DENGAN TR TOKEN ─────────────────────────
if ($action === 'exchange_player') {
    $playerId = (int) ($_POST['player_id'] ?? 0);

    $stmt = $pdo->prepare('
        SELECT fp.*, fpt.tier_name, fpt.is_exclusive
        FROM football_players fp
        JOIN f_player_tier fpt ON fp.tier_id = fpt.id
        WHERE fp.id = ? AND fp.owner_id = ?
    ');
    $stmt->execute([$playerId, $userId]);
    $player = $stmt->fetch();

    if (!$player || $player['is_exclusive']) {
        echo json_encode(['success' => false, 'message' => 'Player tidak valid atau tidak bisa ditukar.']);
        exit;
    }

    $trReward = [
        'Amateur'  => 5,
        'Trained'  => 15,
        'Talented' => 30,
        'Semi-Pro' => 50,
        'Pro'      => 70,
    ];

    $tierName = $player['tier_name'];
    if (!isset($trReward[$tierName])) {
        echo json_encode(['success' => false, 'message' => 'Kasta ini tidak bisa ditukar.']);
        exit;
    }

    $reward = $trReward[$tierName];

    try {
        $pdo->beginTransaction();

        // Keluarkan dari tim
        $team = getUserTeam($userId);
        $pdo->prepare('DELETE FROM team_members WHERE team_id = ? AND player_id = ?')
            ->execute([$team['id'], $playerId]);

        // Hapus player (custom) atau reset owner (default)
        if ($player['is_custom']) {
            $pdo->prepare('DELETE FROM football_players WHERE id = ?')
                ->execute([$playerId]);
        } else {
            $pdo->prepare('UPDATE football_players SET owner_id = NULL WHERE id = ?')
                ->execute([$playerId]);
        }

        // Tambah TR Token
        $pdo->prepare('UPDATE inventory_players SET tr_token = tr_token + ? WHERE user_id = ?')
            ->execute([$reward, $userId]);

        $pdo->commit();

        echo json_encode([
            'success'   => true,
            'message'   => 'Player ditukar dengan ' . $reward . ' TR Token!',
            'tr_reward' => $reward,
            'inventory' => getInventory($userId),
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal tukar player: ' . $e->getMessage()]);
    }
    exit;
}

// ── NAIK KASTA PLAYER (PR TOKEN) ─────────────────────────
if ($action === 'upgrade_tier') {
    $playerId = (int)($_POST['player_id'] ?? 0);

    if ($playerId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID pemain tidak valid.']);
        exit;
    }

    // Ambil data pemain
    $stmt = $pdo->prepare('
        SELECT fp.*, fpt.tier_name, fpt.tier_order, fpt.pr_token_cost, fpt.is_exclusive
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

    // Cek apakah bisa naik kasta
    $maxTiers = ['Legendary', 'Goat', 'ASEAN Legend'];
    if (in_array($player['tier_name'], $maxTiers)) {
        echo json_encode(['success' => false, 'message' => 'Player sudah di kasta tertinggi!']);
        exit;
    }

    $prCost = (int) $player['pr_token_cost'];
    $inventory = getInventory($userId);

    if ((int)$inventory['pr_token'] < $prCost) {
        echo json_encode([
            'success' => false,
            'message' => 'PR Token tidak cukup! Butuh ' . $prCost . ' ⬆️ PR Token.'
        ]);
        exit;
    }

    // Ambil tier berikutnya
    $nextTierStmt = $pdo->prepare('
        SELECT * FROM f_player_tier WHERE tier_order = ?
    ');
    $nextTierStmt->execute([$player['tier_order'] + 1]);
    $nextTier = $nextTierStmt->fetch();

    if (!$nextTier) {
        echo json_encode(['success' => false, 'message' => 'Tier berikutnya tidak ditemukan.']);
        exit;
    }

    // Jika naik ke Expert/Legendary, player jadi eksklusif
    $becomeExclusive = $nextTier['is_exclusive'] ? 1 : 0;

    try {
        $pdo->beginTransaction();

        // Kurangi PR Token
        $pdo->prepare('UPDATE inventory_players SET pr_token = pr_token - ? WHERE user_id = ?')
            ->execute([$prCost, $userId]);

        // Update player ke tier baru
        $pdo->prepare('
            UPDATE football_players
            SET tier_id      = ?,
                rating       = ?,
                offence      = ?,
                defence      = ?,
                teamwork     = ?,
                is_exclusive = ?,
                current_level = 1
            WHERE id = ?
        ')->execute([
            $nextTier['id'],
            $nextTier['rating_min'],
            $nextTier['base_offence'],
            $nextTier['base_defence'],
            $nextTier['base_teamwork'],
            $becomeExclusive,
            $playerId
        ]);

        $pdo->commit();

        echo json_encode([
            'success'    => true,
            'message'    => '✅ Player berhasil naik kasta ke ' . $nextTier['tier_name'] . '!',
            'new_tier'   => $nextTier['tier_name'],
            'inventory'  => getInventory($userId),
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal upgrade kasta: ' . $e->getMessage()]);
    }
    exit;
}

// ── DEFAULT ────────────────────────────────────────────────
echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);