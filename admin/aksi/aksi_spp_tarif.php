<?php
/**
 * Handler Tarif SPP (dt_spp_tarif).
 *
 * Aksi: simpan  -> simpan matriks tarif tingkat x jurusan sekaligus.
 *   POST nominal[<tingkat>][<id_jurusan>] = nominal (string, boleh berformat).
 *   - nominal > 0  -> INSERT/UPDATE baris tarif.
 *   - nominal kosong/0 -> baris tarif dihapus (kembali ke tarif cadangan).
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

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';

$id_ta = (int)($_POST['id_ta'] ?? 0);
$back = "../index.php?menu=spp_tarif" . ($id_ta > 0 ? "&ta=" . $id_ta : "");

if ($aksi !== 'simpan') {
    spp_alert('warning', 'Aksi tidak dikenal.');
    header("Location: $back");
    exit;
}

$ta = spp_ta_info($koneksi, $id_ta);
if (!$ta) {
    spp_alert('danger', 'Tahun ajaran tidak valid.');
    header("Location: ../index.php?menu=spp_tarif");
    exit;
}

$tingkat_valid = spp_tingkat_list($koneksi, $id_ta);
$jurusan_valid = array_map('intval', array_keys(spp_jurusan_list($koneksi)));
$jurusan_valid[] = 0; // 0 = cadangan semua jurusan

$nominal_in = $_POST['nominal'] ?? [];
if (!is_array($nominal_in)) {
    $nominal_in = [];
}

$simpan = 0;
$hapus = 0;
$gagal = 0;

$koneksi->begin_transaction();
try {
    $sql_up = "INSERT INTO dt_spp_tarif (id_tahun_ajaran, tingkat, id_jurusan, nominal)
               VALUES (?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE nominal = VALUES(nominal)";
    $stmt_up = $koneksi->prepare($sql_up);

    $stmt_del = $koneksi->prepare("DELETE FROM dt_spp_tarif WHERE id_tahun_ajaran = ? AND tingkat = ? AND id_jurusan = ?");

    if (!$stmt_up || !$stmt_del) {
        throw new Exception('Gagal menyiapkan query tarif.');
    }

    foreach ($tingkat_valid as $tingkat) {
        foreach ($jurusan_valid as $id_jur) {
            $raw = $nominal_in[$tingkat][$id_jur] ?? '';
            $nominal = spp_nominal_input($raw);
            if ($nominal < 0) {
                $nominal = 0;
            }

            if ($nominal > 0) {
                $stmt_up->bind_param('isid', $id_ta, $tingkat, $id_jur, $nominal);
                if (!$stmt_up->execute()) {
                    $gagal++;
                } else {
                    $simpan++;
                }
            } else {
                $stmt_del->bind_param('isi', $id_ta, $tingkat, $id_jur);
                if (!$stmt_del->execute()) {
                    $gagal++;
                } elseif ($stmt_del->affected_rows > 0) {
                    $hapus++;
                }
            }
        }
    }

    $stmt_up->close();
    $stmt_del->close();
    $koneksi->commit();

    if ($gagal > 0) {
        spp_alert('warning', "Tarif tersimpan $simpan, dihapus $hapus, gagal $gagal baris.");
    } else {
        spp_alert('success', "Tarif SPP disimpan ($simpan baris), $hapus baris dikosongkan.");
    }
} catch (Throwable $e) {
    $koneksi->rollback();
    spp_alert('danger', 'Gagal menyimpan tarif: ' . $e->getMessage());
}

header("Location: $back");
exit;
