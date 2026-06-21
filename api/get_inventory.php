<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$inventory = getInventory(getCurrentUserId());
$team      = getUserTeam(getCurrentUserId());

echo json_encode([
    'success'   => true,
    'inventory' => $inventory,
    'team'      => $team,
]);