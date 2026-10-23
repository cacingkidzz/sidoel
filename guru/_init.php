<?php
/**
 * Inisialisasi mini-app Guru.
 * Memakai session bersama Portal (SIDOELPORTAL) dan akun dt_user level 'Guru'
 * (relasi id_fk = id_guru) yang login lewat portal/login.php.
 */
require_once __DIR__ . '/../portal/_init.php';
require_once __DIR__ . '/../admin/konten/helper_laporan.php';

function guru_logged_in(): bool
{
    return portal_logged_in();
}

function guru_wajib_login(): void
{
    if (!guru_logged_in()) {
        header('Location: ../portal/login.php');
        exit;
    }
    $db = $GLOBALS['koneksi'] ?? null;
    if ($db && !portal_has_role($db, 'guru')) {
        portal_flash_set('warning', 'Akun Anda tidak terhubung ke data guru.');
        header('Location: ../portal/index.php');
        exit;
    }
}

function guru_esc($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function guru_flash_set(string $type, string $msg): void
{
    $_SESSION['guru_flash'] = ['type' => $type, 'msg' => $msg];
}

function guru_flash_get(): ?array
{
    if (!empty($_SESSION['guru_flash'])) {
        $f = $_SESSION['guru_flash'];
        unset($_SESSION['guru_flash']);
        return $f;
    }
    return null;
}

function guru_profil($koneksi): array
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

function guru_logo_url(array $profil): string
{
    return !empty($profil['logo'])
        ? '../admin/assets/img/logo/' . rawurlencode($profil['logo'])
        : '../admin/assets/img/AdminLTELogo.png';
}

function guru_ta_aktif($koneksi): array
{
    $q = $koneksi->query("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar
                          ORDER BY (status = 'aktif') DESC, tahun_ajaran DESC LIMIT 1");
    if ($q && $q->num_rows) {
        $r = $q->fetch_assoc();
        return ['id' => (int)$r['id_tahun_ajaran'], 'nama' => $r['tahun_ajaran']];
    }
    return ['id' => 0, 'nama' => '-'];
}

function guru_hari_id(string $tanggal): string
{
    $map = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
    ];
    $d = date('l', strtotime($tanggal));
    return $map[$d] ?? $d;
}

function guru_valid_tanggal(?string $tgl): string
{
    $tgl = (string)$tgl;
    $hari_ini = date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) return $hari_ini;
    $d = DateTime::createFromFormat('Y-m-d', $tgl);
    if (!$d || $d->format('Y-m-d') !== $tgl) return $hari_ini;
    return ($tgl > $hari_ini) ? $hari_ini : $tgl;
}

function guru_libur_info($koneksi, int $id_ta, string $tanggal): ?array
{
    if ($id_ta <= 0) return null;
    $stmt = $koneksi->prepare("SELECT nama_kegiatan, jenis
                               FROM dt_kalender_akademik
                               WHERE id_tahun_ajaran = ? AND jenis = 'Libur'
                                 AND ? BETWEEN tanggal_mulai AND COALESCE(tanggal_selesai, tanggal_mulai)
                               ORDER BY tanggal_mulai ASC LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('is', $id_ta, $tanggal);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r ?: null;
}

// Kelas yang diajar guru pada TA tertentu (dari pengampu mapel & jadwal).
function guru_kelas_diajar($koneksi, int $id_guru, int $id_ta): array
{
    $rows = [];
    if ($id_guru <= 0 || $id_ta <= 0) return $rows;
    $stmt = $koneksi->prepare("SELECT k.id_kelas, k.nama_kelas, k.tingkat, j.nama_jurusan
                               FROM dt_kelas k
                               LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                               WHERE k.id_tahun_ajaran = ?
                                 AND (EXISTS (SELECT 1 FROM dt_pengampu_mapel pm
                                              WHERE pm.id_kelas = k.id_kelas AND pm.id_guru = ? AND pm.id_tahun_ajaran = k.id_tahun_ajaran)
                                      OR EXISTS (SELECT 1 FROM dt_jadwal jd
                                                 WHERE jd.id_kelas = k.id_kelas AND jd.id_guru = ? AND jd.id_tahun_ajaran = k.id_tahun_ajaran))
                               ORDER BY k.tingkat ASC, k.nama_kelas ASC");
    if (!$stmt) return $rows;
    $stmt->bind_param('iii', $id_ta, $id_guru, $id_guru);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

function guru_kelas_label(array $k): string
{
    $label = trim(($k['tingkat'] ?? '') . ' ' . ($k['nama_kelas'] ?? ''));
    if (!empty($k['nama_jurusan'])) $label .= ' (' . $k['nama_jurusan'] . ')';
    return $label !== '' ? $label : ('Kelas #' . (int)($k['id_kelas'] ?? 0));
}

function guru_jumlah_siswa($koneksi, int $id_kelas, int $id_ta): int
{
    if ($id_kelas <= 0 || $id_ta <= 0) return 0;
    $stmt = $koneksi->prepare("SELECT COUNT(DISTINCT ks.id_siswa) AS n
                               FROM dt_kelas_siswa ks
                               INNER JOIN dt_siswa s ON s.id_siswa = ks.id_siswa
                               WHERE ks.id_kelas = ? AND ks.id_tahun_ajaran = ?");
    if (!$stmt) return 0;
    $stmt->bind_param('ii', $id_kelas, $id_ta);
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($c['n'] ?? 0);
}

function guru_siswa_kelas($koneksi, int $id_kelas, int $id_ta): array
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

// Mapel yang diampu guru per kelas: [id_kelas => ['KODE - Nama', ...]]
function guru_mapel_per_kelas($koneksi, int $id_guru, int $id_ta): array
{
    $out = [];
    $stmt = $koneksi->prepare("SELECT pm.id_kelas, m.kode_mapel, m.nama_mapel
                               FROM dt_pengampu_mapel pm
                               INNER JOIN dt_mapel m ON m.id_mapel = pm.id_mapel
                               WHERE pm.id_guru = ? AND pm.id_tahun_ajaran = ?
                               ORDER BY m.kode_mapel ASC");
    if (!$stmt) return $out;
    $stmt->bind_param('ii', $id_guru, $id_ta);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $label = trim((string)$r['kode_mapel']);
        if ($label === '') $label = (string)$r['nama_mapel'];
        $out[(int)$r['id_kelas']][] = $label;
    }
    $stmt->close();
    return $out;
}

// Jadwal guru pada satu hari: per kelas.
function guru_jadwal_hari($koneksi, int $id_guru, int $id_ta, string $hari): array
{
    $rows = [];
    $stmt = $koneksi->prepare("SELECT jd.id_jadwal, jd.id_kelas, jd.jam_ke, m.kode_mapel, m.nama_mapel,
                                      k.nama_kelas, k.tingkat, j.nama_jurusan,
                                      jp.waktu_mulai, jp.waktu_selesai
                               FROM dt_jadwal jd
                               INNER JOIN dt_kelas k ON k.id_kelas = jd.id_kelas
                               LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                               INNER JOIN dt_mapel m ON m.id_mapel = jd.id_mapel
                               LEFT JOIN dt_jam_pelajaran jp ON jp.jam_ke = jd.jam_ke
                               WHERE jd.id_guru = ? AND jd.id_tahun_ajaran = ? AND jd.hari = ?
                               ORDER BY jd.jam_ke ASC, k.nama_kelas ASC");
    if (!$stmt) return $rows;
    $stmt->bind_param('iis', $id_guru, $id_ta, $hari);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

// Absensi harian satu kelas/tanggal (keyed id_siswa).
function guru_absensi_harian($koneksi, int $id_kelas, int $id_ta, string $tanggal): array
{
    $out = [];
    $stmt = $koneksi->prepare("SELECT a.id_siswa, a.status, a.keterangan, a.dicatat_oleh, p.nama AS pencatat
                               FROM dt_absensi a
                               LEFT JOIN dt_siswa p ON p.id_siswa = a.dicatat_oleh
                               WHERE a.id_kelas = ? AND a.id_tahun_ajaran = ? AND a.tanggal = ?");
    if (!$stmt) return $out;
    $stmt->bind_param('iis', $id_kelas, $id_ta, $tanggal);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $out[(int)$r['id_siswa']] = $r;
    $stmt->close();
    return $out;
}

/**
 * Rekap kehadiran guru + jurnal pada satu rentang tanggal.
 * hadir  = jurnal guru (Mengajar/Tugas) + sesi yang digantikan guru ini.
 * tidak  = jurnal guru berstatus Tidak Hadir.
 */
function guru_rekap_kehadiran($koneksi, int $id_guru, int $id_ta, string $awal, string $akhir, array $libur_map): array
{
    $r = [
        'hadir' => 0, 'tidak' => 0, 'tercatat' => 0, 'terjadwal' => 0,
        'mengajar' => 0, 'pengganti' => 0, 'pct' => 0.0, 'realisasi' => 0.0,
    ];
    if ($id_guru <= 0 || $id_ta <= 0) return $r;

    $stmt = $koneksi->prepare("SELECT tanggal, status, id_guru, id_guru_pengganti
                               FROM dt_jurnal
                               WHERE id_tahun_ajaran = ? AND tanggal BETWEEN ? AND ?
                                 AND (id_guru = ? OR id_guru_pengganti = ?)");
    if ($stmt) {
        $stmt->bind_param('issii', $id_ta, $awal, $akhir, $id_guru, $id_guru);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($j = $res->fetch_assoc()) {
            if (isset($libur_map[$j['tanggal']])) continue;
            $r['tercatat']++;
            $as_pengganti = ((int)($j['id_guru_pengganti'] ?? 0) === $id_guru);
            $as_guru = ((int)($j['id_guru'] ?? 0) === $id_guru);
            if ($as_pengganti) {
                $r['hadir']++;
                $r['pengganti']++;
            } elseif ($as_guru) {
                if (in_array($j['status'], ['Mengajar', 'Tugas/Kegiatan'], true)) {
                    $r['hadir']++;
                    $r['mengajar']++;
                } elseif ($j['status'] === 'Tidak Hadir') {
                    $r['tidak']++;
                }
            }
        }
        $stmt->close();
    }

    // Sesi terjadwal guru sebulan (hari jadwal × kemunculan hari, tanpa libur/akhir pekan).
    $per_hari = [];
    $stmt = $koneksi->prepare("SELECT hari, COUNT(*) AS n FROM dt_jadwal
                               WHERE id_guru = ? AND id_tahun_ajaran = ?
                               GROUP BY hari");
    if ($stmt) {
        $stmt->bind_param('ii', $id_guru, $id_ta);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($h = $res->fetch_assoc()) $per_hari[$h['hari']] = (int)$h['n'];
        $stmt->close();
    }
    $t = strtotime($awal);
    $end = strtotime($akhir);
    while ($t !== false && $t <= $end) {
        $w = (int)date('w', $t);
        $d = date('Y-m-d', $t);
        if ($w >= 1 && $w <= 5 && !isset($libur_map[$d])) {
            $r['terjadwal'] += $per_hari[guru_hari_id($d)] ?? 0;
        }
        $t = strtotime('+1 day', $t);
    }

    $denom = $r['hadir'] + $r['tidak'];
    $r['pct'] = $denom > 0 ? round($r['hadir'] * 100 / $denom, 1) : 0.0;
    $r['realisasi'] = $r['terjadwal'] > 0 ? round($r['tercatat'] * 100 / $r['terjadwal'], 1) : 0.0;
    return $r;
}
