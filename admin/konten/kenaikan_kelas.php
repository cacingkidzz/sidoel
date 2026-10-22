<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
</div>
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("koneksi.php");

// ==========================================================
// 1. TA TARGET (sesi) & TA SUMBER (TA sebelumnya)
// ==========================================================
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
$id_ta_target = 0;
$nama_ta_target = '';

if (!$koneksi->connect_error && $nama_ta_sesi !== '') {
    $q = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    $q->bind_param("s", $nama_ta_sesi);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    if ($r) {
        $id_ta_target = (int)$r['id_tahun_ajaran'];
        $nama_ta_target = htmlspecialchars($r['tahun_ajaran']);
    }
    $q->close();
}

// TA sumber = tahun ajaran aktif sebelum TA target (id terbesar yang lebih kecil)
$id_ta_sumber = 0;
$nama_ta_sumber = '';
if ($id_ta_target > 0) {
    $q2 = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE id_tahun_ajaran < ? ORDER BY id_tahun_ajaran DESC LIMIT 1");
    $q2->bind_param("i", $id_ta_target);
    $q2->execute();
    $r2 = $q2->get_result()->fetch_assoc();
    if ($r2) {
        $id_ta_sumber = (int)$r2['id_tahun_ajaran'];
        $nama_ta_sumber = htmlspecialchars($r2['tahun_ajaran']);
    }
    $q2->close();
}
$ada_ta_sumber = ($id_ta_sumber > 0);

// ==========================================================
// 2. STATUS PERSIAPAN KELAS (Langkah 1)
// ==========================================================
$status_persiapan = null; // null = belum bisa, true = selesai, false = belum
$kurang_xi = [];
$kurang_xii = [];

if ($ada_ta_sumber) {
    // Kelas X & XI di TA sumber (sumber clone)
    $q_sx = $koneksi->query("SELECT nama_kelas, id_jurusan FROM dt_kelas WHERE id_tahun_ajaran = '$id_ta_sumber' AND tingkat = 'X' ORDER BY nama_kelas");
    $sumber_x = [];
    while ($r = $q_sx->fetch_assoc()) $sumber_x[] = $r;

    $q_sxi = $koneksi->query("SELECT nama_kelas, id_jurusan FROM dt_kelas WHERE id_tahun_ajaran = '$id_ta_sumber' AND tingkat = 'XI' ORDER BY nama_kelas");
    $sumber_xi = [];
    while ($r = $q_sxi->fetch_assoc()) $sumber_xi[] = $r;

    // Kelas XI & XII di TA target (hasil clone)
    $q_txi = $koneksi->query("SELECT nama_kelas, id_jurusan FROM dt_kelas WHERE id_tahun_ajaran = '$id_ta_target' AND tingkat = 'XI'");
    $target_xi = [];
    while ($r = $q_txi->fetch_assoc()) $target_xi[$r['nama_kelas'] . '|' . $r['id_jurusan']] = true;

    $q_txii = $koneksi->query("SELECT nama_kelas, id_jurusan FROM dt_kelas WHERE id_tahun_ajaran = '$id_ta_target' AND tingkat = 'XII'");
    $target_xii = [];
    while ($r = $q_txii->fetch_assoc()) $target_xii[$r['nama_kelas'] . '|' . $r['id_jurusan']] = true;

    foreach ($sumber_x as $k) {
        if (!isset($target_xi[$k['nama_kelas'] . '|' . $k['id_jurusan']])) {
            $kurang_xi[] = $k['nama_kelas'];
        }
    }
    foreach ($sumber_xi as $k) {
        if (!isset($target_xii[$k['nama_kelas'] . '|' . $k['id_jurusan']])) {
            $kurang_xii[] = $k['nama_kelas'];
        }
    }

    $status_persiapan = (empty($kurang_xi) && empty($kurang_xii));
    $jumlah_kelas_perlu_clone = count($kurang_xi) + count($kurang_xii);
}

// ==========================================================
// 3. PREVIEW KENAIKAN (Langkah 2) — per jurusan
// ==========================================================
$preview_xi = [];    // X -> XI per jurusan
$preview_xii = [];   // XI -> XII per jurusan
$terlewat_tanpa_kelas = 0;   // siswa sumber tanpa kelas sama sekali
$terlewat_anomali = 0;       // jurusan tidak dikenal
$terlewat_xi = [];   // daftar nama terlewat X (target XI tidak ada)
$terlewat_xii = [];  // daftar nama terlewat XI (target XII tidak ada)
$total_lulus_xii = 0;
$sudah_diproses_xi = 0;  // siswa X sumber yang sudah punya baris TA target (jalan setengah)
$sudah_diproses_xii = 0;

if ($ada_ta_sumber) {
    // 3a. Siswa TANPA kelas di TA sumber
    $q_tk = $koneksi->query("
        SELECT COUNT(*) AS jml FROM dt_siswa s
        WHERE s.id_tahun_ajaran = '$id_ta_sumber'
          AND NOT EXISTS (SELECT 1 FROM dt_kelas_siswa ks WHERE ks.id_siswa = s.id_siswa AND ks.id_tahun_ajaran = '$id_ta_sumber')
    ");
    $terlewat_tanpa_kelas = (int)$q_tk->fetch_assoc()['jml'];

    // 3b. Kelas target map: tingkat -> "nama|jurusan" => id_kelas
    $map_target = ['XI' => [], 'XII' => []];
    foreach (['XI', 'XII'] as $tj) {
        $q_m = $koneksi->query("SELECT id_kelas, nama_kelas, id_jurusan FROM dt_kelas WHERE id_tahun_ajaran = '$id_ta_target' AND tingkat = '$tj'");
        while ($r = $q_m->fetch_assoc()) {
            $map_target[$tj][$r['nama_kelas'] . '|' . $r['id_jurusan']] = $r['id_kelas'];
        }
    }

    // 3c. Fungsi preview per pasangan tingkat
    $fungsi_preview = function ($tingkat_sumber, $tingkat_tujuan, $koneksi, $id_ta_sumber, $id_ta_target, $map_target) use (&$preview_xi, &$preview_xii, &$terlewat_anomali, &$terlewat_xi, &$terlewat_xii, &$sudah_diproses_xi, &$sudah_diproses_xii) {
        // Kelompokkan siswa per jurusan berdasarkan kelasnya di TA sumber.
        // Filter id_tahun_ajaran TIDAK dipakai di sini: siswa yang sudah naik
        // (id_tahun_ajaran sudah pindah ke target) tetap terbaca agar bisa
        // ditandai "sudah diproses" (idempoten / anti double-run).
        $q = $koneksi->query("
            SELECT j.id_jurusan, j.nama_jurusan, s.id_siswa, s.nama, k.nama_kelas AS nama_kelas_sumber,
                   EXISTS (SELECT 1 FROM dt_kelas_siswa ks2 WHERE ks2.id_siswa = s.id_siswa AND ks2.id_tahun_ajaran = '$id_ta_target') AS sudah_pindah
            FROM dt_kelas k
            INNER JOIN dt_kelas_siswa ks ON ks.id_kelas = k.id_kelas AND ks.id_tahun_ajaran = '$id_ta_sumber'
            INNER JOIN dt_siswa s ON s.id_siswa = ks.id_siswa
            LEFT JOIN dt_jurusan j ON s.jurusan = j.nama_jurusan
            WHERE k.id_tahun_ajaran = '$id_ta_sumber' AND k.tingkat = '$tingkat_sumber'
            ORDER BY j.nama_jurusan, k.nama_kelas, s.nama
        ");

        $kelompok = [];   // id_jurusan => ['nama' =>, 'siap' => [], 'terlewat' => [], 'sudah' => n]
        $tanpa_jurusan = 0;

        while ($r = $q->fetch_assoc()) {
            if ($r['id_jurusan'] === null) {
                $tanpa_jurusan++;
                continue;
            }
            $id_j = (int)$r['id_jurusan'];
            if (!isset($kelompok[$id_j])) {
                $kelompok[$id_j] = ['nama' => $r['nama_jurusan'], 'siap' => [], 'terlewat' => [], 'sudah' => 0];
            }
            if ($r['sudah_pindah']) {
                $kelompok[$id_j]['sudah']++;
                continue;
            }
            $kunci = $r['nama_kelas_sumber'] . '|' . $r['id_jurusan'];
            if (isset($map_target[$tingkat_tujuan][$kunci])) {
                $kelompok[$id_j]['siap'][] = $r;
            } else {
                $kelompok[$id_j]['terlewat'][] = $r['nama'];
            }
        }

        // Tempelkan ke preview & daftar terlewat sesuai tujuan
        if ($tingkat_tujuan === 'XI') {
            foreach ($kelompok as $id_j => $d) {
                $preview_xi[$id_j] = $d;
                foreach ($d['terlewat'] as $nm) $terlewat_xi[] = $nm;
            }
            $terlewat_anomali += $tanpa_jurusan;
            foreach ($kelompok as $d) $sudah_diproses_xi += $d['sudah'];
        } else {
            foreach ($kelompok as $id_j => $d) {
                $preview_xii[$id_j] = $d;
                foreach ($d['terlewat'] as $nm) $terlewat_xii[] = $nm;
            }
            $terlewat_anomali += $tanpa_jurusan;
            foreach ($kelompok as $d) $sudah_diproses_xii += $d['sudah'];
        }
    };

    $fungsi_preview('X', 'XI', $koneksi, $id_ta_sumber, $id_ta_target, $map_target);
    $fungsi_preview('XI', 'XII', $koneksi, $id_ta_sumber, $id_ta_target, $map_target);

    // 3d. Jumlah siswa XII sumber (calon lulus)
    $q_lulus = $koneksi->query("
        SELECT COUNT(DISTINCT s.id_siswa) AS jml
        FROM dt_siswa s
        INNER JOIN dt_kelas_siswa ks ON ks.id_siswa = s.id_siswa AND ks.id_tahun_ajaran = '$id_ta_sumber'
        INNER JOIN dt_kelas k ON k.id_kelas = ks.id_kelas AND k.tingkat = 'XII'
        WHERE s.id_tahun_ajaran = '$id_ta_sumber'
    ");
    $total_lulus_xii = (int)$q_lulus->fetch_assoc()['jml'];
}

$persiapan_selesai = ($status_persiapan === true);
$jml_siap_xi = 0;
foreach ($preview_xi as $d) $jml_siap_xi += count($d['siap']);
$jml_siap_xii = 0;
foreach ($preview_xii as $d) $jml_siap_xii += count($d['siap']);
$ada_yang_naik = ($jml_siap_xi > 0 || $jml_siap_xii > 0);
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Kenaikan Kelas</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Kenaikan Kelas</li>
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
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>

            <div class="alert alert-info py-2 mb-3 shadow-sm">
                <i class="bi bi-info-circle-fill me-1"></i>
                Alur kenaikan kelas: <b>Tahun Ajaran <?= $ada_ta_sumber ? $nama_ta_sumber : '(sumber)'; ?></b> &rarr; <b>Tahun Ajaran <?= $nama_ta_target !== '' ? $nama_ta_target : '(aktif)'; ?></b>.
                Kelas XI &amp; XII tahun baru dibuat otomatis dengan menaikkan tingkat kelas lama; kelas X baru dibuat manual lewat menu <a href="?menu=kelas" class="fw-bold">Kelas</a>.
            </div>

            <?php if (!$ada_ta_sumber): ?>
                <!-- ===== BELUM ADA TA SUMBER ===== -->
                <div class="card card-outline card-warning">
                    <div class="card-header"><h5 class="card-title mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i> Belum Siap</h5></div>
                    <div class="card-body">
                        <div class="alert alert-warning mb-3">
                            Tidak ditemukan tahun ajaran sebelum tahun ajaran aktif saat ini.
                            Proses kenaikan membutuhkan <b>dua tahun ajaran</b>: tahun lama (sumber) dan tahun baru (aktif).
                        </div>
                        <ol class="small text-muted mb-0">
                            <li>Buat tahun ajaran baru di menu <a href="?menu=tahun_ajar">Tahun Ajaran</a> dan set status <b>Aktif</b>.</li>
                            <li>Buat <b>kelas X baru</b> untuk tahun ajaran baru di menu <a href="?menu=kelas">Kelas</a> (menyusul, bisa setelah kenaikan).</li>
                            <li>Kembali ke halaman ini untuk menjalankan 3 langkah kenaikan.</li>
                        </ol>
                    </div>
                </div>

            <?php else: ?>
                <div class="alert alert-light border py-2 mb-3 d-flex flex-wrap justify-content-between align-items-center">
                    <span class="small"><b>Sumber:</b> TA <?= $nama_ta_sumber; ?> (ID <?= $id_ta_sumber; ?>) &nbsp;|&nbsp; <b>Target:</b> TA <?= $nama_ta_target; ?> (ID <?= $id_ta_target; ?>)</span>
                </div>

                <!-- ================= LANGKAH 1: PERSIAPAN KELAS ================= -->
                <div class="card card-outline <?= $persiapan_selesai ? 'card-success' : 'card-warning'; ?> mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <span class="badge bg-primary me-2">1</span>
                            <i class="bi bi-door-open me-1"></i> Persiapan Kelas Tahun Baru
                            <?php if ($persiapan_selesai): ?><span class="badge bg-success ms-2">Selesai</span>
                            <?php else: ?><span class="badge bg-warning text-dark ms-2">Belum</span><?php endif; ?>
                        </h5>
                        <form method="POST" action="aksi/naik_kelas.php" onsubmit="return confirm('Clone kelas XI & XII tahun baru dari kelas lama? Kelas yang sudah ada tidak akan diduplikasi.');">
                            <input type="hidden" name="aksi" value="persiapan_kelas">
                            <input type="hidden" name="id_ta_sumber" value="<?= $id_ta_sumber; ?>">
                            <input type="hidden" name="id_ta_target" value="<?= $id_ta_target; ?>">
                            <button type="submit" name="persiapan_kelas" class="btn btn-warning text-dark btn-sm" <?= ($persiapan_selesai) ? 'disabled' : ''; ?>>
                                <i class="bi bi-copy me-1"></i> Buat Kelas XI &amp; XII Otomatis
                            </button>
                        </form>
                    </div>
                    <div class="card-body small">
                        <?php if ($persiapan_selesai): ?>
                            <div class="text-success mb-0"><i class="bi bi-check-circle-fill me-1"></i> Kelas XI &amp; XII tahun baru sudah tersedia (di-clone dari kelas X &amp; XI tahun lama). Kelas XII lama tidak diclone karena siswanya lulus.</div>
                        <?php else: ?>
                            <p class="mb-2">Sistem akan menyalin kelas lama ke tahun baru dengan tingkat naik satu:</p>
                            <ul class="mb-2">
                                <li>Kelas <b>X</b> tahun <?= $nama_ta_sumber; ?> &rarr; jadi kelas <b>XI</b> tahun <?= $nama_ta_target; ?> (<?= count($kurang_xi); ?> kelas belum ada)</li>
                                <li>Kelas <b>XI</b> tahun <?= $nama_ta_sumber; ?> &rarr; jadi kelas <b>XII</b> tahun <?= $nama_ta_target; ?> (<?= count($kurang_xii); ?> kelas belum ada)</li>
                                <li>Kelas <b>XII</b> tahun lama <b>tidak diclone</b> (siswanya lulus)</li>
                                <li>Kelas <b>X</b> tahun baru dibuat manual di menu <a href="?menu=kelas">Kelas</a></li>
                            </ul>
                            <?php if (!empty($kurang_xi)): ?>
                                <div class="alert alert-warning py-1 small mb-1"><b>Belum ada kelas XI (dari X):</b> <?= htmlspecialchars(implode(', ', $kurang_xi)); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($kurang_xii)): ?>
                                <div class="alert alert-warning py-1 small mb-0"><b>Belum ada kelas XII (dari XI):</b> <?= htmlspecialchars(implode(', ', $kurang_xii)); ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ================= LANGKAH 2: KENAIKAN ================= -->
                <div class="card card-outline card-primary mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <span class="badge bg-primary me-2">2</span>
                            <i class="bi bi-arrow-up-circle me-1"></i> Kenaikan Siswa X &rarr; XI dan XI &rarr; XII
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$persiapan_selesai): ?>
                            <div class="alert alert-danger small">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Jalankan <b>Langkah 1 (Persiapan Kelas)</b> terlebih dahulu — kelas tujuan XI/XII tahun baru belum lengkap.
                            </div>
                        <?php endif; ?>

                        <?php if ($terlewat_tanpa_kelas > 0): ?>
                            <div class="alert alert-warning small py-2">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                <?= $terlewat_tanpa_kelas; ?> siswa tahun lama <b>belum punya kelas</b> sehingga tidak bisa dinaikkan. Tempatkan kelasnya dulu lewat <a href="?menu=penempatan_kelas">Atur Penempatan Kelas</a>.
                            </div>
                        <?php endif; ?>
                        <?php if ($sudah_diproses_xi > 0 || $sudah_diproses_xii > 0): ?>
                            <div class="alert alert-info small py-2">
                                <i class="bi bi-info-circle-fill me-1"></i>
                                Sudah diproses sebelumnya: <?= $sudah_diproses_xi; ?> siswa (X&rarr;XI), <?= $sudah_diproses_xii; ?> siswa (XI&rarr;XII) — tidak akan diproses ulang.
                            </div>
                        <?php endif; ?>

                        <?php if (!$ada_yang_naik && !$persiapan_selesai): ?>
                            <div class="alert alert-secondary small mb-0">Belum ada siswa yang bisa dinaikkan (atau Langkah 1 belum dijalankan).</div>
                        <?php endif; ?>

                        <?php if ($ada_yang_naik || $persiapan_selesai): ?>
                        <form method="POST" action="aksi/naik_kelas.php" id="formKenaikan"
                              onsubmit="return confirm('Naikkan siswa terpilih ke kelas berikutnya? Proses ini tidak dapat dibatalkan.');">
                            <input type="hidden" name="aksi" value="kenaikan">
                            <input type="hidden" name="id_ta_sumber" value="<?= $id_ta_sumber; ?>">
                            <input type="hidden" name="id_ta_target" value="<?= $id_ta_target; ?>">

                            <!-- X -> XI -->
                            <h6 class="text-success mt-2"><i class="bi bi-arrow-up-right-square me-1"></i> Kelas X &rarr; XI <span class="badge bg-success"><?= $jml_siap_xi; ?> siswa siap</span></h6>
                            <?php if (empty($preview_xi)): ?>
                                <div class="alert alert-secondary small py-2">Tidak ada siswa kelas X di tahun lama.</div>
                            <?php else: ?>
                                <div class="table-responsive mb-3">
                                    <table class="table table-bordered table-sm small align-middle">
                                        <thead>
                                            <tr class="table-light">
                                                <th style="width: 40px;"><input type="checkbox" class="check-all-xi" title="Pilih semua"></th>
                                                <th>Jurusan</th>
                                                <th style="width: 90px;" class="text-center">Siap Naik</th>
                                                <th style="width: 90px;" class="text-center">Terlewat</th>
                                                <th style="width: 110px;" class="text-center">Sudah Diproses</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($preview_xi as $id_j => $d): ?>
                                                <tr>
                                                    <td>
                                                        <?php if (count($d['siap']) > 0): ?>
                                                            <input type="checkbox" class="check-xi" name="naik_xi[]" value="<?= $id_j; ?>" checked>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><b><?= htmlspecialchars($d['nama']); ?></b>
                                                        <?php if (!empty($d['terlewat'])): ?>
                                                            <br><small class="text-danger"><i class="bi bi-exclamation-triangle"></i> <?= count($d['terlewat']); ?> terlewat: kelas XI tujuan tidak ada di tahun baru</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center"><span class="badge bg-success"><?= count($d['siap']); ?></span></td>
                                                    <td class="text-center"><span class="badge <?= count($d['terlewat']) > 0 ? 'bg-danger' : 'bg-light text-muted'; ?>"><?= count($d['terlewat']); ?></span></td>
                                                    <td class="text-center"><span class="badge bg-info"><?= $d['sudah']; ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <!-- XI -> XII -->
                            <h6 class="text-info mt-3"><i class="bi bi-arrow-up-right-square me-1"></i> Kelas XI &rarr; XII <span class="badge bg-info"><?= $jml_siap_xii; ?> siswa siap</span></h6>
                            <?php if (empty($preview_xii)): ?>
                                <div class="alert alert-secondary small py-2">Tidak ada siswa kelas XI di tahun lama.</div>
                            <?php else: ?>
                                <div class="table-responsive mb-3">
                                    <table class="table table-bordered table-sm small align-middle">
                                        <thead>
                                            <tr class="table-light">
                                                <th style="width: 40px;"><input type="checkbox" class="check-all-xii" title="Pilih semua"></th>
                                                <th>Jurusan</th>
                                                <th style="width: 90px;" class="text-center">Siap Naik</th>
                                                <th style="width: 90px;" class="text-center">Terlewat</th>
                                                <th style="width: 110px;" class="text-center">Sudah Diproses</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($preview_xii as $id_j => $d): ?>
                                                <tr>
                                                    <td>
                                                        <?php if (count($d['siap']) > 0): ?>
                                                            <input type="checkbox" class="check-xii" name="naik_xii[]" value="<?= $id_j; ?>" checked>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><b><?= htmlspecialchars($d['nama']); ?></b>
                                                        <?php if (!empty($d['terlewat'])): ?>
                                                            <br><small class="text-danger"><i class="bi bi-exclamation-triangle"></i> <?= count($d['terlewat']); ?> terlewat: kelas XII tujuan tidak ada di tahun baru</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center"><span class="badge bg-success"><?= count($d['siap']); ?></span></td>
                                                    <td class="text-center"><span class="badge <?= count($d['terlewat']) > 0 ? 'bg-danger' : 'bg-light text-muted'; ?>"><?= count($d['terlewat']); ?></span></td>
                                                    <td class="text-center"><span class="badge bg-info"><?= $d['sudah']; ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <?php if ($terlewat_anomali > 0): ?>
                                <div class="alert alert-warning small py-2">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= $terlewat_anomali; ?> siswa berstatus <b>anomali/jurusan tidak dikenal</b> — tidak ikut kenaikan, perbaiki datanya dulu.
                                </div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-end border-top pt-3">
                                <button type="submit" name="proses_kenaikan" class="btn btn-primary" <?= (!$persiapan_selesai || (!$jml_siap_xi && !$jml_siap_xii)) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-lightning-charge-fill me-1"></i> Naikkan Siswa Terpilih
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ================= LANGKAH 3: KELULUSAN XII ================= -->
                <div class="card card-outline card-danger mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <span class="badge bg-danger me-2">3</span>
                            <i class="bi bi-mortarboard me-1"></i> Kelulusan Siswa Kelas XII
                            <span class="badge bg-secondary ms-2"><?= $total_lulus_xii; ?> siswa</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($total_lulus_xii == 0): ?>
                            <div class="alert alert-secondary small mb-0">Tidak ada siswa kelas XII di tahun lama (semua sudah diluluskan atau tidak ditemukan).</div>
                        <?php else: ?>
                            <div class="alert alert-warning small py-2">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                Siswa kelas XII akan <b>dipindahkan ke data Alumni</b> (NIS/NISN tetap) lalu <b>dihapus dari data siswa aktif</b>. Pastikan Langkah 2 (kenaikan X&rarr;XI &amp; XI&rarr;XII) sudah selesai sebelum meluluskan siswa XII.
                            </div>
                            <form method="POST" action="aksi/naik_kelas.php"
                                  onsubmit="return confirm('Luluskan <?= $total_lulus_xii; ?> siswa kelas XII ke data Alumni? Siswa akan dihapus dari data aktif. Tindakan ini tidak dapat dibatalkan!');">
                                <input type="hidden" name="aksi" value="luluskan">
                                <input type="hidden" name="id_ta_sumber" value="<?= $id_ta_sumber; ?>">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Tahun Lulus</label>
                                        <input type="text" class="form-control" name="tahun_lulus" value="<?= $nama_ta_sumber; ?>" placeholder="Contoh: 2026/2027" required>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" name="proses_lulus" class="btn btn-danger">
                                            <i class="bi bi-mortarboard-fill me-1"></i> Luluskan ke Alumni
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const allXi = document.querySelector('.check-all-xi');
    if (allXi) {
        allXi.addEventListener('change', function() {
            document.querySelectorAll('.check-xi').forEach(cb => cb.checked = this.checked);
        });
    }
    const allXii = document.querySelector('.check-all-xii');
    if (allXii) {
        allXii.addEventListener('change', function() {
            document.querySelectorAll('.check-xii').forEach(cb => cb.checked = this.checked);
        });
    }
});
</script>
