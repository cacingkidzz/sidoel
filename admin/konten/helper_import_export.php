<?php
/**
 * Helper Import/Export Umum (generalized).
 * Menyediakan baca Excel, validasi preview (duplikasi + wajib), dan eksekusi
 * insert transaksional dengan prepared statement untuk entitas apa pun.
 *
 * Konfigurasi entitas didefinisikan di IE_CONFIG (lihat bawah).
 */
if (!function_exists('ie_normalize_header')) {
    function ie_normalize_header($h)
    {
        $h = trim((string)$h);
        $h = strtolower($h);
        $h = preg_replace('/[\s_\-]+/', '', $h);
        return $h;
    }
}

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (!function_exists('ie_read_excel')) {
    /**
     * Baca sheet aktif Excel. Kembalikan ['headers'=>[...], 'rows'=>[assoc keyed by normalized header], 'colMap'=>[]].
     * Baris 1 = header. Baris kosong diabaikan.
     */
    function ie_read_excel($tmp_path)
    {
        require_once __DIR__ . '/../vendor/autoload.php';
        $spreadsheet = IOFactory::load($tmp_path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = (int)$sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        if ($highestRow < 2) {
            return ['headers' => [], 'rows' => [], 'colMap' => []];
        }

        $maxCol = Coordinate::columnIndexFromString($highestCol);
        $headers = [];
        $colMap = []; // normalized header => column letter
        for ($c = 1; $c <= $maxCol; $c++) {
            $colLetter = Coordinate::stringFromColumnIndex($c);
            $val = $sheet->getCell($colLetter . '1')->getValue();
            $norm = ie_normalize_header($val);
            if ($norm !== '') {
                $headers[] = $norm;
                $colMap[$norm] = $colLetter;
            }
        }

        $rows = [];
        for ($row = 2; $row <= $highestRow; $row++) {
            $isEmpty = true;
            $rowData = [];
            foreach ($colMap as $norm => $colLetter) {
                $v = $sheet->getCell($colLetter . $row)->getValue();
                if ($v !== null && $v !== '') {
                    $isEmpty = false;
                }
                $rowData[$norm] = $v;
            }
            if (!$isEmpty) {
                $rows[] = $rowData;
            }
        }
        return ['headers' => $headers, 'rows' => $rows, 'colMap' => $colMap];
    }
}

if (!function_exists('ie_clean_value')) {
    /**
     * Bersihkan nilai cell untuk dijadikan parameter prepared statement.
     * Mengembalikan null bila kosong (untuk jadi NULL di DB).
     */
    function ie_clean_value($v)
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return $v;
        }
        return trim((string)$v);
    }
}

if (!function_exists('ie_column_type')) {
    /**
     * Tebak tipe kolom DB dari nama kolom (heuristik sederhana) untuk validasi tipe.
     */
    function ie_column_type($col)
    {
        $c = strtolower($col);
        if (preg_match('/^(tanggal|tgl|tahun_lahir|tmt|created_at|updated_at|date)$/', $c)) {
            return 'date';
        }
        if (preg_match('/^(id|no|nomor|jumlah|jam_ke|tingkat|anak_ke|kelas|guru|mapel|jurusan|status_aktif|jk)$/', $c)) {
            return 'int';
        }
        if (preg_match('/^(lintang|bujur|berat|tinggi|jarak|penghasilan|biaya|potongan)$/', $c)) {
            return 'float';
        }
        if (preg_match('/^(email|nisn|nik|nipd|nip|nuptk|no_kk|hp|telp)$/', $c)) {
            return 'string';
        }
        return 'string';
    }
}

if (!function_exists('ie_preview')) {
    /**
     * Validasi baris import sebelum disimpan.
     * $config: ['table'=>..., 'identity'=>[cols for duplicate check], 'required'=>[cols], 'map'=>[normHeader=>dbCol]]
     * $rows: array assoc keyed by normalized Excel header.
     * Kembalikan ['valid'=>[...], 'invalid'=>[row=>[errors]], 'duplicates'=>[row=>reason], 'counts'=>[...]]
     * Setiap entri 'valid' berisi data terstruktur keyed by DB column.
     */
    function ie_preview($koneksi, $config, $rows)
    {
        $result = ['valid' => [], 'invalid' => [], 'duplicates' => [], 'counts' => ['total' => 0, 'valid' => 0, 'invalid' => 0, 'duplicate' => 0]];
        $table = $config['table'];
        $identity = $config['identity'] ?? [];
        $required = $config['required'] ?? [];
        $map = $config['map'] ?? [];

        // Cache daftar nilai identitas yang sudah ada di DB (per entitas, satu query)
        $existing = [];
        if ($identity) {
            $idCols = implode(', ', array_map(function ($c) { return '`' . $c . '`'; }, $identity));
            $q = $koneksi->query("SELECT $idCols FROM `$table`");
            if ($q) {
                while ($r = $q->fetch_assoc()) {
                    $key = implode('|', array_map(function ($c) use ($r) { return (string)($r[$c] ?? ''); }, $identity));
                    $existing[$key] = true;
                }
            }
        }

        $seenInFile = []; // deteksi duplikasi dalam file itu sendiri

        foreach ($rows as $idx => $row) {
            $result['counts']['total']++;
            $rowNo = $idx + 2; // baris Excel (header = 1)
            $errors = [];

            // Bangun data keyed by DB column dari map
            $data = [];
            foreach ($map as $normHeader => $dbCol) {
                $data[$dbCol] = ie_clean_value($row[$normHeader] ?? null);
            }

            // Validasi wajib
            foreach ($required as $req) {
                $val = $data[$req] ?? null;
                if ($val === null || $val === '') {
                    $errors[] = "Kolom '$req' wajib diisi.";
                }
            }

            // Validasi tipe sederhana (tanggal)
            foreach ($data as $dbCol => $val) {
                if ($val === null) {
                    continue;
                }
                if (ie_column_type($dbCol) === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$val)) {
                    $errors[] = "Kolom '$dbCol' harus berformat tanggal YYYY-MM-DD.";
                }
            }

            // Cek duplikasi (DB + dalam file)
            if ($identity && empty($errors)) {
                $keyParts = [];
                foreach ($identity as $idCol) {
                    $keyParts[] = (string)($data[$idCol] ?? '');
                }
                $key = implode('|', $keyParts);
                $allEmpty = true;
                foreach ($keyParts as $kp) {
                    if ($kp !== '') { $allEmpty = false; break; }
                }
                if (!$allEmpty) {
                    if (isset($existing[$key]) || isset($seenInFile[$key])) {
                        $result['duplicates'][$rowNo] = 'Duplikat (' . implode(', ', $identity) . ' sudah ada).';
                        $result['counts']['duplicate']++;
                        continue;
                    }
                    $seenInFile[$key] = true;
                }
            }

            if (!empty($errors)) {
                $result['invalid'][$rowNo] = $errors;
                $result['counts']['invalid']++;
                continue;
            }

            $result['valid'][] = ['row' => $rowNo, 'data' => $data];
            $result['counts']['valid']++;
        }

        return $result;
    }
}

if (!function_exists('ie_execute')) {
    /**
     * Eksekusi insert valid rows dalam satu transaksi dengan prepared statement.
     * $validRows: ['valid'=>[['data'=>[dbCol=>val], ...]]]
     * $defaults: array dbCol=>value merged into each row before insert (e.g. id_tahun_ajaran).
     * Kembalikan ['inserted'=>N, 'errors'=>[]].
     */
    function ie_execute($koneksi, $config, $validRows, $defaults = [])
    {
        $result = ['inserted' => 0, 'errors' => []];
        $table = $config['table'];
        $koneksi->begin_transaction();
        try {
            foreach ($validRows as $item) {
                $data = array_merge($defaults, $item['data']);
                $cols = array_keys($data);
                $colSql = implode(', ', array_map(function ($c) { return '`' . $c . '`'; }, $cols));
                $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                $sql = "INSERT INTO `$table` ($colSql) VALUES ($placeholders)";
                $stmt = $koneksi->prepare($sql);
                if (!$stmt) {
                    throw new Exception($koneksi->error);
                }
                $types = '';
                $bindVals = [];
                foreach ($data as $col => $val) {
                    $t = ie_column_type($col);
                    if ($t === 'int') { $types .= 'i'; $bindVals[] = $val === null ? null : (int)$val; }
                    elseif ($t === 'float') { $types .= 'd'; $bindVals[] = $val === null ? null : (float)$val; }
                    else { $types .= 's'; $bindVals[] = $val === null ? null : (string)$val; }
                }
                if ($bindVals) {
                    $stmt->bind_param($types, ...$bindVals);
                }
                if (!$stmt->execute()) {
                    throw new Exception($stmt->error);
                }
                $stmt->close();
                $result['inserted']++;
            }
            $koneksi->commit();
        } catch (Exception $e) {
            $koneksi->rollback();
            $result['errors'][] = $e->getMessage();
        }
        return $result;
    }
}
