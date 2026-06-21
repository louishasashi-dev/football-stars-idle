/* ============================================================
   MAIN.JS — Global Utilities
   ============================================================ */

const BASE_URL = "/football-stars-idle";

function apiUrl(path) {
  return BASE_URL + path;
}

// ── TOAST NOTIFICATION ──────────────────────────────────────
function showToast(message, type = "info", duration = 3000) {
  const container = document.getElementById("toast-container");
  if (!container) return;

  const toast = document.createElement("div");
  toast.className = `toast ${type}`;
  toast.textContent = message;
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.transition = "opacity 0.4s ease";
    toast.style.opacity = "0";
    setTimeout(() => toast.remove(), 400);
  }, duration);
}

// ── UPDATE NAVBAR CURRENCY ───────────────────────────────────
function updateNavCurrency(inventory) {
  if (!inventory) return;
  const set = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  };
  set("nav-euro", formatNumber(parseInt(inventory.euro || 0)));
  set("nav-gems", inventory.gems || 0);
  set("nav-tr", inventory.tr_token || 0);
  set("nav-wt", inventory.win_token || 0);
  set("nav-pr", inventory.pr_token || 0);
}

// ── FORMAT NUMBER ────────────────────────────────────────────
function formatNumber(n) {
  n = parseInt(n) || 0;
  if (n >= 1_000_000_000) return (n / 1_000_000_000).toFixed(1) + "B";
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + "M";
  if (n >= 1_000) return (n / 1_000).toFixed(1) + "K";
  return n.toString();
}

// ── UPGRADE TABS (generic) ───────────────────────────────────
document.querySelectorAll(".upgrade-tab").forEach((tab) => {
  tab.addEventListener("click", function () {
    const section = this.closest(".upgrade-section");
    section
      .querySelectorAll(".upgrade-tab")
      .forEach((t) => t.classList.remove("active"));
    section
      .querySelectorAll(".upgrade-tab-content")
      .forEach((c) => c.classList.remove("active"));
    this.classList.add("active");
    const target = section.querySelector("#tab-" + this.dataset.tab);
    if (target) target.classList.add("active");
  });
});

// ── MODAL HELPERS ────────────────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = "flex";
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = "none";
}

// Tutup modal saat klik overlay
document.querySelectorAll(".modal-overlay").forEach((overlay) => {
  overlay.addEventListener("click", function (e) {
    if (e.target === this) this.style.display = "none";
  });
});

// ── POST HELPER ──────────────────────────────────────────────
async function postData(url, data = {}) {
  const fd = new FormData();
  Object.entries(data).forEach(([k, v]) => fd.append(k, v));
  const res = await fetch(url, { method: "POST", body: fd });
  const text = await res.text();
  try {
    return JSON.parse(text);
  } catch (e) {
    console.error("postData JSON parse error dari", url, ":", text);
    return { success: false, message: "Server error. Cek console (F12)." };
  }
}
