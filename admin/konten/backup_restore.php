<?php
// Halaman Backup & Restore Database (Administrasi Sistem). RBAC: Admin.
include("koneksi.php");
require_once("helper_backup.php");

$tersedia   = backup_available();
$mode       = backup_mode();
$mode_label = backup_mode_label();
$daftar     = backup_list();
$total_size = 0;
foreach ($daftar as $d) {
    $total_size += $d['size'];
}
$bin_dir = backup_bin_dir();
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Backup &amp; Restore</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Backup &amp; Restore</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?= $_SESSION['alert']['type']; ?> alert-dismissible fade show" role="alert">
                    <?= $_SESSION['alert']['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>

            <?php if (!$tersedia): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-octagon me-1"></i>
                    Fitur backup/restore <b>tidak tersedia</b>: ekstensi <code>mysqli</code> tidak aktif dan binary MySQL
                    (<code>mysqldump</code>/<code>mysql</code>) juga tidak ditemukan. Hubungi penyedia hosting.
                </div>
            <?php elseif ($mode === 'php'): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-1"></i>
                    Mode <b>PHP murni (fallback)</b> aktif karena fungsi <code>exec()</code> dinonaktifkan atau binary
                    MySQL tidak tersedia<?= $bin_dir !== '' ? ' di <code>' . htmlspecialchars($bin_dir) . '</code>' : '' ?>.
                    Backup &amp; restore <b>tetap berjalan</b> lewat PHP/mysqli, dengan catatan:
                    <ul class="mb-0 mt-1 small">
                        <li>Struktur + data semua tabel dan view disertakan.</li>
                        <li><b>Trigger, procedure, function, dan event tidak disertakan</b> (aplikasi ini belum memakainya).</li>
                        <li>Database besar memerlukan waktu lebih lama — jangan tutup halaman saat proses berjalan.</li>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-4">
                    <div class="small-box text-bg-primary">
                        <div class="inner"><h3><?= count($daftar) ?></h3><p>File Backup</p></div>
                        <i class="small-box-icon bi bi-folder2-open"></i>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="small-box text-bg-success">
                        <div class="inner"><h3><?= backup_size_human($total_size) ?></h3><p>Total Ukuran</p></div>
                        <i class="small-box-icon bi bi-hdd"></i>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="small-box text-bg-secondary">
                        <div class="inner"><h3 class="fs-5"><?= htmlspecialchars($db) ?></h3><p>Database</p></div>
                        <i class="small-box-icon bi bi-database"></i>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card card-outline card-primary h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="bi bi-download me-1"></i> Buat Backup</h5>
                            <span class="badge <?= $mode === 'binary' ? 'text-bg-success' : 'text-bg-info' ?>"
                                  title="Metode yang dipakai untuk membuat/memulihkan backup">
                                <i class="bi bi-gear-fill me-1"></i><?= htmlspecialchars($mode_label) ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">
                                Membuat salinan penuh database <b><?= htmlspecialchars($db) ?></b> ke folder
                                <code>admin/backup/</code>. File dapat diunduh, dipulihkan, atau dihapus dari daftar di bawah.
                            </p>
                            <form action="aksi/aksi_backup.php" method="post">
                                <input type="hidden" name="aksi" value="buat">
                                <button type="submit" class="btn btn-primary" <?= $tersedia ? '' : 'disabled' ?>>
                                    <i class="bi bi-database-add me-1"></i> Buat Backup Sekarang
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card card-outline card-danger h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-upload me-1"></i> Restore dari File</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning py-2 small mb-3">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                <b>Perhatian!</b> Restore akan <b>menimpa data</b> yang ada saat ini. Pastikan file <code>.sql</code> benar.
                            </div>
                            <form action="aksi/aksi_backup.php" method="post" enctype="multipart/form-data"
                                  onsubmit="return confirm('Restore akan MENIMPA data saat ini. Lanjutkan?');">
                                <input type="hidden" name="aksi" value="restore">
                                <div class="mb-3">
                                    <input type="file" class="form-control" name="file_sql" accept=".sql" required <?= $tersedia ? '' : 'disabled' ?>>
                                </div>
                                <button type="submit" class="btn btn-danger" <?= $tersedia ? '' : 'disabled' ?>>
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Restore Sekarang
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-files me-1"></i> Daftar File Backup</h5>
                </div>
                <div class="card-body table-responsive">
                    <table id="myTable" class="table table-sm table-striped table-hover align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width:45px">No</th>
                                <th>Nama File</th>
                                <th style="width:110px">Ukuran</th>
                                <th style="width:170px">Waktu Dibuat</th>
                                <th style="width:230px" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$daftar): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada file backup.</td></tr>
                            <?php else: $no = 1; foreach ($daftar as $d): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><i class="bi bi-file-earmark-zip me-1 text-secondary"></i><?= htmlspecialchars($d['name']) ?></td>
                                    <td><?= backup_size_human($d['size']) ?></td>
                                    <td><?= date('d M Y H:i', $d['mtime']) ?></td>
                                    <td class="text-center">
                                        <a href="aksi/aksi_backup.php?aksi=download&file=<?= urlencode($d['name']) ?>" class="btn btn-sm btn-outline-primary" title="Unduh">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <form action="aksi/aksi_backup.php" method="post" class="d-inline"
                                              onsubmit="return confirm('Restore dari <?= htmlspecialchars($d['name']) ?>? Data saat ini akan DITIMPA.');">
                                            <input type="hidden" name="aksi" value="restore_file">
                                            <input type="hidden" name="file" value="<?= htmlspecialchars($d['name']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Restore" <?= $tersedia ? '' : 'disabled' ?>>
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                        <a href="aksi/aksi_backup.php?aksi=hapus&file=<?= urlencode($d['name']) ?>"
                                           class="btn btn-sm btn-outline-danger" title="Hapus"
                                           onclick="return confirm('Hapus file <?= htmlspecialchars($d['name']) ?>?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
