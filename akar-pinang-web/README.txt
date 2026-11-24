PATCH LOGIN/REGISTRASI AKAR PINANG

File di ZIP ini adalah patch untuk project yang sudah ada, bukan project lengkap.

Cara memasang:
1. Backup folder C:\xampp\htdocs\akar-pinang-web terlebih dahulu.
2. Salin index.html dari ZIP ini ke folder akar-pinang-web, timpa file lama.
3. Salin auth.js dan auth.css ke folder akar-pinang-web (sejajar dengan index.html).
4. Pastikan backend/config.php sudah ada dan benar.
5. Pastikan migrasi database sudah menambahkan users.is_admin.
6. Pastikan app.js, style.css, dan folder assets milik project lama tetap berada di folder utama; jangan hapus.
7. Buka http://localhost/akar-pinang-web/ lalu tombol Masuk / Daftar muncul di bagian atas.

Fitur yang dihubungkan patch ini:
- Mengambil token CSRF dari backend/api.php?action=csrf
- Login melalui POST backend/api.php?action=login
- Registrasi melalui POST backend/api.php?action=register
- Mengirim cookie sesi ke API dan memperbarui nama pengguna pada header
- Logout saat tombol pengguna yang sudah login diklik

Catatan: patch ini menghubungkan login/registrasi saja. Katalog, keranjang, checkout dan refund belum semuanya tersambung oleh patch ini dan perlu integrasi lanjutan. Pastikan folder backend berisi api.php serta config.php.
