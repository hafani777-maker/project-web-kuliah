<?php

session_start();

require_once "config/koneksi.php";


/*
|--------------------------------------------------------------------------
| CEK LOGIN DAN ORDER
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["order_id"]) ||
    !isset($_SESSION["user_id"])
) {
    header("Location: index.php");
    exit;
}


$order_id = (int) $_SESSION["order_id"];
$user_id  = (int) $_SESSION["user_id"];


try {

    /*
    |--------------------------------------------------------------------------
    | MULAI TRANSAKSI
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | CEK ORDER MILIK USER
    |--------------------------------------------------------------------------
    */

    $query = $pdo->prepare("
        SELECT
            id,
            user_id,
            total,
            status,
            created_at
        FROM orders
        WHERE id = ?
        AND user_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $query->execute([
        $order_id,
        $user_id
    ]);

    $order = $query->fetch(PDO::FETCH_ASSOC);


    if (!$order) {

        $pdo->rollBack();

        die("Pesanan tidak ditemukan.");

    }


    /*
    |--------------------------------------------------------------------------
    | AMBIL PAYMENT
    |--------------------------------------------------------------------------
    */

    $query = $pdo->prepare("
        SELECT
            id,
            metode,
            channel,
            jumlah,
            status,
            tanggal
        FROM payments
        WHERE order_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $query->execute([
        $order_id
    ]);

    $payment = $query->fetch(PDO::FETCH_ASSOC);


    if (!$payment) {

        $pdo->rollBack();

        die("Data pembayaran tidak ditemukan.");

    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE PEMBAYARAN
    |--------------------------------------------------------------------------
    */

    $query = $pdo->prepare("
        UPDATE payments
        SET status = 'Dibayar'
        WHERE order_id = ?
    ");

    $query->execute([
        $order_id
    ]);


    /*
    |--------------------------------------------------------------------------
    | UPDATE STATUS PESANAN
    |--------------------------------------------------------------------------
    */

    $query = $pdo->prepare("
        UPDATE orders
        SET status = 'Diproses'
        WHERE id = ?
        AND user_id = ?
    ");

    $query->execute([
        $order_id,
        $user_id
    ]);


    /*
    |--------------------------------------------------------------------------
    | AMBIL DETAIL PRODUK
    |--------------------------------------------------------------------------
    */

    $query = $pdo->prepare("
        SELECT
            od.product_id,
            od.jumlah,
            od.harga,
            p.nama,
            p.gambar
        FROM order_details od
        LEFT JOIN products p
            ON p.id = od.product_id
        WHERE od.order_id = ?
        ORDER BY od.id ASC
    ");

    $query->execute([
        $order_id
    ]);

    $items = $query->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | HAPUS SESSION ORDER
    |--------------------------------------------------------------------------
    */

    unset($_SESSION["order_id"]);


} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die(
        "Pembayaran gagal: " .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            "UTF-8"
        )
    );

}


/*
|--------------------------------------------------------------------------
| FORMAT PEMBAYARAN
|--------------------------------------------------------------------------
*/

$metode  = $payment["metode"] ?? "";
$channel = $payment["channel"] ?? "";


/*
|--------------------------------------------------------------------------
| NAMA PEMBAYARAN
|--------------------------------------------------------------------------
*/

$namaPembayaran = $metode;

if ($channel !== "") {
    $namaPembayaran .= " - " . $channel;
}


/*
|--------------------------------------------------------------------------
| NOMOR SIMULASI
|--------------------------------------------------------------------------
*/

$nomorPembayaran = "081234567890";


if ($metode === "Virtual Account") {

    $nomorPembayaran =
        "8800" .
        str_pad(
            (string) $order_id,
            8,
            "0",
            STR_PAD_LEFT
        );

}


/*
|--------------------------------------------------------------------------
| FORMAT TANGGAL
|--------------------------------------------------------------------------
*/

$tanggalOrder = date(
    "d F Y",
    strtotime($order["created_at"])
);

$jamOrder = date(
    "H:i",
    strtotime($order["created_at"])
);


/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

$total = (float) $order["total"];

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
        Pembayaran Berhasil - Joyzone Store
    </title>


    <style>

        /* =========================================================
           RESET
        ========================================================= */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #f5f9ff 0%,
                    #f8fbff 50%,
                    #eef5ff 100%
                );

            color: #1d2d44;

            min-height: 100vh;

        }


        a {
            text-decoration: none;
        }


        /* =========================================================
           HEADER
        ========================================================= */

        .site-header {

            height: 82px;

            background: #172536;

            color: white;

            display: flex;

            align-items: center;

            padding: 0 6%;

            box-shadow:
                0 4px 20px rgba(0,0,0,.08);

        }


        .brand {

            display: flex;

            align-items: center;

            gap: 14px;

            color: white;

        }


        .brand-mark {

            width: 52px;
            height: 52px;

            border: 2px solid rgba(255,255,255,.8);

            border-radius: 14px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 25px;

            font-weight: 800;

        }


        .brand-text {

            display: flex;

            flex-direction: column;

        }


        .brand-text strong {

            font-size: 20px;

            letter-spacing: .3px;

        }


        .brand-text span {

            font-size: 10px;

            letter-spacing: 3px;

            color: #b9c6d6;

            margin-top: 3px;

        }


        /* =========================================================
           NAVIGATION
        ========================================================= */

        .nav {

            margin-left: auto;

            display: flex;

            align-items: center;

            gap: 14px;

        }


        .nav a {

            color: #dbe5ef;

            padding: 11px 15px;

            border-radius: 10px;

            font-size: 14px;

            transition: .2s;

        }


        .nav a:hover {

            background: rgba(255,255,255,.08);

            color: white;

        }


        /* =========================================================
           PAGE
        ========================================================= */

        .page {

            width: min(1180px, 92%);

            margin: 42px auto;

        }


        /* =========================================================
           SUCCESS HEADER
        ========================================================= */

        .success-card {

            background: white;

            border-radius: 24px;

            padding: 32px;

            box-shadow:
                0 15px 50px rgba(37, 65, 96, .10);

            border: 1px solid #e4ebf3;

        }


        .success-top {

            display: grid;

            grid-template-columns:
                1fr
                380px;

            gap: 28px;

            align-items: center;

        }


        .success-left {

            display: flex;

            align-items: center;

            gap: 24px;

        }


        .success-icon {

            width: 105px;
            height: 105px;

            min-width: 105px;

            border-radius: 50%;

            background:
                #dcfce7;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 54px;

            color: #16a34a;

            box-shadow:
                0 0 0 12px #f0fdf4;

        }


        .success-label {

            display: inline-block;

            background: #dcfce7;

            color: #15803d;

            padding: 8px 15px;

            border-radius: 30px;

            font-size: 13px;

            font-weight: 800;

            letter-spacing: .4px;

            margin-bottom: 10px;

        }


        .success-left h1 {

            font-size: 38px;

            line-height: 1.1;

            margin-bottom: 10px;

            color: #172b45;

        }


        .success-left p {

            color: #667892;

            font-size: 16px;

            line-height: 1.7;

            max-width: 550px;

        }


        /* =========================================================
           ORDER INFO
        ========================================================= */

        .order-info {

            background:
                linear-gradient(
                    135deg,
                    #effcf4,
                    #f5fbff
                );

            border: 1px solid #dcefe5;

            border-radius: 18px;

            padding: 22px;

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 20px;

        }


        .order-info-item {

            display: flex;

            flex-direction: column;

            gap: 6px;

        }


        .order-info-item span {

            font-size: 13px;

            color: #6d829c;

        }


        .order-info-item strong {

            font-size: 22px;

            color: #183451;

        }


        .order-info-item small {

            color: #66809b;

        }


        /* =========================================================
           MAIN CONTENT
        ========================================================= */

        .content-grid {

            display: grid;

            grid-template-columns:
                minmax(0, 1fr)
                340px;

            gap: 24px;

            margin-top: 28px;

        }


        .card {

            background: white;

            border: 1px solid #e3eaf2;

            border-radius: 20px;

            padding: 25px;

            box-shadow:
                0 8px 30px rgba(37,65,96,.06);

        }


        .card-title {

            display: flex;

            align-items: center;

            gap: 12px;

            font-size: 19px;

            font-weight: 800;

            color: #193554;

            padding-bottom: 18px;

            border-bottom: 1px solid #edf1f5;

            margin-bottom: 20px;

        }


        .card-title-icon {

            width: 38px;
            height: 38px;

            background: #edf5ff;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;

        }


        /* =========================================================
           DETAIL PEMBAYARAN
        ========================================================= */

        .detail-list {

            display: grid;

            gap: 15px;

        }


        .detail-row {

            display: grid;

            grid-template-columns: 190px 1fr;

            gap: 20px;

            align-items: center;

        }


        .detail-row .label {

            color: #6a819d;

            font-size: 14px;

        }


        .detail-row .value {

            font-weight: 700;

            color: #203b5b;

        }


        .badge {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 7px 12px;

            border-radius: 30px;

            font-size: 13px;

            font-weight: 700;

            width: fit-content;

        }


        .badge-success {

            background: #dcfce7;

            color: #15803d;

        }


        .badge-process {

            background: #e0f2fe;

            color: #0284c7;

        }


        /* =========================================================
           PAYMENT BOX
        ========================================================= */

        .payment-box {

            margin-top: 25px;

            padding: 22px;

            border-radius: 18px;

            background:
                linear-gradient(
                    135deg,
                    #eff8ff,
                    #f2fbff
                );

            border: 1px solid #d9ecfa;

            display: grid;

            grid-template-columns: 1fr 1px 1fr;

            gap: 22px;

            align-items: center;

        }


        .payment-method {

            display: flex;

            align-items: center;

            gap: 15px;

        }


        .payment-icon {

            width: 58px;
            height: 58px;

            border-radius: 15px;

            background: #ff5722;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 27px;

            font-weight: 800;

        }


        .payment-method span {

            display: block;

            color: #6c8199;

            font-size: 13px;

            margin-bottom: 5px;

        }


        .payment-method strong {

            font-size: 18px;

            color: #183655;

        }


        .payment-number {

            border-left: 1px solid #d4e3ee;

            padding-left: 22px;

        }


        .payment-number span {

            display: block;

            font-size: 13px;

            color: #6c8199;

            margin-bottom: 6px;

        }


        .payment-number strong {

            font-size: 21px;

            letter-spacing: 1px;

            color: #193a5d;

        }


        /* =========================================================
           ORDER SUMMARY
        ========================================================= */

        .product-list {

            display: grid;

            gap: 15px;

        }


        .product-item {

            display: flex;

            align-items: center;

            gap: 13px;

            padding-bottom: 15px;

            border-bottom: 1px solid #edf1f5;

        }


        .product-image {

            width: 64px;
            height: 64px;

            border-radius: 12px;

            background: #f1f5f9;

            display: flex;

            align-items: center;

            justify-content: center;

            overflow: hidden;

            flex-shrink: 0;

        }


        .product-image img {

            width: 100%;
            height: 100%;

            object-fit: contain;

        }


        .product-placeholder {

            font-size: 25px;

        }


        .product-info {

            flex: 1;

            min-width: 0;

        }


        .product-info strong {

            display: block;

            color: #203c5b;

            font-size: 14px;

            margin-bottom: 5px;

        }


        .product-info span {

            color: #7890a9;

            font-size: 13px;

        }


        .product-price {

            font-weight: 700;

            color: #1c3d5d;

            white-space: nowrap;

            font-size: 14px;

        }


        .summary-line {

            display: flex;

            justify-content: space-between;

            padding: 10px 0;

            color: #607894;

            font-size: 14px;

        }


        .summary-line strong {

            color: #213b58;

        }


        .summary-total {

            margin-top: 10px;

            padding: 18px;

            border-radius: 15px;

            background: #ecf9f1;

            display: flex;

            justify-content: space-between;

            align-items: center;

            color: #087443;

        }


        .summary-total span {

            font-weight: 700;

        }


        .summary-total strong {

            font-size: 20px;

        }


        /* =========================================================
           BUTTONS
        ========================================================= */

        .actions {

            margin-top: 20px;

            display: grid;

            gap: 12px;

        }


        .btn {

            width: 100%;

            min-height: 50px;

            border-radius: 12px;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 10px;

            font-weight: 700;

            font-size: 14px;

            transition: .2s;

        }


        .btn-primary {

            background: #172536;

            color: white;

        }


        .btn-primary:hover {

            background: #243950;

            transform: translateY(-1px);

        }


        .btn-outline {

            border: 1px solid #aebdcd;

            color: #233c58;

            background: white;

        }


        .btn-outline:hover {

            background: #f5f8fb;

        }


        /* =========================================================
           FOOTER
        ========================================================= */

        .footer {

            text-align: center;

            color: #7890a9;

            font-size: 13px;

            padding: 25px 0 40px;

        }


        .footer-line {

            width: 85px;

            height: 1px;

            background: #cbd8e5;

            display: inline-block;

            vertical-align: middle;

            margin: 0 12px;

        }


        .heart {

            display: block;

            margin-top: 8px;

            font-size: 18px;

        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 900px) {

            .nav {
                display: none;
            }

            .success-top {

                grid-template-columns: 1fr;

            }

            .content-grid {

                grid-template-columns: 1fr;

            }

        }


        @media (max-width: 650px) {

            .site-header {

                padding: 0 20px;

            }


            .page {

                width: 94%;

                margin: 25px auto;

            }


            .success-card {

                padding: 18px;

                border-radius: 18px;

            }


            .success-left {

                align-items: flex-start;

                flex-direction: column;

            }


            .success-left h1 {

                font-size: 30px;

            }


            .order-info {

                grid-template-columns: 1fr;

            }


            .detail-row {

                grid-template-columns: 1fr;

                gap: 5px;

            }


            .payment-box {

                grid-template-columns: 1fr;

            }


            .payment-number {

                border-left: none;

                border-top: 1px solid #d4e3ee;

                padding-left: 0;

                padding-top: 18px;

            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header class="site-header">

    <a
        href="index.php"
        class="brand"
    >

        <span class="brand-mark">
            J
        </span>

        <span class="brand-text">

            <strong>
                Joyzone
            </strong>

            <span>
                ONLINE STORE
            </span>

        </span>

    </a>


    <nav class="nav">

        <a href="index.php">
            🏠 Beranda
        </a>

        <a href="cart.php">
            🛒 Troli
        </a>

        <a href="riwayat_pesanan.php">
            📄 Riwayat Pesanan
        </a>

    </nav>

</header>



<!-- =========================================================
     PAGE
========================================================= -->

<main class="page">


    <section class="success-card">


        <!-- SUCCESS HEADER -->

        <div class="success-top">


            <div class="success-left">


                <div class="success-icon">
                    ✓
                </div>


                <div>

                    <span class="success-label">
                        ✓ PEMBAYARAN BERHASIL
                    </span>


                    <h1>
                        Terima Kasih!
                    </h1>


                    <p>
                        Pembayaran Anda telah berhasil dikonfirmasi.
                        Pesanan sedang diproses dan akan segera kami kirim.
                    </p>

                </div>


            </div>



            <!-- ORDER INFO -->

            <div class="order-info">


                <div class="order-info-item">

                    <span>
                        Nomor Pesanan
                    </span>

                    <strong>
                        #<?= $order_id ?>
                    </strong>

                </div>


                <div class="order-info-item">

                    <span>
                        Tanggal Pesanan
                    </span>

                    <strong style="font-size:16px;">

                        <?= htmlspecialchars($tanggalOrder) ?>

                    </strong>

                    <small>
                        <?= htmlspecialchars($jamOrder) ?> WIB
                    </small>

                </div>


            </div>


        </div>



        <!-- MAIN CONTENT -->

        <div class="content-grid">


            <!-- =================================================
                 DETAIL PESANAN
            ================================================== -->

            <div class="card">


                <div class="card-title">

                    <div class="card-title-icon">
                        📄
                    </div>

                    Detail Pesanan

                </div>



                <div class="detail-list">


                    <div class="detail-row">

                        <span class="label">
                            Total Pembayaran
                        </span>

                        <span class="value">

                            Rp
                            <?= number_format(
                                $total,
                                0,
                                ",",
                                "."
                            ) ?>

                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="label">
                            Metode Pembayaran
                        </span>

                        <span class="value">

                            <?= htmlspecialchars(
                                $metode ?: "-"
                            ) ?>

                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="label">
                            Channel
                        </span>

                        <span class="value">

                            <?= htmlspecialchars(
                                $channel ?: "-"
                            ) ?>

                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="label">
                            Status Pesanan
                        </span>

                        <span>

                            <span class="badge badge-process">
                                ● <?= htmlspecialchars($order["status"]) ?>
                            </span>

                        </span>

                    </div>


                    <div class="detail-row">

                        <span class="label">
                            Status Pembayaran
                        </span>

                        <span>

                            <span class="badge badge-success">
                                ✓ Dibayar
                            </span>

                        </span>

                    </div>


                </div>



                <!-- PAYMENT INFORMATION -->

                <div class="payment-box">


                    <div class="payment-method">


                        <div class="payment-icon">

                            <?php if ($metode === "E-Wallet"): ?>

                                S

                            <?php else: ?>

                                🏦

                            <?php endif; ?>

                        </div>


                        <div>

                            <span>
                                <?= htmlspecialchars($metode ?: "Pembayaran") ?>
                            </span>

                            <strong>
                                <?= htmlspecialchars($channel ?: "-") ?>
                            </strong>

                        </div>


                    </div>



                    <div></div>



                    <div class="payment-number">

                        <span>
                            Nomor
                            <?= htmlspecialchars(
                                $channel ?: "Pembayaran"
                            ) ?>
                        </span>

                        <strong>
                            <?= htmlspecialchars($nomorPembayaran) ?>
                        </strong>

                    </div>


                </div>


            </div>



            <!-- =================================================
                 RINGKASAN PESANAN
            ================================================== -->

            <aside class="card">


                <div class="card-title">

                    <div class="card-title-icon">
                        🛒
                    </div>

                    Ringkasan Pesanan

                </div>



                <div class="product-list">


                    <?php foreach ($items as $item): ?>


                        <div class="product-item">


                            <div class="product-image">


                                <?php if (!empty($item["gambar"])): ?>

                                    <img
                                        src="images/<?= htmlspecialchars($item["gambar"]) ?>"
                                        alt="<?= htmlspecialchars($item["nama"]) ?>"
                                    >

                                <?php else: ?>

                                    <span class="product-placeholder">
                                        📦
                                    </span>

                                <?php endif; ?>


                            </div>


                            <div class="product-info">

                                <strong>
                                    <?= htmlspecialchars(
                                        $item["nama"] ?? "Produk"
                                    ) ?>
                                </strong>

                                <span>
                                    x<?= (int)$item["jumlah"] ?>
                                </span>

                            </div>


                            <div class="product-price">

                                Rp
                                <?= number_format(
                                    (float)$item["harga"] *
                                    (int)$item["jumlah"],
                                    0,
                                    ",",
                                    "."
                                ) ?>

                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>



                <!-- TOTAL -->

                <div style="margin-top:15px;">

                    <div class="summary-line">

                        <span>
                            Subtotal
                        </span>

                        <strong>

                            Rp
                            <?= number_format(
                                $total,
                                0,
                                ",",
                                "."
                            ) ?>

                        </strong>

                    </div>


                    <div class="summary-line">

                        <span>
                            Ongkos Kirim
                        </span>

                        <strong>
                            Rp 0
                        </strong>

                    </div>


                    <div class="summary-total">

                        <span>
                            Total Pembayaran
                        </span>

                        <strong>

                            Rp
                            <?= number_format(
                                $total,
                                0,
                                ",",
                                "."
                            ) ?>

                        </strong>

                    </div>

                </div>



                <!-- BUTTON -->

                <div class="actions">


                    <a
                        href="riwayat_pesanan.php"
                        class="btn btn-primary"
                    >
                        📄 Lihat Riwayat Pesanan
                        →
                    </a>


                    <a
                        href="index.php"
                        class="btn btn-outline"
                    >
                        ← Kembali ke Katalog
                    </a>


                </div>


            </aside>


        </div>


    </section>



    <!-- FOOTER -->

    <footer class="footer">

        <span class="footer-line"></span>

        Terima kasih telah berbelanja di Joyzone Store

        <span class="footer-line"></span>

        <span class="heart">
            ♥
        </span>

    </footer>


</main>


</body>

</html>