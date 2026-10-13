<?php

session_start();

require_once "config/koneksi.php";

/*
|--------------------------------------------------------------------------
| CEK LOGIN
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| CEK KERANJANG
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['keranjang']) ||
    empty($_SESSION['keranjang'])
) {
    header("Location: cart.php");
    exit;
}

$keranjang = $_SESSION['keranjang'];

/*
|--------------------------------------------------------------------------
| AMBIL METODE PEMBAYARAN
|--------------------------------------------------------------------------
*/
$metodePembayaran = $_POST['metode_pembayaran'] ?? '';

if ($metodePembayaran === '') {
    die("Metode pembayaran wajib dipilih.");
}

/*
|--------------------------------------------------------------------------
| VALIDASI METODE & CHANNEL PEMBAYARAN
|--------------------------------------------------------------------------
*/
$metode = '';
$channel = '';

/*
|--------------------------------------------------------------------------
| VIRTUAL ACCOUNT
|--------------------------------------------------------------------------
*/
if (strpos($metodePembayaran, 'Virtual Account -') === 0) {

    $metode = 'Virtual Account';

    $channel = trim(
        str_replace(
            'Virtual Account -',
            '',
            $metodePembayaran
        )
    );

    $allowedChannels = [
        'Mandiri',
        'BCA',
        'BNI',
        'BRI'
    ];

    if (!in_array($channel, $allowedChannels, true)) {
        die("Virtual Account tidak valid.");
    }

/*
|--------------------------------------------------------------------------
| E-WALLET
|--------------------------------------------------------------------------
*/
} elseif (strpos($metodePembayaran, 'E-Wallet -') === 0) {

    $metode = 'E-Wallet';

    $channel = trim(
        str_replace(
            'E-Wallet -',
            '',
            $metodePembayaran
        )
    );

    $allowedChannels = [
        'GoPay',
        'OVO',
        'ShopeePay'
    ];

    if (!in_array($channel, $allowedChannels, true)) {
        die("E-Wallet tidak valid.");
    }

} else {

    die("Metode pembayaran tidak valid.");

}

/*
|--------------------------------------------------------------------------
| PROSES CHECKOUT DENGAN TRANSACTION
|--------------------------------------------------------------------------
*/
try {

    $pdo->beginTransaction();

    $total = 0;
    $produkCheckout = [];

    /*
    |--------------------------------------------------------------------------
    | CEK PRODUK, HARGA DAN STOK
    |--------------------------------------------------------------------------
    */
    foreach ($keranjang as $product_id => $jumlah) {

        $product_id = (int) $product_id;
        $jumlah = (int) $jumlah;

        if ($product_id <= 0) {
            throw new Exception(
                "ID produk tidak valid."
            );
        }

        if ($jumlah <= 0) {
            throw new Exception(
                "Jumlah produk tidak valid."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | LOCK PRODUK SAAT CHECKOUT
        |--------------------------------------------------------------------------
        */
        $stmtProduct = $pdo->prepare("
            SELECT
                id,
                nama,
                harga,
                stok
            FROM products
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmtProduct->execute([
            $product_id
        ]);

        $product = $stmtProduct->fetch(
            PDO::FETCH_ASSOC
        );

        /*
        |--------------------------------------------------------------------------
        | PRODUK TIDAK DITEMUKAN
        |--------------------------------------------------------------------------
        */
        if (!$product) {
            throw new Exception(
                "Produk dengan ID {$product_id} tidak ditemukan."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | CEK STOK
        |--------------------------------------------------------------------------
        */
        if ($jumlah > (int) $product['stok']) {

            throw new Exception(
                "Stok produk " .
                $product['nama'] .
                " tidak mencukupi. " .
                "Stok tersedia: " .
                $product['stok']
            );

        }

        /*
        |--------------------------------------------------------------------------
        | HITUNG SUBTOTAL
        |--------------------------------------------------------------------------
        */
        $harga = (float) $product['harga'];

        $subtotal = $harga * $jumlah;

        $total += $subtotal;

        /*
        |--------------------------------------------------------------------------
        | SIMPAN DATA PRODUK CHECKOUT
        |--------------------------------------------------------------------------
        */
        $produkCheckout[] = [

            'id' => (int) $product['id'],

            'nama' => $product['nama'],

            'harga' => $harga,

            'stok' => (int) $product['stok'],

            'jumlah' => $jumlah,

            'subtotal' => $subtotal

        ];
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDASI TOTAL
    |--------------------------------------------------------------------------
    */
    if ($total <= 0) {
        throw new Exception(
            "Total pembayaran tidak valid."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BUAT ORDER
    |--------------------------------------------------------------------------
    */
    $stmtOrder = $pdo->prepare("
        INSERT INTO orders
        (
            user_id,
            total,
            status
        )
        VALUES
        (
            ?,
            ?,
            'Menunggu Pembayaran'
        )
    ");

    $stmtOrder->execute([
        $user_id,
        $total
    ]);

    /*
    |--------------------------------------------------------------------------
    | AMBIL ID ORDER
    |--------------------------------------------------------------------------
    */
    $order_id = $pdo->lastInsertId();

    if (!$order_id) {
        throw new Exception(
            "Gagal membuat pesanan."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN DETAIL ORDER
    |--------------------------------------------------------------------------
    */
    $stmtDetail = $pdo->prepare("
        INSERT INTO order_details
        (
            order_id,
            product_id,
            jumlah,
            harga
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?
        )
    ");

    foreach ($produkCheckout as $item) {

        $stmtDetail->execute([
            $order_id,
            $item['id'],
            $item['jumlah'],
            $item['harga']
        ]);

    }

    /*
    |--------------------------------------------------------------------------
    | KURANGI STOK PRODUK
    |--------------------------------------------------------------------------
    */
    $stmtStock = $pdo->prepare("
        UPDATE products
        SET stok = stok - ?
        WHERE id = ?
    ");

    foreach ($produkCheckout as $item) {

        $stmtStock->execute([
            $item['jumlah'],
            $item['id']
        ]);

    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN DATA PEMBAYARAN
    |--------------------------------------------------------------------------
    |
    | Status awal pembayaran:
    | Menunggu
    |
    */
    $stmtPayment = $pdo->prepare("
        INSERT INTO payments
        (
            order_id,
            metode,
            channel,
            jumlah,
            status
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            'Menunggu'
        )
    ");

    $stmtPayment->execute([
        $order_id,
        $metode,
        $channel,
        $total
    ]);

    /*
    |--------------------------------------------------------------------------
    | SIMPAN ORDER ID KE SESSION
    |--------------------------------------------------------------------------
    */
    $_SESSION['order_id'] = $order_id;

    /*
    |--------------------------------------------------------------------------
    | KOSONGKAN KERANJANG
    |--------------------------------------------------------------------------
    */
    unset($_SESSION['keranjang']);

    /*
    |--------------------------------------------------------------------------
    | SELESAIKAN TRANSACTION
    |--------------------------------------------------------------------------
    */
    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | REDIRECT KE HALAMAN PEMBAYARAN
    |--------------------------------------------------------------------------
    */
    header("Location: payment.php");
    exit;

} catch (Exception $e) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK JIKA TERJADI ERROR
    |--------------------------------------------------------------------------
    */
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    /*
    |--------------------------------------------------------------------------
    | TAMPILKAN ERROR
    |--------------------------------------------------------------------------
    */
    die(
        "Checkout gagal: " .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );

}