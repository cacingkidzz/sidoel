<?php
// Halaman Profil Saya + ganti password sendiri.
include("koneksi.php");

$id_user = (int) ($_SESSION['id_user'] ?? 0);

$u = null;
if ($id_user > 0) {
    $stmt = $koneksi->prepare("SELECT u.id_user, u.username, u.level_user, u.status_aktif, u.created_at, u.password, g.nama_guru
                               FROM dt_user u
                               LEFT JOIN dt_guru g ON g.id_guru = u.id_fk
                               WHERE u.id_user = ? LIMIT 1");
    $stmt->bind_param('i', $id_user);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$nama_tampil = 'Administrator Sistem';
if ($u) {
    if (!empty($u['nama_guru'])) {
        $nama_tampil = $u['nama_guru'];
    } elseif ($u['level_user'] !== 'Admin') {
        $nama_tampil = 'User Internal';
    }
}

$badge_level = [
    'Admin' => 'bg-danger',
    'Kurikulum' => 'bg-success',
    'Kesiswaan' => 'bg-info',
    'Guru' => 'bg-primary',
][$u['level_user'] ?? ''] ?? 'bg-secondary';

$is_md5 = $u && password_md5_lama($u['password']);
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Profil Saya</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Profil</li>
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

            <div class="row">
                <?php if (!$u): ?>
                    <div class="col-12">
                        <div class="alert alert-danger">Data pengguna tidak ditemukan. Silakan login kembali.</div>
                    </div>
                <?php else: ?>
                    <div class="col-md-5 mb-3">
                        <div class="card card-outline card-primary h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-person-badge me-1"></i> Informasi Akun</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <i class="bi bi-person-circle" style="font-size: 4rem; color: #adb5bd;"></i>
                                    <h5 class="mt-2 mb-0"><?= htmlspecialchars($nama_tampil) ?></h5>
                                    <span class="badge <?= $badge_level ?>"><?= htmlspecialchars($u['level_user']) ?></span>
                                </div>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="text-muted">Username</span>
                                        <span class="fw-semibold text-end"><?= htmlspecialchars($u['username']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="text-muted">Level Akses</span>
                                        <span class="fw-semibold"><?= htmlspecialchars($u['level_user']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="text-muted">Status</span>
                                        <span class="badge <?= $u['status_aktif'] === 'Y' ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $u['status_aktif'] === 'Y' ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="text-muted">Guru Terkait</span>
                                        <span class="fw-semibold text-end"><?= htmlspecialchars($u['nama_guru'] ?? '-') ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="text-muted">Dibuat</span>
                                        <span class="text-end"><?= $u['created_at'] ? date('d M Y H:i', strtotime($u['created_at'])) : '-' ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span class="text-muted">Tipe Password</span>
                                        <?php if ($is_md5): ?>
                                            <span class="badge bg-warning text-dark" title="Akan otomatis di-upgrade ke bcrypt saat login / ganti password">
                                                MD5 (lama)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">bcrypt</span>
                                        <?php endif; ?>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-7 mb-3">
                        <div class="card card-outline card-success h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0"><i class="bi bi-key-fill me-1"></i> Ganti Password</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small">
                                    Gunakan minimal <b>6 karakter</b>. Setelah diganti, password disimpan dengan
                                    pengamanan <b>bcrypt</b>.
                                </p>
                                <form action="aksi/aksi_profil.php" method="post" autocomplete="off">
                                    <input type="hidden" name="aksi" value="ganti_password">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Password Lama</label>
                                        <input type="password" class="form-control" name="password_lama" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Password Baru</label>
                                        <input type="password" class="form-control" name="password_baru" minlength="6" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Konfirmasi Password Baru</label>
                                        <input type="password" class="form-control" name="password_konfirm" minlength="6" required>
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-shield-check me-1"></i> Simpan Password Baru
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
