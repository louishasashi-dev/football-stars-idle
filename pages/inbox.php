<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$userId = getCurrentUserId();
$pdo = getDB();

// Ambil inbox
$stmt = $pdo->prepare('
    SELECT * FROM inbox 
    WHERE user_id = ? 
    ORDER BY created_at DESC
');
$stmt->execute([$userId]);
$inbox = $stmt->fetchAll();

// Tandai sudah dibaca
$pdo->prepare('UPDATE inbox SET is_read = 1 WHERE user_id = ? AND is_read = 0')
    ->execute([$userId]);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="section-title">📬 Inbox</div>

<div style="max-width:700px;margin:0 auto;">
    <?php if (empty($inbox)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">📭</div>
        <div class="empty-state-text">Inbox kosong.</div>
    </div>
    <?php else: ?>
    <?php foreach ($inbox as $msg): 
        $data = json_decode($msg['data'], true);
        $isTournament = $msg['type'] == 'tournament';
        $hasReward = $isTournament && isset($data['tournament_id']) && $msg['type'] == 'tournament';
    ?>
    <div class="inbox-item <?= $msg['is_read'] ? 'read' : 'unread' ?>">
        <div class="inbox-icon"><?= $msg['type'] == 'tournament' ? '🏆' : ($msg['type'] == 'match' ? '⚽' : '📢') ?>
        </div>
        <div class="inbox-content">
            <div class="inbox-title"><?= e($msg['title']) ?></div>
            <div class="inbox-message"><?= e($msg['message']) ?></div>
            <div class="inbox-time"><?= date('d M Y H:i', strtotime($msg['created_at'])) ?></div>
        </div>
        <?php if ($isTournament && isset($data['tournament_id']) && $msg['type'] == 'tournament'): ?>
        <div style="display:flex;flex-direction:column;gap:0.3rem;">
            <a href="/pages/tournament_detail.php?id=<?= $data['tournament_id'] ?>" class="btn btn-primary btn-sm">📊
                Lihat</a>
            <button class="btn btn-success btn-sm" onclick="claimReward(<?= $data['tournament_id'] ?>, this)">🎁
                Klaim</button>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
async function claimReward(tournamentId, btn) {
    if (!confirm('Klaim hadiah turnamen?')) return;
    btn.disabled = true;
    btn.textContent = '⏳';

    const data = await postData(apiUrl('/api/tournament_claim.php'), {
        tournament_id: tournamentId
    });
    showToast(data.message, data.success ? 'success' : 'error');

    if (data.success) {
        updateNavCurrency(data.inventory);
        btn.textContent = '✅ Diklaim';
        btn.disabled = true;
        setTimeout(() => location.reload(), 1500);
    } else {
        btn.disabled = false;
        btn.textContent = '🎁 Klaim';
    }
}
</script>

<style>
.inbox-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    margin-bottom: 0.5rem;
    transition: all 0.2s ease;
}

.inbox-item.unread {
    border-left: 3px solid var(--accent);
}

.inbox-item .inbox-icon {
    font-size: 1.5rem;
}

.inbox-item .inbox-content {
    flex: 1;
}

.inbox-item .inbox-title {
    font-weight: 700;
    font-size: 0.9rem;
}

.inbox-item .inbox-message {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.inbox-item .inbox-time {
    font-size: 0.65rem;
    color: var(--text-muted);
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>