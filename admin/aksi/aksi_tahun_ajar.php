<?php
require_once(__DIR__ . '/akses_guard.php');
include("../koneksi.php");

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';

function setAlert($type, $msg)
{
    $_SESSION['alert'] = [
        'type' => $type,
        'msg' => $msg
    ];
}

if ($aksi == "tambah") {
    $tahun = trim($_POST['tahun_ajaran']);
    $status = $_POST['status'];

    // Cek duplikat
    $cek = $koneksi->prepare("SELECT COUNT(*) as jml FROM dt_tahun_ajar WHERE tahun_ajaran=?");
    $cek->bind_param("s", $tahun);
    $cek->execute();
    $h = $cek->get_result()->fetch_assoc();
    if ($h['jml'] > 0) {
        setAlert('danger', "Tahun ajaran $tahun sudah ada!");
        header("Location: ../index.php?menu=tahun_ajar");
        exit;
    }

    if ($status == "aktif") {
        $koneksi->query("UPDATE dt_tahun_ajar SET status='tidak aktif'");
    }

    $query = $koneksi->prepare("INSERT INTO dt_tahun_ajar (tahun_ajaran, status) VALUES (?, ?)");
    $query->bind_param("ss", $tahun, $status);
    $query->execute();

    setAlert('success', "Tahun ajaran $tahun berhasil ditambahkan!");
    header("Location: ../index.php?menu=tahun_ajar");
    exit;
}

if ($aksi == "edit") {
    $id = $_POST['id'];
    $tahun = trim($_POST['tahun_ajaran']);
    $status = $_POST['status'];

    if ($status == "aktif") {
        $koneksi->query("UPDATE dt_tahun_ajar SET status='tidak aktif'");
    }

    $query = $koneksi->prepare("UPDATE dt_tahun_ajar SET tahun_ajaran=?, status=? WHERE id_tahun_ajaran=?");
    $query->bind_param("ssi", $tahun, $status, $id);
    $query->execute();

    setAlert('success', "Tahun ajaran $tahun berhasil diperbarui!");
    header("Location: ../index.php?menu=tahun_ajar");
    exit;
}

if ($aksi == "hapus") {
    $id = $_GET['id'];

    // Cek apakah tahun ajaran ini aktif
    $cek = $koneksi->prepare("SELECT tahun_ajaran, status FROM dt_tahun_ajar WHERE id_tahun_ajaran=?");
    $cek->bind_param("i", $id);
    $cek->execute();
    $row = $cek->get_result()->fetch_assoc();

    if (!$row) {
        setAlert('danger', "Data tahun ajaran tidak ditemukan!");
        header("Location: ../index.php?menu=tahun_ajar");
        exit;
    }

    if ($row['status'] == 'aktif') {
        setAlert('danger', "Tahun ajaran {$row['tahun_ajaran']} sedang aktif dan tidak bisa dihapus!");
        header("Location: ../index.php?menu=tahun_ajar");
        exit;
    }

    $query = $koneksi->prepare("DELETE FROM dt_tahun_ajar WHERE id_tahun_ajaran=?");
    $query->bind_param("i", $id);
    $query->execute();

    setAlert('success', "Tahun ajaran {$row['tahun_ajaran']} berhasil dihapus!");
    header("Location: ../index.php?menu=tahun_ajar");
    exit;
}
