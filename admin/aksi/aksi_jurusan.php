<?php
require_once(__DIR__ . '/akses_guard.php');
include("../koneksi.php");
date_default_timezone_set("Asia/Makassar");

$aksi = $_POST['aksi'] ?? ($_GET['aksi'] ?? '');

if ($aksi == 'tambah') {
    $nama_jurusan = $_POST['nama_jurusan'];
    $wa           = $_POST['wa'];
    $status_jur   = $_POST['status_jur'];

    $stmt = $koneksi->prepare("INSERT INTO dt_jurusan (nama_jurusan, wa, status_jur) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $nama_jurusan, $wa, $status_jur);

    if ($stmt->execute()) {
        header("Location: ../index.php?menu=jurusan&pesan=tambah");
    } else {
        header("Location: ../index.php?menu=jurusan&pesan=error");
    }
    exit();
}

if ($aksi == 'edit') {
    $id_jurusan   = $_POST['id_jurusan'];
    $nama_jurusan = $_POST['nama_jurusan'];
    $wa           = $_POST['wa'];
    $status_jur   = $_POST['status_jur'];

    $stmt = $koneksi->prepare("UPDATE dt_jurusan SET nama_jurusan=?, wa=?, status_jur=? WHERE id_jurusan=?");
    $stmt->bind_param("ssii", $nama_jurusan, $wa, $status_jur, $id_jurusan);

    if ($stmt->execute()) {
        header("Location: ../index.php?menu=jurusan&pesan=edit");
    } else {
        header("Location: ../index.php?menu=jurusan&pesan=error");
    }
    exit();
}

if ($aksi == 'hapus' && isset($_GET['id_jurusan'])) {
    $id = intval($_GET['id_jurusan']);
    $stmt = $koneksi->prepare("DELETE FROM dt_jurusan WHERE id_jurusan=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: ../index.php?menu=jurusan&pesan=hapus");
    } else {
        header("Location: ../index.php?menu=jurusan&pesan=error");
    }
    exit();
}

header("Location: ../index.php?menu=jurusan&pesan=invalid");
exit();
