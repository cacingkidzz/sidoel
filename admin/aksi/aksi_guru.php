<?php
require_once(__DIR__ . '/akses_guard.php');
include("../koneksi.php");

// Folder untuk simpan foto
$uploadDir = "../assets/img/foto_guru/";

$aksi = $_POST['aksi'] ?? ($_GET['aksi'] ?? '');

// Daftar field guru (nama kolom => key POST)
$field_map = [
    'nama_guru' => 'nama_guru',
    'nuptk' => 'nuptk',
    'nip' => 'nip',
    'nik' => 'nik',
    'no_kk' => 'no_kk',
    'tempat_lahir' => 'tempat_lahir',
    'tanggal_lahir' => 'tanggal_lahir',
    'jk' => 'jk',
    'agama' => 'agama',
    'nama_ibu_kandung' => 'nama_ibu_kandung',
    'status_perkawinan' => 'status_perkawinan',
    'nama_suami_istri' => 'nama_suami_istri',
    'kewarganegaraan' => 'kewarganegaraan',
    'email' => 'email',
    'hp' => 'hp',
    'alamat' => 'alamat',
    'desa_kelurahan' => 'desa_kelurahan',
    'kecamatan' => 'kecamatan',
    'lintang' => 'lintang',
    'bujur' => 'bujur',
    'status_kepegawaian' => 'status_kepegawaian',
    'tugas_tambahan' => 'tugas_tambahan',
    'lulusan' => 'lulusan',
    'jurusan' => 'jurusan',
    'sk_pengangkatan' => 'sk_pengangkatan',
    'tmt_pengangkatan' => 'tmt_pengangkatan',
    'lembaga_pengangkatan' => 'lembaga_pengangkatan',
    'sudah_lisensi_kepala_sekolah' => 'sudah_lisensi_kepala_sekolah',
    'npwp' => 'npwp',
    'nama_wajib_pajak' => 'nama_wajib_pajak',
    'bank' => 'bank',
    'nomor_rekening_bank' => 'nomor_rekening_bank',
    'rekening_atas_nama' => 'rekening_atas_nama',
];

if ($aksi == "tambah") {
    // Upload foto
    $foto = "";
    if (!empty($_FILES['foto']['name'])) {
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto = time() . "_" . rand(100, 999) . "." . $ext;
        move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $foto);
    }

    $columns = array_keys($field_map);
    $columns[] = 'password';
    $columns[] = 'foto';

    $values = [];
    foreach ($field_map as $post_key) {
        $values[] = $_POST[$post_key] ?? '';
    }
    $values[] = 'guru12345';
    $values[] = $foto;

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $sql = "INSERT INTO dt_guru (" . implode(', ', $columns) . ") VALUES ($placeholders)";

    $stmt = $koneksi->prepare($sql);
    if ($stmt) {
        $stmt->bind_param(str_repeat('s', count($values)), ...$values);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: ../index.php?menu=guru&pesan=tambah");
    exit();
}

if ($aksi == "edit") {
    $id_guru   = (int)($_POST['id_guru'] ?? 0);
    $foto_lama = $_POST['foto_lama'] ?? '';

    // Upload foto baru
    $foto = $foto_lama;
    if (!empty($_FILES['foto']['name'])) {
        if (!empty($foto_lama) && file_exists($uploadDir . $foto_lama)) {
            unlink($uploadDir . $foto_lama);
        }
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $foto = time() . "_" . rand(100, 999) . "." . $ext;
        move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $foto);
    }

    $set_parts = [];
    $values = [];
    foreach ($field_map as $column => $post_key) {
        $set_parts[] = "$column = ?";
        $values[] = $_POST[$post_key] ?? '';
    }
    $set_parts[] = "foto = ?";
    $values[] = $foto;
    $values[] = $id_guru;

    $sql = "UPDATE dt_guru SET " . implode(', ', $set_parts) . " WHERE id_guru = ?";

    $stmt = $koneksi->prepare($sql);
    if ($stmt) {
        $types = str_repeat('s', count($values) - 1) . 'i';
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: ../index.php?menu=guru&pesan=edit");
    exit();
}

if ($aksi == "hapus") {
    $id_guru = (int)($_GET['id_guru'] ?? 0);

    if ($id_guru > 0) {
        $stmt = $koneksi->prepare("SELECT foto FROM dt_guru WHERE id_guru = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $id_guru);
            $stmt->execute();
            $res = $stmt->get_result();
            $d = $res->fetch_assoc();
            $stmt->close();

            if (!empty($d['foto']) && file_exists($uploadDir . $d['foto'])) {
                unlink($uploadDir . $d['foto']);
            }
        }

        $stmt = $koneksi->prepare("DELETE FROM dt_guru WHERE id_guru = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id_guru);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: ../index.php?menu=guru&pesan=hapus");
    exit();
}
