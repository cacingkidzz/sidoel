<?php
// Handler: bersihkan log aktivitas (Administrasi Sistem).
require_once(__DIR__ . '/akses_guard.php');

include("../koneksi.php");

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';

if ($aksi === 'hapus_semua') {
    $koneksi->query("DELETE FROM dt_audit_log");
    $_SESSION['alert'] = ['type' => 'success', 'msg' => 'Semua <b>log aktivitas</b> telah dibersihkan.'];
    header("Location: ../index.php?menu=audit_log");
    exit;
}

$_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Aksi tidak valid.'];
header("Location: ../index.php?menu=audit_log");
exit;
