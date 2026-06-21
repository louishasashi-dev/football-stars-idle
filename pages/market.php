<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$userId = getCurrentUserId();
$pdo    = getDB();

// Semua listing di market
$listingStmt = $pdo->prepare('
    SELECT ml.id AS listing_id, ml.price, ml.listed_at,
           ml.seller_id,
           fp.id AS player_id, fp.player_name, fp.rating,
           fp.offence, fp.defence, fp.teamwork, fp.country, fp.position,
           fpt.tier_name, fpt.card_color,
           u.username AS seller_name
    FROM market_listings ml
    JOIN football_players fp  ON fp.id  = ml.player_id
    JOIN f_player_tier fpt    ON fpt.id = fp.tier_id
    JOIN users u              ON u.id   = ml.seller_id
    ORDER BY ml.listed_at DESC
');
$listingStmt->execute();
$listings = $listingStmt->fetchAll();

// Listing milik user ini (untuk cancel)
$myListings = array_filter($listings, fn($l) => (int)$l['seller_id'] === $userId);
$inventory  = getInventory($userId);

function tierClass(string $tier): string {
    $map = [
        'Expert'   => 'expert',
        'Legendary'=> 'legendary',
        'Goat'     => 'goat',
    ];
    return 'card-' . ($map[$tier] ?? 'amateur');
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="section-title">🏷️ Market</div>

<div style="display:flex;gap:1rem;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;">
    <div style="font-size:0.875rem;color:var(--text-secondary);">
        Saldo: <strong><?= formatNumber((int)$inventory['euro']) ?> 💶</strong>
    </div>
    <div style="font-size:0.8rem;color:var(--text-muted);">
        Hanya pemain Expert, Legendary, dan Goat yang bisa diperjualbelikan.
    </div>
</div>

<!-- Listing Saya -->
<?php if (!empty($myListings)): ?>
<div style="margin-bottom:1.5rem;">
    <div class="card-header" style="margin-bottom:0.75rem;">
        <div class="card-title">📦 Listing Saya</div>
    </div>
    <div style="display:flex;flex-direction:column;gap:0.5rem;">
        <?php foreach ($myListings as $l): ?>
        <div class="card-list-item" style="justify-content:space-between;gap:1rem;">
            <div class="card-list-tier-dot" style="background:<?= e($l['card_color']) ?>;"></div>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:0.875rem;"><?= e($l['player_name']) ?></div>
                <div style="font-size:0.75rem;color:var(--text-muted);">
                    <?= e($l['tier_name']) ?> · Rating <?= $l['rating'] ?>
                </div>
            </div>
            <div style="font-weight:700;color:var(--gold);">
                <?= formatNumber((int)$l['price']) ?> 💶
            </div>
            <button class="btn btn-danger btn-sm"
                onclick="cancelListing(<?= $l['listing_id'] ?>, '<?= e($l['player_name']) ?>')">
                Tarik
            </button>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Semua Listing -->
<div class="card-header" style="margin-bottom:1rem;">
    <div class="card-title">🛒 Semua Pemain Dijual</div>
</div>

<?php
$otherListings = array_filter($listings, fn($l) => (int)$l['seller_id'] !== $userId);
?>

<?php if (empty($otherListings)): ?>
<div class="empty-state">
    <div class="empty-state-icon">🏷️</div>
    <div class="empty-state-text">Tidak ada yang menjual player saat ini.</div>
</div>
<?php else: ?>
<div class="grid-3">
    <?php foreach ($otherListings as $l): ?>
    <div class="card" style="border-color:<?= e($l['card_color']) ?>33;">
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
            <div style="width:10px;height:10px;border-radius:50%;
                    background:<?= e($l['card_color']) ?>;flex-shrink:0;"></div>
            <span class="badge" style="background:<?= e($l['card_color']) ?>33;
                                   color:<?= e($l['card_color']) ?>;">
                <?= e($l['tier_name']) ?>
            </span>
        </div>

        <div style="font-size:1rem;font-weight:800;margin-bottom:0.25rem;">
            <?= e($l['player_name']) ?>
        </div>
        <div style="font-size:0.75rem;color:var(--text-secondary);margin-bottom:0.75rem;">
            <?= e($l['country']) ?> · <?= e($l['position']) ?>
        </div>

        <div style="display:flex;gap:0.75rem;margin-bottom:0.75rem;font-size:0.8rem;">
            <span>⚽ <?= $l['rating'] ?></span>
            <span>ATK <?= $l['offence'] ?></span>
            <span>DEF <?= $l['defence'] ?></span>
            <span>TWK <?= $l['teamwork'] ?></span>
        </div>

        <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.75rem;">
            Dijual oleh: <strong><?= e($l['seller_name']) ?></strong>
        </div>

        <div style="font-size:1.1rem;font-weight:900;color:var(--gold);margin-bottom:0.75rem;">
            <?= formatNumber((int)$l['price']) ?> 💶
        </div>

        <button class="btn btn-success btn-full btn-sm" onclick="buyPlayer(<?= $l['listing_id'] ?>,
                                 '<?= e($l['player_name']) ?>',
                                 <?= $l['price'] ?>)">
            Beli Sekarang
        </button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Jual Pemain -->
<div class="modal-overlay" id="sell-modal" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">💰 Jual Pemain</div>
            <button class="modal-close" onclick="closeModal('sell-modal')">✕</button>
        </div>

        <div class="form-group">
            <label class="form-label">Pilih Pemain (Expert/Legendary/Goat)</label>
            <select class="form-control" id="sell-player-select">
                <option value="">— Pilih pemain —</option>
                <?php
        $myTeam    = getUserTeam($userId);
        $myPlayers = $myTeam ? getTeamPlayers($myTeam['id']) : [];
        foreach ($myPlayers as $p):
            if (!in_array($p['tier_name'], ['Expert','Legendary','Goat'])) continue;
            // Cek belum di market
            $inMarket = array_filter($myListings, fn($l) => (int)$l['player_id'] === (int)$p['id']);
            if (!empty($inMarket)) continue;
        ?>
                <option value="<?= $p['id'] ?>">
                    <?= e($p['player_name']) ?> (<?= e($p['tier_name']) ?>) — Rating <?= $p['rating'] ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Harga Jual (Euro)</label>
            <input type="number" class="form-control" id="sell-price" placeholder="Masukkan harga" min="1">
        </div>

        <div class="alert alert-warning" style="font-size:0.8rem;">
            ⚠️ Level pemain akan direset ke 1 saat didaftarkan ke market.
        </div>

        <button class="btn btn-danger btn-full" id="btn-sell-confirm">
            Daftarkan ke Market
        </button>
    </div>
</div>

<div id="toast-container"></div>

<button class="btn btn-primary" onclick="openModal('sell-modal')" style="position:fixed;bottom:2rem;right:2rem;z-index:50;
               box-shadow:var(--shadow);">
    + Jual Pemain
</button>

<script>
if (typeof apiUrl === 'undefined') {
    const BASE_URL = '/football-stars-idle';

    function apiUrl(p) {
        return BASE_URL + p;
    }
}

async function buyPlayer(listingId, name, price) {
    if (!confirm('Beli ' + name + ' seharga ' + price.toLocaleString() + ' Euro?')) return;
    const data = await postData(apiUrl('/api/market.php'), {
        action: 'buy',
        listing_id: listingId
    });
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1200);
}

async function cancelListing(listingId, name) {
    if (!confirm('Tarik ' + name + ' dari market?')) return;
    const data = await postData(apiUrl('/api/market.php'), {
        action: 'cancel',
        listing_id: listingId
    });
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1000);
}

document.getElementById('btn-sell-confirm')?.addEventListener('click', async function() {
    const playerId = document.getElementById('sell-player-select').value;
    const price = document.getElementById('sell-price').value;
    if (!playerId || !price || parseInt(price) < 1) {
        showToast('Pilih pemain dan masukkan harga yang valid.', 'error');
        return;
    }
    const data = await postData(apiUrl('/api/market.php'), {
        action: 'sell',
        player_id: playerId,
        price
    });
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1000);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>