<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$userId = getCurrentUserId();
$team   = getUserTeam($userId);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<link rel="stylesheet" href="<?= url('/assets/css/lineup.css') ?>">

<div class="section-title">📋 Lineup & Formasi</div>

<div class="lineup-layout">

    <!-- ═══ KIRI: FIELD + STARTING ═══ -->
    <div class="lineup-main">

        <!-- Formasi Selector -->
        <div class="card" style="margin-bottom:1rem;">
            <div class="card-header">
                <div class="card-title">⚙️ Pengaturan Formasi</div>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <span style="font-size:0.8rem;color:var(--text-secondary);">Formasi:</span>
                    <select class="form-control" id="formation-select" style="width:auto;">
                        <option value="4-3-3">4-3-3</option>
                        <option value="4-4-2">4-4-2</option>
                        <option value="4-2-3-1">4-2-3-1</option>
                        <option value="3-5-2">3-5-2</option>
                        <option value="3-4-3">3-4-3</option>
                        <option value="5-3-2">5-3-2</option>
                        <option value="5-4-1">5-4-1</option>
                    </select>
                </div>
            </div>

            <!-- Info power -->
            <div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:0.8rem;">
                <span>Starting: <strong id="starting-count">0</strong>/11</span>
                <span>Cadangan: <strong id="bench-count">0</strong>/8</span>
                <span>Power Tim: <strong
                        id="lineup-power"><?= formatNumber((int)($team['team_power'] ?? 0)) ?></strong></span>
            </div>
        </div>

        <!-- FIELD VISUAL -->
        <div class="field-wrap">
            <div class="field" id="field">

                <!-- SLOT POSISI (diisi JS berdasarkan formasi) -->
                <div class="field-section field-attack" id="slots-attack"></div>
                <div class="field-section field-mid" id="slots-mid"></div>
                <div class="field-section field-def" id="slots-def"></div>
                <div class="field-section field-gk" id="slots-gk"></div>

            </div>
        </div>

        <!-- Tombol Simpan -->
        <div style="margin-top:1rem;display:flex;gap:0.75rem;">
            <button class="btn btn-success btn-lg" id="btn-save-lineup">
                💾 Simpan Lineup
            </button>
            <button class="btn btn-outline" id="btn-auto-fill">
                ⚡ Auto-Fill Terbaik
            </button>
            <button class="btn btn-danger btn-outline" id="btn-clear-lineup">
                🗑️ Kosongkan
            </button>
        </div>

    </div><!-- /lineup-main -->

    <!-- ═══ KANAN: BENCH + AVAILABLE ═══ -->
    <div class="lineup-sidebar">

        <!-- BENCH -->
        <div class="card" style="margin-bottom:1rem;">
            <div class="card-header">
                <div class="card-title">🪑 Pemain Cadangan (max 8)</div>
            </div>
            <div id="bench-slots" class="bench-grid"></div>
        </div>

        <!-- AVAILABLE PLAYERS -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">👥 Pilih Pemain</div>
                <input type="text" id="player-search" class="form-control" placeholder="Cari nama..."
                    style="width:140px;font-size:0.8rem;padding:0.3rem 0.6rem;">
            </div>
            <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.5rem;">
                Klik pemain → masuk ke slot yang dipilih
            </div>
            <div id="available-players" style="max-height:400px;overflow-y:auto;
           display:flex;flex-direction:column;gap:0.3rem;"></div>
        </div>

    </div><!-- /lineup-sidebar -->

</div><!-- /lineup-layout -->

<!-- Modal pilih slot saat klik pemain -->
<div class="modal-overlay" id="slot-modal" style="display:none;">
    <div class="modal-box" style="max-width:340px;">
        <div class="modal-header">
            <div class="modal-title" id="slot-modal-title">Tambahkan ke?</div>
            <button class="modal-close" onclick="closeModal('slot-modal')">✕</button>
        </div>
        <div id="slot-modal-body"></div>
    </div>
</div>

<div id="toast-container"></div>

<script src="<?= url('/assets/js/lineup.js') ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>