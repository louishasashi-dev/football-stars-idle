<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireLogin();

$userId = getCurrentUserId();
$tournamentId = (int)($_POST['tournament_id'] ?? 0);
$pdo = getDB();

// Cek turnamen
$stmt = $pdo->prepare('SELECT * FROM tournaments WHERE id = ? AND status = "open" AND creator_id = ?');
$stmt->execute([$tournamentId, $userId]);
$tournament = $stmt->fetch();

if (!$tournament) {
    echo json_encode(['success' => false, 'message' => 'Turnamen tidak ditemukan atau Anda bukan pembuat.']);
    exit;
}

// Cek peserta
$participantStmt = $pdo->prepare('SELECT user_id FROM tournament_participants WHERE tournament_id = ?');
$participantStmt->execute([$tournamentId]);
$participants = $participantStmt->fetchAll(PDO::FETCH_COLUMN);

if (count($participants) < 2) {
    echo json_encode(['success' => false, 'message' => 'Minimal 2 peserta untuk memulai.']);
    exit;
}

// Acak urutan
shuffle($participants);

$pdo->beginTransaction();

try {
    // Update status
    $pdo->prepare('UPDATE tournaments SET status = "active", started_at = NOW() WHERE id = ?')
        ->execute([$tournamentId]);

    // ── BUAT & JALANKAN BRACKET BABAK DEMI BABAK ────────────────
    // PENTING: babak harus dijalankan satu-satu secara berurutan, karena
    // peserta babak berikutnya baru diketahui SETELAH babak sebelumnya
    // selesai dimainkan (tidak bisa di-generate semua sekaligus di awal).
    //
    // Penomoran round mengikuti konvensi tampilan bracket yang sudah ada
    // (tournament_detail.php pakai krsort, jadi round terbesar = babak
    // paling awal, round 1 = FINAL).
    $totalRounds = (int)ceil(log(count($participants), 2));
    $round = $totalRounds; // babak pertama = round terbesar

    $currentRoundPlayers = $participants;
    $semifinalLosers = []; // dikumpulkan untuk match perebutan juara 3

    while (count($currentRoundPlayers) > 1) {
        $isSemifinal = (count($currentRoundPlayers) == 4);
        $matchCount = (int)floor(count($currentRoundPlayers) / 2);
        $nextRoundPlayers = [];

        for ($i = 0; $i < $matchCount; $i++) {
            $user1 = $currentRoundPlayers[$i * 2];
            $user2 = $currentRoundPlayers[$i * 2 + 1] ?? null;

            // Bye (jumlah peserta ganjil di babak ini) - lolos otomatis
            if (!$user2) {
                $nextRoundPlayers[] = $user1;
                continue;
            }

            $matchId = $pdo->prepare('
                INSERT INTO tournament_matches (tournament_id, round, match_order, user1_id, user2_id, status)
                VALUES (?, ?, ?, ?, ?, "pending")
            ');
            $matchId->execute([$tournamentId, $round, $i, $user1, $user2]);
            $newMatchId = (int)$pdo->lastInsertId();

            // Jalankan match ini SEKARANG juga supaya pemenangnya bisa
            // dipakai untuk mengisi babak berikutnya.
            $result = playMatch($pdo, $newMatchId, $user1, $user2);
            $nextRoundPlayers[] = $result['winner_id'];

            if ($isSemifinal) {
                $loserId = ($result['winner_id'] == $user1) ? $user2 : $user1;
                $semifinalLosers[] = $loserId;
            }
        }

        $currentRoundPlayers = $nextRoundPlayers;
        $round--;
    }

    $championId = $currentRoundPlayers[0] ?? null;

    // ── MATCH PEREBUTAN JUARA 3 ──────────────────────────────────
    // round = 0 dipakai sebagai penanda khusus match 3rd-place
    // (final selalu round = 1, semifinal round = 2, dst — jadi 0 unik
    // dan tidak akan pernah bentrok dengan penomoran babak normal).
    $thirdPlaceId = null;
    $fourthPlaceId = null;

    if (count($semifinalLosers) == 2) {
        $matchId = $pdo->prepare('
            INSERT INTO tournament_matches (tournament_id, round, match_order, user1_id, user2_id, status)
            VALUES (?, 0, 0, ?, ?, "pending")
        ');
        $matchId->execute([$tournamentId, $semifinalLosers[0], $semifinalLosers[1]]);
        $newMatchId = (int)$pdo->lastInsertId();

        $result = playMatch($pdo, $newMatchId, $semifinalLosers[0], $semifinalLosers[1]);
        $thirdPlaceId = $result['winner_id'];
        $fourthPlaceId = ($result['winner_id'] == $semifinalLosers[0])
            ? $semifinalLosers[1]
            : $semifinalLosers[0];
    } elseif (count($semifinalLosers) == 1) {
        // Hanya 4 peserta tapi satu semifinal "bye" (kasus tidak normal,
        // jaga-jaga saja) - kalah semifinal otomatis jadi juara 3.
        $thirdPlaceId = $semifinalLosers[0];
    }

    // Pemenang final (runner-up = yang kalah di final)
    $finalMatchStmt = $pdo->prepare('
        SELECT * FROM tournament_matches
        WHERE tournament_id = ? AND round = 1
        ORDER BY id DESC LIMIT 1
    ');
    $finalMatchStmt->execute([$tournamentId]);
    $finalMatch = $finalMatchStmt->fetch();

    $runnerUpId = null;
    if ($finalMatch && $finalMatch['winner_id']) {
        $runnerUpId = ($finalMatch['winner_id'] == $finalMatch['user1_id'])
            ? $finalMatch['user2_id']
            : $finalMatch['user1_id'];
    }

    // ── TENTUKAN POSISI & HADIAH ─────────────────────────────────
    determineWinners($pdo, $tournamentId, $tournament, $championId, $runnerUpId, $thirdPlaceId, $fourthPlaceId);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Turnamen selesai! Cek klasemen untuk hasil akhir.'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Gagal menjalankan turnamen: ' . $e->getMessage()]);
}

// ── FUNGSI ──────────────────────────────────────────────────

/**
 * Simulasikan satu match, simpan hasilnya, kirim notifikasi.
 * Mengembalikan ['winner_id' => ..., 'score1' => ..., 'score2' => ...]
 */
function playMatch($pdo, $matchId, $user1, $user2) {
    $power1 = getTeamPowerByUserId($pdo, $user1);
    $power2 = getTeamPowerByUserId($pdo, $user2);

    $total = $power1 + $power2;
    $chance1 = $total > 0 ? ($power1 / $total) * 100 : 50;

    $random = rand(1, 100);
    $winnerId = null;
    $score1 = 0;
    $score2 = 0;

    if ($random <= $chance1) {
        $winnerId = $user1;
        $score1 = rand(1, 5);
        $score2 = rand(0, max(0, $score1 - 1));
    } else {
        $winnerId = $user2;
        $score2 = rand(1, 5);
        $score1 = rand(0, max(0, $score2 - 1));
    }

    $pdo->prepare('
        UPDATE tournament_matches
        SET user1_score = ?, user2_score = ?, winner_id = ?, status = "completed", played_at = NOW()
        WHERE id = ?
    ')->execute([$score1, $score2, $winnerId, $matchId]);

    $match = ['user1_id' => $user1, 'user2_id' => $user2, 'id' => $matchId];
    sendMatchNotification($pdo, $match, $score1, $score2, $winnerId);

    return ['winner_id' => $winnerId, 'score1' => $score1, 'score2' => $score2];
}

function getTeamPowerByUserId($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT team_power FROM user_teams WHERE user_id = ?');
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result ? (int)$result['team_power'] : 0;
}

function sendMatchNotification($pdo, $match, $score1, $score2, $winnerId) {
    $users = [$match['user1_id'], $match['user2_id']];
    $isWin = [];
    $isWin[$match['user1_id']] = ($winnerId == $match['user1_id']);
    $isWin[$match['user2_id']] = ($winnerId == $match['user2_id']);

    foreach ($users as $uid) {
        $userScore = $uid == $match['user1_id'] ? $score1 : $score2;
        $opponentScore = $uid == $match['user1_id'] ? $score2 : $score1;
        $resultText = $isWin[$uid] ? 'Menang' : 'Kalah';
        $color = $isWin[$uid] ? '🏆' : '💔';

        $pdo->prepare('
            INSERT INTO inbox (user_id, type, title, message, data)
            VALUES (?, "match", ?, ?, ?)
        ')->execute([
            $uid,
            "⚽ Hasil Match Turnamen",
            "$resultText $color melawan lawan dengan skor $userScore - $opponentScore",
            json_encode(['match_id' => $match['id'], 'result' => $isWin[$uid] ? 'win' : 'lose'])
        ]);
    }
}

/**
 * Tentukan posisi akhir (1, 2, 3, 4, 5, ...) dan hadiah untuk semua peserta.
 *
 * Distribusi gems dari prize_pool turnamen:
 *   Juara 1 = 50% dari prize_pool
 *   Juara 2 = 35% dari prize_pool
 *   Juara 3 = 15% dari prize_pool
 *   Posisi 4 dst = tidak dapat gems, hanya title partisipasi.
 *
 * euro / tr_token / pr_token tetap memakai proporsi yang sama (50/35/15%)
 * dari nilai dasar yang sebelumnya hardcoded untuk juara 1.
 */
function determineWinners($pdo, $tournamentId, $tournament, $championId, $runnerUpId, $thirdPlaceId, $fourthPlaceId = null) {
    $prizePool = (int)($tournament['prize_pool'] ?? 0);

    // Nilai dasar (basis lama, dipakai sebagai basis 100% untuk juara 1)
    $baseEuro = 100000;
    $baseTr   = 50;
    $basePr   = 10;

    $prizes = [
        1 => [
            'euro'  => (int)round($baseEuro * 0.50),
            'gems'  => (int)round($prizePool * 0.50),
            'tr'    => (int)round($baseTr * 0.50),
            'pr'    => (int)round($basePr * 0.50),
            'title' => '🏆 Champion',
        ],
        2 => [
            'euro'  => (int)round($baseEuro * 0.35),
            'gems'  => (int)round($prizePool * 0.35),
            'tr'    => (int)round($baseTr * 0.35),
            'pr'    => (int)round($basePr * 0.35),
            'title' => '🥈 Runner-up',
        ],
        3 => [
            'euro'  => (int)round($baseEuro * 0.15),
            'gems'  => (int)round($prizePool * 0.15),
            'tr'    => (int)round($baseTr * 0.15),
            'pr'    => (int)round($basePr * 0.15),
            'title' => '🥉 Third Place',
        ],
    ];

    $podium = [
        1 => $championId,
        2 => $runnerUpId,
        3 => $thirdPlaceId,
    ];

    $updateStmt = $pdo->prepare('
        UPDATE tournament_participants
        SET position = ?,
            prize_euro = ?, prize_gems = ?, prize_tr_token = ?, prize_pr_token = ?, prize_title = ?
        WHERE tournament_id = ? AND user_id = ?
    ');

    $assignedIds = [];
    foreach ($podium as $pos => $uid) {
        if (!$uid) continue;
        $p = $prizes[$pos];
        $updateStmt->execute([
            $pos, $p['euro'], $p['gems'], $p['tr'], $p['pr'], $p['title'],
            $tournamentId, $uid
        ]);
        $assignedIds[] = $uid;
    }

    // ── POSISI 4: kalah di match perebutan juara 3 ───────────────
    // Ditetapkan eksplisit di sini (bukan ikut sorting "remaining" di
    // bawah), karena match 3rd-place pakai round=0 yang kalau ikut
    // diurutkan bersama match babak normal akan salah dianggap
    // "paling dekat ke final" (0 adalah angka terkecil dari semua round).
    if ($fourthPlaceId) {
        $pdo->prepare('
            UPDATE tournament_participants
            SET position = 4,
                prize_euro = 0, prize_gems = 0, prize_tr_token = 0, prize_pr_token = 0,
                prize_title = "🎖️ Participant"
            WHERE tournament_id = ? AND user_id = ?
        ')->execute([$tournamentId, $fourthPlaceId]);
        $assignedIds[] = $fourthPlaceId;
    }

    // ── PESERTA LAIN: posisi berurutan 5, 6, 7, ... ──────────────
    // Diurutkan berdasarkan ronde terjauh yang berhasil dicapai
    // (siapa kalah belakangan = peringkat lebih tinggi), lalu dari situ
    // posisi diberi nomor urut, bukan disamaratakan jadi 99.
    $participantsStmt = $pdo->prepare('SELECT user_id FROM tournament_participants WHERE tournament_id = ?');
    $participantsStmt->execute([$tournamentId]);
    $allParticipants = $participantsStmt->fetchAll(PDO::FETCH_COLUMN);

    $remaining = array_values(array_diff($allParticipants, $assignedIds));

    // Ambil match terakhir tiap peserta sisa untuk menentukan urutan
    // (kalah di babak yang lebih dalam / round lebih kecil = peringkat
    // lebih baik, karena round=1 itu final, round besar itu babak awal).
    // round > 0 supaya match 3rd-place (round=0) tidak ikut campur,
    // karena pesertanya sudah pasti masuk podium/posisi-4 di atas.
    $rank = [];
    foreach ($remaining as $uid) {
        $stmt = $pdo->prepare('
            SELECT MIN(round) AS best_round
            FROM tournament_matches
            WHERE tournament_id = ? AND (user1_id = ? OR user2_id = ?) AND status = "completed" AND round > 0
        ');
        $stmt->execute([$tournamentId, $uid, $uid]);
        $row = $stmt->fetch();
        // Kalau tidak pernah main (harusnya tidak terjadi), taruh paling akhir
        $rank[$uid] = $row && $row['best_round'] !== null ? (int)$row['best_round'] : 9999;
    }
    // Round lebih kecil (lebih dekat ke final) = peringkat lebih baik = posisi lebih kecil
    asort($rank);
    $orderedRemaining = array_keys($rank);

    $pos = 5;
    foreach ($orderedRemaining as $uid) {
        $pdo->prepare('
            UPDATE tournament_participants
            SET position = ?,
                prize_euro = 0, prize_gems = 0, prize_tr_token = 0, prize_pr_token = 0,
                prize_title = "🎖️ Participant"
            WHERE tournament_id = ? AND user_id = ?
        ')->execute([$pos, $tournamentId, $uid]);
        $pos++;
    }

    // Update status turnamen
    $pdo->prepare('UPDATE tournaments SET status = "completed", completed_at = NOW() WHERE id = ?')
        ->execute([$tournamentId]);

    // Kirim notifikasi ke semua peserta
    foreach ($allParticipants as $uid) {
        $stmt = $pdo->prepare('SELECT * FROM tournament_participants WHERE tournament_id = ? AND user_id = ?');
        $stmt->execute([$tournamentId, $uid]);
        $data = $stmt->fetch();

        $pdo->prepare('
            INSERT INTO inbox (user_id, type, title, message, data)
            VALUES (?, "tournament", ?, ?, ?)
        ')->execute([
            $uid,
            "🏆 Turnamen Selesai!",
            "Turnamen telah selesai! Posisi: " . ($data['position'] ?? '-') . ". Klik untuk klaim hadiah.",
            json_encode(['tournament_id' => $tournamentId, 'position' => $data['position'] ?? null])
        ]);
    }
}