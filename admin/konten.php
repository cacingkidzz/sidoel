<div class="app-content">
    <div class="content-wrapper">
        <div class="container-fluid">
            <?php
            // Memastikan variabel $m ada untuk keamanan kode.
            $m = isset($_GET['menu']) ? $_GET['menu'] : 'beranda';

            // Menggunakan struktur switch case untuk memuat konten
            switch ($m) {
                // --- DASHBOARD ---
                case 'beranda':
                    include('konten/beranda.php');
                    break;

                // --- DATA POKOK & REFERENSI ---
                case 'data_sekolah':
                    include('konten/data_sekolah.php');
                    break;
                case 'kop_sekolah': // (dt_kop_sekolah) - Kop/kepala surat untuk laporan
                    include('konten/kop_sekolah.php');
                    break;
                case 'tahun_ajar':
                    include('konten/tahun_ajar.php');
                    break;
                case 'jurusan':
                    include('konten/jurusan.php');
                    break;
                case 'mapel': // (dt_mapel) - Penambahan baru
                    include('konten/mapel.php');
                    break;
                case 'guru':
                    include('konten/guru.php');
                    break;
                case 'user':
                    include('konten/user.php');
                    break;

                // --- PENERIMAAN SISWA BARU (PPDB) ---
                case 'tarik_siswa_ppdb': // (ppdb_calon_siswa -> dt_siswa)
                    include('konten/tarik_siswa_ppdb.php');
                    break;

                // --- DATA SISWA ---
                case 'siswa_aktif': // (dt_siswa) - Penambahan baru
                    include('konten/siswa_aktif.php');
                    break;
                case 'foto_siswa': // Manajemen foto siswa bulk
                    include('konten/foto_siswa.php');
                    break;
                case 'import_preview': // Preview hasil validasi import umum
                    include('konten/import_preview.php');
                    break;
                case 'penempatan_kelas': // (dt_kelas_siswa) - Penambahan baru
                    include('konten/penempatan_kelas.php');
                    break;
                case 'kenaikan_kelas': // (dt_kelas_siswa / dt_alumni) - Alur kenaikan & kelulusan
                    include('konten/kenaikan_kelas.php');
                    break;
                case 'siswa_tambah': // (dt_kelas_siswa) - Penambahan baru
                    include('konten/siswa_tambah.php');
                    break;
                case 'siswa_detail': // (dt_kelas_siswa) - Penambahan baru
                    include('konten/siswa_detail.php');
                    break;
                case 'siswa_edit': // (dt_kelas_siswa) - Penambahan baru
                    include('konten/siswa_edit.php');
                    break;
                case 'denah_siswa': // (dt_kelas_siswa) - Penambahan baru
                    include('konten/denah_siswa.php');
                    break;
                case 'alumni': // (dt_alumni) - Data alumni siswa
                    include('konten/alumni.php');
                    break;
                case 'info_kelas': // (dt_kelas, dt_wali_kelas, dt_siswa) - Informasi detail kelas/rombel
                    include('konten/info_kelas.php');
                    break;
                case 'detail_kelas': // (dt_kelas, dt_siswa) - Detail siswa per kelas
                    include('konten/detail_kelas.php');
                    break;

                // --- LAPORAN & CETAK ---
                case 'kartu_siswa': // (dt_siswa, dt_kelas) - Cetak kartu siswa (foto + QR)
                    include('konten/kartu_siswa.php');
                    break;

                // --- PENGATURAN KBM ---
                case 'guru_mapel': // (dt_guru_mapel) - Referensi mapel yang diampu tiap guru
                    include('konten/guru_mapel.php');
                    break;
                case 'sekretaris_kelas': // (dt_sekretaris) - Akun sekretaris kelas (KBM-3)
                    include('konten/sekretaris_kelas.php');
                    break;
                case 'kelas': // (dt_kelas) - Penambahan baru
                    include('konten/kelas.php');
                    break;
                case 'wali_kelas': // (dt_wali_kelas) - Penambahan baru
                    include('konten/wali_kelas.php');
                    break;
                case 'pengampu_mapel': // (dt_pengampu_mapel) - Penugasan guru pengampu per mapel
                    include('konten/pengampu_mapel.php');
                    break;
                case 'jadwal': // (dt_jadwal, dt_jam_pelajaran) - Jadwal pelajaran per tingkat
                    include('konten/jadwal.php');
                    break;
                case 'rekap_absensi': // (dt_absensi, dt_jurnal) - Rekap absensi kelas + cetak (KBM-3)
                    include('konten/rekap_absensi.php');
                    break;
                case 'rekap_jurnal': // (dt_jurnal, dt_jadwal) - Rekap jurnal mengajar per guru + cetak (KBM-3)
                    include('konten/rekap_jurnal.php');
                    break;
                case 'jurnal_kelas': // (dt_jurnal, dt_absensi) - Browse isi jurnal per kelas (KBM-3)
                    include('konten/jurnal_kelas.php');
                    break;
                case 'kalender_akademik': // (dt_kalender_akademik) - Kalender/agenda kegiatan per TA (KBM-5)
                    include('konten/kalender_akademik.php');
                    break;

                // --- ADMINISTRASI SISTEM ---
                case 'audit_log': // (dt_audit_log) - Log aktivitas pengguna
                    include('konten/audit_log.php');
                    break;
                case 'backup_restore': // Backup/restore DB (via mysqldump/mysql XAMPP)
                    include('konten/backup_restore.php');
                    break;
                case 'manajemen_ta': // Salin data (kelas/wali/pengampu) antar tahun ajaran
                    include('konten/manajemen_ta.php');
                    break;

                // --- AKUN ---
                case 'profil': // (dt_user) - Profil & ganti password sendiri
                    include('konten/profil.php');
                    break;
                case 'logout':
                    // File konten/logout.php tidak ada — proses logout lewat aksi
                    header("Location: aksi/aksi_login.php?aksi=logout");
                    exit;
                    break;

                // Jika tidak ada parameter menu atau case tidak ditemukan
                default:
                    include('konten/beranda.php');
                    break;
            }
            ?>
        </div>
    </div>
</div>