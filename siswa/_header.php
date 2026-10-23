<?php
/** @var array $profil @var array $siswa @var string $judul @var string $active @var string $kelas_label */
$active = $active ?? '';
$siswa_nama = trim((string)($siswa['nama'] ?? 'Siswa'));
$kelas_label = trim((string)($kelas_label ?? ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= siswa_esc($judul ?? 'Siswa') ?> - <?= siswa_esc($profil['nama_sekolah']) ?></title>
    <link rel="icon" href="<?= siswa_esc(siswa_logo_url($profil)) ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --ss-primary: #1d4ed8; --ss-soft: #eff6ff; }
        body { background: #f4f6f8; padding-bottom: 76px; -webkit-tap-highlight-color: transparent; }
        .ss-topbar {
            position: sticky; top: 0; z-index: 1030;
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: #fff; padding: .7rem .9rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.12);
        }
        .ss-topbar .ss-logo { width: 38px; height: 38px; object-fit: contain; background: #fff; border-radius: 50%; padding: 3px; }
        .ss-card { border: 0; border-radius: .85rem; box-shadow: 0 1px 6px rgba(16,24,40,.06); }
        .ss-photo { width: 84px; height: 84px; object-fit: cover; border-radius: .8rem; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
        .ss-label { font-size: .68rem; color: #8a93a6; text-transform: uppercase; letter-spacing: .02em; }
        .ss-value { font-size: .86rem; font-weight: 600; word-break: break-word; }
        .ss-section-title { font-size: .82rem; font-weight: 700; color: var(--ss-primary); }
        .ss-bottomnav {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 1035;
            background: #fff; border-top: 1px solid #e9ecef;
            display: flex; padding: .35rem 0 calc(.35rem + env(safe-area-inset-bottom));
        }
        .ss-bottomnav a { flex: 1; text-align: center; color: #8a93a6; text-decoration: none; font-size: .72rem; }
        .ss-bottomnav a i { display: block; font-size: 1.25rem; }
        .ss-bottomnav a.active { color: var(--ss-primary); font-weight: 600; }
        .form-label { font-size: .78rem; font-weight: 600; }
        .form-control, .form-select { font-size: .85rem; }
    </style>
</head>
<body>
<div class="ss-topbar">
    <div class="d-flex align-items-center gap-2">
        <img src="<?= siswa_esc(siswa_logo_url($profil)) ?>" alt="logo" class="ss-logo">
        <div class="flex-grow-1" style="min-width:0;">
            <div class="fw-bold text-truncate" style="font-size:.95rem;"><?= siswa_esc($profil['nama_sekolah']) ?></div>
            <div class="small text-white-50 text-truncate">
                <?= siswa_esc($siswa_nama) ?><?php if ($kelas_label !== ''): ?> &middot; <?= siswa_esc($kelas_label) ?><?php endif; ?>
            </div>
        </div>
        <a href="logout.php" class="btn btn-sm btn-light" title="Keluar"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</div>
<div class="container py-3" style="max-width:760px;">
<?php $flash = siswa_flash_get(); if ($flash): ?>
    <div class="alert alert-<?= siswa_esc($flash['type']) ?> alert-dismissible fade show py-2 small" role="alert">
        <?= siswa_esc($flash['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
