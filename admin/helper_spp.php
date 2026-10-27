<?php
/**
 * Helper modul SPP (Keuangan).
 *
 * Dipakai oleh:
 *   - konten/spp_tarif.php, spp_tagihan.php, spp_pembayaran.php, spp_rekap.php
 *   - aksi/aksi_spp_tarif.php, aksi_spp_tagihan.php, aksi_spp_pembayaran.php
 *
 * Model tagihan: BULANAN, 12 bulan per tahun ajaran (Juli s/d Juni).
 * Tarif dibedakan per TINGKAT + JURUSAN; id_jurusan = 0 = tarif cadangan.
 */

if (!function_exists('spp_rupiah')) {
    /** Format rupiah: 1500000 -> "Rp 1.500.000" */
    function spp_rupiah($nominal, string $prefix = 'Rp '): string
    {
        return $prefix . number_format((float)$nominal, 0, ',', '.');
    }
}

if (!function_exists('spp_nominal_input')) {
    /**
     * Ubah input nominal dari form (boleh "1.500.000", "1500000", "1.500.000,50")
     * menjadi float. Nilai tak valid dianggap 0.
     */
    function spp_nominal_input($nilai): float
    {
        $v = trim((string)$nilai);
        if ($v === '') {
            return 0.0;
        }
        // Buang pemisah ribuan (titik) dan spasi; koma desimal -> titik.
        $v = str_replace([' ', "\xc2\xa0"], '', $v);
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
        return is_numeric($v) ? round((float)$v, 2) : 0.0;
    }
}

if (!function_exists('spp_nama_bulan')) {
    function spp_nama_bulan(int $bulan): string
    {
        $nama = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        return $nama[$bulan] ?? ('Bulan ' . $bulan);
    }
}

if (!function_exists('spp_bulan_label')) {
    /** "Juli 2027" */
    function spp_bulan_label(int $tahun, int $bulan): string
    {
        return spp_nama_bulan($bulan) . ' ' . $tahun;
    }
}

if (!function_exists('spp_ta_periode')) {
    /**
     * Periode 12 bulan dari nama tahun ajaran.
     * "2027/2028" -> Juli 2027 .. Juni 2028.
     * Bila format tidak dikenal, dipakai Juli tahun berjalan.
     *
     * @return array<int, array{tahun:int, bulan:int, label:string, jatuh_tempo:string}>
     */
    function spp_ta_periode(string $nama_ta): array
    {
        $tahun_mulai = (int)date('Y');
        if (preg_match('/(\d{4})\s*\/\s*(\d{4})/', $nama_ta, $m)) {
            $tahun_mulai = (int)$m[1];
        }

        $periode = [];
        for ($i = 0; $i < 12; $i++) {
            $bulan = 7 + $i;
            $tahun = $tahun_mulai;
            if ($bulan > 12) {
                $bulan -= 12;
                $tahun++;
            }
            $periode[] = [
                'tahun' => $tahun,
                'bulan' => $bulan,
                'label' => spp_bulan_label($tahun, $bulan),
                'jatuh_tempo' => sprintf('%04d-%02d-10', $tahun, $bulan),
            ];
        }
        return $periode;
    }
}

if (!function_exists('spp_ta_info')) {
    /** Info tahun ajaran: ['id','nama','periode'] atau null. */
    function spp_ta_info($koneksi, int $id_ta): ?array
    {
        if ($id_ta <= 0 || !($koneksi instanceof mysqli)) {
            return null;
        }
        $stmt = $koneksi->prepare("SELECT id_tahun_ajaran, tahun_ajaran FROM dt_tahun_ajar WHERE id_tahun_ajaran = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id_ta);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$r) {
            return null;
        }
        return [
            'id' => (int)$r['id_tahun_ajaran'],
            'nama' => (string)$r['tahun_ajaran'],
            'periode' => spp_ta_periode((string)$r['tahun_ajaran']),
        ];
    }
}

if (!function_exists('spp_ta_aktif_id')) {
    /** Id TA aktif (status='aktif') sebagai cadangan bila session kosong. */
    function spp_ta_aktif_id($koneksi): int
    {
        $q = $koneksi->query("SELECT id_tahun_ajaran FROM dt_tahun_ajar WHERE status = 'aktif' ORDER BY id_tahun_ajaran DESC LIMIT 1");
        if ($q && ($r = $q->fetch_assoc())) {
            return (int)$r['id_tahun_ajaran'];
        }
        return 0;
    }
}

if (!function_exists('spp_tingkat_list')) {
    /**
     * Daftar tingkat untuk matriks tarif.
     *
     * Selalu memuat X/XI/XII (agar tarif bisa disiapkan sebelum kelas dibuat),
     * ditambah tingkat lain yang benar-benar ada di dt_kelas pada TA tsb.
     *
     * @return string[]
     */
    function spp_tingkat_list($koneksi, int $id_ta): array
    {
        $urutan = ['X' => 1, 'XI' => 2, 'XII' => 3, 'XIII' => 4];
        $hasil = ['X' => 1, 'XI' => 2, 'XII' => 3];
        if ($id_ta > 0) {
            $stmt = $koneksi->prepare("SELECT DISTINCT tingkat FROM dt_kelas WHERE id_tahun_ajaran = ?");
            if ($stmt) {
                $stmt->bind_param('i', $id_ta);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $t = trim((string)$r['tingkat']);
                    if ($t !== '') {
                        $hasil[$t] = $urutan[$t] ?? 9;
                    }
                }
                $stmt->close();
            }
        }
        asort($hasil);
        return array_keys($hasil);
    }
}

if (!function_exists('spp_jurusan_list')) {
    /** @return array<int,string> id_jurusan => nama_jurusan */
    function spp_jurusan_list($koneksi): array
    {
        $out = [];
        $q = $koneksi->query("SELECT id_jurusan, nama_jurusan FROM dt_jurusan ORDER BY nama_jurusan ASC");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $out[(int)$r['id_jurusan']] = (string)$r['nama_jurusan'];
            }
        }
        return $out;
    }
}

if (!function_exists('spp_tarif_map')) {
    /**
     * Peta tarif TA: ["tingkat|id_jurusan" => nominal].
     * Kunci "X|0" = tarif cadangan semua jurusan.
     *
     * @return array<string,float>
     */
    function spp_tarif_map($koneksi, int $id_ta): array
    {
        $out = [];
        if ($id_ta <= 0) {
            return $out;
        }
        $stmt = $koneksi->prepare("SELECT tingkat, id_jurusan, nominal FROM dt_spp_tarif WHERE id_tahun_ajaran = ?");
        if (!$stmt) {
            return $out;
        }
        $stmt->bind_param('i', $id_ta);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $out[trim((string)$r['tingkat']) . '|' . (int)$r['id_jurusan']] = (float)$r['nominal'];
        }
        $stmt->close();
        return $out;
    }
}

if (!function_exists('spp_tarif_cari')) {
    /**
     * Cari nominal tarif: tepat (tingkat+jurusan) -> cadangan (tingkat+0) -> null.
     *
     * @param array<string,float> $map hasil spp_tarif_map()
     */
    function spp_tarif_cari(array $map, string $tingkat, int $id_jurusan): ?float
    {
        $k1 = trim($tingkat) . '|' . $id_jurusan;
        if (isset($map[$k1])) {
            return (float)$map[$k1];
        }
        $k2 = trim($tingkat) . '|0';
        if (isset($map[$k2])) {
            return (float)$map[$k2];
        }
        return null;
    }
}

if (!function_exists('spp_kelas_list')) {
    /**
     * Daftar kelas satu TA.
     * @return array<int,array{id_kelas:int,nama_kelas:string,tingkat:string,id_jurusan:int,jurusan:string,label:string,jumlah_siswa:int}>
     */
    function spp_kelas_list($koneksi, int $id_ta): array
    {
        $out = [];
        if ($id_ta <= 0) {
            return $out;
        }
        $sql = "SELECT k.id_kelas, k.nama_kelas, k.tingkat, k.id_jurusan,
                       IFNULL(j.nama_jurusan, '') AS jurusan,
                       (SELECT COUNT(*) FROM dt_kelas_siswa ks
                          INNER JOIN dt_siswa s ON s.id_siswa = ks.id_siswa
                         WHERE ks.id_kelas = k.id_kelas AND ks.id_tahun_ajaran = k.id_tahun_ajaran) AS jumlah_siswa
                  FROM dt_kelas k
                  LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                 WHERE k.id_tahun_ajaran = ?
                 ORDER BY k.tingkat ASC, k.nama_kelas ASC";
        $stmt = $koneksi->prepare($sql);
        if (!$stmt) {
            return $out;
        }
        $stmt->bind_param('i', $id_ta);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $label = trim($r['tingkat'] . ' ' . $r['nama_kelas']);
            if ($r['jurusan'] !== '') {
                $label .= ' (' . $r['jurusan'] . ')';
            }
            $out[(int)$r['id_kelas']] = [
                'id_kelas' => (int)$r['id_kelas'],
                'nama_kelas' => (string)$r['nama_kelas'],
                'tingkat' => (string)$r['tingkat'],
                'id_jurusan' => (int)$r['id_jurusan'],
                'jurusan' => (string)$r['jurusan'],
                'label' => $label,
                'jumlah_siswa' => (int)$r['jumlah_siswa'],
            ];
        }
        $stmt->close();
        return $out;
    }
}

if (!function_exists('spp_roster')) {
    /**
     * Roster siswa satu TA (history-aware dari dt_kelas_siswa), opsional per kelas.
     *
     * @return array<int,array>
     */
    function spp_roster($koneksi, int $id_ta, int $id_kelas = 0): array
    {
        $out = [];
        if ($id_ta <= 0) {
            return $out;
        }
        $sql = "SELECT s.id_siswa, s.nama, s.nipd, s.nisn, s.jk,
                       ks.id_kelas, k.nama_kelas, k.tingkat, k.id_jurusan,
                       IFNULL(j.nama_jurusan, '') AS jurusan
                  FROM dt_kelas_siswa ks
                  INNER JOIN dt_siswa s ON s.id_siswa = ks.id_siswa
                  INNER JOIN dt_kelas k ON k.id_kelas = ks.id_kelas
                  LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                 WHERE ks.id_tahun_ajaran = ?";
        if ($id_kelas > 0) {
            $sql .= " AND ks.id_kelas = ?";
        }
        $sql .= " ORDER BY k.tingkat ASC, k.nama_kelas ASC, s.nama ASC";

        $stmt = $koneksi->prepare($sql);
        if (!$stmt) {
            return $out;
        }
        if ($id_kelas > 0) {
            $stmt->bind_param('ii', $id_ta, $id_kelas);
        } else {
            $stmt->bind_param('i', $id_ta);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $out[] = $r;
        }
        $stmt->close();
        return $out;
    }
}

if (!function_exists('spp_terbayar_map')) {
    /**
     * Total pembayaran per tagihan.
     *
     * @param int[] $id_tagihan
     * @return array<int,float> id_tagihan => total dibayar
     */
    function spp_terbayar_map($koneksi, array $id_tagihan): array
    {
        $out = [];
        $ids = array_values(array_filter(array_map('intval', $id_tagihan), function ($v) {
            return $v > 0;
        }));
        if (empty($ids)) {
            return $out;
        }
        $in = implode(',', $ids);
        $q = $koneksi->query("SELECT id_tagihan, SUM(nominal) AS bayar FROM dt_spp_pembayaran WHERE id_tagihan IN ($in) GROUP BY id_tagihan");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $out[(int)$r['id_tagihan']] = (float)$r['bayar'];
            }
        }
        return $out;
    }
}

if (!function_exists('spp_status_dari')) {
    /** Hitung status dari total tagihan vs total dibayar. */
    function spp_status_dari(float $total, float $terbayar): string
    {
        if ($total <= 0) {
            return 'Lunas';
        }
        if ($terbayar + 0.005 >= $total) {
            return 'Lunas';
        }
        return $terbayar > 0 ? 'Sebagian' : 'Belum';
    }
}

if (!function_exists('spp_recompute_status')) {
    /** Hitung ulang status satu tagihan dari akumulasi pembayaran. */
    function spp_recompute_status($koneksi, int $id_tagihan): string
    {
        if ($id_tagihan <= 0) {
            return 'Belum';
        }
        $stmt = $koneksi->prepare("SELECT total FROM dt_spp_tagihan WHERE id_tagihan = ? LIMIT 1");
        if (!$stmt) {
            return 'Belum';
        }
        $stmt->bind_param('i', $id_tagihan);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$r) {
            return 'Belum';
        }
        $total = (float)$r['total'];

        $stmt = $koneksi->prepare("SELECT IFNULL(SUM(nominal),0) AS bayar FROM dt_spp_pembayaran WHERE id_tagihan = ?");
        $bayar = 0.0;
        if ($stmt) {
            $stmt->bind_param('i', $id_tagihan);
            $stmt->execute();
            $b = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $bayar = (float)($b['bayar'] ?? 0);
        }

        $status = spp_status_dari($total, $bayar);
        $stmt = $koneksi->prepare("UPDATE dt_spp_tagihan SET status = ? WHERE id_tagihan = ?");
        if ($stmt) {
            $stmt->bind_param('si', $status, $id_tagihan);
            $stmt->execute();
            $stmt->close();
        }
        return $status;
    }
}

if (!function_exists('spp_status_badge')) {
    /** Badge Bootstrap untuk status tagihan. */
    function spp_status_badge(string $status): string
    {
        $map = [
            'Lunas' => 'success',
            'Sebagian' => 'warning text-dark',
            'Belum' => 'danger',
        ];
        $cls = $map[$status] ?? 'secondary';
        return '<span class="badge bg-' . $cls . '">' . htmlspecialchars($status) . '</span>';
    }
}

if (!function_exists('spp_bulan_singkat')) {
    /** Nama bulan 3 huruf untuk header tabel: "Jul", "Agu", ... */
    function spp_bulan_singkat(int $bulan): string
    {
        $peta = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];
        return $peta[$bulan] ?? ('B' . $bulan);
    }
}

if (!function_exists('spp_matriks_kelas')) {
    /**
     * Roster + tagihan satu kelas pada satu TA (bahan tabel pembayaran 12 bulan).
     *
     * Hasil:
     *   siswa    : id_siswa => [id_siswa, nama, nipd, nisn, jk]
     *   tagihan  : id_siswa => ["YYYY-MM" => baris dt_spp_tagihan]
     *   terbayar : id_tagihan => total dibayar
     *   total / terbayar_total / sisa_total : ringkasan kelas
     *
     * @return array<string,mixed>
     */
    function spp_matriks_kelas($koneksi, int $id_ta, int $id_kelas): array
    {
        $out = [
            'siswa' => [],
            'tagihan' => [],
            'terbayar' => [],
            'total' => 0.0,
            'terbayar_total' => 0.0,
            'sisa_total' => 0.0,
        ];
        if ($id_ta <= 0 || $id_kelas <= 0 || !($koneksi instanceof mysqli)) {
            return $out;
        }

        // Roster kelas (history-aware).
        $sql = "SELECT s.id_siswa, s.nama, s.nipd, s.nisn, s.jk
                  FROM dt_kelas_siswa ks
                  INNER JOIN dt_siswa s ON s.id_siswa = ks.id_siswa
                 WHERE ks.id_tahun_ajaran = ? AND ks.id_kelas = ?
                 ORDER BY s.nama ASC";
        $stmt = $koneksi->prepare($sql);
        if (!$stmt) {
            return $out;
        }
        $stmt->bind_param('ii', $id_ta, $id_kelas);
        $stmt->execute();
        $res = $stmt->get_result();
        $ids = [];
        while ($r = $res->fetch_assoc()) {
            $id = (int)$r['id_siswa'];
            $out['siswa'][$id] = $r;
            $ids[] = $id;
        }
        $stmt->close();
        if (!$ids) {
            return $out;
        }

        // Tagihan seluruh siswa kelas tsb pada TA ini.
        $in = implode(',', $ids);
        $q = $koneksi->query("SELECT id_tagihan, id_siswa, tahun, bulan, nominal, potongan, total, status
                                FROM dt_spp_tagihan
                               WHERE id_tahun_ajaran = " . (int)$id_ta . "
                                 AND id_siswa IN ($in)
                               ORDER BY tahun ASC, bulan ASC");
        $tag_ids = [];
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $key = sprintf('%04d-%02d', (int)$r['tahun'], (int)$r['bulan']);
                $out['tagihan'][(int)$r['id_siswa']][$key] = $r;
                $tag_ids[] = (int)$r['id_tagihan'];
            }
        }

        $out['terbayar'] = spp_terbayar_map($koneksi, $tag_ids);

        foreach ($out['tagihan'] as $list) {
            foreach ($list as $t) {
                $bayar = (float)($out['terbayar'][(int)$t['id_tagihan']] ?? 0);
                $out['total'] += (float)$t['total'];
                $out['terbayar_total'] += $bayar;
            }
        }
        $out['sisa_total'] = max(0, $out['total'] - $out['terbayar_total']);

        return $out;
    }
}

if (!function_exists('spp_siswa_ringkas')) {
    /** Data ringkas siswa + kelas pada TA (history-aware). */
    function spp_siswa_ringkas($koneksi, int $id_siswa, int $id_ta): ?array
    {
        if ($id_siswa <= 0) {
            return null;
        }
        $sql = "SELECT s.id_siswa, s.nama, s.nipd, s.nisn, s.jk,
                       IFNULL(ks.id_kelas, 0) AS id_kelas,
                       IFNULL(k.nama_kelas, '') AS nama_kelas,
                       IFNULL(k.tingkat, '') AS tingkat,
                       IFNULL(k.id_jurusan, 0) AS id_jurusan,
                       IFNULL(j.nama_jurusan, '') AS jurusan
                  FROM dt_siswa s
                  LEFT JOIN dt_kelas_siswa ks ON ks.id_siswa = s.id_siswa AND ks.id_tahun_ajaran = ?
                  LEFT JOIN dt_kelas k ON k.id_kelas = ks.id_kelas
                  LEFT JOIN dt_jurusan j ON j.id_jurusan = k.id_jurusan
                 WHERE s.id_siswa = ?
                 LIMIT 1";
        $stmt = $koneksi->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ii', $id_ta, $id_siswa);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $r ?: null;
    }
}
