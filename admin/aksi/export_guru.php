<?php
require_once(__DIR__ . '/akses_guard.php');
// Pastikan file koneksi.php sudah disesuaikan jalurnya
include("../koneksi.php");

// Pastikan autoloader Composer sudah di-include (sesuaikan jika berbeda)
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Pastikan skrip tidak memiliki batas waktu
set_time_limit(0);

// 1. Buat Objek Spreadsheet Baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Guru Sekolah');

// 2. Tentukan Header Kolom CSV (Sesuai dengan semua 36 kolom di dt_guru)
$header_row = [
    'ID Guru',
    'Nama Lengkap',
    'NUPTK',
    'Jenis Kelamin (L/P)',
    'Tempat Lahir',
    'Tanggal Lahir',
    'NIP',
    'Status Kepegawaian',
    'Agama',
    'Alamat Lengkap',
    'Desa/Kelurahan',
    'Kecamatan',
    'Pendidikan Terakhir (Lulusan)',
    'Jurusan/Prodi',
    'No. HP',
    'Email',
    'Tugas Tambahan',
    'No. SK Pengangkatan',
    'TMT Pengangkatan',
    'Lembaga Pengangkatan',
    'Nama Ibu Kandung',
    'Status Perkawinan',
    'Nama Suami/Istri',
    'Lisensi Kepala Sekolah',
    'NPWP',
    'Nama Wajib Pajak',
    'Kewarganegaraan',
    'Nama Bank',
    'No. Rekening Bank',
    'Rekening Atas Nama',
    'NIK',
    'No. KK',
    'Lintang (Koordinat)',
    'Bujur (Koordinat)',
    'Password', // Diisi dengan placeholder
    'Nama File Foto'
];

// 3. Tulis Header ke Baris Pertama (Baris 1)
$sheet->fromArray($header_row, NULL, 'A1');

// === Styling Header (Opsional) ===
$highestColumn = $sheet->getHighestColumn();
$sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => ['rgb' => '007bff']], // Biru Primary
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

// 4. Query Data dari Database (Semua 36 Kolom)
$sql_cols = "id_guru, nama_guru, nuptk, jk, tempat_lahir, tanggal_lahir, nip, status_kepegawaian, agama, alamat, desa_kelurahan, kecamatan, lulusan, jurusan, hp, email, tugas_tambahan, sk_pengangkatan, tmt_pengangkatan, lembaga_pengangkatan, nama_ibu_kandung, status_perkawinan, nama_suami_istri, sudah_lisensi_kepala_sekolah, npwp, nama_wajib_pajak, kewarganegaraan, bank, nomor_rekening_bank, rekening_atas_nama, nik, no_kk, lintang, bujur, password, foto";

$sql = "SELECT {$sql_cols} FROM dt_guru ORDER BY nama_guru ASC";
$result = $koneksi->query($sql);

$row_number = 2; // Mulai menulis data dari Baris 2

if ($result && $result->num_rows > 0) {
    // 5. Loop dan Siapkan Data
    while ($row = $result->fetch_assoc()) {

        // --- DATA PROCESSING SAMA SEPERTI SEBELUMNYA ---
        $tgl_lahir_formatted = !empty($row['tanggal_lahir']) && $row['tanggal_lahir'] !== '0000-00-00' ? date('Y-m-d', strtotime($row['tanggal_lahir'])) : ''; // Format ISO untuk Excel
        $tmt_formatted = !empty($row['tmt_pengangkatan']) && $row['tmt_pengangkatan'] !== '0000-00-00' ? date('Y-m-d', strtotime($row['tmt_pengangkatan'])) : ''; // Format ISO untuk Excel
        $alamat_clean = str_replace(["\r", "\n"], ' ', $row['alamat']);
        $lisensi_ks = ($row['sudah_lisensi_kepala_sekolah'] == 1) ? 'Ya' : 'Tidak';
        $masked_password = !empty($row['password']) ? '[HASH PASSWORD]' : '';

        $data_row = [
            $row['id_guru'],
            $row['nama_guru'],
            $row['nuptk'],
            $row['jk'],
            $row['tempat_lahir'],
            $tgl_lahir_formatted,
            $row['nip'],
            $row['status_kepegawaian'],
            $row['agama'],
            $alamat_clean,
            $row['desa_kelurahan'],
            $row['kecamatan'],
            $row['lulusan'],
            $row['jurusan'],
            $row['hp'],
            $row['email'],
            $row['tugas_tambahan'],
            $row['sk_pengangkatan'],
            $tmt_formatted,
            $row['lembaga_pengangkatan'],
            $row['nama_ibu_kandung'],
            $row['status_perkawinan'],
            $row['nama_suami_istri'],
            $lisensi_ks,
            $row['npwp'],
            $row['nama_wajib_pajak'],
            $row['kewarganegaraan'],
            $row['bank'],
            $row['nomor_rekening_bank'],
            $row['rekening_atas_nama'],
            $row['nik'],
            $row['no_kk'],
            $row['lintang'],
            $row['bujur'],
            $masked_password,
            $row['foto']
        ];

        // Tulis data baris ke sheet
        $sheet->fromArray($data_row, NULL, 'A' . $row_number);
        $row_number++;
    }
}

// 6. Atur Lebar Kolom Otomatis (Opsional)
foreach (range('A', $sheet->getHighestColumn()) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// 7. Output File XLSX
$writer = new Xlsx($spreadsheet);
$filename = 'Data_Guru_Lengkap_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
// Jika Anda menggunakan IE 9 atau lebih tua
header('Cache-Control: max-age=1');

// Jika Anda menggunakan IIS
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

$writer->save('php://output');
exit;
