<?php
/**
 * Pembayaran SPP (Keuangan) - input pembayaran/cicilan, riwayat, dan kwitansi.
 *
 * Parameter:
 *   ?tagihan=<id>   -> form pembayaran untuk satu tagihan + riwayatnya
 *   ?siswa=<id>     -> daftar tagihan satu siswa
 *   ?q=<NIS/nama>   -> cari siswa
 *   ?kwitansi=<id>  -> tampilan kwitansi siap cetak (Print-to-PDF)
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('koneksi.php');
require_once(__DIR__ . '/../helper_spp.php');
require_once(__DIR__ . '/helper_laporan.php');

// ==========================================================
// KWITANSI (halaman cetak)
// ==========================================================
$id_kwitansi = (int)($_GET['kwitansi'] ?? 0);
if ($id_kwitansi > 0) {
    $sql = "SELECT p.id_bayar, p.tanggal_bayar, p.nominal, p.metode, p.no_referensi, p.keterangan,
                   t.id_tagihan, t.tahun, t.bulan, t.nominal AS tag_nominal, t.potongan, t.total, t.status,
                   t.tingkat, t.id_kelas, t.id_tahun_ajaran,
                   s.id_siswa, s.nama, s.nipd, s.nisn,
                   IFNULL(k.nama_kelas, '') AS nama_kelas,
                   IFNULL(j.nama_jurusan, '') AS jurusan,
                   IFNULL(ta.tahun_ajaran, '') AS tahun_ajaran,
                   IFNULL(u.username, '') AS petugas
              FROM dt_spp_pembayaran p
              INNER JOIN dt_spp_tagihan t ON t.id_tagihan = p.id_tagihan
              INNER JOIN dt_siswa s ON s.id_siswa = p.id_siswa
              LEFT JOIN dt_kelas k ON k.id_kelas = t.id_kelas
              LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
              LEFT JOIN dt_tahun_ajar ta ON ta.id_tahun_ajaran = t.id_tahun_ajaran
              LEFT JOIN dt_user u ON u.id_user = p.id_user
             WHERE p.id_bayar = ? LIMIT 1";
    $stmt = $koneksi->prepare($sql);
    $kw = null;
    if ($stmt) {
        $stmt->bind_param('i', $id_kwitansi);
        $stmt->execute();
        $kw = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$kw) {
        echo '<div class="alert alert-danger m-3">Kwitansi tidak ditemukan.</div>';
        return;
    }

    // Total pembayaran tagihan ini
    $stmt = $koneksi->prepare("SELECT IFNULL(SUM(nominal),0) AS bayar, COUNT(*) AS n FROM dt_spp_pembayaran WHERE id_tagihan = ?");
    $total_bayar = 0.0;
    $jumlah_bayar = 0;
    if ($stmt) {
        $stmt->bind_param('i', $kw['id_tagihan']);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $total_bayar = (float)($r['bayar'] ?? 0);
        $jumlah_bayar = (int)($r['n'] ?? 0);
    }
    $sisa = max(0, (float)$kw['total'] - $total_bayar);
    $sekolah = laporan_sekolah($koneksi);
    $kop_url = laporan_kop_aktif($koneksi);
    $no_kwitansi = 'SPP/' . $kw['id_tahun_ajaran'] . '/' . str_pad((string)$kw['id_bayar'], 5, '0', STR_PAD_LEFT);
    ?>
    <?= laporan_print_css(); ?>
    <style>
        .kw-wrap { max-width: 800px; margin: 0 auto; }
        .kw-title { text-align: center; margin: 10px 0 4px; font-weight: 700; font-size: 1.05rem; letter-spacing: .5px; }
        .kw-sub { text-align: center; font-size: .78rem; color: #555; margin-bottom: 12px; }
        .kw-table td, .kw-table th { padding: 5px 8px; font-size: .86rem; }
        .kw-sign { margin-top: 26px; font-size: .86rem; }
        @media print { @page { size: A4 portrait; margin: 12mm; } }
    </style>
    <main class="app-main">
        <div class="app-content">
            <div class="container-fluid">
                <div class="kw-wrap">
                    <div class="no-print d-flex justify-content-between mb-2">
                        <a href="index.php?menu=spp_pembayaran&tagihan=<?= (int)$kw['id_tagihan']; ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Kembali
                        </a>
                        <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i>Cetak Kwitansi
                        </button>
                    </div>

                    <div class="bg-white p-3 p-md-4 border rounded">
                        <?= laporan_kop_html($sekolah, $kop_url); ?>
                        <div class="kw-title">KWITANSI PEMBAYARAN SPP</div>
                        <div class="kw-sub">No. <?= htmlspecialchars($no_kwitansi); ?></div>

                        <div class="row mb-2" style="font-size:.86rem;">
                            <div class="col-6">
                                <div><span class="text-muted">Tanggal</span> : <?= htmlspecialchars(laporan_tanggal_id(strtotime($kw['tanggal_bayar']))); ?></div>
                                <div><span class="text-muted">NIS</span> : <?= htmlspecialchars((string)$kw['nipd']); ?></div>
                                <div><span class="text-muted">Nama</span> : <b><?= htmlspecialchars((string)$kw['nama']); ?></b></div>
                                <div><span class="text-muted">Kelas</span> : <?= htmlspecialchars(trim($kw['tingkat'] . ' ' . $kw['nama_kelas']) . ($kw['jurusan'] !== '' ? ' (' . $kw['jurusan'] . ')' : '')); ?></div>
                            </div>
                            <div class="col-6">
                                <div><span class="text-muted">Tahun Ajaran</span> : <?= htmlspecialchars((string)$kw['tahun_ajaran']); ?></div>
                                <div><span class="text-muted">Pembayaran untuk</span> : <b>SPP <?= htmlspecialchars(spp_bulan_label((int)$kw['tahun'], (int)$kw['bulan'])); ?></b></div>
                                <div><span class="text-muted">Metode</span> : <?= htmlspecialchars((string)$kw['metode']); ?><?= $kw['no_referensi'] ? ' (' . htmlspecialchars((string)$kw['no_referensi']) . ')' : ''; ?></div>
                                <div><span class="text-muted">Petugas</span> : <?= htmlspecialchars($kw['petugas'] !== '' ? (string)$kw['petugas'] : '-'); ?></div>
                            </div>
                        </div>

                        <table class="table table-bordered kw-table mb-0">
                            <tbody>
                                <tr>
                                    <td style="width:55%;">Nominal SPP <?= htmlspecialchars(spp_bulan_label((int)$kw['tahun'], (int)$kw['bulan'])); ?></td>
                                    <td class="text-end"><?= spp_rupiah((float)$kw['tag_nominal']); ?></td>
                                </tr>
                                <tr>
                                    <td>Potongan / keringanan</td>
                                    <td class="text-end"><?= spp_rupiah((float)$kw['potongan']); ?></td>
                                </tr>
                                <tr class="fw-bold">
                                    <td>Total tagihan</td>
                                    <td class="text-end"><?= spp_rupiah((float)$kw['total']); ?></td>
                                </tr>
                                <tr>
                                    <td>Jumlah pembayaran ini <?= $jumlah_bayar > 1 ? '(angsuran ke-' . $jumlah_bayar . ')' : ''; ?></td>
                                    <td class="text-end fw-bold"><?= spp_rupiah((float)$kw['nominal']); ?></td>
                                </tr>
                                <tr>
                                    <td>Sudah dibayar (termasuk pembayaran ini)</td>
                                    <td class="text-end"><?= spp_rupiah($total_bayar); ?></td>
                                </tr>
                                <tr class="<?= $sisa > 0 ? 'table-warning' : 'table-success'; ?>">
                                    <td>Sisa tagihan</td>
                                    <td class="text-end fw-bold"><?= spp_rupiah($sisa); ?></td>
                                </tr>
                                <tr>
                                    <td>Status tagihan</td>
                                    <td class="text-end"><?= spp_status_badge((string)$kw['status']); ?></td>
                                </tr>
                                <?php if (trim((string)$kw['keterangan']) !== ''): ?>
                                    <tr>
                                        <td>Keterangan</td>
                                        <td class="text-end small"><?= htmlspecialchars((string)$kw['keterangan']); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <div class="row kw-sign">
                            <div class="col-6 text-center">
                                <div>Penyetor,</div>
                                <div style="height:52px;"></div>
                                <div class="fw-semibold text-decoration-underline"><?= htmlspecialchars((string)$kw['nama']); ?></div>
                            </div>
                            <div class="col-6 text-center">
                                <div>Petugas,</div>
                                <div style="height:52px;"></div>
                                <div class="fw-semibold text-decoration-underline"><?= htmlspecialchars($kw['petugas'] !== '' ? (string)$kw['petugas'] : '…………'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php
    return;
}

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

// Filter kelas (tabel pembayaran 12 bulan per kelas)
$kelas_list = spp_kelas_list($koneksi, $id_ta);
$f_kelas = (int)($_GET['kelas'] ?? 0);
if ($f_kelas > 0 && !isset($kelas_list[$f_kelas])) {
    $f_kelas = 0; // kelas bukan milik TA ini
}

// ==========================================================
// SATU TAGIHAN (form pembayaran)
// ==========================================================
$id_tagihan = (int)($_GET['tagihan'] ?? 0);
$tagihan = null;
$riwayat = [];
$terbayar = 0.0;
if ($id_tagihan > 0) {
    $sql = "SELECT t.*, s.nama, s.nipd, s.nisn,
                   IFNULL(k.nama_kelas, '') AS nama_kelas,
                   IFNULL(j.nama_jurusan, '') AS jurusan,
                   IFNULL(ta.tahun_ajaran, '') AS tahun_ajaran
              FROM dt_spp_tagihan t
              INNER JOIN dt_siswa s ON s.id_siswa = t.id_siswa
              LEFT JOIN dt_kelas k ON k.id_kelas = t.id_kelas
              LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
              LEFT JOIN dt_tahun_ajar ta ON ta.id_tahun_ajaran = t.id_tahun_ajaran
             WHERE t.id_tagihan = ? LIMIT 1";
    $stmt = $koneksi->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $id_tagihan);
        $stmt->execute();
        $tagihan = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if ($tagihan) {
        $stmt = $koneksi->prepare("SELECT id_bayar, tanggal_bayar, nominal, metode, no_referensi, keterangan
                                     FROM dt_spp_pembayaran WHERE id_tagihan = ? ORDER BY tanggal_bayar ASC, id_bayar ASC");
        if ($stmt) {
            $stmt->bind_param('i', $id_tagihan);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $riwayat[] = $r;
                $terbayar += (float)$r['nominal'];
            }
            $stmt->close();
        }
    }
}

// ==========================================================
// CARI SISWA
// ==========================================================
$f_q = trim((string)($_GET['q'] ?? ''));
$id_siswa_cari = (int)($_GET['siswa'] ?? 0);
$hasil_siswa = [];

if ($id_tagihan <= 0 && ($f_q !== '' || $id_siswa_cari > 0)) {
    $sql = "SELECT s.id_siswa, s.nama, s.nipd, s.nisn,
                   IFNULL(k.nama_kelas, '') AS nama_kelas, IFNULL(k.tingkat, '') AS tingkat,
                   IFNULL(j.nama_jurusan, '') AS jurusan
              FROM dt_siswa s
              LEFT JOIN dt_kelas_siswa ks ON ks.id_siswa = s.id_siswa AND ks.id_tahun_ajaran = ?
              LEFT JOIN dt_kelas k ON k.id_kelas = ks.id_kelas
              LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
             WHERE " . ($id_siswa_cari > 0 ? "s.id_siswa = ?" : "(s.nama LIKE ? OR s.nipd LIKE ? OR s.nisn LIKE ?)") . "
             ORDER BY s.nama ASC LIMIT 10";
    $stmt = $koneksi->prepare($sql);
    if ($stmt) {
        if ($id_siswa_cari > 0) {
            $stmt->bind_param('ii', $id_ta, $id_siswa_cari);
        } else {
            $like = '%' . $f_q . '%';
            $stmt->bind_param('isss', $id_ta, $like, $like, $like);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $hasil_siswa[] = $r;
        }
        $stmt->close();
    }
}

// Tagihan untuk siswa hasil pencarian
$tagihan_siswa = [];
if (!empty($hasil_siswa)) {
    $ids = array_map('intval', array_column($hasil_siswa, 'id_siswa'));
    $in = implode(',', $ids);
    $q2 = $koneksi->query("SELECT t.id_tagihan, t.id_siswa, t.tahun, t.bulan, t.total, t.potongan, t.nominal, t.status,
                                  IFNULL(k.nama_kelas,'') AS nama_kelas
                             FROM dt_spp_tagihan t
                             LEFT JOIN dt_kelas k ON k.id_kelas = t.id_kelas
                            WHERE t.id_tahun_ajaran = " . (int)$id_ta . " AND t.id_siswa IN ($in)
                            ORDER BY t.id_siswa ASC, t.tahun ASC, t.bulan ASC");
    if ($q2) {
        while ($r = $q2->fetch_assoc()) {
            $tagihan_siswa[(int)$r['id_siswa']][] = $r;
        }
    }
    $terbayar_cari = [];
    $semua_id = [];
    foreach ($tagihan_siswa as $list) {
        foreach ($list as $t) {
            $semua_id[] = (int)$t['id_tagihan'];
        }
    }
    $terbayar_cari = spp_terbayar_map($koneksi, $semua_id);
}

// ==========================================================
// MATRIKS KELAS (tabel siswa x 12 bulan)
// ==========================================================
$matriks = null;
if ($id_tagihan <= 0 && $f_kelas > 0) {
    $matriks = spp_matriks_kelas($koneksi, $id_ta, $f_kelas);
}

// ==========================================================
// PEMBAYARAN TERBARU
// ==========================================================
$terbaru = [];
$q3 = $koneksi->query("SELECT p.id_bayar, p.tanggal_bayar, p.nominal, p.metode, s.nama, s.nipd,
                              t.tahun, t.bulan, t.status
                         FROM dt_spp_pembayaran p
                         INNER JOIN dt_siswa s ON s.id_siswa = p.id_siswa
                         INNER JOIN dt_spp_tagihan t ON t.id_tagihan = p.id_tagihan
                        ORDER BY p.tanggal_bayar DESC, p.id_bayar DESC
                        LIMIT 15");
if ($q3) {
    while ($r = $q3->fetch_assoc()) {
        $terbaru[] = $r;
    }
}
$kwitansi_baru = (int)($_SESSION['spp_kwitansi_terakhir'] ?? 0);
unset($_SESSION['spp_kwitansi_terakhir']);
$kwitansi_multi = $_SESSION['spp_kwitansi_multi'] ?? [];
unset($_SESSION['spp_kwitansi_multi']);
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Pembayaran SPP</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item">Keuangan</li>
                        <li class="breadcrumb-item active" aria-current="page">Pembayaran SPP</li>
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

            <?php if ($kwitansi_baru > 0): ?>
                <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="bi bi-check-circle-fill me-1"></i>Pembayaran tersimpan. Kwitansi terakhir siap dicetak.</span>
                    <a href="index.php?menu=spp_pembayaran&kwitansi=<?= $kwitansi_baru; ?>" target="_blank" class="btn btn-sm btn-success">
                        <i class="bi bi-printer me-1"></i>Cetak Kwitansi
                    </a>
                </div>
            <?php endif; ?>

            <?php if (!empty($kwitansi_multi)): ?>
                <div class="alert alert-success">
                    <div class="fw-semibold mb-1"><i class="bi bi-check-circle-fill me-1"></i>Pembayaran tersimpan. Klik bulan untuk mencetak kwitansinya:</div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($kwitansi_multi as $km): ?>
                            <a href="index.php?menu=spp_pembayaran&kwitansi=<?= (int)$km['id_bayar']; ?>" target="_blank"
                               class="btn btn-sm btn-outline-success">
                                <i class="bi bi-printer me-1"></i><?= htmlspecialchars((string)$km['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$ta): ?>
                <div class="alert alert-warning">Tahun ajaran belum dipilih / tidak valid.</div>
            <?php else: ?>

            <!-- ================= CARI / FILTER ================= -->
            <div class="card card-outline card-primary mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-search me-1"></i> Cari / Filter Tagihan</h5>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end">
                        <input type="hidden" name="menu" value="spp_pembayaran">
                        <div class="col-md-2">
                            <label class="form-label fw-bold mb-1">Tahun Ajaran</label>
                            <select name="ta" class="form-select">
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
                                <option value="0">— Semua kelas / cari per siswa —</option>
                                <?php foreach ($kelas_list as $kl): ?>
                                    <option value="<?= (int)$kl['id_kelas']; ?>" <?= ($f_kelas === (int)$kl['id_kelas']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($kl['label']); ?> · <?= (int)$kl['jumlah_siswa']; ?> siswa
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold mb-1">NIS / NISN / Nama Siswa</label>
                            <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($f_q); ?>" placeholder="mis. 5924 atau nama siswa">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Cari</button>
                        </div>
                    </form>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-info-circle me-1"></i>Pilih <b>Kelas</b> untuk menampilkan tabel pembayaran 12 bulan seluruh siswa kelas tersebut.
                    </div>
                </div>
            </div>

            <!-- ================= DETAIL TAGIHAN ================= -->
            <?php if ($id_tagihan > 0 && !$tagihan): ?>
                <div class="alert alert-danger">Tagihan tidak ditemukan.</div>
            <?php elseif ($tagihan): ?>
                <?php
                $sisa = max(0, (float)$tagihan['total'] - $terbayar);
                ?>
                <div class="row g-3 mb-3">
                    <div class="col-lg-5">
                        <div class="card card-outline card-info h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-person-vcard me-1"></i> Data Tagihan</h5>
                                <?= spp_status_badge((string)$tagihan['status']); ?>
                            </div>
                            <div class="card-body">
                                <div class="mb-1 fw-bold fs-5"><?= htmlspecialchars((string)$tagihan['nama']); ?></div>
                                <div class="small text-muted mb-2">
                                    NIS <?= htmlspecialchars((string)$tagihan['nipd']); ?> &middot;
                                    <?= htmlspecialchars(trim($tagihan['tingkat'] . ' ' . $tagihan['nama_kelas']) . ($tagihan['jurusan'] !== '' ? ' (' . $tagihan['jurusan'] . ')' : '')); ?>
                                </div>
                                <table class="table table-sm mb-0">
                                    <tr><td class="text-muted">Tahun ajaran</td><td class="text-end"><?= htmlspecialchars((string)$tagihan['tahun_ajaran']); ?></td></tr>
                                    <tr><td class="text-muted">Bulan tagihan</td><td class="text-end fw-semibold"><?= htmlspecialchars(spp_bulan_label((int)$tagihan['tahun'], (int)$tagihan['bulan'])); ?></td></tr>
                                    <tr><td class="text-muted">Jatuh tempo</td><td class="text-end"><?= $tagihan['jatuh_tempo'] ? htmlspecialchars(laporan_tanggal_id(strtotime($tagihan['jatuh_tempo']))) : '-'; ?></td></tr>
                                    <tr><td class="text-muted">Nominal</td><td class="text-end"><?= spp_rupiah((float)$tagihan['nominal']); ?></td></tr>
                                    <tr><td class="text-muted">Potongan</td><td class="text-end"><?= spp_rupiah((float)$tagihan['potongan']); ?></td></tr>
                                    <tr class="fw-bold"><td>Total</td><td class="text-end"><?= spp_rupiah((float)$tagihan['total']); ?></td></tr>
                                    <tr><td class="text-muted">Sudah dibayar</td><td class="text-end text-success"><?= spp_rupiah($terbayar); ?></td></tr>
                                    <tr class="table-<?= $sisa > 0 ? 'warning' : 'success'; ?>"><td class="fw-bold">Sisa</td><td class="text-end fw-bold"><?= spp_rupiah($sisa); ?></td></tr>
                                </table>
                                <div class="mt-3 d-flex flex-wrap gap-2">
                                    <a href="index.php?menu=spp_pembayaran&ta=<?= (int)$id_ta; ?>&siswa=<?= (int)$tagihan['id_siswa']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-list-ul me-1"></i>Semua tagihan siswa
                                    </a>
                                    <a href="index.php?menu=spp_tagihan&ta=<?= (int)$id_ta; ?>&q=<?= urlencode((string)$tagihan['nipd']); ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-receipt me-1"></i>Daftar tagihan
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="card card-outline card-success h-100">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-cash-coin me-1"></i> Input Pembayaran</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($sisa <= 0): ?>
                                    <div class="alert alert-success mb-0">
                                        <i class="bi bi-check2-circle me-1"></i> Tagihan ini sudah <b>Lunas</b>. Tidak ada sisa yang perlu dibayar.
                                    </div>
                                <?php else: ?>
                                    <form action="aksi/aksi_spp_pembayaran.php" method="post" class="row g-2">
                                        <input type="hidden" name="aksi" value="simpan">
                                        <input type="hidden" name="id_tagihan" value="<?= (int)$tagihan['id_tagihan']; ?>">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold mb-1">Tanggal bayar</label>
                                            <input type="date" name="tanggal_bayar" class="form-control" value="<?= date('Y-m-d'); ?>" max="<?= date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold mb-1">Nominal (Rp)</label>
                                            <input type="number" name="nominal" class="form-control" min="1" max="<?= (int)$sisa; ?>" step="any" value="<?= (int)$sisa; ?>" required>
                                            <div class="form-text">Boleh diisi sebagian untuk angsuran (maks <?= spp_rupiah($sisa); ?>).</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold mb-1">Metode</label>
                                            <select name="metode" class="form-select">
                                                <?php foreach (['Tunai', 'Transfer', 'QRIS', 'Lainnya'] as $m): ?>
                                                    <option value="<?= $m; ?>"><?= $m; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold mb-1">No. referensi <span class="fw-normal text-muted">(opsional)</span></label>
                                            <input type="text" name="no_referensi" class="form-control" maxlength="100" placeholder="mis. no. transfer">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-bold mb-1">Keterangan <span class="fw-normal text-muted">(opsional)</span></label>
                                            <input type="text" name="keterangan" class="form-control" maxlength="255">
                                        </div>
                                        <div class="col-12 d-flex justify-content-between align-items-center mt-2">
                                            <div class="small text-muted">Sisa setelah bayar penuh: <b>Rp 0</b></div>
                                            <button type="submit" class="btn btn-success"><i class="bi bi-save me-1"></i>Simpan Pembayaran</button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-outline card-secondary">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-1"></i> Riwayat Pembayaran</h5>
                        <span class="badge bg-secondary"><?= count($riwayat); ?> transaksi</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Tanggal</th>
                                        <th class="text-end">Nominal</th>
                                        <th>Metode</th>
                                        <th>Referensi</th>
                                        <th>Keterangan</th>
                                        <th class="text-center">Kwitansi</th>
                                        <th class="text-center">Hapus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($riwayat)): ?>
                                        <tr><td colspan="8" class="text-center text-muted py-3">Belum ada pembayaran untuk tagihan ini.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($riwayat as $i => $rw): ?>
                                            <tr>
                                                <td><?= $i + 1; ?></td>
                                                <td class="small text-nowrap"><?= htmlspecialchars(laporan_tanggal_id(strtotime($rw['tanggal_bayar']))); ?></td>
                                                <td class="text-end fw-semibold"><?= spp_rupiah((float)$rw['nominal']); ?></td>
                                                <td class="small"><?= htmlspecialchars((string)$rw['metode']); ?></td>
                                                <td class="small"><?= htmlspecialchars((string)($rw['no_referensi'] ?: '-')); ?></td>
                                                <td class="small"><?= htmlspecialchars((string)($rw['keterangan'] ?: '-')); ?></td>
                                                <td class="text-center">
                                                    <a href="index.php?menu=spp_pembayaran&kwitansi=<?= (int)$rw['id_bayar']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Cetak kwitansi">
                                                        <i class="bi bi-printer"></i>
                                                    </a>
                                                </td>
                                                <td class="text-center">
                                                    <a href="aksi/aksi_spp_pembayaran.php?aksi=hapus&id=<?= (int)$rw['id_bayar']; ?>&id_tagihan=<?= (int)$tagihan['id_tagihan']; ?>"
                                                        class="btn btn-sm btn-outline-danger" title="Hapus pembayaran"
                                                        onclick="return confirm('Hapus pembayaran ini? Status tagihan akan dihitung ulang.');">
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
                </div>
            <?php endif; ?>

            <!-- ================= MATRIKS KELAS (12 BULAN) ================= -->
            <?php if ($matriks !== null): ?>
                <?php
                $kls = $kelas_list[$f_kelas];
                $periode = $ta['periode'];
                $jml_siswa = count($matriks['siswa']);
                $jml_tagihan = 0;
                foreach ($matriks['tagihan'] as $lst) {
                    $jml_tagihan += count($lst);
                }
                ?>
                <div class="card card-outline card-primary mb-3">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-1"></i> Pembayaran SPP — <?= htmlspecialchars($kls['label']); ?></h5>
                            <div class="small text-muted">TA <?= htmlspecialchars((string)$ta['nama']); ?> &middot; <?= $jml_siswa; ?> siswa &middot; <?= $jml_tagihan; ?> tagihan</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 small">
                            <span class="badge text-bg-light border">Total tagihan <b><?= spp_rupiah($matriks['total']); ?></b></span>
                            <span class="badge text-bg-light border">Terbayar <b class="text-success"><?= spp_rupiah($matriks['terbayar_total']); ?></b></span>
                            <span class="badge text-bg-light border">Tunggakan <b class="text-danger"><?= spp_rupiah($matriks['sisa_total']); ?></b></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($jml_siswa === 0): ?>
                            <div class="alert alert-warning mb-0">Kelas ini belum memiliki siswa pada tahun ajaran terpilih.</div>
                        <?php elseif ($jml_tagihan === 0): ?>
                            <div class="alert alert-warning mb-0">
                                Belum ada tagihan untuk kelas ini. Generate dulu di menu
                                <a href="index.php?menu=spp_tagihan&ta=<?= (int)$id_ta; ?>&kelas=<?= (int)$f_kelas; ?>" class="alert-link">Tagihan SPP</a>.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" id="tblSppKelas">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:40px">No</th>
                                            <th style="min-width:190px">Nama Siswa</th>
                                            <?php foreach ($periode as $p): ?>
                                                <th class="text-center" style="min-width:74px" title="<?= htmlspecialchars($p['label']); ?>">
                                                    <?= htmlspecialchars(spp_bulan_singkat((int)$p['bulan'])); ?>
                                                    <div class="fw-normal text-muted" style="font-size:.72rem"><?= (int)$p['tahun']; ?></div>
                                                </th>
                                            <?php endforeach; ?>
                                            <th class="text-end" style="min-width:100px">Sisa</th>
                                            <th class="text-center" style="width:96px">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; foreach ($matriks['siswa'] as $id_siswa => $sw): ?>
                                            <?php
                                            $list = $matriks['tagihan'][$id_siswa] ?? [];
                                            $sisa_siswa = 0.0;
                                            $bulan_belum = 0;
                                            foreach ($list as $t) {
                                                $byr = (float)($matriks['terbayar'][(int)$t['id_tagihan']] ?? 0);
                                                $sisa_t = max(0, (float)$t['total'] - $byr);
                                                $sisa_siswa += $sisa_t;
                                                if ($sisa_t > 0) {
                                                    $bulan_belum++;
                                                }
                                            }
                                            ?>
                                            <tr>
                                                <td class="text-center small"><?= $no++; ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars((string)$sw['nama']); ?></div>
                                                    <div class="small text-muted">NIS <?= htmlspecialchars((string)$sw['nipd']); ?></div>
                                                </td>
                                                <?php foreach ($periode as $p): ?>
                                                    <?php
                                                    $key = sprintf('%04d-%02d', (int)$p['tahun'], (int)$p['bulan']);
                                                    $t = $list[$key] ?? null;
                                                    if (!$t):
                                                    ?>
                                                        <td class="text-center text-muted" title="Belum digenerate">&mdash;</td>
                                                    <?php else:
                                                        $id_tag = (int)$t['id_tagihan'];
                                                        $byr = (float)($matriks['terbayar'][$id_tag] ?? 0);
                                                        $sisa_t = max(0, (float)$t['total'] - $byr);
                                                        $cls = $sisa_t <= 0 ? 'table-success' : ($byr > 0 ? 'table-warning' : 'table-danger');
                                                    ?>
                                                        <td class="text-center p-0 <?= $cls; ?>">
                                                            <a href="index.php?menu=spp_pembayaran&tagihan=<?= $id_tag; ?>"
                                                               class="d-block py-1 px-1 text-decoration-none text-reset"
                                                               title="<?= htmlspecialchars($p['label']); ?>">
                                                                <span class="small"><?= $sisa_t <= 0 ? 'Lunas' : spp_rupiah($sisa_t, ''); ?></span>
                                                            </a>
                                                        </td>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                <td class="text-end small <?= $sisa_siswa > 0 ? 'text-danger fw-semibold' : 'text-success'; ?>">
                                                    <?= spp_rupiah($sisa_siswa, ''); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($bulan_belum > 0): ?>
                                                        <button type="button" class="btn btn-sm btn-success"
                                                                data-bs-toggle="modal" data-bs-target="#modalBayar"
                                                                data-siswa="<?= (int)$id_siswa; ?>">
                                                            <i class="bi bi-cash-coin me-1"></i>Bayar
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="badge bg-success"><i class="bi bi-check2 me-1"></i>Lunas</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="small text-muted mt-2">
                                <span class="badge bg-success">&nbsp;&nbsp;&nbsp;</span> Lunas &middot;
                                <span class="badge bg-warning text-dark">&nbsp;&nbsp;&nbsp;</span> Sebagian &middot;
                                <span class="badge bg-danger">&nbsp;&nbsp;&nbsp;</span> Belum &middot;
                                <span class="text-muted">&mdash;</span> belum digenerate.
                                Angka pada sel = <b>sisa tagihan</b> bulan tsb; klik sel untuk membuka rincian bulan.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($jml_siswa > 0 && $jml_tagihan > 0): ?>
                    <?php
                    // Data untuk modal bayar bersama: hanya bulan yang belum lunas.
                    $js_data = [];
                    foreach ($matriks['siswa'] as $id_siswa => $sw) {
                        $list = $matriks['tagihan'][$id_siswa] ?? [];
                        $bulan = [];
                        foreach ($periode as $p) {
                            $key = sprintf('%04d-%02d', (int)$p['tahun'], (int)$p['bulan']);
                            $t = $list[$key] ?? null;
                            if (!$t) {
                                continue;
                            }
                            $id_tag = (int)$t['id_tagihan'];
                            $byr = (float)($matriks['terbayar'][$id_tag] ?? 0);
                            $sisa_t = round(max(0, (float)$t['total'] - $byr), 2);
                            if ($sisa_t <= 0.005) {
                                continue;
                            }
                            $bulan[] = [
                                'id' => $id_tag,
                                'label' => (string)$p['label'],
                                'total' => (float)$t['total'],
                                'bayar' => $byr,
                                'sisa' => $sisa_t,
                            ];
                        }
                        if (!$bulan) {
                            continue;
                        }
                        $js_data[(int)$id_siswa] = [
                            'id' => (int)$id_siswa,
                            'nama' => (string)$sw['nama'],
                            'nipd' => (string)$sw['nipd'],
                            'bulan' => $bulan,
                        ];
                    }
                    ?>
                    <div class="modal fade" id="modalBayar" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                            <form class="modal-content" id="formBayar" action="aksi/aksi_spp_pembayaran.php" method="post">
                                <input type="hidden" name="aksi" value="bayar_bulanan">
                                <input type="hidden" name="id_siswa" id="mbIdSiswa" value="0">
                                <input type="hidden" name="ta" value="<?= (int)$id_ta; ?>">
                                <input type="hidden" name="kelas" value="<?= (int)$f_kelas; ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="bi bi-cash-coin me-1"></i>Bayar SPP — <span id="mbNama">-</span>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="small text-muted mb-2">
                                        NIS <span id="mbNis">-</span> &middot; <?= htmlspecialchars($kls['label']); ?> &middot;
                                        Centang bulan yang dibayar. Nominal otomatis = sisa tagihan bulan tsb (bisa diubah untuk angsuran).
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="text-center" style="width:40px">
                                                        <input type="checkbox" class="form-check-input" id="mbSemua" title="Pilih semua bulan yang belum lunas">
                                                    </th>
                                                    <th>Bulan</th>
                                                    <th class="text-end">Total</th>
                                                    <th class="text-end">Dibayar</th>
                                                    <th class="text-end">Sisa</th>
                                                    <th class="text-end" style="width:150px">Nominal bayar</th>
                                                </tr>
                                            </thead>
                                            <tbody id="mbBody"></tbody>
                                            <tfoot>
                                                <tr class="fw-bold">
                                                    <td colspan="5" class="text-end">Total dibayar</td>
                                                    <td class="text-end" id="mbTotal">Rp 0</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <div class="row g-2 mt-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold mb-1">Tanggal bayar</label>
                                            <input type="date" name="tanggal_bayar" class="form-control form-control-sm"
                                                   value="<?= date('Y-m-d'); ?>" max="<?= date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold mb-1">Metode</label>
                                            <select name="metode" class="form-select form-select-sm">
                                                <?php foreach (['Tunai', 'Transfer', 'QRIS', 'Lainnya'] as $m): ?>
                                                    <option value="<?= $m; ?>"><?= $m; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold mb-1">No. referensi <span class="fw-normal text-muted">(opsional)</span></label>
                                            <input type="text" name="no_referensi" class="form-control form-control-sm" maxlength="100">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-bold mb-1">Keterangan <span class="fw-normal text-muted">(opsional)</span></label>
                                            <input type="text" name="keterangan" class="form-control form-control-sm" maxlength="255">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-save me-1"></i>Simpan Pembayaran
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <script>
                        (function () {
                            var DATA = <?= json_encode($js_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                            var modalEl = document.getElementById('modalBayar');
                            if (!modalEl) { return; }
                            var form = document.getElementById('formBayar');
                            var body = document.getElementById('mbBody');
                            var totalEl = document.getElementById('mbTotal');
                            var semuaEl = document.getElementById('mbSemua');
                            var namaEl = document.getElementById('mbNama');
                            var nisEl = document.getElementById('mbNis');
                            var idEl = document.getElementById('mbIdSiswa');

                            function rupiah(n) {
                                return 'Rp ' + Math.round(n).toLocaleString('id-ID');
                            }

                            function hitung() {
                                var total = 0;
                                body.querySelectorAll('.spp-cek').forEach(function (cb) {
                                    var tr = cb.closest('tr');
                                    var inp = tr ? tr.querySelector('.spp-nominal') : null;
                                    if (cb.checked) {
                                        if (inp) { inp.disabled = false; }
                                        total += inp ? (parseFloat(inp.value) || 0) : 0;
                                    } else if (inp) {
                                        inp.disabled = true;
                                    }
                                });
                                if (totalEl) { totalEl.textContent = rupiah(total); }
                            }

                            function buatBaris(b) {
                                var tr = document.createElement('tr');

                                var tdCek = document.createElement('td');
                                tdCek.className = 'text-center';
                                var cb = document.createElement('input');
                                cb.type = 'checkbox';
                                cb.className = 'form-check-input spp-cek';
                                cb.name = 'bulan[]';
                                cb.value = b.id;
                                cb.addEventListener('change', hitung);
                                tdCek.appendChild(cb);

                                var tdLabel = document.createElement('td');
                                tdLabel.className = 'small text-nowrap';
                                tdLabel.textContent = b.label;

                                var tdTotal = document.createElement('td');
                                tdTotal.className = 'text-end small text-nowrap';
                                tdTotal.textContent = rupiah(b.total);

                                var tdBayar = document.createElement('td');
                                tdBayar.className = 'text-end small text-nowrap text-success';
                                tdBayar.textContent = rupiah(b.bayar);

                                var tdSisa = document.createElement('td');
                                tdSisa.className = 'text-end small text-nowrap text-danger';
                                tdSisa.textContent = rupiah(b.sisa);

                                var tdNom = document.createElement('td');
                                tdNom.className = 'text-end';
                                var inp = document.createElement('input');
                                inp.type = 'number';
                                inp.className = 'form-control form-control-sm text-end spp-nominal';
                                inp.name = 'nominal[' + b.id + ']';
                                inp.value = Math.round(b.sisa);
                                inp.min = 1;
                                inp.max = Math.round(b.sisa);
                                inp.step = 'any';
                                inp.disabled = true;
                                inp.addEventListener('input', hitung);
                                tdNom.appendChild(inp);

                                tr.appendChild(tdCek);
                                tr.appendChild(tdLabel);
                                tr.appendChild(tdTotal);
                                tr.appendChild(tdBayar);
                                tr.appendChild(tdSisa);
                                tr.appendChild(tdNom);
                                return tr;
                            }

                            modalEl.addEventListener('show.bs.modal', function (ev) {
                                var btn = ev.relatedTarget;
                                var id = btn ? btn.getAttribute('data-siswa') : '';
                                var d = DATA[id];
                                if (!d) { return; }
                                idEl.value = d.id;
                                namaEl.textContent = d.nama;
                                nisEl.textContent = d.nipd;
                                body.innerHTML = '';
                                d.bulan.forEach(function (b) { body.appendChild(buatBaris(b)); });
                                semuaEl.checked = false;
                                hitung();
                            });

                            if (semuaEl) {
                                semuaEl.addEventListener('change', function () {
                                    body.querySelectorAll('.spp-cek').forEach(function (cb) {
                                        cb.checked = semuaEl.checked;
                                    });
                                    hitung();
                                });
                            }

                            form.addEventListener('submit', function (ev) {
                                if (body.querySelectorAll('.spp-cek:checked').length === 0) {
                                    ev.preventDefault();
                                    alert('Pilih minimal satu bulan yang akan dibayar.');
                                }
                            });
                        })();
                    </script>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ================= HASIL PENCARIAN ================= -->
            <?php if ($id_tagihan <= 0 && $f_kelas <= 0 && ($f_q !== '' || $id_siswa_cari > 0)): ?>
                <?php if (empty($hasil_siswa)): ?>
                    <div class="alert alert-warning"><i class="bi bi-search me-1"></i>Tidak ada siswa yang cocok dengan pencarian.</div>
                <?php else: ?>
                    <?php foreach ($hasil_siswa as $sw): ?>
                        <?php $id_sw = (int)$sw['id_siswa']; $list = $tagihan_siswa[$id_sw] ?? []; ?>
                        <div class="card card-outline card-primary mb-3">
                            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <span class="fw-bold"><?= htmlspecialchars((string)$sw['nama']); ?></span>
                                    <span class="text-muted small ms-2">NIS <?= htmlspecialchars((string)$sw['nipd']); ?> &middot;
                                        <?= htmlspecialchars(trim($sw['tingkat'] . ' ' . $sw['nama_kelas'])); ?></span>
                                </div>
                                <span class="badge bg-secondary"><?= count($list); ?> tagihan</span>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($list)): ?>
                                    <div class="p-3 text-muted small"><i class="bi bi-inbox me-1"></i>Belum ada tagihan pada tahun ajaran ini. Generate dulu di menu <a href="index.php?menu=spp_tagihan&ta=<?= (int)$id_ta; ?>">Tagihan SPP</a>.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Bulan</th>
                                                    <th class="text-end">Total</th>
                                                    <th class="text-end">Dibayar</th>
                                                    <th class="text-end">Sisa</th>
                                                    <th class="text-center">Status</th>
                                                    <th class="text-center">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($list as $t): ?>
                                                    <?php
                                                    $byr = $terbayar_cari[(int)$t['id_tagihan']] ?? 0.0;
                                                    $ss = max(0, (float)$t['total'] - $byr);
                                                    ?>
                                                    <tr>
                                                        <td class="small text-nowrap"><?= htmlspecialchars(spp_bulan_label((int)$t['tahun'], (int)$t['bulan'])); ?></td>
                                                        <td class="text-end small"><?= spp_rupiah((float)$t['total']); ?></td>
                                                        <td class="text-end small text-success"><?= spp_rupiah($byr); ?></td>
                                                        <td class="text-end small text-danger"><?= spp_rupiah($ss); ?></td>
                                                        <td class="text-center"><?= spp_status_badge((string)$t['status']); ?></td>
                                                        <td class="text-center">
                                                            <a href="index.php?menu=spp_pembayaran&tagihan=<?= (int)$t['id_tagihan']; ?>"
                                                                class="btn btn-sm <?= (string)$t['status'] === 'Lunas' ? 'btn-outline-secondary' : 'btn-success'; ?>">
                                                                <i class="bi <?= (string)$t['status'] === 'Lunas' ? 'bi-eye' : 'bi-cash-coin'; ?> me-1"></i>
                                                                <?= (string)$t['status'] === 'Lunas' ? 'Lihat' : 'Bayar'; ?>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ================= PEMBAYARAN TERBARU ================= -->
            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock me-1"></i> Pembayaran Terbaru</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>NIS</th>
                                    <th>Nama</th>
                                    <th>Untuk</th>
                                    <th class="text-end">Nominal</th>
                                    <th>Metode</th>
                                    <th class="text-center">Kwitansi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($terbaru)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-3">Belum ada pembayaran tercatat.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($terbaru as $p): ?>
                                        <tr>
                                            <td class="small text-nowrap"><?= htmlspecialchars(laporan_tanggal_id(strtotime($p['tanggal_bayar']))); ?></td>
                                            <td class="small"><?= htmlspecialchars((string)$p['nipd']); ?></td>
                                            <td class="small fw-semibold"><?= htmlspecialchars((string)$p['nama']); ?></td>
                                            <td class="small">SPP <?= htmlspecialchars(spp_bulan_label((int)$p['tahun'], (int)$p['bulan'])); ?></td>
                                            <td class="text-end fw-semibold"><?= spp_rupiah((float)$p['nominal']); ?></td>
                                            <td class="small"><?= htmlspecialchars((string)$p['metode']); ?></td>
                                            <td class="text-center">
                                                <a href="index.php?menu=spp_pembayaran&kwitansi=<?= (int)$p['id_bayar']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>
</main>
