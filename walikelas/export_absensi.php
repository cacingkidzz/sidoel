<?php
require_once __DIR__ . '/_init.php';
wk_wajib_login();

require __DIR__ . '/../admin/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

set_time_limit(0);

$wk = $_SESSION['walikelas'];
$id_guru = (int)($wk['id_guru'] ?? 0);

$ta = wk_ta_aktif($koneksi);
$id_ta = $ta['id'];
$nama_ta = $ta['nama'];

$kelas_wali = wk_kelas_wali($koneksi, $id_guru, $id_ta);

$id_kelas = (int)($_GET['kelas'] ?? 0);
$valid = false;
foreach ($kelas_wali as $k) { if ((int)$k['id_kelas'] === $id_kelas) { $valid = true; break; } }
if (!$valid) $id_kelas = $kelas_wali ? (int)$kelas_wali[0]['id_kelas'] : 0;

if ($id_kelas <= 0) {
    wk_flash_set('danger', 'Belum ada kelas yang diwalikan pada tahun ajaran ' . $nama_ta . '.');
    header('Location: absensi.php');
    exit;
}

$bulan = (string)($_GET['bulan'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) $bulan = date('Y-m');
$awal  = $bulan . '-01';
$akhir = date('Y-m-t', strtotime($awal));

$nama_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
               '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$label_bulan = ($nama_bulan[substr($bulan, 5, 2)] ?? '') . ' ' . substr($bulan, 0, 4);

$info = wk_kelas_info($koneksi, $id_kelas, $id_ta);
$kelas_label = wk_kelas_label(['tingkat'=>$info['tingkat'], 'nama_kelas'=>$info['nama_kelas'], 'nama_jurusan'=>$info['jurusan']]);

$wali = '';
$stmt = $koneksi->prepare("SELECT g.nama_guru FROM dt_wali_kelas w JOIN dt_guru g ON g.id_guru = w.id_guru
                           WHERE w.id_kelas = ? AND w.id_tahun_ajaran = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('ii', $id_kelas, $id_ta);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $wali = $r['nama_guru'] ?? '';
}

$nama_sekolah = 'Sekolah';
$q = $koneksi->query("SELECT nama_sekolah, alamat_sekolah FROM dt_data_sekolah LIMIT 1");
if ($q && $q->num_rows) {
    $d = $q->fetch_assoc();
    if (!empty($d['nama_sekolah'])) $nama_sekolah = trim($d['nama_sekolah']);
}

$libur_map = laporan_hari_libur($koneksi, $id_ta, $awal, $akhir);
$libur_in  = $libur_map ? "'" . implode("','", array_map([$koneksi, 'real_escape_string'], array_keys($libur_map))) . "'" : '';

$roster = wk_siswa_kelas($koneksi, $id_kelas, $id_ta);

$mat = [];
$tanggal_set = [];
$stmt = $koneksi->prepare("SELECT id_siswa, tanggal, status FROM dt_absensi
                           WHERE id_kelas = ? AND id_tahun_ajaran = ? AND tanggal BETWEEN ? AND ?"
                           . ($libur_in ? " AND tanggal NOT IN ($libur_in)" : "")
                           . " ORDER BY tanggal ASC");
if ($stmt) {
    $stmt->bind_param('iiss', $id_kelas, $id_ta, $awal, $akhir);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $mat[(int)$r['id_siswa']][$r['tanggal']] = $r['status'];
        $tanggal_set[$r['tanggal']] = true;
    }
    $stmt->close();
}
$tanggal_list = array_keys($tanggal_set);
sort($tanggal_list);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rekap Absensi');

// --- Kop sekolah aktif (opsional, digeser 4 baris) ---
$kop_path = null;
$q_kop = $koneksi->query("SELECT gambar FROM dt_kop_sekolah WHERE status = 'aktif' ORDER BY id_kop DESC LIMIT 1");
if ($q_kop && $q_kop->num_rows) {
    $gk = trim((string)$q_kop->fetch_assoc()['gambar']);
    if ($gk !== '' && is_file(__DIR__ . '/../admin/assets/img/kop/' . $gk)) {
        $kop_path = __DIR__ . '/../admin/assets/img/kop/' . $gk;
    }
}
$ofs = $kop_path ? 4 : 0;

if ($kop_path) {
    $drawing = new Drawing();
    $drawing->setName('Kop Sekolah');
    $drawing->setDescription('Kop Sekolah');
    $drawing->setPath($kop_path);
    $drawing->setHeight(64);
    $drawing->setCoordinates('A1');
    $drawing->setOffsetX(5);
    $drawing->setOffsetY(5);
    $drawing->setWorksheet($sheet);
}

$n_tgl = count($tanggal_list);
$c_tot = 4 + $n_tgl;          // kolom H
$c_pct = $c_tot + 4;          // kolom %
$last_col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c_pct);

$sheet->mergeCells("A" . (1 + $ofs) . ":{$last_col}" . (1 + $ofs));
$sheet->setCellValue('A' . (1 + $ofs), 'REKAP ABSENSI HARIAN SISWA');
$sheet->mergeCells("A" . (2 + $ofs) . ":{$last_col}" . (2 + $ofs));
$sheet->setCellValue('A' . (2 + $ofs), $nama_sekolah);
$sheet->mergeCells("A" . (3 + $ofs) . ":{$last_col}" . (3 + $ofs));
$sheet->setCellValue('A' . (3 + $ofs), 'Kelas: ' . $kelas_label . '    |    Wali Kelas: ' . ($wali !== '' ? $wali : '-'));
$sheet->mergeCells("A" . (4 + $ofs) . ":{$last_col}" . (4 + $ofs));
$sheet->setCellValue('A' . (4 + $ofs), 'Tahun Ajaran: ' . $nama_ta . '    |    Bulan: ' . $label_bulan . '    |    Jumlah Siswa: ' . count($roster));

$sheet->getStyle('A' . (1 + $ofs))->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A' . (1 + $ofs))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A' . (2 + $ofs))->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A' . (2 + $ofs))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A' . (3 + $ofs) . ":{$last_col}" . (4 + $ofs))->getFont()->setSize(10);

$r_head = 6 + $ofs;
$r_legend = 5 + $ofs;
$sheet->mergeCells("A{$r_legend}:{$last_col}{$r_legend}");
$sheet->setCellValue("A{$r_legend}", 'Keterangan: H = Hadir, S = Sakit, I = Izin, A = Alpa' . ($libur_map ? '  |  Hari libur dikecualikan' : ''));
$sheet->getStyle("A{$r_legend}")->getFont()->setItalic(true)->setSize(9);

$headers = ['No', 'NIS', 'Nama Siswa'];
foreach ($tanggal_list as $tg) {
    $headers[] = (int)date('d', strtotime($tg)) . "\n" . substr(wk_hari_id($tg), 0, 3);
}
$headers[] = 'H';
$headers[] = 'S';
$headers[] = 'I';
$headers[] = 'A';
$headers[] = '% Hadir';

foreach ($headers as $i => $label) {
    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($col . $r_head, $label);
}
$sheet->getStyle("A{$r_head}:{$last_col}{$r_head}")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
]);
$sheet->getRowDimension($r_head)->setRowHeight(28);

$r = $r_head + 1;
if (!$roster) {
    $sheet->mergeCells("A{$r}:{$last_col}{$r}");
    $sheet->setCellValue("A{$r}", 'Belum ada siswa pada kelas ini.');
    $sheet->getStyle("A{$r}")->getFont()->setItalic(true);
    $r++;
} else {
    foreach ($roster as $idx => $sw) {
        $sid = (int)$sw['id_siswa'];
        $h = $s = $i = $a = 0;
        $nis = $sw['nipd'] !== null && $sw['nipd'] !== '' ? $sw['nipd'] : ($sw['nisn'] ?? '');

        $sheet->setCellValue("A{$r}", $idx + 1);
        $sheet->setCellValueExplicit("B{$r}", (string)$nis, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue("C{$r}", $sw['nama']);

        $c = 4;
        foreach ($tanggal_list as $tg) {
            $st = $mat[$sid][$tg] ?? '';
            if ($st === 'H') $h++;
            elseif ($st === 'S') $s++;
            elseif ($st === 'I') $i++;
            elseif ($st === 'A') $a++;

            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            if ($st !== '') {
                $sheet->setCellValue($col . $r, $st);
                $warna = ['H' => 'D1E7DD', 'S' => 'CFE2FF', 'I' => 'FFF3CD', 'A' => 'F8D7DA'][$st] ?? null;
                if ($warna) $sheet->getStyle($col . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($warna);
            }
            $c++;
        }

        $total = $h + $s + $i + $a;
        $pct = $total > 0 ? round($h / $total * 100, 1) : 0;

        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c_tot) . $r, $h);
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c_tot + 1) . $r, $s);
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c_tot + 2) . $r, $i);
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c_tot + 3) . $r, $a);
        $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c_pct) . $r, $pct);
        $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c_pct) . $r)->getNumberFormat()->setFormatCode('0.0"%"');
        if ($a > 0) {
            $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c_tot + 3) . $r)
                  ->getFont()->getColor()->setRGB('B02A37');
        }

        $r++;
    }
}

$r_last = $r - 1;
if ($r_last > $r_head) {
    $sheet->getStyle("A{$r_head}:{$last_col}{$r_last}")->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B9C2CC']]],
        'font' => ['size' => 9],
    ]);
    $sheet->getStyle("A" . ($r_head + 1) . ":A{$r_last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("B" . ($r_head + 1) . ":B{$r_last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("D" . ($r_head + 1) . ":{$last_col}{$r_last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// --- Baris total ---
$r_sum = $r_last > $r_head ? $r_last + 1 : $r_head + 1;
$sum = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
foreach ($mat as $days) {
    foreach ($days as $st) { if (isset($sum[$st])) $sum[$st]++; }
}
$sheet->mergeCells("A{$r_sum}:C{$r_sum}");
$sheet->setCellValue("A{$r_sum}", 'TOTAL');
$sheet->getStyle("A{$r_sum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c_tot) . $r_sum, $sum['H']);
$sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c_tot + 1) . $r_sum, $sum['S']);
$sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c_tot + 2) . $r_sum, $sum['I']);
$sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c_tot + 3) . $r_sum, $sum['A']);
$grand = $sum['H'] + $sum['S'] + $sum['I'] + $sum['A'];
$sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c_pct) . $r_sum,
    $grand > 0 ? round($sum['H'] / $grand * 100, 1) : 0);
$sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c_pct) . $r_sum)->getNumberFormat()->setFormatCode('0.0"%"');
$sheet->getStyle("A{$r_sum}:{$last_col}{$r_sum}")->applyFromArray([
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B9C2CC']]],
]);

$sheet->getColumnDimension('A')->setWidth(5);
$sheet->getColumnDimension('B')->setWidth(12);
$sheet->getColumnDimension('C')->setWidth(30);
for ($i = 4; $i <= $c_pct; $i++) {
    $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setWidth(5);
}
$sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c_pct))->setWidth(9);
$sheet->freezePane('D' . ($r_head + 1));

$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);

$nama_file = 'Rekap_Absensi_' . preg_replace('/[^A-Za-z0-9]+/', '_', $info['nama_kelas']) . '_' . $bulan . '.xlsx';

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nama_file . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);
exit;
