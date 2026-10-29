    </div>

    <div class="ss-bottomnav">
        <a href="index.php" class="<?= $active === 'profil' ? 'active' : '' ?>"><i class="bi bi-person-vcard-fill"></i>Profil</a>
        <a href="edit.php" class="<?= $active === 'edit' ? 'active' : '' ?>"><i class="bi bi-pencil-square"></i>Ubah Data</a>
        <a href="pembayaran.php" class="<?= $active === 'bayar' ? 'active' : '' ?>"><i class="bi bi-cash-coin"></i>Pembayaran</a>
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i>Keluar</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
