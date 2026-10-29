<?php
/**
 * Koneksi database + konfigurasi aplikasi.
 *
 * Nilai di bawah adalah DEFAULT untuk pengembangan lokal (XAMPP).
 *
 * PRODUCTION (cPanel): JANGAN ubah file ini. Buat file `admin/koneksi.local.php`
 * (sudah masuk .gitignore, jadi tidak akan ditimpa `git pull`/deploy) berisi:
 *
 *   <?php
 *   $host = 'localhost';
 *   $user = 'namauser_db';
 *   $pass = 'password_db';
 *   $db   = 'namauser_sidoel';
 *   define('GOOGLE_MAPS_API_KEY', 'KEY_KHUSUS_SERVER');
 *   define('MYSQL_BIN_DIR', '');
 *
 * File tersebut dimuat otomatis di bawah dan menimpa nilai default.
 */
$host   = "localhost";
$user   = "root";
$pass   = "";
$db     = "data_backup";

// Override konfigurasi produksi (tidak ikut git).
// require_once: file ini bisa ikut ter-include beberapa kali per request
// (mis. index.php + konten/*.php), dan define() di dalamnya akan memicu
// "Constant ... already defined" bila dimuat dua kali.
if (is_file(__DIR__ . '/koneksi.local.php')) {
    require_once __DIR__ . '/koneksi.local.php';
}

// buat koneksi
// PHP 8.1+ melempar mysqli_sql_exception saat koneksi gagal (bukan lagi
// mengembalikan false). Tanpa penanganan, hosting hanya menampilkan
// "HTTP ERROR 500" kosong sehingga sulit didiagnosis.
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

// Tampilkan halaman diagnosa yang ramah (bukan 500 kosong) bila koneksi gagal.
if (!$koneksi) {
    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
    }
    $esc = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
    $ada_lokal = is_file(__DIR__ . '/koneksi.local.php');
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
        . '<p>SiDoel sudah terpasang, tetapi koneksi MySQL gagal sehingga semua halaman berhenti di sini '
        . '(penyebab umum "HTTP ERROR 500" di hosting).</p>'
        . '<p><strong>Pesan dari server:</strong></p><pre>' . $esc($koneksi_error) . '</pre>'
        . '<p><strong>Yang perlu diperiksa:</strong></p><ul>'
        . '<li>Buat file <code>admin/koneksi.local.php</code> di server berisi kredensial database cPanel '
        . '(<code>$host</code>, <code>$user</code>, <code>$pass</code>, <code>$db</code>).</li>'
        . '<li>Pastikan user database sudah <em>Add User to Database</em> dengan <strong>ALL PRIVILEGES</strong>.</li>'
        . '<li>Nama database di cPanel biasanya berawalan user, mis. <code>user_sidoel</code>.</li>'
        . '<li>Pastikan dump database sudah diimpor lewat phpMyAdmin (41 tabel).</li>'
        . '</ul>'
        . '<div class="meta">'
        . 'PHP <strong>' . $esc(PHP_VERSION) . '</strong> &middot; '
        . 'ekstensi mysqli <span class="tag ' . (extension_loaded('mysqli') ? 'ok' : 'bad') . '">'
        . (extension_loaded('mysqli') ? 'aktif' : 'TIDAK AKTIF') . '</span> &middot; '
        . '<code>koneksi.local.php</code> <span class="tag ' . ($ada_lokal ? 'ok' : 'bad') . '">'
        . ($ada_lokal ? 'ditemukan' : 'BELUM ADA') . '</span><br>'
        . 'Host <code>' . $esc($host) . '</code> &middot; User <code>' . $esc($user) . '</code> &middot; '
        . 'Database <code>' . $esc($db) . '</code>'
        . '</div></div></body></html>';
    exit;
}

// Konfigurasi aplikasi
// Kunci Google Maps TIDAK disimpan di repo (agar tidak bocor saat repo dibagikan).
// Isi di admin/koneksi.local.php (tidak ikut git):
//   define('GOOGLE_MAPS_API_KEY', 'KEY_ANDA');
// Bila kosong, aplikasi tetap jalan — hanya peta yang tidak tampil.
if (!defined('GOOGLE_MAPS_API_KEY')) {
    define('GOOGLE_MAPS_API_KEY', '');
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
