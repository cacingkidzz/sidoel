<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("koneksi.php");

// ==========================================================
// 1. TAHUN AJARAN AKTIF (dari session)
// ==========================================================
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
$id_ta_aktif = 0;
$nama_ta_aktif = htmlspecialchars($nama_ta_sesi);

if (isset($koneksi) && $koneksi instanceof mysqli && !$koneksi->connect_error && $nama_ta_sesi !== '') {
    $q_ta = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    if ($q_ta) {
        $q_ta->bind_param("s", $nama_ta_sesi);
        $q_ta->execute();
        $res_ta = $q_ta->get_result();
        if ($res_ta && $res_ta->num_rows > 0) {
            $TA = $res_ta->fetch_assoc();
            $id_ta_aktif = (int)$TA['id_tahun_ajaran'];
            $nama_ta_aktif = htmlspecialchars($TA['tahun_ajaran']);
        }
        $q_ta->close();
    }
}

// ==========================================================
// 2. DROPDOWN: KELAS (TA aktif) & GURU
// ==========================================================
$kelas_list = [];
if ($id_ta_aktif > 0) {
    $q_k = $koneksi->query("
        SELECT k.id_kelas, k.nama_kelas, k.tingkat, j.nama_jurusan
        FROM dt_kelas k
        LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
        WHERE k.id_tahun_ajaran = '$id_ta_aktif'
        ORDER BY CASE k.tingkat WHEN 'X' THEN 1 WHEN 'XI' THEN 2 WHEN 'XII' THEN 3 WHEN 'XIII' THEN 4 ELSE 5 END, k.nama_kelas ASC
    ");
    if ($q_k) {
        while ($k = $q_k->fetch_assoc()) {
            $kelas_list[] = $k;
        }
    }
}

// Kelas dikelompokkan per tingkat (untuk tampilan Per Guru)
$kelas_by_tingkat = [];
foreach ($kelas_list as $k) {
    $kelas_by_tingkat[$k['tingkat']][] = $k;
}

$semua_guru = [];
$q_g = $koneksi->query("SELECT id_guru, nama_guru FROM dt_guru ORDER BY nama_guru ASC");
if ($q_g) {
    while ($g = $q_g->fetch_assoc()) {
        $semua_guru[(int)$g['id_guru']] = $g['nama_guru'];
    }
}

// Semua mapel (untuk matriks), dikelompokkan
$mapel_list = [];
$q_m = $koneksi->query("
    SELECT id_mapel, kode_mapel, nama_mapel, kelompok_mapel
    FROM dt_mapel
    ORDER BY CASE kelompok_mapel
                WHEN 'A' THEN 1 WHEN 'B' THEN 2 WHEN 'C1' THEN 3
                WHEN 'C2' THEN 4 WHEN 'C3' THEN 5 WHEN 'Muatan Lokal' THEN 6 ELSE 7 END,
             kode_mapel ASC, nama_mapel ASC
");
if ($q_m) {
    while ($m = $q_m->fetch_assoc()) {
        $mapel_list[] = $m;
    }
}

// ==========================================================
// 3. KELAS TERPILIH
// ==========================================================
$id_kelas = isset($_GET['kelas']) ? (int)$_GET['kelas'] : 0;
$kelas_terpilih = null;
foreach ($kelas_list as $k) {
    if ((int)$k['id_kelas'] === $id_kelas) {
        $kelas_terpilih = $k;
        break;
    }
}

// Pengampu yang sudah ada pada kelas+TA ini -> map id_mapel => id_guru
$pengampu_map = [];
$jml_terisi = 0;
if ($kelas_terpilih) {
    $q_p = $koneksi->prepare("SELECT id_mapel, id_guru FROM dt_pengampu_mapel WHERE id_kelas = ? AND id_tahun_ajaran = ?");
    if ($q_p) {
        $q_p->bind_param("ii", $id_kelas, $id_ta_aktif);
        $q_p->execute();
        $res_p = $q_p->get_result();
        while ($p = $res_p->fetch_assoc()) {
            $pengampu_map[(int)$p['id_mapel']] = (int)$p['id_guru'];
        }
        $q_p->close();
    }
    $jml_terisi = count($pengampu_map);
}

// ==========================================================
// 4. DATA UNTUK MODE "PER GURU" (guru -> mapel -> centang kelas)
// ==========================================================
$mode = $_GET['mode'] ?? 'kelas';

$pengampu_all = []; // id_mapel => [id_kelas => id_guru]
if ($id_ta_aktif > 0) {
    $q_pa = $koneksi->prepare("SELECT id_mapel, id_kelas, id_guru FROM dt_pengampu_mapel WHERE id_tahun_ajaran = ?");
    if ($q_pa) {
        $q_pa->bind_param("i", $id_ta_aktif);
        $q_pa->execute();
        $res_pa = $q_pa->get_result();
        while ($pa = $res_pa->fetch_assoc()) {
            $pengampu_all[(int)$pa['id_mapel']][(int)$pa['id_kelas']] = (int)$pa['id_guru'];
        }
        $q_pa->close();
    }
}

$badge_kelompok = function ($k) {
    return match ($k) {
        'A' => 'bg-primary',
        'B' => 'bg-success',
        'C1' => 'bg-info text-dark',
        'C2' => 'bg-warning text-dark',
        'C3' => 'bg-danger',
        'Muatan Lokal' => 'bg-secondary',
        default => 'bg-light text-dark',
    };
};
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Pengampu Mata Pelajaran</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Pengampu Mapel</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="alert alert-info py-2 mb-3 shadow-sm">
                <i class="bi bi-info-circle-fill me-1"></i> Tugaskan guru pengampu untuk setiap mata pelajaran. Berlaku untuk Tahun Ajaran: <b><?= $nama_ta_aktif !== '' ? $nama_ta_aktif : '-'; ?></b>. Satu mapel dapat diampu oleh satu guru per kelas.
            </div>

            <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['alert']['type']); ?> alert-dismissible fade show" role="alert">
                    <?= $_SESSION['alert']['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>

            <ul class="nav nav-tabs mb-3">
                <li class="nav-item">
                    <a class="nav-link <?= $mode !== 'guru' ? 'active' : ''; ?>" href="index.php?menu=pengampu_mapel"><i class="bi bi-grid-3x3-gap"></i> Per Kelas</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $mode === 'guru' ? 'active' : ''; ?>" href="index.php?menu=pengampu_mapel&mode=guru"><i class="bi bi-person-badge"></i> Per Guru</a>
                </li>
            </ul>

            <?php if ($mode === 'guru'): ?>
                <div class="card card-outline card-success mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-person-badge me-1"></i> Penugasan Per Guru</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($semua_guru)): ?>
                            <div class="alert alert-warning mb-0">Belum ada data guru.</div>
                        <?php elseif (empty($kelas_list)): ?>
                            <div class="alert alert-warning mb-0">Belum ada kelas pada tahun ajaran ini (<b><?= $nama_ta_aktif; ?></b>).</div>
                        <?php else: ?>
                        <?php
                        // Guru terpilih (GET) + mapel referensinya (dt_guru_mapel)
                        $id_guru_sel = isset($_GET['guru']) ? (int)$_GET['guru'] : 0;
                        $guru_sel_nama = ($id_guru_sel > 0 && isset($semua_guru[$id_guru_sel])) ? $semua_guru[$id_guru_sel] : null;

                        $mapel_guru = [];
                        if ($guru_sel_nama !== null) {
                            $q_mg = $koneksi->prepare("
                                SELECT m.id_mapel, m.kode_mapel, m.nama_mapel, m.kelompok_mapel
                                FROM dt_guru_mapel gm
                                INNER JOIN dt_mapel m ON m.id_mapel = gm.id_mapel
                                WHERE gm.id_guru = ?
                                ORDER BY CASE m.kelompok_mapel
                                            WHEN 'A' THEN 1 WHEN 'B' THEN 2 WHEN 'C1' THEN 3
                                            WHEN 'C2' THEN 4 WHEN 'C3' THEN 5 WHEN 'Muatan Lokal' THEN 6 ELSE 7 END,
                                         m.kode_mapel ASC, m.nama_mapel ASC
                            ");
                            if ($q_mg) {
                                $q_mg->bind_param("i", $id_guru_sel);
                                $q_mg->execute();
                                $res_mg = $q_mg->get_result();
                                while ($mg = $res_mg->fetch_assoc()) {
                                    $mapel_guru[] = $mg;
                                }
                                $q_mg->close();
                            }
                        }
                        ?>

                        <form method="get" class="row g-2 align-items-end mb-3">
                            <input type="hidden" name="menu" value="pengampu_mapel">
                            <input type="hidden" name="mode" value="guru">
                            <div class="col-md-6">
                                <label class="form-label fw-bold mb-1">Guru</label>
                                <select name="guru" class="form-select" onchange="this.form.submit()">
                                    <option value="">— Pilih guru —</option>
                                    <?php foreach ($semua_guru as $idg => $nama_g): ?>
                                        <option value="<?= $idg; ?>" <?= ($id_guru_sel === $idg) ? 'selected' : ''; ?>><?= htmlspecialchars($nama_g); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>

                        <?php if (!$guru_sel_nama): ?>
                            <div class="alert alert-secondary mb-0"><i class="bi bi-info-circle"></i> Silakan pilih guru untuk menugaskan kelas pada mapel yang diampu.</div>
                        <?php elseif (empty($mapel_guru)): ?>
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                <b><?= htmlspecialchars($guru_sel_nama); ?></b> belum memiliki mapel di referensi.
                                Atur dulu di menu <a href="index.php?menu=guru_mapel&amp;guru=<?= $id_guru_sel; ?>" class="alert-link">Pendidikan &amp; KBM &rarr; Guru Mapel</a>.
                            </div>
                        <?php else: ?>
                            <form action="aksi/aksi_pengampu_mapel.php" method="post" id="formPengampuGuru">
                                <input type="hidden" name="aksi" value="simpan_guru_total">
                                <input type="hidden" name="id_guru" value="<?= $id_guru_sel; ?>">
                                <input type="hidden" name="id_tahun_ajaran" value="<?= $id_ta_aktif; ?>">

                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                    <div class="text-muted small">
                                        <i class="bi bi-info-circle"></i> Centang kelas tempat <b><?= htmlspecialchars($guru_sel_nama); ?></b> mengajar tiap mapel. Bila kelas sudah diampu guru lain, penugasan akan <b>digantikan</b>.
                                    </div>
                                    <div class="text-nowrap">
                                        <span class="badge bg-info text-dark me-2">Kelas dipilih: <span id="jmlKelas">0</span></span>
                                        <button type="button" id="btnPilihSemua" class="btn btn-outline-secondary btn-sm"><i class="bi bi-check2-all"></i> Pilih semua</button>
                                        <button type="button" id="btnBersihkan" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i> Bersihkan</button>
                                        <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-save"></i> Simpan</button>
                                    </div>
                                </div>

                                <?php foreach ($mapel_guru as $m): $idm = (int)$m['id_mapel']; ?>
                                    <div class="card border mb-2">
                                        <div class="card-header py-1 d-flex justify-content-between align-items-center">
                                            <span>
                                                <span class="badge <?= $badge_kelompok($m['kelompok_mapel']); ?>"><?= htmlspecialchars($m['kelompok_mapel']); ?></span>
                                                <code><?= htmlspecialchars($m['kode_mapel']); ?></code>
                                                <?= htmlspecialchars($m['nama_mapel']); ?>
                                            </span>
                                            <span class="small text-muted">Terisi: <b><?= isset($pengampu_all[$idm]) ? count($pengampu_all[$idm]) : 0; ?></b> kelas</span>
                                        </div>
                                        <div class="card-body py-2">
                                            <div class="row g-2">
                                                <?php foreach ($kelas_by_tingkat as $tingkat => $kelas_grp): ?>
                                                    <div class="col-md-6">
                                                        <div class="small fw-bold text-muted mb-1"><?= htmlspecialchars($tingkat); ?></div>
                                                        <div class="row g-1">
                                                            <?php foreach ($kelas_grp as $k):
                                                                $idk = (int)$k['id_kelas'];
                                                                $cur = $pengampu_all[$idm][$idk] ?? 0;
                                                            ?>
                                                                <div class="col-6">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input chk-kelas" type="checkbox"
                                                                            name="pengampu[<?= $idm; ?>][]" value="<?= $idk; ?>"
                                                                            id="p<?= $idm; ?>_<?= $idk; ?>" <?= ($cur === $id_guru_sel) ? 'checked' : ''; ?>>
                                                                        <label class="form-check-label small" for="p<?= $idm; ?>_<?= $idk; ?>">
                                                                            <?= htmlspecialchars($k['nama_kelas']); ?><?= !empty($k['nama_jurusan']) ? ' <span class="text-muted">(' . htmlspecialchars($k['nama_jurusan']) . ')</span>' : ''; ?>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
            <div class="card card-outline card-primary mb-3">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end">
                        <input type="hidden" name="menu" value="pengampu_mapel">
                        <div class="col-md-6">
                            <label class="form-label fw-bold mb-1">Pilih Kelas</label>
                            <select name="kelas" class="form-select" onchange="this.form.submit()">
                                <option value="">— Pilih kelas —</option>
                                <?php foreach ($kelas_list as $k): ?>
                                    <option value="<?= (int)$k['id_kelas']; ?>" <?= ($id_kelas === (int)$k['id_kelas']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($k['tingkat'] . ' - ' . $k['nama_kelas']); ?><?= !empty($k['nama_jurusan']) ? ' (' . htmlspecialchars($k['nama_jurusan']) . ')' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Tampilkan</button>
                            <a href="aksi/export_pengampu.php" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Download Template</a>
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalImportPengampu"><i class="bi bi-upload"></i> Import Excel</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!$kelas_terpilih): ?>
                <div class="alert alert-secondary"><i class="bi bi-info-circle"></i> Silakan pilih kelas untuk mengatur pengampu mapel.</div>
            <?php else: ?>
                <form action="aksi/aksi_pengampu_mapel.php" method="post" id="formPengampu">
                    <input type="hidden" name="aksi" value="simpan">
                    <input type="hidden" name="id_kelas" value="<?= (int)$kelas_terpilih['id_kelas']; ?>">
                    <input type="hidden" name="id_tahun_ajaran" value="<?= $id_ta_aktif; ?>">

                    <div class="card card-outline card-success">
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-journal-check me-1"></i>
                                Matriks Pengampu — <?= htmlspecialchars($kelas_terpilih['tingkat'] . ' - ' . $kelas_terpilih['nama_kelas']); ?>
                                <?= !empty($kelas_terpilih['nama_jurusan']) ? '<span class="badge bg-secondary ms-1">' . htmlspecialchars($kelas_terpilih['nama_jurusan']) . '</span>' : ''; ?>
                            </h5>
                            <div class="d-flex gap-2">
                                <span class="badge bg-info text-dark align-self-center">Terisi: <span id="jmlTerisi"><?= $jml_terisi; ?></span> / <?= count($mapel_list); ?></span>
                                <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-save me-1"></i> Simpan Pengampu</button>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="btnKosongkan"><i class="bi bi-eraser me-1"></i> Kosongkan</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($mapel_list)): ?>
                                <div class="alert alert-warning mb-0">Belum ada data mata pelajaran. Tambahkan di menu <a href="index.php?menu=mapel">Mata Pelajaran</a>.</div>
                            <?php else: ?>
                                <div class="table-responsive" style="max-height: 65vh; overflow-y:auto;">
                                    <table class="table table-hover table-sm table-bordered align-middle mb-0">
                                        <thead class="table-primary position-sticky top-0">
                                            <tr>
                                                <th style="width:5%;">#</th>
                                                <th style="width:13%;">Kelompok</th>
                                                <th style="width:12%;">Kode</th>
                                                <th style="width:40%;">Mata Pelajaran</th>
                                                <th style="width:30%;">Guru Pengampu</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $no = 1; foreach ($mapel_list as $m):
                                                $idm = (int)$m['id_mapel'];
                                                $guru_terpilih = $pengampu_map[$idm] ?? 0;
                                            ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++; ?></td>
                                                    <td><span class="badge <?= $badge_kelompok($m['kelompok_mapel']); ?>"><?= htmlspecialchars($m['kelompok_mapel']); ?></span></td>
                                                    <td><code><?= htmlspecialchars($m['kode_mapel']); ?></code></td>
                                                    <td><?= htmlspecialchars($m['nama_mapel']); ?></td>
                                                    <td>
                                                        <select name="guru[<?= $idm; ?>]" class="form-select form-select-sm select-pengampu">
                                                            <option value="0">— Belum ditugaskan —</option>
                                                            <?php foreach ($semua_guru as $idg => $nama_g): ?>
                                                                <option value="<?= $idg; ?>" <?= ($guru_terpilih === $idg) ? 'selected' : ''; ?>><?= htmlspecialchars($nama_g); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <form action="aksi/aksi_pengampu_mapel.php" method="post" id="formKosongkan" class="d-none">
                    <input type="hidden" name="aksi" value="hapus_kelas">
                    <input type="hidden" name="id_kelas" value="<?= (int)$kelas_terpilih['id_kelas']; ?>">
                    <input type="hidden" name="id_tahun_ajaran" value="<?= $id_ta_aktif; ?>">
                </form>
            <?php endif; ?>
            <?php endif; /* mode */ ?>
        </div>
    </div>
</main>

<!-- Modal Import Pengampu -->
<div class="modal fade" id="modalImportPengampu" tabindex="-1" aria-labelledby="modalImportPengampuLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="aksi/import_pengampu.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="import" value="1">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalImportPengampuLabel"><i class="bi bi-upload me-1"></i> Import Pengampu Mapel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Import akan <b>mengganti</b> pengampu untuk kelas-kelas yang ada di file (TA aktif: <b><?= $nama_ta_aktif !== '' ? $nama_ta_aktif : '-'; ?></b>). Kelas lain tidak diubah.
                    </div>
                    <ol class="small text-muted ps-3 mb-3">
                        <li>Klik <b>Download Template</b> terlebih dahulu.</li>
                        <li>Isi <b>nama guru</b> pada sel pertemuan mapel &amp; kelas. Kolom A–C jangan diubah.</li>
                        <li>Upload kembali file yang sudah diisi.</li>
                    </ol>
                    <div class="mb-2">
                        <label class="form-label fw-bold">File Excel (.xlsx / .xls)</label>
                        <input type="file" name="fileExcel" class="form-control" accept=".xlsx,.xls" required>
                    </div>
                    <a href="aksi/export_pengampu.php" class="small"><i class="bi bi-download"></i> Download Template Excel</a>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i> Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var selects = document.querySelectorAll('.select-pengampu');
    var counter = document.getElementById('jmlTerisi');
    function refresh() {
        if (!counter) return;
        var n = 0;
        selects.forEach(function (s) { if (s.value && s.value !== '0') n++; });
        counter.textContent = n;
    }
    selects.forEach(function (s) { s.addEventListener('change', refresh); });
    refresh();

    var btnKosong = document.getElementById('btnKosongkan');
    if (btnKosong) {
        btnKosong.addEventListener('click', function () {
            var formKosong = document.getElementById('formKosongkan');
            var submit = function () { if (formKosong) formKosong.submit(); };
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Kosongkan pengampu?',
                    text: 'Seluruh penugasan pengampu pada kelas ini akan dihapus.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, kosongkan',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d'
                }).then(function (r) { if (r.isConfirmed) submit(); });
            } else if (confirm('Kosongkan seluruh pengampu kelas ini?')) {
                submit();
            }
        });
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var boxes = document.querySelectorAll('#formPengampuGuru .chk-kelas');
    var counter = document.getElementById('jmlKelas');
    function count() {
        if (!counter) return;
        var n = 0;
        boxes.forEach(function (c) { if (c.checked) n++; });
        counter.textContent = n;
    }
    boxes.forEach(function (c) { c.addEventListener('change', count); });
    count();

    var btnAll = document.getElementById('btnPilihSemua');
    if (btnAll) btnAll.addEventListener('click', function () {
        boxes.forEach(function (c) { c.checked = true; });
        count();
    });
    var btnClear = document.getElementById('btnBersihkan');
    if (btnClear) btnClear.addEventListener('click', function () {
        boxes.forEach(function (c) { c.checked = false; });
        count();
    });
});
</script>
