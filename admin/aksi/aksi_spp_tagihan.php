<?php
/**
 * Handler Tagihan SPP (dt_spp_tagihan).
 *
 * Aksi:
 *   - generate : buat tagihan 12 bulan (Juli..Juni) untuk seluruh siswa / satu kelas.
 *   - potongan : ubah potongan (keringanan/beasiswa) satu tagihan.
 *   - hapus    : hapus satu tagihan (ditolak bila sudah ada pembayaran).
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

/** Bangun URL kembali ke daftar tagihan dengan filter yang dipertahankan. */
function spp_tagihan_back(): string
{
    $id_ta = (int)($_REQUEST['id_ta'] ?? 0);
    $url = "../index.php?menu=spp_tagihan";
    if ($id_ta > 0) {
        $url .= "&ta=" . $id_ta;
    }
    $kelas = (int)($_REQUEST['f_kelas'] ?? 0);
    if ($kelas > 0) {
        $url .= "&kelas=" . $kelas;
    }
    $bulan = (string)($_REQUEST['f_bulan'] ?? '');
    if (preg_match('/^\d{4}-\d{2}$/', $bulan)) {
        $url .= "&bulan=" . $bulan;
    }
    $status = (string)($_REQUEST['f_status'] ?? '');
    if (in_array($status, ['Belum', 'Sebagian', 'Lunas'], true)) {
        $url .= "&status=" . $status;
    }
    $q = trim((string)($_REQUEST['f_q'] ?? ''));
    if ($q !== '') {
        $url .= "&q=" . urlencode($q);
    }
    return $url;
}

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';
$back = spp_tagihan_back();

// ==========================================================
// GENERATE TAGIHAN 12 BULAN
// ==========================================================
if ($aksi === 'generate') {
    $id_ta = (int)($_POST['id_ta'] ?? 0);
    $id_kelas = (int)($_POST['id_kelas'] ?? 0);
    $perbarui = !empty($_POST['perbarui']);

    $ta = spp_ta_info($koneksi, $id_ta);
    if (!$ta) {
        spp_alert('danger', 'Tahun ajaran tidak valid.');
        header("Location: ../index.php?menu=spp_tagihan");
        exit;
    }

    $tarif_map = spp_tarif_map($koneksi, $id_ta);
    if (empty($tarif_map)) {
        spp_alert('warning', 'Tarif SPP belum diatur untuk tahun ajaran ini. Atur dulu di menu <b>Tarif SPP</b>.');
        header("Location: $back");
        exit;
    }

    $roster = spp_roster($koneksi, $id_ta, $id_kelas);
    if (empty($roster)) {
        spp_alert('warning', 'Tidak ada siswa pada filter kelas tersebut.');
        header("Location: $back");
        exit;
    }

    $periode = $ta['periode'];
    $inserted = 0;
    $diperbarui = 0;
    $skip = 0;
    $tanpa_tarif = 0;

    $sql_ins = "INSERT IGNORE INTO dt_spp_tagihan
                (id_siswa, id_kelas, id_tahun_ajaran, tingkat, id_jurusan, tahun, bulan,
                 nominal, potongan, total, jatuh_tempo, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'Belum')";
    $stmt_ins = $koneksi->prepare($sql_ins);
    if (!$stmt_ins) {
        spp_alert('danger', 'Gagal menyiapkan query tagihan.');
        header("Location: $back");
        exit;
    }

    // Hanya nominal tagihan yang belum dibayar sama sekali yang boleh diperbarui.
    $stmt_up = $koneksi->prepare("UPDATE dt_spp_tagihan
                                     SET nominal = ?, total = ? - potongan
                                   WHERE id_tagihan = ? AND status = 'Belum'
                                     AND NOT EXISTS (SELECT 1 FROM dt_spp_pembayaran p WHERE p.id_tagihan = dt_spp_tagihan.id_tagihan)");
    $stmt_cari = $koneksi->prepare("SELECT id_tagihan FROM dt_spp_tagihan
                                    WHERE id_siswa = ? AND id_tahun_ajaran = ? AND tahun = ? AND bulan = ? LIMIT 1");

    $koneksi->begin_transaction();
    try {
        foreach ($roster as $sw) {
            $id_siswa = (int)$sw['id_siswa'];
            $id_kls = (int)$sw['id_kelas'];
            $tingkat = (string)$sw['tingkat'];
            $id_jur = (int)$sw['id_jurusan'];

            $nominal = spp_tarif_cari($tarif_map, $tingkat, $id_jur);
            if ($nominal === null || $nominal <= 0) {
                $tanpa_tarif++;
                continue;
            }

            foreach ($periode as $p) {
                $tahun = (int)$p['tahun'];
                $bulan = (int)$p['bulan'];
                $jt = $p['jatuh_tempo'];

                $stmt_ins->bind_param(
                    'iiisiiidds',
                    $id_siswa,
                    $id_kls,
                    $id_ta,
                    $tingkat,
                    $id_jur,
                    $tahun,
                    $bulan,
                    $nominal,
                    $nominal,
                    $jt
                );
                $stmt_ins->execute();

                if ($stmt_ins->affected_rows > 0) {
                    $inserted++;
                    continue;
                }

                // Sudah ada: opsional perbarui nominal (hanya yang belum dibayar).
                if ($perbarui && $stmt_up && $stmt_cari) {
                    $stmt_cari->bind_param('iiii', $id_siswa, $id_ta, $tahun, $bulan);
                    $stmt_cari->execute();
                    $rc = $stmt_cari->get_result()->fetch_assoc();
                    if ($rc) {
                        $id_tagihan_tmp = (int)$rc['id_tagihan'];
                        $stmt_up->bind_param('ddi', $nominal, $nominal, $id_tagihan_tmp);
                        $stmt_up->execute();
                        if ($stmt_up->affected_rows > 0) {
                            $diperbarui++;
                        }
                    }
                }

                $skip++;
            }
        }

        $stmt_ins->close();
        if ($stmt_up) {
            $stmt_up->close();
        }
        if ($stmt_cari) {
            $stmt_cari->close();
        }
        $koneksi->commit();
    } catch (Throwable $e) {
        $koneksi->rollback();
        spp_alert('danger', 'Gagal membuat tagihan: ' . $e->getMessage());
        header("Location: $back");
        exit;
    }

    $msg = "Tagihan dibuat: <b>$inserted</b> baris baru";
    if ($diperbarui > 0) {
        $msg .= ", <b>$diperbarui</b> nominal diperbarui";
    }
    if ($skip > 0) {
        $msg .= ", $skip sudah ada (dilewati)";
    }
    if ($tanpa_tarif > 0) {
        $msg .= ", <b>$tanpa_tarif</b> siswa tanpa tarif (dilewati)";
    }
    $msg .= '.';
    spp_alert($inserted > 0 ? 'success' : 'info', $msg);
    header("Location: $back");
    exit;
}

// ==========================================================
// UBAH POTONGAN
// ==========================================================
if ($aksi === 'potongan') {
    $id_tagihan = (int)($_POST['id_tagihan'] ?? 0);
    $potongan = spp_nominal_input($_POST['potongan'] ?? '0');

    $stmt = $koneksi->prepare("SELECT id_tagihan, nominal, total FROM dt_spp_tagihan WHERE id_tagihan = ? LIMIT 1");
    $tag = null;
    if ($stmt) {
        $stmt->bind_param('i', $id_tagihan);
        $stmt->execute();
        $tag = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$tag) {
        spp_alert('danger', 'Tagihan tidak ditemukan.');
        header("Location: $back");
        exit;
    }

    $nominal = (float)$tag['nominal'];
    if ($potongan < 0) {
        $potongan = 0;
    }
    if ($potongan > $nominal) {
        spp_alert('warning', 'Potongan tidak boleh melebihi nominal tagihan (' . spp_rupiah($nominal) . ').');
        header("Location: $back");
        exit;
    }

    $total = $nominal - $potongan;
    $stmt = $koneksi->prepare("UPDATE dt_spp_tagihan SET potongan = ?, total = ? WHERE id_tagihan = ?");
    if ($stmt) {
        $stmt->bind_param('ddi', $potongan, $total, $id_tagihan);
        $stmt->execute();
        $stmt->close();
        spp_recompute_status($koneksi, $id_tagihan);
        spp_alert('success', 'Potongan tagihan disimpan. Total menjadi <b>' . spp_rupiah($total) . '</b>.');
    } else {
        spp_alert('danger', 'Gagal menyimpan potongan.');
    }
    header("Location: $back");
    exit;
}

// ==========================================================
// HAPUS TAGIHAN
// ==========================================================
if ($aksi === 'hapus') {
    $id_tagihan = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

    $stmt = $koneksi->prepare("SELECT COUNT(*) AS n FROM dt_spp_pembayaran WHERE id_tagihan = ?");
    $n = 0;
    if ($stmt) {
        $stmt->bind_param('i', $id_tagihan);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $n = (int)($r['n'] ?? 0);
    }
    if ($n > 0) {
        spp_alert('warning', 'Tagihan tidak bisa dihapus karena sudah memiliki pembayaran. Hapus pembayarannya terlebih dahulu.');
        header("Location: $back");
        exit;
    }

    $stmt = $koneksi->prepare("DELETE FROM dt_spp_tagihan WHERE id_tagihan = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id_tagihan);
        $stmt->execute();
        $stmt->close();
        spp_alert('success', 'Tagihan dihapus.');
    } else {
        spp_alert('danger', 'Gagal menghapus tagihan.');
    }
    header("Location: $back");
    exit;
}

spp_alert('warning', 'Aksi tidak dikenal.');
header("Location: $back");
exit;
