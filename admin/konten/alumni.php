<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
</div>
<?php
// ==========================================================
// 1. TAHUN AJARAN AKTIF DARI SESI
// ==========================================================
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? 'Tahun Ajaran Tidak Ditemukan';
$id_ta_aktif = 0;
$nama_ta_aktif = htmlspecialchars($nama_ta_sesi);

if (isset($koneksi) && !$koneksi->connect_error) {
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

// ==========================================================
// 2. CEK KETERSEDIAAN TABEL ALUMNI
// ==========================================================
$tabel_alumni_ada = false;
if (isset($koneksi) && !$koneksi->connect_error) {
    $cek_tabel = $koneksi->query("SHOW TABLES LIKE 'dt_alumni'");
    $tabel_alumni_ada = ($cek_tabel && $cek_tabel->num_rows > 0);
}

$total_siswa = $total_l = $total_p = 0;
$list_tahun_lulus = [];
$daftar_siswa_aktif = [];

if ($tabel_alumni_ada) {
    // ==========================================================
    // 3. STATISTIK ALUMNI
    // ==========================================================
    $d_total = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM dt_alumni"));
    $d_l = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM dt_alumni WHERE jk='L'"));
    $d_p = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM dt_alumni WHERE jk='P'"));

    $total_siswa = $d_total['total'] ?? 0;
    $total_l = $d_l['total'] ?? 0;
    $total_p = $d_p['total'] ?? 0;

    // Daftar tahun lulus untuk dropdown filter
    $q_th = mysqli_query($koneksi, "SELECT DISTINCT tahun_lulus FROM dt_alumni WHERE tahun_lulus IS NOT NULL AND tahun_lulus <> '' ORDER BY tahun_lulus DESC");
    if ($q_th) {
        while ($row_th = mysqli_fetch_assoc($q_th)) {
            $list_tahun_lulus[] = $row_th['tahun_lulus'];
        }
    }

    // Daftar siswa aktif untuk modal "Pindahkan ke Alumni"
    $q_aktif = mysqli_query($koneksi, "SELECT id_siswa, nama, nipd, rombel, jurusan FROM dt_siswa ORDER BY rombel ASC, nama ASC");
    if ($q_aktif) {
        while ($row_aktif = mysqli_fetch_assoc($q_aktif)) {
            $daftar_siswa_aktif[] = $row_aktif;
        }
    }
}

// ==========================================================
// 4. QUERY TABEL ALUMNI (+ FILTER TAHUN LULUS)
// ==========================================================
$th_filter = trim($_GET['th'] ?? '');
$where = "";
if ($tabel_alumni_ada && $th_filter !== '') {
    $th_filter_esc = mysqli_real_escape_string($koneksi, $th_filter);
    $where = "WHERE a.tahun_lulus = '$th_filter_esc'";
}

$query_alumni = "
    SELECT a.*, ta.tahun_ajaran AS ta_terakhir
    FROM dt_alumni a
    LEFT JOIN dt_tahun_ajar ta ON a.id_tahun_ajaran = ta.id_tahun_ajaran
    $where
    ORDER BY a.tahun_lulus DESC, a.nama ASC
";
$result_alumni = ($tabel_alumni_ada && isset($koneksi)) ? mysqli_query($koneksi, $query_alumni) : false;

// Daftar opsi status kerja
$status_options = ['Bekerja', 'Kuliah', 'Wirausaha', 'Belum Bekerja', 'Lainnya'];

function badge_status($status)
{
    switch ($status) {
        case 'Bekerja':
            return '<span class="badge bg-success">Bekerja</span>';
        case 'Kuliah':
            return '<span class="badge bg-primary">Kuliah</span>';
        case 'Wirausaha':
            return '<span class="badge bg-info">Wirausaha</span>';
        case 'Belum Bekerja':
            return '<span class="badge bg-warning text-dark">Belum Bekerja</span>';
        case 'Lainnya':
            return '<span class="badge bg-secondary">Lainnya</span>';
        default:
            return '<span class="badge bg-light text-muted">Belum diisi</span>';
    }
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Data Alumni Siswa</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item"><a href="#!">Data Siswa</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Alumni</li>
                    </ol>
                </div>
            </div>
            <p class="small text-muted mt-2">Daftar siswa yang telah lulus / keluar dan dipindahkan ke data alumni. Tahun Ajaran Aktif saat ini: <b><?= $nama_ta_aktif; ?></b></p>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">

            <?php if (!$tabel_alumni_ada) : ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Tabel <b>dt_alumni</b> belum ada di database. Jalankan file <code>alumni_siswa.sql</code> terlebih dahulu.
                </div>
            <?php else : ?>

                <div class="row">
                    <div class="col-lg-4 col-md-6 col-sm-12">
                        <div class="info-box bg-secondary text-white shadow-sm">
                            <span class="info-box-icon"><i class="bi bi-person-badge-fill"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">TOTAL ALUMNI</span>
                                <span class="info-box-number"><?= number_format($total_siswa); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 col-sm-12">
                        <div class="info-box bg-primary text-white shadow-sm">
                            <span class="info-box-icon"><i class="bi bi-gender-male"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">LAKI-LAKI</span>
                                <span class="info-box-number"><?= number_format($total_l); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 col-sm-12">
                        <div class="info-box bg-info text-white shadow-sm">
                            <span class="info-box-icon"><i class="bi bi-gender-female"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">PEREMPUAN</span>
                                <span class="info-box-number"><?= number_format($total_p); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="mb-3 mt-1">

                <div class="row">
                    <div class="col-xl-12 col-md-12">
                        <div class="card card-outline card-secondary">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-table me-1"></i> Daftar Data Alumni</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pindahAlumniModal">
                                        <i class="bi bi-person-check-fill me-1"></i> Pindahkan Siswa ke Alumni
                                    </button>

                                    <form method="GET" action="index.php" class="d-flex flex-wrap gap-2">
                                        <input type="hidden" name="menu" value="alumni">
                                        <select name="th" class="form-select form-select-sm" style="width:auto;">
                                            <option value="">-- Semua Tahun Lulus --</option>
                                            <?php foreach ($list_tahun_lulus as $th_opt) : ?>
                                                <option value="<?= htmlspecialchars($th_opt) ?>" <?= ($th_filter === $th_opt) ? 'selected' : '' ?>>
                                                    Lulus <?= htmlspecialchars($th_opt) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel me-1"></i>Filter</button>
                                        <?php if ($th_filter !== '') : ?>
                                            <a href="index.php?menu=alumni" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Reset</a>
                                        <?php endif; ?>
                                    </form>
                                </div>

                                <div class="table-responsive">
                                    <table id="myTable" class="table table-bordered table-striped table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Tahun Lulus</th>
                                                <th>JK</th>
                                                <th>Nama Alumni</th>
                                                <th>NIS / NISN</th>
                                                <th>Jurusan</th>
                                                <th>Rombel Terakhir</th>
                                                <th>Kontak HP</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $no = 1;
                                            if ($result_alumni && mysqli_num_rows($result_alumni) > 0) {
                                                while ($row = mysqli_fetch_assoc($result_alumni)) {
                                                    $id_alumni = (int)($row['id_alumni'] ?? 0);
                                                    $nama_alumni = htmlspecialchars($row['nama'] ?? '-');
                                                    $th_lulus = htmlspecialchars($row['tahun_lulus'] ?? '-');
                                                    $jurusan = htmlspecialchars($row['jurusan'] ?? '-');
                                                    $rombel = htmlspecialchars($row['rombel'] ?? '-');
                                                    $nis_nisn = htmlspecialchars(!empty($row['nipd'] ?? '') ? $row['nipd'] : (!empty($row['nisn'] ?? '') ? $row['nisn'] : '-'));
                                                    $hp = htmlspecialchars($row['hp'] ?? ($row['telp'] ?? '-'));
                                                    if ($hp === '') $hp = '-';
                                                    $jk = $row['jk'] ?? '';
                                                    $jk_icon = ($jk == 'L')
                                                        ? '<i class="bi bi-gender-male text-primary fs-5" title="Laki-laki"></i>'
                                                        : (($jk == 'P')
                                                            ? '<i class="bi bi-gender-female text-danger fs-5" title="Perempuan"></i>'
                                                            : '-');
                                            ?>
                                                    <tr>
                                                        <td><?= $no++; ?></td>
                                                        <td><span class="badge bg-dark"><?= $th_lulus; ?></span></td>
                                                        <td class="text-center"><?= $jk_icon; ?></td>
                                                        <td><?= $nama_alumni; ?></td>
                                                        <td><?= $nis_nisn; ?></td>
                                                        <td><?= $jurusan; ?></td>
                                                        <td><span class="badge bg-primary"><?= $rombel; ?></span></td>
                                                        <td><?= $hp; ?></td>
                                                        <td><?= badge_status($row['status_kerja'] ?? ''); ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-info btn-sm" title="Detail Alumni" data-bs-toggle="modal" data-bs-target="#detailAlumni<?= $id_alumni; ?>"><i class="bi bi-info-circle-fill"></i></button>
                                                            <button type="button" class="btn btn-primary btn-sm" title="Edit Data Alumni" data-bs-toggle="modal" data-bs-target="#editAlumni<?= $id_alumni; ?>"><i class="bi bi-pencil-square"></i></button>
                                                            <button type="button" class="btn btn-warning btn-sm text-dark" title="Kembalikan ke Siswa Aktif" id="btnKembali<?= $id_alumni; ?>"><i class="bi bi-arrow-counterclockwise"></i></button>
                                                            <button type="button" class="btn btn-danger btn-sm" title="Hapus Data" id="btnHapus<?= $id_alumni; ?>"><i class="bi bi-trash-fill"></i></button>
                                                        </td>
                                                    </tr>

                                                    <script>
                                                        document.getElementById('btnKembali<?= $id_alumni; ?>').addEventListener('click', function() {
                                                            Swal.fire({
                                                                title: "Kembalikan ke Siswa Aktif?",
                                                                text: "Alumni <?= addslashes($nama_alumni) ?> akan dikembalikan menjadi siswa aktif. Lanjutkan?",
                                                                icon: "question",
                                                                showCancelButton: true,
                                                                confirmButtonColor: "#198754",
                                                                cancelButtonColor: "#d33",
                                                                confirmButtonText: "Ya, Kembalikan!",
                                                                cancelButtonText: "Batal"
                                                            }).then((result) => {
                                                                if (result.isConfirmed) {
                                                                    window.location.href = "aksi/aksi_alumni.php?aksi=kembalikan&id=<?= $id_alumni; ?>";
                                                                }
                                                            });
                                                        });

                                                        document.getElementById('btnHapus<?= $id_alumni; ?>').addEventListener('click', function() {
                                                            Swal.fire({
                                                                title: "Peringatan!",
                                                                text: "Hapus permanen data alumni bernama <?= addslashes($nama_alumni) ?>?",
                                                                icon: "warning",
                                                                showCancelButton: true,
                                                                confirmButtonColor: "#3085d6",
                                                                cancelButtonColor: "#d33",
                                                                confirmButtonText: "Ya, Hapus!",
                                                                cancelButtonText: "Batal"
                                                            }).then((result) => {
                                                                if (result.isConfirmed) {
                                                                    window.location.href = "aksi/aksi_alumni.php?aksi=hapus&id=<?= $id_alumni; ?>";
                                                                }
                                                            });
                                                        });
                                                    </script>

                                                    <!-- MODAL DETAIL ALUMNI -->
                                                    <div class="modal fade" id="detailAlumni<?= $id_alumni; ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-info text-white">
                                                                    <h1 class="modal-title fs-5">Detail Alumni: <?= $nama_alumni; ?></h1>
                                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="row">
                                                                        <div class="col-md-4 text-center mb-3">
                                                                            <?php
                                                                            $foto_path = '../assets/img/foto_siswa/' . ($row['foto'] ?? '');
                                                                            if (!empty($row['foto'] ?? '') && file_exists(__DIR__ . '/../assets/img/foto_siswa/' . $row['foto'])) { ?>
                                                                                <img src="assets/img/foto_siswa/<?= htmlspecialchars($row['foto']); ?>" alt="Foto" class="img-thumbnail rounded" style="max-height:180px;">
                                                                            <?php } else { ?>
                                                                                <img src="assets/img/pria.png" alt="Foto" class="img-thumbnail rounded" style="max-height:180px;">
                                                                            <?php } ?>
                                                                        </div>
                                                                        <div class="col-md-8">
                                                                            <dl class="row">
                                                                                <dt class="col-sm-4">NIS / NISN</dt>
                                                                                <dd class="col-sm-8"><?= htmlspecialchars(($row['nipd'] ?? '') . ' / ' . ($row['nisn'] ?? '')); ?></dd>
                                                                                <dt class="col-sm-4">Jenis Kelamin</dt>
                                                                                <dd class="col-sm-8"><?= ($jk == 'L') ? 'Laki-laki' : (($jk == 'P') ? 'Perempuan' : '-'); ?></dd>
                                                                                <dt class="col-sm-4">Tempat, Tgl Lahir</dt>
                                                                                <dd class="col-sm-8"><?= htmlspecialchars(($row['tempat_lahir'] ?? '-') . ', ' . (!empty($row['tanggal_lahir']) ? date('d-m-Y', strtotime($row['tanggal_lahir'])) : '-')); ?></dd>
                                                                                <dt class="col-sm-4">Agama</dt>
                                                                                <dd class="col-sm-8"><?= htmlspecialchars($row['agama'] ?? '-'); ?></dd>
                                                                                <dt class="col-sm-4">Jurusan</dt>
                                                                                <dd class="col-sm-8"><?= htmlspecialchars($row['jurusan'] ?? '-'); ?></dd>
                                                                                <dt class="col-sm-4">Rombel Terakhir</dt>
                                                                                <dd class="col-sm-8"><?= htmlspecialchars($row['rombel'] ?? '-'); ?></dd>
                                                                                <dt class="col-sm-4">Tahun Ajaran Terakhir</dt>
                                                                                <dd class="col-sm-8"><?= htmlspecialchars($row['ta_terakhir'] ?? '-'); ?></dd>
                                                                            </dl>
                                                                        </div>
                                                                    </div>
                                                                    <hr>
                                                                    <dl class="row mb-0">
                                                                        <dt class="col-sm-4">Alamat</dt>
                                                                        <dd class="col-sm-8"><?= htmlspecialchars($row['alamat'] ?? '-'); ?></dd>
                                                                        <dt class="col-sm-4">Kecamatan / Kelurahan</dt>
                                                                        <dd class="col-sm-8"><?= htmlspecialchars(($row['kecamatan'] ?? '-') . ' / ' . ($row['kelurahan'] ?? '-')); ?></dd>
                                                                        <dt class="col-sm-4">No. HP / Email</dt>
                                                                        <dd class="col-sm-8"><?= htmlspecialchars(($row['hp'] ?? ($row['telp'] ?? '-')) . ' / ' . ($row['email'] ?? '-')); ?></dd>
                                                                        <dt class="col-sm-4">Nama Ayah</dt>
                                                                        <dd class="col-sm-8"><?= htmlspecialchars($row['nama_ayah'] ?? '-'); ?></dd>
                                                                        <dt class="col-sm-4">Nama Ibu</dt>
                                                                        <dd class="col-sm-8"><?= htmlspecialchars($row['nama_ibu'] ?? '-'); ?></dd>
                                                                        <dt class="col-sm-4">Tahun Lulus</dt>
                                                                        <dd class="col-sm-8"><?= badge_status($row['status_kerja'] ?? ''); ?> <?= $th_lulus; ?></dd>
                                                                        <dt class="col-sm-4">Status Setelah Lulus</dt>
                                                                        <dd class="col-sm-8"><?= badge_status($row['status_kerja'] ?? ''); ?></dd>
                                                                        <dt class="col-sm-4">Tempat Kerja / Perguruan Tinggi</dt>
                                                                        <dd class="col-sm-8"><?= htmlspecialchars($row['tempat_kerja'] ?? '-'); ?></dd>
                                                                    </dl>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1"></i> Tutup</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- MODAL EDIT ALUMNI -->
                                                    <div class="modal fade" id="editAlumni<?= $id_alumni; ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form action="aksi/aksi_alumni.php" method="POST">
                                                                    <div class="modal-header bg-primary text-white">
                                                                        <h1 class="modal-title fs-5">Edit Alumni: <?= $nama_alumni; ?></h1>
                                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="update_alumni" value="1">
                                                                        <input type="hidden" name="id_alumni" value="<?= $id_alumni; ?>">
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-bold">Tahun Lulus</label>
                                                                            <input type="text" class="form-control" name="tahun_lulus" value="<?= $th_lulus; ?>" placeholder="Contoh: 2026/2027" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-bold">Status Setelah Lulus</label>
                                                                            <select class="form-select" name="status_kerja">
                                                                                <option value="">-- Pilih Status --</option>
                                                                                <?php foreach ($status_options as $opt) : ?>
                                                                                    <option value="<?= $opt ?>" <?= (($row['status_kerja'] ?? '') == $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="mb-0">
                                                                            <label class="form-label fw-bold">Tempat Kerja / Perguruan Tinggi</label>
                                                                            <input type="text" class="form-control" name="tempat_kerja" value="<?= htmlspecialchars($row['tempat_kerja'] ?? ''); ?>" placeholder="Opsional">
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle me-1"></i> Tutup</button>
                                                                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                            <?php
                                                }
                                            } else {
                                                echo '<tr><td colspan="10" class="text-center text-muted"><i class="bi bi-inbox me-2"></i>' .
                                                    ($th_filter !== '' ? 'Tidak ada alumni dengan tahun lulus ' . htmlspecialchars($th_filter) . '.' : 'Belum ada data alumni. Gunakan tombol "Pindahkan Siswa ke Alumni" untuk memulai.') .
                                                    '</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MODAL PINDAHKAN SISWA KE ALUMNI -->
                <div class="modal fade" id="pindahAlumniModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <form action="aksi/aksi_alumni.php" method="POST">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title"><i class="bi bi-person-check-fill me-1"></i> Pindahkan Siswa ke Alumni</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info">
                                        Pilih siswa yang akan lulus/dipindahkan. Data siswa akan disalin ke tabel <b>dt_alumni</b> dan dihapus dari data siswa aktif.
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Tahun Lulus <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="tahun_lulus" value="<?= $nama_ta_aktif; ?>" placeholder="Contoh: 2026/2027" required>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label fw-bold">Siswa Aktif <span class="text-danger">*</span></label>
                                        <select name="ids[]" class="form-select" size="12" multiple required>
                                            <?php if (!empty($daftar_siswa_aktif)) : ?>
                                                <?php foreach ($daftar_siswa_aktif as $s) : ?>
                                                    <option value="<?= (int)$s['id_siswa']; ?>">
                                                        <?= htmlspecialchars(($s['rombel'] ?? '-') . ' - ' . $s['nama'] . ' (' . ($s['nipd'] ?? '-') . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php else : ?>
                                                <option value="" disabled>Tidak ada siswa aktif</option>
                                            <?php endif; ?>
                                        </select>
                                        <small class="text-muted">Tahan tombol <b>Ctrl</b> (Windows) / <b>Cmd</b> (Mac) untuk memilih lebih dari satu siswa.</small>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" name="pindahkan_alumni" class="btn btn-primary" onclick="return confirm('Pastikan siswa yang dipilih benar-benar telah lulus. Lanjutkan pemindahan ke alumni?');">
                                        <i class="bi bi-arrow-right-circle-fill me-1"></i> Pindahkan ke Alumni
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>
</main>
