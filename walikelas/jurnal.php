<?php
require_once __DIR__ . '/_init.php';
wk_wajib_login();

$profil = wk_profil($koneksi);
$wk = $_SESSION['walikelas'];

$ta = wk_ta_aktif($koneksi);
$id_ta = $ta['id'];
$nama_ta = $ta['nama'];

$id_guru = (int)($wk['id_guru'] ?? 0);
$kelas_wali = wk_kelas_wali($koneksi, $id_guru, $id_ta);

$id_kelas = (int)($_GET['kelas'] ?? 0);
$valid = false;
foreach ($kelas_wali as $k) { if ((int)$k['id_kelas'] === $id_kelas) { $valid = true; break; } }
if (!$valid) $id_kelas = $kelas_wali ? (int)$kelas_wali[0]['id_kelas'] : 0;

$info_kelas = $id_kelas > 0 ? wk_kelas_info($koneksi, $id_kelas, $id_ta) : null;
$kelas_label = $info_kelas ? wk_kelas_label(['tingkat'=>$info_kelas['tingkat'],'nama_kelas'=>$info_kelas['nama_kelas'],'nama_jurusan'=>$info_kelas['jurusan']]) : '';

$judul = 'Jurnal Kelas';
$active = 'jurnal';
require __DIR__ . '/_header.php';

if (empty($kelas_wali)) {
    echo '<div class="card wk-card"><div class="card-body text-center py-4 text-muted small">Belum ada kelas yang diwalikan pada T.A. <b>' . wk_esc($nama_ta) . '</b>.</div></div>';
    require __DIR__ . '/_footer.php';
    exit;
}

$nama_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
               '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$bulan = (string)($_GET['bulan'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) $bulan = date('Y-m');
$awal  = $bulan . '-01';
$akhir = date('Y-m-t', strtotime($awal));
$label_bulan = ($nama_bulan[substr($bulan, 5, 2)] ?? '') . ' ' . substr($bulan, 0, 4);

$libur_map = laporan_hari_libur($koneksi, $id_ta, $awal, $akhir);

$rows = [];
$stmt = $koneksi->prepare("SELECT j.tanggal, j.jam_ke, j.materi, j.status, j.keterangan,
                                  j.id_guru_pengganti,
                                  m.kode_mapel, m.nama_mapel,
                                  g.nama_guru, gp.nama_guru AS nama_pengganti,
                                  jp.waktu_mulai, jp.waktu_selesai
                           FROM dt_jurnal j
                           LEFT JOIN dt_mapel m ON m.id_mapel = j.id_mapel
                           LEFT JOIN dt_guru g ON g.id_guru = j.id_guru
                           LEFT JOIN dt_guru gp ON gp.id_guru = j.id_guru_pengganti
                           LEFT JOIN dt_jam_pelajaran jp ON jp.jam_ke = j.jam_ke
                           WHERE j.id_kelas = ? AND j.id_tahun_ajaran = ? AND j.tanggal BETWEEN ? AND ?
                           ORDER BY j.tanggal ASC, j.jam_ke ASC");
if ($stmt) {
    $stmt->bind_param('iiss', $id_kelas, $id_ta, $awal, $akhir);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
}

// Sesi terjadwal bulan ini = jumlah jam per hari × kemunculan hari (di luar libur & akhir pekan).
$sched_per_hari = [];
$stmt = $koneksi->prepare("SELECT hari, COUNT(*) AS c FROM dt_jadwal WHERE id_kelas = ? AND id_tahun_ajaran = ? GROUP BY hari");
if ($stmt) {
    $stmt->bind_param('ii', $id_kelas, $id_ta);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $sched_per_hari[$r['hari']] = (int)$r['c'];
    $stmt->close();
}
$sesi_terjadwal = 0;
$t = strtotime($awal);
while ($t !== false && $t <= strtotime($akhir)) {
    $w = (int)date('w', $t);
    $d = date('Y-m-d', $t);
    if ($w >= 1 && $w <= 5 && !isset($libur_map[$d])) {
        $sesi_terjadwal += $sched_per_hari[wk_hari_id($d)] ?? 0;
    }
    $t = strtotime('+1 day', $t);
}
$jurnal_tercatat = count($rows);
$pct = $sesi_terjadwal > 0 ? round($jurnal_tercatat / $sesi_terjadwal * 100) : 0;

$status_warna = ['Mengajar' => 'success', 'Tidak Hadir' => 'warning', 'Tugas/Kegiatan' => 'info', 'Kosong' => 'secondary'];
$breakdown = ['Mengajar' => 0, 'Tidak Hadir' => 0, 'Tugas/Kegiatan' => 0, 'Kosong' => 0];
foreach ($rows as $r) { if (isset($breakdown[$r['status']])) $breakdown[$r['status']]++; }
?>
<div class="card wk-card mb-2">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <?php if (count($kelas_wali) > 1): ?>
            <div class="col-6">
                <label class="form-label small mb-0 fw-semibold">Kelas</label>
                <select name="kelas" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($kelas_wali as $k): ?>
                        <option value="<?= (int)$k['id_kelas']; ?>" <?= (int)$k['id_kelas'] === $id_kelas ? 'selected' : ''; ?>><?= wk_esc(wk_kelas_label($k)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="col-6">
                <label class="form-label small mb-0 fw-semibold">Kelas</label>
                <div class="fw-bold small pt-1"><?= wk_esc($kelas_label); ?></div>
                <input type="hidden" name="kelas" value="<?= (int)$id_kelas; ?>">
            </div>
            <?php endif; ?>
            <div class="col-6">
                <label class="form-label small mb-0 fw-semibold">Bulan</label>
                <input type="month" name="bulan" value="<?= wk_esc($bulan); ?>" class="form-control form-control-sm" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<div class="row row-cols-3 g-2 mb-2">
    <div class="col"><div class="card wk-card h-100"><div class="card-body p-2 text-center">
        <div class="kpi-value text-primary" style="font-size:1.2rem;"><?= $jurnal_tercatat; ?>/<?= $sesi_terjadwal; ?></div>
        <div class="text-muted" style="font-size:.62rem;">jurnal / sesi</div>
    </div></div></div>
    <div class="col"><div class="card wk-card h-100"><div class="card-body p-2 text-center">
        <div class="kpi-value text-success" style="font-size:1.2rem;"><?= $pct; ?>%</div>
        <div class="text-muted" style="font-size:.62rem;">realisasi</div>
    </div></div></div>
    <div class="col"><div class="card wk-card h-100"><div class="card-body p-2 text-center">
        <div class="kpi-value text-warning" style="font-size:1.2rem;"><?= $breakdown['Tidak Hadir'] + $breakdown['Kosong']; ?></div>
        <div class="text-muted" style="font-size:.62rem;">tdk hadir/kosong</div>
    </div></div></div>
</div>

<div class="card wk-card mb-2">
    <div class="card-header bg-white py-2 fw-semibold" style="font-size:.88rem;">
        <i class="bi bi-journal-text text-primary me-1"></i>Jurnal <?= wk_esc($label_bulan); ?>
    </div>
    <div class="card-body p-0">
        <?php if (!$rows): ?>
            <div class="p-3 text-muted small">Belum ada jurnal pada bulan ini.</div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($rows as $r): ?>
                    <div class="list-group-item py-2">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="small fw-semibold">
                                <?= date('d/m', strtotime($r['tanggal'])); ?> <span class="text-muted fw-normal"><?= wk_esc(wk_hari_id($r['tanggal'])); ?></span>
                                &middot; Jam <?= (int)$r['jam_ke']; ?>
                            </div>
                            <span class="badge bg-<?= $status_warna[$r['status']] ?? 'secondary'; ?>"><?= wk_esc($r['status']); ?></span>
                        </div>
                        <div class="small mt-1">
                            <i class="bi bi-book text-muted me-1"></i><?= wk_esc(trim(($r['kode_mapel'] ?? '') . ' ' . ($r['nama_mapel'] ?? '')) ?: '-'); ?>
                        </div>
                        <div class="text-muted" style="font-size:.72rem;">
                            <i class="bi bi-person me-1"></i><?= wk_esc($r['nama_guru'] ?: '-'); ?>
                            <?php if (!empty($r['nama_pengganti'])): ?>
                                <span class="badge bg-warning text-dark ms-1" style="font-size:.6rem;">Pengganti: <?= wk_esc($r['nama_pengganti']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($r['materi']): ?>
                            <div class="text-muted mt-1" style="font-size:.72rem;"><i class="bi bi-journal-check me-1"></i><?= wk_esc($r['materi']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($libur_map): ?>
<div class="alert alert-light border py-2 small mb-2">
    <i class="bi bi-calendar-x text-secondary me-1"></i><b>Hari libur</b> (dikecualikan): <?= wk_esc(implode(', ', array_map(fn($d) => date('d/m', strtotime($d)), array_keys($libur_map)))); ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
