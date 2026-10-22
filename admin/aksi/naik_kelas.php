<?php
require_once(__DIR__ . '/akses_guard.php');
// ===============================================
// File: aksi/naik_kelas.php
// 3 proses alur kenaikan kelas:
//   1. persiapan_kelas : clone kelas X->XI & XI->XII ke TA target
//   2. kenaikan        : pindahkan siswa X->XI & XI->XII ke TA target
//   3. luluskan        : siswa XII -> dt_alumni, hapus dari dt_siswa
// ===============================================
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("../koneksi.php");

function setAlert($type, $msg)
{
    $_SESSION['alert'] = ['type' => $type, 'msg' => $msg];
}

$back_url = "../index.php?menu=kenaikan_kelas";
$aksi = $_POST['aksi'] ?? '';

// ==========================================================
// 0. VALIDASI DASAR
// ==========================================================
$id_ta_sumber = (int)($_POST['id_ta_sumber'] ?? 0);
$id_ta_target = (int)($_POST['id_ta_target'] ?? 0);

// TA target harus aktif sesi (kecuali untuk aksi 'luluskan' yang tidak butuh target)
if ($aksi !== 'luluskan') {
    if ($id_ta_sumber <= 0 || $id_ta_target <= 0) {
        setAlert('danger', 'Tahun ajaran sumber/target tidak valid.');
        header("Location: $back_url");
        exit;
    }
}

// ==========================================================
// 1. PERSIAPAN KELAS (clone X->XI, XI->XII ke TA target)
// ==========================================================
if ($aksi == "persiapan_kelas") {
    if ($id_ta_sumber <= 0 || $id_ta_target <= 0) {
        setAlert('danger', 'Tahun ajaran tidak valid.');
        header("Location: $back_url");
        exit;
    }

    $koneksi->begin_transaction();
    try {
        $clone = [
            ['X', 'XI'],
            ['XI', 'XII'],
        ];
        $jml_clone = 0;

        foreach ($clone as [$dari, $ke]) {
            // Ambil kelas sumber yang BELUM punya padanan di target (nama+jurusan+tingkat baru)
            $q = $koneksi->query("
                SELECT ks.nama_kelas, ks.id_jurusan, ks.keterangan
                FROM dt_kelas ks
                WHERE ks.id_tahun_ajaran = '$id_ta_sumber'
                  AND ks.tingkat = '$dari'
                  AND NOT EXISTS (
                      SELECT 1 FROM dt_kelas kt
                      WHERE kt.id_tahun_ajaran = '$id_ta_target'
                        AND kt.tingkat = '$ke'
                        AND kt.nama_kelas = ks.nama_kelas
                        AND kt.id_jurusan = ks.id_jurusan
                  )
            ");
            if (!$q) throw new Exception("Gagal membaca kelas sumber: " . $koneksi->error);

            while ($k = $q->fetch_assoc()) {
                $nama = $koneksi->real_escape_string($k['nama_kelas']);
                $ket = $koneksi->real_escape_string('Naik tingkat otomatis dari ' . $dari . ' TA ' . $id_ta_sumber);
                $sql = "INSERT INTO dt_kelas (nama_kelas, tingkat, id_jurusan, id_tahun_ajaran, keterangan)
                        VALUES ('$nama', '$ke', '" . (int)$k['id_jurusan'] . "', '$id_ta_target', '$ket')";
                if (!$koneksi->query($sql)) {
                    throw new Exception("Gagal clone kelas $nama ($dari->$ke): " . $koneksi->error);
                }
                $jml_clone++;
            }
        }

        if ($jml_clone == 0) {
            $koneksi->rollback();
            setAlert('info', 'Semua kelas XI &amp; XII tahun baru sudah ada — tidak ada yang perlu dibuat.');
            header("Location: $back_url");
            exit;
        }

        $koneksi->commit();
        setAlert('success', "$jml_clone kelas berhasil dibuat otomatis (XI dari kelas X, XII dari kelas XI).");
        header("Location: $back_url");
        exit;
    } catch (Exception $e) {
        $koneksi->rollback();
        setAlert('danger', 'Persiapan kelas dibatalkan: ' . $e->getMessage());
        header("Location: $back_url");
        exit;
    }
}

// ==========================================================
// 2. KENAIKAN SISWA (X->XI & XI->XII)
// ==========================================================
if ($aksi == "kenaikan") {
    if ($id_ta_sumber <= 0 || $id_ta_target <= 0) {
        setAlert('danger', 'Tahun ajaran tidak valid.');
        header("Location: $back_url");
        exit;
    }

    $jurusan_xi = array_map('intval', $_POST['naik_xi'] ?? []);
    $jurusan_xii = array_map('intval', $_POST['naik_xii'] ?? []);

    if (empty($jurusan_xi) && empty($jurusan_xii)) {
        setAlert('warning', 'Pilih minimal satu jurusan untuk dinaikkan.');
        header("Location: $back_url");
        exit;
    }

    // Pasangan tingkat: sumber => tujuan, dengan daftar jurusan terpilih
    $rencana = [];
    if (!empty($jurusan_xi))   $rencana[] = ['dari' => 'X',   'ke' => 'XI',  'jurusan' => $jurusan_xi];
    if (!empty($jurusan_xii))  $rencana[] = ['dari' => 'XI',  'ke' => 'XII', 'jurusan' => $jurusan_xii];

    $koneksi->begin_transaction();
    try {
        $total_naik = 0;
        $rincian = [];
        $terlewat = [];

        foreach ($rencana as $step) {
            $dari = $step['dari'];
            $ke = $step['ke'];
            $in_jurusan = implode(',', $step['jurusan']);

            // Peta kelas target (nama_kelas|id_jurusan => id_kelas)
            $map_target = [];
            $q_map = $koneksi->query("
                SELECT id_kelas, nama_kelas, id_jurusan FROM dt_kelas
                WHERE id_tahun_ajaran = '$id_ta_target' AND tingkat = '$ke'
            ");
            if (!$q_map) throw new Exception("Gagal membaca kelas target: " . $koneksi->error);
            while ($r = $q_map->fetch_assoc()) {
                $map_target[$r['nama_kelas'] . '|' . $r['id_jurusan']] = $r['id_kelas'];
            }

            // Daftar kelas sumber yang harus jadi target (validasi kelengkapan dulu)
            $q_cek = $koneksi->query("
                SELECT DISTINCT k.nama_kelas, k.id_jurusan, j.nama_jurusan
                FROM dt_siswa s
                INNER JOIN dt_kelas_siswa ks ON ks.id_siswa = s.id_siswa AND ks.id_tahun_ajaran = '$id_ta_sumber'
                INNER JOIN dt_kelas k ON k.id_kelas = ks.id_kelas AND k.tingkat = '$dari'
                INNER JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                WHERE s.id_tahun_ajaran = '$id_ta_sumber'
                  AND k.id_jurusan IN ($in_jurusan)
                  AND NOT EXISTS (SELECT 1 FROM dt_kelas_siswa ks2 WHERE ks2.id_siswa = s.id_siswa AND ks2.id_tahun_ajaran = '$id_ta_target')
            ");
            if (!$q_cek) throw new Exception("Gagal memvalidasi kelas target: " . $koneksi->error);

            $kelas_hilang = [];
            while ($r = $q_cek->fetch_assoc()) {
                $kunci = $r['nama_kelas'] . '|' . $r['id_jurusan'];
                if (!isset($map_target[$kunci])) {
                    $kelas_hilang[] = $r['nama_jurusan'] . ' ' . $r['nama_kelas'];
                }
            }
            if (!empty($kelas_hilang)) {
                // ABORT TOTAL: jangan proses setengah-setengah
                throw new Exception("Kelas tujuan $ke belum ada di tahun baru untuk: " . implode(', ', $kelas_hilang) . ". Jalankan Persiapan Kelas dulu.");
            }

            // Eksekusi kenaikan per siswa
            $q_siswa = $koneksi->query("
                SELECT s.id_siswa, s.nama, k.nama_kelas AS nama_kelas_sumber, k.id_jurusan
                FROM dt_siswa s
                INNER JOIN dt_kelas_siswa ks ON ks.id_siswa = s.id_siswa AND ks.id_tahun_ajaran = '$id_ta_sumber'
                INNER JOIN dt_kelas k ON k.id_kelas = ks.id_kelas AND k.tingkat = '$dari'
                WHERE s.id_tahun_ajaran = '$id_ta_sumber'
                  AND k.id_jurusan IN ($in_jurusan)
                  AND NOT EXISTS (SELECT 1 FROM dt_kelas_siswa ks2 WHERE ks2.id_siswa = s.id_siswa AND ks2.id_tahun_ajaran = '$id_ta_target')
                ORDER BY k.nama_kelas, s.nama
            ");
            if (!$q_siswa) throw new Exception("Gagal membaca daftar siswa: " . $koneksi->error);

            $naik_step = 0;
            while ($s = $q_siswa->fetch_assoc()) {
                $kunci = $s['nama_kelas_sumber'] . '|' . $s['id_jurusan'];
                if (!isset($map_target[$kunci])) {
                    $terlewat[] = $s['nama'] . ' (kelas ' . $s['nama_kelas_sumber'] . ' tidak punya tujuan)';
                    continue;
                }
                $id_siswa = (int)$s['id_siswa'];
                $id_kelas_baru = (int)$map_target[$kunci];

                // Ambil tingkat & nama kelas target untuk rombel
                $q_kt = $koneksi->query("SELECT tingkat, nama_kelas FROM dt_kelas WHERE id_kelas = '$id_kelas_baru'");
                $d_kt = $q_kt->fetch_assoc();
                $rombel_baru = $d_kt['tingkat'] . '-' . $d_kt['nama_kelas'];
                $rombel_esc = $koneksi->real_escape_string($rombel_baru);

                $sql_ins = "INSERT INTO dt_kelas_siswa (id_siswa, id_kelas, id_tahun_ajaran, keterangan)
                            VALUES ('$id_siswa', '$id_kelas_baru', '$id_ta_target', 'Naik Kelas')";
                if (!$koneksi->query($sql_ins)) {
                    throw new Exception("Gagal insert kelas_siswa untuk siswa ID $id_siswa: " . $koneksi->error);
                }

                $sql_upd = "UPDATE dt_siswa SET id_tahun_ajaran = '$id_ta_target', rombel = '$rombel_esc'
                            WHERE id_siswa = '$id_siswa'";
                if (!$koneksi->query($sql_upd)) {
                    throw new Exception("Gagal update siswa ID $id_siswa: " . $koneksi->error);
                }

                $naik_step++;
                $total_naik++;
            }
            $rincian[] = "$naik_step siswa $dari&rarr;$ke";
        }

        if ($total_naik == 0) {
            $koneksi->rollback();
            if (!empty($terlewat)) {
                setAlert('warning', 'Tidak ada siswa yang dinaikkan. Terlewat: ' . implode('; ', array_slice($terlewat, 0, 10)) . (count($terlewat) > 10 ? ', ...' : ''));
            } else {
                setAlert('info', 'Semua siswa sudah memiliki kelas di tahun baru — tidak ada yang perlu dinaikkan.');
            }
            header("Location: $back_url");
            exit;
        }

        $koneksi->commit();
        $pesan = count($rincian) > 0 ? ($total_naik . ' siswa berhasil dinaikkan (' . implode(', ', $rincian) . ').') : "$total_naik siswa berhasil dinaikkan.";
        if (!empty($terlewat)) {
            $pesan .= ' Terlewat: ' . implode('; ', array_slice($terlewat, 0, 5)) . (count($terlewat) > 5 ? ', dan lainnya.' : '.');
        }
        setAlert('success', $pesan);
        header("Location: $back_url");
        exit;
    } catch (Exception $e) {
        $koneksi->rollback();
        setAlert('danger', 'Kenaikan dibatalkan (tidak ada perubahan): ' . $e->getMessage());
        header("Location: $back_url");
        exit;
    }
}

// ==========================================================
// 3. KELULUSAN SISWA XII -> ALUMNI
// ==========================================================
if ($aksi == "luluskan") {
    $tahun_lulus = trim($_POST['tahun_lulus'] ?? '');
    if ($id_ta_sumber <= 0 || $tahun_lulus === '') {
        setAlert('danger', 'Tahun ajaran sumber atau tahun lulus tidak valid.');
        header("Location: $back_url");
        exit;
    }

    // Kolom bersama dt_siswa <-> dt_alumni (pola aksi_alumni.php)
    $kolom = [];
    $sql_kolom = "SELECT s.COLUMN_NAME
            FROM information_schema.COLUMNS s
            INNER JOIN information_schema.COLUMNS a
                ON a.TABLE_SCHEMA = s.TABLE_SCHEMA
                AND a.TABLE_NAME = 'dt_alumni'
                AND a.COLUMN_NAME = s.COLUMN_NAME
            WHERE s.TABLE_SCHEMA = DATABASE()
              AND s.TABLE_NAME = 'dt_siswa'
              AND s.COLUMN_NAME <> 'id_siswa'
            ORDER BY s.ORDINAL_POSITION";
    $qk = $koneksi->query($sql_kolom);
    if ($qk) {
        while ($r = $qk->fetch_assoc()) $kolom[] = '`' . $r['COLUMN_NAME'] . '`';
    }
    if (empty($kolom)) {
        setAlert('danger', 'Gagal membaca struktur tabel dt_alumni.');
        header("Location: $back_url");
        exit;
    }
    $kolom_sql = implode(', ', $kolom);

    // Siswa XII TA sumber yang belum jadi alumni
    $q_lulus = $koneksi->query("
        SELECT DISTINCT s.id_siswa, s.nama
        FROM dt_siswa s
        INNER JOIN dt_kelas_siswa ks ON ks.id_siswa = s.id_siswa AND ks.id_tahun_ajaran = '$id_ta_sumber'
        INNER JOIN dt_kelas k ON k.id_kelas = ks.id_kelas AND k.tingkat = 'XII'
        WHERE s.id_tahun_ajaran = '$id_ta_sumber'
          AND NOT EXISTS (SELECT 1 FROM dt_alumni a WHERE a.id_siswa = s.id_siswa)
    ");
    if (!$q_lulus) {
        setAlert('danger', 'Gagal membaca daftar siswa XII: ' . $koneksi->error);
        header("Location: $back_url");
        exit;
    }

    $daftar = [];
    while ($r = $q_lulus->fetch_assoc()) $daftar[] = $r;

    if (empty($daftar)) {
        setAlert('info', 'Tidak ada siswa kelas XII yang bisa diluluskan (semua sudah menjadi alumni).');
        header("Location: $back_url");
        exit;
    }

    $tahun_esc = $koneksi->real_escape_string($tahun_lulus);
    $koneksi->begin_transaction();
    try {
        $lulus = 0;
        foreach ($daftar as $s) {
            $id_siswa = (int)$s['id_siswa'];
            $sql_ins = "INSERT INTO dt_alumni (id_siswa, tahun_lulus, $kolom_sql)
                        SELECT id_siswa, '$tahun_esc', $kolom_sql
                        FROM dt_siswa WHERE id_siswa = $id_siswa";
            if (!$koneksi->query($sql_ins)) {
                throw new Exception("Gagal memindahkan ke alumni (siswa ID $id_siswa): " . $koneksi->error);
            }
            if ($koneksi->affected_rows <= 0) {
                throw new Exception("Insert alumni tidak menghasilkan baris untuk siswa ID $id_siswa.");
            }
            // Hapus dari data aktif (kelas_siswa dulu karena FK)
            if (!$koneksi->query("DELETE FROM dt_kelas_siswa WHERE id_siswa = $id_siswa")) {
                throw new Exception("Gagal menghapus kelas_siswa siswa ID $id_siswa: " . $koneksi->error);
            }
            if (!$koneksi->query("DELETE FROM dt_siswa WHERE id_siswa = $id_siswa")) {
                throw new Exception("Gagal menghapus siswa ID $id_siswa: " . $koneksi->error);
            }
            $lulus++;
        }
        $koneksi->commit();
        setAlert('success', "$lulus siswa kelas XII berhasil diluluskan ke data Alumni (tahun lulus $tahun_lulus) dan dihapus dari data siswa aktif.");
        header("Location: $back_url");
        exit;
    } catch (Exception $e) {
        $koneksi->rollback();
        setAlert('danger', 'Kelulusan dibatalkan (tidak ada perubahan): ' . $e->getMessage());
        header("Location: $back_url");
        exit;
    }
}

// ==========================================================
// AKSI TIDAK VALID
// ==========================================================
setAlert('danger', 'Aksi tidak valid.');
header("Location: $back_url");
exit;
