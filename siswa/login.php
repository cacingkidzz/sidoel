<?php
require_once __DIR__ . '/_init.php';

if (siswa_logged_in()) {
    header('Location: index.php');
    exit;
}

$profil = siswa_profil_sekolah($koneksi);
$logo_url = siswa_logo_url($profil);
$error = '';
$ident = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ident = trim($_POST['identitas'] ?? '');
    $pass  = (string)($_POST['password'] ?? '');

    if ($ident === '' || $pass === '') {
        $error = 'NIS/NISN dan password wajib diisi.';
    } else {
        $stmt = $koneksi->prepare("SELECT id_siswa, nama, nipd, nisn, jk, id_tahun_ajaran, password
                                   FROM dt_siswa
                                   WHERE nipd = ? OR nisn = ?
                                   ORDER BY (nipd = ?) DESC, id_siswa DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('sss', $ident, $ident, $ident);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $row = null;
        }

        if (!$row || !siswa_verifikasi_password($pass, (string)($row['password'] ?? ''))) {
            $error = 'NIS/NISN atau password salah.';
        } else {
            if (siswa_password_lama((string)$row['password'])) {
                $hash = hash_password($pass);
                $up = $koneksi->prepare("UPDATE dt_siswa SET password = ? WHERE id_siswa = ?");
                if ($up) {
                    $up->bind_param('si', $hash, $row['id_siswa']);
                    $up->execute();
                    $up->close();
                }
            }

            session_regenerate_id(true);
            $_SESSION['siswa'] = [
                'id_siswa' => (int)$row['id_siswa'],
                'nama'     => $row['nama'],
                'nipd'     => $row['nipd'],
                'nisn'     => $row['nisn'],
            ];
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Siswa - <?= siswa_esc($profil['nama_sekolah']); ?></title>
    <link rel="icon" href="<?= siswa_esc($logo_url); ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center;
               background: linear-gradient(135deg, #1e40af, #3b82f6); padding: 18px; }
        .login-card { max-width: 390px; width: 100%; border: 0; border-radius: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,.18); }
        .logo-login { width: 66px; height: 66px; object-fit: contain; background: #fff; border-radius: 50%; padding: 5px; }
    </style>
</head>
<body>
    <div class="w-100" style="max-width:400px;">
        <div class="text-center text-white mb-3">
            <img src="<?= siswa_esc($logo_url); ?>" alt="Logo" style="max-width:86px;" class="mb-2">
            <h5 class="mb-0 fw-bold"><?= siswa_esc($profil['nama_sekolah']); ?></h5>
            <small class="text-white-50">Portal Siswa</small>
        </div>
        <div class="card login-card">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-1 text-center">Masuk Siswa</h6>
                <p class="text-muted text-center small mb-3">Gunakan NIS atau NISN dan password akun siswa.</p>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i><?= siswa_esc($error); ?></div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">NIS / NISN</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-vcard"></i></span>
                            <input type="text" name="identitas" class="form-control" placeholder="Nomor induk siswa" required autofocus
                                   value="<?= siswa_esc($ident); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                            <button type="button" class="btn btn-outline-secondary" id="togglePass"><i class="bi bi-eye" id="eye"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Masuk
                    </button>
                </form>

                <div class="text-center mt-3 small">
                    <a href="../admin/login.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left me-1"></i>Login Admin</a>
                </div>
            </div>
        </div>
        <p class="text-center text-white-50 small mt-3 mb-0">&copy; <?= date('Y'); ?> SiDoel</p>
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
