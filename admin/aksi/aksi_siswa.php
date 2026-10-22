<?php
require_once(__DIR__ . '/akses_guard.php');
// --- PENGATURAN AWAL & KONEKSI ---
// Asumsi koneksi.php berada 1 tingkat di atas (misal: /koneksi.php dan /aksi/siswa_aksi.php)
include("../koneksi.php");

// --- FUNGSI BANTUAN ---
function hashPassword($password)
{
    return password_hash($password, PASSWORD_BCRYPT);
}

function getSqlValue($koneksi, $value, $type = 'string')
{
    $value = trim($value);
    if ($value === '' || $value === null) {
        return 'NULL';
    }
    $safe_value = mysqli_real_escape_string($koneksi, $value);
    if ($type === 'int' || $type === 'float') {
        return $safe_value;
    }
    return "'$safe_value'";
}

function resizeImage($path, $max_width = 800, $max_height = 800)
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
        return;
    }
    if ($ext === 'gif') {
        return; // GIF tidak di-resize untuk menjaga animasi
    }
    list($orig_w, $orig_h) = getimagesize($path);
    if ($orig_w === false || $orig_h === false) {
        return;
    }
    if ($orig_w <= $max_width && $orig_h <= $max_height) {
        return;
    }
    $ratio = min($max_width / $orig_w, $max_height / $orig_h);
    $new_w = (int)round($orig_w * $ratio);
    $new_h = (int)round($orig_h * $ratio);

    if ($ext === 'png') {
        $src = imagecreatefrompng($path);
        $dst = imagecreatetruecolor($new_w, $new_h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
        imagepng($dst, $path);
        imagedestroy($dst);
        imagedestroy($src);
    } else {
        $src = imagecreatefromjpeg($path);
        $dst = imagecreatetruecolor($new_w, $new_h);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
        imagejpeg($dst, $path, 85);
        imagedestroy($dst);
        imagedestroy($src);
    }
}

function handleFotoUpload($koneksi, $id_siswa, $action_type, $foto_lama = null)
{
    $foto_name = $foto_lama;
    $upload_dir = "../assets/img/foto_siswa/";
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $file_info = pathinfo($_FILES['foto']['name']);
        $ext = strtolower($file_info['extension']);
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($ext, $allowed_ext)) {
            echo "<script>alert('Format foto tidak valid. Hanya JPG, JPEG, PNG, GIF yang diizinkan.');window.history.back();</script>";
            exit;
        }
        $nama_file_baru = uniqid('foto_') . time() . '.' . $ext;
        $tmp_file = $_FILES['foto']['tmp_name'];
        $path = $upload_dir . $nama_file_baru;
        if (move_uploaded_file($tmp_file, $path)) {
            resizeImage($path);
            $foto_name = $nama_file_baru;
            if ($action_type == 'UPDATE' && $foto_lama && file_exists($upload_dir . $foto_lama)) {
                unlink($upload_dir . $foto_lama);
            }
        } else {
            $error_message = ($action_type == 'UPDATE') ?
                "Gagal mengupload foto baru! Data lain tidak diubah." :
                "Gagal mengupload foto! Data tidak disimpan.";
            echo "<script>alert('$error_message');window.history.back();</script>";
            exit;
        }
    }
    return $foto_name;
}


// --- START PROSES DATA POST ---
if (isset($_POST['submit']) || isset($_POST['update_siswa'])) {
    $is_update = isset($_POST['id_siswa']) && !empty($_POST['id_siswa']);
    $id_siswa = $is_update ? (int)$_POST['id_siswa'] : 0;
    $foto_lama = $is_update ? ($_POST['foto_lama'] ?? null) : null;

    // 1. Ambil dan Sanitasi Data Siswa
    $id_tahun_ajaran   = getSqlValue($koneksi, $_POST['id_tahun_ajar'] ?? '');
    $nama            = getSqlValue($koneksi, $_POST['nama'] ?? '');
    $nipd            = getSqlValue($koneksi, $_POST['nipd'] ?? '');
    $jk              = getSqlValue($koneksi, $_POST['jk'] ?? '');
    $nisn            = getSqlValue($koneksi, $_POST['nisn'] ?? '');
    $tempat_lahir    = getSqlValue($koneksi, $_POST['tempat'] ?? '');
    $tanggal_lahir   = getSqlValue($koneksi, $_POST['tanggal_lahir'] ?? '');
    $nik             = getSqlValue($koneksi, $_POST['nik'] ?? '');
    $agama           = getSqlValue($koneksi, $_POST['agama'] ?? '');
    $alamat          = getSqlValue($koneksi, $_POST['alamat'] ?? '');
    $rt_rw           = getSqlValue($koneksi, $_POST['rt_rw'] ?? '');
    $dusun           = getSqlValue($koneksi, $_POST['dusun'] ?? '');
    $kelurahan       = getSqlValue($koneksi, $_POST['kelurahan'] ?? '');
    $kecamatan       = getSqlValue($koneksi, $_POST['kecamatan'] ?? '');
    $kode_pos        = getSqlValue($koneksi, $_POST['kode_pos'] ?? '');
    $jenis_tinggal   = getSqlValue($koneksi, $_POST['jenis_tinggal'] ?? '');
    $transportasi    = getSqlValue($koneksi, $_POST['transportasi'] ?? '');
    $telp            = getSqlValue($koneksi, $_POST['telp'] ?? '');
    $hp              = getSqlValue($koneksi, $_POST['hp'] ?? '');
    $email           = getSqlValue($koneksi, $_POST['email'] ?? '');
    $nama_ayah         = getSqlValue($koneksi, $_POST['nama_ayah'] ?? '');
    $tahun_lahir_ayah  = getSqlValue($koneksi, $_POST['tahun_lahir_ayah'] ?? '', 'int');
    $pendidikan_ayah   = getSqlValue($koneksi, $_POST['pendidikan_ayah'] ?? '');
    $pekerjaan_ayah    = getSqlValue($koneksi, $_POST['pekerjaan_ayah'] ?? '');
    $penghasilan_ayah  = getSqlValue($koneksi, $_POST['penghasilan_ayah'] ?? '');
    $nik_ayah          = getSqlValue($koneksi, $_POST['nik_ayah'] ?? '');
    $nama_ibu          = getSqlValue($koneksi, $_POST['nama_ibu'] ?? '');
    $tahun_lahir_ibu   = getSqlValue($koneksi, $_POST['tahun_lahir_ibu'] ?? '', 'int');
    $pendidikan_ibu    = getSqlValue($koneksi, $_POST['pendidikan_ibu'] ?? '');
    $pekerjaan_ibu     = getSqlValue($koneksi, $_POST['pekerjaan_ibu'] ?? '');
    $penghasilan_ibu   = getSqlValue($koneksi, $_POST['penghasilan_ibu'] ?? '');
    $nik_ibu           = getSqlValue($koneksi, $_POST['nik_ibu'] ?? '');
    $nama_wali         = getSqlValue($koneksi, $_POST['nama_wali'] ?? '');
    $tahun_lahir_wali  = getSqlValue($koneksi, $_POST['tahun_lahir_wali'] ?? '', 'int');
    $pendidikan_wali   = getSqlValue($koneksi, $_POST['pendidikan_wali'] ?? '');
    $pekerjaan_wali    = getSqlValue($koneksi, $_POST['pekerjaan_wali'] ?? '');
    $penghasilan_wali  = getSqlValue($koneksi, $_POST['penghasilan_wali'] ?? '');
    $nik_wali          = getSqlValue($koneksi, $_POST['nik_wali'] ?? '');
    $jurusan           = getSqlValue($koneksi, $_POST['jurusan'] ?? '');
    $rombel            = getSqlValue($koneksi, $_POST['rombel'] ?? '');
    $sekolah_asal      = getSqlValue($koneksi, $_POST['sekolah_asal'] ?? '');
    $anak_ke_berapa    = getSqlValue($koneksi, $_POST['anak_ke'] ?? '', 'int');
    $no_kk             = getSqlValue($koneksi, $_POST['no_kk'] ?? '');
    $berat_badan       = getSqlValue($koneksi, $_POST['berat_badan'] ?? '', 'float');
    $tinggi_badan      = getSqlValue($koneksi, $_POST['tinggi_badan'] ?? '', 'float');
    $jml_saudara_kandung = getSqlValue($koneksi, $_POST['jml_saudara_kandung'] ?? '', 'int');
    $lintang           = getSqlValue($koneksi, $_POST['latitude'] ?? '');
    $bujur             = getSqlValue($koneksi, $_POST['longitude'] ?? '');
    $jarak_rumah_ke_sekolah_km = getSqlValue($koneksi, $_POST['jarak_rumah_sekolah'] ?? '', 'float');
    $password_new = trim($_POST['password'] ?? '');

    // 2. Penanganan Foto
    $foto = handleFotoUpload($koneksi, $id_siswa, $is_update ? 'UPDATE' : 'INSERT', $foto_lama);
    $foto_field = $foto ? ", foto = '$foto'" : "";

    // 3. Penanganan Password
    $password_field = "";
    if (!empty($password_new)) {
        $hashedPassword = hashPassword($password_new);
        $password_field = ", password = '$hashedPassword'";
    } else if (!$is_update) {
        $default_password = !empty(trim($_POST['nipd'] ?? '')) ? trim($_POST['nipd']) : 'siswa123';
        $hashedPassword = hashPassword($default_password);
        $password_field = ", password = '$hashedPassword'";
    }

    // 4. Perintah SQL
    $kolom = "id_tahun_ajaran, nama, nipd, jk, nisn, tempat_lahir, tanggal_lahir, nik, agama, alamat, rt_rw, dusun, kelurahan, kecamatan, kode_pos, jenis_tinggal, transportasi, telp, hp, email, nama_ayah, tahun_lahir_ayah, pendidikan_ayah, pekerjaan_ayah, penghasilan_ayah, nik_ayah, nama_ibu, tahun_lahir_ibu, pendidikan_ibu, pekerjaan_ibu, penghasilan_ibu, nik_ibu, nama_wali, tahun_lahir_wali, pendidikan_wali, pekerjaan_wali, penghasilan_wali, nik_wali, jurusan, rombel, sekolah_asal, anak_ke_berapa, no_kk, berat_badan, tinggi_badan, jml_saudara_kandung, lintang, bujur, jarak_rumah_ke_sekolah_km";
    $nilai = "$id_tahun_ajaran, $nama, $nipd, $jk, $nisn, $tempat_lahir, $tanggal_lahir, $nik, $agama, $alamat, $rt_rw, $dusun, $kelurahan, $kecamatan, $kode_pos, $jenis_tinggal, $transportasi, $telp, $hp, $email, $nama_ayah, $tahun_lahir_ayah, $pendidikan_ayah, $pekerjaan_ayah, $penghasilan_ayah, $nik_ayah, $nama_ibu, $tahun_lahir_ibu, $pendidikan_ibu, $pekerjaan_ibu, $penghasilan_ibu, $nik_ibu, $nama_wali, $tahun_lahir_wali, $pendidikan_wali, $pekerjaan_wali, $penghasilan_wali, $nik_wali, $jurusan, $rombel, $sekolah_asal, $anak_ke_berapa, $no_kk, $berat_badan, $tinggi_badan, $jml_saudara_kandung, $lintang, $bujur, $jarak_rumah_ke_sekolah_km";

    if ($is_update) {
        $set_clause = "id_tahun_ajaran = $id_tahun_ajaran, nama = $nama, nipd = $nipd, jk = $jk, nisn = $nisn, tempat_lahir = $tempat_lahir, tanggal_lahir = $tanggal_lahir, nik = $nik, agama = $agama, alamat = $alamat, rt_rw = $rt_rw, dusun = $dusun, kelurahan = $kelurahan, kecamatan = $kecamatan, kode_pos = $kode_pos, jenis_tinggal = $jenis_tinggal, transportasi = $transportasi, telp = $telp, hp = $hp, email = $email, nama_ayah = $nama_ayah, tahun_lahir_ayah = $tahun_lahir_ayah, pendidikan_ayah = $pendidikan_ayah, pekerjaan_ayah = $pekerjaan_ayah, penghasilan_ayah = $penghasilan_ayah, nik_ayah = $nik_ayah, nama_ibu = $nama_ibu, tahun_lahir_ibu = $tahun_lahir_ibu, pendidikan_ibu = $pendidikan_ibu, pekerjaan_ibu = $pekerjaan_ibu, penghasilan_ibu = $penghasilan_ibu, nik_ibu = $nik_ibu, nama_wali = $nama_wali, tahun_lahir_wali = $tahun_lahir_wali, pendidikan_wali = $pendidikan_wali, pekerjaan_wali = $pekerjaan_wali, penghasilan_wali = $penghasilan_wali, nik_wali = $nik_wali, jurusan = $jurusan, rombel = $rombel, sekolah_asal = $sekolah_asal, anak_ke_berapa = $anak_ke_berapa, no_kk = $no_kk, berat_badan = $berat_badan, tinggi_badan = $tinggi_badan, jml_saudara_kandung = $jml_saudara_kandung, lintang = $lintang, bujur = $bujur, jarak_rumah_ke_sekolah_km = $jarak_rumah_ke_sekolah_km $foto_field $password_field";

        $sql_aksi = "UPDATE dt_siswa SET $set_clause WHERE id_siswa = $id_siswa";
        $success_message = "Data siswa berhasil diupdate!";
        $error_message = "Gagal mengupdate data siswa.";
        $redirect_page = "../index.php?menu=siswa_aktif";
    } else {
        $kolom_insert = $kolom . ", foto, password";
        $nilai_insert = $nilai . ", '$foto', '$hashedPassword'";

        $sql_aksi = "INSERT INTO dt_siswa ($kolom_insert) VALUES ($nilai_insert)";
        $success_message = "Data siswa berhasil ditambahkan!";
        $error_message = "Gagal menambahkan data siswa.";
        $redirect_page = "../index.php?menu=siswa_aktif";
    }

    // 5. Eksekusi Query
    $result_aksi = mysqli_query($koneksi, $sql_aksi);

    if ($result_aksi) {
        echo "<script>alert('$success_message');window.location='$redirect_page';</script>";
    } else {
        echo "<script>alert('$error_message Error: " . mysqli_error($koneksi) . "');window.history.back();</script>";
    }

    exit;
} else {
    // Jika tidak ada data POST yang terdeteksi
    echo "<script>alert('Akses tidak sah! Pastikan tombol Simpan telah ditekan dari halaman Tambah atau Edit.');window.location='../index.php?menu=siswa_aktif';</script>";
    exit;
}
