<?php
require_once(__DIR__ . '/akses_guard.php');
// ==========================================================
// File: aksi/export_jadwal.php  (KBM-2 Jadwal Pelajaran)
// Menghasilkan file Excel jadwal pelajaran (ganti-total per tingkat).
// Satu sheet per tingkat: kolom = Hari, Jam, lalu kelas-kelas tingkat tsb.
// Sel berisi KODE mapel (mode template) atau KODE + nama guru (mode data).
// File ini sekaligus berfungsi sebagai TEMPLATE IMPORT (lihat import_jadwal.php).
// Param: ?mode=template|data  (default: template)
// ==========================================================
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("../koneksi.php");
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

set_time_limit(0);

$mode = ($_GET['mode'] ?? 'template') === 'data' ? 'data' : 'template';

$HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
$TINGKAT_ORDER = "CASE k.tingkat WHEN 'X' THEN 1 WHEN 'XI' THEN 2 WHEN 'XII' THEN 3 WHEN 'XIII' THEN 4 ELSE 5 END";

// --- Tahun ajaran aktif (session, fallback status='aktif') ---
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
    $q = $koneksi->query("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE status = 'aktif' ORDER BY id_tahun_ajaran DESC LIMIT 1");
    if ($q && $q->num_rows > 0) {
        $ta = $q->fetch_assoc();
        $id_ta_aktif = (int)$ta['id_tahun_ajaran'];
        $nama_ta_aktif = $ta['tahun_ajaran'];
    }
}
if ($id_ta_aktif <= 0) {
    header("Location: ../index.php?menu=jadwal&pesan=error");
    exit;
}

// --- Jam pelajaran ---
$jam_list = [];
$q = $koneksi->query("SELECT jam_ke, waktu_mulai, waktu_selesai, keterangan FROM dt_jam_pelajaran ORDER BY jam_ke ASC");
if ($q) {
    while ($j = $q->fetch_assoc()) {
        $jam_list[] = $j;
    }
}

// --- Kelas (TA aktif), dikelompokkan per tingkat ---
$kelas_by_tingkat = [];
$tingkat_list = [];
$q = $koneksi->query("SELECT k.id_kelas, k.nama_kelas, k.tingkat, j.nama_jurusan FROM dt_kelas k LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan WHERE k.id_tahun_ajaran = $id_ta_aktif ORDER BY $TINGKAT_ORDER, k.nama_kelas ASC");
if ($q) {
    while ($k = $q->fetch_assoc()) {
        $t = (string)$k['tingkat'];
        if (!isset($kelas_by_tingkat[$t])) {
            $kelas_by_tingkat[$t] = [];
            $tingkat_list[] = $t;
        }
        $kelas_by_tingkat[$t][] = $k;
    }
}

// --- Mapel: id -> [kode, nama] ---
$mapel_map = [];
$q = $koneksi->query("SELECT id_mapel, kode_mapel, nama_mapel, kelompok_mapel FROM dt_mapel ORDER BY CASE kelompok_mapel WHEN 'A' THEN 1 WHEN 'B' THEN 2 WHEN 'C1' THEN 3 WHEN 'C2' THEN 4 WHEN 'C3' THEN 5 WHEN 'Muatan Lokal' THEN 6 ELSE 7 END, kode_mapel ASC");
if ($q) {
    while ($m = $q->fetch_assoc()) {
        $mapel_map[(int)$m['id_mapel']] = $m;
    }
}

// --- Guru: id -> nama ---
$guru_map = [];
$q = $koneksi->query("SELECT id_guru, nama_guru FROM dt_guru ORDER BY nama_guru ASC");
if ($q) {
    while ($g = $q->fetch_assoc()) {
        $guru_map[(int)$g['id_guru']] = $g['nama_guru'];
    }
}

// --- Jadwal existing: [id_kelas][hari][jam_ke] = id_mapel/id_guru ---
$jadwal_exist = [];
$q = $koneksi->query("SELECT id_kelas, hari, jam_ke, id_mapel, id_guru FROM dt_jadwal WHERE id_tahun_ajaran = $id_ta_aktif");
if ($q) {
    while ($s = $q->fetch_assoc()) {
        $jadwal_exist[(int)$s['id_kelas']][$s['hari']][(int)$s['jam_ke']] = $s;
    }
}

$spreadsheet = new Spreadsheet();
$first = true;

foreach ($tingkat_list as $t) {
    $kelas_list = $kelas_by_tingkat[$t];
    if (empty($kelas_list)) {
        continue;
    }
    if ($first) {
        $sheet = $spreadsheet->getActiveSheet();
        $first = false;
    } else {
        $sheet = $spreadsheet->createSheet();
    }
    $sheet->setTitle(substr('Tingkat ' . $t, 0, 31));

    // Header
    $sheet->setCellValueExplicit('A1', 'Hari', DataType::TYPE_STRING);
    $sheet->setCellValueExplicit('B1', 'Jam', DataType::TYPE_STRING);
    $col = 3;
    $kolom_kelas = []; // colIndex => id_kelas
    foreach ($kelas_list as $k) {
        $coord = Coordinate::stringFromColumnIndex($col) . '1';
        $label = $t . ' ' . $k['nama_kelas'] . (!empty($k['nama_jurusan']) ? ' (' . $k['nama_jurusan'] . ')' : '') . ' [id:' . (int)$k['id_kelas'] . ']';
        $sheet->setCellValueExplicit($coord, $label, DataType::TYPE_STRING);
        $kolom_kelas[$col] = (int)$k['id_kelas'];
        $col++;
    }
    $lastCol = $col - 1;
    $lastColLetter = Coordinate::stringFromColumnIndex($lastCol);

    // Baris data: tiap hari × tiap jam
    $row = 2;
    foreach ($HARI as $hari) {
        foreach ($jam_list as $j) {
            $jam_ke = (int)$j['jam_ke'];
            $sheet->setCellValueExplicit('A' . $row, $hari, DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $row, $jam_ke);
            foreach ($kolom_kelas as $c => $idk) {
                $cur = $jadwal_exist[$idk][$hari][$jam_ke] ?? null;
                if (!$cur) {
                    continue;
                }
                $idm = (int)$cur['id_mapel'];
                if (!isset($mapel_map[$idm])) {
                    continue;
                }
                $kode = $mapel_map[$idm]['kode_mapel'] !== '' ? $mapel_map[$idm]['kode_mapel'] : $mapel_map[$idm]['nama_mapel'];
                $val = $kode;
                if ($mode === 'data') {
                    $idg = (int)($cur['id_guru'] ?? 0);
                    $nmG = ($idg > 0 && isset($guru_map[$idg])) ? $guru_map[$idg] : 'Tanpa Guru';
                    $val = $kode . "\n" . $nmG;
                }
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c) . $row, $val);
            }
            $row++;
        }
    }
    $lastRow = $row - 1;

    // Styling
    $sheet->getStyle('A1:' . $lastColLetter . '1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ]);
    $sheet->getStyle('A2:B' . $lastRow)->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'font' => ['bold' => true],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ]);
    if ($lastCol >= 3 && $lastRow >= 2) {
        $sheet->getStyle('C2:' . $lastColLetter . $lastRow)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
    }
    $sheet->getColumnDimension('A')->setWidth(10);
    $sheet->getColumnDimension('B')->setWidth(6);
    foreach (range(3, $lastCol) as $c) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth($mode === 'data' ? 20 : 14);
    }
    $sheet->freezePane('C2');
}

if ($first) {
    // Tidak ada kelas sama sekali -> lembar kosong dengan keterangan
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Kosong');
    $sheet->setCellValue('A1', 'Belum ada kelas pada tahun ajaran aktif.');
}

// ============ SHEET: Daftar Mapel ============
$shM = $spreadsheet->createSheet();
$shM->setTitle('Daftar Mapel');
$shM->fromArray(['No', 'Kode', 'Nama Mapel', 'Kelompok'], NULL, 'A1');
$r = 2;
$no = 1;
foreach ($mapel_map as $m) {
    $shM->setCellValue('A' . $r, $no++);
    $shM->setCellValue('B' . $r, $m['kode_mapel']);
    $shM->setCellValue('C' . $r, $m['nama_mapel']);
    $shM->setCellValue('D' . $r, $m['kelompok_mapel']);
    $r++;
}
$shM->getStyle('A1:D1')->getFont()->setBold(true);
$shM->getColumnDimension('B')->setWidth(14);
$shM->getColumnDimension('C')->setWidth(40);

// ============ SHEET: Daftar Kelas ============
$shK = $spreadsheet->createSheet();
$shK->setTitle('Daftar Kelas');
$shK->fromArray(['No', 'Tingkat', 'Nama Kelas', 'Jurusan', 'id_kelas'], NULL, 'A1');
$r = 2;
$no = 1;
foreach ($tingkat_list as $t) {
    foreach ($kelas_by_tingkat[$t] as $k) {
        $shK->setCellValue('A' . $r, $no++);
        $shK->setCellValue('B' . $r, $t);
        $shK->setCellValue('C' . $r, $k['nama_kelas']);
        $shK->setCellValue('D' . $r, $k['nama_jurusan'] ?? '');
        $shK->setCellValueExplicit('E' . $r, (string)(int)$k['id_kelas'], DataType::TYPE_STRING);
        $r++;
    }
}
$shK->getStyle('A1:E1')->getFont()->setBold(true);

// ============ SHEET: Daftar Guru ============
$shG = $spreadsheet->createSheet();
$shG->setTitle('Daftar Guru');
$shG->fromArray(['No', 'id_guru', 'Nama Guru'], NULL, 'A1');
$r = 2;
$no = 1;
foreach ($guru_map as $idg => $namaG) {
    $shG->setCellValue('A' . $r, $no++);
    $shG->setCellValueExplicit('B' . $r, (string)$idg, DataType::TYPE_STRING);
    $shG->setCellValue('C' . $r, $namaG);
    $r++;
}
$shG->getStyle('A1:C1')->getFont()->setBold(true);

$spreadsheet->setActiveSheetIndex(0);

// --- Output ---
$writer = new Xlsx($spreadsheet);
$ta_file = str_replace('/', '-', $nama_ta_aktif);
$prefix = $mode === 'data' ? 'Jadwal_Pelajaran_' : 'Template_Jadwal_';
$filename = $prefix . $ta_file . '_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
