<?php
require_once __DIR__ . '/_init.php';
sek_wajib_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$sek = $_SESSION['sekretaris'];
$id_kelas = (int)$sek['id_kelas'];
$id_ta    = (int)$sek['id_tahun_ajaran'];
$id_siswa_sek = (int)$sek['id_siswa'];

$tanggal = sek_valid_tanggal($_POST['tanggal'] ?? null);
$tgl_raw = (string)($_POST['tanggal'] ?? '');
if ($tgl_raw === '' || $tgl_raw !== $tanggal || $tanggal > date('Y-m-d')) {
    // Tentukan pesan: format salah vs melewati hari ini
    $pesan = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_raw) && $tgl_raw > date('Y-m-d'))
        ? 'Tanggal tidak boleh melewati hari ini.'
        : 'Tanggal tidak valid.';
    sek_flash_set('danger', $pesan);
    header('Location: index.php');
    exit;
}
$jam_ke  = (int)($_POST['jam_ke'] ?? 0);
$hari_id = sek_hari_id($tanggal);

if (sek_libur_info($koneksi, $id_ta, $tanggal)) {
    sek_flash_set('danger', 'Tanggal ini ditetapkan LIBUR — jurnal tidak dapat diisi.');
    header('Location: index.php?tanggal=' . $tanggal);
    exit;
}

$materi    = trim((string)($_POST['materi'] ?? ''));
$keterangan = trim((string)($_POST['keterangan'] ?? ''));
$status = (string)($_POST['status'] ?? 'Mengajar');
$status_opts = ['Mengajar', 'Tidak Hadir', 'Tugas/Kegiatan', 'Kosong'];
if (!in_array($status, $status_opts, true)) $status = 'Mengajar';

$id_guru_pengganti = (int)($_POST['id_guru_pengganti'] ?? 0);

if ($jam_ke <= 0) {
    sek_flash_set('danger', 'Jam pelajaran tidak valid.');
    header('Location: index.php?tanggal=' . $tanggal);
    exit;
}

// Ambil mapel & guru dari jadwal (snapshot)
$stmt = $koneksi->prepare("SELECT id_mapel, id_guru FROM dt_jadwal
                           WHERE id_kelas = ? AND hari = ? AND jam_ke = ? AND id_tahun_ajaran = ? LIMIT 1");
$stmt->bind_param('isii', $id_kelas, $hari_id, $jam_ke, $id_ta);
$stmt->execute();
$jd = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$jd) {
    sek_flash_set('danger', 'Sesi jam ke-' . $jam_ke . ' tidak ditemukan pada jadwal.');
    header('Location: index.php?tanggal=' . $tanggal);
    exit;
}

$id_mapel = (int)$jd['id_mapel'];
$id_guru  = $jd['id_guru'] !== null ? (int)$jd['id_guru'] : null;
$guru_pengganti = $id_guru_pengganti > 0 ? $id_guru_pengganti : null;

$koneksi->begin_transaction();
try {
    // Upsert jurnal
    $stmt = $koneksi->prepare("SELECT id_jurnal FROM dt_jurnal
                               WHERE id_kelas = ? AND tanggal = ? AND jam_ke = ? AND id_tahun_ajaran = ? LIMIT 1");
    $stmt->bind_param('isii', $id_kelas, $tanggal, $jam_ke, $id_ta);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $id_jurnal = (int)$existing['id_jurnal'];
        $stmt = $koneksi->prepare("UPDATE dt_jurnal SET id_mapel=?, id_guru=?, id_guru_pengganti=?,
                                    materi=?, status=?, keterangan=?, dicatat_oleh=?
                                   WHERE id_jurnal=?");
        $stmt->bind_param('iiisssii', $id_mapel, $id_guru, $guru_pengganti,
                          $materi, $status, $keterangan, $id_siswa_sek, $id_jurnal);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $koneksi->prepare("INSERT INTO dt_jurnal
                                    (id_kelas, id_mapel, id_guru, id_guru_pengganti, id_tahun_ajaran,
                                     tanggal, jam_ke, materi, status, keterangan, dicatat_oleh)
                                   VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('iiiiisssssi', $id_kelas, $id_mapel, $id_guru, $guru_pengganti, $id_ta,
                          $tanggal, $jam_ke, $materi, $status, $keterangan, $id_siswa_sek);
        $stmt->execute();
        $stmt->close();
    }

    $koneksi->commit();
    sek_flash_set('success', 'Jurnal jam ke-' . $jam_ke . ' berhasil disimpan.');
} catch (Throwable $e) {
    $koneksi->rollback();
    sek_flash_set('danger', 'Gagal menyimpan: ' . sek_esc($e->getMessage()));
}

header('Location: index.php?tanggal=' . $tanggal);
exit;
