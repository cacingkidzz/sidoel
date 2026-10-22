<?php
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$r = null;
if ($id > 0 && isset($koneksi) && !$koneksi->connect_error) {
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
    header("Location: index.php?menu=siswa_aktif&pesan=invalid");
    exit;
}

// --- LOGIKA GOOGLE MAPS (Lintang & Bujur) ---
$latitude = trim((string)($r['lintang'] ?? ''));
$longitude = trim((string)($r['bujur'] ?? ''));

// Koordinat bisa memakai koma desimal; normalisasi ke titik lalu validasi rentang wajar (bounding box Bali).
$lat_num = str_replace(',', '.', $latitude);
$lng_num = str_replace(',', '.', $longitude);
$has_coordinates = ($lat_num !== '' && $lng_num !== '' && is_numeric($lat_num) && is_numeric($lng_num))
    && ((float)$lat_num >= -9.5 && (float)$lat_num <= -7.0)
    && ((float)$lng_num >= 114.0 && (float)$lng_num <= 116.5);

$map_url = $has_coordinates
    ? "https://maps.google.com/maps?q={$lat_num},{$lng_num}&z=15&output=embed"
    : "";
?>



<div id="loading-overlay" class="loading-overlay">
    <div class="spinner"></div>
</div>

<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <!--begin::Container-->
        <div class="container-fluid">
            <!--begin::Row-->
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Detail Data Siswa</h3>
                    <p class="small">Data Pokok Sekolah</p>
                </div>
            </div>
            <!--end::Row-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content Header-->
    <!--begin::App Content-->
    <div class="app-content">

        <!--begin::Container-->
        <div class="container-fluid">
            <!-- bagian atas -->

            <a href="index.php?menu=siswa_aktif" class="btn btn-link text-decoration-none"><i class="fas fa-angles-left"></i> Kembali</a>
            <a href="index.php?menu=kartu_siswa&id_siswa=<?= (int)$r['id_siswa'] ?>" target="_blank" class="btn btn-outline-primary btn-sm ms-2"><i class="bi bi-person-vcard"></i> Cetak Kartu</a>
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="data-siswa-tab" data-bs-toggle="tab" data-bs-target="#data-siswa" type="button" role="tab" aria-controls="data-siswa" aria-selected="true">DATA SISWA</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="ortu-tab" data-bs-toggle="tab" data-bs-target="#ortu" type="button" role="tab" aria-controls="ortu" aria-selected="false">ORANG TUA/WALI</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="denah-tab" data-bs-toggle="tab" data-bs-target="#denah" type="button" role="tab" aria-controls="denah" aria-selected="false">DENAH LOKASI</button>
                </li>
            </ul>
            <!--begin::Row-->
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="data-siswa" role="tabpanel" aria-labelledby="data-siswa-tab">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card justify-content-center align-items-center p-4 mb-4">

                                <?php if (!empty($r['foto']) && file_exists('assets/img/foto_siswa/' . $r['foto'])) : ?>
                                    <!-- Jika ada foto, tampilkan foto dari database -->
                                    <img src="assets/img/foto_siswa/<?= htmlspecialchars($r['foto']); ?>" alt="Foto Siswa" width="100">
                                <?php else : ?>
                                    <!-- Jika tidak ada foto, tampilkan gambar alternatif -->
                                    <img src="assets/img/<?= (strtoupper($r['jk'] ?? '') === 'P') ? 'wanita.png' : 'pria.png'; ?>" alt="" style="width:200px; height:200px; border:10px solid black; border-radius:50%" class="mb-4">
                                <?php endif; ?>

                                <h4><b><?= htmlspecialchars($r['nama'] ?? '') ?></b></h4>
                                <h5>Rombel : <b><?= htmlspecialchars($r['rombel'] ?? '-') ?></b></h5>
                                <h5>NIS/NISN : <b><?= htmlspecialchars($r['nipd'] ?? '') ?>/<?= htmlspecialchars($r['nisn'] ?? '') ?></b></h5>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="bg-dark p-2 text-white mb-3 text-center">
                            <b>DATA DIRI SISWA</b>
                        </div>
                        <div class="col-md-6">
                            <div class="card p-3">
                                <table class="table table-sm table-striped">
                                    <tr>
                                        <td>Tempat Lahir</td>
                                        <td>:</td>
                                        <td><?= $r['tempat_lahir'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Tanggal Lahir</td>
                                        <td>:</td>
                                        <td><?= $r['tanggal_lahir'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Jenis Kelamin</td>
                                        <td>:</td>
                                        <td><?= $r['jk'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>NIK</td>
                                        <td>:</td>
                                        <td><?= $r['nik'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Agama</td>
                                        <td>:</td>
                                        <td><?= $r['agama'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Alamat</td>
                                        <td>:</td>
                                        <td><?= $r['alamat'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>RT/RW</td>
                                        <td>:</td>
                                        <td><?= $r['rt_rw'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Dusun</td>
                                        <td>:</td>
                                        <td><?= $r['dusun'] ?></td>
                                    </tr>


                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card p-3">
                                <table class="table table-sm table-striped">
                                    <tr>
                                        <td>Kelurahan</td>
                                        <td>:</td>
                                        <td><?= $r['kelurahan'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Kecamatan</td>
                                        <td>:</td>
                                        <td><?= $r['kecamatan'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Kode Pos</td>
                                        <td>:</td>
                                        <td><?= $r['kode_pos'] ?></td>
                                    </tr>

                                    <tr>
                                        <td>Jenis Tinggal</td>
                                        <td>:</td>
                                        <td><?= $r['jenis_tinggal'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Transportasi</td>
                                        <td>:</td>
                                        <td><?= $r['transportasi'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Telepon</td>
                                        <td>:</td>
                                        <td><?= $r['telp'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>HP</td>
                                        <td>:</td>
                                        <td><?= $r['hp'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Email</td>
                                        <td>:</td>
                                        <td><?= $r['email'] ?></td>
                                    </tr>


                                </table>
                            </div>
                        </div>
                        <div class="bg-dark p-2 text-white mb-3 mt-4 text-center">
                            <b>DATA TAMBAHAN</b>
                        </div>
                        <div class="col-md-6">
                            <div class="card p-3">
                                <table class="table table-sm table-striped">
                                    <tr>
                                        <td>Sekolah Asal</td>
                                        <td>:</td>
                                        <td><?= $r['sekolah_asal'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Anak ke Berapa</td>
                                        <td>:</td>
                                        <td><?= $r['anak_ke_berapa'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Nomor KK</td>
                                        <td>:</td>
                                        <td><?= $r['no_kk'] ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card p-3">
                                <table class="table table-sm table-striped">
                                    <tr>
                                        <td>Berat Badan</td>
                                        <td>:</td>
                                        <td><?= $r['berat_badan'] ?> kg</td>
                                    </tr>
                                    <tr>
                                        <td>Tinggi Badan</td>
                                        <td>:</td>
                                        <td><?= $r['tinggi_badan'] ?> cm</td>
                                    </tr>
                                    <tr>
                                        <td>Jumlah Saudara Kandung</td>
                                        <td>:</td>
                                        <td><?= $r['jml_saudara_kandung'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Jarak Rumah ke Sekolah</td>
                                        <td>:</td>
                                        <td><?= $r['jarak_rumah_ke_sekolah_km'] ?> km</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!--end::Row-->
                </div>
                <div class="tab-pane fade show" id="ortu" role="tabpanel" aria-labelledby="ortu-tab">
                    <div class="card p-2 mb-2">
                        <div class="bg-dark p-2 text-white mb-3 text-center mb-0">
                            <b>DATA ORANG TUA/WALI</b>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card p-3">
                                <b>
                                    <h5>Data Ayah</h5>
                                </b>
                                <table class="table table-sm table-striped">
                                    <tr>
                                        <td>Nama Ayah</td>
                                        <td>:</td>
                                        <td><?= $r['nama_ayah'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Tahun Lahir Ayah</td>
                                        <td>:</td>
                                        <td><?= $r['tahun_lahir_ayah'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Pendidikan Ayah</td>
                                        <td>:</td>
                                        <td><?= $r['pendidikan_ayah'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Pekerjaan Ayah</td>
                                        <td>:</td>
                                        <td><?= $r['pekerjaan_ayah'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Penghasilan Ayah</td>
                                        <td>:</td>
                                        <td><?= $r['penghasilan_ayah'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>NIK Ayah</td>
                                        <td>:</td>
                                        <td><?= $r['nik_ayah'] ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card p-3">
                                <b>
                                    <h5>Data Ibu</h5>
                                </b>
                                <table class="table table-sm table-striped">
                                    <tr>
                                        <td>Nama Ibu</td>
                                        <td>:</td>
                                        <td><?= $r['nama_ibu'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Tahun Lahir Ibu</td>
                                        <td>:</td>
                                        <td><?= $r['tahun_lahir_ibu'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Pendidikan Ibu</td>
                                        <td>:</td>
                                        <td><?= $r['pendidikan_ibu'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Pekerjaan Ibu</td>
                                        <td>:</td>
                                        <td><?= $r['pekerjaan_ibu'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Penghasilan Ibu</td>
                                        <td>:</td>
                                        <td><?= $r['penghasilan_ibu'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>NIK Ibu</td>
                                        <td>:</td>
                                        <td><?= $r['nik_ibu'] ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card p-3 mt-2">
                                <b>
                                    <h5>Data Wali</h5>
                                </b>
                                <table class="table table-sm table-striped">
                                    <tr>
                                        <td>Nama Wali</td>
                                        <td>:</td>
                                        <td><?= $r['nama_wali'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Tahun Lahir Wali</td>
                                        <td>:</td>
                                        <td><?= $r['tahun_lahir_wali'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Pendidikan Wali</td>
                                        <td>:</td>
                                        <td><?= $r['pendidikan_wali'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Pekerjaan Wali</td>
                                        <td>:</td>
                                        <td><?= $r['pekerjaan_wali'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Penghasilan Wali</td>
                                        <td>:</td>
                                        <td><?= $r['penghasilan_wali'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>NIK Wali</td>
                                        <td>:</td>
                                        <td><?= $r['nik_wali'] ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade show" id="denah" role="tabpanel" aria-labelledby="denah-tab">
                    <div class="card p-2 mb-2">
                        <div class="bg-dark p-2 text-white mb-3 text-center mb-0">
                            <b>DENAH RUMAH SISWA</b>
                        </div>
                        <div class="card-body">
                            <?php if ($has_coordinates): ?>
                                <p><strong>Koordinat Lokasi:</strong> <span class="badge bg-success"><?= $latitude ?: 'Menggunakan Alamat' ?></span>, <span class="badge bg-success"><?= $longitude ?: 'Menggunakan Alamat' ?></span></p>
                                <p><strong>Alamat:</strong> <?= htmlspecialchars($r['alamat'] ?? '') ?></p>

                                <div class="ratio ratio-16x9" style="max-height: 500px;">
                                    <iframe
                                        src="<?= $map_url ?>"
                                        frameborder="0"
                                        style="border:0;"
                                        allowfullscreen=""
                                        loading="lazy"
                                        referrerpolicy="no-referrer-when-downgrade">
                                    </iframe>
                                </div>
                                <p class="mt-3 small text-muted">Denah ini ditampilkan menggunakan Google Maps Embed API. Akurasi peta <b>lebih tinggi</b> karena menggunakan koordinat Lintang dan Bujur yang tersimpan (jika tersedia).</p>
                            <?php else: ?>
                                <div class="alert alert-warning" role="alert">
                                    Data <b>Latitude</b> dan <b>Longitude</b> siswa tidak tersedia atau di luar area yang dikenal. Denah lokasi tidak dapat ditampilkan. Mohon lengkapi kolom <code>lintang</code> dan <code>bujur</code> untuk akurasi yang lebih baik.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            </div>
            <!--end::Container-->
        </div>
        <!--end::App Content-->
</main>

</div>