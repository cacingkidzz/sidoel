<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../koneksi.php');
require_once(__DIR__ . '/../helper_kelengkapan.php');

function kel_e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// ==========================================================
// Tahun ajaran (mengikuti sesi sidebar)
// ==========================================================
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
$id_ta_aktif = 0;
$nama_ta_aktif = '-';
if ($nama_ta_sesi !== '' && isset($koneksi) && !$koneksi->connect_error) {
    $q = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    if ($q) {
        $q->bind_param('s', $nama_ta_sesi);
        $q->execute();
        $d = $q->get_result()->fetch_assoc();
        $q->close();
        if ($d) {
            $id_ta_aktif = (int)$d['id_tahun_ajaran'];
            $nama_ta_aktif = $d['tahun_ajaran'];
        }
    }
}
if ($id_ta_aktif <= 0 && isset($koneksi) && !$koneksi->connect_error) {
    $q = $koneksi->query("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE status = 'aktif' LIMIT 1");
    if ($q) {
        $d = $q->fetch_assoc();
        $q->close();
        if ($d) {
            $id_ta_aktif = (int)$d['id_tahun_ajaran'];
            $nama_ta_aktif = $d['tahun_ajaran'];
        }
    }
}

// ==========================================================
// Definisi field wajib & pendukung (helper_kelengkapan.php)
// ==========================================================
$required = kel_required_fields();
$requiredByKey = [];
foreach ($required as $f) $requiredByKey[$f['key']] = $f;
$optionalGroups = kel_optional_groups();

// ==========================================================
// Filter
// ==========================================================
$f_status = $_GET['status'] ?? 'semua';
if (!in_array($f_status, ['semua', 'belum', 'lengkap'], true)) $f_status = 'semua';
$f_kelas = (int)($_GET['kelas'] ?? 0);
$f_field = (string)($_GET['field'] ?? '');
if ($f_field !== '' && !isset($requiredByKey[$f_field])) $f_field = '';
$f_cari = trim((string)($_GET['cari'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;

$kelas_list = [];
$rows = [];

if ($id_ta_aktif > 0 && isset($koneksi) && !$koneksi->connect_error) {
    $qk = $koneksi->prepare("SELECT k.id_kelas, k.nama_kelas, k.tingkat, j.nama_jurusan
        FROM dt_kelas k
        LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
        WHERE k.id_tahun_ajaran = ?
        ORDER BY k.tingkat ASC, k.nama_kelas ASC");
    if ($qk) {
        $qk->bind_param('i', $id_ta_aktif);
        $qk->execute();
        $res = $qk->get_result();
        while ($k = $res->fetch_assoc()) $kelas_list[] = $k;
        $qk->close();
    }

    $sql = "SELECT s.*, k.id_kelas AS kelas_id, k.tingkat AS kelas_tingkat,
                   k.nama_kelas AS kelas_nama, j.nama_jurusan
            FROM dt_siswa s
            LEFT JOIN dt_kelas_siswa ks ON ks.id_siswa = s.id_siswa AND ks.id_tahun_ajaran = ?
            LEFT JOIN dt_kelas k ON k.id_kelas = ks.id_kelas
            LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
            WHERE (s.id_tahun_ajaran = ? OR ks.id_kelas_siswa IS NOT NULL)
            ORDER BY s.nama ASC";
    $stmt = $koneksi->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ii', $id_ta_aktif, $id_ta_aktif);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
    }
}

// ==========================================================
// Hitung kelengkapan per siswa
// ==========================================================
$total_required = count($required);
$total_rows = count($rows);
$sum_pct = 0.0;
$lengkap = 0;

foreach ($rows as &$r) {
    $k = kel_baris($r);
    $r['_filled'] = $k['filled'];
    $r['_pct'] = $k['pct'];
    $r['_missing'] = $k['missing'];
    $r['_missing_keys'] = $k['missing_keys'];
    $r['_lengkap'] = $k['lengkap'];
    $sum_pct += $k['pct'];
    if ($k['lengkap']) $lengkap++;
}
unset($r);

$avg_pct = $total_rows > 0 ? round($sum_pct / $total_rows, 1) : 0.0;
$belum = $total_rows - $lengkap;

// Statistik per field wajib
$fieldStats = [];
foreach ($required as $f) {
    $terisi = 0;
    foreach ($rows as $r) {
        if (kel_isi($r[$f['key']] ?? null)) $terisi++;
    }
    $fieldStats[] = [
        'key' => $f['key'],
        'label' => $f['label'],
        'group' => $f['group'],
        'terisi' => $terisi,
        'kosong' => $total_rows - $terisi,
        'pct' => kel_pct($terisi, $total_rows),
    ];
}
usort($fieldStats, function ($a, $b) {
    if ($a['kosong'] === $b['kosong']) return strcmp($a['label'], $b['label']);
    return $b['kosong'] <=> $a['kosong'];
});
$fieldTerlemah = null;
foreach ($fieldStats as $fs) {
    if ($fs['kosong'] > 0) { $fieldTerlemah = $fs; break; }
}

// Statistik per kelompok wajib
$reqGroups = [];
foreach ($required as $f) {
    $reqGroups[$f['group']][] = $f;
}
$reqGroupStats = [];
foreach ($reqGroups as $g => $fields) {
    $reqGroupStats[$g] = kel_group($rows, $fields);
}
// Statistik kelompok pendukung
$optGroupStats = [];
foreach ($optionalGroups as $g => $fields) {
    $optGroupStats[$g] = kel_group($rows, $fields, true);
}

// ==========================================================
// Filter baris untuk tabel
// ==========================================================
$rowsFiltered = [];
foreach ($rows as $r) {
    if ($f_status === 'belum' && $r['_lengkap']) continue;
    if ($f_status === 'lengkap' && !$r['_lengkap']) continue;
    if ($f_kelas > 0 && (int)($r['kelas_id'] ?? 0) !== $f_kelas) continue;
    if ($f_field !== '' && !in_array($f_field, $r['_missing_keys'], true)) continue;
    if ($f_cari !== '') {
        $hay = ($r['nama'] ?? '') . ' ' . ($r['nipd'] ?? '') . ' ' . ($r['nisn'] ?? '');
        if (stripos($hay, $f_cari) === false) continue;
    }
    $rowsFiltered[] = $r;
}
usort($rowsFiltered, function ($a, $b) {
    if ($a['_pct'] == $b['_pct']) return strcmp((string)$a['nama'], (string)$b['nama']);
    return $a['_pct'] <=> $b['_pct'];
});

$total_filtered = count($rowsFiltered);
$pages = max(1, (int)ceil($total_filtered / $per_page));
if ($page > $pages) $page = $pages;
$offset = ($page - 1) * $per_page;
$rowsPage = array_slice($rowsFiltered, $offset, $per_page);

$baseParams = [
    'menu' => 'kelengkapan_siswa',
    'status' => $f_status,
    'kelas' => $f_kelas > 0 ? $f_kelas : '',
    'field' => $f_field,
    'cari' => $f_cari,
];
$baseParams = array_filter($baseParams, function ($v) { return $v !== '' && $v !== null; });
function kel_url(array $base, int $page): string
{
    $base['page'] = $page;
    return '?' . http_build_query($base);
}
$reset_url = '?menu=kelengkapan_siswa';
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">Kelengkapan Data Siswa</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item"><a href="index.php?menu=siswa_aktif"><i class="bi bi-people-fill"></i> Data Siswa</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Kelengkapan Data</li>
                    </ol>
                </div>
            </div>
            <p class="small text-muted mt-2 mb-0">
                Memantau persentase data siswa yang sudah terisi, dengan prioritas <b>16 field wajib</b>.
                Tahun Ajaran: <b><?= kel_e($nama_ta_aktif); ?></b>
            </p>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">

            <?php if ($id_ta_aktif <= 0): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Tahun ajaran tidak ditemukan. Atur terlebih dahulu di menu <b>Tahun Ajaran</b>.
                </div>
            <?php else: ?>

                <div class="row g-3 mb-3">
                    <div class="col-xl-3 col-md-6">
                        <div class="info-box bg-primary text-white shadow-sm h-100">
                            <span class="info-box-icon"><i class="bi bi-people-fill"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">TOTAL SISWA</span>
                                <span class="info-box-number"><?= number_format($total_rows); ?></span>
                                <span class="progress-description">TA <?= kel_e($nama_ta_aktif); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="info-box bg-info text-white shadow-sm h-100">
                            <span class="info-box-icon"><i class="bi bi-clipboard2-check-fill"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">RATA-RATA DATA WAJIB</span>
                                <span class="info-box-number"><?= number_format($avg_pct, 1, ',', '.'); ?>%</span>
                                <div class="progress mt-1" style="height:5px;">
                                    <div class="progress-bar bg-white" style="width:<?= $avg_pct; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="info-box bg-success text-white shadow-sm h-100">
                            <span class="info-box-icon"><i class="bi bi-patch-check-fill"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">DATA WAJIB LENGKAP</span>
                                <span class="info-box-number"><?= number_format($lengkap); ?></span>
                                <span class="progress-description"><?= kel_pct($lengkap, $total_rows); ?>% dari total</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="info-box bg-danger text-white shadow-sm h-100">
                            <span class="info-box-icon"><i class="bi bi-exclamation-octagon-fill"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">BELUM LENGKAP</span>
                                <span class="info-box-number"><?= number_format($belum); ?></span>
                                <span class="progress-description"><?= kel_pct($belum, $total_rows); ?>% dari total</span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($fieldTerlemah !== null): ?>
                    <div class="alert alert-warning d-flex align-items-start gap-2 py-2">
                        <i class="bi bi-lightbulb-fill mt-1"></i>
                        <div class="small">
                            Field wajib paling banyak kosong: <b><?= kel_e($fieldTerlemah['label']); ?></b>
                            — <b><?= number_format($fieldTerlemah['kosong']); ?></b> siswa
                            (<?= number_format(100 - $fieldTerlemah['pct'], 1, ',', '.'); ?>% belum terisi).
                            <a href="<?= kel_e(kel_url(array_merge($baseParams, ['field' => $fieldTerlemah['key']]), 1)); ?>" class="ms-1">Lihat siswa</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row g-3 mb-3">
                    <div class="col-lg-7">
                        <div class="card card-outline card-warning shadow-sm h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-list-check me-1"></i>Persentase per Field Wajib</h5>
                                <div class="card-tools small text-muted">Diurutkan dari yang paling kosong</div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height:430px;overflow:auto;">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>Field</th>
                                                <th class="text-end">Terisi</th>
                                                <th style="width:42%;">Persentase</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($fieldStats as $fs): ?>
                                                <tr>
                                                    <td>
                                                        <a href="<?= kel_e(kel_url(array_merge($baseParams, ['field' => $fs['key']]), 1)); ?>" class="text-decoration-none fw-semibold">
                                                            <?= kel_e($fs['label']); ?>
                                                        </a>
                                                        <div class="small text-muted"><?= kel_e($fs['group']); ?></div>
                                                    </td>
                                                    <td class="text-end small"><?= number_format($fs['terisi']); ?>/<?= number_format($total_rows); ?></td>
                                                    <td>
                                                        <div class="progress" style="height:7px;">
                                                            <div class="progress-bar bg-<?= kel_cls($fs['pct']); ?>" style="width:<?= max(0, min(100, $fs['pct'])); ?>%"></div>
                                                        </div>
                                                        <div class="small text-muted">
                                                            <?= number_format($fs['pct'], 1, ',', '.'); ?>% terisi
                                                            · <span class="<?= $fs['kosong'] > 0 ? 'text-danger' : ''; ?>"><?= number_format($fs['kosong']); ?> kosong</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card card-outline card-info shadow-sm h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-diagram-3-fill me-1"></i>Kelengkapan per Kelompok</h5>
                            </div>
                            <div class="card-body">
                                <div class="small fw-bold text-uppercase text-muted mb-2">Field Wajib</div>
                                <?php foreach ($reqGroupStats as $g => $st): ?>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between small">
                                            <span class="fw-semibold"><?= kel_e($g); ?></span>
                                            <span><?= number_format($st['pct'], 1, ',', '.'); ?>%</span>
                                        </div>
                                        <div class="progress" style="height:7px;">
                                            <div class="progress-bar bg-<?= kel_cls($st['pct']); ?>" style="width:<?= $st['pct']; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <hr class="my-3">
                                <div class="small fw-bold text-uppercase text-muted mb-2">Data Pendukung</div>
                                <?php foreach ($optGroupStats as $g => $st): ?>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between small">
                                            <span class="fw-semibold"><?= kel_e($g); ?></span>
                                            <span><?= number_format($st['pct'], 1, ',', '.'); ?>%</span>
                                        </div>
                                        <div class="progress" style="height:7px;">
                                            <div class="progress-bar bg-secondary" style="width:<?= $st['pct']; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-outline card-primary shadow-sm">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-table me-1"></i>Daftar Siswa</h5>
                        <div class="card-tools small text-muted">
                            <?= number_format($total_filtered); ?> dari <?= number_format($total_rows); ?> siswa
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-2 align-items-end mb-3">
                            <input type="hidden" name="menu" value="kelengkapan_siswa">
                            <div class="col-lg-2 col-md-4 col-6">
                                <label class="form-label small mb-1">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="semua" <?= $f_status === 'semua' ? 'selected' : ''; ?>>Semua</option>
                                    <option value="belum" <?= $f_status === 'belum' ? 'selected' : ''; ?>>Belum Lengkap</option>
                                    <option value="lengkap" <?= $f_status === 'lengkap' ? 'selected' : ''; ?>>Lengkap (100%)</option>
                                </select>
                            </div>
                            <div class="col-lg-3 col-md-4 col-6">
                                <label class="form-label small mb-1">Kelas</label>
                                <select name="kelas" class="form-select form-select-sm">
                                    <option value="">Semua Kelas</option>
                                    <?php foreach ($kelas_list as $k): ?>
                                        <option value="<?= (int)$k['id_kelas']; ?>" <?= $f_kelas === (int)$k['id_kelas'] ? 'selected' : ''; ?>>
                                            <?= kel_e(trim($k['tingkat'] . ' ' . $k['nama_kelas'] . ($k['nama_jurusan'] ? ' (' . $k['nama_jurusan'] . ')' : ''))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-3 col-md-4 col-6">
                                <label class="form-label small mb-1">Field Wajib Kosong</label>
                                <select name="field" class="form-select form-select-sm">
                                    <option value="">Semua Field</option>
                                    <?php foreach ($required as $f): ?>
                                        <option value="<?= kel_e($f['key']); ?>" <?= $f_field === $f['key'] ? 'selected' : ''; ?>>
                                            <?= kel_e($f['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-8 col-6">
                                <label class="form-label small mb-1">Cari</label>
                                <input type="text" name="cari" class="form-control form-control-sm" placeholder="Nama / NIS / NISN" value="<?= kel_e($f_cari); ?>">
                            </div>
                            <div class="col-lg-2 col-md-4 col-12 d-flex gap-1">
                                <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel"></i> Filter</button>
                                <a href="<?= kel_e($reset_url); ?>" class="btn btn-light btn-sm" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                            </div>
                        </form>

                        <?php if ($f_field !== ''): ?>
                            <div class="mb-2 small">
                                Menampilkan siswa yang <b>belum mengisi <?= kel_e($requiredByKey[$f_field]['label']); ?></b>.
                                <a href="<?= kel_e(kel_url(array_diff_key($baseParams, ['field' => true]), 1)); ?>">Hapus filter field</a>
                            </div>
                        <?php endif; ?>

                        <?php if (!$rowsPage): ?>
                            <div class="alert alert-info mb-0"><i class="bi bi-info-circle me-1"></i>Tidak ada siswa yang cocok dengan filter.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm align-middle mb-0" style="font-size:.85rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:38px;">No</th>
                                            <th>NIS</th>
                                            <th>Nama Siswa</th>
                                            <th>Kelas</th>
                                            <th class="text-center">Wajib</th>
                                            <th style="min-width:150px;">Kelengkapan</th>
                                            <th>Field Wajib Belum Terisi</th>
                                            <th class="text-center" style="width:80px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rowsPage as $i => $r): ?>
                                            <?php
                                            $no = $offset + $i + 1;
                                            $kelas_label = trim((string)($r['kelas_tingkat'] ?? '') . ' ' . (string)($r['kelas_nama'] ?? ''));
                                            if ($kelas_label === '') $kelas_label = trim((string)($r['rombel'] ?? ''));
                                            if (!empty($r['nama_jurusan'])) $kelas_label .= ' (' . $r['nama_jurusan'] . ')';
                                            ?>
                                            <tr>
                                                <td class="text-muted"><?= $no; ?></td>
                                                <td class="font-monospace"><?= kel_e($r['nipd'] ?: '—'); ?></td>
                                                <td>
                                                    <span class="fw-semibold"><?= kel_e($r['nama']); ?></span>
                                                    <?php if (!empty($r['nisn'])): ?>
                                                        <div class="small text-muted">NISN <?= kel_e($r['nisn']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="small"><?= $kelas_label !== '' ? kel_e($kelas_label) : '<span class="text-muted">—</span>'; ?></td>
                                                <td class="text-center">
                                                    <span class="badge text-bg-<?= $r['_lengkap'] ? 'success' : 'secondary'; ?>">
                                                        <?= (int)$r['_filled']; ?>/<?= (int)$total_required; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress flex-grow-1" style="height:7px;">
                                                            <div class="progress-bar bg-<?= kel_cls($r['_pct']); ?>" style="width:<?= $r['_pct']; ?>%"></div>
                                                        </div>
                                                        <span class="small fw-semibold text-<?= kel_cls($r['_pct']); ?>" style="min-width:44px;text-align:right;">
                                                            <?= number_format($r['_pct'], 1, ',', '.'); ?>%
                                                        </span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($r['_lengkap']): ?>
                                                        <span class="badge text-bg-success"><i class="bi bi-check2"></i> Lengkap</span>
                                                    <?php else: ?>
                                                        <div class="d-flex flex-wrap gap-1">
                                                            <?php foreach ($r['_missing'] as $ml): ?>
                                                                <span class="badge text-bg-warning"><?= kel_e($ml); ?></span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center text-nowrap">
                                                    <a href="?menu=siswa_detail&amp;id=<?= (int)$r['id_siswa']; ?>" class="btn btn-info btn-sm" title="Detail"><i class="bi bi-info-circle-fill"></i></a>
                                                    <a href="?menu=siswa_edit&amp;id=<?= (int)$r['id_siswa']; ?>" class="btn btn-primary btn-sm" title="Edit"><i class="bi bi-pencil-square"></i></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                                <div class="small text-muted">
                                    Menampilkan <?= number_format($offset + 1); ?>–<?= number_format(min($offset + $per_page, $total_filtered)); ?>
                                    dari <?= number_format($total_filtered); ?> siswa
                                </div>
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?= kel_e(kel_url($baseParams, 1)); ?>">&laquo;</a>
                                    </li>
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?= kel_e(kel_url($baseParams, max(1, $page - 1))); ?>">&lsaquo;</a>
                                    </li>
                                    <li class="page-item disabled"><span class="page-link"><?= $page; ?> / <?= $pages; ?></span></li>
                                    <li class="page-item <?= $page >= $pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?= kel_e(kel_url($baseParams, min($pages, $page + 1))); ?>">&rsaquo;</a>
                                    </li>
                                    <li class="page-item <?= $page >= $pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?= kel_e(kel_url($baseParams, $pages)); ?>">&raquo;</a>
                                    </li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer small text-muted">
                        <b>16 field wajib:</b>
                        <?php foreach ($required as $f): ?>
                            <span class="badge text-bg-light border me-1 mb-1"><?= kel_e($f['label']); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>
</main>
