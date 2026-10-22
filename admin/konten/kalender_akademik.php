<?php
/**
 * Kalender Akademik (KBM-5) - agenda/kegiatan sekolah per tahun ajaran.
 * Filter: ?ta=<id_tahun_ajaran>&bulan=YYYY-MM (default TA aktif & bulan berjalan).
 * Cetak via Print-to-PDF browser (pakai helper_laporan.php).
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
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

// ---- Daftar tahun ajaran ----
$ta_list = [];
$q = $koneksi->query("SELECT id_tahun_ajaran, tahun_ajaran, status FROM dt_tahun_ajar ORDER BY tahun_ajaran DESC");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $ta_list[] = $r;
    }
}

// ---- Tentukan TA terpilih (GET -> session -> status aktif -> terbaru) ----
$id_ta = isset($_GET['ta']) ? (int)$_GET['ta'] : 0;
$ta_valid = false;
foreach ($ta_list as $t) {
    if ((int)$t['id_tahun_ajaran'] === $id_ta) {
        $ta_valid = true;
        break;
    }
}
if (!$ta_valid) {
    $id_ta = 0;
    $nama_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
    foreach ($ta_list as $t) {
        if ($t['tahun_ajaran'] === $nama_sesi) {
            $id_ta = (int)$t['id_tahun_ajaran'];
            break;
        }
    }
    if ($id_ta === 0) {
        foreach ($ta_list as $t) {
            if ($t['status'] === 'aktif') {
                $id_ta = (int)$t['id_tahun_ajaran'];
                break;
            }
        }
    }
    if ($id_ta === 0 && $ta_list) {
        $id_ta = (int)$ta_list[0]['id_tahun_ajaran'];
    }
}
$nama_ta = '';
foreach ($ta_list as $t) {
    if ((int)$t['id_tahun_ajaran'] === $id_ta) {
        $nama_ta = $t['tahun_ajaran'];
        break;
    }
}

// ---- Bulan ----
$nama_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$bulan = (string)($_GET['bulan'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {
    $bulan = date('Y-m');
}
$awal   = $bulan . '-01';
$akhir  = date('Y-m-t', strtotime($awal));
$label_bulan = ($nama_bulan[substr($bulan, 5, 2)] ?? '') . ' ' . substr($bulan, 0, 4);
$semester_default = ((int)substr($bulan, 5, 2) <= 6) ? 'Genap' : 'Ganjil';

// ---- Ambil kegiatan pada bulan ini (termasuk yang rentangnya menyeberang) ----
$kegiatan = [];
if ($id_ta > 0) {
    $stmt = $koneksi->prepare("SELECT id_kegiatan, nama_kegiatan, deskripsi, tanggal_mulai, tanggal_selesai, semester, jenis
                               FROM dt_kalender_akademik
                               WHERE id_tahun_ajaran = ?
                                 AND tanggal_mulai <= ?
                                 AND COALESCE(tanggal_selesai, tanggal_mulai) >= ?
                               ORDER BY tanggal_mulai ASC, nama_kegiatan ASC");
    $stmt->bind_param("iss", $id_ta, $akhir, $awal);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $r['tgl_selesai'] = $r['tanggal_selesai'] ?: $r['tanggal_mulai'];
        $kegiatan[] = $r;
    }
    $stmt->close();
}

// ---- Petakan kegiatan ke tiap tanggal di dalam bulan ----
$by_date = [];
foreach ($kegiatan as $k) {
    $s = max($k['tanggal_mulai'], $awal);
    $e = min($k['tgl_selesai'], $akhir);
    $cur = $s;
    $guard = 0;
    while ($cur <= $e && $guard < 40) {
        $by_date[$cur][] = $k;
        $cur = date('Y-m-d', strtotime($cur . ' +1 day'));
        $guard++;
    }
}

// ---- Grid kalender (Senin s/d Minggu) ----
$firstDow    = (int)date('N', strtotime($awal)); // 1 = Senin .. 7 = Minggu
$daysInMonth = (int)date('t', strtotime($awal));
$cells = [];
for ($i = 1; $i < $firstDow; $i++) {
    $cells[] = null;
}
for ($d = 1; $d <= $daysInMonth; $d++) {
    $cells[] = $bulan . '-' . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
}
while (count($cells) % 7 !== 0) {
    $cells[] = null;
}

$jenis_color = ['Kegiatan' => 'primary', 'Libur' => 'danger', 'Ujian' => 'warning', 'Rapat' => 'info', 'Lainnya' => 'secondary'];
$hari_nama = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$today = date('Y-m-d');

// Ringkasan
$jml_libur = 0;
$jml_ujian = 0;
foreach ($kegiatan as $k) {
    if ($k['jenis'] === 'Libur') $jml_libur++;
    if ($k['jenis'] === 'Ujian') $jml_ujian++;
}
?>
<?= laporan_print_css(); ?>
<style>
.cal-grid { table-layout: fixed; }
.cal-grid th { text-align: center; font-size: .78rem; }
.cal-grid td { height: 92px; vertical-align: top; padding: 3px 4px !important; }
.cal-daynum { font-weight: 700; font-size: .8rem; }
.cal-today { background: #fff7d6 !important; }
.cal-today .cal-daynum { color: #b45309; }
.cal-chip { display: block; font-size: .6rem; line-height: 1.15; padding: 1px 4px; margin-top: 2px; border-radius: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
@media print {
  @page { size: A4 landscape; margin: 8mm; }
  .cal-grid td { height: 70px; }
  .cal-grid, .cal-grid th, .cal-grid td { border: 1px solid #999 !important; }
  .cal-chip { color: #000 !important; background: #eee !important; border: 1px solid #bbb; }
  .tbl-kegiatan th, .tbl-kegiatan td { font-size: 9px !important; padding: 2px 4px !important; border: 1px solid #999 !important; }
}
</style>

<main class="app-main">
    <div class="app-content-header no-print">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6"><h2 class="mb-0">Kalender Akademik</h2></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item">Pendidikan &amp; KBM</li>
                        <li class="breadcrumb-item active" aria-current="page">Kalender Akademik</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">

            <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['alert']['type']); ?> alert-dismissible fade show no-print" role="alert">
                    <?= $_SESSION['alert']['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>

            <div class="card card-outline card-primary no-print">
                <div class="card-body pb-2">
                    <form method="get" class="row g-2 align-items-end">
                        <input type="hidden" name="menu" value="kalender_akademik">
                        <div class="col-md-4">
                            <label class="form-label fw-bold mb-1">Tahun Ajaran</label>
                            <select name="ta" class="form-select" onchange="this.form.submit()">
                                <?php if (!$ta_list): ?>
                                    <option value="">— Belum ada TA —</option>
                                <?php endif; ?>
                                <?php foreach ($ta_list as $t): ?>
                                    <option value="<?= (int)$t['id_tahun_ajaran']; ?>" <?= ((int)$t['id_tahun_ajaran'] === $id_ta) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($t['tahun_ajaran']); ?><?= $t['status'] === 'aktif' ? ' (aktif)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold mb-1">Bulan</label>
                            <input type="month" name="bulan" class="form-control" value="<?= htmlspecialchars($bulan); ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-5 d-flex gap-2">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah" <?= $id_ta <= 0 ? 'disabled' : ''; ?>>
                                <i class="bi bi-plus-lg me-1"></i> Tambah Kegiatan
                            </button>
                            <button type="button" class="btn btn-success" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($id_ta <= 0): ?>
                <div class="alert alert-warning">Belum ada tahun ajaran. Tambahkan tahun ajaran terlebih dahulu.</div>
            <?php else: ?>

                <div class="d-none d-print-block mb-2">
                    <?= $kop_html; ?>
                    <div style="text-align:center;margin:6px 0;">
                        <div style="font-weight:700;font-size:14px;">KALENDER AKADEMIK</div>
                        <div style="font-size:12px;"><?= htmlspecialchars($label_bulan); ?> &middot; Tahun Ajaran <?= htmlspecialchars($nama_ta); ?></div>
                    </div>
                </div>

                <div class="row g-2 mb-3 no-print">
                    <div class="col-6 col-md-3">
                        <div class="card text-bg-primary"><div class="card-body py-2">
                            <div class="small">Total Kegiatan</div><div class="fs-4 fw-bold"><?= count($kegiatan); ?></div>
                        </div></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-bg-danger"><div class="card-body py-2">
                            <div class="small">Libur</div><div class="fs-4 fw-bold"><?= $jml_libur; ?></div>
                        </div></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-bg-warning"><div class="card-body py-2">
                            <div class="small">Ujian</div><div class="fs-4 fw-bold"><?= $jml_ujian; ?></div>
                        </div></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card text-bg-secondary"><div class="card-body py-2">
                            <div class="small">Tahun Ajaran</div><div class="fw-bold" style="font-size:.95rem;"><?= htmlspecialchars($nama_ta); ?></div>
                        </div></div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="text-center mb-2">
                            <div class="fw-bold" style="font-size:1.05rem;"><?= htmlspecialchars($label_bulan); ?></div>
                            <div class="small text-muted">Tahun Ajaran <?= htmlspecialchars($nama_ta); ?></div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered cal-grid mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <?php foreach ($hari_nama as $hn): ?>
                                            <th><?= $hn; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_chunk($cells, 7) as $week): ?>
                                        <tr>
                                            <?php foreach ($week as $date): ?>
                                                <?php if ($date === null): ?>
                                                    <td class="table-light"></td>
                                                <?php else: ?>
                                                    <td class="<?= $date === $today ? 'cal-today' : ''; ?>">
                                                        <div class="cal-daynum"><?= (int)substr($date, 8, 2); ?></div>
                                                        <?php foreach (($by_date[$date] ?? []) as $k): ?>
                                                            <span class="cal-chip badge text-bg-<?= $jenis_color[$k['jenis']] ?? 'secondary'; ?>"
                                                                  title="<?= htmlspecialchars($k['nama_kegiatan'] . ($k['deskripsi'] ? ' — ' . $k['deskripsi'] : '')); ?>">
                                                                <?= htmlspecialchars($k['nama_kegiatan']); ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </td>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="small text-muted mt-2 no-print">
                            Keterangan warna:
                            <span class="badge text-bg-primary">Kegiatan</span>
                            <span class="badge text-bg-danger">Libur</span>
                            <span class="badge text-bg-warning">Ujian</span>
                            <span class="badge text-bg-info">Rapat</span>
                            <span class="badge text-bg-secondary">Lainnya</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><i class="bi bi-list-ul me-1"></i> Daftar Kegiatan — <?= htmlspecialchars($label_bulan); ?></h5></div>
                    <div class="card-body">
                        <?php if (!$kegiatan): ?>
                            <div class="alert alert-info mb-0">Belum ada kegiatan pada bulan ini.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle tbl-kegiatan">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:5%;">#</th>
                                            <th style="width:22%;">Tanggal</th>
                                            <th>Kegiatan</th>
                                            <th style="width:12%;">Jenis</th>
                                            <th style="width:10%;" class="text-center no-print">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; foreach ($kegiatan as $k):
                                            $idk = (int)$k['id_kegiatan'];
                                            $tgl = date('d/m/Y', strtotime($k['tanggal_mulai']));
                                            if ($k['tgl_selesai'] !== $k['tanggal_mulai']) {
                                                $tgl .= ' – ' . date('d/m/Y', strtotime($k['tgl_selesai']));
                                            }
                                        ?>
                                            <tr>
                                                <td><?= $no++; ?></td>
                                                <td class="text-nowrap"><?= $tgl; ?><?= $k['semester'] ? '<br><span class="badge text-bg-light border">' . htmlspecialchars($k['semester']) . '</span>' : ''; ?></td>
                                                <td>
                                                    <span class="fw-semibold"><?= htmlspecialchars($k['nama_kegiatan']); ?></span>
                                                    <?php if ($k['deskripsi']): ?><br><span class="small text-muted"><?= htmlspecialchars($k['deskripsi']); ?></span><?php endif; ?>
                                                </td>
                                                <td><span class="badge text-bg-<?= $jenis_color[$k['jenis']] ?? 'secondary'; ?>"><?= htmlspecialchars($k['jenis']); ?></span></td>
                                                <td class="text-center no-print">
                                                    <button class="btn btn-sm btn-outline-primary btn-edit"
                                                        data-bs-toggle="modal" data-bs-target="#modalEdit"
                                                        data-id="<?= $idk; ?>"
                                                        data-nama="<?= htmlspecialchars($k['nama_kegiatan']); ?>"
                                                        data-deskripsi="<?= htmlspecialchars($k['deskripsi'] ?? ''); ?>"
                                                        data-mulai="<?= htmlspecialchars($k['tanggal_mulai']); ?>"
                                                        data-selesai="<?= htmlspecialchars($k['tanggal_selesai'] ?? ''); ?>"
                                                        data-semester="<?= htmlspecialchars($k['semester'] ?? ''); ?>"
                                                        data-jenis="<?= htmlspecialchars($k['jenis']); ?>">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <a href="aksi/aksi_kalender.php?aksi=hapus&id=<?= $idk; ?>&ta=<?= $id_ta; ?>&bulan=<?= htmlspecialchars($bulan); ?>"
                                                        class="btn btn-sm btn-outline-danger btn-hapus"
                                                        data-nama="<?= htmlspecialchars($k['nama_kegiatan']); ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-none d-print-block">
                    <?= $footer_html; ?>
                </div>

            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" action="aksi/aksi_kalender.php" method="post">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-plus me-1"></i> Tambah Kegiatan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="aksi" value="tambah">
                <input type="hidden" name="id_tahun_ajaran" value="<?= $id_ta; ?>">
                <input type="hidden" name="redirect_bulan" value="<?= htmlspecialchars($bulan); ?>">
                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Kegiatan</label>
                    <input type="text" name="nama_kegiatan" class="form-control" maxlength="150" required placeholder="mis. Penilaian Tengah Semester">
                </div>
                <div class="row g-2">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Tanggal Mulai</label>
                        <input type="date" name="tanggal_mulai" class="form-control" value="<?= $bulan; ?>-01" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Tanggal Selesai <span class="text-muted small">(opsional)</span></label>
                        <input type="date" name="tanggal_selesai" class="form-control">
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Jenis</label>
                        <select name="jenis" class="form-select">
                            <option value="Kegiatan" selected>Kegiatan</option>
                            <option value="Libur">Libur</option>
                            <option value="Ujian">Ujian</option>
                            <option value="Rapat">Rapat</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Semester</label>
                        <select name="semester" class="form-select">
                            <option value="">— (tidak tentu) —</option>
                            <option value="Ganjil" <?= $semester_default === 'Ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                            <option value="Genap" <?= $semester_default === 'Genap' ? 'selected' : ''; ?>>Genap</option>
                        </select>
                    </div>
                </div>
                <div class="mb-1">
                    <label class="form-label fw-bold">Deskripsi <span class="text-muted small">(opsional)</span></label>
                    <textarea name="deskripsi" class="form-control" rows="2" maxlength="255"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" action="aksi/aksi_kalender.php" method="post">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-1"></i> Edit Kegiatan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="aksi" value="edit">
                <input type="hidden" name="id_kegiatan" id="edit_id">
                <input type="hidden" name="id_tahun_ajaran" value="<?= $id_ta; ?>">
                <input type="hidden" name="redirect_bulan" value="<?= htmlspecialchars($bulan); ?>">
                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Kegiatan</label>
                    <input type="text" name="nama_kegiatan" id="edit_nama" class="form-control" maxlength="150" required>
                </div>
                <div class="row g-2">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Tanggal Mulai</label>
                        <input type="date" name="tanggal_mulai" id="edit_mulai" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Tanggal Selesai <span class="text-muted small">(opsional)</span></label>
                        <input type="date" name="tanggal_selesai" id="edit_selesai" class="form-control">
                    </div>
                </div>
                <div class="row g-2">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Jenis</label>
                        <select name="jenis" id="edit_jenis" class="form-select">
                            <option value="Kegiatan">Kegiatan</option>
                            <option value="Libur">Libur</option>
                            <option value="Ujian">Ujian</option>
                            <option value="Rapat">Rapat</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Semester</label>
                        <select name="semester" id="edit_semester" class="form-select">
                            <option value="">— (tidak tentu) —</option>
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                        </select>
                    </div>
                </div>
                <div class="mb-1">
                    <label class="form-label fw-bold">Deskripsi <span class="text-muted small">(opsional)</span></label>
                    <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="2" maxlength="255"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-edit').forEach(function (b) {
        b.addEventListener('click', function () {
            document.getElementById('edit_id').value = b.getAttribute('data-id');
            document.getElementById('edit_nama').value = b.getAttribute('data-nama');
            document.getElementById('edit_deskripsi').value = b.getAttribute('data-deskripsi');
            document.getElementById('edit_mulai').value = b.getAttribute('data-mulai');
            document.getElementById('edit_selesai').value = b.getAttribute('data-selesai');
            document.getElementById('edit_semester').value = b.getAttribute('data-semester');
            document.getElementById('edit_jenis').value = b.getAttribute('data-jenis');
        });
    });

    document.querySelectorAll('.btn-hapus').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var url = a.getAttribute('href');
            var nama = a.getAttribute('data-nama') || '';
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Hapus kegiatan ini?',
                    html: '<b>' + nama + '</b> akan dihapus dari kalender.',
                    icon: 'warning', showCancelButton: true,
                    confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal',
                    confirmButtonColor: '#dc3545'
                }).then(function (r) { if (r.isConfirmed) window.location.href = url; });
            } else if (confirm('Hapus kegiatan ' + nama + '?')) {
                window.location.href = url;
            }
        });
    });
});
</script>
