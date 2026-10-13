<?php

session_start();

require_once 'config/koneksi.php';
require_once __DIR__ . '/home_data.php';


// ==================================================
// CEK LOGIN
// ==================================================

if (!isset($_SESSION['user_id'])) {

    header('Location: login.php');

    exit;
}


$user_id = $_SESSION['user_id'];


// ==================================================
// MODE DEMO: PESANAN OTOMATIS LANGSUNG "SELESAI"
// Ubah ke false untuk mengembalikan alur normal.
// ==================================================

$MODE_DEMO = true;

if ($MODE_DEMO) {

    try {

        // Pembayaran yang masih menunggu -> Berhasil
        $pdo->prepare("
            UPDATE payments p
            JOIN orders o ON o.id = p.order_id
            SET p.status = 'Berhasil'
            WHERE o.user_id = ?
              AND p.status = 'Menunggu'
        ")->execute([$user_id]);

        // Pesanan (selain yang batal/gagal) -> Selesai
        $pdo->prepare("
            UPDATE orders
            SET status = 'Selesai'
            WHERE user_id = ?
              AND (
                    status IS NULL
                    OR status NOT IN ('Selesai', 'Dibatalkan', 'Batal', 'Gagal')
                  )
        ")->execute([$user_id]);

    } catch (PDOException $e) {
        // Abaikan agar halaman tetap tampil
    }
}


// ==================================================
// AMBIL DATA RIWAYAT PESANAN
// ==================================================

$sql = "
    SELECT
        o.id,
        o.total,
        o.status,
        o.created_at,

        p.metode,
        p.jumlah AS payment_amount,
        p.status AS payment_status,
        p.tanggal AS payment_date

    FROM orders o

    LEFT JOIN payments p
        ON p.order_id = o.id

    WHERE o.user_id = ?

    ORDER BY o.created_at DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute([
    $user_id
]);


$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ==================================================
// FORMAT RUPIAH
// ==================================================

function rupiah($angka)
{
    return 'Rp ' . number_format(
        (float) $angka,
        0,
        ',',
        '.'
    );
}


// ==================================================
// ESCAPE HTML
// ==================================================

function e($text)
{
    return htmlspecialchars(
        (string) $text,
        ENT_QUOTES,
        'UTF-8'
    );
}


// ==================================================
// STATUS PESANAN
// ==================================================

function statusClass($status)
{
    $status = strtolower((string) $status);


    if (
        strpos($status, 'selesai') !== false ||
        strpos($status, 'dikirim') !== false ||
        strpos($status, 'diproses') !== false ||
        strpos($status, 'berhasil') !== false ||
        strpos($status, 'lunas') !== false
    ) {

        return 'success';

    }


    if (
        strpos($status, 'batal') !== false ||
        strpos($status, 'gagal') !== false ||
        strpos($status, 'cancel') !== false
    ) {

        return 'danger';

    }


    return 'pending';
}


// ==================================================
// FORMAT METODE PEMBAYARAN
// ==================================================

function formatPayment($metode)
{
    if (empty($metode)) {

        return 'Belum dipilih';

    }


    return $metode;
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Riwayat Pesanan - Joyzone Store
    </title>
    <link rel="icon" type="image/png" href="assets/images/logo-joyzone.png">


    <link rel="stylesheet" href="riwayat_pesanan.css?v=1">
    <link rel="stylesheet" href="style.css?v=12">
    <link rel="stylesheet" href="banner.css">
    <link rel="stylesheet" href="index.css?v=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="home.css?v=1">
    <link rel="stylesheet" href="menu-fix.css?v=1">

</head>


<body class="home">


<!-- ==========================================
     HEADER
========================================== -->

<?php require __DIR__ . '/header_home.php'; ?>


<!-- ==========================================
     CONTENT
========================================== -->

<main class="container">


    <h1 class="title">

        Riwayat Pesanan

    </h1>


    <p class="subtitle">

        Lihat semua pesanan yang pernah Anda lakukan.

    </p>


    <?php if (empty($orders)): ?>


        <!-- ======================================
             BELUM ADA PESANAN
        ======================================= -->

        <div class="empty">


            <div class="empty-icon">
                🛍️
            </div>


            <div class="empty-title">

                Belum Ada Pesanan

            </div>


            <div class="empty-text">

                Anda belum memiliki riwayat pesanan.

            </div>


            <a
                href="index.php"
                class="shop-button"
            >

                Mulai Belanja

            </a>


        </div>


    <?php else: ?>


        <!-- ======================================
             DAFTAR PESANAN
        ======================================= -->

        <?php foreach ($orders as $order): ?>


            <?php

            $orderStatus =
                $order['status']
                ?: 'Menunggu Pembayaran';


            $paymentStatus =
                $order['payment_status']
                ?: 'Menunggu';


            $paymentMethod =
                formatPayment(
                    $order['metode']
                );

            ?>


            <div class="order-card">


                <!-- ==================================
                     BAGIAN ATAS
                =================================== -->

                <div class="order-top">


                    <div>


                        <div class="order-number">

                            Pesanan #<?= e(
                                $order['id']
                            ) ?>

                        </div>


                        <div class="order-date">

                            <?= e(
                                date(
                                    'd/m/Y H:i',
                                    strtotime(
                                        $order['created_at']
                                    )
                                )
                            ) ?>

                        </div>


                    </div>


                    <span
                        class="status
                        <?= e(
                            statusClass(
                                $orderStatus
                            )
                        ) ?>"
                    >

                        <?= e(
                            $orderStatus
                        ) ?>

                    </span>


                </div>


                <!-- ==================================
                     INFORMASI PESANAN
                =================================== -->

                <div class="order-body">


                    <!-- TOTAL -->

                    <div class="info-row">


                        <div class="info-label">

                            Total Pesanan

                        </div>


                        <div
                            class="info-value total"
                        >

                            <?= rupiah(
                                $order['total']
                            ) ?>

                        </div>


                    </div>


                    <!-- METODE PEMBAYARAN -->

                    <div class="info-row">


                        <div class="info-label">

                            Metode Pembayaran

                        </div>


                        <div class="info-value">


                            <span
                                class="payment-method"
                            >

                                <?= e(
                                    $paymentMethod
                                ) ?>

                            </span>


                        </div>


                    </div>


                    <!-- STATUS PEMBAYARAN -->

                    <div class="info-row">


                        <div class="info-label">

                            Status Pembayaran

                        </div>


                        <div class="info-value">


                            <span
                                class="status
                                <?= e(
                                    statusClass(
                                        $paymentStatus
                                    )
                                ) ?>"
                            >

                                <?= e(
                                    $paymentStatus
                                ) ?>

                            </span>


                        </div>


                    </div>


                    <!-- JUMLAH PEMBAYARAN -->

                    <?php if (
                        $order['payment_amount']
                        !== null
                    ): ?>


                        <div class="info-row">


                            <div class="info-label">

                                Jumlah Pembayaran

                            </div>


                            <div class="info-value">

                                <?= rupiah(
                                    $order[
                                        'payment_amount'
                                    ]
                                ) ?>

                            </div>


                        </div>


                    <?php endif; ?>


                    <!-- ==================================
                         BUTTON DETAIL
                    =================================== -->

                    <div class="order-footer">


                        <a
                            href="detail_pesanan.php?id=<?= (int) $order['id'] ?>"
                            class="detail-button"
                        >

                            Lihat Detail Pesanan →

                        </a>


                    </div>


                </div>


            </div>


        <?php endforeach; ?>


    <?php endif; ?>


</main>


<?php require __DIR__ . '/footer_home.php'; ?>
</body>

</html>