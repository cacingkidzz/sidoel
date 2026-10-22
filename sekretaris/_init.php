<?php
// Inisialisasi mini-app Sekretaris Kelas (session terpisah dari admin)
if (session_status() === PHP_SESSION_NONE) {
    session_name('SIDOELSEK');
    session_start();
}

date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/../admin/koneksi.php';

function sek_logged_in(): bool
{
    return !empty($_SESSION['sekretaris']['id_sekretaris']);
}

function sek_wajib_login(): void
{
    if (!sek_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function sek_esc($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function sek_flash_set(string $type, string $msg): void
{
    $_SESSION['sek_flash'] = ['type' => $type, 'msg' => $msg];
}

function sek_flash_get(): ?array
{
    if (!empty($_SESSION['sek_flash'])) {
        $f = $_SESSION['sek_flash'];
        unset($_SESSION['sek_flash']);
        return $f;
    }
    return null;
}

function sek_profil($koneksi): array
{
    $p = ['nama_sekolah' => 'Sekolah', 'logo' => ''];
    $q = $koneksi->query("SELECT nama_sekolah, logo FROM dt_data_sekolah LIMIT 1");
    if ($q && $q->num_rows) {
        $d = $q->fetch_assoc();
        if (!empty($d['nama_sekolah'])) $p['nama_sekolah'] = $d['nama_sekolah'];
        if (!empty($d['logo']))          $p['logo'] = $d['logo'];
    }
    return $p;
}

function sek_logo_url(array $profil): string
{
    return !empty($profil['logo'])
        ? '../admin/assets/img/logo/' . rawurlencode($profil['logo'])
        : '../admin/assets/img/AdminLTELogo.png';
}

function sek_hari_id(string $tanggal): string
{
    $map = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
    ];
    $d = date('l', strtotime($tanggal));
    return $map[$d] ?? $d;
}

function sek_valid_tanggal(?string $t): string
{
    if ($t && preg_match('/^\d{4}-\d{2}-\d{2}$/', $t)) {
        [$y, $m, $d] = explode('-', $t);
        if (checkdate((int)$m, (int)$d, (int)$y)) {
            $t = sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
            // Tidak boleh melewati hari ini (batas input ke belakang/berjalan).
            return ($t > date('Y-m-d')) ? date('Y-m-d') : $t;
        }
    }
    return date('Y-m-d');
}

// Info kelas sekretaris (termasuk wali kelas & jumlah siswa)
function sek_kelas_info($koneksi, int $id_kelas, int $id_ta = 0): array
{
    $info = ['nama_kelas' => '-', 'tingkat' => '', 'jurusan' => '', 'wali' => '', 'jumlah_siswa' => 0];
    $stmt = $koneksi->prepare("SELECT k.nama_kelas, k.tingkat, j.nama_jurusan, g.nama_guru AS wali
                               FROM dt_kelas k
                               LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                               LEFT JOIN dt_wali_kelas wk ON wk.id_kelas = k.id_kelas
                               LEFT JOIN dt_guru g ON g.id_guru = wk.id_guru
                               WHERE k.id_kelas = ? LIMIT 1");
    $stmt->bind_param('i', $id_kelas);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($r) {
        $info['nama_kelas'] = $r['nama_kelas'];
        $info['tingkat'] = $r['tingkat'];
        $info['jurusan'] = $r['nama_jurusan'] ?? '';
        $info['wali'] = $r['wali'] ?? '';
    }
    if ($id_ta > 0) {
        $stmt = $koneksi->prepare("SELECT COUNT(DISTINCT ks.id_siswa) AS n
                                   FROM dt_kelas_siswa ks
                                   INNER JOIN dt_siswa s ON s.id_siswa = ks.id_siswa
                                   WHERE ks.id_kelas = ? AND ks.id_tahun_ajaran = ?");
        $stmt->bind_param('ii', $id_kelas, $id_ta);
        $stmt->execute();
        $c = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $info['jumlah_siswa'] = (int)($c['n'] ?? 0);
    }
    return $info;
}

// Label tahun ajaran (mis. "2027/2028")
function sek_ta_label($koneksi, int $id_ta): string
{
    if ($id_ta <= 0) return '-';
    $stmt = $koneksi->prepare("SELECT tahun_ajaran FROM dt_tahun_ajar WHERE id_tahun_ajaran = ? LIMIT 1");
    $stmt->bind_param('i', $id_ta);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r['tahun_ajaran'] ?? '-';
}

// Daftar siswa di sebuah kelas pada TA tertentu
function sek_siswa_kelas($koneksi, int $id_kelas, int $id_ta): array
{
    $rows = [];
    $stmt = $koneksi->prepare("SELECT s.id_siswa, s.nama, s.jk, s.nisn, s.nipd
                               FROM dt_kelas_siswa ks
                               INNER JOIN dt_siswa s ON s.id_siswa = ks.id_siswa
                               WHERE ks.id_kelas = ? AND ks.id_tahun_ajaran = ?
                               ORDER BY s.nama ASC");
    $stmt->bind_param('ii', $id_kelas, $id_ta);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

// Absensi harian kelas: map id_siswa => [status, keterangan]
function sek_absensi_harian($koneksi, int $id_kelas, int $id_ta, string $tanggal): array
{
    $rows = [];
    $stmt = $koneksi->prepare("SELECT id_siswa, status, keterangan FROM dt_absensi
                               WHERE id_kelas = ? AND id_tahun_ajaran = ? AND tanggal = ?");
    $stmt->bind_param('iis', $id_kelas, $id_ta, $tanggal);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[(int)$r['id_siswa']] = $r;
    $stmt->close();
    return $rows;
}

// Cek apakah tanggal termasuk kegiatan berjenis 'Libur' pada TA (KBM-5).
// Mengembalikan baris kegiatan (nama_kegiatan, dst) atau null bila bukan libur.
function sek_libur_info($koneksi, int $id_ta, string $tanggal): ?array
{
    $stmt = $koneksi->prepare("SELECT nama_kegiatan, tanggal_mulai, tanggal_selesai
                               FROM dt_kalender_akademik
                               WHERE id_tahun_ajaran = ? AND jenis = 'Libur'
                                 AND tanggal_mulai <= ?
                                 AND COALESCE(tanggal_selesai, tanggal_mulai) >= ?
                               ORDER BY tanggal_mulai ASC LIMIT 1");
    $stmt->bind_param('iss', $id_ta, $tanggal, $tanggal);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r ?: null;
}
