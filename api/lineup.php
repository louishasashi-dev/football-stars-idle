<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$userId = getCurrentUserId();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$pdo    = getDB();

$team = getUserTeam($userId);
if (!$team) {
    echo json_encode(['success' => false, 'message' => 'Tim tidak ditemukan.']);
    exit;
}

$teamId = (int)$team['id'];

// ── HELPER: GET OR CREATE LINEUP ─────────────────────────────
function getOrCreateLineup(PDO $pdo, int $teamId): array {
    $stmt = $pdo->prepare('SELECT * FROM lineups WHERE team_id = ?');
    $stmt->execute([$teamId]);
    $lineup = $stmt->fetch();

    if (!$lineup) {
        $pdo->prepare('INSERT INTO lineups (team_id, formation) VALUES (?, "4-3-3")')
            ->execute([$teamId]);
        $lineupId = (int)$pdo->lastInsertId();
        $lineup   = ['id' => $lineupId, 'team_id' => $teamId, 'formation' => '4-3-3'];
    }

    return $lineup;
}

// ── HELPER: GET LINEUP PLAYERS ────────────────────────────────
function getLineupPlayers(PDO $pdo, int $lineupId): array {
    // PENTING: kolom `id` harus diambil dari football_players (fp.id),
    // bukan dari lineup_players (lp.id). Memakai `lp.*` lalu menambahkan
    // kolom fp.* tanpa alias eksplisit menyebabkan PDO mengembalikan
    // `id` sebagai lineup_players.id, sehingga di frontend pemain bisa
    // ketukar dengan pemain lain yang kebetulan punya id sama dengan
    // baris lineup_players tersebut.
    $stmt = $pdo->prepare('
        SELECT fp.id, fp.player_name, fp.position AS default_position,
               fp.rating, fp.offence, fp.defence, fp.teamwork,
               fp.card_image, fp.current_level,
               fpt.tier_name, fpt.card_color,
               lp.id AS lineup_player_id,
               lp.slot_type, lp.slot_index,
               lp.position
        FROM lineup_players lp
        JOIN football_players fp ON fp.id = lp.player_id
        JOIN f_player_tier fpt   ON fpt.id = fp.tier_id
        WHERE lp.lineup_id = ?
        ORDER BY lp.slot_type ASC, lp.slot_index ASC
    ');
    $stmt->execute([$lineupId]);
    return $stmt->fetchAll();
}

// ── GET LINEUP ────────────────────────────────────────────────
if ($action === 'get') {
    $lineup        = getOrCreateLineup($pdo, $teamId);
    $lineupPlayers = getLineupPlayers($pdo, $lineup['id']);

    // Semua pemain tim (untuk picker)
    $allPlayers = getTeamPlayers($teamId);

    // ID yang sudah ada di lineup
    $inLineup = array_column($lineupPlayers, 'id');

    // Pemain yang belum di lineup
    $available = array_filter($allPlayers, fn($p) => !in_array($p['id'], $inLineup));

    echo json_encode([
        'success'   => true,
        'lineup'    => $lineup,
        'players'   => $lineupPlayers,
        'available' => array_values($available),
    ]);
    exit;
}

// ── SAVE LINEUP ───────────────────────────────────────────────
if ($action === 'save') {
    $formation = $_POST['formation'] ?? '4-3-3';
    $starting  = json_decode($_POST['starting'] ?? '[]', true);  // [{player_id, position}]
    $bench     = json_decode($_POST['bench']    ?? '[]', true);  // [{player_id}]

    // Validasi formasi
    $validFormations = ['4-3-3','4-4-2','4-2-3-1','3-5-2','3-4-3','5-3-2','5-4-1'];
    if (!in_array($formation, $validFormations)) {
        echo json_encode(['success' => false, 'message' => 'Formasi tidak valid.']);
        exit;
    }

    // Validasi jumlah
    if (count($starting) > 11) {
        echo json_encode(['success' => false, 'message' => 'Starting max 11 pemain.']);
        exit;
    }
    if (count($bench) > 8) {
        echo json_encode(['success' => false, 'message' => 'Cadangan max 8 pemain.']);
        exit;
    }

    // Cek tidak ada duplikat
    $allIds = array_merge(
        array_column($starting, 'player_id'),
        array_column($bench,    'player_id')
    );
    if (count($allIds) !== count(array_unique($allIds))) {
        echo json_encode(['success' => false, 'message' => 'Ada pemain duplikat.']);
        exit;
    }

    // Cek semua pemain milik user ini
    if (!empty($allIds)) {
        $intIds       = array_map('intval', $allIds);
        $placeholders = implode(',', array_fill(0, count($intIds), '?'));

        // Ambil semua pemain yang valid untuk user ini:
        // 1. Ada di team_members tim user
        // 2. ATAU owner_id = user ini
        $validStmt = $pdo->prepare("
            SELECT DISTINCT fp.id
            FROM football_players fp
            WHERE fp.id IN ($placeholders)
              AND (
                fp.owner_id = ?
                OR EXISTS (
                    SELECT 1 FROM team_members tm
                    WHERE tm.player_id = fp.id
                      AND tm.team_id = ?
                )
              )
        ");
        $validStmt->execute(array_merge($intIds, [$userId, $teamId]));
        $validIds = array_map('intval', array_column($validStmt->fetchAll(), 'id'));

        foreach ($intIds as $pid) {
            if (!in_array($pid, $validIds)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Pemain ID ' . $pid . ' tidak valid atau bukan milik Anda.'
                ]);
                exit;
            }
        }
    }

    try {
        $pdo->beginTransaction();

        $lineup = getOrCreateLineup($pdo, $teamId);

        // Update formasi
        $pdo->prepare('UPDATE lineups SET formation = ? WHERE id = ?')
            ->execute([$formation, $lineup['id']]);

        // Hapus lineup lama
        $pdo->prepare('DELETE FROM lineup_players WHERE lineup_id = ?')
            ->execute([$lineup['id']]);

        // Insert starting
        $insertStmt = $pdo->prepare('
            INSERT INTO lineup_players (lineup_id, player_id, slot_type, slot_index, position)
            VALUES (?, ?, "starting", ?, ?)
        ');
        foreach ($starting as $idx => $s) {
            $insertStmt->execute([
                $lineup['id'],
                (int)$s['player_id'],
                $idx,
                $s['position'] ?? null,
            ]);
        }

        // Insert bench
        $benchStmt = $pdo->prepare('
            INSERT INTO lineup_players (lineup_id, player_id, slot_type, slot_index, position)
            VALUES (?, ?, "bench", ?, NULL)
        ');
        foreach ($bench as $idx => $b) {
            $benchStmt->execute([$lineup['id'], (int)$b['player_id'], $idx]);
        }

        // Recalculate power (hanya dari starting 11)
        $startingIds = array_column($starting, 'player_id');
        $newPower    = calculateStartingPower($pdo, $teamId, $startingIds, $lineup['id']);

        $pdo->prepare('UPDATE user_teams SET team_power = ? WHERE id = ?')
            ->execute([$newPower, $teamId]);

        $pdo->commit();

        echo json_encode([
            'success'   => true,
            'message'   => 'Lineup berhasil disimpan!',
            'new_power' => $newPower,
            'formation' => $formation,
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
    }
    exit;
}

// ── REMOVE FROM LINEUP ────────────────────────────────────────
if ($action === 'remove') {
    $playerId = (int)($_POST['player_id'] ?? 0);
    $lineup   = getOrCreateLineup($pdo, $teamId);

    $pdo->prepare('DELETE FROM lineup_players WHERE lineup_id = ? AND player_id = ?')
        ->execute([$lineup['id'], $playerId]);

    echo json_encode(['success' => true, 'message' => 'Pemain dikeluarkan dari lineup.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);

// ── HELPER: HITUNG POWER HANYA DARI STARTING 11 ──────────────
function calculateStartingPower(PDO $pdo, int $teamId, array $startingIds, int $lineupId): int {
    if (empty($startingIds)) return 0;

    $placeholders = implode(',', array_fill(0, count($startingIds), '?'));

    $stmt = $pdo->prepare("
        SELECT fp.id, fp.offence, fp.defence, fp.teamwork,
               s.effect_type, s.effect_value
        FROM football_players fp
        LEFT JOIN player_skills ps ON ps.player_id = fp.id
        LEFT JOIN skills s ON s.id = ps.skill_id
        WHERE fp.id IN ($placeholders)
    ");
    $stmt->execute($startingIds);
    $rows = $stmt->fetchAll();

    $playerStats = [];
    foreach ($rows as $row) {
        $pid = $row['id'];
        if (!isset($playerStats[$pid])) {
            $playerStats[$pid] = [
                'offence'     => (float)$row['offence'],
                'defence'     => (float)$row['defence'],
                'teamwork'    => (float)$row['teamwork'],
                'power_bonus' => 0.0,
            ];
        }
        if (!$row['effect_type']) continue;
        switch ($row['effect_type']) {
            case 'offence':
                $playerStats[$pid]['offence']  *= (1 + $row['effect_value'] / 100); break;
            case 'defence':
                $playerStats[$pid]['defence']  *= (1 + $row['effect_value'] / 100); break;
            case 'teamwork':
                $playerStats[$pid]['teamwork'] *= (1 + $row['effect_value'] / 100); break;
            case 'power':
                $playerStats[$pid]['power_bonus'] += $row['effect_value']; break;
        }
    }

    $basePower = 0.0;
    foreach ($playerStats as $p) {
        $stat = $p['offence'] + $p['defence'] + $p['teamwork'];
        $stat *= (1 + $p['power_bonus'] / 100);
        $basePower += $stat;
    }

    // Bonus upgrade level
    $teamStmt = $pdo->prepare('
        SELECT power_level_euro, power_level_wintoken
        FROM user_teams WHERE id = ?
    ');
    $teamStmt->execute([$teamId]);
    $teamData = $teamStmt->fetch();

    $euroPct  = ($teamData['power_level_euro']     - 1) * 5;
    $wtPct    = ($teamData['power_level_wintoken'] - 1) * 8;
    $totalPct = $euroPct + $wtPct;

    return (int) round($basePower * (1 + $totalPct / 100));
}