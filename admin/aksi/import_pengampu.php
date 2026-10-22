<?php
require_once(__DIR__ . '/akses_guard.php');
// ===============================================
// File: aksi/import_pengampu.php
// Import penugasan pengampu mapel dari Excel (template export_pengampu.php).
// Header kelas: "<tingkat> - <nama> [id:<id_kelas>]". Sel guru diisi NAMA guru.
// ===============================================
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("../koneksi.php");
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$back_url = "../index.php?menu=pengampu_mapel";

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
    setAlert('danger', 'Tahun ajaran aktif tidak ditemukan. Buka menu Pengampu Mapel dari sidebar.');
    header("Location: $back_url");
    exit;
}

// --- Referensi: kelas valid (TA), guru (nama->id), mapel (id/kode/nama->id) ---
$kelas_valid = []; // id_kelas => nama tampil
$q = $koneksi->query("SELECT id_kelas, tingkat, nama_kelas FROM dt_kelas WHERE id_tahun_ajaran = $id_ta_aktif");
if ($q) {
    while ($k = $q->fetch_assoc()) {
        $kelas_valid[(int)$k['id_kelas']] = $k['tingkat'] . ' - ' . $k['nama_kelas'];
    }
}

$guru_by_nama = [];
$guru_valid = [];
$q = $koneksi->query("SELECT id_guru, nama_guru FROM dt_guru");
if ($q) {
    while ($g = $q->fetch_assoc()) {
        $guru_valid[(int)$g['id_guru']] = true;
        $guru_by_nama[norm_nama($g['nama_guru'])] = (int)$g['id_guru'];
    }
}

$mapel_valid = [];   // id_mapel => true
$mapel_by_kode = [];
$mapel_by_nama = [];
$q = $koneksi->query("SELECT id_mapel, kode_mapel, nama_mapel FROM dt_mapel");
if ($q) {
    while ($m = $q->fetch_assoc()) {
        $idm = (int)$m['id_mapel'];
        $mapel_valid[$idm] = true;
        $mapel_by_kode[norm_nama($m['kode_mapel'])] = $idm;
        $mapel_by_nama[norm_nama($m['nama_mapel'])] = $idm;
    }
}

// --- Baca file ---
try {
    $spreadsheet = IOFactory::load($_FILES['fileExcel']['tmp_name']);
} catch (Throwable $e) {
    setAlert('danger', 'Gagal membaca file Excel: ' . htmlspecialchars($e->getMessage()));
    header("Location: $back_url");
    exit;
}

// Cari sheet "Pengampu" bila ada, jika tidak pakai sheet pertama
$sheet = $spreadsheet->getSheetByName('Pengampu') ?: $spreadsheet->getSheet(0);
$highestRow = $sheet->getHighestDataRow();
$highestCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

if ($highestRow < 2 || $highestCol < 4) {
    setAlert('danger', 'Format file tidak dikenali. Gunakan template yang disediakan (kolom mapel di A-C, kelas mulai kolom D).');
    header("Location: $back_url");
    exit;
}

// Peta kolom -> id_kelas (dari header, ambil "[id:<angka>]")
$kolom_kelas = [];   // colIndex => id_kelas
for ($c = 4; $c <= $highestCol; $c++) {
    $head = (string)$sheet->getCell(Coordinate::stringFromColumnIndex($c) . '1')->getValue();
    if (preg_match('/\[id:(\d+)\]/', $head, $mm)) {
        $idk = (int)$mm[1];
        if (isset($kelas_valid[$idk])) {
            $kolom_kelas[$c] = $idk;
        }
    }
}
if (empty($kolom_kelas)) {
    setAlert('danger', 'Tidak ada kolom kelas yang valid pada header. Pastikan header memuat "[id:<id_kelas>]".');
    header("Location: $back_url");
    exit;
}

// --- Kumpulkan pasangan (id_mapel, id_kelas) => id_guru ---
$pairs = [];        // "idk|idm" => id_guru
$mapel_baris_valid = 0;
$dilewati_guru = 0;

for ($r = 2; $r <= $highestRow; $r++) {
    // Identifikasi mapel: kolom A (id), fallback B (kode), fallback C (nama)
    $idm = (int)$sheet->getCell('A' . $r)->getValue();
    if (!isset($mapel_valid[$idm])) {
        $kode = norm_nama($sheet->getCell('B' . $r)->getValue());
        $nama = norm_nama($sheet->getCell('C' . $r)->getValue());
        if ($kode !== '' && isset($mapel_by_kode[$kode])) {
            $idm = $mapel_by_kode[$kode];
        } elseif ($nama !== '' && isset($mapel_by_nama[$nama])) {
            $idm = $mapel_by_nama[$nama];
        } else {
            continue; // baris mapel tidak dikenali
        }
    }
    $mapel_baris_valid++;

    foreach ($kolom_kelas as $c => $idk) {
        $val = trim((string)$sheet->getCell(Coordinate::stringFromColumnIndex($c) . $r)->getValue());
        if ($val === '') {
            continue;
        }
        $idg = 0;
        if (preg_match('/\[id:(\d+)\]/', $val, $mg)) {
            $idg = (int)$mg[1];
            if (!isset($guru_valid[$idg])) {
                $idg = 0;
            }
        }
        if ($idg <= 0) {
            // bersihkan "[id:..]" bila ada, lalu cocokkan nama
            $nama_only = norm_nama(preg_replace('/\[id:\d+\]/', '', $val));
            if ($nama_only !== '' && isset($guru_by_nama[$nama_only])) {
                $idg = $guru_by_nama[$nama_only];
            }
        }
        if ($idg <= 0) {
            $dilewati_guru++;
            continue;
        }
        $pairs[$idk . '|' . $idm] = $idg;
    }
}

if (empty($pairs)) {
    setAlert('warning', 'Tidak ada penugasan guru yang terbaca. Pastikan sel berisi nama guru yang terdaftar.');
    header("Location: $back_url");
    exit;
}

// --- Simpan: ganti seluruh pengampu untuk kelas-kelas yang ada di file (TA aktif) ---
$kelas_diproses = array_values(array_unique(array_values($kolom_kelas)));

$koneksi->begin_transaction();
try {
    $del = $koneksi->prepare("DELETE FROM dt_pengampu_mapel WHERE id_kelas = ? AND id_tahun_ajaran = ?");
    $ins = $koneksi->prepare("INSERT IGNORE INTO dt_pengampu_mapel (id_guru, id_mapel, id_kelas, id_tahun_ajaran) VALUES (?, ?, ?, ?)");

    foreach ($kelas_diproses as $idk) {
        $del->bind_param("ii", $idk, $id_ta_aktif);
        $del->execute();
    }

    $tersimpan = 0;
    foreach ($pairs as $key => $idg) {
        [$idk, $idm] = array_map('intval', explode('|', $key));
        $ins->bind_param("iiii", $idg, $idm, $idk, $id_ta_aktif);
        $ins->execute();
        $tersimpan += $ins->affected_rows;
    }
    $del->close();
    $ins->close();
    $koneksi->commit();

    $msg = 'Import selesai. <b>' . $tersimpan . '</b> penugasan pengampu tersimpan pada <b>' . count($kelas_diproses) . '</b> kelas.';
    if ($dilewati_guru > 0) {
        $msg .= ' <span class="text-warning">' . $dilewati_guru . ' sel dilewati (nama guru tidak dikenal).</span>';
    }
    setAlert('success', $msg . ' Tahun ajaran: <b>' . htmlspecialchars($nama_ta_aktif) . '</b>.');
} catch (Throwable $e) {
    $koneksi->rollback();
    setAlert('danger', 'Gagal menyimpan hasil import. Error: ' . $koneksi->error);
}
header("Location: $back_url");
exit;
