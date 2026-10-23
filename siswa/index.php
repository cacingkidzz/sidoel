<?php
require_once __DIR__ . '/_init.php';
siswa_wajib_login();

if (!function_exists('ss_val')) {
    function ss_val($v): string
    {
        $v = trim((string)$v);
        return $v !== '' ? siswa_esc($v) : '<span class="text-muted">&mdash;</span>';
    }
}

$profil = siswa_profil_sekolah($koneksi);
$id_siswa = (int)$_SESSION['siswa']['id_siswa'];
$s = siswa_data($koneksi, $id_siswa);
if (!$s) {
    unset($_SESSION['siswa']);
    header('Location: login.php');
    exit;
}
$koord = siswa_koordinat($s['lintang'] ?? null, $s['bujur'] ?? null);
$_SESSION['siswa']['nama'] = $s['nama'];

$id_ta = (int)$s['id_tahun_ajaran'];
$kelas = siswa_kelas($koneksi, $id_siswa, $id_ta);
$kelas_label = siswa_kelas_label($kelas);
$ta_label = siswa_ta($koneksi, $id_ta);
$foto_url = siswa_foto_url($s);
$kel = kel_baris($s);

$judul = 'Profil Saya';
$active = 'profil';
$siswa = $s;
require __DIR__ . '/_header.php';
?>
<div class="card ss-card mb-2">
    <div class="card-body">
        <div class="d-flex gap-3 align-items-center">
            <img src="<?= siswa_esc($foto_url); ?>" alt="Foto" class="ss-photo">
            <div style="min-width:0;">
                <div class="fw-bold" style="font-size:1.02rem;"><?= siswa_esc($s['nama']); ?></div>
                <div class="text-muted small mb-1"><?= siswa_esc($kelas_label); ?></div>
                <div class="d-flex flex-wrap gap-1">
                    <span class="badge bg-primary">NIS <?= siswa_esc($s['nipd'] ?: '-'); ?></span>
                    <span class="badge bg-light text-dark">NISN <?= siswa_esc($s['nisn'] ?: '-'); ?></span>
                </div>
            </div>
        </div>
        <div class="row row-cols-2 g-2 mt-3">
            <div class="col">
                <div class="ss-label">Tahun Ajaran</div>
                <div class="ss-value"><?= siswa_esc($ta_label); ?></div>
            </div>
            <div class="col">
                <div class="ss-label">Wali Kelas</div>
                <div class="ss-value"><?= ss_val($kelas['wali'] ?? ''); ?></div>
            </div>
        </div>
        <a href="edit.php" class="btn btn-primary btn-sm w-100 mt-3">
            <i class="bi bi-pencil-square me-1"></i>Ubah Data Saya
        </a>
    </div>
</div>

<div class="card ss-card mb-2">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="ss-section-title"><i class="bi bi-clipboard2-check me-1"></i>Kelengkapan Data</div>
            <span class="badge bg-<?= kel_cls($kel['pct']); ?>"><?= (int)$kel['filled']; ?>/<?= (int)$kel['total']; ?> wajib</span>
        </div>
        <div class="d-flex justify-content-between align-items-end mb-1">
            <div class="fs-3 fw-bold text-<?= kel_cls($kel['pct']); ?>"><?= number_format($kel['pct'], 1, ',', '.'); ?>%</div>
            <div class="small text-muted">
                <?php if ($kel['lengkap']) : ?>
                    Semua data wajib sudah terisi
                <?php else : ?>
                    <?= count($kel['missing']); ?> field wajib belum diisi
                <?php endif; ?>
            </div>
        </div>
        <div class="progress mb-2" style="height:8px;">
            <div class="progress-bar bg-<?= kel_cls($kel['pct']); ?>" style="width:<?= max(0, min(100, $kel['pct'])); ?>%"></div>
        </div>
        <?php if ($kel['lengkap']) : ?>
            <div class="alert alert-success py-2 px-3 mb-0 small"><i class="bi bi-check2-circle me-1"></i>Data profil Anda sudah lengkap. Terima kasih.</div>
        <?php else : ?>
            <div class="small text-muted mb-2">Lengkapi field berikut:</div>
            <div class="d-flex flex-wrap gap-1 mb-2">
                <?php foreach ($kel['missing'] as $ml) : ?>
                    <span class="badge bg-danger-subtle text-danger fw-normal"><?= siswa_esc($ml); ?></span>
                <?php endforeach; ?>
            </div>
            <a href="edit.php" class="btn btn-outline-primary btn-sm w-100">
                <i class="bi bi-pencil-square me-1"></i>Lengkapi Data Sekarang
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card ss-card mb-2">
    <div class="card-body">
        <div class="ss-section-title mb-2"><i class="bi bi-person-badge me-1"></i>Identitas</div>
        <div class="row row-cols-2 g-2">
            <div class="col"><div class="ss-label">Jenis Kelamin</div><div class="ss-value"><?= ss_val($s['jk'] === 'L' ? 'Laki-laki' : ($s['jk'] === 'P' ? 'Perempuan' : $s['jk'])); ?></div></div>
            <div class="col"><div class="ss-label">Agama</div><div class="ss-value"><?= ss_val($s['agama']); ?></div></div>
            <div class="col"><div class="ss-label">Tempat Lahir</div><div class="ss-value"><?= ss_val($s['tempat_lahir']); ?></div></div>
            <div class="col"><div class="ss-label">Tanggal Lahir</div><div class="ss-value"><?= siswa_esc(siswa_tanggal_id($s['tanggal_lahir'])); ?></div></div>
            <div class="col"><div class="ss-label">NIK</div><div class="ss-value"><?= ss_val($s['nik']); ?></div></div>
            <div class="col"><div class="ss-label">No. KK</div><div class="ss-value"><?= ss_val($s['no_kk']); ?></div></div>
            <div class="col"><div class="ss-label">Sekolah Asal</div><div class="ss-value"><?= ss_val($s['sekolah_asal']); ?></div></div>
            <div class="col"><div class="ss-label">Anak Ke</div><div class="ss-value"><?= ss_val($s['anak_ke_berapa']); ?></div></div>
            <div class="col"><div class="ss-label">Jumlah Saudara</div><div class="ss-value"><?= ss_val($s['jml_saudara_kandung']); ?></div></div>
        </div>
    </div>
</div>

<div class="card ss-card mb-2">
    <div class="card-body">
        <div class="ss-section-title mb-2"><i class="bi bi-geo-alt me-1"></i>Kontak &amp; Alamat</div>
        <div class="row row-cols-2 g-2">
            <div class="col"><div class="ss-label">No. HP</div><div class="ss-value"><?= ss_val($s['hp']); ?></div></div>
            <div class="col"><div class="ss-label">Telepon</div><div class="ss-value"><?= ss_val($s['telp']); ?></div></div>
            <div class="col-12"><div class="ss-label">Email</div><div class="ss-value"><?= ss_val($s['email']); ?></div></div>
            <div class="col-12"><div class="ss-label">Alamat</div><div class="ss-value"><?= ss_val($s['alamat']); ?></div></div>
            <div class="col"><div class="ss-label">RT/RW</div><div class="ss-value"><?= ss_val($s['rt_rw']); ?></div></div>
            <div class="col"><div class="ss-label">Dusun</div><div class="ss-value"><?= ss_val($s['dusun']); ?></div></div>
            <div class="col"><div class="ss-label">Kelurahan</div><div class="ss-value"><?= ss_val($s['kelurahan']); ?></div></div>
            <div class="col"><div class="ss-label">Kecamatan</div><div class="ss-value"><?= ss_val($s['kecamatan']); ?></div></div>
            <div class="col"><div class="ss-label">Kode Pos</div><div class="ss-value"><?= ss_val($s['kode_pos']); ?></div></div>
            <div class="col"><div class="ss-label">Jenis Tinggal</div><div class="ss-value"><?= ss_val($s['jenis_tinggal']); ?></div></div>
            <div class="col"><div class="ss-label">Transportasi</div><div class="ss-value"><?= ss_val($s['transportasi']); ?></div></div>
            <div class="col"><div class="ss-label">Jarak ke Sekolah</div><div class="ss-value"><?= $s['jarak_rumah_ke_sekolah_km'] !== null && $s['jarak_rumah_ke_sekolah_km'] !== '' ? siswa_esc($s['jarak_rumah_ke_sekolah_km']) . ' km' : '<span class="text-muted">&mdash;</span>'; ?></div></div>
            <div class="col"><div class="ss-label">Titik Lokasi</div><div class="ss-value"><?php if ($koord): ?><a href="https://www.google.com/maps?q=<?= siswa_esc($koord['lat'] . ',' . $koord['lng']); ?>" target="_blank" rel="noopener"><i class="bi bi-geo-alt me-1"></i>Lihat di Google Maps</a><?php else: ?><span class="text-muted">&mdash;</span><?php endif; ?></div></div>
        </div>
    </div>
</div>

<div class="card ss-card mb-2">
    <div class="card-body">
        <div class="ss-section-title mb-2"><i class="bi bi-heart-pulse me-1"></i>Data Fisik</div>
        <div class="row row-cols-2 g-2">
            <div class="col"><div class="ss-label">Berat Badan</div><div class="ss-value"><?= $s['berat_badan'] !== null && $s['berat_badan'] !== '' ? siswa_esc($s['berat_badan']) . ' kg' : '<span class="text-muted">&mdash;</span>'; ?></div></div>
            <div class="col"><div class="ss-label">Tinggi Badan</div><div class="ss-value"><?= $s['tinggi_badan'] !== null && $s['tinggi_badan'] !== '' ? siswa_esc($s['tinggi_badan']) . ' cm' : '<span class="text-muted">&mdash;</span>'; ?></div></div>
        </div>
    </div>
</div>

<div class="card ss-card mb-2">
    <div class="card-body">
        <div class="ss-section-title mb-2"><i class="bi bi-people me-1"></i>Orang Tua / Wali</div>
        <?php
        $orang = [
            'Ayah' => ['nama_ayah', 'tahun_lahir_ayah', 'pendidikan_ayah', 'pekerjaan_ayah', 'penghasilan_ayah'],
            'Ibu' => ['nama_ibu', 'tahun_lahir_ibu', 'pendidikan_ibu', 'pekerjaan_ibu', 'penghasilan_ibu'],
            'Wali' => ['nama_wali', 'tahun_lahir_wali', 'pendidikan_wali', 'pekerjaan_wali', 'penghasilan_wali'],
        ];
        foreach ($orang as $label => $keys): ?>
            <div class="border rounded-3 p-2 mb-2">
                <div class="fw-semibold small mb-1"><?= $label; ?></div>
                <div class="row row-cols-2 g-1">
                    <div class="col-12"><div class="ss-label">Nama</div><div class="ss-value"><?= ss_val($s[$keys[0]]); ?></div></div>
                    <div class="col"><div class="ss-label">Tahun Lahir</div><div class="ss-value"><?= ss_val($s[$keys[1]]); ?></div></div>
                    <div class="col"><div class="ss-label">Pendidikan</div><div class="ss-value"><?= ss_val($s[$keys[2]]); ?></div></div>
                    <div class="col"><div class="ss-label">Pekerjaan</div><div class="ss-value"><?= ss_val($s[$keys[3]]); ?></div></div>
                    <div class="col"><div class="ss-label">Penghasilan</div><div class="ss-value"><?= ss_val($s[$keys[4]]); ?></div></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
