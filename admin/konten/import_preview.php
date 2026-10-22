<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('koneksi.php');

$entity = $_GET['entity'] ?? '';
if ($entity === '') {
    header('Location: index.php?menu=beranda');
    exit;
}

$sessKey = 'ie_preview_' . $entity;
$preview = $_SESSION[$sessKey] ?? null;
if (!$preview) {
    header('Location: index.php?menu=' . $preview['cfg']['menu'] ?? 'siswa_aktif');
    exit;
}

$cfg = $preview['cfg'];
$prev = $preview['prev'];
$validRows = $prev['valid'];
$invalidRows = $prev['invalid'];
$dupRows = $prev['duplicates'];
$c = $prev['counts'];

?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h2 class="mb-0">Preview Import <small class="text-muted"><?= htmlspecialchars($cfg['label']); ?></small></h2>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php?menu=beranda"><i class="bi bi-house-door-fill"></i></a></li>
                        <li class="breadcrumb-item"><a href="index.php?menu=<?= $cfg['menu']; ?>"><?= htmlspecialchars($cfg['label']); ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Preview Import</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">

            <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['alert']['type']); ?> alert-dismissible fade show" role="alert">
                    <?= $_SESSION['alert']['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>

            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-excel me-1"></i> Hasil Validasi: <?= htmlspecialchars($preview['file']); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="bg-light border rounded p-3 text-center">
                                <div class="text-muted small">Total baris</div>
                                <div class="h4 mb-0"><?= $c['total']; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-success bg-opacity-10 border border-success rounded p-3 text-center">
                                <div class="text-success small">Valid</div>
                                <div class="h4 mb-0 text-success"><?= $c['valid']; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-warning bg-opacity-10 border border-warning rounded p-3 text-center">
                                <div class="text-warning small">Duplikat dilewati</div>
                                <div class="h4 mb-0 text-warning"><?= $c['duplicate']; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-danger bg-opacity-10 border border-danger rounded p-3 text-center">
                                <div class="text-danger small">Gagal validasi</div>
                                <div class="h4 mb-0 text-danger"><?= $c['invalid']; ?></div>
                            </div>
                        </div>
                    </div>

                    <form action="aksi/import_general.php?entity=<?= urlencode($entity); ?>&aksi=execute" method="post" onsubmit="return confirm('Yakin ingin mengimpor data yang tercentang ke database?')">
                        <input type="hidden" name="aksi" value="execute">

                        <?php if ($c['valid'] > 0): ?>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Data siap diimpor (<span id="countValid"><?= $c['valid']; ?></span>)</label>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle">
                                        <thead>
                                            <tr>
                                                <th style="width:4%;" class="text-center"><input type="checkbox" id="selectAllValid"></th>
                                                <th style="width:8%;" class="text-center">Baris</th>
                                                <th>Data (kolom kunci)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($validRows as $item): ?>
                                                <?php
                                                $d = $item['data'];
                                                $label = $d['nama'] ?? $d['namaguru'] ?? $d['namakelas'] ?? $d['namamapel'] ?? $d['username'] ?? 'baris #' . $item['row'];
                                                $keyCol = $cfg['identity'][0] ?? array_key_first($d);
                                                $keyVal = $d[$keyCol] ?? '-';
                                                ?>
                                                <tr>
                                                    <td class="text-center"><input type="checkbox" name="rows[]" value="<?= (int)$item['row']; ?>" class="rowValid" checked></td>
                                                    <td class="text-center small"><?= (int)$item['row']; ?></td>
                                                    <td><strong><?= htmlspecialchars((string)$keyVal); ?></strong> — <?= htmlspecialchars((string)$label); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($c['duplicate'] > 0): ?>
                            <div class="alert alert-warning mb-3">
                                <h6><i class="bi bi-exclamation-triangle me-1"></i> Duplikat (tidak akan diimpor)</h6>
                                <ul class="mb-0 small">
                                    <?php foreach ($dupRows as $rowNo => $reason): ?>
                                        <li>Baris <?= (int)$rowNo; ?>: <?= htmlspecialchars($reason); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($c['invalid'] > 0): ?>
                            <div class="alert alert-danger mb-3">
                                <h6><i class="bi bi-x-circle me-1"></i> Gagal validasi (tidak akan diimpor)</h6>
                                <ul class="mb-0 small">
                                    <?php foreach ($invalidRows as $rowNo => $errors): ?>
                                        <li><strong>Baris <?= (int)$rowNo; ?>:</strong> <?= implode(' ', array_map('htmlspecialchars', $errors)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($c['valid'] > 0): ?>
                            <div class="d-flex gap-2 mt-3">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Impor yang terpilih</button>
                                <a href="index.php?menu=<?= $cfg['menu']; ?>" class="btn btn-secondary">Batal</a>
                            </div>
                        <?php else: ?>
                            <a href="index.php?menu=<?= $cfg['menu']; ?>" class="btn btn-secondary">Kembali</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var all = document.getElementById('selectAllValid');
    var boxes = document.querySelectorAll('.rowValid');
    var countEl = document.getElementById('countValid');
    if (!all || !boxes.length) return;

    all.addEventListener('change', function () {
        boxes.forEach(function (b) { b.checked = all.checked; });
        updateCount();
    });
    boxes.forEach(function (b) {
        b.addEventListener('change', updateCount);
    });

    function updateCount() {
        var n = 0;
        boxes.forEach(function (b) { if (b.checked) n++; });
        countEl.textContent = n;
    }
});
</script>
