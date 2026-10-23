<?php
require_once __DIR__ . '/_init.php';

if (portal_logged_in()) {
    header('Location: index.php');
    exit;
}

$profil = portal_profil($koneksi);
$err = '';
$info = !empty($_GET['logout']) ? 'Anda telah keluar dari portal.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = portal_attempt_login($koneksi, (string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
    if ($res['ok']) {
        portal_set_user($res['user']);
        header('Location: index.php');
        exit;
    }
    $err = $res['error'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Portal Guru - <?= portal_esc($profil['nama_sekolah']) ?></title>
    <link rel="icon" href="<?= portal_esc(portal_logo_url($profil)) ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center;
               background: linear-gradient(135deg, #5b21b6, #0f766e); padding: 18px; }
        .login-card { max-width: 400px; width: 100%; border: 0; border-radius: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,.2); }
        .login-logo { width: 72px; height: 72px; object-fit: contain; background: #fff; border-radius: 50%; padding: 6px; }
    </style>
</head>
<body>
    <div class="w-100" style="max-width:410px;">
        <div class="text-center text-white mb-3">
            <img src="<?= portal_esc(portal_logo_url($profil)) ?>" alt="Logo" class="login-logo mb-2">
            <h5 class="mb-0 fw-bold"><?= portal_esc($profil['nama_sekolah']) ?></h5>
            <small class="text-white-50">Portal Guru &amp; Wali Kelas</small>
        </div>
        <div class="card login-card">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-1 text-center">Masuk Portal</h6>
                <p class="text-muted text-center small mb-3">Satu akun untuk Portal Guru dan Portal Wali Kelas.</p>
                <?php if ($info): ?>
                    <div class="alert alert-success py-2 small mb-3"><i class="bi bi-check-circle me-1"></i><?= portal_esc($info) ?></div>
                <?php endif; ?>
                <?php if ($err): ?>
                    <div class="alert alert-danger py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i><?= portal_esc($err) ?></div>
                <?php endif; ?>
                <form method="post" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username / NIP / NUPTK</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="Username, NIP, atau NUPTK" required autofocus
                                   value="<?= portal_esc($_POST['username'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="password" id="password" class="form-control" placeholder="password" required>
                            <button type="button" class="btn btn-outline-secondary" id="togglePass"><i class="bi bi-eye" id="eye"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-semibold" style="background:#5b21b6;border-color:#5b21b6;">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Masuk
                    </button>
                </form>
                <div class="text-center mt-3 small">
                    <a href="../admin/login.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left me-1"></i>Login Admin</a>
                </div>
            </div>
        </div>
        <p class="text-center text-white-50 small mt-3 mb-0">&copy; <?= date('Y') ?> SiDoel</p>
    </div>
    <script>
        document.getElementById('togglePass').addEventListener('click', function () {
            var p = document.getElementById('password'), e = document.getElementById('eye');
            p.type = p.type === 'password' ? 'text' : 'password';
            e.className = p.type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    </script>
</body>
</html>
