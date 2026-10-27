<?php
/**
 * Tagihan SPP (Keuangan) - generate 12 tagihan bulanan + daftar/filter tagihan.
 *
 * Tagihan bersifat bulanan mengikuti nama tahun ajaran (Juli..Juni).
 * Nominal diambil dari tarif tingkat + jurusan (fallback: semua jurusan).
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('koneksi.php');
require_once(__DIR__ . '/../helper_spp.php');

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

// ==========================================================
// FILTER
// ==========================================================
$f_kelas = (int)($_GET['kelas'] ?? 0);
$f_bulan = (string)($_GET['bulan'] ?? '');
if (!preg_match('/^\d{4}-\d{2}$/', $f_bulan)) {
    $f_bulan = '';
}
$f_status = (string)($_GET['status'] ?? '');
if (!in_array($f_status, ['Belum', 'Sebagian', 'Lunas'], true)) {
    $f_status = '';
}
$f_q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['hal'] ?? 1));
$per_page = 50;

$where = ["t.id_tahun_ajaran = ?"];
$types = "i";
$params = [$id_ta];

if ($f_kelas > 0) {
    $where[] = "t.id_kelas = ?";
    $types .= "i";
    $params[] = $f_kelas;
}
if ($f_bulan !== '') {
    [$fy, $fb] = array_map('intval', explode('-', $f_bulan));
    $where[] = "t.tahun = ? AND t.bulan = ?";
    $types .= "ii";
    $params[] = $fy;
    $params[] = $fb;
}
if ($f_status !== '') {
    $where[] = "t.status = ?";
    $types .= "s";
    $params[] = $f_status;
}
if ($f_q !== '') {
    $where[] = "(s.nama LIKE ? OR s.nipd LIKE ? OR s.nisn LIKE ?)";
    $types .= "sss";
    $like = '%' . $f_q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$where_sql = implode(' AND ', $where);
$from_sql = "FROM dt_spp_tagihan t
             INNER JOIN dt_siswa s ON s.id_siswa = t.id_siswa
             LEFT JOIN dt_kelas k ON k.id_kelas = t.id_kelas
             WHERE $where_sql";

// ---- Ringkasan filter ----
$sum = ['n' => 0, 'total' => 0.0];
$stmt = $koneksi->prepare("SELECT COUNT(*) AS n, IFNULL(SUM(t.total),0) AS total $from_sql");
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $sum['n'] = (int)($r['n'] ?? 0);
    $sum['total'] = (float)($r['total'] ?? 0);
}

$dibayar = 0.0;
$stmt = $koneksi->prepare("SELECT IFNULL(SUM(p.nominal),0) AS bayar
                             FROM dt_spp_pembayaran p
                            WHERE p.id_tagihan IN (SELECT t.id_tagihan $from_sql)");
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $dibayar = (float)($r['bayar'] ?? 0);
}

$menunggak = 0;
$stmt = $koneksi->prepare("SELECT COUNT(DISTINCT t.id_siswa) AS n $from_sql AND t.status <> 'Lunas'");
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $menunggak = (int)($r['n'] ?? 0);
}

$tunggakan = max(0, $sum['total'] - $dibayar);
$persen_bayar = $sum['total'] > 0 ? round($dibayar * 100 / $sum['total'], 1) : 0;

// ---- Daftar tagihan (halaman) ----
$total_page = max(1, (int)ceil($sum['n'] / $per_page));
if ($page > $total_page) {
    $page = $total_page;
}
$offset = ($page - 1) * $per_page;

$rows = [];
$sql_rows = "SELECT t.id_tagihan, t.id_siswa, t.tahun, t.bulan, t.nominal, t.potongan, t.total,
                    t.status, t.jatuh_tempo, s.nama, s.nipd,
                    IFNULL(k.nama_kelas, '') AS nama_kelas, t.tingkat
               $from_sql
             ORDER BY t.tahun DESC, t.bulan DESC, s.nama ASC
             LIMIT ? OFFSET ?";
$stmt = $koneksi->prepare($sql_rows);
if ($stmt) {
    $types_page = $types . "ii";
    $params_page = array_merge($params, [$per_page, $offset]);
    $stmt->bind_param($types_page, ...$params_page);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
}

$terbayar_map = spp_terbayar_map($koneksi, array_column($rows, 'id_tagihan'));

/** URL halaman ini dengan filter yang dipertahankan. */
function spp_tagihan_url(array $override = []): string
{
    $q = array_merge([
        'menu' => 'spp_tagihan',
        'ta' => $_GET['ta'] ?? 0,
        'kelas' => $_GET['kelas'] ?? 0,
        'bulan' => $_GET['bulan'] ?? '',
        'status' => $_GET['status'] ?? '',
        'q' => $_GET['q'] ?? '',
        'hal' => $_GET['hal'] ?? 1,
    ], $override);
    $q = array_filter($q, function ($v) {
        return $v !== '' && $v !== 0 && $v !== null;
    });
    return 'index.php?' . http_build_query($q);
}
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Tagihan SPP</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item">Keuangan</li>
                        <li class="breadcrumb-item active" aria-current="page">Tagihan SPP</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['alert']['type']); ?> alert-dismissible fade show" role="alert">
                    <?= $_SESSION['alert']['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>

            <?php if (!$ta): ?>
                <div class="alert alert-warning">Tahun ajaran belum dipilih / tidak valid.</div>
            <?php else: ?>

            <!-- ================= FILTER ================= -->
            <div class="card card-outline card-primary mb-3">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end">
                        <input type="hidden" name="menu" value="spp_tagihan">
                        <div class="col-md-3">
                            <label class="form-label fw-bold mb-1">Tahun Ajaran</label>
                            <select name="ta" class="form-select">
                                <?php foreach ($ta_list as $t): ?>
                                    <option value="<?= (int)$t['id_tahun_ajaran']; ?>" <?= ((int)$t['id_tahun_ajaran'] === $id_ta) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($t['tahun_ajaran']); ?><?= $t['status'] === 'aktif' ? ' (aktif)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold mb-1">Kelas</label>
                            <select name="kelas" class="form-select">
                                <option value="0">— Semua kelas —</option>
                                <?php foreach ($kelas_list as $k): ?>
                                    <option value="<?= (int)$k['id_kelas']; ?>" <?= ($f_kelas === (int)$k['id_kelas']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($k['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold mb-1">Bulan</label>
                            <select name="bulan" class="form-select">
                                <option value="">— Semua bulan —</option>
                                <?php foreach ($periode as $p): ?>
                                    <?php $val = sprintf('%04d-%02d', $p['tahun'], $p['bulan']); ?>
                                    <option value="<?= $val; ?>" <?= ($f_bulan === $val) ? 'selected' : ''; ?>><?= htmlspecialchars($p['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold mb-1">Status</label>
                            <select name="status" class="form-select">
                                <option value="">— Semua —</option>
                                <?php foreach (['Belum', 'Sebagian', 'Lunas'] as $st): ?>
                                    <option value="<?= $st; ?>" <?= ($f_status === $st) ? 'selected' : ''; ?>><?= $st; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold mb-1">Cari siswa</label>
                            <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($f_q); ?>" placeholder="NIS / nama">
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                            <a href="index.php?menu=spp_tagihan&ta=<?= (int)$id_ta; ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ================= RINGKASAN ================= -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="small text-muted text-uppercase">Tagihan</div>
                            <div class="h4 mb-0 fw-bold"><?= number_format($sum['n'], 0, ',', '.'); ?></div>
                            <div class="small text-muted"><?= spp_rupiah($sum['total']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="small text-muted text-uppercase">Terbayar</div>
                            <div class="h4 mb-0 fw-bold text-success"><?= spp_rupiah($dibayar); ?></div>
                            <div class="progress mt-2" style="height:6px;">
                                <div class="progress-bar bg-success" style="width:<?= $persen_bayar; ?>%"></div>
                            </div>
                            <div class="small text-muted mt-1"><?= $persen_bayar; ?>% dari tagihan</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="small text-muted text-uppercase">Tunggakan</div>
                            <div class="h4 mb-0 fw-bold text-danger"><?= spp_rupiah($tunggakan); ?></div>
                            <div class="small text-muted"><?= spp_rupiah($sum['total'] - $dibayar > 0 ? $sum['total'] - $dibayar : 0); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="small text-muted text-uppercase">Siswa Menunggak</div>
                            <div class="h4 mb-0 fw-bold"><?= (int)$menunggak; ?></div>
                            <div class="small text-muted">belum lunas pada filter ini</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= GENERATE ================= -->
            <div class="card card-outline card-success mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-magic me-1"></i> Generate Tagihan 12 Bulan</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border py-2 mb-3 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Membuat tagihan <b>bulanan</b> untuk <b><?= htmlspecialchars($periode[0]['label'] ?? '-'); ?> s/d <?= htmlspecialchars($periode[11]['label'] ?? '-'); ?></b>
                        sesuai tarif tingkat &amp; jurusan. Tagihan yang sudah ada tidak diduplikasi.
                    </div>
                    <form action="aksi/aksi_spp_tagihan.php" method="post" class="row g-2 align-items-end"
                        onsubmit="return confirm('Buat tagihan 12 bulan untuk siswa pada kelas terpilih?');">
                        <input type="hidden" name="aksi" value="generate">
                        <input type="hidden" name="id_ta" value="<?= (int)$id_ta; ?>">
                        <div class="col-md-4">
                            <label class="form-label fw-bold mb-1">Kelas</label>
                            <select name="id_kelas" class="form-select">
                                <option value="0">— Semua kelas (<?= array_sum(array_column($kelas_list, 'jumlah_siswa')); ?> siswa) —</option>
                                <?php foreach ($kelas_list as $k): ?>
                                    <option value="<?= (int)$k['id_kelas']; ?>"><?= htmlspecialchars($k['label']); ?> (<?= (int)$k['jumlah_siswa']; ?> siswa)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="perbarui" id="perbarui" value="1">
                                <label class="form-check-label" for="perbarui">
                                    Perbarui nominal tagihan lama yang belum dibayar
                                </label>
                            </div>
                            <div class="small text-muted">Berguna setelah tarif diubah.</div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <button type="submit" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Generate Tagihan</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ================= DAFTAR ================= -->
            <div class="card card-outline card-secondary">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="mb-0"><i class="bi bi-receipt me-1"></i> Daftar Tagihan</h5>
                    <span class="small text-muted">Halaman <?= $page; ?> dari <?= $total_page; ?> &middot; <?= number_format($sum['n'], 0, ',', '.'); ?> baris</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>NIS</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                    <th>Bulan</th>
                                    <th class="text-end">Nominal</th>
                                    <th class="text-end">Potongan</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Dibayar</th>
                                    <th class="text-end">Sisa</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center" style="width:150px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr><td colspan="11" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox me-1"></i>Belum ada tagihan pada filter ini. Gunakan tombol <b>Generate Tagihan</b>.
                                    </td></tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $r): ?>
                                        <?php
                                        $bayar = $terbayar_map[(int)$r['id_tagihan']] ?? 0.0;
                                        $sisa = max(0, (float)$r['total'] - $bayar);
                                        ?>
                                        <tr>
                                            <td class="small"><?= htmlspecialchars((string)$r['nipd']); ?></td>
                                            <td class="fw-semibold"><?= htmlspecialchars((string)$r['nama']); ?></td>
                                            <td class="small"><?= htmlspecialchars(trim($r['tingkat'] . ' ' . $r['nama_kelas'])); ?></td>
                                            <td class="small text-nowrap"><?= htmlspecialchars(spp_bulan_label((int)$r['tahun'], (int)$r['bulan'])); ?></td>
                                            <td class="text-end small"><?= spp_rupiah((float)$r['nominal']); ?></td>
                                            <td class="text-end small"><?= (float)$r['potongan'] > 0 ? spp_rupiah((float)$r['potongan']) : '-'; ?></td>
                                            <td class="text-end fw-semibold"><?= spp_rupiah((float)$r['total']); ?></td>
                                            <td class="text-end text-success small"><?= spp_rupiah($bayar); ?></td>
                                            <td class="text-end text-danger small"><?= spp_rupiah($sisa); ?></td>
                                            <td class="text-center"><?= spp_status_badge((string)$r['status']); ?></td>
                                            <td class="text-center text-nowrap">
                                                <?php if ((string)$r['status'] !== 'Lunas'): ?>
                                                    <a href="index.php?menu=spp_pembayaran&tagihan=<?= (int)$r['id_tagihan']; ?>"
                                                        class="btn btn-sm btn-success" title="Input pembayaran">
                                                        <i class="bi bi-cash-coin"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="index.php?menu=spp_pembayaran&tagihan=<?= (int)$r['id_tagihan']; ?>"
                                                        class="btn btn-sm btn-outline-secondary" title="Lihat pembayaran">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning btn-potongan"
                                                    title="Potongan / keringanan"
                                                    data-id="<?= (int)$r['id_tagihan']; ?>"
                                                    data-nama="<?= htmlspecialchars((string)$r['nama']); ?>"
                                                    data-bulan="<?= htmlspecialchars(spp_bulan_label((int)$r['tahun'], (int)$r['bulan'])); ?>"
                                                    data-nominal="<?= (int)$r['nominal']; ?>"
                                                    data-potongan="<?= (int)$r['potongan']; ?>">
                                                    <i class="bi bi-percent"></i>
                                                </button>
                                                <a href="aksi/aksi_spp_tagihan.php?aksi=hapus&id=<?= (int)$r['id_tagihan']; ?>&id_ta=<?= (int)$id_ta; ?>&f_kelas=<?= (int)$f_kelas; ?>&f_bulan=<?= urlencode($f_bulan); ?>&f_status=<?= urlencode($f_status); ?>&f_q=<?= urlencode($f_q); ?>"
                                                    class="btn btn-sm btn-outline-danger" title="Hapus tagihan"
                                                    onclick="return confirm('Hapus tagihan ini?');">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if ($total_page > 1): ?>
                    <div class="card-footer">
                        <nav>
                            <ul class="pagination pagination-sm mb-0 justify-content-center flex-wrap">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?= htmlspecialchars(spp_tagihan_url(['hal' => max(1, $page - 1)])); ?>">&laquo;</a>
                                </li>
                                <?php
                                $start = max(1, $page - 2);
                                $end = min($total_page, $page + 2);
                                for ($i = $start; $i <= $end; $i++):
                                ?>
                                    <li class="page-item <?= $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?= htmlspecialchars(spp_tagihan_url(['hal' => $i])); ?>"><?= $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $page >= $total_page ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?= htmlspecialchars(spp_tagihan_url(['hal' => min($total_page, $page + 1)])); ?>">&raquo;</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ================= MODAL POTONGAN ================= -->
            <div class="modal fade" id="modalPotongan" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" action="aksi/aksi_spp_tagihan.php" method="post">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-percent me-1"></i> Potongan / Keringanan</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="aksi" value="potongan">
                            <input type="hidden" name="id_tagihan" id="pot_id">
                            <input type="hidden" name="id_ta" value="<?= (int)$id_ta; ?>">
                            <input type="hidden" name="f_kelas" value="<?= (int)$f_kelas; ?>">
                            <input type="hidden" name="f_bulan" value="<?= htmlspecialchars($f_bulan); ?>">
                            <input type="hidden" name="f_status" value="<?= htmlspecialchars($f_status); ?>">
                            <input type="hidden" name="f_q" value="<?= htmlspecialchars($f_q); ?>">
                            <div class="mb-2">
                                <div class="fw-semibold" id="pot_nama">-</div>
                                <div class="small text-muted" id="pot_bulan">-</div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Nominal tagihan</label>
                                <input type="text" class="form-control" id="pot_nominal_txt" readonly>
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1 fw-bold">Potongan (Rp)</label>
                                <input type="number" min="0" step="1000" class="form-control" name="potongan" id="pot_potongan" value="0">
                                <div class="form-text">Isi 0 bila tidak ada keringanan. Total tagihan = nominal − potongan.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-warning"><i class="bi bi-save me-1"></i>Simpan</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                document.addEventListener('click', function (e) {
                    var btn = e.target.closest('.btn-potongan');
                    if (!btn) return;
                    document.getElementById('pot_id').value = btn.dataset.id;
                    document.getElementById('pot_nama').textContent = btn.dataset.nama;
                    document.getElementById('pot_bulan').textContent = btn.dataset.bulan;
                    var nominal = parseInt(btn.dataset.nominal || '0', 10);
                    document.getElementById('pot_nominal_txt').value = 'Rp ' + nominal.toLocaleString('id-ID');
                    document.getElementById('pot_potongan').value = btn.dataset.potongan || 0;
                    var modal = new bootstrap.Modal(document.getElementById('modalPotongan'));
                    modal.show();
                });
            </script>

            <?php endif; ?>
        </div>
    </div>
</main>
