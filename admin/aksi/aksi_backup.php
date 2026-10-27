<?php
// Handler Backup/Restore Database (Administrasi Sistem). RBAC: Admin.
require_once(__DIR__ . '/akses_guard.php');

include("../koneksi.php");
require_once(__DIR__ . '/../helper_backup.php');

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';

// Konfigurasi koneksi berasal dari global koneksi.php
$cfg_host = $host;
$cfg_user = $user;
$cfg_pass = $pass;
$cfg_db   = $db;

function backup_redirect($type, $msg)
{
    $_SESSION['alert'] = ['type' => $type, 'msg' => $msg];
    header("Location: ../index.php?menu=backup_restore");
    exit;
}

// --- Download file backup ---
if ($aksi === 'download') {
    $file = $_GET['file'] ?? '';
    $path = backup_file_path($file);
    if (!$path || !is_file($path)) {
        backup_redirect('danger', 'File backup tidak ditemukan.');
    }
    // Bersihkan buffer agar biner utuh
    if (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

// --- Buat backup baru ---
if ($aksi === 'buat') {
    $name = 'db_' . date('Ymd_His') . '.sql';
    $path = backup_dir() . DIRECTORY_SEPARATOR . $name;
    list($ok, $out) = backup_dump($path, $cfg_host, $cfg_user, $cfg_pass, $cfg_db, $koneksi);
    if ($ok) {
        $ket = trim($out) !== '' && $out !== 'OK' ? ' — ' . htmlspecialchars($out) : '';
        backup_redirect('success', 'Backup berhasil dibuat: <b>' . htmlspecialchars($name) . '</b> (' . backup_size_human(filesize($path)) . ')'
            . ' via <b>' . htmlspecialchars(backup_mode_label()) . '</b>' . $ket . '.');
    }
    @unlink($path);
    backup_redirect('danger', 'Backup <b>gagal</b>. ' . htmlspecialchars(trim($out)));
}

// --- Restore dari file yang ada ---
if ($aksi === 'restore_file') {
    $file = $_POST['file'] ?? '';
    $path = backup_file_path($file);
    if (!$path || !is_file($path)) {
        backup_redirect('danger', 'File backup tidak ditemukan.');
    }
    list($ok, $out) = backup_restore($path, $cfg_host, $cfg_user, $cfg_pass, $cfg_db, $koneksi);
    if ($ok) {
        backup_redirect('success', 'Database berhasil di-<b>restore</b> dari <b>' . htmlspecialchars($file) . '</b>.');
    }
    backup_redirect('danger', 'Restore <b>gagal</b>. ' . htmlspecialchars(trim($out)));
}

// --- Restore dari file unggahan ---
if ($aksi === 'restore') {
    if (!isset($_FILES['file_sql']) || $_FILES['file_sql']['error'] !== UPLOAD_ERR_OK) {
        backup_redirect('danger', 'Tidak ada file yang diunggah atau terjadi kesalahan unggah.');
    }
    $tmp = $_FILES['file_sql']['tmp_name'];
    $orig = $_FILES['file_sql']['name'] ?? '';
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if ($ext !== 'sql') {
        backup_redirect('danger', 'Format file harus <b>.sql</b>.');
    }
    // Simpan salinan ke folder backup, lalu restore dari salinan itu.
    $name = 'restore_' . date('Ymd_His') . '.sql';
    $path = backup_dir() . DIRECTORY_SEPARATOR . $name;
    if (!@move_uploaded_file($tmp, $path)) {
        backup_redirect('danger', 'Gagal menyimpan file unggahan.');
    }
    list($ok, $out) = backup_restore($path, $cfg_host, $cfg_user, $cfg_pass, $cfg_db, $koneksi);
    if ($ok) {
        backup_redirect('success', 'Database berhasil di-<b>restore</b> dari unggahan (' . backup_size_human(filesize($path)) . '). Salinan disimpan sebagai <b>' . htmlspecialchars($name) . '</b>.');
    }
    backup_redirect('danger', 'Restore <b>gagal</b>. ' . htmlspecialchars(trim($out)));
}

// --- Hapus file backup ---
if ($aksi === 'hapus') {
    $file = $_GET['file'] ?? $_POST['file'] ?? '';
    $path = backup_file_path($file);
    if (!$path || !is_file($path)) {
        backup_redirect('danger', 'File backup tidak ditemukan.');
    }
    if (@unlink($path)) {
        backup_redirect('success', 'File backup <b>' . htmlspecialchars($file) . '</b> dihapus.');
    }
    backup_redirect('danger', 'Gagal menghapus file backup.');
}

backup_redirect('danger', 'Aksi tidak valid.');
