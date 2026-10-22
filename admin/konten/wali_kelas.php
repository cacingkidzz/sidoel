<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
</div>
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("koneksi.php");

// ==========================================================
// 1. AMBIL TAHUN AJARAN DARI SESI (TITIK ACUAN UTAMA)
// ==========================================================
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? 'Tahun Ajaran Tidak Ditemukan';
$id_ta_aktif = 0;
$nama_ta_aktif = htmlspecialchars($nama_ta_sesi);

if (!$koneksi->connect_error) {
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
// 2. DATA UNTUK DROPDOWN
// ==========================================================
// Semua guru (untuk edit: boleh ganti ke guru mana pun)
$semua_guru = [];
$q_guru = $koneksi->query("SELECT id_guru, nama_guru FROM dt_guru ORDER BY nama_guru ASC");
if ($q_guru) {
    while ($g = $q_guru->fetch_assoc()) {
        $semua_guru[$g['id_guru']] = $g['nama_guru'];
    }
}

// Guru yang BELUM menjadi wali kelas manapun di TA ini (untuk tambah)
$guru_terpakai = [];
$q_terpakai = $koneksi->query("SELECT id_guru FROM dt_wali_kelas WHERE id_tahun_ajaran = '$id_ta_aktif'");
if ($q_terpakai) {
    while ($t = $q_terpakai->fetch_assoc()) {
        $guru_terpakai[] = $t['id_guru'];
    }
}
$guru_tersedia = array_diff_key($semua_guru, array_flip($guru_terpakai));

// Kelas TA aktif yang BELUM punya wali (untuk tambah)
$kelas_tanpa_wali = [];
$q_kw = $koneksi->query("
    SELECT k.id_kelas, k.nama_kelas, k.tingkat
    FROM dt_kelas k
    LEFT JOIN dt_wali_kelas wk ON k.id_kelas = wk.id_kelas
    WHERE k.id_tahun_ajaran = '$id_ta_aktif' AND wk.id_wali_kelas IS NULL
    ORDER BY CASE k.tingkat WHEN 'X' THEN 1 WHEN 'XI' THEN 2 WHEN 'XII' THEN 3 ELSE 4 END, k.nama_kelas ASC
");
if ($q_kw) {
    while ($k = $q_kw->fetch_assoc()) {
        $kelas_tanpa_wali[$k['id_kelas']] = $k['tingkat'] . ' - ' . $k['nama_kelas'];
    }
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Penugasan Wali Kelas</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Wali Kelas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="alert alert-info py-2 mb-3 shadow-sm">
                <i class="bi bi-info-circle-fill me-1"></i> Penugasan wali kelas berlaku untuk Tahun Ajaran: <b><?= $nama_ta_aktif; ?></b>. Setiap kelas memiliki <b>satu wali kelas</b>.
            </div>

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

            <div class="row">
                <div class="col-xl-12 col-md-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="bi bi-person-bounding-box me-1"></i> Tabel Wali Kelas</h5>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahWali"
                                <?= ($id_ta_aktif == 0 || empty($kelas_tanpa_wali) || empty($guru_tersedia)) ? 'disabled' : '' ?>>
                                <i class="bi bi-plus-circle-fill me-1"></i> Tambah Wali Kelas
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($kelas_tanpa_wali) && empty($semua_guru) === false): ?>
                                <div class="alert alert-success small py-2 mb-3">
                                    <i class="bi bi-check-circle-fill me-1"></i> Semua kelas tahun ajaran ini sudah memiliki wali kelas.
                                </div>
                            <?php endif; ?>

                            <div class="table-responsive">
                                <table id="myTable" class="table table-hover table-bordered table-sm w-100">
                                    <thead>
                                        <tr class="table-primary">
                                            <th style="width: 5%;">#</th>
                                            <th style="width: 10%;">Tingkat</th>
                                            <th style="width: 20%;">Nama Kelas</th>
                                            <th style="width: 25%;">Jurusan</th>
                                            <th style="width: 25%;">Wali Kelas</th>
                                            <th style="width: 8%;">Jml Siswa</th>
                                            <th style="width: 15%;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $no = 1;
                                        $sql = $koneksi->query("
                                            SELECT k.id_kelas, k.nama_kelas, k.tingkat, j.nama_jurusan,
                                                   wk.id_wali_kelas, wk.id_guru, g.nama_guru,
                                                   (SELECT COUNT(*) FROM dt_kelas_siswa ks WHERE ks.id_kelas = k.id_kelas AND ks.id_tahun_ajaran = k.id_tahun_ajaran) AS jml_siswa
                                            FROM dt_kelas k
                                            LEFT JOIN dt_jurusan j ON k.id_jurusan = j.id_jurusan
                                            LEFT JOIN dt_wali_kelas wk ON k.id_kelas = wk.id_kelas AND wk.id_tahun_ajaran = k.id_tahun_ajaran
                                            LEFT JOIN dt_guru g ON wk.id_guru = g.id_guru
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
                                            $ada_wali = $row['id_wali_kelas'] !== null;
                                        ?>
                                            <tr>
                                                <td><?= $no++; ?></td>
                                                <td><span class="badge <?= $badge_class; ?>"><?= htmlspecialchars($row['tingkat']); ?></span></td>
                                                <td><b><?= htmlspecialchars($row['nama_kelas']); ?></b></td>
                                                <td><?= htmlspecialchars($row['nama_jurusan'] ?? '-'); ?></td>
                                                <td>
                                                    <?php if ($ada_wali): ?>
                                                        <span class="badge bg-success"><i class="bi bi-person-check me-1"></i><?= htmlspecialchars($row['nama_guru']); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Belum Ditugaskan</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center"><?= (int)$row['jml_siswa']; ?></td>
                                                <td class="text-center">
                                                    <?php if ($ada_wali): ?>
                                                        <button type="button" class="btn btn-success btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editWali<?= $row['id_wali_kelas']; ?>" title="Edit Wali">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm" id="deleteWali<?= $row['id_wali_kelas']; ?>" title="Hapus Wali"><i class="bi bi-trash-fill"></i></button>
                                                        <script>
                                                            document.getElementById("deleteWali<?= $row['id_wali_kelas']; ?>").addEventListener("click", function(event) {
                                                                event.preventDefault();
                                                                if (typeof Swal !== 'undefined') {
                                                                    Swal.fire({
                                                                        title: 'Yakin ingin menghapus?',
                                                                        text: 'Wali kelas <?= htmlspecialchars($row['nama_guru'], ENT_QUOTES); ?> untuk kelas <?= htmlspecialchars($row['nama_kelas'], ENT_QUOTES); ?> akan dilepas.',
                                                                        icon: 'warning',
                                                                        showCancelButton: true,
                                                                        confirmButtonText: 'Ya, lepas!',
                                                                        cancelButtonText: 'Batal',
                                                                        confirmButtonColor: '#dc3545',
                                                                        cancelButtonColor: '#6c757d'
                                                                    }).then((result) => {
                                                                        if (result.isConfirmed) {
                                                                            window.location.href = 'aksi/aksi_wali_kelas.php?aksi=hapus&id=<?= $row['id_wali_kelas']; ?>';
                                                                        }
                                                                    });
                                                                } else {
                                                                    if (confirm('Lepas wali kelas <?= htmlspecialchars($row['nama_guru'], ENT_QUOTES); ?> dari kelas <?= htmlspecialchars($row['nama_kelas'], ENT_QUOTES); ?>?')) {
                                                                        window.location.href = 'aksi/aksi_wali_kelas.php?aksi=hapus&id=<?= $row['id_wali_kelas']; ?>';
                                                                    }
                                                                }
                                                            });
                                                        </script>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahWali" title="Tetapkan Wali"
                                                            <?= (empty($guru_tersedia)) ? 'disabled' : '' ?>>
                                                            <i class="bi bi-person-plus-fill"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>

                                            <?php if ($ada_wali): ?>
                                                <!-- Modal Edit Wali Kelas -->
                                                <div class="modal fade" id="editWali<?= $row['id_wali_kelas']; ?>" tabindex="-1" aria-labelledby="editWaliLabel<?= $row['id_wali_kelas']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog modal-md">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-success text-white">
                                                                <h5 class="modal-title" id="editWaliLabel<?= $row['id_wali_kelas']; ?>"><i class="bi bi-pencil-square me-1"></i> Edit Wali Kelas</h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <form action="aksi/aksi_wali_kelas.php" method="post">
                                                                    <input type="hidden" name="aksi" value="edit">
                                                                    <input type="hidden" name="id_wali_kelas" value="<?= $row['id_wali_kelas']; ?>">
                                                                    <input type="hidden" name="id_kelas" value="<?= $row['id_kelas']; ?>">

                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-bold">Kelas</label>
                                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['tingkat'] . ' - ' . $row['nama_kelas']); ?>" readonly>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-bold">Guru / Wali Kelas</label>
                                                                        <select class="form-control" name="id_guru" required>
                                                                            <?php foreach ($semua_guru as $id_g => $nama_g): ?>
                                                                                <option value="<?= $id_g; ?>" <?= ($row['id_guru'] == $id_g) ? 'selected' : ''; ?>><?= htmlspecialchars($nama_g); ?></option>
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
                                            <?php endif; ?>
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

<!-- Modal Tambah Wali Kelas -->
<div class="modal fade" id="tambahWali" tabindex="-1" aria-labelledby="tambahWaliLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="tambahWaliLabel"><i class="bi bi-person-plus-fill me-1"></i> Tambah Wali Kelas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (empty($kelas_tanpa_wali)): ?>
                    <div class="alert alert-warning small mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Semua kelas tahun ajaran ini sudah memiliki wali kelas. Lepas salah satu wali terlebih dahulu untuk mengubah penugasan.
                    </div>
                <?php elseif (empty($guru_tersedia)): ?>
                    <div class="alert alert-warning small mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Semua guru sudah bertugas sebagai wali kelas tahun ajaran ini.
                    </div>
                <?php else: ?>
                    <form action="aksi/aksi_wali_kelas.php" method="post">
                        <input type="hidden" name="aksi" value="tambah">
                        <input type="hidden" name="id_tahun_ajaran" value="<?= $id_ta_aktif; ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Kelas</label>
                            <select class="form-control" name="id_kelas" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($kelas_tanpa_wali as $id_k => $nama_k): ?>
                                    <option value="<?= $id_k; ?>"><?= htmlspecialchars($nama_k); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Hanya kelas yang belum punya wali.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Guru / Wali Kelas</label>
                            <select class="form-control" name="id_guru" required>
                                <option value="">-- Pilih Guru --</option>
                                <?php foreach ($guru_tersedia as $id_g => $nama_g): ?>
                                    <option value="<?= $id_g; ?>"><?= htmlspecialchars($nama_g); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Hanya guru yang belum menjadi wali kelas tahun ajaran ini.</small>
                        </div>

                        <div class="modal-footer px-0 pb-0 pt-3 border-top">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
