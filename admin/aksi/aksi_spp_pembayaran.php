<?php
/**
 * Handler Pembayaran SPP (dt_spp_pembayaran).
 *
 * Aksi:
 *   - simpan : catat pembayaran (boleh mengangsur) untuk satu tagihan.
 *   - hapus  : hapus satu baris pembayaran (status tagihan dihitung ulang).
 */
require_once(__DIR__ . '/akses_guard.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("../koneksi.php");
require_once(__DIR__ . '/../helper_spp.php');

function spp_alert(string $type, string $msg): void
{
    $_SESSION['alert'] = ['type' => $type, 'msg' => $msg];
}

function spp_tanggal_valid($t): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$t)) {
        return false;
    }
    [$y, $m, $d] = array_map('intval', explode('-', (string)$t));
    return checkdate($m, $d, $y);
}

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';
$id_tagihan = (int)($_POST['id_tagihan'] ?? $_GET['id_tagihan'] ?? 0);
$back = "../index.php?menu=spp_pembayaran" . ($id_tagihan > 0 ? "&tagihan=" . $id_tagihan : "");

// ==========================================================
// SIMPAN PEMBAYARAN
// ==========================================================
if ($aksi === 'simpan') {
    $tanggal = trim((string)($_POST['tanggal_bayar'] ?? ''));
    $nominal = spp_nominal_input($_POST['nominal'] ?? '0');
    $metode = (string)($_POST['metode'] ?? 'Tunai');
    $no_ref = trim((string)($_POST['no_referensi'] ?? ''));
    $ket = trim((string)($_POST['keterangan'] ?? ''));

    if (!in_array($metode, ['Tunai', 'Transfer', 'QRIS', 'Lainnya'], true)) {
        $metode = 'Tunai';
    }

    // --- Validasi tagihan ---
    $tag = null;
    $stmt = $koneksi->prepare("SELECT t.id_tagihan, t.id_siswa, t.total, t.status, t.tahun, t.bulan,
                                      s.nama, s.nipd
                                 FROM dt_spp_tagihan t
                                 INNER JOIN dt_siswa s ON s.id_siswa = t.id_siswa
                                WHERE t.id_tagihan = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $id_tagihan);
        $stmt->execute();
        $tag = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$tag) {
        spp_alert('danger', 'Tagihan tidak ditemukan.');
        header("Location: ../index.php?menu=spp_pembayaran");
        exit;
    }

    if (!spp_tanggal_valid($tanggal)) {
        spp_alert('warning', 'Tanggal pembayaran tidak valid.');
        header("Location: $back");
        exit;
    }
    if ($tanggal > date('Y-m-d')) {
        spp_alert('warning', 'Tanggal pembayaran tidak boleh melebihi hari ini.');
        header("Location: $back");
        exit;
    }
    if ($nominal <= 0) {
        spp_alert('warning', 'Nominal pembayaran harus lebih besar dari 0.');
        header("Location: $back");
        exit;
    }

    // --- Sisa tagihan ---
    $stmt = $koneksi->prepare("SELECT IFNULL(SUM(nominal),0) AS bayar FROM dt_spp_pembayaran WHERE id_tagihan = ?");
    $terbayar = 0.0;
    if ($stmt) {
        $stmt->bind_param('i', $id_tagihan);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $terbayar = (float)($r['bayar'] ?? 0);
    }
    $total = (float)$tag['total'];
    $sisa = round($total - $terbayar, 2);

    if ($sisa <= 0) {
        spp_alert('info', 'Tagihan ini sudah <b>Lunas</b>. Tidak ada sisa yang perlu dibayar.');
        header("Location: $back");
        exit;
    }
    if ($nominal > $sisa + 0.005) {
        spp_alert('warning', 'Nominal pembayaran (' . spp_rupiah($nominal) . ') melebihi sisa tagihan (' . spp_rupiah($sisa) . ').');
        header("Location: $back");
        exit;
    }

    $id_siswa = (int)$tag['id_siswa'];
    $id_user = (int)($_SESSION['id_user'] ?? 0);
    $id_user_val = $id_user > 0 ? $id_user : null;

    $stmt = $koneksi->prepare("INSERT INTO dt_spp_pembayaran
                                 (id_tagihan, id_siswa, tanggal_bayar, nominal, metode, no_referensi, keterangan, id_user)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        spp_alert('danger', 'Gagal menyiapkan query pembayaran.');
        header("Location: $back");
        exit;
    }
    $stmt->bind_param('iisdsssi', $id_tagihan, $id_siswa, $tanggal, $nominal, $metode, $no_ref, $ket, $id_user_val);
    $ok = $stmt->execute();
    $id_bayar = (int)$stmt->insert_id;
    $stmt->close();

    if (!$ok) {
        spp_alert('danger', 'Gagal menyimpan pembayaran.');
        header("Location: $back");
        exit;
    }

    $status = spp_recompute_status($koneksi, $id_tagihan);
    $_SESSION['spp_kwitansi_terakhir'] = $id_bayar;

    $sisa_baru = max(0, $sisa - $nominal);
    spp_alert(
        'success',
        'Pembayaran <b>' . spp_rupiah($nominal) . '</b> tersimpan. Status tagihan: <b>' . $status . '</b>'
            . ($sisa_baru > 0 ? ', sisa <b>' . spp_rupiah($sisa_baru) . '</b>.' : '.')
            . ' <a href="../index.php?menu=spp_pembayaran&kwitansi=' . $id_bayar . '" target="_blank" class="alert-link">Cetak kwitansi</a>'
    );
    header("Location: $back");
    exit;
}

// ==========================================================
// HAPUS PEMBAYARAN
// ==========================================================
if ($aksi === 'hapus') {
    $id_bayar = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

    $stmt = $koneksi->prepare("SELECT id_tagihan, nominal FROM dt_spp_pembayaran WHERE id_bayar = ? LIMIT 1");
    $bayar = null;
    if ($stmt) {
        $stmt->bind_param('i', $id_bayar);
        $stmt->execute();
        $bayar = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$bayar) {
        spp_alert('danger', 'Data pembayaran tidak ditemukan.');
        header("Location: $back");
        exit;
    }

    $id_tag = (int)$bayar['id_tagihan'];
    $stmt = $koneksi->prepare("DELETE FROM dt_spp_pembayaran WHERE id_bayar = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id_bayar);
        $stmt->execute();
        $stmt->close();
        spp_recompute_status($koneksi, $id_tag);
        spp_alert('success', 'Pembayaran ' . spp_rupiah((float)$bayar['nominal']) . ' dihapus. Status tagihan diperbarui.');
    } else {
        spp_alert('danger', 'Gagal menghapus pembayaran.');
    }

    $back = "../index.php?menu=spp_pembayaran&tagihan=" . $id_tag;
    header("Location: $back");
    exit;
}

spp_alert('warning', 'Aksi tidak dikenal.');
header("Location: $back");
exit;
