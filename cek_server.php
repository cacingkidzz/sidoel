<?php
/**
 * SiDoel — Diagnosa Server (file SEMENTARA, jangan di-commit)
 * ---------------------------------------------------------------------------
 * Cara pakai:
 *   1. Unggah file ini ke folder aplikasi di hosting, mis. public_html/sidoel/
 *   2. Buka: https://domain-anda/cek_server.php
 *   3. Kirim hasilnya / perbaiki sesuai petunjuk, lalu HAPUS file ini.
 *
 * Ditulis dengan sintaks PHP lama (tanpa ??, tanpa arrow function) agar tetap
 * bisa jalan walau versi PHP hosting masih 5.6/7.x — justru itu yang mau dicek.
 */

// Baris paling awal: kalau ini saja tidak muncul, PHP memang tidak jalan.
echo '<!-- PHP ' . PHP_VERSION . ' | SAPI ' . PHP_SAPI . ' -->';

header('X-Robots-Tag: noindex, nofollow', true);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__FILE__);

$rows = array();
$rows[] = array('PHP versi', PHP_VERSION);
$rows[] = array('PHP terlalu lama?', version_compare(PHP_VERSION, '8.0.0', '>=') ? 'tidak (OK)' : 'YA — SiDoel butuh PHP 8.0+ (disarankan 8.2)');
$rows[] = array('Server API', PHP_SAPI);
$rows[] = array('Sistem operasi', PHP_OS);
$rows[] = array('max_execution_time', ini_get('max_execution_time') . ' detik');
$rows[] = array('upload_max_filesize', ini_get('upload_max_filesize') . ' / post ' . ini_get('post_max_size'));
$rows[] = array('display_errors', ini_get('display_errors') ? 'aktif' : 'nonaktif (normal di produksi)');
$rows[] = array('Folder aplikasi', $root);

$exts = array('mysqli', 'gd', 'mbstring', 'zip', 'fileinfo', 'json', 'xml', 'openssl', 'curl', 'session');
foreach ($exts as $ext) {
    $rows[] = array('Ekstensi ' . $ext, extension_loaded($ext) ? 'aktif' : 'TIDAK AKTIF');
}

$rows[] = array('Folder admin/', is_dir($root . '/admin') ? 'ada' : 'TIDAK ADA (file belum terunggah / path salah)');
$rows[] = array('admin/vendor/autoload.php', is_file($root . '/admin/vendor/autoload.php') ? 'ada' : 'TIDAK ADA (export Excel tidak jalan)');
$rows[] = array('admin/koneksi.local.php', is_file($root . '/admin/koneksi.local.php') ? 'ada' : 'BELUM ADA');
$rows[] = array('admin/backup/ bisa ditulis', is_dir($root . '/admin/backup') ? (is_writable($root . '/admin/backup') ? 'ya' : 'tidak (chmod 755/775)') : 'folder belum ada');
$rows[] = array('foto_siswa/ bisa ditulis', is_dir($root . '/admin/assets/img/foto_siswa') ? (is_writable($root . '/admin/assets/img/foto_siswa') ? 'ya' : 'tidak (chmod 755/775)') : 'folder belum ada');

// --- Uji koneksi database ---------------------------------------------------
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'data_backup';
$lokal = $root . '/admin/koneksi.local.php';
if (is_file($lokal)) {
    include $lokal; // menimpa $host/$user/$pass/$db
    if (isset($host)) { $db_host = $host; }
    if (isset($user)) { $db_user = $user; }
    if (isset($pass)) { $db_pass = $pass; }
    if (isset($db)) { $db_name = $db; }
}
$rows[] = array('DB host / user / nama', $db_host . ' / ' . $db_user . ' / ' . $db_name);

if (!class_exists('mysqli')) {
    $rows[] = array('Koneksi database', 'TIDAK BISA DIUJI — ekstensi mysqli tidak aktif');
} else {
    if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
    $koneksi = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($koneksi->connect_errno) {
        $rows[] = array('Koneksi database', 'GAGAL — ' . $koneksi->connect_error);
    } else {
        $rows[] = array('Koneksi database', 'BERHASIL');
        $t = 0;
        $res = $koneksi->query("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = DATABASE()");
        if ($res) {
            $ar = $res->fetch_assoc();
            $t = (int) $ar['n'];
        }
        $rows[] = array('Jumlah tabel di database', $t . ($t >= 30 ? ' (OK)' : ' (terlalu sedikit — dump belum diimpor?)'));
        $tabel = array('dt_siswa', 'dt_guru', 'dt_user', 'dt_kelas', 'dt_mapel', 'dt_kelas_siswa');
        foreach ($tabel as $tb) {
            $n = '—';
            $r2 = @$koneksi->query("SELECT COUNT(*) AS n FROM `" . $tb . "`");
            if ($r2) {
                $a2 = $r2->fetch_assoc();
                $n = (int) $a2['n'];
            }
            $rows[] = array('Baris ' . $tb, (string) $n);
        }
    }
}

function cs_esc($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Diagnosa Server — SiDoel</title>
<style>
    body { font-family: system-ui, "Segoe UI", Arial, sans-serif; background: #f8fafc; margin: 0; padding: 24px; color: #0f172a; }
    .box { max-width: 880px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px 26px; box-shadow: 0 6px 24px rgba(15,23,42,.06); }
    h1 { font-size: 20px; margin: 0 0 4px; }
    p.sub { margin: 0 0 18px; color: #475569; font-size: 14px; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eef2f7; vertical-align: top; }
    th { width: 40%; color: #334155; font-weight: 600; }
    td.bad { color: #b91c1c; font-weight: 600; }
    td.ok { color: #15803d; font-weight: 600; }
    code { background: #f1f5f9; padding: 1px 5px; border-radius: 5px; font-size: 13px; }
    .warn { margin-top: 18px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 14px; border-radius: 10px; font-size: 14px; }
</style>
</head>
<body>
<div class="box">
    <h1>Diagnosa Server SiDoel</h1>
    <p class="sub">Waktu: <?php echo cs_esc(date('d-m-Y H:i:s')); ?> · Zona waktu: <?php echo cs_esc(date_default_timezone_get()); ?></p>
    <table>
        <?php foreach ($rows as $r) {
            $val = (string) $r[1];
            $cls = '';
            $bad = array('TIDAK', 'GAGAL', 'BELUM', 'YA —', 'tidak (');
            foreach ($bad as $b) {
                if (stripos($val, $b) !== false) { $cls = 'bad'; }
            }
            if (stripos($val, 'BERHASIL') !== false || stripos($val, '(OK)') !== false) { $cls = 'ok'; }
            ?>
            <tr><th><?php echo cs_esc($r[0]); ?></th><td class="<?php echo $cls; ?>"><?php echo cs_esc($val); ?></td></tr>
        <?php } ?>
    </table>
    <div class="warn"><strong>Penting:</strong> hapus file <code>cek_server.php</code> setelah selesai dipakai.</div>
</div>
</body>
</html>
