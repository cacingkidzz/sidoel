<?php
/**
 * Portal Siswa — Informasi Pembayaran SPP (read-only).
 * Siswa hanya melihat tagihan & pembayaran miliknya sendiri
 * (id_siswa dari session). Tidak ada aksi tulis di halaman ini.
 */
require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/../admin/helper_spp.php';
siswa_wajib_login();

$profil = siswa_profil_sekolah($koneksi);
$id_siswa = (int)$_SESSION['siswa']['id_siswa'];
$s = siswa_data($koneksi, $id_siswa);
if (!$s) {
    unset($_SESSION['siswa']);
    header('Location: login.php');
    exit;
}

$id_ta_siswa = (int)$s['id_tahun_ajaran'];
$kelas = siswa_kelas($koneksi, $id_siswa, $id_ta_siswa);
$kelas_label = siswa_kelas_label($kelas);
$ta_label = siswa_ta($koneksi, $id_ta_siswa);

/* ==========================================================
 * TAHUN AJARAN YANG PUNYA TAGIHAN UNTUK SISWA INI
 * ========================================================== */
$pilihan_ta = [];   // id_ta => ['nama','n','total','bayar','sisa']
$stmt = $koneksi->prepare(
    "SELECT t.id_tahun_ajaran, ta.tahun_ajaran,
            COUNT(*) AS n,
            IFNULL(SUM(t.total), 0) AS total,
            IFNULL(SUM((SELECT IFNULL(SUM(p.nominal), 0)
                          FROM dt_spp_pembayaran p
                         WHERE p.id_tagihan = t.id_tagihan)), 0) AS bayar
       FROM dt_spp_tagihan t
       INNER JOIN dt_tahun_ajar ta ON ta.id_tahun_ajaran = t.id_tahun_ajaran
      WHERE t.id_siswa = ?
      GROUP BY t.id_tahun_ajaran, ta.tahun_ajaran
      ORDER BY ta.tahun_ajaran DESC"
);
if ($stmt) {
    $stmt->bind_param('i', $id_siswa);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $total = (float)$r['total'];
        $bayar = (float)$r['bayar'];
        $pilihan_ta[(int)$r['id_tahun_ajaran']] = [
            'nama'  => (string)$r['tahun_ajaran'],
            'n'     => (int)$r['n'],
            'total' => $total,
            'bayar' => $bayar,
            'sisa'  => max(0, $total - $bayar),
        ];
    }
    $stmt->close();
}

/* TA yang ditampilkan: ?ta= (divalidasi) → TA siswa → TA terbaru. */
$id_ta_lihat = (int)($_GET['ta'] ?? 0);
if (!isset($pilihan_ta[$id_ta_lihat])) {
    $id_ta_lihat = isset($pilihan_ta[$id_ta_siswa]) ? $id_ta_siswa : (int)(array_key_first($pilihan_ta) ?? 0);
}
$ta_info = $id_ta_lihat > 0 ? spp_ta_info($koneksi, $id_ta_lihat) : null;

/* ==========================================================
 * TAGIHAN + PEMBAYARAN TA TERPILIH
 * ========================================================== */
$tagihan = [];        // "YYYY-MM" => baris tagihan
$bayar_map = [];      // id_tagihan => total dibayar
$total_ta = 0.0;
$bayar_ta = 0.0;
$sisa_ta = 0.0;
$bulan_lunas = 0;
$bulan_tagih = 0;

if ($ta_info) {
    $stmt = $koneksi->prepare(
        "SELECT id_tagihan, tahun, bulan, nominal, potongan, total, jatuh_tempo, status
           FROM dt_spp_tagihan
          WHERE id_siswa = ? AND id_tahun_ajaran = ?
          ORDER BY tahun ASC, bulan ASC"
    );
    if ($stmt) {
        $stmt->bind_param('ii', $id_siswa, $id_ta_lihat);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $tagihan[sprintf('%04d-%02d', (int)$r['tahun'], (int)$r['bulan'])] = $r;
        }
        $stmt->close();
    }

    $ids = [];
    foreach ($tagihan as $t) $ids[] = (int)$t['id_tagihan'];
    if ($ids) $bayar_map = spp_terbayar_map($koneksi, $ids);

    foreach ($tagihan as $t) {
        $tt = (float)$t['total'];
        $tb = (float)($bayar_map[(int)$t['id_tagihan']] ?? 0);
        $total_ta += $tt;
        $bayar_ta += $tb;
        $sisa_ta += max(0, $tt - $tb);
        $bulan_tagih++;
        if ($tt - $tb <= 0.005) $bulan_lunas++;
    }
}
$pct_bayar = $total_ta > 0 ? min(100, round($bayar_ta / $total_ta * 100, 1)) : 0.0;
$status_ta = $total_ta <= 0 ? '' : spp_status_dari($total_ta, $bayar_ta);

/* Riwayat pembayaran pada TA terpilih. */
$riwayat = [];
if ($ta_info) {
    $stmt = $koneksi->prepare(
        "SELECT p.id_bayar, p.tanggal_bayar, p.nominal, p.metode, p.no_referensi, p.keterangan,
                t.tahun, t.bulan
           FROM dt_spp_pembayaran p
           INNER JOIN dt_spp_tagihan t ON t.id_tagihan = p.id_tagihan
          WHERE p.id_siswa = ? AND t.id_tahun_ajaran = ?
          ORDER BY p.tanggal_bayar DESC, p.id_bayar DESC"
    );
    if ($stmt) {
        $stmt->bind_param('ii', $id_siswa, $id_ta_lihat);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $riwayat[] = $r;
        $stmt->close();
    }
}

/* Tunggakan dari tahun ajaran sebelumnya (tetap tampil walau sudah naik kelas). */
$lain = [];
$lain_total = 0.0;
if ($ta_info) {
    $lain_all = spp_tunggakan_lain($koneksi, $id_ta_lihat, [$id_siswa], true);
    $lain = $lain_all['items'][$id_siswa] ?? [];
    $lain_total = (float)($lain_all['siswa'][$id_siswa]['sisa'] ?? 0);
}

$judul = 'Informasi Pembayaran';
$active = 'bayar';
$siswa = $s;
require __DIR__ . '/_header.php';
?>

<style>
    .ss-kpi { border-radius: .7rem; padding: .55rem .6rem; }
    .ss-kpi .lbl { font-size: .64rem; text-transform: uppercase; letter-spacing: .03em; color: #6b7280; }
    .ss-kpi .val { font-size: .95rem; font-weight: 700; line-height: 1.2; }
    .tbl-bayar th, .tbl-bayar td { font-size: .78rem; padding: .4rem .35rem; vertical-align: middle; }
    .tbl-bayar thead th { font-size: .68rem; text-transform: uppercase; letter-spacing: .02em; color: #6b7280; }
    @media print {
        .ss-topbar, .ss-bottomnav, .no-print { display: none !important; }
        body { background: #fff !important; padding-bottom: 0 !important; }
        .container { max-width: 100% !important; }
        .ss-card { box-shadow: none !important; border: 1px solid #dee2e6 !important; }
        .tbl-bayar th, .tbl-bayar td { font-size: .72rem; }
    }
</style>

<div class="card ss-card mb-2">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div class="ss-section-title"><i class="bi bi-cash-coin me-1"></i>Informasi Pembayaran SPP</div>
            <button type="button" class="btn btn-sm btn-outline-secondary no-print" onclick="window.print()">
                <i class="bi bi-printer"></i>
            </button>
        </div>

        <div class="row row-cols-2 g-2 mb-2">
            <div class="col"><div class="ss-label">Nama</div><div class="ss-value"><?= siswa_esc($s['nama']); ?></div></div>
            <div class="col"><div class="ss-label">NIS</div><div class="ss-value"><?= siswa_esc($s['nipd'] ?: '-'); ?></div></div>
            <div class="col"><div class="ss-label">Kelas</div><div class="ss-value"><?= siswa_esc($kelas_label); ?></div></div>
            <div class="col"><div class="ss-label">Tahun Ajaran</div><div class="ss-value"><?= siswa_esc($ta_label); ?></div></div>
        </div>

        <?php if (count($pilihan_ta) > 1) : ?>
            <label class="form-label mb-1" for="pilih-ta">Tahun ajaran tagihan</label>
            <select id="pilih-ta" class="form-select form-select-sm mb-2"
                    onchange="if(this.value){location.href='pembayaran.php?ta='+this.value;}">
                <?php foreach ($pilihan_ta as $idp => $tp) : ?>
                    <option value="<?= (int)$idp; ?>" <?= (int)$idp === $id_ta_lihat ? 'selected' : ''; ?>>
                        <?= siswa_esc($tp['nama']); ?><?= $tp['sisa'] > 0.005 ? ' — sisa ' . spp_rupiah($tp['sisa']) : ' — lunas'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </div>
</div>

<?php if (!$ta_info) : ?>
    <div class="card ss-card mb-2">
        <div class="card-body text-center py-4">
            <i class="bi bi-inbox text-muted" style="font-size:2rem;"></i>
            <div class="fw-semibold mt-2">Belum ada tagihan SPP</div>
            <div class="small text-muted">Tagihan SPP untuk Anda belum diterbitkan sekolah. Silakan hubungi bagian keuangan/TU bila ada pertanyaan.</div>
        </div>
    </div>
<?php else : ?>

    <!-- ============ RINGKASAN ============ -->
    <div class="card ss-card mb-2">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <span class="fw-semibold" style="font-size:.85rem;">Ringkasan TA <?= siswa_esc($ta_info['nama']); ?></span>
            <?= $status_ta !== '' ? spp_status_badge($status_ta) : '<span class="badge bg-secondary">Belum ada tagihan</span>'; ?>
        </div>
        <div class="card-body py-2">
            <div class="row row-cols-3 g-2">
                <div class="col">
                    <div class="ss-kpi bg-light">
                        <div class="lbl">Tagihan</div>
                        <div class="val"><?= spp_rupiah($total_ta, ''); ?></div>
                    </div>
                </div>
                <div class="col">
                    <div class="ss-kpi bg-success-subtle">
                        <div class="lbl">Dibayar</div>
                        <div class="val text-success"><?= spp_rupiah($bayar_ta, ''); ?></div>
                    </div>
                </div>
                <div class="col">
                    <div class="ss-kpi bg-danger-subtle">
                        <div class="lbl">Sisa</div>
                        <div class="val text-danger"><?= spp_rupiah($sisa_ta, ''); ?></div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2 mb-1">
                <div class="small text-muted">Terbayar <?= number_format($pct_bayar, 1, ',', '.'); ?>%</div>
                <div class="small text-muted"><?= (int)$bulan_lunas; ?>/<?= (int)$bulan_tagih; ?> bulan lunas</div>
            </div>
            <div class="progress" style="height:8px;">
                <div class="progress-bar bg-<?= $pct_bayar >= 100 ? 'success' : ($pct_bayar > 0 ? 'warning' : 'danger'); ?>"
                     style="width:<?= max(0, min(100, $pct_bayar)); ?>%"></div>
            </div>
            <?php if ($sisa_ta > 0.005) : ?>
                <div class="alert alert-warning py-2 px-3 mt-2 mb-0 small">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Masih ada <b><?= spp_rupiah($sisa_ta); ?></b> untuk <?= (int)($bulan_tagih - $bulan_lunas); ?> bulan.
                    Pembayaran dilakukan di bagian keuangan sekolah.
                </div>
            <?php else : ?>
                <div class="alert alert-success py-2 px-3 mt-2 mb-0 small">
                    <i class="bi bi-check2-circle me-1"></i>Semua tagihan tahun ajaran ini sudah lunas. Terima kasih.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============ RINCIAN 12 BULAN ============ -->
    <div class="card ss-card mb-2">
        <div class="card-header bg-white py-2 fw-semibold" style="font-size:.85rem;">
            <i class="bi bi-calendar3 me-1 text-primary"></i>Rincian 12 Bulan (Juli &ndash; Juni)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover tbl-bayar mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Bulan</th>
                            <th class="text-end">Tagihan</th>
                            <th class="text-end">Dibayar</th>
                            <th class="text-end">Sisa</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ta_info['periode'] as $pr) :
                            $key = sprintf('%04d-%02d', (int)$pr['tahun'], (int)$pr['bulan']);
                            $t = $tagihan[$key] ?? null;
                            if ($t) {
                                $tt = (float)$t['total'];
                                $tb = (float)($bayar_map[(int)$t['id_tagihan']] ?? 0);
                                $sisa = max(0, $tt - $tb);
                                $st = spp_status_dari($tt, $tb);
                            }
                            ?>
                            <tr>
                                <td class="text-nowrap"><?= siswa_esc(spp_bulan_singkat((int)$pr['bulan']) . ' ' . (int)$pr['tahun']); ?></td>
                                <?php if ($t) : ?>
                                    <td class="text-end text-nowrap"><?= spp_rupiah($tt, ''); ?></td>
                                    <td class="text-end text-nowrap text-success"><?= $tb > 0.005 ? spp_rupiah($tb, '') : '&ndash;'; ?></td>
                                    <td class="text-end text-nowrap <?= $sisa > 0.005 ? 'text-danger fw-semibold' : 'text-muted'; ?>">
                                        <?= $sisa > 0.005 ? spp_rupiah($sisa, '') : '&ndash;'; ?>
                                    </td>
                                    <td class="text-center"><?= spp_status_badge($st); ?></td>
                                <?php else : ?>
                                    <td class="text-end text-muted" colspan="3"><span class="small">belum digenerate</span></td>
                                    <td class="text-center"><span class="badge bg-secondary">&ndash;</span></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td class="small">TOTAL</td>
                            <td class="text-end text-nowrap small"><?= spp_rupiah($total_ta, ''); ?></td>
                            <td class="text-end text-nowrap small text-success"><?= spp_rupiah($bayar_ta, ''); ?></td>
                            <td class="text-end text-nowrap small text-danger"><?= spp_rupiah($sisa_ta, ''); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- ============ TUNGGAKAN TA SEBELUMNYA ============ -->
    <?php if ($lain) : ?>
        <div class="card ss-card mb-2 border-warning">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold" style="font-size:.85rem;">
                    <i class="bi bi-clock-history me-1 text-warning"></i>Tunggakan Tahun Ajaran Sebelumnya
                </span>
                <span class="badge bg-warning text-dark"><?= spp_rupiah($lain_total); ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm tbl-bayar mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Bulan</th>
                                <th>Tahun Ajaran</th>
                                <th>Kelas Saat Itu</th>
                                <th class="text-end">Sisa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lain as $li) : ?>
                                <tr>
                                    <td class="text-nowrap"><?= siswa_esc($li['label']); ?></td>
                                    <td class="text-nowrap small"><?= siswa_esc($li['nama_ta']); ?></td>
                                    <td class="text-nowrap small text-muted"><?= siswa_esc($li['kelas'] ?: '-'); ?></td>
                                    <td class="text-end text-nowrap text-danger fw-semibold"><?= spp_rupiah($li['sisa'], ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="small text-muted px-3 py-2" style="font-size:.72rem;">
                    <i class="bi bi-info-circle me-1"></i>Tunggakan dari tahun ajaran sebelumnya tetap menjadi kewajiban
                    dan dapat dibayarkan bersama tagihan tahun ini.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============ RIWAYAT PEMBAYARAN ============ -->
    <div class="card ss-card mb-2">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <span class="fw-semibold" style="font-size:.85rem;">
                <i class="bi bi-receipt me-1 text-primary"></i>Riwayat Pembayaran
            </span>
            <span class="badge bg-light text-dark"><?= count($riwayat); ?> transaksi</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($riwayat)) : ?>
                <div class="p-3 text-muted small"><i class="bi bi-inbox me-1"></i>Belum ada pembayaran pada tahun ajaran ini.</div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-sm tbl-bayar mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Bulan</th>
                                <th>Metode</th>
                                <th class="text-end">Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayat as $rw) : ?>
                                <tr>
                                    <td class="text-nowrap small"><?= siswa_esc(siswa_tanggal_id($rw['tanggal_bayar'])); ?></td>
                                    <td class="text-nowrap small"><?= siswa_esc(spp_bulan_singkat((int)$rw['bulan']) . ' ' . (int)$rw['tahun']); ?></td>
                                    <td class="small">
                                        <?= siswa_esc($rw['metode']); ?>
                                        <?php if (!empty($rw['no_referensi'])) : ?>
                                            <div class="text-muted" style="font-size:.68rem;"><?= siswa_esc($rw['no_referensi']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap small fw-semibold text-success"><?= spp_rupiah($rw['nominal'], ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td colspan="3" class="small">TOTAL DIBAYAR</td>
                                <td class="text-end text-nowrap small text-success"><?= spp_rupiah($bayar_ta, ''); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="alert alert-light border small text-muted mb-0">
        <i class="bi bi-info-circle me-1 text-primary"></i>
        Data pembayaran diperbarui oleh bagian keuangan sekolah. Bila ada selisih atau ingin mencetak bukti pembayaran,
        hubungi TU/keuangan
        <?php if (!empty($profil['telp_sekolah'])) : ?> di <b><?= siswa_esc($profil['telp_sekolah']); ?></b><?php endif; ?>.
    </div>

<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
