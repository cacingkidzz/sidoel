<?php
require_once __DIR__ . '/_init.php';
siswa_wajib_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: edit.php');
    exit;
}

$id_siswa = (int)$_SESSION['siswa']['id_siswa'];
$s = siswa_data($koneksi, $id_siswa);
if (!$s) {
    unset($_SESSION['siswa']);
    header('Location: login.php');
    exit;
}

function sp_str(string $key, int $max): ?string
{
    $v = trim((string)($_POST[$key] ?? ''));
    if ($v === '') return null;
    return function_exists('mb_substr') ? mb_substr($v, 0, $max, 'UTF-8') : substr($v, 0, $max);
}

function sp_float(string $key, array &$err, string $label): ?float
{
    $v = trim((string)($_POST[$key] ?? ''));
    if ($v === '') return null;
    if (!is_numeric($v) || (float)$v < 0) {
        $err[] = $label . ' harus berupa angka dan tidak negatif.';
        return null;
    }
    return round((float)$v, 2);
}

function sp_int(string $key, array &$err, string $label, int $min = 0, int $max = 9999): ?int
{
    $v = trim((string)($_POST[$key] ?? ''));
    if ($v === '') return null;
    if (!preg_match('/^\d+$/', $v) || (int)$v < $min || (int)$v > $max) {
        $err[] = $label . ' tidak valid.';
        return null;
    }
    return (int)$v;
}

function sp_tahun(string $key, array &$err, string $label): ?int
{
    $v = trim((string)($_POST[$key] ?? ''));
    if ($v === '' || $v === '0') return null;
    $thn = (int)date('Y');
    if (!preg_match('/^\d{4}$/', $v) || (int)$v < 1900 || (int)$v > $thn) {
        $err[] = $label . ' tidak valid.';
        return null;
    }
    return (int)$v;
}

function sp_koordinat(string $key, array &$err, string $label, float $min, float $max): ?string
{
    $v = str_replace(',', '.', trim((string)($_POST[$key] ?? '')));
    if ($v === '') return null;
    if (!is_numeric($v)) {
        $err[] = $label . ' tidak valid.';
        return null;
    }
    $f = (float)$v;
    if ($f < $min || $f > $max) {
        $err[] = $label . ' di luar rentang yang wajar.';
        return null;
    }
    return number_format($f, 7, '.', '');
}

function sp_resize_foto(string $path, string $mime): void
{
    if ($mime === 'image/gif') return;
    $info = @getimagesize($path);
    if (!$info) return;
    [$w, $h] = $info;
    $max = 800;
    if ($w <= $max && $h <= $max) return;

    $ratio = min($max / $w, $max / $h);
    $nw = (int)round($w * $ratio);
    $nh = (int)round($h * $ratio);

    if ($mime === 'image/png') {
        $src = @imagecreatefrompng($path);
        if (!$src) return;
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagepng($dst, $path);
        imagedestroy($dst);
        imagedestroy($src);
    } else {
        $src = @imagecreatefromjpeg($path);
        if (!$src) return;
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagejpeg($dst, $path, 85);
        imagedestroy($dst);
        imagedestroy($src);
    }
}

function sp_upload_foto(array $file, array &$err): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $err[] = 'Gagal mengunggah foto.';
        return null;
    }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        $err[] = 'Ukuran foto maksimal 2 MB.';
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    $mime = $info['mime'] ?? '';
    $ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
    if (!$info || !isset($ext_map[$mime])) {
        $err[] = 'Format foto harus JPG, PNG, atau GIF.';
        return null;
    }

    $dir = __DIR__ . '/../admin/assets/img/foto_siswa/';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        $err[] = 'Folder foto tidak dapat dibuat.';
        return null;
    }

    $nama = 'foto_' . uniqid() . time() . '.' . $ext_map[$mime];
    $dest = $dir . $nama;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $err[] = 'Gagal menyimpan foto. Silakan coba lagi.';
        return null;
    }
    sp_resize_foto($dest, $mime);
    return $nama;
}

function sp_hapus_foto_lama(?string $foto): void
{
    $foto = trim((string)$foto);
    if ($foto === '' || $foto === 'user.png') return;
    $path = __DIR__ . '/../admin/assets/img/foto_siswa/' . basename($foto);
    if (is_file($path)) @unlink($path);
}

$err = [];

$hp        = sp_str('hp', 20);
$telp      = sp_str('telp', 20);
$email     = sp_str('email', 255);
$alamat    = sp_str('alamat', 255);
$rt_rw     = sp_str('rt_rw', 50);
$dusun     = sp_str('dusun', 255);
$kelurahan = sp_str('kelurahan', 255);
$kecamatan = sp_str('kecamatan', 255);
$kode_pos  = sp_str('kode_pos', 10);
$tinggal   = sp_str('jenis_tinggal', 100);
$transport = sp_str('transportasi', 100);

foreach (['hp' => $hp, 'telp' => $telp] as $lbl => $val) {
    if ($val !== null && !preg_match('/^[0-9+\-\s().]{6,20}$/', $val)) {
        $err[] = ($lbl === 'hp' ? 'No. HP' : 'Telepon') . ' tidak valid.';
    }
}
if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err[] = 'Format email tidak valid.';
}
if ($kode_pos !== null && !preg_match('/^\d{1,10}$/', $kode_pos)) {
    $err[] = 'Kode pos harus berupa angka.';
}

$jarak  = sp_float('jarak_rumah_ke_sekolah_km', $err, 'Jarak ke sekolah');
$berat  = sp_float('berat_badan', $err, 'Berat badan');
$tinggi = sp_float('tinggi_badan', $err, 'Tinggi badan');
$saudara = sp_int('jml_saudara_kandung', $err, 'Jumlah saudara', 0, 100);

$lintang = sp_koordinat('latitude', $err, 'Latitude', -90, 90);
$bujur   = sp_koordinat('longitude', $err, 'Longitude', -180, 180);
$foto_baru = sp_upload_foto($_FILES['foto'] ?? [], $err);

$ortu = [
    'nama_ayah'        => sp_str('nama_ayah', 255),
    'tahun_lahir_ayah' => sp_tahun('tahun_lahir_ayah', $err, 'Tahun lahir ayah'),
    'pendidikan_ayah'  => sp_str('pendidikan_ayah', 100),
    'pekerjaan_ayah'   => sp_str('pekerjaan_ayah', 100),
    'penghasilan_ayah' => sp_str('penghasilan_ayah', 255),
    'nama_ibu'         => sp_str('nama_ibu', 255),
    'tahun_lahir_ibu'  => sp_tahun('tahun_lahir_ibu', $err, 'Tahun lahir ibu'),
    'pendidikan_ibu'   => sp_str('pendidikan_ibu', 100),
    'pekerjaan_ibu'    => sp_str('pekerjaan_ibu', 100),
    'penghasilan_ibu'  => sp_str('penghasilan_ibu', 255),
    'nama_wali'        => sp_str('nama_wali', 255),
    'tahun_lahir_wali' => sp_tahun('tahun_lahir_wali', $err, 'Tahun lahir wali'),
    'pendidikan_wali'  => sp_str('pendidikan_wali', 100),
    'pekerjaan_wali'   => sp_str('pekerjaan_wali', 100),
    'penghasilan_wali' => sp_str('penghasilan_wali', 255),
];

$pass_lama   = (string)($_POST['pass_lama'] ?? '');
$pass_baru   = (string)($_POST['pass_baru'] ?? '');
$pass_konfir = (string)($_POST['pass_konfirm'] ?? '');
$ganti_pass  = ($pass_lama !== '' || $pass_baru !== '' || $pass_konfir !== '');
$hash_baru   = null;

if ($ganti_pass) {
    if ($pass_lama === '') {
        $err[] = 'Password lama wajib diisi.';
    } elseif (!siswa_verifikasi_password($pass_lama, (string)($s['password'] ?? ''))) {
        $err[] = 'Password lama salah.';
    }
    if ($pass_baru === '') {
        $err[] = 'Password baru wajib diisi.';
    } elseif (strlen($pass_baru) < 6) {
        $err[] = 'Password baru minimal 6 karakter.';
    } elseif ($pass_baru !== $pass_konfir) {
        $err[] = 'Konfirmasi password baru tidak sama.';
    } elseif ($pass_baru === $pass_lama) {
        $err[] = 'Password baru harus berbeda dari password lama.';
    } else {
        $hash_baru = hash_password($pass_baru);
    }
}

if ($err) {
    if ($foto_baru !== null) sp_hapus_foto_lama($foto_baru);
    siswa_flash_set('danger', implode(' ', array_unique($err)));
    header('Location: edit.php');
    exit;
}

$data = [
    'hp' => $hp, 'telp' => $telp, 'email' => $email, 'alamat' => $alamat,
    'rt_rw' => $rt_rw, 'dusun' => $dusun, 'kelurahan' => $kelurahan, 'kecamatan' => $kecamatan,
    'kode_pos' => $kode_pos, 'jenis_tinggal' => $tinggal, 'transportasi' => $transport,
    'jarak_rumah_ke_sekolah_km' => $jarak, 'berat_badan' => $berat, 'tinggi_badan' => $tinggi,
    'jml_saudara_kandung' => $saudara, 'lintang' => $lintang, 'bujur' => $bujur,
];
foreach ($ortu as $k => $v) $data[$k] = $v;
if ($foto_baru !== null) $data['foto'] = $foto_baru;

$cols = array_keys($data);
$sets = [];
foreach ($cols as $c) $sets[] = "`$c` = ?";
$types = str_repeat('s', count($cols));
$params = array_values($data);

if ($hash_baru !== null) {
    $sets[] = "`password` = ?";
    $types .= 's';
    $params[] = $hash_baru;
}

$types .= 'i';
$params[] = $id_siswa;

$sql = "UPDATE dt_siswa SET " . implode(', ', $sets) . " WHERE id_siswa = ?";
$stmt = $koneksi->prepare($sql);
if (!$stmt) {
    if ($foto_baru !== null) sp_hapus_foto_lama($foto_baru);
    siswa_flash_set('danger', 'Gagal menyimpan data. Silakan coba lagi.');
    header('Location: edit.php');
    exit;
}

$params_ref = [];
foreach ($params as $i => $v) $params_ref[$i] = $v;
$bind = [$types];
foreach ($params_ref as $i => $v) $bind[] = &$params_ref[$i];
call_user_func_array([$stmt, 'bind_param'], $bind);

if ($stmt->execute()) {
    if ($foto_baru !== null) sp_hapus_foto_lama($s['foto'] ?? null);
    siswa_flash_set('success', 'Data berhasil diperbarui.');
    $stmt->close();
    header('Location: index.php');
    exit;
}

$stmt->close();
if ($foto_baru !== null) sp_hapus_foto_lama($foto_baru);
siswa_flash_set('danger', 'Gagal menyimpan data. Silakan coba lagi.');
header('Location: edit.php');
exit;
