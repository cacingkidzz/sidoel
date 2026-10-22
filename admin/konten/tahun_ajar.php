<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
</div>
<?php
include("koneksi.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Asumsi: File koneksi.php berisi objek koneksi database $koneksi
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Data Tahun Ajaran</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tahun Ajaran</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">

                <?php
                $qtotal = $koneksi->query("SELECT COUNT(*) as total FROM dt_tahun_ajar");
                $dtotal = $qtotal->fetch_assoc();
                ?>
                <div class="col-md-6 col-xl-4">
                    <div class="info-box bg-primary text-white shadow-sm">
                        <span class="info-box-icon"><i class="bi bi-book"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Tahun Ajaran</span>
                            <span class="info-box-number h4"><?= $dtotal['total']; ?></span>
                        </div>
                    </div>
                </div>

                <?php
                $qaktif = $koneksi->query("SELECT COUNT(*) as total FROM dt_tahun_ajar WHERE status='aktif'");
                $daktif = $qaktif->fetch_assoc();
                ?>
                <div class="col-md-6 col-xl-4">
                    <div class="info-box bg-success text-white shadow-sm">
                        <span class="info-box-icon"><i class="bi bi-check-circle-fill"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Tahun Ajaran Aktif</span>
                            <span class="info-box-number h4"><?= $daktif['total']; ?></span>
                        </div>
                    </div>
                </div>

                <?php
                $qnon = $koneksi->query("SELECT COUNT(*) as total FROM dt_tahun_ajar WHERE status='tidak aktif'");
                $dnon = $qnon->fetch_assoc();
                ?>
                <div class="col-md-6 col-xl-4">
                    <div class="info-box bg-secondary text-white shadow-sm">
                        <span class="info-box-icon"><i class="bi bi-x-circle-fill"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Tahun Ajaran Tidak Aktif</span>
                            <span class="info-box-number h4"><?= $dnon['total']; ?></span>
                        </div>
                    </div>
                </div>

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

                <div class="col-xl-12 col-md-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Tabel Data Tahun Ajaran</h5>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahTahun">
                                <i class="bi bi-plus-circle-fill me-1"></i> Tambah Tahun Ajaran
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="myTable" class="table table-hover table-bordered table-sm w-100">
                                    <thead>
                                        <tr class="table-primary">
                                            <th style="width: 5%;">#</th>
                                            <th>Tahun Ajaran</th>
                                            <th style="width: 20%;">Status</th>
                                            <th style="width: 15%;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $no = 1;
                                        $sql = $koneksi->query("SELECT * FROM dt_tahun_ajar ORDER BY id_tahun_ajaran DESC");
                                        while ($row = $sql->fetch_assoc()) {
                                            $badge_class = ($row['status'] == 'aktif') ? 'bg-success' : 'bg-secondary';
                                        ?>
                                            <tr>
                                                <td><?= $no++; ?></td>
                                                <td><?= htmlspecialchars($row['tahun_ajaran']); ?></td>
                                                <td><span class="badge <?= $badge_class ?>"><?= ucfirst($row['status']); ?></span></td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-success btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editdata<?= $row['id_tahun_ajaran'] ?>" title="Edit">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm" id="delete<?= $row['id_tahun_ajaran'] ?>" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                                                </td>
                                                <script>
                                                    // Logika SweetAlert untuk konfirmasi hapus
                                                    document.getElementById("delete<?= $row['id_tahun_ajaran'] ?>").addEventListener("click", function(event) {
                                                        event.preventDefault();
                                                        // Pastikan SweetAlert2 sudah ter-include di halaman utama Anda
                                                        if (typeof Swal !== 'undefined') {
                                                            Swal.fire({
                                                                title: 'Yakin ingin menghapus?',
                                                                text: 'Data akan dihapus permanen!',
                                                                icon: 'warning',
                                                                showCancelButton: true,
                                                                confirmButtonText: 'Ya, hapus!',
                                                                cancelButtonText: 'Batal',
                                                                confirmButtonColor: '#dc3545',
                                                                cancelButtonColor: '#6c757d'
                                                            }).then((result) => {
                                                                if (result.isConfirmed) {
                                                                    window.location.href = 'aksi/aksi_tahun_ajar.php?aksi=hapus&id=<?= $row['id_tahun_ajaran'] ?>';
                                                                }
                                                            });
                                                        } else {
                                                            // Fallback jika SweetAlert2 belum dimuat
                                                            if (confirm('Yakin ingin menghapus data <?= $row['tahun_ajaran'] ?>?')) {
                                                                window.location.href = 'aksi/aksi_tahun_ajar.php?aksi=hapus&id=<?= $row['id_tahun_ajaran'] ?>';
                                                            }
                                                        }
                                                    });
                                                </script>
                                            </tr>

                                            <div class="modal fade" id="editdata<?= $row['id_tahun_ajaran'] ?>" tabindex="-1" aria-labelledby="editdataLabel<?= $row['id_tahun_ajaran'] ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-md">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-success text-white">
                                                            <h5 class="modal-title" id="editdataLabel<?= $row['id_tahun_ajaran'] ?>"><i class="bi bi-pencil-square me-1"></i> Edit Tahun Ajaran</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form action="aksi/aksi_tahun_ajar.php" method="post">
                                                                <input type="hidden" name="aksi" value="edit">
                                                                <input type="hidden" name="id" value="<?= $row['id_tahun_ajaran'] ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Tahun Ajaran</label>
                                                                    <input type="text" class="form-control" name="tahun_ajaran" value="<?= htmlspecialchars($row['tahun_ajaran']) ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Status</label>
                                                                    <select class="form-control" name="status">
                                                                        <option value="aktif" <?= ($row['status'] == 'aktif') ? 'selected' : '' ?>>Aktif</option>
                                                                        <option value="tidak aktif" <?= ($row['status'] == 'tidak aktif') ? 'selected' : '' ?>>Tidak Aktif</option>
                                                                    </select>
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

<div class="modal fade" id="tambahTahun" tabindex="-1" aria-labelledby="tambahTahunLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="tambahTahunLabel"><i class="bi bi-calendar-plus-fill me-1"></i> Tambah Tahun Ajaran</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="aksi/aksi_tahun_ajar.php" method="post">
                    <input type="hidden" name="aksi" value="tambah">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tahun Ajaran</label>
                        <input type="text" class="form-control" name="tahun_ajaran" placeholder="Contoh: 2025/2026" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Status</label>
                        <select class="form-control" name="status" required>
                            <option value="aktif">Aktif</option>
                            <option value="tidak aktif" selected>Tidak Aktif</option>
                        </select>
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