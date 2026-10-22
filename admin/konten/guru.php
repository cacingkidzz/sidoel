<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
</div>
<?php
// Pastikan file koneksi.php sudah ter-include
include("koneksi.php");

// ===========================================
// DEFINISI PILIHAN (OPTIONS) UNTUK FORM
// ===========================================
$agama_options = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'];
$status_kawin_options = ['Belum Kawin', 'Kawin', 'Janda/Duda', 'Cerai Mati'];
$kewarganegaraan_options = ['WNI', 'WNA'];
$status_pegawai_options = ['PNS', 'PPPK', 'GTY/PTY', 'GTT', 'TU', 'Guru Honor Sekolah', 'Lainnya'];
$tugas_tambahan_options = ['Tidak Ada', 'Kepala Sekolah', 'Wakil Kepala Sekolah', 'Kepala Program Keahlian', 'Kepala Lab', 'Kepala Perpustakaan', 'Wali Kelas', 'Guru Mata Pelajaran', 'Staf TU'];
$lulusan_options = ['SD/Sederajat', 'SMP/Sederajat', 'SMA/SMK/Sederajat', 'D I', 'D II', 'D III', 'D IV', 'S1', 'S2', 'S3'];

// --- QUERY UNTUK STATISTIK GURU ---
$q_total = $koneksi->query("SELECT COUNT(*) as total FROM dt_guru");
$d_total = $q_total->fetch_assoc();

$q_guru_l = $koneksi->query("SELECT COUNT(*) as total FROM dt_guru WHERE jk='L'");
$d_guru_l = $q_guru_l->fetch_assoc();

$q_guru_p = $koneksi->query("SELECT COUNT(*) as total FROM dt_guru WHERE jk='P'");
$d_guru_p = $q_guru_p->fetch_assoc();

// --- HELPER TAMPILAN ---
if (!function_exists('inisial_guru')) {
    function inisial_guru($nama)
    {
        $nama = trim(preg_replace('/\s+/', ' ', (string)$nama));
        if ($nama === '') return '?';
        $parts = explode(' ', $nama);
        $ini = strtoupper(substr($parts[0], 0, 1));
        if (count($parts) > 1) {
            $ini .= strtoupper(substr(end($parts), 0, 1));
        }
        return $ini;
    }
}
if (!function_exists('avatar_color_guru')) {
    function avatar_color_guru($nama)
    {
        $colors = ['#2563eb', '#16a34a', '#0891b2', '#d97706', '#db2777', '#7c3aed', '#dc2626', '#0d9488'];
        return $colors[abs(crc32((string)$nama)) % count($colors)];
    }
}
?>

<style>
    /* ===== Modal Guru (rapi & informatif) ===== */
    .guru-modal .modal-content { border: 0; border-radius: .85rem; overflow: hidden; box-shadow: 0 12px 40px rgba(16, 24, 40, .22); }
    .guru-modal .modal-body { background: #f6f8fb; }
    .guru-head { display: flex; align-items: center; gap: .9rem; padding: 1.05rem 1.25rem; color: #fff; }
    .guru-head h5 { margin: 0 0 .2rem; font-weight: 600; }
    .guru-head small { opacity: .92; }
    .guru-head-ic { width: 48px; height: 48px; flex: 0 0 48px; border-radius: 50%; background: rgba(255, 255, 255, .22); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
    .guru-avatar-lg { width: 78px; height: 78px; flex: 0 0 78px; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; font-weight: 700; color: #fff; border: 3px solid rgba(255, 255, 255, .6); background: #64748b; }
    .guru-avatar-lg img { width: 100%; height: 100%; object-fit: cover; }
    .guru-mini { background: #fff; border: 1px solid #e6e9ef; border-radius: .6rem; padding: .55rem .75rem; height: 100%; }
    .guru-mini small { display: block; color: #8a94a6; font-size: .7rem; text-transform: uppercase; letter-spacing: .3px; }
    .guru-mini span { font-weight: 600; font-size: .92rem; }
    .guru-tabs .nav-link { color: #495057; font-weight: 500; border-radius: .55rem; padding: .4rem .9rem; }
    .guru-tabs .nav-link.active { background: #0d6efd; color: #fff; }
    .guru-section { background: #fff; border: 1px solid #e6e9ef; border-radius: .7rem; padding: 1rem 1rem .3rem; margin-bottom: 1rem; }
    .guru-section-title { display: flex; align-items: center; gap: .55rem; font-weight: 600; font-size: .95rem; margin-bottom: .9rem; color: #2b3445; }
    .guru-sec-ic { width: 30px; height: 30px; flex: 0 0 30px; border-radius: .5rem; display: inline-flex; align-items: center; justify-content: center; font-size: .95rem; }
    .guru-dl { font-size: .9rem; }
    .guru-dl dt { font-weight: 500; color: #8a94a6; }
    .guru-dl dd { font-weight: 600; border-bottom: 1px dashed #eef1f5; padding-bottom: .45rem; margin-bottom: .45rem; }
    @media (max-width: 575.98px) { .guru-avatar-lg { width: 60px; height: 60px; flex-basis: 60px; font-size: 1.2rem; } }
</style>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Data Guru</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item"><a href="#!">Data Master</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Guru</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="info-box bg-secondary text-white shadow-sm h-100">
                        <span class="info-box-icon"><i class="bi bi-person-badge-fill"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Guru</span>
                            <span class="info-box-number h4"><?= number_format((int)$d_total['total']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="info-box bg-primary text-white shadow-sm h-100">
                        <span class="info-box-icon"><i class="bi bi-gender-male"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Laki-laki</span>
                            <span class="info-box-number h4"><?= number_format((int)$d_guru_l['total']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="info-box bg-info text-white shadow-sm h-100">
                        <span class="info-box-icon"><i class="bi bi-gender-female"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Perempuan</span>
                            <span class="info-box-number h4"><?= number_format((int)$d_guru_p['total']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <?php
                // Asumsi: $koneksi sudah didefinisikan dari include("koneksi.php");

                // 1. Tentukan Urutan Status yang Ingin Ditampilkan (Status Prioritas)
                // Status ini akan ditampilkan pertama, diikuti status lain yang ditemukan di DB.
                $prioritized_status = ['PNS', 'PPPK', 'GTY/PTY', 'Guru Honor Sekolah'];
                $summary_data = []; // Array untuk menyimpan hasil query

                // 2. Query untuk Menghitung Jumlah Setiap Status Kepegawaian
                $q_status = $koneksi->query("
    SELECT 
        -- Kelompokkan status yang NULL/Kosong menjadi 'STATUS LAIN'
        COALESCE(NULLIF(status_kepegawaian, ''), 'STATUS LAIN/BELUM DIISI') as status_grup,
        COUNT(*) AS total_status
    FROM dt_guru
    GROUP BY status_grup
    ORDER BY total_status DESC
");

                // 3. Masukkan Hasil Query ke dalam $summary_data
                if ($q_status) {
                    while ($d_status = $q_status->fetch_assoc()) {
                        $summary_data[$d_status['status_grup']] = $d_status['total_status'];
                    }
                }

                // 4. Gabungkan Status Prioritas dan Status Lain yang Ditemukan di DB
                // Pastikan tidak ada duplikasi dan urutan prioritas dipertahankan.
                $all_status = array_unique(array_merge($prioritized_status, array_keys($summary_data)));

                // 5. Proses Tampilan HTML
                ?>

                <div class="card card-outline card-info shadow mb-3">
                    <div class="card-header p-2 bg-light">
                        <h6 class="card-title mb-0 fw-bold"><i class="bi bi-people-fill me-1"></i> Rincian Jumlah Guru Berdasarkan Status Kepegawaian</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="d-flex flex-wrap justify-content-start gap-3">
                            <?php
                            // Loop menggunakan $all_status (urutan tampilan)
                            $colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
                            $color_index = 0;

                            foreach ($all_status as $status_name):
                                // Ambil jumlah, jika status tidak ada dalam data, defaultnya 0
                                $jumlah = $summary_data[$status_name] ?? 0;

                                // Tentukan warna badge (berganti-ganti)
                                $bg_color = $jumlah > 0 ? 'bg-' . $colors[$color_index % count($colors)] : 'bg-secondary';
                                $border_class = $jumlah > 0 ? 'border-' . $colors[$color_index % count($colors)] : 'border-secondary';

                                // Label ditampilkan dalam UPPERCASE
                                $status_label = strtoupper(htmlspecialchars($status_name));

                                // Hanya tampilkan jika jumlah > 0 atau jika status termasuk dalam prioritas
                                if ($jumlah > 0 || in_array($status_name, $prioritized_status)):
                            ?>
                                    <div class="p-2 border rounded text-center <?= $border_class ?>" style="min-width: 140px; flex-grow: 1; flex-basis: 0;">
                                        <h6 class="mb-1 text-uppercase fw-bold text-muted small"><?= $status_label ?></h6>
                                        <span class="badge <?= $bg_color ?> fs-5"><?= number_format($jumlah) ?></span>
                                    </div>
                            <?php
                                    if ($jumlah > 0) $color_index++; // Ganti warna hanya jika status memiliki data (> 0)
                                endif;
                            endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-12 col-md-12">
                    <div class="card card-outline card-secondary">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-table me-1"></i> Daftar Data Guru</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahGuru">
                                    <i class="bi bi-plus-lg me-1"></i> Tambah Guru Manual
                                </button>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importGuruModal">
                                    <i class="bi bi-cloud-arrow-up-fill me-1"></i> Import Data (Excel)
                                </button>
                                <a href="laporan/format_data_guru.xlsx" class="btn btn-info" target="_blank">
                                    <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Download Format Excel
                                </a>
                                <a href="aksi/export_guru.php" class="btn btn-danger ms-auto" target="_blank">
                                    <i class="bi bi-file-earmark-arrow-down-fill me-1"></i> Download Data Guru
                                </a>
                            </div>
                            <div class="table-responsive">
                                <table id="myTable1" class="table table-bordered table-striped table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40px;">#</th>
                                            <th>Guru</th>
                                            <th>NUPTK/NIP</th>
                                            <th>Email</th>
                                            <th>Status Kepegawaian</th>
                                            <th>No. Telp</th>
                                            <th class="text-center">JK</th>
                                            <th class="text-center" style="width: 150px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $no = 1;
                                        $modals_html = '';
                                        $sql_guru = $koneksi->query("SELECT * FROM dt_guru ORDER BY nama_guru ASC");
                                        while ($row = $sql_guru->fetch_assoc()) {
                                            // Pengecekan dan mapping data untuk tampilan (Menggunakan ?? untuk keamanan)
                                            $nuptk_nip = !empty($row['nuptk'] ?? '') ? $row['nuptk'] : (!empty($row['nip'] ?? '') ? $row['nip'] : '-');
                                            $jk = $row['jk'] ?? ''; // Ambil nilai jk atau kosong jika tidak ada
                                            $jk_jenis = ($jk == 'L')
                                                ? 'Laki-laki'
                                                : (($jk == 'P')
                                                    ? 'Perempuan'
                                                    : '-');
                                            $jk_badge = ($jk == 'L')
                                                ? '<span class="badge bg-primary">L</span>'
                                                : (($jk == 'P') ? '<span class="badge bg-danger">P</span>' : '<span class="text-muted">-</span>');

                                            // Badge status kepegawaian
                                            $status_kepegawaian = $row['status_kepegawaian'] ?? '';
                                            $status_badge_class = match ($status_kepegawaian) {
                                                'PNS' => 'bg-success',
                                                'PPPK' => 'bg-primary',
                                                'GTY/PTY' => 'bg-info text-dark',
                                                'GTT' => 'bg-warning text-dark',
                                                'Guru Honor Sekolah' => 'bg-secondary',
                                                default => 'bg-light text-dark border',
                                            };
                                            $status_label = $status_kepegawaian !== '' ? htmlspecialchars($status_kepegawaian) : 'Belum diisi';
                                            $status_badge = '<span class="badge ' . $status_badge_class . '">' . $status_label . '</span>';

                                            $tgl_lahir_formatted = !empty($row['tanggal_lahir'] ?? '') ? date('d-m-Y', strtotime($row['tanggal_lahir'])) : '-';
                                            $status_lisensi = (($row['sudah_lisensi_kepala_sekolah'] ?? 0) == 1) ? '<span class="badge bg-success">Sudah</span>' : '<span class="badge bg-danger">Belum</span>';
                                            $foto_file = !empty($row['foto'] ?? '') ? $row['foto'] : 'Belum Ada';
                                            $foto_path = !empty($row['foto'] ?? '') ? "assets/img/foto_guru/" . $row['foto'] : (($row['jk'] ?? '') === 'P' ? "assets/img/wanita.png" : "assets/img/pria.png");

                                            // Avatar: pakai foto bila tersedia, jika tidak tampilkan inisial
                                            $foto_ada = !empty($row['foto']) && file_exists("assets/img/foto_guru/" . $row['foto']);
                                            $inisial = inisial_guru($row['nama_guru'] ?? '');
                                            $ava_color = avatar_color_guru($row['nama_guru'] ?? '');
                                        ?>
                                            <tr>
                                                <td><?= $no++; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($foto_ada): ?>
                                                            <img src="assets/img/foto_guru/<?= htmlspecialchars($row['foto']); ?>" alt="Foto"
                                                                class="rounded-circle me-2 flex-shrink-0" style="width: 38px; height: 38px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="d-flex align-items-center justify-content-center rounded-circle text-white fw-bold me-2 flex-shrink-0"
                                                                style="width: 38px; height: 38px; background-color: <?= $ava_color ?>; font-size: .8rem;">
                                                                <?= htmlspecialchars($inisial); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <div class="fw-semibold"><?= htmlspecialchars($row['nama_guru'] ?? '-'); ?></div>
                                                            <?php if (!empty($row['tugas_tambahan']) && $row['tugas_tambahan'] !== 'Tidak Ada'): ?>
                                                                <small class="text-muted"><?= htmlspecialchars($row['tugas_tambahan']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($nuptk_nip); ?></td>
                                                <td><?= htmlspecialchars($row['email'] ?? '-'); ?></td>
                                                <td><?= $status_badge; ?></td>
                                                <td><?= htmlspecialchars($row['hp'] ?? '-'); ?></td>
                                                <td class="text-center"><?= $jk_badge; ?></td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#detaildata<?= $row['id_guru'] ?>" title="Lihat Detail Lengkap">
                                                            <i class="bi bi-info-circle-fill"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#editdata<?= $row['id_guru'] ?>" title="Edit Data Guru">
                                                            <i class="bi bi-pencil-fill"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-hapus-guru"
                                                            data-id="<?= (int)$row['id_guru'] ?>"
                                                            data-nama="<?= htmlspecialchars($row['nama_guru'] ?? '', ENT_QUOTES) ?>"
                                                            title="Hapus Data Guru"><i class="bi bi-trash-fill"></i></button>
                                                    </div>
                                                </td>
                                            </tr>

                                            <?php ob_start(); ?>
                                            <div class="modal fade guru-modal" id="detaildata<?= $row['id_guru'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                                    <div class="modal-content">
                                                        <div class="guru-head" style="background: linear-gradient(135deg, #0dcaf0, #0d6efd);">
                                                            <div class="guru-avatar-lg" style="background: <?= $ava_color ?>;">
                                                                <?php if ($foto_ada): ?>
                                                                    <img src="assets/img/foto_guru/<?= htmlspecialchars($row['foto']) ?>" alt="Foto">
                                                                <?php else: ?>
                                                                    <?= htmlspecialchars($inisial) ?>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h5><?= htmlspecialchars($row['nama_guru'] ?? '-') ?></h5>
                                                                <div class="d-flex flex-wrap gap-1">
                                                                    <?= $status_badge ?>
                                                                    <span class="badge bg-<?= $jk == 'L' ? 'primary' : ($jk == 'P' ? 'danger' : 'secondary') ?>"><?= $jk_jenis ?></span>
                                                                    <?php if (!empty($row['tugas_tambahan']) && $row['tugas_tambahan'] !== 'Tidak Ada'): ?>
                                                                        <span class="badge bg-light text-dark"><?= htmlspecialchars($row['tugas_tambahan']) ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row g-2 mb-3">
                                                                <div class="col-6 col-md-3"><div class="guru-mini"><small>NUPTK / NIP</small><span><?= htmlspecialchars($nuptk_nip) ?></span></div></div>
                                                                <div class="col-6 col-md-3"><div class="guru-mini"><small>Pendidikan</small><span><?= htmlspecialchars($row['lulusan'] ?? '-') ?></span></div></div>
                                                                <div class="col-6 col-md-3"><div class="guru-mini"><small>No. HP</small><span><?= htmlspecialchars($row['hp'] ?? '-') ?></span></div></div>
                                                                <div class="col-6 col-md-3"><div class="guru-mini"><small>Lisensi Kepsek</small><span><?= $status_lisensi ?></span></div></div>
                                                            </div>

                                                            <ul class="nav nav-pills guru-tabs gap-1 mb-3" role="tablist">
                                                                <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#dd-<?= $row['id_guru'] ?>-profil" type="button" role="tab"><i class="bi bi-person-vcard me-1"></i>Profil</button></li>
                                                                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#dd-<?= $row['id_guru'] ?>-kontak" type="button" role="tab"><i class="bi bi-geo-alt me-1"></i>Kontak</button></li>
                                                                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#dd-<?= $row['id_guru'] ?>-pegawai" type="button" role="tab"><i class="bi bi-briefcase me-1"></i>Kepegawaian</button></li>
                                                                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#dd-<?= $row['id_guru'] ?>-bank" type="button" role="tab"><i class="bi bi-credit-card me-1"></i>Bank &amp; Pajak</button></li>
                                                            </ul>

                                                            <div class="tab-content">
                                                                <div class="tab-pane fade show active" id="dd-<?= $row['id_guru'] ?>-profil" role="tabpanel">
                                                                    <div class="guru-section mb-0">
                                                                        <dl class="row guru-dl mb-0">
                                                                            <dt class="col-sm-4">ID Guru</dt><dd class="col-sm-8"><?= (int)($row['id_guru'] ?? 0) ?></dd>
                                                                            <dt class="col-sm-4">Nama Lengkap</dt><dd class="col-sm-8"><?= htmlspecialchars($row['nama_guru'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-4">NUPTK / NIP</dt><dd class="col-sm-8"><?= htmlspecialchars($nuptk_nip) ?></dd>
                                                                            <dt class="col-sm-4">NIK / No. KK</dt><dd class="col-sm-8"><?= htmlspecialchars($row['nik'] ?? '-') . ' / ' . htmlspecialchars($row['no_kk'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-4">Jenis Kelamin</dt><dd class="col-sm-8"><?= $jk_jenis ?></dd>
                                                                            <dt class="col-sm-4">Tempat, Tgl Lahir</dt><dd class="col-sm-8"><?= htmlspecialchars($row['tempat_lahir'] ?? '-') . ', ' . $tgl_lahir_formatted ?></dd>
                                                                            <dt class="col-sm-4">Agama</dt><dd class="col-sm-8"><?= htmlspecialchars($row['agama'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-4">Status Kawin</dt><dd class="col-sm-8"><?= htmlspecialchars($row['status_perkawinan'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-4">Nama Suami/Istri</dt><dd class="col-sm-8"><?= htmlspecialchars($row['nama_suami_istri'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-4">Nama Ibu Kandung</dt><dd class="col-sm-8"><?= htmlspecialchars($row['nama_ibu_kandung'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-4">Kewarganegaraan</dt><dd class="col-sm-8 mb-0"><?= htmlspecialchars($row['kewarganegaraan'] ?? '-') ?></dd>
                                                                        </dl>
                                                                    </div>
                                                                </div>
                                                                <div class="tab-pane fade" id="dd-<?= $row['id_guru'] ?>-kontak" role="tabpanel">
                                                                    <div class="guru-section mb-0">
                                                                        <dl class="row guru-dl mb-0">
                                                                            <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= htmlspecialchars($row['email'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-4">No. HP</dt><dd class="col-sm-8"><?= htmlspecialchars($row['hp'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-4">Alamat</dt><dd class="col-sm-8"><?= htmlspecialchars($row['alamat'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-4">Desa/Kelurahan</dt><dd class="col-sm-8"><?= htmlspecialchars($row['desa_kelurahan'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-4">Kecamatan</dt><dd class="col-sm-8"><?= htmlspecialchars($row['kecamatan'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-4">Koordinat (Lintang, Bujur)</dt><dd class="col-sm-8 mb-0"><?= htmlspecialchars($row['lintang'] ?? '-') . ', ' . htmlspecialchars($row['bujur'] ?? '-') ?></dd>
                                                                        </dl>
                                                                    </div>
                                                                </div>
                                                                <div class="tab-pane fade" id="dd-<?= $row['id_guru'] ?>-pegawai" role="tabpanel">
                                                                    <div class="guru-section mb-0">
                                                                        <dl class="row guru-dl mb-0">
                                                                            <dt class="col-sm-5">Status Pegawai</dt><dd class="col-sm-7"><?= $status_badge ?></dd>
                                                                            <dt class="col-sm-5">Tugas Tambahan</dt><dd class="col-sm-7"><?= htmlspecialchars($row['tugas_tambahan'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-5">Pendidikan Terakhir</dt><dd class="col-sm-7"><?= htmlspecialchars($row['lulusan'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-5">Jurusan</dt><dd class="col-sm-7"><?= htmlspecialchars($row['jurusan'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-5">SK Pengangkatan</dt><dd class="col-sm-7"><?= htmlspecialchars($row['sk_pengangkatan'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-5">TMT Pengangkatan</dt><dd class="col-sm-7"><?= !empty($row['tmt_pengangkatan'] ?? '') ? date('d-m-Y', strtotime($row['tmt_pengangkatan'])) : '-' ?></dd>
                                                                            <dt class="col-sm-5">Lembaga Pengangkatan</dt><dd class="col-sm-7"><?= htmlspecialchars($row['lembaga_pengangkatan'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-5">Lisensi Kepsek</dt><dd class="col-sm-7 mb-0"><?= $status_lisensi ?></dd>
                                                                        </dl>
                                                                    </div>
                                                                </div>
                                                                <div class="tab-pane fade" id="dd-<?= $row['id_guru'] ?>-bank" role="tabpanel">
                                                                    <div class="guru-section mb-0">
                                                                        <dl class="row guru-dl mb-0">
                                                                            <dt class="col-sm-5">NPWP</dt><dd class="col-sm-7"><?= htmlspecialchars($row['npwp'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-5">Nama Wajib Pajak</dt><dd class="col-sm-7"><?= htmlspecialchars($row['nama_wajib_pajak'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-5">Bank</dt><dd class="col-sm-7"><?= htmlspecialchars($row['bank'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-5">No. Rekening</dt><dd class="col-sm-7"><?= htmlspecialchars($row['nomor_rekening_bank'] ?? '-') ?></dd>
                                                                            <dt class="col-sm-5">A.N. Rekening</dt><dd class="col-sm-7 mb-0"><?= htmlspecialchars($row['rekening_atas_nama'] ?? '-') ?></dd>
                                                                        </dl>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Tutup</button>
                                                            <button type="button" class="btn btn-success btn-ke-edit" data-edit-target="#editdata<?= $row['id_guru'] ?>"><i class="bi bi-pencil-square me-1"></i>Edit Data</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="modal fade guru-modal" id="editdata<?= $row['id_guru'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                                    <form class="modal-content" action="aksi/aksi_guru.php" method="post" enctype="multipart/form-data">
                                                        <div class="guru-head" style="background: linear-gradient(135deg, #198754, #20c997);">
                                                                <div class="guru-head-ic"><i class="bi bi-pencil-square"></i></div>
                                                                <div class="flex-grow-1">
                                                                    <h5>Edit Data Guru</h5>
                                                                    <small><?= htmlspecialchars($row['nama_guru'] ?? '-') ?></small>
                                                                </div>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="aksi" value="edit">
                                                                <input type="hidden" name="id_guru" value="<?= (int)$row['id_guru'] ?>">
                                                                <input type="hidden" name="foto_lama" value="<?= htmlspecialchars($row['foto'] ?? '') ?>">

                                                                <div class="guru-section">
                                                                    <div class="guru-section-title"><span class="guru-sec-ic" style="background:#e7f1ff;color:#0d6efd"><i class="bi bi-person-vcard"></i></span> Data Pribadi</div>
                                                                    <div class="row">
                                                                        <div class="col-md-12 mb-3"><label class="form-label">Nama Lengkap <span class="text-danger">*</span></label><input type="text" class="form-control" name="nama_guru" value="<?= htmlspecialchars($row['nama_guru'] ?? '') ?>" required></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">NUPTK</label><input type="text" class="form-control" name="nuptk" value="<?= htmlspecialchars($row['nuptk'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">NIP</label><input type="text" class="form-control" name="nip" value="<?= htmlspecialchars($row['nip'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">NIK</label><input type="text" class="form-control" name="nik" value="<?= htmlspecialchars($row['nik'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">No. KK</label><input type="text" class="form-control" name="no_kk" value="<?= htmlspecialchars($row['no_kk'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Tempat Lahir</label><input type="text" class="form-control" name="tempat_lahir" value="<?= htmlspecialchars($row['tempat_lahir'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Tanggal Lahir</label><input type="date" class="form-control" name="tanggal_lahir" value="<?= htmlspecialchars($row['tanggal_lahir'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                                                                            <select class="form-control" name="jk" required>
                                                                                <option value="L" <?= (($row['jk'] ?? '') == 'L') ? 'selected' : '' ?>>Laki-laki</option>
                                                                                <option value="P" <?= (($row['jk'] ?? '') == 'P') ? 'selected' : '' ?>>Perempuan</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Agama</label>
                                                                            <select class="form-control" name="agama">
                                                                                <option value="">-- Pilih Agama --</option>
                                                                                <?php foreach ($agama_options as $opt) { ?>
                                                                                    <option value="<?= $opt ?>" <?= (($row['agama'] ?? '') == $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                                                                                <?php } ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-md-12 mb-3"><label class="form-label">Nama Ibu Kandung</label><input type="text" class="form-control" name="nama_ibu_kandung" value="<?= htmlspecialchars($row['nama_ibu_kandung'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Status Kawin</label>
                                                                            <select class="form-control" name="status_perkawinan">
                                                                                <option value="">-- Pilih Status --</option>
                                                                                <?php foreach ($status_kawin_options as $opt) { ?>
                                                                                    <option value="<?= $opt ?>" <?= (($row['status_perkawinan'] ?? '') == $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                                                                                <?php } ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Nama Suami/Istri</label><input type="text" class="form-control" name="nama_suami_istri" value="<?= htmlspecialchars($row['nama_suami_istri'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Kewarganegaraan</label>
                                                                            <select class="form-control" name="kewarganegaraan">
                                                                                <option value="">-- Pilih --</option>
                                                                                <?php foreach ($kewarganegaraan_options as $opt) { ?>
                                                                                    <option value="<?= $opt ?>" <?= (($row['kewarganegaraan'] ?? '') == $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                                                                                <?php } ?>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="guru-section">
                                                                    <div class="guru-section-title"><span class="guru-sec-ic" style="background:#e8f7ee;color:#198754"><i class="bi bi-geo-alt"></i></span> Kontak &amp; Alamat</div>
                                                                    <div class="row">
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($row['email'] ?? '') ?>" required></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">No. HP</label><input type="text" class="form-control" name="hp" value="<?= htmlspecialchars($row['hp'] ?? '') ?>"></div>
                                                                        <div class="col-md-12 mb-3"><label class="form-label">Alamat</label><textarea class="form-control" name="alamat" rows="2"><?= htmlspecialchars($row['alamat'] ?? '') ?></textarea></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Desa/Kelurahan</label><input type="text" class="form-control" name="desa_kelurahan" value="<?= htmlspecialchars($row['desa_kelurahan'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Kecamatan</label><input type="text" class="form-control" name="kecamatan" value="<?= htmlspecialchars($row['kecamatan'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Latitude</label><input type="text" class="form-control" name="lintang" value="<?= htmlspecialchars($row['lintang'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Longitude</label><input type="text" class="form-control" name="bujur" value="<?= htmlspecialchars($row['bujur'] ?? '') ?>"></div>
                                                                    </div>
                                                                </div>

                                                                <div class="guru-section">
                                                                    <div class="guru-section-title"><span class="guru-sec-ic" style="background:#fff4e5;color:#fd7e14"><i class="bi bi-briefcase"></i></span> Data Kepegawaian</div>
                                                                    <div class="row">
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Status Kepegawaian</label>
                                                                            <select class="form-control" name="status_kepegawaian">
                                                                                <option value="">-- Pilih Status --</option>
                                                                                <?php foreach ($status_pegawai_options as $opt) { ?>
                                                                                    <option value="<?= $opt ?>" <?= (($row['status_kepegawaian'] ?? '') == $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                                                                                <?php } ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Tugas Tambahan</label>
                                                                            <select class="form-control" name="tugas_tambahan">
                                                                                <option value="">-- Pilih Tugas --</option>
                                                                                <?php foreach ($tugas_tambahan_options as $opt) { ?>
                                                                                    <option value="<?= $opt ?>" <?= (($row['tugas_tambahan'] ?? '') == $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                                                                                <?php } ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Pendidikan Terakhir (Lulusan)</label>
                                                                            <select class="form-control" name="lulusan">
                                                                                <option value="">-- Pilih Lulusan --</option>
                                                                                <?php foreach ($lulusan_options as $opt) { ?>
                                                                                    <option value="<?= $opt ?>" <?= (($row['lulusan'] ?? '') == $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                                                                                <?php } ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Jurusan</label><input type="text" class="form-control" name="jurusan" value="<?= htmlspecialchars($row['jurusan'] ?? '') ?>"></div>
                                                                        <div class="col-md-12 mb-3"><label class="form-label">SK Pengangkatan</label><input type="text" class="form-control" name="sk_pengangkatan" value="<?= htmlspecialchars($row['sk_pengangkatan'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">TMT Pengangkatan</label><input type="date" class="form-control" name="tmt_pengangkatan" value="<?= htmlspecialchars($row['tmt_pengangkatan'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Lembaga Pengangkatan</label><input type="text" class="form-control" name="lembaga_pengangkatan" value="<?= htmlspecialchars($row['lembaga_pengangkatan'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Lisensi Kepala Sekolah</label>
                                                                            <select class="form-control" name="sudah_lisensi_kepala_sekolah">
                                                                                <option value="0" <?= (($row['sudah_lisensi_kepala_sekolah'] ?? 0) == 0) ? 'selected' : '' ?>>Belum</option>
                                                                                <option value="1" <?= (($row['sudah_lisensi_kepala_sekolah'] ?? 0) == 1) ? 'selected' : '' ?>>Sudah</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="guru-section">
                                                                    <div class="guru-section-title"><span class="guru-sec-ic" style="background:#f3e8ff;color:#7c3aed"><i class="bi bi-credit-card"></i></span> Data Bank &amp; Pajak</div>
                                                                    <div class="row">
                                                                        <div class="col-md-6 mb-3"><label class="form-label">NPWP</label><input type="text" class="form-control" name="npwp" value="<?= htmlspecialchars($row['npwp'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Nama Wajib Pajak</label><input type="text" class="form-control" name="nama_wajib_pajak" value="<?= htmlspecialchars($row['nama_wajib_pajak'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Bank</label><input type="text" class="form-control" name="bank" value="<?= htmlspecialchars($row['bank'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">Nomor Rekening</label><input type="text" class="form-control" name="nomor_rekening_bank" value="<?= htmlspecialchars($row['nomor_rekening_bank'] ?? '') ?>"></div>
                                                                        <div class="col-md-6 mb-3"><label class="form-label">A.N. Rekening</label><input type="text" class="form-control" name="rekening_atas_nama" value="<?= htmlspecialchars($row['rekening_atas_nama'] ?? '') ?>"></div>
                                                                    </div>
                                                                </div>

                                                                <div class="guru-section">
                                                                    <div class="guru-section-title"><span class="guru-sec-ic" style="background:#fde8e8;color:#dc3545"><i class="bi bi-image"></i></span> Foto Guru</div>
                                                                    <div class="d-flex align-items-center gap-3">
                                                                        <img id="preview_foto_edit_<?= $row['id_guru'] ?>" src="<?= htmlspecialchars($foto_path) ?>" alt="Foto" class="rounded border" style="width:72px;height:72px;object-fit:cover;">
                                                                        <div class="flex-grow-1">
                                                                            <input type="file" class="form-control" name="foto" accept="image/*" onchange="previewGuruFoto(this,'preview_foto_edit_<?= $row['id_guru'] ?>')">
                                                                            <small class="form-text text-muted">Biarkan kosong jika tidak ingin mengubah. File saat ini: <b><?= htmlspecialchars($foto_file) ?></b></small>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Tutup</button>
                                                                <button type="submit" class="btn btn-success"><i class="bi bi-save me-1"></i>Simpan Perubahan</button>
                                                            </div>
                                                    </form>
                                                </div>
                                            </div>
                                            <?php
                                            $modals_html .= ob_get_clean();
                                        } // End while loop 
                                        ?>
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

<?= $modals_html ?>

<div class="modal fade guru-modal" id="tambahGuru" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" action="aksi/aksi_guru.php" method="post" enctype="multipart/form-data">
                <div class="guru-head" style="background: linear-gradient(135deg, #0d6efd, #6610f2);">
                    <div class="guru-head-ic"><i class="bi bi-person-plus-fill"></i></div>
                    <div class="flex-grow-1">
                        <h5>Tambah Data Guru Baru</h5>
                        <small>Lengkapi setiap bagian. Kolom bertanda <b>*</b> wajib diisi.</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="aksi" value="tambah">

                    <div class="guru-section">
                        <div class="guru-section-title"><span class="guru-sec-ic" style="background:#e7f1ff;color:#0d6efd"><i class="bi bi-person-vcard"></i></span> Data Pribadi</div>
                        <div class="row">
                            <div class="col-md-12 mb-3"><label class="form-label">Nama Lengkap <span class="text-danger">*</span></label><input type="text" class="form-control" name="nama_guru" placeholder="Contoh: I Gusti Ngurah Dwi Aryastana" required></div>
                            <div class="col-md-6 mb-3"><label class="form-label">NUPTK</label><input type="text" class="form-control" name="nuptk" placeholder="Opsional"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">NIP</label><input type="text" class="form-control" name="nip" placeholder="Opsional"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">NIK</label><input type="text" class="form-control" name="nik"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">No. KK</label><input type="text" class="form-control" name="no_kk"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Tempat Lahir</label><input type="text" class="form-control" name="tempat_lahir"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Tanggal Lahir</label><input type="date" class="form-control" name="tanggal_lahir"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                                <select class="form-control" name="jk" required>
                                    <option value="">-- Pilih --</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3"><label class="form-label">Agama</label>
                                <select class="form-control" name="agama">
                                    <option value="">-- Pilih Agama --</option>
                                    <?php foreach ($agama_options as $opt) { ?>
                                        <option value="<?= $opt ?>"><?= $opt ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3"><label class="form-label">Nama Ibu Kandung</label><input type="text" class="form-control" name="nama_ibu_kandung"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Status Kawin</label>
                                <select class="form-control" name="status_perkawinan">
                                    <option value="">-- Pilih Status --</option>
                                    <?php foreach ($status_kawin_options as $opt) { ?>
                                        <option value="<?= $opt ?>"><?= $opt ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3"><label class="form-label">Nama Suami/Istri</label><input type="text" class="form-control" name="nama_suami_istri"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Kewarganegaraan</label>
                                <select class="form-control" name="kewarganegaraan">
                                    <option value="">-- Pilih --</option>
                                    <?php foreach ($kewarganegaraan_options as $opt) { ?>
                                        <option value="<?= $opt ?>"><?= $opt ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="guru-section">
                        <div class="guru-section-title"><span class="guru-sec-ic" style="background:#e8f7ee;color:#198754"><i class="bi bi-geo-alt"></i></span> Kontak &amp; Alamat</div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control" name="email" placeholder="nama@sekolah.sch.id" required></div>
                            <div class="col-md-6 mb-3"><label class="form-label">No. HP</label><input type="text" class="form-control" name="hp"></div>
                            <div class="col-md-12 mb-3"><label class="form-label">Alamat</label><textarea class="form-control" name="alamat" rows="2"></textarea></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Desa/Kelurahan</label><input type="text" class="form-control" name="desa_kelurahan"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Kecamatan</label><input type="text" class="form-control" name="kecamatan"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Latitude</label><input type="text" class="form-control" name="lintang"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Longitude</label><input type="text" class="form-control" name="bujur"></div>
                        </div>
                    </div>

                    <div class="guru-section">
                        <div class="guru-section-title"><span class="guru-sec-ic" style="background:#fff4e5;color:#fd7e14"><i class="bi bi-briefcase"></i></span> Data Kepegawaian</div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Status Kepegawaian</label>
                                <select class="form-control" name="status_kepegawaian">
                                    <option value="">-- Pilih Status --</option>
                                    <?php foreach ($status_pegawai_options as $opt) { ?>
                                        <option value="<?= $opt ?>"><?= $opt ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3"><label class="form-label">Tugas Tambahan</label>
                                <select class="form-control" name="tugas_tambahan">
                                    <option value="">-- Pilih Tugas --</option>
                                    <?php foreach ($tugas_tambahan_options as $opt) { ?>
                                        <option value="<?= $opt ?>"><?= $opt ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3"><label class="form-label">Pendidikan Terakhir (Lulusan)</label>
                                <select class="form-control" name="lulusan">
                                    <option value="">-- Pilih Lulusan --</option>
                                    <?php foreach ($lulusan_options as $opt) { ?>
                                        <option value="<?= $opt ?>"><?= $opt ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3"><label class="form-label">Jurusan</label><input type="text" class="form-control" name="jurusan"></div>
                            <div class="col-md-12 mb-3"><label class="form-label">SK Pengangkatan</label><input type="text" class="form-control" name="sk_pengangkatan"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">TMT Pengangkatan</label><input type="date" class="form-control" name="tmt_pengangkatan"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Lembaga Pengangkatan</label><input type="text" class="form-control" name="lembaga_pengangkatan"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Lisensi Kepala Sekolah</label>
                                <select class="form-control" name="sudah_lisensi_kepala_sekolah">
                                    <option value="0" selected>Belum</option>
                                    <option value="1">Sudah</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="guru-section">
                        <div class="guru-section-title"><span class="guru-sec-ic" style="background:#f3e8ff;color:#7c3aed"><i class="bi bi-credit-card"></i></span> Data Bank &amp; Pajak</div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">NPWP</label><input type="text" class="form-control" name="npwp"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Nama Wajib Pajak</label><input type="text" class="form-control" name="nama_wajib_pajak"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Bank</label><input type="text" class="form-control" name="bank"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Nomor Rekening</label><input type="text" class="form-control" name="nomor_rekening_bank"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">A.N. Rekening</label><input type="text" class="form-control" name="rekening_atas_nama"></div>
                        </div>
                    </div>

                    <div class="guru-section">
                        <div class="guru-section-title"><span class="guru-sec-ic" style="background:#fde8e8;color:#dc3545"><i class="bi bi-image"></i></span> Foto Guru</div>
                        <div class="d-flex align-items-center gap-3">
                            <img id="preview_foto_tambah" src="assets/img/pria.png" alt="Pratinjau" class="rounded border" style="width:72px;height:72px;object-fit:cover;">
                            <div class="flex-grow-1">
                                <input type="file" class="form-control" name="foto" accept="image/*" onchange="previewGuruFoto(this,'preview_foto_tambah')">
                                <small class="form-text text-muted">Format JPG/PNG, maksimal 2MB. Pratinjau muncul otomatis.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Data Guru</button>
                </div>
        </form>
    </div>
</div>

<div class="modal fade guru-modal" id="importGuruModal" tabindex="-1" aria-labelledby="importGuruModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="aksi/import_guru.php" method="post" enctype="multipart/form-data">
                <div class="guru-head" style="background: linear-gradient(135deg, #20c997, #198754);">
                    <div class="guru-head-ic"><i class="bi bi-cloud-arrow-up-fill"></i></div>
                    <div class="flex-grow-1">
                        <h5 id="importGuruModalLabel">Import Data Guru dari Excel</h5>
                        <small>Gunakan template resmi agar kolom terbaca dengan benar.</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info d-flex align-items-start gap-2 mb-3">
                        <i class="bi bi-info-circle-fill mt-1"></i>
                        <div>Unduh <b>Format Excel</b> terlebih dahulu, isi datanya, lalu unggah di sini. Baris dengan <b>NIP/NUPTK</b> yang sama akan dilewati otomatis.</div>
                    </div>
                    <div class="mb-2">
                        <label for="fileExcel" class="form-label">Pilih File Excel (.xlsx)</label>
                        <input type="file" class="form-control" id="fileExcel" name="fileExcel" accept=".xlsx,.xls" required>
                    </div>
                    <a href="laporan/format_data_guru.xlsx" target="_blank" class="small text-decoration-none"><i class="bi bi-download me-1"></i>Download Format Excel</a>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="import" class="btn btn-success"><i class="bi bi-upload me-1"></i>Import Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Pratinjau foto guru sebelum diunggah
    function previewGuruFoto(input, targetId) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var img = document.getElementById(targetId);
                if (img) img.src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Buka modal Edit dari modal Detail tanpa konflik stacking
    document.addEventListener('click', function(e) {
        var b = e.target.closest('.btn-ke-edit');
        if (!b) return;
        e.preventDefault();
        var target = document.querySelector(b.getAttribute('data-edit-target'));
        if (!target) return;
        var detailEl = b.closest('.modal');
        if (detailEl && typeof bootstrap !== 'undefined') {
            var inst = bootstrap.Modal.getInstance(detailEl);
            if (inst) {
                detailEl.addEventListener('hidden.bs.modal', function() {
                    bootstrap.Modal.getOrCreateInstance(target).show();
                }, { once: true });
                inst.hide();
                return;
            }
        }
        if (typeof bootstrap !== 'undefined') bootstrap.Modal.getOrCreateInstance(target).show();
    });

    // Delegasi hapus guru (satu listener untuk semua baris)
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-hapus-guru');
        if (!btn) return;
        e.preventDefault();
        var id = btn.getAttribute('data-id');
        var nama = btn.getAttribute('data-nama') || '';

        if (typeof Swal === 'undefined') {
            if (confirm('Yakin ingin menghapus data guru ' + nama + '?')) {
                window.location.href = 'aksi/aksi_guru.php?aksi=hapus&id_guru=' + encodeURIComponent(id);
            }
            return;
        }

        Swal.fire({
            title: 'Yakin ingin menghapus data guru?',
            text: 'Data guru ' + nama + ' akan dihapus permanen!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d'
        }).then(function(result) {
            if (result.isConfirmed) {
                window.location.href = 'aksi/aksi_guru.php?aksi=hapus&id_guru=' + encodeURIComponent(id);
            }
        });
    });
</script>
