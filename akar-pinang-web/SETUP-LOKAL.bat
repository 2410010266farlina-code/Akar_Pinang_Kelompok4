@echo off
setlocal
set XAMPP=C:\xampp
set PROJECT=%~dp0
if not exist "%XAMPP%\mysql\bin\mysql.exe" (
  echo [ERROR] XAMPP tidak ditemukan di C:\xampp.
  echo Ubah variabel XAMPP di file SETUP-LOKAL.bat sesuai lokasi instalasi XAMPP.
  pause
  exit /b 1
)
if not exist "%XAMPP%\php\php.exe" (
  echo [ERROR] PHP XAMPP tidak ditemukan.
  pause
  exit /b 1
)
if not exist "%PROJECT%backend\config.php" (
  copy "%PROJECT%backend\config.example.php" "%PROJECT%backend\config.php" >nul
  echo File backend\config.php dibuat dari konfigurasi contoh.
)
echo.
echo Akar Pinang - Setup database lokal
 echo Pastikan MySQL di XAMPP sedang berjalan dan root memakai password kosong.
echo Jika root memakai password, jalankan import melalui phpMyAdmin secara manual.
"%XAMPP%\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS db_akar_pinang CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
if errorlevel 1 (
  echo [ERROR] Tidak dapat membuat database. Pastikan MySQL berjalan dan kredensial sesuai.
  pause
  exit /b 1
)
"%XAMPP%\mysql\bin\mysql.exe" -u root db_akar_pinang < "%PROJECT%db_akar_pinang.sql"
if errorlevel 1 (
  echo [ERROR] Import gagal. Buka phpMyAdmin dan import db_akar_pinang.sql secara manual.
  pause
  exit /b 1
)
echo.
echo [OK] Database db_akar_pinang sudah di-import.
echo [CATATAN] File SQL berisi struktur tabel, belum ada produk contoh.
echo [CATATAN] Jika database sudah berisi data, jangan jalankan import berulang tanpa backup.
echo Buka XAMPP Control Panel, nyalakan Apache dan MySQL, lalu buka:
echo http://localhost/akar-pinang-web/
echo Tes endpoint backend:
echo http://localhost/akar-pinang-web/backend/api.php?action=csrf
start "" "%XAMPP%\xampp-control.exe"
start "" "http://localhost/akar-pinang-web/"
pause
