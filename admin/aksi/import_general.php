<?php
/**
 * Handler Import Umum (generalized import).
 * Dukung multiple entitas melalui ?entity=siswa|guru|jadwal|pengampu|kelas|mapel|user|alumni|...
 * Alur: POST file → aksi=preview (validasi, simpan ke session) → halaman preview → aksi=execute (insert transaksional).
 */
require_once(__DIR__ . '/akses_guard.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('../koneksi.php');
require_once('../konten/helper_import_export.php');

$entity = $_GET['entity'] ?? '';
$aksi   = $_POST['aksi'] ?? $_GET['aksi'] ?? '';

if ($entity === '') {
    $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Entitas import tidak ditentukan.'];
    header("Location: ../index.php?menu=beranda");
    exit;
}

// Konfigurasi entitas yang didukung. Tambahkan entitas baru di sini.
function ie_entity_config($entity)
{
    $cfgs = [];

    // ---------- SISWA ----------
    $cfgs['siswa'] = [
        'table'     => 'dt_siswa',
        'menu'      => 'siswa_aktif',
        'label'     => 'Data Siswa',
        'identity'  => ['nipd', 'nisn'],
        'required'  => ['nama', 'nipd'],
        'defaults'  => ['id_tahun_ajaran' => 'session_ta_id'],
        'map' => [
            'nama' => 'nama', 'nipd' => 'nipd', 'jk' => 'jk', 'nisn' => 'nisn',
            'tempatlahir' => 'tempat_lahir', 'tanggallahir' => 'tanggal_lahir',
            'nik' => 'nik', 'agama' => 'agama', 'alamat' => 'alamat',
            'rtrw' => 'rt_rw', 'dusun' => 'dusun', 'kelurahan' => 'kelurahan',
            'kecamatan' => 'kecamatan', 'kodepos' => 'kode_pos',
            'namayah' => 'nama_ayah', 'tahunlahiyah' => 'tahun_lahir_ayah',
            'pendidikanayah' => 'pendidikan_ayah', 'pekerjaanayah' => 'pekerjaan_ayah',
            'penghasilanayah' => 'penghasilan_ayah', 'nikayah' => 'nik_ayah',
            'namabibu' => 'nama_ibu', 'tahunlaihibu' => 'tahun_lahir_ibu',
            'pendidikanibu' => 'pendidikan_ibu', 'pekerjaanibu' => 'pekerjaan_ibu',
            'penghasilanibu' => 'penghasilan_ibu', 'nikibu' => 'nik_ibu',
            'namawali' => 'nama_wali', 'tahunlahirwali' => 'tahun_lahir_wali',
            'pendidikanwali' => 'pendidikan_wali', 'pekerjaanwali' => 'pekerjaan_wali',
            'penghasilanwali' => 'penghasilan_wali', 'nikwali' => 'nik_wali',
            'jurusan' => 'jurusan', 'rombel' => 'rombel', 'sekolahasal' => 'sekolah_asal',
            'anakeberapa' => 'anak_ke_berapa', 'lintang' => 'lintang', 'bujur' => 'bujur',
            'nokk' => 'no_kk', 'beratbadan' => 'berat_badan', 'tinggibadan' => 'tinggi_badan',
            'jmlsaudarakandung' => 'jml_saudara_kandung', 'jarakrumahkekolahkkm' => 'jarak_rumah_ke_sekolah_km',
            'password' => 'password', 'foto' => 'foto',
        ],
    ];

    // ---------- GURU ----------
    $cfgs['guru'] = [
        'table'     => 'dt_guru',
        'menu'      => 'guru',
        'label'     => 'Data Guru',
        'identity'  => ['nip', 'nuptk'],
        'required'  => ['namaguru'],
        'map' => [
            'namaguru' => 'nama_guru', 'nuptk' => 'nuptk', 'jk' => 'jk',
            'tempatlahir' => 'tempat_lahir', 'tanggallahir' => 'tanggal_lahir',
            'nip' => 'nip', 'statuskepegawaian' => 'status_kepegawaian', 'agama' => 'agama',
            'alamat' => 'alamat', 'desakelurahan' => 'desa_kelurahan', 'kecamatan' => 'kecamatan',
            'lulusan' => 'lulusan', 'jurusan' => 'jurusan', 'hp' => 'hp', 'email' => 'email',
            'tugastambahan' => 'tugas_tambahan', 'skpengangkatan' => 'sk_pengangkatan',
            'tmptpengangkatan' => 'tmt_pengangkatan', 'lembagapengangkatan' => 'lembaga_pengangkatan',
            'namaibukandung' => 'nama_ibu_kandung', 'statusperkawinan' => 'status_perkawinan',
            'namasuamiistri' => 'nama_suami_istri', 'sudahlisensikepsek' => 'sudah_lisensi_kepala_sekolah',
            'npwp' => 'npwp', 'namawajibpajak' => 'nama_wajib_pajak', 'kewarganegaraan' => 'kewarganegaraan',
            'bank' => 'bank', 'nomorrekeningbank' => 'nomor_rekening_bank',
            'rekeningatasnama' => 'rekening_atas_nama', 'nik' => 'nik', 'nokk' => 'no_kk',
            'lintang' => 'lintang', 'bujur' => 'bujur', 'password' => 'password', 'foto' => 'foto',
        ],
    ];

    // ---------- KELAS ----------
    $cfgs['kelas'] = [
        'table'     => 'dt_kelas',
        'menu'      => 'kelas',
        'label'     => 'Data Kelas',
        'identity'  => ['namakelas', 'tingkat', 'idtahunajaran'],
        'required'  => ['namakelas', 'tingkat', 'idtahunajaran'],
        'map' => [
            'namakelas' => 'nama_kelas', 'tingkat' => 'tingkat',
            'idjurusan' => 'id_jurusan', 'idtahunajaran' => 'id_tahun_ajaran',
            'keterangan' => 'keterangan',
        ],
    ];

    // ---------- MAPEL ----------
    $cfgs['mapel'] = [
        'table'     => 'dt_mapel',
        'menu'      => 'mapel',
        'label'     => 'Mata Pelajaran',
        'identity'  => ['kodemapel', 'namamapel'],
        'required'  => ['kodemapel', 'namamapel'],
        'map' => [
            'kodemapel' => 'kode_mapel', 'namamapel' => 'nama_mapel',
            'kelompokmapel' => 'kelompok_mapel',
        ],
    ];

    // ---------- USER ----------
    $cfgs['user'] = [
        'table'     => 'dt_user',
        'menu'      => 'user',
        'label'     => 'User Pengguna',
        'identity'  => ['username'],
        'required'  => ['username', 'password', 'leveluser'],
        'map' => [
            'username' => 'username', 'password' => 'password',
            'leveluser' => 'level_user', 'idfk' => 'id_fk', 'statusaktif' => 'status_aktif',
        ],
    ];

    return $cfgs[$entity] ?? null;
}

$cfg = ie_entity_config($entity);
if (!$cfg) {
    $_SESSION['alert'] = ['type' => 'danger', 'msg' => "Entitas import '$entity' tidak didukung."];
    header("Location: ../index.php?menu=beranda");
    exit;
}

// Resolusi default nilai yang diisi otomatis (contoh: id_tahun_ajaran dari session TA aktif).
$defaults = [];
if (isset($cfg['defaults'])) {
    foreach ($cfg['defaults'] as $dbCol => $resolver) {
        if ($resolver === 'session_ta_id') {
            $taNama = $_SESSION['tahun_ajaran_aktif'] ?? '';
            $taId = 0;
            if ($taNama !== '') {
                $q = $koneksi->prepare("SELECT id_tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
                $q->bind_param("s", $taNama);
                $q->execute();
                $r = $q->get_result()->fetch_assoc();
                $q->close();
                $taId = $r ? (int)$r['id_tahun_ajaran'] : 0;
            }
            $defaults[$dbCol] = $taId ?: null;
        }
    }
}

// RBAC: cek akses menu entitas
if (!boleh_menu($_SESSION['level_user'] ?? '', $cfg['menu'])) {
    $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Anda tidak memiliki akses import ke entitas ' . $cfg['label'] . '.'];
    header("Location: ../index.php?menu=beranda");
    exit;
}

// ============================================================
// PREVIEW: baca file, validasi, simpan ke session
// ============================================================
if ($aksi === 'preview') {
    if ($_FILES['fileExcel']['error'] !== 0) {
        $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Upload file gagal.'];
        header("Location: ../index.php?menu=" . $cfg['menu']);
        exit;
    }
    $file_tmp = $_FILES['fileExcel']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['fileExcel']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'xlsx' && $ext !== 'xls') {
        $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Hanya file Excel (.xlsx/.xls).'];
        header("Location: ../index.php?menu=" . $cfg['menu']);
        exit;
    }

    try {
        $read = ie_read_excel($file_tmp);
        if (empty($read['rows'])) {
            $_SESSION['alert'] = ['type' => 'warning', 'msg' => 'File kosong atau tidak ada data baris.'];
            header("Location: ../index.php?menu=" . $cfg['menu']);
            exit;
        }
        $prev = ie_preview($koneksi, $cfg, $read['rows']);
        $_SESSION['ie_preview_' . $entity] = [
            'entity' => $entity,
            'cfg'    => $cfg,
            'prev'   => $prev,
            'file'   => $_FILES['fileExcel']['name'],
        ];
        header("Location: ../index.php?menu=import_preview&entity=" . urlencode($entity));
        exit;
    } catch (Exception $e) {
        $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Gagal membaca file: ' . $e->getMessage()];
        header("Location: ../index.php?menu=" . $cfg['menu']);
        exit;
    }
}

// ============================================================
// EXECUTE: ambil dari session, insert transaksional
// ============================================================
if ($aksi === 'execute') {
    $sessKey = 'ie_preview_' . $entity;
    if (empty($_SESSION[$sessKey])) {
        $_SESSION['alert'] = ['type' => 'warning', 'msg' => 'Tidak ada data preview untuk dieksekusi.'];
        header("Location: ../index.php?menu=" . $cfg['menu']);
        exit;
    }
    $prev = $_SESSION[$sessKey]['prev'];
    $exec = ie_execute($koneksi, $cfg, $prev['valid'], $defaults);
    unset($_SESSION[$sessKey]);

    if (!empty($exec['errors'])) {
        $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Gagal import: ' . $exec['errors'][0]];
    } else {
        $msg = "Import {$cfg['label']} selesai: {$exec['inserted']} data ditambahkan.";
        if ($prev['counts']['invalid'] > 0) {
            $msg .= " {$prev['counts']['invalid']} baris gagal validasi.";
        }
        if ($prev['counts']['duplicate'] > 0) {
            $msg .= " {$prev['counts']['duplicate']} duplikat dilewati.";
        }
        $_SESSION['alert'] = ['type' => 'success', 'msg' => $msg];
    }
    header("Location: ../index.php?menu=" . $cfg['menu']);
    exit;
}

// Akses tanpa aksi yang dikenali
header("Location: ../index.php?menu=" . $cfg['menu']);
exit;
