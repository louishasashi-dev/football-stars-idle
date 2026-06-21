/* ============================================================
   GACHA.JS — Full Gacha Engine v2
   ============================================================ */
const Gacha = (() => {
  /* ── STATE ─────────────────────────────────────────────────*/
  let isSpinning = false;
  let pityCount = 0;
  let totalSpins = 0;

  /* ── TIER CONFIG ───────────────────────────────────────────*/
  const TIER_CSS = {
    Amateur: "card-amateur",
    Trained: "card-trained",
    Talented: "card-talented",
    "Semi-Pro": "card-semi-pro",
    Pro: "card-pro",
    Expert: "card-expert",
    Legendary: "card-legendary",
    Goat: "card-goat",
    "ASEAN Legend": "card-asean",
    Celestial: "card-celestial",
  };

  const TIER_CLASS_KEY = {
    Amateur: "tier-amateur",
    Trained: "tier-trained",
    Talented: "tier-talented",
    "Semi-Pro": "tier-semipro",
    Pro: "tier-pro",
    Expert: "tier-expert",
    Legendary: "tier-legendary",
    Goat: "tier-goat",
    "ASEAN Legend": "tier-asean",
    Celestial: "tier-celestial",
  };

  const TIER_COLORS = {
    Amateur: "#9e9e9e",
    Trained: "#64b5f6",
    Talented: "#1976d2",
    "Semi-Pro": "#2e7d32",
    Pro: "#388e3c",
    Expert: "#f57c00",
    Legendary: "#9c27b0",
    Goat: "#ffd700",
    "ASEAN Legend": "#FFD700",
    Celestial: "#7B2FBE",
  };

  /* ── MAIN SPIN ENTRY ───────────────────────────────────────*/
  async function spin(qty) {
    if (isSpinning) return;
    isSpinning = true;
    setButtonsDisabled(true);
    try {
      // Tampilkan loading overlay
      showLoadingOverlay(qty);

      const data = await postData(apiUrl("/api/gacha.php"), { qty });

      // Sembunyikan loading
      hideLoadingOverlay();

      if (!data.success) {
        showToast(data.message, "error");
        isSpinning = false;
        setButtonsDisabled(false);
        return;
      }

      // Update state
      pityCount = data.pity_count ?? pityCount;
      totalSpins = data.total_spins ?? totalSpins;

      // Update currency navbar
      updateNavCurrency(data.inventory);
      updateGemsDisplay(data.inventory.gems);

      // Tampilkan animasi reveal
      await showRevealOverlay(data.results, data.is_free, data.tr_earned ?? 0);

      // Update pity UI
      updatePityUI(data.pity_count);

      // Refresh history
      loadHistory();
    } catch (e) {
      hideLoadingOverlay();
      showToast("Gagal spin. Periksa koneksi.", "error");
    }
    isSpinning = false;
    setButtonsDisabled(false);
  }

  /* ── LOADING OVERLAY ───────────────────────────────────────*/
  function showLoadingOverlay(qty) {
    removeOverlay();
    const div = document.createElement("div");
    div.id = "gacha-loading-overlay";
    div.className = "gacha-spin-overlay";
    div.innerHTML = `
      <div class="gacha-spin-title">✨ Menarik ${qty} Pemain...</div>
      <div class="spinner" style="width:60px;height:60px;border-width:5px;"></div>
      <div style="margin-top:1rem;color:var(--text-muted);font-size:0.875rem;">
        Semoga beruntung!
      </div>`;
    document.body.appendChild(div);
  }

  function hideLoadingOverlay() {
    const el = document.getElementById("gacha-loading-overlay");
    if (el) el.remove();
  }

  /* ── REVEAL OVERLAY ────────────────────────────────────────*/
  async function showRevealOverlay(results, isFree, trEarned = 0) {
    return new Promise((resolve) => {
      removeOverlay();

      // Flash efek
      const flash = document.createElement("div");
      flash.className = "gacha-flash";
      document.body.appendChild(flash);
      setTimeout(() => flash.remove(), 400);

      // Cek apakah ada yang expert+
      const hasSpecial = results.some((r) =>
        ["Expert", "Legendary", "Goat", "ASEAN Legend"].includes(r.tier),
      );
      const duplicateCount = results.filter((r) => r.is_duplicate).length;

      // Overlay utama
      const overlay = document.createElement("div");
      overlay.id = "gacha-reveal-overlay";
      overlay.className = "gacha-spin-overlay";

      const freeLabel = isFree
        ? `<div style="background:rgba(34,197,94,0.2);border:1px solid #22c55e;
                       color:#22c55e;border-radius:8px;padding:0.4rem 1rem;
                       font-size:0.8rem;font-weight:700;margin-bottom:0.75rem;">
             🎉 SPIN GRATIS!
           </div>`
        : "";
      const specialLabel = hasSpecial
        ? `<div style="color:var(--gold);font-size:0.875rem;font-weight:700;
                       margin-bottom:0.5rem;animation:pulse 1s ease infinite;">
             ⭐ SELAMAT! Mendapatkan pemain langka!
           </div>`
        : "";
      const duplicateLabel =
        duplicateCount > 0
          ? `<div style="color:var(--text-secondary);font-size:0.8rem;
                       margin-bottom:0.5rem;">
             🔁 ${duplicateCount} pemain duplikat otomatis ditukar
             menjadi <strong>${trEarned} 🔧 TR Token</strong>
           </div>`
          : "";

      overlay.innerHTML = `
        <div style="text-align:center;margin-bottom:1rem;">
          ${freeLabel}
          ${specialLabel}
          ${duplicateLabel}
          <div class="gacha-spin-title">Hasil Spin (${results.length}x)</div>
        </div>
        <div class="gacha-reveal-grid" id="reveal-grid"></div>
        <div class="gacha-overlay-actions">
          <button class="btn btn-primary btn-lg" id="btn-close-reveal">
            ✓ Tutup
          </button>
          ${
            results.length === 1
              ? `
          <button class="btn btn-warning btn-lg" id="btn-spin-again-1">
            🎰 Spin Lagi (1x)
          </button>`
              : `
          <button class="btn btn-warning btn-lg" id="btn-spin-again-10">
            🎰 Spin Lagi (10x)
          </button>`
          }
        </div>`;

      document.body.appendChild(overlay);

      // Render kartu satu per satu
      const grid = document.getElementById("reveal-grid");
      results.forEach((result, i) => {
        const card = buildRevealCard(result);
        grid.appendChild(card);
        setTimeout(
          () => {
            card.classList.add("revealed");
          },
          i * (results.length === 1 ? 200 : 120),
        );
      });

      // Summary di bawah jika multi-spin
      if (results.length > 1) {
        const summary = buildSummary(results, trEarned);
        overlay.insertBefore(summary, overlay.lastElementChild);
      }

      // Tombol tutup
      document
        .getElementById("btn-close-reveal")
        .addEventListener("click", () => {
          removeOverlay();
          resolve();
        });

      // Tombol spin lagi
      const spinAgain1 = document.getElementById("btn-spin-again-1");
      const spinAgain10 = document.getElementById("btn-spin-again-10");
      if (spinAgain1) {
        spinAgain1.addEventListener("click", () => {
          removeOverlay();
          resolve();
          setTimeout(() => spin(1), 100);
        });
      }
      if (spinAgain10) {
        spinAgain10.addEventListener("click", () => {
          removeOverlay();
          resolve();
          setTimeout(() => spin(10), 100);
        });
      }
    });
  }

  /* ── BUILD SINGLE REVEAL CARD ──────────────────────────────*/
  function buildRevealCard(result) {
    const div = document.createElement("div");
    const isDuplicate = result.is_duplicate ?? false;
    div.className = [
      "gacha-reveal-item",
      TIER_CSS[result.tier] || "card-amateur",
      TIER_CLASS_KEY[result.tier] || "",
      isDuplicate ? "is-duplicate" : "",
    ].join(" ");

    const isNew = result.is_new ?? false;
    const icon =
      result.tier === "Goat"
        ? "👑"
        : result.tier === "Legendary"
          ? "⭐"
          : result.tier === "Expert"
            ? "🔥"
            : result.tier === "ASEAN Legend"
              ? "🦅"
              : "⚽";

    const badge = isDuplicate
      ? `<div class="item-duplicate-badge" title="Sudah punya pemain ini, otomatis ditukar TR Token">
           🔁 Duplikat → +${result.tr_reward ?? 0} 🔧
         </div>`
      : isNew
        ? '<div class="item-new-badge">NEW</div>'
        : "";

    div.innerHTML = `
      ${badge}
      <div style="font-size:1.6rem;${isDuplicate ? "opacity:0.6;" : ""}">${icon}</div>
      <div class="item-rating" style="${isDuplicate ? "opacity:0.6;" : ""}">${result.rating}</div>
      <div class="item-name" style="${isDuplicate ? "opacity:0.6;" : ""}">${escapeHtml(result.player_name)}</div>
      <div class="item-tier"
           style="color:${TIER_COLORS[result.tier] || "#fff"};${isDuplicate ? "opacity:0.6;" : ""}">
        ${result.tier}
      </div>`;
    return div;
  }

  /* ── BUILD SUMMARY ─────────────────────────────────────────*/
  function buildSummary(results, trEarned = 0) {
    const counts = {};
    results.forEach((r) => {
      counts[r.tier] = (counts[r.tier] || 0) + 1;
    });
    const rows = Object.entries(counts)
      .sort((a, b) => {
        const order = [
          "Goat",
          "Legendary",
          "ASEAN Legend",
          "Expert",
          "Pro",
          "Semi-Pro",
          "Talented",
          "Trained",
          "Amateur",
        ];
        return order.indexOf(a[0]) - order.indexOf(b[0]);
      })
      .map(
        ([tier, count]) => `
        <div class="gacha-summary-row">
          <span style="color:${TIER_COLORS[tier] || "#fff"};font-weight:700;">
            ${tier}
          </span>
          <span style="font-weight:700;">${count}x</span>
        </div>`,
      )
      .join("");

    const trRow =
      trEarned > 0
        ? `
        <div class="gacha-summary-row" style="color:var(--text-secondary);">
          <span>🔁 Duplikat ditukar</span>
          <span style="font-weight:700;">+${trEarned} 🔧</span>
        </div>`
        : "";

    const div = document.createElement("div");
    div.className = "gacha-summary";
    div.style.maxWidth = "300px";
    div.style.width = "100%";
    div.innerHTML = `
      <div class="gacha-summary-title">📊 Ringkasan</div>
      ${rows}
      ${trRow}
      <div class="gacha-summary-row" style="margin-top:0.25rem;font-weight:700;">
        <span style="color:var(--text-primary);">Total</span>
        <span>${results.length}x</span>
      </div>`;
    return div;
  }

  /* ── REMOVE OVERLAY ────────────────────────────────────────*/
  function removeOverlay() {
    ["gacha-loading-overlay", "gacha-reveal-overlay"].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.remove();
    });
  }

  /* ── PITY UI ───────────────────────────────────────────────*/
  function updatePityUI(count) {
    pityCount = parseInt(count) || 0;
    // Progress bar
    const fill = document.getElementById("pity-fill");
    const label = document.getElementById("pity-count-label");
    if (fill) fill.style.width = (pityCount / 10) * 100 + "%";
    if (label) label.textContent = pityCount + "/10";

    // Dot indicators
    const dots = document.querySelectorAll(".pity-dot");
    dots.forEach((dot, i) => {
      dot.classList.remove("filled", "almost", "ready");
      if (i < pityCount) {
        if (pityCount >= 9) dot.classList.add("ready");
        else if (pityCount >= 7) dot.classList.add("almost");
        else dot.classList.add("filled");
      }
    });
  }

  /* ── GEMS DISPLAY ──────────────────────────────────────────*/
  function updateGemsDisplay(gems) {
    const el = document.getElementById("gems-display");
    if (el) el.textContent = gems ?? 0;

    // Update harga tombol 10x jika sudah pernah spin
    const btn10 = document.getElementById("btn-spin-10");
    if (btn10 && gems !== undefined) {
      // Label dihandle dari server, tapi kita bisa highlight jika gems kurang
      const cost = btn10.dataset.cost ? parseInt(btn10.dataset.cost) : 100;
      btn10.style.opacity = parseInt(gems) < cost ? "0.5" : "1";
    }
  }

  /* ── BUTTONS TOGGLE ────────────────────────────────────────*/
  function setButtonsDisabled(disabled) {
    ["btn-spin-1", "btn-spin-10"].forEach((id) => {
      const btn = document.getElementById(id);
      if (btn) btn.disabled = disabled;
    });
  }

  /* ── LOAD GACHA HISTORY ────────────────────────────────────*/
  async function loadHistory() {
    const container = document.getElementById("gacha-history-container");
    if (!container) return;

    try {
      const data = await postData(apiUrl("/api/gacha_history.php"));

      if (!data.success || !data.history.length) {
        container.innerHTML = `
          <div class="empty-state" style="padding:1.5rem;">
            <div class="empty-state-icon">🎰</div>
            <div class="empty-state-text">Belum ada riwayat spin.</div>
          </div>`;
        return;
      }

      const rows = data.history
        .map(
          (h) => `
        <tr>
          <td>
            <span style="color:${TIER_COLORS[h.tier_result] || "#fff"};
                         font-weight:700;">
              ${escapeHtml(h.tier_result)}
            </span>
          </td>
          <td style="font-weight:600;">${escapeHtml(h.player_name)}</td>
          <td style="color:var(--text-secondary);">${escapeHtml(h.country)}</td>
          <td style="text-align:center;">${h.rating}</td>
          <td style="color:var(--text-muted);font-size:0.75rem;">
            ${formatDate(h.spun_at)}
          </td>
        </tr>`,
        )
        .join("");

      container.innerHTML = `
        <div style="max-height:320px;overflow-y:auto;">
          <table class="history-table">
            <thead>
              <tr>
                <th>Kasta</th>
                <th>Pemain</th>
                <th>Negara</th>
                <th style="text-align:center;">Rating</th>
                <th>Waktu</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>`;
    } catch (e) {
      container.innerHTML =
        '<div class="alert alert-danger">Gagal memuat history.</div>';
    }
  }

  /* ── HELPERS ───────────────────────────────────────────────*/
  function escapeHtml(str) {
    const d = document.createElement("div");
    d.textContent = str;
    return d.innerHTML;
  }

  function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString("id-ID", {
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  /* ── PITY DOT BUILDER ──────────────────────────────────────*/
  function buildPityDots(currentCount) {
    const wrap = document.getElementById("pity-dots-wrap");
    if (!wrap) return;
    wrap.innerHTML = "";
    for (let i = 0; i < 10; i++) {
      const dot = document.createElement("div");
      dot.className = "pity-dot";
      if (i < currentCount) {
        if (currentCount >= 9) dot.classList.add("ready");
        else if (currentCount >= 7) dot.classList.add("almost");
        else dot.classList.add("filled");
      }
      wrap.appendChild(dot);
    }
  }

  /* ── PUBLIC API ────────────────────────────────────────────*/
  return {
    spin,
    updatePityUI,
    loadHistory,
    buildPityDots,
    init(initialPity) {
      pityCount = initialPity || 0;
      buildPityDots(pityCount);
      loadHistory();
    },
  };
})();

/* ── EVENT LISTENERS ───────────────────────────────────────*/
document.addEventListener("DOMContentLoaded", () => {
  const btn1 = document.getElementById("btn-spin-1");
  const btn10 = document.getElementById("btn-spin-10");
  if (btn1) btn1.addEventListener("click", () => Gacha.spin(1));
  if (btn10) btn10.addEventListener("click", () => Gacha.spin(10));
});
