<?php
/**
 * Helper Backup/Restore Database (Administrasi Sistem).
 *
 * Ada dua mode kerja:
 *   1. BINARY : memakai mysqldump.exe / mysql.exe via exec().
 *               Cepat & paling lengkap (termasuk trigger, procedure, function, event).
 *   2. PHP    : fallback murni PHP (mysqli). Dipakai otomatis bila exec() dimatikan
 *               (umum di shared hosting seperti Jagoan Hosting / Rumahweb) atau
 *               binary MySQL tidak ditemukan. Struktur + data semua tabel & view
 *               disertakan; trigger/procedure/function/event TIDAK disertakan.
 *
 * Host/user/password/database diambil dari variabel global koneksi.php
 * ($host, $user, $pass, $db) sehingga tetap satu sumber konfigurasi.
 */

if (!function_exists('backup_bin_dir')) {
    function backup_bin_dir()
    {
        return defined('MYSQL_BIN_DIR') ? trim((string) MYSQL_BIN_DIR) : '';
    }
}

if (!function_exists('backup_bin_exe')) {
    // Path binary MySQL; pada Windows coba dengan/tanpa akhiran .exe.
    function backup_bin_exe($name)
    {
        $dir   = rtrim(backup_bin_dir(), '\\/');
        $names = DIRECTORY_SEPARATOR === '\\' ? [$name . '.exe', $name] : [$name, $name . '.exe'];

        if ($dir !== '') {
            foreach ($names as $n) {
                $p = $dir . DIRECTORY_SEPARATOR . $n;
                if (is_file($p)) {
                    return $p;
                }
            }
            return $dir . DIRECTORY_SEPARATOR . $names[0];
        }

        // MYSQL_BIN_DIR kosong → andalkan PATH sistem.
        return $names[0];
    }
}

if (!function_exists('backup_exec_aktif')) {
    function backup_exec_aktif()
    {
        if (!function_exists('exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return !in_array('exec', $disabled, true);
    }
}

if (!function_exists('backup_binary_ready')) {
    // True bila mode binary (mysqldump/mysql) bisa dipakai.
    function backup_binary_ready()
    {
        if (!backup_exec_aktif()) {
            return false;
        }
        if (backup_bin_dir() === '') {
            // Tanpa MYSQL_BIN_DIR: cek ketersediaan lewat PATH (Linux/Unix).
            $lines = [];
            $code  = 1;
            @exec('command -v mysqldump', $lines, $code);
            return $code === 0 && !empty($lines);
        }
        return is_file(backup_bin_exe('mysqldump')) && is_file(backup_bin_exe('mysql'));
    }
}

if (!function_exists('backup_php_ready')) {
    // True bila mode PHP (mysqli) bisa dipakai.
    function backup_php_ready()
    {
        return class_exists('mysqli');
    }
}

if (!function_exists('backup_mode')) {
    // 'binary' | 'php'
    function backup_mode()
    {
        return backup_binary_ready() ? 'binary' : 'php';
    }
}

if (!function_exists('backup_mode_label')) {
    function backup_mode_label()
    {
        return backup_mode() === 'binary' ? 'mysqldump (binary)' : 'PHP murni (fallback)';
    }
}

if (!function_exists('backup_available')) {
    // Fitur backup/restore selalu bisa dipakai selama salah satu mode tersedia.
    function backup_available()
    {
        return backup_binary_ready() || backup_php_ready();
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

if (!function_exists('backup_open_conn')) {
    /**
     * Ambil koneksi mysqli untuk mode PHP:
     * pakai $koneksi yang diberikan → global $koneksi → buka koneksi baru.
     */
    function backup_open_conn($koneksi, $host, $user, $pass, $db)
    {
        if ($koneksi instanceof mysqli) {
            return $koneksi;
        }
        if (isset($GLOBALS['koneksi']) && $GLOBALS['koneksi'] instanceof mysqli) {
            return $GLOBALS['koneksi'];
        }
        if (!class_exists('mysqli')) {
            return null;
        }
        $conn = @new mysqli($host, $user, $pass, $db);
        if ($conn->connect_errno) {
            return null;
        }
        @$conn->set_charset('utf8mb4');
        return $conn;
    }
}

if (!function_exists('backup_dump')) {
    /**
     * Dump database ke $file. Mengembalikan [bool $ok, string $output].
     * Mode binary (mysqldump) bila tersedia, jika tidak memakai mode PHP.
     */
    function backup_dump($file, $host, $user, $pass, $db, $koneksi = null)
    {
        if (backup_binary_ready()) {
            $exe = escapeshellarg(backup_bin_exe('mysqldump'));
            $out = escapeshellarg($file);
            $cmd = $exe . backup_conn_args($host, $user, $pass)
                . ' --single-transaction --routines --events --default-character-set=utf8mb4 --skip-lock-tables'
                . ' ' . escapeshellarg($db) . ' > ' . $out . ' 2>&1';
            $lines = [];
            $code  = 1;
            @exec($cmd, $lines, $code);
            return [$code === 0 && file_exists($file) && filesize($file) > 0, implode("\n", $lines)];
        }

        $conn = backup_open_conn($koneksi, $host, $user, $pass, $db);
        if (!$conn) {
            return [false, 'Mode PHP tidak dapat terhubung ke database.'];
        }
        return backup_dump_php($file, $conn);
    }
}

if (!function_exists('backup_restore')) {
    /**
     * Import $file (dump .sql) ke database. Mengembalikan [bool $ok, string $output].
     * Mode binary (mysql client) bila tersedia, jika tidak memakai mode PHP.
     */
    function backup_restore($file, $host, $user, $pass, $db, $koneksi = null)
    {
        if (backup_binary_ready()) {
            $exe = escapeshellarg(backup_bin_exe('mysql'));
            $in  = escapeshellarg($file);
            $cmd = $exe . backup_conn_args($host, $user, $pass)
                . ' --default-character-set=utf8mb4'
                . ' ' . escapeshellarg($db) . ' < ' . $in . ' 2>&1';
            $lines = [];
            $code  = 1;
            @exec($cmd, $lines, $code);
            return [$code === 0, implode("\n", $lines)];
        }

        $conn = backup_open_conn($koneksi, $host, $user, $pass, $db);
        if (!$conn) {
            return [false, 'Mode PHP tidak dapat terhubung ke database.'];
        }
        return backup_restore_php($file, $conn);
    }
}

if (!function_exists('backup_quote_ident')) {
    function backup_quote_ident($name)
    {
        return '`' . str_replace('`', '``', (string) $name) . '`';
    }
}

if (!function_exists('backup_dump_php')) {
    /**
     * Dump database murni PHP (tanpa exec/mysqldump).
     * Mengembalikan [bool $ok, string $output].
     */
    function backup_dump_php($file, $koneksi)
    {
        @set_time_limit(0);

        $fh = @fopen($file, 'wb');
        if (!$fh) {
            return [false, 'Gagal membuat file backup (folder admin/backup/ tidak writable?).'];
        }

        $dbname = '';
        if ($res = @$koneksi->query('SELECT DATABASE()')) {
            $row    = $res->fetch_row();
            $dbname = (string) ($row[0] ?? '');
            $res->free();
        }

        $w = function ($s) use ($fh) {
            fwrite($fh, $s);
        };

        $w("-- SiDoel — Backup Database\n");
        $w('-- Database : ' . $dbname . "\n");
        $w('-- Dibuat   : ' . date('Y-m-d H:i:s') . "\n");
        $w("-- Metode   : PHP (fallback tanpa mysqldump)\n");
        $w("-- Catatan  : trigger, procedure, function, dan event tidak disertakan.\n\n");
        $w("SET NAMES utf8mb4;\n");
        $w("SET FOREIGN_KEY_CHECKS=0;\n");
        $w("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
        $w("SET time_zone='+00:00';\n\n");

        // Kumpulkan daftar tabel & view
        $tables = [];
        $res = @$koneksi->query('SHOW FULL TABLES');
        if (!$res) {
            fclose($fh);
            @unlink($file);
            return [false, 'Gagal membaca daftar tabel: ' . $koneksi->error];
        }
        while ($row = $res->fetch_row()) {
            $tables[] = [
                'name' => (string) $row[0],
                'type' => strtoupper((string) ($row[1] ?? 'BASE TABLE')),
            ];
        }
        $res->free();

        if (!$tables) {
            fclose($fh);
            @unlink($file);
            return [false, 'Database tidak memiliki tabel.'];
        }

        $total_rows = 0;
        $total_tbl  = 0;

        // Tabel dulu, view di akhir (view bisa bergantung pada tabel lain).
        usort($tables, function ($a, $b) {
            $pa = $a['type'] === 'VIEW' ? 1 : 0;
            $pb = $b['type'] === 'VIEW' ? 1 : 0;
            return $pa <=> $pb ?: strcmp($a['name'], $b['name']);
        });

        foreach ($tables as $t) {
            $name = $t['name'];
            $q    = backup_quote_ident($name);

            // --- View ---
            if ($t['type'] === 'VIEW') {
                $create = null;
                if ($res = @$koneksi->query('SHOW CREATE VIEW ' . $q)) {
                    $row    = $res->fetch_assoc();
                    $create = $row['Create View'] ?? null;
                    $res->free();
                }
                if ($create !== null) {
                    $w("\n--\n-- View " . $q . "\n--\n\n");
                    $w('DROP VIEW IF EXISTS ' . $q . ";\n");
                    $w($create . ";\n");
                }
                continue;
            }

            // --- Struktur tabel ---
            $create = null;
            if ($res = @$koneksi->query('SHOW CREATE TABLE ' . $q)) {
                $row    = $res->fetch_row();
                $create = $row[1] ?? null;
                $res->free();
            }
            if ($create === null) {
                $w("\n-- (gagal membaca struktur tabel " . $q . ")\n");
                continue;
            }

            $w("\n--\n-- Tabel " . $q . "\n--\n\n");
            $w('DROP TABLE IF EXISTS ' . $q . ";\n");
            $w($create . ";\n\n");
            $total_tbl++;

            // --- Data tabel ---
            $res = @$koneksi->query('SELECT * FROM ' . $q, MYSQLI_USE_RESULT);
            if (!$res) {
                $w('-- (gagal membaca data: ' . str_replace("\n", ' ', (string) $koneksi->error) . ")\n");
                continue;
            }

            $cols = [];
            foreach ($res->fetch_fields() as $f) {
                $cols[] = backup_quote_ident($f->name);
            }
            $col_list = implode(', ', $cols);

            $batch      = [];
            $batch_size = 0;
            $rows       = 0;

            $flush = function () use (&$batch, &$batch_size, $fh, $q, $col_list) {
                if (!$batch) {
                    return;
                }
                fwrite(
                    $fh,
                    'INSERT INTO ' . $q . ' (' . $col_list . ") VALUES\n"
                        . implode(",\n", $batch) . ";\n"
                );
                $batch      = [];
                $batch_size = 0;
            };

            while ($row = $res->fetch_row()) {
                $vals = [];
                foreach ($row as $v) {
                    $vals[] = ($v === null) ? 'NULL' : "'" . $koneksi->real_escape_string($v) . "'";
                }
                $line         = '(' . implode(', ', $vals) . ')';
                $batch[]      = $line;
                $batch_size  += strlen($line) + 3;
                $rows++;

                if (count($batch) >= 200 || $batch_size >= 524288) {
                    $flush();
                }
            }
            $res->free();
            $flush();

            $total_rows += $rows;
            $w('-- (' . $rows . " baris)\n");
        }

        $w("\nSET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
        clearstatcache();

        if (!is_file($file) || filesize($file) <= 0) {
            @unlink($file);
            return [false, 'File backup kosong.'];
        }

        return [true, $total_tbl . ' tabel, ' . $total_rows . ' baris'];
    }
}

if (!function_exists('backup_sql_split')) {
    /**
     * Pecah isi file .sql menjadi perintah-perintah terpisah.
     * Menangani: quote ' " `, escape backslash, komentar garis (-- dan #),
     * komentar blok, komentar eksekusi MySQL, dan perintah DELIMITER.
     */
    function backup_sql_split($sql)
    {
        $out   = [];
        $len   = strlen($sql);
        $i     = 0;
        $delim = ';';
        $buf   = '';
        $in_s  = false; // di dalam '...'
        $in_d  = false; // di dalam "..."
        $in_b  = false; // di dalam `...`

        while ($i < $len) {
            // Perintah DELIMITER (hanya dikenali di awal perintah)
            if (trim($buf) === '' && ($sql[$i] === 'D' || $sql[$i] === 'd')
                && preg_match('/\GDELIMITER[ \t]+(\S+)[ \t]*\r?\n?/i', $sql, $m, 0, $i)
            ) {
                $delim = $m[1];
                $i    += strlen($m[0]);
                $buf   = '';
                continue;
            }

            $ch = $sql[$i];

            if ($in_s || $in_d || $in_b) {
                $buf .= $ch;
                if ($ch === '\\' && !$in_b && $i + 1 < $len) {
                    $buf .= $sql[$i + 1];
                    $i   += 2;
                    continue;
                }
                if ($in_s && $ch === "'") {
                    if ($i + 1 < $len && $sql[$i + 1] === "'") {
                        $buf .= "'";
                        $i   += 2;
                        continue;
                    }
                    $in_s = false;
                } elseif ($in_d && $ch === '"') {
                    if ($i + 1 < $len && $sql[$i + 1] === '"') {
                        $buf .= '"';
                        $i   += 2;
                        continue;
                    }
                    $in_d = false;
                } elseif ($in_b && $ch === '`') {
                    if ($i + 1 < $len && $sql[$i + 1] === '`') {
                        $buf .= '`';
                        $i   += 2;
                        continue;
                    }
                    $in_b = false;
                }
                $i++;
                continue;
            }

            // Di luar quote
            if (substr($sql, $i, strlen($delim)) === $delim) {
                $stmt = trim($buf);
                if ($stmt !== '') {
                    $out[] = $stmt;
                }
                $buf = '';
                $i  += strlen($delim);
                continue;
            }

            if ($ch === "'") {
                $in_s = true;
                $buf .= $ch;
                $i++;
                continue;
            }
            if ($ch === '"') {
                $in_d = true;
                $buf .= $ch;
                $i++;
                continue;
            }
            if ($ch === '`') {
                $in_b = true;
                $buf .= $ch;
                $i++;
                continue;
            }
            if ($ch === '#') { // komentar sampai akhir baris
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                $next = $i + 2 < $len ? $sql[$i + 2] : "\n";
                if ($next === ' ' || $next === "\t" || $next === "\n" || $next === "\r") {
                    while ($i < $len && $sql[$i] !== "\n") {
                        $i++;
                    }
                    continue;
                }
            }
            if ($ch === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) {
                    $i = $len;
                } else {
                    // /*! ... */ = komentar yang tetap dieksekusi MySQL
                    if ($i + 2 < $len && $sql[$i + 2] === '!') {
                        $buf .= substr($sql, $i, $end + 2 - $i);
                    }
                    $i = $end + 2;
                }
                continue;
            }

            $buf .= $ch;
            $i++;
        }

        $stmt = trim($buf);
        if ($stmt !== '') {
            $out[] = $stmt;
        }
        return $out;
    }
}

if (!function_exists('backup_restore_php')) {
    /**
     * Restore file .sql murni PHP (tanpa binary mysql).
     * Mengembalikan [bool $ok, string $output].
     */
    function backup_restore_php($file, $koneksi)
    {
        @set_time_limit(0);

        $sql = @file_get_contents($file);
        if ($sql === false) {
            return [false, 'Gagal membaca file backup.'];
        }
        $stmts = backup_sql_split($sql);
        unset($sql);

        if (!$stmts) {
            return [false, 'File backup tidak berisi perintah SQL yang dapat dijalankan.'];
        }

        @$koneksi->query('SET FOREIGN_KEY_CHECKS=0');
        $errors = [];
        $done   = 0;

        foreach ($stmts as $stmt) {
            if (@$koneksi->query($stmt)) {
                $done++;
            } else {
                $errors[] = trim((string) $koneksi->error);
                if (count($errors) >= 5) {
                    break;
                }
            }
        }

        @$koneksi->query('SET FOREIGN_KEY_CHECKS=1');

        $total = count($stmts);
        if ($errors) {
            return [false, $done . '/' . $total . ' perintah berhasil. Error: ' . implode(' | ', array_unique($errors))];
        }
        return [true, $done . '/' . $total . ' perintah dijalankan'];
    }
}
