<?php
/**
 * Handler Manajemen Foto Siswa (Bulk).
 * Aksi: hapus_foto (satu), hapus_banyak, reset_foto (banyak ke placeholder).
 */
require_once(__DIR__ . '/akses_guard.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('../koneksi.php');

function setAlert($type, $msg)
{
    $_SESSION['alert'] = ['type' => $type, 'msg' => $msg];
}

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';
$back = "../index.php?menu=foto_siswa";
$foto_dir = '../assets/img/foto_siswa/';

if (!isset($_SESSION['level_user']) || !boleh_menu($_SESSION['level_user'], 'foto_siswa')) {
    setAlert('danger', 'Anda tidak memiliki akses ke manajemen foto siswa.');
    header("Location: ../index.php?menu=beranda");
    exit;
}

if (!is_dir($foto_dir)) {
    @mkdir($foto_dir, 0755, true);
}

// Hapus satu foto siswa
if ($aksi === 'hapus_foto') {
    $id_siswa = (int)($_POST['id_siswa'] ?? 0);
    $foto_file = trim((string)($_POST['foto_file'] ?? ''));

    if ($id_siswa <= 0) {
        setAlert('danger', 'Siswa tidak valid.');
        header("Location: $back");
        exit;
    }

    // Hapus file fisik jika ada dan bukan placeholder
    $file_deleted = false;
    if ($foto_file !== '' && $foto_file !== 'user.png' && file_exists($foto_dir . $foto_file)) {
        if (@unlink($foto_dir . $foto_file)) {
            $file_deleted = true;
        }
    }

    // Update DB: kosongkan foto (jadi user.png / placeholder)
    $stmt = $koneksi->prepare("UPDATE dt_siswa SET foto = 'user.png' WHERE id_siswa = ?");
    $stmt->bind_param("i", $id_siswa);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $msg = 'Foto siswa ID #' . $id_siswa . ' berhasil dihapus.';
        if ($file_deleted) {
            $msg .= ' File foto juga dihapus.';
        }
        setAlert('success', $msg);
    } else {
        setAlert('danger', 'Gagal menghapus foto siswa dari database.');
    }
    $stmt->close();
    header("Location: $back");
    exit;
}

// Hapus banyak foto siswa
if ($aksi === 'hapus_banyak') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids) || empty($ids)) {
        setAlert('warning', 'Tidak ada siswa yang dipilih.');
        header("Location: $back");
        exit;
    }

    $deleted = 0;
    $skipped = 0;
    $koneksi->begin_transaction();
    try {
        foreach ($ids as $id_siswa) {
            $id_siswa = (int)$id_siswa;
            if ($id_siswa <= 0) {
                $skipped++;
                continue;
            }

            // Ambil nama file foto saat ini
            $stmt = $koneksi->prepare("SELECT foto FROM dt_siswa WHERE id_siswa = ? LIMIT 1");
            $stmt->bind_param("i", $id_siswa);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) {
                $skipped++;
                continue;
            }

            $foto_file = trim((string)($row['foto'] ?? ''));
            $file_deleted = false;
            if ($foto_file !== '' && $foto_file !== 'user.png' && file_exists($foto_dir . $foto_file)) {
                if (@unlink($foto_dir . $foto_file)) {
                    $file_deleted = true;
                }
            }

            $stmt = $koneksi->prepare("UPDATE dt_siswa SET foto = 'user.png' WHERE id_siswa = ?");
            $stmt->bind_param("i", $id_siswa);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $deleted++;
            } else {
                $skipped++;
            }
            $stmt->close();
        }
        $koneksi->commit();
    } catch (Exception $e) {
        $koneksi->rollback();
        setAlert('danger', 'Gagal menghapus foto: ' . $e->getMessage());
        header("Location: $back");
        exit;
    }

    setAlert('success', "Hapus foto selesai: {$deleted} foto dihapus, {$skipped} dilewati.");
    header("Location: $back");
    exit;
}

// Reset foto banyak siswa ke placeholder (user.png)
if ($aksi === 'reset_foto') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids) || empty($ids)) {
        setAlert('warning', 'Tidak ada siswa yang dipilih.');
        header("Location: $back");
        exit;
    }

    $reset = 0;
    $skipped = 0;
    $koneksi->begin_transaction();
    try {
        foreach ($ids as $id_siswa) {
            $id_siswa = (int)$id_siswa;
            if ($id_siswa <= 0) {
                $skipped++;
                continue;
            }

            $stmt = $koneksi->prepare("SELECT foto FROM dt_siswa WHERE id_siswa = ? LIMIT 1");
            $stmt->bind_param("i", $id_siswa);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) {
                $skipped++;
                continue;
            }

            $foto_file = trim((string)($row['foto'] ?? ''));
            $file_deleted = false;
            if ($foto_file !== '' && $foto_file !== 'user.png' && file_exists($foto_dir . $foto_file)) {
                if (@unlink($foto_dir . $foto_file)) {
                    $file_deleted = true;
                }
            }

            $stmt = $koneksi->prepare("UPDATE dt_siswa SET foto = 'user.png' WHERE id_siswa = ?");
            $stmt->bind_param("i", $id_siswa);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $reset++;
            } else {
                $skipped++;
            }
            $stmt->close();
        }
        $koneksi->commit();
    } catch (Exception $e) {
        $koneksi->rollback();
        setAlert('danger', 'Gagal mereset foto: ' . $e->getMessage());
        header("Location: $back");
        exit;
    }

    setAlert('success', "Reset foto selesai: {$reset} foto direset, {$skipped} dilewati.");
    header("Location: $back");
    exit;
}

setAlert('danger', 'Aksi tidak valid.');
header("Location: $back");
exit;
