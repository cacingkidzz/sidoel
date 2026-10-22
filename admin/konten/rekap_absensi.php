<?php
/**
 * Laporan: Rekap Absensi Kelas (matriks siswa x tanggal) — KBM-3.
 * Filter: ?kelas=<id_kelas>&bulan=YYYY-MM (default bulan berjalan).
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

// ---- Daftar kelas (TA aktif) ----
$kelas_list = [];
if ($id_ta_aktif > 0) {
    $stmt = $koneksi->prepare("SELECT k.id_kelas, k.nama_kelas, k.tingkat, j.nama_jurusan
                               FROM dt_kelas k
                               LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                               WHERE k.id_tahun_ajaran = ?
                               ORDER BY k.tingkat ASC, k.nama_kelas ASC");
    $stmt->bind_param("i", $id_ta_aktif);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $kelas_list[] = $r;
    $stmt->close();
}

$id_kelas = isset($_GET['kelas']) ? (int)$_GET['kelas'] : 0;
$kelas_valid = false;
foreach ($kelas_list as $kls) {
    if ((int)$kls['id_kelas'] === $id_kelas) { $kelas_valid = true; break; }
}
if (!$kelas_valid) {
    $id_kelas = $kelas_list ? (int)$kelas_list[0]['id_kelas'] : 0;
}

// ---- Bulan ----
$nama_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$bulan = (string)($_GET['bulan'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) $bulan = date('Y-m');
$awal  = $bulan . '-01';
$akhir = date('Y-m-t', strtotime($awal));
$label_bulan = ($nama_bulan[substr($bulan, 5, 2)] ?? '') . ' ' . substr($bulan, 0, 4);

// ---- Hari libur (kalender) pada bulan tsb ----
$libur_map  = laporan_hari_libur($koneksi ?? null, $id_ta_aktif, $awal, $akhir);
$libur_list = array_keys($libur_map);
$libur_in   = $libur_list ? "'" . implode("','", array_map([$koneksi, 'real_escape_string'], $libur_list)) . "'" : '';

// ---- Info kelas + wali ----
$info_kelas = ['nama_kelas' => '-', 'tingkat' => '', 'jurusan' => '', 'wali' => ''];
foreach ($kelas_list as $kls) {
    if ((int)$kls['id_kelas'] === $id_kelas) {
        $info_kelas['nama_kelas'] = $kls['nama_kelas'];
        $info_kelas['tingkat'] = $kls['tingkat'];
        $info_kelas['jurusan'] = $kls['nama_jurusan'] ?? '';
        break;
    }
}
if ($id_kelas > 0) {
    $stmt = $koneksi->prepare("SELECT g.nama_guru FROM dt_wali_kelas wk
                               INNER JOIN dt_guru g ON g.id_guru = wk.id_guru
                               WHERE wk.id_kelas = ? AND wk.id_tahun_ajaran = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ii", $id_kelas, $id_ta_aktif);
        $stmt->execute();
        $rw = $stmt->get_result()->fetch_assoc();
        if ($rw) $info_kelas['wali'] = $rw['nama_guru'];
        $stmt->close();
    }
}

// ---- Matriks absensi harian ----
$mat = [];   // mat[id_siswa][tanggal] = status
$tanggal_set = [];
if ($id_kelas > 0 && $id_ta_aktif > 0) {
    $stmt = $koneksi->prepare("SELECT id_siswa, tanggal, status
                               FROM dt_absensi
                               WHERE id_kelas = ? AND id_tahun_ajaran = ? AND tanggal BETWEEN ? AND ?"
                               . ($libur_in ? " AND tanggal NOT IN ($libur_in)" : ""));
    $stmt->bind_param('iiss', $id_kelas, $id_ta_aktif, $awal, $akhir);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $sid = (int)$r['id_siswa']; $tgl = $r['tanggal']; $stt = $r['status'];
        $mat[$sid][$tgl] = $stt;
        $tanggal_set[$tgl] = true;
    }
    $stmt->close();
}
ksort($tanggal_set);
$tanggal_list = array_keys($tanggal_set);

// Total ketidakhadiran (S+I+A) pada periode
$total_absen = 0;
foreach ($mat as $days) {
    foreach ($days as $stt) {
        if (in_array($stt, ['S', 'I', 'A'], true)) $total_absen++;
    }
}

// ---- Daftar siswa kelas ----
$siswa = [];
if ($id_kelas > 0 && $id_ta_aktif > 0) {
    $stmt = $koneksi->prepare("SELECT s.id_siswa, s.nama, s.jk, s.nisn
                               FROM dt_kelas_siswa ks
                               INNER JOIN dt_siswa s ON s.id_siswa = ks.id_siswa
                               WHERE ks.id_kelas = ? AND ks.id_tahun_ajaran = ?
                               ORDER BY s.nama ASC");
    $stmt->bind_param('ii', $id_kelas, $id_ta_aktif);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $siswa[] = $r;
    $stmt->close();
}

$warna = ['H' => 'success', 'S' => 'info', 'I' => 'warning', 'A' => 'danger'];
?>
<?= laporan_print_css(); ?>
<style>
@media print {
  @page { size: A4 landscape; margin: 8mm; }
  .tbl-rekap th, .tbl-rekap td { font-size: 8px !important; padding: 1px 2px !important; }
  .tbl-rekap { border-collapse: collapse; }
  .tbl-rekap, .tbl-rekap th, .tbl-rekap td { border: 1px solid #999 !important; }
}
.tbl-rekap td.st, .tbl-rekap td.st .badge { line-height: 1; }
.tbl-rekap td.st .badge { font-size: .62rem; padding: .18em .34em; }
</style>

<main class="app-main">
    <div class="app-content-header no-print">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6"><h2 class="mb-0">Rekap Absensi Kelas</h2></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item">Pendidikan &amp; KBM</li>
                        <li class="breadcrumb-item active" aria-current="page">Rekap Absensi</li>
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
                        <input type="hidden" name="menu" value="rekap_absensi">
                        <div class="col-md-5">
                            <label class="form-label fw-bold mb-1">Kelas (TA <?= htmlspecialchars($nama_ta_aktif ?: '-'); ?>)</label>
                            <select name="kelas" class="form-select" onchange="this.form.submit()">
                                <?php if (!$kelas_list): ?>
                                    <option value="">— Tidak ada kelas —</option>
                                <?php endif; ?>
                                <?php foreach ($kelas_list as $kls): ?>
                                    <option value="<?= (int)$kls['id_kelas']; ?>" <?= ((int)$kls['id_kelas'] === $id_kelas) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars(trim($kls['tingkat'] . ' ' . $kls['nama_kelas']) . ($kls['nama_jurusan'] ? ' - ' . $kls['nama_jurusan'] : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold mb-1">Bulan</label>
                            <input type="month" name="bulan" class="form-control" value="<?= htmlspecialchars($bulan); ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Tampilkan</button>
                            <button type="button" class="btn btn-success" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($id_ta_aktif <= 0 || $id_kelas <= 0): ?>
                <div class="alert alert-warning no-print">Belum ada tahun ajaran aktif atau kelas yang dipilih.</div>
            <?php else: ?>

                <?php if ($libur_map): ?>
                    <div class="alert alert-danger py-2 no-print">
                        <i class="bi bi-cup-hot me-1"></i> Hari libur pada bulan ini <b>tidak dihitung</b> sebagai sesi:
                        <b><?= htmlspecialchars(laporan_libur_ringkas($libur_map)); ?></b>
                    </div>
                <?php endif; ?>

                <div class="d-none d-print-block mb-2">
                    <?= $kop_html; ?>
                    <div style="text-align:center;margin:6px 0;">
                        <div style="font-weight:700;font-size:14px;">REKAP ABSENSI SISWA</div>
                        <div style="font-size:12px;">
                            Kelas <?= htmlspecialchars(trim($info_kelas['tingkat'] . ' ' . $info_kelas['nama_kelas'])); ?>
                            <?= $info_kelas['jurusan'] ? ' - ' . htmlspecialchars($info_kelas['jurusan']) : ''; ?>
                            &middot; <?= htmlspecialchars($label_bulan); ?> &middot; TA <?= htmlspecialchars($nama_ta_aktif); ?>
                        </div>
                    </div>
                </div>

                <div class="row g-2 mb-3 no-print">
                    <div class="col-6 col-md-3">
                        <div class="card text-bg-primary"><div class="card-body py-2">
                            <div class="small">Jumlah Siswa</div><div class="fs-4 fw-bold"><?= count($siswa); ?></div>
                        </div></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-bg-info"><div class="card-body py-2">
                            <div class="small">Hari Ada Absensi</div><div class="fs-4 fw-bold"><?= count($tanggal_list); ?></div>
                        </div></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-bg-warning"><div class="card-body py-2">
                            <div class="small">Total Ketidakhadiran</div><div class="fs-4 fw-bold"><?= $total_absen; ?></div>
                        </div></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-bg-secondary"><div class="card-body py-2">
                            <div class="small">Wali Kelas</div><div class="fw-bold" style="font-size:.95rem;"><?= htmlspecialchars($info_kelas['wali'] ?: '-'); ?></div>
                        </div></div>
                    </div>
                </div>

                <?php if (!$siswa): ?>
                    <div class="alert alert-warning">Belum ada siswa pada kelas ini.</div>
                <?php elseif (!$tanggal_list): ?>
                    <div class="alert alert-info">Belum ada data absensi pada <?= htmlspecialchars($label_bulan); ?>.</div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered tbl-rekap mb-0 align-middle" style="font-size:.72rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="min-width:24px;">No</th>
                                            <th style="min-width:150px;">Nama Siswa</th>
                                            <th class="text-center" style="min-width:50px;">NISN</th>
                                            <?php foreach ($tanggal_list as $t): ?>
                                                <th class="text-center" style="min-width:20px;"><?= (int)substr($t, 8, 2) ?></th>
                                            <?php endforeach; ?>
                                            <th class="text-center text-success">H</th>
                                            <th class="text-center text-info">S</th>
                                            <th class="text-center text-warning">I</th>
                                            <th class="text-center text-danger">A</th>
                                            <th class="text-center">%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($siswa as $i => $s):
                                            $sid = (int)$s['id_siswa'];
                                            $c = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
                                            foreach ($tanggal_list as $t) {
                                                $stt = $mat[$sid][$t] ?? null;
                                                if ($stt) $c[$stt]++;
                                            }
                                            $total = array_sum($c);
                                            $pct = $total > 0 ? round($c['H'] / $total * 100) : 0;
                                        ?>
                                            <tr>
                                                <td class="text-center"><?= $i + 1 ?></td>
                                                <td class="text-nowrap"><?= htmlspecialchars($s['nama']); ?></td>
                                                <td class="text-center text-nowrap"><?= htmlspecialchars($s['nisn'] ?? '-'); ?></td>
                                                <?php foreach ($tanggal_list as $t):
                                                    $stt = $mat[$sid][$t] ?? '';
                                                ?>
                                                    <td class="text-center st">
                                                        <?php if ($stt): ?>
                                                            <span class="badge text-bg-<?= $warna[$stt] ?? 'secondary'; ?>"><?= htmlspecialchars($stt); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                                <td class="text-center fw-semibold"><?= $c['H'] ?></td>
                                                <td class="text-center"><?= $c['S'] ?></td>
                                                <td class="text-center"><?= $c['I'] ?></td>
                                                <td class="text-center"><?= $c['A'] ?></td>
                                                <td class="text-center fw-semibold"><?= $pct ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="small text-muted mt-2">
                                Absensi <b>harian</b> (diinput sekretaris kelas 1&times; per hari).
                                Keterangan: <span class="badge text-bg-success">H</span> Hadir,
                                <span class="badge text-bg-info">S</span> Sakit,
                                <span class="badge text-bg-warning">I</span> Izin,
                                <span class="badge text-bg-danger">A</span> Alfa. % = persentase kehadiran.
                                Tanggal berstatus <b>Libur</b> (kalender akademik) tidak ditampilkan.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-none d-print-block">
                    <?= $footer_html; ?>
                </div>

            <?php endif; ?>
        </div>
    </div>
</main>
