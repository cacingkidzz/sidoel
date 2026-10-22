<?php
/**
 * Handler Kalender Akademik (dt_kalender_akademik) - KBM-5.
 * Aksi: tambah, edit, hapus.
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

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';

// Verifikasi format tanggal YYYY-MM-DD
function tanggal_valid($t)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$t)) {
        return false;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $t));
    return checkdate($m, $d, $y);
}

// Bangun URL redirect dengan mempertahankan TA & bulan terpilih
function back_url($id_ta = 0, $bulan = '')
{
    $url = "../index.php?menu=kalender_akademik";
    if ($id_ta > 0) {
        $url .= "&ta=" . $id_ta;
    }
    if ($bulan !== '' && preg_match('/^\d{4}-\d{2}$/', $bulan)) {
        $url .= "&bulan=" . $bulan;
    }
    return $url;
}

// ============================================================
// HAPUS
// ============================================================
if ($aksi === 'hapus') {
    $id = (int)($_GET['id'] ?? 0);
    $id_ta = (int)($_GET['ta'] ?? 0);
    $bulan = (string)($_GET['bulan'] ?? '');
    if ($id <= 0) {
        setAlert('danger', 'Kegiatan tidak valid.');
        header("Location: " . back_url($id_ta, $bulan));
        exit;
    }
    $stmt = $koneksi->prepare("DELETE FROM dt_kalender_akademik WHERE id_kegiatan = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        setAlert('success', 'Kegiatan berhasil dihapus.');
    } else {
        setAlert('danger', 'Kegiatan tidak ditemukan atau gagal dihapus.');
    }
    $stmt->close();
    header("Location: " . back_url($id_ta, $bulan));
    exit;
}

// ============================================================
// TAMBAH / EDIT
// ============================================================
if ($aksi === 'tambah' || $aksi === 'edit') {
    $id        = (int)($_POST['id_kegiatan'] ?? 0);
    $id_ta     = (int)($_POST['id_tahun_ajaran'] ?? 0);
    $bulan     = (string)($_POST['redirect_bulan'] ?? '');
    $nama      = trim((string)($_POST['nama_kegiatan'] ?? ''));
    $deskripsi = trim((string)($_POST['deskripsi'] ?? ''));
    $mulai     = trim((string)($_POST['tanggal_mulai'] ?? ''));
    $selesai   = trim((string)($_POST['tanggal_selesai'] ?? ''));
    $semester  = (string)($_POST['semester'] ?? '');
    $jenis     = (string)($_POST['jenis'] ?? 'Kegiatan');

    $jenis_valid = ['Kegiatan', 'Libur', 'Ujian', 'Rapat', 'Lainnya'];
    if (!in_array($jenis, $jenis_valid, true)) {
        $jenis = 'Kegiatan';
    }
    $semester = in_array($semester, ['Ganjil', 'Genap'], true) ? $semester : null;

    if ($nama === '' || $id_ta <= 0 || !tanggal_valid($mulai)) {
        setAlert('danger', 'Nama kegiatan, tahun ajaran, dan tanggal mulai wajib diisi dengan benar.');
        header("Location: " . back_url($id_ta, $bulan));
        exit;
    }
    if ($selesai !== '' && !tanggal_valid($selesai)) {
        setAlert('warning', 'Tanggal selesai tidak valid.');
        header("Location: " . back_url($id_ta, $bulan));
        exit;
    }
    if ($selesai === '' || $selesai < $mulai) {
        $selesai = $mulai;
    }

    // Validasi tahun ajaran
    $stmt = $koneksi->prepare("SELECT tahun_ajaran FROM dt_tahun_ajar WHERE id_tahun_ajaran = ? LIMIT 1");
    $stmt->bind_param("i", $id_ta);
    $stmt->execute();
    $ta_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$ta_row) {
        setAlert('danger', 'Tahun ajaran tidak ditemukan.');
        header("Location: " . back_url($id_ta, $bulan));
        exit;
    }

    $deskripsi = $deskripsi !== '' ? $deskripsi : null;
    $semester_bind = $semester; // mysqli bind null via variable

    if ($aksi === 'edit') {
        if ($id <= 0) {
            setAlert('danger', 'Data kegiatan tidak lengkap.');
            header("Location: " . back_url($id_ta, $bulan));
            exit;
        }
        $stmt = $koneksi->prepare("UPDATE dt_kalender_akademik
            SET id_tahun_ajaran=?, nama_kegiatan=?, deskripsi=?, tanggal_mulai=?, tanggal_selesai=?, semester=?, jenis=?
            WHERE id_kegiatan=?");
        $stmt->bind_param("issssssi", $id_ta, $nama, $deskripsi, $mulai, $selesai, $semester_bind, $jenis, $id);
        if ($stmt->execute()) {
            setAlert('success', 'Kegiatan <b>' . htmlspecialchars($nama) . '</b> berhasil diperbarui.');
        } else {
            setAlert('danger', 'Gagal memperbarui kegiatan. Error: ' . $koneksi->error);
        }
        $stmt->close();
    } else {
        $stmt = $koneksi->prepare("INSERT INTO dt_kalender_akademik
            (id_tahun_ajaran, nama_kegiatan, deskripsi, tanggal_mulai, tanggal_selesai, semester, jenis)
            VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("issssss", $id_ta, $nama, $deskripsi, $mulai, $selesai, $semester_bind, $jenis);
        if ($stmt->execute()) {
            setAlert('success', 'Kegiatan <b>' . htmlspecialchars($nama) . '</b> berhasil ditambahkan.');
        } else {
            setAlert('danger', 'Gagal menambahkan kegiatan. Error: ' . $koneksi->error);
        }
        $stmt->close();
    }

    header("Location: " . back_url($id_ta, $bulan));
    exit;
}

setAlert('danger', 'Aksi tidak valid.');
header("Location: " . back_url());
exit;
