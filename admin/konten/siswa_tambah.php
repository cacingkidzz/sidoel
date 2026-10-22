 <!-- Google Maps API dengan async dan defer -->
 <div id="loading-overlay" class="loading-overlay">
     <div class="spinner"></div>
 </div>

 <?php
    require_once(__DIR__ . '/helper_siswa_form.php');
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
    ?>

 <main class="app-main">
     <!--begin::App Content Header-->
     <div class="app-content-header">
         <!--begin::Container-->
         <div class="container-fluid">
             <!--begin::Row-->
             <div class="row">
                 <div class="col-sm-6">
                     <h3 class="mb-0">Tambah Data Siswa</h3>
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
             <!--begin::Row-->
             <a href="index.php?menu=siswa_aktif" class="btn btn-link text-decoration-none"><i class="fas fa-angles-left"></i> Kembali</a>
             <div class="card p-4">

                 <div class="row">
                     <div class="col-12">

                         <form method="POST" enctype="multipart/form-data" action="aksi/aksi_siswa.php">
                             <div class="row">
                                 <div class="bg-dark p-2 text-white mb-3 text-center">
                                     <b>DATA DIRI SISWA</b>
                                 </div>
                                 <input type="text" name="id_tahun_ajar" id="" value="<?= $id_ta_aktif ?>" hidden>
                                 <div class="col-md-6">
                                     <!-- Nama -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="nama" class="col-md-2">Nama Siswa</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                 <input type="text" class="form-control" id="nama" name="nama" placeholder="Masukkan Nama">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Nipd -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="text" class="col-md-2">NIPD</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                                 <input type="text" class="form-control" id="text" name="nipd" placeholder="Masukkan NIPD">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- jenis kelamin -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="jenisKelamin" class="col-sm-2 col-form-label">Jenis Kelamin</label>
                                         <div class="col-sm-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fa-solid fa-venus-mars"></i></i></span>
                                                 <select class="form-select" id="jenisKelamin" name="jk">
                                                     <option selected disabled>Pilih...</option>
                                                    <option value="L">Laki-laki</option>
                                                    <option value="P">Perempuan</option>
                                                 </select>
                                             </div>
                                         </div>
                                     </div>

                                     <!-- NISN -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="text" class="col-md-2">NISN</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                                 <input type="text" class="form-control" id="text" name="nisn" placeholder="Masukkan NISN">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Tempat -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="tempat" class="col-md-2">Tempat Siswa</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-address-book"></i></span>
                                                 <input type="text" class="form-control" id="tempat" name="tempat" placeholder="Masukkan Tempat Lahir">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Tanggal Lahir -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="tanggal_lahir" class="col-md-2">Tanggal Lahir</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                                 <input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- NIK -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="nik" class="col-md-2">NIK</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                                 <input
                                                     type="number"
                                                     class="form-control"
                                                     id="nik"
                                                     name="nik"
                                                     placeholder="Masukkan NIK"
                                                     maxlength="16"
                                                     oninput="if(this.value.length > 16) this.value = this.value.slice(0, 16);">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- agama -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="agama" class="col-md-2">Agama</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-praying-hands"></i></span>
                                                 <select class="form-select" id="agama" name="agama">
                                                     <option selected disabled>Pilih Agama</option>
                                                     <option value="Islam">Islam</option>
                                                     <option value="Kristen">Kristen</option>
                                                     <option value="Katolik">Katolik</option>
                                                     <option value="Hindu">Hindu</option>
                                                     <option value="Buddha">Buddha</option>
                                                     <option value="Konghucu">Konghucu</option>
                                                 </select>
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Alamat -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="alamat" class="col-md-2">Alamat</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                                 <textarea class="form-control" id="alamat" name="alamat" placeholder="Masukkan Alamat"></textarea>
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Foto -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="foto" class="col-md-2">Foto</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-camera"></i></span>
                                                 <input type="file" class="form-control" id="foto" name="foto">
                                             </div>
                                         </div>
                                     </div>

                                 </div>

                                 <div class="col-md-6">

                                     <!-- RT/RW -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="rt_rw" class="col-md-2">RT/RW</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-map-signs"></i></span>
                                                 <input type="text" class="form-control" id="rt_rw" name="rt_rw" placeholder="Masukkan RT/RW (contoh: 05/02)">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Dusun -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="dusun" class="col-md-2">Dusun</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-home"></i></span>
                                                 <input type="text" class="form-control" id="dusun" name="dusun" placeholder="Masukkan Nama Dusun">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Kelurahan -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="kelurahan" class="col-md-2">Kelurahan</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-building"></i></span>
                                                 <input type="text" class="form-control" id="kelurahan" name="kelurahan" placeholder="Masukkan Nama Kelurahan">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Kecamatan -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="kecamatan" class="col-md-2">Kecamatan</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-city"></i></span>
                                                 <input type="text" class="form-control" id="kecamatan" name="kecamatan" placeholder="Masukkan Nama Kecamatan">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Kode Pos -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="kode_pos" class="col-md-2">Kode Pos</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-mail-bulk"></i></span>
                                                 <input
                                                     type="number"
                                                     class="form-control"
                                                     id="kode_pos"
                                                     name="kode_pos"
                                                     placeholder="Masukkan Kode Pos"
                                                     maxlength="5"
                                                     oninput="if(this.value.length > 5) this.value = this.value.slice(0, 5);">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Jenis Tinggal -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="jenis_tinggal" class="col-md-2">Jenis Tinggal</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-house-user"></i></span>
                                                 <select class="form-select" id="jenis_tinggal" name="jenis_tinggal">
                                                     <option selected disabled>Pilih Jenis Tinggal</option>
                                                     <option value="Orang Tua">Orang Tua</option>
                                                     <option value="Wali">Wali</option>
                                                     <option value="Asrama">Asrama</option>
                                                     <option value="Kos">Kos</option>
                                                     <option value="Lainnya">Lainnya</option>
                                                 </select>
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Transportasi -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="transportasi" class="col-md-2">Transportasi</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-bus"></i></span>
                                                 <select class="form-select" id="transportasi" name="transportasi">
                                                     <option selected disabled>Pilih Transportasi</option>
                                                     <option value="Jalan Kaki">Jalan Kaki</option>
                                                     <option value="Sepeda">Sepeda</option>
                                                     <option value="Sepeda Motor">Sepeda Motor</option>
                                                     <option value="Mobil Pribadi">Mobil Pribadi</option>
                                                     <option value="Angkutan Umum">Angkutan Umum</option>
                                                     <option value="Lainnya">Lainnya</option>
                                                 </select>
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Telepon -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="telp" class="col-md-2">Telepon</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                                 <input
                                                     type="number"
                                                     class="form-control"
                                                     id="telp"
                                                     name="telp"
                                                     placeholder="Masukkan Nomor Telepon"
                                                     maxlength="15"
                                                     oninput="if(this.value.length > 15) this.value = this.value.slice(0, 15);">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- HP -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="hp" class="col-md-2">HP</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                                                 <input
                                                     type="number"
                                                     class="form-control"
                                                     id="hp"
                                                     name="hp"
                                                     placeholder="Masukkan Nomor HP"
                                                     maxlength="15"
                                                     oninput="if(this.value.length > 15) this.value = this.value.slice(0, 15);">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Email -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="email" class="col-md-2">Email</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                                 <input
                                                     type="email"
                                                     class="form-control"
                                                     id="email"
                                                     name="email"
                                                     placeholder="Masukkan Alamat Email">
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                             <div class="row">
                                 <div class="bg-dark p-2 text-white mb-3 text-center mt-3">
                                     <b>DATA ORANG TUA/WALI</b>
                                 </div>
                                 <ul class="nav nav-tabs" id="myTab" role="tablist">
                                     <li class="nav-item" role="presentation">
                                         <button class="nav-link active" id="orangtua-tab" data-bs-toggle="tab" data-bs-target="#orangtua" type="button" role="tab" aria-controls="orangtua" aria-selected="true">ORANG TUA</button>
                                     </li>
                                     <li class="nav-item" role="presentation">
                                         <button class="nav-link" id="wali-tab" data-bs-toggle="tab" data-bs-target="#wali" type="button" role="tab" aria-controls="wali" aria-selected="false">WALI</button>
                                     </li>
                                 </ul>
                                 <div class="tab-content" id="myTabContent">
                                     <div class="tab-pane fade show active" id="orangtua" role="tabpanel" aria-labelledby="orangtua-tab">
                                         <div class="row">
                                             <div class="col-md-6">
                                                 <h5 class="my-3"><b>Data Ayah</b></h5>
                                                 <!-- Nama Ayah -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="nama_ayah" class="col-md-2">Nama Ayah</label>
                                                     <div class="col-md-10">
                                                         <div class="input-group">
                                                             <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                             <input type="text" class="form-control" id="nama_ayah" name="nama_ayah" placeholder="Masukkan Nama Ayah">
                                                         </div>
                                                     </div>
                                                 </div>

                                                 <!-- Tahun Lahir Ayah -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="tahun_lahir_ayah" class="col-md-2">Tahun Lahir Ayah</label>
                                                     <div class="col-md-10">
                                                         <div class="input-group">
                                                             <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                                             <input type="number" class="form-control" id="tahun_lahir_ayah" name="tahun_lahir_ayah" placeholder="Masukkan Tahun Lahir Ayah">
                                                         </div>
                                                     </div>
                                                 </div>

                                                 <!-- Pendidikan Ayah -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="pendidikan_ayah" class="col-md-2">Pendidikan Ayah</label>
                                                     <div class="col-md-10">
                                                         <div class="input-group">
                                                             <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>
                                                             <input type="text" class="form-control" id="pendidikan_ayah" name="pendidikan_ayah" placeholder="Masukkan Pendidikan Ayah">
                                                         </div>
                                                     </div>
                                                 </div>

                                                 <!-- Pekerjaan Ayah -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="pekerjaan_ayah" class="col-md-2">Pekerjaan Ayah</label>
                                                     <div class="col-md-10">
                                                         <select class="form-control" id="pekerjaan_ayah" name="pekerjaan_ayah">
                                                             <option value="">Pilih Pekerjaan Ayah</option>
                                                             <option value="swasta">Pekerja Swasta</option>
                                                             <option value="wiraswasta">Wiraswasta</option>
                                                             <option value="pns">PNS</option>
                                                             <option value="tentara">Tentara</option>
                                                             <option value="polisi">Polisi</option>
                                                             <option value="lainnya">Lainnya</option>
                                                         </select>
                                                     </div>
                                                 </div>

                                                 <!-- Penghasilan Ayah -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="penghasilan_ayah" class="col-md-2">Penghasilan Ayah</label>
                                                     <div class="col-md-10">
                                                         <select class="form-control" id="penghasilan_ayah" name="penghasilan_ayah">
                                                             <option value="">Pilih Penghasilan Ayah</option>
                                                             <option value="0">Tidak Berpenghasilan</option>
                                                             <option value="500000-999000">Rp 500.000 - Rp 999.000</option>
                                                             <option value="1000000-1999000">Rp 1.000.000 - Rp 1.999.000</option>
                                                             <option value="2000000-2999999">Rp 2.000.000 - Rp 2.999.999</option>
                                                             <option value="3000000-4999999">Rp 3.000.000 - Rp 4.999.999</option>
                                                             <option value="5000000">Rp 5.000.000 ke atas</option>
                                                         </select>
                                                     </div>
                                                 </div>

                                                 <!-- NIK Ayah -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="nik_ayah" class="col-md-2">NIK Ayah</label>
                                                     <div class="col-md-10">
                                                         <div class="input-group">
                                                             <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                                             <input type="text" class="form-control" id="nik_ayah" name="nik_ayah" placeholder="Masukkan NIK Ayah" maxlength="16" pattern="\d{16}">
                                                         </div>
                                                     </div>
                                                 </div>
                                             </div>
                                             <div class="col-md-6">
                                                 <h5 class="my-3"><b>Data Ibu</b></h5>
                                                 <!-- Nama Ibu -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="nama_ibu" class="col-md-2">Nama Ibu</label>
                                                     <div class="col-md-10">
                                                         <div class="input-group">
                                                             <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                             <input type="text" class="form-control" id="nama_ibu" name="nama_ibu" placeholder="Masukkan Nama Ibu">
                                                         </div>
                                                     </div>
                                                 </div>

                                                 <!-- Tahun Lahir Ibu -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="tahun_lahir_ibu" class="col-md-2">Tahun Lahir Ibu</label>
                                                     <div class="col-md-10">
                                                         <div class="input-group">
                                                             <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                                             <input type="number" class="form-control" id="tahun_lahir_ibu" name="tahun_lahir_ibu" placeholder="Masukkan Tahun Lahir Ibu">
                                                         </div>
                                                     </div>
                                                 </div>

                                                 <!-- Pendidikan Ibu -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="pendidikan_ibu" class="col-md-2">Pendidikan Ibu</label>
                                                     <div class="col-md-10">
                                                         <div class="input-group">
                                                             <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>
                                                             <input type="text" class="form-control" id="pendidikan_ibu" name="pendidikan_ibu" placeholder="Masukkan Pendidikan Ibu">
                                                         </div>
                                                     </div>
                                                 </div>

                                                 <!-- Pekerjaan Ibu -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="pekerjaan_ibu" class="col-md-2">Pekerjaan Ibu</label>
                                                     <div class="col-md-10">
                                                         <select class="form-control" id="pekerjaan_ibu" name="pekerjaan_ibu">
                                                             <option value="">Pilih Pekerjaan Ibu</option>
                                                             <option value="swasta">Pekerja Swasta</option>
                                                             <option value="wiraswasta">Wiraswasta</option>
                                                             <option value="pns">PNS</option>
                                                             <option value="tentara">Tentara</option>
                                                             <option value="polisi">Polisi</option>
                                                             <option value="lainnya">Lainnya</option>
                                                         </select>
                                                     </div>
                                                 </div>

                                                 <!-- Penghasilan Ibu -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="penghasilan_ibu" class="col-md-2">Penghasilan Ibu</label>
                                                     <div class="col-md-10">
                                                         <select class="form-control" id="penghasilan_ibu" name="penghasilan_ibu">
                                                             <option value="">Pilih Penghasilan Ibu</option>
                                                             <option value="0">Tidak Berpenghasilan</option>
                                                             <option value="500000-999000">Rp 500.000 - Rp 999.000</option>
                                                             <option value="1000000-1999000">Rp 1.000.000 - Rp 1.999.000</option>
                                                             <option value="2000000-2999999">Rp 2.000.000 - Rp 2.999.999</option>
                                                             <option value="3000000-4999999">Rp 3.000.000 - Rp 4.999.999</option>
                                                             <option value="5000000">Rp 5.000.000 ke atas</option>
                                                         </select>
                                                     </div>
                                                 </div>

                                                 <!-- NIK Ibu -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="nik_ibu" class="col-md-2">NIK Ibu</label>
                                                     <div class="col-md-10">
                                                         <div class="input-group">
                                                             <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                                             <input type="text" class="form-control" id="nik_ibu" name="nik_ibu" placeholder="Masukkan NIK Ibu" maxlength="16" pattern="\d{16}">
                                                         </div>
                                                     </div>
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                     <div class="tab-pane fade" id="wali" role="tabpanel" aria-labelledby="wali-tab">
                                         <div class="row">
                                             <div class="col-md-6">
                                                 <h5 class="my-3"><b>Data Wali</b></h5>
                                                 <!-- Nama Wali -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="nama_wali" class="col-md-2">Nama Wali</label>
                                                     <div class="col-md-10">
                                                         <div class="input-group">
                                                             <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                             <input type="text" class="form-control" id="nama_wali" name="nama_wali" placeholder="Masukkan Nama Wali">
                                                         </div>
                                                     </div>
                                                 </div>

                                                 <!-- Tahun Lahir Wali -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="tahun_lahir_wali" class="col-md-2">Tahun Lahir Wali</label>
                                                     <div class="col-md-10">
                                                         <div class="input-group">
                                                             <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                                             <input type="number" class="form-control" id="tahun_lahir_wali" name="tahun_lahir_wali" placeholder="Masukkan Tahun Lahir Wali">
                                                         </div>
                                                     </div>
                                                 </div>

                                                 <!-- Pendidikan Wali -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="pendidikan_wali" class="col-md-2">Pendidikan Wali</label>
                                                     <div class="col-md-10">
                                                         <div class="input-group">
                                                             <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>
                                                             <input type="text" class="form-control" id="pendidikan_wali" name="pendidikan_wali" placeholder="Masukkan Pendidikan Wali">
                                                         </div>
                                                     </div>
                                                 </div>

                                                 <!-- Pekerjaan Wali -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="pekerjaan_wali" class="col-md-2">Pekerjaan Wali</label>
                                                     <div class="col-md-10">
                                                         <select class="form-control" id="pekerjaan_wali" name="pekerjaan_wali">
                                                             <option value="">Pilih Pekerjaan Wali</option>
                                                             <option value="swasta">Pekerja Swasta</option>
                                                             <option value="wiraswasta">Wiraswasta</option>
                                                             <option value="pns">PNS</option>
                                                             <option value="tentara">Tentara</option>
                                                             <option value="polisi">Polisi</option>
                                                             <option value="lainnya">Lainnya</option>
                                                         </select>
                                                     </div>
                                                 </div>

                                                 <!-- Penghasilan Wali -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="penghasilan_wali" class="col-md-2">Penghasilan Wali</label>
                                                     <div class="col-md-10">
                                                         <select class="form-control" id="penghasilan_wali" name="penghasilan_wali">
                                                             <option value="">Pilih Penghasilan Wali</option>
                                                             <option value="0">Tidak Berpenghasilan</option>
                                                             <option value="500000-999000">Rp 500.000 - Rp 999.000</option>
                                                             <option value="1000000-1999000">Rp 1.000.000 - Rp 1.999.000</option>
                                                             <option value="2000000-2999999">Rp 2.000.000 - Rp 2.999.999</option>
                                                             <option value="3000000-4999999">Rp 3.000.000 - Rp 4.999.999</option>
                                                             <option value="5000000">Rp 5.000.000 ke atas</option>
                                                         </select>
                                                     </div>
                                                 </div>

                                                 <!-- NIK Wali -->
                                                 <div class="mb-3 row align-items-center">
                                                     <label for="nik_wali" class="col-md-2">NIK Wali</label>
                                                     <div class="col-md-10">
                                                         <div class="input-group">
                                                             <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                                             <input type="text" class="form-control" id="nik_wali" name="nik_wali" placeholder="Masukkan NIK Wali" maxlength="16" pattern="\d{16}">
                                                         </div>
                                                     </div>
                                                 </div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                             <div class="row">
                                 <div class="bg-dark p-2 text-white mb-3 text-center mt-3">
                                     <b>DATA TAMBAHAN</b>
                                 </div>
                                 <div class="col-md 6">
                                     <!-- Rombel -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="jurusan" class="col-md-2">Jurusan</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>

                                                 <select class="form-control" id="jurusan" name="jurusan">
                                                     <option value="">Pilih Jurusan</option>
                                                     <?php
                                                        // Query untuk mengambil data Jurusan
                                                        $query_jurusan = mysqli_query($koneksi, "SELECT nama_jurusan FROM dt_jurusan ORDER BY nama_jurusan ASC");

                                                        while ($data_jurusan = mysqli_fetch_assoc($query_jurusan)) {
                                                            // Nilai (value) yang dikirim adalah nama_jurusan
                                                            $nama_j = htmlspecialchars($data_jurusan['nama_jurusan']);
                                                            // Pre-select untuk mode EDIT
                                                            $selected = (isset($data_siswa) && getData('jurusan', $data_siswa) == $nama_j) ? 'selected' : '';

                                                            echo "<option value='{$nama_j}' {$selected}>{$nama_j}</option>";
                                                        }
                                                        ?>
                                                 </select>
                                             </div>
                                         </div>
                                     </div>
                                     <div class="mb-3 row align-items-center">
                                         <label for="rombel" class="col-md-2">Rombel/Kelas</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-users"></i></span>

                                                 <select class="form-control" id="rombel" name="rombel">
                                                     <option value="">Pilih Rombel/Kelas</option>
                                                     <?php
                                                        // Query untuk mengambil data Kelas (Rombel)
                                                        // ASUMSI dt_kelas memiliki kolom 'tingkat' dan 'nama_kelas'
                                                        $query_kelas = mysqli_query($koneksi, "SELECT * FROM dt_kelas WHERE id_tahun_ajaran = $id_ta_aktif ORDER BY tingkat ASC, nama_kelas ASC");

                                                        while ($data_kelas = mysqli_fetch_assoc($query_kelas)) {
                                                            // Menggabungkan tingkat dan nama_kelas
                                                            $rombel_text = htmlspecialchars($data_kelas['tingkat'] . " - " . $data_kelas['nama_kelas']);

                                                            // Logika pre-select untuk mode EDIT
                                                            $selected = (isset($data_siswa) && getData('rombel', $data_siswa) == $rombel_text) ? 'selected' : '';

                                                            // Nilai (value) yang dikirim adalah string gabungan
                                                            echo "<option value='{$rombel_text}' {$selected}>{$rombel_text}</option>";
                                                        }
                                                        ?>
                                                 </select>
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Sekolah Asal -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="sekolah_asal" class="col-md-2">Sekolah Asal</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-school"></i></span>
                                                 <input type="text" class="form-control" id="sekolah_asal" name="sekolah_asal" placeholder="Masukkan Nama Sekolah Asal">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Anak Ke Berapa -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="anak_ke" class="col-md-2">Anak Ke Berapa</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-child"></i></span>
                                                 <input type="number" class="form-control" id="anak_ke" name="anak_ke" placeholder="Masukkan Anak Ke Berapa">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Nomor KK -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="no_kk" class="col-md-2">Nomor KK</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                                 <input type="text" class="form-control" id="no_kk" name="no_kk" placeholder="Masukkan Nomor KK" maxlength="16" pattern="\d{16}">
                                             </div>
                                         </div>
                                     </div>

                                 </div>
                                 <div class="col-md 6">
                                     <!-- Berat Badan -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="berat_badan" class="col-md-2">Berat Badan (kg)</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-weight"></i></span>
                                                 <input type="number" class="form-control" id="berat_badan" name="berat_badan" placeholder="Masukkan Berat Badan" step="0.1" min="0">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Tinggi Badan -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="tinggi_badan" class="col-md-2">Tinggi Badan (cm)</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-ruler-vertical"></i></span>
                                                 <input type="number" class="form-control" id="tinggi_badan" name="tinggi_badan" placeholder="Masukkan Tinggi Badan" step="1" min="0">
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Jumlah Saudara Kandung -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="jml_saudara_kandung" class="col-md-2">Jumlah Saudara Kandung</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-users"></i></span>
                                                 <input type="number" class="form-control" id="jml_saudara_kandung" name="jml_saudara_kandung" placeholder="Masukkan Jumlah Saudara Kandung" min="0">
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                             <div class="row">
                                 <div class="bg-dark p-2 text-white mb-3 text-center mt-3">
                                     <b>LOKASI RUMAH SISWA</b>
                                 </div>
                                 <div class="col-md-7">
                                     <div id="map" style="width: 100%; height: 500px;"></div>
                                     <div class="d-flex justify-content-center align-items-center">
                                         <button class="btn btn-dark mt-2" onclick="getCurrentLocation(event)">Posisi saya saat ini!</button>
                                     </div>
                                     <br>
                                     <p class="mb-3">*Silahkan klik pada peta untuk menentukan titik lokasi rumah atau apabila anda sudah berada di rumah Klik tombol <b>Posisi saya saat ini!</b> untuk otomatis mendapatkan titik lokasi rumah</p>
                                     <!-- <input type="text" id="latitude" name="latitude" placeholder="Latitude" readonly>
                                        <input type="text" id="longitude" name="longitude" placeholder="Longitude" readonly> -->
                                 </div>
                                 <div class="col-md-5">
                                     <!-- Longitude -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="longitude" class="col-md-2">Longitude</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                                 <input type="text" class="form-control" id="longitude" name="longitude" placeholder="Masukkan Longitude" pattern="^-?\d+(\.\d+)?$" title="Harus berupa angka desimal, contoh: 115.12345" readonly>
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Latitude -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="latitude" class="col-md-2">Latitude</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                                 <input type="text" class="form-control" id="latitude" name="latitude" placeholder="Masukkan Latitude" pattern="^-?\d+(\.\d+)?$" title="Harus berupa angka desimal, contoh: -8.12345" readonly>
                                             </div>
                                         </div>
                                     </div>

                                     <!-- Jarak dari Rumah ke Sekolah -->
                                     <div class="mb-3 row align-items-center">
                                         <label for="jarak_rumah_sekolah" class="col-md-2">Jarak Rumah ke Sekolah (km)</label>
                                         <div class="col-md-10">
                                             <div class="input-group">
                                                 <span class="input-group-text"><i class="fas fa-route"></i></span>
                                                 <input type="number" class="form-control" id="jarak_rumah_sekolah" name="jarak_rumah_sekolah" placeholder="Masukkan Jarak (km)" step="0.1" min="0">
                                             </div>
                                         </div>
                                     </div>

                                 </div>
                             </div>
                             <input
                                 type="submit"
                                 name="submit" value="SIMPAN DATA"
                                 class="btn btn-primary w-100">
                         </form>


                     </div>
                 </div>
                 <!--end::Row-->
             </div>
         </div>
         <!--end::Container-->
     </div>
     <!--end::App Content-->
 </main>



 <!-- Script -->
 <script>
     function toggleKPSInput(value) {
         const noKpsGroup = document.getElementById('no_kps_group');
         if (value === '1') {
             noKpsGroup.style.display = 'flex';
         } else {
             noKpsGroup.style.display = 'none';
             document.getElementById('no_kps').value = ''; // Clear input if "Tidak" is selected
         }
     }
 </script>
 <script src="https://maps.googleapis.com/maps/api/js?key=<?= GOOGLE_MAPS_API_KEY ?>&callback=initMap" async defer></script>
 <script>
     let map; // Variabel untuk peta
     let marker; // Variabel untuk marker

     // Fungsi inisialisasi peta
     function initMap() {
         const initialLocation = {
             lat: -8.6258946,
             lng: 115.1775938
         }; // Lokasi awal (Badung, Bali)

         // Inisialisasi peta di lokasi awal
         map = new google.maps.Map(document.getElementById('map'), {
             center: initialLocation,
             zoom: 15,
         });

         // Tambahkan marker di lokasi awal
         marker = new google.maps.Marker({
             position: initialLocation,
             map: map,
             title: "Lokasi Awal (Badung)",
         });
         // Pastikan peta dirender ulang setelah konten muncul
         setTimeout(() => {
             google.maps.event.trigger(map, 'resize');
         }, 500);

         // Event listener untuk menangkap klik pada peta
         map.addListener("click", function(event) {
             const clickedLocation = {
                 lat: event.latLng.lat(),
                 lng: event.latLng.lng()
             };

             // Perbarui posisi marker ke lokasi yang diklik
             marker.setPosition(clickedLocation);

             // Perbarui nilai di input form
             document.getElementById("latitude").value = clickedLocation.lat;
             document.getElementById("longitude").value = clickedLocation.lng;

             // Perbarui judul marker
             marker.setTitle("Lokasi Dipilih");
         });
     }

     // Fungsi untuk mendapatkan lokasi pengguna
     function getCurrentLocation(event) {
         event.preventDefault(); // Mencegah tombol memicu pengiriman form
         if (navigator.geolocation) {
             navigator.geolocation.getCurrentPosition(function(position) {
                 const latitude = position.coords.latitude;
                 const longitude = position.coords.longitude;

                 // Log lokasi yang diterima
                 console.log("Latitude: " + latitude);
                 console.log("Longitude: " + longitude);

                 // Perbarui nilai di input form
                 document.getElementById("latitude").value = latitude;
                 document.getElementById("longitude").value = longitude;

                 // Lokasi saat ini
                 const currentLocation = {
                     lat: latitude,
                     lng: longitude
                 };

                 // Perbarui posisi peta dan marker
                 map.setCenter(currentLocation); // Pusatkan peta ke lokasi saat ini
                 marker.setPosition(currentLocation); // Pindahkan marker ke lokasi saat ini
                 marker.setTitle("Lokasi Rumah Siswa");

                 // Zoom peta ke level lebih dekat
                 map.setZoom(18);
             }, function(error) {
                 alert(
                     "Tidak dapat mengambil lokasi. Pastikan izin lokasi diaktifkan: " +
                     error.message
                 );
             });
         } else {
             alert("Geolocation tidak didukung oleh browser Anda.");
         }
     }

     // Panggil fungsi initMap saat halaman selesai dimuat
     window.onload = initMap;
 </script>