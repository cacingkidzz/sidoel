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
foreach ($kelas_wali as $k) {
    if ((int)$k['id_kelas'] === $id_kelas) { $valid = true; break; }
}
if (!$valid) $id_kelas = $kelas_wali ? (int)$kelas_wali[0]['id_kelas'] : 0;

$info_kelas = $id_kelas > 0 ? wk_kelas_info($koneksi, $id_kelas, $id_ta) : null;
$kelas_label = $info_kelas ? wk_kelas_label(['tingkat' => $info_kelas['tingkat'], 'nama_kelas' => $info_kelas['nama_kelas'], 'nama_jurusan' => $info_kelas['jurusan']]) : '';

$judul = 'Dashboard Wali Kelas';
$active = 'beranda';
require __DIR__ . '/_header.php';

if (empty($kelas_wali)) {
    ?>
    <div class="card wk-card">
        <div class="card-body text-center py-4">
            <i class="bi bi-person-badge text-secondary" style="font-size:2.4rem;"></i>
            <h6 class="fw-bold mt-2 mb-1">Belum Ada Kelas yang Diwalikan</h6>
            <p class="text-muted small mb-0">
                Akun <b><?= wk_esc($wk['nama_guru'] ?: $wk['username']) ?></b> belum ditugaskan sebagai wali kelas
                pada tahun ajaran <b><?= wk_esc($nama_ta) ?></b>.<br>
                Hubungi Admin/Kurikulum untuk penugasan wali kelas.
            </p>
        </div>
    </div>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

// ---- Bulan & hari libur ----
$nama_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
               '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$bulan = (string)($_GET['bulan'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) $bulan = date('Y-m');
$awal  = $bulan . '-01';
$akhir = date('Y-m-t', strtotime($awal));
$label_bulan = ($nama_bulan[substr($bulan, 5, 2)] ?? '') . ' ' . substr($bulan, 0, 4);

$libur_map = laporan_hari_libur($koneksi, $id_ta, $awal, $akhir);
$libur_in  = $libur_map ? "'" . implode("','", array_map([$koneksi, 'real_escape_string'], array_keys($libur_map))) . "'" : '';

$hari_ini = date('Y-m-d');
$hari_nama = wk_hari_id($hari_ini);
$hari_libur = $libur_map[$hari_ini] ?? null;
$akhir_pekan = in_array((int)date('w'), [0, 6], true);

// Hari efektif bulan berjalan (Senin–Jumat s.d. hari ini, tanpa libur)
$hari_efektif = [];
$t = strtotime($awal);
$batas = min(strtotime($akhir), strtotime($hari_ini));
while ($t !== false && $t <= $batas) {
    $w = (int)date('w', $t);
    $d = date('Y-m-d', $t);
    if ($w >= 1 && $w <= 5 && !isset($libur_map[$d])) $hari_efektif[] = $d;
    $t = strtotime('+1 day', $t);
}
$jml_hari_efektif = count($hari_efektif);

// ---- Roster & absensi ----
$roster = wk_siswa_kelas($koneksi, $id_kelas, $id_ta);
$total_siswa = count($roster);

$abs_month = [];
$abs_siswa = [];
if ($id_kelas > 0) {
    $sql = "SELECT tanggal, status, COUNT(*) AS c FROM dt_absensi
            WHERE id_kelas = ? AND id_tahun_ajaran = ? AND tanggal BETWEEN ? AND ?"
            . ($libur_in ? " AND tanggal NOT IN ($libur_in)" : "")
            . " GROUP BY tanggal, status";
    $stmt = $koneksi->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('iiss', $id_kelas, $id_ta, $awal, $akhir);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $abs_month[$r['tanggal']][$r['status']] = (int)$r['c'];
        }
        $stmt->close();
    }

    $sql2 = "SELECT id_siswa, status, COUNT(*) AS c FROM dt_absensi
             WHERE id_kelas = ? AND id_tahun_ajaran = ? AND tanggal BETWEEN ? AND ?"
             . ($libur_in ? " AND tanggal NOT IN ($libur_in)" : "")
             . " AND status IN ('S','I','A')
             GROUP BY id_siswa, status";
    $stmt = $koneksi->prepare($sql2);
    if ($stmt) {
        $stmt->bind_param('iiss', $id_kelas, $id_ta, $awal, $akhir);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $sid = (int)$r['id_siswa'];
            if (!isset($abs_siswa[$sid])) $abs_siswa[$sid] = ['S' => 0, 'I' => 0, 'A' => 0];
            $abs_siswa[$sid][$r['status']] = (int)$r['c'];
        }
        $stmt->close();
    }
}
ksort($abs_month);
$hari_terisi = count($abs_month);

// ---- Absensi hari ini / terakhir ----
$tgl_terakhir = null;
$stmt = $koneksi->prepare("SELECT MAX(tanggal) AS tgl FROM dt_absensi
                           WHERE id_kelas = ? AND id_tahun_ajaran = ? AND tanggal <= ?");
if ($stmt) {
    $stmt->bind_param('iis', $id_kelas, $id_ta, $hari_ini);
    $stmt->execute();
    $rt = $stmt->get_result()->fetch_assoc();
    if ($rt && $rt['tgl']) $tgl_terakhir = $rt['tgl'];
    $stmt->close();
}
$tgl_tampil = $tgl_terakhir;
$stmt = $koneksi->prepare("SELECT COUNT(*) AS c FROM dt_absensi
                           WHERE id_kelas = ? AND id_tahun_ajaran = ? AND tanggal = ?");
if ($stmt) {
    $stmt->bind_param('iis', $id_kelas, $id_ta, $hari_ini);
    $stmt->execute();
    $rc = $stmt->get_result()->fetch_assoc();
    if ($rc && (int)$rc['c'] > 0) $tgl_tampil = $hari_ini;
    $stmt->close();
}

$abs_rows = [];
$status_count = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
if ($tgl_tampil) {
    $stmt = $koneksi->prepare("SELECT a.id_siswa, a.status, a.keterangan, s.nama, s.jk
                               FROM dt_absensi a
                               INNER JOIN dt_siswa s ON s.id_siswa = a.id_siswa
                               WHERE a.id_kelas = ? AND a.id_tahun_ajaran = ? AND a.tanggal = ?
                               ORDER BY FIELD(a.status, 'A', 'I', 'S', 'H'), s.nama ASC");
    if ($stmt) {
        $stmt->bind_param('iis', $id_kelas, $id_ta, $tgl_tampil);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $abs_rows[] = $r;
            if (isset($status_count[$r['status']])) $status_count[$r['status']]++;
        }
        $stmt->close();
    }
}
$jml_tidak_hadir = $status_count['S'] + $status_count['I'] + $status_count['A'];
$pct_hadir = ($total_siswa > 0) ? round($status_count['H'] / $total_siswa * 100) : 0;

// ---- Jadwal & jurnal hari ini ----
$jadwal_today = [];
$jurnal_today = [];
$stmt = $koneksi->prepare("SELECT j.jam_ke, m.kode_mapel, m.nama_mapel, g.nama_guru,
                                  jp.waktu_mulai, jp.waktu_selesai
                           FROM dt_jadwal j
                           LEFT JOIN dt_mapel m ON m.id_mapel = j.id_mapel
                           LEFT JOIN dt_guru g ON g.id_guru = j.id_guru
                           LEFT JOIN dt_jam_pelajaran jp ON jp.jam_ke = j.jam_ke
                           WHERE j.id_kelas = ? AND j.id_tahun_ajaran = ? AND j.hari = ?
                           ORDER BY j.jam_ke ASC");
if ($stmt) {
    $stmt->bind_param('iis', $id_kelas, $id_ta, $hari_nama);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $jadwal_today[] = $r;
    $stmt->close();
}
$stmt = $koneksi->prepare("SELECT jr.id_jurnal, jr.jam_ke, jr.status, jr.materi,
                                  jr.id_guru_pengganti,
                                  m.kode_mapel, m.nama_mapel, g.nama_guru, gp.nama_guru AS nama_pengganti
                           FROM dt_jurnal jr
                           LEFT JOIN dt_mapel m ON m.id_mapel = jr.id_mapel
                           LEFT JOIN dt_guru g ON g.id_guru = jr.id_guru
                           LEFT JOIN dt_guru gp ON gp.id_guru = jr.id_guru_pengganti
                           WHERE jr.id_kelas = ? AND jr.id_tahun_ajaran = ? AND jr.tanggal = ?
                           ORDER BY jr.jam_ke ASC");
if ($stmt) {
    $stmt->bind_param('iis', $id_kelas, $id_ta, $hari_ini);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $jurnal_today[(int)$r['jam_ke']] = $r;
    $stmt->close();
}
$sesi_hari_ini = [];
foreach ($jadwal_today as $j) {
    $jam = (int)$j['jam_ke'];
    $jr = $jurnal_today[$jam] ?? null;
    unset($jurnal_today[$jam]);
    $sesi_hari_ini[] = [
        'jam' => $jam,
        'waktu' => $j['waktu_mulai'] ? substr($j['waktu_mulai'], 0, 5) . '–' . substr((string)$j['waktu_selesai'], 0, 5) : '',
        'mapel' => trim(($j['kode_mapel'] ?? '') . ' ' . ($j['nama_mapel'] ?? '')),
        'guru' => $j['nama_guru'] ?? '',
        'jurnal' => $jr,
    ];
}
foreach ($jurnal_today as $jam => $jr) {
    $sesi_hari_ini[] = [
        'jam' => (int)$jam,
        'waktu' => '',
        'mapel' => trim(($jr['kode_mapel'] ?? '') . ' ' . ($jr['nama_mapel'] ?? '')),
        'guru' => $jr['nama_guru'] ?? '',
        'jurnal' => $jr,
    ];
}
usort($sesi_hari_ini, fn($a, $b) => $a['jam'] <=> $b['jam']);
$jadwal_count = count($jadwal_today);
$jurnal_filled = 0;
foreach ($sesi_hari_ini as $s) { if (!empty($s['jurnal'])) $jurnal_filled++; }

// ---- Siswa perlu perhatian ----
$perlu = [];
foreach ($roster as $sw) {
    $ab = $abs_siswa[(int)$sw['id_siswa']] ?? ['S' => 0, 'I' => 0, 'A' => 0];
    $tot = $ab['S'] + $ab['I'] + $ab['A'];
    if ($tot > 0) {
        $sw['sakit'] = $ab['S']; $sw['izin'] = $ab['I']; $sw['alpa'] = $ab['A'];
        $sw['total_absen'] = $tot;
        $perlu[] = $sw;
    }
}
usort($perlu, function ($a, $b) {
    if ($a['alpa'] !== $b['alpa']) return $b['alpa'] <=> $a['alpa'];
    return $b['total_absen'] <=> $a['total_absen'];
});
$perlu = array_slice($perlu, 0, 5);
$jml_alpa3 = 0;
foreach ($abs_siswa as $ab) { if ($ab['A'] >= 3) $jml_alpa3++; }

// ---- Rekomendasi ----
$rekom = [];
if ($hari_libur) {
    $rekom[] = ['secondary', 'Hari ini <b>LIBUR</b> (' . wk_esc($hari_libur) . ').'];
} elseif ($akhir_pekan) {
    $rekom[] = ['secondary', 'Akhir pekan — tidak ada jadwal.'];
} else {
    if (!$tgl_tampil || $tgl_tampil !== $hari_ini) {
        $rekom[] = ['danger', 'Absensi hari ini <b>belum diisi</b> sekretaris.'];
    }
    if ($jadwal_count > 0 && $jurnal_filled < $jadwal_count) {
        $rekom[] = ['warning', '<b>' . ($jadwal_count - $jurnal_filled) . ' sesi</b> jurnal hari ini belum diisi.'];
    }
}
if ($jml_alpa3 > 0) {
    $rekom[] = ['danger', '<b>' . $jml_alpa3 . ' siswa</b> Alpa &ge; 3 kali bulan ini.'];
}
if ($jml_hari_efektif > 0 && $hari_terisi < $jml_hari_efektif) {
    $rekom[] = ['info', 'Absensi bulan ini <b>' . $hari_terisi . '/' . $jml_hari_efektif . '</b> hari efektif.'];
}
if (empty($rekom)) {
    $rekom[] = ['success', 'Semua terpantau baik.'];
}

// Data grafik
$chart_labels = $chart_h = $chart_s = $chart_i = $chart_a = [];
foreach ($hari_efektif as $d) {
    $chart_labels[] = date('d/m', strtotime($d));
    $chart_h[] = $abs_month[$d]['H'] ?? 0;
    $chart_s[] = $abs_month[$d]['S'] ?? 0;
    $chart_i[] = $abs_month[$d]['I'] ?? 0;
    $chart_a[] = $abs_month[$d]['A'] ?? 0;
}

$status_warna = ['Mengajar' => 'success', 'Tidak Hadir' => 'warning', 'Tugas/Kegiatan' => 'info', 'Kosong' => 'secondary'];
$abs_warna = ['H' => 'success', 'S' => 'info', 'I' => 'warning', 'A' => 'danger'];
?>

<?php if (count($kelas_wali) > 1): ?>
<div class="card wk-card mb-2">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-7">
                <label class="form-label small mb-0 fw-semibold">Kelas</label>
                <select name="kelas" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($kelas_wali as $k): ?>
                        <option value="<?= (int)$k['id_kelas']; ?>" <?= (int)$k['id_kelas'] === $id_kelas ? 'selected' : ''; ?>>
                            <?= wk_esc(wk_kelas_label($k)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-5">
                <input type="hidden" name="bulan" value="<?= wk_esc($bulan); ?>">
                <label class="form-label small mb-0 fw-semibold">Bulan</label>
                <input type="month" name="bulan" value="<?= wk_esc($bulan); ?>" class="form-control form-control-sm" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="card wk-card mb-2">
    <div class="card-body py-2 d-flex justify-content-between align-items-center gap-2">
        <div style="min-width:0;">
            <div class="fw-bold text-truncate"><i class="bi bi-people-fill text-success me-1"></i><?= wk_esc(wk_kelas_label(['tingkat'=>$info_kelas['tingkat'],'nama_kelas'=>$info_kelas['nama_kelas'],'nama_jurusan'=>$info_kelas['jurusan']])); ?></div>
            <div class="small text-muted text-truncate"><?= $total_siswa; ?> siswa &middot; T.A. <?= wk_esc($nama_ta); ?></div>
        </div>
        <form method="get">
            <input type="hidden" name="kelas" value="<?= (int)$id_kelas; ?>">
            <input type="month" name="bulan" value="<?= wk_esc($bulan); ?>" class="form-control form-control-sm" style="width:135px;" onchange="this.form.submit()">
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($hari_libur): ?>
<div class="alert alert-secondary py-2 small mb-2"><i class="bi bi-calendar-x me-1"></i><b>LIBUR</b> — <?= wk_esc($hari_libur); ?> (<?= wk_hari_id($hari_ini); ?>, <?= date('d/m/Y', strtotime($hari_ini)); ?>)</div>
<?php endif; ?>

<div class="row row-cols-2 g-2 mb-2">
    <div class="col">
        <div class="card wk-card h-100"><div class="card-body p-2">
            <div class="text-muted text-uppercase" style="font-size:.62rem;">Kehadiran <?= $tgl_tampil === $hari_ini ? 'Hari Ini' : 'Terakhir'; ?></div>
            <div class="kpi-value text-success"><?= $pct_hadir; ?>%</div>
            <div class="text-muted" style="font-size:.7rem;"><?= $status_count['H']; ?>/<?= $total_siswa; ?> siswa</div>
        </div></div>
    </div>
    <div class="col">
        <div class="card wk-card h-100"><div class="card-body p-2">
            <div class="text-muted text-uppercase" style="font-size:.62rem;">Tidak Hadir</div>
            <div class="kpi-value text-danger"><?= $jml_tidak_hadir; ?></div>
            <div class="d-flex gap-1 flex-wrap">
                <span class="badge bg-info" style="font-size:.6rem;">S <?= $status_count['S']; ?></span>
                <span class="badge bg-warning text-dark" style="font-size:.6rem;">I <?= $status_count['I']; ?></span>
                <span class="badge bg-danger" style="font-size:.6rem;">A <?= $status_count['A']; ?></span>
            </div>
        </div></div>
    </div>
    <div class="col">
        <div class="card wk-card h-100"><div class="card-body p-2">
            <div class="text-muted text-uppercase" style="font-size:.62rem;">Jurnal Hari Ini</div>
            <?php if ($hari_libur || $akhir_pekan): ?>
                <div class="kpi-value text-secondary">&mdash;</div>
                <div class="text-muted" style="font-size:.7rem;"><?= $hari_libur ? 'Libur' : 'Akhir pekan'; ?></div>
            <?php else: ?>
                <div class="kpi-value text-primary"><?= $jurnal_filled; ?>/<?= $jadwal_count; ?></div>
                <div class="text-muted" style="font-size:.7rem;"><?= max(0, $jadwal_count - $jurnal_filled); ?> belum diisi</div>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col">
        <div class="card wk-card h-100"><div class="card-body p-2">
            <div class="text-muted text-uppercase" style="font-size:.62rem;">Absensi Bulan</div>
            <div class="kpi-value text-info"><?= $hari_terisi; ?>/<?= $jml_hari_efektif; ?></div>
            <div class="text-muted" style="font-size:.7rem;">hari efektif</div>
        </div></div>
    </div>
</div>

<div class="card wk-card mb-2">
    <div class="card-header bg-white py-2 fw-semibold" style="font-size:.88rem;"><i class="bi bi-lightbulb text-warning me-1"></i>Tindakan yang Disarankan</div>
    <div class="card-body py-2">
        <ul class="list-unstyled mb-0 d-grid gap-1">
            <?php foreach ($rekom as $rk): ?>
                <li class="d-flex gap-2 small"><span class="badge bg-<?= $rk[0]; ?>" style="height:10px;width:10px;padding:0;margin-top:4px;">&nbsp;</span><span><?= $rk[1]; ?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="card wk-card mb-2">
    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold" style="font-size:.88rem;"><i class="bi bi-bar-chart-line text-success me-1"></i>Tren Absensi <?= wk_esc($label_bulan); ?></span>
    </div>
    <div class="card-body py-2">
        <?php if (empty($chart_labels)): ?>
            <div class="text-muted small text-center py-3">Belum ada hari efektif.</div>
        <?php else: ?>
            <div style="position:relative;height:190px;width:100%;"><canvas id="chartAbsensi"></canvas></div>
        <?php endif; ?>
    </div>
</div>

<div class="card wk-card mb-2">
    <div class="card-header bg-white py-2 fw-semibold" style="font-size:.88rem;">
        <i class="bi bi-person-check text-success me-1"></i>Siswa Tidak Hadir
        <span class="text-muted fw-normal">(<?= $tgl_tampil ? date('d/m/Y', strtotime($tgl_tampil)) : '-'; ?>)</span>
    </div>
    <div class="card-body p-0">
        <?php if (!$tgl_tampil): ?>
            <div class="p-3 text-muted small">Belum ada data absensi.</div>
        <?php else:
            $tidak = array_filter($abs_rows, fn($r) => in_array($r['status'], ['S','I','A'], true));
            if (empty($tidak)): ?>
                <div class="p-3 text-success small"><i class="bi bi-check2-circle me-1"></i>Semua siswa hadir (<?= $status_count['H']; ?> orang).</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th style="font-size:.72rem;">Nama</th><th class="text-center" style="font-size:.72rem;">Status</th><th style="font-size:.72rem;">Ket.</th></tr></thead>
                        <tbody>
                        <?php foreach ($tidak as $r): ?>
                            <tr>
                                <td class="small"><?= wk_esc($r['nama']); ?></td>
                                <td class="text-center"><span class="badge bg-<?= $abs_warna[$r['status']] ?? 'secondary'; ?>"><?= wk_esc($r['status']); ?></span></td>
                                <td class="small text-muted"><?= wk_esc($r['keterangan'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
        <?php endif; endif; ?>
    </div>
</div>

<div class="card wk-card mb-2">
    <div class="card-header bg-white py-2 fw-semibold" style="font-size:.88rem;">
        <i class="bi bi-journal-text text-primary me-1"></i>Jurnal Mengajar — <?= wk_esc($hari_nama); ?>
    </div>
    <div class="card-body p-0">
        <?php if ($hari_libur): ?>
            <div class="p-3 text-muted small">Hari ini libur.</div>
        <?php elseif ($akhir_pekan): ?>
            <div class="p-3 text-muted small">Akhir pekan — tidak ada jadwal.</div>
        <?php elseif (empty($sesi_hari_ini)): ?>
            <div class="p-3 text-muted small">Tidak ada jadwal pelajaran hari ini.</div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($sesi_hari_ini as $s): ?>
                    <div class="list-group-item py-2">
                        <div class="d-flex gap-2 align-items-start">
                            <div class="jam-badge"><?= (int)$s['jam']; ?><?php if ($s['waktu']): ?><small><?= wk_esc(substr($s['waktu'], 0, 5)); ?></small><?php endif; ?></div>
                            <div class="flex-grow-1" style="min-width:0;">
                                <div class="small fw-semibold text-truncate"><?= wk_esc($s['mapel'] ?: '-'); ?></div>
                                <div class="text-muted" style="font-size:.72rem;"><?= wk_esc($s['guru'] ?: '-'); ?></div>
                                <?php if (!empty($s['jurnal']['nama_pengganti'])): ?>
                                    <span class="badge bg-warning text-dark" style="font-size:.62rem;">Pengganti: <?= wk_esc($s['jurnal']['nama_pengganti']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($s['jurnal']['materi'])): ?>
                                    <div class="text-muted text-truncate" style="font-size:.72rem;"><i class="bi bi-book me-1"></i><?= wk_esc($s['jurnal']['materi']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <?php if (empty($s['jurnal'])): ?>
                                    <span class="badge bg-secondary">Belum</span>
                                <?php else: ?>
                                    <span class="badge bg-<?= $status_warna[$s['jurnal']['status']] ?? 'secondary'; ?>"><?= wk_esc($s['jurnal']['status']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card wk-card mb-2">
    <div class="card-header bg-white py-2 fw-semibold" style="font-size:.88rem;"><i class="bi bi-exclamation-triangle text-danger me-1"></i>Siswa Perlu Perhatian</div>
    <div class="card-body p-0">
        <?php if (empty($perlu)): ?>
            <div class="p-3 text-success small"><i class="bi bi-emoji-smile me-1"></i>Tidak ada ketidakhadiran bulan ini.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th style="font-size:.72rem;">Nama</th><th class="text-center" style="font-size:.72rem;">S</th><th class="text-center" style="font-size:.72rem;">I</th><th class="text-center" style="font-size:.72rem;">A</th><th class="text-center" style="font-size:.72rem;">Tot</th></tr></thead>
                    <tbody>
                    <?php foreach ($perlu as $sw): ?>
                        <tr>
                            <td class="small"><?= wk_esc($sw['nama']); ?></td>
                            <td class="text-center small"><?= (int)$sw['sakit']; ?></td>
                            <td class="text-center small"><?= (int)$sw['izin']; ?></td>
                            <td class="text-center"><span class="badge bg-<?= $sw['alpa'] >= 3 ? 'danger' : ($sw['alpa'] > 0 ? 'warning text-dark' : 'secondary'); ?>"><?= (int)$sw['alpa']; ?></span></td>
                            <td class="text-center small fw-semibold"><?= (int)$sw['total_absen']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($chart_labels)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var el = document.getElementById('chartAbsensi');
    if (!el || typeof Chart === 'undefined') return;
    Chart.defaults.font.family = "'Source Sans 3', system-ui, sans-serif";
    Chart.defaults.font.size = 10;
    Chart.defaults.color = '#64748b';
    new Chart(el, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels); ?>,
            datasets: [
                { label: 'H', data: <?= json_encode($chart_h); ?>, backgroundColor: '#16a34a' },
                { label: 'S', data: <?= json_encode($chart_s); ?>, backgroundColor: '#0ea5e9' },
                { label: 'I', data: <?= json_encode($chart_i); ?>, backgroundColor: '#f59e0b' },
                { label: 'A', data: <?= json_encode($chart_a); ?>, backgroundColor: '#dc2626' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true, pointStyle: 'rectRounded' } } },
            scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
