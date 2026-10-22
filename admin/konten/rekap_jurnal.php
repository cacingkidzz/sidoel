<?php
/**
 * Laporan: Rekap Jurnal Mengajar per Guru (realisasi jadwal vs jurnal tercatat) — KBM-3.
 * Filter: ?bulan=YYYY-MM&guru=<id_guru> (guru opsional; 0 = semua).
 * Cetak via Print-to-PDF browser (pakai helper_laporan.php).
 */
include('koneksi.php');
require_once(__DIR__ . '/helper_laporan.php');

$sekolah  = laporan_sekolah($koneksi ?? null);
if (empty($sekolah['nama_sekolah'])) {
    $sekolah['nama_sekolah'] = 'Sekolah';
}
$kop_url  = laporan_kop_aktif($koneksi ?? null);
$kop_html = laporan_kop_html($sekolah, $kop_url);
$kepala   = laporan_kepala_sekolah($koneksi ?? null);
$footer_html = laporan_footer_html($kepala);

// ---- Tahun ajaran aktif (dari session) ----
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
$id_ta_aktif = 0;
$nama_ta_aktif = $nama_ta_sesi;
if ($nama_ta_sesi !== '' && isset($koneksi) && $koneksi instanceof mysqli && !$koneksi->connect_error) {
    $stmt = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    $stmt->bind_param("s", $nama_ta_sesi);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $ta = $res->fetch_assoc();
        $id_ta_aktif = (int)$ta['id_tahun_ajaran'];
        $nama_ta_aktif = $ta['tahun_ajaran'];
    }
    $stmt->close();
}

// ---- Bulan & rentang ----
$nama_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$bulan = (string)($_GET['bulan'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) $bulan = date('Y-m');
$awal  = $bulan . '-01';
$akhir = date('Y-m-t', strtotime($awal));
$label_bulan = ($nama_bulan[substr($bulan, 5, 2)] ?? '') . ' ' . substr($bulan, 0, 4);

// ---- Hari libur (kalender) pada bulan tsb -> tidak dihitung sebagai sesi terjadwal ----
$libur_map  = laporan_hari_libur($koneksi ?? null, $id_ta_aktif, $awal, $akhir);
$libur_list = array_keys($libur_map);

// ---- Jumlah kemunculan tiap hari (Senin..Jumat) di bulan tsb (di luar hari libur) ----
$hari_id = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
$occ = [];
$d = strtotime($awal);
$d_akhir = strtotime($akhir);
while ($d <= $d_akhir) {
    $ymd = date('Y-m-d', $d);
    if (!isset($libur_map[$ymd])) {
        $nm = $hari_id[(int)date('N', $d)] ?? '';
        if ($nm !== '') $occ[$nm] = ($occ[$nm] ?? 0) + 1;
    }
    $d = strtotime('+1 day', $d);
}

// ---- Daftar guru (dropdown) ----
$guru_list = [];
$q = $koneksi->query("SELECT id_guru, nama_guru FROM dt_guru ORDER BY nama_guru ASC");
if ($q) { while ($r = $q->fetch_assoc()) $guru_list[] = $r; }

$id_guru = isset($_GET['guru']) ? (int)$_GET['guru'] : 0;

// ---- Sesi terjadwal per guru (dari jadwal; dikali kemunculan hari) ----
$jam_minggu = [];  // [id_guru] = jumlah jam/minggu
$expected   = [];  // [id_guru] = estimasi sesi sebulan
if ($id_ta_aktif > 0) {
    $sql = "SELECT id_guru, hari, COUNT(*) AS c FROM dt_jadwal WHERE id_tahun_ajaran = ?"
         . ($id_guru > 0 ? " AND id_guru = ?" : "")
         . " GROUP BY id_guru, hari";
    $stmt = $koneksi->prepare($sql);
    if ($id_guru > 0) {
        $stmt->bind_param("ii", $id_ta_aktif, $id_guru);
    } else {
        $stmt->bind_param("i", $id_ta_aktif);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $g = (int)$r['id_guru'];
        $c = (int)$r['c'];
        $jam_minggu[$g] = ($jam_minggu[$g] ?? 0) + $c;
        $expected[$g]   = ($expected[$g] ?? 0) + $c * ($occ[$r['hari']] ?? 0);
    }
    $stmt->close();
}

// ---- Jurnal tercatat per guru per status (di luar hari libur) ----
$jurnal = [];  // [id_guru][status] = count
if ($id_ta_aktif > 0) {
    $excl = '';
    if ($libur_list) {
        $excl = " AND tanggal NOT IN ('" . implode("','", array_map([$koneksi, 'real_escape_string'], $libur_list)) . "')";
    }
    $sql = "SELECT IFNULL(id_guru,0) AS id_guru, status, COUNT(*) AS c FROM dt_jurnal
            WHERE id_tahun_ajaran = ? AND tanggal BETWEEN ? AND ?" . $excl
         . ($id_guru > 0 ? " AND id_guru = ?" : "")
         . " GROUP BY IFNULL(id_guru,0), status";
    $stmt = $koneksi->prepare($sql);
    if ($id_guru > 0) {
        $stmt->bind_param("issi", $id_ta_aktif, $awal, $akhir, $id_guru);
    } else {
        $stmt->bind_param("iss", $id_ta_aktif, $awal, $akhir);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $g = (int)$r['id_guru'];
        $jurnal[$g][$r['status']] = ($jurnal[$g][$r['status']] ?? 0) + (int)$r['c'];
    }
    $stmt->close();
}

// ---- Gabungkan id guru (jadwal + jurnal) ----
$guru_ids = [];
foreach (array_keys($jam_minggu) as $g) $guru_ids[$g] = true;
foreach (array_keys($jurnal) as $g) $guru_ids[$g] = true;
if ($id_guru > 0) $guru_ids[$id_guru] = true;

$nama_guru_map = [];
foreach ($guru_list as $gl) $nama_guru_map[(int)$gl['id_guru']] = $gl['nama_guru'];

$rows = [];
$total = ['jam_minggu' => 0, 'expected' => 0, 'jurnal' => 0, 'Mengajar' => 0, 'Tidak Hadir' => 0, 'Tugas/Kegiatan' => 0, 'Kosong' => 0];
foreach (array_keys($guru_ids) as $g) {
    $stat = [
        'Mengajar' => (int)($jurnal[$g]['Mengajar'] ?? 0),
        'Tidak Hadir' => (int)($jurnal[$g]['Tidak Hadir'] ?? 0),
        'Tugas/Kegiatan' => (int)($jurnal[$g]['Tugas/Kegiatan'] ?? 0),
        'Kosong' => (int)($jurnal[$g]['Kosong'] ?? 0),
    ];
    $jtot = array_sum($stat);
    $exp  = (int)($expected[$g] ?? 0);
    $jm   = (int)($jam_minggu[$g] ?? 0);
    $rows[] = [
        'id_guru' => $g,
        'nama'    => $nama_guru_map[$g] ?? ($g === 0 ? '(Tanpa guru)' : 'Guru #' . $g),
        'jam_minggu' => $jm,
        'expected' => $exp,
        'jurnal'   => $jtot,
        'stat'     => $stat,
        'realisasi' => $exp > 0 ? round($jtot / $exp * 100) : ($jtot > 0 ? 100 : 0),
    ];
    $total['jam_minggu'] += $jm;
    $total['expected'] += $exp;
    $total['jurnal'] += $jtot;
    foreach ($stat as $k => $v) $total[$k] += $v;
}
usort($rows, fn($a, $b) => strcmp($a['nama'], $b['nama']));
$total_realisasi = $total['expected'] > 0 ? round($total['jurnal'] / $total['expected'] * 100) : 0;
?>
<?= laporan_print_css(); ?>
<style>
@media print {
  @page { size: A4 portrait; margin: 12mm; }
  .tbl-jurnal th, .tbl-jurnal td { font-size: 10px !important; padding: 2px 4px !important; }
  .tbl-jurnal, .tbl-jurnal th, .tbl-jurnal td { border: 1px solid #999 !important; }
}
</style>

<main class="app-main">
    <div class="app-content-header no-print">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6"><h2 class="mb-0">Rekap Jurnal Mengajar</h2></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item">Pendidikan &amp; KBM</li>
                        <li class="breadcrumb-item active" aria-current="page">Rekap Jurnal Guru</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">

            <div class="card card-outline card-primary no-print">
                <div class="card-body pb-2">
                    <form method="get" class="row g-2 align-items-end">
                        <input type="hidden" name="menu" value="rekap_jurnal">
                        <div class="col-md-4">
                            <label class="form-label fw-bold mb-1">Bulan</label>
                            <input type="month" name="bulan" class="form-control" value="<?= htmlspecialchars($bulan); ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold mb-1">Guru</label>
                            <select name="guru" class="form-select" onchange="this.form.submit()">
                                <option value="0">— Semua guru —</option>
                                <?php foreach ($guru_list as $g): ?>
                                    <option value="<?= (int)$g['id_guru']; ?>" <?= ((int)$g['id_guru'] === $id_guru) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($g['nama_guru']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Tampilkan</button>
                            <button type="button" class="btn btn-success" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($id_ta_aktif <= 0): ?>
                <div class="alert alert-warning no-print">Belum ada tahun ajaran aktif.</div>
            <?php else: ?>

                <?php if ($libur_map): ?>
                    <div class="alert alert-danger py-2 no-print">
                        <i class="bi bi-cup-hot me-1"></i> Hari libur pada bulan ini <b>tidak dihitung</b> sebagai sesi terjadwal:
                        <b><?= htmlspecialchars(laporan_libur_ringkas($libur_map)); ?></b>
                    </div>
                <?php endif; ?>

                <div class="d-none d-print-block mb-2">
                    <?= $kop_html; ?>
                    <div style="text-align:center;margin:6px 0;">
                        <div style="font-weight:700;font-size:14px;">REKAP JURNAL MENGAJAR GURU</div>
                        <div style="font-size:12px;">
                            <?= $id_guru > 0 ? 'Guru: ' . htmlspecialchars($nama_guru_map[$id_guru] ?? '-') . ' &middot; ' : 'Semua Guru &middot; '; ?>
                            <?= htmlspecialchars($label_bulan); ?> &middot; TA <?= htmlspecialchars($nama_ta_aktif); ?>
                        </div>
                    </div>
                </div>

                <div class="row g-2 mb-3 no-print">
                    <div class="col-6 col-md-3">
                        <div class="card text-bg-primary"><div class="card-body py-2">
                            <div class="small">Sesi Terjadwal (bulan)</div><div class="fs-4 fw-bold"><?= $total['expected']; ?></div>
                        </div></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-bg-info"><div class="card-body py-2">
                            <div class="small">Jurnal Tercatat</div><div class="fs-4 fw-bold"><?= $total['jurnal']; ?></div>
                        </div></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-bg-success"><div class="card-body py-2">
                            <div class="small">Status Mengajar</div><div class="fs-4 fw-bold"><?= $total['Mengajar']; ?></div>
                        </div></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-bg-warning"><div class="card-body py-2">
                            <div class="small">Realisasi Jurnal</div><div class="fs-4 fw-bold"><?= $total_realisasi; ?>%</div>
                        </div></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header no-print"><h5 class="mb-0"><i class="bi bi-journal-text me-1"></i> Ringkasan per Guru — <?= htmlspecialchars($label_bulan); ?></h5></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped tbl-jurnal align-middle mb-0" style="font-size:.82rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width:24px;" class="text-center">No</th>
                                        <th style="min-width:150px;">Nama Guru</th>
                                        <th class="text-center">Jam/Minggu</th>
                                        <th class="text-center">Sesi Terjadwal</th>
                                        <th class="text-center">Jurnal Tercatat</th>
                                        <th class="text-center text-success">Mengajar</th>
                                        <th class="text-center text-warning">Tdk Hadir</th>
                                        <th class="text-center text-info">Tugas/Keg.</th>
                                        <th class="text-center text-secondary">Kosong</th>
                                        <th class="text-center">Realisasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$rows): ?>
                                        <tr><td colspan="10" class="text-center text-muted py-3">Belum ada jadwal/jurnal pada periode ini.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($rows as $i => $r): ?>
                                            <tr>
                                                <td class="text-center"><?= $i + 1 ?></td>
                                                <td><?= htmlspecialchars($r['nama']); ?></td>
                                                <td class="text-center"><?= $r['jam_minggu']; ?></td>
                                                <td class="text-center"><?= $r['expected']; ?></td>
                                                <td class="text-center fw-semibold"><?= $r['jurnal']; ?></td>
                                                <td class="text-center text-success fw-semibold"><?= $r['stat']['Mengajar']; ?></td>
                                                <td class="text-center"><?= $r['stat']['Tidak Hadir']; ?></td>
                                                <td class="text-center"><?= $r['stat']['Tugas/Kegiatan']; ?></td>
                                                <td class="text-center"><?= $r['stat']['Kosong']; ?></td>
                                                <td class="text-center fw-semibold"><?= $r['realisasi']; ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <?php if ($rows): ?>
                                <tfoot class="table-secondary fw-bold">
                                    <tr>
                                        <td colspan="2" class="text-center">TOTAL</td>
                                        <td class="text-center"><?= $total['jam_minggu']; ?></td>
                                        <td class="text-center"><?= $total['expected']; ?></td>
                                        <td class="text-center"><?= $total['jurnal']; ?></td>
                                        <td class="text-center"><?= $total['Mengajar']; ?></td>
                                        <td class="text-center"><?= $total['Tidak Hadir']; ?></td>
                                        <td class="text-center"><?= $total['Tugas/Kegiatan']; ?></td>
                                        <td class="text-center"><?= $total['Kosong']; ?></td>
                                        <td class="text-center"><?= $total_realisasi; ?>%</td>
                                    </tr>
                                </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="small text-muted mt-2">
                            <b>Sesi Terjadwal</b> = jumlah jam pada jadwal guru dikali banyaknya hari tersebut di bulan ini (di luar tanggal berstatus <b>Libur</b> pada kalender akademik).
                            <b>Realisasi</b> = Jurnal Tercatat ÷ Sesi Terjadwal × 100%.
                            Status jurnal: <span class="text-success">Mengajar</span>,
                            <span class="text-warning">Tidak Hadir</span>,
                            <span class="text-info">Tugas/Kegiatan</span>,
                            <span class="text-secondary">Kosong</span>.
                        </div>
                    </div>
                </div>

                <div class="d-none d-print-block">
                    <?= $footer_html; ?>
                </div>

            <?php endif; ?>
        </div>
    </div>
</main>
