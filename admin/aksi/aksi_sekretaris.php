<?php
/**
 * Handler akun Sekretaris Kelas (dt_sekretaris) - KBM-3.
 * Aksi: tambah, edit, hapus.
 */
require_once(__DIR__ . '/akses_guard.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("../koneksi.php");

function setAlert($type, $msg)
{
    $_SESSION['alert'] = ['type' => $type, 'msg' => $msg];
}

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';
$back = "../index.php?menu=sekretaris_kelas";

// Resolusi id tahun ajaran aktif (dari session, fallback status aktif)
function ta_aktif_id($koneksi)
{
    $nama = $_SESSION['tahun_ajaran_aktif'] ?? '';
    $stmt = $koneksi->prepare("SELECT id_tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    $stmt->bind_param("s", $nama);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($r) return (int)$r['id_tahun_ajaran'];

    $q = $koneksi->query("SELECT id_tahun_ajaran FROM dt_tahun_ajar WHERE status = 'aktif' LIMIT 1");
    $r = $q ? $q->fetch_assoc() : null;
    return $r ? (int)$r['id_tahun_ajaran'] : 0;
}

// Ambil kelas siswa pada TA tertentu
function kelas_siswa($koneksi, $id_siswa, $id_ta)
{
    $stmt = $koneksi->prepare("SELECT id_kelas FROM dt_kelas_siswa WHERE id_siswa = ? AND id_tahun_ajaran = ? LIMIT 1");
    $stmt->bind_param("ii", $id_siswa, $id_ta);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r ? (int)$r['id_kelas'] : 0;
}

// Ambil NIS siswa (nipd, fallback nisn) untuk dipakai sebagai username
function nis_siswa($koneksi, $id_siswa)
{
    $stmt = $koneksi->prepare("SELECT nipd, nisn FROM dt_siswa WHERE id_siswa = ? LIMIT 1");
    $stmt->bind_param("i", $id_siswa);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) return '';
    $nis = trim((string)($r['nipd'] ?? ''));
    if ($nis === '') $nis = trim((string)($r['nisn'] ?? ''));
    return $nis;
}

// Mengecek username sudah dipakai (opsional kecuali id tertentu)
function username_dipakai($koneksi, $username, $kecuali = 0)
{
    $stmt = $koneksi->prepare("SELECT id_sekretaris FROM dt_sekretaris WHERE username = ? AND id_sekretaris <> ? LIMIT 1");
    $stmt->bind_param("si", $username, $kecuali);
    $stmt->execute();
    $ada = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ada;
}

// ============================================================
// HAPUS
// ============================================================
if ($aksi === 'hapus') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        setAlert('danger', 'Akun tidak valid.');
        header("Location: $back");
        exit;
    }
    $stmt = $koneksi->prepare("DELETE FROM dt_sekretaris WHERE id_sekretaris = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        setAlert('success', 'Akun sekretaris berhasil dihapus.');
    } else {
        setAlert('danger', 'Akun tidak ditemukan atau gagal dihapus.');
    }
    $stmt->close();
    header("Location: $back");
    exit;
}

// ============================================================
// TAMBAH
// ============================================================
if ($aksi === 'tambah') {
    $username_post = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $id_siswa = (int)($_POST['id_siswa'] ?? 0);
    $status   = ($_POST['status_aktif'] ?? 'Y') === 'N' ? 'N' : 'Y';

    if ($password === '' || $id_siswa <= 0) {
        setAlert('danger', 'Siswa dan password wajib diisi.');
        header("Location: $back");
        exit;
    }
    // Default username = NIS siswa (lebih mudah diingat/diketik).
    $nis = nis_siswa($koneksi, $id_siswa);
    $username = $nis !== '' ? $nis : $username_post;
    if ($username === '') {
        setAlert('danger', 'NIS siswa kosong. Isi username secara manual.');
        header("Location: $back");
        exit;
    }
    if (strlen($password) < 5) {
        setAlert('warning', 'Password minimal 5 karakter.');
        header("Location: $back");
        exit;
    }
    if (username_dipakai($koneksi, $username)) {
        setAlert('warning', 'Username <b>' . htmlspecialchars($username) . '</b> sudah dipakai.');
        header("Location: $back");
        exit;
    }

    $id_ta = ta_aktif_id($koneksi);
    if ($id_ta <= 0) {
        setAlert('danger', 'Tidak ada tahun ajaran aktif.');
        header("Location: $back");
        exit;
    }
    $id_kelas = kelas_siswa($koneksi, $id_siswa, $id_ta);
    if ($id_kelas <= 0) {
        setAlert('danger', 'Siswa tidak terdaftar di kelas pada TA aktif.');
        header("Location: $back");
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $koneksi->prepare("INSERT INTO dt_sekretaris (username, password, id_siswa, id_kelas, id_tahun_ajaran, status_aktif)
                               VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("ssiiis", $username, $hash, $id_siswa, $id_kelas, $id_ta, $status);
    if ($stmt->execute()) {
        setAlert('success', 'Akun sekretaris <b>' . htmlspecialchars($username) . '</b> berhasil dibuat.');
    } else {
        setAlert('danger', 'Gagal membuat akun. Error: ' . $koneksi->error);
    }
    $stmt->close();
    header("Location: $back");
    exit;
}

// ============================================================
// EDIT
// ============================================================
if ($aksi === 'edit') {
    $id_sek   = (int)($_POST['id_sekretaris'] ?? 0);
    $username_post = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $id_siswa = (int)($_POST['id_siswa'] ?? 0);
    $status   = ($_POST['status_aktif'] ?? 'Y') === 'N' ? 'N' : 'Y';

    if ($id_sek <= 0 || $id_siswa <= 0) {
        setAlert('danger', 'Data tidak lengkap.');
        header("Location: $back");
        exit;
    }
    // Default username = NIS siswa bila field dikosongkan.
    $nis = nis_siswa($koneksi, $id_siswa);
    $username = ($username_post === '' && $nis !== '') ? $nis : $username_post;
    if ($username === '') {
        setAlert('danger', 'NIS siswa kosong. Isi username secara manual.');
        header("Location: $back");
        exit;
    }

    $stmt = $koneksi->prepare("SELECT id_siswa, id_tahun_ajaran FROM dt_sekretaris WHERE id_sekretaris = ? LIMIT 1");
    $stmt->bind_param("i", $id_sek);
    $stmt->execute();
    $cek = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$cek) {
        setAlert('danger', 'Akun tidak ditemukan.');
        header("Location: $back");
        exit;
    }
    $id_ta = (int)$cek['id_tahun_ajaran'];

    if (username_dipakai($koneksi, $username, $id_sek)) {
        setAlert('warning', 'Username <b>' . htmlspecialchars($username) . '</b> sudah dipakai akun lain.');
        header("Location: $back");
        exit;
    }

    $id_kelas = kelas_siswa($koneksi, $id_siswa, $id_ta);
    if ($id_kelas <= 0) {
        setAlert('danger', 'Siswa tidak terdaftar di kelas pada tahun ajaran akun ini.');
        header("Location: $back");
        exit;
    }

    if ($password !== '') {
        if (strlen($password) < 5) {
            setAlert('warning', 'Password minimal 5 karakter.');
            header("Location: $back");
            exit;
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $koneksi->prepare("UPDATE dt_sekretaris SET username=?, password=?, id_siswa=?, id_kelas=?, status_aktif=? WHERE id_sekretaris=?");
        $stmt->bind_param("ssiisi", $username, $hash, $id_siswa, $id_kelas, $status, $id_sek);
    } else {
        $stmt = $koneksi->prepare("UPDATE dt_sekretaris SET username=?, id_siswa=?, id_kelas=?, status_aktif=? WHERE id_sekretaris=?");
        $stmt->bind_param("siisi", $username, $id_siswa, $id_kelas, $status, $id_sek);
    }
    if ($stmt->execute()) {
        setAlert('success', 'Akun <b>' . htmlspecialchars($username) . '</b> berhasil diperbarui.');
    } else {
        setAlert('danger', 'Gagal memperbarui akun. Error: ' . $koneksi->error);
    }
    $stmt->close();
    header("Location: $back");
    exit;
}

setAlert('danger', 'Aksi tidak valid.');
header("Location: $back");
exit;
