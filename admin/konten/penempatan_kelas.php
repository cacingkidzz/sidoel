<?php
// Pastikan file koneksi.php sudah ter-include
include("koneksi.php"); // Ganti dengan path yang benar
// Sertakan library Bootstrap Icons di header HTML Anda.

// ===========================================
// Penentuan ID Tahun Ajaran Aktif (TETAP)
// ===========================================
$q_ta_aktif = $koneksi->query("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE status = 'aktif'");
if ($q_ta_aktif && $q_ta_aktif->num_rows > 0) {
    $d_ta_aktif = $q_ta_aktif->fetch_assoc();
    $id_ta_aktif = $d_ta_aktif['id_tahun_ajaran'];
    $nama_ta_aktif = $d_ta_aktif['tahun_ajaran'];
} else {
    // Handle error atau set nilai default jika tidak ada Tahun Ajaran Aktif
    $id_ta_aktif = 0;
    $nama_ta_aktif = "TA Tidak Ditemukan";
}

// ===========================================
// Ambil Daftar Jurusan (TETAP)
// ===========================================
$q_jurusan = $koneksi->query("SELECT * FROM dt_jurusan ORDER BY nama_jurusan");
$jurusan_list = [];
while ($row = $q_jurusan->fetch_assoc()) {
    $jurusan_list[$row['id_jurusan']] = $row;
}

// ===========================================
// Ambil Daftar Kelas (REVISI: Sekarang mencakup X, XI, XII)
// Kelas di tahun ajaran aktif
// ===========================================
$q_kelas = $koneksi->query("
    SELECT k.*, j.nama_jurusan 
    FROM dt_kelas k
    JOIN dt_jurusan j ON k.id_jurusan = j.id_jurusan
    WHERE k.id_tahun_ajaran = '$id_ta_aktif' /* Kondisi tingkat 'X' dihapus */
    ORDER BY k.tingkat, j.nama_jurusan, k.nama_kelas
");
$kelas_list = [];
while ($row = $q_kelas->fetch_assoc()) {
    $kelas_list[] = $row;
}

// ===========================================
// Okupansi kelas saat ini (untuk modal otomatis)
// ===========================================
$okupansi_kelas = [];
$q_okupansi = $koneksi->query("
    SELECT id_kelas, COUNT(*) AS jml
    FROM dt_kelas_siswa
    WHERE id_tahun_ajaran = '$id_ta_aktif'
    GROUP BY id_kelas
");
if ($q_okupansi) {
    while ($row = $q_okupansi->fetch_assoc()) {
        $okupansi_kelas[$row['id_kelas']] = (int)$row['jml'];
    }
}

// ===========================================
// >> QUERY UTAMA: Siswa yang BELUM Punya Kelas
// PERUBAHAN: JOIN langsung dari dt_siswa ke dt_jurusan
// ===========================================
$q_siswa_butuh_kelas = $koneksi->query("
    SELECT 
        s.id_siswa, s.nama, s.jk, s.nisn, s.nipd, s.jurusan, s.rombel, /* Anggap s.jurusan adalah kolom VARCHAR */
        j.id_jurusan AS id_jurusan_siswa, j.nama_jurusan
    FROM dt_siswa s
    
    /* GANTI: LEFT JOIN langsung ke dt_jurusan menggunakan kolom nama jurusan di dt_siswa */
    LEFT JOIN dt_jurusan j ON s.jurusan = j.nama_jurusan
    
    /* KUNCI: LEFT JOIN ke dt_kelas_siswa dan pastikan TIDAK ADA di tahun ajaran ini */
    LEFT JOIN dt_kelas_siswa ks ON s.id_siswa = ks.id_siswa AND ks.id_tahun_ajaran = '$id_ta_aktif'
    WHERE 
        s.id_tahun_ajaran = '$id_ta_aktif' 
        AND ks.id_kelas IS NULL /* KUNCI: BELUM punya kelas di dt_kelas_siswa */
    ORDER BY s.nipd DESC, j.id_jurusan, s.nama 
");

$siswa_butuh_kelas = [];
$total_siswa_butuh_kelas = $q_siswa_butuh_kelas ? $q_siswa_butuh_kelas->num_rows : 0;
$anomaly_count = 0; // Hitungan siswa anomali

if ($q_siswa_butuh_kelas) {
    while ($row = $q_siswa_butuh_kelas->fetch_assoc()) {
        $id_jurusan = (int) ($row['id_jurusan_siswa'] ?? 0);

        // ===========================================
        // LOGIKA PERBAIKAN SISWA ANOMALI (NIPD ADA, KELAS KOSONG)
        // Jika ID Jurusan dari JOIN kosong/NULL (0) DAN siswa punya NIPD (anomali/siswa lama),
        // alokasikan ke ID Jurusan placeholder (99)
        if ($id_jurusan == 0 && !empty($row['nipd'])) {
            $id_jurusan = 99; // ID Placeholder untuk Anomali
            $row['nama_jurusan'] = 'ANOMALI (NIPD/NIS Ada) - Jurusan Asal: ' . ($row['jurusan'] ?? 'Kosong');
            $anomaly_count++;
        }
        // ===========================================

        // Hanya masukkan siswa yang ID Jurusan teridentifikasi (> 0 atau 99)
        if ($id_jurusan > 0) {
            $siswa_butuh_kelas[$id_jurusan][] = $row;
        }
    }
}

// Tambahkan Jurusan placeholder ke list jika ada siswa anomali
if ($anomaly_count > 0) {
    if (!isset($jurusan_list[99])) {
        $jurusan_list[99] = ['id_jurusan' => 99, 'nama_jurusan' => 'ANOMALI (NIPD/NIS Ada)'];
    }
}

// ===========================================
// QUERY Siswa Siap NIS 
// PERUBAHAN: JOIN langsung dari dt_siswa ke dt_jurusan
// ===========================================
$q_siswa_siap_nis = $koneksi->query("
    SELECT 
        s.id_siswa, s.nama, s.jk, s.nisn, s.nipd, 
        j.id_jurusan AS id_jurusan_siswa, j.nama_jurusan, 
        k.nama_kelas, k.id_kelas
    FROM dt_siswa s
    
    /* GANTI: LEFT JOIN langsung ke dt_jurusan menggunakan kolom nama jurusan di dt_siswa */
    LEFT JOIN dt_jurusan j ON s.jurusan = j.nama_jurusan

    INNER JOIN dt_kelas_siswa ks ON s.id_siswa = ks.id_siswa AND ks.id_tahun_ajaran = '$id_ta_aktif'
    INNER JOIN dt_kelas k ON ks.id_kelas = k.id_kelas
    
    WHERE 
        s.id_tahun_ajaran = '$id_ta_aktif' 
        AND (s.nipd IS NULL OR s.nipd = '') /* KUNCI: BELUM punya NIS */
        
    ORDER BY j.id_jurusan, k.nama_kelas, s.nama
");

$siswa_siap_nis = [];
$total_siswa_siap_nis = $q_siswa_siap_nis ? $q_siswa_siap_nis->num_rows : 0;

if ($q_siswa_siap_nis) {
    while ($row = $q_siswa_siap_nis->fetch_assoc()) {
        $id_jurusan = (int) ($row['id_jurusan_siswa'] ?? 0);
        if ($id_jurusan > 0) {
            $siswa_siap_nis[$id_jurusan][] = $row;
        }
    }
}

// ===========================================
// Nomor NIS berikutnya (lanjutan dari NIS terbesar yang sudah ada)
// ===========================================
$q_max_nis = $koneksi->query("SELECT MAX(CAST(NULLIF(nipd, '') AS UNSIGNED)) AS max_nis FROM dt_siswa");
$max_nis_row = $q_max_nis ? $q_max_nis->fetch_assoc() : null;
$nis_berikutnya = (int)($max_nis_row['max_nis'] ?? 0) + 1;
if ($nis_berikutnya <= 1) {
    $nis_berikutnya = 5922; // fallback: lanjut dari data NIS lama (max 5921)
}

// ===========================================
// Data Pindah Kelas / Mutasi Rombel
// (siswa yang SUDAH punya kelas dipindah ke rombel lain pada TA aktif)
// ===========================================
$id_kelas_asal = isset($_GET['kelas_asal']) ? (int)$_GET['kelas_asal'] : 0;
$kelas_asal_info = null;
$siswa_kelas_asal = [];

if ($id_kelas_asal > 0) {
    foreach ($kelas_list as $kelas_asal) {
        if ((int)$kelas_asal['id_kelas'] === $id_kelas_asal) {
            $kelas_asal_info = $kelas_asal;
            break;
        }
    }

    if ($kelas_asal_info) {
        $q_siswa_asal = $koneksi->query("
            SELECT s.id_siswa, s.nama, s.jk, s.nisn, s.nipd
            FROM dt_kelas_siswa ks
            INNER JOIN dt_siswa s ON s.id_siswa = ks.id_siswa
            WHERE ks.id_kelas = '$id_kelas_asal' AND ks.id_tahun_ajaran = '$id_ta_aktif'
            ORDER BY s.nama
        ");
        if ($q_siswa_asal) {
            while ($row = $q_siswa_asal->fetch_assoc()) {
                $siswa_kelas_asal[] = $row;
            }
        }
    }
}
?>

<div class="card shadow mb-4 mt-4">

    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Penempatan Kelas Siswa Tahun Ajaran <?= $nama_ta_aktif; ?></h6>
        <small class="text-muted">
            Butuh Kelas: <b><?= $total_siswa_butuh_kelas; ?></b> siswa |
            Siap Penomoran NIS: <b><?= $total_siswa_siap_nis; ?></b> siswa
        </small>
        <?php if (isset($_GET['status']) && isset($_GET['pesan'])): ?>
            <div class="alert alert-<?= $_GET['status'] == 'success' ? 'success' : 'danger'; ?> mt-2" role="alert">
                <?= htmlspecialchars($_GET['pesan']); ?>
            </div>
        <?php endif; ?>
    </div>

    <hr>

    <div class="card-body">

        <?php if ($total_siswa_butuh_kelas > 0): ?>

            <h5 class="mb-3 text-warning"><i class="bi bi-person-exclamation me-1"></i> Alokasi Kelas Manual (<?= $total_siswa_butuh_kelas; ?> Siswa)</h5>

            <div class="alert alert-warning small">
                Daftar di bawah ini berisi siswa baru (<b>NIS/NIPD kosong</b>) dan siswa anomali (<b>NIS/NIPD sudah ada</b>) yang belum terdaftar di kelas manapun pada Tahun Ajaran ini. Harap segera alokasikan kelasnya.
            </div>

            <div class="mb-4">
            </div>

            <ul class="nav nav-tabs" id="jurusanTab" role="tablist">
                <?php $i = 0;
                // MENGGUNAKAN $siswa_butuh_kelas
                foreach ($siswa_butuh_kelas as $id_jurusan => $siswa_list): ?>
                    <?php
                    $jurusan_data = $jurusan_list[$id_jurusan] ?? ['nama_jurusan' => 'Jurusan Tidak Ditemukan'];
                    $active = ($i == 0) ? 'active' : '';
                    ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $active; ?>" id="tab-<?= $id_jurusan; ?>" data-bs-toggle="tab" data-bs-target="#content-<?= $id_jurusan; ?>" type="button" role="tab" aria-controls="content-<?= $id_jurusan; ?>" aria-selected="<?= ($i == 0) ? 'true' : 'false'; ?>">
                            <?= $jurusan_data['nama_jurusan']; ?> (<?= count($siswa_list); ?>)
                        </button>
                    </li>
                <?php $i++;
                endforeach; ?>
            </ul>

            <div class="tab-content" id="jurusanTabContent">
                <?php $i = 0;
                // MENGGUNAKAN $siswa_butuh_kelas
                foreach ($siswa_butuh_kelas as $id_jurusan => $siswa_list): ?>
                    <?php
                    $active = ($i == 0) ? 'show active' : '';
                    $jurusan_data_aman = $jurusan_list[$id_jurusan] ?? ['nama_jurusan' => 'Jurusan Tidak Ditemukan'];
                    ?>

                    <div class="tab-pane fade <?= $active; ?> pt-3" id="content-<?= $id_jurusan; ?>" role="tabpanel" aria-labelledby="tab-<?= $id_jurusan; ?>">

                        <h5 class="mb-3">Daftar Siswa Jurusan <b><?= $jurusan_data_aman['nama_jurusan']; ?></b></h5>

                        <?php if ($id_jurusan != 99): ?>
                            <div class="alert alert-light border d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div class="small">
                                    <i class="bi bi-magic text-primary me-1"></i>
                                    <b>Penempatan Otomatis:</b> siswa diurutkan berdasarkan <b>nama</b>, diselang-seling L/P,
                                    lalu dibagi <b>rata</b> ke kelas-<b><?= $jurusan_data_aman['nama_jurusan']; ?></b> yang dicentang.
                                </div>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#otomatisModal<?= $id_jurusan; ?>">
                                    <i class="bi bi-magic me-1"></i> Penempatan Otomatis (<?= count($siswa_list); ?> Siswa)
                                </button>
                            </div>
                        <?php endif; ?>

                        <form action="aksi/alokasi_kelas.php" method="post" onsubmit="return validateForm_<?= $id_jurusan; ?>(this);">
                            <input type="hidden" name="id_jurusan" value="<?= $id_jurusan; ?>">
                            <input type="hidden" name="alokasi_manual_massal" value="1">
                            <input type="hidden" name="id_tahun_ajaran" value="<?= $id_ta_aktif; ?>">

                            <div class="row mb-3 align-items-center">
                                <div class="col-md-auto">
                                    <label for="id_kelas_target_<?= $id_jurusan; ?>" class="col-form-label fw-bold">Pilih Kelas Target:</label>
                                </div>
                                <div class="col-md-5">
                                    <select name="id_kelas_target" id="id_kelas_target_<?= $id_jurusan; ?>" class="form-select form-select-sm" required>
                                        <option value="">-- Pilih Kelas --</option>
                                        <?php
                                        foreach ($kelas_list as $kelas):
                                            // Filter Kelas berdasarkan Jurusan yang relevan, KECUALI Jurusan Anomali (ID 99)
                                            if ($id_jurusan == 99 || $kelas['id_jurusan'] == $id_jurusan):
                                        ?>
                                                <option value="<?= $kelas['id_kelas']; ?>">Kelas <?= $kelas['tingkat']; ?> - <?= $kelas['nama_kelas']; ?> (<?= $kelas['nama_jurusan']; ?>)</option>
                                        <?php
                                            endif;
                                        endforeach;
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-auto">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-save me-1"></i> Alokasikan Siswa Terpilih
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table id="myTable2" class="table table-bordered table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 5%;">
                                                <input type="checkbox" id="checkAll_<?= $id_jurusan; ?>" class="form-check-input check-all" data-target="<?= $id_jurusan; ?>">
                                            </th>
                                            <th>Nama Siswa</th>
                                            <th>Jenis Kelamin</th>
                                            <th>NISN/NIS</th>
                                            <th>Kelas</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($siswa_list as $siswa): ?>
                                            <?php
                                            $status_text = empty($siswa['nipd']) ? 'Baru' : 'Anomali (NIS/NIPD ada)';
                                            $status_badge = empty($siswa['nipd']) ? 'bg-primary' : 'bg-danger';
                                            $nis_display = empty($siswa['nipd']) ? $siswa['nisn'] : $siswa['nipd'];
                                            ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="siswa_id_terpilih[]" value="<?= $siswa['id_siswa']; ?>" class="form-check-input check-item-<?= $id_jurusan; ?>">
                                                </td>
                                                <td><?= $siswa['nama']; ?></td>
                                                <td><?= $siswa['jk']; ?></td>
                                                <td><?= $nis_display; ?></td>
                                                <td><?= $siswa['rombel']; ?></td>
                                                <td><span class="badge <?= $status_badge; ?>"><?= $status_text; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    </div>
                <?php $i++;
                endforeach; ?>
            </div>

            <?php
            // ==================================================
            // MODAL PENEMPATAN OTOMATIS (per jurusan, kecuali 99)
            // ==================================================
            foreach ($siswa_butuh_kelas as $id_jurusan_modal => $siswa_list_modal):
                if ($id_jurusan_modal == 99) continue;
                $jurusan_modal = $jurusan_list[$id_jurusan_modal] ?? ['nama_jurusan' => 'Jurusan Tidak Ditemukan'];
                $jml_l = $jml_p = 0;
                foreach ($siswa_list_modal as $s) {
                    if (strtoupper(trim($s['jk'])) === 'P') $jml_p++;
                    else $jml_l++;
                }
            ?>
                <div class="modal fade" id="otomatisModal<?= $id_jurusan_modal; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <form action="aksi/alokasi_otomatis.php" method="post">
                                <input type="hidden" name="alokasi_otomatis" value="1">
                                <input type="hidden" name="id_jurusan" value="<?= $id_jurusan_modal; ?>">
                                <input type="hidden" name="id_tahun_ajaran" value="<?= $id_ta_aktif; ?>">

                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title">
                                        <i class="bi bi-magic me-1"></i> Penempatan Otomatis — <?= $jurusan_modal['nama_jurusan']; ?>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info small mb-3">
                                        <b><?= count($siswa_list_modal); ?> siswa</b> (Laki-laki: <b><?= $jml_l; ?></b>, Perempuan: <b><?= $jml_p; ?></b>)
                                        akan diurutkan berdasarkan <b>nama</b>, diselang-seling L/P,
                                        lalu dibagi <b>rata</b> ke kelas yang dicentang di bawah ini.
                                        Centang hanya kelas yang menjadi target penempatan (misalnya kelas X untuk siswa baru).
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-bold mb-0">Kelas Target:</label>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary" id="centangSemua<?= $id_jurusan_modal; ?>">Centang Semua</button>
                                            <button type="button" class="btn btn-outline-secondary" id="hapusCentang<?= $id_jurusan_modal; ?>">Hapus Centang</button>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th style="width: 8%;" class="text-center">Pilih</th>
                                                    <th>Kelas</th>
                                                    <th style="width: 25%;">Tingkat</th>
                                                    <th style="width: 25%;">Terisi Saat Ini</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $ada_kelas = false;
                                                foreach ($kelas_list as $kelas):
                                                    if ($kelas['id_jurusan'] != $id_jurusan_modal) continue;
                                                    $ada_kelas = true;
                                                    $jml_terisi = $okupansi_kelas[$kelas['id_kelas']] ?? 0;
                                                ?>
                                                    <tr>
                                                        <td class="text-center">
                                                            <input type="checkbox" class="form-check-input kelas-otomatis-<?= $id_jurusan_modal; ?>"
                                                                name="id_kelas_terpilih[]" value="<?= $kelas['id_kelas']; ?>" checked>
                                                        </td>
                                                        <td><b><?= $kelas['nama_kelas']; ?></b></td>
                                                        <td><span class="badge bg-secondary">Kelas <?= $kelas['tingkat']; ?></span></td>
                                                        <td><?= number_format($jml_terisi); ?> siswa</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (!$ada_kelas): ?>
                                                    <tr><td colspan="4" class="text-center text-muted">Tidak ada kelas untuk jurusan ini.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary" id="submitOtomatis<?= $id_jurusan_modal; ?>"
                                        onclick="return confirm('Lanjutkan penempatan otomatis <?= count($siswa_list_modal); ?> siswa jurusan <?= addslashes($jurusan_modal['nama_jurusan']); ?>? Operasi ini akan mengupdate Jurusan dan Rombel pada data siswa.');">
                                        <i class="bi bi-magic me-1"></i> Tempatkan Otomatis
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <hr class="mt-5 mb-5">

        <?php endif; ?>

        <?php if ($total_siswa_siap_nis > 0): ?>
            <h5 class="mb-3 text-success"><i class="bi bi-person-check me-1"></i> Siswa Siap Penomoran NIS</h5>
            <div class="alert alert-info">
                Siswa di bawah ini <b>sudah</b> mendapatkan alokasi kelas dan siap untuk diberikan Nomor Induk Siswa (NIS).
            </div>

            <form action="aksi/generatenis.php" method="post" id="formNisGenerator">
                <input type="hidden" name="id_tahun_ajaran" value="<?= $id_ta_aktif; ?>">

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nomor NIS Berikutnya</label>
                        <input type="text" class="form-control form-control-sm" value="<?= $nis_berikutnya; ?> - <?= $nis_berikutnya + max($total_siswa_siap_nis - 1, 0); ?>" readonly>
                        <small class="text-muted">Nomor urut berlanjut dari NIS siswa terakhir (<?= $nis_berikutnya; ?>, <?= $nis_berikutnya + 1; ?>, dst). Tidak perlu input awalan.</small>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button type="submit" name="generate_nis" class="btn btn-primary w-100">
                            <i class="bi bi-gear me-1"></i> Mulai Penomoran NIS Otomatis
                        </button>
                    </div>
                </div>

                <hr>

                <?php foreach ($siswa_siap_nis as $id_jurusan => $siswa_list): ?>
                    <?php $jurusan_data = $jurusan_list[$id_jurusan] ?? ['nama_jurusan' => 'Jurusan Tidak Ditemukan']; ?>

                    <h6 class="mt-4 mb-3 text-info">
                        <i class="bi bi-bookmark-fill me-1"></i> Jurusan <b><?= $jurusan_data['nama_jurusan']; ?></b> (<?= count($siswa_list); ?> Siswa)
                    </h6>

                    <div class="table-responsive mb-5">
                        <table id="myTable" class="table table-bordered table-striped table-hover small">
                            <thead>
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th>Nama Siswa</th>
                                    <th>Kelas X</th>
                                    <th>NISN</th>
                                    <th style="width: 20%;">Status NIS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1;
                                foreach ($siswa_list as $siswa): ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= $siswa['nama']; ?></td>
                                        <td><b><?= $siswa['nama_kelas']; ?></b></td>
                                        <td><?= $siswa['nisn']; ?></td>
                                        <td>
                                            <input type="hidden" name="siswa_to_process[]" value="<?= $siswa['id_siswa']; ?>">
                                            <span class="badge bg-warning text-dark">Menunggu NIS</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </form>

        <?php elseif ($total_siswa_butuh_kelas == 0 && $total_siswa_siap_nis == 0): ?>
            <div class="alert alert-success mt-3">
                <i class="bi bi-check-circle-fill me-2"></i> <b>Selesai!</b> Semua siswa sudah dialokasikan kelas dan sudah memiliki NIS.
            </div>
        <?php endif; ?>

    </div>
</div>

<div class="card shadow mb-4" id="pindah-kelas">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-arrow-left-right me-1"></i> Pindah Kelas / Mutasi Siswa</h6>
        <small class="text-muted">
            Pindahkan siswa yang <b>sudah punya kelas</b> ke rombel lain pada Tahun Ajaran <?= htmlspecialchars($nama_ta_aktif); ?>.
        </small>
    </div>

    <div class="card-body">

        <form method="get" action="index.php" class="row g-2 align-items-end mb-4">
            <input type="hidden" name="menu" value="penempatan_kelas">
            <div class="col-md-6">
                <label for="kelas_asal" class="form-label fw-bold">Kelas Asal</label>
                <select name="kelas_asal" id="kelas_asal" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Pilih Kelas Asal --</option>
                    <?php foreach ($kelas_list as $kelas): ?>
                        <option value="<?= $kelas['id_kelas']; ?>" <?= ($id_kelas_asal == $kelas['id_kelas']) ? 'selected' : ''; ?>>
                            Kelas <?= htmlspecialchars($kelas['tingkat']); ?> - <?= htmlspecialchars($kelas['nama_kelas']); ?> (<?= htmlspecialchars($kelas['nama_jurusan']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search me-1"></i>Tampilkan</button>
            </div>
        </form>

        <?php if ($id_kelas_asal > 0 && !$kelas_asal_info): ?>
            <div class="alert alert-danger mb-0">Kelas asal tidak ditemukan pada tahun ajaran ini.</div>
        <?php elseif ($kelas_asal_info): ?>
            <?php if (count($siswa_kelas_asal) === 0): ?>
                <div class="alert alert-warning mb-0">Tidak ada siswa pada kelas <b><?= htmlspecialchars($kelas_asal_info['nama_kelas']); ?></b>.</div>
            <?php else: ?>
                <form action="aksi/pindah_kelas.php" method="post" onsubmit="return confirmPindahKelas(this);">
                    <input type="hidden" name="pindah_kelas" value="1">
                    <input type="hidden" name="id_tahun_ajaran" value="<?= $id_ta_aktif; ?>">
                    <input type="hidden" name="id_kelas_asal" value="<?= $id_kelas_asal; ?>">

                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-6">
                            <label for="id_kelas_tujuan" class="form-label fw-bold">Kelas Tujuan</label>
                            <select name="id_kelas_tujuan" id="id_kelas_tujuan" class="form-select" required>
                                <option value="">-- Pilih Kelas Tujuan --</option>
                                <?php foreach ($kelas_list as $kelas): ?>
                                    <?php if ($kelas['id_kelas'] == $id_kelas_asal) continue; ?>
                                    <option value="<?= $kelas['id_kelas']; ?>">
                                        Kelas <?= htmlspecialchars($kelas['tingkat']); ?> - <?= htmlspecialchars($kelas['nama_kelas']); ?> (<?= htmlspecialchars($kelas['nama_jurusan']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-arrow-left-right me-1"></i> Pindahkan Siswa Terpilih
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 5%;">
                                        <input type="checkbox" id="checkAllPindah" class="form-check-input">
                                    </th>
                                    <th>Nama Siswa</th>
                                    <th style="width: 10%;">JK</th>
                                    <th>NISN</th>
                                    <th>NIS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($siswa_kelas_asal as $sw): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="siswa_id_pindah[]" value="<?= $sw['id_siswa']; ?>" class="form-check-input check-pindah">
                                        </td>
                                        <td><?= htmlspecialchars($sw['nama']); ?></td>
                                        <td><?= htmlspecialchars($sw['jk']); ?></td>
                                        <td><?= htmlspecialchars($sw['nisn'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($sw['nipd'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <script>
                    (function() {
                        var checkAll = document.getElementById('checkAllPindah');
                        if (checkAll) {
                            checkAll.addEventListener('change', function() {
                                document.querySelectorAll('.check-pindah').forEach(function(cb) {
                                    cb.checked = checkAll.checked;
                                });
                            });
                        }
                    })();

                    function confirmPindahKelas(form) {
                        var selected = form.querySelectorAll('.check-pindah:checked');
                        if (selected.length === 0) {
                            alert('Pilih minimal satu siswa untuk dipindahkan.');
                            return false;
                        }
                        var target = form.querySelector('#id_kelas_tujuan');
                        if (!target.value) {
                            alert('Pilih kelas tujuan terlebih dahulu.');
                            return false;
                        }
                        return confirm('Pindahkan ' + selected.length + ' siswa terpilih ke kelas tujuan? Jurusan dan Rombel pada data siswa akan diperbarui.');
                    }
                </script>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkAll = document.querySelectorAll('.check-all');

        checkAll.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const targetId = this.getAttribute('data-target');
                const checkboxes = document.querySelectorAll('.check-item-' + targetId);
                checkboxes.forEach(function(item) {
                    item.checked = checkbox.checked;
                });
            });
        });

        // Tombol Centang/Hapus Centang kelas pada modal penempatan otomatis
        <?php foreach ($siswa_butuh_kelas as $id_jurusan_js => $list_js): if ($id_jurusan_js == 99) continue; ?>
        (function() {
            const btnSemua = document.getElementById('centangSemua<?= $id_jurusan_js; ?>');
            const btnHapus = document.getElementById('hapusCentang<?= $id_jurusan_js; ?>');
            if (btnSemua) {
                btnSemua.addEventListener('click', function() {
                    document.querySelectorAll('.kelas-otomatis-<?= $id_jurusan_js; ?>').forEach(function(cb) { cb.checked = true; });
                });
            }
            if (btnHapus) {
                btnHapus.addEventListener('click', function() {
                    document.querySelectorAll('.kelas-otomatis-<?= $id_jurusan_js; ?>').forEach(function(cb) { cb.checked = false; });
                });
            }
        })();
        <?php endforeach; ?>
    });

    // Fungsi Validasi Form Penempatan Kelas
    <?php
    foreach ($siswa_butuh_kelas as $id_jurusan => $siswa_list): ?>

        function validateForm_<?= $id_jurusan; ?>(form) {
            const selectedStudents = form.querySelectorAll('.check-item-<?= $id_jurusan; ?>:checked');

            if (selectedStudents.length === 0) {
                alert('Pilih minimal satu siswa untuk dialokasikan ke kelas.');
                return false;
            }

            const targetClass = form.querySelector('#id_kelas_target_<?= $id_jurusan; ?>').value;
            if (targetClass === "") {
                alert('Pilih kelas target sebelum menyimpan.');
                return false;
            }

            return confirm('Anda yakin ingin memindahkan ' + selectedStudents.length + ' siswa ke kelas yang dipilih? Operasi ini juga akan mengupdate data Jurusan dan Rombel pada data siswa.');
        }
    <?php endforeach; ?>
</script>