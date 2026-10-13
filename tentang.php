<?php
require_once __DIR__ . '/home_data.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami - Joyzone Store</title>
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
            <h1>Tentang Kami</h1>
            <div class="breadcrumb">Home &nbsp;›&nbsp; Tentang Kami</div>
        </div>
    </section>

    <div class="page-container">
        <div class="about-grid">
            <div class="content-card">
                <h2>Selamat Datang di Joyzone Store</h2>
                <p>
                    Joyzone Store adalah toko online yang menyediakan berbagai
                    produk pilihan dengan informasi produk yang jelas dan proses
                    layanan yang mudah digunakan.
                </p>
                <p style="margin-top:12px;">
                    Kami berusaha memberikan pengalaman berbelanja yang rapi,
                    informatif, dan nyaman bagi setiap pengunjung.
                </p>

                <div class="about-features">
                    <div class="feature-box">
                        <div class="feature-icon">✓</div>
                        <h3>Informasi Jelas</h3>
                        <p>Detail produk ditampilkan dengan sederhana dan mudah dipahami.</p>
                    </div>

                    <div class="feature-box">
                        <div class="feature-icon">🚚</div>
                        <h3>Proses Teratur</h3>
                        <p>Informasi pesanan disusun agar pelanggan mudah mengikuti prosesnya.</p>
                    </div>

                    <div class="feature-box">
                        <div class="feature-icon">💬</div>
                        <h3>Layanan Ramah</h3>
                        <p>Kami menyediakan kanal komunikasi untuk membantu pelanggan.</p>
                    </div>
                </div>
            </div>

            <div>
                <div class="about-image">
                    <img src="assets/images/logo-joyzone.png"
                         alt="Produk Joyzone"
                         onerror="this.style.display='none';">
                </div>

                <div class="quote-box">
                    “Kami mengutamakan informasi yang jelas, layanan yang tertata,
                    dan pengalaman pengguna yang nyaman.”
                    <br><br>
                    — Joyzone Store
                </div>
            </div>
        </div>
    </div>
</main>



<?php require __DIR__ . '/footer_home.php'; ?>
</body>
</html>
