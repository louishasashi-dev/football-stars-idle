/* ============================================================
   LINEUP.JS — Formasi & Starting 11
   ============================================================ */

const Lineup = (() => {
  // ── STATE ─────────────────────────────────────────────────
  let formation = "4-3-3";
  let startingSlots = []; // [{slotIndex, position, player|null}]
  let benchSlots = []; // [{slotIndex, player|null}]
  let allPlayers = []; // semua pemain di tim
  let selectedSlot = null; // slot yang sedang menunggu pemain

  // ── FORMASI CONFIG ────────────────────────────────────────
  const FORMATIONS = {
    "4-3-3": { def: 4, mid: 3, att: 3 },
    "4-4-2": { def: 4, mid: 4, att: 2 },
    "4-2-3-1": { def: 4, mid: 5, att: 2 }, // 4+2+3 = mid 5, att 2 (CAM row)
    "3-5-2": { def: 3, mid: 5, att: 2 },
    "3-4-3": { def: 3, mid: 4, att: 3 },
    "5-3-2": { def: 5, mid: 3, att: 2 },
    "5-4-1": { def: 5, mid: 4, att: 1 },
  };

  // Posisi default per section
  const POSITION_OPTIONS = {
    gk: ["GK"],
    def: ["CB", "LB", "RB", "CDM"],
    mid: ["CM", "CDM", "CAM", "LM", "RM"],
    att: ["ST", "LW", "RW", "CAM"],
  };

  // ── INIT ─────────────────────────────────────────────────
  async function init() {
    await loadLineup();
    bindEvents();
  }

  // ── LOAD LINEUP FROM SERVER ───────────────────────────────
  async function loadLineup() {
    try {
      const data = await postData(apiUrl("/api/lineup.php"), { action: "get" });
      if (!data.success) {
        showToast(data.message, "error");
        return;
      }

      formation = data.lineup.formation || "4-3-3";
      allPlayers = [...(data.players || []), ...(data.available || [])];

      // Set formasi selector
      const sel = document.getElementById("formation-select");
      if (sel) sel.value = formation;

      // Build slot structure
      buildSlots(formation);

      // Isi slot dari data server
      fillSlotsFromServer(data.players);

      // Render available
      renderAvailable(data.available);

      updateCounts();
    } catch (e) {
      showToast("Gagal memuat lineup.", "error");
    }
  }

  // ── BUILD SLOT STRUCTURE ──────────────────────────────────
  function buildSlots(f) {
    const config = FORMATIONS[f] || FORMATIONS["4-3-3"];
    startingSlots = [];

    // GK (1 slot)
    startingSlots.push({
      slotIndex: 0,
      section: "gk",
      position: "GK",
      player: null,
    });

    // DEF
    for (let i = 0; i < config.def; i++) {
      const pos = i === 0 ? "LB" : i === config.def - 1 ? "RB" : "CB";
      startingSlots.push({
        slotIndex: startingSlots.length,
        section: "def",
        position: pos,
        player: null,
      });
    }

    // MID
    for (let i = 0; i < config.mid; i++) {
      const pos = i === 0 ? "CM" : i === config.mid - 1 ? "CM" : "CM";
      startingSlots.push({
        slotIndex: startingSlots.length,
        section: "mid",
        position: pos,
        player: null,
      });
    }

    // ATT
    for (let i = 0; i < config.att; i++) {
      const pos =
        config.att === 1
          ? "ST"
          : i === 0
            ? "LW"
            : i === config.att - 1
              ? "RW"
              : "ST";
      startingSlots.push({
        slotIndex: startingSlots.length,
        section: "att",
        position: pos,
        player: null,
      });
    }

    // Bench (8 slot)
    benchSlots = Array.from({ length: 8 }, (_, i) => ({
      slotIndex: i,
      player: null,
    }));

    renderField();
    renderBench();
  }

  // ── FILL SLOTS FROM SERVER DATA ───────────────────────────
  function fillSlotsFromServer(players) {
    // Reset dulu
    startingSlots.forEach((s) => (s.player = null));
    benchSlots.forEach((s) => (s.player = null));

    players.forEach((p) => {
      if (p.slot_type === "starting") {
        const slot = startingSlots[p.slot_index];
        if (slot) {
          slot.player = p;
          slot.position = p.position || slot.position;
        }
      } else {
        const slot = benchSlots[p.slot_index];
        if (slot) slot.player = p;
      }
    });

    renderField();
    renderBench();
  }

  // ── RENDER FIELD ──────────────────────────────────────────
  function renderField() {
    const sections = {
      att: document.getElementById("slots-attack"),
      mid: document.getElementById("slots-mid"),
      def: document.getElementById("slots-def"),
      gk: document.getElementById("slots-gk"),
    };

    Object.values(sections).forEach((s) => {
      if (s) s.innerHTML = "";
    });

    startingSlots.forEach((slot, idx) => {
      const section = sections[slot.section];
      if (!section) return;

      const div = document.createElement("div");
      div.className = "field-slot";
      div.dataset.idx = idx;

      if (slot.player) {
        // ── FULL CARD (sama seperti card_collection) ──
        const p = slot.player;

        // Tentukan class tier
        const tierClassMap = {
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
        const tierClass = tierClassMap[p.tier_name] || "card-amateur";

        div.innerHTML = `
                <div class="player-card field-card ${tierClass}" 
                     data-player-id="${p.id}"
                     title="Klik untuk hapus dari starting">
                    <div class="card-rating">${p.rating}</div>
                    <div class="card-position">${p.position || slot.position}</div>
                    ${
                      p.card_image
                        ? `<img src="${p.card_image}" alt="" class="card-image">`
                        : `<div class="card-image-placeholder">👤</div>`
                    }
                    <div class="card-name">${p.player_name}</div>
                    <div class="card-country">${p.country || ""}</div>
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
                    <div class="card-tier-badge">${p.tier_name || ""}</div>
                    <div class="card-level">Lv. ${p.current_level || 1}/20</div>
                    <div class="card-remove-btn" onclick="event.stopPropagation(); Lineup.removeFromStarting(${idx})">✕</div>
                </div>
            `;
        div.addEventListener("click", () => removeFromStarting(idx));
      } else {
        // ── SLOT KOSONG ──────────────────────────────────
        div.innerHTML = `
                <div class="field-slot-empty-card" 
                     title="Klik untuk isi slot ${slot.position}">
                    <div class="empty-slot-icon">+</div>
                    <div class="empty-slot-pos">${slot.position}</div>
                </div>
            `;
        div.addEventListener("click", () => selectStartingSlot(idx));
      }

      // Position selector
      const posSection =
        slot.section === "gk"
          ? "gk"
          : slot.section === "def"
            ? "def"
            : slot.section === "mid"
              ? "mid"
              : "att";
      const posOptions = POSITION_OPTIONS[posSection] || ["CM"];

      const posSelect = document.createElement("select");
      posSelect.className = "pos-select";
      posOptions.forEach((p) => {
        const opt = document.createElement("option");
        opt.value = p;
        opt.textContent = p;
        if (p === slot.position) opt.selected = true;
        posSelect.appendChild(opt);
      });
      posSelect.addEventListener("change", function () {
        startingSlots[idx].position = this.value;
        // Re-render untuk update posisi di card
        renderField();
      });
      posSelect.addEventListener("click", (e) => e.stopPropagation());

      div.appendChild(posSelect);
      section.appendChild(div);
    });
  }

  // ── RENDER BENCH ──────────────────────────────────────────
  function renderBench() {
    const container = document.getElementById("bench-slots");
    if (!container) return;
    container.innerHTML = "";

    benchSlots.forEach((slot, idx) => {
      const div = document.createElement("div");
      div.className = "bench-slot-wrapper";
      div.dataset.idx = idx;

      if (slot.player) {
        // ── MINI CARD UNTUK BENCH ──────────────────────
        const p = slot.player;

        // Tentukan class tier
        const tierClassMap = {
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
        const tierClass = tierClassMap[p.tier_name] || "card-amateur";

        div.innerHTML = `
                <div class="bench-mini-card ${tierClass}" 
                     title="${p.player_name} — Klik untuk hapus dari cadangan">
                    <div class="bench-mini-rating">${p.rating}</div>
                    <div class="bench-mini-name">${p.player_name.split(" ").slice(-1)[0]}</div>
                    <div class="bench-mini-tier">${p.tier_name || ""}</div>
                    <div class="bench-mini-remove" onclick="event.stopPropagation(); Lineup.removeFromBench(${idx})">✕</div>
                </div>
            `;
        div.addEventListener("click", () => removeFromBench(idx));
      } else {
        // ── SLOT KOSONG ──────────────────────────────────
        div.innerHTML = `
                <div class="bench-empty-card" 
                     title="Klik untuk tambah cadangan">
                    <span class="bench-empty-icon">+</span>
                    <span class="bench-empty-num">${idx + 1}</span>
                </div>
            `;
        div.addEventListener("click", () => selectBenchSlot(idx));
      }

      container.appendChild(div);
    });
  }

  // ── RENDER AVAILABLE PLAYERS ──────────────────────────────
  function renderAvailable(players, filter = "") {
    const container = document.getElementById("available-players");
    if (!container) return;

    // ID yang sudah di lineup
    const usedIds = new Set([
      ...startingSlots.filter((s) => s.player).map((s) => s.player.id),
      ...benchSlots.filter((s) => s.player).map((s) => s.player.id),
    ]);

    const filtered = players.filter(
      (p) =>
        !filter || p.player_name.toLowerCase().includes(filter.toLowerCase()),
    );

    if (filtered.length === 0) {
      container.innerHTML =
        '<div style="color:var(--text-muted);font-size:0.8rem;padding:0.5rem;">Tidak ada pemain.</div>';
      return;
    }

    container.innerHTML = filtered
      .map((p) => {
        const inLineup = usedIds.has(p.id);
        return `
        <div class="avail-player-item ${inLineup ? "in-lineup" : ""}"
             data-player-id="${p.id}"
             onclick="${inLineup ? "" : `Lineup.pickPlayer(${p.id})`}">
          <div class="avail-tier-dot" style="background:${p.card_color || "#9e9e9e"};"></div>
          <div class="avail-player-name">${p.player_name}</div>
          <div class="avail-player-meta">${p.position || p.default_position || ""} · ${p.tier_name || ""}</div>
          <div class="avail-player-rating">${p.rating}</div>
          ${inLineup ? '<span style="font-size:0.65rem;color:var(--success);">✓</span>' : ""}
        </div>`;
      })
      .join("");
  }

  // ── SELECT SLOT ───────────────────────────────────────────
  function selectStartingSlot(idx) {
    selectedSlot = { type: "starting", idx };
    showToast("Pilih pemain dari daftar di kanan.", "info");
    highlightSelectedSlot(idx, "starting");
    refreshAvailableDisplay();
  }

  function selectBenchSlot(idx) {
    selectedSlot = { type: "bench", idx };
    showToast("Pilih pemain dari daftar di kanan.", "info");
    highlightSelectedSlot(idx, "bench");
    refreshAvailableDisplay();
  }

  function highlightSelectedSlot(idx, type) {
    document
      .querySelectorAll(".field-slot-circle, .bench-slot")
      .forEach((el) => el.classList.remove("slot-selected"));
  }

  // ── PICK PLAYER ───────────────────────────────────────────
  function pickPlayer(playerId) {
    const player = allPlayers.find(
      (p) => parseInt(p.id) === parseInt(playerId),
    );
    if (!player) return;

    // Cek tidak sudah di lineup
    const inStarting = startingSlots.some(
      (s) => s.player && parseInt(s.player.id) === parseInt(playerId),
    );
    const inBench = benchSlots.some(
      (s) => s.player && parseInt(s.player.id) === parseInt(playerId),
    );
    if (inStarting || inBench) {
      showToast("Pemain sudah ada di lineup.", "error");
      return;
    }

    if (!selectedSlot) {
      // Auto-assign: cari slot kosong
      if (autoAssign(player)) {
        refreshAll();
        return;
      }
      showToast("Pilih slot dulu (klik + di field atau bench).", "info");
      return;
    }

    if (selectedSlot.type === "starting") {
      const slot = startingSlots[selectedSlot.idx];
      if (slot) {
        slot.player = player;
        showToast(player.player_name + " masuk starting!", "success");
      }
    } else {
      const slot = benchSlots[selectedSlot.idx];
      if (slot) {
        slot.player = player;
        showToast(player.player_name + " masuk cadangan!", "success");
      }
    }

    selectedSlot = null;
    refreshAll();
  }

  // ── AUTO ASSIGN ───────────────────────────────────────────
  function autoAssign(player) {
    // Coba starting dulu
    const emptyStarting = startingSlots.find((s) => !s.player);
    if (emptyStarting && startingSlots.filter((s) => s.player).length < 11) {
      emptyStarting.player = player;
      return true;
    }
    // Coba bench
    const emptyBench = benchSlots.find((s) => !s.player);
    if (emptyBench && benchSlots.filter((s) => s.player).length < 8) {
      emptyBench.player = player;
      return true;
    }
    return false;
  }

  // ── REMOVE ───────────────────────────────────────────────
  function removeFromStarting(idx) {
    const slot = startingSlots[idx];
    if (!slot || !slot.player) return;
    const name = slot.player.player_name;
    slot.player = null;
    showToast(name + " dikeluarkan dari starting.", "info");
    refreshAll();
  }

  function removeFromBench(idx) {
    const slot = benchSlots[idx];
    if (!slot || !slot.player) return;
    const name = slot.player.player_name;
    slot.player = null;
    showToast(name + " dikeluarkan dari cadangan.", "info");
    refreshAll();
  }

  // ── AUTO FILL ────────────────────────────────────────────
  function autoFill() {
    // Clear lineup
    startingSlots.forEach((s) => (s.player = null));
    benchSlots.forEach((s) => (s.player = null));

    // Sort pemain berdasarkan rating desc
    const sorted = [...allPlayers].sort((a, b) => b.rating - a.rating);

    // Isi starting
    let startingCount = 0;
    for (const p of sorted) {
      if (startingCount >= 11) break;
      const slot = startingSlots[startingCount];
      if (slot) {
        slot.player = p;
        startingCount++;
      }
    }

    // Isi bench (sisa pemain)
    let benchCount = 0;
    for (const p of sorted) {
      if (benchCount >= 8) break;
      const inStarting = startingSlots.some(
        (s) => s.player && s.player.id === p.id,
      );
      if (!inStarting) {
        const slot = benchSlots[benchCount];
        if (slot) {
          slot.player = p;
          benchCount++;
        }
      }
    }

    showToast(
      "Auto-fill selesai! " +
        startingCount +
        " starting, " +
        benchCount +
        " cadangan.",
      "success",
    );
    refreshAll();
  }

  // ── CLEAR ────────────────────────────────────────────────
  function clearLineup() {
    if (!confirm("Kosongkan semua lineup?")) return;
    startingSlots.forEach((s) => (s.player = null));
    benchSlots.forEach((s) => (s.player = null));
    selectedSlot = null;
    refreshAll();
    showToast("Lineup dikosongkan.", "info");
  }

  // ── SAVE ─────────────────────────────────────────────────
  async function saveLineup() {
    const starting = startingSlots
      .filter((s) => s.player)
      .map((s) => ({ player_id: s.player.id, position: s.position }));

    const bench = benchSlots
      .filter((s) => s.player)
      .map((s) => ({ player_id: s.player.id }));

    const btn = document.getElementById("btn-save-lineup");
    if (btn) {
      btn.disabled = true;
      btn.textContent = "Menyimpan...";
    }

    try {
      const data = await postData(apiUrl("/api/lineup.php"), {
        action: "save",
        formation: formation,
        starting: JSON.stringify(starting),
        bench: JSON.stringify(bench),
      });

      showToast(data.message, data.success ? "success" : "error");

      if (data.success) {
        // Update power display
        const pwrEl = document.getElementById("lineup-power");
        if (pwrEl) pwrEl.textContent = formatNumber(data.new_power);

        // Update navbar juga
        const navPwr = document.getElementById("sidebar-power");
        if (navPwr) navPwr.textContent = formatNumber(data.new_power);
      }
    } catch (e) {
      showToast("Gagal menyimpan.", "error");
    }

    if (btn) {
      btn.disabled = false;
      btn.textContent = "💾 Simpan Lineup";
    }
  }

  // ── UPDATE COUNTS ────────────────────────────────────────
  function updateCounts() {
    const sc = document.getElementById("starting-count");
    const bc = document.getElementById("bench-count");
    if (sc) sc.textContent = startingSlots.filter((s) => s.player).length;
    if (bc) bc.textContent = benchSlots.filter((s) => s.player).length;
  }

  // ── REFRESH ALL ──────────────────────────────────────────
  function refreshAll() {
    renderField();
    renderBench();
    refreshAvailableDisplay();
    updateCounts();
  }

  function refreshAvailableDisplay() {
    const filter = document.getElementById("player-search")?.value || "";
    renderAvailable(allPlayers, filter);
  }

  // ── BIND EVENTS ──────────────────────────────────────────
  function bindEvents() {
    // Ganti formasi
    document
      .getElementById("formation-select")
      ?.addEventListener("change", function () {
        formation = this.value;
        buildSlots(formation);
        updateCounts();
      });

    // Simpan
    document
      .getElementById("btn-save-lineup")
      ?.addEventListener("click", saveLineup);

    // Auto fill
    document
      .getElementById("btn-auto-fill")
      ?.addEventListener("click", autoFill);

    // Clear
    document
      .getElementById("btn-clear-lineup")
      ?.addEventListener("click", clearLineup);

    // Search
    document
      .getElementById("player-search")
      ?.addEventListener("input", function () {
        renderAvailable(allPlayers, this.value);
      });
  }

  return { init, pickPlayer };
})();

document.addEventListener("DOMContentLoaded", () => Lineup.init());
