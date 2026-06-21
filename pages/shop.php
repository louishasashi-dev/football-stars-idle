<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$userId    = getCurrentUserId();
$pdo       = getDB();
$inventory = getInventory($userId);

// Pity counter
$pityStmt = $pdo->prepare(
    'SELECT spin_count_since_pity FROM pity_tracker WHERE user_id = ?'
);
$pityStmt->execute([$userId]);
$pityRow   = $pityStmt->fetch();
$pityCount = (int)($pityRow['spin_count_since_pity'] ?? 0);

// Total spin user
$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM gacha_log WHERE user_id = ?');
$totalStmt->execute([$userId]);
$totalSpins = (int)$totalStmt->fetchColumn();

// Goat belum dimiliki
$goatStmt = $pdo->prepare('
    SELECT fp.* FROM football_players fp
    JOIN f_player_tier fpt ON fp.tier_id = fpt.id
    WHERE fpt.tier_name = "Goat"
      AND fp.owner_id IS NULL
');
$goatStmt->execute();
$goatPlayers = $goatStmt->fetchAll();

// Pemain user untuk exchange & upgrade kasta
$team    = getUserTeam($userId);
$players = $team ? getTeamPlayers($team['id']) : [];

$exchangeable = array_filter($players, fn($p) =>
    in_array($p['tier_name'], ['Amateur','Trained','Talented','Semi-Pro','Pro'])
);

$upgradeable = array_filter($players, fn($p) =>
    !in_array($p['tier_name'], ['Legendary','Goat'])
);

$trRewardMap = [
    'Amateur'  => 5,
    'Trained'  => 15,
    'Talented' => 30,
    'Semi-Pro' => 50,
    'Pro'      => 70,
];

$prCostMap = [
    'Amateur'  => 10,
    'Trained'  => 25,
    'Talented' => 50,
    'Semi-Pro' => 100,
    'Pro'      => 200,
    'Expert'   => 400,
];

// Harga spin 10x
$spin10Cost = $inventory['first_10spin_used'] ? 100 : 50;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<link rel="stylesheet" href="<?= url('/assets/css/shop.css') ?>">
<link rel="stylesheet" href="<?= url('/assets/css/gacha.css') ?>">

<!-- ============================================================
     BANNER ASEAN ALL-STARS
     ============================================================ -->
<div class="asean-banner-wrapper">
    <div class="asean-banner-content">
        <img src="<?= url('/assets/images/banner/banner123.png') ?>" alt="ASEAN All-Stars" class="asean-banner-img"
            loading="lazy">
        <div class="asean-banner-overlay">
            <!-- <div class="asean-banner-text">
                <span class="asean-banner-icon">🦅</span>
                <span class="asean-banner-title">ASEAN ALL-STARS</span>
                <span class="asean-banner-subtitle">Dapatkan Pemain Legenda Asia Tenggara!</span>
            </div> -->
            <div class="asean-banner-badge">
                <span class="badge-limited">🌟 LIMITED EVENT</span>
            </div>
        </div>
    </div>
</div>

<!-- Banner Celestial Strikers -->
<div class="celestial-banner-wrapper">
    <div class="celestial-banner">
        <div class="celestial-banner-content">
            <div class="celestial-banner-title">✨ CELESTIAL STRIKERS</div>
            <div class="celestial-banner-sub">Event Spesial! Dapatkan Pemain Bintang!</div>
            <div class="celestial-banner-badge">⭐ LIMITED EVENT</div>
        </div>
        <div class="celestial-banner-glow"></div>
    </div>
</div>

<div class="section-title">🏪 Shop</div>

<div class="shop-layout">

    <!-- ═══ KOLOM KIRI ═══ -->
    <div>

        <!-- ── GACHA BANNER ── -->
        <div class="gacha-banner-modern" id="gacha-section">
            <div class="gacha-header-modern">
                <div class="gacha-title-modern">
                    <span class="gacha-icon">✨</span>
                    Gacha Pemain
                    <span class="gacha-badge-new">NEW</span>
                </div>
                <div class="gacha-subtitle-modern">
                    Dapatkan pemain langka dari seluruh dunia!
                </div>
            </div>

            <!-- Info gems & total spin -->
            <div class="gacha-stats-modern">
                <div class="gacha-stat-item">
                    <span class="stat-icon">💎</span>
                    <span class="stat-label">Gems</span>
                    <strong id="gems-display"><?= $inventory['gems'] ?></strong>
                </div>
                <div class="gacha-stat-divider"></div>
                <div class="gacha-stat-item">
                    <span class="stat-icon">🎰</span>
                    <span class="stat-label">Total Spin</span>
                    <strong><?= $totalSpins ?>x</strong>
                </div>
                <div class="gacha-stat-divider"></div>
                <div class="gacha-stat-item">
                    <span class="stat-icon">⚡</span>
                    <span class="stat-label">Pity</span>
                    <strong id="pity-count-label"><?= $pityCount ?>/10</strong>
                </div>
            </div>

            <!-- Tombol spin -->
            <div class="gacha-buttons-modern">
                <?php if (!$inventory['first_10spin_used']): ?>
                <button class="btn-gacha btn-gacha-free" id="btn-spin-1">
                    <span class="btn-gacha-icon">🎰</span>
                    <span class="btn-gacha-text">
                        1x Spin
                        <small class="btn-gacha-sub">GRATIS!</small>
                    </span>
                </button>
                <?php else: ?>
                <button class="btn-gacha btn-gacha-primary" id="btn-spin-1">
                    <span class="btn-gacha-icon">🎰</span>
                    <span class="btn-gacha-text">
                        1x Spin
                        <small class="btn-gacha-sub">10 💎</small>
                    </span>
                </button>
                <?php endif; ?>

                <button class="btn-gacha btn-gacha-warning" id="btn-spin-10" data-cost="<?= $spin10Cost ?>">
                    <span class="btn-gacha-icon">🎰</span>
                    <span class="btn-gacha-text">
                        10x Spin
                        <small class="btn-gacha-sub"><?= $spin10Cost ?> 💎</small>
                        <?php if (!$inventory['first_10spin_used']): ?>
                        <small class="btn-gacha-bonus">⚡ Hemat 50%!</small>
                        <?php endif; ?>
                    </span>
                </button>
            </div>

            <!-- Pity Progress -->
            <div class="pity-section-modern">
                <div class="pity-header-modern">
                    <span class="pity-label">🔮 Pity Progress</span>
                    <span
                        class="pity-counter <?= $pityCount >= 9 ? 'pity-ready' : ($pityCount >= 7 ? 'pity-almost' : '') ?>">
                        <?= $pityCount ?>/10
                    </span>
                </div>
                <div class="pity-track-modern">
                    <div class="pity-fill-modern" id="pity-fill"
                        style="width:<?= ($pityCount/10)*100 ?>%;
                                background:<?= $pityCount >= 9 ? 'linear-gradient(90deg, #FFD700, #FF6B35)' : ($pityCount >= 7 ? 'linear-gradient(90deg, #FF9800, #FF5722)' : 'linear-gradient(90deg, #4CAF50, #8BC34A)') ?>;">
                    </div>
                    <div class="pity-dots-modern" id="pity-dots-wrap">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                        <div class="pity-dot-modern <?= $i <= $pityCount ? 'pity-dot-filled' : '' ?> 
                             <?= $pityCount >= 9 && $i == $pityCount ? 'pity-dot-ready' : '' ?>">
                            <?php if ($i <= $pityCount): ?>
                            <span class="pity-dot-check">✓</span>
                            <?php else: ?>
                            <span class="pity-dot-number"><?= $i ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php if ($pityCount >= 9): ?>
                <div class="pity-notification pity-ready-notification">
                    ⚡ Pity AKTIF! Dijamin dapat Expert/Legendary/ASEAN!
                </div>
                <?php elseif ($pityCount >= 7): ?>
                <div class="pity-notification pity-almost-notification">
                    🔥 Hampir pity! 1-2 spin lagi!
                </div>
                <?php endif; ?>
            </div>

            <!-- Drop Rates -->
            <div class="gacha-drop-rates-modern">
                <div class="drop-rates-title">📊 Drop Rates</div>
                <div class="drop-rates-grid">
                    <div class="drop-rate-item" style="--rate-color:#9e9e9e;">
                        <span class="rate-tier">Amateur</span>
                        <span class="rate-value">45%</span>
                        <div class="rate-bar">
                            <div style="width:45%;background:#9e9e9e;"></div>
                        </div>
                    </div>
                    <div class="drop-rate-item" style="--rate-color:#64b5f6;">
                        <span class="rate-tier">Trained</span>
                        <span class="rate-value">20%</span>
                        <div class="rate-bar">
                            <div style="width:20%;background:#64b5f6;"></div>
                        </div>
                    </div>
                    <div class="drop-rate-item" style="--rate-color:#42a5f5;">
                        <span class="rate-tier">Talented</span>
                        <span class="rate-value">10%</span>
                        <div class="rate-bar">
                            <div style="width:10%;background:#42a5f5;"></div>
                        </div>
                    </div>
                    <div class="drop-rate-item" style="--rate-color:#66bb6a;">
                        <span class="rate-tier">Semi-Pro</span>
                        <span class="rate-value">10%</span>
                        <div class="rate-bar">
                            <div style="width:10%;background:#66bb6a;"></div>
                        </div>
                    </div>
                    <div class="drop-rate-item" style="--rate-color:#a5d6a7;">
                        <span class="rate-tier">Pro</span>
                        <span class="rate-value">7%</span>
                        <div class="rate-bar">
                            <div style="width:7%;background:#a5d6a7;"></div>
                        </div>
                    </div>
                    <div class="drop-rate-item" style="--rate-color:#ffb74d;">
                        <span class="rate-tier">Expert</span>
                        <span class="rate-value">5%</span>
                        <div class="rate-bar">
                            <div style="width:5%;background:#ffb74d;"></div>
                        </div>
                    </div>
                    <div class="drop-rate-item" style="--rate-color:#ce93d8;">
                        <span class="rate-tier">Legendary</span>
                        <span class="rate-value">3%</span>
                        <div class="rate-bar">
                            <div style="width:3%;background:#ce93d8;"></div>
                        </div>
                    </div>
                    <div class="drop-rate-item" style="--rate-color:#FFD700;">
                        <span class="rate-tier">🦅 ASEAN</span>
                        <span class="rate-value">0.5%</span>
                        <div class="rate-bar">
                            <div style="width:0.5%;background:#FFD700;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── HISTORY GACHA ── -->
        <div class="card" style="margin-top:1.25rem;">
            <div class="card-header">
                <div class="card-title">📜 Riwayat Spin</div>
                <button class="btn btn-outline btn-sm" onclick="Gacha.loadHistory()">
                    🔄 Refresh
                </button>
            </div>
            <div id="gacha-history-container">
                <div class="spinner"></div>
            </div>
        </div>

        <!-- ── BELI GOAT ── -->
        <div style="margin-top:1.25rem;">
            <div class="section-title" style="font-size:1rem;">👑 Pemain GOAT</div>
            <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.75rem;">
                Hanya 2 pemain GOAT di dunia. Sekali dimiliki, tidak bisa direbut kecuali dijual.
            </div>

            <?php if (empty($goatPlayers)): ?>
            <div class="alert alert-warning">
                Semua pemain GOAT sudah dimiliki. Cek
                <a href="/pages/market.php">Market</a> untuk membelinya.
            </div>
            <?php else: ?>
            <div class="grid-2">
                <?php foreach ($goatPlayers as $g): ?>
                <div class="goat-card">
                    <div style="font-size:0.65rem;color:var(--gold);text-transform:uppercase;
                        letter-spacing:0.1em;">Greatest of All Time</div>
                    <div class="goat-player-name"><?= e($g['player_name']) ?></div>
                    <div style="font-size:0.78rem;color:var(--text-secondary);">
                        <?= e($g['country']) ?> · <?= e($g['position']) ?>
                    </div>
                    <div class="goat-rating"><?= $g['rating'] ?></div>
                    <div style="display:flex;gap:1.25rem;justify-content:center;
                        font-size:0.8rem;color:var(--text-secondary);margin:0.5rem 0;">
                        <span>⚔️ <?= $g['offence'] ?></span>
                        <span>🛡️ <?= $g['defence'] ?></span>
                        <span>🤝 <?= $g['teamwork'] ?></span>
                    </div>
                    <div class="goat-price">1.000 💎 Gems</div>
                    <button class="btn btn-gold btn-full" onclick="buyGoat(<?= $g['id'] ?>,
                                     '<?= e($g['player_name']) ?>')">
                        👑 Beli Sekarang
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /kolom kiri -->

    <!-- ═══ KOLOM KANAN ═══ -->
    <div>

        <!-- ── BELI PR TOKEN ── -->
        <div class="card" style="margin-bottom:1rem;">
            <div class="card-header">
                <div class="card-title">⬆️ Beli PR Token</div>
            </div>
            <div style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:0.75rem;">
                Gunakan PR Token untuk menaikkan kasta pemain.<br>
                Kamu punya: <strong id="pr-token-display">
                    <?= $inventory['pr_token'] ?> PR Token
                </strong>
            </div>
            <div style="font-size:0.75rem;color:var(--text-muted);
                  margin-bottom:0.75rem;">
                Harga: <strong>50 💎 per PR Token</strong>
            </div>

            <table class="exchange-table" style="margin-bottom:1rem;">
                <thead>
                    <tr>
                        <th>Dari → Ke</th>
                        <th>PR Token</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
          $kastaTabel = [
            'Amateur → Trained'   => 10,
            'Trained → Talented'  => 25,
            'Talented → Semi-Pro' => 50,
            'Semi-Pro → Pro'      => 100,
            'Pro → Expert'        => 200,
            'Expert → Legendary'  => 400,
          ];
          foreach ($kastaTabel as $label => $cost): ?>
                    <tr>
                        <td><?= $label ?></td>
                        <td><strong><?= $cost ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="display:flex;gap:0.5rem;align-items:center;margin-bottom:0.75rem;">
                <input type="number" id="pr-qty" class="form-control" value="1" min="1" max="999" style="width:80px;">
                <span style="font-size:0.8rem;color:var(--text-secondary);">
                    PR Token = <span id="pr-total-cost">50</span> 💎
                </span>
            </div>
            <button class="btn btn-primary btn-full" id="btn-buy-pr">
                Beli PR Token
            </button>
        </div>

        <!-- ── NAIK KASTA ── -->
        <div class="card" style="margin-bottom:1rem;">
            <div class="card-header">
                <div class="card-title">🚀 Naik Kasta Pemain</div>
            </div>
            <div style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:0.75rem;">
                Tingkatkan kasta pemain menggunakan PR Token.
                Maks hingga kasta Legendary.
            </div>

            <?php if (empty($upgradeable)): ?>
            <div style="font-size:0.8rem;color:var(--text-muted);">
                Tidak ada pemain yang bisa dinaikkan kastanya.
            </div>
            <?php else: ?>
            <div class="form-group">
                <label class="form-label">Pilih Pemain</label>
                <select class="form-control" id="upgrade-tier-select">
                    <option value="">— Pilih pemain —</option>
                    <?php foreach ($upgradeable as $p): ?>
                    <option value="<?= $p['id'] ?>" data-tier="<?= e($p['tier_name']) ?>"
                        data-cost="<?= $prCostMap[$p['tier_name']] ?? 0 ?>">
                        <?= e($p['player_name']) ?>
                        (<?= e($p['tier_name']) ?>)
                        — <?= $prCostMap[$p['tier_name']] ?? '?' ?> PR Token
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="upgrade-tier-info" style="display:none;margin-bottom:0.75rem;">
                <div class="alert alert-info" style="font-size:0.8rem;">
                    Naik ke: <strong id="upgrade-next-tier">—</strong><br>
                    Butuh: <strong id="upgrade-pr-cost">—</strong> PR Token<br>
                    PR Token kamu: <strong id="upgrade-pr-have">
                        <?= $inventory['pr_token'] ?>
                    </strong>
                </div>
            </div>
            <button class="btn btn-success btn-full" id="btn-upgrade-tier">
                🚀 Naik Kasta
            </button>
            <?php endif; ?>
        </div>

        <!-- ── TUKAR PEMAIN ── -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">🔄 Tukar Pemain → TR Token</div>
            </div>
            <div style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:0.75rem;">
                TR Token kamu:
                <strong id="tr-token-display"><?= $inventory['tr_token'] ?></strong> 🔧
            </div>

            <table class="exchange-table" style="margin-bottom:0.75rem;">
                <thead>
                    <tr>
                        <th>Kasta</th>
                        <th>TR Token</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trRewardMap as $kasta => $reward): ?>
                    <tr>
                        <td><?= $kasta ?></td>
                        <td><strong>+<?= $reward ?></strong> 🔧</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($exchangeable)): ?>
            <div class="empty-state" style="padding:1rem;">
                <div class="empty-state-text">
                    Tidak ada pemain yang bisa ditukar.
                </div>
            </div>
            <?php else: ?>
            <div style="max-height:260px;overflow-y:auto;
                    display:flex;flex-direction:column;gap:0.4rem;">
                <?php foreach ($exchangeable as $p): ?>
                <div class="card-list-item" style="justify-content:space-between;">
                    <div>
                        <div style="font-size:0.8rem;font-weight:700;">
                            <?= e($p['player_name']) ?>
                        </div>
                        <div style="font-size:0.7rem;color:var(--text-muted);">
                            <?= e($p['tier_name']) ?> · Lv.<?= $p['current_level'] ?>
                        </div>
                    </div>
                    <button class="btn btn-warning btn-sm" onclick="exchangePlayer(
                      <?= $p['id'] ?>,
                      '<?= e($p['player_name']) ?>',
                      <?= $trRewardMap[$p['tier_name']] ?>
                    )">
                        +<?= $trRewardMap[$p['tier_name']] ?> 🔧
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /kolom kanan -->

</div><!-- /shop-layout -->

<div id="toast-container"></div>

<script src="<?= url('/assets/js/gacha.js') ?>"></script>
<script>
if (typeof apiUrl === 'undefined') {
    const BASE_URL = '/football-stars-idle';

    function apiUrl(p) {
        return BASE_URL + p;
    }
}

/* ── INIT GACHA ─────────────────────────────────────────────*/
document.addEventListener('DOMContentLoaded', () => {
    Gacha.init(<?= $pityCount ?>);
});

/* ── BELI GOAT ──────────────────────────────────────────────*/
async function buyGoat(playerId, name) {
    if (!confirm('Beli ' + name + ' seharga 1.000 Gems?\nGems kamu: ' +
            document.getElementById('gems-display').textContent)) return;

    const data = await postData(apiUrl('/api/shop.php'), {
        action: 'buy_goat',
        player_id: playerId
    });
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1500);
}

/* ── BELI PR TOKEN ──────────────────────────────────────────*/
document.getElementById('pr-qty').addEventListener('input', function() {
    const qty = Math.max(1, parseInt(this.value) || 1);
    document.getElementById('pr-total-cost').textContent = qty * 50;
});

document.getElementById('btn-buy-pr').addEventListener('click', async function() {
    const qty = parseInt(document.getElementById('pr-qty').value) || 1;
    const cost = qty * 50;
    const gems = parseInt(document.getElementById('gems-display').textContent) || 0;

    if (gems < cost) {
        showToast('Gems tidak cukup. Butuh ' + cost + ' 💎', 'error');
        return;
    }

    const data = await postData(apiUrl('/api/shop.php'), {
        action: 'buy_pr_token',
        qty
    });
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) {
        updateNavCurrency(data.inventory);
        document.getElementById('pr-token-display').textContent =
            data.inventory.pr_token + ' PR Token';
        document.getElementById('upgrade-pr-have').textContent =
            data.inventory.pr_token;
    }
});

/* ── NAIK KASTA ─────────────────────────────────────────────*/
const tierOrder = [
    'Amateur', 'Trained', 'Talented', 'Semi-Pro',
    'Pro', 'Expert', 'Legendary'
];

document.getElementById('upgrade-tier-select')?.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const tier = opt.dataset.tier;
    const cost = opt.dataset.cost;
    const info = document.getElementById('upgrade-tier-info');

    if (!this.value || !tier) {
        info.style.display = 'none';
        return;
    }

    const nextIdx = tierOrder.indexOf(tier) + 1;
    const nextTier = nextIdx < tierOrder.length ? tierOrder[nextIdx] : null;

    if (!nextTier) {
        info.style.display = 'none';
        return;
    }

    document.getElementById('upgrade-next-tier').textContent = nextTier;
    document.getElementById('upgrade-pr-cost').textContent = cost;
    info.style.display = 'block';
});

document.getElementById('btn-upgrade-tier')?.addEventListener('click', async function() {
    const sel = document.getElementById('upgrade-tier-select');
    const playerId = sel?.value;
    if (!playerId) {
        showToast('Pilih pemain terlebih dahulu.', 'error');
        return;
    }

    const name = sel.options[sel.selectedIndex].text;
    if (!confirm('Naik kasta: ' + name + '?')) return;

    const data = await postData(apiUrl('/api/shop.php'), {
        action: 'upgrade_tier',
        player_id: playerId
    });
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) {
        updateNavCurrency(data.inventory);
        document.getElementById('pr-token-display').textContent =
            data.inventory.pr_token + ' PR Token';
        document.getElementById('upgrade-pr-have').textContent =
            data.inventory.pr_token;
        setTimeout(() => location.reload(), 1200);
    }
});

/* ── TUKAR PEMAIN ───────────────────────────────────────────*/
async function exchangePlayer(playerId, name, reward) {
    if (!confirm('Tukar ' + name + ' dengan ' + reward + ' TR Token?\n' +
            'Pemain akan dihapus dari tim kamu.')) return;

    const data = await postData(apiUrl('/api/shop.php'), {
        action: 'exchange_player',
        player_id: playerId
    });
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) {
        updateNavCurrency(data.inventory);
        document.getElementById('tr-token-display').textContent =
            data.inventory.tr_token;
        setTimeout(() => location.reload(), 1000);
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>