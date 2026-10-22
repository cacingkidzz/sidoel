<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
</div>
<?php
// Pastikan sesi sudah dimulai di index.php atau file induk
include("koneksi.php");

// Ambil daftar kop sekolah
$kop_list = [];
$q_kop = $koneksi->query("SELECT * FROM dt_kop_sekolah ORDER BY id_kop DESC");
if ($q_kop) {
    while ($r = $q_kop->fetch_assoc()) {
        $kop_list[] = $r;
    }
}

$kop_aktif = null;
foreach ($kop_list as $r) {
    if ($r['status'] === 'aktif') {
        $kop_aktif = $r;
        break;
    }
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Kop Sekolah</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Kop Sekolah</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">

            <div class="alert alert-info py-2 mb-3 shadow-sm">
                <i class="bi bi-info-circle-fill me-1"></i> Unggah gambar kepala surat (kop) sekolah. Gambar berstatus <b>aktif</b> akan dipakai sebagai kop pada setiap laporan.
            </div>

            <div class="row">
                <!-- FORM UPLOAD -->
                <div class="col-lg-4 col-md-12">
                    <div class="card card-outline card-primary shadow-sm">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-cloud-arrow-up-fill me-1"></i> Unggah Kop Baru</h5>
                        </div>
                        <div class="card-body">
                            <form action="aksi/aksi_kop_sekolah.php" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="aksi" value="tambah">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Nama / Keterangan <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama_kop" placeholder="Contoh: Kop Laporan Utama" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Gambar Kop <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" name="gambar" accept="image/*" required
                                        onchange="if(this.files[0]){var u=URL.createObjectURL(this.files[0]);document.getElementById('previewKop').src=u;document.getElementById('previewKop').style.display='block';}">
                                    <div class="form-text">Format: JPG, JPEG, PNG, WEBP, GIF. Disarankan lebar penuh (mis. 1000&#215;250 px).</div>
                                </div>
                                <img id="previewKop" src="" alt="Pratinjau" class="img-fluid border rounded mb-3" style="display:none; max-height:140px;">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- DAFTAR KOP -->
                <div class="col-lg-8 col-md-12">
                    <div class="card card-outline card-primary shadow-sm">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-images me-1"></i> Daftar Kop Sekolah</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="myTable" class="table table-hover table-bordered table-sm w-100">
                                    <thead>
                                        <tr class="table-primary">
                                            <th style="width:5%;">#</th>
                                            <th style="width:22%;">Pratinjau</th>
                                            <th>Nama</th>
                                            <th style="width:12%;">Status</th>
                                            <th style="width:15%;">Diunggah</th>
                                            <th style="width:18%;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($kop_list) > 0): ?>
                                            <?php $no = 1; foreach ($kop_list as $row): ?>
                                                <?php $is_aktif = ($row['status'] === 'aktif'); ?>
                                                <tr>
                                                    <td><?= $no++; ?></td>
                                                    <td class="text-center">
                                                        <a href="assets/img/kop/<?= htmlspecialchars($row['gambar']); ?>" target="_blank">
                                                            <img src="assets/img/kop/<?= htmlspecialchars($row['gambar']); ?>" alt="Kop"
                                                                class="img-fluid border rounded" style="max-height:70px; max-width:150px; object-fit:contain;">
                                                        </a>
                                                    </td>
                                                    <td><?= htmlspecialchars($row['nama_kop']); ?></td>
                                                    <td class="text-center">
                                                        <?php if ($is_aktif): ?>
                                                            <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>Aktif</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Tidak Aktif</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($row['created_at']); ?></td>
                                                    <td class="text-center text-nowrap">
                                                        <?php if (!$is_aktif): ?>
                                                            <a href="aksi/aksi_kop_sekolah.php?aksi=aktifkan&id=<?= $row['id_kop'] ?>"
                                                                class="btn btn-success btn-sm me-1" title="Jadikan Aktif">
                                                                <i class="bi bi-check2-square"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <button class="btn btn-success btn-sm me-1" disabled title="Sedang Aktif">
                                                                <i class="bi bi-check2-square"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-danger btn-sm" id="delete<?= $row['id_kop'] ?>" title="Hapus">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <script>
                                                    document.getElementById("delete<?= $row['id_kop'] ?>").addEventListener("click", function(event) {
                                                        event.preventDefault();
                                                        var target = 'aksi/aksi_kop_sekolah.php?aksi=hapus&id=<?= $row['id_kop'] ?>';
                                                        if (typeof Swal !== 'undefined') {
                                                            Swal.fire({
                                                                title: 'Yakin ingin menghapus?',
                                                                text: 'Kop "<?= htmlspecialchars($row['nama_kop'], ENT_QUOTES); ?>" akan dihapus permanen!',
                                                                icon: 'warning',
                                                                showCancelButton: true,
                                                                confirmButtonText: 'Ya, hapus!',
                                                                cancelButtonText: 'Batal',
                                                                confirmButtonColor: '#dc3545',
                                                                cancelButtonColor: '#6c757d'
                                                            }).then((result) => {
                                                                if (result.isConfirmed) {
                                                                    window.location.href = target;
                                                                }
                                                            });
                                                        } else {
                                                            if (confirm('Yakin ingin menghapus kop ini?')) {
                                                                window.location.href = target;
                                                            }
                                                        }
                                                    });
                                                </script>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">
                                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                                    Belum ada kop sekolah. Silakan unggah melalui form di sebelah.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($kop_aktif): ?>
                <div class="card card-outline card-success shadow-sm">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-eye-fill me-1"></i> Pratinjau Kop Aktif</h5>
                    </div>
                    <div class="card-body text-center bg-light">
                        <img src="assets/img/kop/<?= htmlspecialchars($kop_aktif['gambar']); ?>" alt="Kop Aktif" class="img-fluid border rounded bg-white p-2" style="max-width:100%;">
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</main>
