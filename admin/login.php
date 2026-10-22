<?php
// Pastikan sesi sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi.php
include("koneksi.php");

// --- LOGIKA MENGAMBIL DATA SEKOLAH ---
$nama_sekolah = 'Sistem Informasi'; // Default jika data tidak ditemukan
$logo_path = 'assets/img/default_logo.png'; // Default path logo (sesuaikan)

if (isset($koneksi)) {
    // Asumsi: Tabel dt_data_sekolah memiliki kolom nama_sekolah dan logo
    $q_sekolah = $koneksi->query("SELECT nama_sekolah, logo FROM dt_data_sekolah LIMIT 1");
    if ($q_sekolah && $q_sekolah->num_rows > 0) {
        $sekolah = $q_sekolah->fetch_assoc();
        $nama_sekolah = htmlspecialchars($sekolah['nama_sekolah']);
        $logo_path = $sekolah['logo'];
    }
}
// --- AKHIR LOGIKA DATA SEKOLAH ---


// Cek jika user sudah login, redirect ke halaman utama
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Logika redirect disederhanakan
    header("Location: index.php?menu=beranda");
    exit;
}
?>

<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | <?= $nama_sekolah ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" href="assets/img/logo/<?= $logo_path ?>" type="image/x-icon">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
        }

        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 20px;
        }

        .card-header {
            background-color: var(--bs-primary);
            color: white;
            border-bottom: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .logo-login {
            width: 70px;
            height: 70px;
            object-fit: contain;
            background-color: white;
            border-radius: 50%;
            padding: 5px;
            margin-bottom: 10px;
        }

        body {
            background-image: url('assets/img/bg-content.jpg') !important;
        }
    </style>
</head>

<body>

    <div class="login-container">
        <div class="text-center mb-3">
            <img src="assets/img/logo/<?= $logo_path ?>" alt="Logo Sekolah" style="max-width: 100px"">
            <h5 class=" mb-0 fw-bold"><?= $nama_sekolah ?></h5>
            <small>Area Login Administrator</small>
        </div>
        <div class="card shadow-lg border-0">
            <div class="card-header text-center rounded-top p-3">
                <h3 class="fw-bold mb-0">- SiDoel -</h3>
                <small>Sistem Data Online Sekolah</small>
            </div>

            <div class="card-body p-4">

                <?php if (isset($_SESSION['alert'])): ?>
                    <div class="alert alert-<?= $_SESSION['alert']['type']; ?> alert-dismissible fade show" role="alert">
                        <?= $_SESSION['alert']['msg']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['alert']); ?>
                <?php endif; ?>

                <form action="aksi/aksi_login.php" method="POST">

                    <input type="hidden" name="login" value="1">

                    <div class="mb-3">
                        <label for="username" class="form-label">Username (Email)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan email terdaftar" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword" title="Tampilkan Password">
                                <i class="bi bi-eye-slash-fill" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                        </button>
                    </div>
                </form>

            </div>

            <div class="card-footer text-center text-muted small py-2">
                &copy; 2025 SiDoel - Sistem Informasi.
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');

            // Toggle the type attribute (password <-> text)
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // Toggle the eye icon class
            if (type === 'text') {
                eyeIcon.classList.remove('bi-eye-slash-fill');
                eyeIcon.classList.add('bi-eye-fill');
            } else {
                eyeIcon.classList.remove('bi-eye-fill');
                eyeIcon.classList.add('bi-eye-slash-fill');
            }
        });
    </script>
</body>

</html>