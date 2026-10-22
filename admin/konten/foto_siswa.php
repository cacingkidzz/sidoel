<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('koneksi.php');

// ==========================================================
// Tahun ajaran aktif
// ==========================================================
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
$id_ta_aktif = 0;
$nama_ta_aktif = '-';
if ($nama_ta_sesi !== '') {
    $q_ta = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    $q_ta->bind_param("s", $nama_ta_sesi);
    $q_ta->execute();
    $dta = $q_ta->get_result()->fetch_assoc();
    $q_ta->close();
    if ($dta) {
        $id_ta_aktif = (int)$dta['id_tahun_ajaran'];
        $nama_ta_aktif = htmlspecialchars($dta['tahun_ajaran']);
    }
}
if ($id_ta_aktif <= 0) {
    $q_ta = $koneksi->query("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE status = 'aktif' LIMIT 1");
    if ($q_ta) {
        $dta = $q_ta->fetch_assoc();
        $q_ta->close();
        if ($dta) {
            $id_ta_aktif = (int)$dta['id_tahun_ajaran'];
            $nama_ta_aktif = htmlspecialchars($dta['tahun_ajaran']);
        }
    }
}

$foto_dir = '../assets/img/foto_siswa/';
if (!is_dir($foto_dir)) {
    @mkdir($foto_dir, 0755, true);
}

// ==========================================================
// Filter
// ==========================================================
$f_filter = $_GET['filter'] ?? 'semua'; // semua, ada, tidak
$f_cari = trim($_GET['cari'] ?? '');
$where = [];
$params = [];
$types = '';
if ($id_ta_aktif > 0) {
    $where[] = "ks.id_tahun_ajaran = ?";
    $params[] = $id_ta_aktif;
    $types .= 'i';
}
if ($f_filter === 'ada') {
    $where[] = "(s.foto IS NOT NULL AND s.foto <> '' AND s.foto <> 'user.png')";
} elseif ($f_filter === 'tidak') {
    $where[] = "(s.foto IS NULL OR s.foto = '' OR s.foto = 'user.png')";
}
if ($f_cari !== '') {
    $where[] = "(s.nama LIKE ? OR s.nipd LIKE ? OR s.nisn LIKE ?)";
    $params[] = "%$f_cari%";
    $params[] = "%$f_cari%";
    $params[] = "%$f_cari%";
    $types .= 'sss';
}
$sql = "SELECT s.id_siswa, s.nama, s.nipd, s.nisn, s.jk, s.foto, k.tingkat, k.nama_kelas, j.nama_jurusan
        FROM dt_kelas_siswa ks
        INNER JOIN dt_siswa s ON s.id_siswa = ks.id_siswa
        INNER JOIN dt_kelas k ON k.id_kelas = ks.id_kelas
        LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan";
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " GROUP BY s.id_siswa ORDER BY k.tingkat ASC, k.nama_kelas ASC, s.nama ASC";
$stmt = $koneksi->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$siswa_list = [];
while ($r = $res->fetch_assoc()) {
    $siswa_list[] = $r;
}
$stmt->close();

// Statistik
$total = count($siswa_list);
$ada_foto = 0;
$tidak_foto = 0;
foreach ($siswa_list as $s) {
    $foto_file = trim((string)($s['foto'] ?? ''));
    $ada = ($foto_file !== '' && $foto_file !== 'user.png' && file_exists($foto_dir . $foto_file));
    if ($ada) {
        $ada_foto++;
    } else {
        $tidak_foto++;
    }
}

?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Manajemen Foto Siswa (Bulk)</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item"><a href="index.php?menu=siswa_aktif"><i class="bi bi-people-fill"></i> Data Siswa</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Foto Siswa</li>
                    </ol>
                </div>
            </div>
            <p class="small text-muted mt-2">
                Pratinjau foto siswa secara massal, hapus foto, atau reset foto ke placeholder berdasarkan jenis kelamin.
                Foto disimpan di <code>assets/img/foto_siswa/</code>.
                Tahun Ajaran Aktif: <b><?= $nama_ta_aktif; ?></b>
            </p>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">

            <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['alert']['type']); ?> alert-dismissible fade show" role="alert">
                    <?= $_SESSION['alert']['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>

            <?php if ($id_ta_aktif <= 0): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Tahun ajaran aktif tidak ditemukan. Atur terlebih dahulu di menu <b>Data Referensi &rarr; Tahun Ajaran</b>.
                </div>
            <?php else: ?>

                <div class="row mb-3">
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <div class="info-box bg-secondary text-white shadow-sm">
                            <span class="info-box-icon"><i class="bi bi-people-fill"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">TOTAL SISWA</span>
                                <span class="info-box-number"><?= number_format($total); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <div class="info-box bg-success text-white shadow-sm">
                            <span class="info-box-icon"><i class="bi bi-image-fill"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">ADA FOTO</span>
                                <span class="info-box-number"><?= number_format($ada_foto); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <div class="info-box bg-warning text-dark shadow-sm">
                            <span class="info-box-icon"><i class="bi bi-image-off"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">TANPA FOTO</span>
                                <span class="info-box-number"><?= number_format($tidak_foto); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <div class="info-box bg-info text-white shadow-sm">
                            <span class="info-box-icon"><i class="bi bi-folder2-open"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">DIREKTORI</span>
                                <span class="info-box-number"><?= is_dir($foto_dir) ? 'Tersedia' : 'Dibuat'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-outline card-info shadow-sm">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-search me-1"></i> Filter &amp; Aksi Bulk</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-2 align-items-end mb-3">
                            <input type="hidden" name="menu" value="foto_siswa">
                            <div class="col-md-3">
                                <label class="form-label small">Filter Foto</label>
                                <select name="filter" class="form-select form-select-sm">
                                    <option value="semua" <?= ($f_filter === 'semua') ? 'selected' : ''; ?>>Semua</option>
                                    <option value="ada" <?= ($f_filter === 'ada') ? 'selected' : ''; ?>>Ada Foto (<?= $ada_foto; ?>)</option>
                                    <option value="tidak" <?= ($f_filter === 'tidak') ? 'selected' : ''; ?>>Tanpa Foto (<?= $tidak_foto; ?>)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Cari Nama / NIS / NISN</label>
                                <input type="text" name="cari" class="form-control form-control-sm" placeholder="nama, NIS atau NISN" value="<?= htmlspecialchars($f_cari); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Terapkan</button>
                            </div>
                            <div class="col-md-2">
                                <a href="index.php?menu=foto_siswa" class="btn btn-outline-secondary w-100">Reset</a>
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#modalHapusBanyak">
                                    <i class="bi bi-trash me-1"></i> Hapus Banyak
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card card-outline card-primary shadow-sm mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-camera-fill me-1"></i> Daftar Foto Siswa</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table id="myTable" class="table table-bordered table-striped table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:4%;" class="text-center"><input type="checkbox" id="selectAll"></th>
                                        <th style="width:12%;">Foto</th>
                                        <th style="width:12%;">NIS</th>
                                        <th>Nama</th>
                                        <th style="width:10%;">JK</th>
                                        <th style="width:16%;">Kelas</th>
                                        <th style="width:10%;">Status File</th>
                                        <th style="width:12%;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    if (!empty($siswa_list)):
                                        foreach ($siswa_list as $s):
                                            $id_siswa = (int)$s['id_siswa'];
                                            $nama = htmlspecialchars(trim($s['nama'] ?? '-'));
                                            $nipd = htmlspecialchars(trim($s['nipd'] ?? '-'));
                                            $nisn = htmlspecialchars(trim($s['nisn'] ?? '-'));
                                            $jk = $s['jk'] ?? '';
                                            $jk_icon = ($jk === 'P') ? '<i class="bi bi-gender-female text-danger" title="Perempuan"></i>' : '<i class="bi bi-gender-male text-primary" title="Laki-laki"></i>';
                                            $kelas = htmlspecialchars(trim(($s['tingkat'] ?? '') . ' ' . ($s['nama_kelas'] ?? '')) . ($s['nama_jurusan'] ? ' (' . $s['nama_jurusan'] . ')' : ''));
                                            $foto_db = trim((string)($s['foto'] ?? ''));
                                            $foto_exists = ($foto_db !== '' && $foto_db !== 'user.png' && file_exists($foto_dir . $foto_db));
                                            $foto_url = $foto_exists ? 'assets/img/foto_siswa/' . htmlspecialchars($foto_db) : ('assets/img/' . (($jk === 'P') ? 'wanita.png' : 'pria.png'));
                                            $status_file = $foto_exists ? '<span class="badge bg-success">Ada</span>' : '<span class="badge bg-secondary">Placeholder</span>';
                                    ?>
                                    <tr>
                                        <td class="text-center"><input type="checkbox" name="ids[]" value="<?= $id_siswa; ?>" class="rowCheck"></td>
                                        <td>
                                            <img src="<?= htmlspecialchars($foto_url); ?>" alt="Foto" class="img-thumbnail" style="width:48px;height:48px;object-fit:cover;">
                                        </td>
                                        <td><?= $nipd; ?></td>
                                        <td><?= $nama; ?></td>
                                        <td class="text-center"><?= $jk_icon; ?></td>
                                        <td><?= $kelas; ?></td>
                                        <td class="text-center"><?= $status_file; ?></td>
                                        <td class="text-center">
                                            <?php if ($foto_exists): ?>
                                                <form method="POST" action="aksi/aksi_foto_siswa.php" class="d-inline"
                                                    onsubmit="return confirm('Hapus foto siswa <?= addslashes($nama); ?>?');">
                                                    <input type="hidden" name="aksi" value="hapus_foto">
                                                    <input type="hidden" name="id_siswa" value="<?= $id_siswa; ?>">
                                                    <input type="hidden" name="foto_file" value="<?= htmlspecialchars($foto_db); ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Hapus Foto">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Tidak ada foto">
                                                    <i class="bi bi-image-off"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php
                                        $no++;
                                        endforeach;
                                    else:
                                    ?>
                                    <tr><td colspan="8" class="text-center text-muted py-3"><i class="bi bi-inbox me-2"></i>Tidak ada data siswa.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Modal Hapus Banyak Foto -->
<div class="modal fade" id="modalHapusBanyak" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="aksi/aksi_foto_siswa.php" method="post" onsubmit="return confirm('Yakin ingin menghapus foto siswa yang terpilih?');">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-trash me-1"></i> Hapus Foto Banyak Siswa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="aksi" value="hapus_banyak">
                    <p class="small text-muted">Foto file akan dihapus dari direktori dan kolom foto di database dikosongkan (diganti placeholder).</p>
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Pilih siswa dari tabel di atas terlebih dahulu (centang kotak di kolom pertama).
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i> Hapus Foto Terpilih</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Reset Foto ke Placeholder -->
<div class="modal fade" id="modalResetFoto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="aksi/aksi_foto_siswa.php" method="post" onsubmit="return confirm('Yakin ingin mereset foto siswa yang terpilih ke placeholder?');">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-arrow-repeat me-1"></i> Reset Foto ke Placeholder</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="aksi" value="reset_foto">
                    <p class="small text-muted">Foto file akan dihapus dan kolom foto di database diisi <b>user.png</b> (tampilan akan menggunakan pria.png/wanita.png sesuai jenis kelamin).</p>
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Pilih siswa dari tabel di atas terlebih dahulu.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-arrow-repeat me-1"></i> Reset Foto Terpilih</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var all = document.getElementById('selectAll');
    var boxes = document.querySelectorAll('.rowCheck');
    if (!all || !boxes.length) return;

    all.addEventListener('change', function () {
        boxes.forEach(function (b) { b.checked = all.checked; });
    });
    boxes.forEach(function (b) {
        b.addEventListener('change', function () {
            if (!b.checked) all.checked = false;
        });
    });
});
</script>
