<?php
require_once __DIR__ . '/_init.php';
guru_wajib_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: absensi.php');
    exit;
}

$guru = $_SESSION['guru'];
$id_guru = (int)($guru['id_guru'] ?? 0);
$ta = guru_ta_aktif($koneksi);
$id_ta = $ta['id'];

$id_kelas = (int)($_POST['kelas'] ?? 0);

// Kelas harus termasuk kelas yang diajar guru pada TA aktif.
$valid = false;
foreach (guru_kelas_diajar($koneksi, $id_guru, $id_ta) as $k) {
    if ((int)$k['id_kelas'] === $id_kelas) { $valid = true; break; }
}
if (!$valid) {
    guru_flash_set('danger', 'Kelas tidak valid / bukan kelas yang Anda ajar.');
    header('Location: absensi.php');
    exit;
}

$tgl_raw = (string)($_POST['tanggal'] ?? '');
$tanggal = guru_valid_tanggal($tgl_raw);
if ($tgl_raw === '' || $tgl_raw !== $tanggal || $tanggal > date('Y-m-d')) {
    $pesan = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_raw) && $tgl_raw > date('Y-m-d'))
        ? 'Tanggal tidak boleh melewati hari ini.'
        : 'Tanggal tidak valid.';
    guru_flash_set('danger', $pesan);
    header('Location: absensi.php?kelas=' . $id_kelas);
    exit;
}

if (guru_libur_info($koneksi, $id_ta, $tanggal)) {
    guru_flash_set('danger', 'Tanggal ini ditetapkan LIBUR — absensi tidak dapat diisi.');
    header('Location: absensi.php?kelas=' . $id_kelas . '&tanggal=' . $tanggal);
    exit;
}

// Bila sudah diisi (biasanya oleh sekretaris), guru hanya boleh mengecek.
$stmt = $koneksi->prepare("SELECT COUNT(*) AS n FROM dt_absensi
                           WHERE id_kelas = ? AND id_tahun_ajaran = ? AND tanggal = ?");
$stmt->bind_param('iis', $id_kelas, $id_ta, $tanggal);
$stmt->execute();
$n = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);
$stmt->close();
if ($n > 0) {
    guru_flash_set('warning', 'Absensi tanggal ini sudah diisi. Guru hanya dapat mengecek data yang sudah ada.');
    header('Location: absensi.php?kelas=' . $id_kelas . '&tanggal=' . $tanggal);
    exit;
}

$siswa = guru_siswa_kelas($koneksi, $id_kelas, $id_ta);
if (empty($siswa)) {
    guru_flash_set('danger', 'Belum ada siswa di kelas ini.');
    header('Location: absensi.php?kelas=' . $id_kelas . '&tanggal=' . $tanggal);
    exit;
}

$abs = $_POST['abs'] ?? [];
$ket = $_POST['ket'] ?? [];
$allowed = ['H', 'S', 'I', 'A'];
$dicatat = null;

$koneksi->begin_transaction();
try {
    $stmt = $koneksi->prepare("INSERT INTO dt_absensi
                                (id_siswa, id_kelas, id_tahun_ajaran, tanggal, status, keterangan, dicatat_oleh)
                               VALUES (?,?,?,?,?,?,?)");
    foreach ($siswa as $s) {
        $sid = (int)$s['id_siswa'];
        $stt = $abs[$sid] ?? 'H';
        $stt = in_array($stt, $allowed, true) ? $stt : 'H';
        $k = trim((string)($ket[$sid] ?? ''));
        $stmt->bind_param('iiisssi', $sid, $id_kelas, $id_ta, $tanggal, $stt, $k, $dicatat);
        $stmt->execute();
    }
    $stmt->close();

    $koneksi->commit();
    guru_flash_set('success', 'Absensi ' . date('d/m/Y', strtotime($tanggal)) . ' berhasil disimpan.');
} catch (Throwable $e) {
    $koneksi->rollback();
    guru_flash_set('danger', 'Gagal menyimpan absensi: ' . guru_esc($e->getMessage()));
}

header('Location: absensi.php?kelas=' . $id_kelas . '&tanggal=' . $tanggal);
exit;
