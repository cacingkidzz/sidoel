<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('koneksi.php');

// ==========================================================
// 1. DAFTAR GURU
// ==========================================================
$semua_guru = [];
$q_g = $koneksi->query("SELECT id_guru, nama_guru FROM dt_guru ORDER BY nama_guru ASC");
if ($q_g) {
    while ($g = $q_g->fetch_assoc()) {
        $semua_guru[(int)$g['id_guru']] = $g['nama_guru'];
    }
}

// ==========================================================
// 2. DAFTAR MAPEL (dikelompokkan)
// ==========================================================
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

$mapel_by_kelompok = [];
foreach ($mapel_list as $m) {
    $mapel_by_kelompok[$m['kelompok_mapel']][] = $m;
}

// ==========================================================
// 3. PETA GURU -> MAPEL (referensi global)
// ==========================================================
$guru_mapel_map = []; // id_guru => [id_mapel => true]
$q_gm = $koneksi->query("SELECT id_guru, id_mapel FROM dt_guru_mapel");
if ($q_gm) {
    while ($r = $q_gm->fetch_assoc()) {
        $guru_mapel_map[(int)$r['id_guru']][(int)$r['id_mapel']] = true;
    }
}

// ==========================================================
// 4. GURU TERPILIH
// ==========================================================
$id_guru = isset($_GET['guru']) ? (int)$_GET['guru'] : 0;
$guru_terpilih = isset($semua_guru[$id_guru]) ? $semua_guru[$id_guru] : null;
$mapel_terpilih = $guru_terpilih ? ($guru_mapel_map[$id_guru] ?? []) : [];

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

$nama_mapel_by_id = [];
foreach ($mapel_list as $m) {
    $nama_mapel_by_id[(int)$m['id_mapel']] = $m['kode_mapel'] . ' — ' . $m['nama_mapel'];
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Guru Mapel</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item">Pendidikan &amp; KBM</li>
                        <li class="breadcrumb-item active" aria-current="page">Guru Mapel</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="alert alert-info py-2 mb-3 shadow-sm">
                <i class="bi bi-info-circle-fill me-1"></i>
                Referensi <b>mapel yang diampu tiap guru</b> (berlaku untuk semua tahun ajaran). Setelah diatur di sini,
                penugasan kelas cukup dilakukan di menu <a href="index.php?menu=pengampu_mapel&amp;mode=guru" class="alert-link">Pengampu Mapel → Per Guru</a>.
            </div>

            <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['alert']['type']); ?> alert-dismissible fade show" role="alert">
                    <?= $_SESSION['alert']['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>

            <div class="card card-outline card-primary mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-person-badge me-1"></i> Atur Mapel yang Diampu</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($semua_guru)): ?>
                        <div class="alert alert-warning mb-0">Belum ada data guru. Tambahkan di menu <a href="index.php?menu=guru">Data Guru &amp; Staf</a>.</div>
                    <?php elseif (empty($mapel_list)): ?>
                        <div class="alert alert-warning mb-0">Belum ada data mata pelajaran. Tambahkan di menu <a href="index.php?menu=mapel">Mata Pelajaran</a>.</div>
                    <?php else: ?>
                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold mb-1">Pilih Guru</label>
                                <select class="form-select" onchange="if(this.value){window.location='index.php?menu=guru_mapel&guru='+this.value}else{window.location='index.php?menu=guru_mapel'}">
                                    <option value="">— Pilih guru —</option>
                                    <?php foreach ($semua_guru as $idg => $nama_g): ?>
                                        <option value="<?= $idg; ?>" <?= ($id_guru === $idg) ? 'selected' : ''; ?>><?= htmlspecialchars($nama_g); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <?php if (!$guru_terpilih): ?>
                            <div class="alert alert-secondary mb-0"><i class="bi bi-info-circle"></i> Silakan pilih guru untuk mengatur mapel yang diampu.</div>
                        <?php else: ?>
                            <form action="aksi/aksi_guru_mapel.php" method="post" id="formGuruMapel">
                                <input type="hidden" name="aksi" value="simpan">
                                <input type="hidden" name="id_guru" value="<?= $id_guru; ?>">

                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                    <div class="fw-bold">
                                        <?= htmlspecialchars($guru_terpilih); ?>
                                        <span class="badge bg-info text-dark ms-1">Terpilih: <span id="jmlMapel"><?= count($mapel_terpilih); ?></span> / <?= count($mapel_list); ?></span>
                                    </div>
                                    <div>
                                        <button type="button" id="btnMapelSemua" class="btn btn-outline-secondary btn-sm"><i class="bi bi-check2-all"></i> Pilih semua</button>
                                        <button type="button" id="btnMapelBersih" class="btn btn-outline-secondary btn-sm"><i class="bi bi-eraser"></i> Bersihkan</button>
                                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i> Simpan</button>
                                    </div>
                                </div>

                                <?php foreach ($mapel_by_kelompok as $kel => $items): ?>
                                    <div class="mb-1 mt-2">
                                        <span class="badge <?= $badge_kelompok($kel); ?>"><?= htmlspecialchars($kel); ?></span>
                                    </div>
                                    <div class="row g-1">
                                        <?php foreach ($items as $m):
                                            $idm = (int)$m['id_mapel'];
                                            $cek = isset($mapel_terpilih[$idm]);
                                        ?>
                                            <div class="col-md-4 col-lg-3">
                                                <div class="form-check">
                                                    <input class="form-check-input chk-mapel" type="checkbox" name="mapel[]" value="<?= $idm; ?>" id="mp<?= $idm; ?>" <?= isset($mapel_terpilih[$idm]) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label small" for="mp<?= $idm; ?>"><code><?= htmlspecialchars($m['kode_mapel']); ?></code> <?= htmlspecialchars($m['nama_mapel']); ?></label>
                                        </div>
                                    </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-table me-1"></i> Ringkasan Guru &amp; Mapel</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="myTable" class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width:5%;">#</th>
                                    <th style="width:25%;">Nama Guru</th>
                                    <th style="width:7%;" class="text-center">Jumlah</th>
                                    <th>Mapel yang Diampu</th>
                                    <th style="width:9%;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($semua_guru as $idg => $nama_g):
                                    $mp = $guru_mapel_map[$idg] ?? [];
                                ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($nama_g); ?></td>
                                        <td class="text-center"><span class="badge <?= count($mp) ? 'bg-success' : 'bg-secondary'; ?>"><?= count($mp); ?></span></td>
                                        <td>
                                            <?php if (empty($mp)): ?>
                                                <span class="text-muted small">— belum diatur —</span>
                                            <?php else: ?>
                                                <?php foreach ($mapel_list as $m): if (!isset($mp[(int)$m['id_mapel']])) continue; ?>
                                                    <span class="badge <?= $badge_kelompok($m['kelompok_mapel']); ?> mb-1" title="<?= htmlspecialchars($m['nama_mapel']); ?>"><?= htmlspecialchars($m['kode_mapel']); ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="index.php?menu=guru_mapel&guru=<?= $idg; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i> Atur</a>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    var boxes = document.querySelectorAll('#formGuruMapel input[name="mapel[]"]');
    var counter = document.getElementById('jmlMapel');
    function refresh() {
        if (!counter) return;
        var n = 0;
        boxes.forEach(function (b) { if (b.checked) n++; });
        counter.textContent = n;
    }
    boxes.forEach(function (b) { b.addEventListener('change', refresh); });
    refresh();

    var all = document.getElementById('btnMapelSemua');
    if (all) all.addEventListener('click', function () { boxes.forEach(function (b) { b.checked = true; }); refresh(); });
    var clear = document.getElementById('btnMapelBersih');
    if (clear) clear.addEventListener('click', function () { boxes.forEach(function (b) { b.checked = false; }); refresh(); });
});
</script>
