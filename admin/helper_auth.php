<?php
/**
 * Helper autentikasi password (shared).
 * Mendukung verifikasi hash MD5 lama (kompatibilitas) dan bcrypt modern,
 * serta auto-upgrade MD5 -> bcrypt saat login berhasil.
 */

if (!function_exists('password_md5_lama')) {
    /**
     * True bila hash tersimpan terlihat seperti hash MD5 lama (32 hex),
     * bukan hash modern (bcrypt/argon2 yang diawali '$').
     */
    function password_md5_lama($stored)
    {
        if (!is_string($stored) || $stored === '') {
            return false;
        }
        if ($stored[0] === '$') {
            return false;
        }
        return (bool) preg_match('/^[a-f0-9]{32}$/i', $stored);
    }
}

if (!function_exists('verifikasi_password')) {
    /**
     * Verifikasi password mentah terhadap hash tersimpan (MD5 atau bcrypt).
     */
    function verifikasi_password($plain, $stored)
    {
        if (!is_string($plain) || !is_string($stored) || $stored === '') {
            return false;
        }
        if (password_md5_lama($stored)) {
            return hash_equals(strtolower($stored), md5($plain));
        }
        return password_verify($plain, $stored);
    }
}

if (!function_exists('hash_password')) {
    /**
     * Hash password baru dengan bcrypt.
     */
    function hash_password($plain)
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }
}

if (!function_exists('upgrade_password_jika_perlu')) {
    /**
     * Bila password cocok namun masih tersimpan sebagai MD5 lama,
     * ganti ke bcrypt. Mengembalikan true bila berhasil di-upgrade.
     */
    function upgrade_password_jika_perlu($koneksi, $id_user, $plain, $stored)
    {
        if (!password_md5_lama($stored)) {
            return false;
        }
        if (!hash_equals(strtolower($stored), md5($plain))) {
            return false;
        }
        $new = hash_password($plain);
        $stmt = $koneksi->prepare("UPDATE dt_user SET password = ? WHERE id_user = ?");
        if (!$stmt) {
            return false;
        }
        $id_user = (int) $id_user;
        $stmt->bind_param('si', $new, $id_user);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool) $ok;
    }
}
