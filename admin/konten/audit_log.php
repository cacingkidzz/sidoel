<?php
// Halaman Log Aktivitas (Audit Log) — Administrasi Sistem.
// Read-only: menampilkan riwayat aksi siapa/kapan/apa + filter.
include("koneksi.php");

// --- Filter ---
$f_user   = trim($_GET['f_user'] ?? '');
$f_aksi   = trim($_GET['f_aksi'] ?? '');
$f_status = trim($_GET['f_status'] ?? '');
$f_dari   = trim($_GET['f_dari'] ?? '');
$f_sampai = trim($_GET['f_sampai'] ?? '');

// Daftar Aksi unik untuk dropdown filter
$aksi_options = [];
$res_aksi = $koneksi->query("SELECT DISTINCT aksi FROM dt_audit_log WHERE aksi IS NOT NULL AND aksi <> '' ORDER BY aksi");
if ($res_aksi) {
    while ($r = $res_aksi->fetch_assoc()) {
        $aksi_options[] = $r['aksi'];
    }
}

// --- Bangun query dengan prepared statement ---
$where  = [];
$params = [];
$types  = '';

if ($f_user !== '') {
    $where[]  = "(username LIKE ? OR level_user LIKE ?)";
    $like     = '%' . $f_user . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if ($f_aksi !== '') {
    $where[]  = "aksi = ?";
    $params[] = $f_aksi;
    $types   .= 's';
}
if ($f_status !== '') {
    $where[]  = "status = ?";
    $params[] = $f_status;
    $types   .= 's';
}
if ($f_dari !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_dari)) {
    $where[]  = "DATE(created_at) >= ?";
    $params[] = $f_dari;
    $types   .= 's';
}
if ($f_sampai !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_sampai)) {
    $where[]  = "DATE(created_at) <= ?";
    $params[] = $f_sampai;
    $types   .= 's';
}

$sql = "SELECT * FROM dt_audit_log";
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY id_log DESC LIMIT 1000";

$logs = [];
$stmt = $koneksi->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();
}

// Statistik ringkas
$total_log = (int) ($koneksi->query("SELECT COUNT(*) AS n FROM dt_audit_log")->fetch_assoc()['n'] ?? 0);
$total_denied = (int) ($koneksi->query("SELECT COUNT(*) AS n FROM dt_audit_log WHERE status='denied'")->fetch_assoc()['n'] ?? 0);
$total_gagal = (int) ($koneksi->query("SELECT COUNT(*) AS n FROM dt_audit_log WHERE status='gagal'")->fetch_assoc()['n'] ?? 0);

function audit_badge($status)
{
    switch ($status) {
        case 'ok':
            return '<span class="badge bg-success">OK</span>';
        case 'denied':
            return '<span class="badge bg-warning text-dark">Ditolak</span>';
        case 'gagal':
            return '<span class="badge bg-danger">Gagal</span>';
        default:
            return '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Log Aktivitas</h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Log Aktivitas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?= $_SESSION['alert']['type']; ?> alert-dismissible fade show" role="alert">
                    <?= $_SESSION['alert']['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>

            <div class="row mb-3">
                <div class="col-md-3 col-6">
                    <div class="small-box text-bg-primary">
                        <div class="inner"><h3><?= number_format($total_log, 0, ',', '.') ?></h3><p>Total Aktivitas</p></div>
                        <i class="small-box-icon bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box text-bg-warning">
                        <div class="inner"><h3><?= number_format($total_denied, 0, ',', '.') ?></h3><p>Akses Ditolak</p></div>
                        <i class="small-box-icon bi bi-shield-slash"></i>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box text-bg-danger">
                        <div class="inner"><h3><?= number_format($total_gagal, 0, ',', '.') ?></h3><p>Gagal</p></div>
                        <i class="small-box-icon bi bi-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="small-box text-bg-secondary">
                        <div class="inner"><h3><?= number_format(count($logs), 0, ',', '.') ?></h3><p>Ditampilkan</p></div>
                        <i class="small-box-icon bi bi-list-ul"></i>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-funnel me-1"></i> Filter</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="index.php" class="row g-2 align-items-end">
                        <input type="hidden" name="menu" value="audit_log">
                        <div class="col-md-3">
                            <label class="form-label mb-1 small">User / Level</label>
                            <input type="text" class="form-control form-control-sm" name="f_user" value="<?= htmlspecialchars($f_user) ?>" placeholder="mis. admin / Kurikulum">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1 small">Aksi</label>
                            <select class="form-select form-select-sm" name="f_aksi">
                                <option value="">Semua aksi</option>
                                <?php foreach ($aksi_options as $ao): ?>
                                    <option value="<?= htmlspecialchars($ao) ?>" <?= $f_aksi === $ao ? 'selected' : '' ?>><?= htmlspecialchars($ao) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-1 small">Status</label>
                            <select class="form-select form-select-sm" name="f_status">
                                <option value="">Semua</option>
                                <option value="ok" <?= $f_status === 'ok' ? 'selected' : '' ?>>OK</option>
                                <option value="denied" <?= $f_status === 'denied' ? 'selected' : '' ?>>Ditolak</option>
                                <option value="gagal" <?= $f_status === 'gagal' ? 'selected' : '' ?>>Gagal</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-1 small">Dari Tanggal</label>
                            <input type="date" class="form-control form-control-sm" name="f_dari" value="<?= htmlspecialchars($f_dari) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-1 small">Sampai Tanggal</label>
                            <input type="date" class="form-control form-control-sm" name="f_sampai" value="<?= htmlspecialchars($f_sampai) ?>">
                        </div>
                        <div class="col-12 d-flex gap-2 mt-2">
                            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i> Terapkan</button>
                            <a href="index.php?menu=audit_log" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card card-outline card-primary">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-list-ul me-1"></i> Riwayat Aktivitas</h5>
                    <form method="post" action="aksi/aksi_audit.php" class="m-0" onsubmit="return confirm('Hapus SEMUA log aktivitas? Tindakan ini tidak dapat dibatalkan.');">
                        <input type="hidden" name="aksi" value="hapus_semua">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i> Bersihkan Log</button>
                    </form>
                </div>
                <div class="card-body table-responsive">
                    <table id="myTable" class="table table-sm table-striped table-hover align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width:130px">Waktu</th>
                                <th>User</th>
                                <th>Aksi</th>
                                <th>Operasi</th>
                                <th>Menu</th>
                                <th>Keterangan</th>
                                <th style="width:90px">Status</th>
                                <th style="width:110px">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$logs): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data log.</td></tr>
                            <?php else: foreach ($logs as $r): ?>
                                <tr>
                                    <td class="text-nowrap"><?= $r['created_at'] ? date('d/m/Y H:i:s', strtotime($r['created_at'])) : '-' ?></td>
                                    <td>
                                        <?= htmlspecialchars($r['username'] ?? '-') ?>
                                        <?php if (!empty($r['level_user'])): ?><br><span class="badge bg-light text-dark border"><?= htmlspecialchars($r['level_user']) ?></span><?php endif; ?>
                                    </td>
                                    <td><code><?= htmlspecialchars($r['aksi'] ?? '-') ?></code></td>
                                    <td><?= $r['operasi'] !== null && $r['operasi'] !== '' ? '<span class="badge bg-info text-dark">' . htmlspecialchars($r['operasi']) . '</span>' : '-' ?></td>
                                    <td><?= htmlspecialchars($r['menu'] ?? '-') ?></td>
                                    <td><small class="text-muted" style="word-break:break-all"><?= htmlspecialchars($r['keterangan'] ?? '') ?></small></td>
                                    <td><?= audit_badge($r['status']) ?></td>
                                    <td><small><?= htmlspecialchars($r['ip_address'] ?? '-') ?></small></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    <p class="text-muted small mb-0 mt-2">Menampilkan maksimal 1000 entri terbaru. Gunakan filter untuk mempersempit.</p>
                </div>
            </div>
        </div>
    </div>
</main>
