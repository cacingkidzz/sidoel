<?php
// Pastikan sesi sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi.php (Asumsi koneksi.php berada satu level di atas folder aksi)
include("../koneksi.php");
require_once(__DIR__ . '/../helper_audit.php');

// Pastikan koneksi.php berhasil di-include
if (!isset($koneksi) || $koneksi->connect_error) {
    // Jika koneksi gagal, set alert dan redirect ke login
    $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Koneksi ke database gagal. Silakan hubungi Administrator.'];
    header("Location: ../login.php");
    exit;
}

// --- 1. PROSES LOGOUT ---
if (isset($_GET['aksi']) && $_GET['aksi'] == 'logout') {
    // Catat aksi logout sebelum sesi dihancurkan
    audit_write($koneksi, 'login', 'ok', 'login', 'logout', 'Logout: ' . ($_SESSION['username'] ?? '-'));

    // Hancurkan semua data sesi
    session_unset();
    session_destroy();

    // Set pesan alert
    $_SESSION['alert'] = ['type' => 'success', 'msg' => 'Anda telah berhasil <b>Log Out</b>. Silakan masuk kembali.'];

    // Arahkan kembali ke halaman login
    header("Location: ../login.php");
    exit;
}

// --- 2. PROSES LOGIN ---
if (isset($_POST['login'])) {
    // Ambil dan sanitasi input dari form
    $username = $koneksi->real_escape_string($_POST['username']);
    $password = trim($_POST['password']);

    // 1. Cari user berdasarkan username
    // Query tetap ke dt_user sesuai permintaan
    $sql = "SELECT id_user, username, password, level_user, status_aktif FROM dt_user WHERE username = '$username'";
    $query = $koneksi->query($sql);

    if ($query && $query->num_rows == 1) {
        $user = $query->fetch_assoc();

        // 2. Verifikasi Password
        // Mendukung hash lama (MD5) & baru (bcrypt). Bila masih MD5 dan cocok,
        // otomatis di-upgrade ke bcrypt.
        $stored_password = $user['password'];

        if (verifikasi_password($password, $stored_password)) {
            // Auto-upgrade MD5 -> bcrypt (sekali saja, saat login berhasil)
            upgrade_password_jika_perlu($koneksi, $user['id_user'], $password, $stored_password);
            // Cek status aktif
            if ($user['status_aktif'] == 'Y') {

                // Login Berhasil!

                // Set Session User
                $_SESSION['logged_in'] = true;
                $_SESSION['id_user'] = $user['id_user'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['level_user'] = $user['level_user'];

                // Catat login berhasil
                audit_write($koneksi, 'login', 'ok', 'login', 'login', 'Login berhasil: ' . $user['username']);

                // ==========================================================
                // >>> AMBIL TAHUN AJARAN AKTIF DARI dt_tahun_ajar <<<
                // ==========================================================

                // Query mengambil tahun ajaran yang statusnya 'aktif'
                $sql_ta = "SELECT tahun_ajaran FROM dt_tahun_ajar WHERE status = 'aktif' LIMIT 1";
                $query_ta = $koneksi->query($sql_ta);

                $tahun_aktif = 'Tidak Ditemukan';
                if ($query_ta && $query_ta->num_rows > 0) {
                    $tahun_aktif = $query_ta->fetch_assoc()['tahun_ajaran'];
                }

                // Simpan Tahun Ajaran ke Session
                $_SESSION['tahun_ajaran_aktif'] = $tahun_aktif;

                // ==========================================================

                // Tentukan halaman redirect
                $redirect_url = "../index.php?menu=beranda";

                $_SESSION['alert'] = ['type' => 'success', 'msg' => 'Selamat datang, <b>' . $user['username'] . '</b>! Anda berhasil masuk di TA <b>' . $tahun_aktif . '</b>.'];
                header("Location: " . $redirect_url);
                exit;
            } else {
                // User tidak aktif
                audit_write($koneksi, 'login', 'gagal', 'login', 'login', 'Akun nonaktif: ' . $username);
                $_SESSION['alert'] = ['type' => 'warning', 'msg' => 'Akun Anda <b>belum diaktifkan</b> atau <b>sudah dinonaktifkan</b> oleh Administrator.'];
            }
        } else {
            // Password salah
            audit_write($koneksi, 'login', 'gagal', 'login', 'login', 'Password salah: ' . $username);
            $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Login gagal! <b>Password</b> tidak sesuai.'];
        }
    } else {
        // Username tidak ditemukan
        audit_write($koneksi, 'login', 'gagal', 'login', 'login', 'Username tidak ditemukan: ' . $username);
        $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Login gagal! <b>Username</b> tidak ditemukan.'];
    }

    // Jika terjadi kegagalan, kembali ke halaman login
    header("Location: ../login.php");
    exit;
}

// --- 3. AKSI TIDAK DIKENAL ---
// Jika file diakses tanpa parameter POST/GET yang valid
if (!isset($_POST['login']) && !isset($_GET['aksi'])) {
    $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Akses langsung ke file aksi tidak diizinkan.'];
    header("Location: ../login.php");
    exit;
}
