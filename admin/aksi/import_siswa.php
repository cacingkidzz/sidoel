<?php
require_once(__DIR__ . '/akses_guard.php');
// --- PENGATURAN AWAL ---
// Pastikan file koneksi.php mendefinisikan variabel koneksi $koneksi (mysqli)
include("../koneksi.php");
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// --- FUNGSI PENDUKUNG ---

/**
 * Mendapatkan ID Tahun Ajaran yang sedang aktif
 * Menggunakan koneksi mysqli global ($koneksi)
 */
function getActiveTahunAjaranId($koneksi)
{
    if (!$koneksi) return 0;

    // ASUMSI: Ada tabel dt_tahun_ajaran dengan kolom 'status' (misal: 1 = Aktif)
    $query = mysqli_query($koneksi, "SELECT id_tahun_ajaran FROM dt_tahun_ajar WHERE status = 1 LIMIT 1");

    if ($query && mysqli_num_rows($query) > 0) {
        $result = mysqli_fetch_assoc($query);
        return $result['id_tahun_ajaran'];
    }
    return 0;
}

/**
 * Melakukan hash pada password menggunakan BCRYPT
 */
function hashPassword($password)
{
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Mengubah nilai kosong atau 0 (untuk numerik) menjadi string 'NULL' untuk query SQL.
 */
function cleanNonStringValue($koneksi, $value, $type = 'string')
{
    $value = trim($value);
    if ($value === '' || $value === null) {
        return 'NULL';
    }

    if ($type === 'int') {
        $value = (int)$value;
    } elseif ($type === 'float') {
        $value = (float)$value;
    }

    // Jika nilai adalah 0 setelah di-cast dan type adalah numerik, tetap dianggap NULL (untuk tahun, berat, dll.)
    if (($type === 'int' || $type === 'float') && $value === 0) {
        return 'NULL';
    }

    return "'" . mysqli_real_escape_string($koneksi, $value) . "'";
}


// --- PROSES IMPORT UTAMA ---

if (isset($_POST['import'])) {

    // 1. Validasi File Upload
    if ($_FILES['fileExcel']['error'] !== 0) {
        echo "<script>alert('Upload file gagal! Error code: " . $_FILES['fileExcel']['error'] . "');window.location='../index.php?menu=siswa';</script>";
        exit;
    }

    $file_tmp = $_FILES['fileExcel']['tmp_name'];
    $fileExt = strtolower(pathinfo($_FILES['fileExcel']['name'], PATHINFO_EXTENSION));

    if ($fileExt !== 'xlsx' && $fileExt !== 'xls') {
        echo "<script>alert('Hanya file Excel (.xlsx atau .xls) yang diizinkan.');window.location='../index.php?menu=siswa';</script>";
        exit;
    }

    // 2. Ambil ID Tahun Ajaran Aktif
    $activeTahunAjaranId = getActiveTahunAjaranId($koneksi);
    if ($activeTahunAjaranId === 0) {
        echo "<script>alert('Error: Tidak ditemukan Tahun Ajaran yang aktif di database.');window.location='../index.php?menu=siswa';</script>";
        exit;
    }

    try {
        // Load file Excel
        $spreadsheet = IOFactory::load($file_tmp);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        $inserted = 0;
        $skipped_count = 0;
        $error_messages = [];

        // Looping data mulai baris ke-2 (baris 1 = header)
        for ($row = 2; $row <= $highestRow; $row++) {

            // --- Mengambil dan Membersihkan Data Sesuai Kolom Template (A - AX) ---

            // Kolom A - E (Wajib/String)
            $nama            = mysqli_real_escape_string($koneksi, trim($sheet->getCell("A" . $row)->getValue()));
            $nipd            = mysqli_real_escape_string($koneksi, trim($sheet->getCell("B" . $row)->getValue()));
            $jk              = mysqli_real_escape_string($koneksi, trim($sheet->getCell("C" . $row)->getValue()));
            $nisn            = mysqli_real_escape_string($koneksi, trim($sheet->getCell("D" . $row)->getValue()));
            $tempat_lahir    = mysqli_real_escape_string($koneksi, trim($sheet->getCell("E" . $row)->getValue()));

            // Kolom F: Tanggal Lahir (Perbaikan Error PHPSpreadsheet)
            $cellF = $sheet->getCell("F" . $row);
            $tanggal_lahir = 'NULL';

            if (Date::isDateTime($cellF)) { // Pengecekan menggunakan Objek Cell
                $excel_serial_date = $cellF->getValue();

                if (is_numeric($excel_serial_date) && $excel_serial_date > 0) {
                    $timestamp = Date::excelToTimestamp($excel_serial_date);
                    $tanggal_lahir_sql = date('Y-m-d', $timestamp);
                    $tanggal_lahir = "'" . $tanggal_lahir_sql . "'"; // Format untuk SQL
                }
            }

            // Kolom G - O (String)
            $nik             = mysqli_real_escape_string($koneksi, trim($sheet->getCell("G" . $row)->getValue()));
            $agama           = mysqli_real_escape_string($koneksi, trim($sheet->getCell("H" . $row)->getValue()));
            $alamat          = mysqli_real_escape_string($koneksi, trim($sheet->getCell("I" . $row)->getValue()));
            $rt_rw           = mysqli_real_escape_string($koneksi, trim($sheet->getCell("J" . $row)->getValue()));
            $dusun           = mysqli_real_escape_string($koneksi, trim($sheet->getCell("K" . $row)->getValue()));
            $kelurahan       = mysqli_real_escape_string($koneksi, trim($sheet->getCell("L" . $row)->getValue()));
            $kecamatan       = mysqli_real_escape_string($koneksi, trim($sheet->getCell("M" . $row)->getValue()));
            $kode_pos        = mysqli_real_escape_string($koneksi, trim($sheet->getCell("N" . $row)->getValue()));
            $jenis_tinggal   = mysqli_real_escape_string($koneksi, trim($sheet->getCell("O" . $row)->getValue()));

            // Kolom P - S (String)
            $transportasi    = mysqli_real_escape_string($koneksi, trim($sheet->getCell("P" . $row)->getValue()));
            $telp            = mysqli_real_escape_string($koneksi, trim($sheet->getCell("Q" . $row)->getValue()));
            $hp              = mysqli_real_escape_string($koneksi, trim($sheet->getCell("R" . $row)->getValue()));
            $email           = mysqli_real_escape_string($koneksi, trim($sheet->getCell("S" . $row)->getValue()));

            // Kolom T - Y (Ayah)
            $nama_ayah         = mysqli_real_escape_string($koneksi, trim($sheet->getCell("T" . $row)->getValue()));
            $tahun_lahir_ayah  = cleanNonStringValue($koneksi, $sheet->getCell("U" . $row)->getValue(), 'int');
            $pendidikan_ayah   = mysqli_real_escape_string($koneksi, trim($sheet->getCell("V" . $row)->getValue()));
            $pekerjaan_ayah    = mysqli_real_escape_string($koneksi, trim($sheet->getCell("W" . $row)->getValue()));
            $penghasilan_ayah  = mysqli_real_escape_string($koneksi, trim($sheet->getCell("X" . $row)->getValue()));
            $nik_ayah          = mysqli_real_escape_string($koneksi, trim($sheet->getCell("Y" . $row)->getValue()));

            // Kolom Z - AE (Ibu)
            $nama_ibu          = mysqli_real_escape_string($koneksi, trim($sheet->getCell("Z" . $row)->getValue()));
            $tahun_lahir_ibu   = cleanNonStringValue($koneksi, $sheet->getCell("AA" . $row)->getValue(), 'int');
            $pendidikan_ibu    = mysqli_real_escape_string($koneksi, trim($sheet->getCell("AB" . $row)->getValue()));
            $pekerjaan_ibu     = mysqli_real_escape_string($koneksi, trim($sheet->getCell("AC" . $row)->getValue()));
            $penghasilan_ibu   = mysqli_real_escape_string($koneksi, trim($sheet->getCell("AD" . $row)->getValue()));
            $nik_ibu           = mysqli_real_escape_string($koneksi, trim($sheet->getCell("AE" . $row)->getValue()));

            // Kolom AF - AK (Wali)
            $nama_wali         = mysqli_real_escape_string($koneksi, trim($sheet->getCell("AF" . $row)->getValue()));
            $tahun_lahir_wali  = cleanNonStringValue($koneksi, $sheet->getCell("AG" . $row)->getValue(), 'int');
            $pendidikan_wali   = mysqli_real_escape_string($koneksi, trim($sheet->getCell("AH" . $row)->getValue()));
            $pekerjaan_wali    = mysqli_real_escape_string($koneksi, trim($sheet->getCell("AI" . $row)->getValue()));
            $penghasilan_wali  = mysqli_real_escape_string($koneksi, trim($sheet->getCell("AJ" . $row)->getValue()));
            $nik_wali          = mysqli_real_escape_string($koneksi, trim($sheet->getCell("AK" . $row)->getValue()));

            // Kolom AL - AN (Wajib/String)
            $jurusan         = mysqli_real_escape_string($koneksi, trim($sheet->getCell("AL" . $row)->getValue()));
            $rombel          = mysqli_real_escape_string($koneksi, trim($sheet->getCell("AM" . $row)->getValue()));
            $sekolah_asal    = mysqli_real_escape_string($koneksi, trim($sheet->getCell("AN" . $row)->getValue()));
            $anak_ke_berapa  = cleanNonStringValue($koneksi, $sheet->getCell("AO" . $row)->getValue(), 'int');

            // Kolom AP - AV (Data Fisik/Lokasi)
            $lintang         = cleanNonStringValue($koneksi, $sheet->getCell("AP" . $row)->getValue(), 'float');
            $bujur           = cleanNonStringValue($koneksi, $sheet->getCell("AQ" . $row)->getValue(), 'float');
            $no_kk           = mysqli_real_escape_string($koneksi, trim($sheet->getCell("AR" . $row)->getValue()));
            $berat_badan     = cleanNonStringValue($koneksi, $sheet->getCell("AS" . $row)->getValue(), 'float');
            $tinggi_badan    = cleanNonStringValue($koneksi, $sheet->getCell("AT" . $row)->getValue(), 'float');
            $jml_saudara_kandung = cleanNonStringValue($koneksi, $sheet->getCell("AU" . $row)->getValue(), 'int');
            $jarak_rumah_ke_sekolah_km = cleanNonStringValue($koneksi, $sheet->getCell("AV" . $row)->getValue(), 'float');

            // Kolom AW - AX (Wajib)
            $raw_password    = trim($sheet->getCell("AW" . $row)->getValue());
            $foto            = mysqli_real_escape_string($koneksi, trim($sheet->getCell("AX" . $row)->getValue()));


            // --- Validasi Data Wajib ---
            if (empty($nama) || empty($nipd)) {
                $error_messages[] = "Baris " . $row . ": Data 'Nama' atau 'NIPD' kosong. Baris dilewati.";
                $skipped_count++;
                continue;
            }
            if (empty($jurusan) || empty($rombel)) {
                $error_messages[] = "Baris " . $row . " (Nama: " . $nama . "): Kolom 'Jurusan' atau 'Rombel' wajib diisi.";
                $skipped_count++;
                continue;
            }
            if (empty($raw_password) || empty($foto)) {
                $error_messages[] = "Baris " . $row . " (Nama: " . $nama . "): Kolom 'Password' atau 'Foto' wajib diisi.";
                $skipped_count++;
                continue;
            }

            // Hash Password
            $hashedPassword = hashPassword($raw_password);
            $password_sql = "'" . mysqli_real_escape_string($koneksi, $hashedPassword) . "'";

            // --- Cek Duplikasi (Berdasarkan NISN atau NIPD) ---
            $cek_duplikat = mysqli_query($koneksi, "SELECT id_siswa FROM dt_siswa WHERE nisn='$nisn' OR nipd='$nipd'");

            if (mysqli_num_rows($cek_duplikat) == 0) {

                // Query INSERT
                $sql = "INSERT INTO dt_siswa (
                    id_tahun_ajaran, nama, nipd, jk, nisn, tempat_lahir, tanggal_lahir, 
                    nik, agama, alamat, rt_rw, dusun, kelurahan, kecamatan, kode_pos, 
                    jenis_tinggal, transportasi, telp, hp, email, nama_ayah, tahun_lahir_ayah, 
                    pendidikan_ayah, pekerjaan_ayah, penghasilan_ayah, nik_ayah, nama_ibu, 
                    tahun_lahir_ibu, pendidikan_ibu, pekerjaan_ibu, penghasilan_ibu, nik_ibu, 
                    nama_wali, tahun_lahir_wali, pendidikan_wali, pekerjaan_wali, penghasilan_wali, 
                    nik_wali, jurusan, rombel, sekolah_asal, anak_ke_berapa, 
                    lintang, bujur, no_kk, berat_badan, tinggi_badan, jml_saudara_kandung, 
                    jarak_rumah_ke_sekolah_km, password, foto
                ) VALUES (
                    '$activeTahunAjaranId', '$nama', '$nipd', '$jk', '$nisn', '$tempat_lahir', $tanggal_lahir, 
                    '$nik', '$agama', '$alamat', '$rt_rw', '$dusun', '$kelurahan', '$kecamatan', '$kode_pos', 
                    '$jenis_tinggal', '$transportasi', '$telp', '$hp', '$email', '$nama_ayah', $tahun_lahir_ayah, 
                    '$pendidikan_ayah', '$pekerjaan_ayah', '$penghasilan_ayah', '$nik_ayah', '$nama_ibu', 
                    $tahun_lahir_ibu, '$pendidikan_ibu', '$pekerjaan_ibu', '$penghasilan_ibu', '$nik_ibu', 
                    '$nama_wali', $tahun_lahir_wali, '$pendidikan_wali', '$pekerjaan_wali', '$penghasilan_wali', 
                    '$nik_wali', '$jurusan', '$rombel', '$sekolah_asal', $anak_ke_berapa, 
                    $lintang, $bujur, '$no_kk', $berat_badan, $tinggi_badan, $jml_saudara_kandung, 
                    $jarak_rumah_ke_sekolah_km, $password_sql, '$foto'
                )";

                if (mysqli_query($koneksi, $sql)) {
                    $inserted++;
                } else {
                    $error_messages[] = "Baris " . $row . " (Nama: " . $nama . "): Gagal INSERT ke DB. MySQL Error: " . mysqli_error($koneksi);
                }
            } else {
                $error_messages[] = "Baris " . $row . " (Nama: " . $nama . "): NISN atau NIPD sudah ada (Duplikat). Baris dilewati.";
                $skipped_count++;
            }
        }

        // 4. Laporan Hasil
        $laporan_error = implode('\n', $error_messages);

        // Menggunakan alert yang lebih informatif
        echo "<script>
            var inserted = $inserted;
            var skipped = $skipped_count;
            var errors = \"$laporan_error\";
            
            var msg = 'Import selesai! ' + inserted + ' data ditambahkan.';
            if (skipped > 0) {
                msg += ' ' + skipped + ' duplikat dilewati.';
            }
            if (errors.length > 0) {
                 msg += '\\n\\nBEBERAPA GAGAL (Cek Log):\\n' + errors;
            }
            
            alert(msg);
            window.location='../index.php?menu=siswa_aktif';
        </script>";
    } catch (Exception $e) {
        echo "<script>alert('Gagal membaca file Excel: " . $e->getMessage() . "');window.location='../index.php?menu=siswa_aktif';</script>";
    }
} else {
    // Jika diakses tanpa POST request
    header("Location: ../index.php?menu=siswa_aktif");
    exit;
}
