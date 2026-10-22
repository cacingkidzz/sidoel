<?php
require_once(__DIR__ . '/akses_guard.php');
include("../koneksi.php"); // Sesuaikan jalur include() ini

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';
$redirect = "../index.php?menu=kop_sekolah";
$dir = "../assets/img/kop/";

// Pastikan folder tujuan ada
if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}

// Ekstensi gambar yang diizinkan
$ekstensi_diizinkan = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if ($aksi == "tambah") {
    $nama = trim($_POST['nama_kop'] ?? '');
    if ($nama === '') {
        $nama = 'Kop Sekolah';
    }

    $file = $_FILES['gambar'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        header("Location: " . $redirect . "&pesan=invalid");
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $info = @getimagesize($file['tmp_name']);
    if (!in_array($ext, $ekstensi_diizinkan) || $info === false) {
        header("Location: " . $redirect . "&pesan=invalid");
        exit;
    }

    // Nama file unik
    $newName = 'kop_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $newName)) {
        header("Location: " . $redirect . "&pesan=error");
        exit;
    }

    // Bila belum ada kop aktif, yang baru diupload langsung dijadikan aktif
    $ada_aktif = 0;
    $q = $koneksi->query("SELECT COUNT(*) AS c FROM dt_kop_sekolah WHERE status = 'aktif'");
    if ($q) {
        $ada_aktif = (int)$q->fetch_assoc()['c'];
    }
    $status = $ada_aktif > 0 ? 'tidak aktif' : 'aktif';

    $sql = $koneksi->prepare("INSERT INTO dt_kop_sekolah (nama_kop, gambar, status) VALUES (?, ?, ?)");
    $sql->bind_param("sss", $nama, $newName, $status);

    if ($sql->execute()) {
        header("Location: " . $redirect . "&pesan=tambah");
    } else {
        @unlink($dir . $newName);
        header("Location: " . $redirect . "&pesan=error");
    }
    $sql->close();
    exit;
}

if ($aksi == "aktifkan") {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        header("Location: " . $redirect . "&pesan=invalid");
        exit;
    }

    // Hanya satu kop yang aktif
    $koneksi->query("UPDATE dt_kop_sekolah SET status = 'tidak aktif'");
    $sql = $koneksi->prepare("UPDATE dt_kop_sekolah SET status = 'aktif' WHERE id_kop = ?");
    $sql->bind_param("i", $id);

    if ($sql->execute()) {
        header("Location: " . $redirect . "&pesan=edit");
    } else {
        header("Location: " . $redirect . "&pesan=error");
    }
    $sql->close();
    exit;
}

if ($aksi == "hapus") {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        header("Location: " . $redirect . "&pesan=invalid");
        exit;
    }

    $sql = $koneksi->prepare("SELECT gambar, status FROM dt_kop_sekolah WHERE id_kop = ? LIMIT 1");
    $sql->bind_param("i", $id);
    $sql->execute();
    $row = $sql->get_result()->fetch_assoc();
    $sql->close();

    if (!$row) {
        header("Location: " . $redirect . "&pesan=invalid");
        exit;
    }

    $was_active = ($row['status'] === 'aktif');

    $sql = $koneksi->prepare("DELETE FROM dt_kop_sekolah WHERE id_kop = ?");
    $sql->bind_param("i", $id);
    $ok = $sql->execute();
    $sql->close();

    if ($ok) {
        $filePath = $dir . $row['gambar'];
        if (is_file($filePath)) {
            @unlink($filePath);
        }
        // Bila yang dihapus adalah kop aktif, aktifkan kop terbaru (bila ada)
        if ($was_active) {
            $koneksi->query("UPDATE dt_kop_sekolah SET status = 'aktif' ORDER BY id_kop DESC LIMIT 1");
        }
        header("Location: " . $redirect . "&pesan=hapus");
    } else {
        header("Location: " . $redirect . "&pesan=error");
    }
    exit;
}

header("Location: " . $redirect . "&pesan=invalid");
exit;
