<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$userId = getCurrentUserId();
$pdo    = getDB();

$team = getUserTeam($userId);
if (!$team) {
    echo json_encode(['success' => false, 'message' => 'Tim tidak ditemukan.']);
    exit;
}

$userPower = calculateTeamPower($team['id']);
$pdo->prepare('UPDATE user_teams SET team_power = ? WHERE id = ?')
    ->execute([$userPower, $team['id']]);

// Pilih bot random
$botTeam = $pdo->query('SELECT * FROM teams ORDER BY RAND() LIMIT 1')->fetch();
if (!$botTeam) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada tim lawan.']);
    exit;
}

$_SESSION['current_bot_team_id'] = $botTeam['id'];

// Prediksi hasil & skor untuk JS (agar gol bisa dijadwalkan sebelum match)
$botPower     = $botTeam['team_power'];
$randomFactor = rand(90, 110) / 100;
$effective    = $userPower * $randomFactor;
$result       = $effective >= $botPower ? 'win' : 'lose';

// Generate skor prediksi
function predictScore(string $result): array {
    if ($result === 'win') {
        $userGoals = rand(1, 5);
        $botGoals  = rand(0, max(0, $userGoals - 1));
    } else {
        $botGoals  = rand(1, 5);
        $userGoals = rand(0, max(0, $botGoals - 1));
    }
    return ['user' => $userGoals, 'bot' => $botGoals];
}

$score = predictScore($result);

// Simpan skor di session agar match.php konsisten
$_SESSION['predicted_score_user']   = $score['user'];
$_SESSION['predicted_score_bot']    = $score['bot'];
$_SESSION['predicted_result']       = $result;

// ── AMBIL PEMAIN YANG ADA DI LINEUP (STARTING 11) ──────
$lineupIdStmt = $pdo->prepare('
    SELECT id FROM lineups WHERE team_id = ?
');
$lineupIdStmt->execute([$team['id']]);
$lineupRow = $lineupIdStmt->fetch();

$userPlayers = [];

if ($lineupRow) {
    // Ambil pemain dari lineup (starting 11)
    $playerStmt = $pdo->prepare('
        SELECT fp.player_name
        FROM lineup_players lp
        JOIN football_players fp ON lp.player_id = fp.id
        WHERE lp.lineup_id = ?
          AND lp.slot_type = "starting"
        ORDER BY lp.slot_index ASC
    ');
    $playerStmt->execute([$lineupRow['id']]);
    $userPlayers = array_column($playerStmt->fetchAll(), 'player_name');
}

// Jika tidak ada lineup atau lineup kosong, fallback ke pemain dengan rating tertinggi
if (empty($userPlayers)) {
    $fallbackStmt = $pdo->prepare('
        SELECT fp.player_name
        FROM team_members tm
        JOIN football_players fp ON fp.id = tm.player_id
        WHERE tm.team_id = ?
        ORDER BY fp.rating DESC
        LIMIT 11
    ');
    $fallbackStmt->execute([$team['id']]);
    $userPlayers = array_column($fallbackStmt->fetchAll(), 'player_name');
}

// Jika masih kosong, fallback terakhir
if (empty($userPlayers)) {
    $userPlayers = ['Pemain'];
}

// ── RESPONSE ──────────────────────────────────────────────
echo json_encode([
    'success'          => true,
    'user_power'       => $userPower,
    'bot_team_id'      => $botTeam['id'],
    'bot_team_name'    => $botTeam['team_name'],
    'bot_power'        => $botPower,
    'predicted_result' => $result,
    'score_user'       => $score['user'],
    'score_bot'        => $score['bot'],
    'user_players'     => $userPlayers,
]);