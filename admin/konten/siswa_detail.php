<?php
/**
 * Detail siswa (read-only) — ringkasan profil, kelengkapan data,
 * kehadiran bulan ini, riwayat kelas, orang tua/wali, dan denah lokasi.
 */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$r = null;
if ($id > 0 && isset($koneksi) && $koneksi instanceof mysqli && !$koneksi->connect_error) {
    $stmt = $koneksi->prepare("SELECT * FROM dt_siswa WHERE id_siswa = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $r = $res->fetch_assoc();
        $stmt->close();
    }
}

if (!$r) {
    echo '<main class="app-main"><div class="app-content"><div class="container-fluid">'
        . '<div class="alert alert-danger d-flex align-items-center gap-2 mt-3">'
        . '<i class="bi bi-exclamation-triangle-fill fs-4"></i>'
        . '<div>Data siswa tidak ditemukan atau sudah tidak tersedia. '
        . '<a href="index.php?menu=siswa_aktif" class="alert-link">Kembali ke Data Siswa</a>.</div>'
        . '</div></div></div></main>';
    return;
}

require_once(__DIR__ . '/../helper_kelengkapan.php');

if (!function_exists('sd_v')) {
    function sd_v($v, string $dash = '-'): string
    {
        if ($v === null) return '<span class="text-muted">' . $dash . '</span>';
        $s = trim((string)$v);
        if ($s === '' || $s === '0' || $s === '0000-00-00') {
            return '<span class="text-muted">' . $dash . '</span>';
        }
        return htmlspecialchars($s);
    }
}

if (!function_exists('sd_tgl')) {
    function sd_tgl($v): string
    {
        if ($v === null || trim((string)$v) === '' || $v === '0000-00-00') {
            return '<span class="text-muted">-</span>';
        }
        $ts = strtotime((string)$v);
        if (!$ts) return htmlspecialchars((string)$v);
        $bulan = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
                  7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        return date('d', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    }
}

if (!function_exists('sd_jk')) {
    function sd_jk($v): string
    {
        $s = strtoupper(trim((string)$v));
        if ($s === 'L' || $s === 'LAKI-LAKI') return 'Laki-laki';
        if ($s === 'P' || $s === 'PEREMPUAN') return 'Perempuan';
        return sd_v($v);
    }
}

if (!function_exists('sd_angka')) {
    function sd_angka($v, string $unit = '', int $desimal = 0): string
    {
        if (!is_numeric($v) || (float)$v <= 0) return '<span class="text-muted">-</span>';
        $s = number_format((float)$v, $desimal, ',', '.');
        return htmlspecialchars($s . ($unit !== '' ? ' ' . $unit : ''));
    }
}

if (!function_exists('sd_row')) {
    function sd_row(string $label, string $value): string
    {
        return '<dt class="col-sm-5">' . htmlspecialchars($label) . '</dt><dd class="col-sm-7">' . $value . '</dd>';
    }
}

$level_user = $_SESSION['level_user'] ?? '';
$boleh_edit = function_exists('boleh_menu') ? boleh_menu($level_user, 'siswa_edit') : true;
$boleh_kartu = function_exists('boleh_menu') ? boleh_menu($level_user, 'kartu_siswa') : true;

// --- Tahun ajaran aktif (mengikuti pilihan TA di sidebar) ---
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
$id_ta = (int)($r['id_tahun_ajaran'] ?? 0);
$ta_label = $nama_ta_sesi;
if ($nama_ta_sesi !== '' && $koneksi instanceof mysqli) {
    $stmt = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $nama_ta_sesi);
        $stmt->execute();
        $ta = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($ta) {
            $id_ta = (int)$ta['id_tahun_ajaran'];
            $ta_label = $ta['tahun_ajaran'];
        }
    }
}
if ($ta_label === '' && $id_ta > 0 && $koneksi instanceof mysqli) {
    $stmt = $koneksi->prepare("SELECT tahun_ajaran FROM dt_tahun_ajar WHERE id_tahun_ajaran = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $id_ta);
        $stmt->execute();
        $ta = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($ta) $ta_label = $ta['tahun_ajaran'];
    }
}

// --- Kelas siswa pada TA aktif (fallback ke riwayat terakhir) ---
$kelas = null;
$sql_kelas = "SELECT ks.id_kelas, ks.id_tahun_ajaran, k.nama_kelas, k.tingkat, j.nama_jurusan
              FROM dt_kelas_siswa ks
              INNER JOIN dt_kelas k ON k.id_kelas = ks.id_kelas
              LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
              WHERE ks.id_siswa = ?";
if ($id_ta > 0 && $koneksi instanceof mysqli) {
    $stmt = $koneksi->prepare($sql_kelas . " AND ks.id_tahun_ajaran = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ii", $id, $id_ta);
        $stmt->execute();
        $kelas = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
if (!$kelas && $koneksi instanceof mysqli) {
    $stmt = $koneksi->prepare($sql_kelas . " ORDER BY ks.id_tahun_ajaran DESC, ks.id_kelas_siswa DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $kelas = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$id_kelas = $kelas ? (int)$kelas['id_kelas'] : 0;
$kelas_label = $kelas ? trim((string)$kelas['tingkat'] . ' ' . (string)$kelas['nama_kelas']) : trim((string)($r['rombel'] ?? ''));
$jurusan_label = $kelas ? trim((string)($kelas['nama_jurusan'] ?? '')) : trim((string)($r['jurusan'] ?? ''));

// --- Wali kelas ---
$wali = null;
if ($id_kelas > 0 && $id_ta > 0 && $koneksi instanceof mysqli) {
    $stmt = $koneksi->prepare("SELECT g.nama_guru, g.nip
                               FROM dt_wali_kelas wk
                               INNER JOIN dt_guru g ON g.id_guru = wk.id_guru
                               WHERE wk.id_kelas = ? AND wk.id_tahun_ajaran = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ii", $id_kelas, $id_ta);
        $stmt->execute();
        $wali = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}
$wali_label = $wali ? trim((string)$wali['nama_guru']) : '';

// --- Riwayat penempatan kelas ---
$riwayat_kelas = [];
if ($koneksi instanceof mysqli) {
    $stmt = $koneksi->prepare("SELECT ks.id_tahun_ajaran, ks.keterangan, ta.tahun_ajaran,
                                      k.nama_kelas, k.tingkat, j.nama_jurusan, g.nama_guru AS wali
                               FROM dt_kelas_siswa ks
                               INNER JOIN dt_kelas k ON k.id_kelas = ks.id_kelas
                               INNER JOIN dt_tahun_ajar ta ON ta.id_tahun_ajaran = ks.id_tahun_ajaran
                               LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                               LEFT JOIN dt_wali_kelas wk ON wk.id_kelas = k.id_kelas AND wk.id_tahun_ajaran = ks.id_tahun_ajaran
                               LEFT JOIN dt_guru g ON g.id_guru = wk.id_guru
                               WHERE ks.id_siswa = ?
                               ORDER BY ta.tahun_ajaran DESC, ks.id_kelas_siswa DESC");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $riwayat_kelas[] = $row;
        $stmt->close();
    }
}

// --- Kehadiran bulan berjalan ---
$nama_bulan_id = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
                  7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
$bulan_ini = date('Y-m');
$awal_bulan = $bulan_ini . '-01';
$akhir_bulan = date('Y-m-t');
$bulan_label = $nama_bulan_id[(int)date('n')] . ' ' . date('Y');
$att = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
if ($id_ta > 0 && $koneksi instanceof mysqli) {
    $stmt = $koneksi->prepare("SELECT status, COUNT(*) AS c
                               FROM dt_absensi
                               WHERE id_siswa = ? AND id_tahun_ajaran = ? AND tanggal BETWEEN ? AND ?
                               GROUP BY status");
    if ($stmt) {
        $stmt->bind_param("iiss", $id, $id_ta, $awal_bulan, $akhir_bulan);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($a = $res->fetch_assoc()) {
            $st = strtoupper((string)$a['status']);
            if (isset($att[$st])) $att[$st] = (int)$a['c'];
        }
        $stmt->close();
    }
}
$att_total = array_sum($att);
$att_pct = $att_total > 0 ? round($att['H'] * 100 / $att_total, 1) : null;

// --- Kelengkapan data wajib ---
$kel = kel_baris($r);
$kel_cls = kel_cls($kel['pct']);

// --- Foto siswa ---
$foto_nama = basename(trim((string)($r['foto'] ?? '')));
$foto_rel = 'assets/img/foto_siswa/' . $foto_nama;
$has_foto = $foto_nama !== '' && $foto_nama !== 'user.png' && is_file(__DIR__ . '/../' . $foto_rel);
$is_perempuan = in_array(strtoupper(trim((string)($r['jk'] ?? ''))), ['P', 'PEREMPUAN'], true);
$foto_url = $has_foto ? $foto_rel : ('assets/img/' . ($is_perempuan ? 'wanita.png' : 'pria.png'));

// --- Usia & IMT ---
$umur = '';
if (!empty($r['tanggal_lahir']) && $r['tanggal_lahir'] !== '0000-00-00') {
    $lahir = date_create((string)$r['tanggal_lahir']);
    if ($lahir) {
        $diff = date_diff($lahir, date_create('today'));
        $umur = $diff->y . ' tahun';
    }
}
$bmi = null;
$bmi_label = '';
$bmi_cls = 'secondary';
if (is_numeric($r['berat_badan']) && is_numeric($r['tinggi_badan']) && (float)$r['berat_badan'] > 0 && (float)$r['tinggi_badan'] > 0) {
    $meter = (float)$r['tinggi_badan'] / 100;
    $bmi = round((float)$r['berat_badan'] / ($meter * $meter), 1);
    if ($bmi < 18.5) { $bmi_label = 'Kurang'; $bmi_cls = 'warning'; }
    elseif ($bmi < 25) { $bmi_label = 'Normal'; $bmi_cls = 'success'; }
    elseif ($bmi < 30) { $bmi_label = 'Berlebih'; $bmi_cls = 'warning'; }
    else { $bmi_label = 'Obesitas'; $bmi_cls = 'danger'; }
}

// --- Alamat lengkap & koordinat ---
$alamat_parts = [];
foreach (['alamat', 'rt_rw', 'dusun', 'kelurahan', 'kecamatan', 'kode_pos'] as $key_alamat) {
    $v = trim((string)($r[$key_alamat] ?? ''));
    if ($v !== '' && $v !== '0' && $v !== '0000-00-00') $alamat_parts[] = $v;
}
$alamat_lengkap = implode(', ', $alamat_parts);

$lat_num = str_replace(',', '.', trim((string)($r['lintang'] ?? '')));
$lng_num = str_replace(',', '.', trim((string)($r['bujur'] ?? '')));
$has_coordinates = ($lat_num !== '' && $lng_num !== '' && is_numeric($lat_num) && is_numeric($lng_num))
    && ((float)$lat_num >= -9.5 && (float)$lat_num <= -7.0)
    && ((float)$lng_num >= 114.0 && (float)$lng_num <= 116.5);
$map_url = $has_coordinates ? "https://maps.google.com/maps?q={$lat_num},{$lng_num}&z=16&output=embed" : '';
$gmaps_url = $has_coordinates ? "https://www.google.com/maps/search/?api=1&query={$lat_num},{$lng_num}" : '';
?>

<style>
    .sd-profile-photo { width: 116px; height: 116px; object-fit: cover; background: #f1f3f5; }
    .sd-detail-list dt { font-weight: 500; color: #6c757d; }
    .sd-detail-list dd { margin-bottom: .55rem; overflow-wrap: anywhere; }
    .sd-stat-icon { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: .65rem; }
    .sd-muted-box { background: #f8f9fa; }
    @media (max-width: 575.98px) {
        .sd-profile-photo { width: 92px; height: 92px; }
    }
</style>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center g-2">
                <div class="col-md-7">
                    <h3 class="mb-0">Detail Data Siswa</h3>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 small">
                            <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                            <li class="breadcrumb-item"><a href="index.php?menu=siswa_aktif">Data Siswa</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Detail</li>
                        </ol>
                    </nav>
                </div>
                <div class="col-md-5 text-md-end">
                    <a href="index.php?menu=siswa_aktif" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Kembali
                    </a>
                    <?php if ($boleh_edit): ?>
                        <a href="index.php?menu=siswa_edit&id=<?= (int)$r['id_siswa'] ?>" class="btn btn-primary btn-sm ms-1">
                            <i class="bi bi-pencil-square me-1"></i>Edit Data
                        </a>
                    <?php endif; ?>
                    <?php if ($boleh_kartu): ?>
                        <a href="index.php?menu=kartu_siswa&id_siswa=<?= (int)$r['id_siswa'] ?>" target="_blank" class="btn btn-outline-primary btn-sm ms-1">
                            <i class="bi bi-person-vcard me-1"></i>Cetak Kartu
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3 p-md-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-auto">
                            <img src="<?= htmlspecialchars($foto_url) ?>" alt="Foto <?= htmlspecialchars((string)$r['nama']) ?>" class="sd-profile-photo rounded-circle border border-3 border-white shadow-sm" loading="lazy">
                        </div>
                        <div class="col">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <h3 class="mb-0"><?= sd_v($r['nama']) ?></h3>
                                <?php if ($kelas_label !== ''): ?>
                                    <span class="badge text-bg-primary"><i class="bi bi-mortarboard me-1"></i><?= htmlspecialchars($kelas_label) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted mt-1">
                                <span class="me-3"><i class="bi bi-upc-scan me-1"></i>NIS/NIPD: <b><?= sd_v($r['nipd']) ?></b></span>
                                <span><i class="bi bi-person-vcard me-1"></i>NISN: <b><?= sd_v($r['nisn']) ?></b></span>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php if ($jurusan_label !== ''): ?>
                                    <span class="badge text-bg-info"><?= htmlspecialchars($jurusan_label) ?></span>
                                <?php endif; ?>
                                <span class="badge text-bg-secondary"><i class="bi bi-calendar3 me-1"></i>TA <?= htmlspecialchars($ta_label !== '' ? $ta_label : '-') ?></span>
                                <span class="badge text-bg-light border"><i class="bi bi-gender-ambiguous me-1"></i><?= sd_jk($r['jk']) ?></span>
                                <?php if ($wali_label !== ''): ?>
                                    <span class="badge text-bg-light border"><i class="bi bi-person-badge me-1"></i>Wali: <?= htmlspecialchars($wali_label) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded-3 p-3 sd-muted-box h-100">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold"><i class="bi bi-clipboard2-check me-1"></i>Kelengkapan Data</span>
                                    <span class="badge text-bg-<?= $kel_cls ?>"><?= number_format($kel['pct'], 1, ',', '.') ?>%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?= $kel_cls ?>" role="progressbar" style="width: <?= $kel['pct'] ?>%"></div>
                                </div>
                                <div class="small text-muted mt-2"><?= (int)$kel['filled'] ?>/<?= (int)$kel['total'] ?> field wajib terisi</div>
                                <?php if ($kel['missing']): ?>
                                    <div class="small text-danger mt-1">
                                        Kurang: <?= htmlspecialchars(implode(', ', array_slice($kel['missing'], 0, 5))) ?><?= count($kel['missing']) > 5 ? ' +' . (count($kel['missing']) - 5) . ' lainnya' : '' ?>
                                    </div>
                                <?php else: ?>
                                    <div class="small text-success mt-1"><i class="bi bi-check2-circle me-1"></i>Data wajib sudah lengkap</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-3">
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="small text-muted">Kehadiran <?= htmlspecialchars($bulan_label) ?></div>
                                    <div class="fs-3 fw-bold text-success"><?= $att_pct !== null ? number_format($att_pct, 1, ',', '.') . '%' : '-' ?></div>
                                </div>
                                <span class="sd-stat-icon text-bg-success-subtle text-success"><i class="bi bi-calendar-check"></i></span>
                            </div>
                            <div class="small text-muted mt-1"><?= $att_total > 0 ? (int)$att['H'] . ' dari ' . $att_total . ' hari absensi' : 'Belum ada data absensi' ?></div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="small text-muted">Sakit</div>
                                    <div class="fs-3 fw-bold text-info"><?= (int)$att['S'] ?></div>
                                </div>
                                <span class="sd-stat-icon text-bg-info-subtle text-info"><i class="bi bi-thermometer-half"></i></span>
                            </div>
                            <div class="small text-muted mt-1">hari pada bulan ini</div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="small text-muted">Izin</div>
                                    <div class="fs-3 fw-bold text-warning"><?= (int)$att['I'] ?></div>
                                </div>
                                <span class="sd-stat-icon text-bg-warning-subtle text-warning"><i class="bi bi-envelope-paper"></i></span>
                            </div>
                            <div class="small text-muted mt-1">hari pada bulan ini</div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="small text-muted">Alfa</div>
                                    <div class="fs-3 fw-bold text-danger"><?= (int)$att['A'] ?></div>
                                </div>
                                <span class="sd-stat-icon text-bg-danger-subtle text-danger"><i class="bi bi-person-x"></i></span>
                            </div>
                            <div class="small text-muted mt-1">hari pada bulan ini</div>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs" id="siswaDetailTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-profil-link" data-bs-toggle="tab" data-bs-target="#tab-profil" type="button" role="tab" aria-controls="tab-profil" aria-selected="true">
                        <i class="bi bi-person-lines-fill me-1"></i>Profil
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-ortu-link" data-bs-toggle="tab" data-bs-target="#tab-ortu" type="button" role="tab" aria-controls="tab-ortu" aria-selected="false">
                        <i class="bi bi-people me-1"></i>Orang Tua/Wali
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-riwayat-link" data-bs-toggle="tab" data-bs-target="#tab-riwayat" type="button" role="tab" aria-controls="tab-riwayat" aria-selected="false">
                        <i class="bi bi-clock-history me-1"></i>Riwayat Kelas
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-denah-link" data-bs-toggle="tab" data-bs-target="#tab-denah" type="button" role="tab" aria-controls="tab-denah" aria-selected="false">
                        <i class="bi bi-geo-alt me-1"></i>Denah Lokasi
                    </button>
                </li>
            </ul>

            <div class="tab-content pt-3" id="siswaDetailTabContent">
                <div class="tab-pane fade show active" id="tab-profil" role="tabpanel" aria-labelledby="tab-profil-link">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white fw-semibold"><i class="bi bi-person-vcard me-1 text-primary"></i>Identitas Siswa</div>
                                <div class="card-body">
                                    <dl class="row mb-0 sd-detail-list">
                                        <?= sd_row('Nama Lengkap', sd_v($r['nama'])) ?>
                                        <?= sd_row('NIS / NIPD', sd_v($r['nipd'])) ?>
                                        <?= sd_row('NISN', sd_v($r['nisn'])) ?>
                                        <?= sd_row('Jenis Kelamin', sd_jk($r['jk'])) ?>
                                        <?= sd_row('Tempat Lahir', sd_v($r['tempat_lahir'])) ?>
                                        <?= sd_row('Tanggal Lahir', sd_tgl($r['tanggal_lahir'])) ?>
                                        <?= sd_row('Usia', $umur !== '' ? htmlspecialchars($umur) : '<span class="text-muted">-</span>') ?>
                                        <?= sd_row('NIK', sd_v($r['nik'])) ?>
                                        <?= sd_row('Agama', sd_v($r['agama'])) ?>
                                        <?= sd_row('No. Kartu Keluarga', sd_v($r['no_kk'])) ?>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white fw-semibold"><i class="bi bi-telephone me-1 text-primary"></i>Kontak &amp; Alamat</div>
                                <div class="card-body">
                                    <dl class="row mb-0 sd-detail-list">
                                        <?php
                                        $hp = trim((string)($r['hp'] ?? ''));
                                        $hp_html = $hp !== '' ? '<a href="tel:' . htmlspecialchars($hp) . '">' . htmlspecialchars($hp) . '</a>' : '<span class="text-muted">-</span>';
                                        $email = trim((string)($r['email'] ?? ''));
                                        $email_html = $email !== '' ? '<a href="mailto:' . htmlspecialchars($email) . '">' . htmlspecialchars($email) . '</a>' : '<span class="text-muted">-</span>';
                                        ?>
                                        <?= sd_row('No. HP', $hp_html) ?>
                                        <?= sd_row('Telepon', sd_v($r['telp'])) ?>
                                        <?= sd_row('Email', $email_html) ?>
                                        <?= sd_row('Alamat', sd_v($r['alamat'])) ?>
                                        <?= sd_row('RT/RW', sd_v($r['rt_rw'])) ?>
                                        <?= sd_row('Dusun', sd_v($r['dusun'])) ?>
                                        <?= sd_row('Kelurahan / Desa', sd_v($r['kelurahan'])) ?>
                                        <?= sd_row('Kecamatan', sd_v($r['kecamatan'])) ?>
                                        <?= sd_row('Kode Pos', sd_v($r['kode_pos'])) ?>
                                        <?= sd_row('Jenis Tinggal', sd_v($r['jenis_tinggal'])) ?>
                                        <?= sd_row('Transportasi', sd_v($r['transportasi'])) ?>
                                        <?= sd_row('Jarak ke Sekolah', sd_angka($r['jarak_rumah_ke_sekolah_km'], 'km', 1)) ?>
                                        <?= sd_row('Alamat Lengkap', $alamat_lengkap !== '' ? htmlspecialchars($alamat_lengkap) : '<span class="text-muted">-</span>') ?>
                                        <?= sd_row('Titik Lokasi', $has_coordinates ? '<a href="' . htmlspecialchars($gmaps_url) . '" target="_blank" rel="noopener"><i class="bi bi-geo-alt me-1"></i>Lihat di Google Maps</a>' : '<span class="text-muted">Belum diisi</span>') ?>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white fw-semibold"><i class="bi bi-mortarboard me-1 text-primary"></i>Data Akademik</div>
                                <div class="card-body">
                                    <dl class="row mb-0 sd-detail-list">
                                        <?= sd_row('Kelas', $kelas_label !== '' ? htmlspecialchars($kelas_label) : '<span class="text-muted">-</span>') ?>
                                        <?= sd_row('Tingkat', $kelas ? sd_v($kelas['tingkat']) : '<span class="text-muted">-</span>') ?>
                                        <?= sd_row('Jurusan', $jurusan_label !== '' ? htmlspecialchars($jurusan_label) : '<span class="text-muted">-</span>') ?>
                                        <?= sd_row('Tahun Ajaran', $ta_label !== '' ? htmlspecialchars($ta_label) : '<span class="text-muted">-</span>') ?>
                                        <?= sd_row('Wali Kelas', $wali_label !== '' ? htmlspecialchars($wali_label) : '<span class="text-muted">Belum ditentukan</span>') ?>
                                        <?= sd_row('NIP Wali', $wali && trim((string)$wali['nip']) !== '' ? htmlspecialchars((string)$wali['nip']) : '<span class="text-muted">-</span>') ?>
                                        <?= sd_row('Sekolah Asal', sd_v($r['sekolah_asal'])) ?>
                                        <?= sd_row('Anak ke-', sd_angka($r['anak_ke_berapa'])) ?>
                                        <?= sd_row('Jumlah Saudara Kandung', sd_angka($r['jml_saudara_kandung'])) ?>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white fw-semibold"><i class="bi bi-activity me-1 text-primary"></i>Data Fisik</div>
                                <div class="card-body">
                                    <dl class="row mb-0 sd-detail-list">
                                        <?= sd_row('Berat Badan', sd_angka($r['berat_badan'], 'kg')) ?>
                                        <?= sd_row('Tinggi Badan', sd_angka($r['tinggi_badan'], 'cm')) ?>
                                        <?= sd_row('IMT (indikatif)', $bmi !== null ? htmlspecialchars(number_format($bmi, 1, ',', '.')) . ' <span class="badge text-bg-' . $bmi_cls . '">' . htmlspecialchars($bmi_label) . '</span>' : '<span class="text-muted">-</span>') ?>
                                        <?= sd_row('Jumlah Saudara Kandung', sd_angka($r['jml_saudara_kandung'])) ?>
                                        <?= sd_row('Anak ke-', sd_angka($r['anak_ke_berapa'])) ?>
                                    </dl>
                                    <div class="alert alert-light border small mt-3 mb-0">
                                        <i class="bi bi-info-circle me-1"></i>IMT hanya indikasi awal, bukan diagnosis kesehatan.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-ortu" role="tabpanel" aria-labelledby="tab-ortu-link">
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-1 text-primary"></i>Data Ayah</div>
                                <div class="card-body">
                                    <dl class="row mb-0 sd-detail-list">
                                        <?= sd_row('Nama Ayah', sd_v($r['nama_ayah'])) ?>
                                        <?= sd_row('Tahun Lahir', sd_v($r['tahun_lahir_ayah'])) ?>
                                        <?= sd_row('Pendidikan', sd_v($r['pendidikan_ayah'])) ?>
                                        <?= sd_row('Pekerjaan', sd_v($r['pekerjaan_ayah'])) ?>
                                        <?= sd_row('Penghasilan', sd_v($r['penghasilan_ayah'])) ?>
                                        <?= sd_row('NIK Ayah', sd_v($r['nik_ayah'])) ?>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-1 text-primary"></i>Data Ibu</div>
                                <div class="card-body">
                                    <dl class="row mb-0 sd-detail-list">
                                        <?= sd_row('Nama Ibu', sd_v($r['nama_ibu'])) ?>
                                        <?= sd_row('Tahun Lahir', sd_v($r['tahun_lahir_ibu'])) ?>
                                        <?= sd_row('Pendidikan', sd_v($r['pendidikan_ibu'])) ?>
                                        <?= sd_row('Pekerjaan', sd_v($r['pekerjaan_ibu'])) ?>
                                        <?= sd_row('Penghasilan', sd_v($r['penghasilan_ibu'])) ?>
                                        <?= sd_row('NIK Ibu', sd_v($r['nik_ibu'])) ?>
                                    </dl>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-1 text-primary"></i>Data Wali</div>
                                <div class="card-body">
                                    <dl class="row mb-0 sd-detail-list">
                                        <?= sd_row('Nama Wali', sd_v($r['nama_wali'])) ?>
                                        <?= sd_row('Tahun Lahir', sd_v($r['tahun_lahir_wali'])) ?>
                                        <?= sd_row('Pendidikan', sd_v($r['pendidikan_wali'])) ?>
                                        <?= sd_row('Pekerjaan', sd_v($r['pekerjaan_wali'])) ?>
                                        <?= sd_row('Penghasilan', sd_v($r['penghasilan_wali'])) ?>
                                        <?= sd_row('NIK Wali', sd_v($r['nik_wali'])) ?>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-riwayat" role="tabpanel" aria-labelledby="tab-riwayat-link">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-1 text-primary"></i>Riwayat Penempatan Kelas</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width: 56px;">No</th>
                                            <th>Tahun Ajaran</th>
                                            <th>Kelas</th>
                                            <th>Jurusan</th>
                                            <th>Wali Kelas</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$riwayat_kelas): ?>
                                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada riwayat penempatan kelas.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($riwayat_kelas as $i => $h): ?>
                                                <tr>
                                                    <td class="text-center"><?= $i + 1 ?></td>
                                                    <td>
                                                        <?= htmlspecialchars((string)$h['tahun_ajaran']) ?>
                                                        <?php if ((int)$h['id_tahun_ajaran'] === (int)$id_ta): ?>
                                                            <span class="badge text-bg-primary ms-1">Aktif</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars(trim((string)$h['tingkat'] . ' ' . (string)$h['nama_kelas'])) ?></td>
                                                    <td><?= sd_v($h['nama_jurusan']) ?></td>
                                                    <td><?= sd_v($h['wali']) ?></td>
                                                    <td><?= sd_v($h['keterangan']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-denah" role="tabpanel" aria-labelledby="tab-denah-link">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white fw-semibold"><i class="bi bi-geo-alt me-1 text-primary"></i>Denah Lokasi Rumah</div>
                        <div class="card-body">
                            <?php if ($has_coordinates): ?>
                                <div class="row g-3">
                                    <div class="col-lg-4">
                                        <div class="border rounded-3 p-3 sd-muted-box h-100">
                                            <div class="small text-muted">Koordinat</div>
                                            <div class="fw-semibold mb-2"><?= htmlspecialchars($lat_num) ?>, <?= htmlspecialchars($lng_num) ?></div>
                                            <div class="small text-muted">Alamat</div>
                                            <div><?= $alamat_lengkap !== '' ? htmlspecialchars($alamat_lengkap) : '<span class="text-muted">-</span>' ?></div>
                                            <a href="<?= htmlspecialchars($gmaps_url) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mt-3">
                                                <i class="bi bi-box-arrow-up-right me-1"></i>Buka di Google Maps
                                            </a>
                                        </div>
                                    </div>
                                    <div class="col-lg-8">
                                        <div class="ratio ratio-16x9 rounded overflow-hidden border">
                                            <iframe src="<?= htmlspecialchars($map_url) ?>" frameborder="0" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                                        </div>
                                    </div>
                                </div>
                                <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Denah memakai koordinat lintang/bujur yang tersimpan. Akurasi tergantung titik yang diisi.</p>
                            <?php else: ?>
                                <div class="alert alert-warning mb-0">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    Data <b>latitude</b> dan <b>longitude</b> belum tersedia atau di luar area yang dikenal. Lengkapi koordinat lewat menu Edit Data agar denah dapat ditampilkan.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>
