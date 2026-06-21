<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$userId = getCurrentUserId();
$pdo = getDB();
$inventory = getInventory($userId);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="section-title">➕ Buat Turnamen Baru</div>

<div class="card" style="max-width:600px;margin:0 auto;">
    <div class="card-header">
        <div class="card-title">📝 Detail Turnamen</div>
    </div>
    <div id="create-msg"></div>

    <div class="form-group">
        <label class="form-label">Nama Turnamen</label>
        <input type="text" id="tournament-name" class="form-control" placeholder="Contoh: Liga Champions Season 1">
    </div>

    <div class="form-group">
        <label class="form-label">Deskripsi (opsional)</label>
        <textarea id="tournament-desc" class="form-control" rows="3" placeholder="Deskripsi turnamen..."></textarea>
    </div>

    <div class="form-group">
        <label class="form-label">Max Peserta</label>
        <select id="tournament-max" class="form-control">
            <option value="4">4 Peserta</option>
            <option value="8" selected>8 Peserta</option>
            <option value="16">16 Peserta</option>
            <option value="32">32 Peserta</option>
        </select>
    </div>

    <div class="form-group">
        <label class="form-label">Biaya Masuk (Gems)</label>
        <input type="number" id="tournament-fee" class="form-control" value="0" min="0">
        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.3rem;">
            Gems kamu: <strong><?= $inventory['gems'] ?></strong>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Hadiah Juara 1 (Gems)</label>
        <input type="number" id="tournament-prize" class="form-control" value="100" min="0">
    </div>

    <button class="btn btn-success btn-full btn-lg" id="btn-create">🚀 Buat Turnamen</button>
</div>

<script>
document.getElementById('btn-create').addEventListener('click', async function() {
    const name = document.getElementById('tournament-name').value.trim();
    const desc = document.getElementById('tournament-desc').value.trim();
    const max = document.getElementById('tournament-max').value;
    const fee = parseInt(document.getElementById('tournament-fee').value) || 0;
    const prize = parseInt(document.getElementById('tournament-prize').value) || 0;
    const msgEl = document.getElementById('create-msg');

    if (!name || name.length < 3) {
        msgEl.innerHTML = '<div class="alert alert-danger">Nama turnamen minimal 3 karakter.</div>';
        return;
    }

    this.disabled = true;
    this.textContent = 'Membuat...';

    const data = await postData(apiUrl('/api/tournament_create.php'), {
        name,
        description: desc,
        max_participants: max,
        entry_fee: fee,
        prize_pool: prize
    });

    showToast(data.message, data.success ? 'success' : 'error');
    msgEl.innerHTML = data.success ?
        `<div class="alert alert-success">${data.message}</div>` :
        `<div class="alert alert-danger">${data.message}</div>`;

    if (data.success) {
        setTimeout(() => window.location.href = '<?= url('/pages/tournaments.php') ?>', 1500);
    }

    this.disabled = false;
    this.textContent = '🚀 Buat Turnamen';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>