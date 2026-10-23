<?php
/** @var array $profil @var array $wk @var string $judul @var string $active @var string $kelas_label */
$active = $active ?? '';
$wk_nama = trim((string)($wk['nama_guru'] ?? $wk['username'] ?? 'Wali Kelas'));
$kelas_label = trim((string)($kelas_label ?? ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= wk_esc($judul ?? 'Wali Kelas') ?> - <?= wk_esc($profil['nama_sekolah']) ?></title>
    <link rel="icon" href="<?= wk_esc(wk_logo_url($profil)) ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --wk-primary: #0f766e; }
        body { background: #f4f6f8; padding-bottom: 76px; -webkit-tap-highlight-color: transparent; }
        .wk-topbar {
            position: sticky; top: 0; z-index: 1030;
            background: linear-gradient(135deg, #0f766e, #14b8a6);
            color: #fff; padding: .7rem .9rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.12);
        }
        .wk-topbar .wk-logo { width: 38px; height: 38px; object-fit: contain; background: #fff; border-radius: 50%; padding: 3px; }
        .wk-card { border: 0; border-radius: .85rem; box-shadow: 0 1px 6px rgba(16,24,40,.06); }
        .kpi-value { font-size: 1.6rem; font-weight: 700; line-height: 1.1; }
        .jam-badge { width: 44px; height: 44px; border-radius: .7rem; background: #ecfdf5; color: #0f766e; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-direction: column; line-height: 1; flex: 0 0 auto; }
        .jam-badge small { font-size: .55rem; font-weight: 500; }
        .wk-bottomnav {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 1035;
            background: #fff; border-top: 1px solid #e9ecef;
            display: flex; padding: .35rem 0 calc(.35rem + env(safe-area-inset-bottom));
        }
        .wk-bottomnav a { flex: 1; text-align: center; color: #8a93a6; text-decoration: none; font-size: .72rem; }
        .wk-bottomnav a i { display: block; font-size: 1.25rem; }
        .wk-bottomnav a.active { color: var(--wk-primary); font-weight: 600; }
    </style>
</head>
<body>
<div class="wk-topbar">
    <div class="d-flex align-items-center gap-2">
        <img src="<?= wk_esc(wk_logo_url($profil)) ?>" alt="logo" class="wk-logo">
        <div class="flex-grow-1" style="min-width:0;">
            <div class="fw-bold text-truncate" style="font-size:.95rem;"><?= wk_esc($profil['nama_sekolah']) ?></div>
            <div class="small text-white-50 text-truncate">
                <?= wk_esc($wk_nama) ?><?php if ($kelas_label !== ''): ?> &middot; <?= wk_esc($kelas_label) ?><?php endif; ?>
            </div>
        </div>
        <a href="../portal/index.php" class="btn btn-sm btn-light" title="Menu Portal"><i class="bi bi-grid-1x2-fill"></i></a>
        <a href="../portal/logout.php" class="btn btn-sm btn-light" title="Keluar"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</div>
<div class="container py-3" style="max-width:760px;">
<?php $flash = wk_flash_get(); if ($flash): ?>
    <div class="alert alert-<?= wk_esc($flash['type']) ?> alert-dismissible fade show py-2 small" role="alert">
        <?= $flash['msg'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
