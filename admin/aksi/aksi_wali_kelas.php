<?php
require_once(__DIR__ . '/akses_guard.php');
// ===============================================
// File: aksi/aksi_wali_kelas.php
// CRUD Penugasan Wali Kelas (dt_wali_kelas)
// - Tambah: kelas + guru (1 kelas = 1 wali per TA)
// - Edit: ganti guru
// - Hapus: lepas penugasan
// ===============================================
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("../koneksi.php");

function setAlert($type, $msg)
{
    $_SESSION['alert'] = ['type' => $type, 'msg' => $msg];
}

$back_url = "../index.php?menu=wali_kelas";
$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';

// ==========================================================
// 1. TAMBAH
// ==========================================================
if ($aksi == "tambah") {
    $id_ta = (int)($_POST['id_tahun_ajaran'] ?? 0);
    $id_kelas = (int)($_POST['id_kelas'] ?? 0);
    $id_guru = (int)($_POST['id_guru'] ?? 0);

    if ($id_ta <= 0 || $id_kelas <= 0 || $id_guru <= 0) {
        setAlert('danger', 'Data tidak lengkap. Silakan pilih kelas dan guru.');
        header("Location: $back_url");
        exit;
    }

    // Validasi: kelas harus milik TA ini
    $q_kelas = $koneksi->prepare("SELECT nama_kelas FROM dt_kelas WHERE id_kelas = ? AND id_tahun_ajaran = ?");
    $q_kelas->bind_param("ii", $id_kelas, $id_ta);
    $q_kelas->execute();
    $d_kelas = $q_kelas->get_result()->fetch_assoc();
    if (!$d_kelas) {
        setAlert('danger', 'Kelas tidak ditemukan pada tahun ajaran ini.');
        header("Location: $back_url");
        exit;
    }

    // Validasi: guru harus ada
    $q_guru = $koneksi->prepare("SELECT nama_guru FROM dt_guru WHERE id_guru = ?");
    $q_guru->bind_param("i", $id_guru);
    $q_guru->execute();
    $d_guru = $q_guru->get_result()->fetch_assoc();
    if (!$d_guru) {
        setAlert('danger', 'Guru tidak ditemukan.');
        header("Location: $back_url");
        exit;
    }

    // Validasi: kelas belum punya wali (UNIQUE id_kelas)
    $q_dup = $koneksi->prepare("SELECT id_wali_kelas FROM dt_wali_kelas WHERE id_kelas = ?");
    $q_dup->bind_param("i", $id_kelas);
    $q_dup->execute();
    if ($q_dup->get_result()->num_rows > 0) {
        setAlert('warning', 'Kelas <b>' . htmlspecialchars($d_kelas['nama_kelas']) . '</b> sudah memiliki wali kelas.');
        header("Location: $back_url");
        exit;
    }

    // Validasi: guru belum jadi wali lain di TA ini (kebijakan 1 guru = 1 wali kelas per TA)
    $q_gdup = $koneksi->prepare("SELECT id_wali_kelas FROM dt_wali_kelas WHERE id_guru = ? AND id_tahun_ajaran = ?");
    $q_gdup->bind_param("ii", $id_guru, $id_ta);
    $q_gdup->execute();
    if ($q_gdup->get_result()->num_rows > 0) {
        setAlert('warning', 'Guru <b>' . htmlspecialchars($d_guru['nama_guru']) . '</b> sudah menjadi wali kelas lain pada tahun ajaran ini.');
        header("Location: $back_url");
        exit;
    }

    $stmt = $koneksi->prepare("INSERT INTO dt_wali_kelas (id_guru, id_kelas, id_tahun_ajaran) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $id_guru, $id_kelas, $id_ta);
    if ($stmt->execute()) {
        setAlert('success', 'Wali kelas <b>' . htmlspecialchars($d_guru['nama_guru']) . '</b> berhasil ditugaskan untuk kelas <b>' . htmlspecialchars($d_kelas['nama_kelas']) . '</b>.');
    } else {
        setAlert('danger', 'Gagal menambah wali kelas. Error: ' . $koneksi->error);
    }
    header("Location: $back_url");
    exit;
}

// ==========================================================
// 2. EDIT (ganti guru)
// ==========================================================
if ($aksi == "edit") {
    $id_wali = (int)($_POST['id_wali_kelas'] ?? 0);
    $id_kelas = (int)($_POST['id_kelas'] ?? 0);
    $id_guru = (int)($_POST['id_guru'] ?? 0);

    if ($id_wali <= 0 || $id_kelas <= 0 || $id_guru <= 0) {
        setAlert('danger', 'Data tidak lengkap.');
        header("Location: $back_url");
        exit;
    }

    // Cek baris wali yang mau diedit
    $q_wali = $koneksi->prepare("
        SELECT wk.id_wali_kelas, wk.id_tahun_ajaran, k.nama_kelas
        FROM dt_wali_kelas wk
        INNER JOIN dt_kelas k ON wk.id_kelas = k.id_kelas
        WHERE wk.id_wali_kelas = ?
    ");
    $q_wali->bind_param("i", $id_wali);
    $q_wali->execute();
    $d_wali = $q_wali->get_result()->fetch_assoc();
    if (!$d_wali) {
        setAlert('danger', 'Data penugasan wali kelas tidak ditemukan.');
        header("Location: $back_url");
        exit;
    }

    // Cek guru ada
    $q_guru = $koneksi->prepare("SELECT nama_guru FROM dt_guru WHERE id_guru = ?");
    $q_guru->bind_param("i", $id_guru);
    $q_guru->execute();
    $d_guru = $q_guru->get_result()->fetch_assoc();
    if (!$d_guru) {
        setAlert('danger', 'Guru tidak ditemukan.');
        header("Location: $back_url");
        exit;
    }

    // Jika ganti guru ke guru yang sudah jadi wali lain di TA sama -> tolak
    $q_gdup = $koneksi->prepare("SELECT id_wali_kelas FROM dt_wali_kelas WHERE id_guru = ? AND id_tahun_ajaran = ? AND id_wali_kelas <> ?");
    $q_gdup->bind_param("iii", $id_guru, $d_wali['id_tahun_ajaran'], $id_wali);
    $q_gdup->execute();
    if ($q_gdup->get_result()->num_rows > 0) {
        setAlert('warning', 'Guru <b>' . htmlspecialchars($d_guru['nama_guru']) . '</b> sudah menjadi wali kelas lain pada tahun ajaran ini.');
        header("Location: $back_url");
        exit;
    }

    $stmt = $koneksi->prepare("UPDATE dt_wali_kelas SET id_guru = ? WHERE id_wali_kelas = ?");
    $stmt->bind_param("ii", $id_guru, $id_wali);
    if ($stmt->execute()) {
        setAlert('success', 'Wali kelas kelas <b>' . htmlspecialchars($d_wali['nama_kelas']) . '</b> berhasil diganti menjadi <b>' . htmlspecialchars($d_guru['nama_guru']) . '</b>.');
    } else {
        setAlert('danger', 'Gagal mengupdate wali kelas. Error: ' . $koneksi->error);
    }
    header("Location: $back_url");
    exit;
}

// ==========================================================
// 3. HAPUS (lepas penugasan)
// ==========================================================
if ($aksi == "hapus") {
    $id_wali = (int)($_GET['id'] ?? 0);
    if ($id_wali <= 0) {
        setAlert('danger', 'ID penugasan tidak valid.');
        header("Location: $back_url");
        exit;
    }

    // Ambil nama untuk pesan
    $q = $koneksi->prepare("
        SELECT g.nama_guru, k.nama_kelas
        FROM dt_wali_kelas wk
        INNER JOIN dt_guru g ON wk.id_guru = g.id_guru
        INNER JOIN dt_kelas k ON wk.id_kelas = k.id_kelas
        WHERE wk.id_wali_kelas = ?
    ");
    $q->bind_param("i", $id_wali);
    $q->execute();
    $d = $q->get_result()->fetch_assoc();

    $stmt = $koneksi->prepare("DELETE FROM dt_wali_kelas WHERE id_wali_kelas = ?");
    $stmt->bind_param("i", $id_wali);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            setAlert('success', 'Wali kelas <b>' . htmlspecialchars($d['nama_guru'] ?? '') . '</b> untuk kelas <b>' . htmlspecialchars($d['nama_kelas'] ?? '') . '</b> berhasil dilepas.');
        } else {
            setAlert('warning', 'Data penugasan tidak ditemukan.');
        }
    } else {
        setAlert('danger', 'Gagal menghapus penugasan wali kelas. Error: ' . $koneksi->error);
    }
    header("Location: $back_url");
    exit;
}

// ==========================================================
// AKSI TIDAK VALID
// ==========================================================
setAlert('danger', 'Aksi tidak valid.');
header("Location: $back_url");
exit;
