/* ============================================================
   MATCH.JS — Idle Match Engine v4 (Live Score + Goal Events)
   ============================================================ */

const Match = (() => {
  let matchSeconds = 60;
  let cooldownSecs = 10;
  let timerInterval = null;

  // State match
  let scoreUser = 0;
  let scoreBot = 0;
  let goalEvents = []; // [{minute, team, playerName}]
  let userPlayers = []; // nama pemain user (diambil dari API)
  let botTeamName = "";
  let pendingGoals = []; // goal yang akan "terjadi" selama match

  const el = (id) => document.getElementById(id);

  // ── INIT ─────────────────────────────────────────────────
  function init() {
    if (!el("match-timer")) return;
    resetAll();
    startMatch();
  }

  function resetAll() {
    scoreUser = 0;
    scoreBot = 0;
    goalEvents = [];
    pendingGoals = [];
    renderScore();
    renderGoalEvents();
    const display = document.querySelector(".match-score-display");
    if (display) display.className = "match-score-display";
  }

  // ── START MATCH ───────────────────────────────────────────
  async function startMatch() {
    matchSeconds = 60;
    resetAll();

    // Reset banner & rewards
    const bannerEl = el("result-banner");
    const rewardsEl = el("match-rewards");
    if (bannerEl) {
      bannerEl.className = "match-result-banner";
      bannerEl.textContent = "";
    }
    if (rewardsEl) rewardsEl.style.display = "none";

    el("match-timer")?.classList.remove("cooldown");
    if (el("match-timer")) el("match-timer").textContent = "60";
    if (el("match-status")) el("match-status").textContent = "Memuat lawan...";
    if (el("bot-team-name"))
      el("bot-team-name").textContent = "Mencari lawan...";
    if (el("bot-power")) el("bot-power").textContent = "...";

    // Panggil match_start
    try {
      const data = await postData(apiUrl("/api/match_start.php"));
      if (data.success) {
        if (el("user-power"))
          el("user-power").textContent = formatNumber(data.user_power);
        if (el("bot-team-name"))
          el("bot-team-name").textContent = data.bot_team_name;
        if (el("bot-power"))
          el("bot-power").textContent = formatNumber(data.bot_power);
        if (el("match-status"))
          el("match-status").textContent = "Match sedang berjalan...";

        botTeamName = data.bot_team_name;
        userPlayers = data.user_players || ["Pemain"];

        // Generate semua kejadian gol untuk 60 detik ini
        pendingGoals = generateGoalSchedule(
          data.predicted_result,
          data.score_user,
          data.score_bot,
          userPlayers,
          data.bot_team_name,
        );
      } else {
        if (el("match-status"))
          el("match-status").textContent = "Match sedang berjalan...";
      }
    } catch (e) {
      if (el("match-status"))
        el("match-status").textContent = "Match sedang berjalan...";
    }

    clearInterval(timerInterval);
    timerInterval = setInterval(tickMatch, 1000);
  }

  // ── GENERATE JADWAL GOL ───────────────────────────────────
  // ── GENERATE JADWAL GOL ───────────────────────────────────
  // Buat daftar gol dengan menit random sebelum match dimulai
  function generateGoalSchedule(result, totalUser, totalBot, players, botName) {
    const goals = [];
    const usedMinutes = new Set();

    function randMinute() {
      let m,
        tries = 0;
      do {
        m = Math.floor(Math.random() * 58) + 1;
        tries++;
      } while (usedMinutes.has(m) && tries < 100);
      usedMinutes.add(m);
      return m;
    }

    function gameSecToMinute(sec) {
      const minute = Math.round((60 - sec) * 1.5);
      return Math.max(1, Math.min(90, minute));
    }

    // ── FILTER: HANYA PEMAIN YANG ADA DI LINEUP ──
    // Data dari server seharusnya sudah mengirim `user_players`
    // Tapi kita filter hanya yang ada di lineup (starting + bench)

    let lineupPlayers = [];
    if (Array.isArray(players) && players.length > 0) {
      // Jika players adalah array objek dengan property name
      lineupPlayers = players.map((p) => {
        if (typeof p === "string") return p;
        return p.player_name || p.name || "Pemain";
      });
    } else {
      // Fallback ke daftar default
      lineupPlayers = [
        "Pemain",
        "Pemain 2",
        "Pemain 3",
        "Pemain 4",
        "Pemain 5",
      ];
    }

    // Hapus duplikat dan pastikan minimal 5 pemain
    lineupPlayers = [...new Set(lineupPlayers)];
    while (lineupPlayers.length < 5) {
      lineupPlayers.push("Pemain " + (lineupPlayers.length + 1));
    }

    // Gol user - HANYA dari lineup players
    for (let i = 0; i < totalUser; i++) {
      const sec = randMinute();
      const minute = gameSecToMinute(sec);
      const player =
        lineupPlayers[Math.floor(Math.random() * lineupPlayers.length)];
      goals.push({
        atSecond: sec,
        minute: minute,
        team: "user",
        playerName: player,
      });
    }

    // Gol bot
    const botPlayerNames = [
      "Adam Alis",
      "Frans Putros",
      "Vab der verden",
      "Sylvanus Comvallius",
      "Marco Simic",
      "Ezechiel Ndouassel",
      "Bojan Malisic",
      "David Da Silva",
      "Seds",
      "Nicholas Stefains",
      "Fasial Akbar",
      "Kamal",
      "Arya Saputra",
      "Korla",
      "Damian",
    ];

    for (let i = 0; i < totalBot; i++) {
      const sec = randMinute();
      const minute = gameSecToMinute(sec);
      const player =
        botPlayerNames[Math.floor(Math.random() * botPlayerNames.length)];
      goals.push({
        atSecond: sec,
        minute: minute,
        team: "bot",
        playerName: player,
      });
    }

    return goals;
  }

  // ── TICK MATCH ────────────────────────────────────────────
  function tickMatch() {
    matchSeconds--;
    if (el("match-timer")) el("match-timer").textContent = matchSeconds;

    // Cek apakah ada gol di detik ini
    const goalsNow = pendingGoals.filter((g) => g.atSecond === matchSeconds);
    goalsNow.forEach((goal) => {
      if (goal.team === "user") {
        scoreUser++;
      } else {
        scoreBot++;
      }
      renderScore();

      // Tambah ke event log
      goalEvents.push(goal);
      renderGoalEvents();

      // Flash efek di papan skor
      flashScore(goal.team);
    });

    if (matchSeconds <= 0) {
      clearInterval(timerInterval);
      processMatchResult();
    }
  }

  // ── RENDER SKOR ───────────────────────────────────────────
  function renderScore(result = null) {
    const userEl = el("score-user");
    const botEl = el("score-bot");
    const displayEl = document.querySelector(".match-score-display");

    if (userEl) userEl.textContent = scoreUser;
    if (botEl) botEl.textContent = scoreBot;

    if (displayEl && result) {
      displayEl.className = "match-score-display " + result;
    }
  }

  // ── FLASH ANIMASI SKOR ────────────────────────────────────
  function flashScore(team) {
    const targetEl = el(team === "user" ? "score-user" : "score-bot");
    if (!targetEl) return;
    targetEl.classList.remove("updated");
    void targetEl.offsetWidth;
    targetEl.classList.add("updated");
  }

  // ── RENDER EVENT GOL ──────────────────────────────────────
  function renderGoalEvents() {
    const container = el("goal-events");
    if (!container) return;

    if (goalEvents.length === 0) {
      container.innerHTML = "";
      return;
    }

    // Urutkan berdasarkan menit
    const sorted = [...goalEvents].sort((a, b) => a.minute - b.minute);

    container.innerHTML = sorted
      .map((g) => {
        const isUser = g.team === "user";
        const minLabel =
          g.minute > 90 ? `90+${g.minute - 90}'` : `${g.minute}'`;
        const icon = isUser ? "⚽" : "🥅";
        const color = isUser ? "var(--success)" : "var(--danger)";
        const align = isUser ? "flex-start" : "flex-end";
        const textAlign = isUser ? "left" : "right";

        return `
        <div class="goal-event" style="justify-content:${align};text-align:${textAlign};">
          <span class="goal-event-inner" style="color:${color};">
            ${isUser ? `${icon} <strong>${g.playerName}</strong> ${minLabel}` : `${minLabel} <strong>${g.playerName}</strong> ${icon}`}
          </span>
        </div>`;
      })
      .join("");
  }

  // ── PROCESS RESULT ────────────────────────────────────────
  async function processMatchResult() {
    if (el("match-status"))
      el("match-status").textContent = "Menghitung hasil...";

    try {
      const data = await postData(apiUrl("/api/match.php"), {
        score_user: scoreUser,
        score_bot: scoreBot,
      });

      if (!data.success) {
        showToast(data.message || "Gagal memproses match.", "error");
        startCooldown();
        return;
      }

      // Render skor final dengan warna
      renderScore(data.result);

      if (el("bot-team-name"))
        el("bot-team-name").textContent = data.bot_team_name;
      if (el("bot-power"))
        el("bot-power").textContent = formatNumber(data.bot_power);
      if (el("user-power"))
        el("user-power").textContent = formatNumber(data.user_power);

      showResult(data);
      updateNavCurrency(data.inventory);
      updateSidebar(data.inventory, data.user_power);
      updateIncomeDisplay(data.base_income);
      appendMatchLog(data);
      startCooldown();
    } catch (e) {
      showToast("Koneksi bermasalah.", "error");
      startCooldown();
    }
  }

  // ── SHOW RESULT BANNER ────────────────────────────────────
  function showResult(data) {
    const isWin = data.result === "win";
    const bannerEl = el("result-banner");
    const rewardsEl = el("match-rewards");

    if (bannerEl) {
      bannerEl.className = "match-result-banner " + (isWin ? "win" : "lose");
      bannerEl.textContent = isWin
        ? `🏆 MENANG! (${scoreUser} - ${scoreBot})`
        : `💔 KALAH (${scoreUser} - ${scoreBot})`;
    }

    if (rewardsEl) rewardsEl.style.display = "flex";
    if (el("reward-euro"))
      el("reward-euro").textContent =
        "+" + formatNumber(data.euro_earned) + " 💶";
    if (el("reward-gems"))
      el("reward-gems").textContent = "+" + data.gems_earned + " 💎";
    if (el("reward-tr"))
      el("reward-tr").textContent = "+" + data.tr_token_earned + " 🔧TR";
    if (el("reward-wt"))
      el("reward-wt").textContent = "+" + data.win_token_earned + " 🏆WT";
  }

  // ── UPDATE INCOME DISPLAY ─────────────────────────────────
  function updateIncomeDisplay(baseIncome) {
    const incomeEl = el("base-income-display");
    if (incomeEl && baseIncome !== undefined) {
      incomeEl.textContent = formatNumber(baseIncome);
    }
  }

  // ── COOLDOWN ──────────────────────────────────────────────
  function startCooldown() {
    cooldownSecs = 10;
    el("match-timer")?.classList.add("cooldown");
    if (el("match-timer")) el("match-timer").textContent = cooldownSecs;
    if (el("match-status"))
      el("match-status").textContent = "Cooldown sebelum match berikutnya...";

    clearInterval(timerInterval);
    timerInterval = setInterval(tickCooldown, 1000);
  }

  function tickCooldown() {
    cooldownSecs--;
    if (el("match-timer")) el("match-timer").textContent = cooldownSecs;
    if (cooldownSecs <= 0) {
      clearInterval(timerInterval);
      startMatch();
    }
  }

  // ── SIDEBAR ───────────────────────────────────────────────
  function updateSidebar(inventory, userPower) {
    const set = (id, val) => {
      const e = el(id);
      if (e) e.textContent = val;
    };
    set("sidebar-euro", formatNumber(parseInt(inventory.euro || 0)));
    set("sidebar-gems", inventory.gems || 0);
    set("sidebar-power", formatNumber(userPower || 0));
  }

  // ── MATCH LOG ─────────────────────────────────────────────
  function appendMatchLog(data) {
    const list = el("match-log-list");
    if (!list) return;
    const empty = list.querySelector(".empty-state");
    if (empty) empty.remove();

    const item = document.createElement("div");
    item.className = "match-log-item";
    item.innerHTML = `
      <span class="match-log-result ${data.result}">
        ${data.result.toUpperCase()}
      </span>
      <span class="match-log-vs">
        vs ${data.bot_team_name}
        <span style="color:var(--text-muted);font-size:0.7rem;">
          (${scoreUser}-${scoreBot})
        </span>
      </span>
      <span class="match-log-earn">+${formatNumber(data.euro_earned)}💶</span>`;

    list.insertBefore(item, list.firstChild);
    const items = list.querySelectorAll(".match-log-item");
    if (items.length > 5) items[items.length - 1].remove();
  }

  return { init };
})();

document.addEventListener("DOMContentLoaded", () => Match.init());
