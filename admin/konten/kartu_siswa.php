<?php
/**
 * Laporan: Kartu Siswa (foto + QR) — siap cetak (Print-to-PDF).
 * Filter: ?id_kelas=<id> (satu kelas) atau ?id_siswa=<id> (satu siswa), keduanya opsional.
 */
require_once(__DIR__ . '/helper_laporan.php');

$sekolah = laporan_sekolah($koneksi ?? null);
if (empty($sekolah['nama_sekolah'])) {
    $sekolah['nama_sekolah'] = 'Sekolah';
}
$logo_url = laporan_logo_url($sekolah);

// ---- Tahun ajaran aktif dari session ----
$nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
$id_ta_aktif = 0;
$nama_ta_aktif = $nama_ta_sesi;
if ($nama_ta_sesi !== '' && isset($koneksi) && $koneksi instanceof mysqli && !$koneksi->connect_error) {
    $stmt = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
    if ($stmt) {
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
}

$id_kelas = isset($_GET['id_kelas']) ? (int)$_GET['id_kelas'] : 0;
$id_siswa = isset($_GET['id_siswa']) ? (int)$_GET['id_siswa'] : 0;

// ---- Daftar kelas (TA aktif) untuk dropdown ----
$kelas_list = [];
if ($id_ta_aktif > 0 && isset($koneksi) && $koneksi instanceof mysqli) {
    $stmt = $koneksi->prepare("SELECT k.id_kelas, k.nama_kelas, k.tingkat, j.nama_jurusan
                               FROM dt_kelas k
                               LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                               WHERE k.id_tahun_ajaran = ?
                               ORDER BY CASE k.tingkat
                                   WHEN 'X' THEN 1 WHEN 'XI' THEN 2 WHEN 'XII' THEN 3 WHEN 'XIII' THEN 4 ELSE 5 END,
                                   k.nama_kelas ASC");
    if ($stmt) {
        $stmt->bind_param("i", $id_ta_aktif);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($k = $res->fetch_assoc()) {
            $kelas_list[] = $k;
        }
        $stmt->close();
    }
}

// ---- Ambil data siswa ----
$rows = [];
$judul_kelas = '';
if ($id_siswa > 0 && isset($koneksi) && $koneksi instanceof mysqli) {
    $stmt = $koneksi->prepare("SELECT s.id_siswa, s.nama, s.nipd, s.nisn, s.jk, s.tempat_lahir, s.tanggal_lahir, s.foto,
                                      COALESCE(k.nama_kelas, s.rombel) AS nama_kelas,
                                      k.tingkat, COALESCE(j.nama_jurusan, s.jurusan) AS nama_jurusan
                               FROM dt_siswa s
                               LEFT JOIN dt_kelas_siswa ks ON ks.id_siswa = s.id_siswa AND ks.id_tahun_ajaran = ?
                               LEFT JOIN dt_kelas k ON k.id_kelas = ks.id_kelas
                               LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                               WHERE s.id_siswa = ?
                               LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ii", $id_ta_aktif, $id_siswa);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();
    }
} elseif ($id_kelas > 0 && isset($koneksi) && $koneksi instanceof mysqli) {
    $stmt = $koneksi->prepare("SELECT s.id_siswa, s.nama, s.nipd, s.nisn, s.jk, s.tempat_lahir, s.tanggal_lahir, s.foto,
                                      k.nama_kelas, k.tingkat, j.nama_jurusan
                               FROM dt_kelas_siswa ks
                               INNER JOIN dt_siswa s ON s.id_siswa = ks.id_siswa
                               LEFT JOIN dt_kelas k ON k.id_kelas = ks.id_kelas
                               LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                               WHERE ks.id_tahun_ajaran = ? AND ks.id_kelas = ?
                               ORDER BY s.nama ASC");
    if ($stmt) {
        $stmt->bind_param("ii", $id_ta_aktif, $id_kelas);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();
    }
    foreach ($kelas_list as $k) {
        if ((int)$k['id_kelas'] === $id_kelas) {
            $judul_kelas = $k['nama_kelas'];
            break;
        }
    }
}

if (!function_exists('kartu_foto_url')) {
    /**
     * Foto siswa; fallback ke placeholder pria/wanita bila kosong / 'user.png' / file hilang.
     */
    function kartu_foto_url($foto, $jk)
    {
        $dir = 'assets/img/foto_siswa/';
        $f = trim((string)$foto);
        if ($f !== '' && strtolower($f) !== 'user.png' && is_file(__DIR__ . '/../' . $dir . $f)) {
            return $dir . $f;
        }
        return 'assets/img/' . (strtoupper((string)$jk) === 'P' ? 'wanita.png' : 'pria.png');
    }
}

if (!function_exists('kartu_tanggal_id')) {
    function kartu_tanggal_id($tgl)
    {
        $tgl = trim((string)$tgl);
        if ($tgl === '' || $tgl === '0000-00-00') {
            return '-';
        }
        $ts = strtotime($tgl);
        if ($ts === false) {
            return $tgl;
        }
        $bulan = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];
        return date('d', $ts) . ' ' . ($bulan[date('m', $ts)] ?? date('m', $ts)) . ' ' . date('Y', $ts);
    }
}
?>
<?= laporan_print_css(); ?>
<style>
.kartu-siswa {
    width: 86mm;
    height: 54mm;
    box-sizing: border-box;
    position: relative;
    overflow: hidden;
    border: 1.5pt solid #1b3a63;
    border-radius: 3mm;
    background: #fff;
    color: #111;
    font-family: "Segoe UI", Arial, sans-serif;
    page-break-inside: avoid;
    break-inside: avoid;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
.kartu-header {
    display: flex;
    align-items: center;
    gap: 2mm;
    background: #1b3a63;
    color: #fff;
    padding: 1.5mm 2.5mm;
}
.kartu-header.kartu-header-plain { background: #fff; color: #111; border-bottom: 1.5pt solid #1b3a63; }
.kartu-logo { height: 8mm; width: auto; }
.kartu-header-text { line-height: 1.15; }
.kartu-sekolah { font-size: 8.5pt; font-weight: 700; }
.kartu-judul { font-size: 7pt; letter-spacing: .5px; text-transform: uppercase; }
.kartu-body { display: flex; gap: 2.5mm; padding: 2.5mm; }
.kartu-foto { width: 21mm; height: 27mm; border: 1px solid #999; flex: 0 0 auto; overflow: hidden; background: #f2f2f2; }
.kartu-foto img { width: 100%; height: 100%; object-fit: cover; display: block; }
.kartu-data { border-collapse: collapse; font-size: 7.6pt; flex: 1 1 auto; }
.kartu-data td { padding: 0.4mm 0.6mm; vertical-align: top; }
.kartu-data td.k-label { white-space: nowrap; color: #333; width: 16mm; }
.kartu-data td.k-sep { width: 2mm; }
.kartu-qr { position: absolute; right: 1.5mm; bottom: 1.5mm; width: 15mm; height: 15mm; }
.kartu-qr img, .kartu-qr canvas { width: 100% !important; height: 100% !important; display: block; }
.kartu-grid { display: grid; grid-template-columns: repeat(auto-fill, 86mm); gap: 5mm; justify-content: center; }
@media print { .kartu-grid { gap: 4mm; } }
</style>

<div class="laporan-toolbar no-print">
    <div class="card">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <input type="hidden" name="menu" value="kartu_siswa">
                <div class="col-md-5">
                    <label class="form-label mb-1">Kelas (TA <?= htmlspecialchars($nama_ta_aktif !== '' ? $nama_ta_aktif : '-') ?>)</label>
                    <select name="id_kelas" class="form-select" onchange="this.form.submit()">
                        <option value="">— Pilih kelas —</option>
                        <?php foreach ($kelas_list as $k): ?>
                            <option value="<?= (int)$k['id_kelas'] ?>" <?= ($id_kelas === (int)$k['id_kelas']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['nama_kelas']) ?><?= !empty($k['nama_jurusan']) ? ' — ' . htmlspecialchars($k['nama_jurusan']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-7">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Tampilkan</button>
                    <?php if (!empty($rows)): ?>
                        <button type="button" class="btn btn-success" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
                        <span class="ms-2 text-muted"><?= count($rows) ?> kartu<?= $judul_kelas !== '' ? ' — ' . htmlspecialchars($judul_kelas) : '' ?></span>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (empty($rows)): ?>
    <div class="alert alert-info no-print"><i class="bi bi-info-circle"></i> Pilih kelas untuk menampilkan kartu siswa.</div>
<?php else: ?>
    <div class="kartu-grid">
        <?php foreach ($rows as $s):
            $nama = (string)($s['nama'] ?? '');
            $ttl = trim((string)($s['tempat_lahir'] ?? ''));
            $tgl = kartu_tanggal_id($s['tanggal_lahir'] ?? '');
            $ttl = ($ttl !== '' ? $ttl : '-') . ', ' . $tgl;
            $jk = strtoupper((string)($s['jk'] ?? '')) === 'P' ? 'Perempuan' : 'Laki-laki';
            $foto = kartu_foto_url($s['foto'] ?? '', $s['jk'] ?? '');
            $qr_text = json_encode([
                'nisn' => (string)($s['nisn'] ?? ''),
                'nama' => $nama,
                'kelas' => (string)($s['nama_kelas'] ?? ''),
                'ta' => $nama_ta_aktif,
            ], JSON_UNESCAPED_UNICODE);
        ?>
        <div class="kartu-siswa">
            <div class="kartu-header">
                <?php if ($logo_url !== ''): ?><img src="<?= htmlspecialchars($logo_url) ?>" class="kartu-logo" alt="Logo"><?php endif; ?>
                <div class="kartu-header-text">
                    <div class="kartu-sekolah"><?= htmlspecialchars((string)$sekolah['nama_sekolah']) ?></div>
                    <div class="kartu-judul">Kartu Pelajar</div>
                </div>
            </div>
            <div class="kartu-body">
                <div class="kartu-foto"><img src="<?= htmlspecialchars($foto) ?>" alt="Foto"></div>
                <table class="kartu-data">
                    <tr><td class="k-label">Nama</td><td class="k-sep">:</td><td><b><?= htmlspecialchars($nama) ?></b></td></tr>
                    <tr><td class="k-label">NIS / NISN</td><td class="k-sep">:</td><td><?= htmlspecialchars((string)($s['nipd'] ?? '-')) ?> / <?= htmlspecialchars((string)($s['nisn'] ?? '-')) ?></td></tr>
                    <tr><td class="k-label">TTL</td><td class="k-sep">:</td><td><?= htmlspecialchars($ttl) ?></td></tr>
                    <tr><td class="k-label">J. Kelamin</td><td class="k-sep">:</td><td><?= htmlspecialchars($jk) ?></td></tr>
                    <tr><td class="k-label">Kelas</td><td class="k-sep">:</td><td><?= htmlspecialchars((string)($s['nama_kelas'] ?? '-')) ?></td></tr>
                    <tr><td class="k-label">Jurusan</td><td class="k-sep">:</td><td><?= htmlspecialchars((string)($s['nama_jurusan'] ?? '-')) ?></td></tr>
                    <tr><td class="k-label">T. Ajaran</td><td class="k-sep">:</td><td><?= htmlspecialchars($nama_ta_aktif !== '' ? $nama_ta_aktif : '-') ?></td></tr>
                </table>
            </div>
            <div class="kartu-qr" data-qr="<?= htmlspecialchars($qr_text, ENT_QUOTES) ?>"></div>
        </div>
        <?php endforeach; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof QRCode === 'undefined') { return; }
        document.querySelectorAll('.kartu-qr').forEach(function (el) {
            var text = el.getAttribute('data-qr') || '';
            var canvas = document.createElement('canvas');
            el.appendChild(canvas);
            QRCode.toCanvas(canvas, text, { width: 140, margin: 0 }, function (err) {
                if (err) { el.innerHTML = ''; }
            });
        });
    });
    </script>
<?php endif; ?>
