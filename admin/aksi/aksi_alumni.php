<?php
require_once(__DIR__ . '/akses_guard.php');
// --- PENGATURAN AWAL & KONEKSI ---
include("../koneksi.php");

// --- FUNGSI BANTUAN: ambil kolom yang dimiliki dt_siswa DAN dt_alumni ---
function kolom_siswa_alumni($koneksi)
{
    $kolom = [];
    $sql = "SELECT s.COLUMN_NAME
            FROM information_schema.COLUMNS s
            INNER JOIN information_schema.COLUMNS a
                ON a.TABLE_SCHEMA = s.TABLE_SCHEMA
                AND a.TABLE_NAME = 'dt_alumni'
                AND a.COLUMN_NAME = s.COLUMN_NAME
            WHERE s.TABLE_SCHEMA = DATABASE()
              AND s.TABLE_NAME = 'dt_siswa'
              AND s.COLUMN_NAME <> 'id_siswa'
            ORDER BY s.ORDINAL_POSITION";
    $result = $koneksi->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $kolom[] = '`' . $row['COLUMN_NAME'] . '`';
        }
    }
    return $kolom;
}

$back_url = "../index.php?menu=alumni";

// ==========================================================
// 1. PINDAHKAN SISWA AKTIF MENJADI ALUMNI
// ==========================================================
if (isset($_POST['pindahkan_alumni'])) {
    $tahun_lulus = trim($_POST['tahun_lulus'] ?? '');
    $ids = array_map('intval', $_POST['ids'] ?? []);

    if ($tahun_lulus === '' || empty($ids)) {
        echo "<script>alert('Pilih minimal satu siswa dan isi tahun lulus terlebih dahulu.');window.history.back();</script>";
        exit;
    }

    $kolom = kolom_siswa_alumni($koneksi);
    if (empty($kolom)) {
        echo "<script>alert('Gagal membaca struktur tabel. Pastikan tabel dt_alumni sudah dibuat (jalankan alumni_siswa.sql).');window.history.back();</script>";
        exit;
    }

    $kolom_sql = implode(', ', $kolom);
    $tahun_lulus_esc = mysqli_real_escape_string($koneksi, $tahun_lulus);
    $sukses = 0;

    mysqli_begin_transaction($koneksi);
    foreach ($ids as $id_siswa) {
        $sql_insert = "INSERT INTO dt_alumni (id_siswa, tahun_lulus, $kolom_sql)
                       SELECT id_siswa, '$tahun_lulus_esc', $kolom_sql
                       FROM dt_siswa WHERE id_siswa = $id_siswa";
        if (mysqli_query($koneksi, $sql_insert) && mysqli_affected_rows($koneksi) > 0) {
            mysqli_query($koneksi, "DELETE FROM dt_kelas_siswa WHERE id_siswa = $id_siswa");
            mysqli_query($koneksi, "DELETE FROM dt_siswa WHERE id_siswa = $id_siswa");
            $sukses++;
        }
    }
    mysqli_commit($koneksi);

    if ($sukses > 0) {
        echo "<script>alert('$sukses siswa berhasil dipindahkan ke alumni.');window.location='$back_url&pesan=pindah';</script>";
    } else {
        echo "<script>alert('Tidak ada data yang dipindahkan.');window.history.back();</script>";
    }
    exit;
}

// ==========================================================
// 2. KEMBALIKAN ALUMNI MENJADI SISWA AKTIF
// ==========================================================
if (isset($_GET['aksi']) && $_GET['aksi'] == 'kembalikan') {
    $id_alumni = (int)($_GET['id'] ?? 0);
    $kolom = kolom_siswa_alumni($koneksi);
    $kolom_sql = implode(', ', $kolom);

    $q_alumni = mysqli_query($koneksi, "SELECT * FROM dt_alumni WHERE id_alumni = $id_alumni");
    $d_alumni = $q_alumni ? mysqli_fetch_assoc($q_alumni) : null;

    if (!$d_alumni) {
        echo "<script>alert('Data alumni tidak ditemukan.');window.location='$back_url';</script>";
        exit;
    }

    $id_siswa_asli = (int)($d_alumni['id_siswa'] ?? 0);
    $cek_id = mysqli_query($koneksi, "SELECT id_siswa FROM dt_siswa WHERE id_siswa = $id_siswa_asli");

    if ($id_siswa_asli > 0 && $cek_id && mysqli_num_rows($cek_id) == 0) {
        $sql_restore = "INSERT INTO dt_siswa (id_siswa, $kolom_sql)
                        SELECT id_siswa, $kolom_sql FROM dt_alumni WHERE id_alumni = $id_alumni";
    } else {
        $sql_restore = "INSERT INTO dt_siswa ($kolom_sql)
                        SELECT $kolom_sql FROM dt_alumni WHERE id_alumni = $id_alumni";
    }

    mysqli_begin_transaction($koneksi);
    $ok = mysqli_query($koneksi, $sql_restore);
    if ($ok) {
        mysqli_query($koneksi, "DELETE FROM dt_alumni WHERE id_alumni = $id_alumni");
        mysqli_commit($koneksi);
        echo "<script>alert('Alumni berhasil dikembalikan menjadi siswa aktif.');window.location='$back_url&pesan=kembali';</script>";
    } else {
        mysqli_rollback($koneksi);
        echo "<script>alert('Gagal mengembalikan data: " . mysqli_error($koneksi) . "');window.history.back();</script>";
    }
    exit;
}

// ==========================================================
// 3. EDIT DATA ALUMNI (Tahun Lulus, Status, Tempat Kerja)
// ==========================================================
if (isset($_POST['update_alumni'])) {
    $id_alumni     = (int)($_POST['id_alumni'] ?? 0);
    $tahun_lulus   = trim($_POST['tahun_lulus'] ?? '');
    $status_kerja  = trim($_POST['status_kerja'] ?? '');
    $tempat_kerja  = trim($_POST['tempat_kerja'] ?? '');

    if ($id_alumni <= 0 || $tahun_lulus === '') {
        echo "<script>alert('Tahun lulus wajib diisi.');window.history.back();</script>";
        exit;
    }

    $tahun_lulus  = mysqli_real_escape_string($koneksi, $tahun_lulus);
    $status_kerja = mysqli_real_escape_string($koneksi, $status_kerja);
    $tempat_kerja = mysqli_real_escape_string($koneksi, $tempat_kerja);

    $sql = "UPDATE dt_alumni SET
              tahun_lulus = '$tahun_lulus',
              status_kerja = " . ($status_kerja === '' ? "NULL" : "'$status_kerja'") . ",
              tempat_kerja = " . ($tempat_kerja === '' ? "NULL" : "'$tempat_kerja'") . "
            WHERE id_alumni = $id_alumni";

    if (mysqli_query($koneksi, $sql)) {
        echo "<script>alert('Data alumni berhasil diupdate.');window.location='$back_url&pesan=edit';</script>";
    } else {
        echo "<script>alert('Gagal mengupdate data: " . mysqli_error($koneksi) . "');window.history.back();</script>";
    }
    exit;
}

// ==========================================================
// 4. HAPUS DATA ALUMNI
// ==========================================================
if (isset($_GET['aksi']) && $_GET['aksi'] == 'hapus') {
    $id_alumni = (int)($_GET['id'] ?? 0);
    if ($id_alumni > 0 && mysqli_query($koneksi, "DELETE FROM dt_alumni WHERE id_alumni = $id_alumni")) {
        echo "<script>alert('Data alumni berhasil dihapus.');window.location='$back_url&pesan=hapus';</script>";
    } else {
        echo "<script>alert('Gagal menghapus data alumni.');window.history.back();</script>";
    }
    exit;
}

// --- AKU TIDAK VALID ---
echo "<script>alert('Aksi tidak valid.');window.location='$back_url';</script>";
exit;
