<?php
/**
 * Handler referensi "Guru Mapel" (dt_guru_mapel) — global semua tahun ajaran.
 * Aksi: simpan (ganti seluruh mapel yang diampu oleh seorang guru).
 */
require_once(__DIR__ . '/akses_guard.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("../koneksi.php");

function setAlert($type, $msg)
{
    $_SESSION['alert'] = ['type' => $type, 'msg' => $msg];
}

$aksi = $_POST['aksi'] ?? '';
$id_guru = (int)($_POST['id_guru'] ?? 0);
$back = "../index.php?menu=guru_mapel" . ($id_guru > 0 ? "&guru=" . $id_guru : "");

if ($aksi !== 'simpan') {
    setAlert('danger', 'Aksi tidak valid.');
    header("Location: $back");
    exit;
}

if ($id_guru <= 0) {
    setAlert('danger', 'Pilih guru terlebih dahulu.');
    header("Location: ../index.php?menu=guru_mapel");
    exit;
}

// Validasi guru
$q = $koneksi->prepare("SELECT nama_guru FROM dt_guru WHERE id_guru = ?");
$q->bind_param("i", $id_guru);
$q->execute();
$d_guru = $q->get_result()->fetch_assoc();
$q->close();
if (!$d_guru) {
    setAlert('danger', 'Guru tidak ditemukan.');
    header("Location: ../index.php?menu=guru_mapel");
    exit;
}

// Daftar mapel valid
$mapel_valid = [];
$q = $koneksi->query("SELECT id_mapel FROM dt_mapel");
if ($q) {
    while ($m = $q->fetch_assoc()) {
        $mapel_valid[(int)$m['id_mapel']] = true;
    }
}

$mapel_ids = isset($_POST['mapel']) && is_array($_POST['mapel']) ? array_map('intval', $_POST['mapel']) : [];
$target = [];
foreach ($mapel_ids as $idm) {
    if (isset($mapel_valid[$idm]) && !isset($target[$idm])) {
        $target[$idm] = true;
    }
}

$koneksi->begin_transaction();
try {
    $del = $koneksi->prepare("DELETE FROM dt_guru_mapel WHERE id_guru = ?");
    $del->bind_param("i", $id_guru);
    $del->execute();
    $del->close();

    $ins = $koneksi->prepare("INSERT IGNORE INTO dt_guru_mapel (id_guru, id_mapel) VALUES (?, ?)");
    $tersimpan = 0;
    foreach ($target as $idm => $_) {
        $ins->bind_param("ii", $id_guru, $idm);
        $ins->execute();
        $tersimpan += $ins->affected_rows;
    }
    $ins->close();
    $koneksi->commit();

    if ($tersimpan > 0) {
        setAlert('success', 'Mapel untuk <b>' . htmlspecialchars($d_guru['nama_guru']) . '</b> berhasil disimpan (<b>' . $tersimpan . '</b> mapel).');
    } else {
        setAlert('warning', 'Tidak ada mapel dipilih untuk <b>' . htmlspecialchars($d_guru['nama_guru']) . '</b>.');
    }
} catch (Throwable $e) {
    $koneksi->rollback();
    setAlert('danger', 'Gagal menyimpan mapel guru. Error: ' . $koneksi->error);
}
header("Location: $back");
exit;
