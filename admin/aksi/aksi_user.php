<?php
require_once(__DIR__ . '/akses_guard.php');
// Pastikan sesi sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include("../koneksi.php"); // Sesuaikan path file koneksi.php

// ... (Bagian pengecekan otentikasi)

// Cek apakah ada parameter aksi
if (isset($_POST['aksi']) || isset($_GET['aksi'])) {
    $aksi = isset($_POST['aksi']) ? $_POST['aksi'] : $_GET['aksi'];

    // --- FUNGSI TAMBAH DATA USER BARU ---
    if ($aksi == 'tambah') {
        // Ambil data POST
        $id_fk        = isset($_POST['id_fk']) ? (int) $_POST['id_fk'] : 0;
        $username     = trim((string) ($_POST['username'] ?? ''));
        $password_raw = trim((string) ($_POST['password'] ?? ''));
        $level_user   = (string) ($_POST['level_user'] ?? '');

        // Level akses yang sah (whitelist — cegah nilai enum tak dikenal)
        $level_diizinkan = ['Admin', 'Kurikulum', 'Kesiswaan', 'Guru', 'Bendahara'];

        if ($username === '' || $password_raw === '' || !in_array($level_user, $level_diizinkan, true)) {
            $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Data user tidak lengkap atau level akses tidak valid.'];
            header("Location: ../index.php?menu=user");
            exit;
        }

        // 1. Hashing Password (bcrypt)
        $password_hash = hash_password($password_raw);

        // 2. Query INSERT (prepared). id_fk kosong = tanpa relasi guru (mis. Bendahara).
        $id_fk_val = $id_fk > 0 ? $id_fk : null;
        $stmt = $koneksi->prepare("INSERT INTO dt_user (username, password, level_user, id_fk, status_aktif) VALUES (?, ?, ?, ?, 'Y')");
        $berhasil = false;
        $errno = 0;
        $error = '';
        if ($stmt) {
            $stmt->bind_param('sssi', $username, $password_hash, $level_user, $id_fk_val);
            $berhasil = $stmt->execute();
            $errno = $stmt->errno;
            $error = $stmt->error;
            $stmt->close();
        } else {
            $errno = $koneksi->errno;
            $error = $koneksi->error;
        }

        if ($berhasil) {
            $_SESSION['alert'] = ['type' => 'success', 'msg' => 'Akun user <b>' . htmlspecialchars($username) . '</b> berhasil ditambahkan dengan level <b>' . htmlspecialchars($level_user) . '</b>.'];
        } else {
            // Cek jika error karena UNIQUE constraint (username sudah ada)
            if ($errno == 1062) {
                $_SESSION['alert'] = ['type' => 'warning', 'msg' => 'Gagal! Username (Email) <b>' . htmlspecialchars($username) . '</b> sudah terdaftar.'];
            } else {
                $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Data user gagal ditambahkan. Error: ' . htmlspecialchars($error)];
            }
        }

        header("Location: ../index.php?menu=user");
        exit;
    }

    // --- FUNGSI UPDATE PASSWORD ---
    elseif ($aksi == 'update_password') {
        // Ambil dan sanitasi data POST
        $id_user        = $koneksi->real_escape_string($_POST['id_user']);

        // FIX: Gunakan trim() untuk menghilangkan spasi tersembunyi
        $password_raw   = trim($_POST['password']); // Password mentah

        // 1. Hashing Password Baru (bcrypt)
        $password_hash = hash_password($password_raw);

        // 2. Query UPDATE
        $sql = "UPDATE dt_user SET 
                password = '$password_hash'
                WHERE id_user = '$id_user'";

        if ($koneksi->query($sql)) {
            // Ambil username untuk pesan alert
            $q_user = $koneksi->query("SELECT username FROM dt_user WHERE id_user = '$id_user'")->fetch_assoc();
            $username = $q_user['username'] ?? 'User ID ' . $id_user;

            $_SESSION['alert'] = ['type' => 'success', 'msg' => 'Password user **' . $username . '** berhasil direset.'];
        } else {
            $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Password gagal direset. Error: ' . $koneksi->error];
        }

        header("Location: ../index.php?menu=user");
        exit;
    }

    // --- FUNGSI HAPUS DATA ---
    elseif ($aksi == 'hapus') {
        // Ambil dan sanitasi data GET
        $id_user = $koneksi->real_escape_string($_GET['id_user']);

        // Ambil username untuk pesan alert
        $q_user = $koneksi->query("SELECT username FROM dt_user WHERE id_user = '$id_user'")->fetch_assoc();
        $username = $q_user['username'] ?? 'User ID ' . $id_user;

        // Query DELETE
        $sql = "DELETE FROM dt_user WHERE id_user = '$id_user'";

        if ($koneksi->query($sql)) {
            $_SESSION['alert'] = ['type' => 'success', 'msg' => 'Data user **' . $username . '** berhasil dihapus.'];
        } else {
            $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Data user gagal dihapus. Error: ' . $koneksi->error];
        }

        header("Location: ../index.php?menu=user");
        exit;
    }

    // Jika aksi tidak valid
    else {
        $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Aksi tidak valid.'];
        header("Location: ../index.php?menu=user");
        exit;
    }
} else {
    // Jika tidak ada parameter aksi
    $_SESSION['alert'] = ['type' => 'danger', 'msg' => 'Akses langsung tidak diizinkan.'];
    header("Location: ../index.php?menu=user");
    exit;
}
