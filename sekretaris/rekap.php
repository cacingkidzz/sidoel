<?php
require_once __DIR__ . '/_init.php';
sek_wajib_login();

$sek = $_SESSION['sekretaris'];
$profil = sek_profil($koneksi);
$id_kelas = (int)$sek['id_kelas'];
$id_ta    = (int)$sek['id_tahun_ajaran'];

$bulan = (string)($_GET['bulan'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) $bulan = date('Y-m');
$awal  = $bulan . '-01';
$akhir = date('Y-m-t', strtotime($awal));
$nama_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$label_bulan = ($nama_bulan[substr($bulan,5,2)] ?? '') . ' ' . substr($bulan,0,4);

$prev = date('Y-m', strtotime($awal . ' -1 month'));
$next = date('Y-m', strtotime($awal . ' +1 month'));

// Rekap harian per siswa
$mat = [];   // mat[sid][tanggal] = status
$stmt = $koneksi->prepare("SELECT id_siswa, tanggal, status
                           FROM dt_absensi
                           WHERE id_kelas = ? AND id_tahun_ajaran = ? AND tanggal BETWEEN ? AND ?");
$stmt->bind_param('iiss', $id_kelas, $id_ta, $awal, $akhir);
$stmt->execute();
$res = $stmt->get_result();
$prio = ['A' => 4, 'I' => 3, 'S' => 2, 'H' => 1];
while ($r = $res->fetch_assoc()) {
    $sid = (int)$r['id_siswa']; $tgl = $r['tanggal']; $stt = $r['status'];
    if (!isset($mat[$sid][$tgl]) || $prio[$stt] > $prio[$mat[$sid][$tgl]]) {
        $mat[$sid][$tgl] = $stt;
    }
}
$stmt->close();

// Daftar tanggal yang punya data
$tanggal_set = [];
foreach ($mat as $days) foreach ($days as $t => $_) $tanggal_set[$t] = true;
ksort($tanggal_set);
$tanggal_list = array_keys($tanggal_set);

$siswa = sek_siswa_kelas($koneksi, $id_kelas, $id_ta);

$judul = 'Rekap Absensi';
$active = 'rekap';
require __DIR__ . '/_header.php';

$warna = ['H' => 'success', 'S' => 'info', 'I' => 'warning', 'A' => 'danger'];
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <a href="rekap.php?bulan=<?= sek_esc($prev) ?>" class="btn btn-sm btn-light border"><i class="bi bi-chevron-left"></i></a>
    <div class="text-center">
        <div class="fw-bold"><i class="bi bi-calendar-month me-1"></i><?= sek_esc($label_bulan) ?></div>
        <form method="get" class="mt-1">
            <input type="month" name="bulan" class="form-control form-control-sm" value="<?= sek_esc($bulan) ?>" onchange="this.form.submit()" style="max-width:160px;">
        </form>
    </div>
    <a href="rekap.php?bulan=<?= sek_esc($next) ?>" class="btn btn-sm btn-light border"><i class="bi bi-chevron-right"></i></a>
</div>

<?php if (!$siswa || !$tanggal_list): ?>
    <div class="card sek-card">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-clipboard-x fs-2 d-block mb-2"></i>
            Belum ada data absensi pada bulan ini.
        </div>
    </div>
<?php else: ?>
    <div class="card sek-card mb-3">
        <div class="card-body pb-2">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle" style="font-size:.72rem;">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="position:sticky;left:0;background:#f8f9fa;z-index:2;">No</th>
                            <th style="min-width:120px;">Nama</th>
                            <?php foreach ($tanggal_list as $t): ?>
                                <th class="text-center" style="min-width:26px;"><?= (int)substr($t, 8, 2) ?></th>
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
                                <td class="text-nowrap"><?= sek_esc($s['nama']) ?></td>
                                <?php foreach ($tanggal_list as $t):
                                    $stt = $mat[$sid][$t] ?? '';
                                ?>
                                    <td class="text-center">
                                        <?php if ($stt): ?>
                                            <span class="badge text-bg-<?= $warna[$stt] ?? 'secondary' ?>"><?= sek_esc($stt) ?></span>
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
            <div class="small text-muted mt-2">Absensi harian kelas (input sekretaris 1&times; per hari). Geser tabel ke samping untuk melihat semua tanggal.</div>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
