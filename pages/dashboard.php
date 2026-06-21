<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$userId    = getCurrentUserId();
$team      = getUserTeam($userId);
$inventory = getInventory($userId);
$players   = $team ? getTeamPlayers($team['id']) : [];

$baseIncome = $team ? calculateBaseIncome(
    (int)$team['income_level_euro'],
    (int)$team['income_level_wintoken']
) : 100;

$pdo     = getDB();
$logStmt = $pdo->prepare('
    SELECT ml.result, ml.euro_earned, ml.user_power, ml.bot_power, t.team_name
    FROM match_log ml
    JOIN teams t ON t.id = ml.bot_team_id
    WHERE ml.user_id = ?
    ORDER BY ml.played_at DESC
    LIMIT 5
');
$logStmt->execute([$userId]);
$matchLogs = $logStmt->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<link rel="stylesheet" href="<?= url('/assets/css/dashboard.css') ?>">

<div class="dashboard-layout">

    <!-- ── KOLOM KIRI ── -->
    <div>

        <!-- MATCH ARENA -->
        <div class="match-arena">
            <div class="section-title" style="justify-content:center;">⚽ Match Arena</div>

            <!-- Scoreboard nama + power -->
            <div class="match-scoreboard">
                <div class="match-team">
                    <div class="match-team-name" id="user-team-name">
                        <?= e($team['team_name'] ?? 'Your Team') ?>
                    </div>
                    <div class="match-team-power">
                        PWR: <span id="user-power"><?= formatNumber((int)($team['team_power'] ?? 0)) ?></span>
                    </div>
                </div>

                <div class="match-scoreboard-center">
                    <div class="match-score-display">
                        <span class="score-num" id="score-user">0</span>
                        <span class="score-sep">—</span>
                        <span class="score-num" id="score-bot">0</span>
                    </div>
                    <div class="match-score-label">SKOR</div>
                </div>

                <div class="match-team">
                    <div class="match-team-name" id="bot-team-name">—</div>
                    <div class="match-team-power">
                        PWR: <span id="bot-power">—</span>
                    </div>
                </div>
            </div>

            <!-- Goal events — di dalam match-arena, di bawah scoreboard -->
            <div id="goal-events-wrap" style="
                max-height: 120px;
                overflow-y: auto;
                margin: 0.5rem 0;
                padding: 0 0.5rem;
                display: flex;
                flex-direction: column;
                gap: 0.2rem;
            ">
                <div id="goal-events"></div>
            </div>

            <!-- Timer & status -->
            <div class="match-timer-wrap">
                <div class="match-timer" id="match-timer">60</div>
                <div class="match-status" id="match-status">Match sedang berjalan...</div>
            </div>

            <div class="match-result-banner" id="result-banner"></div>

            <div class="match-rewards" id="match-rewards" style="display:none;">
                <span class="reward-chip" id="reward-euro">💶 —</span>
                <span class="reward-chip" id="reward-gems">💎 —</span>
                <span class="reward-chip" id="reward-tr">🔧 — TR</span>
                <span class="reward-chip" id="reward-wt">🏆 — WT</span>
            </div>

            <div style="margin-top:0.75rem;font-size:0.8rem;color:var(--text-muted);">
                Income dasar: <strong id="base-income-display">
                    <?= formatNumber($baseIncome) ?>
                </strong> euro/match
                <span style="font-size:0.7rem;color:var(--text-muted);">
                    (Lv.Euro <?= $team['income_level_euro'] ?> +
                    Lv.WT <?= $team['income_level_wintoken'] ?>)
                </span>
            </div>
        </div><!-- /match-arena -->

        <!-- UPGRADE POWER -->
        <div class="upgrade-section">
            <div class="upgrade-section-title">⚡ Upgrade Team Power</div>
            <div class="upgrade-tabs">
                <div class="upgrade-tab active" data-tab="power-euro">Bayar Euro</div>
                <div class="upgrade-tab" data-tab="power-wt">Bayar Win Token</div>
            </div>
            <div class="upgrade-tab-content active" id="tab-power-euro">
                <div class="upgrade-row">
                    <div class="upgrade-info">
                        <div class="upgrade-label">Power Level (Euro)</div>
                        <div class="upgrade-level">Level <span
                                id="power-euro-level"><?= $team['power_level_euro'] ?? 1 ?></span></div>
                        <div class="upgrade-cost">Harga: <span
                                id="power-euro-cost"><?= formatNumber((int)($team['power_upgrade_cost_euro'] ?? 100)) ?></span>
                            💶</div>
                    </div>
                    <button class="btn btn-primary" id="btn-upgrade-power-euro" data-type="power"
                        data-payment="euro">Upgrade</button>
                </div>
            </div>
            <div class="upgrade-tab-content" id="tab-power-wt">
                <div class="upgrade-row">
                    <div class="upgrade-info">
                        <div class="upgrade-label">Power Level (Win Token)</div>
                        <div class="upgrade-level">Level <span
                                id="power-wt-level"><?= $team['power_level_wintoken'] ?? 1 ?></span></div>
                        <div class="upgrade-cost">Harga: <span
                                id="power-wt-cost"><?= (int)($team['power_upgrade_cost_wintoken'] ?? 1) ?></span> 🏆 WT
                        </div>
                    </div>
                    <button class="btn btn-warning" id="btn-upgrade-power-wt" data-type="power"
                        data-payment="wintoken">Upgrade</button>
                </div>
            </div>
        </div>

        <!-- UPGRADE INCOME -->
        <div class="upgrade-section">
            <div class="upgrade-section-title">💰 Upgrade Team Income</div>
            <div class="upgrade-tabs">
                <div class="upgrade-tab active" data-tab="income-euro">Bayar Euro</div>
                <div class="upgrade-tab" data-tab="income-wt">Bayar Win Token</div>
            </div>
            <div class="upgrade-tab-content active" id="tab-income-euro">
                <div class="upgrade-row">
                    <div class="upgrade-info">
                        <div class="upgrade-label">Income Level (Euro)</div>
                        <div class="upgrade-level">Level <span
                                id="income-euro-level"><?= $team['income_level_euro'] ?? 1 ?></span></div>
                        <div class="upgrade-cost">Harga: <span
                                id="income-euro-cost"><?= formatNumber((int)($team['income_upgrade_cost_euro'] ?? 100)) ?></span>
                            💶</div>
                    </div>
                    <button class="btn btn-primary" id="btn-upgrade-income-euro" data-type="income"
                        data-payment="euro">Upgrade</button>
                </div>
            </div>
            <div class="upgrade-tab-content" id="tab-income-wt">
                <div class="upgrade-row">
                    <div class="upgrade-info">
                        <div class="upgrade-label">Income Level (Win Token)</div>
                        <div class="upgrade-level">Level <span
                                id="income-wt-level"><?= $team['income_level_wintoken'] ?? 1 ?></span></div>
                        <div class="upgrade-cost">Harga: <span
                                id="income-wt-cost"><?= (int)($team['income_upgrade_cost_wintoken'] ?? 1) ?></span> 🏆
                            WT</div>
                    </div>
                    <button class="btn btn-warning" id="btn-upgrade-income-wt" data-type="income"
                        data-payment="wintoken">Upgrade</button>
                </div>
            </div>
        </div>

    </div><!-- /kolom kiri -->

    <!-- ── SIDEBAR ── -->
    <div>

        <div class="sidebar-card">
            <div class="sidebar-card-title">📊 Statistik Tim</div>
            <div class="team-stat-grid">
                <div class="team-stat-box">
                    <div class="team-stat-box-label">Total Power</div>
                    <div class="team-stat-box-value" id="sidebar-power">
                        <?= formatNumber((int)($team['team_power'] ?? 0)) ?>
                    </div>
                </div>
                <div class="team-stat-box">
                    <div class="team-stat-box-label">Pemain</div>
                    <div class="team-stat-box-value"><?= count($players) ?></div>
                </div>
                <div class="team-stat-box">
                    <div class="team-stat-box-label">Euro</div>
                    <div class="team-stat-box-value" id="sidebar-euro">
                        <?= formatNumber((int)($inventory['euro'] ?? 0)) ?>
                    </div>
                </div>
                <div class="team-stat-box">
                    <div class="team-stat-box-label">Gems</div>
                    <div class="team-stat-box-value" id="sidebar-gems">
                        <?= $inventory['gems'] ?? 0 ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="sidebar-card">
            <div class="sidebar-card-title">📋 Riwayat Match</div>
            <div id="match-log-list">
                <?php if (empty($matchLogs)): ?>
                <div class="empty-state" style="padding:1rem;">
                    <div class="empty-state-text">Belum ada match.</div>
                </div>
                <?php else: ?>
                <?php foreach ($matchLogs as $log): ?>
                <div class="match-log-item">
                    <span class="match-log-result <?= $log['result'] ?>"><?= strtoupper($log['result']) ?></span>
                    <span class="match-log-vs">vs <?= e($log['team_name']) ?></span>
                    <span class="match-log-earn">+<?= formatNumber((int)$log['euro_earned']) ?>💶</span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="sidebar-card">
            <div class="sidebar-card-title">⭐ Pemain Terbaik</div>
            <?php if (empty($players)): ?>
            <div class="empty-state" style="padding:1rem;">
                <div class="empty-state-text">Belum ada pemain.</div>
            </div>
            <?php else: ?>
            <?php foreach (array_slice($players, 0, 5) as $p): ?>
            <div class="card-list-item">
                <div class="card-list-tier-dot" style="background:<?= e($p['card_color']) ?>;"></div>
                <div class="card-list-name"><?= e($p['player_name']) ?></div>
                <div class="card-list-stats"><span>⚽<?= $p['rating'] ?></span></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- /sidebar -->

</div><!-- /dashboard-layout -->

<div id="toast-container"></div>
<script src="<?= url('/assets/js/match.js') ?>"></script>
<script src="<?= url('/assets/js/upgrade.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>