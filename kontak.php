<?php
require_once __DIR__ . '/home_data.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontak Kami - Joyzone Store</title>
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
            <h1>Kontak Kami</h1>
            <div class="breadcrumb">Home &nbsp;›&nbsp; Kontak Kami</div>
        </div>
    </section>

    <div class="page-container">
        <div class="contact-grid">
            <div>
                <div class="content-card">
                    <h2>Hubungi Kami</h2>
                    <p>Jika ada pertanyaan atau membutuhkan informasi, silakan hubungi kami melalui kontak berikut.</p>

                    <div class="contact-info">
                        <div class="contact-item">
                            <div class="icon">📞</div>
                            <div>
                                <strong>Nomor Telepon</strong>
                                <span>+62 85819872435<br>Senin - Minggu, 08.00 - 22.00</span>
                            </div>
                        </div>

                        <div class="contact-item">
                            <div class="icon">✉</div>
                            <div>
                                <strong>Email</strong>
                                <span>joyzonevape@gmail.com<br>Respons maksimal 1×24 jam</span>
                            </div>
                        </div>

                        <div class="contact-item">
                            <div class="icon">📍</div>
                            <div>
                                <strong>Alamat</strong>
                                <span>Jl. Raya Bogor no.125, Jakarta, Indonesia</span>
                            </div>
                        </div>
                    </div>

                    <h3>Ikuti Kami</h3>
                    <div class="social-links">
                        <a href="#" aria-label="Instagram">IG</a>
                        <a href="#" aria-label="TikTok">TT</a>
                        <a href="#" aria-label="YouTube">YT</a>
                    </div>
                </div>
            </div>

            <div class="content-card contact-form">
                <h2>Kirim Pesan</h2>
                <p>Silakan isi formulir di bawah ini. Kami akan merespons pesan Anda.</p>

                <form action="#" method="post">
                    <label for="nama">Nama Lengkap</label>
                    <input id="nama" name="nama" type="text" placeholder="Masukkan nama Anda" required>

                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" placeholder="Masukkan email Anda" required>

                    <label for="pesan">Pesan</label>
                    <textarea id="pesan" name="pesan" placeholder="Tulis pesan Anda di sini..." required></textarea>

                    <button class="btn-submit" type="submit">Kirim Pesan</button>
                </form>
            </div>
        </div>
    </div>
</main>



<?php require __DIR__ . '/footer_home.php'; ?>
</body>
</html>
