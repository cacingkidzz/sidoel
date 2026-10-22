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
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Data Mata Pelajaran</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Mata Pelajaran</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">

                <?php
                // Query 1: Total Mata Pelajaran Keseluruhan
                $qtotal = $koneksi->query("SELECT COUNT(*) as total FROM dt_mapel");
                $dtotal = $qtotal->fetch_assoc();
                ?>
                <div class="col-md-6 col-xl-4">
                    <div class="info-box bg-primary text-white shadow-sm">
                        <span class="info-box-icon"><i class="bi bi-journal-text"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Mata Pelajaran</span>
                            <span class="info-box-number h4"><?= $dtotal['total']; ?></span>
                        </div>
                    </div>
                </div>

                <?php
                // Query 2: Mapel Kelompok Wajib (A dan B)
                $qWajib = $koneksi->query("SELECT COUNT(*) as total FROM dt_mapel WHERE kelompok_mapel IN ('A', 'B')");
                $dWajib = $qWajib->fetch_assoc();
                ?>
                <div class="col-md-6 col-xl-4">
                    <div class="info-box bg-success text-white shadow-sm">
                        <span class="info-box-icon"><i class="bi bi-award-fill"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Mapel Kelompok Wajib (A & B)</span>
                            <span class="info-box-number h4"><?= $dWajib['total']; ?></span>
                        </div>
                    </div>
                </div>

                <?php
                // Query 3: Mapel Peminatan & Muatan Lokal (C1, C2, C3, Muatan Lokal)
                $qPeminatan = $koneksi->query("SELECT COUNT(*) as total FROM dt_mapel WHERE kelompok_mapel IN ('C1', 'C2', 'C3', 'Muatan Lokal')");
                $dPeminatan = $qPeminatan->fetch_assoc();
                ?>
                <div class="col-md-6 col-xl-4">
                    <div class="info-box bg-warning text-white shadow-sm">
                        <span class="info-box-icon"><i class="bi bi-stack-fill"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Mapel Peminatan & Mulok</span>
                            <span class="info-box-number h4"><?= $dPeminatan['total']; ?></span>
                        </div>
                    </div>
                </div>

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
                            <h5 class="card-title mb-0">Tabel Data Mata Pelajaran</h5>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahMapel">
                                <i class="bi bi-plus-circle-fill me-1"></i> Tambah Mata Pelajaran
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="myTable" class="table table-hover table-bordered table-sm w-100">
                                    <thead>
                                        <tr class="table-primary">
                                            <th style="width: 5%;">#</th>
                                            <th style="width: 15%;">Kode Mapel</th>
                                            <th>Nama Mata Pelajaran</th>
                                            <th style="width: 25%;">Kelompok</th>
                                            <th style="width: 15%;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $no = 1;
                                        $sql = $koneksi->query("SELECT * FROM dt_mapel ORDER BY id_mapel DESC");
                                        while ($row = $sql->fetch_assoc()) {
                                            // Menentukan badge class berdasarkan kelompok
                                            $badge_class = match ($row['kelompok_mapel']) {
                                                'A', 'B' => 'bg-info',
                                                'C1', 'C2', 'C3' => 'bg-success',
                                                'Muatan Lokal' => 'bg-warning',
                                                default => 'bg-secondary',
                                            };
                                        ?>
                                            <tr>
                                                <td><?= $no++; ?></td>
                                                <td><?= htmlspecialchars($row['kode_mapel']); ?></td>
                                                <td><?= htmlspecialchars($row['nama_mapel']); ?></td>
                                                <td><span class="badge <?= $badge_class ?>"><?= $row['kelompok_mapel']; ?></span></td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-success btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editdata<?= $row['id_mapel'] ?>" title="Edit">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm" id="delete<?= $row['id_mapel'] ?>" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                                                </td>
                                                <script>
                                                    // Logika SweetAlert untuk konfirmasi hapus
                                                    document.getElementById("delete<?= $row['id_mapel'] ?>").addEventListener("click", function(event) {
                                                        event.preventDefault();
                                                        if (typeof Swal !== 'undefined') {
                                                            Swal.fire({
                                                                title: 'Yakin ingin menghapus?',
                                                                text: 'Data <?= $row['nama_mapel'] ?> akan dihapus permanen!',
                                                                icon: 'warning',
                                                                showCancelButton: true,
                                                                confirmButtonText: 'Ya, hapus!',
                                                                cancelButtonText: 'Batal',
                                                                confirmButtonColor: '#dc3545',
                                                                cancelButtonColor: '#6c757d'
                                                            }).then((result) => {
                                                                if (result.isConfirmed) {
                                                                    // Redirect ke file aksi untuk proses hapus
                                                                    window.location.href = 'aksi/aksi_mapel.php?aksi=hapus&id=<?= $row['id_mapel'] ?>';
                                                                }
                                                            });
                                                        } else {
                                                            if (confirm('Yakin ingin menghapus mata pelajaran <?= $row['nama_mapel'] ?>?')) {
                                                                window.location.href = 'aksi/aksi_mapel.php?aksi=hapus&id=<?= $row['id_mapel'] ?>';
                                                            }
                                                        }
                                                    });
                                                </script>
                                            </tr>

                                            <div class="modal fade" id="editdata<?= $row['id_mapel'] ?>" tabindex="-1" aria-labelledby="editdataLabel<?= $row['id_mapel'] ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-md">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-success text-white">
                                                            <h5 class="modal-title" id="editdataLabel<?= $row['id_mapel'] ?>"><i class="bi bi-pencil-square me-1"></i> Edit Mata Pelajaran</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form action="aksi/aksi_mapel.php" method="post">
                                                                <input type="hidden" name="aksi" value="edit">
                                                                <input type="hidden" name="id_mapel" value="<?= $row['id_mapel'] ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Kode Mapel</label>
                                                                    <input type="text" class="form-control" name="kode_mapel" value="<?= htmlspecialchars($row['kode_mapel']) ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Nama Mata Pelajaran</label>
                                                                    <input type="text" class="form-control" name="nama_mapel" value="<?= htmlspecialchars($row['nama_mapel']) ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Kelompok Mapel</label>
                                                                    <select class="form-control" name="kelompok_mapel" required>
                                                                        <?php
                                                                        $kelompok_enum = ['A', 'B', 'C1', 'C2', 'C3', 'Muatan Lokal'];
                                                                        foreach ($kelompok_enum as $kel):
                                                                        ?>
                                                                            <option value="<?= $kel ?>" <?= ($row['kelompok_mapel'] == $kel) ? 'selected' : '' ?>><?= $kel ?></option>
                                                                        <?php endforeach; ?>
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

<div class="modal fade" id="tambahMapel" tabindex="-1" aria-labelledby="tambahMapelLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="tambahMapelLabel"><i class="bi bi-plus-circle-fill me-1"></i> Tambah Mata Pelajaran</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="aksi/aksi_mapel.php" method="post">
                    <input type="hidden" name="aksi" value="tambah">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Kode Mata Pelajaran</label>
                        <input type="text" class="form-control" name="kode_mapel" placeholder="Contoh: B.ING" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Mata Pelajaran</label>
                        <input type="text" class="form-control" name="nama_mapel" placeholder="Contoh: Bahasa Inggris" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Kelompok Mapel</label>
                        <select class="form-control" name="kelompok_mapel" required>
                            <option value="">-- Pilih Kelompok --</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C1">C1</option>
                            <option value="C2">C2</option>
                            <option value="C3">C3</option>
                            <option value="Muatan Lokal">Muatan Lokal</option>
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