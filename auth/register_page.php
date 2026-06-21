<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/session.php';
if (isLoggedIn()) {
    header('Location: ' . url('/pages/dashboard.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar — Football Stars Idle</title>
    <link rel="stylesheet" href="<?= url('/assets/css/main.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/auth.css') ?>">
</head>

<body>
    <div class="auth-wrapper">
        <div class="auth-box">
            <div class="auth-logo">
                <span class="auth-logo-icon">⚽</span>
                <div class="auth-logo-title">Football Stars Idle</div>
                <div class="auth-logo-sub">Buat akun baru</div>
            </div>

            <div class="auth-title">Daftar Akun</div>
            <div id="auth-message"></div>

            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" id="username" class="form-control" placeholder="3-50 karakter, huruf/angka/_">
            </div>
            <div class="form-group">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" id="fullname" class="form-control" placeholder="Nama lengkap Anda">
            </div>
            <div class="form-group">
                <label class="form-label">Negara</label>
                <input type="text" id="country" class="form-control" placeholder="Contoh: Indonesia">
            </div>
            <div class="form-group">
                <label class="form-label">Nama Tim</label> <!-- ← TAMBAHKAN INI -->
                <input type="text" id="team_name" class="form-control" placeholder="Nama tim Anda (3-50 karakter)">
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" id="password" class="form-control" placeholder="Minimal 6 karakter">
            </div>
            <div class="form-group">
                <label class="form-label">Konfirmasi Password</label>
                <input type="password" id="confirm_password" class="form-control" placeholder="Ulangi password">
            </div>

            <button class="btn btn-success btn-full btn-lg" id="btn-register">Buat Akun</button>

            <div class="auth-footer">
                Sudah punya akun?
                <a href="<?= url('/auth/login_page.php') ?>">Masuk di sini</a>
            </div>
        </div>
    </div>

    <script>
    const BASE_URL = '<?= BASE_URL ?>';

    document.getElementById('btn-register').addEventListener('click', async function() {
        const msgEl = document.getElementById('auth-message');
        const fd = new FormData();
        fd.append('username', document.getElementById('username').value.trim());
        fd.append('fullname', document.getElementById('fullname').value.trim());
        fd.append('country', document.getElementById('country').value.trim());
        fd.append('team_name', document.getElementById('team_name').value.trim()); // ← TAMBAHKAN INI
        fd.append('password', document.getElementById('password').value);
        fd.append('confirm_password', document.getElementById('confirm_password').value);

        this.disabled = true;
        this.textContent = 'Membuat akun...';

        try {
            const res = await fetch(BASE_URL + '/auth/register.php', {
                method: 'POST',
                body: fd
            });
            const text = await res.text();

            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Response bukan JSON:', text);
                msgEl.innerHTML = '<div class="alert alert-danger">Server error. Cek console.</div>';
                this.disabled = false;
                this.textContent = 'Buat Akun';
                return;
            }

            if (data.success) {
                msgEl.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                setTimeout(() => window.location.href = BASE_URL + '/auth/login_page.php', 1500);
            } else {
                msgEl.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                this.disabled = false;
                this.textContent = 'Buat Akun';
            }
        } catch (e) {
            msgEl.innerHTML = '<div class="alert alert-danger">Terjadi kesalahan. Coba lagi.</div>';
            this.disabled = false;
            this.textContent = 'Buat Akun';
        }
    });
    </script>
</body>

</html>