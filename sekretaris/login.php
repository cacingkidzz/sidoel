<?php
require_once __DIR__ . '/_init.php';

if (sek_logged_in()) {
    header('Location: index.php');
    exit;
}

$profil = sek_profil($koneksi);
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $err = 'NIS/username dan password wajib diisi.';
    } else {
        // Login bisa pakai NIS siswa (nipd/nisn) ATAU username akun.
        $stmt = $koneksi->prepare("SELECT sk.*, s.nama AS nama_siswa, k.nama_kelas, k.tingkat
                                   FROM dt_sekretaris sk
                                   LEFT JOIN dt_siswa s ON s.id_siswa = sk.id_siswa
                                   LEFT JOIN dt_kelas k ON k.id_kelas = sk.id_kelas
                                   WHERE sk.username = ? OR s.nipd = ? OR s.nisn = ?
                                   ORDER BY (sk.username = ?) DESC, (sk.status_aktif = 'Y') DESC, sk.id_tahun_ajaran DESC
                                   LIMIT 1");
        $stmt->bind_param('ssss', $identifier, $identifier, $identifier, $identifier);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$u) {
            $err = 'NIS/username tidak ditemukan.';
        } elseif ($u['status_aktif'] !== 'Y') {
            $err = 'Akun belum diaktifkan / sudah dinonaktifkan. Hubungi admin.';
        } else {
            $ok = password_verify($password, $u['password']) || (md5($password) === $u['password']);
            if (!$ok) {
                $err = 'Password salah.';
            } else {
                $_SESSION['sekretaris'] = [
                    'id_sekretaris'   => (int)$u['id_sekretaris'],
                    'username'        => $u['username'],
                    'id_siswa'        => (int)$u['id_siswa'],
                    'nama_siswa'      => $u['nama_siswa'] ?? '',
                    'id_kelas'        => (int)$u['id_kelas'],
                    'nama_kelas'      => $u['nama_kelas'] ?? '-',
                    'tingkat'         => $u['tingkat'] ?? '',
                    'id_tahun_ajaran' => (int)$u['id_tahun_ajaran'],
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
    <title>Login Sekretaris - <?= sek_esc($profil['nama_sekolah']) ?></title>
    <link rel="icon" href="<?= sek_esc(sek_logo_url($profil)) ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center;
               background: linear-gradient(135deg, #0d6efd, #5aa2ff); padding: 18px; }
        .login-card { max-width: 390px; width: 100%; border: 0; border-radius: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,.18); }
        .logo-login { width: 66px; height: 66px; object-fit: contain; background: #fff; border-radius: 50%; padding: 5px; }
    </style>
</head>
<body>
    <div class="w-100" style="max-width:400px;">
        <div class="text-center text-white mb-3">
            <img src="<?= sek_esc(sek_logo_url($profil)) ?>" alt="Logo" style="max-width:86px;" class="mb-2">
            <h5 class="mb-0 fw-bold"><?= sek_esc($profil['nama_sekolah']) ?></h5>
            <small class="text-white-50">Portal Sekretaris Kelas</small>
        </div>
        <div class="card sek-card shadow-lg border-0">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3 text-center">Masuk Sekretaris Kelas</h6>
                <?php if ($err): ?>
                    <div class="alert alert-danger py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i><?= sek_esc($err) ?></div>
                <?php endif; ?>
                <form method="post" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">NIS / Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="NIS siswa" required autofocus
                                   value="<?= sek_esc($_POST['username'] ?? '') ?>">
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
                    <button type="submit" class="btn btn-primary w-100 fw-semibold"><i class="bi bi-box-arrow-in-right me-1"></i>Masuk</button>
                </form>
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
