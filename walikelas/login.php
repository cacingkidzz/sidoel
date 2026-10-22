<?php
require_once __DIR__ . '/_init.php';

if (wk_logged_in()) {
    header('Location: index.php');
    exit;
}

$profil = wk_profil($koneksi);
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $err = 'Username/NIP dan password wajib diisi.';
    } else {
        // Login memakai akun level "Guru": username, NIP, atau NUPTK.
        $stmt = $koneksi->prepare("SELECT u.id_user, u.username, u.password, u.status_aktif, u.id_fk,
                                          g.nama_guru, g.nip, g.nuptk
                                   FROM dt_user u
                                   LEFT JOIN dt_guru g ON g.id_guru = u.id_fk
                                   WHERE u.level_user = 'Guru'
                                     AND (u.username = ? OR g.nip = ? OR g.nuptk = ?)
                                   ORDER BY (u.username = ?) DESC, (u.status_aktif = 'Y') DESC, u.id_user DESC
                                   LIMIT 1");
        $stmt->bind_param('ssss', $identifier, $identifier, $identifier, $identifier);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$u) {
            $err = 'Akun wali kelas tidak ditemukan. Hubungi admin.';
        } elseif ($u['status_aktif'] !== 'Y') {
            $err = 'Akun belum diaktifkan / sudah dinonaktifkan. Hubungi admin.';
        } else {
            $stored = (string)$u['password'];
            if (!verifikasi_password($password, $stored)) {
                $err = 'Password salah.';
            } else {
                upgrade_password_jika_perlu($koneksi, (int)$u['id_user'], $password, $stored);
                $_SESSION['walikelas'] = [
                    'id_user'  => (int)$u['id_user'],
                    'username' => $u['username'],
                    'id_guru'  => (int)($u['id_fk'] ?? 0),
                    'nama_guru' => $u['nama_guru'] ?? '',
                ];
                header('Location: index.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Wali Kelas - <?= wk_esc($profil['nama_sekolah']) ?></title>
    <link rel="icon" href="<?= wk_esc(wk_logo_url($profil)) ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center;
               background: linear-gradient(135deg, #0f766e, #2dd4bf); padding: 18px; }
        .login-card { max-width: 390px; width: 100%; border: 0; border-radius: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,.18); }
        .logo-login { width: 66px; height: 66px; object-fit: contain; background: #fff; border-radius: 50%; padding: 5px; }
    </style>
</head>
<body>
    <div class="w-100" style="max-width:400px;">
        <div class="text-center text-white mb-3">
            <img src="<?= wk_esc(wk_logo_url($profil)) ?>" alt="Logo" style="max-width:86px;" class="mb-2">
            <h5 class="mb-0 fw-bold"><?= wk_esc($profil['nama_sekolah']) ?></h5>
            <small class="text-white-50">Portal Wali Kelas</small>
        </div>
        <div class="card login-card">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 text-center">Masuk Wali Kelas</h6>
                <?php if ($err): ?>
                    <div class="alert alert-danger py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i><?= wk_esc($err) ?></div>
                <?php endif; ?>
                <form method="post" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Username / NIP</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="Username akun atau NIP guru" required autofocus
                                   value="<?= wk_esc($_POST['username'] ?? '') ?>">
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
                    <button type="submit" class="btn btn-success w-100 fw-semibold" style="background:#0f766e;border-color:#0f766e;">
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
