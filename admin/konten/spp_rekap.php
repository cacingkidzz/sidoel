<?php
/**
 * Rekap & Tunggakan SPP (Keuangan).
 *
 * Read-only: rekap penerimaan per bulan, per kelas, dan daftar tunggakan siswa.
 * Cetak: Print-to-PDF (A4 portrait) ber-kop + footer (helper_laporan.php).
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('koneksi.php');
require_once(__DIR__ . '/../helper_spp.php');
require_once(__DIR__ . '/helper_laporan.php');

// ==========================================================
// TAHUN AJARAN
// ==========================================================
$ta_list = [];
$q = $koneksi->query("SELECT id_tahun_ajaran, tahun_ajaran, status FROM dt_tahun_ajar ORDER BY tahun_ajaran DESC");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $ta_list[] = $r;
    }
}

$id_ta = (int)($_GET['ta'] ?? 0);
if ($id_ta <= 0) {
    $nama_sesi = (string)($_SESSION['tahun_ajaran_aktif'] ?? '');
    foreach ($ta_list as $t) {
        if ($t['tahun_ajaran'] === $nama_sesi) {
            $id_ta = (int)$t['id_tahun_ajaran'];
            break;
        }
    }
}
if ($id_ta <= 0) {
    $id_ta = spp_ta_aktif_id($koneksi);
}
if ($id_ta <= 0 && !empty($ta_list)) {
    $id_ta = (int)$ta_list[0]['id_tahun_ajaran'];
}

$ta = spp_ta_info($koneksi, $id_ta);
$periode = $ta['periode'] ?? spp_ta_periode('');
$kelas_list = spp_kelas_list($koneksi, $id_ta);

$f_kelas = (int)($_GET['kelas'] ?? 0);
$label_kelas = $f_kelas > 0 && isset($kelas_list[$f_kelas]) ? $kelas_list[$f_kelas]['label'] : 'Semua Kelas';

// ==========================================================
// AMBIL TAGIHAN + PEMBAYARAN
// ==========================================================
$rows = [];
if ($id_ta > 0) {
    $sql = "SELECT t.id_tagihan, t.id_siswa, t.id_kelas, t.tingkat, t.tahun, t.bulan,
                   t.nominal, t.potongan, t.total, t.status,
                   s.nama, s.nipd,
                   IFNULL(k.nama_kelas, '') AS nama_kelas,
                   IFNULL((SELECT SUM(p.nominal) FROM dt_spp_pembayaran p WHERE p.id_tagihan = t.id_tagihan), 0) AS bayar
              FROM dt_spp_tagihan t
              INNER JOIN dt_siswa s ON s.id_siswa = t.id_siswa
              LEFT JOIN dt_kelas k ON k.id_kelas = t.id_kelas
             WHERE t.id_tahun_ajaran = ?";
    if ($f_kelas > 0) {
        $sql .= " AND t.id_kelas = ?";
    }
    $sql .= " ORDER BY t.tahun ASC, t.bulan ASC, s.nama ASC";

    $stmt = $koneksi->prepare($sql);
    if ($stmt) {
        if ($f_kelas > 0) {
            $stmt->bind_param('ii', $id_ta, $f_kelas);
        } else {
            $stmt->bind_param('i', $id_ta);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();
    }
}

// ---- Agregasi ----
$per_bulan = [];
foreach ($periode as $p) {
    $key = sprintf('%04d-%02d', $p['tahun'], $p['bulan']);
    $per_bulan[$key] = ['label' => $p['label'], 'n' => 0, 'total' => 0.0, 'bayar' => 0.0, 'siswa' => []];
}

$per_siswa = [];
$per_kelas = [];
$grand = ['n' => 0, 'total' => 0.0, 'bayar' => 0.0, 'siswa' => []];

foreach ($rows as $r) {
    $key = sprintf('%04d-%02d', (int)$r['tahun'], (int)$r['bulan']);
    if (!isset($per_bulan[$key])) {
        $per_bulan[$key] = ['label' => spp_bulan_label((int)$r['tahun'], (int)$r['bulan']), 'n' => 0, 'total' => 0.0, 'bayar' => 0.0, 'siswa' => []];
    }
    $total = (float)$r['total'];
    $bayar = (float)$r['bayar'];

    $per_bulan[$key]['n']++;
    $per_bulan[$key]['total'] += $total;
    $per_bulan[$key]['bayar'] += $bayar;
    $per_bulan[$key]['siswa'][(int)$r['id_siswa']] = true;

    $grand['n']++;
    $grand['total'] += $total;
    $grand['bayar'] += $bayar;
    $grand['siswa'][(int)$r['id_siswa']] = true;

    $id_s = (int)$r['id_siswa'];
    if (!isset($per_siswa[$id_s])) {
        $per_siswa[$id_s] = [
            'nama' => (string)$r['nama'],
            'nipd' => (string)$r['nipd'],
            'kelas' => trim($r['tingkat'] . ' ' . $r['nama_kelas']),
            'bulan' => 0,
            'total' => 0.0,
            'bayar' => 0.0,
            'belum' => [],
            'rincian' => [],
        ];
    }
    $per_siswa[$id_s]['bulan']++;
    $per_siswa[$id_s]['total'] += $total;
    $per_siswa[$id_s]['bayar'] += $bayar;
    $per_siswa[$id_s]['rincian'][$key] = ['total' => $total, 'bayar' => $bayar];
    if ($total - $bayar > 0.005) {
        $per_siswa[$id_s]['belum'][] = spp_bulan_label((int)$r['tahun'], (int)$r['bulan']);
    }

    $id_k = (int)$r['id_kelas'];
    if (!isset($per_kelas[$id_k])) {
        $per_kelas[$id_k] = [
            'label' => trim($r['tingkat'] . ' ' . $r['nama_kelas']) ?: ('Kelas #' . $id_k),
            'n' => 0,
            'total' => 0.0,
            'bayar' => 0.0,
            'siswa' => [],
        ];
    }
    $per_kelas[$id_k]['n']++;
    $per_kelas[$id_k]['total'] += $total;
    $per_kelas[$id_k]['bayar'] += $bayar;
    $per_kelas[$id_k]['siswa'][$id_s] = true;
}

// ==========================================================
// TUNGGAKAN TAHUN AJARAN SEBELUMNYA
// ==========================================================
// `dt_spp_tagihan.id_kelas` + `id_tahun_ajaran` adalah snapshot saat generate,
// jadi tagihan kelas X tidak muncul di rekap TA XI/XII bila hanya difilter TA.
$kelas_roster = [];
$id_scope = array_keys($per_siswa);
if ($f_kelas > 0) {
    foreach (spp_roster($koneksi, $id_ta, $f_kelas) as $rs) {
        $id = (int)$rs['id_siswa'];
        $id_scope[$id] = $id;
        $kelas_roster[$id] = trim($rs['tingkat'] . ' ' . $rs['nama_kelas']);
    }
}
$lain_ta = ['items' => [], 'siswa' => [], 'total' => 0.0];
if ($id_ta > 0) {
    // Tanpa filter kelas = semua siswa (agar tunggakan TA lama tidak terlewat).
    $lain_ta = spp_tunggakan_lain($koneksi, $id_ta, array_values($id_scope), true, ($f_kelas <= 0));
}
$lain_total = (float)$lain_ta['total'];
$lain_n_total = 0;
foreach ($lain_ta['siswa'] as $ls) {
    $lain_n_total += (int)$ls['n'];
}

// Urutkan tunggakan terbesar (TA ini + TA sebelumnya)
$tunggakan_list = [];
foreach ($per_siswa as $id_s => $sw) {
    $sisa = $sw['total'] - $sw['bayar'];
    $ls = $lain_ta['siswa'][$id_s] ?? null;
    $sw['lain_sisa'] = $ls ? (float)$ls['sisa'] : 0.0;
    $sw['lain_n'] = $ls ? (int)$ls['n'] : 0;
    if ($sisa > 0.005 || $sw['lain_sisa'] > 0.005) {
        $sw['id_siswa'] = $id_s;
        $sw['sisa'] = $sisa;
        $tunggakan_list[] = $sw;
    }
}

// Siswa yang hanya punya tunggakan TA sebelumnya (tidak punya tagihan di TA ini).
foreach ($lain_ta['siswa'] as $id_s => $ls) {
    if (isset($per_siswa[$id_s])) {
        continue;
    }
    $it = $lain_ta['items'][$id_s][0] ?? null;
    $tunggakan_list[] = [
        'id_siswa' => $id_s,
        'nama' => $it ? (string)$it['nama'] : ('Siswa #' . $id_s),
        'nipd' => $it ? (string)$it['nipd'] : '',
        'kelas' => $kelas_roster[$id_s] ?? '',
        'bulan' => 0,
        'total' => 0.0,
        'bayar' => 0.0,
        'belum' => [],
        'rincian' => [],
        'sisa' => 0.0,
        'lain_sisa' => (float)$ls['sisa'],
        'lain_n' => (int)$ls['n'],
    ];
}

usort($tunggakan_list, function ($a, $b) {
    $ta_s = $a['sisa'] + $a['lain_sisa'];
    $tb_s = $b['sisa'] + $b['lain_sisa'];
    return $tb_s <=> $ta_s;
});

$grand_sisa = max(0, $grand['total'] - $grand['bayar']);
$persen = $grand['total'] > 0 ? round($grand['bayar'] * 100 / $grand['total'], 1) : 0;
$jumlah_siswa = count($grand['siswa']);
$jumlah_menunggak = count($tunggakan_list);
$total_belum_bulan = 0;
foreach ($tunggakan_list as $t) {
    $total_belum_bulan += count($t['belum']);
}

// Cetak
$sekolah = laporan_sekolah($koneksi);
$kop_url = laporan_kop_aktif($koneksi);
$kepala = laporan_kepala_sekolah($koneksi);
$footer_html = laporan_footer_html($kepala);
?>
<?= laporan_print_css(); ?>
<style id="spp-rekap-css">
    .spp-tbl-tunggakan td, .spp-tbl-tunggakan th { padding: 2px 3px; }
    .spp-tbl-tunggakan thead th { font-size: .78rem; }
    .spp-tbl-tunggakan .spp-bulan { width: 34px; }
    @media print {
        @page { size: A4 landscape; margin: 8mm; }
        .spp-tbl-tunggakan { font-size: 7.5pt; table-layout: fixed; width: 100%; }
        .spp-tbl-tunggakan th, .spp-tbl-tunggakan td { padding: 1px 2px !important; line-height: 1.2; }
        .spp-tbl-tunggakan thead th { font-size: 7pt; }
        .spp-tbl-tunggakan tbody td { font-size: 7.2pt; }
        .spp-tbl-tunggakan .badge { font-size: 6.5pt; padding: .1em .3em; }
        .spp-tbl-tunggakan tr { page-break-inside: avoid; break-inside: avoid; }
        .spp-tbl-tunggakan thead { display: table-header-group; }
    }
</style>
<main class="app-main">
    <div class="app-content-header no-print">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Rekap &amp; Tunggakan SPP</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item">Keuangan</li>
                        <li class="breadcrumb-item active" aria-current="page">Rekap &amp; Tunggakan</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['alert']['type']); ?> alert-dismissible fade show no-print" role="alert">
                    <?= $_SESSION['alert']['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>

            <?php if (!$ta): ?>
                <div class="alert alert-warning">Tahun ajaran belum dipilih / tidak valid.</div>
            <?php else: ?>

            <div class="card card-outline card-primary mb-3 no-print">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end">
                        <input type="hidden" name="menu" value="spp_rekap">
                        <div class="col-md-4">
                            <label class="form-label fw-bold mb-1">Tahun Ajaran</label>
                            <select name="ta" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($ta_list as $t): ?>
                                    <option value="<?= (int)$t['id_tahun_ajaran']; ?>" <?= ((int)$t['id_tahun_ajaran'] === $id_ta) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($t['tahun_ajaran']); ?><?= $t['status'] === 'aktif' ? ' (aktif)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold mb-1">Kelas</label>
                            <select name="kelas" class="form-select" onchange="this.form.submit()">
                                <option value="0">— Semua kelas —</option>
                                <?php foreach ($kelas_list as $k): ?>
                                    <option value="<?= (int)$k['id_kelas']; ?>" <?= ($f_kelas === (int)$k['id_kelas']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($k['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <button type="button" class="btn btn-primary" onclick="window.print()">
                                <i class="bi bi-printer me-1"></i>Cetak / PDF
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ================= RINGKASAN ================= -->
            <div class="row g-3 mb-3 no-print">
                <div class="col-6 col-lg">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="small text-muted text-uppercase">Total Tagihan</div>
                            <div class="h5 mb-0 fw-bold"><?= spp_rupiah($grand['total']); ?></div>
                            <div class="small text-muted"><?= (int)$grand['n']; ?> tagihan &middot; <?= (int)$jumlah_siswa; ?> siswa</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="small text-muted text-uppercase">Terbayar</div>
                            <div class="h5 mb-0 fw-bold text-success"><?= spp_rupiah($grand['bayar']); ?></div>
                            <div class="progress mt-2" style="height:6px;">
                                <div class="progress-bar bg-success" style="width:<?= $persen; ?>%"></div>
                            </div>
                            <div class="small text-muted mt-1"><?= $persen; ?>%</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="small text-muted text-uppercase">Tunggakan</div>
                            <div class="h5 mb-0 fw-bold text-danger"><?= spp_rupiah($grand_sisa); ?></div>
                            <div class="small text-muted"><?= (int)$total_belum_bulan; ?> tagihan belum lunas</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="small text-muted text-uppercase">Tunggakan TA Lalu</div>
                            <div class="h5 mb-0 fw-bold text-warning-emphasis"><?= spp_rupiah($lain_total); ?></div>
                            <div class="small text-muted">
                                <?= count($lain_ta['siswa']); ?> siswa &middot; <?= (int)$lain_n_total; ?> tagihan
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="small text-muted text-uppercase">Siswa Menunggak</div>
                            <div class="h5 mb-0 fw-bold"><?= (int)$jumlah_menunggak; ?></div>
                            <div class="small text-muted">dari <?= (int)$jumlah_siswa; ?> siswa</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= KOP (CETAK) ================= -->
            <div class="d-none d-print-block mb-2">
                <?= laporan_kop_html($sekolah, $kop_url); ?>
                <div class="text-center fw-bold mt-2" style="font-size:1.05rem;">REKAP PEMBAYARAN SPP</div>
                <div class="text-center small">
                    Tahun Ajaran <?= htmlspecialchars($ta['nama']); ?> &middot; <?= htmlspecialchars($label_kelas); ?>
                    &middot; Dicetak: <?= htmlspecialchars(laporan_tanggal_id()); ?>
                </div>
            </div>

            <!-- ================= PER BULAN ================= -->
            <div class="card card-outline card-primary mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calendar3 me-1"></i> Rekap Penerimaan per Bulan</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Bulan</th>
                                    <th class="text-center">Tagihan</th>
                                    <th class="text-center">Siswa</th>
                                    <th class="text-end">Total Tagihan</th>
                                    <th class="text-end">Terbayar</th>
                                    <th class="text-end">Tunggakan</th>
                                    <th class="text-center" style="width:120px;">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($per_bulan as $b): ?>
                                    <?php
                                    $sisa = max(0, $b['total'] - $b['bayar']);
                                    $pc = $b['total'] > 0 ? round($b['bayar'] * 100 / $b['total'], 1) : 0;
                                    ?>
                                    <tr>
                                        <td class="fw-semibold text-nowrap"><?= htmlspecialchars($b['label']); ?></td>
                                        <td class="text-center"><?= (int)$b['n']; ?></td>
                                        <td class="text-center"><?= count($b['siswa']); ?></td>
                                        <td class="text-end"><?= spp_rupiah($b['total']); ?></td>
                                        <td class="text-end text-success"><?= spp_rupiah($b['bayar']); ?></td>
                                        <td class="text-end <?= $sisa > 0 ? 'text-danger' : 'text-muted'; ?>"><?= spp_rupiah($sisa); ?></td>
                                        <td class="text-center">
                                            <div class="progress" style="height:6px;">
                                                <div class="progress-bar bg-<?= $pc >= 100 ? 'success' : ($pc > 0 ? 'warning' : 'danger'); ?>" style="width:<?= $pc; ?>%"></div>
                                            </div>
                                            <small><?= $pc; ?>%</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td>TOTAL</td>
                                    <td class="text-center"><?= (int)$grand['n']; ?></td>
                                    <td class="text-center"><?= (int)$jumlah_siswa; ?></td>
                                    <td class="text-end"><?= spp_rupiah($grand['total']); ?></td>
                                    <td class="text-end text-success"><?= spp_rupiah($grand['bayar']); ?></td>
                                    <td class="text-end text-danger"><?= spp_rupiah($grand_sisa); ?></td>
                                    <td class="text-center"><?= $persen; ?>%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($f_kelas <= 0 && !empty($per_kelas)): ?>
                <!-- ================= PER KELAS ================= -->
                <div class="card card-outline card-secondary mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-houses me-1"></i> Rekap per Kelas</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Kelas</th>
                                        <th class="text-center">Siswa</th>
                                        <th class="text-center">Tagihan</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Terbayar</th>
                                        <th class="text-end">Tunggakan</th>
                                        <th class="text-center" style="width:110px;">%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    ksort($per_kelas);
                                    foreach ($per_kelas as $k):
                                        $sisa = max(0, $k['total'] - $k['bayar']);
                                        $pc = $k['total'] > 0 ? round($k['bayar'] * 100 / $k['total'], 1) : 0;
                                    ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($k['label']); ?></td>
                                            <td class="text-center"><?= count($k['siswa']); ?></td>
                                            <td class="text-center"><?= (int)$k['n']; ?></td>
                                            <td class="text-end"><?= spp_rupiah($k['total']); ?></td>
                                            <td class="text-end text-success"><?= spp_rupiah($k['bayar']); ?></td>
                                            <td class="text-end <?= $sisa > 0 ? 'text-danger' : 'text-muted'; ?>"><?= spp_rupiah($sisa); ?></td>
                                            <td class="text-center"><?= $pc; ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ================= TUNGGAKAN: RINCIAN 12 BULAN ================= -->
            <?php
            $periode_label = '';
            if (!empty($periode)) {
                $awal = $periode[0];
                $akhir = $periode[count($periode) - 1];
                $periode_label = $awal['label'] . ' – ' . $akhir['label'];
            }
            // No + NIS + Nama + Kelas + Tagihan + Blm + 12 bulan + Total + Dibayar + Tunggakan + Tunggakan Lalu + Aksi
            $kolom_total = 11 + count($periode);
            ?>
            <div class="card card-outline card-danger mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-1 text-danger"></i> Daftar Tunggakan Siswa per Bulan</h5>
                        <div class="small text-muted">
                            Periode <?= htmlspecialchars($periode_label); ?> &middot; <?= htmlspecialchars($label_kelas); ?>
                            &middot; termasuk tunggakan tahun ajaran sebelumnya
                            &middot; urut tunggakan terbesar
                        </div>
                    </div>
                    <span class="badge bg-danger"><?= (int)$jumlah_menunggak; ?> siswa</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0 spp-tbl-tunggakan" id="tblTunggakanBulan">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:28px">No</th>
                                    <th style="width:50px">NIS</th>
                                    <th style="width:130px">Nama Siswa</th>
                                    <th style="width:54px">Kelas</th>
                                    <th class="text-center" style="width:40px" title="Jumlah tagihan">Tagihan</th>
                                    <th class="text-center" style="width:40px" title="Jumlah bulan belum lunas">Blm</th>
                                    <?php foreach ($periode as $p): ?>
                                        <th class="text-center spp-bulan" style="width:34px" title="<?= htmlspecialchars($p['label']); ?>">
                                            <?= htmlspecialchars(spp_bulan_singkat((int)$p['bulan'])); ?>
                                            <div class="fw-normal text-muted" style="font-size:.6rem;line-height:1"><?= substr((string)$p['tahun'], 2); ?></div>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="text-end" style="width:72px">Total</th>
                                    <th class="text-end" style="width:70px">Dibayar</th>
                                    <th class="text-end" style="width:76px">Tunggakan</th>
                                    <th class="text-end" style="width:76px" title="Tunggakan dari tahun ajaran sebelumnya (kelas saat itu)">Tunggakan Lalu</th>
                                    <th class="text-center no-print" style="width:44px">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tunggakan_list)): ?>
                                    <tr>
                                        <td colspan="<?= (int)$kolom_total; ?>" class="text-center text-muted py-4">
                                            <i class="bi bi-check2-circle me-1 text-success"></i>Tidak ada tunggakan pada filter ini.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tunggakan_list as $i => $t): ?>
                                        <tr>
                                            <td class="text-center small"><?= $i + 1; ?></td>
                                            <td class="small"><?= htmlspecialchars($t['nipd']); ?></td>
                                            <td class="small fw-semibold"><?= htmlspecialchars($t['nama']); ?></td>
                                            <td class="small"><?= $t['kelas'] !== '' ? htmlspecialchars($t['kelas']) : '<span class="text-muted">&ndash;</span>'; ?></td>
                                            <td class="text-center small"><?= (int)$t['bulan']; ?></td>
                                            <td class="text-center small"><?= count($t['belum']) > 0 ? '<span class="badge bg-danger">' . count($t['belum']) . '</span>' : '<span class="text-muted">0</span>'; ?></td>
                                            <?php foreach ($periode as $p):
                                                $key = sprintf('%04d-%02d', (int)$p['tahun'], (int)$p['bulan']);
                                                $rr = $t['rincian'][$key] ?? null;
                                                if (!$rr) {
                                                    echo '<td class="text-center small text-muted" title="Belum digenerate">&ndash;</td>';
                                                    continue;
                                                }
                                                $sisa_m = (float)$rr['total'] - (float)$rr['bayar'];
                                                if ($sisa_m <= 0.005) {
                                                    $cls = 'table-success';
                                                    $txt = 'L';
                                                    $tip = 'Lunas';
                                                } elseif ((float)$rr['bayar'] > 0) {
                                                    $cls = 'table-warning';
                                                    $txt = 'S';
                                                    $tip = 'Sebagian, sisa ' . spp_rupiah($sisa_m);
                                                } else {
                                                    $cls = 'table-danger';
                                                    $txt = 'B';
                                                    $tip = 'Menunggak ' . spp_rupiah($sisa_m);
                                                }
                                                echo '<td class="text-center small ' . $cls . '" title="' . htmlspecialchars($p['label'] . ' — ' . $tip) . '">' . $txt . '</td>';
                                            endforeach; ?>
                                            <td class="text-end small"><?= spp_rupiah($t['total']); ?></td>
                                            <td class="text-end small text-success"><?= spp_rupiah($t['bayar']); ?></td>
                                            <td class="text-end small fw-bold text-danger"><?= spp_rupiah($t['sisa']); ?></td>
                                            <td class="text-end small">
                                                <?php if ($t['lain_sisa'] > 0.005): ?>
                                                    <span class="fw-bold text-warning-emphasis"><?= spp_rupiah($t['lain_sisa']); ?></span>
                                                    <span class="badge bg-warning text-dark" title="Jumlah tagihan tahun ajaran sebelumnya"><?= (int)$t['lain_n']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">&ndash;</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center no-print">
                                                <a href="index.php?menu=spp_pembayaran&ta=<?= (int)$id_ta; ?>&siswa=<?= (int)$t['id_siswa']; ?>"
                                                    class="btn btn-sm btn-outline-success" title="Lihat / bayar tagihan">
                                                    <i class="bi bi-cash-coin"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted px-2 py-2">
                        Keterangan: <span class="badge bg-success">L</span> Lunas &middot;
                        <span class="badge bg-warning text-dark">S</span> Sebagian / angsuran &middot;
                        <span class="badge bg-danger">B</span> Belum dibayar (menunggak) &middot;
                        <span class="text-muted">&ndash;</span> tagihan belum digenerate &middot;
                        kolom <b>Tunggakan Lalu</b> = sisa tagihan dari tahun ajaran sebelumnya (kelas saat tagihan dibuat),
                        <span class="text-muted">&ndash;</span> bila tidak ada.
                    </div>
                </div>
            </div>

            <div class="d-none d-print-block mt-3">
                <?= $footer_html; ?>
            </div>

            <?php endif; ?>
        </div>
    </div>
</main>
