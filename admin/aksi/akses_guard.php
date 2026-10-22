<?php
/**
 * Guard kontrol akses untuk handler di folder aksi/.
 * Sertakan di baris paling atas setiap file aksi (kecuali aksi_login.php).
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../akses.php');
require_once(__DIR__ . '/../helper_audit.php');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

$__level = $_SESSION['level_user'] ?? '';
$__file  = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
$__menu  = AKSES_AKSI_MENU[$__file] ?? null;

if (!boleh_aksi($__level, $__file)) {
    // Catat percobaan aksi yang ditolak RBAC.
    audit_register('denied', $__file, $__menu);
    header("Location: ../index.php?menu=beranda&pesan=tolak");
    exit;
}

// Catat aksi yang diizinkan (di akhir request, termasuk saat handler exit).
audit_register('ok', $__file, $__menu);
