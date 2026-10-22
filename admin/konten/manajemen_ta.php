<?php
// Halaman Manajemen Tahun Ajaran — Salin Data Antar TA (Administrasi Sistem). RBAC: Admin.
include("koneksi.php");

// Reset pratinjau
if (isset($_GET['reset'])) {
    unset($_SESSION['ta_preview']);
}

// Daftar TA + jumlah kelas (untuk label dropdown)
$ta_list = [];
$q = $koneksi->query("
    SELECT t.id_tahun_ajaran, t.tahun_ajaran, t.status,
           (SELECT COUNT(*) FROM dt_kelas k WHERE k.id_tahun_ajaran = t.id_tahun_ajaran) AS jml_kelas
    FROM dt_tahun_ajar t
    ORDER BY t.tahun_ajaran DESC
");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $ta_list[] = $r;
    }
}

$preview = $_SESSION['ta_preview'] ?? null;
$hasil = $_SESSION['ta_hasil'] ?? null;
if ($hasil) unset($_SESSION['ta_hasil']);
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Manajemen Tahun Ajaran</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item">Administrasi Sistem</li>
                        <li class="breadcrumb-item active" aria-current="page">Manajemen Tahun Ajaran</li>
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

            <?php if ($hasil): ?>
                <div class="card card-outline card-success">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-check-circle me-1"></i> Hasil Salin Terakhir</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">
                            Salin dari <b><?= htmlspecialchars($hasil['nama_sumber']) ?></b>
                            &rarr; <b><?= htmlspecialchars($hasil['nama_target']) ?></b>
                        </p>
                        <span class="badge text-bg-primary me-1"><?= (int)$hasil['kelas'] ?> kelas baru</span>
                        <span class="badge text-bg-success me-1"><?= (int)$hasil['wali'] ?> wali kelas baru</span>
                        <span class="badge text-bg-info me-1"><?= (int)$hasil['pengampu'] ?> pengampu mapel baru</span>
                        <?php if ($hasil['skip_wali'] > 0): ?><span class="badge text-bg-secondary me-1"><?= (int)$hasil['skip_wali'] ?> wali dilewati</span><?php endif; ?>
                        <?php if ($hasil['skip_pen'] > 0): ?><span class="badge text-bg-secondary me-1"><?= (int)$hasil['skip_pen'] ?> pengampu dilewati</span><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-5 mb-3">
                    <div class="card card-outline card-primary h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-copy me-1"></i> Salin Data Antar Tahun Ajaran</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">
                                Menyalin <b>struktur rombel</b> (kelas), <b>wali kelas</b>, dan <b>pengampu mapel</b> dari satu
                                tahun ajaran ke tahun ajaran lain. Pemetaan kelas berdasarkan
                                <i>tingkat + nama kelas + jurusan</i>; data yang sudah ada di tujuan tidak akan diduplikasi.
                                Cocok untuk menyiapkan struktur tahun ajaran baru.
                            </p>
                            <form action="aksi/aksi_manajemen_ta.php" method="post">
                                <input type="hidden" name="aksi" value="preview">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Tahun Ajaran Sumber</label>
                                    <select name="id_sumber" class="form-select" required>
                                        <option value="">— pilih sumber —</option>
                                        <?php foreach ($ta_list as $t): ?>
                                            <option value="<?= (int)$t['id_tahun_ajaran'] ?>">
                                                <?= htmlspecialchars($t['tahun_ajaran']) ?><?= $t['status'] === 'aktif' ? ' (aktif)' : '' ?> — <?= (int)$t['jml_kelas'] ?> kelas
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="text-center text-muted my-2"><i class="bi bi-arrow-down"></i></div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Tahun Ajaran Tujuan</label>
                                    <select name="id_target" class="form-select" required>
                                        <option value="">— pilih tujuan —</option>
                                        <?php foreach ($ta_list as $t): ?>
                                            <option value="<?= (int)$t['id_tahun_ajaran'] ?>">
                                                <?= htmlspecialchars($t['tahun_ajaran']) ?><?= $t['status'] === 'aktif' ? ' (aktif)' : '' ?> — <?= (int)$t['jml_kelas'] ?> kelas
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Komponen yang Disalin</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="komp_kelas" id="komp_kelas" value="1" checked>
                                        <label class="form-check-label" for="komp_kelas">Kelas / Rombel <span class="text-muted small">(dasar untuk komponen lain)</span></label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="komp_wali" id="komp_wali" value="1" checked>
                                        <label class="form-check-label" for="komp_wali">Wali Kelas</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="komp_pengampu" id="komp_pengampu" value="1" checked>
                                        <label class="form-check-label" for="komp_pengampu">Pengampu Mapel</label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search me-1"></i> Pratinjau (Preview)
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 mb-3">
                    <?php if (!$preview): ?>
                        <div class="card h-100 card-outline card-secondary">
                            <div class="card-body d-flex flex-column align-items-center justify-content-center text-center text-muted py-5">
                                <i class="bi bi-clipboard-check" style="font-size:3rem"></i>
                                <p class="mt-3 mb-0">Pilih tahun ajaran sumber &amp; tujuan, lalu klik <b>Pratinjau</b> untuk melihat rencana penyalinan sebelum dijalankan.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card card-outline card-warning">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0"><i class="bi bi-clipboard-check me-1"></i> Pratinjau</h5>
                                <a href="index.php?menu=manajemen_ta&reset=1" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i> Batal</a>
                            </div>
                            <div class="card-body">
                                <p class="mb-3">
                                    Salin dari <b><?= htmlspecialchars($preview['nama_sumber']) ?></b>
                                    &rarr; <b><?= htmlspecialchars($preview['nama_target']) ?></b>
                                </p>

                                <?php if ($preview['komp']['kelas']): ?>
                                    <h6 class="text-uppercase text-muted small mb-1">Kelas</h6>
                                    <p class="mb-2">
                                        <span class="badge text-bg-success"><?= (int)$preview['kelas']['baru'] ?> baru</span>
                                        <span class="badge text-bg-secondary"><?= (int)$preview['kelas']['ada'] ?> sudah ada</span>
                                    </p>
                                <?php endif; ?>
                                <?php if ($preview['komp']['wali']): ?>
                                    <h6 class="text-uppercase text-muted small mb-1">Wali Kelas</h6>
                                    <p class="mb-2">
                                        <span class="badge text-bg-success"><?= (int)$preview['wali']['baru'] ?> baru</span>
                                        <span class="badge text-bg-secondary"><?= (int)$preview['wali']['ada'] ?> sudah ada</span>
                                        <?php if ($preview['wali']['skip'] > 0): ?><span class="badge text-bg-warning"><?= (int)$preview['wali']['skip'] ?> dilewati</span><?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($preview['komp']['pengampu']): ?>
                                    <h6 class="text-uppercase text-muted small mb-1">Pengampu Mapel</h6>
                                    <p class="mb-2">
                                        <span class="badge text-bg-success"><?= (int)$preview['pengampu']['baru'] ?> baru</span>
                                        <span class="badge text-bg-secondary"><?= (int)$preview['pengampu']['ada'] ?> sudah ada</span>
                                        <?php if ($preview['pengampu']['skip'] > 0): ?><span class="badge text-bg-warning"><?= (int)$preview['pengampu']['skip'] ?> dilewati</span><?php endif; ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ($preview['komp']['kelas'] && !empty($preview['kelas']['items'])): ?>
                                    <div class="table-responsive" style="max-height:220px;overflow:auto">
                                        <table class="table table-sm table-striped align-middle mb-2">
                                            <thead><tr><th>Kelas</th><th>Jurusan</th><th style="width:110px">Status</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($preview['kelas']['items'] as $it): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($it['nama']) ?></td>
                                                    <td><?= htmlspecialchars($it['jurusan']) ?></td>
                                                    <td>
                                                        <?php if ($it['status'] === 'baru'): ?>
                                                            <span class="badge text-bg-success">Baru</span>
                                                        <?php else: ?>
                                                            <span class="badge text-bg-secondary">Sudah ada</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>

                                <?php if ($preview['komp']['wali'] && !empty($preview['wali']['items'])): ?>
                                    <div class="table-responsive" style="max-height:200px;overflow:auto">
                                        <table class="table table-sm table-striped align-middle mb-2">
                                            <thead><tr><th>Kelas</th><th>Guru</th><th style="width:110px">Status</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($preview['wali']['items'] as $it): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($it['kelas']) ?></td>
                                                    <td><?= htmlspecialchars($it['guru']) ?></td>
                                                    <td>
                                                        <?php if ($it['status'] === 'baru'): ?>
                                                            <span class="badge text-bg-success">Baru</span>
                                                        <?php else: ?>
                                                            <span class="badge text-bg-secondary">Sudah ada</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>

                                <hr>
                                <form action="aksi/aksi_manajemen_ta.php" method="post"
                                      onsubmit="return confirm('Jalankan penyalinan data sekarang? Data yang sudah ada tidak akan diduplikasi.');">
                                    <input type="hidden" name="aksi" value="salin">
                                    <input type="hidden" name="id_sumber" value="<?= (int)$preview['id_sumber'] ?>">
                                    <input type="hidden" name="id_target" value="<?= (int)$preview['id_target'] ?>">
                                    <?php if ($preview['komp']['kelas']): ?><input type="hidden" name="komp_kelas" value="1"><?php endif; ?>
                                    <?php if ($preview['komp']['wali']): ?><input type="hidden" name="komp_wali" value="1"><?php endif; ?>
                                    <?php if ($preview['komp']['pengampu']): ?><input type="hidden" name="komp_pengampu" value="1"><?php endif; ?>
                                    <a href="index.php?menu=manajemen_ta&reset=1" class="btn btn-outline-secondary">Ulangi Pilih</a>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="bi bi-play-circle me-1"></i> Eksekusi Salin
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
