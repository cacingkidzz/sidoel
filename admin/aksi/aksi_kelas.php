<?php
require_once(__DIR__ . '/akses_guard.php');
// Pastikan sesi sudah dimulai di file induk (misalnya index.php)
// Jika Anda mengakses file ini langsung, Anda mungkin perlu memanggil session_start()
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include("../koneksi.php"); // Sesuaikan path file koneksi.php

// Cek apakah ada parameter aksi
if (isset($_POST['aksi']) || isset($_GET['aksi'])) {
    $aksi = isset($_POST['aksi']) ? $_POST['aksi'] : $_GET['aksi'];

    // --- FUNGSI TAMBAH DATA ---
    if ($aksi == 'tambah') {
        // Ambil dan sanitasi data POST
        $tingkat            = $koneksi->real_escape_string($_POST['tingkat']);
        $id_jurusan         = $koneksi->real_escape_string($_POST['id_jurusan']);
        $nama_kelas         = $koneksi->real_escape_string($_POST['nama_kelas']);
        $keterangan         = $koneksi->real_escape_string($_POST['keterangan']);
        $id_tahun_ajaran    = $koneksi->real_escape_string($_POST['id_tahun_ajaran']);

        // Pastikan Tahun Ajaran Aktif ditemukan
        if ($id_tahun_ajaran == 0) {
            $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Gagal! Tahun Ajaran Aktif tidak ditemukan.'];
            header("Location: ../index.php?menu=kelas");
            exit;
        }

        // Query INSERT
        $sql = "INSERT INTO dt_kelas (tingkat, id_jurusan, nama_kelas, keterangan, id_tahun_ajaran) 
                VALUES ('$tingkat', '$id_jurusan', '$nama_kelas', '$keterangan', '$id_tahun_ajaran')";

        if ($koneksi->query($sql)) {
            $_SESSION['alert'] = ['type' => 'success', 'msg' => 'Data kelas <b>' . $nama_kelas . '</b> berhasil ditambahkan.'];
        } else {
            $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Data kelas gagal ditambahkan. Error: ' . $koneksi->error];
        }

        header("Location: ../index.php?menu=kelas");
        exit;
    }

    // --- FUNGSI EDIT DATA ---
    elseif ($aksi == 'edit') {
        // Ambil dan sanitasi data POST
        $id_kelas           = $koneksi->real_escape_string($_POST['id_kelas']);
        $tingkat            = $koneksi->real_escape_string($_POST['tingkat']);
        $id_jurusan         = $koneksi->real_escape_string($_POST['id_jurusan']);
        $nama_kelas         = $koneksi->real_escape_string($_POST['nama_kelas']);
        $keterangan         = $koneksi->real_escape_string($_POST['keterangan']);
        // Kolom id_tahun_ajaran tidak diubah saat edit karena kelas ini milik TA yang sudah berjalan

        // Query UPDATE
        $sql = "UPDATE dt_kelas SET 
                tingkat = '$tingkat', 
                id_jurusan = '$id_jurusan', 
                nama_kelas = '$nama_kelas', 
                keterangan = '$keterangan' 
                WHERE id_kelas = '$id_kelas'";

        if ($koneksi->query($sql)) {
            $_SESSION['alert'] = ['type' => 'success', 'msg' => 'Data kelas <b>' . $nama_kelas . '</b> berhasil diupdate.'];
        } else {
            $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Data kelas gagal diupdate. Error: ' . $koneksi->error];
        }

        header("Location: ../index.php?menu=kelas");
        exit;
    }

    // --- FUNGSI HAPUS DATA ---
    elseif ($aksi == 'hapus') {
        // Ambil dan sanitasi data GET
        $id_kelas = $koneksi->real_escape_string($_GET['id']);

        // Ambil nama kelas untuk pesan alert
        $q_nama = $koneksi->query("SELECT nama_kelas FROM dt_kelas WHERE id_kelas = '$id_kelas'");
        $d_nama = $q_nama->fetch_assoc();
        $nama_kelas = $d_nama ? $d_nama['nama_kelas'] : 'ID Kelas ' . $id_kelas;

        // Query DELETE
        $sql = "DELETE FROM dt_kelas WHERE id_kelas = '$id_kelas'";

        if ($koneksi->query($sql)) {
            $_SESSION['alert'] = ['type' => 'success', 'msg' => 'Data kelas <b>' . $nama_kelas . '</b> berhasil dihapus.'];
        } else {
            // Cek jika error karena foreign key constraint (data masih dipakai)
            if ($koneksi->errno == 1451) {
                $_SESSION['alert'] = ['type' => 'warning', 'msg' => 'Gagal menghapus! Kelas <b>' . $nama_kelas . '</b> masih digunakan oleh data lain (siswa/jadwal).'];
            } else {
                $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Data kelas gagal dihapus. Error: ' . $koneksi->error];
            }
        }

        header("Location: ../index.php?menu=kelas");
        exit;
    }

    // Jika aksi tidak valid
    else {
        $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Aksi tidak valid.'];
        header("Location: ../index.php?menu=kelas");
        exit;
    }
} else {
    // Jika tidak ada parameter aksi
    $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Akses langsung tidak diizinkan.'];
    header("Location: ../index.php?menu=kelas");
    exit;
}
