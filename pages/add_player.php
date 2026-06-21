<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$userId    = getCurrentUserId();
$pdo       = getDB();
$inventory = getInventory($userId);

$positions = ['GK','CB','LB','RB','CDM','CM','CAM','LW','RW','ST'];

// ── TIERS ──────────────────────────────────────────────────────
$tiers = [
    ['name' => 'Amateur', 'cost' => 0, 'rating' => 45, 'off' => 35, 'def' => 35, 'twk' => 35],
    ['name' => 'Trained', 'cost' => 10, 'rating' => 55, 'off' => 45, 'def' => 45, 'twk' => 45],
    ['name' => 'Talented', 'cost' => 25, 'rating' => 63, 'off' => 54, 'def' => 54, 'twk' => 54],
    ['name' => 'Semi-Pro', 'cost' => 50, 'rating' => 70, 'off' => 61, 'def' => 61, 'twk' => 61],
    ['name' => 'Pro', 'cost' => 100, 'rating' => 75, 'off' => 69, 'def' => 69, 'twk' => 69],
    ['name' => 'Expert', 'cost' => 200, 'rating' => 80, 'off' => 78, 'def' => 78, 'twk' => 78],
    ['name' => 'Legendary', 'cost' => 700, 'rating' => 100, 'off' => 97, 'def' => 90, 'twk' => 97],
];

$skillsStmt = $pdo->query('SELECT * FROM skills ORDER BY skill_name');
$skills     = $skillsStmt->fetchAll();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="section-title">➕ Tambah Pemain Baru</div>

<div class="grid-2" style="max-width:900px;align-items:start;">

    <!-- FORM -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Data Pemain</div>
        </div>

        <div id="add-player-msg"></div>

        <!-- ── PILIH KASTA ── -->
        <div class="form-group">
            <label class="form-label">Kasta Pemain</label>
            <select id="player-tier" class="form-control">
                <?php foreach ($tiers as $t): ?>
                <option value="<?= $t['name'] ?>" data-cost="<?= $t['cost'] ?>" data-rating="<?= $t['rating'] ?>"
                    data-off="<?= $t['off'] ?>" data-def="<?= $t['def'] ?>" data-twk="<?= $t['twk'] ?>">
                    <?= $t['name'] ?>
                    <?php if ($t['cost'] > 0): ?>
                    — <?= $t['cost'] ?> 💎
                    <?php else: ?>
                    (Gratis)
                    <?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
            <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.3rem;">
                Gems kamu: <strong id="gems-display"><?= $inventory['gems'] ?></strong> 💎
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Nama Pemain</label>
            <input type="text" id="player-name" class="form-control" placeholder="Nama pemain">
        </div>

        <div class="form-group">
            <label class="form-label">Negara</label>
            <input type="text" id="player-country" class="form-control" placeholder="Contoh: Indonesia">
        </div>

        <div class="form-group">
            <label class="form-label">Posisi</label>
            <select id="player-position" class="form-control">
                <option value="">— Pilih posisi —</option>
                <?php foreach ($positions as $pos): ?>
                <option value="<?= $pos ?>"><?= $pos ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Skill 1</label>
            <select id="skill1" class="form-control">
                <option value="">— Pilih skill (opsional) —</option>
                <?php foreach ($skills as $s): ?>
                <option value="<?= $s['id'] ?>">
                    <?= e($s['skill_name']) ?> (+<?= $s['effect_value'] ?>% <?= $s['effect_type'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Skill 2</label>
            <select id="skill2" class="form-control">
                <option value="">— Pilih skill (opsional) —</option>
                <?php foreach ($skills as $s): ?>
                <option value="<?= $s['id'] ?>">
                    <?= e($s['skill_name']) ?> (+<?= $s['effect_value'] ?>% <?= $s['effect_type'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">
                Gambar Card (opsional, 200×200px, maks 2MB)
            </label>
            <input type="file" id="card-image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
            <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.3rem;">
                Format: JPG, PNG, GIF, WebP. Biarkan kosong untuk gunakan card default.
            </div>
        </div>

        <button class="btn btn-success btn-full btn-lg" id="btn-add-player">
            ➕ Tambahkan Pemain
        </button>
    </div>

    <!-- PREVIEW CARD -->
    <div>
        <div style="font-size:0.875rem;color:var(--text-secondary);
                margin-bottom:0.75rem;font-weight:600;">
            Preview Card
        </div>
        <div id="card-preview-wrap" style="display:flex;justify-content:center;">
            <div class="player-card card-amateur" id="preview-card">
                <div class="card-rating" id="preview-rating">45</div>
                <div class="card-position" id="preview-position">ST</div>
                <div class="card-image-placeholder" id="preview-img-wrap">👤</div>
                <div class="card-name" id="preview-name">Nama Pemain</div>
                <div class="card-country" id="preview-country">Negara</div>
                <div class="card-stats">
                    <div class="card-stat">
                        <div class="card-stat-val" id="preview-off">35</div>
                        <div class="card-stat-lbl">OFF</div>
                    </div>
                    <div class="card-stat">
                        <div class="card-stat-val" id="preview-def">35</div>
                        <div class="card-stat-lbl">DEF</div>
                    </div>
                    <div class="card-stat">
                        <div class="card-stat-val" id="preview-twk">35</div>
                        <div class="card-stat-lbl">TWK</div>
                    </div>
                </div>
                <div class="card-tier-badge" id="preview-tier">Amateur</div>
                <div class="card-level">Lv. 1/20</div>
            </div>
        </div>

        <div class="card" style="margin-top:1rem;font-size:0.8rem;">
            <div style="font-weight:700;margin-bottom:0.5rem;">Info Statistik Awal</div>
            <?php foreach ($tiers as $t): ?>
            <div class="stat-row">
                <span class="stat-label"><?= $t['name'] ?></span>
                <span class="stat-value">
                    Rating <?= $t['rating'] ?> · OFF/DEF/TWK <?= $t['off'] ?>
                    <?php if ($t['cost'] > 0): ?>
                    <span style="color:var(--text-muted);font-size:0.7rem;">
                        (<?= $t['cost'] ?> 💎)
                    </span>
                    <?php else: ?>
                    <span style="color:var(--success);font-size:0.7rem;">(Gratis)</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php endforeach; ?>
            <div class="stat-row">
                <span class="stat-label">Max Level</span>
                <span class="stat-value">Level 20</span>
            </div>
        </div>
    </div>

</div>

<div id="toast-container"></div>

<script>
// ── TIER DATA ────────────────────────────────────────────────
const tierMap = <?= json_encode($tiers) ?>;

// ── CLASS MAP ──────────────────────────────────────────────
const tierClassMap = {
    'Amateur': 'card-amateur',
    'Trained': 'card-trained',
    'Talented': 'card-talented',
    'Semi-Pro': 'card-semi-pro',
    'Pro': 'card-pro',
    'Expert': 'card-expert',
    'Legendary': 'card-legendary',
};

// ── LIVE PREVIEW ─────────────────────────────────────────────
function updatePreview() {
    const tierSelect = document.getElementById('player-tier');
    const tierName = tierSelect.value;
    const opt = tierSelect.options[tierSelect.selectedIndex];

    const rating = parseInt(opt.dataset.rating) || 45;
    const off = parseInt(opt.dataset.off) || 35;
    const def = parseInt(opt.dataset.def) || 35;
    const twk = parseInt(opt.dataset.twk) || 35;
    const cls = tierClassMap[tierName] || 'card-amateur';

    const card = document.getElementById('preview-card');
    const nameVal = document.getElementById('player-name').value || 'Nama Pemain';
    const cntryVal = document.getElementById('player-country').value || 'Negara';
    const posVal = document.getElementById('player-position').value || 'ST';

    card.className = 'player-card ' + cls;

    document.getElementById('preview-rating').textContent = rating;
    document.getElementById('preview-position').textContent = posVal;
    document.getElementById('preview-name').textContent = nameVal;
    document.getElementById('preview-country').textContent = cntryVal;
    document.getElementById('preview-off').textContent = off;
    document.getElementById('preview-def').textContent = def;
    document.getElementById('preview-twk').textContent = twk;
    document.getElementById('preview-tier').textContent = tierName;
}

document.getElementById('player-tier').addEventListener('change', updatePreview);
document.getElementById('player-name').addEventListener('input', updatePreview);
document.getElementById('player-country').addEventListener('input', updatePreview);
document.getElementById('player-position').addEventListener('change', updatePreview);

// Preview gambar
document.getElementById('card-image').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const wrap = document.getElementById('preview-img-wrap');
    const reader = new FileReader();
    reader.onload = e => {
        wrap.outerHTML = `<img src="${e.target.result}"
      class="card-image" id="preview-img-wrap" alt="preview">`;
    };
    reader.readAsDataURL(file);
});

// ── SUBMIT ───────────────────────────────────────────────────
document.getElementById('btn-add-player').addEventListener('click', async function() {
    const name = document.getElementById('player-name').value.trim();
    const country = document.getElementById('player-country').value.trim();
    const position = document.getElementById('player-position').value;
    const tier = document.getElementById('player-tier').value;
    const skill1 = document.getElementById('skill1').value;
    const skill2 = document.getElementById('skill2').value;
    const imgFile = document.getElementById('card-image').files[0];
    const msgEl = document.getElementById('add-player-msg');

    if (!name || !country || !position) {
        msgEl.innerHTML = '<div class="alert alert-danger">Nama, negara, dan posisi wajib diisi.</div>';
        return;
    }

    if (skill1 && skill1 === skill2) {
        msgEl.innerHTML = '<div class="alert alert-danger">Skill 1 dan 2 tidak boleh sama.</div>';
        return;
    }

    this.disabled = true;
    this.textContent = 'Menyimpan...';

    const fd = new FormData();
    fd.append('player_name', name);
    fd.append('country', country);
    fd.append('position', position);
    fd.append('tier', tier);
    fd.append('skill1', skill1);
    fd.append('skill2', skill2);
    if (imgFile) fd.append('card_image', imgFile);

    try {
        const res = await fetch(apiUrl('/api/add_player.php'), {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (data.success) {
            msgEl.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
            showToast(data.message, 'success');
            // Reset form
            document.getElementById('player-name').value = '';
            document.getElementById('player-country').value = '';
            document.getElementById('player-position').value = '';
            document.getElementById('skill1').value = '';
            document.getElementById('skill2').value = '';
            document.getElementById('card-image').value = '';

            // Reset preview image
            const wrap = document.getElementById('preview-img-wrap');
            wrap.outerHTML = `<div class="card-image-placeholder" id="preview-img-wrap">👤</div>`;

            // Update gems display
            const gemsEl = document.getElementById('gems-display');
            if (data.inventory) {
                gemsEl.textContent = data.inventory.gems || 0;
            }

            updatePreview();
        } else {
            msgEl.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        }
    } catch (e) {
        msgEl.innerHTML = '<div class="alert alert-danger">Gagal. Coba lagi.</div>';
    }

    this.disabled = false;
    this.textContent = '➕ Tambahkan Pemain';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>