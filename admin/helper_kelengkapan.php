<?php
/**
 * Helper kelengkapan data siswa.
 * Definisi field wajib & pendukung dipakai bersama oleh halaman admin
 * (Kelengkapan Data, Dashboard Wali Kelas) dan Portal Siswa.
 */

if (!function_exists('kel_required_fields')) {
    function kel_required_fields(): array
    {
        return [
            ['key' => 'nama', 'label' => 'Nama Siswa', 'group' => 'Identitas'],
            ['key' => 'nipd', 'label' => 'NIPD / NIS', 'group' => 'Identitas'],
            ['key' => 'jk', 'label' => 'Jenis Kelamin', 'group' => 'Identitas'],
            ['key' => 'nisn', 'label' => 'NISN', 'group' => 'Identitas'],
            ['key' => 'tempat_lahir', 'label' => 'Tempat Lahir', 'group' => 'Identitas'],
            ['key' => 'tanggal_lahir', 'label' => 'Tanggal Lahir', 'group' => 'Identitas'],
            ['key' => 'nik', 'label' => 'NIK', 'group' => 'Identitas'],
            ['key' => 'agama', 'label' => 'Agama', 'group' => 'Identitas'],
            ['key' => 'no_kk', 'label' => 'No. KK', 'group' => 'Identitas'],
            ['key' => 'alamat', 'label' => 'Alamat', 'group' => 'Alamat'],
            ['key' => 'kelurahan', 'label' => 'Kelurahan / Desa', 'group' => 'Alamat'],
            ['key' => 'kecamatan', 'label' => 'Kecamatan', 'group' => 'Alamat'],
            ['key' => 'hp', 'label' => 'No. HP', 'group' => 'Kontak'],
            ['key' => 'nama_ayah', 'label' => 'Nama Ayah', 'group' => 'Orang Tua'],
            ['key' => 'nama_ibu', 'label' => 'Nama Ibu', 'group' => 'Orang Tua'],
            ['key' => 'sekolah_asal', 'label' => 'Sekolah Asal', 'group' => 'Akademik'],
        ];
    }
}

if (!function_exists('kel_optional_groups')) {
    function kel_optional_groups(): array
    {
        return [
            'Foto' => [['key' => 'foto', 'label' => 'Foto Siswa']],
            'Kontak Tambahan' => [
                ['key' => 'telp', 'label' => 'Telepon'],
                ['key' => 'email', 'label' => 'Email'],
            ],
            'Alamat Tambahan' => [
                ['key' => 'rt_rw', 'label' => 'RT/RW'],
                ['key' => 'dusun', 'label' => 'Dusun'],
                ['key' => 'kode_pos', 'label' => 'Kode Pos'],
            ],
            'Domisili' => [
                ['key' => 'jenis_tinggal', 'label' => 'Jenis Tinggal'],
                ['key' => 'transportasi', 'label' => 'Transportasi'],
                ['key' => 'jarak_rumah_ke_sekolah_km', 'label' => 'Jarak ke Sekolah'],
            ],
            'Koordinat' => [
                ['key' => 'lintang', 'label' => 'Latitude'],
                ['key' => 'bujur', 'label' => 'Longitude'],
            ],
            'Data Fisik' => [
                ['key' => 'berat_badan', 'label' => 'Berat Badan'],
                ['key' => 'tinggi_badan', 'label' => 'Tinggi Badan'],
                ['key' => 'jml_saudara_kandung', 'label' => 'Jumlah Saudara'],
            ],
            'Data Ayah' => [
                ['key' => 'tahun_lahir_ayah', 'label' => 'Tahun Lahir'],
                ['key' => 'pendidikan_ayah', 'label' => 'Pendidikan'],
                ['key' => 'pekerjaan_ayah', 'label' => 'Pekerjaan'],
                ['key' => 'penghasilan_ayah', 'label' => 'Penghasilan'],
                ['key' => 'nik_ayah', 'label' => 'NIK Ayah'],
            ],
            'Data Ibu' => [
                ['key' => 'tahun_lahir_ibu', 'label' => 'Tahun Lahir'],
                ['key' => 'pendidikan_ibu', 'label' => 'Pendidikan'],
                ['key' => 'pekerjaan_ibu', 'label' => 'Pekerjaan'],
                ['key' => 'penghasilan_ibu', 'label' => 'Penghasilan'],
                ['key' => 'nik_ibu', 'label' => 'NIK Ibu'],
            ],
            'Data Wali' => [
                ['key' => 'nama_wali', 'label' => 'Nama Wali'],
                ['key' => 'tahun_lahir_wali', 'label' => 'Tahun Lahir'],
                ['key' => 'pendidikan_wali', 'label' => 'Pendidikan'],
                ['key' => 'pekerjaan_wali', 'label' => 'Pekerjaan'],
                ['key' => 'penghasilan_wali', 'label' => 'Penghasilan'],
                ['key' => 'nik_wali', 'label' => 'NIK Wali'],
            ],
        ];
    }
}

if (!function_exists('kel_isi')) {
    function kel_isi($v): bool
    {
        if ($v === null) return false;
        $v = trim((string)$v);
        return $v !== '' && $v !== '0' && $v !== '0000-00-00';
    }
}

if (!function_exists('kel_opsi_isi')) {
    function kel_opsi_isi(string $key, $v): bool
    {
        if ($key === 'foto') {
            $f = basename(trim((string)$v));
            return $f !== '' && $f !== 'user.png'
                && is_file(__DIR__ . '/assets/img/foto_siswa/' . $f);
        }
        return kel_isi($v);
    }
}

if (!function_exists('kel_pct')) {
    function kel_pct(int $filled, int $total): float
    {
        return $total > 0 ? round($filled * 100 / $total, 1) : 0.0;
    }
}

if (!function_exists('kel_cls')) {
    function kel_cls(float $pct): string
    {
        if ($pct >= 90) return 'success';
        if ($pct >= 70) return 'warning';
        return 'danger';
    }
}

if (!function_exists('kel_baris')) {
    function kel_baris(array $row): array
    {
        $required = kel_required_fields();
        $total = count($required);
        $filled = 0;
        $missing = [];
        $missing_keys = [];
        foreach ($required as $f) {
            if (kel_isi($row[$f['key']] ?? null)) {
                $filled++;
            } else {
                $missing[] = $f['label'];
                $missing_keys[] = $f['key'];
            }
        }
        return [
            'filled' => $filled,
            'total' => $total,
            'pct' => kel_pct($filled, $total),
            'missing' => $missing,
            'missing_keys' => $missing_keys,
            'lengkap' => ($filled === $total),
        ];
    }
}

if (!function_exists('kel_group')) {
    function kel_group(array $rows, array $fields, bool $optional = false): array
    {
        $filled = 0;
        $total = 0;
        foreach ($rows as $r) {
            foreach ($fields as $f) {
                $total++;
                $ok = $optional
                    ? kel_opsi_isi($f['key'], $r[$f['key']] ?? null)
                    : kel_isi($r[$f['key']] ?? null);
                if ($ok) $filled++;
            }
        }
        return ['filled' => $filled, 'total' => $total, 'pct' => kel_pct($filled, $total)];
    }
}
