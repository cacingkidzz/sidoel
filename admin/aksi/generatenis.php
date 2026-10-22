<?php
require_once(__DIR__ . '/akses_guard.php');
// ===============================================
// File: aksi/generatenis.php
// Penomoran NIS otomatis untuk siswa yang sudah
// mendapatkan alokasi kelas tapi belum punya NIS.
// Format: lanjutan 4 digit dari NIS terbesar
// (mis. 5922, 5923, ...).
// ===============================================
include("../koneksi.php");

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$back_url = "../index.php?menu=penempatan_kelas";

if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['generate_nis'])) {
    echo "<script>alert('Akses tidak sah.');window.location='$back_url';</script>";
    exit;
}

$id_ta = (int)($_POST['id_tahun_ajaran'] ?? 0);
$ids = array_map('intval', $_POST['siswa_to_process'] ?? []);

if ($id_ta <= 0 || empty($ids)) {
    echo "<script>alert('Tidak ada siswa yang siap menerima NIS.');window.location='$back_url';</script>";
    exit;
}

// --- Hitung nomor awal: NIS terbesar + 1 ---
$q_max = $koneksi->query("SELECT MAX(CAST(NULLIF(nipd, '') AS UNSIGNED)) AS max_nis FROM dt_siswa");
$row_max = $q_max ? $q_max->fetch_assoc() : null;
$nis_sekarang = (int)($row_max['max_nis'] ?? 0) + 1;
if ($nis_sekarang <= 1) {
    $nis_sekarang = 5922;
}
$nis_awal = $nis_sekarang;

$koneksi->begin_transaction();
$sukses = 0;
$lewati = 0;
$gagal = [];

try {
    foreach ($ids as $id_siswa) {
        // Cek status terkini: hanya proses siswa yang masih BELUM punya NIS
        $q_siswa = $koneksi->prepare("SELECT nama, nipd FROM dt_siswa WHERE id_siswa = ? AND id_tahun_ajaran = ?");
        $q_siswa->bind_param("ii", $id_siswa, $id_ta);
        $q_siswa->execute();
        $d_siswa = $q_siswa->get_result()->fetch_assoc();

        if (!$d_siswa) {
            $lewati++;
            continue;
        }
        if ($d_siswa['nipd'] !== null && trim($d_siswa['nipd']) !== '') {
            $lewati++; // sudah punya NIS (mis. sudah diproses di run sebelumnya)
            continue;
        }

        // Cek duplikat defensif
        $nis_str = (string)$nis_sekarang;
        $q_dup = $koneksi->prepare("SELECT COUNT(*) AS jml FROM dt_siswa WHERE nipd = ?");
        $q_dup->bind_param("s", $nis_str);
        $q_dup->execute();
        $h_dup = $q_dup->get_result()->fetch_assoc();
        if ($h_dup['jml'] > 0) {
            throw new Exception("NIS $nis_str sudah dipakai siswa lain. Penomoran dihentikan agar tidak duplikat.");
        }

        $q_upd = $koneksi->prepare("UPDATE dt_siswa SET nipd = ? WHERE id_siswa = ? AND (nipd IS NULL OR nipd = '')");
        $q_upd->bind_param("si", $nis_str, $id_siswa);
        if (!$q_upd->execute()) {
            throw new Exception("Gagal menetapkan NIS $nis_str untuk siswa ID $id_siswa.");
        }

        $nis_sekarang++;
        $sukses++;
    }

    if ($sukses == 0) {
        $koneksi->rollback();
        echo "<script>alert('Tidak ada siswa yang diberi NIS. Semua siswa sudah memiliki NIS.');window.location='$back_url';</script>";
        exit;
    }

    $koneksi->commit();

    $pesan_alert = "$sukses siswa berhasil diberi NIS (mulai dari $nis_awal s/d " . ($nis_sekarang - 1) . ").";
    if ($lewati > 0) {
        $pesan_alert .= " $lewati siswa dilewati karena sudah memiliki NIS.";
    }
    $pesan_get = urlencode("$sukses siswa berhasil diberi NIS mulai dari nomor $nis_awal.");
    echo "<script>alert('$pesan_alert');window.location='$back_url&status=success&pesan=$pesan_get';</script>";
    exit;
} catch (Exception $e) {
    $koneksi->rollback();
    $err = urlencode('Penomoran NIS dibatalkan: ' . $e->getMessage());
    echo "<script>alert('Penomoran NIS dibatalkan.');window.location='$back_url&status=danger&pesan=$err';</script>";
    exit;
}
