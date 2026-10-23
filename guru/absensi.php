<?php
require_once __DIR__ . '/_init.php';
guru_wajib_login();

$profil = guru_profil($koneksi);
$guru = $_SESSION['guru'];
$id_guru = (int)($guru['id_guru'] ?? 0);
$ta = guru_ta_aktif($koneksi);
$id_ta = $ta['id'];
$nama_ta = $ta['nama'];

$kelas_diajar = guru_kelas_diajar($koneksi, $id_guru, $id_ta);
$id_kelas = (int)($_GET['kelas'] ?? 0);
$valid = false;
foreach ($kelas_diajar as $k) {
    if ((int)$k['id_kelas'] === $id_kelas) { $valid = true; break; }
}
if (!$valid) $id_kelas = $kelas_diajar ? (int)$kelas_diajar[0]['id_kelas'] : 0;

$tanggal = guru_valid_tanggal($_GET['tanggal'] ?? null);
$libur = $id_kelas > 0 ? guru_libur_info($koneksi, $id_ta, $tanggal) : null;

$siswa = $id_kelas > 0 ? guru_siswa_kelas($koneksi, $id_kelas, $id_ta) : [];
$abs = ($id_kelas > 0 && !$libur) ? guru_absensi_harian($koneksi, $id_kelas, $id_ta, $tanggal) : [];
$terisi = count($abs) > 0;

$sum = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
$pencatat = [];
foreach ($abs as $a) {
    if (isset($sum[$a['status']])) $sum[$a['status']]++;
    if (!empty($a['pencatat'])) $pencatat[$a['pencatat']] = true;
}

$judul = 'Absensi Kelas';
$active = 'absensi';
require __DIR__ . '/_header.php';

if (empty($kelas_diajar)) {
    ?>
    <div class="card gr-card">
        <div class="card-body text-center py-4 text-muted">
            <i class="bi bi-person-video3 fs-2 d-block mb-2"></i>
            Belum ada kelas yang Anda ajar pada T.A. <b><?= guru_esc($nama_ta); ?></b>.
        </div>
    </div>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

$info_kelas = null;
foreach ($kelas_diajar as $k) {
    if ((int)$k['id_kelas'] === $id_kelas) { $info_kelas = $k; break; }
}
$label_kelas = $info_kelas ? guru_kelas_label($info_kelas) : '-';
?>

<div class="d-flex align-items-center justify-content-between mb-2">
    <h6 class="fw-bold mb-0"><i class="bi bi-clipboard2-check me-1"></i><?= guru_esc(guru_hari_id($tanggal)); ?>, <?= date('d M Y', strtotime($tanggal)); ?></h6>
    <?php if ($libur): ?>
        <span class="badge text-bg-danger rounded-pill">Libur</span>
    <?php elseif ($terisi): ?>
        <span class="badge text-bg-success rounded-pill">Terisi</span>
    <?php else: ?>
        <span class="badge text-bg-secondary rounded-pill">Belum diisi</span>
    <?php endif; ?>
</div>

<form method="get" class="card gr-card mb-3">
    <div class="card-body py-2">
        <div class="row g-2">
            <div class="col-12">
                <label class="form-label small mb-1 text-muted">Kelas yang diajar</label>
                <select name="kelas" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($kelas_diajar as $k): ?>
                        <option value="<?= (int)$k['id_kelas']; ?>" <?= (int)$k['id_kelas'] === $id_kelas ? 'selected' : ''; ?>>
                            <?= guru_esc(guru_kelas_label($k)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label small mb-1 text-muted">Tanggal absensi</label>
                <input type="date" name="tanggal" class="form-control form-control-sm" value="<?= guru_esc($tanggal); ?>"
                       max="<?= date('Y-m-d'); ?>" onchange="this.form.submit()">
            </div>
        </div>
    </div>
</form>

<?php if ($libur): ?>
    <div class="card gr-card">
        <div class="card-body text-center py-4">
            <i class="bi bi-cup-hot fs-2 d-block mb-2 text-danger"></i>
            <div class="fw-bold text-danger">LIBUR</div>
            <div class="text-muted"><?= guru_esc($libur['nama_kegiatan']); ?></div>
            <div class="small text-muted mt-1">Tidak ada absensi pada tanggal ini.</div>
        </div>
    </div>
<?php elseif (!$siswa): ?>
    <div class="card gr-card">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-people fs-2 d-block mb-2"></i>
            Belum ada siswa di kelas ini.
        </div>
    </div>
<?php elseif ($terisi): ?>
    <div class="alert alert-info py-2 small">
        <i class="bi bi-info-circle me-1"></i>
        Absensi <b><?= guru_esc($label_kelas); ?></b> tanggal ini sudah diisi
        <?= $pencatat ? 'oleh sekretaris <b>' . guru_esc(implode(', ', array_keys($pencatat))) . '</b>' : 'oleh guru/petugas'; ?>.
        <b>Guru hanya dapat mengecek</b> data absensi yang sudah ada.
    </div>

    <div class="card gr-card mb-3">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div class="fw-semibold small"><i class="bi bi-people me-1"></i><?= guru_esc($label_kelas); ?></div>
                <span class="badge text-bg-light border"><?= count($siswa); ?> siswa</span>
            </div>
            <div class="d-flex flex-wrap gap-1">
                <span class="badge bg-success-subtle text-success">H <?= $sum['H']; ?></span>
                <span class="badge bg-info-subtle text-info">S <?= $sum['S']; ?></span>
                <span class="badge bg-warning-subtle text-warning">I <?= $sum['I']; ?></span>
                <span class="badge bg-danger-subtle text-danger">A <?= $sum['A']; ?></span>
            </div>
        </div>
    </div>

    <div class="card gr-card mb-2">
        <div class="card-body p-0">
            <?php foreach ($siswa as $i => $s):
                $sid = (int)$s['id_siswa'];
                $a = $abs[$sid] ?? null;
                $st = $a['status'] ?? 'H';
                $ket = $a['keterangan'] ?? '';
                $warna = ['H' => 'success', 'S' => 'info', 'I' => 'warning', 'A' => 'danger'][$st] ?? 'secondary';
            ?>
                <div class="d-flex align-items-start justify-content-between gap-2 p-2 border-bottom">
                    <div class="small" style="min-width:0;">
                        <span class="text-muted me-1"><?= $i + 1; ?>.</span>
                        <span class="fw-semibold"><?= guru_esc($s['nama']); ?></span>
                        <span class="text-muted">(<?= guru_esc($s['jk']); ?>)</span>
                        <?php if ($ket !== ''): ?>
                            <div class="text-muted" style="font-size:.7rem;"><i class="bi bi-chat-left-text me-1"></i><?= guru_esc($ket); ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="badge text-bg-<?= $warna; ?> flex-shrink-0"><?= $st; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php else: ?>
    <form method="post" action="simpan_absensi.php">
        <input type="hidden" name="kelas" value="<?= $id_kelas; ?>">
        <input type="hidden" name="tanggal" value="<?= guru_esc($tanggal); ?>">

        <div class="card gr-card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="fw-semibold"><i class="bi bi-people me-1"></i><?= guru_esc($label_kelas); ?></div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-success py-0" id="btnSemuaHadir"><i class="bi bi-check2-all me-1"></i>Semua Hadir</button>
                        <span class="badge text-bg-light border"><?= count($siswa); ?> siswa</span>
                    </div>
                </div>
                <div class="small text-muted mb-2">Tandai siswa yang <b>tidak hadir</b>; sisanya otomatis <b>Hadir</b>. Cukup 1&times; per hari.</div>

                <?php foreach ($siswa as $i => $s):
                    $sid = (int)$s['id_siswa'];
                ?>
                    <div class="py-2 border-bottom">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div class="small" style="min-width:0;">
                                <span class="text-muted me-1"><?= $i + 1; ?>.</span>
                                <span class="fw-semibold"><?= guru_esc($s['nama']); ?></span>
                                <span class="text-muted">(<?= guru_esc($s['jk']); ?>)</span>
                            </div>
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="abs[<?= $sid; ?>]" id="a<?= $sid; ?>h" value="H" checked>
                                <label class="btn btn-outline-success" for="a<?= $sid; ?>h">H</label>
                                <input type="radio" class="btn-check" name="abs[<?= $sid; ?>]" id="a<?= $sid; ?>s" value="S">
                                <label class="btn btn-outline-info" for="a<?= $sid; ?>s">S</label>
                                <input type="radio" class="btn-check" name="abs[<?= $sid; ?>]" id="a<?= $sid; ?>i" value="I">
                                <label class="btn btn-outline-warning" for="a<?= $sid; ?>i">I</label>
                                <input type="radio" class="btn-check" name="abs[<?= $sid; ?>]" id="a<?= $sid; ?>a" value="A">
                                <label class="btn btn-outline-danger" for="a<?= $sid; ?>a">A</label>
                            </div>
                        </div>
                        <input type="text" name="ket[<?= $sid; ?>]" class="form-control form-control-sm mt-1" placeholder="keterangan (opsional)">
                    </div>
                <?php endforeach; ?>
                <div class="small text-muted mt-2">H = Hadir, S = Sakit, I = Izin, A = Alpa</div>
            </div>
        </div>

        <div class="mb-2">
            <button type="submit" class="btn btn-primary w-100 fw-semibold shadow"><i class="bi bi-save me-1"></i>Simpan Absensi</button>
        </div>
    </form>

    <script>
    document.getElementById('btnSemuaHadir')?.addEventListener('click', function () {
        document.querySelectorAll('input.btn-check[value="H"]').forEach(function (r) { r.checked = true; });
    });
    </script>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
