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

if ($action === 'update_team_name') {
    $teamName = trim($_POST['team_name'] ?? '');
    $pdo = getDB();

    if (strlen($teamName) < 3 || strlen($teamName) > 50) {
        echo json_encode(['success' => false, 'message' => 'Nama tim harus 3-50 karakter.']);
        exit;
    }

    try {
        $pdo->prepare('UPDATE user_teams SET team_name = ? WHERE user_id = ?')
            ->execute([$teamName, $userId]);

        echo json_encode([
            'success' => true,
            'message' => 'Nama tim berhasil diupdate!',
            'team_name' => $teamName
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal update: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);