<?php
require_once __DIR__ . '/_init.php';
sek_wajib_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: absensi.php');
    exit;
}

$sek = $_SESSION['sekretaris'];
$id_kelas = (int)$sek['id_kelas'];
$id_ta    = (int)$sek['id_tahun_ajaran'];
$id_siswa_sek = (int)$sek['id_siswa'];

$tanggal = sek_valid_tanggal($_POST['tanggal'] ?? null);
$tgl_raw = (string)($_POST['tanggal'] ?? '');
if ($tgl_raw === '' || $tgl_raw !== $tanggal || $tanggal > date('Y-m-d')) {
    $pesan = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_raw) && $tgl_raw > date('Y-m-d'))
        ? 'Tanggal tidak boleh melewati hari ini.'
        : 'Tanggal tidak valid.';
    sek_flash_set('danger', $pesan);
    header('Location: absensi.php');
    exit;
}

if (sek_libur_info($koneksi, $id_ta, $tanggal)) {
    sek_flash_set('danger', 'Tanggal ini ditetapkan LIBUR — absensi tidak dapat diisi.');
    header('Location: absensi.php?tanggal=' . $tanggal);
    exit;
}

// Daftar id siswa valid di kelas ini
$valid_ids = [];
$stmt = $koneksi->prepare("SELECT ks.id_siswa FROM dt_kelas_siswa ks
                           WHERE ks.id_kelas = ? AND ks.id_tahun_ajaran = ?");
$stmt->bind_param('ii', $id_kelas, $id_ta);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $valid_ids[(int)$r['id_siswa']] = true;
$stmt->close();

$abs = $_POST['abs'] ?? [];
$ket = $_POST['ket'] ?? [];
$allowed = ['H', 'S', 'I', 'A'];

$koneksi->begin_transaction();
try {
    // Ganti total absensi harian kelas + tanggal ini
    $stmt = $koneksi->prepare("DELETE FROM dt_absensi WHERE id_kelas = ? AND id_tahun_ajaran = ? AND tanggal = ?");
    $stmt->bind_param('iis', $id_kelas, $id_ta, $tanggal);
    $stmt->execute();
    $stmt->close();

    $stmt = $koneksi->prepare("INSERT INTO dt_absensi
                                (id_siswa, id_kelas, id_tahun_ajaran, tanggal, status, keterangan, dicatat_oleh)
                               VALUES (?,?,?,?,?,?,?)");
    foreach ($valid_ids as $sid => $_) {
        $stt = $abs[$sid] ?? 'H';
        $stt = in_array($stt, $allowed, true) ? $stt : 'H';
        $k = trim((string)($ket[$sid] ?? ''));
        $stmt->bind_param('iiisssi', $sid, $id_kelas, $id_ta, $tanggal, $stt, $k, $id_siswa_sek);
        $stmt->execute();
    }
    $stmt->close();

    $koneksi->commit();
    sek_flash_set('success', 'Absensi harian ' . date('d/m/Y', strtotime($tanggal)) . ' berhasil disimpan.');
} catch (Throwable $e) {
    $koneksi->rollback();
    sek_flash_set('danger', 'Gagal menyimpan: ' . sek_esc($e->getMessage()));
}

header('Location: absensi.php?tanggal=' . $tanggal);
exit;
