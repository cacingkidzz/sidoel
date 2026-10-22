<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
</div>
<?php
// Catatan: Pastikan $koneksi sudah terdefinisi (asumsi file koneksi.php sudah di-include di file utama)

// ==========================================================
// >>> 1. AMBIL TAHUN AJARAN DARI SESI (TITIK ACUAN UTAMA) <<<
// ==========================================================
// Ambil nama Tahun Ajaran dari SESSION yang di-set oleh aksi/aksi_tahun_ajar.php
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? 'Tahun Ajaran Tidak Ditemukan';
$id_ta_aktif = 0;
$nama_ta_aktif = htmlspecialchars($nama_ta_sesi);

if (isset($koneksi) && !$koneksi->connect_error) {
    // Cari ID Tahun Ajaran berdasarkan nama TAHUN AJARAN yang ada di sesi.
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

    // ===========================================
    // 2. QUERY UNTUK STATISTIK SISWA (BERBASIS RIWAYAT dt_kelas_siswa)
    // ===========================================
    // Siswa dihitung milik TA terpilih (dari dropdown sidebar) bila:
    //   - dt_siswa.id_tahun_ajaran = TA terpilih (siswa aktif saat ini), ATAU
    //   - punya baris penempatan (dt_kelas_siswa) pada TA terpilih (siswa tahun sebelumnya).
    // Dengan begitu data tahun lama tetap tampil, tidak "hilang" saat kenaikan kelas.
    $from_riwayat = "FROM dt_siswa s
        LEFT JOIN dt_kelas_siswa ks ON ks.id_siswa = s.id_siswa AND ks.id_tahun_ajaran = '$id_ta_aktif'
        WHERE (s.id_tahun_ajaran = '$id_ta_aktif' OR ks.id_kelas_siswa IS NOT NULL)";

    $q_total = mysqli_query($koneksi, "SELECT COUNT(*) as total $from_riwayat");
    $d_total = mysqli_fetch_assoc($q_total);

    $q_siswa_l = mysqli_query($koneksi, "SELECT COUNT(*) as total $from_riwayat AND s.jk='L'");
    $d_siswa_l = mysqli_fetch_assoc($q_siswa_l);

    $q_siswa_p = mysqli_query($koneksi, "SELECT COUNT(*) as total $from_riwayat AND s.jk='P'");
    $d_siswa_p = mysqli_fetch_assoc($q_siswa_p);

    $total_siswa = $d_total['total'] ?? 0;
    $total_l = $d_siswa_l['total'] ?? 0;
    $total_p = $d_siswa_p['total'] ?? 0;
} else {
    $total_siswa = 0;
    $total_l = 0;
    $total_p = 0;
}

// Query untuk data tabel utama (ambil kelas/rombel sesuai TA terpilih)
$query_siswa = "
    SELECT s.*,
           k.nama_kelas AS kelas_ta,
           k.tingkat AS tingkat_ta
    FROM dt_siswa s
    LEFT JOIN dt_kelas_siswa ks ON ks.id_siswa = s.id_siswa AND ks.id_tahun_ajaran = '$id_ta_aktif'
    LEFT JOIN dt_kelas k ON k.id_kelas = ks.id_kelas
    WHERE (s.id_tahun_ajaran = '$id_ta_aktif' OR ks.id_kelas_siswa IS NOT NULL)
    ORDER BY COALESCE(k.tingkat, '~') ASC, COALESCE(k.nama_kelas, s.rombel) ASC, s.nama ASC
";
$result_siswa = isset($koneksi) ? mysqli_query($koneksi, $query_siswa) : false;

?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Data Siswa Aktif</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item"><a href="#!">Data Master</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Siswa</li>
                    </ol>
                </div>
            </div>
            <p class="small text-muted mt-2">Data Siswa untuk Tahun Ajaran Aktif: <b><?= $nama_ta_aktif; ?></b></p>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-4 col-md-6 col-sm-12">
                    <div class="info-box bg-secondary text-white shadow-sm">
                        <span class="info-box-icon"><i class="bi bi-person-badge-fill"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">TOTAL SISWA AKTIF</span>
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
                            <h5 class="card-title mb-0"><i class="bi bi-table me-1"></i> Daftar Data Siswa</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="index.php?menu=siswa_tambah" type="button" class="btn btn-primary">
                                        <i class="bi bi-person-plus-fill me-1"></i> Tambah Siswa Manual
                                    </a>
                                    <a href="index.php?menu=kenaikan_kelas" class="btn btn-warning text-dark">
                                        <i class="bi bi-arrow-up-circle-fill me-1"></i> Ambil/Naik Kelas
                                    </a>
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    <a href="laporan/format_siswa.php" class="btn btn-info" target="_blank">
                                        <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Download Format Excel
                                    </a>
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importSiswaModal">
                                        <i class="bi bi-cloud-arrow-up-fill me-1"></i> Import Data (Excel)
                                    </button>
                                    <a href="aksi/export_siswa.php?ta=<?= $id_ta_aktif ?>" class="btn btn-danger" target="_blank">
                                        <i class="bi bi-file-earmark-arrow-down-fill me-1"></i> Download Data Aktif
                                    </a>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="myTable2" class="table table-bordered table-striped table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Rombel</th>
                                            <th>JK</th>
                                            <th>Nama Siswa</th>
                                            <th>NIS / NISN</th>
                                            <th>Tempat, Tgl Lahir</th>
                                            <th>Kontak HP</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $no = 1;
                                        if ($result_siswa) {
                                            while ($row = mysqli_fetch_assoc($result_siswa)) {
                                                $nama_siswa = htmlspecialchars($row['nama'] ?? '-');
                                                if (!empty($row['kelas_ta'])) {
                                                    $rombel = htmlspecialchars(trim($row['tingkat_ta'] . ' - ' . $row['kelas_ta']));
                                                } else {
                                                    $rombel = htmlspecialchars($row['rombel'] ?? '-');
                                                }
                                                $nis_nisn = htmlspecialchars(!empty($row['nipd'] ?? '') ? $row['nipd'] : (!empty($row['nisn'] ?? '') ? $row['nisn'] : '-'));
                                                $tgl_lahir_formatted = !empty($row['tanggal_lahir'] ?? '') ? htmlspecialchars($row['tempat_lahir'] . ', ' . date('d-m-Y', strtotime($row['tanggal_lahir']))) : '-';
                                                $hp = htmlspecialchars($row['hp'] ?? '-');

                                                $jk = $row['jk'] ?? '';
                                                $jk_icon = ($jk == 'L')
                                                    ? '<i class="bi bi-gender-male text-primary fs-5" title="Laki-laki"></i>'
                                                    : (($jk == 'P')
                                                        ? '<i class="bi bi-gender-female text-danger fs-5" title="Perempuan"></i>'
                                                        : '-');

                                                $id_siswa = $row['id_siswa'] ?? '';
                                                $row_id_safe = htmlspecialchars($id_siswa);

                                        ?>
                                                <tr>
                                                    <td><?= $no++; ?></td>
                                                    <td><span class="badge bg-primary"><?= $rombel; ?></span></td>
                                                    <td class="text-center"><?= $jk_icon; ?></td>
                                                    <td><?= $nama_siswa; ?></td>
                                                    <td><?= $nis_nisn; ?></td>
                                                    <td><?= $tgl_lahir_formatted; ?></td>
                                                    <td><?= $hp; ?></td>
                                                    <td>
                                                        <a href="index.php?menu=siswa_detail&id=<?= $row_id_safe ?>" class="btn btn-info btn-sm" title="Detail Siswa"><i class="bi bi-info-circle-fill"></i></a>
                                                        <a href="index.php?menu=siswa_edit&id=<?= $row_id_safe ?>" class="btn btn-primary btn-sm" title="Edit Siswa"><i class="bi bi-pencil-square"></i></a>
                                                        <button type="button" id="alertButton<?= $row_id_safe ?>" class="btn btn-danger btn-sm" title="Hapus Data"><i class="bi bi-trash-fill"></i></button>
                                                    </td>
                                                </tr>

                                                <script>
                                                    document.getElementById('alertButton<?= $row_id_safe ?>').addEventListener('click', function() {
                                                        Swal.fire({
                                                            title: "Peringatan!",
                                                            text: "Apakah kamu yakin menghapus data siswa bernama <?= addslashes($nama_siswa) ?>?",
                                                            icon: "warning",
                                                            showCancelButton: true,
                                                            confirmButtonColor: "#3085d6",
                                                            cancelButtonColor: "#d33",
                                                            confirmButtonText: "Ya, Hapus!",
                                                            cancelButtonText: "Batal"
                                                        }).then((result) => {
                                                            if (result.isConfirmed) {
                                                                // Ganti URL aksi_siswa.php sesuai struktur Anda
                                                                window.location.href = "aksi/aksi_siswa.php?aksi=hapus&id=<?= $row_id_safe ?>";
                                                            }
                                                        });
                                                    });
                                                </script>

                                                <div class="modal fade" id="editSiswaModal<?= $row_id_safe ?>" tabindex="-1" aria-labelledby="editSiswaLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-primary text-white">
                                                                <h1 class="modal-title fs-5" id="editSiswaLabel">Edit Data Siswa: <?= $nama_siswa ?></h1>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <form action="aksi/aksi_siswa.php" method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="aksi" value="edit">
                                                                    <input type="hidden" name="id" value="<?= $row_id_safe ?>">
                                                                    <div class="mb-3">
                                                                        <label for="nama_siswa" class="form-label fw-bold">Nama Siswa</label>
                                                                        <input type="text" class="form-control" id="nama_siswa" name="nama" value="<?= $nama_siswa ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label for="rombel" class="form-label fw-bold">Rombel/Kelas</label>
                                                                        <input type="text" class="form-control" id="rombel" name="rombel" value="<?= $rombel ?>" required>
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
                                            echo '<tr><td colspan="8" class="text-center text-danger"><i class="bi bi-exclamation-triangle me-2"></i> Gagal memuat data siswa. Cek koneksi atau tahun ajaran aktif.</td></tr>';
                                        }
                                        ?>
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

<div class="modal fade" id="importSiswaModal" tabindex="-1" aria-labelledby="importSiswaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="aksi/import_general.php?entity=siswa" method="post" enctype="multipart/form-data">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="importSiswaModalLabel">
                        <i class="bi bi-cloud-arrow-up-fill me-1"></i> Import Data Siswa dari Excel
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        Pastikan header kolom sesuai nama DB (nama, nipd, jk, nisn, dll.). Siswa akan dimasukkan ke Tahun Ajaran Aktif: <b><?= $nama_ta_aktif ?></b>.
                    </div>
                    <div class="mb-3">
                        <label for="fileExcel" class="form-label">Pilih File Excel</label>
                        <input type="file" class="form-control" id="fileExcel" name="fileExcel" accept=".xlsx,.xls" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="import" class="btn btn-success">
                        <i class="bi bi-upload me-1"></i> Import Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>