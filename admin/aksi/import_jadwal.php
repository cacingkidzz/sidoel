<?php
require_once(__DIR__ . '/akses_guard.php');
// ==========================================================
// File: aksi/import_jadwal.php  (KBM-2 Jadwal Pelajaran)
// Import jadwal dari Excel (template export_jadwal.php).
// Satu sheet per tingkat; kolom A=Hari, B=Jam, C..=kelas (header "[id:<id_kelas>]").
// Sel berisi KODE mapel (boleh diikuti baris kedua nama guru). GANTI TOTAL
// jadwal untuk kelas-kelas yang muncul di file (TA aktif).
// ==========================================================
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("../koneksi.php");
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$back_url = "../index.php?menu=jadwal";

function setAlert($type, $msg)
{
    $_SESSION['alert'] = ['type' => $type, 'msg' => $msg];
}

function norm_nama($s)
{
    $s = trim((string)$s);
    $s = preg_replace('/\s+/', ' ', $s);
    return mb_strtolower($s, 'UTF-8');
}

if (!isset($_POST['import'])) {
    setAlert('danger', 'Permintaan import tidak valid.');
    header("Location: $back_url");
    exit;
}

// --- Validasi file ---
if (!isset($_FILES['fileExcel']) || $_FILES['fileExcel']['error'] !== 0) {
    setAlert('danger', 'Upload file gagal (kode: ' . ($_FILES['fileExcel']['error'] ?? '-') . ').');
    header("Location: $back_url");
    exit;
}
$ext = strtolower(pathinfo($_FILES['fileExcel']['name'], PATHINFO_EXTENSION));
if ($ext !== 'xlsx' && $ext !== 'xls') {
    setAlert('danger', 'Hanya file Excel (.xlsx / .xls) yang diizinkan.');
    header("Location: $back_url");
    exit;
}

// --- TA target ---
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
$id_ta_aktif = 0;
$nama_ta_aktif = '';
if ($nama_ta_sesi !== '') {
    $stmt = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $nama_ta_sesi);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $ta = $res->fetch_assoc();
            $id_ta_aktif = (int)$ta['id_tahun_ajaran'];
            $nama_ta_aktif = $ta['tahun_ajaran'];
        }
        $stmt->close();
    }
}
if ($id_ta_aktif <= 0) {
    setAlert('danger', 'Tahun ajaran aktif tidak ditemukan. Buka menu Jadwal dari sidebar.');
    header("Location: $back_url");
    exit;
}

// --- Referensi ---
$kelas_valid = [];
$q = $koneksi->query("SELECT id_kelas FROM dt_kelas WHERE id_tahun_ajaran = $id_ta_aktif");
if ($q) {
    while ($k = $q->fetch_assoc()) {
        $kelas_valid[(int)$k['id_kelas']] = true;
    }
}

$mapel_valid = [];
$mapel_by_kode = [];
$mapel_by_nama = [];
$q = $koneksi->query("SELECT id_mapel, kode_mapel, nama_mapel FROM dt_mapel");
if ($q) {
    while ($m = $q->fetch_assoc()) {
        $idm = (int)$m['id_mapel'];
        $mapel_valid[$idm] = true;
        if (trim((string)$m['kode_mapel']) !== '') {
            $mapel_by_kode[norm_nama($m['kode_mapel'])] = $idm;
        }
        if (trim((string)$m['nama_mapel']) !== '') {
            $mapel_by_nama[norm_nama($m['nama_mapel'])] = $idm;
        }
    }
}

$pengampu_map = []; // [id_kelas][id_mapel] = id_guru
$q = $koneksi->query("SELECT id_kelas, id_mapel, id_guru FROM dt_pengampu_mapel WHERE id_tahun_ajaran = $id_ta_aktif");
if ($q) {
    while ($p = $q->fetch_assoc()) {
        $pengampu_map[(int)$p['id_kelas']][(int)$p['id_mapel']] = (int)$p['id_guru'];
    }
}

$HARI_VALID = ['Senin' => true, 'Selasa' => true, 'Rabu' => true, 'Kamis' => true, 'Jumat' => true];

// --- Baca file ---
try {
    $spreadsheet = IOFactory::load($_FILES['fileExcel']['tmp_name']);
} catch (Throwable $e) {
    setAlert('danger', 'Gagal membaca file Excel: ' . htmlspecialchars($e->getMessage()));
    header("Location: $back_url");
    exit;
}

$entries = [];      // [ [id_kelas, hari, jam_ke, id_mapel], ... ]
$kelas_diproses = []; // id_kelas => true
$skipped_mapel = 0;
$skipped_baris = 0;

foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
    $highestRow = $sheet->getHighestDataRow();
    $highestCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
    if ($highestRow < 2 || $highestCol < 3) {
        continue;
    }

    // Peta kolom -> id_kelas dari header (ambil "[id:<angka>]")
    $kolom_kelas = [];
    for ($c = 3; $c <= $highestCol; $c++) {
        $head = (string)$sheet->getCell(Coordinate::stringFromColumnIndex($c) . '1')->getValue();
        if (preg_match('/\[id:(\d+)\]/', $head, $mm)) {
            $idk = (int)$mm[1];
            if (isset($kelas_valid[$idk])) {
                $kolom_kelas[$c] = $idk;
                $kelas_diproses[$idk] = true; // kelas ini ikut diganti-total (walau selnya kosong)
            }
        }
    }
    if (empty($kolom_kelas)) {
        continue; // bukan sheet jadwal (mis. Daftar Mapel/Kelas/Guru)
    }

    for ($r = 2; $r <= $highestRow; $r++) {
        $hari = trim((string)$sheet->getCell('A' . $r)->getValue());
        $hari = $hari === '' ? '' : ucfirst(strtolower($hari));
        $jam_ke = (int)$sheet->getCell('B' . $r)->getValue();
        if (!isset($HARI_VALID[$hari]) || $jam_ke <= 0) {
            $skipped_baris++;
            continue;
        }

        foreach ($kolom_kelas as $c => $idk) {
            $raw = trim((string)$sheet->getCell(Coordinate::stringFromColumnIndex($c) . $r)->getValue());
            if ($raw === '') {
                continue;
            }
            // Ambil baris pertama, tangani "[id:..]" / angka / kode / nama
            $first = trim(preg_split('/\r?\n/', $raw)[0]);
            $idm = 0;
            if (preg_match('/\[id:(\d+)\]/', $first, $mg)) {
                $idm = (int)$mg[1];
            } elseif (ctype_digit($first)) {
                $idm = (int)$first;
            } else {
                $key = norm_nama($first);
                if (isset($mapel_by_kode[$key])) {
                    $idm = $mapel_by_kode[$key];
                } elseif (isset($mapel_by_nama[$key])) {
                    $idm = $mapel_by_nama[$key];
                } else {
                    $idm = -1;
                }
            }
            if ($idm === -1 || !isset($mapel_valid[$idm])) {
                $skipped_mapel++;
                continue;
            }
            $entries[] = [$idk, $hari, $jam_ke, $idm];
            $kelas_diproses[$idk] = true;
        }
    }
}

if (empty($entries)) {
    setAlert('warning', 'Tidak ada jadwal yang terbaca dari file. Pastikan Anda mengisi sel dengan <b>kode mapel</b> dan menggunakan template yang disediakan.');
    header("Location: $back_url");
    exit;
}

// --- Simpan: ganti total jadwal kelas-kelas yang ada di file ---
$koneksi->begin_transaction();
try {
    $del = $koneksi->prepare("DELETE FROM dt_jadwal WHERE id_kelas = ? AND id_tahun_ajaran = ?");
    foreach (array_keys($kelas_diproses) as $idk) {
        $del->bind_param("ii", $idk, $id_ta_aktif);
        $del->execute();
    }
    $del->close();

    $ins = $koneksi->prepare("INSERT INTO dt_jadwal (id_kelas, id_mapel, id_guru, hari, jam_ke, id_tahun_ajaran) VALUES (?, ?, ?, ?, ?, ?)");
    $tersimpan = 0;
    $bentrok = 0;
    $guru_jam = []; // deteksi bentrok guru dalam file: "hari|jam|id_guru" => true
    foreach ($entries as $e) {
        [$idk, $hari, $jam_ke, $idm] = $e;
        $idg = isset($pengampu_map[$idk][$idm]) ? (int)$pengampu_map[$idk][$idm] : 0;
        $idg_param = $idg > 0 ? $idg : null;
        if ($idg > 0) {
            $gkey = $hari . '|' . $jam_ke . '|' . $idg;
            if (isset($guru_jam[$gkey])) {
                $bentrok++;
            } else {
                $guru_jam[$gkey] = true;
            }
        }
        $ins->bind_param("iiisii", $idk, $idm, $idg_param, $hari, $jam_ke, $id_ta_aktif);
        $ins->execute();
        $tersimpan++;
    }
    $ins->close();
    $koneksi->commit();

    $msg = 'Import jadwal selesai. <b>' . $tersimpan . '</b> entri tersimpan pada <b>' . count($kelas_diproses) . '</b> kelas.';
    if ($skipped_mapel > 0) {
        $msg .= ' <span class="text-warning">' . $skipped_mapel . ' sel dilewati (kode mapel tidak dikenal).</span>';
    }
    if ($skipped_baris > 0) {
        $msg .= ' <span class="text-warning">' . $skipped_baris . ' baris dilewati (hari/jam tidak valid).</span>';
    }
    if ($bentrok > 0) {
        $msg .= ' <span class="text-warning">' . $bentrok . ' potensi bentrok guru pada jam sama (periksa di halaman Jadwal).</span>';
    }
    setAlert($bentrok > 0 ? 'warning' : 'success', $msg . ' Tahun ajaran: <b>' . htmlspecialchars($nama_ta_aktif) . '</b>.');
} catch (Throwable $e) {
    $koneksi->rollback();
    setAlert('danger', 'Gagal menyimpan hasil import. Error: ' . $koneksi->error);
}
header("Location: $back_url");
exit;
