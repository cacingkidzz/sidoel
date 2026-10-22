<?php
require_once(__DIR__ . '/akses_guard.php');
// ===============================================
// File: aksi/alokasi_kelas.php
// ===============================================

// 1. Pastikan file koneksi sudah ter-include
include("../koneksi.php");

// Fungsi untuk redirect dengan pesan
function redirect_with_message($status, $pesan)
{
    header("Location: ../index.php?menu=penempatan_kelas&status=" . urlencode($status) . "&pesan=" . urlencode($pesan));
    exit();
}

// 2. Cek apakah form disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['alokasi_manual_massal'])) {

    // 3. Ambil data dari POST
    $id_ta_aktif = $_POST['id_tahun_ajaran'] ?? null;
    $id_kelas_target = $_POST['id_kelas_target'] ?? null;
    $siswa_ids = $_POST['siswa_id_terpilih'] ?? [];

    // 4. Validasi input dasar
    if (empty($id_ta_aktif) || empty($id_kelas_target) || empty($siswa_ids)) {
        redirect_with_message('danger', 'Data tidak lengkap. Pastikan Anda memilih kelas target dan minimal satu siswa.');
    }

    // Mengamankan data (Sanitasi dan Escaping)
    $id_ta_aktif = $koneksi->real_escape_string($id_ta_aktif);
    $id_kelas_target = $koneksi->real_escape_string($id_kelas_target);
    $total_siswa = count($siswa_ids);

    // 5. Ambil detail kelas target untuk update dt_siswa
    $q_kelas_target = $koneksi->query("
        SELECT k.tingkat, k.nama_kelas, j.id_jurusan, j.nama_jurusan
        FROM dt_kelas k
        JOIN dt_jurusan j ON k.id_jurusan = j.id_jurusan
        WHERE k.id_kelas = '$id_kelas_target'
    ");

    if (!$q_kelas_target || $q_kelas_target->num_rows == 0) {
        redirect_with_message('danger', 'Kelas target tidak ditemukan.');
    }

    $d_kelas_target = $q_kelas_target->fetch_assoc();
    $jurusan_siswa_baru = $koneksi->real_escape_string($d_kelas_target['nama_jurusan']);
    $rombel_siswa_baru = $koneksi->real_escape_string($d_kelas_target['tingkat'] . '-' . $d_kelas_target['nama_kelas']);

    // Mulai Transaksi untuk memastikan semua operasi sukses atau tidak sama sekali
    $koneksi->begin_transaction();
    $sukses_count = 0;

    try {
        // 6. Loop dan Proses Setiap Siswa
        foreach ($siswa_ids as $id_siswa) {
            $id_siswa_clean = $koneksi->real_escape_string($id_siswa);

            // A. INSERT ke dt_kelas_siswa (Penempatan Kelas)
            $q_insert = "
                INSERT INTO dt_kelas_siswa (id_siswa, id_kelas, id_tahun_ajaran)
                VALUES ('$id_siswa_clean', '$id_kelas_target', '$id_ta_aktif')
            ";

            if (!$koneksi->query($q_insert)) {
                // Jika insert gagal, batalkan transaksi dan beri pesan error
                throw new Exception("Gagal memasukkan data ke dt_kelas_siswa untuk siswa ID $id_siswa_clean: " . $koneksi->error);
            }

            // B. UPDATE dt_siswa (Perbarui Jurusan dan Rombel di data pokok siswa)
            // Ini akan memastikan data pokok siswa (jurusan dan rombel) terstandardisasi sesuai kelas yang dialokasikan
            $q_update = "
                UPDATE dt_siswa
                SET jurusan = '$jurusan_siswa_baru',
                    rombel = '$rombel_siswa_baru'
                WHERE id_siswa = '$id_siswa_clean'
            ";

            if (!$koneksi->query($q_update)) {
                // Jika update gagal, batalkan transaksi dan beri pesan error
                throw new Exception("Gagal memperbarui dt_siswa untuk siswa ID $id_siswa_clean: " . $koneksi->error);
            }

            $sukses_count++;
        }

        // 7. Commit Transaksi jika semua berhasil
        $koneksi->commit();
        redirect_with_message('success', "$sukses_count dari $total_siswa siswa berhasil dialokasikan ke kelas **$rombel_siswa_baru**.");
    } catch (Exception $e) {
        // Rollback jika terjadi kesalahan
        $koneksi->rollback();
        redirect_with_message('danger', 'Gagal memproses alokasi kelas. Operasi dibatalkan. Detail Error: ' . $e->getMessage());
    }
} else {
    // Jika diakses tanpa POST
    redirect_with_message('danger', 'Akses tidak sah.');
}

// Tutup koneksi
$koneksi->close();
