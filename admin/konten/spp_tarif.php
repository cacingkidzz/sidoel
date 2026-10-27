<?php
/**
 * Tarif SPP (Keuangan) - matriks tarif per TINGKAT x JURUSAN.
 *
 * Setiap jurusan dapat memiliki nominal berbeda. Kolom "Semua jurusan"
 * (id_jurusan = 0) dipakai sebagai tarif cadangan bila kombinasi
 * tingkat+jurusan belum diatur.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('koneksi.php');
require_once(__DIR__ . '/../helper_spp.php');

// ==========================================================
// TAHUN AJARAN
// ==========================================================
$ta_list = [];
$q = $koneksi->query("SELECT id_tahun_ajaran, tahun_ajaran, status FROM dt_tahun_ajar ORDER BY tahun_ajaran DESC");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $ta_list[] = $r;
    }
}

$id_ta = (int)($_GET['ta'] ?? 0);
if ($id_ta <= 0) {
    $nama_sesi = (string)($_SESSION['tahun_ajaran_aktif'] ?? '');
    foreach ($ta_list as $t) {
        if ($t['tahun_ajaran'] === $nama_sesi) {
            $id_ta = (int)$t['id_tahun_ajaran'];
            break;
        }
    }
}
if ($id_ta <= 0) {
    $id_ta = spp_ta_aktif_id($koneksi);
}
if ($id_ta <= 0 && !empty($ta_list)) {
    $id_ta = (int)$ta_list[0]['id_tahun_ajaran'];
}

$ta = spp_ta_info($koneksi, $id_ta);
$periode = $ta['periode'] ?? spp_ta_periode('');

// ==========================================================
// DATA TARIF & KELAS
// ==========================================================
$tingkat_list = spp_tingkat_list($koneksi, $id_ta);
$jurusan_list = spp_jurusan_list($koneksi);
$tarif = spp_tarif_map($koneksi, $id_ta);
$kelas_list = spp_kelas_list($koneksi, $id_ta);

// Jumlah siswa per kombinasi tingkat|jurusan
$siswa_cell = [];
$siswa_tingkat = [];
foreach ($kelas_list as $k) {
    $key = $k['tingkat'] . '|' . $k['id_jurusan'];
    $siswa_cell[$key] = ($siswa_cell[$key] ?? 0) + $k['jumlah_siswa'];
    $siswa_tingkat[$k['tingkat']] = ($siswa_tingkat[$k['tingkat']] ?? 0) + $k['jumlah_siswa'];
}

// Potensi penerimaan (tarif efektif x jumlah siswa)
$potensi_bulan = 0.0;
$tanpa_tarif = 0;
foreach ($kelas_list as $k) {
    $nom = spp_tarif_cari($tarif, $k['tingkat'], $k['id_jurusan']);
    if ($nom === null || $nom <= 0) {
        $tanpa_tarif += $k['jumlah_siswa'];
        continue;
    }
    $potensi_bulan += $nom * $k['jumlah_siswa'];
}
$total_siswa = array_sum(array_column($kelas_list, 'jumlah_siswa'));
$jumlah_tarif = count($tarif);
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Tarif SPP</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item">Keuangan</li>
                        <li class="breadcrumb-item active" aria-current="page">Tarif SPP</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="alert alert-info py-2 mb-3 shadow-sm">
                <i class="bi bi-info-circle-fill me-1"></i>
                Nominal SPP dapat <b>berbeda per jurusan</b>. Isi nominal per tingkat &amp; jurusan; kolom
                <b>Semua Jurusan</b> adalah tarif cadangan yang dipakai bila kombinasi tingkat + jurusan belum diatur.
                Kosongkan input untuk menghapus tarif.
            </div>

            <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['alert']['type']); ?> alert-dismissible fade show" role="alert">
                    <?= $_SESSION['alert']['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>

            <div class="card card-outline card-primary mb-3">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end">
                        <input type="hidden" name="menu" value="spp_tarif">
                        <div class="col-md-5">
                            <label class="form-label fw-bold mb-1">Tahun Ajaran</label>
                            <select name="ta" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($ta_list as $t): ?>
                                    <option value="<?= (int)$t['id_tahun_ajaran']; ?>" <?= ((int)$t['id_tahun_ajaran'] === $id_ta) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($t['tahun_ajaran']); ?><?= $t['status'] === 'aktif' ? ' (aktif)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge bg-primary-subtle text-primary p-2">
                                    <i class="bi bi-calendar-range me-1"></i>Periode: <?= htmlspecialchars($periode[0]['label'] ?? '-'); ?> &ndash; <?= htmlspecialchars($periode[11]['label'] ?? '-'); ?>
                                </span>
                                <span class="badge bg-secondary-subtle text-secondary p-2">
                                    <i class="bi bi-people me-1"></i><?= (int)$total_siswa; ?> siswa / <?= count($kelas_list); ?> kelas
                                </span>
                                <span class="badge bg-success-subtle text-success p-2">
                                    <i class="bi bi-cash-stack me-1"></i>Potensi <?= spp_rupiah($potensi_bulan); ?>/bulan
                                </span>
                                <span class="badge bg-info-subtle text-info p-2">
                                    <i class="bi bi-calculator me-1"></i><?= spp_rupiah($potensi_bulan * 12); ?>/tahun
                                </span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!$ta): ?>
                <div class="alert alert-warning">Tahun ajaran belum dipilih / tidak valid.</div>
            <?php elseif (empty($jurusan_list)): ?>
                <div class="alert alert-warning">Belum ada data jurusan. Tambahkan di menu <a href="index.php?menu=jurusan">Data Jurusan</a>.</div>
            <?php else: ?>

            <div class="card card-outline card-primary mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="mb-0"><i class="bi bi-table me-1"></i> Matriks Tarif — <?= htmlspecialchars($ta['nama']); ?></h5>
                    <span class="badge bg-<?= $jumlah_tarif > 0 ? 'success' : 'secondary'; ?>"><?= (int)$jumlah_tarif; ?> tarif tersimpan</span>
                </div>
                <form action="aksi/aksi_spp_tarif.php" method="post">
                    <input type="hidden" name="aksi" value="simpan">
                    <input type="hidden" name="id_ta" value="<?= (int)$id_ta; ?>">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width:90px;">Tingkat</th>
                                        <?php foreach ($jurusan_list as $id_jur => $nama_jur): ?>
                                            <th class="text-center" style="min-width:150px;"><?= htmlspecialchars($nama_jur); ?></th>
                                        <?php endforeach; ?>
                                        <th class="text-center table-warning" style="min-width:150px;">Semua Jurusan<br><small class="fw-normal">(cadangan)</small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tingkat_list as $tk): ?>
                                        <tr>
                                            <th class="table-light"><?= htmlspecialchars($tk); ?></th>
                                            <?php foreach ($jurusan_list as $id_jur => $nama_jur): ?>
                                                <?php
                                                $key = $tk . '|' . (int)$id_jur;
                                                $val = $tarif[$key] ?? null;
                                                $jml = $siswa_cell[$key] ?? 0;
                                                ?>
                                                <td class="<?= $val !== null ? 'table-success' : ''; ?>">
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">Rp</span>
                                                        <input type="number" min="0" step="1000" class="form-control text-end"
                                                            name="nominal[<?= htmlspecialchars($tk); ?>][<?= (int)$id_jur; ?>]"
                                                            value="<?= $val !== null ? (int)$val : ''; ?>"
                                                            placeholder="0">
                                                    </div>
                                                    <div class="small text-muted mt-1">
                                                        <?= (int)$jml; ?> siswa
                                                        <?= $val !== null ? '· tarif khusus' : '· pakai cadangan'; ?>
                                                    </div>
                                                </td>
                                            <?php endforeach; ?>
                                            <?php $val0 = $tarif[$tk . '|0'] ?? null; ?>
                                            <td class="table-warning">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="number" min="0" step="1000" class="form-control text-end"
                                                        name="nominal[<?= htmlspecialchars($tk); ?>][0]"
                                                        value="<?= $val0 !== null ? (int)$val0 : ''; ?>"
                                                        placeholder="0">
                                                </div>
                                                <div class="small text-muted mt-1">
                                                    <?= (int)($siswa_tingkat[$tk] ?? 0); ?> siswa tingkat <?= htmlspecialchars($tk); ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="small text-muted">
                            <i class="bi bi-lightbulb me-1"></i>Contoh: tarif Kuliner XI bisa berbeda dengan Perhotelan XI.
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Simpan Tarif
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($tanpa_tarif > 0): ?>
                <div class="alert alert-warning py-2">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <b><?= (int)$tanpa_tarif; ?> siswa</b> belum punya tarif (tingkat &amp; jurusannya kosong dan tidak ada tarif cadangan).
                    Tagihan mereka akan dilewati saat generate.
                </div>
            <?php endif; ?>

            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-check me-1"></i> Tarif Efektif per Kelas</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kelas</th>
                                    <th>Tingkat</th>
                                    <th>Jurusan</th>
                                    <th class="text-center">Siswa</th>
                                    <th class="text-end">Tarif / Bulan</th>
                                    <th class="text-end">Potensi / Tahun</th>
                                    <th class="text-center">Sumber Tarif</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($kelas_list)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-3">Belum ada kelas pada tahun ajaran ini.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($kelas_list as $k): ?>
                                        <?php
                                        $nom = spp_tarif_cari($tarif, $k['tingkat'], $k['id_jurusan']);
                                        $exact = isset($tarif[$k['tingkat'] . '|' . $k['id_jurusan']]);
                                        ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($k['tingkat'] . ' ' . $k['nama_kelas']); ?></td>
                                            <td><?= htmlspecialchars($k['tingkat']); ?></td>
                                            <td class="small"><?= htmlspecialchars($k['jurusan'] ?: '-'); ?></td>
                                            <td class="text-center"><?= (int)$k['jumlah_siswa']; ?></td>
                                            <td class="text-end"><?= $nom !== null ? spp_rupiah($nom) : '<span class="text-danger">-</span>'; ?></td>
                                            <td class="text-end"><?= $nom !== null ? spp_rupiah($nom * 12 * $k['jumlah_siswa']) : '-'; ?></td>
                                            <td class="text-center">
                                                <?php if ($nom === null): ?>
                                                    <span class="badge bg-danger">belum diatur</span>
                                                <?php elseif ($exact): ?>
                                                    <span class="badge bg-success">jurusan</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">cadangan</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>
</main>
