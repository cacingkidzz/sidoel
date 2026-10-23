<?php
/**
 * Inisialisasi mini-app Siswa (session terpisah dari admin, sekretaris, wali kelas).
 * Login memakai NIS/NISN + password pada tabel dt_siswa.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_name('SIDOELSISWA');
    session_start();
}

date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/../admin/koneksi.php';
require_once __DIR__ . '/../admin/helper_auth.php';
require_once __DIR__ . '/../admin/helper_kelengkapan.php';

function siswa_logged_in(): bool
{
    return !empty($_SESSION['siswa']['id_siswa']);
}

function siswa_wajib_login(): void
{
    if (!siswa_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function siswa_esc($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function siswa_flash_set(string $type, string $msg): void
{
    $_SESSION['siswa_flash'] = ['type' => $type, 'msg' => $msg];
}

function siswa_flash_get(): ?array
{
    if (!empty($_SESSION['siswa_flash'])) {
        $f = $_SESSION['siswa_flash'];
        unset($_SESSION['siswa_flash']);
        return $f;
    }
    return null;
}

function siswa_profil_sekolah($koneksi): array
{
    $p = ['nama_sekolah' => 'Sekolah', 'alamat_sekolah' => '', 'logo' => '', 'telp_sekolah' => '', 'email' => '', 'web' => ''];
    $q = $koneksi->query("SELECT nama_sekolah, alamat_sekolah, logo, telp_sekolah, email, web FROM dt_data_sekolah LIMIT 1");
    if ($q && $q->num_rows) {
        $d = $q->fetch_assoc();
        foreach ($p as $k => $v) {
            if (!empty($d[$k])) $p[$k] = $d[$k];
        }
    }
    return $p;
}

function siswa_logo_url(array $profil): string
{
    return !empty($profil['logo'])
        ? '../admin/assets/img/logo/' . rawurlencode($profil['logo'])
        : '../admin/assets/img/AdminLTELogo.png';
}

function siswa_data($koneksi, int $id_siswa): ?array
{
    $stmt = $koneksi->prepare("SELECT * FROM dt_siswa WHERE id_siswa = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $id_siswa);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r ?: null;
}

function siswa_kelas($koneksi, int $id_siswa, int $id_ta): ?array
{
    if ($id_siswa <= 0 || $id_ta <= 0) return null;
    $stmt = $koneksi->prepare("SELECT k.id_kelas, k.nama_kelas, k.tingkat, j.nama_jurusan, g.nama_guru AS wali
                               FROM dt_kelas_siswa ks
                               INNER JOIN dt_kelas k ON k.id_kelas = ks.id_kelas
                               LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                               LEFT JOIN dt_wali_kelas w ON w.id_kelas = k.id_kelas AND w.id_tahun_ajaran = ks.id_tahun_ajaran
                               LEFT JOIN dt_guru g ON g.id_guru = w.id_guru
                               WHERE ks.id_siswa = ? AND ks.id_tahun_ajaran = ?
                               ORDER BY k.tingkat ASC, k.nama_kelas ASC LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('ii', $id_siswa, $id_ta);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r ?: null;
}

function siswa_ta($koneksi, int $id_ta): string
{
    if ($id_ta <= 0) return '-';
    $stmt = $koneksi->prepare("SELECT tahun_ajaran FROM dt_tahun_ajar WHERE id_tahun_ajaran = ? LIMIT 1");
    if (!$stmt) return '-';
    $stmt->bind_param('i', $id_ta);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r['tahun_ajaran'] ?? '-';
}

function siswa_kelas_label(?array $k): string
{
    if (!$k) return '-';
    $label = trim(($k['tingkat'] ?? '') . ' ' . ($k['nama_kelas'] ?? ''));
    if (!empty($k['nama_jurusan'])) $label .= ' (' . $k['nama_jurusan'] . ')';
    return $label !== '' ? $label : '-';
}

function siswa_foto_url(array $s): string
{
    $f = trim((string)($s['foto'] ?? ''));
    if ($f !== '' && $f !== 'user.png' && is_file(__DIR__ . '/../admin/assets/img/foto_siswa/' . $f)) {
        return '../admin/assets/img/foto_siswa/' . rawurlencode($f);
    }
    return ($s['jk'] ?? '') === 'P'
        ? '../admin/assets/img/wanita.png'
        : '../admin/assets/img/pria.png';
}

function siswa_koordinat(?string $lat, ?string $lng): ?array
{
    $la = str_replace(',', '.', trim((string)$lat));
    $ln = str_replace(',', '.', trim((string)$lng));
    if ($la === '' || $ln === '' || !is_numeric($la) || !is_numeric($ln)) return null;
    $la = (float)$la;
    $ln = (float)$ln;
    if ($la < -90 || $la > 90 || $ln < -180 || $ln > 180) return null;
    if ($la == 0.0 && $ln == 0.0) return null;
    return ['lat' => $la, 'lng' => $ln];
}

function siswa_tanggal_id(?string $tanggal): string
{
    if (empty($tanggal) || $tanggal === '0000-00-00') return '-';
    $ts = strtotime($tanggal);
    if ($ts === false) return '-';
    $bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
              '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
    return date('d', $ts) . ' ' . ($bulan[date('m', $ts)] ?? '') . ' ' . date('Y', $ts);
}

// Password siswa lama bisa berupa plaintext (hasil import lama) atau MD5, selain bcrypt.
function siswa_password_lama(string $stored): bool
{
    if ($stored === '') return false;
    return $stored[0] !== '$';
}

function siswa_verifikasi_password(string $plain, string $stored): bool
{
    if ($stored === '') return false;
    if (verifikasi_password($plain, $stored)) return true;
    if (siswa_password_lama($stored) && !password_md5_lama($stored)) {
        return hash_equals($stored, $plain);
    }
    return false;
}
