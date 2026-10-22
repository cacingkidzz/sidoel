<?php
// Pastikan sesi sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("koneksi.php");

// =====================================================
// IDENTITAS & TAHUN AJARAN AKTIF
// =====================================================
$nama_sekolah   = $judul ?? ($dsek['nama_sekolah'] ?? 'Sekolah');
$ta_nama        = $tahun_aktif_sesi ?? ($_SESSION['tahun_ajaran_aktif'] ?? '');
$user_display   = $username ?? ($_SESSION['username'] ?? 'Pengguna');
$level_display  = $level ?? ($_SESSION['level_user'] ?? 'Admin');

// Resolve ID tahun ajaran aktif dari nama
$id_ta = 0;
if (!$koneksi->connect_error && $ta_nama !== '') {
    $st = $koneksi->prepare("SELECT id_tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    if ($st) {
        $st->bind_param('s', $ta_nama);
        $st->execute();
        $rs = $st->get_result();
        if ($rs && $rs->num_rows > 0) {
            $id_ta = (int)$rs->fetch_assoc()['id_tahun_ajaran'];
        }
        $st->close();
    }
}

// Helper aman untuk mengambil satu nilai
function dash_val($koneksi, $sql)
{
    $r = $koneksi->query($sql);
    if (!$r) return 0;
    $row = $r->fetch_row();
    return $row ? (int)$row[0] : 0;
}

// =====================================================
// STATISTIK UTAMA
// =====================================================
$total_siswa   = ($id_ta > 0) ? dash_val($koneksi, "SELECT COUNT(DISTINCT ksw.id_siswa) FROM dt_kelas_siswa ksw INNER JOIN dt_siswa s ON s.id_siswa = ksw.id_siswa WHERE ksw.id_tahun_ajaran = '$id_ta'") : 0;
$total_guru    = dash_val($koneksi, "SELECT COUNT(*) FROM dt_guru");
$total_kelas   = ($id_ta > 0) ? dash_val($koneksi, "SELECT COUNT(*) FROM dt_kelas WHERE id_tahun_ajaran = '$id_ta'") : 0;
$total_jurusan = dash_val($koneksi, "SELECT COUNT(*) FROM dt_jurusan");
$total_alumni  = dash_val($koneksi, "SELECT COUNT(*) FROM dt_alumni");

// Gender siswa aktif
$gender_siswa = ['L' => 0, 'P' => 0];
if ($id_ta > 0) {
    $r = $koneksi->query("SELECT s.jk, COUNT(DISTINCT s.id_siswa) AS jml FROM dt_kelas_siswa ksw INNER JOIN dt_siswa s ON s.id_siswa = ksw.id_siswa WHERE ksw.id_tahun_ajaran = '$id_ta' GROUP BY s.jk");
    if ($r) {
        while ($x = $r->fetch_assoc()) {
            $key = strtoupper(trim((string)$x['jk']));
            if ($key === 'L' || $key === 'P') $gender_siswa[$key] = (int)$x['jml'];
        }
    }
}

// Gender guru
$gender_guru = ['L' => 0, 'P' => 0];
$r = $koneksi->query("SELECT jk, COUNT(*) AS jml FROM dt_guru GROUP BY jk");
if ($r) {
    while ($x = $r->fetch_assoc()) {
        $key = strtoupper(trim((string)$x['jk']));
        if ($key === 'L' || $key === 'P') $gender_guru[$key] = (int)$x['jml'];
    }
}

// =====================================================
// SEBARAN PER TINGKAT
// =====================================================
$tingkat_order = ['X', 'XI', 'XII', 'XIII'];
$tingkat_siswa = array_fill_keys($tingkat_order, 0);
$tingkat_kelas = array_fill_keys($tingkat_order, 0);

if ($id_ta > 0) {
    $r = $koneksi->query("SELECT k.tingkat, COUNT(DISTINCT ksw.id_siswa) AS jml FROM dt_kelas_siswa ksw INNER JOIN dt_kelas k ON k.id_kelas = ksw.id_kelas INNER JOIN dt_siswa s ON s.id_siswa = ksw.id_siswa WHERE ksw.id_tahun_ajaran = '$id_ta' GROUP BY k.tingkat");
    if ($r) {
        while ($x = $r->fetch_assoc()) {
            if (isset($tingkat_siswa[$x['tingkat']])) $tingkat_siswa[$x['tingkat']] = (int)$x['jml'];
        }
    }
    $r = $koneksi->query("SELECT tingkat, COUNT(*) AS jml FROM dt_kelas WHERE id_tahun_ajaran = '$id_ta' GROUP BY tingkat");
    if ($r) {
        while ($x = $r->fetch_assoc()) {
            if (isset($tingkat_kelas[$x['tingkat']])) $tingkat_kelas[$x['tingkat']] = (int)$x['jml'];
        }
    }
}

// =====================================================
// SEBARAN PER JURUSAN
// =====================================================
$jurusan_list = [];
$r = $koneksi->query("SELECT id_jurusan, nama_jurusan FROM dt_jurusan ORDER BY nama_jurusan ASC");
if ($r) {
    while ($x = $r->fetch_assoc()) {
        $jurusan_list[(int)$x['id_jurusan']] = ['nama' => $x['nama_jurusan'], 'kelas' => 0, 'siswa' => 0];
    }
}
if ($id_ta > 0 && !empty($jurusan_list)) {
    $r = $koneksi->query("SELECT k.id_jurusan, COUNT(DISTINCT k.id_kelas) AS kls, COUNT(DISTINCT s.id_siswa) AS sw FROM dt_kelas k LEFT JOIN dt_kelas_siswa ksw ON ksw.id_kelas = k.id_kelas AND ksw.id_tahun_ajaran = '$id_ta' LEFT JOIN dt_siswa s ON s.id_siswa = ksw.id_siswa WHERE k.id_tahun_ajaran = '$id_ta' GROUP BY k.id_jurusan");
    if ($r) {
        while ($x = $r->fetch_assoc()) {
            $jid = (int)$x['id_jurusan'];
            if (isset($jurusan_list[$jid])) {
                $jurusan_list[$jid]['kelas'] = (int)$x['kls'];
                $jurusan_list[$jid]['siswa'] = (int)$x['sw'];
            }
        }
    }
}
// Urutkan jurusan berdasar jumlah siswa (terbesar dulu) untuk chart
$jurusan_sorted = $jurusan_list;
uasort($jurusan_sorted, function ($a, $b) {
    return $b['siswa'] <=> $a['siswa'];
});
$jurusan_labels = array_values(array_map(fn($j) => $j['nama'], $jurusan_sorted));
$jurusan_siswa  = array_values(array_map(fn($j) => $j['siswa'], $jurusan_sorted));
$jurusan_kelas  = array_values(array_map(fn($j) => $j['kelas'], $jurusan_sorted));

// =====================================================
// STATUS KEPEGAWAIAN & PENDIDIKAN GURU
// =====================================================
$status_guru = [];
$r = $koneksi->query("SELECT COALESCE(NULLIF(status_kepegawaian, ''), 'Belum diisi') AS s, COUNT(*) AS c FROM dt_guru GROUP BY s ORDER BY c DESC");
if ($r) {
    while ($x = $r->fetch_assoc()) $status_guru[$x['s']] = (int)$x['c'];
}

$pendidikan_guru = [];
$r = $koneksi->query("SELECT COALESCE(NULLIF(lulusan, ''), 'Belum diisi') AS l, COUNT(*) AS c FROM dt_guru GROUP BY l ORDER BY c DESC");
if ($r) {
    while ($x = $r->fetch_assoc()) $pendidikan_guru[$x['l']] = (int)$x['c'];
}

// =====================================================
// KELENGKAPAN DATA
// =====================================================
$wali_terisi    = ($id_ta > 0) ? dash_val($koneksi, "SELECT COUNT(*) FROM dt_wali_kelas WHERE id_tahun_ajaran = '$id_ta'") : 0;
$siswa_koordinat = dash_val($koneksi, "SELECT COUNT(*) FROM dt_siswa WHERE lintang IS NOT NULL AND lintang <> '' AND bujur IS NOT NULL AND bujur <> ''");
$kelas_kosong   = ($id_ta > 0) ? dash_val($koneksi, "SELECT COUNT(*) FROM dt_kelas k WHERE k.id_tahun_ajaran = '$id_ta' AND NOT EXISTS (SELECT 1 FROM dt_kelas_siswa ks JOIN dt_siswa s ON s.id_siswa = ks.id_siswa WHERE ks.id_kelas = k.id_kelas)") : 0;
$kelas_terisi   = max(0, $total_kelas - $kelas_kosong);

$pct = function ($a, $b) {
    return ($b > 0) ? round(($a / $b) * 100, 1) : 0;
};

// =====================================================
// ROMBEL (Top berdasarkan jumlah siswa) + WALI KELAS
// =====================================================
$wali_map = [];
if ($id_ta > 0) {
    $r = $koneksi->query("SELECT w.id_kelas, g.nama_guru FROM dt_wali_kelas w INNER JOIN dt_guru g ON g.id_guru = w.id_guru WHERE w.id_tahun_ajaran = '$id_ta'");
    if ($r) {
        while ($x = $r->fetch_assoc()) $wali_map[(int)$x['id_kelas']] = $x['nama_guru'];
    }
}
$rombel_rows = [];
if ($id_ta > 0) {
    $r = $koneksi->query("SELECT k.id_kelas, k.nama_kelas, k.tingkat, j.nama_jurusan, COUNT(DISTINCT s.id_siswa) AS jml FROM dt_kelas k LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan LEFT JOIN dt_kelas_siswa ksw ON ksw.id_kelas = k.id_kelas AND ksw.id_tahun_ajaran = '$id_ta' LEFT JOIN dt_siswa s ON s.id_siswa = ksw.id_siswa WHERE k.id_tahun_ajaran = '$id_ta' GROUP BY k.id_kelas ORDER BY jml DESC, k.nama_kelas ASC LIMIT 8");
    if ($r) {
        while ($x = $r->fetch_assoc()) $rombel_rows[] = $x;
    }
}

// =====================================================
// KOMPOSISI SISWA PER KELAS (JENIS KELAMIN & AGAMA)
// =====================================================
$kelas_rows = [];
if ($id_ta > 0) {
    $r = $koneksi->query("SELECT k.id_kelas, k.nama_kelas, k.tingkat, j.nama_jurusan FROM dt_kelas k LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan WHERE k.id_tahun_ajaran = '$id_ta' ORDER BY FIELD(k.tingkat, 'X', 'XI', 'XII', 'XIII'), k.nama_kelas ASC");
    if ($r) {
        while ($x = $r->fetch_assoc()) $kelas_rows[] = $x;
    }
}

$pivot_gender = [];   // id_kelas => ['l'=>, 'p'=>, 't'=>]
$pivot_agama  = [];   // id_kelas => [agama => jumlah]
$agama_totals = [];   // agama => jumlah total
if ($id_ta > 0) {
    // Jenis kelamin per kelas
    $r = $koneksi->query("SELECT ksw.id_kelas, SUM(s.jk = 'L') AS l, SUM(s.jk = 'P') AS p, COUNT(DISTINCT s.id_siswa) AS t FROM dt_kelas_siswa ksw INNER JOIN dt_siswa s ON s.id_siswa = ksw.id_siswa WHERE ksw.id_tahun_ajaran = '$id_ta' GROUP BY ksw.id_kelas");
    if ($r) {
        while ($x = $r->fetch_assoc()) {
            $pivot_gender[(int)$x['id_kelas']] = ['l' => (int)$x['l'], 'p' => (int)$x['p'], 't' => (int)$x['t']];
        }
    }

    // Agama per kelas (ejaan "Katholik" diseragamkan menjadi "Katolik")
    $r = $koneksi->query("SELECT ksw.id_kelas, COALESCE(NULLIF(s.agama, ''), '(kosong)') AS agama, COUNT(DISTINCT s.id_siswa) AS jml FROM dt_kelas_siswa ksw INNER JOIN dt_siswa s ON s.id_siswa = ksw.id_siswa WHERE ksw.id_tahun_ajaran = '$id_ta' GROUP BY ksw.id_kelas, agama");
    if ($r) {
        while ($x = $r->fetch_assoc()) {
            $ag = ($x['agama'] === 'Katholik') ? 'Katolik' : $x['agama'];
            $kid = (int)$x['id_kelas'];
            $pivot_agama[$kid][$ag] = ($pivot_agama[$kid][$ag] ?? 0) + (int)$x['jml'];
            $agama_totals[$ag] = ($agama_totals[$ag] ?? 0) + (int)$x['jml'];
        }
    }
}
arsort($agama_totals);
$agama_cols = array_keys($agama_totals);
$grand_gender_l = array_sum(array_column($pivot_gender, 'l'));
$grand_gender_p = array_sum(array_column($pivot_gender, 'p'));

// Warna badge per agama (opsional, fallback abu-abu)
$agama_color = [
    'Hindu'    => 'bg-warning text-dark',
    'Islam'    => 'bg-success',
    'Kristen'  => 'bg-primary',
    'Katolik'  => 'bg-info text-dark',
    'Buddha'   => 'bg-danger',
    'Konghucu' => 'bg-secondary',
    '(kosong)' => 'bg-light text-dark border',
];

$badge_tingkat = function ($t) {
    return match ($t) {
        'X' => 'bg-success',
        'XI' => 'bg-info',
        'XII' => 'bg-warning text-dark',
        'XIII' => 'bg-danger',
        default => 'bg-secondary',
    };
};
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Dashboard</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">

            <!-- ============ HERO / SAMBUTAN ============ -->
            <div class="dash-hero mb-3">
                <div class="dash-hero-text">
                    <div class="dash-hero-hi">Selamat datang, <?= htmlspecialchars(strtoupper((string)$user_display)); ?></div>
                    <div class="dash-hero-school"><i class="bi bi-building me-1"></i><?= htmlspecialchars((string)$nama_sekolah); ?></div>
                    <div class="dash-hero-meta">
                        <span class="dash-chip"><i class="bi bi-calendar-check me-1"></i>TA <?= htmlspecialchars((string)$ta_nama ?: '-'); ?></span>
                        <span class="dash-chip"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars((string)$level_display); ?></span>
                        <span class="dash-chip"><i class="bi bi-clock me-1"></i><span id="dash-clock">-</span></span>
                    </div>
                </div>
                <div class="dash-hero-actions">
                    <?php if (!function_exists('boleh_menu') || boleh_menu($level_user, 'siswa_aktif')): ?>
                    <a href="index.php?menu=siswa_aktif" class="btn btn-light btn-sm fw-semibold"><i class="bi bi-people-fill me-1"></i>Siswa</a>
                    <?php endif; ?>
                    <?php if (!function_exists('boleh_menu') || boleh_menu($level_user, 'guru')): ?>
                    <a href="index.php?menu=guru" class="btn btn-light btn-sm fw-semibold"><i class="bi bi-person-workspace me-1"></i>Guru</a>
                    <?php endif; ?>
                    <?php if (!function_exists('boleh_menu') || boleh_menu($level_user, 'info_kelas')): ?>
                    <a href="index.php?menu=info_kelas" class="btn btn-light btn-sm fw-semibold"><i class="bi bi-house-door-fill me-1"></i>Rombel</a>
                    <?php endif; ?>
                    <?php if (!function_exists('boleh_menu') || boleh_menu($level_user, 'kenaikan_kelas')): ?>
                    <a href="index.php?menu=kenaikan_kelas" class="btn btn-warning btn-sm fw-semibold"><i class="bi bi-arrow-up-circle me-1"></i>Kenaikan</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['alert']['type']); ?> alert-dismissible fade show" role="alert">
                    <?= $_SESSION['alert']['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>

            <!-- ============ KARTU STATISTIK ============ -->
            <div class="row g-3 mb-1">
                <div class="col-sm-6 col-xl-3">
                    <div class="dash-stat ds-primary">
                        <div class="ds-ic"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <div class="ds-label">Siswa Aktif</div>
                            <div class="ds-value"><?= number_format($total_siswa); ?></div>
                            <div class="ds-sub">L <?= number_format($gender_siswa['L']); ?> &middot; P <?= number_format($gender_siswa['P']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="dash-stat ds-green">
                        <div class="ds-ic"><i class="bi bi-person-workspace"></i></div>
                        <div>
                            <div class="ds-label">Guru &amp; Staf</div>
                            <div class="ds-value"><?= number_format($total_guru); ?></div>
                            <div class="ds-sub">L <?= number_format($gender_guru['L']); ?> &middot; P <?= number_format($gender_guru['P']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="dash-stat ds-cyan">
                        <div class="ds-ic"><i class="bi bi-house-door-fill"></i></div>
                        <div>
                            <div class="ds-label">Rombel</div>
                            <div class="ds-value"><?= number_format($total_kelas); ?></div>
                            <div class="ds-sub">X <?= $tingkat_kelas['X']; ?> &middot; XI <?= $tingkat_kelas['XI']; ?> &middot; XII <?= $tingkat_kelas['XII']; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="dash-stat ds-amber">
                        <div class="ds-ic"><i class="bi bi-award-fill"></i></div>
                        <div>
                            <div class="ds-label">Jurusan</div>
                            <div class="ds-value"><?= number_format($total_jurusan); ?></div>
                            <div class="ds-sub">Alumni <?= number_format($total_alumni); ?> orang</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ GRAFIK BARIS 1 ============ -->
            <div class="row g-3">
                <div class="col-xl-8">
                    <div class="card dash-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-bar-chart-fill text-primary me-1"></i> Sebaran Siswa per Jurusan</h6>
                            <span class="badge bg-light text-dark border">Total <?= number_format(array_sum($jurusan_siswa)); ?> siswa</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($jurusan_labels)): ?>
                                <div class="text-center text-muted py-5"><i class="bi bi-inboxes fs-1"></i><p class="mt-2 mb-0">Belum ada data jurusan.</p></div>
                            <?php else: ?>
                                <div class="dash-chart" style="height: 320px;"><canvas id="chartJurusan"></canvas></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card dash-card h-100">
                        <div class="card-header">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-gender-ambiguous text-danger me-1"></i> Jenis Kelamin Siswa</h6>
                        </div>
                        <div class="card-body">
                            <div class="dash-chart" style="height: 320px;"><canvas id="chartGender"></canvas></div>
                            <div class="d-flex justify-content-center gap-3 mt-2 small">
                                <span><span class="dash-dot" style="background:#2563eb"></span> Laki-laki (<?= number_format($gender_siswa['L']); ?>)</span>
                                <span><span class="dash-dot" style="background:#db2777"></span> Perempuan (<?= number_format($gender_siswa['P']); ?>)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ GRAFIK BARIS 2 ============ -->
            <div class="row g-3 mt-0">
                <div class="col-xl-4">
                    <div class="card dash-card h-100">
                        <div class="card-header">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-diagram-3-fill text-info me-1"></i> Siswa per Tingkat</h6>
                        </div>
                        <div class="card-body">
                            <div class="dash-chart" style="height: 260px;"><canvas id="chartTingkat"></canvas></div>
                            <div class="d-flex justify-content-center gap-3 mt-2 small">
                                <span><span class="dash-dot" style="background:#16a34a"></span> X: <?= $tingkat_siswa['X']; ?></span>
                                <span><span class="dash-dot" style="background:#0891b2"></span> XI: <?= $tingkat_siswa['XI']; ?></span>
                                <span><span class="dash-dot" style="background:#f59e0b"></span> XII: <?= $tingkat_siswa['XII']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card dash-card h-100">
                        <div class="card-header">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-briefcase-fill text-success me-1"></i> Status Kepegawaian Guru</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($status_guru)): ?>
                                <div class="text-center text-muted py-5"><i class="bi bi-inboxes fs-1"></i><p class="mt-2 mb-0">Belum ada data guru.</p></div>
                            <?php else: ?>
                                <div class="dash-chart" style="height: 260px;"><canvas id="chartStatusGuru"></canvas></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card dash-card h-100">
                        <div class="card-header">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-clipboard2-check-fill text-warning me-1"></i> Kelengkapan Data</h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $progress_items = [
                                ['label' => 'Wali Kelas ditetapkan', 'val' => $wali_terisi, 'max' => $total_kelas, 'color' => 'bg-success', 'icon' => 'bi-person-bounding-box'],
                                ['label' => 'Rombel terisi siswa', 'val' => $kelas_terisi, 'max' => $total_kelas, 'color' => 'bg-primary', 'icon' => 'bi-house-door-fill'],
                                ['label' => 'Siswa punya koordinat', 'val' => $siswa_koordinat, 'max' => $total_siswa, 'color' => 'bg-info', 'icon' => 'bi-geo-alt-fill'],
                            ];
                            foreach ($progress_items as $it):
                                $p = $pct($it['val'], $it['max']);
                            ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small fw-semibold"><i class="bi <?= $it['icon']; ?> me-1 text-muted"></i><?= $it['label']; ?></span>
                                        <span class="small text-muted"><?= number_format($it['val']); ?> / <?= number_format($it['max']); ?> <b class="text-dark">(<?= $p; ?>%)</b></span>
                                    </div>
                                    <div class="progress" style="height: 9px;">
                                        <div class="progress-bar <?= $it['color']; ?>" role="progressbar" style="width: <?= $p; ?>%;" aria-valuenow="<?= $p; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <hr class="my-2">
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($pendidikan_guru as $edu => $c): ?>
                                    <span class="badge bg-light text-dark border"><i class="bi bi-mortarboard me-1"></i><?= htmlspecialchars($edu); ?>: <b><?= $c; ?></b></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ TABEL KOMPOSISI PER KELAS ============ -->
            <div class="row g-3 mt-0">
                <div class="col-xl-5">
                    <div class="card dash-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-gender-ambiguous text-primary me-1"></i> Laki-laki &amp; Perempuan per Kelas</h6>
                            <span class="badge bg-light text-dark border"><?= count($kelas_rows); ?> kelas</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive dash-scroll">
                                <table class="table table-sm table-hover align-middle mb-0 text-nowrap">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">Kelas</th>
                                            <th class="text-center"><i class="bi bi-gender-male text-primary"></i> L</th>
                                            <th class="text-center"><i class="bi bi-gender-female text-danger"></i> P</th>
                                            <th class="text-center">Jml</th>
                                            <th style="width: 30%;">Komposisi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($kelas_rows)): ?>
                                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data kelas.</td></tr>
                                        <?php else: foreach ($kelas_rows as $k):
                                            $kid = (int)$k['id_kelas'];
                                            $pg = $pivot_gender[$kid] ?? ['l' => 0, 'p' => 0, 't' => 0];
                                            $l = $pg['l'];
                                            $p = $pg['p'];
                                            $t = $l + $p;
                                            $pl = ($t > 0) ? round($l / $t * 100, 1) : 0;
                                            $pp = ($t > 0) ? round($p / $t * 100, 1) : 0;
                                        ?>
                                            <tr>
                                                <td class="ps-3">
                                                    <span class="fw-semibold"><?= htmlspecialchars($k['nama_kelas']); ?></span>
                                                    <span class="badge <?= $badge_tingkat($k['tingkat']); ?> ms-1"><?= htmlspecialchars($k['tingkat']); ?></span>
                                                </td>
                                                <td class="text-center fw-semibold text-primary"><?= $l; ?></td>
                                                <td class="text-center fw-semibold text-danger"><?= $p; ?></td>
                                                <td class="text-center"><span class="badge bg-secondary rounded-pill"><?= $t; ?></span></td>
                                                <td>
                                                    <div class="progress" style="height: 8px;" title="L <?= $pl; ?>% / P <?= $pp; ?>%">
                                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $pl; ?>%"></div>
                                                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $pp; ?>%"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                        <?php if (!empty($kelas_rows)): ?>
                                            <tr class="table-light fw-bold">
                                                <td class="ps-3">TOTAL</td>
                                                <td class="text-center text-primary"><?= number_format($grand_gender_l); ?></td>
                                                <td class="text-center text-danger"><?= number_format($grand_gender_p); ?></td>
                                                <td class="text-center"><span class="badge bg-dark rounded-pill"><?= number_format($grand_gender_l + $grand_gender_p); ?></span></td>
                                                <td></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-7">
                    <div class="card dash-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-bookmark-star text-success me-1"></i> Jumlah Siswa per Agama di Setiap Kelas</h6>
                            <a href="index.php?menu=siswa_aktif" class="small text-decoration-none">Kelola <i class="bi bi-arrow-right"></i></a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive dash-scroll">
                                <table class="table table-sm table-hover align-middle mb-0 text-nowrap">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">Kelas</th>
                                            <?php foreach ($agama_cols as $ag): ?>
                                                <th class="text-center">
                                                    <span class="badge <?= $agama_color[$ag] ?? 'bg-light text-dark border'; ?>"><?= htmlspecialchars($ag); ?></span>
                                                </th>
                                            <?php endforeach; ?>
                                            <th class="text-center">Jml</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($kelas_rows) || empty($agama_cols)): ?>
                                            <tr><td colspan="<?= count($agama_cols) + 2; ?>" class="text-center text-muted py-4">Belum ada data.</td></tr>
                                        <?php else: foreach ($kelas_rows as $k):
                                            $kid = (int)$k['id_kelas'];
                                            $row_total = 0;
                                        ?>
                                            <tr>
                                                <td class="ps-3">
                                                    <span class="fw-semibold"><?= htmlspecialchars($k['nama_kelas']); ?></span>
                                                    <span class="badge <?= $badge_tingkat($k['tingkat']); ?> ms-1"><?= htmlspecialchars($k['tingkat']); ?></span>
                                                </td>
                                                <?php foreach ($agama_cols as $ag):
                                                    $c = $pivot_agama[$kid][$ag] ?? 0;
                                                    $row_total += $c;
                                                ?>
                                                    <td class="text-center"><?= $c > 0 ? number_format($c) : '<span class="text-muted">-</span>'; ?></td>
                                                <?php endforeach; ?>
                                                <td class="text-center"><span class="badge bg-secondary rounded-pill"><?= number_format($row_total); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                            <tr class="table-light fw-bold">
                                                <td class="ps-3">TOTAL</td>
                                                <?php foreach ($agama_cols as $ag): ?>
                                                    <td class="text-center"><?= number_format($agama_totals[$ag]); ?></td>
                                                <?php endforeach; ?>
                                                <td class="text-center"><span class="badge bg-dark rounded-pill"><?= number_format(array_sum($agama_totals)); ?></span></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ TABEL ============ -->
            <div class="row g-3 mt-0">
                <div class="col-xl-6">
                    <div class="card dash-card h-100">
                        <div class="card-header">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-table text-primary me-1"></i> Rekap per Jurusan</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">Jurusan</th>
                                            <th class="text-center">Kelas</th>
                                            <th class="text-center">Siswa</th>
                                            <th style="width: 34%;">Proporsi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($jurusan_sorted)): ?>
                                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data.</td></tr>
                                        <?php else: foreach ($jurusan_sorted as $j):
                                            $p = $pct($j['siswa'], array_sum($jurusan_siswa));
                                        ?>
                                            <tr>
                                                <td class="ps-3 fw-semibold"><?= htmlspecialchars($j['nama']); ?></td>
                                                <td class="text-center"><span class="badge bg-secondary"><?= $j['kelas']; ?></span></td>
                                                <td class="text-center fw-bold"><?= number_format($j['siswa']); ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress flex-grow-1" style="height: 8px;">
                                                            <div class="progress-bar bg-primary" style="width: <?= $p; ?>%"></div>
                                                        </div>
                                                        <small class="text-muted" style="min-width:42px;"><?= $p; ?>%</small>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card dash-card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-people text-info me-1"></i> Rombel Terbesar</h6>
                            <a href="index.php?menu=info_kelas" class="small text-decoration-none">Lihat semua <i class="bi bi-arrow-right"></i></a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">Kelas</th>
                                            <th>Jurusan</th>
                                            <th>Wali Kelas</th>
                                            <th class="text-center">Siswa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($rombel_rows)): ?>
                                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data rombel.</td></tr>
                                        <?php else: foreach ($rombel_rows as $rb):
                                            $wali = $wali_map[(int)$rb['id_kelas']] ?? null;
                                        ?>
                                            <tr>
                                                <td class="ps-3">
                                                    <span class="fw-semibold"><?= htmlspecialchars($rb['nama_kelas']); ?></span>
                                                    <span class="badge <?= $badge_tingkat($rb['tingkat']); ?> ms-1"><?= htmlspecialchars($rb['tingkat']); ?></span>
                                                </td>
                                                <td><small><?= htmlspecialchars($rb['nama_jurusan'] ?? '-'); ?></small></td>
                                                <td>
                                                    <?php if ($wali): ?>
                                                        <small><i class="bi bi-person-check text-success me-1"></i><?= htmlspecialchars($wali); ?></small>
                                                    <?php else: ?>
                                                        <small class="text-muted fst-italic">Belum ditetapkan</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center"><span class="badge bg-primary rounded-pill"><?= (int)$rb['jml']; ?></span></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<style>
    /* ====== Dashboard ====== */
    .dash-hero {
        background: linear-gradient(135deg, #1e293b 0%, #2563eb 100%);
        color: #fff;
        border-radius: 1rem;
        padding: 1.35rem 1.5rem;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 10px 26px rgba(37, 99, 235, .25);
    }

    .dash-hero::after {
        content: "";
        position: absolute;
        right: -70px;
        top: -80px;
        width: 240px;
        height: 240px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .08);
    }

    .dash-hero-text { position: relative; z-index: 1; }
    .dash-hero-hi { font-size: 1.35rem; font-weight: 700; line-height: 1.2; }
    .dash-hero-school { font-size: .95rem; opacity: .92; margin-top: .15rem; }
    .dash-hero-meta { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .7rem; }

    .dash-chip {
        background: rgba(255, 255, 255, .16);
        border: 1px solid rgba(255, 255, 255, .22);
        border-radius: 2rem;
        padding: .25rem .75rem;
        font-size: .78rem;
        display: inline-flex;
        align-items: center;
    }

    .dash-hero-actions { position: relative; z-index: 1; display: flex; flex-wrap: wrap; gap: .4rem; }

    .dash-stat {
        display: flex;
        align-items: center;
        gap: 1rem;
        border-radius: .9rem;
        padding: 1.05rem 1.15rem;
        color: #fff;
        height: 100%;
        box-shadow: 0 6px 18px rgba(16, 24, 40, .12);
    }

    .dash-stat .ds-ic {
        width: 54px;
        height: 54px;
        flex: 0 0 54px;
        border-radius: .75rem;
        background: rgba(255, 255, 255, .2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .dash-stat .ds-label { font-size: .74rem; text-transform: uppercase; letter-spacing: .05em; opacity: .92; font-weight: 600; }
    .dash-stat .ds-value { font-size: 1.65rem; font-weight: 700; line-height: 1.1; }
    .dash-stat .ds-sub { font-size: .76rem; opacity: .9; }

    .ds-primary { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
    .ds-green { background: linear-gradient(135deg, #16a34a, #15803d); }
    .ds-cyan { background: linear-gradient(135deg, #0891b2, #0e7490); }
    .ds-amber { background: linear-gradient(135deg, #f59e0b, #d97706); }

    .dash-card {
        border: 0;
        border-radius: .85rem;
        box-shadow: 0 4px 16px rgba(16, 24, 40, .07);
    }

    .dash-card .card-header {
        background: #fff;
        border-bottom: 1px solid #eef1f5;
        border-radius: .85rem .85rem 0 0;
        padding: .8rem 1rem;
    }

    .dash-chart { position: relative; width: 100%; }

    .dash-scroll { max-height: 400px; overflow-y: auto; }

    .dash-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f1f3f7;
        box-shadow: inset 0 -1px 0 #e2e6ee;
    }

    .dash-scroll tbody tr:last-child td { border-bottom: 0; }

    .dash-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 4px;
        vertical-align: middle;
    }

    @media (max-width: 575.98px) {
        .dash-hero-hi { font-size: 1.1rem; }
        .dash-stat .ds-value { font-size: 1.4rem; }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    (function() {
        // Jam realtime WITA
        function tickDashClock() {
            var el = document.getElementById('dash-clock');
            if (!el) return;
            try {
                var now = new Date();
                var d = new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Makassar', weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(now);
                var t = new Intl.DateTimeFormat('id-ID', { timeZone: 'Asia/Makassar', hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(now);
                el.textContent = d + ' - ' + t + ' WITA';
            } catch (e) { /* ignore */ }
        }
        tickDashClock();
        setInterval(tickDashClock, 1000);

        function initDashCharts() {
            if (typeof Chart === 'undefined') return;
            Chart.defaults.font.family = "'Source Sans 3', system-ui, sans-serif";
            Chart.defaults.color = '#64748b';

            var PALETTE = ['#2563eb', '#0891b2', '#16a34a', '#f59e0b', '#e11d48', '#7c3aed', '#0d9488', '#db2777'];

            // 1) Sebaran siswa per jurusan (horizontal bar)
            var elJurusan = document.getElementById('chartJurusan');
            if (elJurusan) {
                new Chart(elJurusan, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($jurusan_labels, JSON_UNESCAPED_UNICODE); ?>,
                        datasets: [{
                            label: 'Jumlah Siswa',
                            data: <?= json_encode($jurusan_siswa); ?>,
                            backgroundColor: PALETTE,
                            borderRadius: 6,
                            maxBarThickness: 38
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    afterLabel: function(ctx) {
                                        var kls = <?= json_encode($jurusan_kelas); ?>;
                                        return 'Jumlah kelas: ' + (kls[ctx.dataIndex] || 0);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,.05)' } },
                            y: { grid: { display: false } }
                        }
                    }
                });
            }

            // 2) Jenis kelamin siswa (doughnut)
            var elGender = document.getElementById('chartGender');
            if (elGender) {
                new Chart(elGender, {
                    type: 'doughnut',
                    data: {
                        labels: ['Laki-laki', 'Perempuan'],
                        datasets: [{
                            data: [<?= (int)$gender_siswa['L']; ?>, <?= (int)$gender_siswa['P']; ?>],
                            backgroundColor: ['#2563eb', '#db2777'],
                            borderWidth: 0,
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '62%',
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 3) Siswa per tingkat (doughnut)
            var elTingkat = document.getElementById('chartTingkat');
            if (elTingkat) {
                new Chart(elTingkat, {
                    type: 'doughnut',
                    data: {
                        labels: ['Kelas X', 'Kelas XI', 'Kelas XII'],
                        datasets: [{
                            data: [<?= (int)$tingkat_siswa['X']; ?>, <?= (int)$tingkat_siswa['XI']; ?>, <?= (int)$tingkat_siswa['XII']; ?>],
                            backgroundColor: ['#16a34a', '#0891b2', '#f59e0b'],
                            borderWidth: 0,
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        plugins: { legend: { display: false } }
                    }
                });
            }

            // 4) Status kepegawaian guru (horizontal bar)
            var elStatus = document.getElementById('chartStatusGuru');
            if (elStatus) {
                new Chart(elStatus, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_keys($status_guru), JSON_UNESCAPED_UNICODE); ?>,
                        datasets: [{
                            label: 'Jumlah Guru',
                            data: <?= json_encode(array_values($status_guru)); ?>,
                            backgroundColor: PALETTE,
                            borderRadius: 6,
                            maxBarThickness: 26
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,.05)' } },
                            y: { grid: { display: false } }
                        }
                    }
                });
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDashCharts);
        } else {
            initDashCharts();
        }
    })();
</script>
