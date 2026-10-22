<?php
require_once(__DIR__ . '/akses_guard.php');
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
    header("Location: ../index.php?menu=pengampu_mapel&pesan=error");
    exit;
}

// --- Guru ---
$guru_map = [];
$q = $koneksi->query("SELECT id_guru, nama_guru FROM dt_guru ORDER BY nama_guru ASC");
if ($q) {
    while ($g = $q->fetch_assoc()) {
        $guru_map[(int)$g['id_guru']] = $g['nama_guru'];
    }
}

// --- Kelas (TA aktif) ---
$kelas_list = [];
$q = $koneksi->query("
    SELECT k.id_kelas, k.nama_kelas, k.tingkat
    FROM dt_kelas k
    WHERE k.id_tahun_ajaran = $id_ta_aktif
    ORDER BY CASE k.tingkat WHEN 'X' THEN 1 WHEN 'XI' THEN 2 WHEN 'XII' THEN 3 WHEN 'XIII' THEN 4 ELSE 5 END, k.nama_kelas ASC
");
if ($q) {
    while ($k = $q->fetch_assoc()) {
        $kelas_list[] = $k;
    }
}

// --- Mapel ---
$mapel_list = [];
$q = $koneksi->query("
    SELECT id_mapel, kode_mapel, nama_mapel, kelompok_mapel
    FROM dt_mapel
    ORDER BY CASE kelompok_mapel
        WHEN 'A' THEN 1 WHEN 'B' THEN 2 WHEN 'C1' THEN 3 WHEN 'C2' THEN 4 WHEN 'C3' THEN 5 WHEN 'Muatan Lokal' THEN 6 ELSE 7 END,
        kode_mapel ASC, nama_mapel ASC
");
if ($q) {
    while ($m = $q->fetch_assoc()) {
        $mapel_list[] = $m;
    }
}

// --- Pengampu existing: key "id_mapel|id_kelas" => id_guru ---
$exist = [];
$stmt = $koneksi->prepare("SELECT id_mapel, id_kelas, id_guru FROM dt_pengampu_mapel WHERE id_tahun_ajaran = ?");
if ($stmt) {
    $stmt->bind_param("i", $id_ta_aktif);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($p = $res->fetch_assoc()) {
        $exist[(int)$p['id_mapel'] . '|' . (int)$p['id_kelas']] = (int)$p['id_guru'];
    }
    $stmt->close();
}

$spreadsheet = new Spreadsheet();

// ============ SHEET 1: Pengampu ============
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Pengampu');

// Header
$sheet->setCellValueExplicit('A1', 'id_mapel', DataType::TYPE_STRING);
$sheet->setCellValueExplicit('B1', 'kode_mapel', DataType::TYPE_STRING);
$sheet->setCellValueExplicit('C1', 'nama_mapel', DataType::TYPE_STRING);
$col = 4;
$kolom_kelas = []; // colIndex => id_kelas
foreach ($kelas_list as $k) {
    $coord = Coordinate::stringFromColumnIndex($col) . '1';
    $sheet->setCellValueExplicit($coord, $k['tingkat'] . ' - ' . $k['nama_kelas'] . ' [id:' . (int)$k['id_kelas'] . ']', DataType::TYPE_STRING);
    $kolom_kelas[$col] = (int)$k['id_kelas'];
    $col++;
}
$lastCol = $col - 1;
$lastColLetter = Coordinate::stringFromColumnIndex($lastCol);

// Baris data mapel
$row = 2;
foreach ($mapel_list as $m) {
    $idm = (int)$m['id_mapel'];
    $sheet->setCellValueExplicit('A' . $row, (string)$idm, DataType::TYPE_STRING);
    $sheet->setCellValueExplicit('B' . $row, (string)$m['kode_mapel'], DataType::TYPE_STRING);
    $sheet->setCellValue('C' . $row, (string)$m['nama_mapel']);
    foreach ($kolom_kelas as $c => $idk) {
        $idg = $exist[$idm . '|' . $idk] ?? 0;
        if ($idg > 0 && isset($guru_map[$idg])) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($c) . $row, $guru_map[$idg]);
        }
    }
    $row++;
}
$lastRow = $row - 1;

// Styling header
$sheet->getStyle('A1:' . $lastColLetter . '1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '4472C4']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
]);
// Styling kolom id/kode/mapel (referensi, jangan diubah)
$sheet->getStyle('A2:C' . $lastRow)->applyFromArray([
    'font' => ['color' => ['rgb' => '808080']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
]);
// Styling area pengampu (isi guru)
if ($lastCol >= 4 && $lastRow >= 2) {
    $sheet->getStyle('D2:' . $lastColLetter . $lastRow)->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ]);
}

// Lebar kolom
$sheet->getColumnDimension('A')->setWidth(9);
$sheet->getColumnDimension('B')->setWidth(13);
$sheet->getColumnDimension('C')->setWidth(34);
if ($lastCol >= 4) {
    foreach (range(4, $lastCol) as $c) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(26);
    }
}
$sheet->freezePane('D2');

// ============ SHEET 2: Daftar Guru ============
$shG = $spreadsheet->createSheet();
$shG->setTitle('Daftar Guru');
$shG->fromArray(['No', 'id_guru', 'nama_guru'], NULL, 'A1');
$r = 2;
$no = 1;
foreach ($guru_map as $idg => $namaG) {
    $shG->setCellValue('A' . $r, $no++);
    $shG->setCellValueExplicit('B' . $r, (string)$idg, DataType::TYPE_STRING);
    $shG->setCellValue('C' . $r, $namaG);
    $r++;
}
$shG->getStyle('A1:C1')->getFont()->setBold(true);
$shG->getColumnDimension('B')->setWidth(10);
$shG->getColumnDimension('C')->setWidth(40);

// ============ SHEET 3: Daftar Kelas ============
$shK = $spreadsheet->createSheet();
$shK->setTitle('Daftar Kelas');
$shK->fromArray(['No', 'id_kelas', 'tingkat', 'nama_kelas'], NULL, 'A1');
$r = 2;
$no = 1;
foreach ($kelas_list as $k) {
    $shK->setCellValue('A' . $r, $no++);
    $shK->setCellValueExplicit('B' . $r, (string)(int)$k['id_kelas'], DataType::TYPE_STRING);
    $shK->setCellValue('C' . $r, $k['tingkat']);
    $shK->setCellValue('D' . $r, $k['nama_kelas']);
    $r++;
}
$shK->getStyle('A1:D1')->getFont()->setBold(true);
$shK->getColumnDimension('B')->setWidth(10);

$spreadsheet->setActiveSheetIndex(0);

// --- Output ---
$writer = new Xlsx($spreadsheet);
$ta_file = str_replace('/', '-', $nama_ta_aktif);
$filename = 'Template_Pengampu_Mapel_' . $ta_file . '_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
