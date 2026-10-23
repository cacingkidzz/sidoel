<?php
/**
 * Inisialisasi Portal Guru/Wali Kelas.
 * Satu session (SIDOELPORTAL) dipakai bersama oleh portal/, guru/, dan walikelas/.
 * Login memakai akun dt_user dengan level_user = 'Guru' (relasi id_fk = id_guru).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('SIDOELPORTAL');
    session_start();
}

date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/../admin/koneksi.php';
require_once __DIR__ . '/../admin/helper_auth.php';

function portal_user(): array
{
    return !empty($_SESSION['portal']) && is_array($_SESSION['portal'])
        ? $_SESSION['portal']
        : [];
}

function portal_logged_in(): bool
{
    return !empty($_SESSION['portal']['id_user']);
}

function portal_flash_set(string $type, string $msg): void
{
    $_SESSION['portal_flash'] = ['type' => $type, 'msg' => $msg];
}

function portal_flash_get(): ?array
{
    if (!empty($_SESSION['portal_flash'])) {
        $f = $_SESSION['portal_flash'];
        unset($_SESSION['portal_flash']);
        return $f;
    }
    return null;
}

function portal_esc($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function portal_sync_aliases(): void
{
    if (!empty($_SESSION['portal'])) {
        $_SESSION['guru'] = $_SESSION['portal'];
        $_SESSION['walikelas'] = $_SESSION['portal'];
    }
}

function portal_set_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['portal'] = $user;
    portal_sync_aliases();
}

function portal_logout(): void
{
    unset($_SESSION['portal'], $_SESSION['guru'], $_SESSION['walikelas']);
    unset($_SESSION['portal_flash'], $_SESSION['guru_flash'], $_SESSION['wk_flash']);
    session_destroy();
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
}

function portal_profil($koneksi): array
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

function portal_logo_url(array $profil): string
{
    return !empty($profil['logo'])
        ? '../admin/assets/img/logo/' . rawurlencode($profil['logo'])
        : '../admin/assets/img/AdminLTELogo.png';
}

function portal_ta_aktif($koneksi): array
{
    $q = $koneksi->query("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar
                          ORDER BY (status = 'aktif') DESC, tahun_ajaran DESC LIMIT 1");
    if ($q && $q->num_rows) {
        $r = $q->fetch_assoc();
        return ['id' => (int)$r['id_tahun_ajaran'], 'nama' => $r['tahun_ajaran']];
    }
    return ['id' => 0, 'nama' => '-'];
}

function portal_kelas_label(array $k): string
{
    $label = trim(($k['tingkat'] ?? '') . ' ' . ($k['nama_kelas'] ?? ''));
    if (!empty($k['nama_jurusan'])) $label .= ' (' . $k['nama_jurusan'] . ')';
    return $label !== '' ? $label : ('Kelas #' . (int)($k['id_kelas'] ?? 0));
}

function portal_kelas_diajar($koneksi, int $id_guru, int $id_ta): array
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

function portal_wali_kelas($koneksi, int $id_guru, int $id_ta): array
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

function portal_roles($koneksi, ?array $user = null): array
{
    static $cache = [];
    $user = $user ?? portal_user();
    $id_guru = (int)($user['id_guru'] ?? 0);
    $ta = portal_ta_aktif($koneksi);
    $key = $id_guru . '|' . $ta['id'];
    if (isset($cache[$key])) return $cache[$key];

    $kelas = $id_guru > 0 ? portal_kelas_diajar($koneksi, $id_guru, $ta['id']) : [];
    $wali  = $id_guru > 0 ? portal_wali_kelas($koneksi, $id_guru, $ta['id']) : [];
    return $cache[$key] = [
        'guru' => $id_guru > 0,
        'kelas_diajar' => $kelas,
        'wali' => !empty($wali),
        'wali_kelas' => $wali,
        'id_guru' => $id_guru,
        'ta' => $ta,
    ];
}

function portal_has_role($koneksi, string $role): bool
{
    $roles = portal_roles($koneksi);
    return !empty($roles[$role]);
}

function portal_menu_items(array $roles): array
{
    $jumlah_kelas = count($roles['kelas_diajar'] ?? []);
    $wali_labels = array_map('portal_kelas_label', $roles['wali_kelas'] ?? []);
    return [
        [
            'key' => 'guru',
            'title' => 'Portal Guru',
            'icon' => 'bi-easel2-fill',
            'url' => '../guru/index.php',
            'active' => !empty($roles['guru']),
            'desc' => 'Absensi siswa, jadwal mengajar, dan rekap kehadiran guru.',
            'meta' => $jumlah_kelas > 0 ? ($jumlah_kelas . ' kelas diajar') : 'Belum ada kelas di TA ini',
            'reason' => 'Akun belum terhubung ke data guru.',
            'theme' => '#6d28d9',
            'soft' => '#f5f3ff',
        ],
        [
            'key' => 'wali',
            'title' => 'Portal Wali Kelas',
            'icon' => 'bi-people-fill',
            'url' => '../walikelas/index.php',
            'active' => !empty($roles['wali']),
            'desc' => 'Monitoring absensi, jurnal, dan kelengkapan data kelas wali.',
            'meta' => !empty($wali_labels) ? implode(', ', $wali_labels) : 'Belum ditugaskan sebagai wali kelas',
            'reason' => 'Anda belum ditugaskan sebagai wali kelas pada tahun ajaran aktif.',
            'theme' => '#0f766e',
            'soft' => '#ecfdf5',
        ],
    ];
}

function portal_attempt_login($koneksi, string $identifier, string $password): array
{
    $identifier = trim($identifier);
    if ($identifier === '' || $password === '') {
        return ['ok' => false, 'error' => 'Username/NIP/NUPTK dan password wajib diisi.'];
    }

    $stmt = $koneksi->prepare("SELECT u.id_user, u.username, u.password, u.level_user, u.status_aktif, u.id_fk,
                                      g.nama_guru, g.nip, g.nuptk
                               FROM dt_user u
                               LEFT JOIN dt_guru g ON g.id_guru = u.id_fk
                               WHERE u.level_user <> 'Admin'
                                 AND u.id_fk > 0
                                 AND (u.username = ? OR g.nip = ? OR g.nuptk = ?)
                               ORDER BY (u.username = ?) DESC, (u.status_aktif = 'Y') DESC, u.id_user DESC
                               LIMIT 1");
    if (!$stmt) return ['ok' => false, 'error' => 'Login sedang tidak tersedia. Coba lagi.'];
    $stmt->bind_param('ssss', $identifier, $identifier, $identifier, $identifier);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$u) return ['ok' => false, 'error' => 'Akun tidak ditemukan atau belum terhubung ke data guru. Hubungi admin.'];
    if ($u['status_aktif'] !== 'Y') return ['ok' => false, 'error' => 'Akun belum diaktifkan / sudah dinonaktifkan. Hubungi admin.'];
    if ((int)($u['id_fk'] ?? 0) <= 0) return ['ok' => false, 'error' => 'Akun belum terhubung ke data guru. Hubungi admin.'];

    $stored = (string)$u['password'];
    if (!verifikasi_password($password, $stored)) return ['ok' => false, 'error' => 'Password salah.'];

    upgrade_password_jika_perlu($koneksi, (int)$u['id_user'], $password, $stored);
    return ['ok' => true, 'user' => [
        'id_user' => (int)$u['id_user'],
        'username' => $u['username'],
        'level_user' => $u['level_user'] ?? 'Guru',
        'id_guru' => (int)$u['id_fk'],
        'nama_guru' => $u['nama_guru'] ?? '',
        'nip' => $u['nip'] ?? '',
        'nuptk' => $u['nuptk'] ?? '',
    ]];
}

function portal_require_role($koneksi, string $role, string $message = ''): void
{
    if (!portal_logged_in()) {
        header('Location: ../portal/login.php');
        exit;
    }
    if (!portal_has_role($koneksi, $role)) {
        portal_flash_set('warning', $message !== '' ? $message : 'Akun Anda tidak memiliki akses ke portal tersebut.');
        header('Location: ../portal/index.php');
        exit;
    }
}

portal_sync_aliases();
