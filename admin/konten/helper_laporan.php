<?php
/**
 * Helper bersama untuk modul laporan / cetak.
 * Strategi cetak: halaman HTML + CSS @media print (Print-to-PDF browser), tanpa dependensi.
 */

if (!function_exists('laporan_sekolah')) {
    /**
     * Ambil profil sekolah (dipakai untuk kepala surat / kartu).
     */
    function laporan_sekolah($koneksi)
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [
            'nama_sekolah' => '',
            'alamat_sekolah' => '',
            'NPSN' => '',
            'telp_sekolah' => '',
            'email' => '',
            'web' => '',
            'logo' => '',
        ];
        if ($koneksi instanceof mysqli && !$koneksi->connect_error) {
            $q = $koneksi->query("SELECT nama_sekolah, alamat_sekolah, NPSN, telp_sekolah, email, web, logo FROM dt_data_sekolah LIMIT 1");
            if ($q && $q->num_rows > 0) {
                $cache = array_merge($cache, $q->fetch_assoc());
            }
        }
        return $cache;
    }
}

if (!function_exists('laporan_logo_url')) {
    /**
     * URL logo sekolah (relatif terhadap folder admin/), '' bila tak ada.
     */
    function laporan_logo_url($sekolah)
    {
        $logo = trim((string)($sekolah['logo'] ?? ''));
        $rel = 'assets/img/logo/' . $logo;
        if ($logo !== '' && is_file(__DIR__ . '/../' . $rel)) {
            return $rel;
        }
        return '';
    }
}

if (!function_exists('laporan_kop_aktif')) {
    /**
     * URL gambar kop aktif dari dt_kop_sekolah (relatif terhadap admin/), '' bila tak ada.
     */
    function laporan_kop_aktif($koneksi)
    {
        if (!($koneksi instanceof mysqli) || $koneksi->connect_error) {
            return '';
        }
        $q = $koneksi->query("SELECT gambar FROM dt_kop_sekolah WHERE status = 'aktif' ORDER BY id_kop DESC LIMIT 1");
        if ($q && $q->num_rows > 0) {
            $g = trim((string)$q->fetch_assoc()['gambar']);
            $rel = 'assets/img/kop/' . $g;
            if ($g !== '' && is_file(__DIR__ . '/../' . $rel)) {
                return $rel;
            }
        }
        return '';
    }
}

if (!function_exists('laporan_kop_html')) {
    /**
     * Kepala surat: gambar kop bila ada; jika tidak, logo + nama + alamat sekolah.
     */
    function laporan_kop_html($sekolah, $kop_url = '')
    {
        if ($kop_url !== '') {
            return '<div class="laporan-kop laporan-kop-img"><img src="' . htmlspecialchars($kop_url) . '" alt="Kop Sekolah"></div>';
        }
        $logo = laporan_logo_url($sekolah);
        $logo_html = $logo !== '' ? '<img src="' . htmlspecialchars($logo) . '" class="laporan-logo" alt="Logo">' : '';
        $nama = htmlspecialchars((string)($sekolah['nama_sekolah'] ?? ''));
        $alamat = htmlspecialchars((string)($sekolah['alamat_sekolah'] ?? ''));
        $extra = [];
        if (!empty($sekolah['NPSN'])) {
            $extra[] = 'NPSN: ' . htmlspecialchars((string)$sekolah['NPSN']);
        }
        if (!empty($sekolah['telp_sekolah'])) {
            $extra[] = 'Telp: ' . htmlspecialchars((string)$sekolah['telp_sekolah']);
        }
        if (!empty($sekolah['email'])) {
            $extra[] = htmlspecialchars((string)$sekolah['email']);
        }
        return '<div class="laporan-kop">' . $logo_html .
            '<div class="laporan-kop-text">' .
            '<div class="laporan-kop-nama">' . $nama . '</div>' .
            ($alamat !== '' ? '<div class="laporan-kop-alamat">' . $alamat . '</div>' : '') .
            (!empty($extra) ? '<div class="laporan-kop-alamat">' . implode(' | ', $extra) . '</div>' : '') .
            '</div></div>';
    }
}

if (!function_exists('laporan_hari_libur')) {
    /**
     * Peta hari libur pada rentang tanggal (dari dt_kalender_akademik, jenis='Libur').
     * @return array ['YYYY-MM-DD' => 'nama_kegiatan']
     */
    function laporan_hari_libur($koneksi, $id_ta, $awal, $akhir)
    {
        $map = [];
        $id_ta = (int)$id_ta;
        if (!($koneksi instanceof mysqli) || $koneksi->connect_error || $id_ta <= 0 || $awal === '' || $akhir === '') {
            return $map;
        }
        $stmt = $koneksi->prepare("SELECT nama_kegiatan, tanggal_mulai, COALESCE(tanggal_selesai, tanggal_mulai) AS selesai
                                   FROM dt_kalender_akademik
                                   WHERE id_tahun_ajaran = ? AND jenis = 'Libur'
                                     AND tanggal_mulai <= ? AND COALESCE(tanggal_selesai, tanggal_mulai) >= ?");
        if (!$stmt) {
            return $map;
        }
        $stmt->bind_param('iss', $id_ta, $akhir, $awal);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $a = max($r['tanggal_mulai'], $awal);
            $b = min($r['selesai'], $akhir);
            $ta = strtotime($a);
            $tb = strtotime($b);
            for ($t = $ta; $t !== false && $t <= $tb; $t = strtotime('+1 day', $t)) {
                $map[date('Y-m-d', $t)] = $r['nama_kegiatan'];
            }
        }
        $stmt->close();
        return $map;
    }
}

if (!function_exists('laporan_libur_ringkas')) {
    /**
     * Ringkas peta hari libur menjadi teks: "18,19,...,31 — Galungan dan Kuningan".
     */
    function laporan_libur_ringkas($map)
    {
        if (!$map) {
            return '';
        }
        $byName = [];
        foreach ($map as $tgl => $nama) {
            $byName[$nama][] = (int)substr($tgl, 8, 2);
        }
        $parts = [];
        foreach ($byName as $nama => $days) {
            sort($days);
            $parts[] = implode(',', $days) . ' — ' . $nama;
        }
        return implode('; ', $parts);
    }
}

if (!function_exists('laporan_tanggal_id')) {
    /**
     * Format tanggal Indonesia: "15 Oktober 2026". Default = hari ini.
     */
    function laporan_tanggal_id($ts = null)
    {
        if ($ts === null) {
            $ts = time();
        } elseif (!is_int($ts)) {
            $ts = strtotime((string)$ts);
        }
        if ($ts === false) {
            return '-';
        }
        $bulan = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
                  '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
                  '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];
        return date('d', $ts) . ' ' . ($bulan[date('m', $ts)] ?? date('m', $ts)) . ' ' . date('Y', $ts);
    }
}

if (!function_exists('laporan_kepala_sekolah')) {
    /**
     * Nama kepala sekolah dari dt_guru (yang sudah punya lisensi kepala sekolah).
     * @return array ['nama' => string, 'nip' => string]
     */
    function laporan_kepala_sekolah($koneksi)
    {
        $out = ['nama' => '', 'nip' => ''];
        if (!($koneksi instanceof mysqli) || $koneksi->connect_error) {
            return $out;
        }
        $q = $koneksi->query("SELECT nama_guru, nip FROM dt_guru WHERE sudah_lisensi_kepala_sekolah = 1 ORDER BY id_guru ASC LIMIT 1");
        if ($q && $q->num_rows > 0) {
            $r = $q->fetch_assoc();
            $out['nama'] = trim((string)$r['nama_guru']);
            $out['nip']  = trim((string)($r['nip'] ?? ''));
        }
        return $out;
    }
}

if (!function_exists('laporan_footer_html')) {
    /**
     * Blok tanda tangan bawah laporan: tanggal diterbitkan + nama kepala sekolah.
     * Hanya tampil saat cetak (dibungkus .d-none.d-print-block oleh pemanggil).
     */
    function laporan_footer_html($kepala, $tanggal = null)
    {
        $nama = trim((string)($kepala['nama'] ?? ''));
        $nip  = trim((string)($kepala['nip'] ?? ''));
        $tgl  = laporan_tanggal_id($tanggal);
        $h  = '<div class="laporan-footer"><div class="laporan-footer-kanan">';
        $h .= '<div class="laporan-footer-tgl">Diterbitkan: ' . htmlspecialchars($tgl) . '</div>';
        $h .= '<div class="laporan-footer-jabatan">Kepala Sekolah,</div>';
        $h .= '<div class="laporan-footer-ruang"></div>';
        $h .= '<div class="laporan-footer-nama">' . ($nama !== '' ? htmlspecialchars($nama) : '&hellip;&hellip;&hellip;&hellip;&hellip;&hellip;') . '</div>';
        if ($nip !== '') {
            $h .= '<div class="laporan-footer-nip">NIP. ' . htmlspecialchars($nip) . '</div>';
        }
        $h .= '</div></div>';
        return $h;
    }
}

if (!function_exists('laporan_print_css')) {
    /**
     * CSS bersama untuk mode cetak (menyembunyikan kerangka AdminLTE).
     */
    function laporan_print_css()
    {
        return <<<HTML
<style id="laporan-print-css">
.laporan-toolbar { margin-bottom: 1rem; }
.laporan-kop { display: flex; align-items: center; gap: 12px; padding-bottom: 6px; margin-bottom: 10px; border-bottom: 2px solid #000; }
.laporan-kop-img { display: block; padding: 0 2cm; box-sizing: border-box; margin: 0 0 10px 0; border-bottom: none; }
.laporan-kop-img img { display: block; width: 100%; height: auto; }
.laporan-logo { max-height: 22mm; width: auto; flex: 0 0 auto; }
.laporan-kop-nama { font-size: 15pt; font-weight: 700; line-height: 1.15; }
.laporan-kop-alamat { font-size: 9pt; line-height: 1.2; }
.laporan-footer { display: flex; justify-content: flex-end; margin-top: 18px; page-break-inside: avoid; break-inside: avoid; }
.laporan-footer-kanan { width: 65mm; }
.laporan-footer-tgl { margin-bottom: 6px; }
.laporan-footer-jabatan { margin-bottom: 2px; }
.laporan-footer-ruang { height: 18mm; }
.laporan-footer-nama { font-weight: 700; text-decoration: underline; line-height: 1.2; }
.laporan-footer-nip { font-size: 10pt; }
@media print {
  @page { size: A4; margin: 10mm; }
  .no-print, .app-sidebar, .app-header, .app-footer, .main-sidebar,
  .app-content-header, .navbar-top { display: none !important; }
  html, body, body.bg-body-tertiary { background: #fff !important; background-color: #fff !important; background-image: none !important; }
  body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .app-main { margin: 0 !important; }
  .app-wrapper, .content-wrapper, .app-content, .container-fluid,
  .app-main, main, .bg-body-tertiary {
    padding: 0 !important; margin: 0 !important;
    background: #fff !important; background-color: #fff !important; background-image: none !important;
  }
  body.bg-body-tertiary { background: #fff !important; background-color: #fff !important; background-image: none !important; }
  .card { border: 0 !important; box-shadow: none !important; }
}
</style>
HTML;
    }
}
