<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$inventory   = getInventory(getCurrentUserId());
$userTeam    = getUserTeam(getCurrentUserId());
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Football Stars Idle</title>
    <link rel="stylesheet" href="<?= url('/assets/css/main.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/card.css') ?>">
    <script src="<?= url('/assets/js/main.js') ?>"></script>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <span class="brand-icon">⚽</span>
            <span class="brand-name">Football Stars Idle</span>
        </div>
        <div class="navbar-menu">
            <a href="<?= url('/pages/dashboard.php') ?>"
                class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <a href="<?= url('/pages/tournaments.php') ?>"
                class="<?= $currentPage === 'tournaments' ? 'active' : '' ?>">🏆 Turnamen</a>
            <a href="<?= url('/pages/inbox.php') ?>" class="<?= $currentPage === 'inbox' ? 'active' : '' ?>">📬
                Inbox</a>
            <a href="<?= url('/pages/shop.php') ?>" class="<?= $currentPage === 'shop' ? 'active' : '' ?>">Shop</a>
            <a href="<?= url('/pages/market.php') ?>"
                class="<?= $currentPage === 'market' ? 'active' : '' ?>">Market</a>
            <a href="<?= url('/pages/lineup.php') ?>"
                class="<?= $currentPage === 'lineup' ? 'active' : '' ?>">Lineup</a>
            <a href="<?= url('/pages/card_collection.php') ?>"
                class="<?= $currentPage === 'card_collection' ? 'active' : '' ?>">Koleksi</a>
            <a href="<?= url('/pages/add_player.php') ?>"
                class="<?= $currentPage === 'add_player' ? 'active' : '' ?>">Tambah Pemain</a>
            <a href="<?= url('/pages/available_players.php') ?>"
                class="<?= $currentPage === 'available_players' ? 'active' : '' ?>">Pool Pemain</a>
            <a href="<?= url('/pages/profile.php') ?>"
                class="<?= $currentPage === 'profile' ? 'active' : '' ?>">Profil</a>
            <a href="<?= url('/auth/logout.php') ?>" class="btn-logout">Logout</a>
        </div>
        <div class="navbar-currency">
            <span class="currency-item">💶 <span
                    id="nav-euro"><?= formatNumber((int)($inventory['euro'] ?? 0)) ?></span></span>
            <span class="currency-item">💎 <span id="nav-gems"><?= $inventory['gems'] ?? 0 ?></span></span>
            <span class="currency-item">🔧 <span id="nav-tr"><?= $inventory['tr_token'] ?? 0 ?></span> TR</span>
            <span class="currency-item">🏆 <span id="nav-wt"><?= $inventory['win_token'] ?? 0 ?></span> WT</span>
            <span class="currency-item">⬆️ <span id="nav-pr"><?= $inventory['pr_token'] ?? 0 ?></span> PR</span>
        </div>
    </nav>
    <main class="main-content">