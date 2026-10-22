<?php
/** @var array $profil @var array $sek @var string $judul @var string $active */
$active = $active ?? '';
$sek_nama = trim((string)($sek['nama_siswa'] ?? ''));
$sek_kelas_label = trim(($sek['tingkat'] ?? '') . ' ' . ($sek['nama_kelas'] ?? ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= sek_esc($judul ?? 'Sekretaris') ?> - <?= sek_esc($profil['nama_sekolah']) ?></title>
    <link rel="icon" href="<?= sek_esc(sek_logo_url($profil)) ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --sek-primary: #0d6efd; }
        body { background: #f4f6fb; padding-bottom: 76px; -webkit-tap-highlight-color: transparent; }
        .sek-topbar {
            position: sticky; top: 0; z-index: 1030;
            background: linear-gradient(135deg, #0d6efd, #3d8bfd);
            color: #fff; padding: .7rem .9rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.12);
        }
        .sek-topbar .sek-logo { width: 38px; height: 38px; object-fit: contain; background: #fff; border-radius: 50%; padding: 3px; }
        .sek-card { border: 0; border-radius: .85rem; box-shadow: 0 1px 6px rgba(16,24,40,.06); }
        .jam-badge { width: 46px; height: 46px; border-radius: .7rem; background: #eef4ff; color: #0d6efd; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-direction: column; line-height: 1; }
        .jam-badge small { font-size: .58rem; font-weight: 500; }
        .sek-bottomnav {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 1035;
            background: #fff; border-top: 1px solid #e9ecef;
            display: flex; padding: .35rem 0 calc(.35rem + env(safe-area-inset-bottom));
        }
        .sek-bottomnav a { flex: 1; text-align: center; color: #8a93a6; text-decoration: none; font-size: .72rem; }
        .sek-bottomnav a i { display: block; font-size: 1.25rem; }
        .sek-bottomnav a.active { color: var(--sek-primary); font-weight: 600; }
        .abs-seg .btn { --bs-btn-padding-y: .15rem; --bs-btn-padding-x: .55rem; --bs-btn-font-size: .78rem; }
        .sticky-save { position: sticky; bottom: 72px; z-index: 1020; }
    </style>
</head>
<body>
<div class="sek-topbar">
    <div class="d-flex align-items-center gap-2">
        <img src="<?= sek_esc(sek_logo_url($profil)) ?>" alt="logo" class="sek-logo">
        <div class="flex-grow-1" style="min-width:0;">
            <div class="fw-bold text-truncate" style="font-size:.95rem;"><?= sek_esc($profil['nama_sekolah']) ?></div>
            <div class="small text-white-50 text-truncate"><?= sek_esc($sek_nama) ?><?php if ($sek_kelas_label !== ''): ?> &middot; <?= sek_esc($sek_kelas_label) ?><?php endif; ?></div>
        </div>
        <a href="logout.php" class="btn btn-sm btn-light" title="Keluar"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</div>
<div class="container py-3" style="max-width:640px;">
<?php $flash = sek_flash_get(); if ($flash): ?>
    <div class="alert alert-<?= sek_esc($flash['type']) ?> alert-dismissible fade show py-2 small" role="alert">
        <?= $flash['msg'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
