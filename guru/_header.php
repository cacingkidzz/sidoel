<?php
/** @var array $profil @var array $guru @var string $judul @var string $active */
$active = $active ?? '';
$guru_nama = trim((string)($guru['nama_guru'] ?? $guru['username'] ?? 'Guru'));
$guru_sub = trim((string)($guru['nip'] ?? ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= guru_esc($judul ?? 'Guru') ?> - <?= guru_esc($profil['nama_sekolah']) ?></title>
    <link rel="icon" href="<?= guru_esc(guru_logo_url($profil)) ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --gr-primary: #6d28d9; }
        body { background: #f4f6f8; padding-bottom: 76px; -webkit-tap-highlight-color: transparent; }
        .gr-topbar {
            position: sticky; top: 0; z-index: 1030;
            background: linear-gradient(135deg, #5b21b6, #8b5cf6);
            color: #fff; padding: .7rem .9rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.12);
        }
        .gr-topbar .gr-logo { width: 38px; height: 38px; object-fit: contain; background: #fff; border-radius: 50%; padding: 3px; }
        .gr-card { border: 0; border-radius: .85rem; box-shadow: 0 1px 6px rgba(16,24,40,.06); }
        .kpi-value { font-size: 1.6rem; font-weight: 700; line-height: 1.1; }
        .jam-badge { width: 44px; height: 44px; border-radius: .7rem; background: #f5f3ff; color: #6d28d9; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-direction: column; line-height: 1; flex: 0 0 auto; }
        .jam-badge small { font-size: .55rem; font-weight: 500; }
        .kelas-dot { width: 8px; height: 8px; border-radius: 50%; background: #8b5cf6; display: inline-block; }
        .gr-bottomnav {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 1035;
            background: #fff; border-top: 1px solid #e9ecef;
            display: flex; padding: .35rem 0 calc(.35rem + env(safe-area-inset-bottom));
        }
        .gr-bottomnav a { flex: 1; text-align: center; color: #8a93a6; text-decoration: none; font-size: .72rem; }
        .gr-bottomnav a i { display: block; font-size: 1.25rem; }
        .gr-bottomnav a.active { color: var(--gr-primary); font-weight: 600; }
    </style>
</head>
<body>
<div class="gr-topbar">
    <div class="d-flex align-items-center gap-2">
        <img src="<?= guru_esc(guru_logo_url($profil)) ?>" alt="logo" class="gr-logo">
        <div class="flex-grow-1" style="min-width:0;">
            <div class="fw-bold text-truncate" style="font-size:.95rem;"><?= guru_esc($profil['nama_sekolah']) ?></div>
            <div class="small text-white-50 text-truncate">
                <?= guru_esc($guru_nama) ?><?php if ($guru_sub !== ''): ?> &middot; NIP <?= guru_esc($guru_sub) ?><?php endif; ?>
            </div>
        </div>
        <a href="../portal/index.php" class="btn btn-sm btn-light" title="Menu Portal"><i class="bi bi-grid-1x2-fill"></i></a>
        <a href="../portal/logout.php" class="btn btn-sm btn-light" title="Keluar"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</div>
<div class="container py-3" style="max-width:760px;">
<?php $flash = guru_flash_get(); if ($flash): ?>
    <div class="alert alert-<?= guru_esc($flash['type']) ?> alert-dismissible fade show py-2 small" role="alert">
        <?= $flash['msg'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
