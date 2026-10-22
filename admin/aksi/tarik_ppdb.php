<?php
require_once(__DIR__ . '/akses_guard.php');
include("../koneksi.php");

$back_url = "../index.php?menu=tarik_siswa_ppdb";

// ==========================================================
// FUNGSI BANTUAN
// ==========================================================
function hashPassword($password)
{
    return password_hash($password, PASSWORD_BCRYPT);
}

// Validasi id tahun ajaran dari input; 0 bila tidak valid
function validIdTa($koneksi, $id)
{
    $id = (int)$id;
    if ($id <= 0) return 0;
    $stmt = $koneksi->prepare("SELECT id_tahun_ajaran FROM dt_tahun_ajar WHERE id_tahun_ajaran = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $ada = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $ada ? $id : 0;
}

// Ambil ID tahun ajaran aktif dari sesi
function getIdTahunAjaranAktif($koneksi)
{
    $nama_ta = $_SESSION['tahun_ajaran_aktif'] ?? '';
    if ($nama_ta !== '') {
        $stmt = $koneksi->prepare("SELECT id_tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
        $stmt->bind_param("s", $nama_ta);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $id = (int)$res->fetch_assoc()['id_tahun_ajaran'];
            $stmt->close();
            return $id;
        }
        $stmt->close();
    }
    $res = $koneksi->query("SELECT id_tahun_ajaran FROM dt_tahun_ajar WHERE status = 'aktif' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        return (int)$res->fetch_assoc()['id_tahun_ajaran'];
    }
    return 0;
}

// Tarik satu calon siswa (status 4) ke dt_siswa. Mengembalikan: sukses | duplikat | tidak_ditemukan | gagal
function tarikCalonSiswa($koneksi, $id_calon, $id_ta_aktif)
{
    $stmt = $koneksi->prepare("SELECT * FROM ppdb_calon_siswa WHERE id_calon = ? AND status = 4");
    $stmt->bind_param("i", $id_calon);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return 'tidak_ditemukan';
    }

    $nisn = trim($row['nisn'] ?? '');

    // Cek duplikat: NISN yang sama sudah ada di dt_siswa pada tahun ajaran aktif
    if ($nisn !== '') {
        $cek = $koneksi->prepare("SELECT id_siswa FROM dt_siswa WHERE nisn = ? AND id_tahun_ajaran = ?");
        $cek->bind_param("si", $nisn, $id_ta_aktif);
        $cek->execute();
        if ($cek->get_result()->num_rows > 0) {
            $cek->close();
            return 'duplikat';
        }
        $cek->close();
    }

    $jk           = ($row['jenis_kelamin'] === 'P') ? 'P' : 'L';
    $jurusan      = trim($row['pilihan1'] ?? '');
    if ($jurusan === '') {
        $jurusan = 'Belum Ditentukan';
    }
    $sekolah_asal = trim($row['asal_sekolah'] ?? '');
    $nama_ibu     = trim($row['nama_ibu'] ?? '');
    $no_telp      = trim($row['no_telp'] ?? '');
    $password_raw = trim($row['password'] ?? '');
    if ($password_raw === '') {
        $password_raw = 'siswa123';
    }
    $password_hash = hashPassword($password_raw);

    $sql = "INSERT INTO dt_siswa (
                id_tahun_ajaran, nama, nipd, jk, nisn, tempat_lahir, tanggal_lahir,
                agama, alamat, telp, hp, nama_ibu, jurusan, sekolah_asal, password, foto
            ) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'user.png')";

    $ins = $koneksi->prepare($sql);
    $ins->bind_param(
        "isssssssssssss",
        $id_ta_aktif,
        $row['nama_siswa'],
        $jk,
        $nisn,
        $row['tempat_lahir'],
        $row['tgl_lahir'],
        $row['agama'],
        $row['alamat'],
        $no_telp,
        $no_telp,
        $nama_ibu,
        $jurusan,
        $sekolah_asal,
        $password_hash
    );

    if ($ins->execute()) {
        $ins->close();
        return 'sukses';
    }
    $ins->close();
    return 'gagal';
}

// ==========================================================
// 1. TARIK SATU CALON SISWA
// ==========================================================
if (isset($_POST['tarik_calon'])) {
    $id_calon = (int)($_POST['id_calon'] ?? 0);
    $id_ta_aktif = validIdTa($koneksi, $_POST['id_ta'] ?? 0);
    if ($id_ta_aktif <= 0) {
        $id_ta_aktif = getIdTahunAjaranAktif($koneksi);
    }
    if ($id_ta_aktif > 0) {
        $back_url .= "&ta=" . $id_ta_aktif;
    }

    if ($id_ta_aktif <= 0) {
        echo "<script>alert('Tahun ajaran aktif tidak ditemukan.');window.location='$back_url';</script>";
        exit;
    }
    if ($id_calon <= 0) {
        echo "<script>alert('Data calon siswa tidak valid.');window.location='$back_url';</script>";
        exit;
    }

    $hasil = tarikCalonSiswa($koneksi, $id_calon, $id_ta_aktif);

    switch ($hasil) {
        case 'sukses':
            echo "<script>alert('Calon siswa berhasil ditarik ke data siswa aktif.');window.location='$back_url&pesan=tarikppdb';</script>";
            break;
        case 'duplikat':
            echo "<script>alert('Gagal: NISN siswa ini sudah ada di data siswa tahun ajaran aktif.');window.location='$back_url';</script>";
            break;
        case 'tidak_ditemukan':
            echo "<script>alert('Gagal: Calon siswa tidak ditemukan atau bukan status diterima (4).');window.location='$back_url';</script>";
            break;
        default:
            echo "<script>alert('Gagal menarik data siswa.');window.history.back();</script>";
            break;
    }
    exit;
}

// ==========================================================
// 2. TARIK SEMUA CALON SISWA DITERIMA YANG BELUM DITARIK
// ==========================================================
if (isset($_POST['tarik_semua'])) {
    $id_ta_aktif = validIdTa($koneksi, $_POST['id_ta'] ?? 0);
    if ($id_ta_aktif <= 0) {
        $id_ta_aktif = getIdTahunAjaranAktif($koneksi);
    }
    if ($id_ta_aktif > 0) {
        $back_url .= "&ta=" . $id_ta_aktif;
    }

    if ($id_ta_aktif <= 0) {
        echo "<script>alert('Tahun ajaran aktif tidak ditemukan.');window.location='$back_url';</script>";
        exit;
    }

    $q = $koneksi->query(
        "SELECT cs.id_calon
         FROM ppdb_calon_siswa cs
         LEFT JOIN dt_siswa s ON s.nisn = cs.nisn AND s.id_tahun_ajaran = $id_ta_aktif
         WHERE cs.status = 4 AND cs.tahun_ajaran = $id_ta_aktif AND s.id_siswa IS NULL"
    );

    $ids = [];
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $ids[] = (int)$r['id_calon'];
        }
    }

    if (empty($ids)) {
        echo "<script>alert('Tidak ada calon siswa diterima yang belum ditarik.');window.location='$back_url';</script>";
        exit;
    }

    $sukses = 0;
    $gagal = 0;

    $koneksi->begin_transaction();
    try {
        foreach ($ids as $id_calon) {
            $hasil = tarikCalonSiswa($koneksi, $id_calon, $id_ta_aktif);
            if ($hasil === 'sukses') {
                $sukses++;
            } else {
                $gagal++;
            }
        }
        $koneksi->commit();
    } catch (\Throwable $e) {
        $koneksi->rollback();
        echo "<script>alert('Terjadi kesalahan saat penarikan data: " . addslashes($e->getMessage()) . "');window.location='$back_url';</script>";
        exit;
    }

    $pesan = "Penarikan massal selesai: $sukses siswa berhasil ditarik";
    if ($gagal > 0) {
        $pesan .= ", $gagal gagal/skip";
    }
    $pesan .= ".";

    echo "<script>alert('$pesan');window.location='$back_url&pesan=tarikppdb';</script>";
    exit;
}

// Aksi tidak valid
echo "<script>alert('Aksi tidak valid.');window.location='$back_url';</script>";
exit;
