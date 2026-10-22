<?php
// Helper pre-fill form siswa (dipakai siswa_tambah.php & siswa_edit.php)

if (!function_exists('getData')) {
    // Ambil data dengan aman (hindari error bila kolom null/kosong) + escape.
    function getData($key, $data)
    {
        $value = $data[$key] ?? '';
        return htmlspecialchars($value);
    }
}

if (!function_exists('isSelected')) {
    // Tandai opsi <select> yang terpilih.
    function isSelected($dbKey, $optionValue, $data)
    {
        $dbValue = $data[$dbKey] ?? '';
        return ($dbValue == $optionValue) ? 'selected' : '';
    }
}
