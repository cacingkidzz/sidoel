<?php
require_once __DIR__ . '/_init.php';
siswa_wajib_login();

$profil = siswa_profil_sekolah($koneksi);
$id_siswa = (int)$_SESSION['siswa']['id_siswa'];
$s = siswa_data($koneksi, $id_siswa);
if (!$s) {
    unset($_SESSION['siswa']);
    header('Location: login.php');
    exit;
}

$id_ta = (int)$s['id_tahun_ajaran'];
$kelas = siswa_kelas($koneksi, $id_siswa, $id_ta);
$kelas_label = siswa_kelas_label($kelas);

if (!function_exists('sv')) {
    function sv(array $s, string $key): string
    {
        return siswa_esc($s[$key] ?? '');
    }
}
if (!function_exists('sv_tahun')) {
    function sv_tahun(array $s, string $key): string
    {
        $v = trim((string)($s[$key] ?? ''));
        return ($v === '' || (int)$v === 0) ? '' : siswa_esc($v);
    }
}

$koord = siswa_koordinat($s['lintang'] ?? null, $s['bujur'] ?? null);

$judul = 'Ubah Data';
$active = 'edit';
$siswa = $s;
require __DIR__ . '/_header.php';
?>
<form method="post" action="simpan_profil.php" autocomplete="off" enctype="multipart/form-data">
    <div class="card ss-card mb-2">
        <div class="card-body">
            <div class="ss-section-title mb-2"><i class="bi bi-camera me-1"></i>Foto Siswa</div>
            <div class="d-flex align-items-center gap-3">
                <img src="<?= siswa_esc(siswa_foto_url($s)); ?>" alt="Foto" class="ss-photo" id="fotoPreview">
                <div class="flex-grow-1">
                    <input type="file" name="foto" id="fotoInput" class="form-control form-control-sm" accept="image/jpeg,image/png,image/gif">
                    <div class="form-text" style="font-size:.68rem;">JPG/PNG/GIF, maks 2 MB. Foto otomatis diperkecil ke 800 px.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card ss-card mb-2">
        <div class="card-body">
            <div class="ss-section-title mb-2"><i class="bi bi-geo-alt me-1"></i>Kontak &amp; Alamat</div>
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label">No. HP</label>
                    <input type="text" name="hp" class="form-control" maxlength="20" value="<?= sv($s, 'hp'); ?>" placeholder="08xxxxxxxxxx">
                </div>
                <div class="col-6">
                    <label class="form-label">Telepon</label>
                    <input type="text" name="telp" class="form-control" maxlength="20" value="<?= sv($s, 'telp'); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" maxlength="255" value="<?= sv($s, 'email'); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" class="form-control" rows="2" maxlength="255"><?= sv($s, 'alamat'); ?></textarea>
                </div>
                <div class="col-6">
                    <label class="form-label">RT/RW</label>
                    <input type="text" name="rt_rw" class="form-control" maxlength="50" value="<?= sv($s, 'rt_rw'); ?>" placeholder="001/002">
                </div>
                <div class="col-6">
                    <label class="form-label">Dusun</label>
                    <input type="text" name="dusun" class="form-control" maxlength="255" value="<?= sv($s, 'dusun'); ?>">
                </div>
                <div class="col-6">
                    <label class="form-label">Kelurahan/Desa</label>
                    <input type="text" name="kelurahan" class="form-control" maxlength="255" value="<?= sv($s, 'kelurahan'); ?>">
                </div>
                <div class="col-6">
                    <label class="form-label">Kecamatan</label>
                    <input type="text" name="kecamatan" class="form-control" maxlength="255" value="<?= sv($s, 'kecamatan'); ?>">
                </div>
                <div class="col-6">
                    <label class="form-label">Kode Pos</label>
                    <input type="text" name="kode_pos" class="form-control" maxlength="10" inputmode="numeric" value="<?= sv($s, 'kode_pos'); ?>">
                </div>
                <div class="col-6">
                    <label class="form-label">Jarak ke Sekolah (km)</label>
                    <input type="number" name="jarak_rumah_ke_sekolah_km" class="form-control" min="0" step="0.1" value="<?= sv($s, 'jarak_rumah_ke_sekolah_km'); ?>">
                </div>
                <div class="col-6">
                    <label class="form-label">Jenis Tinggal</label>
                    <input type="text" name="jenis_tinggal" class="form-control" maxlength="100" list="listTinggal" value="<?= sv($s, 'jenis_tinggal'); ?>">
                    <datalist id="listTinggal">
                        <option value="Bersama Orang Tua"><option value="Wali"><option value="Kost"><option value="Asrama"><option value="Panti Asuhan"><option value="Lainnya">
                    </datalist>
                </div>
                <div class="col-6">
                    <label class="form-label">Transportasi</label>
                    <input type="text" name="transportasi" class="form-control" maxlength="100" list="listTransport" value="<?= sv($s, 'transportasi'); ?>">
                    <datalist id="listTransport">
                        <option value="Jalan Kaki"><option value="Sepeda"><option value="Sepeda Motor"><option value="Mobil"><option value="Ojek"><option value="Antar Jemput"><option value="Lainnya">
                    </datalist>
                </div>
            </div>
        </div>
    </div>

    <div class="card ss-card mb-2">
        <div class="card-body">
            <div class="ss-section-title mb-2"><i class="bi bi-pin-map me-1"></i>Lokasi Rumah</div>
            <div id="map" style="width:100%;height:280px;border-radius:.6rem;background:#e9ecef;"></div>
            <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-2" onclick="getCurrentLocation(event)">
                <i class="bi bi-crosshair me-1"></i>Gunakan posisi saya saat ini
            </button>
            <div class="row g-2 mt-1">
                <div class="col-6">
                    <label class="form-label">Latitude</label>
                    <input type="text" id="latitude" name="latitude" class="form-control" readonly
                           value="<?= $koord ? siswa_esc(number_format($koord['lat'], 7, '.', '')) : ''; ?>">
                </div>
                <div class="col-6">
                    <label class="form-label">Longitude</label>
                    <input type="text" id="longitude" name="longitude" class="form-control" readonly
                           value="<?= $koord ? siswa_esc(number_format($koord['lng'], 7, '.', '')) : ''; ?>">
                </div>
            </div>
            <div class="form-text" style="font-size:.68rem;">Klik peta atau geser pin untuk menandai lokasi rumah.
                <a href="#" class="ms-1" onclick="clearLocation(event);return false;">Hapus titik</a>
            </div>
        </div>
    </div>

    <div class="card ss-card mb-2">
        <div class="card-body">
            <div class="ss-section-title mb-2"><i class="bi bi-heart-pulse me-1"></i>Data Fisik</div>
            <div class="row g-2">
                <div class="col-4">
                    <label class="form-label">Berat (kg)</label>
                    <input type="number" name="berat_badan" class="form-control" min="0" step="0.1" value="<?= sv($s, 'berat_badan'); ?>">
                </div>
                <div class="col-4">
                    <label class="form-label">Tinggi (cm)</label>
                    <input type="number" name="tinggi_badan" class="form-control" min="0" step="0.1" value="<?= sv($s, 'tinggi_badan'); ?>">
                </div>
                <div class="col-4">
                    <label class="form-label">Saudara</label>
                    <input type="number" name="jml_saudara_kandung" class="form-control" min="0" step="1" value="<?= sv($s, 'jml_saudara_kandung'); ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card ss-card mb-2">
        <div class="card-body">
            <div class="ss-section-title mb-2"><i class="bi bi-people me-1"></i>Orang Tua / Wali</div>
            <div class="accordion accordion-flush" id="accOrtu">
                <?php
                $orang = [
                    'Ayah' => ['nama_ayah', 'tahun_lahir_ayah', 'pendidikan_ayah', 'pekerjaan_ayah', 'penghasilan_ayah'],
                    'Ibu' => ['nama_ibu', 'tahun_lahir_ibu', 'pendidikan_ibu', 'pekerjaan_ibu', 'penghasilan_ibu'],
                    'Wali' => ['nama_wali', 'tahun_lahir_wali', 'pendidikan_wali', 'pekerjaan_wali', 'penghasilan_wali'],
                ];
                $no = 0;
                foreach ($orang as $label => $keys):
                    $no++;
                    $cid = 'ortu' . $no;
                ?>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button <?= $no === 1 ? '' : 'collapsed'; ?> py-2 small fw-semibold" type="button"
                                data-bs-toggle="collapse" data-bs-target="#<?= $cid; ?>">
                            Data <?= $label; ?>
                        </button>
                    </h2>
                    <div id="<?= $cid; ?>" class="accordion-collapse collapse <?= $no === 1 ? 'show' : ''; ?>" data-bs-parent="#accOrtu">
                        <div class="accordion-body px-0 pt-2">
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label">Nama <?= $label; ?></label>
                                    <input type="text" name="<?= $keys[0]; ?>" class="form-control" maxlength="255" value="<?= sv($s, $keys[0]); ?>">
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Tahun Lahir</label>
                                    <input type="number" name="<?= $keys[1]; ?>" class="form-control" min="1900" max="<?= date('Y'); ?>" step="1" value="<?= sv_tahun($s, $keys[1]); ?>">
                                </div>
                                <div class="col-8">
                                    <label class="form-label">Pendidikan</label>
                                    <input type="text" name="<?= $keys[2]; ?>" class="form-control" maxlength="100" value="<?= sv($s, $keys[2]); ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Pekerjaan</label>
                                    <input type="text" name="<?= $keys[3]; ?>" class="form-control" maxlength="100" value="<?= sv($s, $keys[3]); ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Penghasilan</label>
                                    <input type="text" name="<?= $keys[4]; ?>" class="form-control" maxlength="255" value="<?= sv($s, $keys[4]); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card ss-card mb-2">
        <div class="card-body">
            <div class="ss-section-title mb-2"><i class="bi bi-shield-lock me-1"></i>Keamanan (Opsional)</div>
            <p class="text-muted small mb-2">Kosongkan bila tidak ingin mengganti password.</p>
            <div class="row g-2">
                <div class="col-12">
                    <label class="form-label">Password Lama</label>
                    <input type="password" name="pass_lama" class="form-control" autocomplete="current-password">
                </div>
                <div class="col-6">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="pass_baru" class="form-control" autocomplete="new-password" minlength="6">
                </div>
                <div class="col-6">
                    <label class="form-label">Ulangi Password Baru</label>
                    <input type="password" name="pass_konfirm" class="form-control" autocomplete="new-password" minlength="6">
                </div>
            </div>
        </div>
    </div>

    <div class="d-grid gap-2 mb-3">
        <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-save me-1"></i>Simpan Perubahan</button>
        <a href="index.php" class="btn btn-light">Batal</a>
    </div>
</form>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= siswa_esc(GOOGLE_MAPS_API_KEY); ?>&amp;callback=initMap&amp;loading=async" defer></script>
<script>
    (function () {
        var input = document.getElementById('fotoInput');
        if (!input) return;
        input.addEventListener('change', function () {
            var file = this.files && this.files[0];
            if (!file) return;
            document.getElementById('fotoPreview').src = URL.createObjectURL(file);
        });
    })();

    var map = null;
    var marker = null;

    function isiKoordinat(lat, lng) {
        document.getElementById('latitude').value = lat.toFixed(7);
        document.getElementById('longitude').value = lng.toFixed(7);
    }

    function pasangMarker(lat, lng) {
        var pos = {lat: lat, lng: lng};
        if (!marker) {
            marker = new google.maps.Marker({
                position: pos,
                map: map,
                draggable: true,
                title: 'Lokasi Rumah'
            });
            google.maps.event.addListener(marker, 'dragend', function (e) {
                isiKoordinat(e.latLng.lat(), e.latLng.lng());
            });
        } else {
            marker.setPosition(pos);
            marker.setMap(map);
        }
        isiKoordinat(lat, lng);
    }

    function clearLocation(e) {
        if (e) e.preventDefault();
        document.getElementById('latitude').value = '';
        document.getElementById('longitude').value = '';
        if (marker) marker.setMap(null);
    }

    function initMap() {
        var lat = parseFloat(document.getElementById('latitude').value.replace(',', '.'));
        var lng = parseFloat(document.getElementById('longitude').value.replace(',', '.'));
        var ada = !isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180 && !(lat === 0 && lng === 0);
        var center = ada ? {lat: lat, lng: lng} : {lat: -8.6258946, lng: 115.1775938};

        map = new google.maps.Map(document.getElementById('map'), {
            center: center,
            zoom: ada ? 17 : 13,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false
        });

        if (ada) pasangMarker(lat, lng);

        map.addListener('click', function (e) {
            pasangMarker(e.latLng.lat(), e.latLng.lng());
        });
    }

    function getCurrentLocation(event) {
        event.preventDefault();
        if (!navigator.geolocation) {
            alert('Geolocation tidak didukung oleh browser Anda.');
            return;
        }
        navigator.geolocation.getCurrentPosition(function (position) {
            var lat = position.coords.latitude;
            var lng = position.coords.longitude;
            if (!map) return;
            map.setCenter({lat: lat, lng: lng});
            map.setZoom(18);
            pasangMarker(lat, lng);
        }, function (error) {
            alert('Tidak dapat mengambil lokasi. Pastikan izin lokasi diaktifkan: ' + error.message);
        }, {enableHighAccuracy: true, timeout: 10000, maximumAge: 0});
    }
</script>
<?php require __DIR__ . '/_footer.php'; ?>
