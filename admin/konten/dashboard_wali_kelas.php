<?php
/**
 * Dashboard Wali Kelas (admin) — monitoring absensi siswa & jurnal mengajar
 * yang diisi oleh sekretaris kelas.
 *
 * Read-only. Responsive (mobile-first) agar bisa dibuka lewat HP.
 * Filter: ?kelas=<id_kelas>&bulan=YYYY-MM (default: bulan berjalan).
 * - Level "Guru" hanya melihat kelas yang menjadi wali kelasnya (TA sesi).
 * - Level Admin/Kurikulum/Kesiswaan dapat memilih kelas mana pun.
 */
include('koneksi.php');
require_once(__DIR__ . '/helper_laporan.php');
require_once(__DIR__ . '/../akses.php');

$level_user = $_SESSION['level_user'] ?? 'Guru';
$id_user    = (int)($_SESSION['id_user'] ?? 0);
$is_guru    = ($level_user === 'Guru');

// ==========================================================
// TAHUN AJARAN (dari session)
// ==========================================================
$ta_nama_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
$id_ta = 0;
$nama_ta = $ta_nama_sesi;
if ($ta_nama_sesi !== '' && isset($koneksi) && $koneksi instanceof mysqli && !$koneksi->connect_error) {
    $stmt = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $ta_nama_sesi);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $ta = $res->fetch_assoc();
            $id_ta = (int)$ta['id_tahun_ajaran'];
            $nama_ta = $ta['tahun_ajaran'];
        }
        $stmt->close();
    }
}

// ==========================================================
// GURU TERKAIT USER + KELAS YANG DIWALIKAN
// ==========================================================
$id_guru_user = 0;
if ($id_user > 0) {
    $stmt = $koneksi->prepare("SELECT id_fk FROM dt_user WHERE id_user = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $id_user);
        $stmt->execute();
        $ru = $stmt->get_result()->fetch_assoc();
        if ($ru) $id_guru_user = (int)($ru['id_fk'] ?? 0);
        $stmt->close();
    }
}

$wali_user = []; // id_kelas => true (kelas yang diwalikan user ini pada TA sesi)
if ($id_guru_user > 0 && $id_ta > 0) {
    $stmt = $koneksi->prepare("SELECT id_kelas FROM dt_wali_kelas WHERE id_guru = ? AND id_tahun_ajaran = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $id_guru_user, $id_ta);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $wali_user[(int)$r['id_kelas']] = true;
        $stmt->close();
    }
}

// ==========================================================
// DAFTAR KELAS (TA sesi) & KELAS YANG BOLEH DILIHAT
// ==========================================================
$kelas_list = [];
if ($id_ta > 0) {
    $stmt = $koneksi->prepare("SELECT k.id_kelas, k.nama_kelas, k.tingkat, j.nama_jurusan
                               FROM dt_kelas k
                               LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                               WHERE k.id_tahun_ajaran = ?
                               ORDER BY k.tingkat ASC, k.nama_kelas ASC");
    if ($stmt) {
        $stmt->bind_param("i", $id_ta);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $kelas_list[] = $r;
        $stmt->close();
    }
}

function dash_kelas_label($k)
{
    $label = trim(($k['tingkat'] ?? '') . ' ' . ($k['nama_kelas'] ?? ''));
    if (!empty($k['nama_jurusan'])) $label .= ' (' . $k['nama_jurusan'] . ')';
    return $label !== '' ? $label : ('Kelas #' . (int)($k['id_kelas'] ?? 0));
}

$allowed_kelas = [];
if ($is_guru) {
    foreach ($kelas_list as $k) {
        if (isset($wali_user[(int)$k['id_kelas']])) $allowed_kelas[] = $k;
    }
} else {
    $allowed_kelas = $kelas_list;
}

$id_kelas = (int)($_GET['kelas'] ?? 0);
$kelas_valid = false;
foreach ($allowed_kelas as $k) {
    if ((int)$k['id_kelas'] === $id_kelas) { $kelas_valid = true; break; }
}
if (!$kelas_valid) {
    if (!empty($wali_user)) {
        foreach ($allowed_kelas as $k) {
            if (isset($wali_user[(int)$k['id_kelas']])) { $id_kelas = (int)$k['id_kelas']; break; }
        }
    }
    if ($id_kelas <= 0) {
        $id_kelas = $allowed_kelas ? (int)$allowed_kelas[0]['id_kelas'] : 0;
    }
}

// Info kelas terpilih
$info_kelas = ['nama_kelas' => '-', 'tingkat' => '', 'jurusan' => '', 'wali' => ''];
foreach ($kelas_list as $k) {
    if ((int)$k['id_kelas'] === $id_kelas) {
        $info_kelas['nama_kelas'] = $k['nama_kelas'];
        $info_kelas['tingkat']    = $k['tingkat'];
        $info_kelas['jurusan']    = $k['nama_jurusan'] ?? '';
        break;
    }
}
if ($id_kelas > 0 && $id_ta > 0) {
    $stmt = $koneksi->prepare("SELECT g.nama_guru FROM dt_wali_kelas wk
                               INNER JOIN dt_guru g ON g.id_guru = wk.id_guru
                               WHERE wk.id_kelas = ? AND wk.id_tahun_ajaran = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ii", $id_kelas, $id_ta);
        $stmt->execute();
        $rw = $stmt->get_result()->fetch_assoc();
        if ($rw) $info_kelas['wali'] = $rw['nama_guru'];
        $stmt->close();
    }
}

// ==========================================================
// BULAN + HARI LIBUR
// ==========================================================
$nama_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
               '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$bulan = (string)($_GET['bulan'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) $bulan = date('Y-m');
$awal  = $bulan . '-01';
$akhir = date('Y-m-t', strtotime($awal));
$label_bulan = ($nama_bulan[substr($bulan, 5, 2)] ?? '') . ' ' . substr($bulan, 0, 4);

$libur_map = ($id_ta > 0) ? laporan_hari_libur($koneksi, $id_ta, $awal, $akhir) : [];
$libur_in  = $libur_map ? "'" . implode("','", array_map([$koneksi, 'real_escape_string'], array_keys($libur_map))) . "'" : '';

$hari_ini      = date('Y-m-d');
$hari_ini_nama = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][(int)date('w')];
$hari_ini_libur = $libur_map[$hari_ini] ?? null;
$akhir_pekan   = in_array((int)date('w'), [0, 6], true);

// Hari efektif bulan ini (Senin–Jumat, s.d. hari ini, di luar libur)
$hari_efektif = [];
$t = strtotime($awal);
$batas = min(strtotime($akhir), strtotime($hari_ini));
while ($t !== false && $t <= $batas) {
    $w = (int)date('w', $t);
    $d = date('Y-m-d', $t);
    if ($w >= 1 && $w <= 5 && !isset($libur_map[$d])) $hari_efektif[] = $d;
    $t = strtotime('+1 day', $t);
}
$jml_hari_efektif = count($hari_efektif);

// ==========================================================
// DATA KELAS
// ==========================================================
$roster = [];
$total_siswa = 0;
if ($id_kelas > 0 && $id_ta > 0) {
    $stmt = $koneksi->prepare("SELECT s.id_siswa, s.nama, s.jk, s.nisn, s.nipd
                               FROM dt_kelas_siswa ks
                               INNER JOIN dt_siswa s ON s.id_siswa = ks.id_siswa
                               WHERE ks.id_kelas = ? AND ks.id_tahun_ajaran = ?
                               ORDER BY s.nama ASC");
    if ($stmt) {
        $stmt->bind_param("ii", $id_kelas, $id_ta);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $roster[] = $r;
        $stmt->close();
    }
}
$total_siswa = count($roster);

// ---- Absensi sebulan (per tanggal & status) ----
$abs_month = [];
$abs_siswa = []; // id_siswa => ['S'=>n,'I'=>n,'A'=>n]
if ($id_kelas > 0 && $id_ta > 0) {
    $sql = "SELECT tanggal, status, COUNT(*) AS c
            FROM dt_absensi
            WHERE id_kelas = ? AND id_tahun_ajaran = ? AND tanggal BETWEEN ? AND ?"
            . ($libur_in ? " AND tanggal NOT IN ($libur_in)" : "")
            . " GROUP BY tanggal, status";
    $stmt = $koneksi->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("iiss", $id_kelas, $id_ta, $awal, $akhir);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $tg = $r['tanggal'];
            $abs_month[$tg][$r['status']] = (int)$r['c'];
        }
        $stmt->close();
    }

    $sql2 = "SELECT id_siswa, status, COUNT(*) AS c
             FROM dt_absensi
             WHERE id_kelas = ? AND id_tahun_ajaran = ? AND tanggal BETWEEN ? AND ?"
             . ($libur_in ? " AND tanggal NOT IN ($libur_in)" : "")
             . " AND status IN ('S','I','A')
             GROUP BY id_siswa, status";
    $stmt = $koneksi->prepare($sql2);
    if ($stmt) {
        $stmt->bind_param("iiss", $id_kelas, $id_ta, $awal, $akhir);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $sid = (int)$r['id_siswa'];
            if (!isset($abs_siswa[$sid])) $abs_siswa[$sid] = ['S' => 0, 'I' => 0, 'A' => 0];
            $abs_siswa[$sid][$r['status']] = (int)$r['c'];
        }
        $stmt->close();
    }
}
ksort($abs_month);

// ---- Absensi hari ini (atau tanggal terakhir bila hari ini belum ada) ----
// Dashboard monitoring: tanggal absensi tidak terikat filter bulan.
$tgl_terakhir = null;
if ($id_kelas > 0 && $id_ta > 0) {
    $stmt = $koneksi->prepare("SELECT MAX(tanggal) AS tgl FROM dt_absensi
                               WHERE id_kelas = ? AND id_tahun_ajaran = ? AND tanggal <= ?");
    if ($stmt) {
        $stmt->bind_param("iis", $id_kelas, $id_ta, $hari_ini);
        $stmt->execute();
        $rt = $stmt->get_result()->fetch_assoc();
        if ($rt && $rt['tgl']) $tgl_terakhir = $rt['tgl'];
        $stmt->close();
    }
}
$tgl_tampil = $tgl_terakhir;
$ada_hari_ini = false;
if ($id_kelas > 0 && $id_ta > 0) {
    $stmt = $koneksi->prepare("SELECT COUNT(*) AS c FROM dt_absensi
                               WHERE id_kelas = ? AND id_tahun_ajaran = ? AND tanggal = ?");
    if ($stmt) {
        $stmt->bind_param("iis", $id_kelas, $id_ta, $hari_ini);
        $stmt->execute();
        $rc = $stmt->get_result()->fetch_assoc();
        $ada_hari_ini = $rc && (int)$rc['c'] > 0;
        $stmt->close();
    }
}
if ($ada_hari_ini) $tgl_tampil = $hari_ini;

$abs_rows = [];
$status_count = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
if ($tgl_tampil && $id_kelas > 0 && $id_ta > 0) {
    $stmt = $koneksi->prepare("SELECT a.id_siswa, a.status, a.keterangan, s.nama, s.jk
                               FROM dt_absensi a
                               INNER JOIN dt_siswa s ON s.id_siswa = a.id_siswa
                               WHERE a.id_kelas = ? AND a.id_tahun_ajaran = ? AND a.tanggal = ?
                               ORDER BY FIELD(a.status, 'A', 'I', 'S', 'H'), s.nama ASC");
    if ($stmt) {
        $stmt->bind_param("iis", $id_kelas, $id_ta, $tgl_tampil);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $abs_rows[] = $r;
            $stt = $r['status'];
            if (isset($status_count[$stt])) $status_count[$stt]++;
        }
        $stmt->close();
    }
}
$hari_terisi = count($abs_month);
$jml_tidak_hadir = $status_count['S'] + $status_count['I'] + $status_count['A'];
$pct_hadir = ($total_siswa > 0) ? round($status_count['H'] / $total_siswa * 100) : 0;
$pct_kehadiran_bulan = 0;
$total_status_bulan = 0;
$total_hadir_bulan = 0;
foreach ($abs_month as $st) {
    foreach ($st as $k => $v) {
        $total_status_bulan += $v;
        if ($k === 'H') $total_hadir_bulan += $v;
    }
}
if ($total_status_bulan > 0) $pct_kehadiran_bulan = round($total_hadir_bulan / $total_status_bulan * 100);

// ---- Sesi terjadwal bulan ini (denominator realisasi jurnal) ----
$sched_per_hari = [];
if ($id_kelas > 0 && $id_ta > 0) {
    $stmt = $koneksi->prepare("SELECT hari, COUNT(*) AS c FROM dt_jadwal WHERE id_kelas = ? AND id_tahun_ajaran = ? GROUP BY hari");
    if ($stmt) {
        $stmt->bind_param("ii", $id_kelas, $id_ta);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $sched_per_hari[$r['hari']] = (int)$r['c'];
        $stmt->close();
    }
}
$sesi_terjadwal_bulan = 0;
$nama_hari_map = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
foreach ($hari_efektif as $d) {
    $hn = $nama_hari_map[(int)date('w', strtotime($d))];
    $sesi_terjadwal_bulan += $sched_per_hari[$hn] ?? 0;
}

// ---- Jurnal bulan ini ----
$jurnal_bulan = 0;
if ($id_kelas > 0 && $id_ta > 0) {
    $sqlj = "SELECT COUNT(*) AS c FROM dt_jurnal
             WHERE id_kelas = ? AND id_tahun_ajaran = ? AND tanggal BETWEEN ? AND ?"
             . ($libur_in ? " AND tanggal NOT IN ($libur_in)" : "");
    $stmt = $koneksi->prepare($sqlj);
    if ($stmt) {
        $stmt->bind_param("iiss", $id_kelas, $id_ta, $awal, $akhir);
        $stmt->execute();
        $rj = $stmt->get_result()->fetch_assoc();
        $jurnal_bulan = $rj ? (int)$rj['c'] : 0;
        $stmt->close();
    }
}
$pct_jurnal_bulan = ($sesi_terjadwal_bulan > 0) ? round($jurnal_bulan / $sesi_terjadwal_bulan * 100) : 0;

// ==========================================================
// JADWAL & JURNAL HARI INI
// ==========================================================
$jadwal_today = [];
$jurnal_today = [];
if ($id_kelas > 0 && $id_ta > 0) {
    $stmt = $koneksi->prepare("SELECT j.jam_ke, m.kode_mapel, m.nama_mapel, g.nama_guru,
                                      jp.waktu_mulai, jp.waktu_selesai
                               FROM dt_jadwal j
                               LEFT JOIN dt_mapel m ON m.id_mapel = j.id_mapel
                               LEFT JOIN dt_guru g ON g.id_guru = j.id_guru
                               LEFT JOIN dt_jam_pelajaran jp ON jp.jam_ke = j.jam_ke
                               WHERE j.id_kelas = ? AND j.id_tahun_ajaran = ? AND j.hari = ?
                               ORDER BY j.jam_ke ASC");
    if ($stmt) {
        $stmt->bind_param("iis", $id_kelas, $id_ta, $hari_ini_nama);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $jadwal_today[] = $r;
        $stmt->close();
    }

    $stmt = $koneksi->prepare("SELECT jr.id_jurnal, jr.jam_ke, jr.status, jr.materi, jr.keterangan,
                                      jr.id_guru_pengganti,
                                      m.kode_mapel, m.nama_mapel,
                                      g.nama_guru, gp.nama_guru AS nama_pengganti,
                                      s.nama AS nama_sekretaris
                               FROM dt_jurnal jr
                               LEFT JOIN dt_mapel m ON m.id_mapel = jr.id_mapel
                               LEFT JOIN dt_guru g ON g.id_guru = jr.id_guru
                               LEFT JOIN dt_guru gp ON gp.id_guru = jr.id_guru_pengganti
                               LEFT JOIN dt_siswa s ON s.id_siswa = jr.dicatat_oleh
                               WHERE jr.id_kelas = ? AND jr.id_tahun_ajaran = ? AND jr.tanggal = ?
                               ORDER BY jr.jam_ke ASC");
    if ($stmt) {
        $stmt->bind_param("iis", $id_kelas, $id_ta, $hari_ini);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $jurnal_today[(int)$r['jam_ke']] = $r;
        $stmt->close();
    }
}

// Gabungkan jadwal + jurnal hari ini (jurnal di luar jadwal tetap ditampilkan)
$sesi_hari_ini = [];
foreach ($jadwal_today as $j) {
    $jam = (int)$j['jam_ke'];
    $jr = $jurnal_today[$jam] ?? null;
    unset($jurnal_today[$jam]);
    $sesi_hari_ini[] = [
        'jam' => $jam,
        'waktu' => $j['waktu_mulai'] ? substr($j['waktu_mulai'], 0, 5) . '–' . substr((string)$j['waktu_selesai'], 0, 5) : '',
        'mapel' => trim(($j['kode_mapel'] ?? '') . ' ' . ($j['nama_mapel'] ?? '')),
        'guru' => $j['nama_guru'] ?? '',
        'jurnal' => $jr,
        'tambahan' => false,
    ];
}
foreach ($jurnal_today as $jam => $jr) {
    $sesi_hari_ini[] = [
        'jam' => (int)$jam,
        'waktu' => '',
        'mapel' => trim(($jr['kode_mapel'] ?? '') . ' ' . ($jr['nama_mapel'] ?? '')),
        'guru' => $jr['nama_guru'] ?? '',
        'jurnal' => $jr,
        'tambahan' => true,
    ];
}
usort($sesi_hari_ini, function ($a, $b) { return $a['jam'] <=> $b['jam']; });

$jadwal_count = count($jadwal_today);
$jurnal_filled = 0;
foreach ($sesi_hari_ini as $s) {
    if (!empty($s['jurnal'])) $jurnal_filled++;
}

// ==========================================================
// SISWA PERLU PERHATIAN (ketidakhadiran terbanyak bulan ini)
// ==========================================================
$perlu_perhatian = [];
foreach ($roster as $sw) {
    $sid = (int)$sw['id_siswa'];
    $ab = $abs_siswa[$sid] ?? ['S' => 0, 'I' => 0, 'A' => 0];
    $tot = $ab['S'] + $ab['I'] + $ab['A'];
    if ($tot > 0) {
        $sw['sakit'] = $ab['S'];
        $sw['izin']  = $ab['I'];
        $sw['alpa']  = $ab['A'];
        $sw['total_absen'] = $tot;
        $perlu_perhatian[] = $sw;
    }
}
usort($perlu_perhatian, function ($a, $b) {
    if ($a['alpa'] !== $b['alpa']) return $b['alpa'] <=> $a['alpa'];
    return $b['total_absen'] <=> $a['total_absen'];
});
$perlu_perhatian = array_slice($perlu_perhatian, 0, 5);
$jml_siswa_alpa = 0;
$jml_siswa_alpa3 = 0;
foreach ($abs_siswa as $ab) {
    if ($ab['A'] > 0) $jml_siswa_alpa++;
    if ($ab['A'] >= 3) $jml_siswa_alpa3++;
}

// ==========================================================
// REKOMENDASI TINDAKAN
// ==========================================================
$rekom = [];
if ($id_ta <= 0) {
    $rekom[] = ['warning', 'Tahun ajaran belum ditentukan. Atur di menu Data Referensi &rarr; Tahun Ajaran.'];
} elseif ($id_kelas <= 0) {
    $rekom[] = ['warning', 'Belum ada kelas yang bisa dipantau pada tahun ajaran ini.'];
} else {
    if ($hari_ini_libur) {
        $rekom[] = ['secondary', 'Hari ini <b>LIBUR</b> (' . htmlspecialchars($hari_ini_libur) . '). Tidak perlu absensi/jurnal.'];
    } elseif ($akhir_pekan) {
        $rekom[] = ['secondary', 'Hari ini akhir pekan — tidak ada jadwal pelajaran.'];
    } else {
        if (empty($abs_rows) || $tgl_tampil !== $hari_ini) {
            $rekom[] = ['danger', 'Absensi hari ini <b>belum diisi</b> oleh sekretaris.'];
        }
        if ($jadwal_count > 0 && $jurnal_filled < $jadwal_count) {
            $belum = $jadwal_count - $jurnal_filled;
            $rekom[] = ['warning', '<b>' . $belum . ' sesi</b> jurnal mengajar hari ini belum diisi.'];
        }
    }
    if ($jml_siswa_alpa3 > 0) {
        $rekom[] = ['danger', '<b>' . $jml_siswa_alpa3 . ' siswa</b> berstatus Alpa &ge; 3 kali bulan ini — perlu pembinaan.'];
    } elseif ($jml_siswa_alpa > 0) {
        $rekom[] = ['warning', '<b>' . $jml_siswa_alpa . ' siswa</b> pernah Alpa bulan ini — perlu konfirmasi.'];
    }
    if ($jml_hari_efektif > 0 && $hari_terisi < $jml_hari_efektif) {
        $rekom[] = ['info', 'Kelengkapan absensi bulan ini <b>' . $hari_terisi . '/' . $jml_hari_efektif . '</b> hari efektif.'];
    }
    if ($sesi_terjadwal_bulan > 0 && $pct_jurnal_bulan < 80) {
        $rekom[] = ['info', 'Realisasi jurnal mengajar baru <b>' . $pct_jurnal_bulan . '%</b> dari sesi terjadwal.'];
    }
    if (empty($rekom)) {
        $rekom[] = ['success', 'Semua terpantau baik. Absensi &amp; jurnal bulan ini lengkap.'];
    }
}

// Data grafik
$chart_labels = [];
$chart_h = [];
$chart_s = [];
$chart_i = [];
$chart_a = [];
foreach ($hari_efektif as $d) {
    $chart_labels[] = date('d/m', strtotime($d));
    $chart_h[] = $abs_month[$d]['H'] ?? 0;
    $chart_s[] = $abs_month[$d]['S'] ?? 0;
    $chart_i[] = $abs_month[$d]['I'] ?? 0;
    $chart_a[] = $abs_month[$d]['A'] ?? 0;
}

$status_warna = ['Mengajar' => 'success', 'Tidak Hadir' => 'warning', 'Tugas/Kegiatan' => 'info', 'Kosong' => 'secondary'];
$abs_warna = ['H' => 'success', 'S' => 'info', 'I' => 'warning', 'A' => 'danger'];
$abs_label = ['H' => 'Hadir', 'S' => 'Sakit', 'I' => 'Izin', 'A' => 'Alpa'];
?>
<div class="app-content-header">
    <div class="container-fluid px-0">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h3 class="mb-0"><i class="bi bi-speedometer2 me-1"></i> Dashboard Wali Kelas</h3>
                <div class="text-muted small">Monitoring absensi siswa &amp; jurnal mengajar sekretaris kelas</div>
            </div>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                <li class="breadcrumb-item">Pendidikan &amp; KBM</li>
                <li class="breadcrumb-item active">Dashboard Wali Kelas</li>
            </ol>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid px-0">

        <?php if ($id_ta <= 0) : ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i>
                Tahun ajaran belum ditentukan. Atur di menu <b>Data Referensi &rarr; Tahun Ajaran</b>.</div>
        <?php elseif (empty($allowed_kelas)) : ?>
            <div class="alert alert-info"><i class="bi bi-info-circle-fill me-1"></i>
                <?php if ($is_guru) : ?>
                    Anda belum ditugaskan sebagai <b>wali kelas</b> pada tahun ajaran <b><?= htmlspecialchars($nama_ta); ?></b>.
                    Hubungi Admin/Kurikulum untuk penugasan wali kelas.
                <?php else : ?>
                    Belum ada kelas pada tahun ajaran <b><?= htmlspecialchars($nama_ta); ?></b>.
                <?php endif; ?>
            </div>
        <?php else : ?>

        <div class="card card-outline card-primary mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <?php if (!$is_guru || count($allowed_kelas) > 1) : ?>
                    <div class="col-12 col-sm-6 col-lg-4">
                        <label class="form-label small mb-1 fw-semibold">Kelas</label>
                        <form method="get" action="index.php">
                            <input type="hidden" name="menu" value="dashboard_wali_kelas">
                            <input type="hidden" name="bulan" value="<?= htmlspecialchars($bulan); ?>">
                            <select name="kelas" class="form-select" onchange="this.form.submit()">
                                <?php foreach ($allowed_kelas as $k) : ?>
                                    <option value="<?= (int)$k['id_kelas']; ?>" <?= ((int)$k['id_kelas'] === $id_kelas) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars(dash_kelas_label($k)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <?php else : ?>
                    <div class="col-12 col-sm-6 col-lg-4">
                        <label class="form-label small mb-1 fw-semibold">Kelas</label>
                        <div class="form-control-plaintext fw-bold"><?= htmlspecialchars(dash_kelas_label($info_kelas)); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label class="form-label small mb-1 fw-semibold">Bulan</label>
                        <form method="get" action="index.php">
                            <input type="hidden" name="menu" value="dashboard_wali_kelas">
                            <input type="hidden" name="kelas" value="<?= (int)$id_kelas; ?>">
                            <input type="month" name="bulan" value="<?= htmlspecialchars($bulan); ?>" class="form-control" onchange="this.form.submit()">
                        </form>
                    </div>
                    <div class="col-12 col-lg-5">
                        <div class="small text-muted">
                            Wali Kelas: <b><?= htmlspecialchars($info_kelas['wali'] ?: 'Belum ditentukan'); ?></b><br>
                            T.A. <b><?= htmlspecialchars($nama_ta); ?></b> &middot; <?= $total_siswa; ?> siswa &middot; <?= htmlspecialchars($label_bulan); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($hari_ini_libur) : ?>
            <div class="alert alert-secondary shadow-sm">
                <i class="bi bi-calendar-x me-1"></i> <b>LIBUR</b> — <?= htmlspecialchars($hari_ini_libur); ?> (<?= $hari_ini_nama; ?>, <?= laporan_tanggal_id(strtotime($hari_ini)); ?>).
            </div>
        <?php endif; ?>

        <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-3">
            <div class="col">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small text-uppercase">Kehadiran <?= $tgl_tampil ? (($tgl_tampil === $hari_ini) ? 'Hari Ini' : laporan_tanggal_id(strtotime($tgl_tampil))) : 'Terakhir'; ?></div>
                                <div class="fs-2 fw-bold text-success"><?= $pct_hadir; ?>%</div>
                                <div class="small text-muted"><?= $status_count['H']; ?> dari <?= $total_siswa; ?> siswa</div>
                            </div>
                            <span class="badge bg-success-subtle text-success fs-6 rounded-circle p-2"><i class="bi bi-check2-circle fs-5"></i></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small text-uppercase">Tidak Hadir</div>
                                <div class="fs-2 fw-bold text-danger"><?= $jml_tidak_hadir; ?></div>
                                <div class="small text-muted d-flex gap-1 flex-wrap">
                                    <span class="badge bg-info">S <?= $status_count['S']; ?></span>
                                    <span class="badge bg-warning text-dark">I <?= $status_count['I']; ?></span>
                                    <span class="badge bg-danger">A <?= $status_count['A']; ?></span>
                                </div>
                            </div>
                            <span class="badge bg-danger-subtle text-danger fs-6 rounded-circle p-2"><i class="bi bi-person-x fs-5"></i></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small text-uppercase">Jurnal Hari Ini</div>
                                <?php if ($hari_ini_libur) : ?>
                                    <div class="fs-3 fw-bold text-secondary">LIBUR</div>
                                    <div class="small text-muted">Tidak ada jurnal</div>
                                <?php elseif ($akhir_pekan) : ?>
                                    <div class="fs-3 fw-bold text-secondary">&mdash;</div>
                                    <div class="small text-muted">Akhir pekan</div>
                                <?php else : ?>
                                    <div class="fs-2 fw-bold text-primary"><?= $jurnal_filled; ?>/<?= $jadwal_count; ?></div>
                                    <div class="small text-muted"><?= max(0, $jadwal_count - $jurnal_filled); ?> sesi belum diisi</div>
                                <?php endif; ?>
                            </div>
                            <span class="badge bg-primary-subtle text-primary fs-6 rounded-circle p-2"><i class="bi bi-journal-text fs-5"></i></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small text-uppercase">Absensi <?= htmlspecialchars($label_bulan); ?></div>
                                <div class="fs-2 fw-bold text-info"><?= $hari_terisi; ?>/<?= $jml_hari_efektif; ?></div>
                                <div class="small text-muted">hari efektif terisi</div>
                            </div>
                            <span class="badge bg-info-subtle text-info fs-6 rounded-circle p-2"><i class="bi bi-clipboard-check fs-5"></i></span>
                        </div>
                        <div class="progress mt-2" style="height:6px;">
                            <div class="progress-bar bg-info" style="width:<?= $jml_hari_efektif > 0 ? min(100, round($hari_terisi / $jml_hari_efektif * 100)) : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-xl-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">
                        <i class="bi bi-bar-chart-line me-1 text-primary"></i> Tren Absensi Harian — <?= htmlspecialchars($label_bulan); ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($chart_labels)) : ?>
                            <div class="text-muted text-center py-4"><i class="bi bi-graph-up me-1"></i> Belum ada hari efektif pada periode ini.</div>
                        <?php else : ?>
                            <div style="position:relative;height:260px;width:100%;"><canvas id="chartAbsensi"></canvas></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">
                        <i class="bi bi-lightbulb me-1 text-warning"></i> Rekomendasi Tindakan
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0 d-grid gap-2">
                            <?php foreach ($rekom as $rk) : ?>
                                <li class="d-flex gap-2">
                                    <span class="badge bg-<?= $rk[0]; ?> mt-1">&nbsp;</span>
                                    <span class="small"><?= $rk[1]; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-xl-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><i class="bi bi-person-check me-1 text-success"></i> Absensi
                            <?= $tgl_tampil ? (($tgl_tampil === $hari_ini) ? 'Hari Ini' : laporan_tanggal_id(strtotime($tgl_tampil))) : 'Siswa'; ?></span>
                        <?php if (boleh_menu($level_user, 'rekap_absensi')) : ?>
                        <a href="index.php?menu=rekap_absensi&kelas=<?= (int)$id_kelas; ?>&bulan=<?= urlencode($bulan); ?>" class="btn btn-sm btn-outline-primary">Rekap</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($tgl_tampil === null) : ?>
                            <div class="p-3 text-muted small"><i class="bi bi-inbox me-1"></i> Belum ada data absensi pada bulan ini.</div>
                        <?php elseif (empty($abs_rows)) : ?>
                            <div class="p-3 text-muted small"><i class="bi bi-inbox me-1"></i> Tidak ada data pada tanggal ini.</div>
                        <?php else : ?>
                            <?php
                            $tidak_hadir_rows = array_filter($abs_rows, fn($r) => in_array($r['status'], ['S', 'I', 'A'], true));
                            ?>
                            <?php if (empty($tidak_hadir_rows)) : ?>
                                <div class="p-3 text-success small"><i class="bi bi-check2-circle me-1"></i> Semua siswa hadir (<?= $status_count['H']; ?> orang).</div>
                            <?php else : ?>
                                <div class="table-responsive" style="max-height:280px;">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr><th>Nama Siswa</th><th class="text-center">Status</th><th>Keterangan</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tidak_hadir_rows as $r) : ?>
                                                <tr>
                                                    <td class="small"><?= htmlspecialchars($r['nama']); ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-<?= $abs_warna[$r['status']] ?? 'secondary'; ?>"><?= htmlspecialchars($r['status']); ?></span>
                                                    </td>
                                                    <td class="small text-muted"><?= htmlspecialchars($r['keterangan'] ?: '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold"><i class="bi bi-journal-text me-1 text-primary"></i> Jurnal Mengajar — <?= $hari_ini_nama; ?></span>
                        <?php if (boleh_menu($level_user, 'jurnal_kelas')) : ?>
                        <a href="index.php?menu=jurnal_kelas&kelas=<?= (int)$id_kelas; ?>&bulan=<?= urlencode($bulan); ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($hari_ini_libur) : ?>
                            <div class="p-3 text-muted small"><i class="bi bi-calendar-x me-1"></i> Hari ini libur.</div>
                        <?php elseif ($akhir_pekan) : ?>
                            <div class="p-3 text-muted small"><i class="bi bi-calendar-week me-1"></i> Akhir pekan — tidak ada jadwal.</div>
                        <?php elseif (empty($sesi_hari_ini)) : ?>
                            <div class="p-3 text-muted small"><i class="bi bi-inbox me-1"></i> Tidak ada jadwal pelajaran hari ini.</div>
                        <?php else : ?>
                            <div class="table-responsive" style="max-height:280px;">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr><th>Jam</th><th>Mapel / Guru</th><th class="text-center">Status</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sesi_hari_ini as $s) : ?>
                                            <tr>
                                                <td class="small text-nowrap"><?= (int)$s['jam']; ?><?= $s['waktu'] ? '<br><span class="text-muted" style="font-size:.72rem;">' . htmlspecialchars($s['waktu']) . '</span>' : ''; ?></td>
                                                <td class="small">
                                                    <?= htmlspecialchars($s['mapel'] ?: '-'); ?>
                                                    <?php if ($s['guru']) : ?><br><span class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($s['guru']); ?></span><?php endif; ?>
                                                    <?php if (!empty($s['jurnal']['nama_pengganti'])) : ?>
                                                        <span class="badge bg-warning text-dark" style="font-size:.65rem;">Pengganti: <?= htmlspecialchars($s['jurnal']['nama_pengganti']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($s['jurnal']['materi'])) : ?>
                                                        <div class="text-muted" style="font-size:.72rem;"><i class="bi bi-book me-1"></i><?= htmlspecialchars($s['jurnal']['materi']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if (empty($s['jurnal'])) : ?>
                                                        <span class="badge bg-secondary">Belum diisi</span>
                                                    <?php else : ?>
                                                        <span class="badge bg-<?= $status_warna[$s['jurnal']['status']] ?? 'secondary'; ?>"><?= htmlspecialchars($s['jurnal']['status']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">
                        <i class="bi bi-exclamation-triangle me-1 text-danger"></i> Siswa Perlu Perhatian — <?= htmlspecialchars($label_bulan); ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($perlu_perhatian)) : ?>
                            <div class="p-3 text-success small"><i class="bi bi-emoji-smile me-1"></i> Tidak ada ketidakhadiran tercatat bulan ini.</div>
                        <?php else : ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr><th>Nama Siswa</th><th class="text-center">S</th><th class="text-center">I</th><th class="text-center">A</th><th class="text-center">Total</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($perlu_perhatian as $sw) : ?>
                                            <tr>
                                                <td class="small">
                                                    <a href="index.php?menu=siswa_detail&id=<?= (int)$sw['id_siswa']; ?>" class="text-decoration-none"><?= htmlspecialchars($sw['nama']); ?></a>
                                                </td>
                                                <td class="text-center small"><?= (int)$sw['sakit']; ?></td>
                                                <td class="text-center small"><?= (int)$sw['izin']; ?></td>
                                                <td class="text-center"><span class="badge bg-<?= $sw['alpa'] >= 3 ? 'danger' : ($sw['alpa'] > 0 ? 'warning text-dark' : 'secondary'); ?>"><?= (int)$sw['alpa']; ?></span></td>
                                                <td class="text-center fw-semibold small"><?= (int)$sw['total_absen']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-lightning-charge me-1 text-warning"></i> Aksi Cepat Wali Kelas</div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if (boleh_menu($level_user, 'rekap_absensi')) : ?>
                            <a href="index.php?menu=rekap_absensi&kelas=<?= (int)$id_kelas; ?>&bulan=<?= urlencode($bulan); ?>" class="btn btn-outline-success text-start">
                                <i class="bi bi-clipboard-data me-1"></i> Rekap Absensi Bulanan
                            </a>
                            <?php endif; ?>
                            <?php if (boleh_menu($level_user, 'jurnal_kelas')) : ?>
                            <a href="index.php?menu=jurnal_kelas&kelas=<?= (int)$id_kelas; ?>&bulan=<?= urlencode($bulan); ?>" class="btn btn-outline-primary text-start">
                                <i class="bi bi-journal-bookmark me-1"></i> Jurnal Kelas
                            </a>
                            <?php endif; ?>
                            <?php if (boleh_menu($level_user, 'detail_kelas')) : ?>
                            <a href="index.php?menu=detail_kelas&id_kelas=<?= (int)$id_kelas; ?>" class="btn btn-outline-secondary text-start">
                                <i class="bi bi-people me-1"></i> Daftar Siswa Kelas
                            </a>
                            <?php endif; ?>
                            <?php if (boleh_menu($level_user, 'rekap_jurnal')) : ?>
                            <a href="index.php?menu=rekap_jurnal&bulan=<?= urlencode($bulan); ?>" class="btn btn-outline-info text-start">
                                <i class="bi bi-graph-up me-1"></i> Rekap Jurnal Guru
                            </a>
                            <?php endif; ?>
                        </div>
                        <hr>
                        <div class="small text-muted">
                            <div class="d-flex justify-content-between"><span>Total sesi terjadwal</span><b><?= $sesi_terjadwal_bulan; ?> sesi</b></div>
                            <div class="d-flex justify-content-between"><span>Jurnal tercatat</span><b><?= $jurnal_bulan; ?> sesi</b></div>
                            <div class="d-flex justify-content-between"><span>Realisasi jurnal</span><b><?= $pct_jurnal_bulan; ?>%</b></div>
                            <div class="d-flex justify-content-between"><span>Kehadiran bulan ini</span><b><?= $pct_kehadiran_bulan; ?>%</b></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<?php if (!empty($chart_labels) && $id_kelas > 0) : ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var el = document.getElementById('chartAbsensi');
    if (!el || typeof Chart === 'undefined') return;
    Chart.defaults.font.family = "'Source Sans 3', system-ui, sans-serif";
    Chart.defaults.color = '#64748b';
    new Chart(el, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels); ?>,
            datasets: [
                { label: 'Hadir', data: <?= json_encode($chart_h); ?>, backgroundColor: '#16a34a' },
                { label: 'Sakit', data: <?= json_encode($chart_s); ?>, backgroundColor: '#0ea5e9' },
                { label: 'Izin', data: <?= json_encode($chart_i); ?>, backgroundColor: '#f59e0b' },
                { label: 'Alpa', data: <?= json_encode($chart_a); ?>, backgroundColor: '#dc2626' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true, pointStyle: 'rectRounded' } }
            },
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
})();
</script>
<?php endif; ?>
