<?php

// Pastikan sesi sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('koneksi.php');

$username = $_SESSION['username'] ?? '';
$level =  $_SESSION['level_user'] ?? '';
// ==========================================================
// KODE PENCEGAHAN AKSES SEBELUM LOGIN (Wajib di Awal File)
// ==========================================================
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Jika user belum login, set pesan peringatan dan arahkan ke login.php
    $_SESSION['alert'] = ['type' => 'warning', 'msg' => 'Anda harus <b>Login</b> terlebih dahulu untuk mengakses halaman ini.'];
    header("Location: login.php");
    exit; // Wajib menghentikan eksekusi script setelah redirect
}
// ==========================================================

// ==========================================================
// Data User (diambil dari session)
// ==========================================================
$level_user = $_SESSION['level_user'] ?? 'Admin';
$user_display = strtoupper($level_user);
$tahun_aktif_sesi = $_SESSION['tahun_ajaran_aktif'] ?? 'N/A';

// ==========================================================
// Ambil semua tahun ajaran dari database
// ==========================================================
$tahun_ajar_list = [];
if (isset($koneksi)) {
    $q_ta_all = $koneksi->query("SELECT tahun_ajaran, status FROM dt_tahun_ajar ORDER BY tahun_ajaran DESC");
    if ($q_ta_all) {
        while ($row = $q_ta_all->fetch_assoc()) {
            $tahun_ajar_list[] = $row;
        }
    }
}

$qsek = $koneksi->query("SELECT nama_sekolah,logo FROM dt_data_sekolah");
$dsek = $qsek->fetch_assoc();

$judul = $dsek['nama_sekolah'];


// Tentukan menu yang aktif dari parameter 'menu' di URL
$activeMenu = $_GET['menu'] ?? 'beranda';

// --- KONTROL AKSES MENU (berbasis level user) ---
require_once('akses.php');
if (!boleh_menu($level_user, $activeMenu)) {
    header("Location: index.php?menu=beranda&pesan=tolak");
    exit;
}

// --- LOGIKA GRUP MENU (Dropdown) ---

// Grup Data Referensi
$dataReferensiMenus = ['data_sekolah', 'kop_sekolah', 'tahun_ajar', 'user', 'jurusan', 'mapel', 'kelas'];
$isDataReferensiActive = in_array($activeMenu, $dataReferensiMenus);

// Grup Penerimaan Siswa Baru (PPDB) (BARU)
$ppdbMenus = ['tarik_siswa_ppdb'];
$isPPDBActive = in_array($activeMenu, $ppdbMenus);

// Grup Data Siswa
$siswaMenus = ['siswa_aktif', 'penempatan_kelas', 'kenaikan_kelas', 'denah_siswa', 'alumni', 'foto_siswa'];
$isSiswaActive = in_array($activeMenu, $siswaMenus);

// Grup Pendidikan & KBM (dipecah menjadi sub-grup agar sidebar rapi)
$kbmReferensiMenus = ['guru_mapel', 'pengampu_mapel', 'wali_kelas', 'sekretaris_kelas', 'info_kelas'];
$isKbmReferensiActive = in_array($activeMenu, $kbmReferensiMenus);

$kbmPembelajaranMenus = ['jadwal', 'jurnal_kelas', 'kalender_akademik'];
$isKbmPembelajaranActive = in_array($activeMenu, $kbmPembelajaranMenus);

$kbmRekapMenus = ['dashboard_wali_kelas', 'rekap_absensi', 'rekap_jurnal'];
$isKbmRekapActive = in_array($activeMenu, $kbmRekapMenus);

$kbmMenus = array_merge($kbmReferensiMenus, $kbmPembelajaranMenus, $kbmRekapMenus);
$isKBMActive = in_array($activeMenu, $kbmMenus);

// Grup Laporan & Cetak
$laporanMenus = ['kartu_siswa'];
$isLaporanActive = in_array($activeMenu, $laporanMenus);

// Grup Administrasi Sistem
$administrasiMenus = ['audit_log', 'backup_restore', 'manajemen_ta'];
$isAdministrasiActive = in_array($activeMenu, $administrasiMenus);




// CATATAN:
// - Saya perbaiki link 'tahun_ajaran' menjadi 'tahun_ajar' agar sesuai konten.php sebelumnya.
// - Saya perbaiki link 'data_guru' menjadi 'guru' agar sesuai konten.php sebelumnya.
?>
<!doctype html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>SiDoel | Sistem Data Online Sekolah</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
    <meta name="title" content="SiDoel | Sistem Data Online Sekolah" />

    <link rel="icon" href="assets/img/logo/<?= $dsek['logo'] ?>" type="image/x-icon">
    <meta name="supported-color-schemes" content="light dark" />
    <link rel="preload" href="assets/css/adminlte.css" as="style" />
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css"
        integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q="
        crossorigin="anonymous"
        media="print"
        onload="this.media='all'" />


    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css"
        crossorigin="anonymous" />
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.css" />

    <link rel="stylesheet" href="assets/css/adminlte.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />


    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    <style>
        @media screen {
            body {
                background-image: url('assets/img/bg-content.jpg') !important;
            }
        }

        /* Gaya untuk overlay loading */
        /* Overlay loading */
        #loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 99;
            /* Pastikan overlay berada di atas menu */
        }

        /* Spinner animasi */
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Animasi spin */
        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        #mapSiswa {
            height: 500px;
            /* Tentukan tinggi peta */
            width: 100%;
            border-radius: .25rem;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        /* --- Sidebar: tema dark solid bersih --- */
        .app-sidebar.sidebar-clean {
            background-color: #1e293b !important;
            background-image: none !important;
            border-right: 1px solid rgba(255, 255, 255, .06);
        }

        .sidebar-clean .sidebar-brand {
            border-bottom: 1px solid rgba(255, 255, 255, .08);
        }

        .sidebar-clean .brand-link {
            color: #fff !important;
            border-bottom: none !important;
        }

        .sidebar-avatar {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, .25);
        }

        .sidebar-ta-select {
            background-color: rgba(255, 255, 255, .06);
            border-color: rgba(255, 255, 255, .14);
            color: #fff;
        }

        .sidebar-ta-select:focus {
            background-color: rgba(255, 255, 255, .10);
            border-color: rgba(255, 255, 255, .30);
            color: #fff;
            box-shadow: none;
        }

        .sidebar-ta-select option {
            background-color: #1e293b;
            color: #fff;
        }

        /* Header section: teks kecil, redup, tanpa blok putih */
        .sidebar-clean .nav-header {
            background-color: transparent !important;
            color: rgba(255, 255, 255, .40) !important;
            padding: .8rem .85rem .3rem;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        /* Tautan menu */
        .sidebar-clean .sidebar-menu .nav-link {
            color: rgba(255, 255, 255, .80);
            border-radius: .5rem;
            margin: .1rem .5rem;
            padding: .5rem .75rem;
            font-size: .9rem;
            transition: background-color .15s ease, color .15s ease;
        }

        .sidebar-clean .sidebar-menu .nav-link:hover {
            background-color: rgba(255, 255, 255, .08);
            color: #fff;
        }

        .sidebar-clean .sidebar-menu .nav-link.active {
            background-color: #2563eb !important;
            color: #fff !important;
            box-shadow: 0 2px 6px rgba(37, 99, 235, .4);
        }

        .sidebar-clean .sidebar-menu .nav-icon {
            color: inherit;
            opacity: .9;
        }

        /* Submenu (treeview) */
        .sidebar-clean .nav-treeview .nav-link {
            margin: .1rem .5rem .1rem .75rem;
            padding: .35rem .6rem .35rem 1.4rem;
            font-size: .84rem;
        }

        .sidebar-clean .nav-treeview .nav-link.active {
            background-color: rgba(37, 99, 235, .85) !important;
            box-shadow: none;
        }

        .sidebar-clean .nav-arrow {
            color: rgba(255, 255, 255, .45);
        }

        /* Scrollbar sidebar */
        .sidebar-clean .sidebar-wrapper::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-clean .sidebar-wrapper::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, .18);
            border-radius: 3px;
        }
    </style>


</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
        <nav class="app-header navbar navbar-expand bg-body">
            <div class="container-fluid">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                            <i class="bi bi-list"></i>
                        </a>
                    </li>
                    <li class="nav-item d-none d-md-block">
                        <span class="nav-link fw-bold text-dark">
                            SISTEM DATA ONLINE SEKOLAH
                        </span>
                    </li>

                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item d-none d-md-block">
                        <span class="nav-link d-flex align-items-center text-muted">
                            <i class="bi bi-clock me-1"></i>
                            <span id="wita-time"></span>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                            <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                            <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i>
                            <span class="d-none d-sm-inline"><?= htmlspecialchars($username) ?></span>
                            <span class="badge bg-danger ms-2" style="font-size: .62rem;"><?= htmlspecialchars($level) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><h6 class="dropdown-header">Masuk sebagai <b><?= htmlspecialchars($username) ?></b></h6></li>
                            <li><a class="dropdown-item" href="?menu=profil"><i class="bi bi-person-gear me-2"></i>Profil Saya</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="aksi/aksi_login.php?aksi=logout"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
        <aside class="app-sidebar sidebar-clean shadow" data-bs-theme="dark">
            <div class="sidebar-brand">
                <a href="index.php?menu=beranda" class="brand-link">
                    <img
                        src="assets/img/logo_putih.png"
                        alt="AdminLTE Logo"
                        class="brand-image opacity-75 shadow" />
                    <span class="brand-text fw-light">SiDoel</span>
                </a>
            </div>
            <div class="sidebar-wrapper">
                <nav class="mt-2">
                    <!-- Identitas sekolah + akun -->
                    <div class="d-flex align-items-center px-3 pt-2 pb-1">
                        <img src="assets/img/logo/<?= $dsek['logo']; ?>"
                            alt="Logo Sekolah"
                            class="sidebar-avatar rounded-circle me-2 flex-shrink-0">
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="text-white fw-semibold small text-truncate"><?= htmlspecialchars($judul) ?></div>
                            <div class="d-flex align-items-center mt-1">
                                <i class="bi bi-person-circle text-white-50 me-1"></i>
                                <span class="text-white-50 small text-truncate me-1"><?= htmlspecialchars($username) ?></span>
                                <span class="badge bg-danger flex-shrink-0" style="font-size: .62rem;"><?= htmlspecialchars($level) ?></span>
                            </div>
                        </div>
                    </div>
                    <hr class="text-white-50 mx-3 my-2" style="opacity: .2;">

                    <!-- Ganti Tahun Ajaran -->
                    <div class="px-3 pb-3">
                        <label for="select-ta-aktif" class="text-white-50 small d-block mb-1">
                            <i class="bi bi-calendar3 me-1"></i>Tahun Ajaran Aktif
                        </label>
                        <form action="aksi/aksi_rubah_tahun.php" method="POST" id="form-ta-ganti">
                            <select name="tahun_ajaran_baru" id="select-ta-aktif"
                                class="form-select form-select-sm sidebar-ta-select fw-bold text-center"
                                onchange="this.form.submit()">
                                <?php if (!empty($tahun_ajar_list)): ?>
                                    <?php foreach ($tahun_ajar_list as $ta):
                                        $selected = (($_SESSION['tahun_ajaran_aktif'] ?? '') == $ta['tahun_ajaran']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= htmlspecialchars($ta['tahun_ajaran']) ?>" <?= $selected ?>>
                                            <?= htmlspecialchars($ta['tahun_ajaran']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">Belum ada tahun ajaran</option>
                                <?php endif; ?>
                            </select>
                        </form>
                    </div>

                    <ul
                        class="nav sidebar-menu flex-column"
                        data-lte-toggle="treeview"
                        role="navigation"
                        aria-label="Main navigation"
                        data-accordion="false"
                        id="navigation">

                        <li class="nav-item">
                            <a href="?menu=beranda"
                                class="nav-link <?php echo ($activeMenu == 'beranda') ? 'active' : ''; ?>">
                                <i class="nav-icon bi bi-speedometer2"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>

                        <li class="nav-header">DATA MASTER</li>

                        <?php if (ada_menu_izin($level_user, ['data_sekolah', 'kop_sekolah', 'tahun_ajar', 'jurusan', 'mapel', 'kelas', 'user'])): ?>
                        <li class="nav-item <?php echo $isDataReferensiActive ? 'menu-open' : ''; ?>">
                            <a href="#" class="nav-link <?php echo $isDataReferensiActive ? 'active' : ''; ?>">
                                <i class="nav-icon bi bi-database"></i>
                                <p>
                                    Data Referensi
                                    <i class="nav-arrow bi bi-chevron-right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <?php if (boleh_menu($level_user, 'data_sekolah')): ?>
                                <li class="nav-item">
                                    <a href="?menu=data_sekolah"
                                        class="nav-link <?php echo ($activeMenu == 'data_sekolah') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-building"></i>
                                        <p>Data Sekolah</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'kop_sekolah')): ?>
                                <li class="nav-item">
                                    <a href="?menu=kop_sekolah"
                                        class="nav-link <?php echo ($activeMenu == 'kop_sekolah') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-file-earmark-richtext"></i>
                                        <p>Kop Sekolah</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'tahun_ajar')): ?>
                                <li class="nav-item">
                                    <a href="?menu=tahun_ajar" class="nav-link <?php echo ($activeMenu == 'tahun_ajar') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-calendar-check"></i>
                                        <p>Tahun Ajaran</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'jurusan')): ?>
                                <li class="nav-item">
                                    <a href="?menu=jurusan"
                                        class="nav-link <?php echo ($activeMenu == 'jurusan') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-award"></i>
                                        <p>Data Jurusan</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'mapel')): ?>
                                <li class="nav-item">
                                    <a href="?menu=mapel"
                                        class="nav-link <?php echo ($activeMenu == 'mapel') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-journal-text"></i>
                                        <p>Mata Pelajaran</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'kelas')): ?>
                                <li class="nav-item">
                                    <a href="?menu=kelas"
                                        class="nav-link <?php echo ($activeMenu == 'kelas') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-house-door"></i>
                                        <p>Data Kelas/Rombel</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'user')): ?>
                                <li class="nav-item">
                                    <a href="?menu=user"
                                        class="nav-link <?php echo ($activeMenu == 'user') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-person"></i>
                                        <p>User Pengguna</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>

                        <?php if (boleh_menu($level_user, 'guru')): ?>
                        <li class="nav-item">
                            <a href="?menu=guru" class="nav-link <?php echo ($activeMenu == 'guru') ? 'active' : ''; ?>">
                                <i class="nav-icon bi bi-person-workspace"></i>
                                <p>Data Guru &amp; Staf</p>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (boleh_menu($level_user, 'tarik_siswa_ppdb')): ?>
                        <li class="nav-header">PENERIMAAN SISWA BARU (PPDB)</li>

                        <li class="nav-item">
                            <a href="?menu=tarik_siswa_ppdb"
                                class="nav-link <?php echo ($activeMenu == 'tarik_siswa_ppdb') ? 'active' : ''; ?>">
                                <i class="nav-icon bi bi-arrow-bar-right"></i>
                                <p>Tarik Siswa (PPDB)</p>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (ada_menu_izin($level_user, ['siswa_aktif', 'penempatan_kelas', 'kenaikan_kelas', 'denah_siswa', 'alumni', 'foto_siswa'])): ?>
                        <li class="nav-header">DATA SISWA</li>

                        <li class="nav-item <?php echo $isSiswaActive ? 'menu-open' : ''; ?>">
                            <a href="#" class="nav-link <?php echo $isSiswaActive ? 'active' : ''; ?>">
                                <i class="nav-icon bi bi-person-badge"></i>
                                <p>
                                    Data Siswa
                                    <i class="nav-arrow bi bi-chevron-right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <?php if (boleh_menu($level_user, 'siswa_aktif')): ?>
                                <li class="nav-item">
                                    <a href="?menu=siswa_aktif"
                                        class="nav-link <?php echo ($activeMenu == 'siswa_aktif') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-person-fill"></i>
                                        <p>Siswa Aktif</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'penempatan_kelas')): ?>
                                <li class="nav-item">
                                    <a href="?menu=penempatan_kelas"
                                        class="nav-link <?php echo ($activeMenu == 'penempatan_kelas') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-list-columns"></i>
                                        <p>Atur Penempatan Kelas</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'kenaikan_kelas')): ?>
                                <li class="nav-item">
                                    <a href="?menu=kenaikan_kelas"
                                        class="nav-link <?php echo ($activeMenu == 'kenaikan_kelas') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-arrow-up-circle"></i>
                                        <p>Kenaikan Kelas</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'denah_siswa')): ?>
                                <li class="nav-item">
                                    <a href="?menu=denah_siswa"
                                        class="nav-link <?php echo ($activeMenu == 'denah_siswa') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-geo-alt-fill"></i>
                                        <p>Sebaran Siswa</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'alumni')): ?>
                                <li class="nav-item">
                                    <a href="?menu=alumni"
                                        class="nav-link <?php echo ($activeMenu == 'alumni') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-mortarboard-fill"></i>
                                        <p>Alumni Siswa</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'foto_siswa')): ?>
                                <li class="nav-item">
                                    <a href="?menu=foto_siswa"
                                        class="nav-link <?php echo ($activeMenu == 'foto_siswa') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-image-fill"></i>
                                        <p>Foto Siswa (Bulk)</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>

                        <?php if (ada_menu_izin($level_user, $kbmMenus)): ?>
                        <li class="nav-header">PENDIDIKAN &amp; KBM</li>

                        <?php if (ada_menu_izin($level_user, $kbmReferensiMenus)): ?>
                        <li class="nav-item <?php echo $isKbmReferensiActive ? 'menu-open' : ''; ?>">
                            <a href="#" class="nav-link <?php echo $isKbmReferensiActive ? 'active' : ''; ?>">
                                <i class="nav-icon bi bi-diagram-3"></i>
                                <p>
                                    Penugasan &amp; Referensi
                                    <i class="nav-arrow bi bi-chevron-right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <?php if (boleh_menu($level_user, 'guru_mapel')): ?>
                                <li class="nav-item">
                                    <a href="?menu=guru_mapel"
                                        class="nav-link <?php echo ($activeMenu == 'guru_mapel') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-person-lines-fill"></i>
                                        <p>Guru Mapel</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'pengampu_mapel')): ?>
                                <li class="nav-item">
                                    <a href="?menu=pengampu_mapel"
                                        class="nav-link <?php echo ($activeMenu == 'pengampu_mapel') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-journal-check"></i>
                                        <p>Pengampu Mapel</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'wali_kelas')): ?>
                                <li class="nav-item">
                                    <a href="?menu=wali_kelas"
                                        class="nav-link <?php echo ($activeMenu == 'wali_kelas') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-person-bounding-box"></i>
                                        <p>Penugasan Wali Kelas</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'sekretaris_kelas')): ?>
                                <li class="nav-item">
                                    <a href="?menu=sekretaris_kelas"
                                        class="nav-link <?php echo ($activeMenu == 'sekretaris_kelas') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-person-badge"></i>
                                        <p>Sekretaris Kelas</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'info_kelas')): ?>
                                <li class="nav-item">
                                    <a href="?menu=info_kelas"
                                        class="nav-link <?php echo ($activeMenu == 'info_kelas') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-info-circle"></i>
                                        <p>Info Kelas / Rombel</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>

                        <?php if (ada_menu_izin($level_user, $kbmPembelajaranMenus)): ?>
                        <li class="nav-item <?php echo $isKbmPembelajaranActive ? 'menu-open' : ''; ?>">
                            <a href="#" class="nav-link <?php echo $isKbmPembelajaranActive ? 'active' : ''; ?>">
                                <i class="nav-icon bi bi-calendar2-week"></i>
                                <p>
                                    Pembelajaran
                                    <i class="nav-arrow bi bi-chevron-right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <?php if (boleh_menu($level_user, 'jadwal')): ?>
                                <li class="nav-item">
                                    <a href="?menu=jadwal"
                                        class="nav-link <?php echo ($activeMenu == 'jadwal') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-calendar-week"></i>
                                        <p>Jadwal Pelajaran</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'jurnal_kelas')): ?>
                                <li class="nav-item">
                                    <a href="?menu=jurnal_kelas"
                                        class="nav-link <?php echo ($activeMenu == 'jurnal_kelas') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-journal-bookmark"></i>
                                        <p>Jurnal Kelas</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'kalender_akademik')): ?>
                                <li class="nav-item">
                                    <a href="?menu=kalender_akademik"
                                        class="nav-link <?php echo ($activeMenu == 'kalender_akademik') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-calendar3"></i>
                                        <p>Kalender Akademik</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>

                        <?php if (ada_menu_izin($level_user, $kbmRekapMenus)): ?>
                        <li class="nav-item <?php echo $isKbmRekapActive ? 'menu-open' : ''; ?>">
                            <a href="#" class="nav-link <?php echo $isKbmRekapActive ? 'active' : ''; ?>">
                                <i class="nav-icon bi bi-clipboard2-data"></i>
                                <p>
                                    Rekap &amp; Monitoring
                                    <i class="nav-arrow bi bi-chevron-right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <?php if (boleh_menu($level_user, 'dashboard_wali_kelas')): ?>
                                <li class="nav-item">
                                    <a href="?menu=dashboard_wali_kelas"
                                        class="nav-link <?php echo ($activeMenu == 'dashboard_wali_kelas') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-speedometer2"></i>
                                        <p>Dashboard Wali Kelas</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'rekap_absensi')): ?>
                                <li class="nav-item">
                                    <a href="?menu=rekap_absensi"
                                        class="nav-link <?php echo ($activeMenu == 'rekap_absensi') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-clipboard-data"></i>
                                        <p>Rekap Absensi</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if (boleh_menu($level_user, 'rekap_jurnal')): ?>
                                <li class="nav-item">
                                    <a href="?menu=rekap_jurnal"
                                        class="nav-link <?php echo ($activeMenu == 'rekap_jurnal') ? 'active' : ''; ?>">
                                        <i class="nav-icon bi bi-journal-text"></i>
                                        <p>Rekap Jurnal Guru</p>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php if (boleh_menu($level_user, 'kartu_siswa')): ?>
                        <li class="nav-header">LAPORAN &amp; CETAK</li>

                        <li class="nav-item">
                            <a href="?menu=kartu_siswa"
                                class="nav-link <?php echo ($activeMenu == 'kartu_siswa') ? 'active' : ''; ?>">
                                <i class="nav-icon bi bi-person-vcard"></i>
                                <p>Kartu Siswa</p>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (ada_menu_izin($level_user, $administrasiMenus)): ?>
                        <li class="nav-header">ADMINISTRASI SISTEM</li>

                        <?php if (boleh_menu($level_user, 'audit_log')): ?>
                        <li class="nav-item">
                            <a href="?menu=audit_log"
                                class="nav-link <?php echo ($activeMenu == 'audit_log') ? 'active' : ''; ?>">
                                <i class="nav-icon bi bi-clock-history"></i>
                                <p>Log Aktivitas</p>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (boleh_menu($level_user, 'backup_restore')): ?>
                        <li class="nav-item">
                            <a href="?menu=backup_restore"
                                class="nav-link <?php echo ($activeMenu == 'backup_restore') ? 'active' : ''; ?>">
                                <i class="nav-icon bi bi-database-fill-gear"></i>
                                <p>Backup &amp; Restore</p>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (boleh_menu($level_user, 'manajemen_ta')): ?>
                        <li class="nav-item">
                            <a href="?menu=manajemen_ta"
                                class="nav-link <?php echo ($activeMenu == 'manajemen_ta') ? 'active' : ''; ?>">
                                <i class="nav-icon bi bi-calendar2-range"></i>
                                <p>Manajemen Tahun Ajaran</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php endif; ?>

                        <li class="nav-item mt-2">
                            <a href="?menu=profil"
                                class="nav-link <?php echo ($activeMenu == 'profil') ? 'active' : ''; ?>">
                                <i class="nav-icon bi bi-person-gear"></i>
                                <p>Profil Saya</p>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="aksi/aksi_login.php?aksi=logout"
                                class="nav-link bg-danger text-white<?php echo ($activeMenu == 'logout') ? 'active' : ''; ?>">
                                <i class="nav-icon bi bi-box-arrow-right"></i>
                                <p>Logout</p>
                            </a>
                        </li>

                    </ul>
                </nav>
            </div>
        </aside>
        <main class="app-main">


            <?php include('konten.php'); ?>

        </main>
        <footer class="app-footer">
            <div class="float-end d-none d-sm-inline">Anything you want</div>
            <strong>
                Copyright &copy; 2014-2025&nbsp;
                <a href="https://adminlte.io" class="text-decoration-none">AdminLTE.io</a>.
            </strong>
            All rights reserved.
        </footer>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" crossorigin="anonymous"></script>
    <script
        src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"
        crossorigin="anonymous"></script>
    <script
        src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        crossorigin="anonymous"></script>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"
        crossorigin="anonymous"></script>
    <script src="assets/js/adminlte.js"></script>
    <script src="https://cdn.datatables.net/2.3.2/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.js"></script>

    <script>
        $(document).ready(function() {
            // Inisialisasi DataTables dengan Bootstrap 5 theme
            $('#myTable').DataTable({
                responsive: true,
                language: {
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ entri",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ entri",
                    infoEmpty: "Menampilkan 0 sampai 0 dari 0 entri",
                    infoFiltered: "(disaring dari total _MAX_ entri)",
                    paginate: {
                        first: "Awal",
                        last: "Akhir",
                        next: ">",
                        previous: "<"
                    },
                }
            });
        });
    </script>
    <script>
        $(document).ready(function() {
            $('#myTable1').DataTable({
                paging: false, // ❌ hilangkan pagination
                scrollY: '300px', // 📏 tinggi tabel 400px
                scrollCollapse: true, // biar scroll pas konten sedikit
                info: false, // hilangkan tulisan "Showing 1 of ..."
            });
        });
    </script>
    <script>
        $(document).ready(function() {
            $('#myTable2').DataTable({
                paging: false, // ❌ hilangkan pagination
                scrollY: '440px', // 📏 tinggi tabel 400px
                scrollCollapse: true, // biar scroll pas konten sedikit
                info: false, // hilangkan tulisan "Showing 1 of ..."
            });
        });
    </script>
    <script>
        const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
        const Default = {
            scrollbarTheme: 'os-theme-light',
            scrollbarAutoHide: 'leave',
            scrollbarClickScroll: true,
        };
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);

            // Disable OverlayScrollbars on mobile devices to prevent touch interference
            const isMobile = window.innerWidth <= 992;

            if (
                sidebarWrapper &&
                OverlayScrollbarsGlobal?.OverlayScrollbars !== undefined &&
                !isMobile
            ) {
                OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
                    scrollbars: {
                        theme: Default.scrollbarTheme,
                        autoHide: Default.scrollbarAutoHide,
                        clickScroll: Default.scrollbarClickScroll,
                    },
                });
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if (isset($_GET['pesan'])) : ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                let pesan = "<?= $_GET['pesan'] ?>";

                if (pesan === "tambah") {
                    Swal.fire("Berhasil!", "Data berhasil ditambahkan.", "success");
                } else if (pesan === "edit") {
                    Swal.fire("Berhasil!", "Data berhasil diupdate.", "success");
                } else if (pesan === "hapus") {
                    Swal.fire("Berhasil!", "Data berhasil dihapus.", "success");
                } else if (pesan === "duplikat") {
                    Swal.fire("Gagal!", "Data sudah digunakan.", "warning");
                } else if (pesan === "kosong") {
                    Swal.fire("Gagal!", "Password tidak boleh kosong.", "error");
                } else if (pesan === "error") {
                    Swal.fire("Error!", "Terjadi kesalahan. Coba lagi.", "error");
                } else if (pesan === "invalid") {
                    Swal.fire("Error!", "Aksi tidak valid.", "error");
                } else if (pesan === "pindah") {
                    Swal.fire("Berhasil!", "Data siswa berhasil dipindahkan ke alumni.", "success");
                } else if (pesan === "kembali") {
                    Swal.fire("Berhasil!", "Alumni berhasil dikembalikan menjadi siswa aktif.", "success");
                } else if (pesan === "tarikppdb") {
                    Swal.fire("Berhasil!", "Data calon siswa berhasil ditarik ke data siswa aktif.", "success");
                } else if (pesan === "validasi") {
                    Swal.fire("Berhasil!", "Pembayaran Tervalidasi.", "success");
                } else if (pesan === "tolak") {
                    Swal.fire("Error!", "Pembayaran Ditolak", "error");
                }

            });
        </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Pastikan jQuery sudah dimuat

        $(document).ready(function() {
            // 1. Inisialisasi Select2 (pastikan width: '100%' tetap ada)
            $('#id_fk_guru').select2({
                width: '100%',
                dropdownParent: $('#tambahUser'),
                placeholder: "-- Pilih Guru yang Belum Punya Akun --",
                allowClear: true
            });

            // 2. MENGGANTI EVENT LISTENER (MENGGUNAKAN JQUERY)
            $('#id_fk_guru').on('change', function() {
                // Ambil elemen <option> yang sedang terpilih
                var selectedOption = $(this).find('option:selected');

                // Ambil nilai data-email dari elemen option tersebut
                // jQuery .data() lebih mudah digunakan daripada getAttribute()
                var email = selectedOption.data('email');

                // Set nilai ke input username
                if (email) {
                    $('#username').val(email);
                } else {
                    $('#username').val('');
                }
            });
        });
    </script>

    <script>
        function updateWitaTime() {
            const now = new Date();
            const optionsDate = {
                timeZone: "Asia/Makassar",
                weekday: "long",
                year: "numeric",
                month: "long",
                day: "numeric"
            };
            const optionsTime = {
                timeZone: "Asia/Makassar",
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit"
            };

            const formatterDate = new Intl.DateTimeFormat("id-ID", optionsDate);
            const formatterTime = new Intl.DateTimeFormat("id-ID", optionsTime);

            document.getElementById("wita-time").textContent =
                formatterDate.format(now) + " - " + formatterTime.format(now) + " WITA";
        }

        setInterval(updateWitaTime, 1000);
        updateWitaTime();
    </script>

    <script>
        window.onload = function() {
            const loadingOverlay = document.getElementById('loading-overlay');
            const content = document.getElementById('content');

            // Hilangkan overlay loading
            loadingOverlay.style.display = 'none';

            // Tampilkan konten utama
            content.style.display = 'block';

            // Pastikan peta dirender ulang
            if (typeof google !== 'undefined' && map) {
                google.maps.event.trigger(map, 'resize');
            }
        };
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script>
        // 1. Ambil data lokasi siswa dari PHP
        const siswaLocations = <?= $siswa_locations_json ?? '[]'; ?>;
        const centerLat = <?= $center_lat ?? '0'; ?>;
        const centerLng = <?= $center_lng ?? '0'; ?>;

        // 2. Pastikan ada data untuk ditampilkan
        if (siswaLocations.length > 0) {
            // Cek jika elemen 'mapSiswa' sudah ada di DOM
            if (document.getElementById('mapSiswa')) {
                // 3. Inisialisasi Peta
                var map = L.map('mapSiswa').setView([centerLat, centerLng], 12);

                // 4. Tambahkan Tile Layer (Peta Dasar)
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                // Gunakan marker cluster bila plugin tersedia agar ratusan titik
                // tidak saling menumpuk; jika tidak, fallback ke feature group.
                var markers = (typeof L.markerClusterGroup === 'function')
                    ? L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 50 })
                    : L.featureGroup();

                // 5. Tambahkan Marker untuk setiap lokasi siswa
                siswaLocations.forEach(function(siswa) {
                    var popupContent = `
                     <b>Nama: ${siswa.nama}</b><br>
                     NIS: ${siswa.nis}<br>
                     Rombel: ${siswa.rombel}<br>
                     <small>Lat/Lng: ${siswa.lat}, ${siswa.lng}</small>
                 `;
                    var marker = L.marker([siswa.lat, siswa.lng])
                        .bindTooltip(String(siswa.nama || '(tanpa nama)'), { direction: 'top', sticky: true })
                        .bindPopup(popupContent);
                    if (typeof markers.addLayer === 'function') {
                        markers.addLayer(marker);
                    } else {
                        marker.addTo(map);
                    }
                });

                if (typeof markers.getBounds === 'function') {
                    if (typeof markers.addTo === 'function') {
                        markers.addTo(map);
                    }
                    if (siswaLocations.length > 1) {
                        // 6. Atur batas pandangan peta agar mencakup semua marker
                        map.fitBounds(markers.getBounds().pad(0.5));
                    }
                }

                // 7. Pastikan tile ter-render setelah kontainer tampil
                setTimeout(function() { map.invalidateSize(); }, 200);
            }

        } else {
            // Tampilkan pesan jika tidak ada data
            let mapDiv = document.getElementById('mapSiswa');
            if (mapDiv) {
                mapDiv.innerHTML = '<div class="alert alert-info text-center">Tidak ada data siswa dengan koordinat Lintang dan Bujur yang valid.</div>';
                mapDiv.style.height = 'auto';
            }
        }
    </script>
</body>

</html>