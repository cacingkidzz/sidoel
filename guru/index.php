<?php
require_once __DIR__ . '/_init.php';
guru_wajib_login();

$profil = guru_profil($koneksi);
$guru = $_SESSION['guru'];
$id_guru = (int)($guru['id_guru'] ?? 0);

$ta = guru_ta_aktif($koneksi);
$id_ta = $ta['id'];
$nama_ta = $ta['nama'];

$judul = 'Beranda Guru';
$active = 'beranda';
require __DIR__ . '/_header.php';

$kelas_diajar = guru_kelas_diajar($koneksi, $id_guru, $id_ta);
if (empty($kelas_diajar)) {
    ?>
    <div class="card gr-card">
        <div class="card-body text-center py-4">
            <i class="bi bi-person-video3 text-secondary" style="font-size:2.4rem;"></i>
            <h6 class="fw-bold mt-2 mb-1">Belum Ada Kelas yang Diajar</h6>
            <p class="text-muted small mb-0">
                Akun <b><?= guru_esc($guru['nama_guru'] ?: $guru['username']) ?></b> belum memiliki penugasan
                pengampu mapel atau jadwal pada tahun ajaran <b><?= guru_esc($nama_ta) ?></b>.<br>
                Hubungi Admin/Kurikulum untuk penugasan mengajar.
            </p>
        </div>
    </div>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

$hari_ini = date('Y-m-d');
$hari_nama = guru_hari_id($hari_ini);
$libur_today = guru_libur_info($koneksi, $id_ta, $hari_ini);
$akhir_pekan = in_array((int)date('w'), [0, 6], true);

// Bulan berjalan
$awal = date('Y-m-01');
$akhir = date('Y-m-t');
$nama_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
               '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$label_bulan = ($nama_bulan[date('m')] ?? '') . ' ' . date('Y');
$libur_map = laporan_hari_libur($koneksi, $id_ta, $awal, $akhir);

$rekap = guru_rekap_kehadiran($koneksi, $id_guru, $id_ta, $awal, $akhir, $libur_map);

$mapel_kelas = guru_mapel_per_kelas($koneksi, $id_guru, $id_ta);
$jadwal_today = guru_jadwal_hari($koneksi, $id_guru, $id_ta, $hari_nama);

// Absensi hari ini per kelas
$abs_hari = [];
$kelas_terisi = 0;
foreach ($kelas_diajar as $k) {
    $kid = (int)$k['id_kelas'];
    $rows = (!$libur_today && !$akhir_pekan) ? guru_absensi_harian($koneksi, $kid, $id_ta, $hari_ini) : [];
    $sum = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
    foreach ($rows as $a) {
        if (isset($sum[$a['status']])) $sum[$a['status']]++;
    }
    $abs_hari[$kid] = ['terisi' => count($rows) > 0, 'sum' => $sum, 'n' => count($rows)];
    if (count($rows) > 0) $kelas_terisi++;
}

$total_kelas = count($kelas_diajar);
?>

<div class="card gr-card mb-2">
    <div class="card-body py-2 d-flex justify-content-between align-items-center gap-2">
        <div class="small text-muted">
            <i class="bi bi-calendar3 me-1"></i><?= guru_esc($hari_nama) ?>, <?= date('d M Y') ?>
            &middot; T.A. <b><?= guru_esc($nama_ta) ?></b>
        </div>
        <?php if ($libur_today): ?>
            <span class="badge text-bg-danger">Libur</span>
        <?php elseif ($akhir_pekan): ?>
            <span class="badge text-bg-secondary">Akhir pekan</span>
        <?php else: ?>
            <span class="badge text-bg-success">Hari efektif</span>
        <?php endif; ?>
    </div>
</div>

<?php if ($libur_today): ?>
    <div class="alert alert-secondary py-2 small">
        <i class="bi bi-cup-hot me-1"></i><b>LIBUR</b> — <?= guru_esc($libur_today['nama_kegiatan']); ?>.
    </div>
<?php endif; ?>

<div class="row row-cols-2 g-2 mb-2">
    <div class="col">
        <div class="card gr-card h-100"><div class="card-body p-2">
            <div class="text-muted text-uppercase" style="font-size:.62rem;">Kelas Diajar</div>
            <div class="kpi-value text-primary"><?= $total_kelas; ?></div>
            <div class="text-muted" style="font-size:.68rem;">kelas pada TA ini</div>
        </div></div>
    </div>
    <div class="col">
        <div class="card gr-card h-100"><div class="card-body p-2">
            <div class="text-muted text-uppercase" style="font-size:.62rem;">Kehadiran Guru</div>
            <div class="kpi-value text-<?= $rekap['hadir'] + $rekap['tidak'] > 0 ? ($rekap['pct'] >= 90 ? 'success' : ($rekap['pct'] >= 70 ? 'warning' : 'danger')) : 'secondary'; ?>">
                <?= $rekap['hadir'] + $rekap['tidak'] > 0 ? number_format($rekap['pct'], 1, ',', '.') . '%' : '&mdash;'; ?>
            </div>
            <div class="text-muted" style="font-size:.68rem;"><?= $rekap['hadir']; ?> hadir &middot; <?= $rekap['tidak']; ?> tidak</div>
        </div></div>
    </div>
    <div class="col">
        <div class="card gr-card h-100"><div class="card-body p-2">
            <div class="text-muted text-uppercase" style="font-size:.62rem;">Jurnal Tercatat</div>
            <div class="kpi-value text-info"><?= $rekap['tercatat']; ?><span class="fs-6 text-muted">/<?= $rekap['terjadwal']; ?></span></div>
            <div class="text-muted" style="font-size:.68rem;">realisasi <?= number_format($rekap['realisasi'], 1, ',', '.'); ?>%</div>
        </div></div>
    </div>
    <div class="col">
        <div class="card gr-card h-100"><div class="card-body p-2">
            <div class="text-muted text-uppercase" style="font-size:.62rem;">Absensi Hari Ini</div>
            <?php if ($libur_today): ?>
                <div class="kpi-value text-danger" style="font-size:1.05rem;">LIBUR</div>
                <div class="text-muted" style="font-size:.68rem;">tidak ada absensi</div>
            <?php elseif ($akhir_pekan): ?>
                <div class="kpi-value text-secondary" style="font-size:1.05rem;">Akhir pekan</div>
                <div class="text-muted" style="font-size:.68rem;">tidak ada absensi</div>
            <?php else: ?>
                <div class="kpi-value text-<?= $kelas_terisi === $total_kelas ? 'success' : ($kelas_terisi > 0 ? 'warning' : 'secondary'); ?>"><?= $kelas_terisi; ?><span class="fs-6 text-muted">/<?= $total_kelas; ?></span></div>
                <div class="text-muted" style="font-size:.68rem;">kelas sudah terisi</div>
            <?php endif; ?>
        </div></div>
    </div>
</div>

<div class="card gr-card mb-2">
    <div class="card-header bg-white py-2 fw-semibold" style="font-size:.88rem;">
        <i class="bi bi-calendar-week text-primary me-1"></i>Jadwal Mengajar Hari Ini
    </div>
    <div class="card-body p-0">
        <?php if ($libur_today): ?>
            <div class="p-3 text-muted small"><i class="bi bi-cup-hot me-1"></i>Libur — tidak ada jadwal.</div>
        <?php elseif ($akhir_pekan): ?>
            <div class="p-3 text-muted small"><i class="bi bi-calendar-week me-1"></i>Akhir pekan — tidak ada jadwal.</div>
        <?php elseif (empty($jadwal_today)): ?>
            <div class="p-3 text-muted small"><i class="bi bi-inbox me-1"></i>Tidak ada jadwal mengajar hari ini.</div>
        <?php else: ?>
            <?php foreach ($jadwal_today as $j): ?>
                <div class="d-flex gap-2 align-items-start p-2 border-bottom">
                    <div class="jam-badge"><?= (int)$j['jam_ke']; ?><small>JAM</small></div>
                    <div class="flex-grow-1" style="min-width:0;">
                        <div class="small fw-semibold text-truncate"><?= guru_esc($j['kode_mapel'] ?: $j['nama_mapel']); ?></div>
                        <div class="small text-muted text-truncate"><?= guru_esc(guru_kelas_label($j)); ?></div>
                        <?php if (!empty($j['waktu_mulai'])): ?>
                            <div class="text-muted" style="font-size:.68rem;"><i class="bi bi-clock me-1"></i><?= substr($j['waktu_mulai'], 0, 5); ?><?= !empty($j['waktu_selesai']) ? '–' . substr($j['waktu_selesai'], 0, 5) : ''; ?></div>
                        <?php endif; ?>
                    </div>
                    <a href="absensi.php?kelas=<?= (int)$j['id_kelas']; ?>&tanggal=<?= guru_esc($hari_ini); ?>" class="btn btn-sm btn-outline-primary py-0">Absensi</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-1">
    <h6 class="fw-bold mb-0" style="font-size:.92rem;"><i class="bi bi-people text-primary me-1"></i>Kelas yang Diajar</h6>
    <span class="small text-muted"><?= $label_bulan; ?></span>
</div>

<?php foreach ($kelas_diajar as $k):
    $kid = (int)$k['id_kelas'];
    $label = guru_kelas_label($k);
    $jml = guru_jumlah_siswa($koneksi, $kid, $id_ta);
    $mapel = $mapel_kelas[$kid] ?? [];
    $jadwal = [];
    foreach ($jadwal_today as $j) {
        if ((int)$j['id_kelas'] === $kid) $jadwal[] = $j;
    }
    $abs = $abs_hari[$kid] ?? ['terisi' => false, 'sum' => ['H'=>0,'S'=>0,'I'=>0,'A'=>0], 'n'=>0];
?>
<div class="card gr-card mb-2">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <div style="min-width:0;">
                <div class="fw-bold text-truncate"><i class="bi bi-door-open me-1 text-primary"></i><?= guru_esc($label); ?></div>
                <div class="small text-muted"><?= $jml; ?> siswa<?= !empty($mapel) ? ' &middot; ' . guru_esc(implode(', ', $mapel)) : ''; ?></div>
            </div>
            <a href="absensi.php?kelas=<?= $kid; ?>&tanggal=<?= guru_esc($hari_ini); ?>" class="btn btn-sm btn-primary py-0 flex-shrink-0">
                <i class="bi bi-clipboard2-check me-1"></i><?= $abs['terisi'] ? 'Cek' : 'Isi'; ?>
            </a>
        </div>

        <?php if (!empty($jadwal)): ?>
            <div class="mt-2">
                <?php foreach ($jadwal as $j): ?>
                    <span class="badge bg-light text-dark border fw-normal mb-1">
                        Jam <?= (int)$j['jam_ke']; ?> &middot; <?= guru_esc($j['kode_mapel'] ?: $j['nama_mapel']); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="mt-2 pt-2 border-top small">
            <?php if ($libur_today): ?>
                <span class="text-danger"><i class="bi bi-cup-hot me-1"></i>Libur</span>
            <?php elseif ($akhir_pekan): ?>
                <span class="text-muted"><i class="bi bi-calendar-week me-1"></i>Akhir pekan</span>
            <?php elseif ($abs['terisi']): ?>
                <span class="text-success"><i class="bi bi-check2-circle me-1"></i>Absensi sudah diisi</span>
                <span class="ms-1">
                    <span class="badge bg-success-subtle text-success">H <?= $abs['sum']['H']; ?></span>
                    <span class="badge bg-info-subtle text-info">S <?= $abs['sum']['S']; ?></span>
                    <span class="badge bg-warning-subtle text-warning">I <?= $abs['sum']['I']; ?></span>
                    <span class="badge bg-danger-subtle text-danger">A <?= $abs['sum']['A']; ?></span>
                </span>
            <?php else: ?>
                <span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Absensi belum diisi — Anda dapat mengisi.</span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/_footer.php'; ?>
