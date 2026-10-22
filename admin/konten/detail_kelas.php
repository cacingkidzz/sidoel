<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
</div>
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("koneksi.php");

// Validasi parameter
$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;
if ($id_kelas <= 0) {
    header("Location: index.php?menu=info_kelas");
    exit;
}

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

// Data kelas
$kelas_info = null;
if ($id_ta_aktif > 0) {
    $q_kelas = $koneksi->prepare("
        SELECT k.*, j.nama_jurusan 
        FROM dt_kelas k
        LEFT JOIN dt_jurusan j ON k.id_jurusan = j.id_jurusan
        WHERE k.id_kelas = ? AND k.id_tahun_ajaran = ?
    ");
    if ($q_kelas) {
        $q_kelas->bind_param("ii", $id_kelas, $id_ta_aktif);
        $q_kelas->execute();
        $r_kelas = $q_kelas->get_result();
        if ($r_kelas->num_rows > 0) {
            $kelas_info = $r_kelas->fetch_assoc();
        }
        $q_kelas->close();
    }
}

if (!$kelas_info) {
    header("Location: index.php?menu=info_kelas");
    exit;
}

// Wali kelas
$wali_kelas_nama = null;
$q_wali = $koneksi->prepare("SELECT g.nama_guru FROM dt_wali_kelas w JOIN dt_guru g ON g.id_guru = w.id_guru WHERE w.id_kelas = ? AND w.id_tahun_ajaran = ?");
if ($q_wali) {
    $q_wali->bind_param("ii", $id_kelas, $id_ta_aktif);
    $q_wali->execute();
    $r_wali = $q_wali->get_result();
    if ($r_wali->num_rows > 0) {
        $wali_kelas_nama = $r_wali->fetch_assoc()['nama_guru'];
    }
    $q_wali->close();
}

// Daftar siswa
$daftar_siswa = [];
$q_sws = $koneksi->prepare("SELECT s.nipd, s.nisn, s.nama, s.jk
                            FROM dt_kelas_siswa ksw
                            INNER JOIN dt_siswa s ON s.id_siswa = ksw.id_siswa
                            WHERE ksw.id_kelas = ? AND ksw.id_tahun_ajaran = ?
                            ORDER BY s.nama ASC");
if ($q_sws) {
    $q_sws->bind_param("ii", $id_kelas, $id_ta_aktif);
    $q_sws->execute();
    $r_sws = $q_sws->get_result();
    while ($s = $r_sws->fetch_assoc()) {
        $daftar_siswa[] = $s;
    }
    $q_sws->close();
}
$total_siswa = count($daftar_siswa);

$badge_class = match ($kelas_info['tingkat']) {
    'X' => 'bg-success',
    'XI' => 'bg-info',
    'XII' => 'bg-warning',
    'XIII' => 'bg-danger',
    default => 'bg-secondary',
};
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">
                        <a href="index.php?menu=info_kelas" class="text-decoration-none text-dark">
                            <i class="bi bi-arrow-left-circle me-2"></i>Detail Kelas: <?= htmlspecialchars($kelas_info['nama_kelas']) ?>
                        </a>
                    </h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item"><a href="index.php?menu=info_kelas">Info Kelas</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Detail</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <?= htmlspecialchars($kelas_info['nama_kelas']) ?>
                                <span class="badge <?= $badge_class ?> ms-2"><?= htmlspecialchars($kelas_info['tingkat']) ?></span>
                            </h5>
                            <div class="d-flex gap-2">
                                <a href="index.php?menu=penempatan_kelas&kelas_asal=<?= $id_kelas ?>#pindah-kelas" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-arrow-left-right me-1"></i>Pindah Kelas
                                </a>
                                <a href="index.php?menu=info_kelas" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i>Kembali
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4 mb-2">
                                    <small class="text-muted d-block">Jurusan</small>
                                    <b><?= htmlspecialchars($kelas_info['nama_jurusan'] ?? 'Tidak Ditemukan') ?></b>
                                </div>
                                <div class="col-md-4 mb-2">
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
                                <div class="col-md-4 mb-2">
                                    <small class="text-muted d-block">Tahun Ajaran</small>
                                    <b><?= $nama_ta_aktif ?></b>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <small class="text-muted d-block">Keterangan</small>
                                    <span><?= htmlspecialchars($kelas_info['keterangan'] ?? '-') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="bi bi-people-fill me-1"></i>Daftar Siswa</h5>
                            <span class="badge bg-secondary"><?= $total_siswa ?> siswa</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped table-hover mb-0">
                                    <thead class="table-primary">
                                        <tr>
                                            <th style="width: 5%;">No</th>
                                            <th style="width: 15%;">NIS</th>
                                            <th style="width: 20%;">NISN</th>
                                            <th>Nama Siswa</th>
                                            <th style="width: 10%;">JK</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($total_siswa > 0): ?>
                                            <?php $no = 1;
                                            foreach ($daftar_siswa as $sws): ?>
                                                <tr>
                                                    <td><?= $no++; ?></td>
                                                    <td><?= htmlspecialchars($sws['nipd'] ?? '-'); ?></td>
                                                    <td><?= htmlspecialchars($sws['nisn'] ?? '-'); ?></td>
                                                    <td><?= htmlspecialchars($sws['nama']); ?></td>
                                                    <td class="text-center">
                                                        <span class="badge <?= (strtoupper($sws['jk'] ?? '') === 'L') ? 'bg-primary' : 'bg-danger'; ?>">
                                                            <?= htmlspecialchars(strtoupper($sws['jk'] ?? '-')); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted fst-italic py-4">Belum ada siswa di kelas ini</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>