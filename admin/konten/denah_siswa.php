<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
</div>
<?php
// Pastikan sesi sudah dimulai di index.php atau file induk
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("koneksi.php");
// Asumsi: File koneksi.php berisi objek koneksi database $koneksi

// ==========================================================
// >>> 1. AMBIL TAHUN AJARAN DARI SESI (TITIK ACUAN UTAMA) <<<
// ==========================================================
// Ambil nama Tahun Ajaran dari SESSION yang di-set oleh aksi/aksi_tahun_ajar.php
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? 'Tahun Ajaran Tidak Ditemukan';

// Inisialisasi default jika koneksi gagal atau data tidak ditemukan
$id_ta_aktif = 0;
$nama_ta_aktif = htmlspecialchars($nama_ta_sesi);

// Cari ID Tahun Ajaran berdasarkan nama TAHUN AJARAN yang ada di sesi.
if (!$koneksi->connect_error) {
    // Menggunakan prepared statement untuk keamanan dan efisiensi
    $q_ta_aktif = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    if ($q_ta_aktif) {
        $q_ta_aktif->bind_param("s", $nama_ta_sesi);
        $q_ta_aktif->execute();
        $result_ta_aktif = $q_ta_aktif->get_result();

        if ($result_ta_aktif->num_rows > 0) {
            $TA_AKTIF = $result_ta_aktif->fetch_assoc();
            $id_ta_aktif = $TA_AKTIF['id_tahun_ajaran'];
            $nama_ta_aktif = htmlspecialchars($TA_AKTIF['tahun_ajaran']);
        }
        $q_ta_aktif->close();
    }
}
// ==========================================================


// LOGIKA BARU UNTUK PETA SEMUA SISWA (Leaflet.js)
// ----------------------------------------------------

// Kolom lintang/bujur bertipe varchar dan sebagian tersimpan dengan koma
// desimal (mis. "-8,68114"), sehingga is_numeric() menolaknya dan peta
// menjadi kosong. Fungsi berikut menormalkan nilai tersebut ke float.
if (!function_exists('normalisasi_koordinat')) {
    function normalisasi_koordinat($nilai)
    {
        if ($nilai === null) {
            return null;
        }
        $v = trim($nilai);
        if ($v === '') {
            return null;
        }
        $v = str_replace(',', '.', $v); // koma desimal -> titik desimal
        if (!is_numeric($v)) {
            return null;
        }
        return (float)$v;
    }
}

// Daftar siswa history-aware: ambil dari riwayat penempatan (dt_kelas_siswa)
// pada TA terpilih, bukan hanya dt_siswa.id_tahun_ajaran (yang ditimpa saat
// kenaikan kelas). Rombel di-resolve dari dt_kelas TA tersebut.
$query_all_siswa = "
    SELECT s.id_tahun_ajaran, s.nama, s.nipd,
           COALESCE(CONCAT(k.tingkat, ' - ', k.nama_kelas), NULLIF(s.rombel, '')) AS rombel,
           s.lintang, s.bujur
    FROM dt_siswa s
    LEFT JOIN dt_kelas_siswa ks ON ks.id_siswa = s.id_siswa AND ks.id_tahun_ajaran = $id_ta_aktif
    LEFT JOIN dt_kelas k ON k.id_kelas = ks.id_kelas
    WHERE (s.id_tahun_ajaran = $id_ta_aktif OR ks.id_kelas_siswa IS NOT NULL)
      AND s.lintang IS NOT NULL AND s.bujur IS NOT NULL AND s.lintang != '' AND s.bujur != ''
";
$result_all_siswa = mysqli_query($koneksi, $query_all_siswa);

$siswa_locations = [];
$koordinat_invalid = 0;

// Wilayah yang layak ditampilkan di peta (sekitar Bali). Titik di luar kotak
// ini dianggap salah input/data rusak (mis. (0,0), (1,1), atau titik di luar
// pulau) dan tidak ditampilkan agar tampilan peta tidak melebar tak wajar.
$batas_lat_min = -9.5;
$batas_lat_max = -7.0;
$batas_lng_min = 114.0;
$batas_lng_max = 116.5;

if ($result_all_siswa) {
    while ($row = mysqli_fetch_assoc($result_all_siswa)) {
        $lat = normalisasi_koordinat($row['lintang']);
        $lng = normalisasi_koordinat($row['bujur']);

        // Lewati data yang tidak dapat dikonversi menjadi angka
        if ($lat === null || $lng === null) {
            $koordinat_invalid++;
            continue;
        }

        // Seluruh siswa berada di Bali (belahan selatan). Nilai lintang positif
        // (mis. 8,6) adalah kesalahan tanda, jadi dijadikan negatif.
        if ($lat > 0 && $lat <= 90) {
            $lat = -$lat;
        }

        // Validasi rentang koordinat yang sah
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $koordinat_invalid++;
            continue;
        }

        // Validasi wilayah (buang outlier di luar Bali)
        if ($lat < $batas_lat_min || $lat > $batas_lat_max || $lng < $batas_lng_min || $lng > $batas_lng_max) {
            $koordinat_invalid++;
            continue;
        }

        $siswa_locations[] = [
            'lat' => $lat,
            'lng' => $lng,
            'nama' => $row['nama'],
            'nis' => $row['nipd'],
            'rombel' => $row['rombel']
        ];
    }
}

// Konversi array PHP ke JSON untuk digunakan di JavaScript
$siswa_locations_json = json_encode($siswa_locations);

// Tentukan koordinat pusat peta dari rata-rata titik siswa yang valid.
// Bila tidak ada titik valid, pakai lokasi default (Badung, Bali).
$center_lat = -8.6258946;
$center_lng = 115.1775938;
if (count($siswa_locations) > 0) {
    $sum_lat = 0;
    $sum_lng = 0;
    foreach ($siswa_locations as $loc) {
        $sum_lat += $loc['lat'];
        $sum_lng += $loc['lng'];
    }
    $center_lat = $sum_lat / count($siswa_locations);
    $center_lng = $sum_lng / count($siswa_locations);
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Data Sebaran Rumah Siswa</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Data Rumah Siswa</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="alert alert-info py-2 mb-3 shadow-sm">
                <i class="bi bi-info-circle-fill me-1"></i> Data kelas yang ditampilkan saat ini adalah untuk Tahun Ajaran: <b><?= $nama_ta_aktif ?></b>.
                <?php if ($koordinat_invalid > 0): ?>
                    <span class="text-danger ms-2"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= $koordinat_invalid ?> data siswa memiliki koordinat tidak valid dan tidak ditampilkan.</span>
                <?php endif; ?>
            </div>

            <div class="row d-flex flex-wrap mb-3">
                <div class="card p-2 mb-2">
                    <div class="bg-dark p-2 text-white mb-3 text-center mb-0">
                        <b>PETA LOKASI SEMUA SISWA (<?= count($siswa_locations); ?> Titik)</b>
                    </div>
                    <div class="card-body">
                        <div id="mapSiswa"></div>
                        <p class="mt-3 small text-muted">Peta ini menampilkan semua titik rumah siswa yang memiliki data Lintang (Latitude) dan Bujur (Longitude) yang valid.</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>