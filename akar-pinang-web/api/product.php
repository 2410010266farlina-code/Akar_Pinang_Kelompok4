
<?php

header('Content-Type: application/json; charset=utf-8');

try {
    // Ambil konfigurasi database
    $config = require __DIR__ . '/../backend/config.php';

    // Buat koneksi PDO
    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";

    $pdo = new PDO(
        $dsn,
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Ambil data produk
    $stmt = $pdo->prepare("
        SELECT *
        FROM produk
        ORDER BY id_produk DESC
    ");

    $stmt->execute();
    $produk = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'message' => 'Data produk berhasil diambil',
        'total' => count($produk),
        'data' => $produk
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
