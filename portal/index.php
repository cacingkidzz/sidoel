<?php
require_once __DIR__ . '/_init.php';

if (!portal_logged_in()) {
    header('Location: login.php');
    exit;
}

$profil = portal_profil($koneksi);
$user = portal_user();
$roles = portal_roles($koneksi, $user);
$items = portal_menu_items($roles);
$flash = portal_flash_get();
$nama = trim((string)($user['nama_guru'] ?? $user['username'] ?? 'Guru'));
$nip = trim((string)($user['nip'] ?? ''));
$nuptk = trim((string)($user['nuptk'] ?? ''));
$level = trim((string)($user['level_user'] ?? 'Guru'));
$ta = $roles['ta'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Portal Guru - <?= portal_esc($profil['nama_sekolah']) ?></title>
    <link rel="icon" href="<?= portal_esc(portal_logo_url($profil)) ?>" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f8; min-height: 100vh; -webkit-tap-highlight-color: transparent; }
        .portal-hero { background: linear-gradient(135deg, #5b21b6, #0f766e); color: #fff; padding: 1.4rem 1rem 2.6rem; }
        .portal-logo { width: 44px; height: 44px; object-fit: contain; background: #fff; border-radius: 50%; padding: 4px; }
        .portal-wrap { max-width: 760px; margin-top: -1.8rem; }
        .portal-card { border: 0; border-radius: .9rem; box-shadow: 0 2px 12px rgba(16,24,40,.08); }
        .menu-card { border: 1px solid #e9ecef; border-radius: .9rem; background: #fff; height: 100%; transition: .15s ease; }
        .menu-card.is-active:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(16,24,40,.12); border-color: #cfd4dc; }
        .menu-icon { width: 52px; height: 52px; border-radius: .85rem; display: flex; align-items: center; justify-content: center; font-size: 1.45rem; flex: 0 0 auto; }
        .menu-card.is-disabled { background: #f8f9fa; }
        .menu-card.is-disabled .menu-icon { filter: grayscale(1); opacity: .55; }
        .avatar { width: 52px; height: 52px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #ede9fe; color: #6d28d9; font-size: 1.4rem; flex: 0 0 auto; }
        .badge-soft { background: #ede9fe; color: #5b21b6; }
    </style>
</head>
<body>
<div class="portal-hero">
    <div class="container" style="max-width:760px;">
        <div class="d-flex align-items-center gap-2">
            <img src="<?= portal_esc(portal_logo_url($profil)) ?>" alt="logo" class="portal-logo">
            <div class="flex-grow-1" style="min-width:0;">
                <div class="fw-bold text-truncate"><?= portal_esc($profil['nama_sekolah']) ?></div>
                <div class="small text-white-50">Portal Guru &amp; Wali Kelas</div>
            </div>
            <a href="logout.php" class="btn btn-sm btn-light" title="Keluar"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</div>

<div class="container portal-wrap pb-4" style="max-width:760px;">
    <?php if ($flash): ?>
        <div class="alert alert-<?= portal_esc($flash['type']) ?> alert-dismissible fade show py-2 small" role="alert">
            <?= portal_esc($flash['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card portal-card mb-3">
        <div class="card-body p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar"><i class="bi bi-person-badge-fill"></i></div>
                <div class="flex-grow-1" style="min-width:0;">
                    <div class="fw-bold text-truncate"><?= portal_esc($nama) ?></div>
                    <div class="small text-muted text-truncate">
                        <?php if ($nip !== ''): ?>NIP <?= portal_esc($nip); ?><?php elseif ($nuptk !== ''): ?>NUPTK <?= portal_esc($nuptk); ?><?php else: ?>@<?= portal_esc($user['username'] ?? ''); ?><?php endif; ?>
                        <?php if ($level !== 'Guru'): ?> &middot; <?= portal_esc($level); ?><?php endif; ?>
                    </div>
                </div>
                <span class="badge badge-soft rounded-pill">TA <?= portal_esc($ta['nama']) ?></span>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-end justify-content-between mb-2 px-1">
        <div>
            <h6 class="fw-bold mb-0">Pilih Portal</h6>
            <div class="small text-muted">Menu aktif sesuai tugas Anda.</div>
        </div>
        <span class="small text-muted"><?= portal_esc($user['username'] ?? '') ?></span>
    </div>

    <div class="row g-3">
        <?php foreach ($items as $item): ?>
            <div class="col-12 col-md-6">
                <div class="menu-card <?= $item['active'] ? 'is-active' : 'is-disabled' ?> p-3 d-flex flex-column">
                    <div class="d-flex align-items-start gap-3">
                        <div class="menu-icon" style="background:<?= portal_esc($item['soft']) ?>;color:<?= portal_esc($item['theme']) ?>;">
                            <i class="bi <?= portal_esc($item['icon']) ?>"></i>
                        </div>
                        <div class="flex-grow-1" style="min-width:0;">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div class="fw-bold"><?= portal_esc($item['title']) ?></div>
                                <?php if ($item['active']): ?>
                                    <span class="badge bg-success rounded-pill">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary rounded-pill">Nonaktif</span>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted mt-1"><?= portal_esc($item['desc']) ?></div>
                            <div class="small mt-2" style="color:<?= $item['active'] ? portal_esc($item['theme']) : '#6c757d' ?>;">
                                <i class="bi <?= $item['active'] ? 'bi-check2-circle' : 'bi-info-circle' ?> me-1"></i><?= portal_esc($item['meta']) ?>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <?php if ($item['active']): ?>
                            <a href="<?= portal_esc($item['url']) ?>" class="btn btn-sm w-100 fw-semibold text-white" style="background:<?= portal_esc($item['theme']) ?>;border-color:<?= portal_esc($item['theme']) ?>;">
                                Buka <?= portal_esc($item['title']) ?> <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn btn-sm btn-light w-100" disabled>Belum tersedia</button>
                            <div class="small text-muted text-center mt-2"><?= portal_esc($item['reason']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="text-center small text-muted mt-4">
        <i class="bi bi-info-circle me-1"></i>Butuh akses tambahan? Hubungi Admin sekolah.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
