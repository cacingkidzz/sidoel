<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
</div>
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("koneksi.php");

// Ambil Tahun Ajaran aktif dari session
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? 'Tahun Ajaran Tidak Ditemukan';
$id_ta_aktif = 0;
$nama_ta_aktif = htmlspecialchars($nama_ta_sesi);

if (!$koneksi->connect_error) {
    $q_ta_aktif = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    if ($q_ta_aktif) {
        $q_ta_aktif->bind_param("s", $nama_ta_sesi);
        $q_ta_aktif->execute();
        $result_ta_aktif = $q_ta_aktif->get_result();
        if ($result_ta_aktif->num_rows > 0) {
            $TA_AKTIF = $result_ta_aktif->fetch_assoc();
            $id_ta_aktif = $TA_AKTIF['id_tahun_ajaran'];
            $nama_ta_aktif = htmlspecialchars($TA_AKTIF['tahun_ajaran']);
        }
        $q_ta_aktif->close();
    }
}

// Data master jurusan
$jurusan_data = [];
$q_jurusan = $koneksi->query("SELECT id_jurusan, nama_jurusan FROM dt_jurusan ORDER BY nama_jurusan ASC");
if ($q_jurusan) {
    while ($j = $q_jurusan->fetch_assoc()) {
        $jurusan_data[$j['id_jurusan']] = htmlspecialchars($j['nama_jurusan']);
    }
}

// Preload: Wali Kelas per kelas (TA aktif)
$wali_kelas_map = [];
if ($id_ta_aktif > 0) {
    $q_wali = $koneksi->prepare("SELECT w.id_kelas, g.nama_guru FROM dt_wali_kelas w JOIN dt_guru g ON g.id_guru = w.id_guru WHERE w.id_tahun_ajaran = ?");
    if ($q_wali) {
        $q_wali->bind_param("i", $id_ta_aktif);
        $q_wali->execute();
        $r_wali = $q_wali->get_result();
        while ($w = $r_wali->fetch_assoc()) {
            $wali_kelas_map[$w['id_kelas']] = $w['nama_guru'];
        }
        $q_wali->close();
    }
}

// Preload: Jumlah siswa per kelas (TA aktif)
$jumlah_siswa_map = [];
if ($id_ta_aktif > 0) {
    $q_jml = $koneksi->prepare("SELECT ksw.id_kelas, COUNT(*) as jml 
                                FROM dt_kelas_siswa ksw
                                INNER JOIN dt_siswa s ON s.id_siswa = ksw.id_siswa
                                WHERE ksw.id_tahun_ajaran = ?
                                GROUP BY ksw.id_kelas");
    if ($q_jml) {
        $q_jml->bind_param("i", $id_ta_aktif);
        $q_jml->execute();
        $r_jml = $q_jml->get_result();
        while ($j = $r_jml->fetch_assoc()) {
            $jumlah_siswa_map[$j['id_kelas']] = $j['jml'];
        }
        $q_jml->close();
    }
}

// Query data kelas untuk daftar
$daftar_kelas = [];
if ($id_ta_aktif > 0) {
    $q_kelas = $koneksi->query("
        SELECT k.*, j.nama_jurusan 
        FROM dt_kelas k
        LEFT JOIN dt_jurusan j ON k.id_jurusan = j.id_jurusan
        WHERE k.id_tahun_ajaran = '$id_ta_aktif'
        ORDER BY 
            CASE k.tingkat
                WHEN 'X' THEN 1
                WHEN 'XI' THEN 2
                WHEN 'XII' THEN 3
                WHEN 'XIII' THEN 4
                ELSE 5
            END, 
            k.nama_kelas ASC
    ");
    if ($q_kelas) {
        while ($row = $q_kelas->fetch_assoc()) {
            $daftar_kelas[] = $row;
        }
    }
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Informasi Kelas / Rombel</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Info Kelas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="alert alert-info py-2 mb-3 shadow-sm">
                <i class="bi bi-info-circle-fill me-1"></i> Klik card kelas untuk melihat daftar siswa. Tahun Ajaran: <b><?= $nama_ta_aktif ?></b>.
            </div>

            <?php if (!empty($daftar_kelas)): ?>
                <div class="d-flex justify-content-end mb-3">
                    <a href="aksi/export_kelas.php" class="btn btn-success">
                        <i class="bi bi-file-earmark-arrow-down-fill me-1"></i> Download Excel Kelas (Absensi)
                    </a>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    <div class="row mb-3">
                        <div class="col-12">
                            <?php if (isset($_SESSION['alert'])): ?>
                                <div class="alert alert-<?= $_SESSION['alert']['type']; ?> alert-dismissible fade show" role="alert">
                                    <?= $_SESSION['alert']['msg']; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php unset($_SESSION['alert']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (empty($daftar_kelas)): ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-door-closed text-muted" style="font-size: 3rem;"></i>
                                <h5 class="text-muted mt-3">Belum ada data kelas</h5>
                                <p class="text-muted">Silakan tambah kelas terlebih dahulu di menu <a href="index.php?menu=kelas">Data Kelas/Rombel</a>.</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($daftar_kelas as $row):
                        $badge_class = match ($row['tingkat']) {
                            'X' => 'bg-success',
                            'XI' => 'bg-info',
                            'XII' => 'bg-warning',
                            'XIII' => 'bg-danger',
                            default => 'bg-secondary',
                        };
                        $wali_kelas_nama = $wali_kelas_map[$row['id_kelas']] ?? null;
                        $total_siswa_kelas = $jumlah_siswa_map[$row['id_kelas']] ?? 0;
                        $detail_url = "index.php?menu=detail_kelas&id_kelas={$row['id_kelas']}";
                    ?>
                        <div class="col-xl-4 col-md-6 mb-3">
                            <a href="<?= $detail_url ?>" class="text-decoration-none text-dark">
                                <div class="card card-outline card-primary h-100 shadow-sm hover-shadow">
                                    <div class="card-body d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title mb-0">
                                                <?= htmlspecialchars($row['nama_kelas']) ?>
                                                <span class="badge <?= $badge_class ?> ms-2"><?= htmlspecialchars($row['tingkat']) ?></span>
                                            </h5>
                                        </div>
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Jurusan</small>
                                            <b><?= htmlspecialchars($row['nama_jurusan'] ?? 'Tidak Ditemukan') ?></b>
                                        </div>
                                        <div class="mb-2">
                                            <small class="text-muted d-block">Wali Kelas</small>
                                            <?php if ($wali_kelas_nama): ?>
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-person-badge text-primary me-1"></i>
                                                    <span><?= htmlspecialchars($wali_kelas_nama) ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic">Belum ditetapkan</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-auto pt-2 border-top">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><i class="bi bi-people-fill me-1"></i>Jumlah Siswa</small>
                                                <span class="badge bg-secondary fs-6"><?= $total_siswa_kelas ?> siswa</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-light text-center text-muted small">
                                        <i class="bi bi-arrow-right-circle me-1"></i>Klik untuk lihat daftar siswa
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<style>
    .hover-shadow:hover {
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15) !important;
        transform: translateY(-2px);
        transition: all 0.2s ease;
    }
</style>