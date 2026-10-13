<?php
require_once __DIR__ . '/home_data.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - Joyzone Store</title>
    <link rel="icon" type="image/png" href="assets/images/logo-joyzone.png">
    <link rel="stylesheet" href="style.css?v=12">
    <link rel="stylesheet" href="banner.css">
    <link rel="stylesheet" href="index.css?v=1">
    <link rel="stylesheet" href="pages.css?v=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="home.css?v=1">
    <link rel="stylesheet" href="menu-fix.css?v=1">
</head>
<body class="home">


<?php require __DIR__ . '/header_home.php'; ?>



<main class="page-wrapper">
    <section class="page-hero">
        <div class="page-hero-inner">
            <h1>FAQ</h1>
            <div class="breadcrumb">Home &nbsp;›&nbsp; FAQ</div>
        </div>
    </section>

    <div class="page-container">
        <div class="faq-layout">
            <div>
                <h2 style="margin-bottom:15px;">Pertanyaan yang Sering Diajukan</h2>

                <div class="faq-list">
                    <details class="faq-item">
                        <summary>Bagaimana cara melakukan pemesanan?</summary>
                        <div class="faq-answer">Pilih produk pada katalog, buka detail produk, lalu ikuti proses pemesanan yang tersedia pada website.</div>
                    </details>

                    <details class="faq-item">
                        <summary>Metode pembayaran apa saja yang tersedia?</summary>
                        <div class="faq-answer">Metode pembayaran yang tersedia akan ditampilkan pada halaman pembayaran sesuai konfigurasi website.</div>
                    </details>

                    <details class="faq-item">
                        <summary>Bagaimana cara melihat status pesanan?</summary>
                        <div class="faq-answer">Status pesanan dapat diperiksa melalui informasi pesanan yang tersedia setelah proses checkout.</div>
                    </details>

                    <details class="faq-item">
                        <summary>Apakah produk yang ditampilkan memiliki informasi stok?</summary>
                        <div class="faq-answer">Ya. Stok yang tersedia ditampilkan pada halaman katalog dan detail produk.</div>
                    </details>

                    <details class="faq-item">
                        <summary>Bagaimana jika ada masalah dengan pesanan?</summary>
                        <div class="faq-answer">Silakan hubungi kami melalui halaman Kontak Kami agar tim dapat membantu memeriksa masalah tersebut.</div>
                    </details>

                    <details class="faq-item">
                        <summary>Bagaimana cara menghubungi customer service?</summary>
                        <div class="faq-answer">Gunakan nomor telepon atau email yang tercantum pada halaman Kontak Kami.</div>
                    </details>
                </div>
            </div>

            <aside class="faq-side">
                <div class="big-icon">🎧</div>
                <h3>Masih ada pertanyaan?</h3>
                <p style="margin-top:8px; color:#666; line-height:1.6;">
                    Jika pertanyaan Anda belum terjawab, silakan hubungi kami.
                </p>
                <a href="kontak.php" class="btn-dark">Hubungi Kami</a>
            </aside>
        </div>
    </div>
</main>



<?php require __DIR__ . '/footer_home.php'; ?>
</body>
</html>
