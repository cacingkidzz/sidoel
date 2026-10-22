<?php
require_once(__DIR__ . '/akses_guard.php');
include("../koneksi.php"); // Sesuaikan jalur include() ini

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';
$redirect_halaman = "../index.php?menu=mapel"; // Sesuaikan dengan menu Mata Pelajaran Anda

if ($aksi == "tambah") {
    $kode = $_POST['kode_mapel'];
    $nama = $_POST['nama_mapel'];
    $kelompok = $_POST['kelompok_mapel'];

    // 1. Cek duplikasi Kode Mapel atau Nama Mapel
    $q_cek = $koneksi->prepare("SELECT COUNT(*) FROM dt_mapel WHERE kode_mapel = ? OR nama_mapel = ?");
    $q_cek->bind_param("ss", $kode, $nama);
    $q_cek->execute();
    $q_cek->bind_result($count);
    $q_cek->fetch();
    $q_cek->close();

    if ($count > 0) {
        $_SESSION['alert'] = ['type' => 'warning', 'msg' => 'Gagal menambah data! Kode Mapel atau Nama Mapel sudah ada.'];
    } else {
        // 2. Eksekusi INSERT (Kolom: kode_mapel, nama_mapel, kelompok_mapel)
        $sql = $koneksi->prepare("INSERT INTO dt_mapel (kode_mapel, nama_mapel, kelompok_mapel) VALUES (?, ?, ?)");
        // Tiga string: sss
        $sql->bind_param("sss", $kode, $nama, $kelompok);

        if ($sql->execute()) {
            header("Location: " . $redirect_halaman . "&pesan=tambah");
        } else {
            $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Gagal menambah Mata Pelajaran! Error: ' . $koneksi->error];
        }
        $sql->close();
    }
} elseif ($aksi == "edit") {
    $id = $_POST['id_mapel'];
    $kode = $_POST['kode_mapel'];
    $nama = $_POST['nama_mapel'];
    $kelompok = $_POST['kelompok_mapel'];

    // Eksekusi UPDATE
    $sql = $koneksi->prepare("UPDATE dt_mapel SET kode_mapel=?, nama_mapel=?, kelompok_mapel=? WHERE id_mapel=?");
    // Tiga string, satu integer: sssi
    $sql->bind_param("sssi", $kode, $nama, $kelompok, $id);

    if ($sql->execute()) {
        header("Location: " . $redirect_halaman . "&pesan=edit");
    } else {
        $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Gagal memperbarui Mata Pelajaran! Error: ' . $koneksi->error];
    }
    $sql->close();
} elseif ($aksi == "hapus") {
    // Aksi hapus menggunakan method GET dari URL (SweetAlert)
    $id = $_GET['id'] ?? null;

    if ($id) {
        // Eksekusi DELETE
        $sql = $koneksi->prepare("DELETE FROM dt_mapel WHERE id_mapel=?");
        // Satu integer: i
        $sql->bind_param("i", $id);

        if ($sql->execute()) {
            header("Location: " . $redirect_halaman . "&pesan=hapus");
        } else {
            $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Gagal menghapus Mata Pelajaran! Error: ' . $koneksi->error];
        }
        $sql->close();
    } else {
        $_SESSION['alert'] = ['type' => 'warning', 'msg' => 'ID Mata Pelajaran tidak ditemukan!'];
    }
}


exit;
