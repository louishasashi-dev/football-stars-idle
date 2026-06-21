<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

$tournamentId = (int)($_GET['id'] ?? 0);
$userId = getCurrentUserId();
$pdo = getDB();

// Ambil detail turnamen
$stmt = $pdo->prepare('
    SELECT t.*, u.fullname as creator_name
    FROM tournaments t
    JOIN users u ON t.creator_id = u.id
    WHERE t.id = ?
');
$stmt->execute([$tournamentId]);
$tournament = $stmt->fetch();

if (!$tournament) {
    header('Location: ' . url('/pages/tournaments.php'));
    exit;
}

// Ambil peserta
$participantStmt = $pdo->prepare('
    SELECT tp.*, u.fullname, ut.team_name
    FROM tournament_participants tp
    JOIN users u ON tp.user_id = u.id
    JOIN user_teams ut ON ut.user_id = u.id
    WHERE tp.tournament_id = ?
    ORDER BY tp.position ASC
');
$participantStmt->execute([$tournamentId]);
$participants = $participantStmt->fetchAll();

// Ambil match
$matchStmt = $pdo->prepare('
    SELECT * FROM tournament_matches 
    WHERE tournament_id = ?
    ORDER BY round DESC, match_order ASC
');
$matchStmt->execute([$tournamentId]);
$matches = $matchStmt->fetchAll();

$isParticipant = false;
foreach ($participants as $p) {
    if ($p['user_id'] == $userId) { $isParticipant = true; break; }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="section-title">📊 <?= e($tournament['name']) ?></div>

<div class="tournament-detail-layout">
    <!-- Info -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">ℹ️ Info Turnamen</div>
        </div>
        <div class="stat-row"><span class="stat-label">Status</span>
            <span class="stat-value">
                <?php if ($tournament['status'] == 'open'): ?>🟢 Terbuka
                <?php elseif ($tournament['status'] == 'active'): ?>🟡 Berlangsung
                <?php else: ?>🔴 Selesai
                <?php endif; ?>
            </span>
        </div>
        <div class="stat-row"><span class="stat-label">Pembuat</span><span
                class="stat-value"><?= e($tournament['creator_name']) ?></span></div>
        <div class="stat-row"><span class="stat-label">Peserta</span><span
                class="stat-value"><?= count($participants) ?>/<?= $tournament['max_participants'] ?></span></div>
        <div class="stat-row"><span class="stat-label">Biaya Masuk</span><span
                class="stat-value"><?= $tournament['entry_fee'] ?> 💎</span></div>
        <div class="stat-row"><span class="stat-label">Hadiah Pool</span><span
                class="stat-value"><?= number_format($tournament['prize_pool']) ?> 💎</span></div>
    </div>

    <!-- Klasemen -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">📊 Klasemen</div>
        </div>
        <?php if (empty($participants)): ?>
        <div class="empty-state">
            <div class="empty-state-text">Belum ada peserta.</div>
        </div>
        <?php else: ?>
        <table style="width:100%;font-size:0.8rem;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Tim</th>
                    <th>Posisi</th>
                    <th>Hadiah</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $pos = 1;
                foreach ($participants as $p): 
                    $isUser = $p['user_id'] == $userId;
                ?>
                <tr style="<?= $isUser ? 'background:rgba(255,215,0,0.1);' : '' ?>">
                    <td><?= $pos ?></td>
                    <td><?= e($p['team_name'] ?? $p['fullname']) ?> <?= $isUser ? '⭐' : '' ?></td>
                    <td><?= $p['position'] ?: '-' ?></td>
                    <td><?= $p['prize_claimed'] ? '✅ Diklaim' : ($p['position'] ? '🎁' : '-') ?></td>
                </tr>
                <?php $pos++; endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Bracket -->
<div class="card" style="margin-top:1rem;">
    <div class="card-header">
        <div class="card-title">🏆 Bracket</div>
    </div>
    <?php if (empty($matches)): ?>
    <div class="empty-state">
        <div class="empty-state-text">Belum ada match.</div>
    </div>
    <?php else: ?>
    <div class="bracket-container">
        <?php 
        $rounds = [];
        foreach ($matches as $m) {
            $rounds[$m['round']][] = $m;
        }
        krsort($rounds);
        foreach ($rounds as $round => $roundMatches): 
        ?>
        <div class="bracket-round">
            <div class="bracket-round-title">
                <?= $round == 0 ? '🥉 Perebutan Juara 3' : 'Round ' . $round ?>
            </div>
            <?php foreach ($roundMatches as $m): 
                $user1 = $m['user1_id'] ? getUserName($pdo, $m['user1_id']) : 'Bye';
                $user2 = $m['user2_id'] ? getUserName($pdo, $m['user2_id']) : 'Bye';
                $score1 = $m['user1_score'] ?? '-';
                $score2 = $m['user2_score'] ?? '-';
                $winner = $m['winner_id'] ? getUserName($pdo, $m['winner_id']) : '-';
            ?>
            <div class="bracket-match">
                <div class="bracket-match-user <?= $m['winner_id'] == $m['user1_id'] ? 'winner' : '' ?>">
                    <?= e($user1) ?> <?= $m['status'] == 'completed' ? $score1 : '' ?>
                </div>
                <div class="bracket-match-vs">vs</div>
                <div class="bracket-match-user <?= $m['winner_id'] == $m['user2_id'] ? 'winner' : '' ?>">
                    <?= e($user2) ?> <?= $m['status'] == 'completed' ? $score2 : '' ?>
                </div>
                <div class="bracket-match-status">
                    <?= $m['status'] == 'completed' ? "✅ Winner: " . e($winner) : "⏳ Pending" ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.tournament-detail-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.bracket-container {
    display: flex;
    gap: 1.5rem;
    overflow-x: auto;
    padding: 0.5rem 0;
}

.bracket-round {
    min-width: 180px;
    flex-shrink: 0;
}

.bracket-round-title {
    font-weight: 700;
    text-align: center;
    margin-bottom: 0.5rem;
    color: var(--text-secondary);
}

.bracket-match {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 0.4rem 0.6rem;
    margin-bottom: 0.4rem;
    font-size: 0.75rem;
}

.bracket-match-user {
    font-weight: 600;
}

.bracket-match-user.winner {
    color: var(--gold);
}

.bracket-match-vs {
    font-size: 0.6rem;
    color: var(--text-muted);
    text-align: center;
}

.bracket-match-status {
    font-size: 0.6rem;
    color: var(--text-muted);
    margin-top: 0.2rem;
    text-align: center;
}

@media (max-width: 768px) {
    .tournament-detail-layout {
        grid-template-columns: 1fr;
    }
}
</style>

<?php 
function getUserName($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT fullname FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $r = $stmt->fetch();
    return $r ? $r['fullname'] : 'Unknown';
}
include __DIR__ . '/../includes/footer.php'; 
?>