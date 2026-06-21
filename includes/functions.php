<?php
if (function_exists('calculateTeamPower')) return;
function calculateTeamPower(int $teamId): int {
    $pdo = getDB();

    // Cek apakah ada lineup aktif
    $lineupStmt = $pdo->prepare('SELECT id FROM lineups WHERE team_id = ?');
    $lineupStmt->execute([$teamId]);
    $lineup = $lineupStmt->fetch();

    if ($lineup) {
        // Ambil hanya starting 11
        $stmt = $pdo->prepare('
            SELECT fp.id, fp.offence, fp.defence, fp.teamwork,
                   s.effect_type, s.effect_value
            FROM lineup_players lp
            JOIN football_players fp ON fp.id = lp.player_id
            LEFT JOIN player_skills ps ON ps.player_id = fp.id
            LEFT JOIN skills s ON s.id = ps.skill_id
            WHERE lp.lineup_id = ? AND lp.slot_type = "starting"
        ');
        $stmt->execute([$lineup['id']]);
    } else {
        // Fallback: semua pemain di tim
        $stmt = $pdo->prepare('
            SELECT fp.id, fp.offence, fp.defence, fp.teamwork,
                   s.effect_type, s.effect_value
            FROM team_members tm
            JOIN football_players fp ON tm.player_id = fp.id
            LEFT JOIN player_skills ps ON ps.player_id = fp.id
            LEFT JOIN skills s ON s.id = ps.skill_id
            WHERE tm.team_id = ?
        ');
        $stmt->execute([$teamId]);
    }

    $rows = $stmt->fetchAll();

    $playerStats = [];
    foreach ($rows as $row) {
        $pid = $row['id'];
        if (!isset($playerStats[$pid])) {
            $playerStats[$pid] = [
                'offence'     => (float)$row['offence'],
                'defence'     => (float)$row['defence'],
                'teamwork'    => (float)$row['teamwork'],
                'power_bonus' => 0.0,
            ];
        }
        if (!$row['effect_type']) continue;
        switch ($row['effect_type']) {
            case 'offence':
                $playerStats[$pid]['offence']  *= (1 + $row['effect_value'] / 100); break;
            case 'defence':
                $playerStats[$pid]['defence']  *= (1 + $row['effect_value'] / 100); break;
            case 'teamwork':
                $playerStats[$pid]['teamwork'] *= (1 + $row['effect_value'] / 100); break;
            case 'power':
                $playerStats[$pid]['power_bonus'] += $row['effect_value']; break;
        }
    }

    $basePower = 0.0;
    foreach ($playerStats as $p) {
        $stat = $p['offence'] + $p['defence'] + $p['teamwork'];
        $stat *= (1 + $p['power_bonus'] / 100);
        $basePower += $stat;
    }

    // Bonus upgrade level power
    $teamStmt = $pdo->prepare('
        SELECT power_level_euro, power_level_wintoken
        FROM user_teams WHERE id = ?
    ');
    $teamStmt->execute([$teamId]);
    $teamData = $teamStmt->fetch();

    $euroPct  = ($teamData['power_level_euro']     - 1) * 5;
    $wtPct    = ($teamData['power_level_wintoken'] - 1) * 8;
    $totalPct = $euroPct + $wtPct;

    return (int) round($basePower * (1 + $totalPct / 100));
}
function calculateBaseIncome(int $incomeEuroLevel, int $incomeWinTokenLevel): int {
    // Base 100, tiap level euro +100, tiap level wintoken +150
    return 100
        + (($incomeEuroLevel     - 1) * 100)
        + (($incomeWinTokenLevel - 1) * 150);
}

function formatNumber(int $number): string {
    if ($number >= 1_000_000_000) return round($number / 1_000_000_000, 1) . 'B';
    if ($number >= 1_000_000)     return round($number / 1_000_000, 1)     . 'M';
    if ($number >= 1_000)         return round($number / 1_000, 1)         . 'K';
    return (string) $number;
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function getInventory(int $userId): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT * FROM inventory_players WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: [];
}

function getUserTeam(int $userId): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT * FROM user_teams WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: [];
}

function getTeamPlayers(int $teamId): array {
    $pdo = getDB();
    
    // Optimasi: ambil hanya data yang diperlukan, join lebih efisien
    $stmt = $pdo->prepare('
        SELECT 
            fp.id, fp.player_name, fp.country, fp.position, 
            fp.rating, fp.offence, fp.defence, fp.teamwork,
            fp.card_image, fp.current_level, fp.tier_id,
            fpt.tier_name, fpt.card_color,
            fpt.card_color_secondary, fpt.card_color_tertiary,
            fpt.upgrade_base_cost, fpt.upgrade_stat_per_level
        FROM team_members tm
        JOIN football_players fp ON tm.player_id = fp.id
        JOIN f_player_tier fpt ON fp.tier_id = fpt.id
        WHERE tm.team_id = ?
        ORDER BY fp.rating DESC
    ');
    $stmt->execute([$teamId]);
    return $stmt->fetchAll();
}

function getPlayerSkills(int $playerId): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare('
        SELECT s.* FROM player_skills ps
        JOIN skills s ON s.id = ps.skill_id
        WHERE ps.player_id = ?
    ');
    $stmt->execute([$playerId]);
    return $stmt->fetchAll();
}

function isExclusivePlayerAvailable(int $playerId): bool {
    $pdo  = getDB();
    $stmt = $pdo->prepare('
        SELECT id FROM football_players
        WHERE id = ? AND is_exclusive = 1 AND owner_id IS NOT NULL
    ');
    $stmt->execute([$playerId]);
    return $stmt->fetch() === false;
}

/**
 * Hitung biaya upgrade untuk Euro (naik 20% per level)
 */
function upgradeEuroCost(int $level): int {
    $base = 100;
    for ($i = 1; $i < $level; $i++) {
        $base = (int)($base * 1.2);
    }
    return max($base, 100);
}

/**
 * Hitung biaya upgrade untuk Win Token (naik 2 per level)
 */
function upgradeWinTokenCost(int $level): int {
    $base = 1;
    for ($i = 1; $i < $level; $i++) {
        $base = $base + 2;
    }
    return max($base, 1);
}

/**
 * Hitung biaya upgrade player berdasarkan base cost dan level saat ini
 */
function playerUpgradeCost(int $baseCost, int $currentLevel): int {
    // Formula: baseCost * 1.5^(level-1)
    return (int) round($baseCost * pow(1.5, $currentLevel - 1));
}