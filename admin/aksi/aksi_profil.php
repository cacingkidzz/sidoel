<?php
require_once(__DIR__ . '/akses_guard.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include(__DIR__ . '/../koneksi.php');

$id_user = (int) ($_SESSION['id_user'] ?? 0);
$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';

// Hanya menerima aksi ganti_password via POST.
if ($aksi !== 'ganti_password' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Aksi tidak valid.'];
    header('Location: ../index.php?menu=profil');
    exit;
}

$lama = (string) ($_POST['password_lama'] ?? '');
$baru = (string) ($_POST['password_baru'] ?? '');
$konf = (string) ($_POST['password_konfirm'] ?? '');

// Ambil hash password saat ini.
$stmt = $koneksi->prepare("SELECT username, password FROM dt_user WHERE id_user = ? LIMIT 1");
$stmt->bind_param('i', $id_user);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Pengguna tidak ditemukan. Silakan login kembali.'];
    header('Location: ../index.php?menu=profil');
    exit;
}

if ($lama === '' || !verifikasi_password($lama, $row['password'])) {
    $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Password lama tidak sesuai.'];
    header('Location: ../index.php?menu=profil');
    exit;
}

if (strlen($baru) < 6) {
    $_SESSION['alert'] = ['type' => 'warning', 'msg' => 'Password baru minimal 6 karakter.'];
    header('Location: ../index.php?menu=profil');
    exit;
}

if ($baru !== $konf) {
    $_SESSION['alert'] = ['type' => 'warning', 'msg' => 'Konfirmasi password baru tidak sama.'];
    header('Location: ../index.php?menu=profil');
    exit;
}

if ($baru === $lama) {
    $_SESSION['alert'] = ['type' => 'warning', 'msg' => 'Password baru harus berbeda dari password lama.'];
    header('Location: ../index.php?menu=profil');
    exit;
}

$hash = hash_password($baru);
$stmt = $koneksi->prepare("UPDATE dt_user SET password = ? WHERE id_user = ?");
$stmt->bind_param('si', $hash, $id_user);

if ($stmt->execute()) {
    $_SESSION['alert'] = ['type' => 'success', 'msg' => 'Password berhasil diubah. Gunakan password baru saat login berikutnya.'];
} else {
    $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Gagal mengubah password. Coba lagi.'];
}
$stmt->close();

header('Location: ../index.php?menu=profil');
exit;
