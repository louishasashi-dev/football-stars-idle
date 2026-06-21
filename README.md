⚽ Football Stars Idle
Football Stars Idle adalah game manajemen sepak bola berbasis web dengan sistem idle (otomatis) yang terinspirasi dari game-game gacha dan manajemen tim sepak bola. Kembangkan timmu, kumpulkan pemain bintang dari berbagai kasta, ikuti turnamen, dan raih gelar juara!

📖 Daftar Isi
Fitur Utama

Mekanisme Game

Teknologi & Tools

Struktur Proyek

Instalasi

Kontribusi

🚀 Fitur Utama
Fitur	Deskripsi
Match Arena	Pertandingan otomatis melawan bot dengan sistem power dan income
Gacha System	Spin pemain dengan sistem pity (10 spin = jaminan tier tinggi)
Lineup & Formasi	Atur starting 11 dan cadangan dengan 7 formasi berbeda
Marketplace	Jual-beli pemain antar user dengan sistem listing
Tournament	Sistem turnamen knockout dengan bracket dan hadiah
Koleksi Pemain	Lihat semua pemain yang dimiliki dengan filter tier
Upgrade System	Tingkatkan power tim, income, dan level pemain
Event Spesial	Event terbatas seperti "Celestial Strikers" dengan pemain eksklusif
🎮 Mekanisme Game
Match Arena ⚽
Match berjalan otomatis (idle) setiap 60 detik

Hasil ditentukan oleh perbandingan power tim user vs bot

Setiap match menghasilkan Euro, Gems, TR Token, dan Win Token

Cooldown 10 detik sebelum match berikutnya

Gacha System 🎰
1x Spin = 10 Gems, 10x Spin = 100 Gems (diskon 50% untuk pertama kali)

Drop Rate:

Amateur: 45%

Trained: 20%

Talented: 10%

Semi-Pro: 10%

Pro: 7%

Expert: 5%

Legendary: 3%

ASEAN Legend: 0.5%

Celestial: 0.3% (event khusus)

Pity System: Setiap 10 spin tanpa mendapatkan Expert/Legendary, chance meningkat drastis (65% Expert, 35% Legendary)

Lineup & Formasi 📋
7 formasi tersedia: 4-3-3, 4-4-2, 4-2-3-1, 3-5-2, 3-4-3, 5-3-2, 5-4-1

Starting 11 + 8 cadangan

Auto-fill berdasarkan rating tertinggi

Power tim dihitung dari statistik pemain + skill + upgrade level

Tournament 🏆
User dapat membuat turnamen sendiri (max 8/16/32 peserta)

Sistem knockout (gugur) dengan bracket otomatis

Hasil ditentukan oleh persentase power tim

Hadiah: Euro, Gems, TR/PR Token, dan pemain eksklusif

Notifikasi hasil masuk ke Inbox

Upgrade System ⬆️
Power Team: Tingkatkan dengan Euro atau Win Token

Income: Tingkatkan pendapatan per match

Player Level: Naikkan level pemain dengan TR Token (max level 20)

Player Tier: Naikkan kasta pemain dengan PR Token

🛠️ Teknologi & Tools
Tech Stack
Komponen	Teknologi
Backend	PHP 8.1 (Native, tanpa framework)
Database	MySQL 8.0
Frontend	HTML5, CSS3, JavaScript (Vanilla)
Server	Apache (Laragon)
Struktur Halaman
Halaman	Fungsi
dashboard.php	Match arena, upgrade power & income
shop.php	Gacha system, beli PR Token, tukar pemain
lineup.php	Atur formasi dan starting 11
card_collection.php	Lihat koleksi pemain + modal detail
market.php	Jual-beli pemain antar user
tournaments.php	Daftar turnamen, buat/join turnamen
inbox.php	Notifikasi hasil turnamen dan match
add_player.php	Tambah pemain custom (Amateur/Semi-Pro)
profile.php	Profil user dan statistik game
API Endpoints (Folder api/)
API	Fungsi
gacha.php	Proses spin gacha dengan drop rate & pity
match_start.php	Mulai match, pilih bot, generate skor prediksi
match.php	Proses hasil match, update inventory
lineup.php	Get & save lineup (starting/bench)
shop.php	Beli GOAT, PR Token, exchange player, upgrade tier
tournament_*.php	Create, join, start, claim tournament
inbox.php	Ambil notifikasi user
upgrade.php	Upgrade power & income
get_player.php	Detail pemain untuk modal
market.php	Listing & beli pemain dari market
JavaScript Engine (Folder assets/js/)
File	Fungsi
main.js	Utility: fetch, toast, format number, modal helper
gacha.js	Engine gacha: spin, reveal overlay, pity tracker, history
match.js	Engine match: timer, goal events, score, result
lineup.js	Engine lineup: render field, bench, drag & drop
upgrade.js	Handler upgrade power & income
tournament.js	Engine tournament: join, start, claim
🤖 AI Tools yang Digunakan
AI Tool	Peran
ChatGPT	Ide fitur, brainstorming mekanisme game, desain sistem
DeepSeek	Pembuatan query SQL, kode PHP/JS/CSS menengah, debugging
Claude AI	Kode kompleks, pengembangan fitur besar, arsitektur sistem
Gemini	Generate gambar pemain "Celestial Strikers"
💻 Software & Aplikasi
Software	Penggunaan
VS Code	Editor kode utama
Laragon	Server lokal (Apache, MySQL, PHP)
Excel	Data pemain, perhitungan statistik
Word	Dokumentasi, catatan pengembangan
Notepad	Catatan cepat, temporary code
Canva	Desain card pemain "Celestial Strikers"
📂 Struktur Proyek
text
football-stars-idle/
├── api/               # Backend endpoints (PHP)
├── assets/
│   ├── css/           # Styling (main, card, shop, lineup, dashboard)
│   ├── images/        # Gambar pemain, banner, icons
│   └── js/            # JavaScript engine (gacha, match, lineup, dll)
├── auth/              # Login, register, session handling
├── config/            # Database & app configuration
├── database/          # SQL dump & migrations
├── includes/          # Header, footer, functions
├── pages/             # Semua halaman frontend
├── .htaccess          # Apache rewrite rules
└── index.php          # Entry point (redirect ke dashboard)
🔧 Instalasi
Prasyarat
PHP 8.1+

MySQL 8.0+

Apache (Laragon/XAMPP/WAMP)

Composer (opsional)

Langkah Instalasi
bash
# 1. Clone repository
git clone https://github.com/louishasashi-dev/football-stars-idle.git

# 2. Import database
# - Buka phpMyAdmin
# - Buat database baru: football_stars_db
# - Import file database/football_stars_db.sql

# 3. Konfigurasi
# - Edit config/db.php: sesuaikan host, username, password
# - Edit config/app.php: sesuaikan BASE_URL

# 4. Jalankan di browser
# http://localhost/football-stars-idle/
Konfigurasi Database
php
// config/db.php
return [
    'host' => 'localhost',
    'dbname' => 'football_stars_db',
    'username' => 'root',
    'password' => '',
];
🤝 Kontribusi
Fork repository

Buat branch fitur (git checkout -b fitur-baru)

Commit perubahan (git commit -m 'Tambah fitur X')

Push ke branch (git push origin fitur-baru)

Buat Pull Request

📜 Lisensi
MIT

🙏 Kredit
Dikembangkan oleh louishasashi-dev dengan bantuan AI Tools:

ChatGPT (OpenAI)

DeepSeek

Claude AI (Anthropic)

Gemini (Google)
