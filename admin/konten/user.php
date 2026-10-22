<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
</div>
<?php
// Pastikan file koneksi.php sudah ter-include
include("koneksi.php");
// Asumsi: File koneksi.php berisi objek koneksi database $koneksi

// Query untuk data Guru yang BELUM memiliki akun user
$guru_data = [];
$qguru = $koneksi->query("
    SELECT g.id_guru, g.nama_guru, g.email
    FROM dt_guru g
    LEFT JOIN dt_user u ON g.id_guru = u.id_fk
    WHERE u.id_fk IS NULL
    ORDER BY g.nama_guru ASC
");
while ($g = $qguru->fetch_assoc()) {
    $guru_data[] = $g;
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Data User</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Users</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <?php if (isset($_SESSION['alert'])): ?>
                        <div class="alert alert-<?= $_SESSION['alert']['type']; ?> alert-dismissible fade show" role="alert">
                            <?= $_SESSION['alert']['msg']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['alert']); ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <?php
                    $qadmin = $koneksi->query("SELECT COUNT(*) as total FROM dt_user WHERE level_user='Admin'");
                    $dadmin = $qadmin->fetch_assoc();
                    ?>
                    <div class="card bg-danger text-white shadow-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <i class="bi bi-person-fill-gear fs-2 opacity-75"></i>
                                </div>
                                <div class="col">
                                    <h4 class="mb-0 text-end"><?= $dadmin['total']; ?></h4>
                                    <h6 class="text-end opacity-75">Admin</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <?php
                    $qkurikulum = $koneksi->query("SELECT COUNT(*) as total FROM dt_user WHERE level_user='Kurikulum'");
                    $dkurikulum = $qkurikulum->fetch_assoc();
                    ?>
                    <div class="card bg-success text-white shadow-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <i class="bi bi-person-badge-fill fs-2 opacity-75"></i>
                                </div>
                                <div class="col">
                                    <h4 class="mb-0 text-end"><?= $dkurikulum['total']; ?></h4>
                                    <h6 class="text-end opacity-75">Kurikulum</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <?php
                    $qkesiswaan = $koneksi->query("SELECT COUNT(*) as total FROM dt_user WHERE level_user='Kesiswaan'");
                    $dkesiswaan = $qkesiswaan->fetch_assoc();
                    ?>
                    <div class="card bg-info text-white shadow-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <i class="bi bi-person-fill fs-2 opacity-75"></i>
                                </div>
                                <div class="col">
                                    <h4 class="mb-0 text-end"><?= $dkesiswaan['total']; ?></h4>
                                    <h6 class="text-end opacity-75">Kesiswaan</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <?php
                    $qguru = $koneksi->query("SELECT COUNT(*) as total FROM dt_user WHERE level_user='Guru'");
                    $dguru = $qguru->fetch_assoc();
                    ?>
                    <div class="card bg-primary text-white shadow-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <i class="bi bi-person-lines-fill fs-2 opacity-75"></i>
                                </div>
                                <div class="col">
                                    <h4 class="mb-0 text-end"><?= $dguru['total']; ?></h4>
                                    <h6 class="text-end opacity-75">Guru</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-12 col-md-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Tabel Data Users</h5>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahUser">
                                <i class="bi bi-plus-lg me-1"></i> Tambah User
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="myTable" class="table table-hover table-striped w-100">
                                    <thead>
                                        <tr class="table-primary">
                                            <th style="width: 5%;">#</th>
                                            <th style="width: 25%;">Username (Email)</th>
                                            <th style="width: 35%;">Nama User</th>
                                            <th style="width: 15%;">Level Akses</th>
                                            <th style="width: 10%;">Status</th>
                                            <th style="width: 10%;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $no = 1;
                                        // Query JOIN dt_user dan dt_guru untuk menampilkan nama
                                        $sql = $koneksi->query("
                                            SELECT u.*, g.nama_guru 
                                            FROM dt_user u
                                            LEFT JOIN dt_guru g ON u.id_fk = g.id_guru
                                            ORDER BY u.id_user DESC
                                        ");

                                        while ($row = $sql->fetch_assoc()) {
                                            $nama_user_display = '';
                                            $badge_class = '';

                                            // Menentukan Nama User (ambil dari dt_guru jika ada, atau default jika Admin)
                                            if ($row['level_user'] == 'Admin' && $row['id_fk'] === NULL) {
                                                $nama_user_display = 'Administrator Sistem';
                                            } else {
                                                $nama_user_display = $row['nama_guru'] ?? 'User Internal';
                                            }

                                            // Menentukan Badge dan Text Level Akses
                                            switch ($row['level_user']) {
                                                case 'Admin':
                                                    $badge_class = 'bg-danger';
                                                    break;
                                                case 'Kurikulum':
                                                    $badge_class = 'bg-success';
                                                    break;
                                                case 'Kesiswaan':
                                                    $badge_class = 'bg-info';
                                                    break;
                                                case 'Guru':
                                                    $badge_class = 'bg-primary';
                                                    break;
                                                default:
                                                    $badge_class = 'bg-secondary';
                                                    break;
                                            }

                                            // Menentukan Badge Status
                                            $status_text = ($row['status_aktif'] == 'Y') ? 'Aktif' : 'Nonaktif';
                                            $status_class = ($row['status_aktif'] == 'Y') ? 'bg-success' : 'bg-secondary';
                                        ?>
                                            <tr>
                                                <td><?= $no++; ?></td>
                                                <td><?= htmlspecialchars($row['username']); ?></td>
                                                <td><?= htmlspecialchars($nama_user_display); ?></td>
                                                <td><span class="badge <?= $badge_class ?>"><?= htmlspecialchars($row['level_user']); ?></span></td>
                                                <td><span class="badge <?= $status_class ?>"><?= $status_text; ?></span></td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-success btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editdata<?= $row['id_user'] ?>" title="Reset Password">
                                                        <i class="bi bi-key-fill"></i>
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" id="delete<?= $row['id_user'] ?>" title="Hapus User">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                </td>
                                                <script>
                                                    document.getElementById("delete<?= $row['id_user'] ?>").addEventListener("click", function(event) {
                                                        event.preventDefault();
                                                        if (typeof Swal !== 'undefined') {
                                                            Swal.fire({
                                                                title: 'Yakin ingin menghapus?',
                                                                text: 'Data user <?= htmlspecialchars($row['username']) ?> akan dihapus permanen!',
                                                                icon: 'warning',
                                                                showCancelButton: true,
                                                                confirmButtonText: 'Ya, hapus!',
                                                                cancelButtonText: 'Batal',
                                                                confirmButtonColor: '#dc3545',
                                                                cancelButtonColor: '#6c757d'
                                                            }).then((result) => {
                                                                if (result.isConfirmed) {
                                                                    window.location.href = 'aksi/aksi_user.php?aksi=hapus&id_user=<?= $row['id_user'] ?>';
                                                                }
                                                            });
                                                        } else {
                                                            if (confirm('Yakin ingin menghapus user <?= $row['username'] ?>?')) {
                                                                window.location.href = 'aksi/aksi_user.php?aksi=hapus&id_user=<?= $row['id_user'] ?>';
                                                            }
                                                        }
                                                    });
                                                </script>
                                            </tr>

                                            <div class="modal fade" id="editdata<?= $row['id_user'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form action="aksi/aksi_user.php" method="post">
                                                            <div class="modal-header bg-success text-white">
                                                                <h5 class="modal-title"><i class="bi bi-lock-fill me-1"></i> Reset Password</h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="aksi" value="update_password">
                                                                <input type="hidden" name="id_user" value="<?= $row['id_user'] ?>">
                                                                <p>Anda akan mereset password untuk user: <b><?= htmlspecialchars($nama_user_display) ?></b> (<?= htmlspecialchars($row['username']) ?>)</p>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Password Baru</label>
                                                                    <input type="password" class="form-control" name="password" required>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                                <button type="submit" class="btn btn-success"><i class="bi bi-arrow-repeat me-1"></i> Reset Password</button>
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

<div class="modal fade" id="tambahUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="aksi/aksi_user.php" method="post">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-person-plus-fill me-1"></i> Tambah User Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="aksi" value="tambah">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih Guru (Relasi)</label>
                        <select class="form-select select2" name="id_fk" id="id_fk_guru" required>
                            <option value="">-- Pilih Guru yang Belum Punya Akun --</option>
                            <?php foreach ($guru_data as $g): ?>
                                <option value="<?= $g['id_guru'] ?>"
                                    data-nama="<?= htmlspecialchars($g['nama_guru']) ?>"
                                    data-email="<?= htmlspecialchars($g['email']) ?>">
                                    <?= htmlspecialchars($g['nama_guru']) ?> - <?= htmlspecialchars($g['email']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Hanya menampilkan guru yang belum memiliki akun login.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Username (Email)</label>
                        <input type="email" class="form-control" id="username" name="username" readonly required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Level Akses</label>
                        <select class="form-select" name="level_user" required>
                            <option value="Admin">Admin</option>
                            <option value="Kurikulum">Kurikulum</option>
                            <option value="Kesiswaan">Kesiswaan</option>
                            <option value="Guru" selected>Guru</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan User</button>
                </div>
            </form>
        </div>
    </div>
</div>