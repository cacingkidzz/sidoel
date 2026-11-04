<?php

/**
 * Koneksi database + konfigurasi aplikasi.
 * Menggunakan deteksi otomatis Host Lokal vs Hosting Produksi.
 */

// 1. Atur Variabel Database Berdasarkan Environment (Hostname)
if ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1') {
    // --- KONFIGURASI LOKAL (XAMPP/LAPTOP) ---
    $host   = "localhost";
    $user   = "root";
    $pass   = "";
    $db     = "data_backup"; // Sesuaikan dengan nama DB di phpMyAdmin lokal Anda
} else {
    // --- KONFIGURASI PRODUCTION (HOSTING CPANEL) ---
    $host   = "localhost";
    $user   = "ppdf2433_sidoel";       // GANTI dengan User DB asli cPanel Anda
    $pass   = "=!~^}N=kzr,Qot!D";     // GANTI dengan Password DB asli cPanel Anda
    $db     = "ppdf2433_sidoel";       // GANTI dengan Nama DB asli cPanel Anda
}

// Matikan laporan error bawaan agar bisa ditangkap oleh try-catch khusus di bawah
mysqli_report(MYSQLI_REPORT_OFF);
$koneksi = null;
$koneksi_error = '';

try {
    $koneksi = @new mysqli($host, $user, $pass, $db);
    if ($koneksi->connect_errno) {
        $koneksi_error = $koneksi->connect_error ?: ('Kode ' . $koneksi->connect_errno);
        $koneksi = null;
    }
} catch (Throwable $e) {
    $koneksi_error = $e->getMessage();
    $koneksi = null;
}

// Tampilkan halaman diagnosa yang ramah bila koneksi gagal.
if (!$koneksi) {
    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
    }
    $esc = static function ($v) {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    };
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Koneksi Database Gagal — SiDoel</title>'
        . '<style>body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#f8fafc;margin:0;padding:24px;color:#0f172a}'
        . '.box{max-width:720px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px 26px;box-shadow:0 6px 24px rgba(15,23,42,.06)}'
        . 'h1{font-size:20px;margin:0 0 6px;color:#b91c1c}p{line-height:1.6;margin:8px 0}'
        . 'code{background:#f1f5f9;padding:2px 6px;border-radius:5px;font-size:13px}'
        . 'pre{background:#0f172a;color:#e2e8f0;padding:12px 14px;border-radius:10px;overflow:auto;font-size:13px}'
        . 'ul{margin:8px 0 0 18px;padding:0}li{margin:6px 0;line-height:1.55}'
        . '.meta{margin-top:16px;font-size:13px;color:#475569;border-top:1px solid #e2e8f0;padding-top:12px}'
        . '.tag{display:inline-block;padding:1px 8px;border-radius:99px;font-size:12px;font-weight:600}'
        . '.ok{background:#dcfce7;color:#166534}.bad{background:#fee2e2;color:#991b1b}</style></head><body><div class="box">'
        . '<h1><i>⚠</i> Aplikasi tidak dapat terhubung ke database</h1>'
        . '<p>SiDoel sudah terpasang, tetapi koneksi MySQL gagal sehingga semua halaman berhenti di sini.</p>'
        . '<p><strong>Pesan dari server:</strong></p><pre>' . $esc($koneksi_error) . '</pre>'
        . '<p><strong>Yang perlu diperiksa:</strong></p><ul>'
        . '<li>Pastikan akun database di cPanel sudah sesuai.</li>'
        . '<li>Pastikan user database sudah dikaitkan ke database dengan <strong>ALL PRIVILEGES</strong>.</li>'
        . '</ul>'
        . '<div class="meta">'
        . 'PHP <strong>' . $esc(PHP_VERSION) . '</strong> &middot; '
        . 'Host <code>' . $esc($host) . '</code> &middot; User <code>' . $esc($user) . '</code> &middot; '
        . 'Database <code>' . $esc($db) . '</code>'
        . '</div></div></body></html>';
    exit;
}

// Konfigurasi aplikasi tambahan
if (!defined('GOOGLE_MAPS_API_KEY')) {
    define('GOOGLE_MAPS_API_KEY', $_SERVER['HTTP_HOST'] == 'localhost' ? '' : 'KEY_HOSTING_ANDA');
}

if (!defined('MYSQL_BIN_DIR')) {
    define('MYSQL_BIN_DIR', DIRECTORY_SEPARATOR === '\\' ? 'C:\\xampp\\mysql\\bin' : '');
}

// Helper autentikasi password
require_once __DIR__ . '/helper_auth.php';

// Notifikasi tersembunyi
echo "<!-- Koneksi berhasil terpasang via Auto-Switching -->";
