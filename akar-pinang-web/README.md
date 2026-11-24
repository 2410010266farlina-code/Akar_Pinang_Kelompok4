# Akar Pinang — web responsif + backend PHP/MySQL

Project ini memakai desain visual Akar Pinang dari PPT dan database `db_akar_pinang.sql`. API backend dan panel admin ada di `backend/`. **Belum dipublikasikan ke internet dan bukan payment gateway**. Tampilan toko utama sebelumnya masih memiliki beberapa alur demo/localStorage yang belum semuanya dipindahkan ke API; API dapat diuji secara langsung dan diintegrasikan menggunakan dokumentasi di bawah.

## 1. Persyaratan
- XAMPP dengan PHP 8.1+ (PHP 8.0 mungkin berjalan, tetapi disarankan versi PHP yang masih didukung), ekstensi PDO MySQL, Fileinfo.
- MySQL/MariaDB aktif.
- Backup database sebelum migrasi.

## 2. Instalasi lokal Windows
1. Ekstrak folder ini ke `C:\xampp\htdocs\akar-pinang-web`.
2. Start Apache dan MySQL dari XAMPP Control Panel.
3. Import `db_akar_pinang.sql` melalui `http://localhost/phpmyadmin` ke database `db_akar_pinang`. Jika database sudah berisi data, backup dahulu dan jangan import ulang.
4. Jalankan satu kali `database/migration-v2.sql` di tab SQL phpMyAdmin. Migrasi menambahkan kolom `users.is_admin` dan memperlebar kolom alamat yang terlalu pendek. Jangan jalankan ulang; ALTER ADD COLUMN akan gagal jika kolom sudah ada.
5. Salin `backend/config.example.php` menjadi `backend/config.php`; sesuaikan host, nama DB, user, dan password MySQL. `config.php` sengaja tidak disertakan karena berisi kredensial lokal.
6. Buka `http://localhost/akar-pinang-web/`. Tes API di `http://localhost/akar-pinang-web/backend/api.php?action=csrf`.

`SETUP-LOKAL.bat` mengimpor skema awal dan mengasumsikan XAMPP di `C:\xampp`, user MySQL `root` tanpa password. Setelah setup, jalankan migrasi v2 secara manual melalui phpMyAdmin.

## 3. Buat akun admin
1. Ambil token CSRF dari `GET /backend/api.php?action=csrf`.
2. Daftarkan akun biasa melalui `POST /backend/api.php?action=register` (JSON berisi `nama`, `email`, `no_telepon`, `password`, header `X-CSRF-Token`). Password minimal 10 karakter.
3. Di phpMyAdmin jalankan, ganti email dengan akun yang dibuat: `UPDATE users SET is_admin=1 WHERE email='admin@domainanda.com';`
4. Buka `http://localhost/akar-pinang-web/backend/admin.html`, login dengan akun tersebut. Jangan gunakan akun pelanggan sebagai admin.

## 4. Endpoint API
Semua endpoint ada di `backend/api.php`; POST JSON menggunakan header `Content-Type: application/json` dan `X-CSRF-Token`, kecuali upload bukti yang memakai `multipart/form-data`.

- `GET ?action=csrf` — token CSRF dan status sesi
- `POST ?action=register` — registrasi dan password hashing
- `POST ?action=login`, `POST ?action=logout`, `GET ?action=me`
- `GET ?action=products` — produk aktif plus varian
- `GET ?action=cart`, `POST ?action=cart-add` (`id_varian`, `jumlah`), `POST ?action=cart-update` (`id_detail_keranjang`, `jumlah`; 0 untuk hapus)
- `POST ?action=checkout` — JSON: `nama_penerima`, `no_telepon`, `provinsi`, `kota`, `kecamatan`, `kode_pos`, `alamat_lengkap`, `ekspedisi` (`JNE`, `SiCepat`, `J&T Express`), `metode_pembayaran` (`transfer_bank`, `dana`, `ovo`, `gopay`, `shopeepay`, `cod`). Memvalidasi stok dan menyimpan alamat, pesanan, detail pesanan, pembayaran pending, mengurangi stok dan mengosongkan keranjang dalam transaksi database.
- `GET ?action=orders`, `GET ?action=refunds`
- `POST ?action=refund` untuk tanpa foto; `POST ?action=refund-upload` untuk foto, `multipart/form-data` dengan `id_pesanan`, `alasan`, `metode_refund`, `deskripsi` opsional, `bukti_foto`, serta detail bank jika metode transfer.
- Admin: `GET ?action=admin-dashboard`, `admin-orders`, `admin-refunds`, `admin-products`; `POST ?action=admin-refund-status`, `admin-order-status`, `admin-payment-status`, `admin-product-save`.

API menggunakan prepared statements, session cookie HttpOnly/SameSite, session ID rotation saat login, token CSRF pada perubahan data, validasi input, dan pemeriksaan hak akses admin di sisi server. Batasi percobaan login/rate-limit dan audit keamanan tambahan diperlukan sebelum produksi.

## 5. Penting mengenai transaksi/refund
- Checkout hanya membuat pesanan dan status pembayaran `menunggu`; **tidak memproses uang**. Untuk pembayaran online sungguhan perlu integrasi payment gateway, verifikasi webhook bertanda tangan, idempotensi, dan rekonsiliasi.
- Status `berhasil` tidak boleh ditandai hanya karena pengguna mengaku sudah membayar; verifikasi manual atau gateway dulu.
- Refund hanya mengajukan permintaan dan status admin; perubahan ke `selesai` memperbarui status pembayaran menjadi `dikembalikan`, tetapi **tidak mengirim transfer uang otomatis**. Admin harus benar-benar memproses pengembalian dana melalui kanal pembayaran.
- Skema SQL asli hanya struktur, tidak berisi data kategori/produk. Tambahkan kategori dan varian di database/admin agar katalog terisi.
- Antarmuka toko awal masih memiliki alur demo; integrasikan formnya dengan API sebelum digunakan sungguhan. Panel admin dapat dibuka di `backend/admin.html`.

## 6. Deploy ke hosting HTTPS
1. Pilih hosting yang mendukung PHP 8.1+, PDO MySQL, Fileinfo, HTTPS, dan folder upload writable.
2. Buat database/user/password unik di panel hosting; impor `db_akar_pinang.sql`, lalu jalankan migrasi v2 satu kali.
3. Upload file project. Idealnya tempatkan backend/config di luar public webroot atau lindungi dengan aturan server; jangan upload `config.php` ke repositori publik.
4. Buat `backend/config.php` dengan kredensial hosting. Ubah `session_secure` menjadi `true` hanya setelah HTTPS aktif.
5. Pastikan `backend/uploads/refund` dapat ditulis oleh PHP, tapi file unggahan tidak dapat dieksekusi sebagai skrip. Backup database, aktifkan HTTPS paksa, nonaktifkan display_errors, dan atur batas upload.
6. Hapus/nonaktifkan file setup setelah instalasi, buat akun admin lalu set `is_admin=1`, uji semua endpoint, dan uji backup/restore.
7. Jangan mengekspos port MySQL atau XAMPP lokal langsung ke internet.

## Keamanan sebelum produksi
Pasang rate limiting login, kebijakan password/reset, log audit admin, pembatasan MIME dan scanning upload, backup terjadwal, kebijakan privasi, validasi stok dan voucher tambahan, serta uji transaksi paralel. Jangan simpan data kartu pembayaran di aplikasi.
