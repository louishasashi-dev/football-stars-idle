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
$pdo    = getDB();

$team = getUserTeam($userId);
if (!$team) {
    echo json_encode(['success' => false, 'message' => 'Tim tidak ditemukan.']);
    exit;
}

$botTeamId = $_SESSION['current_bot_team_id'] ?? null;
$botTeam   = null;

if ($botTeamId) {
    $stmt = $pdo->prepare('SELECT * FROM teams WHERE id = ?');
    $stmt->execute([$botTeamId]);
    $botTeam = $stmt->fetch();
}

if (!$botTeam) {
    $botTeam = $pdo->query('SELECT * FROM teams ORDER BY RAND() LIMIT 1')->fetch();
}

if (!$botTeam) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada tim lawan.']);
    exit;
}

$userPower = calculateTeamPower($team['id']);
$pdo->prepare('UPDATE user_teams SET team_power = ? WHERE id = ?')
    ->execute([$userPower, $team['id']]);

// Gunakan hasil dari session (konsisten dengan yang ditampilkan di JS)
$result    = $_SESSION['predicted_result']     ?? 'lose';
$scoreUser = (int)($_SESSION['predicted_score_user'] ?? $_POST['score_user'] ?? 0);
$scoreBot  = (int)($_SESSION['predicted_score_bot']  ?? $_POST['score_bot']  ?? 0);
$botPower  = $botTeam['team_power'];

// Hitung reward
$baseIncome = calculateBaseIncome(
    (int)$team['income_level_euro'],
    (int)$team['income_level_wintoken']
);

if ($result === 'win') {
    $euroEarned     = $baseIncome * 10;
    $gemsEarned     = 1;
    $trTokenEarned  = 10;
    $winTokenEarned = 1;
} else {
    $euroEarned     = $baseIncome * 2;
    $gemsEarned     = 0;
    $trTokenEarned  = 5;
    $winTokenEarned = 0;
}

try {
    $pdo->beginTransaction();

    $pdo->prepare('
        UPDATE inventory_players
        SET euro      = euro + ?,
            gems      = gems + ?,
            tr_token  = tr_token + ?,
            win_token = win_token + ?
        WHERE user_id = ?
    ')->execute([
        $euroEarned, $gemsEarned,
        $trTokenEarned, $winTokenEarned,
        $userId
    ]);

    $pdo->prepare('
        INSERT INTO match_log
        (user_id, bot_team_id, user_power, bot_power, result,
         euro_earned, gems_earned, tr_token_earned, win_token_earned)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ')->execute([
        $userId, $botTeam['id'], $userPower, $botPower, $result,
        $euroEarned, $gemsEarned, $trTokenEarned, $winTokenEarned
    ]);

    $pdo->commit();

    // Bersihkan session
    unset(
        $_SESSION['current_bot_team_id'],
        $_SESSION['predicted_result'],
        $_SESSION['predicted_score_user'],
        $_SESSION['predicted_score_bot']
    );

    $newInventory = getInventory($userId);

    echo json_encode([
        'success'          => true,
        'result'           => $result,
        'score_user'       => $scoreUser,
        'score_bot'        => $scoreBot,
        'user_power'       => $userPower,
        'bot_power'        => $botPower,
        'bot_team_name'    => $botTeam['team_name'],
        'euro_earned'      => $euroEarned,
        'gems_earned'      => $gemsEarned,
        'tr_token_earned'  => $trTokenEarned,
        'win_token_earned' => $winTokenEarned,
        'base_income'      => $baseIncome,
        'inventory'        => $newInventory,
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Gagal: ' . $e->getMessage()
    ]);
}