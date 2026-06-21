<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak valid.']);
    exit;
}

$userId      = getCurrentUserId();
$playerName  = trim($_POST['player_name'] ?? '');
$country     = trim($_POST['country'] ?? '');
$position    = $_POST['position'] ?? '';
$skill1Id    = (int) ($_POST['skill1'] ?? 0);
$skill2Id    = (int) ($_POST['skill2'] ?? 0);
$tierName    = $_POST['tier'] ?? 'Amateur'; // ← Ambil tier dari form
$pdo         = getDB();

$validPositions = ['GK','CB','LB','RB','CDM','CM','CAM','LW','RW','ST'];
$validTiers = ['Amateur', 'Trained', 'Talented', 'Semi-Pro', 'Pro', 'Expert', 'Legendary'];

if (!$playerName || !$country || !in_array($position, $validPositions)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap atau tidak valid.']);
    exit;
}

if (!in_array($tierName, $validTiers)) {
    echo json_encode(['success' => false, 'message' => 'Kasta tidak valid.']);
    exit;
}

if ($skill1Id && $skill1Id === $skill2Id) {
    echo json_encode(['success' => false, 'message' => 'Skill 1 dan Skill 2 tidak boleh sama.']);
    exit;
}

// ── KONFIGURASI BIAYA PER TIER ──────────────────────────────
$tierCostMap = [
    'Amateur'   => 0,      // Gratis
    'Trained'   => 10,
    'Talented'  => 25,
    'Semi-Pro'  => 50,
    'Pro'       => 100,
    'Expert'    => 200,
    'Legendary' => 700,
];

// ── STATS PER TIER ───────────────────────────────────────────
$tierStatsMap = [
    'Amateur'   => ['rating' => 45, 'offence' => 35, 'defence' => 35, 'teamwork' => 35],
    'Trained'   => ['rating' => 55, 'offence' => 45, 'defence' => 45, 'teamwork' => 45],
    'Talented'  => ['rating' => 63, 'offence' => 54, 'defence' => 54, 'teamwork' => 54],
    'Semi-Pro'  => ['rating' => 70, 'offence' => 61, 'defence' => 61, 'teamwork' => 61],
    'Pro'       => ['rating' => 75, 'offence' => 69, 'defence' => 69, 'teamwork' => 69],
    'Expert'    => ['rating' => 80, 'offence' => 78, 'defence' => 78, 'teamwork' => 78],
    'Legendary' => ['rating' => 100, 'offence' => 97, 'defence' => 90, 'teamwork' => 97],
];

$cost = $tierCostMap[$tierName] ?? 0;
$stats = $tierStatsMap[$tierName] ?? $tierStatsMap['Amateur'];

// ── CEK GEMS ──────────────────────────────────────────────────
$inventory = getInventory($userId);

if ($cost > 0 && (int)$inventory['gems'] < $cost) {
    echo json_encode([
        'success' => false,
        'message' => "Gems tidak cukup. Butuh $cost gems untuk kasta $tierName."
    ]);
    exit;
}

// Handle upload gambar
$cardImagePath = null;
if (!empty($_FILES['card_image']['tmp_name'])) {
    $file     = $_FILES['card_image'];
    $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Format gambar tidak valid. Gunakan JPG, PNG, GIF, atau WebP.']);
        exit;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Ukuran gambar maksimal 2MB.']);
        exit;
    }

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'card_' . $userId . '_' . time() . '.' . $ext;
    $uploadDir = __DIR__ . '/../assets/images/uploads/';

    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        echo json_encode(['success' => false, 'message' => 'Gagal upload gambar.']);
        exit;
    }

    $cardImagePath = BASE_URL . '/assets/images/uploads/' . $filename;
}

// Ambil tier_id
$tierStmt = $pdo->prepare('SELECT id FROM f_player_tier WHERE tier_name = ?');
$tierStmt->execute([$tierName]);
$tier = $tierStmt->fetch();

if (!$tier) {
    echo json_encode(['success' => false, 'message' => 'Tier tidak ditemukan.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Kurangi gems
    if ($cost > 0) {
        $pdo->prepare('UPDATE inventory_players SET gems = gems - ? WHERE user_id = ?')
            ->execute([$cost, $userId]);
    }

    // Insert player
    $pdo->prepare('
        INSERT INTO football_players
        (player_name, country, position, rating, offence, defence, teamwork,
         tier_id, card_image, is_custom, is_exclusive, owner_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?)
    ')->execute([
        $playerName, $country, $position,
        $stats['rating'], $stats['offence'], $stats['defence'], $stats['teamwork'],
        $tier['id'], $cardImagePath, $userId
    ]);

    $newPlayerId = (int) $pdo->lastInsertId();

    // Assign skills
    if ($skill1Id) {
        $pdo->prepare('INSERT IGNORE INTO player_skills (player_id, skill_id) VALUES (?, ?)')
            ->execute([$newPlayerId, $skill1Id]);
    }
    if ($skill2Id) {
        $pdo->prepare('INSERT IGNORE INTO player_skills (player_id, skill_id) VALUES (?, ?)')
            ->execute([$newPlayerId, $skill2Id]);
    }

    // Tambah ke tim user
    $team = getUserTeam($userId);
    $pdo->prepare('INSERT IGNORE INTO team_members (team_id, player_id) VALUES (?, ?)')
        ->execute([$team['id'], $newPlayerId]);

    $pdo->commit();

    echo json_encode([
        'success'   => true,
        'message'   => "✅ Player $playerName ($tierName) berhasil ditambahkan!",
        'player_id' => $newPlayerId,
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Gagal menambahkan player: ' . $e->getMessage()]);
}