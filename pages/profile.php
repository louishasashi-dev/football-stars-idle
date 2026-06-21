<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$userId    = getCurrentUserId();
$pdo       = getDB();
$inventory = getInventory($userId);
$team      = getUserTeam($userId);

$userStmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

$matchCount = $pdo->prepare('SELECT COUNT(*) as total, SUM(result="win") as wins FROM match_log WHERE user_id = ?');
$matchCount->execute([$userId]);
$stats = $matchCount->fetch();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="section-title">👤 Profil</div>

<div id="profile-message"></div>

<div class="grid-2" style="max-width:800px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">Informasi Akun</div>
        </div>
        <div class="stat-row">
            <span class="stat-label">Username</span>
            <span class="stat-value"><?= e($user['username']) ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Nama Lengkap</span>
            <span class="stat-value"><?= e($user['fullname']) ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Negara</span>
            <span class="stat-value"><?= e($user['country']) ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Bergabung</span>
            <span class="stat-value"><?= date('d M Y', strtotime($user['created_at'])) ?></span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">⚽ Tim</div>
        </div>

        <!-- ── EDIT NAMA TIM ── -->
        <div style="margin-bottom:0.75rem;">
            <label class="form-label" style="font-size:0.8rem;">Nama Tim</label>
            <div style="display:flex;gap:0.5rem;">
                <input type="text" id="team-name-input" class="form-control"
                    value="<?= e($team['team_name'] ?? 'Your Team') ?>" placeholder="Nama tim Anda">
                <button class="btn btn-primary" id="btn-update-team">Update</button>
            </div>
        </div>

        <div class="stat-row">
            <span class="stat-label">Total Power</span>
            <span class="stat-value"><?= formatNumber((int)($team['team_power'] ?? 0)) ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Income Level</span>
            <span class="stat-value">Euro Lv.<?= $team['income_level_euro'] ?> / WT
                Lv.<?= $team['income_level_wintoken'] ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Power Level</span>
            <span class="stat-value">Euro Lv.<?= $team['power_level_euro'] ?> / WT
                Lv.<?= $team['power_level_wintoken'] ?></span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Statistik Game</div>
        </div>
        <div class="stat-row">
            <span class="stat-label">Total Match</span>
            <span class="stat-value"><?= (int)$stats['total'] ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Total Menang</span>
            <span class="stat-value" style="color:var(--success)"><?= (int)$stats['wins'] ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Total Kalah</span>
            <span class="stat-value"
                style="color:var(--danger)"><?= (int)$stats['total'] - (int)$stats['wins'] ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Win Rate</span>
            <span class="stat-value">
                <?= $stats['total'] > 0 ? round(($stats['wins'] / $stats['total']) * 100, 1) : 0 ?>%
            </span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">💰 Inventory</div>
        </div>
        <div class="stat-row">
            <span class="stat-label">💶 Euro</span>
            <span class="stat-value"><?= formatNumber((int)$inventory['euro']) ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">💎 Gems</span>
            <span class="stat-value"><?= $inventory['gems'] ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">🔧 TR Token</span>
            <span class="stat-value"><?= $inventory['tr_token'] ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">🏆 Win Token</span>
            <span class="stat-value"><?= $inventory['win_token'] ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">⬆️ PR Token</span>
            <span class="stat-value"><?= $inventory['pr_token'] ?></span>
        </div>
    </div>
</div>

<script>
// ── UPDATE NAMA TIM ──────────────────────────────────────────
document.getElementById('btn-update-team').addEventListener('click', async function() {
    const name = document.getElementById('team-name-input').value.trim();
    const msgEl = document.getElementById('profile-message');

    if (!name || name.length < 3 || name.length > 50) {
        msgEl.innerHTML = '<div class="alert alert-danger">Nama tim harus 3-50 karakter.</div>';
        return;
    }

    this.disabled = true;
    this.textContent = 'Menyimpan...';

    try {
        const fd = new FormData();
        fd.append('action', 'update_team_name');
        fd.append('team_name', name);

        const res = await fetch(apiUrl('/api/update_profile.php'), {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (data.success) {
            msgEl.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
            // Update navbar team name jika ada
            const teamNameEl = document.querySelector('.navbar-brand .brand-name');
            if (teamNameEl) teamNameEl.textContent = name;
        } else {
            msgEl.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        }
    } catch (e) {
        msgEl.innerHTML = '<div class="alert alert-danger">Gagal update. Coba lagi.</div>';
    }

    this.disabled = false;
    this.textContent = 'Update';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>