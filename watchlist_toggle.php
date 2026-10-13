<?php

session_start();

require_once 'config/koneksi.php';

/* =========================================================
   TAMBAH / HAPUS PRODUK DARI WATCHLIST
   Dipanggil lewat fetch() (AJAX) dari index.php,
   detail_produk.php, dan watchlist.php.
========================================================= */

header('Content-Type: application/json; charset=utf-8');

function balas(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    balas(['ok' => false, 'pesan' => 'Metode tidak valid.'], 405);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    // Belum login -> halaman akan mengarahkan ke login.php
    balas(['ok' => false, 'login' => true, 'pesan' => 'Silakan login terlebih dahulu.'], 401);
}

$productId = (int) ($_POST['product_id'] ?? 0);

if ($productId <= 0) {
    balas(['ok' => false, 'pesan' => 'Produk tidak valid.'], 400);
}

try {

    // Pastikan produk ada
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$productId]);

    if (!$stmt->fetch()) {
        balas(['ok' => false, 'pesan' => 'Produk tidak ditemukan.'], 404);
    }

    // Sudah ada di watchlist? Kalau ya hapus, kalau belum tambahkan.
    $stmt = $pdo->prepare("SELECT id FROM watchlist WHERE user_id = ? AND product_id = ? LIMIT 1");
    $stmt->execute([$userId, $productId]);
    $ada = $stmt->fetch();

    // Opsional: aksi=hapus -> hanya menghapus, aksi=tambah -> hanya menambah.
    // Tanpa aksi -> toggle (kalau ada dihapus, kalau belum ditambah).
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        $hapus = true;
    } elseif ($aksi === 'tambah') {
        $hapus = false;
    } else {
        $hapus = (bool) $ada;
    }

    if ($hapus) {

        $stmt = $pdo->prepare("DELETE FROM watchlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        $aktif = false;

    } else {

        $stmt = $pdo->prepare("INSERT IGNORE INTO watchlist (user_id, product_id) VALUES (?, ?)");
        $stmt->execute([$userId, $productId]);
        $aktif = true;

    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM watchlist WHERE user_id = ?");
    $stmt->execute([$userId]);
    $total = (int) $stmt->fetchColumn();

    balas(['ok' => true, 'aktif' => $aktif, 'total' => $total]);

} catch (PDOException $e) {

    balas(['ok' => false, 'pesan' => 'Gagal memproses watchlist. Pastikan tabel watchlist sudah dibuat.'], 500);

}
