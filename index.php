<?php
/**
 * ==========================================================
 * SiDoel — Halaman Depan (Pintu Masuk Aplikasi)
 * ----------------------------------------------------------
 * Halaman publik di root `sidoel/`:
 *   - informasi aplikasi & profil sekolah
 *   - statistik ringkas (siswa, guru, kelas, jurusan, mapel)
 *   - tombol menuju setiap aplikasi di dalam sistem
 *     (Admin Sekolah, Portal Guru & Wali Kelas, Portal Siswa,
 *      Sekretaris Kelas)
 * Tidak memerlukan login; autentikasi ditangani masing-masing app.
 * ==========================================================
 */

date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/admin/koneksi.php';

/** Escape HTML. */
function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* ==========================================================
 * PROFIL SEKOLAH
 * ========================================================== */
$sekolah = [
    'nama_sekolah' => 'SiDoel',
    'NPSN' => '',
    'alamat_sekolah' => '',
    'telp_sekolah' => '',
    'email' => '',
    'web' => '',
    'logo' => '',
];
$q = $koneksi->query("SELECT nama_sekolah, NPSN, alamat_sekolah, telp_sekolah, email, web, logo
                      FROM dt_data_sekolah LIMIT 1");
if ($q && $q->num_rows) {
    foreach ($q->fetch_assoc() as $k => $v) {
        if (trim((string)$v) !== '') $sekolah[$k] = (string)$v;
    }
}

/* Logo: file di admin/assets/img/logo/, fallback logo AdminLTE. */
$logo_url = 'admin/assets/img/AdminLTELogo.png';
if ($sekolah['logo'] !== '') {
    $logo_file = __DIR__ . '/admin/assets/img/logo/' . $sekolah['logo'];
    if (is_file($logo_file)) {
        $logo_url = 'admin/assets/img/logo/' . rawurlencode($sekolah['logo']);
    }
}

/* ==========================================================
 * TAHUN AJARAN AKTIF
 * ========================================================== */
$ta = ['id' => 0, 'nama' => '-'];
$q = $koneksi->query("SELECT id_tahun_ajaran, tahun_ajaran
                      FROM dt_tahun_ajar
                      ORDER BY (status = 'aktif') DESC, tahun_ajaran DESC
                      LIMIT 1");
if ($q && $q->num_rows) {
    $r = $q->fetch_assoc();
    $ta = ['id' => (int)$r['id_tahun_ajaran'], 'nama' => (string)$r['tahun_ajaran']];
}

/* ==========================================================
 * STATISTIK RINGKAS (history-aware untuk TA aktif)
 * ========================================================== */
$stat = ['siswa' => 0, 'l' => 0, 'p' => 0, 'guru' => 0, 'kelas' => 0, 'jurusan' => 0, 'mapel' => 0];

if ($ta['id'] > 0) {
    $stmt = $koneksi->prepare("SELECT s.jk, COUNT(DISTINCT s.id_siswa) AS c
                               FROM dt_siswa s
                               LEFT JOIN dt_kelas_siswa ks
                                      ON ks.id_siswa = s.id_siswa AND ks.id_tahun_ajaran = ?
                               WHERE s.id_tahun_ajaran = ? OR ks.id_kelas_siswa IS NOT NULL
                               GROUP BY s.jk");
    if ($stmt) {
        $id_ta = $ta['id'];
        $stmt->bind_param('ii', $id_ta, $id_ta);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $c = (int)$r['c'];
            $stat['siswa'] += $c;
            if ($r['jk'] === 'L') $stat['l'] += $c;
            if ($r['jk'] === 'P') $stat['p'] += $c;
        }
        $stmt->close();
    }

    $stmt = $koneksi->prepare("SELECT COUNT(*) AS c FROM dt_kelas WHERE id_tahun_ajaran = ?");
    if ($stmt) {
        $id_ta = $ta['id'];
        $stmt->bind_param('i', $id_ta);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($r = $res->fetch_assoc()) $stat['kelas'] = (int)$r['c'];
        $stmt->close();
    }
}

foreach (['guru' => 'dt_guru', 'jurusan' => 'dt_jurusan', 'mapel' => 'dt_mapel'] as $key => $tbl) {
    $q = $koneksi->query("SELECT COUNT(*) AS c FROM `$tbl`");
    if ($q && ($r = $q->fetch_assoc())) $stat[$key] = (int)$r['c'];
}

/* ==========================================================
 * DAFTAR APLIKASI
 * ========================================================== */
$apps = [
    [
        'title' => 'Admin Sekolah',
        'url'   => 'admin/',
        'icon'  => 'bi-shield-lock-fill',
        'theme' => '#0f172a',
        'soft'  => '#e2e8f0',
        'desc'  => 'Kelola data master, data siswa & PPDB, KBM (jadwal, absensi, jurnal), keuangan SPP, laporan, dan administrasi sistem.',
        'roles' => ['Admin', 'Kurikulum', 'Kesiswaan'],
        'login' => 'Email / username akun admin',
        'cta'   => 'Masuk Admin',
    ],
    [
        'title' => 'Portal Guru & Wali Kelas',
        'url'   => 'portal/',
        'icon'  => 'bi-person-workspace',
        'theme' => '#5b21b6',
        'soft'  => '#ede9fe',
        'desc'  => 'Jadwal mengajar, absensi harian, jurnal mengajar, serta monitoring kelas yang diwalikan (absensi, jurnal, tunggakan SPP).',
        'roles' => ['Guru', 'Wali Kelas'],
        'login' => 'Username, NIP, atau NUPTK',
        'cta'   => 'Masuk Portal',
    ],
    [
        'title' => 'Portal Siswa',
        'url'   => 'siswa/',
        'icon'  => 'bi-mortarboard-fill',
        'theme' => '#1e40af',
        'soft'  => '#dbeafe',
        'desc'  => 'Lihat profil, lengkapi data pribadi, kontak & alamat, data orang tua/wali, foto, dan titik lokasi rumah.',
        'roles' => ['Siswa'],
        'login' => 'NIS atau NISN',
        'cta'   => 'Masuk Portal',
    ],
    [
        'title' => 'Sekretaris Kelas',
        'url'   => 'sekretaris/',
        'icon'  => 'bi-journal-check',
        'theme' => '#b45309',
        'soft'  => '#fef3c7',
        'desc'  => 'Isi absensi harian kelas (H/S/I/A) dan jurnal mengajar per jam pelajaran, lalu pantau rekap bulanan.',
        'roles' => ['Sekretaris Kelas'],
        'login' => 'NIS atau username sekretaris',
        'cta'   => 'Masuk Aplikasi',
    ],
];

/* ==========================================================
 * MODUL APLIKASI
 * ========================================================== */
$modules = [
    ['bi-database-fill', 'Data Master', 'Sekolah, kop surat, tahun ajaran, jurusan, mata pelajaran, kelas, guru & staf, user pengguna.'],
    ['bi-people-fill', 'Data Siswa', 'Siswa aktif, penempatan & kenaikan kelas, kelengkapan data, sebaran lokasi, foto, alumni.'],
    ['bi-person-plus-fill', 'PPDB', 'Tarik calon siswa diterima menjadi siswa aktif pada tahun ajaran yang dipilih.'],
    ['bi-calendar3-week-fill', 'KBM', 'Guru mapel, pengampu, jadwal pelajaran, jam pelajaran, kalender akademik, absensi harian, jurnal mengajar.'],
    ['bi-cash-stack', 'Keuangan SPP', 'Tarif per tingkat/jurusan, tagihan 12 bulan, pembayaran & angsuran, tunggakan lintas tahun ajaran, kwitansi.'],
    ['bi-printer-fill', 'Laporan & Cetak', 'Kartu siswa, rekap absensi & jurnal, jadwal, kalender, rekap SPP — ber-kop sekolah, siap cetak PDF.'],
    ['bi-gear-fill', 'Administrasi Sistem', 'Profil & ganti password, log aktivitas (audit), backup & restore database, salin data antar tahun ajaran.'],
];

$tahun_ini = (int)date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SiDoel &mdash; Sistem Data Online Sekolah | <?= h($sekolah['nama_sekolah']); ?></title>
    <meta name="description" content="SiDoel — sistem informasi sekolah: data master, data siswa, PPDB, KBM, keuangan SPP, dan laporan.">
    <link rel="icon" href="<?= h($logo_url); ?>" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --brand-1: #0f172a; --brand-2: #1e3a8a; --brand-3: #0f766e; }
        body { background: #f4f6f9; color: #1f2937; -webkit-tap-highlight-color: transparent; }
        .wrap { max-width: 1080px; }

        .hero { position: relative; overflow: hidden; color: #fff; padding: 2.6rem 0 4rem;
                background: linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 55%, var(--brand-3) 100%); }
        .hero::before, .hero::after { content: ""; position: absolute; border-radius: 50%; pointer-events: none; }
        .hero::before { width: 340px; height: 340px; right: -110px; top: -140px; background: rgba(255,255,255,.08); }
        .hero::after  { width: 220px; height: 220px; left: -90px; bottom: -120px; background: rgba(255,255,255,.06); }
        .hero-logo { width: 78px; height: 78px; object-fit: contain; background: #fff; border-radius: 1rem;
                     padding: .45rem; box-shadow: 0 10px 26px rgba(0,0,0,.28); flex: 0 0 auto; }
        .hero-title { font-weight: 800; letter-spacing: -.4px; line-height: 1.15; }
        .hero-sub { color: rgba(255,255,255,.8); }
        .pill { display: inline-flex; align-items: center; gap: .35rem; background: rgba(255,255,255,.14);
                border: 1px solid rgba(255,255,255,.22); color: #fff; border-radius: 999px;
                padding: .3rem .7rem; font-size: .78rem; }

        .stat-strip { margin-top: -2.6rem; position: relative; z-index: 2; }
        .stat-card { border: 0; border-radius: .9rem; box-shadow: 0 6px 22px rgba(16,24,40,.09); height: 100%; }
        .stat-ico { width: 42px; height: 42px; border-radius: .75rem; display: flex; align-items: center;
                    justify-content: center; font-size: 1.15rem; flex: 0 0 auto; }
        .stat-num { font-size: 1.5rem; font-weight: 800; line-height: 1.1; }

        .section-label { font-size: .72rem; letter-spacing: .12em; text-transform: uppercase;
                         font-weight: 700; color: #64748b; }

        .app-card { border: 1px solid #e6e9ee; border-radius: 1rem; background: #fff; height: 100%;
                    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
        .app-card:hover { transform: translateY(-3px); box-shadow: 0 14px 30px rgba(16,24,40,.13); border-color: #cfd6e2; }
        .app-icon { width: 56px; height: 56px; border-radius: .9rem; display: flex; align-items: center;
                    justify-content: center; font-size: 1.5rem; flex: 0 0 auto; }
        .app-title { font-weight: 700; font-size: 1.02rem; }
        .chip { display: inline-block; background: #eef2f7; color: #334155; border-radius: 999px;
                padding: .18rem .55rem; font-size: .72rem; font-weight: 600; }
        .btn-app { font-weight: 600; }

        .info-card { border: 0; border-radius: 1rem; box-shadow: 0 4px 18px rgba(16,24,40,.07); }
        .mod-item { display: flex; gap: .75rem; padding: .6rem 0; border-bottom: 1px dashed #e8ecf2; }
        .mod-item:last-child { border-bottom: 0; }
        .mod-ico { width: 34px; height: 34px; border-radius: .6rem; background: #eef2f7; color: #1e3a8a;
                   display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
        .kv { display: flex; gap: .75rem; padding: .45rem 0; border-bottom: 1px solid #f1f4f8; font-size: .88rem; }
        .kv:last-child { border-bottom: 0; }
        .kv dt { width: 42%; color: #6b7280; font-weight: 500; margin: 0; flex: 0 0 auto; }
        .kv dd { margin: 0; font-weight: 600; word-break: break-word; }

        footer.site { background: #0f172a; color: rgba(255,255,255,.62); }
        footer.site a { color: rgba(255,255,255,.85); text-decoration: none; }
        footer.site a:hover { color: #fff; }
    </style>
</head>
<body>

<!-- ============================ HERO ============================ -->
<header class="hero">
    <div class="container wrap">
        <div class="d-flex flex-wrap align-items-center gap-3 gap-md-4">
            <img src="<?= h($logo_url); ?>" alt="Logo <?= h($sekolah['nama_sekolah']); ?>" class="hero-logo">
            <div class="flex-grow-1" style="min-width:260px;">
                <div class="pill mb-2"><i class="bi bi-mortarboard-fill"></i> Sistem Informasi Sekolah</div>
                <h1 class="hero-title h3 mb-1">SiDoel &mdash; Sistem Data Online Sekolah</h1>
                <div class="hero-sub"><?= h($sekolah['nama_sekolah']); ?>
                    <?php if ($sekolah['NPSN'] !== ''): ?> &middot; NPSN <?= h($sekolah['NPSN']); ?><?php endif; ?>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="pill"><i class="bi bi-calendar-check"></i> TA <?= h($ta['nama']); ?></span>
                <span class="pill"><i class="bi bi-clock-history"></i> <?= h(date('d/m/Y H:i')); ?> WITA</span>
            </div>
        </div>
    </div>
</header>

<main class="container wrap pb-5">

    <!-- ======================= STATISTIK ======================= -->
    <div class="row g-3 stat-strip">
        <?php
        $stats = [
            ['bi-people-fill', '#dbeafe', '#1d4ed8', $stat['siswa'], 'Siswa Aktif',
                ($stat['l'] + $stat['p'] > 0 ? 'L ' . $stat['l'] . ' &middot; P ' . $stat['p'] : 'TA ' . $ta['nama'])],
            ['bi-person-video3', '#ede9fe', '#6d28d9', $stat['guru'], 'Guru &amp; Staf', 'Pendidik dan tenaga kependidikan'],
            ['bi-easel2-fill', '#dcfce7', '#15803d', $stat['kelas'], 'Kelas / Rombel', 'Tahun ajaran ' . $ta['nama']],
            ['bi-diagram-3-fill', '#fef3c7', '#b45309', $stat['jurusan'], 'Kompetensi Keahlian', 'Jurusan aktif'],
            ['bi-journal-bookmark-fill', '#fee2e2', '#b91c1c', $stat['mapel'], 'Mata Pelajaran', 'Kurikulum Merdeka'],
        ];
        foreach ($stats as $s): ?>
            <div class="col-6 col-lg">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <div class="stat-ico" style="background:<?= $s[1]; ?>;color:<?= $s[2]; ?>;"><i class="bi <?= $s[0]; ?>"></i></div>
                        <div style="min-width:0;">
                            <div class="stat-num"><?= number_format((int)$s[3], 0, ',', '.'); ?></div>
                            <div class="small fw-semibold text-truncate"><?= $s[4]; ?></div>
                            <div class="text-muted" style="font-size:.72rem;"><?= $s[5]; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ==================== TOMBOL APLIKASI ==================== -->
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mt-4 mb-3">
        <div>
            <div class="section-label mb-1">Pintu Masuk</div>
            <h2 class="h5 fw-bold mb-0">Masuk ke Aplikasi</h2>
        </div>
        <div class="small text-muted"><i class="bi bi-info-circle me-1"></i>Pilih aplikasi sesuai peran Anda.</div>
    </div>

    <div class="row g-3">
        <?php foreach ($apps as $a): ?>
            <div class="col-12 col-md-6">
                <div class="app-card p-3 p-sm-4 d-flex flex-column">
                    <div class="d-flex align-items-start gap-3">
                        <div class="app-icon" style="background:<?= h($a['soft']); ?>;color:<?= h($a['theme']); ?>;">
                            <i class="bi <?= h($a['icon']); ?>"></i>
                        </div>
                        <div class="flex-grow-1" style="min-width:0;">
                            <div class="app-title"><?= h($a['title']); ?></div>
                            <div class="small text-muted mt-1"><?= h($a['desc']); ?></div>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                <?php foreach ($a['roles'] as $role): ?>
                                    <span class="chip"><?= h($role); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between gap-2 mt-3 pt-3 border-top">
                        <div class="small text-muted" style="min-width:0;">
                            <i class="bi bi-key me-1"></i>Login: <?= h($a['login']); ?>
                        </div>
                        <a href="<?= h($a['url']); ?>"
                           class="btn btn-sm text-white btn-app flex-shrink-0"
                           style="background:<?= h($a['theme']); ?>;border-color:<?= h($a['theme']); ?>;">
                            <?= h($a['cta']); ?> <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ==================== INFORMASI APLIKASI ==================== -->
    <div class="mt-4 mb-3">
        <div class="section-label mb-1">Tentang</div>
        <h2 class="h5 fw-bold mb-0">Informasi Aplikasi</h2>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card info-card h-100">
                <div class="card-body p-3 p-sm-4">
                    <p class="small text-muted mb-3">
                        <b>SiDoel</b> adalah aplikasi administrasi sekolah berbasis web untuk mengelola data
                        akademik dan kesiswaan secara terpadu &mdash; mulai dari data master, penerimaan siswa baru,
                        kegiatan belajar mengajar, keuangan SPP, sampai laporan siap cetak. Setiap peran
                        (admin, guru, wali kelas, sekretaris kelas, siswa) memiliki aplikasi dan hak aksesnya sendiri.
                    </p>
                    <?php foreach ($modules as $m): ?>
                        <div class="mod-item">
                            <div class="mod-ico"><i class="bi <?= h($m[0]); ?>"></i></div>
                            <div style="min-width:0;">
                                <div class="fw-semibold small"><?= h($m[1]); ?></div>
                                <div class="text-muted" style="font-size:.8rem;"><?= h($m[2]); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card info-card mb-3">
                <div class="card-header bg-white py-2 fw-semibold small">
                    <i class="bi bi-building me-1 text-primary"></i> Profil Sekolah
                </div>
                <div class="card-body py-2">
                    <dl class="mb-0">
                        <div class="kv"><dt>Nama Sekolah</dt><dd><?= h($sekolah['nama_sekolah']); ?></dd></div>
                        <?php if ($sekolah['NPSN'] !== ''): ?>
                            <div class="kv"><dt>NPSN</dt><dd><?= h($sekolah['NPSN']); ?></dd></div>
                        <?php endif; ?>
                        <?php if ($sekolah['alamat_sekolah'] !== ''): ?>
                            <div class="kv"><dt>Alamat</dt><dd><?= h($sekolah['alamat_sekolah']); ?></dd></div>
                        <?php endif; ?>
                        <?php if ($sekolah['telp_sekolah'] !== ''): ?>
                            <div class="kv"><dt>Telepon</dt><dd><?= h($sekolah['telp_sekolah']); ?></dd></div>
                        <?php endif; ?>
                        <?php if ($sekolah['email'] !== ''): ?>
                            <div class="kv"><dt>Email</dt><dd><a href="mailto:<?= h($sekolah['email']); ?>" class="text-decoration-none"><?= h($sekolah['email']); ?></a></dd></div>
                        <?php endif; ?>
                        <?php if ($sekolah['web'] !== ''): ?>
                            <div class="kv"><dt>Website</dt><dd><a href="<?= h($sekolah['web']); ?>" target="_blank" rel="noopener" class="text-decoration-none"><?= h($sekolah['web']); ?></a></dd></div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <div class="card info-card">
                <div class="card-header bg-white py-2 fw-semibold small">
                    <i class="bi bi-cpu me-1 text-primary"></i> Detail Sistem
                </div>
                <div class="card-body py-2">
                    <dl class="mb-0">
                        <div class="kv"><dt>Aplikasi</dt><dd>SiDoel &mdash; Sistem Data Online Sekolah</dd></div>
                        <div class="kv"><dt>Tahun Ajaran</dt><dd><?= h($ta['nama']); ?></dd></div>
                        <div class="kv"><dt>Zona Waktu</dt><dd>WITA (Asia/Makassar)</dd></div>
                        <div class="kv"><dt>Teknologi</dt><dd>PHP &middot; MySQL/MariaDB &middot; Bootstrap 5</dd></div>
                        <div class="kv"><dt>Aplikasi Terpasang</dt><dd><?= count($apps); ?> modul aplikasi</dd></div>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-light border mt-3 small text-muted mb-0">
        <i class="bi bi-shield-lock me-1 text-primary"></i>
        Setiap aplikasi memiliki halaman login sendiri dan hak akses sesuai peran. Lupa password atau butuh akses
        tambahan? Hubungi <b>Admin sekolah</b>.
    </div>
</main>

<footer class="site py-4 mt-auto">
    <div class="container wrap">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small">
                &copy; <?= $tahun_ini; ?> <?= h($sekolah['nama_sekolah']); ?> &middot; SiDoel
            </div>
            <div class="small d-flex flex-wrap gap-3">
                <a href="admin/"><i class="bi bi-shield-lock me-1"></i>Admin</a>
                <a href="portal/"><i class="bi bi-person-workspace me-1"></i>Guru &amp; Wali</a>
                <a href="siswa/"><i class="bi bi-mortarboard me-1"></i>Siswa</a>
                <a href="sekretaris/"><i class="bi bi-journal-check me-1"></i>Sekretaris</a>
            </div>
        </div>
    </div>
</footer>

</body>
</html>
