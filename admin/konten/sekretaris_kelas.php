<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('koneksi.php');

// ==========================================================
// Tahun ajaran aktif (dari session)
// ==========================================================
$ta_nama_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
$id_ta_sesi = 0;
$q_sesi = $koneksi->prepare("SELECT id_tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
$q_sesi->bind_param("s", $ta_nama_sesi);
$q_sesi->execute();
$r_sesi = $q_sesi->get_result()->fetch_assoc();
$q_sesi->close();
if ($r_sesi) $id_ta_sesi = (int)$r_sesi['id_tahun_ajaran'];

// TA yang dipilih filter (default = aktif dari session)
$id_ta_filter = (int)($_GET['ta'] ?? $id_ta_sesi);
$ta_nama = '-';
if ($id_ta_filter > 0) {
    $q_ta = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE id_tahun_ajaran = ? LIMIT 1");
    $q_ta->bind_param("i", $id_ta_filter);
    $q_ta->execute();
    $row_ta = $q_ta->get_result()->fetch_assoc();
    $q_ta->close();
    if ($row_ta) {
        $id_ta = (int)$row_ta['id_tahun_ajaran'];
        $ta_nama = $row_ta['tahun_ajaran'];
    }
} else {
    $id_ta = $id_ta_sesi;
    $ta_nama = $ta_nama_sesi ?: '-';
}

// ==========================================================
// Daftar semua tahun ajaran (untuk filter dropdown)
// ==========================================================
$ta_list = [];
$q_ta_all = $koneksi->query("SELECT id_tahun_ajaran, tahun_ajaran, status FROM dt_tahun_ajar ORDER BY tahun_ajaran DESC");
if ($q_ta_all) {
    while ($rt = $q_ta_all->fetch_assoc()) {
        $ta_list[] = $rt;
    }
}

// ==========================================================
// Daftar siswa (TA terpilih) dikelompokkan per kelas
// ==========================================================
$siswa_list = [];
if ($id_ta > 0) {
    $q = $koneksi->prepare("
        SELECT s.id_siswa, s.nama, s.jk, s.nipd, s.nisn, ks.id_kelas, k.tingkat, k.nama_kelas, j.nama_jurusan
        FROM dt_kelas_siswa ks
        INNER JOIN dt_siswa s ON s.id_siswa = ks.id_siswa
        INNER JOIN dt_kelas k ON k.id_kelas = ks.id_kelas
        LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
        WHERE ks.id_tahun_ajaran = ?
        ORDER BY k.tingkat ASC, k.nama_kelas ASC, s.nama ASC
    ");
    $q->bind_param("i", $id_ta);
    $q->execute();
    $res = $q->get_result();
    while ($r = $res->fetch_assoc()) {
        $siswa_list[] = $r;
    }
    $q->close();
}

$siswa_per_kelas = [];
foreach ($siswa_list as $s) {
    $key = $s['tingkat'] . ' ' . $s['nama_kelas'] . ($s['nama_jurusan'] ? ' (' . $s['nama_jurusan'] . ')' : '');
    $siswa_per_kelas[$key][] = $s;
}

// ==========================================================
// Daftar akun sekretaris (filter TA terpilih, atau semua bila 0)
// ==========================================================
$sek_list = [];
$where_ta = '';
$params = [];
$types = '';
if ($id_ta > 0) {
    $where_ta = "WHERE sk.id_tahun_ajaran = ?";
    $params[] = $id_ta;
    $types = 'i';
}
$q = $koneksi->prepare("
    SELECT sk.id_sekretaris, sk.username, sk.status_aktif, sk.id_siswa, sk.id_kelas, sk.id_tahun_ajaran,
           s.nama AS nama_siswa, k.tingkat, k.nama_kelas, ta.tahun_ajaran
    FROM dt_sekretaris sk
    LEFT JOIN dt_siswa s ON s.id_siswa = sk.id_siswa
    LEFT JOIN dt_kelas k ON k.id_kelas = sk.id_kelas
    LEFT JOIN dt_tahun_ajar ta ON ta.id_tahun_ajaran = sk.id_tahun_ajaran
    $where_ta
    ORDER BY ta.tahun_ajaran DESC, k.tingkat ASC, k.nama_kelas ASC, s.nama ASC
");
if ($params) {
    $q->bind_param($types, ...$params);
}
$q->execute();
$res = $q->get_result();
while ($r = $res->fetch_assoc()) {
    $sek_list[] = $r;
}
$q->close();
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Sekretaris Kelas</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item">Pendidikan &amp; KBM</li>
                        <li class="breadcrumb-item active" aria-current="page">Sekretaris Kelas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="alert alert-info py-2 mb-3 shadow-sm">
                <i class="bi bi-info-circle-fill me-1"></i>
                Akun untuk <b>sekretaris kelas</b> (login terpisah di <code>/sekretaris</code>). Sekretaris mengisi
                <b>jurnal mengajar &amp; absensi</b> kelasnya. Akun berlaku per tahun ajaran:
                <form method="get" class="d-inline" style="margin:0;">
                    <input type="hidden" name="menu" value="sekretaris_kelas">
                    <select name="ta" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                        <?php foreach ($ta_list as $t): ?>
                            <option value="<?= (int)$t['id_tahun_ajaran']; ?>" <?= ($id_ta_filter == (int)$t['id_tahun_ajaran']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($t['tahun_ajaran']); ?> <?= $t['status'] === 'aktif' ? '<span class="badge bg-success text-xs ms-1">Aktif</span>' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                Terpilih: <b><?= htmlspecialchars($ta_nama); ?></b>.
            </div>

            <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['alert']['type']); ?> alert-dismissible fade show" role="alert">
                    <?= $_SESSION['alert']['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>

            <div class="card card-outline card-primary">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-person-badge me-1"></i> Daftar Akun Sekretaris</h5>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah"
                        <?= ($id_ta <= 0 || empty($siswa_list)) ? 'disabled' : ''; ?>>
                        <i class="bi bi-plus-lg me-1"></i> Tambah Akun
                    </button>
                </div>
                <div class="card-body">
                    <?php if ($id_ta <= 0): ?>
                        <div class="alert alert-warning mb-0">Tidak ada tahun ajaran terpilih.</div>
                    <?php elseif (empty($siswa_list)): ?>
                        <div class="alert alert-warning mb-0">Belum ada siswa terdaftar di kelas pada tahun ajaran <b><?= htmlspecialchars($ta_nama); ?></b>.</div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table id="myTable" class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width:5%;">#</th>
                                    <th>Username</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas</th>
                                    <th>TA</th>
                                    <th class="text-center">Status</th>
                                    <th style="width:16%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($sek_list as $r):
                                    $id_sek = (int)$r['id_sekretaris'];
                                ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><code><?= htmlspecialchars($r['username']); ?></code></td>
                                        <td><?= htmlspecialchars($r['nama_siswa'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars(trim(($r['tingkat'] ?? '') . ' ' . ($r['nama_kelas'] ?? '')) ?: '-'); ?></td>
                                        <td><?= htmlspecialchars($r['tahun_ajaran'] ?? '-'); ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= $r['status_aktif'] === 'Y' ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?= $r['status_aktif'] === 'Y' ? 'Aktif' : 'Nonaktif'; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-primary btn-edit"
                                                data-bs-toggle="modal" data-bs-target="#modalEdit"
                                                data-id="<?= $id_sek; ?>"
                                                data-username="<?= htmlspecialchars($r['username']); ?>"
                                                data-siswa="<?= (int)$r['id_siswa']; ?>"
                                                data-status="<?= htmlspecialchars($r['status_aktif']); ?>">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <a href="aksi/aksi_sekretaris.php?aksi=hapus&id=<?= $id_sek; ?>"
                                                class="btn btn-sm btn-outline-danger btn-hapus"
                                                data-nama="<?= htmlspecialchars($r['username']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" action="aksi/aksi_sekretaris.php" method="post">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-1"></i> Tambah Akun Sekretaris</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="aksi" value="tambah">
                <div class="mb-3">
                    <label class="form-label fw-bold">Siswa (kelas otomatis mengikuti)</label>
                    <select name="id_siswa" class="form-select" required>
                        <option value="">— Pilih siswa —</option>
                        <?php foreach ($siswa_per_kelas as $kel => $items): ?>
                            <optgroup label="<?= htmlspecialchars($kel); ?>">
                                <?php foreach ($items as $s): ?>
                                    <?php $nis_o = trim((string)($s['nipd'] ?? '')) !== '' ? $s['nipd'] : ($s['nisn'] ?? ''); ?>
                                    <option value="<?= (int)$s['id_siswa']; ?>" data-nis="<?= htmlspecialchars((string)$nis_o); ?>"><?= htmlspecialchars($s['nama']); ?> (<?= htmlspecialchars($s['jk']); ?>)<?= $nis_o !== '' ? ' — NIS ' . htmlspecialchars((string)$nis_o) : ''; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Username</label>
                        <input type="text" name="username" id="tambah_username" class="form-control" placeholder="terisi otomatis dari NIS">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Password</label>
                        <input type="text" name="password" class="form-control" placeholder="min. 5 karakter" required>
                    </div>
                </div>
                <div class="mb-1">
                    <label class="form-label fw-bold">Status</label>
                    <select name="status_aktif" class="form-select">
                        <option value="Y" selected>Aktif</option>
                        <option value="N">Nonaktif</option>
                    </select>
                </div>
                <div class="small text-muted mt-2">
                    <i class="bi bi-info-circle me-1"></i> Username otomatis memakai <b>NIS siswa</b> (boleh diubah). Sekretaris login memakai NIS-nya. Akun dibuat untuk TA <b><?= htmlspecialchars($ta_nama ?: '-'); ?></b>; password disimpan terenkripsi (bcrypt).
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" action="aksi/aksi_sekretaris.php" method="post">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-1"></i> Edit Akun Sekretaris</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="aksi" value="edit">
                <input type="hidden" name="id_sekretaris" id="edit_id">
                <div class="mb-3">
                    <label class="form-label fw-bold">Siswa (kelas otomatis mengikuti)</label>
                    <select name="id_siswa" id="edit_siswa" class="form-select" required>
                        <option value="">— Pilih siswa —</option>
                        <?php foreach ($siswa_per_kelas as $kel => $items): ?>
                            <optgroup label="<?= htmlspecialchars($kel); ?>">
                                <?php foreach ($items as $s): ?>
                                    <?php $nis_o = trim((string)($s['nipd'] ?? '')) !== '' ? $s['nipd'] : ($s['nisn'] ?? ''); ?>
                                    <option value="<?= (int)$s['id_siswa']; ?>" data-nis="<?= htmlspecialchars((string)$nis_o); ?>"><?= htmlspecialchars($s['nama']); ?> (<?= htmlspecialchars($s['jk']); ?>)<?= $nis_o !== '' ? ' — NIS ' . htmlspecialchars((string)$nis_o) : ''; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Username</label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Password Baru</label>
                        <input type="text" name="password" class="form-control" placeholder="kosongkan bila tidak diubah">
                    </div>
                </div>
                <div class="mb-1">
                    <label class="form-label fw-bold">Status</label>
                    <select name="status_aktif" id="edit_status" class="form-select">
                        <option value="Y">Aktif</option>
                        <option value="N">Nonaktif</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-edit').forEach(function (b) {
        b.addEventListener('click', function () {
            document.getElementById('edit_id').value = b.getAttribute('data-id');
            document.getElementById('edit_username').value = b.getAttribute('data-username');
            document.getElementById('edit_status').value = b.getAttribute('data-status');
            var sel = document.getElementById('edit_siswa');
            sel.value = b.getAttribute('data-siswa');
        });
    });

    function nisDariSelect(sel) {
        var opt = sel.options[sel.selectedIndex];
        return opt ? (opt.getAttribute('data-nis') || '') : '';
    }
    var selTambah = document.querySelector('#modalTambah select[name="id_siswa"]');
    if (selTambah) {
        selTambah.addEventListener('change', function () {
            var nis = nisDariSelect(selTambah);
            if (nis !== '') document.getElementById('tambah_username').value = nis;
        });
    }
    var selEdit = document.getElementById('edit_siswa');
    if (selEdit) {
        selEdit.addEventListener('change', function () {
            var nis = nisDariSelect(selEdit);
            if (nis !== '') document.getElementById('edit_username').value = nis;
        });
    }

    document.querySelectorAll('.btn-hapus').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var url = a.getAttribute('href');
            var nama = a.getAttribute('data-nama') || '';
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Hapus akun ini?',
                    html: 'Akun <b>' + nama + '</b> akan dihapus.',
                    icon: 'warning', showCancelButton: true,
                    confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal',
                    confirmButtonColor: '#dc3545'
                }).then(function (r) { if (r.isConfirmed) window.location.href = url; });
            } else if (confirm('Hapus akun ' + nama + '?')) {
                window.location.href = url;
            }
        });
    });
});
</script>
