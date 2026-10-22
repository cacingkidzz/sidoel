<?php
require_once(__DIR__ . '/akses_guard.php');
// ===============================================
// File: aksi/alokasi_otomatis.php
// Penempatan otomatis siswa ke kelas:
// - Jumlah siswa dibagi rata ke kelas terpilih
// - Jenis kelamin (L/P) diusahakan merata di tiap kelas
// - Urutan siswa berdasarkan nama
// ===============================================
include("../koneksi.php");

function redirect_with_message($status, $pesan)
{
    header("Location: ../index.php?menu=penempatan_kelas&status=" . urlencode($status) . "&pesan=" . urlencode($pesan));
    exit();
}

// Bagi rata round-robin: L dan P didistribusikan TERPISAH agar tiap kelas
// kebagian porsi L dan P yang seimbang (selisih <= 1 per gender).
// offset P=1 agar posisi "ekor" (sisa pembagian) tidak menumpuk di kelas yang sama.
function distribusiGender($siswa, $kelas_ids, $offset = 0)
{
    $hasil = [];
    $n = count($kelas_ids);
    foreach ($siswa as $i => $s) {
        $hasil[$kelas_ids[($i + $offset) % $n]][] = $s;
    }
    return $hasil;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['alokasi_otomatis'])) {

    $id_ta_aktif    = (int)($_POST['id_tahun_ajaran'] ?? 0);
    $id_jurusan     = (int)($_POST['id_jurusan'] ?? 0);
    $kelas_terpilih = array_map('intval', $_POST['id_kelas_terpilih'] ?? []);

    // Validasi input dasar
    if ($id_ta_aktif <= 0 || $id_jurusan <= 0 || empty($kelas_terpilih)) {
        redirect_with_message('danger', 'Data tidak lengkap. Centang minimal satu kelas target untuk penempatan otomatis.');
    }

    // Validasi kelas: harus milik jurusan & tahun ajaran ini
    $in_kelas = implode(',', $kelas_terpilih);
    $q_kelas = $koneksi->query("
        SELECT k.id_kelas, k.nama_kelas, k.tingkat, j.nama_jurusan
        FROM dt_kelas k
        JOIN dt_jurusan j ON k.id_jurusan = j.id_jurusan
        WHERE k.id_kelas IN ($in_kelas)
          AND k.id_jurusan = $id_jurusan
          AND k.id_tahun_ajaran = $id_ta_aktif
        ORDER BY k.nama_kelas ASC
    ");

    if (!$q_kelas || $q_kelas->num_rows == 0) {
        redirect_with_message('danger', 'Kelas target tidak ditemukan atau bukan milik jurusan ini.');
    }

    $kelas_list = [];
    while ($row = $q_kelas->fetch_assoc()) {
        $kelas_list[] = $row;
    }

    // Ambil siswa jurusan ini yang BELUM punya kelas, urut berdasarkan NAMA
    $q_siswa = $koneksi->query("
        SELECT s.id_siswa, s.nama, s.jk
        FROM dt_siswa s
        JOIN dt_jurusan j ON s.jurusan = j.nama_jurusan
        LEFT JOIN dt_kelas_siswa ks ON s.id_siswa = ks.id_siswa AND ks.id_tahun_ajaran = $id_ta_aktif
        WHERE s.id_tahun_ajaran = $id_ta_aktif
          AND j.id_jurusan = $id_jurusan
          AND ks.id_kelas IS NULL
        ORDER BY s.nama ASC
    ");

    if (!$q_siswa || $q_siswa->num_rows == 0) {
        redirect_with_message('danger', 'Tidak ada siswa jurusan ini yang belum memiliki kelas.');
    }

    $laki = [];
    $cewe = [];
    while ($row = $q_siswa->fetch_assoc()) {
        if (strtoupper(trim($row['jk'])) === 'P') {
            $cewe[] = $row;
        } else {
            $laki[] = $row;
        }
    }

    // 1. Kumpulkan ID kelas terpilih (sudah terurut nama_kelas)
    $id_kelas_urut = array_column($kelas_list, 'id_kelas');

    // 2. Distribusi TERPISAH per gender (round-robin).
    //    L dari urutan kelas normal, P dari urutan TERBALIK:
    //    sisa pembagian L menumpuk di kelas awal, sisa P di kelas akhir -> saling menyeimbangkan,
    //    sehingga jumlah total per kelas selisih <= 1 dan proporsi L/P tetap merata.
    $distribusi_l = distribusiGender($laki, $id_kelas_urut, 0);
    $distribusi_p = distribusiGender($cewe, array_reverse($id_kelas_urut), 0);

    $siswa_per_kelas = [];
    foreach ($id_kelas_urut as $id_kelas) {
        $siswa_per_kelas[$id_kelas] = [];
        foreach ($distribusi_l[$id_kelas] ?? [] as $s) {
            $siswa_per_kelas[$id_kelas][] = $s;
        }
        foreach ($distribusi_p[$id_kelas] ?? [] as $s) {
            $siswa_per_kelas[$id_kelas][] = $s;
        }
        // Urutkan nama di tiap kelas agar rapi
        usort($siswa_per_kelas[$id_kelas], function ($a, $b) {
            return strcmp($a['nama'], $b['nama']);
        });
    }

    // 3. Eksekusi dalam transaksi: INSERT dt_kelas_siswa + UPDATE dt_siswa
    $koneksi->begin_transaction();
    $sukses_count = 0;
    $rincian = [];

    try {
        foreach ($kelas_list as $kelas) {
            $id_kelas = $kelas['id_kelas'];
            $rombel_baru = $kelas['tingkat'] . '-' . $kelas['nama_kelas'];
            $jurusan_esc = $koneksi->real_escape_string($kelas['nama_jurusan']);
            $rombel_esc  = $koneksi->real_escape_string($rombel_baru);

            foreach ($siswa_per_kelas[$id_kelas] as $siswa) {
                $id_siswa = (int)$siswa['id_siswa'];

                $q_insert = "INSERT INTO dt_kelas_siswa (id_siswa, id_kelas, id_tahun_ajaran)
                             VALUES ('$id_siswa', '$id_kelas', '$id_ta_aktif')";
                if (!$koneksi->query($q_insert)) {
                    throw new Exception("Gagal insert dt_kelas_siswa untuk siswa ID $id_siswa: " . $koneksi->error);
                }

                $q_update = "UPDATE dt_siswa
                             SET jurusan = '$jurusan_esc', rombel = '$rombel_esc'
                             WHERE id_siswa = '$id_siswa'";
                if (!$koneksi->query($q_update)) {
                    throw new Exception("Gagal update dt_siswa untuk siswa ID $id_siswa: " . $koneksi->error);
                }

                $sukses_count++;
            }

            $jml_l = 0;
            $jml_p = 0;
            foreach ($siswa_per_kelas[$id_kelas] as $s) {
                if (strtoupper(trim($s['jk'])) === 'P') $jml_p++;
                else $jml_l++;
            }
            $rincian[] = $kelas['nama_kelas'] . ' (' . count($siswa_per_kelas[$id_kelas]) . ': L' . $jml_l . '/P' . $jml_p . ')';
        }

        $koneksi->commit();

        $pesan = "$sukses_count siswa berhasil ditempatkan otomatis ke " . count($kelas_list) . " kelas. Rincian: " . implode(', ', $rincian) . ".";
        redirect_with_message('success', $pesan);
    } catch (Exception $e) {
        $koneksi->rollback();
        redirect_with_message('danger', 'Penempatan otomatis dibatalkan. Detail Error: ' . $e->getMessage());
    }
} else {
    redirect_with_message('danger', 'Akses tidak sah.');
}

$koneksi->close();
