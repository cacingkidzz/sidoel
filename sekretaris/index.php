<?php
require_once __DIR__ . '/_init.php';
sek_wajib_login();

$sek = $_SESSION['sekretaris'];
$profil = sek_profil($koneksi);

$tanggal = sek_valid_tanggal($_GET['tanggal'] ?? null);
$id_kelas = (int)$sek['id_kelas'];
$id_ta   = (int)$sek['id_tahun_ajaran'];

$kelas = sek_kelas_info($koneksi, $id_kelas, $id_ta);
$ta_label = sek_ta_label($koneksi, $id_ta);

$libur = sek_libur_info($koneksi, $id_ta, $tanggal);

// Sesi jadwal hari itu + status jurnal
$sesi = [];
if (!$libur) {
$stmt = $koneksi->prepare("SELECT jd.jam_ke, jd.id_mapel, jd.id_guru, jd.id_jadwal,
                                  m.kode_mapel, m.nama_mapel, g.nama_guru,
                                  jp.waktu_mulai, jp.waktu_selesai, jp.keterangan AS ket_jam,
                                  jr.id_jurnal, jr.status, jr.materi
                           FROM dt_jadwal jd
                           LEFT JOIN dt_mapel m ON m.id_mapel = jd.id_mapel
                           LEFT JOIN dt_guru g ON g.id_guru = jd.id_guru
                           LEFT JOIN dt_jam_pelajaran jp ON jp.jam_ke = jd.jam_ke
                           LEFT JOIN dt_jurnal jr ON jr.id_kelas = jd.id_kelas AND jr.tanggal = ?
                                                  AND jr.jam_ke = jd.jam_ke AND jr.id_tahun_ajaran = jd.id_tahun_ajaran
                           WHERE jd.id_kelas = ? AND jd.hari = ? AND jd.id_tahun_ajaran = ?
                           ORDER BY jd.jam_ke ASC");
$hari_id = sek_hari_id($tanggal);
$stmt->bind_param('sisi', $tanggal, $id_kelas, $hari_id, $id_ta);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $sesi[] = $r;
$stmt->close();
}

$terisi = 0;
foreach ($sesi as $s) { if (!empty($s['id_jurnal'])) $terisi++; }

$abs_terisi = !$libur && count(sek_absensi_harian($koneksi, $id_kelas, $id_ta, $tanggal)) > 0;

$badge = [
    'Mengajar'       => 'success',
    'Tidak Hadir'    => 'danger',
    'Tugas/Kegiatan' => 'warning',
    'Kosong'         => 'secondary',
];

$judul = 'Beranda';
$active = 'beranda';
require __DIR__ . '/_header.php';?>

<div class="card sek-card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center gap-3">
            <div class="jam-badge"><i class="bi bi-people-fill"></i></div>
            <div class="flex-grow-1" style="min-width:0;">
                <div class="fw-bold text-truncate"><?= sek_esc($kelas['nama_kelas']) ?></div>
                <div class="small text-muted text-truncate">
                    <?= sek_esc($kelas['tingkat']) ?> &middot; <?= sek_esc($kelas['jurusan'] ?: '-') ?> &middot; <?= (int)$kelas['jumlah_siswa'] ?> siswa
                </div>
            </div>
        </div>
        <hr class="my-2">
        <div class="d-flex align-items-center justify-content-between">
            <div style="min-width:0;">
                <div class="small text-muted">Wali Kelas</div>
                <div class="fw-semibold text-truncate"><i class="bi bi-person-badge me-1"></i><?= sek_esc($kelas['wali'] ?: 'Belum ditentukan') ?></div>
            </div>
            <div class="text-end">
                <div class="small text-muted">Tahun Ajaran</div>
                <div class="fw-semibold"><?= sek_esc($ta_label ?? '-') ?></div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-2">
    <h6 class="fw-bold mb-0"><i class="bi bi-calendar3 me-1"></i><?= sek_esc(sek_hari_id($tanggal)) ?>, <?= date('d M Y', strtotime($tanggal)) ?></h6>
    <?php if ($libur): ?>
        <span class="badge text-bg-danger rounded-pill">Libur</span>
    <?php else: ?>
        <span class="badge text-bg-primary rounded-pill"><?= $terisi ?>/<?= count($sesi) ?> sesi</span>
    <?php endif; ?>
</div>

<form method="get" class="mb-3">
    <label class="form-label small mb-1 text-muted">Pilih tanggal</label>
    <input type="date" name="tanggal" class="form-control" value="<?= sek_esc($tanggal) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
</form>

<?php if (!$libur): ?>
    <a href="absensi.php?tanggal=<?= sek_esc($tanggal) ?>" class="btn btn-<?= $abs_terisi ? 'outline-success' : 'primary' ?> btn-sm w-100 fw-semibold mb-3">
        <i class="bi bi-clipboard2-check me-1"></i><?= $abs_terisi ? 'Absensi hari ini sudah terisi — lihat/ubah' : 'Isi Absensi Hari Ini (1× per hari)' ?>
    </a>
<?php endif; ?>

<?php if ($libur): ?>
    <div class="card sek-card">
        <div class="card-body text-center py-4">
            <i class="bi bi-cup-hot fs-2 d-block mb-2 text-danger"></i>
            <div class="fw-bold text-danger">LIBUR</div>
            <div class="text-muted"><?= sek_esc($libur['nama_kegiatan']) ?></div>
            <div class="small text-muted mt-1">Tidak ada kegiatan pembelajaran pada tanggal ini.</div>
        </div>
    </div>
<?php elseif (!$sesi): ?>
    <div class="card sek-card">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-cup-hot fs-2 d-block mb-2"></i>
            Tidak ada jadwal pelajaran untuk tanggal ini.
            <?php if (in_array(sek_hari_id($tanggal), ['Sabtu', 'Minggu'], true)): ?>
                <div class="small mt-1">(akhir pekan)</div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($sesi as $s): ?>
        <a href="jurnal.php?tanggal=<?= sek_esc($tanggal) ?>&jam=<?= (int)$s['jam_ke'] ?>" class="text-decoration-none">
            <div class="card sek-card mb-2">
                <div class="card-body d-flex align-items-center gap-3 py-2">
                    <div class="jam-badge"><span><?= (int)$s['jam_ke'] ?></span><small>jam</small></div>
                    <div class="flex-grow-1" style="min-width:0;">
                        <div class="fw-semibold text-truncate"><?= sek_esc($s['nama_mapel'] ?: ($s['kode_mapel'] ?: 'Mapel')) ?></div>
                        <div class="small text-muted text-truncate">
                            <i class="bi bi-person-badge me-1"></i><?= sek_esc($s['nama_guru'] ?: 'Tanpa guru') ?>
                        </div>
                        <div class="small text-muted">
                            <i class="bi bi-clock me-1"></i><?= sek_esc(substr((string)$s['waktu_mulai'], 0, 5)) ?><?= $s['waktu_selesai'] ? '-' . sek_esc(substr((string)$s['waktu_selesai'], 0, 5)) : '' ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <?php if (!empty($s['id_jurnal'])): ?>
                            <span class="badge text-bg-<?= $badge[$s['status']] ?? 'secondary' ?>"><?= sek_esc($s['status']) ?></span>
                        <?php else: ?>
                            <span class="badge text-bg-light border text-muted">Belum diisi</span>
                        <?php endif; ?>
                        <i class="bi bi-chevron-right text-muted d-block mt-1"></i>
                    </div>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
