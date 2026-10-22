<?php
/**
 * Helper Audit Log Aktivitas (Administrasi Sistem).
 * Mencatat siapa mengakses aksi apa, kapan — untuk memperkuat RBAC.
 *
 * - audit_write()    : tulis satu baris ke dt_audit_log.
 * - audit_register() : daftarkan logger otomatis (register_shutdown_function)
 *                      sehingga setiap handler aksi tercatat tanpa mengubah
 *                      alur handler (dipanggil dari aksi/akses_guard.php).
 */

if (!function_exists('audit_client_ip')) {
    function audit_client_ip()
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = explode(',', $_SERVER[$k])[0];
                return substr(trim($ip), 0, 45);
            }
        }
        return null;
    }
}

if (!function_exists('audit_sensitive_key')) {
    function audit_sensitive_key($key)
    {
        $k = strtolower((string)$key);
        foreach (['password', 'passwd', 'pwd', 'token', 'secret'] as $s) {
            if (strpos($k, $s) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('audit_snapshot')) {
    /**
     * Ringkasan parameter request (GET/POST) sebagai JSON, tanpa data sensitif.
     * Array dinilai sebagai "(N item)" agar tidak membengkak (mis. mapel[]).
     */
    function audit_snapshot()
    {
        $out = [];
        foreach (['GET' => $_GET, 'POST' => $_POST] as $src => $arr) {
            if (!is_array($arr)) {
                continue;
            }
            foreach ($arr as $k => $v) {
                if (is_array($v)) {
                    $out[$src . '.' . $k] = '(' . count($v) . ' item)';
                    continue;
                }
                $out[$src . '.' . $k] = audit_sensitive_key($k)
                    ? '***'
                    : substr((string)$v, 0, 120);
            }
        }
        $json = json_encode($out, JSON_UNESCAPED_UNICODE);
        return ($json === false) ? null : substr($json, 0, 4000);
    }
}

if (!function_exists('audit_write')) {
    /**
     * Tulis satu entri audit. Aman dipanggil kapan pun (gagal = false, tanpa fatal).
     */
    function audit_write($koneksi, $aksi, $status = 'ok', $menu = null, $operasi = null, $keterangan = null)
    {
        if (!($koneksi instanceof mysqli) || $koneksi->connect_error) {
            return false;
        }

        $id_user  = isset($_SESSION['id_user']) ? (int)$_SESSION['id_user'] : null;
        $username = $_SESSION['username'] ?? null;
        $level    = $_SESSION['level_user'] ?? null;
        $ip       = audit_client_ip();
        $ua       = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

        if ($operasi === null) {
            $operasi = $_POST['aksi'] ?? $_GET['aksi'] ?? null;
        }
        if ($keterangan === null) {
            $keterangan = audit_snapshot();
        }

        $stmt = $koneksi->prepare(
            "INSERT INTO dt_audit_log
                (id_user, username, level_user, aksi, operasi, menu, keterangan, status, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param(
            'isssssssss',
            $id_user, $username, $level, $aksi, $operasi, $menu, $keterangan, $status, $ip, $ua
        );
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}

if (!function_exists('audit_register')) {
    /**
     * Daftarkan logger otomatis di akhir request handler.
     * Dipanggil dari aksi/akses_guard.php (setelah lolos/gagal cek izin).
     * $koneksi diambil dari global jika sudah ada; bila belum (mis. jalur ditolak),
     * buka koneksi sendiri lewat koneksi.php.
     */
    function audit_register($status = 'ok', $aksi = null, $menu = null)
    {
        register_shutdown_function(function () use ($status, $aksi, $menu) {
            $conn = $GLOBALS['koneksi'] ?? null;
            if (!($conn instanceof mysqli) || $conn->connect_error) {
                $conn = null;
                require_once __DIR__ . '/koneksi.php'; // scope closure: men-set $koneksi lokal
                if (isset($koneksi) && $koneksi instanceof mysqli && !$koneksi->connect_error) {
                    $conn = $koneksi;
                }
            }
            if (!$conn) {
                return;
            }
            if ($aksi === null) {
                $aksi = basename($_SERVER['SCRIPT_FILENAME'] ?? '') ?: 'unknown';
            }
            audit_write($conn, $aksi, $status, $menu);
        });
    }
}
