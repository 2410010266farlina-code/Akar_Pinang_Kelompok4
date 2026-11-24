# Backend PHP + MySQL Akar Pinang (starter API)

API ini ditujukan untuk PHP 8+ dan MySQL/MariaDB. Ia memakai tabel dari `db_akar_pinang.sql` dan menyediakan:

- `GET api.php?action=csrf` — sesi/CSRF token
- `POST api.php?action=register` — registrasi dengan `password_hash`
- `POST api.php?action=login` / `POST api.php?action=logout`
- `GET api.php?action=products` — katalog aktif dari database
- `GET api.php?action=orders` — pesanan milik pengguna yang login
- `POST api.php?action=refund` — ajukan refund multipart dengan validasi kepemilikan pesanan, pembayaran, alasan, metode, dan foto
- `GET api.php?action=refunds` — status refund milik pengguna

## Setup lokal XAMPP
1. Salin folder project ke `C:\xampp\htdocs\akar-pinang-web`.
2. Jalankan Apache dan MySQL dari XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin`, buat database `db_akar_pinang` (collation `utf8mb4_general_ci`) lalu Import file `db_akar_pinang.sql` asli yang disediakan.
4. Salin `backend/config.example.php` menjadi `backend/config.php`. Sesuaikan nama DB, user, dan password MySQL lokal.
5. Pastikan ekstensi PHP `pdo_mysql`, `fileinfo` dan `mbstring` aktif. Pastikan `backend/uploads/refund` dapat ditulis oleh PHP.
6. Buka `http://localhost/akar-pinang-web/backend/api.php?action=csrf`. Hasil yang diharapkan berupa JSON berisi token `csrf` dan `user: null`.

## Online dari mana saja
XAMPP di komputer pribadi tidak otomatis menjadi hosting online yang aman. Rekomendasi: gunakan hosting yang mendukung PHP 8+, MySQL/MariaDB, HTTPS, dan backup; unggah file web ke `public_html` dan impor SQL melalui panel hosting. Letakkan `config.php` di folder backend yang tidak bisa diakses publik, gunakan kredensial database khusus (bukan root), aktifkan HTTPS, set `session_secure` ke `true`, dan pastikan direktori bukti refund tidak mengeksekusi PHP. Jangan membuka port MySQL ke internet.

Jika harus memakai komputer sendiri sebagai server publik, perlu konfigurasi router/firewall, IP publik atau tunnel, HTTPS, pembaruan keamanan, backup dan pemantauan. Ini lebih berisiko daripada hosting terkelola.

## Catatan penting sebelum produksi
- API ini adalah fondasi backend, belum seluruh UI `index.html` terhubung ke API. UI prototype yang ada masih memakai `localStorage`; jangan menganggap pesanan demo sebagai transaksi database.
- Skema SQL yang dikirim tidak memiliki kolom role/admin atau status verifikasi refund. Proses meninjau/menyetujui/menolak refund perlu dibuat pada panel admin terpisah dengan otorisasi admin yang benar sebelum dipakai nyata.
- Skema yang diberikan membatasi beberapa kolom alamat seperti `nama_penerima`, `no_telepon`, dan wilayah ke `VARCHAR(10)`. Perbaiki ukuran kolom sebelum mengembangkan checkout penuh, lalu uji migrasi dengan backup terlebih dahulu.
- Refund hanya disimpan sebagai permohonan; API tidak mengirim uang dan tidak terhubung ke payment gateway.
- Foto bukti berisi informasi pelanggan. Di produksi, blokir eksekusi script di folder uploads, batasi akses, atur masa retensi dan backup dengan benar.
