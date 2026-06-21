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
    <title>Login — Football Stars Idle</title>
    <link rel="stylesheet" href="<?= url('/assets/css/main.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/auth.css') ?>">
</head>

<body>
    <div class="auth-wrapper">
        <div class="auth-box">
            <div class="auth-logo">
                <span class="auth-logo-icon">⚽</span>
                <div class="auth-logo-title">Football Stars Idle</div>
                <div class="auth-logo-sub">Manage your dream team</div>
            </div>

            <div class="auth-title">Masuk ke Akun</div>
            <div id="auth-message"></div>

            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" id="username" class="form-control" placeholder="Masukkan username">
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" id="password" class="form-control" placeholder="Masukkan password">
            </div>
            <button class="btn btn-primary btn-full btn-lg" id="btn-login">Masuk</button>

            <div class="auth-footer">
                Belum punya akun?
                <a href="<?= url('/auth/register_page.php') ?>">Daftar sekarang</a>
            </div>
        </div>
    </div>

    <script>
    const BASE_URL = '<?= BASE_URL ?>';

    document.getElementById('btn-login').addEventListener('click', async function() {
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        const msgEl = document.getElementById('auth-message');

        if (!username || !password) {
            msgEl.innerHTML = '<div class="alert alert-danger">Username dan password wajib diisi.</div>';
            return;
        }

        this.disabled = true;
        this.textContent = 'Memproses...';

        const fd = new FormData();
        fd.append('username', username);
        fd.append('password', password);

        try {
            const res = await fetch(BASE_URL + '/auth/login.php', {
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
                this.textContent = 'Masuk';
                return;
            }

            if (data.success) {
                msgEl.innerHTML = '<div class="alert alert-success">Login berhasil! Mengalihkan...</div>';
                setTimeout(() => window.location.href = data.redirect, 800);
            } else {
                msgEl.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                this.disabled = false;
                this.textContent = 'Masuk';
            }
        } catch (e) {
            msgEl.innerHTML = '<div class="alert alert-danger">Terjadi kesalahan. Coba lagi.</div>';
            this.disabled = false;
            this.textContent = 'Masuk';
        }
    });

    document.getElementById('password').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') document.getElementById('btn-login').click();
    });
    </script>
</body>

</html>