<?php
/**
 * Modul kontrol akses berbasis level user.
 * Dipakai oleh index.php (gating menu) & aksi/akses_guard.php (gating aksi).
 */

// Izin menu per level user. '*' = semua menu.
const AKSES_MENU = [
    'Admin' => '*',
    // Akademik & KBM: data master akademik, penugasan, jadwal, monitoring, dan data siswa.
    'Kurikulum' => [
        'beranda',
        'data_sekolah', 'kop_sekolah', 'tahun_ajar', 'jurusan', 'mapel', 'kelas', 'guru',
        'guru_mapel', 'pengampu_mapel', 'wali_kelas', 'sekretaris_kelas',
        'jadwal', 'kalender_akademik',
        'dashboard_wali_kelas', 'rekap_absensi', 'rekap_jurnal', 'jurnal_kelas',
        'siswa_aktif', 'kelengkapan_siswa', 'siswa_tambah', 'siswa_detail', 'siswa_edit',
        'import_preview', 'penempatan_kelas', 'kenaikan_kelas', 'info_kelas', 'detail_kelas', 'rekap_kelas',
        'denah_siswa', 'alumni', 'tarik_siswa_ppdb', 'foto_siswa', 'kartu_siswa',
        'spp_tarif', 'spp_tagihan', 'spp_pembayaran', 'spp_rekap',
    ],
    // Kesiswaan: kelas, siswa, PPDB, alumni, penugasan wali/sekretaris, monitoring kelas.
    'Kesiswaan' => [
        'beranda', 'kelas',
        'wali_kelas', 'sekretaris_kelas',
        'dashboard_wali_kelas', 'rekap_absensi', 'jurnal_kelas',
        'siswa_aktif', 'kelengkapan_siswa', 'siswa_tambah', 'siswa_detail', 'siswa_edit',
        'import_preview', 'penempatan_kelas', 'kenaikan_kelas', 'info_kelas', 'detail_kelas', 'rekap_kelas',
        'denah_siswa', 'alumni', 'tarik_siswa_ppdb', 'foto_siswa', 'kartu_siswa',
        'spp_rekap',
    ],
    'Guru' => [
        'beranda', 'dashboard_wali_kelas', 'info_kelas', 'detail_kelas', 'denah_siswa', 'siswa_aktif', 'siswa_detail',
    ],
];

// Menu yang selalu diizinkan bagi siapa pun yang sudah login.
const AKSES_MENU_BEBAS = ['beranda', 'logout', 'aktivitas_ppdb', 'profil'];

// Peta file aksi => menu (izin aksi diturunkan dari izin menu).
const AKSES_AKSI_MENU = [
    'aksi_tahun_ajar.php'   => 'tahun_ajar',
    'aksi_sekolah.php'      => 'data_sekolah',
    'aksi_kop_sekolah.php'  => 'kop_sekolah',
    'aksi_jurusan.php'      => 'jurusan',
    'aksi_mapel.php'        => 'mapel',
    'aksi_guru_mapel.php'   => 'guru_mapel',
    'aksi_sekretaris.php'   => 'sekretaris_kelas',
    'aksi_kelas.php'        => 'kelas',
    'aksi_guru.php'         => 'guru',
    'aksi_user.php'         => 'user',
    'aksi_wali_kelas.php'   => 'wali_kelas',
    'aksi_pengampu_mapel.php' => 'pengampu_mapel',
    'export_pengampu.php'   => 'pengampu_mapel',
    'import_pengampu.php'   => 'pengampu_mapel',
    'aksi_jadwal.php'       => 'jadwal',
    'aksi_jam_pelajaran.php' => 'jadwal',
    'export_jadwal.php'     => 'jadwal',
    'import_jadwal.php'     => 'jadwal',
    'aksi_kalender.php'     => 'kalender_akademik',
    'aksi_siswa.php'        => 'siswa_aktif',
    'aksi_alumni.php'       => 'alumni',
    'tarik_ppdb.php'        => 'tarik_siswa_ppdb',
    'import_siswa.php'      => 'siswa_aktif',
    'export_siswa.php'      => 'siswa_aktif',
    'aksi_foto_siswa.php'   => 'foto_siswa',
    'import_general.php'    => 'siswa_aktif',
    'import_guru.php'       => 'guru',
    'export_guru.php'       => 'guru',
    'export_kelas.php'      => 'info_kelas',
    'generatenis.php'       => 'penempatan_kelas',
    'alokasi_kelas.php'     => 'penempatan_kelas',
    'alokasi_otomatis.php'  => 'penempatan_kelas',
    'pindah_kelas.php'      => 'penempatan_kelas',
    'naik_kelas.php'        => 'kenaikan_kelas',
    'aksi_profil.php'       => 'profil',
    'aksi_audit.php'        => 'audit_log',
    'aksi_backup.php'       => 'backup_restore',
    'aksi_manajemen_ta.php' => 'manajemen_ta',
    'aksi_spp_tarif.php'    => 'spp_tarif',
    'aksi_spp_tagihan.php'  => 'spp_tagihan',
    'aksi_spp_pembayaran.php' => 'spp_pembayaran',
];

if (!function_exists('boleh_menu')) {
    function boleh_menu($level, $menu)
    {
        if ($menu === '' || $menu === null) {
            return false;
        }
        if (in_array($menu, AKSES_MENU_BEBAS, true)) {
            return true;
        }
        $izin = AKSES_MENU[$level] ?? null;
        if ($izin === '*') {
            return true;
        }
        if (is_array($izin)) {
            return in_array($menu, $izin, true);
        }
        return false;
    }
}

if (!function_exists('ada_menu_izin')) {
    // True bila minimal satu menu pada daftar diizinkan untuk level tsb.
    function ada_menu_izin($level, $daftar_menu)
    {
        foreach ($daftar_menu as $m) {
            if (boleh_menu($level, $m)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('boleh_aksi')) {
    function boleh_aksi($level, $nama_file)
    {
        $menu = AKSES_AKSI_MENU[$nama_file] ?? null;
        if ($menu === null) {
            return true; // aksi tak terpetakan (mis. login) dibiarkan
        }
        return boleh_menu($level, $menu);
    }
}
