<?php
require_once(__DIR__ . '/akses_guard.php');
include("../koneksi.php");
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

if (isset($_POST['import'])) {
    if ($_FILES['fileExcel']['error'] == 0) {
        $file_tmp = $_FILES['fileExcel']['tmp_name'];

        try {
            // Load file Excel
            $spreadsheet = IOFactory::load($file_tmp);
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            $inserted = 0;
            $skipped  = 0;

            // Loop data mulai baris ke-2 (baris 1 = header)
            for ($row = 2; $row <= $highestRow; $row++) {
                $nama_guru              = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("A" . $row)->getValue());
                $nuptk                  = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("B" . $row)->getValue());
                $jk                     = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("C" . $row)->getValue());
                $tempat_lahir           = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("D" . $row)->getValue());
                $tanggal_lahir          = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("E" . $row)->getValue());
                $nip                    = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("F" . $row)->getValue());
                $status_kepegawaian     = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("G" . $row)->getValue());
                $agama                  = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("H" . $row)->getValue());
                $alamat                 = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("I" . $row)->getValue());
                $desa_kelurahan         = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("J" . $row)->getValue());
                $kecamatan              = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("K" . $row)->getValue());
                $lulusan                = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("L" . $row)->getValue());
                $jurusan                = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("M" . $row)->getValue());
                $hp                     = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("N" . $row)->getValue());
                $email                  = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("O" . $row)->getValue());
                $tugas_tambahan         = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("P" . $row)->getValue());
                $sk_pengangkatan        = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("Q" . $row)->getValue());
                $tmt_pengangkatan       = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("R" . $row)->getValue());
                $lembaga_pengangkatan   = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("S" . $row)->getValue());
                $nama_ibu_kandung       = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("T" . $row)->getValue());
                $status_perkawinan      = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("U" . $row)->getValue());
                $nama_suami_istri       = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("V" . $row)->getValue());
                $sudah_lisensi          = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("W" . $row)->getValue());
                $npwp                   = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("X" . $row)->getValue());
                $nama_wajib_pajak       = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("Y" . $row)->getValue());
                $kewarganegaraan        = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("Z" . $row)->getValue());
                $bank                   = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("AA" . $row)->getValue());
                $nomor_rekening_bank    = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("AB" . $row)->getValue());
                $rekening_atas_nama     = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("AC" . $row)->getValue());
                $nik                    = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("AD" . $row)->getValue());
                $no_kk                  = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("AE" . $row)->getValue());
                $lintang                = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("AF" . $row)->getValue());
                $bujur                  = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("AG" . $row)->getValue());
                $password               = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("AH" . $row)->getValue());
                $foto                   = mysqli_real_escape_string($koneksi, (string)$sheet->getCell("AI" . $row)->getValue());

                if (trim($nama_guru) != "") {
                    // Cek duplikat berdasarkan NIP atau NUPTK
                    $cek = mysqli_query($koneksi, "SELECT id_guru FROM dt_guru WHERE nip='$nip' OR nuptk='$nuptk'");

                    if (mysqli_num_rows($cek) == 0) {
                        $sql = "INSERT INTO dt_guru 
                            (nama_guru, nuptk, jk, tempat_lahir, tanggal_lahir, nip, status_kepegawaian, agama, alamat, desa_kelurahan, kecamatan, lulusan, jurusan, hp, email, tugas_tambahan, sk_pengangkatan, tmt_pengangkatan, lembaga_pengangkatan, nama_ibu_kandung, status_perkawinan, nama_suami_istri, sudah_lisensi_kepala_sekolah, npwp, nama_wajib_pajak, kewarganegaraan, bank, nomor_rekening_bank, rekening_atas_nama, nik, no_kk, lintang, bujur, password, foto)
                            VALUES
                            ('$nama_guru','$nuptk','$jk','$tempat_lahir','$tanggal_lahir','$nip','$status_kepegawaian','$agama','$alamat','$desa_kelurahan','$kecamatan','$lulusan','$jurusan','$hp','$email','$tugas_tambahan','$sk_pengangkatan','$tmt_pengangkatan','$lembaga_pengangkatan','$nama_ibu_kandung','$status_perkawinan','$nama_suami_istri','$sudah_lisensi','$npwp','$nama_wajib_pajak','$kewarganegaraan','$bank','$nomor_rekening_bank','$rekening_atas_nama','$nik','$no_kk','$lintang','$bujur','$password','$foto')";

                        mysqli_query($koneksi, $sql);
                        $inserted++;
                    } else {
                        $skipped++;
                    }
                }
            }

            echo "<script>alert('Import selesai! $inserted data ditambahkan, $skipped duplikat dilewati.');window.location='../index.php?menu=guru';</script>";
        } catch (Exception $e) {
            echo "<script>alert('Gagal membaca file Excel: " . $e->getMessage() . "');window.location='../index.php?menu=guru';</script>";
        }
    } else {
        echo "<script>alert('Upload file gagal!');window.location='../index.php?menu=guru';</script>";
    }
}
