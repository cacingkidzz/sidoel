-- ============================================================================
-- SiDoel — Peran BENDAHARA (Keuangan / SPP)
-- ----------------------------------------------------------------------------
-- Menambah level user baru: Bendahara.
-- Izin menu diatur di admin/akses.php (const AKSES_MENU).
--
-- Cara pakai (phpMyAdmin / CLI):
--   mysql -u root data_backup < bendahara_role.sql
-- Aman dijalankan berulang (hanya mengubah definisi ENUM, data tidak hilang).
-- ============================================================================

ALTER TABLE dt_user
    MODIFY COLUMN level_user ENUM('Admin', 'Kurikulum', 'Kesiswaan', 'Guru', 'Bendahara') NOT NULL;

-- Verifikasi:
-- SHOW COLUMNS FROM dt_user LIKE 'level_user';
-- SELECT level_user, COUNT(*) FROM dt_user GROUP BY level_user;
