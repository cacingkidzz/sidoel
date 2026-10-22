<?php
/**
 * Helper Backup/Restore Database (Administrasi Sistem).
 * Memakai binary MySQL XAMPP (mysqldump.exe / mysql.exe) via exec().
 *
 * Host/user/password/database diambil dari variabel global koneksi.php
 * ($host, $user, $pass, $db) sehingga tetap satu sumber konfigurasi.
 */

if (!function_exists('backup_bin_exe')) {
    function backup_bin_exe($name)
    {
        $dir = defined('MYSQL_BIN_DIR') ? MYSQL_BIN_DIR : 'C:\\xampp\\mysql\\bin';
        return rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . $name;
    }
}

if (!function_exists('backup_available')) {
    function backup_available()
    {
        return function_exists('exec')
            && file_exists(backup_bin_exe('mysqldump.exe'))
            && file_exists(backup_bin_exe('mysql.exe'));
    }
}

if (!function_exists('backup_dir')) {
    function backup_dir()
    {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'backup';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }
}

if (!function_exists('backup_valid_name')) {
    // Nama file backup yang valid: tanpa path, berakhiran .sql.
    function backup_valid_name($name)
    {
        return is_string($name)
            && $name === basename($name)
            && (bool) preg_match('/^[A-Za-z0-9._-]+\.sql$/', $name);
    }
}

if (!function_exists('backup_file_path')) {
    function backup_file_path($name)
    {
        return backup_valid_name($name) ? (backup_dir() . DIRECTORY_SEPARATOR . $name) : null;
    }
}

if (!function_exists('backup_list')) {
    // Daftar file backup terurut terbaru lebih dulu.
    function backup_list()
    {
        $out = [];
        foreach (glob(backup_dir() . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $f) {
            $out[] = [
                'name'  => basename($f),
                'size'  => filesize($f),
                'mtime' => filemtime($f),
            ];
        }
        usort($out, function ($a, $b) {
            return $b['mtime'] <=> $a['mtime'];
        });
        return $out;
    }
}

if (!function_exists('backup_size_human')) {
    function backup_size_human($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $b = (float) $bytes;
        while ($b >= 1024 && $i < count($units) - 1) {
            $b /= 1024;
            $i++;
        }
        return ($i === 0 ? (int) $b : number_format($b, 1, ',', '.')) . ' ' . $units[$i];
    }
}

if (!function_exists('backup_conn_args')) {
    // Bangun argumen koneksi dari global koneksi.php.
    function backup_conn_args($host, $user, $pass)
    {
        $args = ' --host=' . escapeshellarg($host) . ' --user=' . escapeshellarg($user);
        if ($pass !== '' && $pass !== null) {
            $args .= ' --password=' . escapeshellarg($pass);
        }
        return $args;
    }
}

if (!function_exists('backup_dump')) {
    /**
     * Dump database ke $file. Mengembalikan [bool $ok, string $output].
     */
    function backup_dump($file, $host, $user, $pass, $db)
    {
        if (!backup_available()) {
            return [false, 'exec() tidak aktif atau binary MySQL tidak ditemukan.'];
        }
        $exe = escapeshellarg(backup_bin_exe('mysqldump.exe'));
        $out = escapeshellarg($file);
        $cmd = $exe . backup_conn_args($host, $user, $pass)
            . ' --single-transaction --routines --events --default-character-set=utf8mb4 --skip-lock-tables'
            . ' ' . escapeshellarg($db) . ' > ' . $out . ' 2>&1';
        $lines = [];
        $code = 1;
        @exec($cmd, $lines, $code);
        return [$code === 0 && file_exists($file) && filesize($file) > 0, implode("\n", $lines)];
    }
}

if (!function_exists('backup_restore')) {
    /**
     * Import $file (dump .sql) ke database. Mengembalikan [bool $ok, string $output].
     */
    function backup_restore($file, $host, $user, $pass, $db)
    {
        if (!backup_available()) {
            return [false, 'exec() tidak aktif atau binary MySQL tidak ditemukan.'];
        }
        $exe = escapeshellarg(backup_bin_exe('mysql.exe'));
        $in  = escapeshellarg($file);
        $cmd = $exe . backup_conn_args($host, $user, $pass)
            . ' --default-character-set=utf8mb4'
            . ' ' . escapeshellarg($db) . ' < ' . $in . ' 2>&1';
        $lines = [];
        $code = 1;
        @exec($cmd, $lines, $code);
        return [$code === 0, implode("\n", $lines)];
    }
}
