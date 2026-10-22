<?php
require_once(__DIR__ . '/akses_guard.php');
// ==========================================================
// File: aksi/aksi_jam_pelajaran.php  (KBM-2 referensi waktu)
// - simpan : update waktu mulai/selesai & keterangan semua jam
// - tambah : tambah 1 baris jam baru (jam_ke = max+1)
// - hapus  : hapus 1 jam (ditolak bila masih dipakai di dt_jadwal)
// ==========================================================
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("../koneksi.php");

function setAlert($type, $msg)
{
    $_SESSION['alert'] = ['type' => $type, 'msg' => $msg];
}

$back_url = "../index.php?menu=jadwal";
$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';

// Normalisasi waktu: '' -> NULL, "HH:MM" -> "HH:MM:00"
function norm_waktu($v)
{
    $v = trim((string)$v);
    if ($v === '') {
        return null;
    }
    if (preg_match('/^\d{2}:\d{2}$/', $v)) {
        return $v . ':00';
    }
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $v)) {
        return $v;
    }
    return null;
}

// ==========================================================
// 1. SIMPAN (update massal)
// ==========================================================
if ($aksi == "simpan") {
    $id_jam = isset($_POST['id_jam']) && is_array($_POST['id_jam']) ? $_POST['id_jam'] : [];
    $mulai = isset($_POST['waktu_mulai']) && is_array($_POST['waktu_mulai']) ? $_POST['waktu_mulai'] : [];
    $selesai = isset($_POST['waktu_selesai']) && is_array($_POST['waktu_selesai']) ? $_POST['waktu_selesai'] : [];
    $ket = isset($_POST['keterangan']) && is_array($_POST['keterangan']) ? $_POST['keterangan'] : [];

    if (empty($id_jam)) {
        setAlert('warning', 'Tidak ada jam pelajaran yang dikirim.');
        header("Location: $back_url");
        exit;
    }

    $koneksi->begin_transaction();
    try {
        $upd = $koneksi->prepare("UPDATE dt_jam_pelajaran SET waktu_mulai = ?, waktu_selesai = ?, keterangan = ? WHERE id_jam = ?");
        $n = 0;
        foreach ($id_jam as $i => $idj) {
            $idj = (int)$idj;
            if ($idj <= 0) {
                continue;
            }
            $wm = norm_waktu($mulai[$i] ?? '');
            $ws = norm_waktu($selesai[$i] ?? '');
            $k = trim((string)($ket[$i] ?? ''));
            $k = ($k === '') ? null : $k;
            $upd->bind_param("sssi", $wm, $ws, $k, $idj);
            $upd->execute();
            $n++;
        }
        $upd->close();
        $koneksi->commit();
        setAlert('success', 'Waktu jam pelajaran disimpan (' . $n . ' baris).');
    } catch (Throwable $e) {
        $koneksi->rollback();
        setAlert('danger', 'Gagal menyimpan jam pelajaran. Error: ' . $koneksi->error);
    }
    header("Location: $back_url");
    exit;
}

// ==========================================================
// 2. TAMBAH JAM
// ==========================================================
if ($aksi == "tambah") {
    $q = $koneksi->query("SELECT COALESCE(MAX(jam_ke), 0) + 1 AS next_ke FROM dt_jam_pelajaran");
    $next = ($q && ($r = $q->fetch_assoc())) ? (int)$r['next_ke'] : 1;
    $stmt = $koneksi->prepare("INSERT INTO dt_jam_pelajaran (jam_ke) VALUES (?)");
    $stmt->bind_param("i", $next);
    if ($stmt->execute()) {
        setAlert('success', 'Jam ke-' . $next . ' ditambahkan. Silakan atur waktunya.');
    } else {
        setAlert('danger', 'Gagal menambah jam. Error: ' . $koneksi->error);
    }
    $stmt->close();
    header("Location: $back_url");
    exit;
}

// ==========================================================
// 3. HAPUS JAM
// ==========================================================
if ($aksi == "hapus") {
    $id_jam = (int)($_GET['id'] ?? 0);
    if ($id_jam <= 0) {
        setAlert('danger', 'ID jam tidak valid.');
        header("Location: $back_url");
        exit;
    }

    $q = $koneksi->prepare("SELECT jam_ke FROM dt_jam_pelajaran WHERE id_jam = ?");
    $q->bind_param("i", $id_jam);
    $q->execute();
    $d = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$d) {
        setAlert('warning', 'Jam tidak ditemukan.');
        header("Location: $back_url");
        exit;
    }
    $jam_ke = (int)$d['jam_ke'];

    // Cek pemakaian di jadwal
    $q = $koneksi->prepare("SELECT COUNT(*) AS c FROM dt_jadwal WHERE jam_ke = ?");
    $q->bind_param("i", $jam_ke);
    $q->execute();
    $c = (int)($q->get_result()->fetch_assoc()['c'] ?? 0);
    $q->close();
    if ($c > 0) {
        setAlert('warning', 'Jam ke-' . $jam_ke . ' masih dipakai pada <b>' . $c . '</b> entri jadwal. Hapus/ubah jadwal tersebut terlebih dahulu.');
        header("Location: $back_url");
        exit;
    }

    $stmt = $koneksi->prepare("DELETE FROM dt_jam_pelajaran WHERE id_jam = ?");
    $stmt->bind_param("i", $id_jam);
    if ($stmt->execute()) {
        setAlert('success', 'Jam ke-' . $jam_ke . ' dihapus.');
    } else {
        setAlert('danger', 'Gagal menghapus jam. Error: ' . $koneksi->error);
    }
    $stmt->close();
    header("Location: $back_url");
    exit;
}

setAlert('danger', 'Aksi tidak valid.');
header("Location: $back_url");
exit;
