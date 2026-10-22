<?php
// ===============================================
// File: aksi/aksi_manajemen_ta.php
// SALIN DATA ANTAR TAHUN AJARAN (Administrasi Sistem). RBAC: Admin.
// Komponen yang disalin: kelas, wali kelas, pengampu mapel.
// Pemetaan kelas: tingkat + nama_kelas + id_jurusan (identitas rombel).
// Aksi: 'preview' (dry-run, disimpan ke session) & 'salin' (eksekusi transaksional).
// ===============================================
require_once(__DIR__ . '/akses_guard.php');
include("../koneksi.php");

$back_url = "../index.php?menu=manajemen_ta";
$aksi = $_POST['aksi'] ?? '';

function ta_set_alert($type, $msg)
{
    $_SESSION['alert'] = ['type' => $type, 'msg' => $msg];
}

// Peta rombel -> id_kelas untuk satu TA. Key = "tingkat|nama_kelas|id_jurusan".
function ta_map_kelas($koneksi, $id_ta)
{
    $map = [];
    $q = $koneksi->query("SELECT id_kelas, tingkat, nama_kelas, id_jurusan FROM dt_kelas WHERE id_tahun_ajaran = " . (int)$id_ta);
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $map[$r['tingkat'] . '|' . $r['nama_kelas'] . '|' . (int)$r['id_jurusan']] = (int)$r['id_kelas'];
        }
    }
    return $map;
}

function ta_key_kelas($k)
{
    return $k['tingkat'] . '|' . $k['nama_kelas'] . '|' . (int)$k['id_jurusan'];
}

function ta_ambil_ta($koneksi, $id)
{
    $stmt = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE id_tahun_ajaran = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// ----------------------------------------------------------
// VALIDASI DASAR
// ----------------------------------------------------------
if ($aksi !== 'preview' && $aksi !== 'salin') {
    ta_set_alert('danger', 'Aksi tidak valid.');
    header("Location: $back_url");
    exit;
}

$id_sumber = (int)($_POST['id_sumber'] ?? 0);
$id_target = (int)($_POST['id_target'] ?? 0);
$komp = [
    'kelas'    => isset($_POST['komp_kelas']) ? 1 : 0,
    'wali'     => isset($_POST['komp_wali']) ? 1 : 0,
    'pengampu' => isset($_POST['komp_pengampu']) ? 1 : 0,
];

if ($id_sumber <= 0 || $id_target <= 0) {
    ta_set_alert('danger', 'Tahun ajaran sumber/tujuan belum dipilih.');
    header("Location: $back_url");
    exit;
}
if ($id_sumber === $id_target) {
    ta_set_alert('danger', 'Tahun ajaran sumber dan tujuan tidak boleh sama.');
    header("Location: $back_url");
    exit;
}
if (!$komp['kelas'] && !$komp['wali'] && !$komp['pengampu']) {
    ta_set_alert('danger', 'Pilih minimal satu komponen data yang akan disalin.');
    header("Location: $back_url");
    exit;
}

$ta_sumber = ta_ambil_ta($koneksi, $id_sumber);
$ta_target = ta_ambil_ta($koneksi, $id_target);
if (!$ta_sumber || !$ta_target) {
    ta_set_alert('danger', 'Data tahun ajaran tidak ditemukan.');
    header("Location: $back_url");
    exit;
}
$nama_sumber = $ta_sumber['tahun_ajaran'];
$nama_target = $ta_target['tahun_ajaran'];

// ==========================================================
// PREVIEW (DRY-RUN) — hitung rencana tanpa menulis DB
// ==========================================================
if ($aksi === 'preview') {
    $target_map = ta_map_kelas($koneksi, $id_target);

    // Rencana kelas
    $kelas_items = [];
    $kelas_baru = 0;
    $kelas_ada = 0;
    $rencana_key = [];
    $q = $koneksi->query("
        SELECT k.id_kelas, k.tingkat, k.nama_kelas, k.id_jurusan, j.nama_jurusan
        FROM dt_kelas k
        LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
        WHERE k.id_tahun_ajaran = $id_sumber
        ORDER BY k.tingkat, j.nama_jurusan, k.nama_kelas
    ");
    if ($q) {
        while ($k = $q->fetch_assoc()) {
            $key = ta_key_kelas($k);
            $ada = isset($target_map[$key]);
            if ($ada) {
                $kelas_ada++;
            } else {
                $kelas_baru++;
                $rencana_key[$key] = 1;
            }
            $kelas_items[] = [
                'nama'    => $k['tingkat'] . '-' . $k['nama_kelas'],
                'jurusan' => $k['nama_jurusan'],
                'status'  => $ada ? 'ada' : 'baru',
            ];
        }
    }

    // Peta efektif: kelas target yang ada + kelas baru yang direncanakan (bila komponen kelas dipilih)
    $effective_map = $target_map;
    if ($komp['kelas']) {
        foreach ($rencana_key as $key => $x) {
            $effective_map[$key] = 'NEW';
        }
    }

    // Rencana wali kelas
    $wali_baru = 0;
    $wali_ada = 0;
    $wali_skip = 0;
    $wali_items = [];
    if ($komp['wali']) {
        $q = $koneksi->query("
            SELECT w.id_guru, g.nama_guru, k.tingkat, k.nama_kelas, k.id_jurusan
            FROM dt_wali_kelas w
            INNER JOIN dt_kelas k ON k.id_kelas = w.id_kelas
            LEFT JOIN dt_guru g ON g.id_guru = w.id_guru
            WHERE w.id_tahun_ajaran = $id_sumber
        ");
        if ($q) {
            while ($w = $q->fetch_assoc()) {
                $key = ta_key_kelas($w);
                if (!isset($effective_map[$key])) {
                    $wali_skip++;
                    continue;
                }
                $tk = $effective_map[$key];
                $ada = false;
                if ($tk !== 'NEW') {
                    $e = $koneksi->query("SELECT id_wali_kelas FROM dt_wali_kelas WHERE id_kelas = " . (int)$tk . " LIMIT 1");
                    $ada = ($e && $e->num_rows > 0);
                }
                if ($ada) {
                    $wali_ada++;
                } else {
                    $wali_baru++;
                }
                $wali_items[] = [
                    'kelas'  => $w['tingkat'] . '-' . $w['nama_kelas'],
                    'guru'   => $w['nama_guru'] ?: '(guru #' . (int)$w['id_guru'] . ')',
                    'status' => $ada ? 'ada' : 'baru',
                ];
            }
        }
    }

    // Rencana pengampu mapel
    $pen_baru = 0;
    $pen_ada = 0;
    $pen_skip = 0;
    if ($komp['pengampu']) {
        $q = $koneksi->query("
            SELECT p.id_guru, p.id_mapel, m.nama_mapel, k.tingkat, k.nama_kelas, k.id_jurusan
            FROM dt_pengampu_mapel p
            INNER JOIN dt_kelas k ON k.id_kelas = p.id_kelas
            LEFT JOIN dt_mapel m ON m.id_mapel = p.id_mapel
            WHERE p.id_tahun_ajaran = $id_sumber
        ");
        if ($q) {
            while ($p = $q->fetch_assoc()) {
                $key = ta_key_kelas($p);
                if (!isset($effective_map[$key])) {
                    $pen_skip++;
                    continue;
                }
                $tk = $effective_map[$key];
                $ada = false;
                if ($tk !== 'NEW') {
                    $e = $koneksi->query("SELECT id_pengampu FROM dt_pengampu_mapel WHERE id_mapel = " . (int)$p['id_mapel'] . " AND id_kelas = " . (int)$tk . " AND id_tahun_ajaran = $id_target LIMIT 1");
                    $ada = ($e && $e->num_rows > 0);
                }
                if ($ada) {
                    $pen_ada++;
                } else {
                    $pen_baru++;
                }
            }
        }
    }

    $_SESSION['ta_preview'] = [
        'id_sumber'   => $id_sumber,
        'id_target'   => $id_target,
        'nama_sumber' => $nama_sumber,
        'nama_target' => $nama_target,
        'komp'        => $komp,
        'kelas'       => ['baru' => $kelas_baru, 'ada' => $kelas_ada, 'items' => $kelas_items],
        'wali'        => ['baru' => $wali_baru, 'ada' => $wali_ada, 'skip' => $wali_skip, 'items' => $wali_items],
        'pengampu'    => ['baru' => $pen_baru, 'ada' => $pen_ada, 'skip' => $pen_skip],
    ];

    header("Location: $back_url");
    exit;
}

// ==========================================================
// SALIN (EKSEKUSI) — transaksional
// ==========================================================
if ($aksi === 'salin') {
    $koneksi->begin_transaction();
    try {
        $real_map = ta_map_kelas($koneksi, $id_target);

        $n_kelas = 0;
        $n_wali = 0;
        $n_pen = 0;
        $skip_wali = 0;
        $skip_pen = 0;

        $ket_salin = $koneksi->real_escape_string('Salin dari TA ' . $nama_sumber);

        // 1. KELAS
        if ($komp['kelas']) {
            $q = $koneksi->query("SELECT id_kelas, tingkat, nama_kelas, id_jurusan FROM dt_kelas WHERE id_tahun_ajaran = $id_sumber");
            if (!$q) throw new Exception("Gagal membaca kelas sumber: " . $koneksi->error);
            while ($k = $q->fetch_assoc()) {
                $key = ta_key_kelas($k);
                if (isset($real_map[$key])) {
                    continue;
                }
                $nama = $koneksi->real_escape_string($k['nama_kelas']);
                $tingkat = $koneksi->real_escape_string($k['tingkat']);
                $sql = "INSERT INTO dt_kelas (nama_kelas, tingkat, id_jurusan, id_tahun_ajaran, keterangan)
                        VALUES ('$nama', '$tingkat', " . (int)$k['id_jurusan'] . ", $id_target, '$ket_salin')";
                if (!$koneksi->query($sql)) {
                    throw new Exception("Gagal menyalin kelas " . $k['tingkat'] . '-' . $k['nama_kelas'] . ": " . $koneksi->error);
                }
                $real_map[$key] = (int)$koneksi->insert_id;
                $n_kelas++;
            }
        }

        // 2. WALI KELAS
        if ($komp['wali']) {
            $q = $koneksi->query("
                SELECT w.id_guru, k.tingkat, k.nama_kelas, k.id_jurusan
                FROM dt_wali_kelas w
                INNER JOIN dt_kelas k ON k.id_kelas = w.id_kelas
                WHERE w.id_tahun_ajaran = $id_sumber
            ");
            if (!$q) throw new Exception("Gagal membaca wali kelas sumber: " . $koneksi->error);
            while ($w = $q->fetch_assoc()) {
                $key = ta_key_kelas($w);
                if (!isset($real_map[$key])) {
                    $skip_wali++;
                    continue;
                }
                $tk = (int)$real_map[$key];
                $e = $koneksi->query("SELECT id_wali_kelas FROM dt_wali_kelas WHERE id_kelas = $tk LIMIT 1");
                if ($e && $e->num_rows > 0) {
                    $skip_wali++;
                    continue;
                }
                $guru = (int)$w['id_guru'];
                if (!$koneksi->query("INSERT INTO dt_wali_kelas (id_guru, id_kelas, id_tahun_ajaran) VALUES ($guru, $tk, $id_target)")) {
                    throw new Exception("Gagal menyalin wali kelas: " . $koneksi->error);
                }
                $n_wali++;
            }
        }

        // 3. PENGAMPU MAPEL
        if ($komp['pengampu']) {
            $q = $koneksi->query("
                SELECT p.id_guru, p.id_mapel, k.tingkat, k.nama_kelas, k.id_jurusan
                FROM dt_pengampu_mapel p
                INNER JOIN dt_kelas k ON k.id_kelas = p.id_kelas
                WHERE p.id_tahun_ajaran = $id_sumber
            ");
            if (!$q) throw new Exception("Gagal membaca pengampu mapel sumber: " . $koneksi->error);
            while ($p = $q->fetch_assoc()) {
                $key = ta_key_kelas($p);
                if (!isset($real_map[$key])) {
                    $skip_pen++;
                    continue;
                }
                $tk = (int)$real_map[$key];
                $mapel = (int)$p['id_mapel'];
                $e = $koneksi->query("SELECT id_pengampu FROM dt_pengampu_mapel WHERE id_mapel = $mapel AND id_kelas = $tk AND id_tahun_ajaran = $id_target LIMIT 1");
                if ($e && $e->num_rows > 0) {
                    $skip_pen++;
                    continue;
                }
                $guru = (int)$p['id_guru'];
                if (!$koneksi->query("INSERT INTO dt_pengampu_mapel (id_guru, id_mapel, id_kelas, id_tahun_ajaran) VALUES ($guru, $mapel, $tk, $id_target)")) {
                    throw new Exception("Gagal menyalin pengampu mapel: " . $koneksi->error);
                }
                $n_pen++;
            }
        }

        $koneksi->commit();

        $rincian = [];
        if ($komp['kelas'])     $rincian[] = "$n_kelas kelas";
        if ($komp['wali'])      $rincian[] = "$n_wali wali kelas";
        if ($komp['pengampu'])  $rincian[] = "$n_pen pengampu mapel";
        $pesan = 'Salin data TA ' . $nama_sumber . ' &rarr; ' . $nama_target . ' selesai: ' . implode(', ', $rincian) . '.';
        $skipped = [];
        if ($skip_wali > 0) $skipped[] = "$skip_wali wali (sudah ada/kelas tak ditemukan)";
        if ($skip_pen > 0)  $skipped[] = "$skip_pen pengampu (sudah ada/kelas tak ditemukan)";
        if ($skipped) $pesan .= ' Dilewati: ' . implode(', ', $skipped) . '.';

        $_SESSION['ta_hasil'] = [
            'nama_sumber' => $nama_sumber,
            'nama_target' => $nama_target,
            'kelas'       => $n_kelas,
            'wali'        => $n_wali,
            'pengampu'    => $n_pen,
            'skip_wali'   => $skip_wali,
            'skip_pen'    => $skip_pen,
        ];
        unset($_SESSION['ta_preview']);
        ta_set_alert('success', $pesan);
        header("Location: $back_url");
        exit;
    } catch (Exception $e) {
        $koneksi->rollback();
        ta_set_alert('danger', 'Salin data dibatalkan (tidak ada perubahan): ' . $e->getMessage());
        header("Location: $back_url");
        exit;
    }
}

// Tidak seharusnya sampai di sini
ta_set_alert('danger', 'Aksi tidak valid.');
header("Location: $back_url");
exit;
