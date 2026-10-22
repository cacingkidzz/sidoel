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

// --- Tentukan Tahun Ajaran: dari ?ta= (int) atau dari session ---
$id_ta = isset($_GET['ta']) ? (int)$_GET['ta'] : 0;

if ($id_ta <= 0) {
    $nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
    if ($nama_ta_sesi !== '' && !$koneksi->connect_error) {
        $stmt = $koneksi->prepare("SELECT id_tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $nama_ta_sesi);
            $stmt->execute();
            $r = $stmt->get_result();
            if ($r && ($row = $r->fetch_assoc())) {
                $id_ta = (int)$row['id_tahun_ajaran'];
            }
            $stmt->close();
        }
    }
}

if ($id_ta <= 0) {
    header("Location: ../index.php?menu=siswa_aktif&pesan=error");
    exit;
}

// --- Nama tahun ajaran untuk judul file ---
$nama_ta = '';
$stmt = $koneksi->prepare("SELECT tahun_ajaran FROM dt_tahun_ajar WHERE id_tahun_ajaran = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $id_ta);
    $stmt->execute();
    $r = $stmt->get_result();
    if ($r && ($row = $r->fetch_assoc())) {
        $nama_ta = $row['tahun_ajaran'];
    }
    $stmt->close();
}

// --- Ambil siswa (history-aware, sama seperti konten/siswa_aktif.php) ---
$sql = "SELECT s.*, k.nama_kelas AS kelas_ta, k.tingkat AS tingkat_ta
        FROM dt_siswa s
        LEFT JOIN dt_kelas_siswa ks ON ks.id_siswa = s.id_siswa AND ks.id_tahun_ajaran = ?
        LEFT JOIN dt_kelas k ON k.id_kelas = ks.id_kelas
        WHERE (s.id_tahun_ajaran = ? OR ks.id_kelas_siswa IS NOT NULL)
        ORDER BY COALESCE(k.tingkat, '~') ASC, COALESCE(k.nama_kelas, s.rombel) ASC, s.nama ASC";

$daftar = [];
$stmt = $koneksi->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ii", $id_ta, $id_ta);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $daftar[] = $row;
    }
    $stmt->close();
}

// --- Susun spreadsheet ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Siswa');

$header = [
    'No',
    'NIS',
    'NISN',
    'Nama Siswa',
    'Jenis Kelamin (L/P)',
    'Tempat Lahir',
    'Tanggal Lahir',
    'NIK',
    'Agama',
    'Alamat',
    'RT/RW',
    'Dusun',
    'Kelurahan/Desa',
    'Kecamatan',
    'Kode Pos',
    'Jenis Tinggal',
    'Transportasi',
    'Telepon',
    'HP',
    'Email',
    'Nama Ayah',
    'Tahun Lahir Ayah',
    'Pendidikan Ayah',
    'Pekerjaan Ayah',
    'Penghasilan Ayah',
    'NIK Ayah',
    'Nama Ibu',
    'Tahun Lahir Ibu',
    'Pendidikan Ibu',
    'Pekerjaan Ibu',
    'Penghasilan Ibu',
    'NIK Ibu',
    'Nama Wali',
    'Tahun Lahir Wali',
    'Pendidikan Wali',
    'Pekerjaan Wali',
    'Penghasilan Wali',
    'NIK Wali',
    'Jurusan',
    'Rombel',
    'Sekolah Asal',
    'Anak Ke',
    'No. KK',
    'Berat Badan (kg)',
    'Tinggi Badan (cm)',
    'Jml Saudara Kandung',
    'Jarak Rumah ke Sekolah (km)',
    'Lintang',
    'Bujur',
    'Nama File Foto',
];
$sheet->fromArray($header, NULL, 'A1');

$highestCol = $sheet->getHighestColumn();
$sheet->getStyle('A1:' . $highestCol . '1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '007bff']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);

$rowNumber = 2;
$no = 1;
foreach ($daftar as $s) {
    if (!empty($s['kelas_ta'])) {
        $rombel = trim(($s['tingkat_ta'] ?? '') . ' - ' . $s['kelas_ta']);
    } else {
        $rombel = $s['rombel'] ?? '';
    }
    $tgl_lahir = (!empty($s['tanggal_lahir']) && $s['tanggal_lahir'] !== '0000-00-00')
        ? date('d-m-Y', strtotime($s['tanggal_lahir'])) : '';
    $alamat = str_replace(["\r", "\n"], ' ', (string)($s['alamat'] ?? ''));

    $data = [
        $no++,
        (string)($s['nipd'] ?? ''),
        (string)($s['nisn'] ?? ''),
        (string)($s['nama'] ?? ''),
        (string)($s['jk'] ?? ''),
        (string)($s['tempat_lahir'] ?? ''),
        $tgl_lahir,
        (string)($s['nik'] ?? ''),
        (string)($s['agama'] ?? ''),
        $alamat,
        (string)($s['rt_rw'] ?? ''),
        (string)($s['dusun'] ?? ''),
        (string)($s['kelurahan'] ?? ''),
        (string)($s['kecamatan'] ?? ''),
        (string)($s['kode_pos'] ?? ''),
        (string)($s['jenis_tinggal'] ?? ''),
        (string)($s['transportasi'] ?? ''),
        (string)($s['telp'] ?? ''),
        (string)($s['hp'] ?? ''),
        (string)($s['email'] ?? ''),
        (string)($s['nama_ayah'] ?? ''),
        (string)($s['tahun_lahir_ayah'] ?? ''),
        (string)($s['pendidikan_ayah'] ?? ''),
        (string)($s['pekerjaan_ayah'] ?? ''),
        (string)($s['penghasilan_ayah'] ?? ''),
        (string)($s['nik_ayah'] ?? ''),
        (string)($s['nama_ibu'] ?? ''),
        (string)($s['tahun_lahir_ibu'] ?? ''),
        (string)($s['pendidikan_ibu'] ?? ''),
        (string)($s['pekerjaan_ibu'] ?? ''),
        (string)($s['penghasilan_ibu'] ?? ''),
        (string)($s['nik_ibu'] ?? ''),
        (string)($s['nama_wali'] ?? ''),
        (string)($s['tahun_lahir_wali'] ?? ''),
        (string)($s['pendidikan_wali'] ?? ''),
        (string)($s['pekerjaan_wali'] ?? ''),
        (string)($s['penghasilan_wali'] ?? ''),
        (string)($s['nik_wali'] ?? ''),
        (string)($s['jurusan'] ?? ''),
        $rombel,
        (string)($s['sekolah_asal'] ?? ''),
        (string)($s['anak_ke_berapa'] ?? ''),
        (string)($s['no_kk'] ?? ''),
        (string)($s['berat_badan'] ?? ''),
        (string)($s['tinggi_badan'] ?? ''),
        (string)($s['jml_saudara_kandung'] ?? ''),
        (string)($s['jarak_rumah_ke_sekolah_km'] ?? ''),
        (string)($s['lintang'] ?? ''),
        (string)($s['bujur'] ?? ''),
        (string)($s['foto'] ?? ''),
    ];
    $sheet->fromArray($data, NULL, 'A' . $rowNumber);
    $rowNumber++;
}

// Kolom bernilai panjang (NIS/NISN/NIK/KK) simpan sebagai teks agar nol depan aman
if ($rowNumber > 2) {
    foreach ([2, 3, 8, 26, 32, 38, 43] as $colIdx) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
        for ($rr = 2; $rr < $rowNumber; $rr++) {
            $val = $sheet->getCell($colLetter . $rr)->getValue();
            if ($val !== null && $val !== '') {
                $sheet->setCellValueExplicit($colLetter . $rr, (string)$val, DataType::TYPE_STRING);
            }
        }
    }
}

foreach (range('A', $highestCol) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
$sheet->freezePane('A2');

// --- Output ---
$writer = new Xlsx($spreadsheet);
$ta_file = str_replace('/', '-', $nama_ta !== '' ? $nama_ta : 'TA');
$filename = 'Data_Siswa_' . $ta_file . '_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Pragma: public');

$writer->save('php://output');
exit;
