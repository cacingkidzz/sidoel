<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
</div>
<?php
include("koneksi.php");
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Data Jurusan</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Jurusan</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-xl-12 col-md-12">
                    <div class="card card-outline card-info">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-briefcase-fill me-1"></i> Data Jurusan</h5>
                        </div>
                        <div class="card-body">
                            <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#tambahJurusan">
                                <i class="bi bi-plus-lg me-1"></i> Tambah Jurusan
                            </button>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">#</th>
                                            <th>Nama Jurusan</th>
                                            <th>WhatsApp</th>
                                            <th style="width: 100px;">Status</th>
                                            <th style="width: 150px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $no = 1;
                                        $sql = $koneksi->query("SELECT * FROM dt_jurusan ORDER BY id_jurusan DESC");
                                        while ($row = $sql->fetch_assoc()) {
                                            $status_text = ($row['status_jur'] == 1) ? 'Aktif' : 'Nonaktif';
                                            $badge_class = ($row['status_jur'] == 1) ? 'bg-success' : 'bg-danger';
                                        ?>
                                            <tr>
                                                <td><?= $no++; ?></td>
                                                <td><?= htmlspecialchars($row['nama_jurusan']); ?></td>
                                                <td><?= htmlspecialchars($row['wa']); ?></td>
                                                <td><span class="badge <?= $badge_class ?>"><?= $status_text; ?></span></td>
                                                <td>
                                                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#editdata<?= $row['id_jurusan'] ?>" title="Edit Data">
                                                        <i class="bi bi-pencil-fill"></i>
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" id="delete<?= $row['id_jurusan'] ?>" title="Hapus Data">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                </td>

                                                <script>
                                                    // SweetAlert Konfirmasi Hapus
                                                    document.getElementById("delete<?= $row['id_jurusan'] ?>").addEventListener("click", function(event) {
                                                        event.preventDefault();
                                                        Swal.fire({
                                                            title: 'Yakin ingin menghapus?',
                                                            text: 'Jurusan "<?= htmlspecialchars($row['nama_jurusan']) ?>" akan dihapus permanen!',
                                                            icon: 'warning',
                                                            showCancelButton: true,
                                                            confirmButtonText: 'Ya, hapus!',
                                                            cancelButtonText: 'Batal',
                                                            confirmButtonColor: '#d33',
                                                            cancelButtonColor: '#6c757d'
                                                        }).then((result) => {
                                                            if (result.isConfirmed) {
                                                                window.location.href = 'aksi/aksi_jurusan.php?aksi=hapus&id_jurusan=<?= $row['id_jurusan'] ?>';
                                                            }
                                                        });
                                                    });
                                                </script>
                                            </tr>

                                            <div class="modal fade" id="editdata<?= $row['id_jurusan'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form action="aksi/aksi_jurusan.php" method="post">
                                                            <div class="modal-header bg-success text-white">
                                                                <h5 class="modal-title"><i class="bi bi-pencil-square me-1"></i> Edit Jurusan</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="aksi" value="edit">
                                                                <input type="hidden" name="id_jurusan" value="<?= $row['id_jurusan'] ?>">

                                                                <div class="mb-3">
                                                                    <label class="form-label">Nama Jurusan</label>
                                                                    <input type="text" class="form-control" name="nama_jurusan" value="<?= htmlspecialchars($row['nama_jurusan']) ?>" required>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label">Link WhatsApp Group</label>
                                                                    <input type="text" class="form-control" name="wa" value="<?= htmlspecialchars($row['wa']) ?>" required>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label">Status</label>
                                                                    <select class="form-control" name="status_jur" required>
                                                                        <option value="1" <?= ($row['status_jur'] == 1) ? 'selected' : '' ?>>Aktif</option>
                                                                        <option value="0" <?= ($row['status_jur'] == 0) ? 'selected' : '' ?>>Nonaktif</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                                <button type="submit" class="btn btn-success"><i class="bi bi-save me-1"></i> Simpan</button>
                                                            </div>
                                                        </form>
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

<div class="modal fade" id="tambahJurusan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="aksi/aksi_jurusan.php" method="post">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle-fill me-1"></i> Tambah Jurusan Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="aksi" value="tambah">

                    <div class="mb-3">
                        <label class="form-label">Nama Jurusan</label>
                        <input type="text" class="form-control" name="nama_jurusan" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Link WhatsApp Group</label>
                        <input type="text" class="form-control" name="wa" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status_jur" required>
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>