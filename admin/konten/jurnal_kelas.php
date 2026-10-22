<?php
/**
 * Jurnal Kelas (admin) — browse isi jurnal mengajar per kelas (KBM-3).
 * Filter: ?kelas=<id_kelas>&bulan=YYYY-MM (bulan opsional; kosong = semua).
 * Read-only. Detail absensi per sesi lewat modal.
 */
include('koneksi.php');
require_once(__DIR__ . '/helper_laporan.php');

$sekolah  = laporan_sekolah($koneksi ?? null);
if (empty($sekolah['nama_sekolah'])) {
    $sekolah['nama_sekolah'] = 'Sekolah';
}
$kop_url  = laporan_kop_aktif($koneksi ?? null);
$kop_html = laporan_kop_html($sekolah, $kop_url);
$kepala   = laporan_kepala_sekolah($koneksi ?? null);
$footer_html = laporan_footer_html($kepala);

// ---- Tahun ajaran aktif ----
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
$id_ta_aktif = 0;
$nama_ta_aktif = $nama_ta_sesi;
if ($nama_ta_sesi !== '' && isset($koneksi) && $koneksi instanceof mysqli && !$koneksi->connect_error) {
    $stmt = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    $stmt->bind_param("s", $nama_ta_sesi);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $ta = $res->fetch_assoc();
        $id_ta_aktif = (int)$ta['id_tahun_ajaran'];
        $nama_ta_aktif = $ta['tahun_ajaran'];
    }
    $stmt->close();
}

// ---- Daftar kelas (TA aktif) ----
$kelas_list = [];
if ($id_ta_aktif > 0) {
    $stmt = $koneksi->prepare("SELECT k.id_kelas, k.nama_kelas, k.tingkat, j.nama_jurusan
                               FROM dt_kelas k LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                               WHERE k.id_tahun_ajaran = ?
                               ORDER BY k.tingkat ASC, k.nama_kelas ASC");
    $stmt->bind_param("i", $id_ta_aktif);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $kelas_list[] = $r;
    $stmt->close();
}

$id_kelas = isset($_GET['kelas']) ? (int)$_GET['kelas'] : 0;
$kelas_valid = false;
foreach ($kelas_list as $kls) {
    if ((int)$kls['id_kelas'] === $id_kelas) { $kelas_valid = true; break; }
}
if (!$kelas_valid) $id_kelas = $kelas_list ? (int)$kelas_list[0]['id_kelas'] : 0;

// ---- Bulan (opsional) ----
$nama_bulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$bulan = (string)($_GET['bulan'] ?? '');
if ($bulan !== '' && !preg_match('/^\d{4}-\d{2}$/', $bulan)) $bulan = '';
$awal = $akhir = null;
$label_bulan = 'Semua periode';
if ($bulan !== '') {
    $awal = $bulan . '-01';
    $akhir = date('Y-m-t', strtotime($awal));
    $label_bulan = ($nama_bulan[substr($bulan, 5, 2)] ?? '') . ' ' . substr($bulan, 0, 4);
}
$libur_map = ($awal !== null) ? laporan_hari_libur($koneksi ?? null, $id_ta_aktif, $awal, $akhir) : [];

// ---- Info kelas + wali ----
$info_kelas = ['nama_kelas' => '-', 'tingkat' => '', 'jurusan' => '', 'wali' => ''];
foreach ($kelas_list as $kls) {
    if ((int)$kls['id_kelas'] === $id_kelas) {
        $info_kelas['nama_kelas'] = $kls['nama_kelas'];
        $info_kelas['tingkat'] = $kls['tingkat'];
        $info_kelas['jurusan'] = $kls['nama_jurusan'] ?? '';
        break;
    }
}
if ($id_kelas > 0) {
    $stmt = $koneksi->prepare("SELECT g.nama_guru FROM dt_wali_kelas wk
                               INNER JOIN dt_guru g ON g.id_guru = wk.id_guru
                               WHERE wk.id_kelas = ? AND wk.id_tahun_ajaran = ? LIMIT 1");
    $stmt->bind_param("ii", $id_kelas, $id_ta_aktif);
    $stmt->execute();
    $rw = $stmt->get_result()->fetch_assoc();
    if ($rw) $info_kelas['wali'] = $rw['nama_guru'];
    $stmt->close();
}

// ---- Daftar jurnal ----
$jurnal_rows = [];
if ($id_kelas > 0 && $id_ta_aktif > 0) {
    $sql = "SELECT j.id_jurnal, j.tanggal, j.jam_ke, j.materi, j.status, j.keterangan, j.created_at,
                   j.id_mapel, j.id_guru, j.id_guru_pengganti,
                   m.kode_mapel, m.nama_mapel,
                   g.nama_guru, gp.nama_guru AS nama_pengganti,
                   s.nama AS nama_sekretaris,
                   jp.waktu_mulai, jp.waktu_selesai
            FROM dt_jurnal j
            LEFT JOIN dt_mapel m ON m.id_mapel = j.id_mapel
            LEFT JOIN dt_guru g ON g.id_guru = j.id_guru
            LEFT JOIN dt_guru gp ON gp.id_guru = j.id_guru_pengganti
            LEFT JOIN dt_siswa s ON s.id_siswa = j.dicatat_oleh
            LEFT JOIN dt_jam_pelajaran jp ON jp.jam_ke = j.jam_ke
            WHERE j.id_kelas = ? AND j.id_tahun_ajaran = ?";
    $params = [$id_kelas, $id_ta_aktif];
    $types = "ii";
    if ($awal !== null) {
        $sql .= " AND j.tanggal BETWEEN ? AND ?";
        $params[] = $awal; $params[] = $akhir; $types .= "ss";
    }
    $sql .= " ORDER BY j.tanggal DESC, j.jam_ke ASC";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $jurnal_rows[] = $r;
    $stmt->close();
}

// ---- Rekap absensi harian per tanggal (absensi = 1x/hari, bukan per sesi) ----
$abs_sum = [];     // [tanggal][status] = count
$abs_detail = [];  // [tanggal][] = row siswa
$tgl_keys = [];
foreach ($jurnal_rows as $r) $tgl_keys[$r['tanggal']] = true;
$tgl_keys = array_keys($tgl_keys);
if ($tgl_keys && $id_kelas > 0 && $id_ta_aktif > 0) {
    $in = implode(',', array_map(function ($t) use ($koneksi) {
        return "'" . $koneksi->real_escape_string($t) . "'";
    }, $tgl_keys));
    $q = $koneksi->query("SELECT a.tanggal, a.id_siswa, a.status, a.keterangan, s.nama, s.jk
                          FROM dt_absensi a INNER JOIN dt_siswa s ON s.id_siswa = a.id_siswa
                          WHERE a.id_kelas = $id_kelas AND a.id_tahun_ajaran = $id_ta_aktif AND a.tanggal IN ($in)
                          ORDER BY s.nama ASC");
    if ($q) {
        while ($a = $q->fetch_assoc()) {
            $t = $a['tanggal'];
            $abs_sum[$t][$a['status']] = ($abs_sum[$t][$a['status']] ?? 0) + 1;
            $abs_detail[$t][] = $a;
        }
    }
}

$status_warna = ['Mengajar' => 'success', 'Tidak Hadir' => 'warning', 'Tugas/Kegiatan' => 'info', 'Kosong' => 'secondary'];
$abs_warna = ['H' => 'success', 'S' => 'info', 'I' => 'warning', 'A' => 'danger'];

// Ringkasan
// Ringkasan (hari libur tidak dihitung sebagai sesi)
$ring = ['total' => 0, 'Mengajar' => 0, 'Tidak Hadir' => 0, 'Tugas/Kegiatan' => 0, 'Kosong' => 0];
foreach ($jurnal_rows as $r) {
    if (isset($libur_map[$r['tanggal']])) continue;
    $ring['total']++;
    if (isset($ring[$r['status']])) $ring[$r['status']]++;
}
?>
<?= laporan_print_css(); ?>
<style>
@media print {
  @page { size: A4 landscape; margin: 8mm; }
  .tbl-jk th, .tbl-jk td { font-size: 9px !important; padding: 2px 3px !important; }
  .tbl-jk, .tbl-jk th, .tbl-jk td { border: 1px solid #999 !important; }
}
.materi-cell { max-width: 260px; white-space: normal; }
</style>

<main class="app-main">
    <div class="app-content-header no-print">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6"><h2 class="mb-0">Jurnal Kelas</h2></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item">Pendidikan &amp; KBM</li>
                        <li class="breadcrumb-item active" aria-current="page">Jurnal Kelas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">

            <div class="card card-outline card-primary no-print">
                <div class="card-body pb-2">
                    <form method="get" class="row g-2 align-items-end">
                        <input type="hidden" name="menu" value="jurnal_kelas">
                        <div class="col-md-4">
                            <label class="form-label fw-bold mb-1">Kelas (TA <?= htmlspecialchars($nama_ta_aktif ?: '-'); ?>)</label>
                            <select name="kelas" class="form-select" onchange="this.form.submit()">
                                <?php if (!$kelas_list): ?><option value="">— Tidak ada kelas —</option><?php endif; ?>
                                <?php foreach ($kelas_list as $kls): ?>
                                    <option value="<?= (int)$kls['id_kelas']; ?>" <?= ((int)$kls['id_kelas'] === $id_kelas) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars(trim($kls['tingkat'] . ' ' . $kls['nama_kelas']) . ($kls['nama_jurusan'] ? ' - ' . $kls['nama_jurusan'] : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold mb-1">Bulan (opsional)</label>
                            <div class="input-group">
                                <input type="month" name="bulan" class="form-control" value="<?= htmlspecialchars($bulan); ?>">
                                <a class="btn btn-outline-secondary" href="?menu=jurnal_kelas&kelas=<?= (int)$id_kelas; ?>" title="Reset bulan"><i class="bi bi-x-lg"></i></a>
                            </div>
                        </div>
                        <div class="col-md-5 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Tampilkan</button>
                            <button type="button" class="btn btn-success" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($id_ta_aktif <= 0 || $id_kelas <= 0): ?>
                <div class="alert alert-warning no-print">Belum ada tahun ajaran aktif atau kelas yang dipilih.</div>
            <?php else: ?>

                <?php if ($libur_map): ?>
                    <div class="alert alert-danger py-2 no-print">
                        <i class="bi bi-cup-hot me-1"></i> Hari libur pada periode ini <b>tidak dihitung</b> sebagai sesi (baris bertanda merah tetap ditampilkan bila ada data):
                        <b><?= htmlspecialchars(laporan_libur_ringkas($libur_map)); ?></b>
                    </div>
                <?php endif; ?>

                <div class="d-none d-print-block mb-2">
                    <?= $kop_html; ?>
                    <div style="text-align:center;margin:6px 0;">
                        <div style="font-weight:700;font-size:14px;">JURNAL MENGAJAR KELAS</div>
                        <div style="font-size:12px;">
                            Kelas <?= htmlspecialchars(trim($info_kelas['tingkat'] . ' ' . $info_kelas['nama_kelas'])); ?>
                            <?= $info_kelas['jurusan'] ? ' - ' . htmlspecialchars($info_kelas['jurusan']) : ''; ?>
                            &middot; <?= htmlspecialchars($label_bulan); ?> &middot; TA <?= htmlspecialchars($nama_ta_aktif); ?>
                        </div>
                    </div>
                </div>

                <div class="row g-2 mb-3 no-print">
                    <div class="col-6 col-md-3"><div class="card text-bg-primary"><div class="card-body py-2"><div class="small">Total Sesi</div><div class="fs-4 fw-bold"><?= $ring['total']; ?></div></div></div></div>
                    <div class="col-6 col-md-3"><div class="card text-bg-success"><div class="card-body py-2"><div class="small">Mengajar</div><div class="fs-4 fw-bold"><?= $ring['Mengajar']; ?></div></div></div></div>
                    <div class="col-6 col-md-3"><div class="card text-bg-warning"><div class="card-body py-2"><div class="small">Tidak Hadir</div><div class="fs-4 fw-bold"><?= $ring['Tidak Hadir']; ?></div></div></div></div>
                    <div class="col-6 col-md-3"><div class="card text-bg-secondary"><div class="card-body py-2"><div class="small">Wali Kelas</div><div class="fw-bold" style="font-size:.9rem;"><?= htmlspecialchars($info_kelas['wali'] ?: '-'); ?></div></div></div></div>
                </div>

                <div class="card">
                    <div class="card-header no-print d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-journal-bookmark me-1"></i> Jurnal — <?= htmlspecialchars(trim($info_kelas['tingkat'] . ' ' . $info_kelas['nama_kelas'])); ?> (<?= htmlspecialchars($label_bulan); ?>)</h5>
                        <span class="badge text-bg-light"><?= count($jurnal_rows); ?> entri</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover tbl-jk align-middle mb-0" style="font-size:.8rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="min-width:24px;">No</th>
                                        <th style="min-width:84px;">Tanggal</th>
                                        <th class="text-center">Jam</th>
                                        <th style="min-width:90px;">Mapel</th>
                                        <th style="min-width:120px;">Guru</th>
                                        <th class="text-center">Status</th>
                                        <th class="materi-cell">Materi</th>
                                        <th class="text-center">Kehadiran<span class="d-block text-muted" style="font-size:.65rem;font-weight:400;">harian</span></th>
                                        <th class="text-center no-print">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$jurnal_rows): ?>
                                        <tr><td colspan="9" class="text-center text-muted py-3">Belum ada jurnal pada kelas/periode ini.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($jurnal_rows as $i => $r):
                                            $tgl = $r['tanggal'];
                                            $sm = $abs_sum[$tgl] ?? [];
                                            $guru_tampil = $r['nama_guru'] ?: '-';
                                            if ($r['nama_pengganti']) $guru_tampil .= ' (pengganti: ' . $r['nama_pengganti'] . ')';
                                            $waktu = ($r['waktu_mulai'] ? substr($r['waktu_mulai'], 0, 5) : '') . ($r['waktu_selesai'] ? '-' . substr($r['waktu_selesai'], 0, 5) : '');
                                            $nc = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
                                            foreach ($nc as $st => $_) $nc[$st] = (int)($sm[$st] ?? 0);
                                            $is_libur = isset($libur_map[$tgl]);
                                        ?>
                                            <tr class="<?= $is_libur ? 'table-danger' : ''; ?>">
                                                <td class="text-center"><?= $i + 1 ?></td>
                                                <td class="text-nowrap"><?= htmlspecialchars(date('d/m/Y', strtotime($r['tanggal']))); ?>
                                                    <?php if ($is_libur): ?><span class="badge text-bg-danger" title="<?= htmlspecialchars($libur_map[$r['tanggal']]); ?>">Libur</span><?php endif; ?>
                                                </td>
                                                <td class="text-center text-nowrap">
                                                    <b><?= (int)$r['jam_ke']; ?></b><br><span class="text-muted" style="font-size:.7rem;"><?= htmlspecialchars($waktu); ?></span>
                                                </td>
                                                <td><?= htmlspecialchars(($r['kode_mapel'] ? $r['kode_mapel'] . ' — ' : '') . ($r['nama_mapel'] ?? '-')); ?></td>
                                                <td><?= htmlspecialchars($guru_tampil); ?></td>
                                                <td class="text-center"><span class="badge text-bg-<?= $status_warna[$r['status']] ?? 'secondary'; ?>"><?= htmlspecialchars($r['status']); ?></span></td>
                                                <td class="materi-cell"><?= nl2br(htmlspecialchars($r['materi'] ?? '')); ?></td>
                                                <td class="text-center text-nowrap">
                                                    <span class="badge text-bg-success">H <?= $nc['H']; ?></span>
                                                    <span class="badge text-bg-info">S <?= $nc['S']; ?></span>
                                                    <span class="badge text-bg-warning">I <?= $nc['I']; ?></span>
                                                    <span class="badge text-bg-danger">A <?= $nc['A']; ?></span>
                                                </td>
                                                <td class="text-center no-print">
                                                    <?php if (!empty($abs_detail[$tgl])): ?>
                                                        <button class="btn btn-sm btn-outline-secondary btn-detail"
                                                            data-bs-toggle="modal" data-bs-target="#modalDetail"
                                                            data-tgl="<?= htmlspecialchars($tgl); ?>"
                                                            data-info="<?= htmlspecialchars(date('d/m/Y', strtotime($tgl)) . ' — Absensi Harian'); ?>">
                                                            <i class="bi bi-list-ul"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Modal detail absensi (diisi JS) -->
                <div class="modal fade" id="modalDetail" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="bi bi-people me-1"></i> Detail Absensi <span id="detailInfo" class="text-muted fw-normal" style="font-size:.85rem;"></span></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body" id="detailBody"></div>
                        </div>
                    </div>
                </div>

                <script>
                (function () {
                    var DATA = <?= json_encode(array_reduce(array_keys($abs_detail), function ($carry, $t) use ($abs_detail) {
                        $carry[$t] = array_map(function ($a) {
                            return ['nama' => $a['nama'], 'jk' => $a['jk'], 'status' => $a['status'], 'ket' => $a['keterangan']];
                        }, $abs_detail[$t]);
                        return $carry;
                    }, []), JSON_UNESCAPED_UNICODE); ?>;
                    var WARNA = { H: 'success', S: 'info', I: 'warning', A: 'danger' };
                    var NAMA = { H: 'Hadir', S: 'Sakit', I: 'Izin', A: 'Alfa' };
                    document.querySelectorAll('.btn-detail').forEach(function (b) {
                        b.addEventListener('click', function () {
                            var tgl = b.getAttribute('data-tgl');
                            document.getElementById('detailInfo').textContent = b.getAttribute('data-info') || '';
                            var list = DATA[tgl] || [];
                            var html = '<table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>No</th><th>Nama</th><th>L/P</th><th>Status</th><th>Keterangan</th></tr></thead><tbody>';
                            if (!list.length) {
                                html += '<tr><td colspan="5" class="text-center text-muted">Tidak ada data.</td></tr>';
                            } else {
                                list.forEach(function (a, i) {
                                    html += '<tr><td>' + (i + 1) + '</td><td>' + a.nama + '</td><td>' + a.jk + '</td>'
                                        + '<td><span class="badge text-bg-' + (WARNA[a.status] || 'secondary') + '">' + a.status + ' - ' + (NAMA[a.status] || '') + '</span></td>'
                                        + '<td>' + (a.ket ? a.ket : '-') + '</td></tr>';
                                });
                            }
                            html += '</tbody></table>';
                            document.getElementById('detailBody').innerHTML = html;
                        });
                    });
                })();
                </script>

                <div class="d-none d-print-block">
                    <?= $footer_html; ?>
                </div>

            <?php endif; ?>
        </div>
    </div>
</main>
