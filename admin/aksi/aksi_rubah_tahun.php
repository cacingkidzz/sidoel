<?php
require_once(__DIR__ . '/akses_guard.php');
// Pastikan ini adalah baris kode PERTAMA. TIDAK ADA SPASI, enter, atau kode HTML di atasnya.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Pastikan path koneksi sudah benar, relatif dari folder aksi/
include("../koneksi.php");

if (isset($_POST['tahun_ajaran_baru'])) {
    // Amankan data yang diterima
    $tahun_baru = $koneksi->real_escape_string($_POST['tahun_ajaran_baru']);

    // OPERASI KRITIS: Update Session
    $_SESSION['tahun_ajaran_aktif'] = $tahun_baru;

    // Set notifikasi (Opsional)
    $_SESSION['alert'] = [
        'type' => 'success',
        'msg' => 'Tahun Ajaran berhasil diganti ke <b>' . $tahun_baru . '</b>.'
    ];
} else {
    // Jika data POST gagal dikirim
    $_SESSION['alert'] = [
        'type' => 'danger',
        'msg' => 'Terjadi kesalahan: Data Tahun Ajaran tidak terkirim.'
    ];
}

// Redirect ke halaman sebelumnya atau index utama
// Pastikan tidak ada output sebelum header()
$referer = $_SERVER['HTTP_REFERER'] ?? '../index.php?menu=beranda';
header("Location: " . $referer);
exit;
