<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$userId = getCurrentUserId();
$team   = getUserTeam($userId);
$pdo    = getDB();

// ── PAGINATION ──────────────────────────────────────────────
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Hitung total pemain
$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM team_members WHERE team_id = ?');
$totalStmt->execute([$team['id']]);
$totalPlayers = (int)$totalStmt->fetchColumn();
$totalPages = ceil($totalPlayers / $perPage);

// Ambil pemain dengan limit
$stmt = $pdo->prepare('
    SELECT 
        fp.id, fp.player_name, fp.country, fp.position, 
        fp.rating, fp.offence, fp.defence, fp.teamwork,
        fp.card_image, fp.current_level,
        fpt.tier_name, fpt.card_color,
        fpt.card_color_secondary, fpt.card_color_tertiary,
        fpt.upgrade_base_cost, fpt.upgrade_stat_per_level
    FROM team_members tm
    JOIN football_players fp ON tm.player_id = fp.id
    JOIN f_player_tier fpt ON fp.tier_id = fpt.id
    WHERE tm.team_id = ?
    ORDER BY fp.rating DESC
    LIMIT ? OFFSET ?
');
$stmt->execute([$team['id'], $perPage, $offset]);
$players = $stmt->fetchAll();

// ── FUNGSI TIER CLASS ──────────────────────────────────────
function tierClass(string $tier): string {
    $map = [
        'Amateur'      => 'amateur',
        'Trained'      => 'trained',
        'Talented'     => 'talented',
        'Semi-Pro'     => 'semi-pro',
        'Pro'          => 'pro',
        'Expert'       => 'expert',
        'Legendary'    => 'legendary',
        'Goat'         => 'goat',
        'ASEAN Legend' => 'asean',
        'Celestial'    => 'celestial',
    ];
    return 'card-' . ($map[$tier] ?? 'amateur');
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="section-title">🃏 Koleksi Pemain</div>

<!-- Filter Kasta -->
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.25rem;">
    <button class="btn btn-outline btn-sm filter-btn active" data-tier="all">Semua</button>
    <?php
    $tiers = array_unique(array_column($players, 'tier_name'));
    foreach ($tiers as $t):
    ?>
    <button class="btn btn-outline btn-sm filter-btn" data-tier="<?= e($t) ?>"><?= e($t) ?></button>
    <?php endforeach; ?>
</div>

<div class="cards-grid" id="cards-grid">
    <?php if (empty($players)): ?>
    <div class="empty-state" style="grid-column:1/-1;">
        <div class="empty-state-icon">👤</div>
        <div class="empty-state-text">Belum ada pemain di tim Anda.</div>
    </div>
    <?php else: ?>
    <?php foreach ($players as $p): ?>
    <div class="player-card <?= tierClass($p['tier_name']) ?>" data-tier="<?= e($p['tier_name']) ?>"
        data-player-id="<?= $p['id'] ?>" onclick="openPlayerModal(<?= $p['id'] ?>)">

        <div class="card-rating"><?= $p['rating'] ?></div>
        <div class="card-position"><?= e($p['position']) ?></div>

        <?php if ($p['card_image']): ?>
        <img src="<?= e($p['card_image']) ?>" alt="" class="card-image" loading="lazy">
        <?php else: ?>
        <div class="card-image-placeholder">👤</div>
        <?php endif; ?>

        <div class="card-name"><?= e($p['player_name']) ?></div>
        <div class="card-country"><?= e($p['country']) ?></div>

        <div class="card-stats">
            <div class="card-stat">
                <div class="card-stat-val"><?= $p['offence'] ?></div>
                <div class="card-stat-lbl">OFF</div>
            </div>
            <div class="card-stat">
                <div class="card-stat-val"><?= $p['defence'] ?></div>
                <div class="card-stat-lbl">DEF</div>
            </div>
            <div class="card-stat">
                <div class="card-stat-val"><?= $p['teamwork'] ?></div>
                <div class="card-stat-lbl">TWK</div>
            </div>
        </div>

        <div class="card-tier-badge"><?= e($p['tier_name']) ?></div>
        <div class="card-level">Lv. <?= $p['current_level'] ?>/20</div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ── PAGINATION ── -->
<?php if ($totalPages > 1): ?>
<div style="display:flex;gap:0.5rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap;">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?page=<?= $i ?>" class="btn btn-outline btn-sm <?= $i == $page ? 'active' : '' ?>">
        <?= $i ?>
    </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<!-- Modal Detail Player -->
<div class="modal-overlay" id="player-modal" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" id="modal-player-name">—</div>
            <button class="modal-close" onclick="closePlayerModal()">✕</button>
        </div>
        <div id="modal-player-body">
            <div class="spinner"></div>
        </div>
    </div>
</div>

<div id="toast-container"></div>

<script>
// ── FILTER ──────────────────────────────────────────────────
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const tier = this.dataset.tier;
        document.querySelectorAll('.player-card').forEach(card => {
            card.style.display = (tier === 'all' || card.dataset.tier === tier) ? '' : 'none';
        });
    });
});

// ── MODAL ──────────────────────────────────────────────────
let modalCache = {};

async function openPlayerModal(playerId) {
    const modal = document.getElementById('player-modal');
    const body = document.getElementById('modal-player-body');

    if (!modal || !body) {
        console.error('Modal elements not found');
        return;
    }

    modal.style.display = 'flex';

    // Cek cache
    if (modalCache[playerId]) {
        body.innerHTML = modalCache[playerId];
        const nameEl = document.getElementById('modal-player-name');
        if (nameEl) {
            const cachedName = body.querySelector('.modal-player-name-cache');
            nameEl.textContent = cachedName?.textContent || '—';
        }
        return;
    }

    body.innerHTML = '<div class="spinner"></div>';

    try {
        const fd = new FormData();
        fd.append('player_id', playerId);

        const res = await fetch(apiUrl('/api/get_player.php'), {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (!data.success) {
            body.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
            return;
        }

        const p = data.player;
        const s = data.skills || [];
        const nameEl = document.getElementById('modal-player-name');
        if (nameEl) nameEl.textContent = p.player_name;

        const nextCost = data.next_upgrade_cost || 0;

        // ── CEK APAKAH BISA NAIK KASTA ──────────────────
        const tierOrder = ['Amateur', 'Trained', 'Talented', 'Semi-Pro', 'Pro', 'Expert', 'Legendary'];
        const currentTierIndex = tierOrder.indexOf(p.tier_name);
        const canUpgradeTier = currentTierIndex !== -1 && currentTierIndex < tierOrder.length - 1;
        const nextTier = canUpgradeTier ? tierOrder[currentTierIndex + 1] : null;

        const prCostMap = {
            'Amateur': 10,
            'Trained': 25,
            'Talented': 50,
            'Semi-Pro': 100,
            'Pro': 200,
            'Expert': 400
        };
        const prCost = prCostMap[p.tier_name] || 0;

        const skillHtml = s.length ?
            s.map(sk =>
                `<span class="badge" style="background:var(--accent);color:#fff;">${sk.skill_name} (+${sk.effect_value}% ${sk.effect_type})</span>`
            ).join(' ') :
            '<span style="color:var(--text-muted)">Tidak ada skill</span>';

        // ── BUILD HTML MODAL ────────────────────────────
        let upgradeTierHtml = '';
        if (canUpgradeTier && p.tier_name !== 'Legendary' && p.tier_name !== 'Goat' && p.tier_name !==
            'ASEAN Legend') {
            upgradeTierHtml = `
                <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid var(--border);">
                    <div style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:0.4rem;">
                        ⬆️ Naik Kasta: <strong>${p.tier_name} → ${nextTier}</strong>
                        <span style="color:var(--text-muted);font-size:0.7rem;">
                            (Butuh ${prCost} ⬆️ PR Token)
                        </span>
                    </div>
                    <button class="btn btn-warning btn-full" onclick="upgradeTier(${p.id}, '${p.tier_name}', '${nextTier}', ${prCost})" 
                            id="btn-upgrade-tier-${p.id}">
                        🚀 Naik ke ${nextTier}
                    </button>
                </div>
            `;
        } else if (p.tier_name === 'Legendary' || p.tier_name === 'Goat' || p.tier_name === 'ASEAN Legend') {
            upgradeTierHtml = `
                <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid var(--border);">
                    <div style="font-size:0.8rem;color:var(--gold);font-weight:700;">
                        👑 Sudah di kasta tertinggi!
                    </div>
                </div>
            `;
        }

        const html = `
            <div class="modal-player-name-cache" style="display:none;">${p.player_name}</div>
            <div class="stat-row"><span class="stat-label">Kasta</span><span class="stat-value">${p.tier_name}</span></div>
            <div class="stat-row"><span class="stat-label">Rating</span><span class="stat-value">${p.rating}</span></div>
            <div class="stat-row"><span class="stat-label">Offence</span><span class="stat-value">${p.offence}</span></div>
            <div class="stat-row"><span class="stat-label">Defence</span><span class="stat-value">${p.defence}</span></div>
            <div class="stat-row"><span class="stat-label">Teamwork</span><span class="stat-value">${p.teamwork}</span></div>
            <div class="stat-row"><span class="stat-label">Level</span><span class="stat-value">${p.current_level}/20</span></div>
            <div class="stat-row"><span class="stat-label">Negara</span><span class="stat-value">${p.country}</span></div>
            <div class="stat-row"><span class="stat-label">Posisi</span><span class="stat-value">${p.position}</span></div>
            <div style="margin:0.75rem 0;"><div class="stat-label" style="margin-bottom:0.4rem;">Skills</div>${skillHtml}</div>

            ${upgradeTierHtml}

            ${p.current_level < 20 ? `
            <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid var(--border);">
                <div style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:0.4rem;">
                    ⬆️ Upgrade Level: <strong>${nextCost} 🔧 TR Token</strong>
                </div>
                <button class="btn btn-success btn-full" onclick="upgradePlayer(${p.id})">⬆️ Upgrade Level</button>
            </div>` : `<div class="alert alert-info" style="margin-top:0.75rem;">Player sudah level maksimal!</div>`}
        `;

        // Simpan ke cache
        modalCache[playerId] = html;
        body.innerHTML = html;

    } catch (e) {
        console.error('Error loading player:', e);
        body.innerHTML = '<div class="alert alert-danger">Gagal memuat detail pemain.</div>';
    }
}

function closePlayerModal() {
    document.getElementById('player-modal').style.display = 'none';
}

// ── UPGRADE LEVEL ──────────────────────────────────────────
async function upgradePlayer(playerId) {
    const fd = new FormData();
    fd.append('player_id', playerId);
    try {
        const res = await fetch(apiUrl('/api/upgrade_player.php'), {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            updateNavCurrency(data.inventory);
            delete modalCache[playerId];
            openPlayerModal(playerId);
        }
    } catch (e) {
        showToast('Gagal upgrade.', 'error');
    }
}

// ── NAIK KASTA ─────────────────────────────────────────────
async function upgradeTier(playerId, currentTier, nextTier, cost) {
    // Cek PR Token dari navbar
    const prTokenEl = document.getElementById('nav-pr');
    const prToken = parseInt(prTokenEl?.textContent || '0');

    if (prToken < cost) {
        showToast(`PR Token tidak cukup! Butuh ${cost} ⬆️ PR Token.`, 'error');
        return;
    }

    if (!confirm(`Naikkan kasta pemain dari ${currentTier} ke ${nextTier}?\nButuh ${cost} ⬆️ PR Token.`)) {
        return;
    }

    const btn = document.getElementById(`btn-upgrade-tier-${playerId}`);
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Memproses...';
    }

    try {
        const fd = new FormData();
        fd.append('action', 'upgrade_tier');
        fd.append('player_id', playerId);

        const res = await fetch(apiUrl('/api/shop.php'), {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        showToast(data.message, data.success ? 'success' : 'error');

        if (data.success) {
            updateNavCurrency(data.inventory);
            delete modalCache[playerId];
            openPlayerModal(playerId);
            setTimeout(() => location.reload(), 1500);
        }
    } catch (e) {
        console.error('Error upgrading tier:', e);
        showToast('Gagal naik kasta: ' + e.message, 'error');
    }

    if (btn) {
        btn.disabled = false;
        btn.textContent = `🚀 Naik ke ${nextTier}`;
    }
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>