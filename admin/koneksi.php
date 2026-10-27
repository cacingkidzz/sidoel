<?php
$host   = "localhost";
$user   = "root";
$pass   = "";
$db     = "data_backup";

// buat koneksi
$koneksi = new mysqli($host, $user, $pass, $db);

// cek koneksi
if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

// Konfigurasi aplikasi
if (!defined('GOOGLE_MAPS_API_KEY')) {
    define('GOOGLE_MAPS_API_KEY', 'AIzaSyAsGJSXm412kL3GXd0zGAjwzVIGupbIWoE');
}

// Lokasi binary MySQL (untuk fitur Backup/Restore — Administrasi Sistem).
// Windows/XAMPP: C:\xampp\mysql\bin. Linux/shared hosting: kosongkan ('') —
// bila exec() dimatikan atau binary tidak ada, fitur otomatis memakai mode
// PHP murni (lihat helper_backup.php).
if (!defined('MYSQL_BIN_DIR')) {
    define('MYSQL_BIN_DIR', DIRECTORY_SEPARATOR === '\\' ? 'C:\\xampp\\mysql\\bin' : '');
}

// Helper autentikasi password (verifikasi MD5/bcrypt + auto-upgrade).
require_once __DIR__ . '/helper_auth.php';

// Tambahkan pesan ini jika ingin melihat notifikasi koneksi berhasil
// echo "Koneksi berhasil";
