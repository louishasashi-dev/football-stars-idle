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

$userId  = getCurrentUserId();
$spinQty = (int)($_POST['qty'] ?? 1);
$pdo     = getDB();

if (!in_array($spinQty, [1, 10])) {
    echo json_encode(['success' => false, 'message' => 'Jumlah spin tidak valid.']);
    exit;
}

$inventory = getInventory($userId);

// ── CEK FREE SPIN ─────────────────────────────────────────────
$isFreeSpin = (!$inventory['first_10spin_used'] && $spinQty === 1);
$cost       = 0;

if (!$isFreeSpin) {
    $cost = $spinQty === 1 ? 10 : ($inventory['first_10spin_used'] ? 100 : 50);

    if ((int)$inventory['gems'] < $cost) {
        echo json_encode([
            'success' => false,
            'message' => 'Gems tidak cukup. Butuh ' . $cost . ' 💎'
        ]);
        exit;
    }
}

// ── DROP RATE ─────────────────────────────────────────────────
// ✅ TAMBAHKAN ASEAN Legend
$dropRates = [
    'Amateur'      => 45,
    'Trained'      => 20,
    'Talented'     => 10,
    'Semi-Pro'     => 10,
    'Pro'          => 7,
    'Expert'       => 5,
    'Legendary'    => 3,
    'ASEAN Legend' => 0.5,
    'Celestial'    => 0.3, 
];

// ── TIER YANG KENA AUTO-EXCHANGE JIKA DUPLIKAT ─────────────────
$DUPLICATE_EXCHANGE_TIERS = ['Amateur', 'Trained', 'Talented', 'Semi-Pro', 'Pro', 'Expert'];

// Reward TR Token saat duplikat
$trRewardMap = [
    'Amateur'      => 5,
    'Trained'      => 15,
    'Talented'     => 30,
    'Semi-Pro'     => 50,
    'Pro'          => 70,
    'ASEAN Legend' => 200,
    'Celestial'    => 300, 
];

// ── PITY ──────────────────────────────────────────────────────
$pityStmt = $pdo->prepare('SELECT spin_count_since_pity FROM pity_tracker WHERE user_id = ?');
$pityStmt->execute([$userId]);
$pityRow   = $pityStmt->fetch();
$pityCount = (int)($pityRow['spin_count_since_pity'] ?? 0);

// ── FUNGSI ROLL ───────────────────────────────────────────────
// ✅ TAMBAHKAN ASEAN Legend di pity
function rollTier(array $rates, bool $forcePity): string {
    if ($forcePity) {
        // Pity: chance lebih tinggi untuk tier langka
        $roll = rand(1, 100);
        if ($roll <= 35) return 'Expert';
        if ($roll <= 60) return 'Legendary';
        if ($roll <= 75) return 'ASEAN Legend';
        if ($roll <= 85) return 'Celestial';
        return 'Expert';
    }
    $roll = rand(1, 100);
    $acc  = 0;
    foreach ($rates as $tier => $rate) {
        $acc += $rate;
        if ($roll <= $acc) return $tier;
    }
    return 'Amateur';
}

// ── CARI PLAYER TEMPLATE ──────────────────────────────────────
function getPlayerTemplate(PDO $pdo, string $tierName): ?array {
    $stmt = $pdo->prepare('
        SELECT fp.* FROM football_players fp
        JOIN f_player_tier fpt ON fp.tier_id = fpt.id
        WHERE fpt.tier_name = ?
          AND fp.is_custom   = 0
          AND fp.owner_id IS NULL
          AND fp.id NOT IN (SELECT player_id FROM market_listings)
        ORDER BY RAND()
        LIMIT 1
    ');
    $stmt->execute([$tierName]);
    return $stmt->fetch() ?: null;
}

// ── FALLBACK TIER ─────────────────────────────────────────────
function getFallbackTier(string $tier): string {
    $order = ['Amateur','Trained','Talented','Semi-Pro','Pro','Expert','ASEAN Legend','Celestial','Legendary'];
    $idx   = array_search($tier, $order);
    if ($idx === false || $idx === 0) return 'Amateur';
    return $order[$idx - 1];
}

// ── CEK APAKAH USER SUDAH PUNYA PEMAIN DENGAN NAMA INI ─────────
function userHasPlayerName(PDO $pdo, int $userId, string $playerName): bool {
    $stmt = $pdo->prepare('
        SELECT id FROM football_players
        WHERE owner_id = ? AND player_name = ?
        LIMIT 1
    ');
    $stmt->execute([$userId, $playerName]);
    return (bool) $stmt->fetch();
}

// ── BUAT SALINAN PEMAIN UNTUK USER ────────────────────────────
function assignPlayerToUser(PDO $pdo, array $player, int $userId, int $teamId): array {
    if ($player['is_exclusive']) {
        $pdo->prepare('UPDATE football_players SET owner_id = ? WHERE id = ?')
            ->execute([$userId, $player['id']]);

        $check = $pdo->prepare('SELECT id FROM team_members WHERE team_id = ? AND player_id = ?');
        $check->execute([$teamId, $player['id']]);
        if (!$check->fetch()) {
            $pdo->prepare('INSERT INTO team_members (team_id, player_id) VALUES (?, ?)')
                ->execute([$teamId, $player['id']]);
        }

        return $player;

    } else {
        $pdo->prepare('
            INSERT INTO football_players
                (player_name, country, position, rating, offence, defence,
                 teamwork, tier_id, card_image, is_custom, is_exclusive,
                 owner_id, current_level)
            SELECT
                player_name, country, position, rating, offence, defence,
                teamwork, tier_id, card_image, is_custom, is_exclusive,
                ?, current_level
            FROM football_players
            WHERE id = ?
        ')->execute([$userId, $player['id']]);

        $newId = (int)$pdo->lastInsertId();

        $skills = $pdo->prepare('SELECT skill_id FROM player_skills WHERE player_id = ?');
        $skills->execute([$player['id']]);
        $insertSkill = $pdo->prepare('INSERT IGNORE INTO player_skills (player_id, skill_id) VALUES (?, ?)');
        foreach ($skills->fetchAll() as $sk) {
            $insertSkill->execute([$newId, $sk['skill_id']]);
        }

        $pdo->prepare('INSERT INTO team_members (team_id, player_id) VALUES (?, ?)')
            ->execute([$teamId, $newId]);

        $player['id']       = $newId;
        $player['owner_id'] = $userId;
        return $player;
    }
}

// ── PROSES SPIN ───────────────────────────────────────────────
$results   = [];
$trEarned  = 0;

try {
    $pdo->beginTransaction();

    if (!$isFreeSpin) {
        $pdo->prepare('UPDATE inventory_players SET gems = gems - ? WHERE user_id = ?')
            ->execute([$cost, $userId]);
    }

    if ($isFreeSpin || ($spinQty === 10 && !$inventory['first_10spin_used'])) {
        $pdo->prepare('UPDATE inventory_players SET first_10spin_used = 1 WHERE user_id = ?')
            ->execute([$userId]);
    }

    $team   = getUserTeam($userId);
    $teamId = $team ? (int)$team['id'] : 0;

    if (!$teamId) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Tim tidak ditemukan.']);
        exit;
    }

    for ($i = 0; $i < $spinQty; $i++) {
        $pityCount++;
        $forcePity = ($pityCount >= 10);

        $tierName = rollTier($dropRates, $forcePity);

        $template = null;
        $attempts = 0;
        $currentTier = $tierName;

        while (!$template && $attempts < 6) {
            $template = getPlayerTemplate($pdo, $currentTier);
            if (!$template) {
                $currentTier = getFallbackTier($currentTier);
            }
            $attempts++;
        }

        if (!$template) {
            $template    = getPlayerTemplate($pdo, 'Amateur');
            $currentTier = 'Amateur';
        }

        if (!$template) continue;

        $logPity = $pityCount;
        if ($forcePity) $pityCount = 0;

        // ✅ ASEAN Legend TIDAK masuk duplicate exchange
        $isDuplicateTier = in_array($currentTier, $DUPLICATE_EXCHANGE_TIERS);
        $isDuplicate      = $isDuplicateTier
            && userHasPlayerName($pdo, $userId, $template['player_name']);

        if ($isDuplicate) {
            $reward = $trRewardMap[$currentTier] ?? 0;

            if ($reward > 0) {
                $pdo->prepare('UPDATE inventory_players SET tr_token = tr_token + ? WHERE user_id = ?')
                    ->execute([$reward, $userId]);
                $trEarned += $reward;
            }

            $pdo->prepare('
                INSERT INTO gacha_log (user_id, player_id, tier_result, pity_count)
                VALUES (?, ?, ?, ?)
            ')->execute([$userId, $template['id'], $currentTier, $logPity]);

            $results[] = [
                'player_name'   => $template['player_name'],
                'tier'          => $currentTier,
                'rating'        => $template['rating'],
                'player_id'     => $template['id'],
                'country'       => $template['country'],
                'is_new'        => false,
                'is_duplicate'  => true,
                'tr_reward'     => $reward,
            ];

            continue;
        }

        $assignedPlayer = assignPlayerToUser($pdo, $template, $userId, $teamId);

        $pdo->prepare('
            INSERT INTO gacha_log (user_id, player_id, tier_result, pity_count)
            VALUES (?, ?, ?, ?)
        ')->execute([$userId, $assignedPlayer['id'], $currentTier, $logPity]);

        $results[] = [
            'player_name'  => $assignedPlayer['player_name'],
            'tier'         => $currentTier,
            'rating'       => $assignedPlayer['rating'],
            'player_id'    => $assignedPlayer['id'],
            'country'      => $assignedPlayer['country'],
            'is_new'       => true,
            'is_duplicate' => false,
        ];
    }

    $pdo->prepare('UPDATE pity_tracker SET spin_count_since_pity = ? WHERE user_id = ?')
        ->execute([$pityCount, $userId]);

    $pdo->commit();

    $newInventory = getInventory($userId);

    $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM gacha_log WHERE user_id = ?');
    $totalStmt->execute([$userId]);
    $totalSpins = (int)$totalStmt->fetchColumn();

    echo json_encode([
        'success'     => true,
        'results'     => $results,
        'inventory'   => $newInventory,
        'is_free'     => $isFreeSpin,
        'pity_count'  => $pityCount,
        'total_spins' => $totalSpins,
        'tr_earned'   => $trEarned,
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Gagal spin: ' . $e->getMessage()
    ]);
}