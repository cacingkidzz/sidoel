<?php
require_once __DIR__ . '/_init.php';
sek_wajib_login();

$sek = $_SESSION['sekretaris'];
$profil = sek_profil($koneksi);
$id_kelas = (int)$sek['id_kelas'];
$id_ta    = (int)$sek['id_tahun_ajaran'];

$tanggal = sek_valid_tanggal($_GET['tanggal'] ?? null);
$hari_id = sek_hari_id($tanggal);
$jam_ke  = isset($_GET['jam']) ? (int)$_GET['jam'] : 0;
if ($jam_ke <= 0) {
    sek_flash_set('danger', 'Jam pelajaran tidak valid.');
    header('Location: index.php?tanggal=' . $tanggal);
    exit;
}

if (sek_libur_info($koneksi, $id_ta, $tanggal)) {
    sek_flash_set('danger', 'Tanggal ini ditetapkan LIBUR — tidak ada kegiatan pembelajaran.');
    header('Location: index.php?tanggal=' . $tanggal);
    exit;
}

// Ambil sesi dari jadwal
$jadwal = null;
$stmt = $koneksi->prepare("SELECT jd.jam_ke, jd.id_mapel, jd.id_guru,
                                  m.kode_mapel, m.nama_mapel, g.nama_guru,
                                  jp.waktu_mulai, jp.waktu_selesai
                           FROM dt_jadwal jd
                           LEFT JOIN dt_mapel m ON m.id_mapel = jd.id_mapel
                           LEFT JOIN dt_guru g ON g.id_guru = jd.id_guru
                           LEFT JOIN dt_jam_pelajaran jp ON jp.jam_ke = jd.jam_ke
                           WHERE jd.id_kelas = ? AND jd.hari = ? AND jd.jam_ke = ? AND jd.id_tahun_ajaran = ?
                           LIMIT 1");
$stmt->bind_param('isii', $id_kelas, $hari_id, $jam_ke, $id_ta);
$stmt->execute();
$jadwal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$jadwal) {
    sek_flash_set('danger', 'Sesi jam ke-' . $jam_ke . ' tidak ditemukan pada jadwal kelas ini.');
    header('Location: index.php?tanggal=' . $tanggal);
    exit;
}

// Jurnal existing
$jurnal = null;
$stmt = $koneksi->prepare("SELECT * FROM dt_jurnal WHERE id_kelas = ? AND tanggal = ? AND jam_ke = ? AND id_tahun_ajaran = ? LIMIT 1");
$stmt->bind_param('iisi', $id_kelas, $tanggal, $jam_ke, $id_ta);
$stmt->execute();
$jurnal = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Daftar guru untuk pengganti
$guru_list = [];
$qg = $koneksi->query("SELECT id_guru, nama_guru FROM dt_guru ORDER BY nama_guru ASC");
if ($qg) { while ($g = $qg->fetch_assoc()) $guru_list[] = $g; }

$status_opts = ['Mengajar', 'Tidak Hadir', 'Tugas/Kegiatan', 'Kosong'];
$st = $jurnal['status'] ?? 'Mengajar';

$judul = 'Jurnal & Absensi';
$active = '';
require __DIR__ . '/_header.php';
?>
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="index.php?tanggal=<?= sek_esc($tanggal) ?>" class="btn btn-sm btn-light border"><i class="bi bi-arrow-left"></i></a>
    <div>
        <div class="fw-bold">Jam ke-<?= $jam_ke ?> &middot; <?= sek_esc($jadwal['nama_mapel'] ?: $jadwal['kode_mapel']) ?></div>
        <div class="small text-muted"><?= sek_esc($hari_id) ?>, <?= date('d M Y', strtotime($tanggal)) ?></div>
    </div>
</div>

<form method="post" action="simpan_jurnal.php">
    <input type="hidden" name="tanggal" value="<?= sek_esc($tanggal) ?>">
    <input type="hidden" name="jam_ke" value="<?= $jam_ke ?>">

    <div class="card sek-card mb-3">
        <div class="card-body">
            <div class="small text-muted mb-1">Guru pengajar (jadwal)</div>
            <div class="fw-semibold mb-3"><i class="bi bi-person-badge me-1"></i><?= sek_esc($jadwal['nama_guru'] ?: 'Tanpa guru') ?>
                <span class="text-muted small ms-1"><?= sek_esc(substr((string)$jadwal['waktu_mulai'], 0, 5)) ?><?= $jadwal['waktu_selesai'] ? '-' . sek_esc(substr((string)$jadwal['waktu_selesai'], 0, 5)) : '' ?></span>
            </div>

            <label class="form-label small fw-semibold">Materi / pokok bahasan</label>
            <textarea name="materi" class="form-control mb-3" rows="2" placeholder="Contoh: Latihan soal bab 3"><?= sek_esc($jurnal['materi'] ?? '') ?></textarea>

            <label class="form-label small fw-semibold">Status guru mengajar</label>
            <select name="status" class="form-select mb-3" id="statusGuru">
                <?php foreach ($status_opts as $o): ?>
                    <option value="<?= sek_esc($o) ?>" <?= ($st === $o) ? 'selected' : '' ?>><?= sek_esc($o) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="form-label small fw-semibold">Guru pengganti (opsional)</label>
            <select name="id_guru_pengganti" class="form-select mb-3">
                <option value="0">&mdash; tidak ada &mdash;</option>
                <?php foreach ($guru_list as $g): ?>
                    <option value="<?= (int)$g['id_guru'] ?>" <?= ((int)($jurnal['id_guru_pengganti'] ?? 0) === (int)$g['id_guru']) ? 'selected' : '' ?>><?= sek_esc($g['nama_guru']) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="form-label small fw-semibold">Keterangan (opsional)</label>
            <input type="text" name="keterangan" class="form-control" placeholder="Catatan tambahan" value="<?= sek_esc($jurnal['keterangan'] ?? '') ?>">
        </div>
    </div>

    <div class="alert alert-light border small mb-3">
        <i class="bi bi-info-circle me-1"></i>Absensi siswa diisi terpisah — cukup <b>1&times; per hari</b> di menu
        <a href="absensi.php?tanggal=<?= sek_esc($tanggal) ?>" class="fw-semibold">Absensi</a>.
    </div>

    <div class="sticky-save mb-2">
        <button type="submit" class="btn btn-primary w-100 fw-semibold shadow"><i class="bi bi-save me-1"></i>Simpan Jurnal</button>
    </div>
</form>

<?php require __DIR__ . '/_footer.php'; ?>
