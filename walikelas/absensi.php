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

$judul = 'Rekap Absensi';
$active = 'absensi';
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
$libur_in  = $libur_map ? "'" . implode("','", array_map([$koneksi, 'real_escape_string'], array_keys($libur_map))) . "'" : '';

$roster = wk_siswa_kelas($koneksi, $id_kelas, $id_ta);

$mat = [];       // [id_siswa][tanggal] = status
$tanggal_set = [];
$stmt = $koneksi->prepare("SELECT id_siswa, tanggal, status FROM dt_absensi
                           WHERE id_kelas = ? AND id_tahun_ajaran = ? AND tanggal BETWEEN ? AND ?"
                           . ($libur_in ? " AND tanggal NOT IN ($libur_in)" : "")
                           . " ORDER BY tanggal ASC");
if ($stmt) {
    $stmt->bind_param('iiss', $id_kelas, $id_ta, $awal, $akhir);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $mat[(int)$r['id_siswa']][$r['tanggal']] = $r['status'];
        $tanggal_set[$r['tanggal']] = true;
    }
    $stmt->close();
}
$tanggal_list = array_keys($tanggal_set);
sort($tanggal_list);

$tot = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
foreach ($mat as $days) {
    foreach ($days as $st) { if (isset($tot[$st])) $tot[$st]++; }
}
$total_absen = $tot['S'] + $tot['I'] + $tot['A'];

$abs_warna = ['H' => 'success', 'S' => 'info', 'I' => 'warning', 'A' => 'danger'];
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
        <a href="export_absensi.php?kelas=<?= (int)$id_kelas; ?>&bulan=<?= urlencode($bulan); ?>"
           class="btn btn-outline-success btn-sm w-100 mt-2">
            <i class="bi bi-file-earmark-excel me-1"></i>Download Excel Rekap Bulan Ini
        </a>
    </div>
</div>

<div class="row row-cols-3 g-2 mb-2">
    <div class="col"><div class="card wk-card h-100"><div class="card-body p-2 text-center">
        <div class="kpi-value text-primary" style="font-size:1.25rem;"><?= count($roster); ?></div>
        <div class="text-muted" style="font-size:.65rem;">siswa</div>
    </div></div></div>
    <div class="col"><div class="card wk-card h-100"><div class="card-body p-2 text-center">
        <div class="kpi-value text-info" style="font-size:1.25rem;"><?= count($tanggal_list); ?></div>
        <div class="text-muted" style="font-size:.65rem;">hari data</div>
    </div></div></div>
    <div class="col"><div class="card wk-card h-100"><div class="card-body p-2 text-center">
        <div class="kpi-value text-danger" style="font-size:1.25rem;"><?= $total_absen; ?></div>
        <div class="text-muted" style="font-size:.65rem;">tidak hadir</div>
    </div></div></div>
</div>

<div class="card wk-card mb-2">
    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold" style="font-size:.88rem;"><i class="bi bi-clipboard2-data text-success me-1"></i>Rekap <?= wk_esc($label_bulan); ?></span>
        <span class="badge bg-light text-dark" style="font-size:.62rem;">H/S/I/A</span>
    </div>
    <div class="card-body p-0">
        <?php if (!$tanggal_list): ?>
            <div class="p-3 text-muted small">Belum ada absensi pada bulan ini.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0" style="white-space:nowrap;">
                <thead class="table-light">
                    <tr>
                        <th style="font-size:.68rem;">Nama</th>
                        <?php foreach ($tanggal_list as $tg): ?>
                            <th class="text-center" style="font-size:.62rem;padding:2px 3px;" title="<?= wk_esc($tg); ?>">
                                <?= (int)date('d', strtotime($tg)); ?><br><span class="text-muted" style="font-size:.55rem;"><?= wk_esc(substr(wk_hari_id($tg), 0, 3)); ?></span>
                            </th>
                        <?php endforeach; ?>
                        <th class="text-center" style="font-size:.62rem;">H</th>
                        <th class="text-center" style="font-size:.62rem;">S</th>
                        <th class="text-center" style="font-size:.62rem;">I</th>
                        <th class="text-center" style="font-size:.62rem;">A</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roster as $sw): $sid = (int)$sw['id_siswa'];
                        $h = $s = $i = $a = 0; ?>
                        <tr>
                            <td class="small" style="max-width:130px;overflow:hidden;text-overflow:ellipsis;"><?= wk_esc($sw['nama']); ?></td>
                            <?php foreach ($tanggal_list as $tg):
                                $st = $mat[$sid][$tg] ?? '';
                                if ($st === 'H') $h++; elseif ($st === 'S') $s++; elseif ($st === 'I') $i++; elseif ($st === 'A') $a++; ?>
                                <td class="text-center" style="padding:2px 3px;">
                                    <?php if ($st): ?>
                                        <span class="badge bg-<?= $abs_warna[$st] ?? 'secondary'; ?>" style="font-size:.58rem;padding:.15em .3em;"><?= wk_esc($st); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:.6rem;">&middot;</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="text-center small"><?= $h; ?></td>
                            <td class="text-center small"><?= $s; ?></td>
                            <td class="text-center small"><?= $i; ?></td>
                            <td class="text-center"><span class="badge bg-<?= $a > 0 ? 'danger' : 'secondary'; ?>" style="font-size:.6rem;"><?= $a; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th class="small">Total</th>
                        <?php foreach ($tanggal_list as $tg): ?>
                            <th class="text-center" style="font-size:.58rem;padding:2px 3px;">&nbsp;</th>
                        <?php endforeach; ?>
                        <th class="text-center small"><?= $tot['H']; ?></th>
                        <th class="text-center small"><?= $tot['S']; ?></th>
                        <th class="text-center small"><?= $tot['I']; ?></th>
                        <th class="text-center small"><?= $tot['A']; ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($libur_map): ?>
<div class="alert alert-light border py-2 small mb-2">
    <i class="bi bi-calendar-x text-secondary me-1"></i><b>Hari libur</b> (dikecualikan):
    <?= wk_esc(implode(', ', array_map(fn($d) => date('d/m', strtotime($d)), array_keys($libur_map)))); ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
