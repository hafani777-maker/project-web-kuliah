<?php

session_start();

require_once "config/koneksi.php";
require_once __DIR__ . "/terakhir_dilihat.php";

/* =========================================================
   CEK LOGIN
========================================================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

/* =========================================================
   HARUS DIAKSES LEWAT FORM (POST) DARI cart.php
========================================================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cart.php");
    exit;
}

/* =========================================================
   CEK KERANJANG
   Menggunakan session "cart" (sama seperti cart.php)
   Format: [product_id => ['nama', 'harga', 'jumlah', ...]]
========================================================= */
$idProdukPost = (int) ($_POST['id_produk'] ?? 0);
$langsung = ($idProdukPost > 0) || (($_POST['mode'] ?? '') === 'langsung');

if ($langsung) {
    // Beli langsung: produk dari form (atau session buy_now sebagai cadangan)
    $idProduk = $idProdukPost > 0
        ? $idProdukPost
        : (int) ($_SESSION['buy_now']['product_id'] ?? 0);

    if ($idProduk <= 0) {
        header("Location: katalog.php");
        exit;
    }

    $qty = (int) ($_POST['jumlah'] ?? $_POST['qty'] ?? 1);

    $keranjang = [
        $idProduk => ['jumlah' => max(1, $qty)]
    ];
} else {
    if (empty($_SESSION['cart'])) {
        header("Location: cart.php");
        exit;
    }
    $keranjang = $_SESSION['cart'];
}

/* =========================================================
   AMBIL & VALIDASI METODE PEMBAYARAN
========================================================= */
$metodePembayaran = trim($_POST['metode_pembayaran'] ?? '');

if ($metodePembayaran === '') {
    die("Metode pembayaran wajib dipilih. <a href='cart.php'>Kembali</a>");
}

$metode  = '';
$channel = '';

if (strpos($metodePembayaran, 'Virtual Account -') === 0) {

    $metode  = 'Virtual Account';
    $channel = trim(str_replace('Virtual Account -', '', $metodePembayaran));

    if (!in_array($channel, ['Mandiri', 'BCA', 'BNI', 'BRI'], true)) {
        die("Virtual Account tidak valid.");
    }

} elseif (strpos($metodePembayaran, 'E-Wallet -') === 0) {

    $metode  = 'E-Wallet';
    $channel = trim(str_replace('E-Wallet -', '', $metodePembayaran));

    if (!in_array($channel, ['GoPay', 'OVO', 'ShopeePay'], true)) {
        die("E-Wallet tidak valid.");
    }

} else {

    die("Metode pembayaran tidak valid.");

}

/* =========================================================
   PROSES CHECKOUT DENGAN TRANSACTION
========================================================= */
try {

    $pdo->beginTransaction();

    $total          = 0;
    $produkCheckout = [];

    $stmtProduct = $pdo->prepare("
        SELECT id, nama, harga, diskon_persen, stok
        FROM products
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    foreach ($keranjang as $product_id => $item) {

        $product_id = (int) $product_id;
        $jumlah     = (int) ($item['jumlah'] ?? 0);

        if ($product_id <= 0) {
            throw new Exception("ID produk tidak valid.");
        }

        if ($jumlah <= 0) {
            throw new Exception("Jumlah produk tidak valid.");
        }

        $stmtProduct->execute([$product_id]);
        $product = $stmtProduct->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception("Produk dengan ID {$product_id} tidak ditemukan.");
        }

        if ($jumlah > (int) $product['stok']) {
            throw new Exception(
                "Stok produk " . $product['nama'] .
                " tidak mencukupi. Stok tersedia: " . $product['stok']
            );
        }

        /*
         * Harga selalu diambil dari database (bukan dari session)
         * supaya tidak bisa dimanipulasi. Diskon produk (jika ada)
         * sudah diterapkan di sini.
         */
        $diskon   = (int) ($product['diskon_persen'] ?? 0);
        $harga    = round((float) $product['harga'] * (100 - $diskon) / 100);
        $subtotal = $harga * $jumlah;
        $total   += $subtotal;

        $produkCheckout[] = [
            'id'       => (int) $product['id'],
            'nama'     => $product['nama'],
            'harga'    => $harga,
            'jumlah'   => $jumlah,
            'subtotal' => $subtotal
        ];
    }

    if ($total <= 0) {
        throw new Exception("Total pembayaran tidak valid.");
    }

    /* BUAT ORDER */
    $stmtOrder = $pdo->prepare("
        INSERT INTO orders (user_id, total, status)
        VALUES (?, ?, 'Menunggu Pembayaran')
    ");
    $stmtOrder->execute([$user_id, $total]);

    $order_id = $pdo->lastInsertId();

    if (!$order_id) {
        throw new Exception("Gagal membuat pesanan.");
    }

    /* DETAIL ORDER + KURANGI STOK */
    $stmtDetail = $pdo->prepare("
        INSERT INTO order_details (order_id, product_id, jumlah, harga)
        VALUES (?, ?, ?, ?)
    ");

    $stmtStock = $pdo->prepare("
        UPDATE products SET stok = stok - ? WHERE id = ?
    ");

    foreach ($produkCheckout as $item) {

        $stmtDetail->execute([
            $order_id,
            $item['id'],
            $item['jumlah'],
            $item['harga']
        ]);

        $stmtStock->execute([
            $item['jumlah'],
            $item['id']
        ]);

    }

    /* DATA PEMBAYARAN */
    $stmtPayment = $pdo->prepare("
        INSERT INTO payments (order_id, metode, channel, jumlah, status)
        VALUES (?, ?, ?, ?, 'Menunggu')
    ");
    $stmtPayment->execute([$order_id, $metode, $channel, $total]);

    $pdo->commit();

    /* Catat produk yang dipesan (fitur "Terakhir Dilihat & Dipesan") */
    foreach ($produkCheckout as $itemDipesan) {
        catatDilihat($pdo, $itemDipesan['id']);
    }

    /* SESSION: simpan order, kosongkan troli (setelah commit sukses) */
    $_SESSION['order_id'] = $order_id;
    if ($langsung) {
        unset($_SESSION['buy_now']);   // troli lama tetap utuh
    } else {
        unset($_SESSION['cart']);
    }

    header("Location: payment.php");
    exit;

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die(
        "Checkout gagal: " .
        htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') .
        " <a href='cart.php'>Kembali ke troli</a>"
    );

}
