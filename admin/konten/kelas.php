<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
</div>
<?php
// Pastikan sesi sudah dimulai di index.php atau file induk
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("koneksi.php");
// Asumsi: File koneksi.php berisi objek koneksi database $koneksi

// ==========================================================
// >>> 1. AMBIL TAHUN AJARAN DARI SESI (TITIK ACUAN UTAMA) <<<
// ==========================================================
// Ambil nama Tahun Ajaran dari SESSION yang di-set oleh aksi/aksi_tahun_ajar.php
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? 'Tahun Ajaran Tidak Ditemukan';

// Inisialisasi default jika koneksi gagal atau data tidak ditemukan
$id_ta_aktif = 0;
$nama_ta_aktif = htmlspecialchars($nama_ta_sesi);

// Cari ID Tahun Ajaran berdasarkan nama TAHUN AJARAN yang ada di sesi.
if (!$koneksi->connect_error) {
    // Menggunakan prepared statement untuk keamanan dan efisiensi
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

// --- 2. AMBIL DATA MASTER JURUSAN UNTUK DROPDOWN ---
$jurusan_data = [];
$q_jurusan = $koneksi->query("SELECT id_jurusan, nama_jurusan FROM dt_jurusan ORDER BY nama_jurusan ASC");
if ($q_jurusan) {
    while ($j = $q_jurusan->fetch_assoc()) {
        $jurusan_data[$j['id_jurusan']] = htmlspecialchars($j['nama_jurusan']);
    }
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Data Kelas</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Kelas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="alert alert-info py-2 mb-3 shadow-sm">
                <i class="bi bi-info-circle-fill me-1"></i> Data kelas yang ditampilkan saat ini adalah untuk Tahun Ajaran: <b><?= $nama_ta_aktif ?></b>.
            </div>

            <div class="row d-flex flex-wrap mb-3">

                <?php
                // Query 1: Total Kelas Keseluruhan (berdasarkan $id_ta_aktif dari Sesi)
                $qtotal = $koneksi->query("SELECT COUNT(*) as total FROM dt_kelas WHERE id_tahun_ajaran = '$id_ta_aktif'");
                $dtotal = $qtotal->fetch_assoc();
                ?>
                <div class="col-md-6 col-xl-3 mb-3">
                    <div class="info-box bg-primary text-white shadow-sm h-100">
                        <span class="info-box-icon"><i class="bi bi-door-open-fill"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Kelas</span>
                            <span class="info-box-number h4"><?= $dtotal['total']; ?></span>
                        </div>
                    </div>
                </div>

                <?php
                // Query 2: Kelas Tingkat X (berdasarkan $id_ta_aktif dari Sesi)
                $qX = $koneksi->query("SELECT COUNT(*) as total FROM dt_kelas WHERE tingkat = 'X' AND id_tahun_ajaran = '$id_ta_aktif'");
                $dX = $qX->fetch_assoc();
                ?>
                <div class="col-md-6 col-xl-3 mb-3">
                    <div class="info-box bg-success text-white shadow-sm h-100">
                        <span class="info-box-icon"><i class="bi bi-1-square-fill"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Tingkat X</span>
                            <span class="info-box-number h4"><?= $dX['total']; ?></span>
                        </div>
                    </div>
                </div>

                <?php
                // Query 3: Kelas Tingkat XI (berdasarkan $id_ta_aktif dari Sesi)
                $qXI = $koneksi->query("SELECT COUNT(*) as total FROM dt_kelas WHERE tingkat = 'XI' AND id_tahun_ajaran = '$id_ta_aktif'");
                $dXI = $qXI->fetch_assoc();
                ?>
                <div class="col-md-6 col-xl-3 mb-3">
                    <div class="info-box bg-info text-white shadow-sm h-100">
                        <span class="info-box-icon"><i class="bi bi-2-square-fill"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Tingkat XI</span>
                            <span class="info-box-number h4"><?= $dXI['total']; ?></span>
                        </div>
                    </div>
                </div>

                <?php
                // Query 4: Kelas Tingkat XII (berdasarkan $id_ta_aktif dari Sesi)
                $qXII = $koneksi->query("SELECT COUNT(*) as total FROM dt_kelas WHERE tingkat = 'XII' AND id_tahun_ajaran = '$id_ta_aktif'");
                $dXII = $qXII->fetch_assoc();
                ?>
                <div class="col-md-6 col-xl-3 mb-3">
                    <div class="info-box bg-warning text-white shadow-sm h-100">
                        <span class="info-box-icon"><i class="bi bi-3-square-fill"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Tingkat XII</span>
                            <span class="info-box-number h4"><?= $dXII['total']; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="row mb-3">
                        <div class="col-12">
                            <?php
                            // Tampilkan Alert dari sesi
                            if (isset($_SESSION['alert'])): ?>
                                <div class="alert alert-<?= $_SESSION['alert']['type']; ?> alert-dismissible fade show" role="alert">
                                    <?= $_SESSION['alert']['msg']; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php unset($_SESSION['alert']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-xl-12 col-md-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Tabel Data Kelas</h5>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahKelas" <?= ($id_ta_aktif == 0) ? 'disabled' : '' ?>>
                                <i class="bi bi-plus-circle-fill me-1"></i> Tambah Kelas
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="myTable" class="table table-hover table-bordered table-sm w-100">
                                    <thead>
                                        <tr class="table-primary">
                                            <th style="width: 5%;">#</th>
                                            <th style="width: 15%;">Tingkat</th>
                                            <th>Nama Kelas</th>
                                            <th style="width: 20%;">Jurusan</th>
                                            <th style="width: 30%;">Keterangan</th>
                                            <th style="width: 15%;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $no = 1;
                                        // Filter data hanya untuk ID Tahun Ajaran dari Sesi ($id_ta_aktif)
                                        $sql = $koneksi->query("
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

                                        while ($row = $sql->fetch_assoc()) {
                                            $badge_class = match ($row['tingkat']) {
                                                'X' => 'bg-success',
                                                'XI' => 'bg-info',
                                                'XII' => 'bg-warning',
                                                'XIII' => 'bg-danger',
                                                default => 'bg-secondary',
                                            };
                                        ?>
                                            <tr>
                                                <td><?= $no++; ?></td>
                                                <td><span class="badge <?= $badge_class ?>"><?= htmlspecialchars($row['tingkat']); ?></span></td>
                                                <td><?= htmlspecialchars($row['nama_kelas']); ?></td>
                                                <td><?= htmlspecialchars($row['nama_jurusan'] ?? 'Tidak Ditemukan'); ?></td>
                                                <td><?= htmlspecialchars($row['keterangan'] ?? '-'); ?></td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-success btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editdata<?= $row['id_kelas'] ?>" title="Edit">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm" id="delete<?= $row['id_kelas'] ?>" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                                                </td>
                                                <script>
                                                    // Logika SweetAlert untuk konfirmasi hapus
                                                    document.getElementById("delete<?= $row['id_kelas'] ?>").addEventListener("click", function(event) {
                                                        event.preventDefault();
                                                        if (typeof Swal !== 'undefined') {
                                                            Swal.fire({
                                                                title: 'Yakin ingin menghapus?',
                                                                text: 'Data kelas <?= $row['nama_kelas'] ?> akan dihapus permanen!',
                                                                icon: 'warning',
                                                                showCancelButton: true,
                                                                confirmButtonText: 'Ya, hapus!',
                                                                cancelButtonText: 'Batal',
                                                                confirmButtonColor: '#dc3545',
                                                                cancelButtonColor: '#6c757d'
                                                            }).then((result) => {
                                                                if (result.isConfirmed) {
                                                                    // Redirect ke file aksi untuk proses hapus
                                                                    window.location.href = 'aksi/aksi_kelas.php?aksi=hapus&id=<?= $row['id_kelas'] ?>';
                                                                }
                                                            });
                                                        } else {
                                                            if (confirm('Yakin ingin menghapus kelas <?= $row['nama_kelas'] ?>?')) {
                                                                window.location.href = 'aksi/aksi_kelas.php?aksi=hapus&id=<?= $row['id_kelas'] ?>';
                                                            }
                                                        }
                                                    });
                                                </script>
                                            </tr>

                                            <div class="modal fade" id="editdata<?= $row['id_kelas'] ?>" tabindex="-1" aria-labelledby="editdataLabel<?= $row['id_kelas'] ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-md">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-success text-white">
                                                            <h5 class="modal-title" id="editdataLabel<?= $row['id_kelas'] ?>"><i class="bi bi-pencil-square me-1"></i> Edit Kelas</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form action="aksi/aksi_kelas.php" method="post">
                                                                <input type="hidden" name="aksi" value="edit">
                                                                <input type="hidden" name="id_kelas" value="<?= $row['id_kelas'] ?>">
                                                                <input type="hidden" name="id_tahun_ajaran" value="<?= $id_ta_aktif ?>">

                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Tingkat</label>
                                                                    <select class="form-control" name="tingkat" required>
                                                                        <?php
                                                                        $tingkat_enum = ['X', 'XI', 'XII'];
                                                                        foreach ($tingkat_enum as $tingkat):
                                                                        ?>
                                                                            <option value="<?= $tingkat ?>" <?= ($row['tingkat'] == $tingkat) ? 'selected' : '' ?>><?= $tingkat ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Jurusan</label>
                                                                    <select class="form-control" name="id_jurusan" required>
                                                                        <option value="">-- Pilih Jurusan --</option>
                                                                        <?php foreach ($jurusan_data as $id_jur => $nama_jur): ?>
                                                                            <option value="<?= $id_jur ?>" <?= ($row['id_jurusan'] == $id_jur) ? 'selected' : '' ?>><?= $nama_jur ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Nama Kelas</label>
                                                                    <input type="text" class="form-control" name="nama_kelas" value="<?= htmlspecialchars($row['nama_kelas']) ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Keterangan</label>
                                                                    <textarea class="form-control" name="keterangan" rows="2"><?= htmlspecialchars($row['keterangan'] ?? '') ?></textarea>
                                                                </div>
                                                                <div class="modal-footer px-0 pb-0 pt-3 border-top">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                                    <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i> Update</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php } ?>
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

<div class="modal fade" id="tambahKelas" tabindex="-1" aria-labelledby="tambahKelasLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="tambahKelasLabel"><i class="bi bi-plus-circle-fill me-1"></i> Tambah Kelas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="aksi/aksi_kelas.php" method="post">
                    <input type="hidden" name="aksi" value="tambah">
                    <input type="hidden" name="id_tahun_ajaran" value="<?= $id_ta_aktif ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Tingkat</label>
                        <select class="form-control" name="tingkat" required>
                            <option value="">-- Pilih Tingkat --</option>
                            <option value="X">X</option>
                            <option value="XI">XI</option>
                            <option value="XII">XII</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Jurusan</label>
                        <select class="form-control" name="id_jurusan" required>
                            <option value="">-- Pilih Jurusan --</option>
                            <?php foreach ($jurusan_data as $id_jur => $nama_jur): ?>
                                <option value="<?= $id_jur ?>"><?= $nama_jur ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Kelas</label>
                        <input type="text" class="form-control" name="nama_kelas" placeholder="Contoh: RPL 1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="2"></textarea>
                    </div>

                    <div class="modal-footer px-0 pb-0 pt-3 border-top">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>