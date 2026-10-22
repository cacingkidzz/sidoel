<?php
require_once(__DIR__ . '/akses_guard.php');
// ===============================================
// File: aksi/aksi_pengampu_mapel.php
// Penugasan Pengampu Mata Pelajaran (dt_pengampu_mapel)
// - simpan  : set guru pengampu per mapel untuk 1 kelas pada 1 TA (massal)
// - hapus_kelas : kosongkan seluruh pengampu kelas tsb pada TA tsb
// - hapus   : hapus satu baris pengampu
// ===============================================
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include("../koneksi.php");

function setAlert($type, $msg)
{
    $_SESSION['alert'] = ['type' => $type, 'msg' => $msg];
}

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';
$id_kelas = (int)($_POST['id_kelas'] ?? $_GET['id_kelas'] ?? 0);
$back_url = "../index.php?menu=pengampu_mapel" . ($id_kelas > 0 ? "&kelas=" . $id_kelas : "");

// ==========================================================
// 1. SIMPAN (massal: satu guru pengampu per mapel)
// ==========================================================
if ($aksi == "simpan") {
    $id_ta = (int)($_POST['id_tahun_ajaran'] ?? 0);
    $guru_map = isset($_POST['guru']) && is_array($_POST['guru']) ? $_POST['guru'] : [];

    if ($id_ta <= 0 || $id_kelas <= 0) {
        setAlert('danger', 'Data tidak lengkap. Pilih kelas terlebih dahulu.');
        header("Location: $back_url");
        exit;
    }

    // Validasi: kelas harus milik TA ini
    $q_kelas = $koneksi->prepare("SELECT nama_kelas FROM dt_kelas WHERE id_kelas = ? AND id_tahun_ajaran = ?");
    $q_kelas->bind_param("ii", $id_kelas, $id_ta);
    $q_kelas->execute();
    $d_kelas = $q_kelas->get_result()->fetch_assoc();
    if (!$d_kelas) {
        setAlert('danger', 'Kelas tidak ditemukan pada tahun ajaran ini.');
        header("Location: $back_url");
        exit;
    }

    // Daftar id valid (mapel & guru) untuk mencegah data sampah
    $mapel_valid = [];
    $q_m = $koneksi->query("SELECT id_mapel FROM dt_mapel");
    if ($q_m) {
        while ($m = $q_m->fetch_assoc()) {
            $mapel_valid[(int)$m['id_mapel']] = true;
        }
    }
    $guru_valid = [];
    $q_g = $koneksi->query("SELECT id_guru FROM dt_guru");
    if ($q_g) {
        while ($g = $q_g->fetch_assoc()) {
            $guru_valid[(int)$g['id_guru']] = true;
        }
    }

    $koneksi->begin_transaction();
    try {
        $del = $koneksi->prepare("DELETE FROM dt_pengampu_mapel WHERE id_mapel = ? AND id_kelas = ? AND id_tahun_ajaran = ?");
        $ins = $koneksi->prepare("INSERT INTO dt_pengampu_mapel (id_guru, id_mapel, id_kelas, id_tahun_ajaran) VALUES (?, ?, ?, ?)");

        $tersimpan = 0;
        foreach ($guru_map as $idm => $idg) {
            $idm = (int)$idm;
            $idg = (int)$idg;
            if (!isset($mapel_valid[$idm])) {
                continue;
            }
            // bersihkan penugasan lama untuk mapel+kelas+TA ini
            $del->bind_param("iii", $idm, $id_kelas, $id_ta);
            $del->execute();

            if ($idg > 0 && isset($guru_valid[$idg])) {
                $ins->bind_param("iiii", $idg, $idm, $id_kelas, $id_ta);
                $ins->execute();
                $tersimpan++;
            }
        }
        $del->close();
        $ins->close();
        $koneksi->commit();

        if ($tersimpan > 0) {
            setAlert('success', 'Pengampu mapel kelas <b>' . htmlspecialchars($d_kelas['nama_kelas']) . '</b> berhasil disimpan (<b>' . $tersimpan . '</b> mapel).');
        } else {
            setAlert('warning', 'Tidak ada guru pengampu yang dipilih untuk kelas <b>' . htmlspecialchars($d_kelas['nama_kelas']) . '</b>.');
        }
    } catch (Throwable $e) {
        $koneksi->rollback();
        setAlert('danger', 'Gagal menyimpan pengampu mapel. Error: ' . $koneksi->error);
    }
    header("Location: $back_url");
    exit;
}

// ==========================================================
// 2. HAPUS SEMUA PENGAMPU KELAS INI (TA aktif)
// ==========================================================
if ($aksi == "hapus_kelas") {
    $id_ta = (int)($_POST['id_tahun_ajaran'] ?? $_GET['id_tahun_ajaran'] ?? 0);
    if ($id_ta <= 0 || $id_kelas <= 0) {
        setAlert('danger', 'Data tidak lengkap.');
        header("Location: $back_url");
        exit;
    }

    $stmt = $koneksi->prepare("DELETE FROM dt_pengampu_mapel WHERE id_kelas = ? AND id_tahun_ajaran = ?");
    $stmt->bind_param("ii", $id_kelas, $id_ta);
    if ($stmt->execute()) {
        setAlert('success', 'Seluruh pengampu pada kelas terpilih berhasil dikosongkan (<b>' . (int)$stmt->affected_rows . '</b> baris).');
    } else {
        setAlert('danger', 'Gagal mengosongkan pengampu. Error: ' . $koneksi->error);
    }
    header("Location: $back_url");
    exit;
}

// ==========================================================
// 3. HAPUS SATU BARIS PENGAMPU
// ==========================================================
if ($aksi == "hapus") {
    $id_pengampu = (int)($_GET['id'] ?? 0);
    $back_here = "../index.php?menu=pengampu_mapel";
    if ($id_pengampu <= 0) {
        setAlert('danger', 'ID pengampu tidak valid.');
        header("Location: $back_here");
        exit;
    }

    $q = $koneksi->prepare("SELECT id_kelas FROM dt_pengampu_mapel WHERE id_pengampu = ?");
    $q->bind_param("i", $id_pengampu);
    $q->execute();
    $d = $q->get_result()->fetch_assoc();
    if ($d) {
        $back_here = "../index.php?menu=pengampu_mapel&kelas=" . (int)$d['id_kelas'];
    }

    $stmt = $koneksi->prepare("DELETE FROM dt_pengampu_mapel WHERE id_pengampu = ?");
    $stmt->bind_param("i", $id_pengampu);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            setAlert('success', 'Penugasan pengampu berhasil dihapus.');
        } else {
            setAlert('warning', 'Data pengampu tidak ditemukan.');
        }
    } else {
        setAlert('danger', 'Gagal menghapus pengampu. Error: ' . $koneksi->error);
    }
    header("Location: $back_here");
    exit;
}

// ==========================================================
// 4. SIMPAN PER GURU (pilih guru + mapel, centang kelas)
// ==========================================================
if ($aksi == "simpan_guru") {
    $id_ta = (int)($_POST['id_tahun_ajaran'] ?? 0);
    $id_guru = (int)($_POST['id_guru'] ?? 0);
    $id_mapel = (int)($_POST['id_mapel'] ?? 0);
    $kelas_ids = isset($_POST['kelas']) && is_array($_POST['kelas']) ? array_map('intval', $_POST['kelas']) : [];
    $back_guru = "../index.php?menu=pengampu_mapel&mode=guru";

    if ($id_ta <= 0 || $id_guru <= 0 || $id_mapel <= 0) {
        setAlert('danger', 'Data tidak lengkap. Pilih guru dan mata pelajaran terlebih dahulu.');
        header("Location: $back_guru");
        exit;
    }

    // Validasi guru & mapel
    $d_guru = null;
    $q = $koneksi->prepare("SELECT nama_guru FROM dt_guru WHERE id_guru = ?");
    $q->bind_param("i", $id_guru);
    $q->execute();
    $d_guru = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$d_guru) {
        setAlert('danger', 'Guru tidak ditemukan.');
        header("Location: $back_guru");
        exit;
    }

    $d_mapel = null;
    $q = $koneksi->prepare("SELECT nama_mapel FROM dt_mapel WHERE id_mapel = ?");
    $q->bind_param("i", $id_mapel);
    $q->execute();
    $d_mapel = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$d_mapel) {
        setAlert('danger', 'Mata pelajaran tidak ditemukan.');
        header("Location: $back_guru");
        exit;
    }

    // Kelas valid pada TA ini
    $kelas_valid_ta = [];
    $q = $koneksi->prepare("SELECT id_kelas FROM dt_kelas WHERE id_tahun_ajaran = ?");
    $q->bind_param("i", $id_ta);
    $q->execute();
    $r = $q->get_result();
    while ($kk = $r->fetch_assoc()) {
        $kelas_valid_ta[(int)$kk['id_kelas']] = true;
    }
    $q->close();

    // Hanya proses kelas yang valid & milik TA ini, buang duplikat
    $kelas_target = [];
    foreach ($kelas_ids as $kid) {
        if (isset($kelas_valid_ta[$kid]) && !isset($kelas_target[$kid])) {
            $kelas_target[$kid] = true;
        }
    }

    $koneksi->begin_transaction();
    try {
        // hapus semua penugasan guru ini untuk mapel+TA (dasar: keadaan baru akan di-set ulang)
        $delG = $koneksi->prepare("DELETE FROM dt_pengampu_mapel WHERE id_guru = ? AND id_mapel = ? AND id_tahun_ajaran = ?");
        $delG->bind_param("iii", $id_guru, $id_mapel, $id_ta);
        $delG->execute();
        $delG->close();

        $delC = $koneksi->prepare("DELETE FROM dt_pengampu_mapel WHERE id_mapel = ? AND id_kelas = ? AND id_tahun_ajaran = ?");
        $ins = $koneksi->prepare("INSERT IGNORE INTO dt_pengampu_mapel (id_guru, id_mapel, id_kelas, id_tahun_ajaran) VALUES (?, ?, ?, ?)");

        $tersimpan = 0;
        foreach ($kelas_target as $kid => $_) {
            // rebut dari guru lain bila kelas ini sudah punya pengampu mapel tsb
            $delC->bind_param("iii", $id_mapel, $kid, $id_ta);
            $delC->execute();

            $ins->bind_param("iiii", $id_guru, $id_mapel, $kid, $id_ta);
            $ins->execute();
            $tersimpan += $ins->affected_rows;
        }
        $delC->close();
        $ins->close();
        $koneksi->commit();

        $msg = 'Pengampu <b>' . htmlspecialchars($d_mapel['nama_mapel']) . '</b> oleh <b>' . htmlspecialchars($d_guru['nama_guru']) . '</b> disimpan pada <b>' . $tersimpan . '</b> kelas.';
        if ($tersimpan === 0) {
            setAlert('warning', 'Tidak ada kelas dipilih. Penugasan <b>' . htmlspecialchars($d_mapel['nama_mapel']) . '</b> oleh <b>' . htmlspecialchars($d_guru['nama_guru']) . '</b> dikosongkan.');
        } else {
            setAlert('success', $msg);
        }
    } catch (Throwable $e) {
        $koneksi->rollback();
        setAlert('danger', 'Gagal menyimpan pengampu. Error: ' . $koneksi->error);
    }
    header("Location: $back_guru");
    exit;
}

// ==========================================================
// 5. SIMPAN PER GURU TOTAL (semua mapel referensi guru sekaligus)
// ==========================================================
if ($aksi == "simpan_guru_total") {
    $id_ta = (int)($_POST['id_tahun_ajaran'] ?? 0);
    $id_guru = (int)($_POST['id_guru'] ?? 0);
    $back_guru = "../index.php?menu=pengampu_mapel&mode=guru" . ($id_guru > 0 ? "&guru=" . $id_guru : "");

    if ($id_ta <= 0 || $id_guru <= 0) {
        setAlert('danger', 'Data tidak lengkap. Pilih guru terlebih dahulu.');
        header("Location: $back_guru");
        exit;
    }

    $q = $koneksi->prepare("SELECT nama_guru FROM dt_guru WHERE id_guru = ?");
    $q->bind_param("i", $id_guru);
    $q->execute();
    $d_guru = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$d_guru) {
        setAlert('danger', 'Guru tidak ditemukan.');
        header("Location: $back_guru");
        exit;
    }

    // Mapel referensi guru ini (hanya mapel inilah yang boleh ditugaskan)
    $mapel_guru_valid = [];
    $q = $koneksi->prepare("SELECT id_mapel FROM dt_guru_mapel WHERE id_guru = ?");
    $q->bind_param("i", $id_guru);
    $q->execute();
    $r = $q->get_result();
    while ($mg = $r->fetch_assoc()) {
        $mapel_guru_valid[(int)$mg['id_mapel']] = true;
    }
    $q->close();

    // Kelas valid pada TA ini
    $kelas_valid_ta = [];
    $q = $koneksi->prepare("SELECT id_kelas FROM dt_kelas WHERE id_tahun_ajaran = ?");
    $q->bind_param("i", $id_ta);
    $q->execute();
    $r = $q->get_result();
    while ($kk = $r->fetch_assoc()) {
        $kelas_valid_ta[(int)$kk['id_kelas']] = true;
    }
    $q->close();

    // Kumpulkan target: mapel -> [id_kelas => true]
    $pengampu_in = isset($_POST['pengampu']) && is_array($_POST['pengampu']) ? $_POST['pengampu'] : [];
    $target = [];
    foreach ($pengampu_in as $idm => $kelas_arr) {
        $idm = (int)$idm;
        if (!isset($mapel_guru_valid[$idm]) || !is_array($kelas_arr)) {
            continue;
        }
        foreach ($kelas_arr as $kid) {
            $kid = (int)$kid;
            if (isset($kelas_valid_ta[$kid])) {
                $target[$idm][$kid] = true;
            }
        }
    }

    $koneksi->begin_transaction();
    try {
        // Reset seluruh penugasan guru ini pada TA (keadaan baru di-set ulang)
        $delG = $koneksi->prepare("DELETE FROM dt_pengampu_mapel WHERE id_guru = ? AND id_tahun_ajaran = ?");
        $delG->bind_param("ii", $id_guru, $id_ta);
        $delG->execute();
        $delG->close();

        $delC = $koneksi->prepare("DELETE FROM dt_pengampu_mapel WHERE id_mapel = ? AND id_kelas = ? AND id_tahun_ajaran = ?");
        $ins = $koneksi->prepare("INSERT IGNORE INTO dt_pengampu_mapel (id_guru, id_mapel, id_kelas, id_tahun_ajaran) VALUES (?, ?, ?, ?)");

        $tersimpan = 0;
        foreach ($target as $idm => $arr) {
            foreach ($arr as $kid => $_) {
                // Rebut dari guru lain bila kelas ini sudah punya pengampu mapel tsb
                $delC->bind_param("iii", $idm, $kid, $id_ta);
                $delC->execute();

                $ins->bind_param("iiii", $id_guru, $idm, $kid, $id_ta);
                $ins->execute();
                $tersimpan += $ins->affected_rows;
            }
        }
        $delC->close();
        $ins->close();
        $koneksi->commit();

        if ($tersimpan > 0) {
            setAlert('success', 'Pengampu <b>' . htmlspecialchars($d_guru['nama_guru']) . '</b> disimpan pada <b>' . $tersimpan . '</b> kelas.');
        } else {
            setAlert('warning', 'Tidak ada kelas dipilih. Seluruh penugasan <b>' . htmlspecialchars($d_guru['nama_guru']) . '</b> pada TA ini dikosongkan.');
        }
    } catch (Throwable $e) {
        $koneksi->rollback();
        setAlert('danger', 'Gagal menyimpan pengampu. Error: ' . $koneksi->error);
    }
    header("Location: $back_guru");
    exit;
}

// ==========================================================
// AKSI TIDAK VALID
// ==========================================================
setAlert('danger', 'Aksi tidak valid.');
header("Location: $back_url");
exit;
