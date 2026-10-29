# Deploy SiDoel ke cPanel — Panduan Langkah demi Langkah

Panduan ini untuk menaikkan aplikasi **SiDoel** (PHP native + MySQL) ke shared hosting
berbasis **cPanel** memakai fitur **Git™ Version Control**. Perkiraan waktu: 15–30 menit.

> File ini diblokir dari akses web oleh `.htaccess` (ekstensi `.md` ditolak), jadi aman disimpan di server.

---

## 0. Prasyarat di hosting

| Kebutuhan | Nilai / cara cek |
|---|---|
| PHP | **8.1+** (disarankan 8.2). cPanel → *Select PHP Version* |
| Ekstensi PHP | `mysqli`, `gd`, `mbstring`, `zip`, `fileinfo`, `json`, `xml` |
| Database | MySQL/MariaDB + **phpMyAdmin** |
| Git | cPanel → *Git™ Version Control* (tersedia di hampir semua paket) |
| Composer | opsional (kalau tidak ada, unggah `admin/vendor/` manual) |

---

## 1. Siapkan database

1. cPanel → **MySQL® Databases**:
   - Buat database, mis. `USER_sidoel`
   - Buat user, mis. `USER_sidoel` + password kuat
   - **Add User to Database** → centang **ALL PRIVILEGES**
2. cPanel → **phpMyAdmin** → pilih database tadi → tab **Import**:
   - Pilih file dump `sidoel_data_backup_YYYYMMDD.sql` (atau versi `.sql.gz` yang jauh lebih kecil)
   - Format: **SQL** → **Go**
3. Pastikan 41 tabel terimpor (cek `dt_siswa` = 734 baris pada data contoh).

> Dump terbaru bisa dibuat dari lokal: `mysqldump -u root --single-transaction --routines --events data_backup > sidoel.sql`
> atau dari aplikasi: **Admin → Administrasi Sistem → Backup & Restore**.

---

## 2. Clone repo lewat cPanel Git

1. cPanel → **Git™ Version Control** → **Create**
2. Isi:
   - **Clone a Repository**: `https://github.com/cacingkidzz/sidoel.git`
   - **Repository Path**: biarkan default (mis. `repositories/sidoel`)
   - **Repository Name**: `sidoel`
3. **Create**.

---

## 3. Sesuaikan `.cpanel.yml` (SEKALI SAJA)

Edit baris `DEPLOYPATH` di `.cpanel.yml` (di komputer Anda, lalu commit + push):

```yaml
- export DEPLOYPATH=/home/USER/public_html/sidoel
```

Contoh lain:
- domain utama → `/home/USER/public_html`
- subfolder → `/home/USER/public_html/sidoel`
- addon domain → `/home/USER/namaaddon.com`

Lalu di cPanel → Git → **Pull or Deploy** → **Update from Remote** → **Deploy HEAD Commit**.

Yang dilakukan `.cpanel.yml`:

1. Membuat folder tujuan + folder writable (`admin/backup`, `img/foto_siswa`, `img/foto_guru`, `img/kop`, `img/logo`)
2. Menyalin `admin/ guru/ portal/ sekretaris/ siswa/ walikelas/ index.php` + `.htaccess`
3. `composer install --no-dev` (dilewati otomatis bila Composer tidak ada)
4. `chmod` folder upload & backup agar bisa ditulis

---

## 4. Buat `admin/koneksi.local.php` (SEKALI SAJA, di server)

cPanel → **File Manager** → masuk `…/sidoel/admin/` → **+ File** → `koneksi.local.php`:

```php
<?php
$host = 'localhost';
$user = 'USER_sidoel';        // user database cPanel
$pass = 'PASSWORD_DB';        // password database
$db   = 'USER_sidoel';        // nama database

define('GOOGLE_MAPS_API_KEY', 'KEY_GOOGLE_MAPS_KHUSUS_SERVER');
define('MYSQL_BIN_DIR', '');  // Linux: kosongkan (backup otomatis mode PHP murni)
```

File ini **tidak ada di git**, jadi tidak akan tertimpa saat deploy berikutnya.
`admin/koneksi.php` memuatnya otomatis bila ada.

---

## 5. Dependensi Composer (`admin/vendor/`)

Pilih salah satu:

- **Composer tersedia** (cPanel → *Terminal*, atau sudah dijalankan `.cpanel.yml`):
  ```bash
  cd ~/public_html/sidoel/admin && composer install --no-dev --optimize-autoloader
  ```
- **Tanpa Composer**: di komputer, zip folder `admin/vendor/` (5,4 MB) → unggah ke
  `…/sidoel/admin/` → **Extract**. Struktur harus jadi `admin/vendor/autoload.php`.

Tanpa `vendor/`, menu **Export Excel / Template** akan error (butuh PhpSpreadsheet).

---

## 6. Verifikasi

| Cek | Harapan |
|---|---|
| `https://domain/` (atau `/sidoel/`) | Halaman depan: statistik + 4 kartu aplikasi |
| `https://domain/admin/login.php` | Form login admin |
| Login admin (user dari tabel `dt_user`) | Masuk beranda, sidebar sesuai level |
| `…/siswa/login.php` | Login siswa (NIS + password) |
| `…/portal/login.php` | Login guru/wali (username/NIP/NUPTK) |
| `https://domain/AGENTS.md` | **403** (proteksi `.htaccess`) |
| `https://domain/admin/backup/` | **403** |

Ganti password admin setelah login pertama (**Profil Saya → Ganti Password**).

---

## 7. Keamanan yang sudah disiapkan

- `.gitignore`: hanya kode yang masuk repo — DB dump, foto upload, `vendor/`, `koneksi.local.php` tidak ikut.
- `.htaccess` root: blokir `.sql`, `.md`, `.yml`, `.log`, `.zip`, `.git/`, `.env`, `composer.lock`; tanpa directory listing.
- `admin/.htaccess`: blokir `admin/backup/` (dump DB) & `admin/vendor/` dari web.
- `admin/assets/img/.htaccess`: folder upload tidak bisa mengeksekusi `.php`/`.phtml`/`.cgi`.
- Password admin & user: **bcrypt** (MD5 lama otomatis di-upgrade saat login).
- **Sisa tugas manual:** batasi **Google Maps API key** di Google Cloud Console
  (HTTP referrers: `https://domain/*`, batasi ke Maps JavaScript API), karena key lama
  pernah ada di file yang di-commit.

---

## 8. Update rutin (setelah aplikasi jalan)

```bash
# di cPanel: Git → Update from Remote → Deploy HEAD Commit
```

Aman karena deploy **tidak** menyentuh:
`admin/koneksi.local.php`, `admin/vendor/`, `admin/backup/`, foto siswa/guru, kop, logo.

**Sebelum update**: buat backup DB dulu (Admin → Backup & Restore → Buat Backup, atau phpMyAdmin → Export).

Catatan: `cp` tidak menghapus file yang sudah dihapus dari repo — bila ada file yang dihapus
di git, hapus manual di server.

---

## 9. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| **500 Internal Server Error** setelah deploy | `.htaccess` memakai direktif yang dilarang hosting. Coba komentari baris `<IfModule mod_autoindex.c> Options -Indexes </IfModule>` di `.htaccess`, lalu cek `error_log` di folder aplikasi |
| **Koneksi gagal: Access denied** | `admin/koneksi.local.php` belum ada / kredensial salah (user DB harus di-*add to database*) |
| **Blank / 500 di halaman tertentu** | PHP < 8.1, atau ekstensi `gd`/`mbstring` belum aktif |
| **Export Excel error: `vendor/autoload.php` tidak ditemukan** | Langkah 5 belum dilakukan |
| **Upload foto gagal** | Permission `admin/assets/img/foto_siswa` — jalankan `chmod 755` (atau 775), atau deploy ulang agar `.cpanel.yml` mengatur izin |
| **Backup menampilkan mode "PHP murni"** | Normal di shared hosting (`exec()` dimatikan). Tanpa trigger/procedure, data tetap lengkap |
| **Peta siswa kosong** | `GOOGLE_MAPS_API_KEY` salah / referrer belum diizinkan di Google Cloud Console |
| **Favicon/logo hilang** | `admin/assets/img/logo/` belum berisi file logo sekolah |
| **Login admin ditolak** | Password user di tabel `dt_user`; reset lewat phpMyAdmin (`password` = hash bcrypt baru) atau menu User di admin lain |

---

## 10. Struktur di server (setelah deploy)

```
/home/USER/public_html/sidoel/
├── .htaccess                 # proteksi
├── index.php                 # halaman depan publik
├── admin/                    # aplikasi admin (RBAC, konten, aksi, assets)
│   ├── .htaccess             # blokir backup/ & vendor/
│   ├── koneksi.php           # default + pemuat koneksi.local.php
│   ├── koneksi.local.php     # (dibuat manual, tidak di git)
│   ├── vendor/               # (Composer, tidak di git)
│   ├── backup/               # (dump DB, tidak di git)
│   └── assets/img/…          # logo, foto siswa/guru, kop
├── portal/  guru/  walikelas/ # portal guru & wali kelas (login terpusat)
├── siswa/                    # portal siswa
└── sekretaris/               # mini-app sekretaris kelas
```
