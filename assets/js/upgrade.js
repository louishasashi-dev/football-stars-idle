/* ============================================================
   UPGRADE.JS — Team Power & Income Upgrade (FIXED v2)
   ============================================================ */

// Tunggu DOM dan semua resource siap
document.addEventListener("DOMContentLoaded", function () {
  console.log("🚀 Upgrade.js loaded");

  // ── FUNGSI UPGRADE ──────────────────────────────────────
  async function handleUpgrade(btn, type, payment) {
    if (btn.disabled) return;

    console.log(`🔧 Upgrade: type=${type}, payment=${payment}`);

    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = "⏳ ...";

    try {
      const data = await postData(apiUrl("/api/upgrade.php"), {
        type: type,
        payment: payment,
      });

      console.log("📦 Response:", data);

      showToast(data.message, data.success ? "success" : "error");

      if (data.success) {
        // Update navbar currency
        if (typeof updateNavCurrency === "function") {
          updateNavCurrency(data.inventory);
        }

        // Update UI upgrade
        updateUpgradeUI(type, payment, data);

        // Update income display
        if (data.base_income !== undefined) {
          const el = document.getElementById("base-income-display");
          if (el) el.textContent = formatNumber(data.base_income);
        }

        // Update sidebar power
        if (data.new_power !== undefined) {
          const pwrEl = document.getElementById("sidebar-power");
          if (pwrEl) pwrEl.textContent = formatNumber(data.new_power);

          const userPwrEl = document.getElementById("user-power");
          if (userPwrEl) userPwrEl.textContent = formatNumber(data.new_power);
        }
      }
    } catch (error) {
      console.error("❌ Upgrade error:", error);
      showToast("Terjadi kesalahan. Cek console (F12).", "error");
    }

    btn.disabled = false;
    btn.textContent = originalText || "Upgrade";
  }

  // ── REGISTER TOMBOL ──────────────────────────────────────
  const upgradeButtons = [
    { btnId: "btn-upgrade-power-euro", type: "power", payment: "euro" },
    { btnId: "btn-upgrade-power-wt", type: "power", payment: "wintoken" },
    { btnId: "btn-upgrade-income-euro", type: "income", payment: "euro" },
    { btnId: "btn-upgrade-income-wt", type: "income", payment: "wintoken" },
  ];

  upgradeButtons.forEach(function ({ btnId, type, payment }) {
    const btn = document.getElementById(btnId);

    if (!btn) {
      console.warn("⚠️ Tombol tidak ditemukan:", btnId);
      return;
    }

    console.log("✅ Tombol ditemukan:", btnId);

    // ── HAPUS EVENT LISTENER LAMA ──
    const newBtn = btn.cloneNode(true);
    btn.parentNode.replaceChild(newBtn, btn);

    // ── PASANG EVENT LISTENER BARU ──
    newBtn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      handleUpgrade(this, type, payment);
    });
  });

  // ── FUNGSI UPDATE UI ──────────────────────────────────
  function updateUpgradeUI(type, payment, data) {
    const prefix = type + "-" + (payment === "euro" ? "euro" : "wt");

    // Update level
    const levelEl = document.getElementById(prefix + "-level");
    if (levelEl && data.new_level !== undefined) {
      levelEl.textContent = data.new_level;
    }

    // Update cost
    const costEl = document.getElementById(prefix + "-cost");
    if (costEl && data.next_cost !== undefined) {
      if (payment === "euro") {
        costEl.textContent = formatNumber(data.next_cost);
      } else {
        costEl.textContent = data.next_cost;
      }
    }
  }

  // ── TABS UPGRADE ──────────────────────────────────────
  document.querySelectorAll(".upgrade-tab").forEach(function (tab) {
    tab.addEventListener("click", function () {
      const section = this.closest(".upgrade-section");
      if (!section) return;

      section.querySelectorAll(".upgrade-tab").forEach(function (t) {
        t.classList.remove("active");
      });
      section.querySelectorAll(".upgrade-tab-content").forEach(function (c) {
        c.classList.remove("active");
      });

      this.classList.add("active");
      const targetId = "tab-" + this.dataset.tab;
      const target = section.querySelector("#" + targetId);
      if (target) target.classList.add("active");
    });
  });

  console.log("✅ Upgrade.js selesai diinisialisasi");
});

// ── TAMBAHKAN FUNGSI GLOBAL UNTUK DEBUG ─────────────────────
// (Opsional, untuk cek dari console)
console.log(
  "💡 Ketik 'window.upgradeButtons' untuk melihat tombol yang terdaftar",
);
window.upgradeButtons = document.querySelectorAll('[id^="btn-upgrade"]');
