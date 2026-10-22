<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
</div>
<?php
// ==========================================================
// 1. Tahun ajaran aktif (dari session)
// ==========================================================
$ta_nama_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
$id_ta_sesi = 0;
if ($ta_nama_sesi !== '') {
    $q_sesi = $koneksi->prepare("SELECT id_tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    $q_sesi->bind_param("s", $ta_nama_sesi);
    $q_sesi->execute();
    $r_sesi = $q_sesi->get_result()->fetch_assoc();
    $q_sesi->close();
    if ($r_sesi) $id_ta_sesi = (int)$r_sesi['id_tahun_ajaran'];
}

// TA yang dipilih filter (default = aktif dari session)
$id_ta_filter = (int)($_GET['ta'] ?? $id_ta_sesi);
$id_ta_aktif = 0;
$nama_ta_aktif = '-';
if ($id_ta_filter > 0) {
    $q_ta = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE id_tahun_ajaran = ? LIMIT 1");
    $q_ta->bind_param("i", $id_ta_filter);
    $q_ta->execute();
    $row_ta = $q_ta->get_result()->fetch_assoc();
    $q_ta->close();
    if ($row_ta) {
        $id_ta_aktif = (int)$row_ta['id_tahun_ajaran'];
        $nama_ta_aktif = htmlspecialchars($row_ta['tahun_ajaran']);
    }
}

// ==========================================================
// Daftar semua tahun ajaran (untuk filter dropdown)
// ==========================================================
$ta_list = [];
$q_ta_all = $koneksi->query("SELECT id_tahun_ajaran, tahun_ajaran, status FROM dt_tahun_ajar ORDER BY tahun_ajaran DESC");
if ($q_ta_all) {
    while ($rt = $q_ta_all->fetch_assoc()) {
        $ta_list[] = $rt;
    }
}

// ==========================================================
// 2. STATISTIK CALON SISWA DITERIMA (STATUS 4) per TA terpilih
// ==========================================================
$total_diterima = $sudah_ditarik = $belum_ditarik = 0;

if ($id_ta_aktif > 0) {
    $q_stat = $koneksi->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN EXISTS(SELECT 1 FROM dt_siswa s WHERE s.nisn = ppdb_calon_siswa.nisn AND s.id_tahun_ajaran = ?) THEN 1 ELSE 0 END) AS ditarik
         FROM ppdb_calon_siswa
         WHERE status = 4 AND tahun_ajaran = ?"
    );
    $q_stat->bind_param("ii", $id_ta_aktif, $id_ta_aktif);
    $q_stat->execute();
    $d_stat = $q_stat->get_result()->fetch_assoc();
    $q_stat->close();
    if ($d_stat) {
        $total_diterima = (int)$d_stat['total'];
        $sudah_ditarik = (int)$d_stat['ditarik'];
        $belum_ditarik = $total_diterima - $sudah_ditarik;
    }
}

// ==========================================================
// 3. DAFTAR CALON SISWA DITERIMA per TA terpilih
// ==========================================================
$daftar_calon = [];
if ($id_ta_aktif > 0) {
    $q_calon = $koneksi->prepare(
        "SELECT cs.*,
            EXISTS(SELECT 1 FROM dt_siswa s WHERE s.nisn = cs.nisn AND s.id_tahun_ajaran = ?) AS sudah_ditarik
         FROM ppdb_calon_siswa cs
         WHERE cs.status = 4 AND cs.tahun_ajaran = ?
         ORDER BY cs.nama_siswa ASC"
    );
    $q_calon->bind_param("ii", $id_ta_aktif, $id_ta_aktif);
    $q_calon->execute();
    $res = $q_calon->get_result();
    while ($row = $res->fetch_assoc()) {
        $daftar_calon[] = $row;
    }
    $q_calon->close();
}
?>

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <h2 class="mb-0">Tarik Siswa PPDB</h2>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                    <li class="breadcrumb-item"><a href="#!">Manajemen PPDB</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Tarik Siswa</li>
                </ol>
            </div>
        </div>
        <p class="small text-muted mt-2">
            Daftar calon siswa <b>DITERIMA</b> (status 4) dari data PPDB yang siap ditarik menjadi siswa aktif.
            Tahun Ajaran: <b><?= $nama_ta_aktif; ?></b>
        </p>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">

        <?php if ($id_ta_aktif <= 0) : ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                Tahun ajaran aktif tidak ditemukan. Atur terlebih dahulu di menu <b>Data Referensi &rarr; Tahun Ajaran</b>.
            </div>
        <?php else : ?>

            <div class="alert alert-info py-2 mb-3 shadow-sm">
                <i class="bi bi-info-circle-fill me-1"></i>
                Calon siswa PPDB ditampilkan <b>per tahun ajaran</b>. Pilih tahun ajaran:
                <form method="get" class="d-inline" style="margin:0;">
                    <input type="hidden" name="menu" value="tarik_siswa_ppdb">
                    <select name="ta" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                        <?php foreach ($ta_list as $t): ?>
                            <option value="<?= (int)$t['id_tahun_ajaran']; ?>" <?= ($id_ta_filter == (int)$t['id_tahun_ajaran']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($t['tahun_ajaran']); ?> <?= $t['status'] === 'aktif' ? '<span class="badge bg-success text-xs ms-1">Aktif</span>' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                Terpilih: <b><?= $nama_ta_aktif; ?></b>.
            </div>

            <div class="row">
                <div class="col-lg-4 col-md-6 col-sm-12">
                    <div class="info-box bg-success text-white shadow-sm">
                        <span class="info-box-icon"><i class="bi bi-person-check-fill"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">TOTAL DITERIMA (STATUS 4)</span>
                            <span class="info-box-number"><?= number_format($total_diterima); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 col-sm-12">
                    <div class="info-box bg-primary text-white shadow-sm">
                        <span class="info-box-icon"><i class="bi bi-download"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">SUDAH DITARIK</span>
                            <span class="info-box-number"><?= number_format($sudah_ditarik); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 col-sm-12">
                    <div class="info-box bg-warning text-dark shadow-sm">
                        <span class="info-box-icon"><i class="bi bi-hourglass-split"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">BELUM DITARIK</span>
                            <span class="info-box-number"><?= number_format($belum_ditarik); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <hr class="mb-3 mt-1">

            <div class="row">
                <div class="col-xl-12 col-md-12">
                    <div class="card card-outline card-success">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-person-vcard me-1"></i> Calon Siswa Diterima (Status 4)</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                                <form method="POST" action="aksi/tarik_ppdb.php" class="d-inline"
                                    onsubmit="return confirm('Tarik SEMUA calon siswa yang belum ditarik menjadi siswa aktif Tahun Ajaran <?= $nama_ta_aktif; ?>?');">
                                    <input type="hidden" name="id_ta" value="<?= (int)$id_ta_aktif; ?>">
                                    <button type="submit" name="tarik_semua" class="btn btn-success" <?= ($belum_ditarik <= 0) ? 'disabled' : ''; ?>>
                                        <i class="bi bi-cloud-download-fill me-1"></i> Tarik Semua Yang Belum (<?= number_format($belum_ditarik); ?>)
                                    </button>
                                </form>
                                <a href="index.php?menu=siswa_aktif" class="btn btn-outline-primary">
                                    <i class="bi bi-people-fill me-1"></i> Lihat Data Siswa Aktif
                                </a>
                            </div>

                            <div class="table-responsive">
                                <table id="myTable" class="table table-bordered table-striped table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>NISN</th>
                                            <th>Nama Calon Siswa</th>
                                            <th>JK</th>
                                            <th>Tempat, Tgl Lahir</th>
                                            <th>Asal Sekolah</th>
                                            <th>Pilihan Jurusan</th>
                                            <th>No. HP</th>
                                            <th>Status Tarik</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $no = 1;
                                        if (!empty($daftar_calon)) {
                                            foreach ($daftar_calon as $row) {
                                                $id_calon = (int)$row['id_calon'];
                                                $nisn = trim($row['nisn'] ?? '');
                                                $nama = htmlspecialchars(trim($row['nama_siswa'] ?? '-'));
                                                $jk = $row['jenis_kelamin'] ?? '';
                                                $jk_icon = ($jk == 'P')
                                                    ? '<i class="bi bi-gender-female text-danger fs-5" title="Perempuan"></i>'
                                                    : '<i class="bi bi-gender-male text-primary fs-5" title="Laki-laki"></i>';
                                                $ttl = htmlspecialchars(trim($row['tempat_lahir'] ?? '-'))
                                                    . ', ' . date('d-m-Y', strtotime($row['tgl_lahir']));
                                                $asal = htmlspecialchars(trim($row['asal_sekolah'] ?? '-'));
                                                if ($asal === '') $asal = '-';
                                                $jurusan = htmlspecialchars(trim($row['pilihan1'] ?? '-'));
                                                $hp = htmlspecialchars(trim($row['no_telp'] ?? '-'));
                                                if ($hp === '') $hp = '-';
                                                $ditarik = (int)$row['sudah_ditarik'];
                                        ?>
                                                <tr>
                                                    <td><?= $no++; ?></td>
                                                    <td><?= htmlspecialchars($nisn); ?></td>
                                                    <td><?= $nama; ?></td>
                                                    <td class="text-center"><?= $jk_icon; ?></td>
                                                    <td><?= $ttl; ?></td>
                                                    <td><?= $asal; ?></td>
                                                    <td><span class="badge bg-secondary"><?= $jurusan; ?></span></td>
                                                    <td><?= $hp; ?></td>
                                                    <td>
                                                        <?php if ($ditarik) : ?>
                                                            <span class="badge bg-primary"><i class="bi bi-check-circle me-1"></i>Sudah Ditarik</span>
                                                        <?php else : ?>
                                                            <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Belum</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($ditarik) : ?>
                                                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled
                                                                title="Siswa ini sudah menjadi siswa aktif">
                                                                <i class="bi bi-check-lg"></i> Sudah
                                                            </button>
                                                        <?php else : ?>
                                                            <form method="POST" action="aksi/tarik_ppdb.php" class="d-inline"
                                                                onsubmit="return confirm('Tarik calon siswa <?= addslashes($nama); ?> menjadi siswa aktif Tahun Ajaran <?= $nama_ta_aktif; ?>?');">
                                                                <input type="hidden" name="id_calon" value="<?= $id_calon; ?>">
                                                                <input type="hidden" name="id_ta" value="<?= (int)$id_ta_aktif; ?>">
                                                                <button type="submit" name="tarik_calon" class="btn btn-success btn-sm" title="Tarik ke Data Siswa Aktif">
                                                                    <i class="bi bi-download me-1"></i> Tarik
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                        <?php
                                            }
                                        } else {
                                            echo '<tr><td colspan="10" class="text-center text-muted"><i class="bi bi-inbox me-2"></i>Tidak ada calon siswa dengan status diterima (4).</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>
