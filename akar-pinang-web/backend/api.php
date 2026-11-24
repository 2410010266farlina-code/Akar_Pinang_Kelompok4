<?php

declare(strict_types=1);
$configFile = __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
if (!is_file($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Konfigurasi backend belum dibuat. Salin config.example.php menjadi config.php.']);
    exit;
}
$c = require $configFile;
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(['httponly' => true, 'secure' => (bool)($c['session_secure'] ?? $https), 'samesite' => 'Lax']);
session_start();
function out($d, int $s = 200): never
{
    http_response_code($s);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function input(): array
{
    $j = json_decode(file_get_contents('php://input'), true);
    return is_array($j) ? $j : $_POST;
}
function uid(): int
{
    if (empty($_SESSION['uid'])) out(['error' => 'Silakan login terlebih dahulu.'], 401);
    return (int)$_SESSION['uid'];
}
function admin(): int
{
    $id = uid();
    if (empty($_SESSION['admin'])) out(['error' => 'Akses khusus admin.'], 403);
    return $id;
}
function csrf(): void
{
    $v = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $v)) out(['error' => 'Token CSRF tidak valid. Muat ulang halaman.'], 419);
}
function strv(array $d, string $k, int $max = 255): string
{
    $v = trim((string)($d[$k] ?? ''));
    if (mb_strlen($v) > $max) out(['error' => "Kolom $k terlalu panjang."], 422);
    return $v;
}
try {
    $pdo = new PDO('mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_name'] . ';charset=utf8mb4', $c['db_user'], $c['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
} catch (Throwable $e) {
    out(['error' => 'Koneksi database gagal. Periksa backend/config.php.'], 500);
}
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET' && $action === 'csrf') {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    out(['csrf' => $_SESSION['csrf'], 'user' => isset($_SESSION['uid']) ? ['id' => (int)$_SESSION['uid'], 'nama' => $_SESSION['nama'] ?? '', 'email' => $_SESSION['email'] ?? '', 'admin' => !empty($_SESSION['admin'])] : null]);
}
if ($method === 'POST' && $action === 'register') {
    csrf();
    $d = input();
    $name = strv($d, 'nama', 100);
    $email = strtolower(strv($d, 'email', 200));
    $phone = strv($d, 'no_telepon', 200);
    $pass = (string)($d['password'] ?? '');
    if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 10) out(['error' => 'Nama/email tidak valid; password minimal 10 karakter.'], 422);
    $q = $pdo->prepare('SELECT id_user FROM users WHERE email=?');
    $q->execute([$email]);
    if ($q->fetch()) out(['error' => 'Email sudah terdaftar.'], 409);
    $q = $pdo->prepare("INSERT INTO users(nama,email,no_telepon,password,foto_profile,status_member,tanggal_daftar) VALUES(?,?,?,?,?,'reguler',NOW())");
    $q->execute([$name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT), '']);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$pdo->lastInsertId();
    $_SESSION['nama'] = $name;
    $_SESSION['email'] = $email;
    $_SESSION['admin'] = false;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    out(['ok' => true, 'user' => ['nama' => $name, 'email' => $email], 'csrf' => $_SESSION['csrf']], 201);
}
if ($method === 'POST' && $action === 'login') {
    csrf();
    $d = input();
    $email = strtolower(strv($d, 'email', 200));
    $pass = (string)($d['password'] ?? '');
    $q = $pdo->prepare('SELECT id_user,nama,email,password,is_admin FROM users WHERE email=? LIMIT 1');
    $q->execute([$email]);
    $u = $q->fetch();
    if (!$u || !password_verify($pass, $u['password'])) out(['error' => 'Email atau password salah.'], 401);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id_user'];
    $_SESSION['nama'] = $u['nama'];
    $_SESSION['email'] = $u['email'];
    $_SESSION['admin'] = (bool)$u['is_admin'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    out(['ok' => true, 'user' => ['nama' => $u['nama'], 'email' => $u['email'], 'admin' => (bool)$u['is_admin']], 'csrf' => $_SESSION['csrf']]);
}
if ($method === 'POST' && $action === 'logout') {
    csrf();
    $_SESSION = [];
    session_regenerate_id(true);
    out(['ok' => true]);
}
if ($method === 'GET' && $action === 'products') {
    $q = $pdo->query("SELECT p.id_produk,p.nama_produk,p.deskripsi,p.harga,p.stok,p.berat_gram,p.foto_produk,p.rating,p.terlaris,k.nama_kategori FROM produk p LEFT JOIN kategori k ON k.id_kategori=p.id_kategori WHERE p.status_produk='aktif' ORDER BY p.terlaris DESC,p.id_produk DESC");
    $products = $q->fetchAll();
    foreach ($products as &$x) {
        $v = $pdo->prepare('SELECT id_varian,rasa,berat_gram,harga,stok FROM varian_produk WHERE id_produk=? ORDER BY berat_gram');
        $v->execute([$x['id_produk']]);
        $x['varian'] = $v->fetchAll();
    }
    out(['products' => $products]);
}
if ($method === 'GET' && $action === 'me') {
    uid();
    out(['user' => ['id' => $_SESSION['uid'], 'nama' => $_SESSION['nama'], 'email' => $_SESSION['email'], 'admin' => !empty($_SESSION['admin'])]]);
}
if ($method === 'GET' && $action === 'cart') {
    $id = uid();
    $q = $pdo->prepare('SELECT id_keranjang FROM keranjang WHERE id_user=? ORDER BY id_keranjang DESC LIMIT 1');
    $q->execute([$id]);
    $cart = $q->fetch();
    if (!$cart) {
        out(['items' => []]);
    }
    $q = $pdo->prepare('SELECT dk.id_detail_keranjang,dk.id_varian,dk.jumlah,v.rasa,v.berat_gram,v.harga,v.stok,p.id_produk,p.nama_produk,p.foto_produk FROM detail_keranjang dk JOIN varian_produk v ON v.id_varian=dk.id_varian JOIN produk p ON p.id_produk=v.id_produk WHERE dk.id_keranjang=?');
    $q->execute([$cart['id_keranjang']]);
    out(['items' => $q->fetchAll()]);
}
if ($method === 'POST' && $action === 'cart-add') {
    $id = uid();
    csrf();
    $d = input();
    $variant = (int)($d['id_varian'] ?? 0);
    $qty = (int)($d['jumlah'] ?? 1);
    if ($variant < 1 || $qty < 1 || $qty > 99) out(['error' => 'Varian/jumlah tidak valid.'], 422);
    $q = $pdo->prepare('SELECT stok FROM varian_produk WHERE id_varian=?');
    $q->execute([$variant]);
    $v = $q->fetch();
    if (!$v || $qty > (int)$v['stok']) out(['error' => 'Varian tidak tersedia atau stok tidak cukup.'], 422);
    $pdo->beginTransaction();
    $q = $pdo->prepare('SELECT id_keranjang FROM keranjang WHERE id_user=? ORDER BY id_keranjang DESC LIMIT 1');
    $q->execute([$id]);
    $cRow = $q->fetch();
    if (!$cRow) {
        $q = $pdo->prepare('INSERT INTO keranjang(id_user) VALUES(?)');
        $q->execute([$id]);
        $cid = (int)$pdo->lastInsertId();
    } else $cid = (int)$cRow['id_keranjang'];
    $q = $pdo->prepare('SELECT id_detail_keranjang,jumlah FROM detail_keranjang WHERE id_keranjang=? AND id_varian=?');
    $q->execute([$cid, $variant]);
    $old = $q->fetch();
    if ($old) {
        if ((int)$old['jumlah'] + $qty > (int)$v['stok']) {
            $pdo->rollBack();
            out(['error' => 'Jumlah melebihi stok.'], 422);
        }
        $q = $pdo->prepare('UPDATE detail_keranjang SET jumlah=jumlah+? WHERE id_detail_keranjang=?');
        $q->execute([$qty, $old['id_detail_keranjang']]);
    } else {
        $q = $pdo->prepare('INSERT INTO detail_keranjang(id_keranjang,id_varian,jumlah) VALUES(?,?,?)');
        $q->execute([$cid, $variant, $qty]);
    }
    $pdo->commit();
    out(['ok' => true]);
}
if ($method === 'POST' && $action === 'cart-update') {
    uid();
    csrf();
    $d = input();
    $detail = (int)($d['id_detail_keranjang'] ?? 0);
    $qty = (int)($d['jumlah'] ?? 0);
    $q = $pdo->prepare('SELECT dk.id_detail_keranjang,v.stok FROM detail_keranjang dk JOIN keranjang k ON k.id_keranjang=dk.id_keranjang JOIN varian_produk v ON v.id_varian=dk.id_varian WHERE dk.id_detail_keranjang=? AND k.id_user=?');
    $q->execute([$detail, $_SESSION['uid']]);
    $r = $q->fetch();
    if (!$r) out(['error' => 'Item tidak ditemukan.'], 404);
    if ($qty < 0 || $qty > (int)$r['stok']) out(['error' => 'Jumlah tidak valid.'], 422);
    if ($qty === 0) $pdo->prepare('DELETE FROM detail_keranjang WHERE id_detail_keranjang=?')->execute([$detail]);
    else $pdo->prepare('UPDATE detail_keranjang SET jumlah=? WHERE id_detail_keranjang=?')->execute([$qty, $detail]);
    out(['ok' => true]);
}
if ($method === 'POST' && $action === 'checkout') {
    $id = uid();
    csrf();
    $d = input();
    $recipient = strv($d, 'nama_penerima', 100);
    $phone = strv($d, 'no_telepon', 30);
    $province = strv($d, 'provinsi', 100);
    $city = strv($d, 'kota', 100);
    $district = strv($d, 'kecamatan', 100);
    $zip = strv($d, 'kode_pos', 15);
    $address = strv($d, 'alamat_lengkap', 2000);
    $ship = strv($d, 'ekspedisi', 50);
    $payment = strv($d, 'metode_pembayaran', 30);
    $allowed = ['transfer_bank', 'dana', 'ovo', 'gopay', 'shopeepay', 'cod'];
    if (!$recipient || !$phone || !$city || !$address || !in_array($payment, $allowed, true) || !in_array($ship, ['JNE', 'SiCepat', 'J&T Express'], true)) out(['error' => 'Lengkapi alamat dan pilih ekspedisi/pembayaran yang valid.'], 422);
    $q = $pdo->prepare('SELECT id_keranjang FROM keranjang WHERE id_user=? ORDER BY id_keranjang DESC LIMIT 1');
    $q->execute([$id]);
    $cr = $q->fetch();
    if (!$cr) out(['error' => 'Keranjang kosong.'], 422);
    $q = $pdo->prepare('SELECT dk.id_varian,dk.jumlah,v.rasa,v.berat_gram,v.harga,v.stok,p.nama_produk FROM detail_keranjang dk JOIN varian_produk v ON v.id_varian=dk.id_varian JOIN produk p ON p.id_produk=v.id_produk WHERE dk.id_keranjang=? FOR UPDATE');
    $pdo->beginTransaction();
    $q->execute([$cr['id_keranjang']]);
    $items = $q->fetchAll();
    if (!$items) {
        $pdo->rollBack();
        out(['error' => 'Keranjang kosong.'], 422);
    }
    $subtotal = 0;
    foreach ($items as $it) {
        if ((int)$it['jumlah'] > (int)$it['stok']) {
            $pdo->rollBack();
            out(['error' => 'Stok tidak cukup untuk ' . $it['nama_produk']], 422);
        }
        $subtotal += (float)$it['harga'] * (int)$it['jumlah'];
    }
    $shipping = ['JNE' => 12000, 'SiCepat' => 13000, 'J&T Express' => 14000][$ship];
    $total = $subtotal + $shipping;
    $q = $pdo->prepare('INSERT INTO alamat(id_user,nama_penerima,no_telepon,provinsi,kota,kecamatan,kode_pos,alamat_lengkap,alamat_utama) VALUES(?,?,?,?,?,?,?,?,0)');
    $q->execute([$id, $recipient, $phone, $province, $city, $district, $zip, $address]);
    $addressId = (int)$pdo->lastInsertId();
    $code = 'AP' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
    $q = $pdo->prepare("INSERT INTO pesanan(kode_pesanan,id_user,id_alamat,tanggal_pesanan,subtotal,ongkir,diskon,total_bayar,ekspedisi,status_pesanan) VALUES(?,?,?,NOW(),?,?,0,?,?,'menunggu')");
    $q->execute([$code, $id, $addressId, $subtotal, $shipping, $total, $ship]);
    $orderId = (int)$pdo->lastInsertId();
    $detail = $pdo->prepare('INSERT INTO detail_pesanan(id_pesanan,id_varian,nama_produk,rasa,berat_gram,harga_satuan,jumlah,subtotal) VALUES(?,?,?,?,?,?,?,?)');
    $stock = $pdo->prepare('UPDATE varian_produk SET stok=stok-? WHERE id_varian=? AND stok>=?');
    foreach ($items as $it) {
        $line = (float)$it['harga'] * (int)$it['jumlah'];
        $detail->execute([$orderId, $it['id_varian'], $it['nama_produk'], $it['rasa'], $it['berat_gram'], $it['harga'], $it['jumlah'], $line]);
        $stock->execute([$it['jumlah'], $it['id_varian'], $it['jumlah']]);
        if (!$stock->rowCount()) {
            $pdo->rollBack();
            out(['error' => 'Stok berubah saat checkout. Coba kembali.'], 409);
        }
    }
    $q = $pdo->prepare("INSERT INTO pembayaran(id_pesanan,metode_pembayaran,jumlah_bayar,status_pembayaran) VALUES(?,?,?,'menunggu')");
    $q->execute([$orderId, $payment, $total]);
    $pdo->prepare('DELETE FROM detail_keranjang WHERE id_keranjang=?')->execute([$cr['id_keranjang']]);
    $pdo->commit();
    out(['ok' => true, 'kode_pesanan' => $code, 'total_bayar' => $total, 'status_pembayaran' => 'menunggu', 'catatan' => 'Pesanan dicatat; pembayaran belum diproses otomatis.'], 201);
}
if ($method === 'GET' && $action === 'orders') {
    $id = uid();
    $q = $pdo->prepare('SELECT p.*,b.metode_pembayaran,b.status_pembayaran FROM pesanan p LEFT JOIN pembayaran b ON b.id_pesanan=p.id_pesanan WHERE p.id_user=? ORDER BY p.tanggal_pesanan DESC');
    $q->execute([$id]);
    $orders = $q->fetchAll();
    $q = $pdo->prepare('SELECT dp.* FROM detail_pesanan dp JOIN pesanan p ON p.id_pesanan=dp.id_pesanan WHERE p.id_user=? ORDER BY dp.id_detail_pesanan');
    $q->execute([$id]);
    out(['orders' => $orders, 'items' => $q->fetchAll()]);
}
if ($method === 'POST' && $action === 'refund') {
    $id = uid();
    csrf();
    $d = input();
    $order = (int)($d['id_pesanan'] ?? 0);
    $reason = strv($d, 'alasan', 40);
    $methodR = strv($d, 'metode_refund', 30);
    $desc = strv($d, 'deskripsi', 3000);
    $validR = ['produk_rusak', 'produk_salah', 'tidak_sesuai', 'pesanan_tidak_sampai', 'lainnya'];
    $validM = ['transfer_bank', 'dana', 'ovo', 'gopay', 'shopeepay'];
    if ($order < 1 || !in_array($reason, $validR, true) || !in_array($methodR, $validM, true)) out(['error' => 'Data refund tidak valid.'], 422);
    $q = $pdo->prepare("SELECT p.id_pesanan,p.total_bayar,p.status_pesanan,b.id_pembayaran,b.status_pembayaran FROM pesanan p JOIN pembayaran b ON b.id_pesanan=p.id_pesanan WHERE p.id_pesanan=? AND p.id_user=? ORDER BY b.id_pembayaran DESC LIMIT 1");
    $q->execute([$order, $id]);
    $o = $q->fetch();
    if (!$o) out(['error' => 'Pesanan tidak ditemukan.'], 404);
    if (!in_array($o['status_pesanan'], ['selesai', 'dikirim', 'dibatalkan'], true) || $o['status_pembayaran'] !== 'berhasil') out(['error' => 'Refund hanya bisa diajukan untuk pesanan yang memenuhi syarat dan pembayarannya berstatus berhasil.'], 422);
    $q = $pdo->prepare("SELECT id_refund FROM refund WHERE id_pesanan=? AND id_user=? AND status_refund IN ('diajukan','ditinjau','disetujui','selesai')");
    $q->execute([$order, $id]);
    if ($q->fetch()) out(['error' => 'Sudah ada refund aktif/selesai untuk pesanan ini.'], 409);
    $photo = null;
    if (!empty($d['bukti_foto_data'])) out(['error' => 'Unggah bukti melalui form-data ke endpoint refund-upload; data gambar base64 tidak diterima.'], 422);
    $bank = strv($d, 'nama_bank', 50);
    $account = strv($d, 'no_rekening', 50);
    $holder = strv($d, 'atas_nama', 100);
    if ($methodR === 'transfer_bank' && (!$bank || !$account || !$holder)) out(['error' => 'Lengkapi bank, nomor rekening, dan nama pemilik.'], 422);
    $code = 'RF' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
    $q = $pdo->prepare("INSERT INTO refund(kode_refund,id_pesanan,id_user,id_pembayaran,alasan,deskripsi,bukti_foto,jumlah_refund,metode_refund,nama_bank,no_rekening,atas_nama,status_refund) VALUES(?,?,?,?,?,?,NULL,?,?,?,?,?,'diajukan')");
    $q->execute([$code, $order, $id, $o['id_pembayaran'], $reason, $desc ?: null, $o['total_bayar'], $methodR, $bank ?: null, $account ?: null, $holder ?: null]);
    out(['ok' => true, 'kode_refund' => $code, 'status_refund' => 'diajukan'], 201);
}
if ($method === 'POST' && $action === 'refund-upload') {
    $id = uid();
    csrf();
    $order = (int)($_POST['id_pesanan'] ?? 0);
    $reason = (string)($_POST['alasan'] ?? '');
    $methodR = (string)($_POST['metode_refund'] ?? '');
    $desc = trim((string)($_POST['deskripsi'] ?? ''));
    $validR = ['produk_rusak', 'produk_salah', 'tidak_sesuai', 'pesanan_tidak_sampai', 'lainnya'];
    $validM = ['transfer_bank', 'dana', 'ovo', 'gopay', 'shopeepay'];
    if ($order < 1 || !in_array($reason, $validR, true) || !in_array($methodR, $validM, true)) out(['error' => 'Data refund tidak valid.'], 422);
    if (!isset($_FILES['bukti_foto']) || $_FILES['bukti_foto']['error'] !== UPLOAD_ERR_OK || $_FILES['bukti_foto']['size'] > 4 * 1024 * 1024) out(['error' => 'Bukti foto wajib valid dan maksimal 4 MB.'], 422);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['bukti_foto']['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) out(['error' => 'Format bukti harus JPG, PNG, atau WEBP.'], 422);
    $q = $pdo->prepare("SELECT p.id_pesanan,p.total_bayar,p.status_pesanan,b.id_pembayaran,b.status_pembayaran FROM pesanan p JOIN pembayaran b ON b.id_pesanan=p.id_pesanan WHERE p.id_pesanan=? AND p.id_user=? ORDER BY b.id_pembayaran DESC LIMIT 1");
    $q->execute([$order, $id]);
    $o = $q->fetch();
    if (!$o || !in_array($o['status_pesanan'], ['selesai', 'dikirim', 'dibatalkan'], true) || $o['status_pembayaran'] !== 'berhasil') out(['error' => 'Pesanan tidak ditemukan atau belum memenuhi syarat refund.'], 422);
    $q = $pdo->prepare("SELECT id_refund FROM refund WHERE id_pesanan=? AND id_user=? AND status_refund IN ('diajukan','ditinjau','disetujui','selesai')");
    $q->execute([$order, $id]);
    if ($q->fetch()) out(['error' => 'Sudah ada refund aktif/selesai untuk pesanan ini.'], 409);
    $bank = trim((string)($_POST['nama_bank'] ?? ''));
    $account = trim((string)($_POST['no_rekening'] ?? ''));
    $holder = trim((string)($_POST['atas_nama'] ?? ''));
    if ($methodR === 'transfer_bank' && (!$bank || !$account || !$holder)) out(['error' => 'Lengkapi bank, nomor rekening, dan nama pemilik.'], 422);
    $dir = __DIR__ . '/uploads/refund';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) out(['error' => 'Folder upload tidak dapat dibuat.'], 500);
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['bukti_foto']['tmp_name'], $dir . '/' . $name)) out(['error' => 'Gagal menyimpan bukti.'], 500);
    $photo = 'uploads/refund/' . $name;
    $code = 'RF' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
    $q = $pdo->prepare("INSERT INTO refund(kode_refund,id_pesanan,id_user,id_pembayaran,alasan,deskripsi,bukti_foto,jumlah_refund,metode_refund,nama_bank,no_rekening,atas_nama,status_refund) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,'diajukan')");
    $q->execute([$code, $order, $id, $o['id_pembayaran'], $reason, $desc ?: null, $photo, $o['total_bayar'], $methodR, $bank ?: null, $account ?: null, $holder ?: null]);
    out(['ok' => true, 'kode_refund' => $code, 'status_refund' => 'diajukan'], 201);
}
if ($method === 'GET' && $action === 'refunds') {
    $id = uid();
    $q = $pdo->prepare('SELECT r.*,p.kode_pesanan FROM refund r JOIN pesanan p ON p.id_pesanan=r.id_pesanan WHERE r.id_user=? ORDER BY r.tanggal_pengajuan DESC');
    $q->execute([$id]);
    out(['refunds' => $q->fetchAll()]);
}
// Admin APIs
if (str_starts_with($action, 'admin-')) {
    admin();
    if ($method === 'GET' && $action === 'admin-dashboard') {
        $stats = [];
        foreach (['users', 'produk', 'pesanan', 'refund'] as $t) {
            $stats[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        }
        out(['stats' => $stats]);
    }
    if ($method === 'GET' && $action === 'admin-orders') {
        $q = $pdo->query('SELECT p.*,u.nama,u.email,b.id_pembayaran,b.metode_pembayaran,b.status_pembayaran FROM pesanan p JOIN users u ON u.id_user=p.id_user LEFT JOIN pembayaran b ON b.id_pesanan=p.id_pesanan ORDER BY p.tanggal_pesanan DESC');
        out(['orders' => $q->fetchAll()]);
    }
    if ($method === 'GET' && $action === 'admin-refunds') {
        $q = $pdo->query('SELECT r.*,u.nama,u.email,p.kode_pesanan FROM refund r JOIN users u ON u.id_user=r.id_user JOIN pesanan p ON p.id_pesanan=r.id_pesanan ORDER BY r.tanggal_pengajuan DESC');
        out(['refunds' => $q->fetchAll()]);
    }
    if ($method === 'GET' && $action === 'admin-products') {
        $q = $pdo->query('SELECT * FROM produk ORDER BY id_produk DESC');
        out(['products' => $q->fetchAll()]);
    }
    if ($method === 'POST' && $action === 'admin-refund-status') {
        csrf();
        $d = input();
        $rid = (int)($d['id_refund'] ?? 0);
        $status = strv($d, 'status_refund', 20);
        $note = strv($d, 'catatan_admin', 3000);
        if (!in_array($status, ['ditinjau', 'disetujui', 'ditolak', 'selesai'], true)) out(['error' => 'Status tidak valid.'], 422);
        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT id_pembayaran FROM refund WHERE id_refund=? FOR UPDATE');
        $q->execute([$rid]);
        $r = $q->fetch();
        if (!$r) {
            $pdo->rollBack();
            out(['error' => 'Refund tidak ditemukan.'], 404);
        }
        $q = $pdo->prepare('UPDATE refund SET status_refund=?,catatan_admin=?,tanggal_diproses=NOW() WHERE id_refund=?');
        $q->execute([$status, $note ?: null, $rid]);
        if ($status === 'selesai') $pdo->prepare("UPDATE pembayaran SET status_pembayaran='dikembalikan' WHERE id_pembayaran=?")->execute([$r['id_pembayaran']]);
        $pdo->commit();
        out(['ok' => true]);
    }
    if ($method === 'POST' && $action === 'admin-order-status') {
        csrf();
        $d = input();
        $oid = (int)($d['id_pesanan'] ?? 0);
        $status = strv($d, 'status_pesanan', 20);
        if (!in_array($status, ['menunggu', 'diproses', 'dikirim', 'selesai', 'dibatalkan'], true)) out(['error' => 'Status pesanan tidak valid.'], 422);
        $q = $pdo->prepare('UPDATE pesanan SET status_pesanan=? WHERE id_pesanan=?');
        $q->execute([$status, $oid]);
        out(['ok' => true]);
    }
    if ($method === 'POST' && $action === 'admin-payment-status') {
        csrf();
        $d = input();
        $pid = (int)($d['id_pembayaran'] ?? 0);
        $status = strv($d, 'status_pembayaran', 20);
        if (!in_array($status, ['menunggu', 'berhasil', 'gagal', 'dikembalikan'], true)) out(['error' => 'Status pembayaran tidak valid.'], 422);
        $q = $pdo->prepare('UPDATE pembayaran SET status_pembayaran=? WHERE id_pembayaran=?');
        $q->execute([$status, $pid]);
        out(['ok' => true]);
    }
    if ($method === 'POST' && $action === 'admin-product-save') {
        csrf();
        $d = input();
        $id = (int)($d['id_produk'] ?? 0);
        $name = strv($d, 'nama_produk', 300);
        $desc = strv($d, 'deskripsi', 10000);
        $cat = (int)($d['id_kategori'] ?? 0);
        $price = (float)($d['harga'] ?? 0);
        $stock = (int)($d['stok'] ?? 0);
        $weight = (int)($d['berat_gram'] ?? 0);
        $photo = strv($d, 'foto_produk', 200);
        $rating = (float)($d['rating'] ?? 0);
        $hot = !empty($d['terlaris']) ? 1 : 0;
        $active = ($d['status_produk'] ?? 'aktif') === 'aktif' ? 'aktif' : 'nonaktif';
        if (!$name || $cat < 1 || $price < 0 || $stock < 0 || $weight < 1) out(['error' => 'Data produk tidak valid.'], 422);
        if ($id) {
            $q = $pdo->prepare('UPDATE produk SET id_kategori=?,nama_produk=?,deskripsi=?,harga=?,stok=?,berat_gram=?,foto_produk=?,rating=?,terlaris=?,status_produk=? WHERE id_produk=?');
            $q->execute([$cat, $name, $desc, $price, $stock, $weight, $photo, $rating, $hot, $active, $id]);
        } else {
            $q = $pdo->prepare('INSERT INTO produk(id_kategori,nama_produk,deskripsi,harga,stok,berat_gram,foto_produk,rating,terlaris,status_produk,tanggal_dibuat) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())');
            $q->execute([$cat, $name, $desc, $price, $stock, $weight, $photo, $rating, $hot, $active]);
        }
        out(['ok' => true]);
    }
}
out(['error' => 'Endpoint tidak ditemukan.'], 404);
