<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

$userId = getCurrentUserId();
$pdo = getDB();

// Ambil semua turnamen
$stmt = $pdo->prepare('
    SELECT t.*, u.fullname as creator_name,
           (SELECT COUNT(*) FROM tournament_participants WHERE tournament_id = t.id) as participants_count
    FROM tournaments t
    JOIN users u ON t.creator_id = u.id
    ORDER BY t.created_at DESC
');
$stmt->execute();
$tournaments = $stmt->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="section-title">🏆 Turnamen</div>

<div style="display:flex;gap:0.75rem;margin-bottom:1.25rem;flex-wrap:wrap;">
    <a href="<?= url('/pages/create_tournament.php') ?>" class="btn btn-success">➕ Buat Turnamen Baru</a>
    <a href="<?= url('/pages/inbox.php') ?>" class="btn btn-outline">📬 Inbox</a>
</div>

<div class="tournaments-grid">
    <?php if (empty($tournaments)): ?>
    <div class="empty-state" style="grid-column:1/-1;">
        <div class="empty-state-icon">🏆</div>
        <div class="empty-state-text">Belum ada turnamen. Buat turnamen pertama!</div>
    </div>
    <?php else: ?>
    <?php foreach ($tournaments as $t): 
        $isJoined = false;
        $isCreator = $t['creator_id'] == $userId;
        if ($userId) {
            $check = $pdo->prepare('SELECT id FROM tournament_participants WHERE tournament_id = ? AND user_id = ?');
            $check->execute([$t['id'], $userId]);
            $isJoined = (bool)$check->fetch();
        }
    ?>
    <div class="tournament-card">
        <div class="tournament-status <?= $t['status'] ?>">
            <?php if ($t['status'] == 'open'): ?>🟢 Terbuka
            <?php elseif ($t['status'] == 'active'): ?>🟡 Berlangsung
            <?php else: ?>🔴 Selesai
            <?php endif; ?>
        </div>
        <div class="tournament-name"><?= e($t['name']) ?></div>
        <div class="tournament-meta">
            <span>👤 <?= e($t['creator_name']) ?></span>
            <span>👥 <?= $t['participants_count'] ?>/<?= $t['max_participants'] ?></span>
        </div>
        <div class="tournament-desc"><?= e($t['description'] ?? '') ?></div>
        <div class="tournament-prize">
            🎁 Hadiah: <?= number_format($t['prize_pool']) ?> 💎
            <?php if ($t['entry_fee'] > 0): ?>
            · 💰 Biaya: <?= $t['entry_fee'] ?> 💎
            <?php else: ?>
            · Gratis!
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:0.5rem;margin-top:0.5rem;">
            <a href="<?= url('/pages/tournament_detail.php?id=' . $t['id']) ?>" class="btn btn-primary btn-sm">📊
                Lihat</a>
            <?php if ($t['status'] == 'open' && $userId && !$isJoined && !$isCreator): ?>
            <button class="btn btn-success btn-sm" onclick="joinTournament(<?= $t['id'] ?>)">➕ Join</button>
            <?php endif; ?>
            <?php if ($isCreator && $t['status'] == 'open' && $t['participants_count'] >= 2): ?>
            <button class="btn btn-warning btn-sm" onclick="startTournament(<?= $t['id'] ?>)">🚀 Mulai</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
async function joinTournament(tournamentId) {
    if (!confirm('Join turnamen ini?')) return;
    const data = await postData(apiUrl('/api/tournament_join.php'), {
        tournament_id: tournamentId
    });
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1000);
}

async function startTournament(tournamentId) {
    if (!confirm('Mulai turnamen? Match akan berjalan otomatis!')) return;
    const data = await postData(apiUrl('/api/tournament_start.php'), {
        tournament_id: tournamentId
    });
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 2000);
}
</script>

<style>
.tournaments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}

.tournament-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 1rem;
    transition: all 0.2s ease;
}

.tournament-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.tournament-status {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 0.15rem 0.5rem;
    border-radius: 99px;
    display: inline-block;
    margin-bottom: 0.3rem;
}

.tournament-status.open {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

.tournament-status.active {
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
}

.tournament-status.completed {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.tournament-name {
    font-size: 1.1rem;
    font-weight: 700;
}

.tournament-meta {
    font-size: 0.75rem;
    color: var(--text-secondary);
    display: flex;
    gap: 1rem;
}

.tournament-desc {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin: 0.3rem 0;
}

.tournament-prize {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gold);
    margin: 0.3rem 0;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>