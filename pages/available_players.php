<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<style>
.available-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.filter-bar {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
    align-items: center;
}

.filter-bar .filter-btn {
    font-size: 0.75rem;
}

.search-input {
    flex: 1;
    min-width: 150px;
}

.stats-bar {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
    padding: 0.75rem 1rem;
    background: var(--bg-card);
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
}

.stats-bar .stat-item {
    font-size: 0.85rem;
}

.stats-bar .stat-item strong {
    font-size: 1.1rem;
}
</style>
<div class="section-title">📋 Pool Pemain (Belum Dimiliki)</div>
<div class="stats-bar">
    <div class="stat-item">Total tersedia: <strong id="total-available">0</strong> pemain</div>
    <div class="stat-item">💡 Semua pemain di sini <strong>belum dimiliki</strong> oleh siapapun</div>
</div>
<div class="filter-bar">
    <button class="btn btn-outline btn-sm filter-btn active" data-tier="all">Semua</button>
    <button class="btn btn-outline btn-sm filter-btn" data-tier="Amateur">Amateur</button>
    <button class="btn btn-outline btn-sm filter-btn" data-tier="Trained">Trained</button>
    <button class="btn btn-outline btn-sm filter-btn" data-tier="Talented">Talented</button>
    <button class="btn btn-outline btn-sm filter-btn" data-tier="Semi-Pro">Semi-Pro</button>
    <button class="btn btn-outline btn-sm filter-btn" data-tier="Pro">Pro</button>
    <button class="btn btn-outline btn-sm filter-btn" data-tier="Expert">Expert</button>
    <button class="btn btn-outline btn-sm filter-btn" data-tier="Legendary">Legendary</button>
    <button class="btn btn-outline btn-sm filter-btn" data-tier="ASEAN Legend">🦅 ASEAN</button>
    <button class="btn btn-outline btn-sm filter-btn" data-tier="Goat">👑 Goat</button>
    <button class="btn btn-outline btn-sm filter-btn" data-tier="Celestial">🎌 Celestial</button>
    <input type="text" class="form-control search-input" id="search-input" placeholder="Cari pemain...">
</div>
<div class="available-grid" id="available-grid">
    <div class="spinner"></div>
</div>
<script>
const TIER_CLASS_MAP = {
    'Amateur': 'card-amateur',
    'Trained': 'card-trained',
    'Talented': 'card-talented',
    'Semi-Pro': 'card-semi-pro',
    'Pro': 'card-pro',
    'Expert': 'card-expert',
    'Legendary': 'card-legendary',
    'Goat': 'card-goat',
    'ASEAN Legend': 'card-asean',
    'Celestial': 'card-celestial',
};

async function loadAvailablePlayers(tier = 'all', search = '') {
    const grid = document.getElementById('available-grid');
    grid.innerHTML = '<div class="spinner"></div>';
    const url = apiUrl('/api/get_available_players.php') +
        '?tier=' + encodeURIComponent(tier) +
        '&search=' + encodeURIComponent(search);
    try {
        const res = await fetch(url);
        const data = await res.json();
        if (!data.success) {
            grid.innerHTML = '<div class="alert alert-danger">Gagal memuat data.</div>';
            return;
        }
        document.getElementById('total-available').textContent = data.total;
        if (data.players.length === 0) {
            grid.innerHTML = `
                <div style="grid-column:1/-1;text-align:center;padding:2rem;">
                    <div style="font-size:2rem;margin-bottom:0.5rem;">🎯</div>
                    <div style="color:var(--text-secondary);">
                        Tidak ada pemain yang tersedia di tier ini.
                    </div>
                </div>
            `;
            return;
        }
        grid.innerHTML = data.players.map(p => {
            const tierClass = TIER_CLASS_MAP[p.tier_name] || 'card-amateur';
            const imageHtml = p.card_image ?
                `<img src="${escapeHtml(p.card_image)}" alt="" class="card-image" loading="lazy">` :
                `<div class="card-image-placeholder">👤</div>`;
            return `
                <div class="player-card ${tierClass}" data-tier="${escapeHtml(p.tier_name)}" data-player-id="${p.id}">
                    <div class="card-rating">${p.rating}</div>
                    <div class="card-position">${escapeHtml(p.position)}</div>
                    ${imageHtml}
                    <div class="card-name">${escapeHtml(p.player_name)}</div>
                    <div class="card-country">${escapeHtml(p.country)}</div>
                    <div class="card-stats">
                        <div class="card-stat">
                            <div class="card-stat-val">${p.offence}</div>
                            <div class="card-stat-lbl">OFF</div>
                        </div>
                        <div class="card-stat">
                            <div class="card-stat-val">${p.defence}</div>
                            <div class="card-stat-lbl">DEF</div>
                        </div>
                        <div class="card-stat">
                            <div class="card-stat-val">${p.teamwork}</div>
                            <div class="card-stat-lbl">TWK</div>
                        </div>
                    </div>
                    <div class="card-tier-badge">${escapeHtml(p.tier_name)}</div>
                    <div class="card-level">Lv. ${p.current_level || 1}/20</div>
                </div>
            `;
        }).join('');
    } catch (e) {
        grid.innerHTML = '<div class="alert alert-danger">Error: ' + e.message + '</div>';
    }
}

function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}
let currentTier = 'all';
let currentSearch = '';
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        currentTier = this.dataset.tier;
        loadAvailablePlayers(currentTier, currentSearch);
    });
});
document.getElementById('search-input').addEventListener('input', function() {
    currentSearch = this.value;
    loadAvailablePlayers(currentTier, currentSearch);
});
document.addEventListener('DOMContentLoaded', () => {
    loadAvailablePlayers();
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>