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
// SIMPAN PEMBAYARAN BANYAK BULAN (dari tabel per kelas)
// ==========================================================
if ($aksi === 'bayar_bulanan') {
    $tanggal  = trim((string)($_POST['tanggal_bayar'] ?? ''));
    $metode   = (string)($_POST['metode'] ?? 'Tunai');
    $no_ref   = trim((string)($_POST['no_referensi'] ?? ''));
    $ket      = trim((string)($_POST['keterangan'] ?? ''));
    $id_siswa = (int)($_POST['id_siswa'] ?? 0);
    $id_ta    = (int)($_POST['ta'] ?? 0);
    $id_kelas = (int)($_POST['kelas'] ?? 0);
    $pilih    = $_POST['bulan'] ?? [];
    $nom_in   = is_array($_POST['nominal'] ?? null) ? $_POST['nominal'] : [];

    if (!in_array($metode, ['Tunai', 'Transfer', 'QRIS', 'Lainnya'], true)) {
        $metode = 'Tunai';
    }

    $back = "../index.php?menu=spp_pembayaran&ta=" . $id_ta
        . ($id_kelas > 0 ? "&kelas=" . $id_kelas : ($id_siswa > 0 ? "&siswa=" . $id_siswa : ""));

    if ($id_siswa <= 0) {
        spp_alert('danger', 'Siswa tidak valid.');
        header("Location: $back");
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

    $ids = array_values(array_unique(array_filter(
        array_map('intval', is_array($pilih) ? $pilih : []),
        function ($v) {
            return $v > 0;
        }
    )));
    if (!$ids) {
        spp_alert('warning', 'Pilih minimal satu bulan yang akan dibayar.');
        header("Location: $back");
        exit;
    }

    // Nama siswa (untuk pesan)
    $nama_siswa = '';
    $stmt = $koneksi->prepare("SELECT nama FROM dt_siswa WHERE id_siswa = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $id_siswa);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $nama_siswa = $r ? (string)$r['nama'] : '';
    }

    // Tagihan yang dipilih — wajib milik siswa ini (dan TA yang sama bila dikirim).
    $in = implode(',', $ids);
    $rows = [];
    $sql = "SELECT id_tagihan, total, tahun, bulan
              FROM dt_spp_tagihan
             WHERE id_tagihan IN ($in) AND id_siswa = " . (int)$id_siswa
        . ($id_ta > 0 ? " AND id_tahun_ajaran = " . (int)$id_ta : '');
    $q = $koneksi->query($sql);
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $rows[(int)$r['id_tagihan']] = $r;
        }
    }
    if (!$rows) {
        spp_alert('danger', 'Tagihan yang dipilih tidak ditemukan atau bukan milik siswa ini.');
        header("Location: $back");
        exit;
    }

    $bayar_map  = spp_terbayar_map($koneksi, array_keys($rows));
    $bayar_list = [];
    $total_semua = 0.0;
    $tolak = [];

    foreach ($rows as $id_tag => $r) {
        $sisa = round((float)$r['total'] - (float)($bayar_map[$id_tag] ?? 0), 2);
        if ($sisa <= 0.005) {
            continue; // sudah lunas
        }
        $label = spp_bulan_label((int)$r['tahun'], (int)$r['bulan']);
        $nom = spp_nominal_input($nom_in[$id_tag] ?? '');
        if ($nom <= 0) {
            $nom = $sisa; // default: bayar penuh sisa bulan tsb
        }
        if ($nom > $sisa + 0.005) {
            $tolak[] = $label . ' (maks ' . spp_rupiah($sisa) . ')';
            continue;
        }
        $bayar_list[] = [
            'id_tagihan' => $id_tag,
            'nominal' => $nom,
            'label' => $label,
        ];
        $total_semua += $nom;
    }

    if ($tolak) {
        spp_alert('warning', 'Nominal melebihi sisa tagihan: <b>' . implode(', ', array_map('htmlspecialchars', $tolak)) . '</b>.');
        header("Location: $back");
        exit;
    }
    if (!$bayar_list) {
        spp_alert('info', 'Semua bulan yang dipilih sudah <b>Lunas</b>. Tidak ada yang perlu dibayar.');
        header("Location: $back");
        exit;
    }

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

    $koneksi->begin_transaction();
    $inserted = [];
    $gagal = false;
    foreach ($bayar_list as $b) {
        $stmt->bind_param('iisdsssi', $b['id_tagihan'], $id_siswa, $tanggal, $b['nominal'], $metode, $no_ref, $ket, $id_user_val);
        if (!$stmt->execute()) {
            $gagal = true;
            break;
        }
        $inserted[] = [
            'id_bayar' => (int)$stmt->insert_id,
            'label' => $b['label'],
        ];
    }
    $stmt->close();

    if ($gagal) {
        $koneksi->rollback();
        spp_alert('danger', 'Gagal menyimpan pembayaran. Tidak ada data yang diubah.');
        header("Location: $back");
        exit;
    }
    $koneksi->commit();

    foreach ($bayar_list as $b) {
        spp_recompute_status($koneksi, $b['id_tagihan']);
    }

    $_SESSION['spp_kwitansi_multi'] = $inserted;
    spp_alert(
        'success',
        'Pembayaran <b>' . count($inserted) . ' bulan</b> tersimpan (total <b>' . spp_rupiah($total_semua) . '</b>)'
            . ($nama_siswa !== '' ? ' untuk <b>' . htmlspecialchars($nama_siswa) . '</b>' : '')
            . '. Status tagihan sudah diperbarui.'
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
