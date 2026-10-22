<?php
require_once(__DIR__ . '/akses_guard.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

include("../koneksi.php");

$aksi = $_POST['aksi'] ?? '';

if ($aksi == "update") {
    $id = $_POST['id_sekolah'];
    $nama = $_POST['nama_sekolah'];
    $alamat = $_POST['alamat_sekolah'];
    $rek = $_POST['rek_sekolah'];
    $npsn = $_POST['NPSN'];
    $telp = $_POST['telp_sekolah'];
    $email = $_POST['email'];
    $web = $_POST['web'];
    $token = $_POST['token'];
    $domain = $_POST['domain'] ?? '';
    $lokasi = $_POST['lokasi'];

    // handle upload logo
    $logo = $_FILES['logo']['name'];
    if ($logo) {
        $ext = pathinfo($logo, PATHINFO_EXTENSION);
        $newName = "logo_" . time() . "." . $ext;
        $uploadPath = "../assets/img/logo/" . $newName;
        move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath);

        // update dengan logo
        $sql = $koneksi->prepare("UPDATE dt_data_sekolah SET nama_sekolah=?, alamat_sekolah=?, rek_sekolah=?, NPSN=?, telp_sekolah=?, email=?, web=?, token=?, domain=?, lokasi=?, logo=? WHERE id_sekolah=?");
        $sql->bind_param("sssssssssssi", $nama, $alamat, $rek, $npsn, $telp, $email, $web, $token, $domain, $lokasi, $newName, $id);
    } else {
        // update tanpa logo
        $sql = $koneksi->prepare("UPDATE dt_data_sekolah SET nama_sekolah=?, alamat_sekolah=?, rek_sekolah=?, NPSN=?, telp_sekolah=?, email=?, web=?, token=?, domain=?, lokasi=? WHERE id_sekolah=?");
        $sql->bind_param("ssssssssssi", $nama, $alamat, $rek, $npsn, $telp, $email, $web, $token, $domain, $lokasi, $id);
    }

    if ($sql->execute()) {
        header("Location: ../index.php?menu=data_sekolah&pesan=edit");
    } else {
        header("Location: ../index.php?menu=data_sekolah&pesat=error");
    }


    exit;
}
