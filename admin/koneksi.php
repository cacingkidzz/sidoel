<?php

/**
 * Koneksi database + konfigurasi aplikasi.
 * Menggunakan deteksi otomatis Host Lokal vs Hosting Produksi.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
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

// 2. Buat Koneksi Menggunakan Fungsi Standar
mysqli_report(MYSQLI_REPORT_OFF); // Matikan error bawaan agar tidak memicu blank 500
$koneksi = @mysqli_connect($host, $user, $pass, $db);

// 3. Tampilkan Pesan Sederhana Jika Gagal
if (!$koneksi) {
    http_response_code(503);
    die("<div style='font-family:sans-serif; padding:30px; background:#fff5f5; border:1px solid #feb2b2; color:#c53030; border-radius:8px; max-width:600px; margin:40px auto;'>
            <h3>⚠ Koneksi Database Gagal</h3>
            <p>Aplikasi tidak dapat terhubung ke MySQL server.</p>
            <p><strong>Pesan Error:</strong> " . mysqli_connect_error() . "</p>
            <small>Mencoba koneksi ke Host: <code>$host</code> | User: <code>$user</code> | DB: <code>$db</code></small>
         </div>");
}

// 4. Konfigurasi Tambahan Aplikasi
if (!defined('GOOGLE_MAPS_API_KEY')) {
    define('GOOGLE_MAPS_API_KEY', '');
}

if (!defined('MYSQL_BIN_DIR')) {
    define('MYSQL_BIN_DIR', DIRECTORY_SEPARATOR === '\\' ? 'C:\\xampp\\mysql\\bin' : '');
}

// Muat Helper Autentikasi bawaan
if (is_file(__DIR__ . '/helper_auth.php')) {
    require_once __DIR__ . '/helper_auth.php';
}

