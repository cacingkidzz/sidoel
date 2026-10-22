<?php
require_once __DIR__ . '/_init.php';
sek_wajib_login();

$sek = $_SESSION['sekretaris'];
$profil = sek_profil($koneksi);
$id_kelas = (int)$sek['id_kelas'];
$id_ta    = (int)$sek['id_tahun_ajaran'];

$tanggal = sek_valid_tanggal($_GET['tanggal'] ?? null);
$libur   = sek_libur_info($koneksi, $id_ta, $tanggal);

$siswa = sek_siswa_kelas($koneksi, $id_kelas, $id_ta);
$abs   = $libur ? [] : sek_absensi_harian($koneksi, $id_kelas, $id_ta, $tanggal);
$terisi = count($abs) > 0;

$judul = 'Absensi Harian';
$active = 'absensi';
require __DIR__ . '/_header.php';?>

<div class="d-flex align-items-center justify-content-between mb-2">
    <h6 class="fw-bold mb-0"><i class="bi bi-clipboard2-check me-1"></i><?= sek_esc(sek_hari_id($tanggal)) ?>, <?= date('d M Y', strtotime($tanggal)) ?></h6>
    <?php if ($libur): ?>
        <span class="badge text-bg-danger rounded-pill">Libur</span>
    <?php elseif ($terisi): ?>
        <span class="badge text-bg-success rounded-pill">Terisi</span>
    <?php else: ?>
        <span class="badge text-bg-secondary rounded-pill">Belum diisi</span>
    <?php endif; ?>
</div>

<form method="get" class="mb-3">
    <label class="form-label small mb-1 text-muted">Pilih tanggal</label>
    <input type="date" name="tanggal" class="form-control" value="<?= sek_esc($tanggal) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
</form>

<?php if ($libur): ?>
    <div class="card sek-card">
        <div class="card-body text-center py-4">
            <i class="bi bi-cup-hot fs-2 d-block mb-2 text-danger"></i>
            <div class="fw-bold text-danger">LIBUR</div>
            <div class="text-muted"><?= sek_esc($libur['nama_kegiatan']) ?></div>
            <div class="small text-muted mt-1">Tidak ada absensi pada tanggal ini.</div>
        </div>
    </div>
<?php elseif (!$siswa): ?>
    <div class="card sek-card">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-people fs-2 d-block mb-2"></i>
            Belum ada siswa di kelas ini.
        </div>
    </div>
<?php else: ?>
    <form method="post" action="simpan_absensi.php">
        <input type="hidden" name="tanggal" value="<?= sek_esc($tanggal) ?>">

        <div class="card sek-card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="fw-semibold"><i class="bi bi-people me-1"></i>Absensi Siswa</div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-success py-0" id="btnSemuaHadir"><i class="bi bi-check2-all me-1"></i>Semua Hadir</button>
                        <span class="badge text-bg-light border"><?= count($siswa) ?> siswa</span>
                    </div>
                </div>
                <div class="small text-muted mb-2">Tandai siswa yang <b>tidak hadir</b>; sisanya otomatis <b>Hadir</b>. Cukup 1&times; per hari.</div>

                <?php foreach ($siswa as $i => $s):
                    $sid = (int)$s['id_siswa'];
                    $cur = $abs[$sid] ?? null;
                    $cur_st = $cur['status'] ?? 'H';
                    $cur_ket = $cur['keterangan'] ?? '';
                ?>
                    <div class="py-2 border-bottom">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div class="small" style="min-width:0;">
                                <span class="text-muted me-1"><?= $i + 1 ?>.</span>
                                <span class="fw-semibold"><?= sek_esc($s['nama']) ?></span>
                                <span class="text-muted">(<?= sek_esc($s['jk']) ?>)</span>
                            </div>
                            <div class="btn-group abs-seg" role="group">
                                <input type="radio" class="btn-check" name="abs[<?= $sid ?>]" id="a<?= $sid ?>h" value="H" <?= $cur_st === 'H' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-success" for="a<?= $sid ?>h">H</label>
                                <input type="radio" class="btn-check" name="abs[<?= $sid ?>]" id="a<?= $sid ?>s" value="S" <?= $cur_st === 'S' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-info" for="a<?= $sid ?>s">S</label>
                                <input type="radio" class="btn-check" name="abs[<?= $sid ?>]" id="a<?= $sid ?>i" value="I" <?= $cur_st === 'I' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-warning" for="a<?= $sid ?>i">I</label>
                                <input type="radio" class="btn-check" name="abs[<?= $sid ?>]" id="a<?= $sid ?>a" value="A" <?= $cur_st === 'A' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-danger" for="a<?= $sid ?>a">A</label>
                            </div>
                        </div>
                        <input type="text" name="ket[<?= $sid ?>]" class="form-control form-control-sm mt-1" placeholder="keterangan (opsional)" value="<?= sek_esc($cur_ket) ?>">
                    </div>
                <?php endforeach; ?>
                <div class="small text-muted mt-2">H = Hadir, S = Sakit, I = Izin, A = Alpa</div>
            </div>
        </div>

        <div class="sticky-save mb-2">
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
