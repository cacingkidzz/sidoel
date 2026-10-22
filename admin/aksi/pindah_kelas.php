<?php
require_once(__DIR__ . '/akses_guard.php');
// ===============================================
// File: aksi/pindah_kelas.php
// Memindahkan siswa yang SUDAH punya kelas ke rombel lain
// pada tahun ajaran yang sama (mutasi/pindah kelas).
// ===============================================

include("../koneksi.php");

// Fungsi untuk redirect dengan pesan
function redirect_with_message($status, $pesan)
{
    header("Location: ../index.php?menu=penempatan_kelas&status=" . urlencode($status) . "&pesan=" . urlencode($pesan));
    exit();
}

// 1. Cek apakah form disubmit
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['pindah_kelas'])) {
    redirect_with_message('danger', 'Akses tidak sah.');
}

// 2. Ambil data dari POST
$id_ta_aktif     = isset($_POST['id_tahun_ajaran']) ? (int)$_POST['id_tahun_ajaran'] : 0;
$id_kelas_asal   = isset($_POST['id_kelas_asal']) ? (int)$_POST['id_kelas_asal'] : 0;
$id_kelas_tujuan = isset($_POST['id_kelas_tujuan']) ? (int)$_POST['id_kelas_tujuan'] : 0;
$siswa_ids       = $_POST['siswa_id_pindah'] ?? [];

// 3. Validasi input dasar
if ($id_ta_aktif <= 0 || $id_kelas_tujuan <= 0 || empty($siswa_ids)) {
    redirect_with_message('danger', 'Data tidak lengkap. Pilih minimal satu siswa dan kelas tujuan.');
}

// 4. Ambil detail kelas tujuan (untuk update jurusan & rombel di dt_siswa)
$q_kelas_target = $koneksi->query("
    SELECT k.tingkat, k.nama_kelas, j.nama_jurusan
    FROM dt_kelas k
    JOIN dt_jurusan j ON k.id_jurusan = j.id_jurusan
    WHERE k.id_kelas = '$id_kelas_tujuan' AND k.id_tahun_ajaran = '$id_ta_aktif'
");

if (!$q_kelas_target || $q_kelas_target->num_rows == 0) {
    redirect_with_message('danger', 'Kelas tujuan tidak ditemukan pada tahun ajaran ini.');
}

$d_kelas_target = $q_kelas_target->fetch_assoc();
$jurusan_baru = $koneksi->real_escape_string($d_kelas_target['nama_jurusan']);
$rombel_baru  = $koneksi->real_escape_string($d_kelas_target['tingkat'] . '-' . $d_kelas_target['nama_kelas']);

$total_siswa = count($siswa_ids);

// 5. Transaksi: pindahkan semua siswa atau batalkan semua
$koneksi->begin_transaction();
$sukses_count = 0;

try {
    foreach ($siswa_ids as $id_siswa) {
        $id_siswa_clean = (int)$id_siswa;
        if ($id_siswa_clean <= 0) {
            continue;
        }

        // A. Cek keberadaan baris penempatan pada TA aktif
        $q_cek = $koneksi->query("
            SELECT id_kelas_siswa
            FROM dt_kelas_siswa
            WHERE id_siswa = '$id_siswa_clean' AND id_tahun_ajaran = '$id_ta_aktif'
            LIMIT 1
        ");

        if ($q_cek && $q_cek->num_rows > 0) {
            // Pindah = UPDATE baris penempatan yang ada (UNIQUE id_siswa+id_tahun_ajaran)
            $row_ks = $q_cek->fetch_assoc();
            $id_ks_clean = (int)$row_ks['id_kelas_siswa'];
            $q_pindah = "
                UPDATE dt_kelas_siswa
                SET id_kelas = '$id_kelas_tujuan', keterangan = 'Pindah Kelas'
                WHERE id_kelas_siswa = '$id_ks_clean'
            ";
        } else {
            // Fallback: belum punya penempatan di TA ini -> INSERT
            $q_pindah = "
                INSERT INTO dt_kelas_siswa (id_siswa, id_kelas, id_tahun_ajaran, keterangan)
                VALUES ('$id_siswa_clean', '$id_kelas_tujuan', '$id_ta_aktif', 'Pindah Kelas')
            ";
        }

        if (!$koneksi->query($q_pindah)) {
            throw new Exception("Gagal memindahkan siswa ID $id_siswa_clean: " . $koneksi->error);
        }

        // B. Sinkronkan jurusan & rombel pada data pokok siswa
        $q_update = "
            UPDATE dt_siswa
            SET jurusan = '$jurusan_baru',
                rombel = '$rombel_baru'
            WHERE id_siswa = '$id_siswa_clean'
        ";

        if (!$koneksi->query($q_update)) {
            throw new Exception("Gagal memperbarui data siswa ID $id_siswa_clean: " . $koneksi->error);
        }

        $sukses_count++;
    }

    $koneksi->commit();
    redirect_with_message('success', "$sukses_count dari $total_siswa siswa berhasil dipindahkan ke rombel $rombel_baru.");
} catch (Exception $e) {
    $koneksi->rollback();
    redirect_with_message('danger', 'Gagal memindahkan siswa. Operasi dibatalkan. Detail Error: ' . $e->getMessage());
}

$koneksi->close();
