<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("koneksi.php");
require_once(__DIR__ . '/helper_laporan.php');

$sekolah  = laporan_sekolah($koneksi ?? null);
if (empty($sekolah['nama_sekolah'])) {
    $sekolah['nama_sekolah'] = 'Sekolah';
}
$kop_url  = laporan_kop_aktif($koneksi ?? null);
$kop_html = laporan_kop_html($sekolah, $kop_url);
$kepala   = laporan_kepala_sekolah($koneksi ?? null);
$footer_html = laporan_footer_html($kepala);

// ==========================================================
// 1. TAHUN AJARAN AKTIF
// ==========================================================
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
$id_ta_aktif = 0;
$nama_ta_aktif = htmlspecialchars($nama_ta_sesi);

if (isset($koneksi) && $koneksi instanceof mysqli && !$koneksi->connect_error && $nama_ta_sesi !== '') {
    $q_ta = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    if ($q_ta) {
        $q_ta->bind_param("s", $nama_ta_sesi);
        $q_ta->execute();
        $res_ta = $q_ta->get_result();
        if ($res_ta && $res_ta->num_rows > 0) {
            $TA = $res_ta->fetch_assoc();
            $id_ta_aktif = (int)$TA['id_tahun_ajaran'];
            $nama_ta_aktif = htmlspecialchars($TA['tahun_ajaran']);
        }
        $q_ta->close();
    }
}

$HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

// ==========================================================
// 2. JAM PELAJARAN & DAFTAR TINGKAT
// ==========================================================
$jam_list = [];
$q_j = $koneksi->query("SELECT id_jam, jam_ke, waktu_mulai, waktu_selesai, keterangan FROM dt_jam_pelajaran ORDER BY jam_ke ASC");
if ($q_j) {
    while ($j = $q_j->fetch_assoc()) {
        $jam_list[] = $j;
    }
}

$tingkat_list = [];
if ($id_ta_aktif > 0) {
    $q_t = $koneksi->query("SELECT DISTINCT tingkat FROM dt_kelas WHERE id_tahun_ajaran = '$id_ta_aktif' ORDER BY CASE tingkat WHEN 'X' THEN 1 WHEN 'XI' THEN 2 WHEN 'XII' THEN 3 WHEN 'XIII' THEN 4 ELSE 5 END");
    if ($q_t) {
        while ($t = $q_t->fetch_assoc()) {
            $tingkat_list[] = $t['tingkat'];
        }
    }
}

$tingkat = isset($_GET['tingkat']) ? trim((string)$_GET['tingkat']) : '';
if ($tingkat === '' || !in_array($tingkat, $tingkat_list, true)) {
    $tingkat = $tingkat_list[0] ?? '';
}

// ==========================================================
// 3. KELAS PADA TINGKAT TERPILIH
// ==========================================================
$kelas_list = [];
if ($id_ta_aktif > 0 && $tingkat !== '') {
    $q_k = $koneksi->prepare("
        SELECT k.id_kelas, k.nama_kelas, k.tingkat, j.nama_jurusan
        FROM dt_kelas k
        LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
        WHERE k.id_tahun_ajaran = ? AND k.tingkat = ?
        ORDER BY k.nama_kelas ASC
    ");
    if ($q_k) {
        $q_k->bind_param("is", $id_ta_aktif, $tingkat);
        $q_k->execute();
        $res_k = $q_k->get_result();
        while ($k = $res_k->fetch_assoc()) {
            $kelas_list[] = $k;
        }
        $q_k->close();
    }
}

$kelas_ids = [];
foreach ($kelas_list as $k) {
    $kelas_ids[] = (int)$k['id_kelas'];
}
$kelas_ids_sql = !empty($kelas_ids) ? implode(',', $kelas_ids) : '0';

// ==========================================================
// 3b. PENGAMPU MAPEL PER KELAS (sumber pilihan mapel + guru)
// ==========================================================
$pengampu_kelas = []; // [id_kelas][] = row
if (!empty($kelas_ids)) {
    $q_p = $koneksi->query("
        SELECT pm.id_kelas, pm.id_mapel, pm.id_guru, m.kode_mapel, m.nama_mapel, g.nama_guru
        FROM dt_pengampu_mapel pm
        JOIN dt_mapel m ON m.id_mapel = pm.id_mapel
        LEFT JOIN dt_guru g ON g.id_guru = pm.id_guru
        WHERE pm.id_tahun_ajaran = '$id_ta_aktif' AND pm.id_kelas IN ($kelas_ids_sql)
        ORDER BY m.nama_mapel ASC
    ");
    if ($q_p) {
        while ($p = $q_p->fetch_assoc()) {
            $pengampu_kelas[(int)$p['id_kelas']][] = $p;
        }
    }
}

// ==========================================================
// 3c. JADWAL EXISTING (semua kelas tingkat ini)
// ==========================================================
$jadwal_exist = []; // [id_kelas][hari][jam_ke] = row
if (!empty($kelas_ids)) {
    $q_s = $koneksi->query("
        SELECT jd.id_kelas, jd.hari, jd.jam_ke, jd.id_mapel, jd.id_guru,
               m.kode_mapel, m.nama_mapel, g.nama_guru
        FROM dt_jadwal jd
        JOIN dt_mapel m ON m.id_mapel = jd.id_mapel
        LEFT JOIN dt_guru g ON g.id_guru = jd.id_guru
        WHERE jd.id_tahun_ajaran = '$id_ta_aktif' AND jd.id_kelas IN ($kelas_ids_sql)
    ");
    if ($q_s) {
        while ($s = $q_s->fetch_assoc()) {
            $jadwal_exist[(int)$s['id_kelas']][$s['hari']][(int)$s['jam_ke']] = $s;
        }
    }
}

// Opsi mapel tiap kelas = pengampu kelas + (jaring aman) mapel yang sudah terjadwal
$opsi_kelas = [];
foreach ($kelas_ids as $idk) {
    $list = [];
    $seen = [];
    foreach (($pengampu_kelas[$idk] ?? []) as $p) {
        $idm = (int)$p['id_mapel'];
        if (isset($seen[$idm])) {
            continue;
        }
        $seen[$idm] = true;
        $list[] = ['id_mapel' => $idm, 'kode_mapel' => $p['kode_mapel'], 'nama_mapel' => $p['nama_mapel'], 'id_guru' => (int)($p['id_guru'] ?? 0), 'nama_guru' => $p['nama_guru']];
    }
    foreach (($jadwal_exist[$idk] ?? []) as $hariArr) {
        foreach ($hariArr as $row) {
            $idm = (int)$row['id_mapel'];
            if (isset($seen[$idm])) {
                continue;
            }
            $seen[$idm] = true;
            $list[] = ['id_mapel' => $idm, 'kode_mapel' => $row['kode_mapel'], 'nama_mapel' => $row['nama_mapel'], 'id_guru' => (int)($row['id_guru'] ?? 0), 'nama_guru' => $row['nama_guru']];
        }
    }
    $opsi_kelas[$idk] = $list;
}

$judul_tingkat = $tingkat;

// ==========================================================
// 3d. JADWAL TINGKAT LAIN (TA ini) untuk deteksi bentrok guru
// ==========================================================
$bentrok_base = [];
if ($id_ta_aktif > 0) {
    $q_b = $koneksi->query("
        SELECT jd.hari, jd.jam_ke, jd.id_guru, g.nama_guru, k.tingkat, k.nama_kelas
        FROM dt_jadwal jd
        LEFT JOIN dt_guru g ON g.id_guru = jd.id_guru
        LEFT JOIN dt_kelas k ON k.id_kelas = jd.id_kelas
        WHERE jd.id_tahun_ajaran = '$id_ta_aktif'
          AND jd.id_guru IS NOT NULL
          AND jd.id_kelas NOT IN ($kelas_ids_sql)
    ");
    if ($q_b) {
        while ($b = $q_b->fetch_assoc()) {
            $bentrok_base[] = [
                'h' => $b['hari'],
                'j' => (int)$b['jam_ke'],
                'g' => (int)$b['id_guru'],
                'n' => $b['nama_guru'],
                'k' => trim($b['tingkat'] . ' ' . $b['nama_kelas']),
            ];
        }
    }
}
?>
<?= laporan_print_css(); ?>

<main class="app-main">
    <div class="app-content-header no-print">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Jadwal Pelajaran</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Jadwal Pelajaran</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="alert alert-info py-2 mb-3 shadow-sm no-print">
                <i class="bi bi-info-circle-fill me-1"></i> Susun jadwal <b>per tingkat</b> sekaligus. Kolom = <b>Hari</b>, <b>Jam</b>, lalu <b>kelas-kelas</b> pada tingkat tersebut; setiap baris = satu jam pelajaran. Pilih mapel per kelas, guru terisi otomatis dari <a href="index.php?menu=pengampu_mapel" class="alert-link">Pengampu Mapel</a>. Tahun Ajaran: <b><?= $nama_ta_aktif !== '' ? $nama_ta_aktif : '-'; ?></b>.
            </div>

            <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['alert']['type']); ?> alert-dismissible fade show no-print" role="alert">
                    <?= $_SESSION['alert']['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>

            <div class="card shadow-sm no-print">
                <div class="card-body d-flex flex-wrap gap-2 align-items-end">
                    <form class="d-flex align-items-end gap-2" method="get">
                        <input type="hidden" name="menu" value="jadwal">
                        <div>
                            <label class="form-label mb-1 small fw-semibold">Tingkat</label>
                            <select name="tingkat" class="form-select" onchange="this.form.submit()">
                                <?php if (empty($tingkat_list)): ?>
                                    <option value="">(belum ada kelas)</option>
                                <?php else: foreach ($tingkat_list as $t): ?>
                                    <option value="<?= htmlspecialchars($t); ?>" <?= ($t === $tingkat) ? 'selected' : ''; ?>>Tingkat <?= htmlspecialchars($t); ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Tampilkan</button>
                    </form>
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalJam"><i class="bi bi-clock-history"></i> Atur Jam &amp; Waktu</button>
                    <a href="aksi/export_jadwal.php?mode=template" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Download Template</a>
                    <a href="aksi/export_jadwal.php?mode=data" class="btn btn-outline-dark"><i class="bi bi-download"></i> Download Excel</a>
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalImportJadwal"><i class="bi bi-upload"></i> Import Excel</button>
                    <?php if (!empty($kelas_list)): ?>
                        <button type="button" class="btn btn-outline-secondary ms-auto" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($kelas_list)): ?>
                <div class="alert alert-secondary no-print"><i class="bi bi-hand-index-thumb"></i> Belum ada kelas pada tingkat ini untuk Tahun Ajaran aktif.</div>
            <?php else: ?>
                <?php if (empty($jam_list)): ?>
                    <div class="alert alert-warning no-print"><i class="bi bi-exclamation-triangle-fill me-1"></i> Belum ada jam pelajaran. Klik <b>Atur Jam &amp; Waktu</b> untuk menambahkannya.</div>
                <?php endif; ?>

                <form id="formJadwal" action="aksi/aksi_jadwal.php" method="post" class="no-print" onsubmit="return prepareJadwal();">
                    <input type="hidden" name="aksi" value="simpan_tingkat">
                    <input type="hidden" name="id_tahun_ajaran" value="<?= $id_ta_aktif; ?>">
                    <input type="hidden" name="tingkat" value="<?= htmlspecialchars($tingkat); ?>">
                    <input type="hidden" name="payload" id="payload" value="">

                    <div class="d-flex align-items-center mb-2 gap-2 flex-wrap">
                        <span class="badge bg-primary">Tingkat <?= htmlspecialchars($judul_tingkat); ?></span>
                        <span class="text-muted small"><?= count($kelas_list); ?> kelas &middot; <?= count($jam_list); ?> jam &middot; baris = jam, kolom = kelas</span>
                        <button type="submit" class="btn btn-success btn-sm ms-auto"><i class="bi bi-save"></i> Simpan Semua</button>
                    </div>

                    <div id="bentrokBox" class="alert alert-warning py-2 mb-2 d-none">
                        <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Bentrok guru pada jam yang sama:</div>
                        <ul class="mb-0 small" id="bentrokList"></ul>
                    </div>

                    <div class="table-responsive jadwal-scroll">
                        <table class="table table-bordered align-top mb-0 jadwal-grid">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center hari-col" style="min-width:74px;">Hari</th>
                                    <th class="text-center" style="min-width:80px;">Jam</th>
                                    <?php foreach ($kelas_list as $k): ?>
                                        <th class="text-center" style="min-width:120px;" title="<?= htmlspecialchars(($k['tingkat'] . ' ' . $k['nama_kelas']) . (!empty($k['nama_jurusan']) ? ' - ' . $k['nama_jurusan'] : '')); ?>">
                                            <div class="text-nowrap"><?= htmlspecialchars($k['nama_kelas']); ?></div>
                                            <?php if (!empty($k['nama_jurusan'])): ?>
                                                <div class="small fw-normal text-secondary"><?= htmlspecialchars($k['nama_jurusan']); ?></div>
                                            <?php endif; ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($HARI as $hari): $firstJam = true; ?>
                                    <?php foreach ($jam_list as $j):
                                        $jam_ke = (int)$j['jam_ke'];
                                    ?>
                                        <tr>
                                            <?php if ($firstJam): ?>
                                                <th class="table-light align-middle text-center hari-col" rowspan="<?= count($jam_list); ?>"><?= htmlspecialchars($hari); ?></th>
                                            <?php endif; ?>
                                            <td class="text-center align-middle bg-light">
                                                <div class="fw-bold"><?= $jam_ke; ?></div>
                                                <?php if (!empty($j['waktu_mulai'])): ?>
                                                    <div class="small text-muted text-nowrap" style="font-size:.66rem;"><?= htmlspecialchars(substr((string)$j['waktu_mulai'], 0, 5)); ?>&ndash;<?= htmlspecialchars(substr((string)$j['waktu_selesai'], 0, 5)); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($j['keterangan'])): ?>
                                                    <div class="small fst-italic text-secondary" style="font-size:.66rem;"><?= htmlspecialchars($j['keterangan']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <?php foreach ($kelas_list as $k):
                                                $idk = (int)$k['id_kelas'];
                                                $cur = (int)($jadwal_exist[$idk][$hari][$jam_ke]['id_mapel'] ?? 0);
                                            ?>
                                                <td class="p-1">
                                                    <select class="form-select form-select-sm mini-select"
                                                        data-hari="<?= htmlspecialchars($hari); ?>" data-jam="<?= $jam_ke; ?>" data-kelas="<?= $idk; ?>"
                                                        data-kelas-nama="<?= htmlspecialchars($k['tingkat'] . ' ' . $k['nama_kelas']); ?>">
                                                        <option value="0">&mdash;</option>
                                                        <?php foreach (($opsi_kelas[$idk] ?? []) as $o): ?>
                                                            <option value="<?= (int)$o['id_mapel']; ?>" data-guru="<?= (int)($o['id_guru'] ?? 0); ?>" data-guru-nama="<?= htmlspecialchars($o['nama_guru'] ?? ''); ?>" <?= ((int)$o['id_mapel'] === $cur) ? 'selected' : ''; ?> title="<?= htmlspecialchars($o['nama_mapel'] ?? ''); ?>"><?= htmlspecialchars($o['kode_mapel'] ?: $o['nama_mapel']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div class="pick-guru text-muted"></div>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php $firstJam = false; ?>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <div class="only-print">
                    <?= $kop_html; ?>
                    <div class="text-center mb-2">
                        <h4 class="mb-0 fw-bold">JADWAL PELAJARAN</h4>
                        <div>Tingkat <b><?= htmlspecialchars($judul_tingkat); ?></b> &middot; Tahun Ajaran <b><?= $nama_ta_aktif; ?></b></div>
                    </div>
                    <div class="print-grid">
                        <?php foreach ($kelas_list as $k):
                            $idk = (int)$k['id_kelas'];
                        ?>
                            <div class="print-card">
                                <div class="print-card-title">
                                    <?= htmlspecialchars($k['tingkat'] . ' ' . $k['nama_kelas']); ?><?= !empty($k['nama_jurusan']) ? ' (' . htmlspecialchars($k['nama_jurusan']) . ')' : ''; ?>
                                </div>
                                <table class="table table-bordered table-sm mb-0 print-sched">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width:34px;">Jam</th>
                                            <?php foreach ($HARI as $h): ?><th class="text-center"><?= htmlspecialchars($h); ?></th><?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($jam_list as $j):
                                            $jam_ke = (int)$j['jam_ke'];
                                        ?>
                                            <tr>
                                                <th class="text-center"><?= $jam_ke; ?></th>
                                                <?php foreach ($HARI as $h):
                                                    $row = $jadwal_exist[$idk][$h][$jam_ke] ?? null;
                                                ?>
                                                    <td><?php if ($row): ?><b><?= htmlspecialchars($row['nama_mapel'] ?: $row['kode_mapel']); ?></b><br><span class="text-muted"><?= htmlspecialchars(($row['nama_guru'] ?? '') !== '' ? $row['nama_guru'] : 'Tanpa Guru'); ?></span><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?= $footer_html; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Modal Atur Jam & Waktu -->
<div class="modal fade" id="modalJam" tabindex="-1" aria-labelledby="modalJamLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="aksi/aksi_jam_pelajaran.php" method="post">
                <input type="hidden" name="aksi" value="simpan">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title" id="modalJamLabel"><i class="bi bi-clock-history me-1"></i> Atur Jam &amp; Waktu Pelajaran</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-secondary">
                                <tr>
                                    <th style="width:10%;">Jam ke</th>
                                    <th style="width:22%;">Mulai</th>
                                    <th style="width:22%;">Selesai</th>
                                    <th>Keterangan</th>
                                    <th style="width:8%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($jam_list)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">Belum ada jam. Klik "Tambah Jam".</td></tr>
                                <?php else: foreach ($jam_list as $j): ?>
                                    <tr>
                                        <td class="text-center fw-bold">
                                            <?= (int)$j['jam_ke']; ?>
                                            <input type="hidden" name="id_jam[]" value="<?= (int)$j['id_jam']; ?>">
                                        </td>
                                        <td><input type="time" name="waktu_mulai[]" class="form-control form-control-sm" value="<?= htmlspecialchars(substr((string)$j['waktu_mulai'], 0, 5)); ?>"></td>
                                        <td><input type="time" name="waktu_selesai[]" class="form-control form-control-sm" value="<?= htmlspecialchars(substr((string)$j['waktu_selesai'], 0, 5)); ?>"></td>
                                        <td><input type="text" name="keterangan[]" class="form-control form-control-sm" value="<?= htmlspecialchars($j['keterangan'] ?? ''); ?>" placeholder="mis. Istirahat"></td>
                                        <td class="text-center">
                                            <a href="aksi/aksi_jam_pelajaran.php?aksi=hapus&id=<?= (int)$j['id_jam']; ?>" class="btn btn-sm btn-outline-danger btn-hapus-jam" data-jam="<?= (int)$j['jam_ke']; ?>"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="aksi/aksi_jam_pelajaran.php?aksi=tambah" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Tambah Jam</a>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Simpan Waktu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Import Jadwal -->
<div class="modal fade" id="modalImportJadwal" tabindex="-1" aria-labelledby="modalImportJadwalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="aksi/import_jadwal.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="import" value="1">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalImportJadwalLabel"><i class="bi bi-upload me-1"></i> Import Jadwal Pelajaran</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Import akan <b>mengganti seluruh jadwal</b> untuk kelas-kelas yang ada di file (TA aktif: <b><?= $nama_ta_aktif !== '' ? $nama_ta_aktif : '-'; ?></b>). Kelas lain tidak diubah.
                    </div>
                    <ol class="small text-muted ps-3 mb-3">
                        <li>Klik <b>Download Template</b> terlebih dahulu (satu sheet per tingkat).</li>
                        <li>Isi setiap sel dengan <b>kode mapel</b> (lihat sheet <b>Daftar Mapel</b>). Kosongkan bila tidak ada pelajaran.</li>
                        <li>Jangan mengubah kolom header (baris 1) maupun kolom <b>Hari</b> dan <b>Jam</b>.</li>
                        <li>Upload kembali file yang sudah diisi.</li>
                    </ol>
                    <div class="mb-2">
                        <label class="form-label fw-bold">File Excel (.xlsx / .xls)</label>
                        <input type="file" name="fileExcel" class="form-control" accept=".xlsx,.xls" required>
                    </div>
                    <a href="aksi/export_jadwal.php?mode=template" class="small"><i class="bi bi-download"></i> Download Template Excel</a>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i> Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var bentrokBase = <?= json_encode($bentrok_base); ?>;

function currentGuru(sel) {
    var opt = sel.options[sel.selectedIndex];
    if (!opt) return null;
    var g = opt.getAttribute('data-guru');
    if (g === null || g === '' || g === '0') return null;
    return { id: g, nama: opt.getAttribute('data-guru-nama') || '' };
}

function checkBentrok(showToast) {
    var selects = document.querySelectorAll('#formJadwal .mini-select');
    var occ = {};
    function add(key, nama, kelas) {
        if (!occ[key]) occ[key] = { nama: nama, kelas: [] };
        occ[key].kelas.push(kelas);
    }
    (bentrokBase || []).forEach(function (b) {
        if (b.g) add(b.h + '|' + b.j + '|' + b.g, b.n, b.k);
    });

    var gridKeys = {};
    selects.forEach(function (s) {
        s.classList.remove('is-invalid');
        var opt = s.options[s.selectedIndex];
        if (!opt) return;
        var g = opt.getAttribute('data-guru');
        if (!g || g === '0' || s.value === '0') return;
        var key = s.getAttribute('data-hari') + '|' + s.getAttribute('data-jam') + '|' + g;
        gridKeys[key] = true;
        add(key, opt.getAttribute('data-guru-nama') || '', s.getAttribute('data-kelas-nama'));
    });

    var items = [];
    Object.keys(occ).forEach(function (key) {
        if (occ[key].kelas.length > 1 && gridKeys[key]) {
            var p = key.split('|');
            items.push({ h: p[0], j: p[1], key: key, nama: occ[key].nama, kelas: occ[key].kelas });
        }
    });

    var conflictKeys = {};
    items.forEach(function (it) { conflictKeys[it.key] = true; });
    selects.forEach(function (s) {
        var opt = s.options[s.selectedIndex];
        if (!opt) return;
        var g = opt.getAttribute('data-guru');
        if (!g || g === '0' || s.value === '0') return;
        if (conflictKeys[s.getAttribute('data-hari') + '|' + s.getAttribute('data-jam') + '|' + g]) {
            s.classList.add('is-invalid');
        }
    });

    var box = document.getElementById('bentrokBox');
    var list = document.getElementById('bentrokList');
    if (items.length) {
        var html = '';
        items.forEach(function (it) {
            html += '<li><b>' + it.nama + '</b> &mdash; ' + it.h + ' jam ' + it.j + ': ' + it.kelas.join(', ') + '</li>';
        });
        list.innerHTML = html;
        box.classList.remove('d-none');
    } else {
        list.innerHTML = '';
        box.classList.add('d-none');
    }

    if (showToast && items.length && typeof Swal !== 'undefined') {
        Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: 'Bentrok jadwal guru!', showConfirmButton: false, timer: 2500 });
    }
    return items.length;
}

function prepareJadwal() {
    var out = {};
    document.querySelectorAll('#formJadwal .mini-select').forEach(function (s) {
        var v = s.value;
        if (v === '0' || v === '') return;
        var h = s.getAttribute('data-hari');
        var j = s.getAttribute('data-jam');
        var k = s.getAttribute('data-kelas');
        if (!out[h]) out[h] = {};
        if (!out[h][j]) out[h][j] = {};
        out[h][j][k] = v;
    });
    document.getElementById('payload').value = JSON.stringify(out);
    return true;
}

function updatePickGuru(sel) {
    var box = sel.parentElement.querySelector('.pick-guru');
    if (!box) { return; }
    var opt = sel.options[sel.selectedIndex];
    var nama = '';

    if (sel.value !== '0' && opt) { nama = opt.getAttribute('data-guru-nama') || ''; }

    box.textContent = nama;
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#formJadwal .mini-select').forEach(function (s) {
        s.addEventListener('change', function () { updatePickGuru(s); checkBentrok(true); });
        updatePickGuru(s);
    });
    checkBentrok(false);

    document.querySelectorAll('.btn-hapus-jam').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var url = a.getAttribute('href');
            var jam = a.getAttribute('data-jam');
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Hapus jam ke-' + jam + '?',
                    icon: 'warning', showCancelButton: true,
                    confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal',
                    confirmButtonColor: '#dc3545'
                }).then(function (r) { if (r.isConfirmed) window.location.href = url; });
            } else if (confirm('Hapus jam ke-' + jam + '?')) { window.location.href = url; }
        });
    });
});
</script>

<style>
.jadwal-scroll { overflow: auto; max-height: 76vh; }
.mini-select { font-size: .72rem; padding: .1rem .35rem; height: auto; min-width: 84px; max-width: 120px; }
.pick-guru { font-size: .64rem; line-height: 1.1; margin-top: 1px; max-width: 120px; white-space: normal; word-break: break-word; }
.mini-select.is-invalid { background-image: none; background-color: #fff5f5; border-color: #dc3545; box-shadow: 0 0 0 .12rem rgba(220,53,69,.25); }
#formJadwal .table th, #formJadwal .table td { font-size: .74rem; }
#formJadwal thead th { position: sticky; top: 0; z-index: 3; }
#formJadwal .hari-col { position: sticky; left: 0; z-index: 2; background: #f8f9fa; }
#formJadwal thead th.hari-col { z-index: 4; }

.only-print { display: none; }
.print-grid { display: flex; flex-wrap: wrap; }
.print-card { width: 33.333%; padding: 4px; box-sizing: border-box; }
.print-card-title { font-weight: 700; font-size: 12px; }
.print-sched { font-size: 11px; }
.print-sched td, .print-sched th { font-size: 11px !important; padding: 2px 3px !important; }

@media print {
    @page { size: A4 landscape; margin: 8mm; }
    .only-print { display: block !important; }
}
</style>
