<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak valid.']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$fullname = trim($_POST['fullname'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';
$country  = trim($_POST['country'] ?? '');
$teamName = trim($_POST['team_name'] ?? ''); // ← TAMBAHKAN INI

if (!$username || !$fullname || !$password || !$confirm || !$country || !$teamName) {
    echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi.']);
    exit;
}

if (strlen($username) < 3 || strlen($username) > 50) {
    echo json_encode(['success' => false, 'message' => 'Username harus 3-50 karakter.']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    echo json_encode(['success' => false, 'message' => 'Username hanya boleh huruf, angka, underscore.']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter.']);
    exit;
}

if ($password !== $confirm) {
    echo json_encode(['success' => false, 'message' => 'Password dan konfirmasi tidak cocok.']);
    exit;
}

if (strlen($teamName) < 3 || strlen($teamName) > 50) {
    echo json_encode(['success' => false, 'message' => 'Nama tim harus 3-50 karakter.']);
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Username sudah digunakan.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $pdo->prepare('INSERT INTO users (username, fullname, password, country) VALUES (?, ?, ?, ?)')
        ->execute([$username, $fullname, $hash, $country]);

    $userId = (int)$pdo->lastInsertId();

    // ── BUAT INVENTORY ──
    $pdo->prepare('INSERT INTO inventory_players (user_id, euro, gems, tr_token, win_token, pr_token) VALUES (?, 500, 0, 0, 0, 0)')
        ->execute([$userId]);

    // ── BUAT PITY TRACKER ──
    $pdo->prepare('INSERT INTO pity_tracker (user_id, spin_count_since_pity) VALUES (?, 0)')
        ->execute([$userId]);

    // ── BUAT TIM DENGAN NAMA CUSTOM ──
    $pdo->prepare('INSERT INTO user_teams (user_id, team_name) VALUES (?, ?)')
        ->execute([$userId, $teamName]);

    $teamId = (int)$pdo->lastInsertId();

    // ── BUAT 20 PEMAIN AMATEUR ──
    $amateurTier = $pdo->query('SELECT id FROM f_player_tier WHERE tier_name = "Amateur"')->fetchColumn();

    $insertPlayer = $pdo->prepare('
        INSERT INTO football_players
        (player_name, country, position, rating, offence, defence, teamwork, tier_id, is_custom, is_exclusive, owner_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?)
    ');

    $insertTeamMember = $pdo->prepare('INSERT INTO team_members (team_id, player_id) VALUES (?, ?)');

    for ($i = 1; $i <= 20; $i++) {
        $insertPlayer->execute([
            'Player ' . $i,
            'Unknown',
            'ST',
            45,
            35,
            35,
            35,
            $amateurTier,
            $userId
        ]);
        $playerId = (int)$pdo->lastInsertId();
        $insertTeamMember->execute([$teamId, $playerId]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Akun berhasil dibuat! Silakan login.']);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
}