<?php

session_start();

require_once 'config/koneksi.php';
require_once __DIR__ . '/foto_profil.php';
if (is_file(__DIR__ . '/notif.php')) {
    require_once __DIR__ . '/notif.php';
}
require_once __DIR__ . '/terakhir_dilihat.php';


/* ==================================================
   URL GAMBAR PRODUK
   Mencari file di beberapa folder, karena produk lama dan baru
   disimpan di tempat berbeda (admin/uploads/, upload/, assets/images/).
================================================== */

function gambarProdukUrl($file)
{
    $file = basename(trim((string) $file));

    if ($file === '') {
        return '';
    }

    foreach (['admin/uploads/', 'upload/', 'assets/images/', 'assets/'] as $dir) {
        if (is_file(__DIR__ . '/' . $dir . $file)) {
            return $dir . rawurlencode($file);
        }
    }

    // Tidak ketemu: arahkan ke folder lama agar onerror tetap jalan
    return 'assets/images/' . rawurlencode($file);
}

/* ==================================================
   DATA SESSION USER
================================================== */

$userId = $_SESSION['user_id'] ?? null;

/* Jumlah item di troli (untuk badge notifikasi) */
$jumlahTroli = 0;
foreach ($_SESSION['cart'] ?? [] as $itemTroli) {
    $jumlahTroli += (int) ($itemTroli['jumlah'] ?? 0);
}

$namaUser = null;
$emailUser = null;


/* ==================================================
   AMBIL DATA USER YANG SEDANG LOGIN
================================================== */

if ($userId !== null) {

    try {

        $stmtUser = $pdo->prepare("
            SELECT id,
                   COALESCE(NULLIF(TRIM(name), ''), NULLIF(TRIM(nama), ''), '') AS name,
                   email
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $stmtUser->execute([$userId]);

        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);


        if ($user) {

            $namaUser = $user['name'];
            $emailUser = $user['email'];

        } else {

            /*
             * Jika user ID di session
             * tidak ditemukan di database.
             */

            $userId = null;

            unset(
                $_SESSION['user_id'],
                $_SESSION['user_name'],
                $_SESSION['user_email']
            );

        }

    } catch (PDOException $e) {

        $userId = null;

        $namaUser = null;

        $emailUser = null;

    }

}


/* ==================================================
   AMBIL DATA PRODUK
================================================== */

$products = [];

$dbError = null;


try {

    $stmt = $pdo->query("
        SELECT *
        FROM products
        ORDER BY id DESC
    ");

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {

    $dbError = $e->getMessage();

}


/* ==================================================
   WATCHLIST USER (id produk yang sudah disimpan)
================================================== */

$watchIds = [];

if ($userId !== null) {

    try {

        $stmtWatch = $pdo->prepare("
            SELECT product_id
            FROM watchlist
            WHERE user_id = ?
        ");

        $stmtWatch->execute([$userId]);

        $watchIds = array_map('intval', $stmtWatch->fetchAll(PDO::FETCH_COLUMN));

    } catch (PDOException $e) {

        // Tabel watchlist belum dibuat -> abaikan, halaman tetap jalan
        $watchIds = [];

    }

}

$jumlahWatch = count($watchIds);

?>


<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Joyzone Store</title>
    <link rel="icon" type="image/png" href="assets/images/logo-joyzone.png">


    <!-- CSS UTAMA -->

    <link
        rel="stylesheet"
        href="./style.css?v=12"
    >

    <link rel="stylesheet" href="banner.css">


    <!-- ==================================================
         CSS TAMBAHAN ACCOUNT & MENU
    ================================================== -->

    <link rel="stylesheet" href="index.css?v=2">

    <!-- DESAIN BARU BERANDA (harus paling akhir) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="home.css?v=1">

</head>


<body class="home">

<div class="hp-topbar">Khusus pembeli usia 18 tahun ke atas. Pesanan dicek langsung oleh admin.</div>


<!-- ==================================================
     NAVBAR
================================================== -->

<nav class="navbar">

    <div class="nav-container">


        <!-- ==================================================
             LOGO
        ================================================== -->

        <div class="nav-logo">

            <a href="index.php" style="display:inline-flex;align-items:center;gap:10px;">
                <img src="assets/images/logo-joyzone.png" alt="Joyzone Vape" width="48" height="48" style="width:48px;height:48px;border-radius:50%;display:block;">
                <span>JOYZONE</span>
            </a>

        </div>



        <!-- ==================================================
             AKSI NAVBAR: TROLI, WATCHLIST, HAMBURGER
        ================================================== -->

        <div class="nav-actions">

            <!-- TROLI -->
            <a href="cart.php" class="nav-icon-link" aria-label="Troli" title="Troli">

                <span class="troli-icon">

                    <svg class="cart-ico" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2 3h2.6l2.2 11.2a1.6 1.6 0 0 0 1.6 1.3h8.4a1.6 1.6 0 0 0 1.6-1.2L20 7H5.4"/>
                        <circle cx="9.5" cy="19.5" r="1.4"/>
                        <circle cx="17" cy="19.5" r="1.4"/>
                    </svg>

                    <?php if ($jumlahTroli > 0): ?>

                        <span class="troli-badge">
                            <?= $jumlahTroli > 99 ? '99+' : $jumlahTroli ?>
                        </span>

                    <?php endif; ?>

                </span>

            </a>

            <!-- WATCHLIST -->
            <a href="watchlist.php" class="nav-icon-link" aria-label="Watchlist" title="Watchlist">

                <span class="troli-icon">

                    ❤️

                    <span
                        class="troli-badge"
                        id="watchBadge"<?= $jumlahWatch > 0 ? '' : ' style="display:none"' ?>
                    >
                        <?= $jumlahWatch > 99 ? '99+' : $jumlahWatch ?>
                    </span>

                </span>

            </a>

            <!-- TOMBOL HAMBURGER -->
            <button
                class="menu-toggle"
                onclick="toggleMenu()"
                aria-label="Buka menu"
                type="button"
            >

                ☰

            </button>

        </div>



        <!-- ==================================================
             MENU NAVBAR
        ================================================== -->

        <div
            class="nav-menu"
            id="navMenu"
        >


            <!-- ==================================================
                 ACCOUNT
                 PALING ATAS
            ================================================== -->

            <?php if ($userId !== null && $namaUser !== null): ?>


                <!-- ACCOUNT USER YANG LOGIN -->

                <a href="profil.php" class="user-account user-account-link" title="Ubah profil &amp; kata sandi">

                    <div class="account-header">


                        <div class="account-icon"><?php $fp = fotoProfilUrl((int) $userId); ?><?php if ($fp !== ''): ?><img src="<?= htmlspecialchars($fp) ?>" alt=""><?php else: ?>👤<?php endif; ?></div>


                        <div class="account-text">


                            <div class="account-label">

                                Akun Saya

                            </div>


                            <div class="account-name">

                                <?= htmlspecialchars($namaUser) ?>

                            </div>


                            <div class="account-email">

                                <?= htmlspecialchars(
                                    $emailUser ?? ''
                                ) ?>

                            </div>


                        </div>

                    </div>

                
                <span class="account-arrow" aria-hidden="true">&rsaquo;</span>
                </a>


            <?php else: ?>


                <!-- ACCOUNT BELUM LOGIN -->

                <div class="user-account user-guest">

                    <div class="account-header">


                        <div class="account-icon">

                            👤

                        </div>


                        <div class="account-text">


                            <div class="account-label">

                                Akun Saya

                            </div>


                            <div class="account-email">

                                Belum login

                            </div>


                        </div>

                    </div>

                </div>


            <?php endif; ?>



            <!-- ==================================================
                 PEMISAH
            ================================================== -->

            <div class="menu-divider"></div>



            <!-- ==================================================
                 HOME
            ================================================== -->

            <a href="index.php">

                Home

            </a>



            <!-- ==================================================
                 KATALOG
            ================================================== -->

            <a href="katalog.php">

                Katalog

            </a>



            <!-- ==================================================
                 RIWAYAT PESANAN
            ================================================== -->

            <?php if ($userId !== null): ?>

                <a href="riwayat_pesanan.php">

                    🧾 Riwayat Pesanan

                </a>

            <?php endif; ?>



            <!-- ==================================================
                 LOGIN / LOGOUT
            ================================================== -->

            <?php if ($userId !== null && $namaUser !== null): ?>


                <a
                    href="logout.php"
                    class="logout-link"
                >

                    Logout

                </a>


            <?php else: ?>


                <a href="login.php">

                    Login

                </a>


                <a href="register.php">

                    Register

                </a>


            <?php endif; ?>



            <!-- ==================================================
                 PEMISAH
            ================================================== -->

            <div class="menu-divider"></div>



            <!-- ==================================================
                 TENTANG KAMI
            ================================================== -->

            <a href="tentang.php">

                Tentang Kami

            </a>



            <!-- ==================================================
                 KONTAK
            ================================================== -->

            <a href="kontak.php">

                Kontak Kami

            </a>



            <!-- ==================================================
                 FAQ
            ================================================== -->

            <a href="faq.php">

                FAQ

            </a>



            <!-- ==================================================
                 SYARAT
            ================================================== -->

            <a href="syarat.php">

                Syarat & Ketentuan

            </a>



            <!-- ==================================================
                 PRIVASI
            ================================================== -->

            <a href="privasi.php">

                Kebijakan Privasi

            </a>


        </div>

    </div>

</nav>


<?php

/* ==================================================
   DATA TAMPILAN BERANDA
================================================== */

$hargaMulai = !empty($products)
    ? min(array_map('intval', array_column($products, 'harga')))
    : 0;

$banners = [
    [
        'tema'  => 'ungu',
        'judul' => 'Upgrade device kamu',
        'teks'  => 'Pilihan device terbaik dengan harga bersahabat. Stok terbatas.',
        'tombol'=> 'Lihat device',
        'link'  => 'katalog.php',
    ],
    [
        'tema'  => 'hijau',
        'judul' => 'Freebase dan saltnic',
        'teks'  => 'Temukan rasa favoritmu, dari yang segar sampai manis.',
        'tombol'=> 'Lihat liquid',
        'link'  => 'katalog.php',
    ],
    [
        'tema'  => 'oranye',
        'judul' => 'Aksesoris dan perlengkapan',
        'teks'  => $hargaMulai > 0
            ? 'Battery, case, dan lainnya. Mulai dari Rp ' . number_format($hargaMulai, 0, ',', '.') . '.'
            : 'Battery, case, dan perlengkapan lainnya untuk setup-mu.',
        'tombol'=> 'Belanja sekarang',
        'link'  => 'katalog.php',
    ],
];

$infoStrip = [
    ['💳', 'Bayar via Virtual Account & E-Wallet'],
    ['📦', 'Stok selalu diperbarui'],
    ['✅', 'Pesanan dicek langsung oleh admin'],
    ['🔞', 'Khusus pembeli usia 18 tahun ke atas'],
];

/* Gambar untuk tiap slide hero: pakai foto produk terbaru */
$gambarProduk = array_values(array_filter(
    array_column($products, 'gambar')
));

/* Kategori (maks. 5) lengkap dengan foto & jumlah produk */
$kategoriMap = [];
foreach ($products as $p) {
    $k = trim((string) ($p['kategori'] ?? ''));
    if ($k === '') { continue; }
    if (!isset($kategoriMap[$k])) {
        $kategoriMap[$k] = ['gambar' => '', 'n' => 0];
    }
    $kategoriMap[$k]['n']++;
    if ($kategoriMap[$k]['gambar'] === '' && !empty($p['gambar'])) {
        $kategoriMap[$k]['gambar'] = $p['gambar'];
    }
}
$kategoriTampil = array_slice($kategoriMap, 0, 5, true);

/* Produk terbaru, produk diskon, dan produk unggulan */
$produkTerbaru = array_slice($products, 0, 8);

$produkDiskon = array_slice(array_values(array_filter(
    $products,
    fn($p) => (int) ($p['diskon_persen'] ?? 0) > 0
)), 0, 8);

$produkUnggulan = $products[0] ?? null;
$produkSamping  = array_slice($products, 1, 5);


/* ==================================================
   KARTU PRODUK (dipakai di semua bagian)
================================================== */

function kartuProduk(array $p, array $watchIds, bool $badgeDiskon = false): void
{
    $id     = (int) $p['id'];
    $aktif  = in_array($id, $watchIds, true);
    $stok   = (int) ($p['stok'] ?? $p['jumlah'] ?? 0);
    $diskon = (int) ($p['diskon_persen'] ?? 0);
    $asli   = (float) $p['harga'];
    $akhir  = round($asli * (100 - $diskon) / 100);
    ?>

    <article class="hp-card">

        <div class="hp-card-media">

            <?php if ($badgeDiskon && $diskon > 0): ?>
                <span class="hp-flag">-<?= $diskon ?>%</span>
            <?php endif; ?>

            <button
                type="button"
                class="btn-watch<?= $aktif ? ' aktif' : '' ?>"
                data-id="<?= $id ?>"
                aria-pressed="<?= $aktif ? 'true' : 'false' ?>"
                aria-label="Simpan ke watchlist"
                title="Simpan ke watchlist"
            ><?= $aktif ? '❤️' : '🤍' ?></button>

            <a href="detail_produk.php?id=<?= $id ?>" tabindex="-1" aria-hidden="true">
                <?php if (!empty($p['gambar'])): ?>
                    <img
                        src="<?= htmlspecialchars(gambarProdukUrl($p['gambar'])) ?>"
                        alt="<?= htmlspecialchars($p['nama']) ?>"
                        loading="lazy"
                        onerror="this.style.display='none';"
                    >
                <?php else: ?>
                    <span class="no-image">Tidak ada gambar</span>
                <?php endif; ?>
            </a>

        </div>

        <div class="hp-card-body">

            <?php if (!empty($p['kategori'])): ?>
                <span class="hp-cat"><?= htmlspecialchars($p['kategori']) ?></span>
            <?php endif; ?>

            <h3>
                <a href="detail_produk.php?id=<?= $id ?>">
                    <?= htmlspecialchars($p['nama']) ?>
                </a>
            </h3>

            <div class="hp-price">
                <?php if ($diskon > 0): ?>
                    <s>Rp <?= number_format($asli, 0, ',', '.') ?></s>
                    <strong>Rp <?= number_format($akhir, 0, ',', '.') ?></strong>
                <?php else: ?>
                    <strong>Rp <?= number_format($asli, 0, ',', '.') ?></strong>
                <?php endif; ?>
            </div>

            <div class="hp-stock <?= $stok < 1 ? 'habis' : '' ?>">
                <?= $stok < 1 ? 'Stok habis' : 'Stok: ' . $stok ?>
            </div>

            <div class="hp-actions">
                <a href="detail_produk.php?id=<?= $id ?>" class="hp-btn hp-btn-ghost">Detail</a>

                <!-- BELI SEKARANG: langsung ke halaman ringkasan + pembayaran -->
                <form method="post" action="beli_langsung.php">
                    <input type="hidden" name="product_id" value="<?= $id ?>">
                    <input type="hidden" name="qty" value="1">
                    <button type="submit" class="hp-btn hp-btn-solid" <?= $stok < 1 ? 'disabled' : '' ?>>
                        Beli sekarang
                    </button>
                </form>
            </div>

        </div>

    </article>

    <?php
}

?>


<!-- ==================================================
     HERO (carousel, digerakkan banner.js)
================================================== -->

<main class="hp-wrap">

<section
    class="promo-hero"
    id="promoHero"
    aria-roledescription="carousel"
    aria-label="Promo Joyzone"
>

    <div class="promo-slides">

        <?php foreach ($banners as $i => $b): ?>

            <article class="promo-slide theme-<?= htmlspecialchars($b['tema']) ?>">

                <?php if (!empty($gambarProduk)): ?>
                    <img
                        class="promo-photo"
                        src="<?= htmlspecialchars(gambarProdukUrl($gambarProduk[$i % count($gambarProduk)])) ?>"
                        alt=""
                        onerror="this.style.display='none';"
                    >
                <?php endif; ?>

                <div class="promo-content">

                    <h2><?= htmlspecialchars($b['judul']) ?></h2>

                    <p><?= htmlspecialchars($b['teks']) ?></p>

                    <a class="promo-cta" href="<?= htmlspecialchars($b['link']) ?>">
                        <?= htmlspecialchars($b['tombol']) ?>
                    </a>

                </div>

            </article>

        <?php endforeach; ?>

    </div>

    <button type="button" class="promo-nav prev" aria-label="Banner sebelumnya">&#8249;</button>
    <button type="button" class="promo-nav next" aria-label="Banner berikutnya">&#8250;</button>

    <div class="promo-dots"></div>

</section>


<!-- ==================================================
     KATEGORI
================================================== -->

<?php if (!empty($kategoriTampil)): ?>

<section class="hp-tiles" aria-label="Kategori produk">

    <?php foreach ($kategoriTampil as $nama => $k): ?>

        <a class="hp-tile" href="katalog.php?kategori=<?= urlencode($nama) ?>">

            <?php if ($k['gambar'] !== ''): ?>
                <img
                    src="<?= htmlspecialchars(gambarProdukUrl($k['gambar'])) ?>"
                    alt=""
                    loading="lazy"
                    onerror="this.style.display='none';"
                >
            <?php endif; ?>

            <span class="hp-tile-label">
                <?= htmlspecialchars($nama) ?>
                <small><?= (int) $k['n'] ?> produk</small>
            </span>

        </a>

    <?php endforeach; ?>

</section>

<?php endif; ?>


<!-- ==================================================
     TERAKHIR DILIHAT & DIPESAN (tampil jika ada riwayat)
================================================== -->

<?php tampilTerakhirDilihat($pdo); ?>


<?php if ($dbError !== null): ?>

    <div class="empty">Database error: <?= htmlspecialchars($dbError) ?></div>

<?php elseif (empty($products)): ?>

    <div class="empty">Belum ada produk yang tersedia.</div>

<?php else: ?>


<!-- ==================================================
     PRODUK TERBARU
================================================== -->

<section class="hp-section">

    <header class="hp-head">
        <h2>Produk terbaru</h2>
        <a href="katalog.php">Lihat semua</a>
    </header>

    <div class="hp-row">
        <?php foreach ($produkTerbaru as $p) { kartuProduk($p, $watchIds); } ?>
    </div>

</section>


<!-- ==================================================
     PRODUK DISKON (hanya tampil jika ada diskon)
================================================== -->

<?php if (!empty($produkDiskon)): ?>

<section class="hp-section">

    <header class="hp-head">
        <h2>Sedang diskon</h2>
        <a href="katalog.php">Lihat semua</a>
    </header>

    <div class="hp-row">
        <?php foreach ($produkDiskon as $p) { kartuProduk($p, $watchIds, true); } ?>
    </div>

</section>

<?php endif; ?>


<!-- ==================================================
     BANNER LEBAR
================================================== -->

<section class="hp-wide">

    <div class="hp-wide-text">
        <h2>Belanja aman, pesanan dicek admin</h2>
        <p>Bayar lewat Virtual Account atau e-wallet. Stok diperbarui setiap saat.</p>
        <a class="promo-cta" href="katalog.php">Buka katalog</a>
    </div>

    <?php if (!empty($gambarProduk)): ?>
        <img
            src="<?= htmlspecialchars(gambarProdukUrl($gambarProduk[count($gambarProduk) > 3 ? 3 : 0])) ?>"
            alt=""
            loading="lazy"
            onerror="this.style.display='none';"
        >
    <?php endif; ?>

</section>


<!-- ==================================================
     PILIHAN JOYZONE (satu unggulan + daftar)
================================================== -->

<?php if ($produkUnggulan !== null): ?>

<section class="hp-section">

    <header class="hp-head">
        <h2>Pilihan Joyzone</h2>
    </header>

    <div class="hp-feature">

        <a class="hp-feature-main" href="detail_produk.php?id=<?= (int) $produkUnggulan['id'] ?>">

            <span class="hp-feature-img">
                <?php if (!empty($produkUnggulan['gambar'])): ?>
                    <img
                        src="<?= htmlspecialchars(gambarProdukUrl($produkUnggulan['gambar'])) ?>"
                        alt="<?= htmlspecialchars($produkUnggulan['nama']) ?>"
                        onerror="this.style.display='none';"
                    >
                <?php endif; ?>
            </span>

            <strong><?= htmlspecialchars($produkUnggulan['nama']) ?></strong>

            <span>Rp <?= number_format(
                round((float) $produkUnggulan['harga'] * (100 - (int) ($produkUnggulan['diskon_persen'] ?? 0)) / 100),
                0, ',', '.'
            ) ?></span>

        </a>

        <ul class="hp-feature-list">

            <?php foreach ($produkSamping as $p): ?>

                <li>
                    <a href="detail_produk.php?id=<?= (int) $p['id'] ?>">

                        <span class="hp-thumb">
                            <?php if (!empty($p['gambar'])): ?>
                                <img
                                    src="<?= htmlspecialchars(gambarProdukUrl($p['gambar'])) ?>"
                                    alt=""
                                    loading="lazy"
                                    onerror="this.style.display='none';"
                                >
                            <?php endif; ?>
                        </span>

                        <span class="hp-feature-info">
                            <strong><?= htmlspecialchars($p['nama']) ?></strong>
                            <small>
                                <?= htmlspecialchars($p['kategori'] ?? '') ?>
                                <?= !empty($p['kategori']) ? ' - ' : '' ?>Rp <?= number_format(
                                    round((float) $p['harga'] * (100 - (int) ($p['diskon_persen'] ?? 0)) / 100),
                                    0, ',', '.'
                                ) ?>
                            </small>
                        </span>

                    </a>
                </li>

            <?php endforeach; ?>

        </ul>

    </div>

</section>

<?php endif; ?>


<!-- ==================================================
     SEMUA PRODUK
================================================== -->

<section class="hp-section hp-all">

    <header class="hp-head hp-head-center">
        <h2>Semua produk</h2>
        <p><?= count($products) ?> produk tersedia di Joyzone Store</p>
    </header>

    <div class="hp-grid">
        <?php foreach ($products as $p) { kartuProduk($p, $watchIds, true); } ?>
    </div>

</section>

<?php endif; ?>


<!-- ==================================================
     INFO STRIP
================================================== -->

<div class="promo-strip">

    <?php foreach ($infoStrip as $item): ?>

        <div class="promo-strip-item">
            <span class="promo-strip-icon" aria-hidden="true"><?= $item[0] ?></span>
            <span><?= htmlspecialchars($item[1]) ?></span>
        </div>

    <?php endforeach; ?>

</div>

</main>


<!-- ==================================================
     FOOTER
================================================== -->

<footer class="hp-footer">

    <div class="hp-footer-grid">

        <div>
            <img src="assets/images/logo-joyzone.png" alt="Joyzone Vape" width="64" height="64" style="width:64px;height:64px;border-radius:50%;display:block;margin-bottom:10px;">
            <strong class="hp-footer-logo">JOYZONE</strong>
            <p>Device, liquid, dan aksesoris. Khusus pembeli usia 18 tahun ke atas.</p>
        </div>

        <div>
            <h4>Belanja</h4>
            <a href="katalog.php">Katalog</a>
            <a href="watchlist.php">Watchlist</a>
            <a href="cart.php">Troli</a>
            <a href="riwayat_pesanan.php">Riwayat pesanan</a>
        </div>

        <div>
            <h4>Bantuan</h4>
            <a href="faq.php">FAQ</a>
            <a href="kontak.php">Kontak kami</a>
            <a href="tentang.php">Tentang kami</a>
        </div>

        <div>
            <h4>Legal</h4>
            <a href="syarat.php">Syarat &amp; ketentuan</a>
            <a href="privasi.php">Kebijakan privasi</a>
        </div>

    </div>

    <div class="hp-footer-bottom">
        &copy; <?= date('Y') ?> Joyzone Store
    </div>

</footer>


<!-- ==================================================
     JAVASCRIPT
================================================== -->

<script>


/* ==================================================
   BUKA / TUTUP MENU
================================================== */

function toggleMenu() {

    const navMenu =
        document.getElementById("navMenu");


    navMenu.classList.toggle("active");

}



/* ==================================================
   TUTUP MENU JIKA KLIK DI LUAR NAVBAR
================================================== */

document.addEventListener(
    "click",
    function(event) {


        const navMenu =
            document.getElementById("navMenu");


        const navbar =
            document.querySelector(".navbar");


        /*
         * Kalau klik di luar navbar,
         * menu ditutup.
         */

        if (!navbar.contains(event.target)) {

            navMenu.classList.remove("active");

        }

    }
);


</script>


<script>

/* ==================================================
   WATCHLIST: SIMPAN / HAPUS PRODUK (AJAX)
================================================== */

document.addEventListener("click", function (event) {

    var btn = event.target.closest(".btn-watch");

    if (!btn) { return; }

    event.preventDefault();

    if (btn.disabled) { return; }

    btn.disabled = true;

    var data = new FormData();
    data.append("product_id", btn.getAttribute("data-id"));

    fetch("watchlist_toggle.php", {
        method: "POST",
        body: data,
        headers: { "X-Requested-With": "XMLHttpRequest" }
    })
    .then(function (res) { return res.json(); })
    .then(function (json) {

        if (json.login) {
            window.location.href = "login.php";
            return;
        }

        if (!json.ok) {
            alert(json.pesan || "Gagal memproses watchlist.");
            return;
        }

        btn.classList.toggle("aktif", json.aktif);
        btn.textContent = json.aktif ? "\u2764\uFE0F" : "\uD83E\uDD0D";
        btn.setAttribute("aria-pressed", json.aktif ? "true" : "false");

        var badge = document.getElementById("watchBadge");

        if (badge) {
            badge.textContent = json.total > 99 ? "99+" : json.total;
            badge.style.display = json.total > 0 ? "" : "none";
        }

    })
    .catch(function () {
        alert("Terjadi kesalahan, coba lagi.");
    })
    .finally(function () {
        btn.disabled = false;
    });

});

</script>

<?php if ($userId === null): ?>

<!-- ==================================================
     ALERT UNTUK PENGUNJUNG BELUM LOGIN
================================================== -->

<div class="login-alert" id="loginAlert" role="alertdialog" aria-modal="true" aria-labelledby="loginAlertJudul">

    <div class="login-alert-box">

        <button type="button" class="login-alert-close" id="loginAlertTutup" aria-label="Tutup">&times;</button>

        <div class="login-alert-icon" aria-hidden="true">🔒</div>

        <h2 id="loginAlertJudul">Harap login/register terlebih dahulu</h2>

        <p>Masuk atau buat akun untuk berbelanja, menyimpan watchlist, dan melihat riwayat pesanan.</p>

        <div class="login-alert-actions">
            <a href="login.php" class="hp-btn hp-btn-solid" id="loginAlertLogin">Login</a>
            <a href="register.php" class="hp-btn hp-btn-ghost">Register</a>
        </div>

    </div>

</div>

<script>
(function () {
    var box = document.getElementById("loginAlert");
    if (!box) { return; }

    function tutup() { box.classList.add("hide"); }

    document.getElementById("loginAlertTutup").addEventListener("click", tutup);

    box.addEventListener("click", function (e) {
        if (e.target === box) { tutup(); }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") { tutup(); }
    });

    document.getElementById("loginAlertLogin").focus();
})();
</script>

<?php endif; ?>

<?php if (function_exists('render_notif')) { render_notif(); } ?>

<script src="banner.js" defer></script>

</body>

</html>