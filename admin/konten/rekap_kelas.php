<?php
// Inisialisasi mandiri (halaman ini bisa dipanggil langsung / dari parent).
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($koneksi)) {
    include(__DIR__ . '/../koneksi.php');
}
if (!isset($id_ta_aktif)) {
    $id_ta_aktif = 0;
    $nama_ta_sesi = $_SESSION['tahun_ajaran_aktif'] ?? '';
    if ($nama_ta_sesi !== '' && isset($koneksi) && !$koneksi->connect_error) {
        $q_ta = $koneksi->prepare("SELECT id_tahun_ajaran FROM dt_tahun_ajar WHERE tahun_ajaran = ? LIMIT 1");
        if ($q_ta) {
            $q_ta->bind_param("s", $nama_ta_sesi);
            $q_ta->execute();
            $r_ta = $q_ta->get_result()->fetch_assoc();
            if ($r_ta) {
                $id_ta_aktif = (int)$r_ta['id_tahun_ajaran'];
            }
            $q_ta->close();
        }
    }
}
?>
 <main class="app-main">
     <!--begin::App Content Header-->
     <div class="app-content-header">
         <!--begin::Container-->
         <div class="container-fluid">
             <!--begin::Row-->
             <div class="row">
                 <div class="col-sm-6">
                     <h3 class="mb-0">Rekap Siswa per Rombel</h3>
                 </div>
                 <div class="col-sm-6">
                     <ol class="breadcrumb float-sm-end">
                         <li class="breadcrumb-item"><a href="index.php?menu=beranda">Home</a></li>
                         <li class="breadcrumb-item active" aria-current="page">Rekap Siswa per Rombel</li>
                     </ol>
                 </div>
             </div>
             <!--end::Row-->
         </div>
         <!--end::Container-->
     </div>
     <!--end::App Content Header-->
     <!--begin::App Content-->
     <div class="app-content">
         <!--begin::Container-->
         <div class="container-fluid">
             <div class="row">
                 <div class="col-12">
                     <?php if (isset($_SESSION['alert'])): ?>
                         <div class="alert alert-<?= $_SESSION['alert']['type']; ?> alert-dismissible fade show" role="alert">
                             <?= $_SESSION['alert']['msg']; ?>
                             <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                         </div>
                         <?php unset($_SESSION['alert']); ?>
                     <?php endif; ?>
                 </div>
             </div>
             <!--begin::Row-->
             <div class="row">
                 <div class="row">
                     <?php
                        $summary_rombel = [];
                        $default_rombels = ['X', 'XI', 'XII', 'Lainnya']; // Rombel yang ingin diprioritaskan

                        if (isset($koneksi) && $id_ta_aktif > 0) {
                            // Query untuk Menghitung Jumlah Setiap Rombel
                            $q_rombel = mysqli_query($koneksi, "
                        SELECT
                            COALESCE(CONCAT(k.tingkat, ' - ', k.nama_kelas), NULLIF(s.rombel, ''), 'BELUM ADA ROMBEL') AS rombel_grup,
                            COUNT(DISTINCT s.id_siswa) AS total_rombel
                        FROM dt_siswa s
                        LEFT JOIN dt_kelas_siswa ks ON ks.id_siswa = s.id_siswa AND ks.id_tahun_ajaran = '$id_ta_aktif'
                        LEFT JOIN dt_kelas k ON k.id_kelas = ks.id_kelas
                        WHERE (s.id_tahun_ajaran = '$id_ta_aktif' OR ks.id_kelas_siswa IS NOT NULL)
                        GROUP BY rombel_grup
                        ORDER BY rombel_grup ASC
                    ");

                            if ($q_rombel) {
                                while ($d_rombel = mysqli_fetch_assoc($q_rombel)) {
                                    $summary_rombel[htmlspecialchars($d_rombel['rombel_grup'])] = $d_rombel['total_rombel'];
                                }
                            }
                            $all_rombels = array_unique(array_merge($default_rombels, array_keys($summary_rombel)));
                        } else {
                            $all_rombels = $default_rombels;
                        }
                        ?>

                     <div class="col-12">
                         <div class="card card-outline card-primary shadow mb-3">
                             <div class="card-header p-2 bg-light">
                                 <h6 class="card-title mb-0 fw-bold"><i class="bi bi-house-door-fill me-1"></i> Rincian Jumlah Siswa Berdasarkan Rombel/Kelas</h6>
                             </div>
                             <div class="card-body p-3">
                                 <?php
                                    // --- PERSIAPAN DATA: Kelompokkan Rombel berdasarkan Tingkat ---
                                    $colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'dark', 'light'];
                                    $color_index = 0;
                                    $grouped_rombels = [];

                                    foreach ($all_rombels as $rombel_name) {
                                        $jumlah = $summary_rombel[$rombel_name] ?? 0;

                                        // Hanya tampilkan Rombel yang memiliki siswa
                                        if ($jumlah > 0) {
                                            // Asumsi: Tingkat (Grade) adalah kata/segmen pertama dari nama Rombel.
                                            $parts = explode(' ', trim($rombel_name), 2);
                                            $tingkat = strtoupper($parts[0]);

                                            // Tambahkan ke array pengelompokan
                                            if (!isset($grouped_rombels[$tingkat])) {
                                                $grouped_rombels[$tingkat] = [];
                                            }
                                            $grouped_rombels[$tingkat][$rombel_name] = $jumlah;
                                        }
                                    }

                                    // Urutkan Tingkat secara spesifik untuk kolom (X, XI, XII)
                                    $sorted_tingkats = ['X', 'XI', 'XII']; // Anda bisa menambahkan Tingkat lain jika ada
                                    $final_groups = [];

                                    foreach ($sorted_tingkats as $tingkat) {
                                        if (isset($grouped_rombels[$tingkat])) {
                                            $final_groups[$tingkat] = $grouped_rombels[$tingkat];
                                        }
                                    }
                                    // Jika ada tingkat lain yang belum terdaftar (misal: "XIII"), tambahkan di akhir.
                                    $final_groups = array_merge($final_groups, array_diff_key($grouped_rombels, array_flip($sorted_tingkats)));

                                    // Tentukan lebar kolom Bootstrap: col-md-4 untuk 3 kolom
                                    $col_class = 'col-md-' . (12 / max(1, count($final_groups)));
                                    ?>

                                 <?php if (!empty($final_groups)): ?>
                                     <div class="row g-3">
                                         <?php
                                            // --- TAMPILKAN DATA DALAM BENTUK KOLOM PER TINGKAT ---
                                            foreach ($final_groups as $tingkat => $rombels_in_tingkat):
                                                $total_tingkat = array_sum($rombels_in_tingkat);
                                                $current_color = $colors[$color_index % count($colors)];
                                                $border_class = 'border-' . $current_color;
                                                $text_color = 'text-' . $current_color;
                                                $bg_color_light = 'bg-' . $current_color . ' bg-opacity-10'; // Warna background terang
                                            ?>
                                             <div class="<?= $col_class ?>">
                                                 <div class="card border <?= $border_class ?> h-100 shadow-sm">
                                                     <div class="card-header <?= $bg_color_light ?> border-bottom <?= $border_class ?> text-center">
                                                         <h5 class="mb-0 fw-bold text-uppercase <?= $text_color ?>">
                                                             <i class="bi bi-bookmarks-fill me-2"></i> Kelas <?= $tingkat ?>
                                                         </h5>
                                                         <small class="text-muted">Total Siswa: <b><?= number_format($total_tingkat) ?></b></small>
                                                     </div>
                                                     <div class="card-body p-2">
                                                         <ul class="list-group list-group-flush">
                                                             <?php
                                                                // Tampilkan daftar rombel di tingkat ini
                                                                foreach ($rombels_in_tingkat as $rombel_name => $jumlah):
                                                                    $rombel_label = strtoupper($rombel_name);
                                                                ?>
                                                                 <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                     <span class="fw-normal"><?= $rombel_label ?></span>
                                                                     <span class="badge bg-secondary rounded-pill"><?= number_format($jumlah) ?></span>
                                                                 </li>
                                                             <?php endforeach; ?>
                                                         </ul>
                                                     </div>
                                                 </div>
                                             </div>
                                         <?php
                                                $color_index++; // Ganti warna untuk tingkat berikutnya
                                            endforeach;
                                            ?>
                                     </div>

                                     <div class="mt-3 pt-3 border-top text-end">
                                         <h4 class="mb-0">
                                             <span class="badge bg-dark p-2">
                                                 TOTAL SISWA KESELURUHAN: <b><?= number_format(array_sum(array_map('array_sum', $grouped_rombels))) ?></b>
                                             </span>
                                         </h4>
                                     </div>

                                 <?php else: ?>
                                     <div class="alert alert-info text-center">
                                         Tidak ada data Rombel yang memiliki siswa saat ini (semua jumlah = 0).
                                     </div>
                                 <?php endif; ?>
                             </div>
                         </div>
                     </div>
                 </div>
             </div>
             <!--end::Row-->
         </div>
         <!--end::Container-->
     </div>
     <!--end::App Content-->
 </main>