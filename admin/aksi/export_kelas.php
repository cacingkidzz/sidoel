<?php
require_once(__DIR__ . '/akses_guard.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include("../koneksi.php");
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

set_time_limit(0);

// --- Tahun ajaran aktif dari session ---
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
$id_ta_aktif = 0;
$nama_ta_aktif = $nama_ta_sesi;

if ($nama_ta_sesi !== '' && !$koneksi->connect_error) {
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
    header("Location: ../index.php?menu=info_kelas&pesan=error");
    exit;
}

// --- Nama sekolah untuk header tiap sheet ---
$nama_sekolah = '';
$q_sek = $koneksi->query("SELECT nama_sekolah FROM dt_data_sekolah LIMIT 1");
if ($q_sek && $q_sek->num_rows > 0) {
    $nama_sekolah = trim($q_sek->fetch_assoc()['nama_sekolah']);
}

// --- Data jurusan ---
$jurusan_map = [];
$q_jur = $koneksi->query("SELECT id_jurusan, nama_jurusan FROM dt_jurusan");
if ($q_jur) {
    while ($j = $q_jur->fetch_assoc()) {
        $jurusan_map[$j['id_jurusan']] = $j['nama_jurusan'];
    }
}

// --- Wali kelas per kelas (TA aktif) ---
$wali_map = [];
$stmt = $koneksi->prepare("SELECT w.id_kelas, g.nama_guru FROM dt_wali_kelas w JOIN dt_guru g ON g.id_guru = w.id_guru WHERE w.id_tahun_ajaran = ?");
if ($stmt) {
    $stmt->bind_param("i", $id_ta_aktif);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($w = $res->fetch_assoc()) {
        $wali_map[$w['id_kelas']] = $w['nama_guru'];
    }
    $stmt->close();
}

// --- Daftar kelas (TA aktif), urut tingkat lalu nama kelas ---
$kelas_list = [];
$q_kelas = $koneksi->query("
    SELECT k.id_kelas, k.nama_kelas, k.tingkat, k.id_jurusan
    FROM dt_kelas k
    WHERE k.id_tahun_ajaran = $id_ta_aktif
    ORDER BY
        CASE k.tingkat
            WHEN 'X' THEN 1
            WHEN 'XI' THEN 2
            WHEN 'XII' THEN 3
            WHEN 'XIII' THEN 4
            ELSE 5
        END,
        k.nama_kelas ASC
");
if ($q_kelas) {
    while ($k = $q_kelas->fetch_assoc()) {
        $kelas_list[] = $k;
    }
}

// --- Siswa per kelas (TA aktif), satu query lalu dikelompokkan ---
$siswa_map = [];
$stmt = $koneksi->prepare("SELECT ksw.id_kelas, s.nipd, s.nisn, s.nama, s.jk
                            FROM dt_kelas_siswa ksw
                            INNER JOIN dt_siswa s ON s.id_siswa = ksw.id_siswa
                            WHERE ksw.id_tahun_ajaran = ?
                            ORDER BY s.nama ASC");
if ($stmt) {
    $stmt->bind_param("i", $id_ta_aktif);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($s = $res->fetch_assoc()) {
        $siswa_map[$s['id_kelas']][] = $s;
    }
    $stmt->close();
}

// --- Helper: judul sheet unik & valid (maks 31 karakter) ---
function buat_judul_sheet($nama, &$dipakai)
{
    $t = str_replace(['\\', '/', '*', '?', ':', '[', ']'], ' ', $nama);
    $t = preg_replace('/\s+/', ' ', $t);
    $t = trim($t);
    if ($t === '') {
        $t = 'Kelas';
    }
    $t = function_exists('mb_substr') ? mb_substr($t, 0, 31, 'UTF-8') : substr($t, 0, 31);

    $base = $t;
    $i = 2;
    while (isset($dipakai[$t])) {
        $suf = ' (' . $i . ')';
        $t = (function_exists('mb_substr') ? mb_substr($base, 0, 31 - strlen($suf), 'UTF-8') : substr($base, 0, 31 - strlen($suf))) . $suf;
        $i++;
    }
    $dipakai[$t] = true;
    return $t;
}

// --- Helper: sisipkan gambar kop di atas sheet (anchor A1) ---
function tambah_kop_ke_sheet($sheet, $path)
{
    $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
    $drawing->setName('Kop Sekolah');
    $drawing->setDescription('Kop Sekolah');
    $drawing->setPath($path);
    $drawing->setHeight(64);
    $drawing->setCoordinates('A1');
    $drawing->setOffsetX(5);
    $drawing->setOffsetY(5);
    $drawing->setWorksheet($sheet);
}

// --- Kop sekolah aktif (gambar dari dt_kop_sekolah) ---
$kop_gambar = null;
$q_kop = $koneksi->query("SELECT gambar FROM dt_kop_sekolah WHERE status = 'aktif' ORDER BY id_kop DESC LIMIT 1");
if ($q_kop && $q_kop->num_rows > 0) {
    $gk = trim((string)$q_kop->fetch_assoc()['gambar']);
    if ($gk !== '') {
        $cand = __DIR__ . '/../assets/img/kop/' . $gk;
        if (is_file($cand)) {
            $kop_gambar = $cand;
        }
    }
}
// Bila kop ada, konten digeser turun 4 baris agar tidak menimpa gambar.
$ofs = ($kop_gambar !== null) ? 4 : 0;

$spreadsheet = new Spreadsheet();
$dipakai = [];
$is_first = true;

if (empty($kelas_list)) {
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Info');
    if ($kop_gambar !== null) {
        tambah_kop_ke_sheet($sheet, $kop_gambar);
    }
    $cell_info = 'A' . (1 + $ofs);
    $sheet->setCellValue($cell_info, 'Belum ada data kelas pada tahun ajaran ' . $nama_ta_aktif);
    $sheet->getStyle($cell_info)->getFont()->setBold(true);
    $sheet->getColumnDimension('A')->setWidth(60);
} else {
    foreach ($kelas_list as $kelas) {
        $idk = $kelas['id_kelas'];

        if ($is_first) {
            $sheet = $spreadsheet->getActiveSheet();
            $is_first = false;
        } else {
            $sheet = $spreadsheet->createSheet();
        }
        $sheet->setTitle(buat_judul_sheet($kelas['nama_kelas'], $dipakai));

        // Baris dinamis: digeser turun bila ada kop
        $o = $ofs;
        $r_t1 = 1 + $o;
        $r_t2 = 2 + $o;
        $r_i1 = 4 + $o;
        $r_i2 = 5 + $o;
        $r_i3 = 6 + $o;
        $r_hdr = 8 + $o;
        $r_data = 9 + $o;

        // Kop sekolah di atas tiap sheet
        if ($kop_gambar !== null) {
            tambah_kop_ke_sheet($sheet, $kop_gambar);
        }

        // Judul
        $sheet->mergeCells("A{$r_t1}:F{$r_t1}");
        $sheet->setCellValue("A{$r_t1}", 'DAFTAR HADIR SISWA');
        $sheet->mergeCells("A{$r_t2}:F{$r_t2}");
        $sheet->setCellValue("A{$r_t2}", $nama_sekolah !== '' ? $nama_sekolah : 'Sekolah');

        // Info kelas
        $sheet->setCellValue("A{$r_i1}", 'Kelas');
        $sheet->mergeCells("B{$r_i1}:C{$r_i1}");
        $sheet->setCellValue("B{$r_i1}", $kelas['nama_kelas']);
        $sheet->setCellValue("D{$r_i1}", 'Tingkat');
        $sheet->mergeCells("E{$r_i1}:F{$r_i1}");
        $sheet->setCellValue("E{$r_i1}", $kelas['tingkat']);

        $sheet->setCellValue("A{$r_i2}", 'Jurusan');
        $sheet->mergeCells("B{$r_i2}:C{$r_i2}");
        $sheet->setCellValue("B{$r_i2}", $jurusan_map[$kelas['id_jurusan']] ?? '-');
        $sheet->setCellValue("D{$r_i2}", 'Wali Kelas');
        $sheet->mergeCells("E{$r_i2}:F{$r_i2}");
        $sheet->setCellValue("E{$r_i2}", $wali_map[$idk] ?? '-');

        $sheet->setCellValue("A{$r_i3}", 'Tahun Ajaran');
        $sheet->mergeCells("B{$r_i3}:C{$r_i3}");
        $sheet->setCellValue("B{$r_i3}", $nama_ta_aktif);

        // Header tabel
        $sheet->fromArray(['No', 'NIS', 'NISN', 'Nama Siswa', 'L/P', 'Tanda Tangan'], NULL, "A{$r_hdr}");

        // Baris data siswa
        $rows = $siswa_map[$idk] ?? [];
        $r = $r_data;
        $no = 1;
        foreach ($rows as $s) {
            $sheet->setCellValue('A' . $r, $no++);
            $sheet->setCellValueExplicit('B' . $r, (string)($s['nipd'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C' . $r, (string)($s['nisn'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValue('D' . $r, (string)($s['nama'] ?? ''));
            $sheet->setCellValue('E' . $r, strtoupper((string)($s['jk'] ?? '')));
            $r++;
        }

        if (empty($rows)) {
            $sheet->mergeCells("A{$r_data}:F{$r_data}");
            $sheet->setCellValue("A{$r_data}", 'Belum ada siswa di kelas ini');
            $last_row = $r_data;
        } else {
            $last_row = $r - 1;
        }

        // Styling judul
        $sheet->getStyle("A{$r_t1}")->applyFromArray(['font' => ['bold' => true, 'size' => 14], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);
        $sheet->getStyle("A{$r_t2}")->applyFromArray(['font' => ['bold' => true, 'size' => 12], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

        // Label info
        foreach (["A{$r_i1}", "D{$r_i1}", "A{$r_i2}", "D{$r_i2}", "A{$r_i3}"] as $lbl) {
            $sheet->getStyle($lbl)->getFont()->setBold(true);
        }

        // Styling header tabel
        $sheet->getStyle("A{$r_hdr}:F{$r_hdr}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Border + perataan data
        $sheet->getStyle("A{$r_data}:F{$last_row}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        if (!empty($rows)) {
            $sheet->getStyle("A{$r_data}:A{$last_row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$r_data}:E{$last_row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Lebar kolom
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(34);
        $sheet->getColumnDimension('E')->setWidth(7);
        $sheet->getColumnDimension('F')->setWidth(20);

        $sheet->freezePane("A{$r_data}");
    }
}

// --- Output xlsx ---
$writer = new Xlsx($spreadsheet);
$ta_file = str_replace('/', '-', $nama_ta_aktif);
$filename = 'Absensi_Kelas_' . $ta_file . '_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Pragma: public');

$writer->save('php://output');
exit;
