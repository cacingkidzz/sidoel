<?php
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// 1. Inisialisasi Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('dt_siswa_template');

// --- Definisi Kolom yang Diperlukan (id_siswa dan id_tahun_ajaran Dihilangkan) ---
$columns = [
    'nama',
    'nipd',
    'jk',
    'nisn',
    'tempat_lahir',
    'tanggal_lahir',
    'nik',
    'agama',
    'alamat',
    'rt_rw',
    'dusun',
    'kelurahan',
    'kecamatan',
    'kode_pos',
    'jenis_tinggal',
    'transportasi',
    'telp',
    'hp',
    'email',
    'nama_ayah',
    'tahun_lahir_ayah',
    'pendidikan_ayah',
    'pekerjaan_ayah',
    'penghasilan_ayah',
    'nik_ayah',
    'nama_ibu',
    'tahun_lahir_ibu',
    'pendidikan_ibu',
    'pekerjaan_ibu',
    'penghasilan_ibu',
    'nik_ibu',
    'nama_wali',
    'tahun_lahir_wali',
    'pendidikan_wali',
    'pekerjaan_wali',
    'penghasilan_wali',
    'nik_wali',
    'jurusan (Wajib)',
    'rombel (Wajib)',
    'sekolah_asal',
    'anak_ke_berapa',
    'lintang',
    'bujur',
    'no_kk',
    'berat_badan',
    'tinggi_badan',
    'jml_saudara_kandung',
    'jarak_rumah_ke_sekolah_km',
    'password (Wajib)',
    'foto (Wajib)'
];

// Data Contoh (Baris ke-2), disesuaikan dengan penghilangan 2 kolom pertama
$sampleData = [
    'Budi Santoso',
    '20250001',
    'L',
    '0081234567',
    'Denpasar',
    '2008-01-15', // Tanggal
    '5101011234567890',
    'Islam',
    'Jl. Merdeka No. 10',
    '001/001',
    'Dusun Timur',
    'Kelurahan A',
    'Kecamatan B',
    '80361',
    'Bersama Orang Tua',
    'Motor Pribadi',
    '0361xxxxxx',
    '0812xxxxxx',
    'budi@siswa.com',
    'Ayah Santoso',
    1980,
    'S1',
    'Wiraswasta',
    '1-5 Juta',
    '5101011234567891',
    'Ibu Dewi',
    1985,
    'D3',
    'Ibu Rumah Tangga',
    'Tidak Berpenghasilan',
    '5101011234567892',
    'Wali Paman',
    1975,
    'SMA',
    'Pegawai Swasta',
    '5-10 Juta',
    '5101011234567893',
    'RPL',
    'X RPL 1',
    'SMP Negeri 1',
    1,
    '-8.650000',
    '115.216667',
    '5101019999999999',
    60.5,
    170,
    2,
    5.2,
    '$2y$10$abcdefghijklmnopqrstuvwxyza', // HASH Password 
    '20250001.jpg'
];

// --- Menulis Header dan Contoh Data ---
$columnIndex = 1; // Mulai dari kolom A (indeks 1)

// Menulis Header (Baris 1)
foreach ($columns as $header) {
    // Coordinate untuk baris 1 (A1, B1, C1, dst)
    $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex) . '1';
    $sheet->setCellValue($cellCoordinate, $header);

    // Memberi warna latar belakang untuk header
    $sheet->getStyle($cellCoordinate)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFA0A0A0'); // Abu-abu

    // Mengatur lebar kolom otomatis
    $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);

    $columnIndex++;
}

// Menulis Data Contoh (Baris 2)
$columnIndex = 1;
foreach ($sampleData as $data) {
    // Coordinate untuk baris 2 (A2, B2, C2, dst)
    $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex) . '2';
    $sheet->setCellValue($cellCoordinate, $data);
    $columnIndex++;
}

// Tambahkan gaya Border untuk seluruh data dan header
$styleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
];
$sheet->getStyle('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns)) . '2')->applyFromArray($styleArray);


// --- Konfigurasi File Download ---
$fileName = 'template_dt_siswa_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
