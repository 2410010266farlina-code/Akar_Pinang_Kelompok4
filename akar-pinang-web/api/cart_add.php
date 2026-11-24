
<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

try {
    // Pastikan pengguna sudah login
    if (!user()) {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'Silakan login terlebih dahulu'
        ]);
        exit;
    }

    // Pastikan request menggunakan POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);

        echo json_encode([
            'success' => false,
            'message' => 'Metode request tidak diizinkan'
        ]);
        exit;
    }

    // Ambil data JSON
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        throw new Exception('Format data JSON tidak valid');
    }

    $id_varian = (int)($input['id_varian'] ?? 0);
    $jumlah = (int)($input['jumlah'] ?? 1);

    if ($id_varian <= 0 || $jumlah <= 0) {
        throw new Exception('ID varian dan jumlah harus valid');
    }

    // Periksa varian dan stok
    $varian = q(
        'SELECT id_varian, stok
         FROM varian_produk
         WHERE id_varian = ?',
        [$id_varian]
    )->fetch();

    if (!$varian) {
        throw new Exception('Varian produk tidak ditemukan');
    }

    if ($jumlah > (int)$varian['stok']) {
        throw new Exception('Jumlah melebihi stok tersedia');
    }

    // Ambil atau buat keranjang pengguna
    $id_keranjang = cart_id();

    // Periksa apakah varian sudah ada di keranjang
    $detail = q(
        'SELECT id_detail_keranjang, jumlah
         FROM detail_keranjang
         WHERE id_keranjang = ? AND id_varian = ?',
        [$id_keranjang, $id_varian]
    )->fetch();

    if ($detail) {
        $jumlah_baru = (int)$detail['jumlah'] + $jumlah;

        if ($jumlah_baru > (int)$varian['stok']) {
            throw new Exception(
                'Total jumlah melebihi stok tersedia'
            );
        }

        q(
            'UPDATE detail_keranjang
             SET jumlah = ?
             WHERE id_detail_keranjang = ?',
            [$jumlah_baru, $detail['id_detail_keranjang']]
        );
    } else {
        q(
            'INSERT INTO detail_keranjang
             (id_keranjang, id_varian, jumlah)
             VALUES (?, ?, ?)',
            [$id_keranjang, $id_varian, $jumlah]
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Produk berhasil ditambahkan ke keranjang'
    ]);
} catch (Exception $e) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
