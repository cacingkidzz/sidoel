<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
</div>
<?php
include("koneksi.php");

// Ambil data sekolah
$sql = $koneksi->query("SELECT * FROM dt_data_sekolah LIMIT 1");
$sekolah = $sql->fetch_assoc();

// Tahun ajaran aktif untuk statistik kelas
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
$id_ta_aktif = 0;
if (!$koneksi->connect_error && $nama_ta_sesi !== '') {
    $q_ta = $koneksi->prepare("SELECT id_tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    if ($q_ta) {
        $q_ta->bind_param("s", $nama_ta_sesi);
        $q_ta->execute();
        $r_ta = $q_ta->get_result();
        if ($r_ta->num_rows > 0) {
            $id_ta_aktif = $r_ta->fetch_assoc()['id_tahun_ajaran'];
        }
        $q_ta->close();
    }
}

// Statistik ringkas
$jml_siswa = (int)($koneksi->query("SELECT COUNT(*) AS c FROM dt_siswa")->fetch_assoc()['c'] ?? 0);
$jml_guru = (int)($koneksi->query("SELECT COUNT(*) AS c FROM dt_guru")->fetch_assoc()['c'] ?? 0);
$jml_jurusan = (int)($koneksi->query("SELECT COUNT(*) AS c FROM dt_jurusan")->fetch_assoc()['c'] ?? 0);
$jml_kelas = 0;
if ($id_ta_aktif > 0) {
    $q_k = $koneksi->query("SELECT COUNT(*) AS c FROM dt_kelas WHERE id_tahun_ajaran = '$id_ta_aktif'");
    $jml_kelas = (int)($q_k->fetch_assoc()['c'] ?? 0);
}

// Siapkan tautan kontak
$telp_display = $sekolah['telp_sekolah'] ?? '';
$telp_href = preg_replace('/[^0-9+]/', '', $telp_display);
$web = trim($sekolah['web'] ?? '');
$web_href = $web;
if ($web !== '' && !preg_match('#^https?://#i', $web)) {
    $web_href = 'https://' . $web;
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Profil Sekolah</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Data Sekolah</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">

            <?php if (($_GET['pesan'] ?? '') === 'edit'): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill me-1"></i> Data sekolah berhasil diperbarui.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif (($_GET['pesan'] ?? '') === 'error'): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Gagal menyimpan data. Silakan coba lagi.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- ===================== HERO IDENTITAS ===================== -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card shadow-sm border-0 overflow-hidden">
                        <div class="card-body p-0">
                            <div class="row g-0">
                                <div class="col-md-4 col-lg-3 d-flex align-items-center justify-content-center bg-primary text-white p-4" style="background: linear-gradient(135deg, #2563eb, #1e40af);">
                                    <div class="text-center">
                                        <?php if (!empty($sekolah['logo'])): ?>
                                            <img src="assets/img/logo/<?= htmlspecialchars($sekolah['logo']); ?>" alt="Logo Sekolah"
                                                class="img-fluid bg-white rounded-3 p-2 shadow-sm" style="max-height: 130px;">
                                        <?php else: ?>
                                            <div class="bg-white bg-opacity-25 rounded-3 d-flex align-items-center justify-content-center mx-auto" style="width: 130px; height: 130px;">
                                                <i class="bi bi-building" style="font-size: 3.5rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-8 col-lg-9 p-4">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <h3 class="mb-0 fw-bold"><?= htmlspecialchars($sekolah['nama_sekolah']); ?></h3>
                                    </div>
                                    <div class="mb-3">
                                        <span class="badge bg-primary">NPSN: <?= htmlspecialchars($sekolah['NPSN'] ?: '-'); ?></span>
                                        <?php if (!empty($sekolah['logo'])): ?>
                                            <span class="badge bg-success"><i class="bi bi-image me-1"></i>Logo tersedia</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="bi bi-image me-1"></i>Tanpa logo</span>
                                        <?php endif; ?>
                                        <?php if (!empty($sekolah['lokasi'])): ?>
                                            <span class="badge bg-info text-dark"><i class="bi bi-geo-alt me-1"></i>Peta tersemat</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="bi bi-geo-alt me-1"></i>Peta kosong</span>
                                        <?php endif; ?>
                                        <?php if (!empty($sekolah['token'])): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-key me-1"></i>Token API aktif</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="row">
                                        <div class="col-lg-6 mb-2">
                                            <div class="d-flex text-muted small">
                                                <i class="bi bi-geo-alt-fill text-primary me-2 mt-1"></i>
                                                <span><?= nl2br(htmlspecialchars($sekolah['alamat_sekolah'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php if ($telp_display !== ''): ?>
                                                    <a href="tel:<?= htmlspecialchars($telp_href); ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-telephone-fill me-1"></i><?= htmlspecialchars($telp_display); ?>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($sekolah['email'])): ?>
                                                    <a href="mailto:<?= htmlspecialchars($sekolah['email']); ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-envelope-fill me-1"></i>Email
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($web !== ''): ?>
                                                    <a href="<?= htmlspecialchars($web_href); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-globe2 me-1"></i>Website
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($sekolah['email'])): ?>
                                                <div class="text-muted small mt-2"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($sekolah['email']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($web !== ''): ?>
                                                <div class="text-muted small"><i class="bi bi-globe me-1"></i><?= htmlspecialchars($web); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===================== STATISTIK ===================== -->
            <div class="row mb-3">
                <div class="col-md-6 col-xl-3 mb-3 mb-xl-0">
                    <div class="info-box bg-primary text-white shadow-sm h-100">
                        <span class="info-box-icon"><i class="bi bi-people-fill"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Siswa</span>
                            <span class="info-box-number h4"><?= number_format($jml_siswa, 0, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3 mb-3 mb-xl-0">
                    <div class="info-box bg-success text-white shadow-sm h-100">
                        <span class="info-box-icon"><i class="bi bi-house-door-fill"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Kelas Aktif</span>
                            <span class="info-box-number h4"><?= number_format($jml_kelas, 0, ',', '.'); ?></span>
                            <span class="info-box-text small"><?= htmlspecialchars($nama_ta_sesi ?: '-'); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3 mb-3 mb-xl-0">
                    <div class="info-box bg-info text-white shadow-sm h-100">
                        <span class="info-box-icon"><i class="bi bi-person-workspace"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Guru &amp; Staf</span>
                            <span class="info-box-number h4"><?= number_format($jml_guru, 0, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="info-box bg-warning text-white shadow-sm h-100">
                        <span class="info-box-icon"><i class="bi bi-award-fill"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Jurusan</span>
                            <span class="info-box-number h4"><?= number_format($jml_jurusan, 0, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===================== FORM EDIT ===================== -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bi bi-building me-2"></i> Pengaturan Data Sekolah
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="aksi/aksi_sekolah.php" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="aksi" value="update">
                                <input type="hidden" name="id_sekolah" value="<?= $sekolah['id_sekolah']; ?>">

                                <ul class="nav nav-tabs mb-3" id="tabSekolah" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="tab-profil-tab" data-bs-toggle="tab" data-bs-target="#tab-profil" type="button" role="tab">
                                            <i class="bi bi-info-circle-fill me-1"></i> Profil &amp; Kontak
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="tab-lokasi-tab" data-bs-toggle="tab" data-bs-target="#tab-lokasi" type="button" role="tab">
                                            <i class="bi bi-geo-alt-fill me-1"></i> Lokasi &amp; Logo
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="tab-api-tab" data-bs-toggle="tab" data-bs-target="#tab-api" type="button" role="tab">
                                            <i class="bi bi-key-fill me-1"></i> API &amp; Integrasi
                                        </button>
                                    </li>
                                </ul>

                                <div class="tab-content" id="tabSekolahContent">
                                    <!-- TAB 1: PROFIL & KONTAK -->
                                    <div class="tab-pane fade show active" id="tab-profil" role="tabpanel">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="mb-3 text-primary"><i class="bi bi-info-circle-fill me-1"></i> Informasi Dasar</h6>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Nama Sekolah <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="nama_sekolah" value="<?= htmlspecialchars($sekolah['nama_sekolah']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Alamat <span class="text-danger">*</span></label>
                                                    <textarea class="form-control" name="alamat_sekolah" rows="3" required><?= htmlspecialchars($sekolah['alamat_sekolah']); ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">NPSN</label>
                                                    <input type="text" class="form-control" name="NPSN" value="<?= htmlspecialchars($sekolah['NPSN']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="mb-3 text-primary"><i class="bi bi-gear-fill me-1"></i> Kontak &amp; Keuangan</h6>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Telepon</label>
                                                    <input type="text" class="form-control" name="telp_sekolah" value="<?= htmlspecialchars($sekolah['telp_sekolah']); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Email</label>
                                                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($sekolah['email']); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Website</label>
                                                    <input type="url" class="form-control" name="web" value="<?= htmlspecialchars($sekolah['web']); ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Rekening Sekolah</label>
                                                    <input type="text" class="form-control" name="rek_sekolah" value="<?= htmlspecialchars($sekolah['rek_sekolah']); ?>">
                                                    <small class="text-muted"><i class="bi bi-credit-card"></i> Digunakan untuk informasi pembayaran.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TAB 2: LOKASI & LOGO -->
                                    <div class="tab-pane fade" id="tab-lokasi" role="tabpanel">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="mb-3 text-primary"><i class="bi bi-geo-alt-fill me-1"></i> Kode Embed Lokasi</h6>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Kode Embed Google Maps</label>
                                                    <textarea class="form-control" name="lokasi" rows="8"><?= htmlspecialchars($sekolah['lokasi']); ?></textarea>
                                                    <small class="text-muted"><i class="bi bi-lightbulb-fill"></i> Tempelkan kode <code>&lt;iframe&gt;</code> dari Google Maps di sini.</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="mb-3 text-primary"><i class="bi bi-image-fill me-1"></i> Pengaturan Logo</h6>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Unggah Logo Baru</label>
                                                    <input type="file" class="form-control" name="logo" id="inputLogo" accept="image/*">
                                                    <small class="text-muted">Kosongkan jika tidak ingin mengubah logo.</small>
                                                </div>
                                                <label class="form-label fw-bold">Pratinjau Logo</label>
                                                <div class="p-3 border rounded text-center bg-light">
                                                    <img id="previewLogo" src="<?= !empty($sekolah['logo']) ? 'assets/img/logo/' . htmlspecialchars($sekolah['logo']) : ''; ?>"
                                                        alt="Logo Sekolah" class="img-fluid <?= empty($sekolah['logo']) ? 'd-none' : ''; ?>" style="max-height: 90px;">
                                                    <span id="previewLogoEmpty" class="text-muted <?= !empty($sekolah['logo']) ? 'd-none' : ''; ?>">Logo belum diunggah.</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TAB 3: API & INTEGRASI -->
                                    <div class="tab-pane fade" id="tab-api" role="tabpanel">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="mb-3 text-primary"><i class="bi bi-key-fill me-1"></i> Token &amp; Endpoint</h6>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Token</label>
                                                    <input type="text" class="form-control" name="token" value="<?= htmlspecialchars($sekolah['token']); ?>">
                                                    <small class="text-muted"><i class="bi bi-key-fill"></i> Kunci rahasia untuk integrasi atau API (mis. WhatsApp gateway).</small>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Domain API</label>
                                                    <input type="text" class="form-control" name="domain" value="<?= htmlspecialchars($sekolah['domain']); ?>">
                                                    <small class="text-muted"><i class="bi bi-hdd-network-fill"></i> Endpoint layanan pesan, mis. <code>https://api.fonnte.com/send</code>.</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="alert alert-secondary mb-0">
                                                    <h6 class="fw-bold"><i class="bi bi-shield-lock-fill me-1"></i> Catatan Keamanan</h6>
                                                    <ul class="mb-0 small ps-3">
                                                        <li>Token bersifat rahasia — jangan dibagikan ke pihak luar.</li>
                                                        <li>Perubahan token dapat memengaruhi fitur notifikasi yang bergantung padanya.</li>
                                                        <li>Domain API hanya perlu diubah bila berpindah penyedia layanan.</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-end pt-4 border-top mt-4">
                                    <button type="submit" class="btn btn-lg btn-primary">
                                        <i class="bi bi-save-fill me-2"></i> Simpan Semua Perubahan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===================== PRATINJAU PETA ===================== -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card card-outline card-info">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bi bi-map-fill me-2"></i> Pratinjau Peta Lokasi
                            </h5>
                            <div class="card-tools">
                                <?php if (!empty($sekolah['lokasi'])): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Terpasang</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Belum diatur</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Pratinjau visual dari kode Embed Lokasi yang dimasukkan pada tab <b>Lokasi &amp; Logo</b>.</p>
                            <div class="embed-responsive border rounded shadow-sm" style="min-height: 350px;">
                                <?php if (!empty($sekolah['lokasi'])): ?>
                                    <div style="width: 100%; height: 350px;">
                                        <?= str_replace('<iframe', '<iframe width="100%" height="350px" style="border:0;"', $sekolah['lokasi']); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="p-5 text-center bg-light" style="height: 350px; display: flex; flex-direction: column; justify-content: center;">
                                        <i class="bi bi-map fs-1 text-secondary"></i>
                                        <p class="mt-2 text-secondary">Lokasi Google Maps belum disematkan. Masukkan kode embed di kolom Lokasi dan klik Simpan.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    // Pratinjau logo sebelum diunggah
    document.getElementById('inputLogo')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        const img = document.getElementById('previewLogo');
        const empty = document.getElementById('previewLogoEmpty');
        if (!file || !img) return;
        const reader = new FileReader();
        reader.onload = function(ev) {
            img.src = ev.target.result;
            img.classList.remove('d-none');
            if (empty) empty.classList.add('d-none');
        };
        reader.readAsDataURL(file);
    });
</script>