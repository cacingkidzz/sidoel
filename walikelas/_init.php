<?php
/**
 * Inisialisasi mini-app Wali Kelas.
 * Memakai session bersama Portal (SIDOELPORTAL) dan akun dt_user level 'Guru'
 * (relasi id_fk = id_guru) yang login lewat portal/login.php.
 */
require_once __DIR__ . '/../portal/_init.php';
require_once __DIR__ . '/../admin/konten/helper_laporan.php';

function wk_logged_in(): bool
{
    return portal_logged_in();
}

function wk_wajib_login(): void
{
    if (!wk_logged_in()) {
        header('Location: ../portal/login.php');
        exit;
    }
    $db = $GLOBALS['koneksi'] ?? null;
    if ($db && !portal_has_role($db, 'wali')) {
        portal_flash_set('warning', 'Anda belum ditugaskan sebagai wali kelas pada tahun ajaran aktif.');
        header('Location: ../portal/index.php');
        exit;
    }
}

function wk_esc($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function wk_flash_set(string $type, string $msg): void
{
    $_SESSION['wk_flash'] = ['type' => $type, 'msg' => $msg];
}

function wk_flash_get(): ?array
{
    if (!empty($_SESSION['wk_flash'])) {
        $f = $_SESSION['wk_flash'];
        unset($_SESSION['wk_flash']);
        return $f;
    }
    return null;
}

function wk_profil($koneksi): array
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

function wk_logo_url(array $profil): string
{
    return !empty($profil['logo'])
        ? '../admin/assets/img/logo/' . rawurlencode($profil['logo'])
        : '../admin/assets/img/AdminLTELogo.png';
}

function wk_ta_aktif($koneksi): array
{
    $q = $koneksi->query("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar
                          ORDER BY (status = 'aktif') DESC, tahun_ajaran DESC LIMIT 1");
    if ($q && $q->num_rows) {
        $r = $q->fetch_assoc();
        return ['id' => (int)$r['id_tahun_ajaran'], 'nama' => $r['tahun_ajaran']];
    }
    return ['id' => 0, 'nama' => '-'];
}

function wk_hari_id(string $tanggal): string
{
    $map = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
    ];
    $d = date('l', strtotime($tanggal));
    return $map[$d] ?? $d;
}

// Kelas yang diwalikan seorang guru pada TA tertentu.
function wk_kelas_wali($koneksi, int $id_guru, int $id_ta): array
{
    $rows = [];
    if ($id_guru <= 0 || $id_ta <= 0) return $rows;
    $stmt = $koneksi->prepare("SELECT k.id_kelas, k.nama_kelas, k.tingkat, j.nama_jurusan
                               FROM dt_wali_kelas wk
                               INNER JOIN dt_kelas k ON k.id_kelas = wk.id_kelas
                               LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                               WHERE wk.id_guru = ? AND wk.id_tahun_ajaran = ?
                               ORDER BY k.tingkat ASC, k.nama_kelas ASC");
    if (!$stmt) return $rows;
    $stmt->bind_param('ii', $id_guru, $id_ta);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

function wk_kelas_info($koneksi, int $id_kelas, int $id_ta): array
{
    $info = ['nama_kelas' => '-', 'tingkat' => '', 'jurusan' => '', 'jumlah_siswa' => 0, 'wali' => ''];
    $stmt = $koneksi->prepare("SELECT k.nama_kelas, k.tingkat, j.nama_jurusan
                               FROM dt_kelas k
                               LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                               WHERE k.id_kelas = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $id_kelas);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) {
            $info['nama_kelas'] = $r['nama_kelas'];
            $info['tingkat'] = $r['tingkat'];
            $info['jurusan'] = $r['nama_jurusan'] ?? '';
        }
    }
    if ($id_ta > 0) {
        $stmt = $koneksi->prepare("SELECT COUNT(DISTINCT ks.id_siswa) AS n
                                   FROM dt_kelas_siswa ks
                                   INNER JOIN dt_siswa s ON s.id_siswa = ks.id_siswa
                                   WHERE ks.id_kelas = ? AND ks.id_tahun_ajaran = ?");
        if ($stmt) {
            $stmt->bind_param('ii', $id_kelas, $id_ta);
            $stmt->execute();
            $c = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $info['jumlah_siswa'] = (int)($c['n'] ?? 0);
        }
    }
    return $info;
}

function wk_siswa_kelas($koneksi, int $id_kelas, int $id_ta): array
{
    $rows = [];
    $stmt = $koneksi->prepare("SELECT s.id_siswa, s.nama, s.jk, s.nisn, s.nipd
                               FROM dt_kelas_siswa ks
                               INNER JOIN dt_siswa s ON s.id_siswa = ks.id_siswa
                               WHERE ks.id_kelas = ? AND ks.id_tahun_ajaran = ?
                               ORDER BY s.nama ASC");
    if (!$stmt) return $rows;
    $stmt->bind_param('ii', $id_kelas, $id_ta);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

function wk_kelas_label(array $k): string
{
    $label = trim(($k['tingkat'] ?? '') . ' ' . ($k['nama_kelas'] ?? ''));
    if (!empty($k['nama_jurusan'])) $label .= ' (' . $k['nama_jurusan'] . ')';
    return $label !== '' ? $label : ('Kelas #' . (int)($k['id_kelas'] ?? 0));
}
