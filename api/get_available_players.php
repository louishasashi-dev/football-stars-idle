<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$userId = getCurrentUserId();
$pdo = getDB();

// Ambil tier filter dari GET
$tierFilter = $_GET['tier'] ?? 'all';
$search = $_GET['search'] ?? '';

// Query pemain yang owner_id-nya NULL (belum dimiliki siapapun)
$sql = "
    SELECT 
        fp.id,
        fp.player_name,
        fp.country,
        fp.position,
        fp.rating,
        fp.offence,
        fp.defence,
        fp.teamwork,
        fp.card_image,
        fp.current_level,
        fpt.tier_name,
        fpt.card_color,
        fpt.tier_order
    FROM football_players fp
    JOIN f_player_tier fpt ON fp.tier_id = fpt.id
    WHERE fp.owner_id IS NULL
      AND fp.is_custom = 0
      AND fp.id NOT IN (SELECT player_id FROM market_listings)
";

// Filter tier
if ($tierFilter !== 'all') {
    $sql .= " AND fpt.tier_name = :tier";
}

// Filter search
if (!empty($search)) {
    $sql .= " AND fp.player_name LIKE :search";
}

$sql .= " ORDER BY fpt.tier_order DESC, fp.rating DESC";

$stmt = $pdo->prepare($sql);

if ($tierFilter !== 'all') {
    $stmt->bindParam(':tier', $tierFilter);
}
if (!empty($search)) {
    $stmt->bindValue(':search', '%' . $search . '%');
}

$stmt->execute();
$players = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'total' => count($players),
    'players' => $players
]);