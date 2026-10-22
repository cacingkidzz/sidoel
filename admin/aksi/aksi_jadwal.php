<?php
require_once(__DIR__ . '/akses_guard.php');
// ==========================================================
// File: aksi/aksi_jadwal.php  (KBM-2 Jadwal Pelajaran)
// - simpan : set 1 mapel untuk 1 sel (kelas + hari + jam) pada 1 TA; guru otomatis dari pengampu
// - hapus  : hapus satu entri jadwal
// ==========================================================
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("../koneksi.php");

function setAlert($type, $msg)
{
    $_SESSION['alert'] = ['type' => $type, 'msg' => $msg];
}

$HARI_VALID = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';
$id_kelas = (int)($_POST['id_kelas'] ?? $_GET['id_kelas'] ?? 0);
$back_url = "../index.php?menu=jadwal" . ($id_kelas > 0 ? "&kelas=" . $id_kelas : "");

// ==========================================================
// 1. SIMPAN
// ==========================================================
if ($aksi == "simpan") {
    $id_ta = (int)($_POST['id_tahun_ajaran'] ?? 0);
    $id_mapel = (int)($_POST['id_mapel'] ?? 0);
    $hari = trim($_POST['hari'] ?? '');
    $jam_ke = (int)($_POST['jam_ke'] ?? 0);

    if ($id_ta <= 0 || $id_kelas <= 0 || $id_mapel <= 0 || $jam_ke <= 0 || !in_array($hari, $HARI_VALID, true)) {
        setAlert('danger', 'Data jadwal tidak lengkap / tidak valid.');
        header("Location: $back_url");
        exit;
    }

    // Validasi kelas milik TA
    $q = $koneksi->prepare("SELECT nama_kelas FROM dt_kelas WHERE id_kelas = ? AND id_tahun_ajaran = ?");
    $q->bind_param("ii", $id_kelas, $id_ta);
    $q->execute();
    $d_kelas = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$d_kelas) {
        setAlert('danger', 'Kelas tidak ditemukan pada tahun ajaran ini.');
        header("Location: $back_url");
        exit;
    }

    // Validasi mapel
    $q = $koneksi->prepare("SELECT nama_mapel FROM dt_mapel WHERE id_mapel = ?");
    $q->bind_param("i", $id_mapel);
    $q->execute();
    $d_mapel = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$d_mapel) {
        setAlert('danger', 'Mata pelajaran tidak ditemukan.');
        header("Location: $back_url");
        exit;
    }

    // Ambil guru dari pengampu mapel kelas ini (boleh NULL bila belum ada)
    $id_guru = null;
    $q = $koneksi->prepare("SELECT id_guru FROM dt_pengampu_mapel WHERE id_kelas = ? AND id_mapel = ? AND id_tahun_ajaran = ? LIMIT 1");
    $q->bind_param("iii", $id_kelas, $id_mapel, $id_ta);
    $q->execute();
    $d_pengampu = $q->get_result()->fetch_assoc();
    $q->close();
    if ($d_pengampu) {
        $id_guru = (int)$d_pengampu['id_guru'];
    }

    $koneksi->begin_transaction();
    try {
        // Satu sel hanya satu entri -> hapus dulu
        $del = $koneksi->prepare("DELETE FROM dt_jadwal WHERE id_kelas = ? AND hari = ? AND jam_ke = ? AND id_tahun_ajaran = ?");
        $del->bind_param("isii", $id_kelas, $hari, $jam_ke, $id_ta);
        $del->execute();
        $del->close();

        $ins = $koneksi->prepare("INSERT INTO dt_jadwal (id_kelas, id_mapel, id_guru, hari, jam_ke, id_tahun_ajaran) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->bind_param("iiisii", $id_kelas, $id_mapel, $id_guru, $hari, $jam_ke, $id_ta);
        $ins->execute();
        $ins->close();
        $koneksi->commit();

        setAlert('success', 'Jadwal <b>' . htmlspecialchars($d_mapel['nama_mapel']) . '</b> (' . htmlspecialchars($hari) . ' jam ke-' . $jam_ke . ') untuk kelas <b>' . htmlspecialchars($d_kelas['nama_kelas']) . '</b> tersimpan.');
    } catch (Throwable $e) {
        $koneksi->rollback();
        setAlert('danger', 'Gagal menyimpan jadwal. Error: ' . $koneksi->error);
    }
    header("Location: $back_url");
    exit;
}

// ==========================================================
// 1b. SIMPAN PER TINGKAT (massal)
//     payload JSON: { "<hari>": { "<jam_ke>": { "<id_kelas>": "<id_mapel>" } } }
//     Mengganti seluruh jadwal kelas-kelas pada tingkat tsb di TA ini.
// ==========================================================
if ($aksi == "simpan_tingkat") {
    $id_ta = (int)($_POST['id_tahun_ajaran'] ?? 0);
    $tingkat = trim((string)($_POST['tingkat'] ?? ''));
    $payload = (string)($_POST['payload'] ?? '');
    $back_url = "../index.php?menu=jadwal" . ($tingkat !== '' ? "&tingkat=" . urlencode($tingkat) : "");

    $data = json_decode($payload, true);
    if ($id_ta <= 0 || $tingkat === '' || !is_array($data)) {
        setAlert('danger', 'Data jadwal tidak lengkap / tidak valid.');
        header("Location: $back_url");
        exit;
    }

    // Kelas valid pada TA + tingkat ini
    $kelas_valid = [];
    $q = $koneksi->prepare("SELECT id_kelas FROM dt_kelas WHERE id_tahun_ajaran = ? AND tingkat = ?");
    $q->bind_param("is", $id_ta, $tingkat);
    $q->execute();
    $res = $q->get_result();
    while ($r = $res->fetch_assoc()) {
        $kelas_valid[(int)$r['id_kelas']] = true;
    }
    $q->close();
    if (empty($kelas_valid)) {
        setAlert('danger', 'Tingkat <b>' . htmlspecialchars($tingkat) . '</b> tidak ditemukan pada tahun ajaran ini.');
        header("Location: $back_url");
        exit;
    }

    // Mapel valid
    $mapel_valid = [];
    $res = $koneksi->query("SELECT id_mapel FROM dt_mapel");
    while ($r = $res->fetch_assoc()) {
        $mapel_valid[(int)$r['id_mapel']] = true;
    }

    // Peta pengampu [id_kelas][id_mapel] = id_guru
    $pengampu_map = [];
    $q = $koneksi->prepare("SELECT id_kelas, id_mapel, id_guru FROM dt_pengampu_mapel WHERE id_tahun_ajaran = ?");
    $q->bind_param("i", $id_ta);
    $q->execute();
    $res = $q->get_result();
    while ($r = $res->fetch_assoc()) {
        $pengampu_map[(int)$r['id_kelas']][(int)$r['id_mapel']] = (int)$r['id_guru'];
    }
    $q->close();

    $kelas_ids = implode(',', array_map('intval', array_keys($kelas_valid)));
    $tersimpan = 0;

    $koneksi->begin_transaction();
    try {
        // Ganti total jadwal tingkat ini pada TA ini
        $koneksi->query("DELETE FROM dt_jadwal WHERE id_tahun_ajaran = $id_ta AND id_kelas IN ($kelas_ids)");

        $ins = $koneksi->prepare("INSERT INTO dt_jadwal (id_kelas, id_mapel, id_guru, hari, jam_ke, id_tahun_ajaran) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($data as $hari => $jamArr) {
            if (!in_array($hari, $HARI_VALID, true) || !is_array($jamArr)) {
                continue;
            }
            foreach ($jamArr as $jam_ke => $kelasArr) {
                $jam_ke = (int)$jam_ke;
                if ($jam_ke <= 0 || !is_array($kelasArr)) {
                    continue;
                }
                foreach ($kelasArr as $id_kelas_x => $id_mapel_x) {
                    $id_kelas_x = (int)$id_kelas_x;
                    $id_mapel_x = (int)$id_mapel_x;
                    if (!isset($kelas_valid[$id_kelas_x]) || $id_mapel_x <= 0 || !isset($mapel_valid[$id_mapel_x])) {
                        continue;
                    }
                    $id_guru_x = $pengampu_map[$id_kelas_x][$id_mapel_x] ?? null;
                    $ins->bind_param("iiisii", $id_kelas_x, $id_mapel_x, $id_guru_x, $hari, $jam_ke, $id_ta);
                    $ins->execute();
                    $tersimpan++;
                }
            }
        }
        $ins->close();
        $koneksi->commit();
        setAlert('success', 'Jadwal tingkat <b>' . htmlspecialchars($tingkat) . '</b> disimpan (' . $tersimpan . ' entri).');
    } catch (Throwable $e) {
        $koneksi->rollback();
        setAlert('danger', 'Gagal menyimpan jadwal. Error: ' . $koneksi->error);
    }

    header("Location: $back_url");
    exit;
}

// ==========================================================
// 2. HAPUS SATU ENTRI
// ==========================================================
if ($aksi == "hapus") {
    $id_jadwal = (int)($_GET['id'] ?? 0);
    if ($id_jadwal <= 0) {
        setAlert('danger', 'ID jadwal tidak valid.');
        header("Location: $back_url");
        exit;
    }

    // Cari kelasnya untuk redirect yang tepat
    $q = $koneksi->prepare("SELECT id_kelas FROM dt_jadwal WHERE id_jadwal = ?");
    $q->bind_param("i", $id_jadwal);
    $q->execute();
    $d = $q->get_result()->fetch_assoc();
    $q->close();
    if ($d) {
        $back_url = "../index.php?menu=jadwal&kelas=" . (int)$d['id_kelas'];
    }

    $stmt = $koneksi->prepare("DELETE FROM dt_jadwal WHERE id_jadwal = ?");
    $stmt->bind_param("i", $id_jadwal);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            setAlert('success', 'Entri jadwal berhasil dihapus.');
        } else {
            setAlert('warning', 'Entri jadwal tidak ditemukan.');
        }
    } else {
        setAlert('danger', 'Gagal menghapus jadwal. Error: ' . $koneksi->error);
    }
    header("Location: $back_url");
    exit;
}

// ==========================================================
// AKSI TIDAK VALID
// ==========================================================
setAlert('danger', 'Aksi tidak valid.');
header("Location: $back_url");
exit;
