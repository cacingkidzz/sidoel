<?php
require_once __DIR__ . '/_init.php';
guru_wajib_login();

$profil = guru_profil($koneksi);
$guru = $_SESSION['guru'];
$id_guru = (int)($guru['id_guru'] ?? 0);
$ta = guru_ta_aktif($koneksi);
$id_ta = $ta['id'];
$nama_ta = $ta['nama'];

$hari_urutan = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$jadwal = [];
$stmt = $koneksi->prepare("SELECT jd.hari, jd.jam_ke, jd.id_kelas, m.kode_mapel, m.nama_mapel,
                                  k.nama_kelas, k.tingkat, j.nama_jurusan,
                                  jp.waktu_mulai, jp.waktu_selesai
                           FROM dt_jadwal jd
                           INNER JOIN dt_kelas k ON k.id_kelas = jd.id_kelas
                           LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                           INNER JOIN dt_mapel m ON m.id_mapel = jd.id_mapel
                           LEFT JOIN dt_jam_pelajaran jp ON jp.jam_ke = jd.jam_ke
                           WHERE jd.id_guru = ? AND jd.id_tahun_ajaran = ?
                           ORDER BY FIELD(jd.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'), jd.jam_ke ASC");
if ($stmt) {
    $stmt->bind_param('ii', $id_guru, $id_ta);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $jadwal[$r['hari']][] = $r;
    $stmt->close();
}

$total_jam = 0;
foreach ($jadwal as $rows) $total_jam += count($rows);

$judul = 'Jadwal Mengajar';
$active = 'jadwal';
require __DIR__ . '/_header.php';
?>

<div class="card gr-card mb-2">
    <div class="card-body py-2 d-flex justify-content-between align-items-center">
        <div class="small text-muted"><i class="bi bi-calendar-week me-1"></i>T.A. <b><?= guru_esc($nama_ta); ?></b></div>
        <span class="badge text-bg-primary"><?= $total_jam; ?> jam/minggu</span>
    </div>
</div>

<?php if (empty($jadwal)): ?>
    <div class="card gr-card">
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
            Belum ada jadwal mengajar pada T.A. <b><?= guru_esc($nama_ta); ?></b>.
        </div>
    </div>
<?php else: ?>
    <?php foreach ($hari_urutan as $hari): ?>
        <?php if (empty($jadwal[$hari])) continue; ?>
        <div class="card gr-card mb-2">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <span class="fw-semibold" style="font-size:.88rem;">
                    <i class="bi bi-calendar-day text-primary me-1"></i><?= guru_esc($hari); ?>
                </span>
                <span class="badge text-bg-light border"><?= count($jadwal[$hari]); ?> jam</span>
            </div>
            <div class="card-body p-0">
                <?php foreach ($jadwal[$hari] as $j): ?>
                    <div class="d-flex gap-2 align-items-start p-2 border-bottom">
                        <div class="jam-badge"><?= (int)$j['jam_ke']; ?><small>JAM</small></div>
                        <div class="flex-grow-1" style="min-width:0;">
                            <div class="small fw-semibold text-truncate"><?= guru_esc($j['kode_mapel'] ?: $j['nama_mapel']); ?></div>
                            <div class="small text-muted text-truncate"><?= guru_esc(guru_kelas_label($j)); ?></div>
                            <?php if (!empty($j['waktu_mulai'])): ?>
                                <div class="text-muted" style="font-size:.68rem;">
                                    <i class="bi bi-clock me-1"></i><?= substr($j['waktu_mulai'], 0, 5); ?><?= !empty($j['waktu_selesai']) ? '–' . substr($j['waktu_selesai'], 0, 5) : ''; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="absensi.php?kelas=<?= (int)$j['id_kelas']; ?>" class="btn btn-sm btn-outline-primary py-0 flex-shrink-0">Absensi</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
