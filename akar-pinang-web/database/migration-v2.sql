-- Jalankan satu kali setelah mengimpor db_akar_pinang.sql.
-- Backup database terlebih dahulu.
ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER status_member;
ALTER TABLE alamat MODIFY nama_penerima VARCHAR(100) NOT NULL,
 MODIFY no_telepon VARCHAR(30) NOT NULL,
 MODIFY provinsi VARCHAR(100) NOT NULL,
 MODIFY kota VARCHAR(100) NOT NULL,
 MODIFY kecamatan VARCHAR(100) NOT NULL,
 MODIFY kode_pos VARCHAR(15) NOT NULL;
-- Ganti email di bawah dengan email admin yang benar sebelum menjalankan.
-- Setelah akun didaftarkan melalui website/API, jalankan:
-- UPDATE users SET is_admin=1 WHERE email='admin@domainanda.com';
