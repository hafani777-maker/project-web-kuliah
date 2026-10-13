<?php

session_start();

require_once 'config/koneksi.php';
require_once __DIR__ . '/foto_profil.php';

/* =========================================================
   WAJIB LOGIN
========================================================= */

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

/* =========================================================
   URL GAMBAR PRODUK (sama dengan index.php)
========================================================= */

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

    return 'assets/images/' . rawurlencode($file);
}

/* =========================================================
   DATA USER (untuk menu akun, sama dengan index.php)
========================================================= */

$namaUser  = null;
$emailUser = null;

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
        $namaUser  = $user['name'];
        $emailUser = $user['email'];
    }
} catch (PDOException $e) {
    $namaUser  = null;
    $emailUser = null;
}

/* =========================================================
   DATA WATCHLIST
========================================================= */

$items   = [];
$dbError = null;

try {

    $stmt = $pdo->prepare("
        SELECT p.*
        FROM watchlist w
        INNER JOIN products p ON p.id = w.product_id
        WHERE w.user_id = ?
        ORDER BY w.id DESC
    ");
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $dbError = 'Tabel watchlist belum dibuat. Jalankan perintah SQL pembuatan tabel di phpMyAdmin.';

}

$jumlahWatch = count($items);

/* Jumlah item troli untuk badge */
$jumlahTroli = 0;
foreach ($_SESSION['cart'] ?? [] as $itemTroli) {
    $jumlahTroli += (int) ($itemTroli['jumlah'] ?? 0);
}

/* =========================================================
   KARTU PRODUK (tampilan sama dengan kartu di index.php,
   tombol hati = hapus dari watchlist)
========================================================= */

function kartuWatchlist(array $p): void
{
    $id     = (int) $p['id'];
    $stok   = (int) ($p['stok'] ?? $p['jumlah'] ?? 0);
    $diskon = (int) ($p['diskon_persen'] ?? 0);
    $asli   = (float) $p['harga'];
    $akhir  = round($asli * (100 - $diskon) / 100);
    ?>
    <article class="hp-card" data-id="<?= $id ?>">

        <div class="hp-card-media">

            <?php if ($diskon > 0): ?>
                <span class="hp-flag">-<?= $diskon ?>%</span>
            <?php endif; ?>

            <button
                type="button"
                class="btn-watch aktif"
                data-id="<?= $id ?>"
                aria-label="Hapus dari watchlist"
                title="Hapus dari watchlist"
            >❤️</button>

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
<!DOCTYPE html>
<html lang="id">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Watchlist - Joyzone Store</title>
    <link rel="icon" type="image/png" href="assets/images/logo-joyzone.png">

    <!-- CSS yang sama dengan index.php -->
    <link rel="stylesheet" href="./style.css?v=12">
    <link rel="stylesheet" href="index.css?v=1">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="home.css?v=1">

    <style>
        .wl-kosong {
            max-width: 520px;
            margin: 10px auto 0;
            padding: 48px 24px;
            text-align: center;
            border: 1px dashed #e5e5e1;
            border-radius: 14px;
        }

        .wl-kosong .ikon { font-size: 44px; margin-bottom: 10px; }
        .wl-kosong h3 { font-size: 20px; margin-bottom: 8px; }
        .wl-kosong p { color: #6b6b66; font-size: 14px; margin-bottom: 20px; }
        .wl-kosong .hp-btn { display: inline-flex; align-items: center; justify-content: center; }

        .wl-error {
            max-width: 640px;
            margin: 0 auto 20px;
            padding: 14px 18px;
            border-radius: 12px;
            background: #fdecea;
            color: #b71c1c;
            font-size: 14px;
        }

        .hp-card { transition: opacity .25s ease, transform .25s ease; }
        .hp-card.hilang { opacity: 0; transform: scale(.95); }
    </style>

</head>

<body class="home">

<div class="hp-topbar">Khusus pembeli usia 18 tahun ke atas. Pesanan dicek langsung oleh admin.</div>


<!-- ==================================================
     NAVBAR (sama dengan index.php)
================================================== -->

<nav class="navbar">

    <div class="nav-container">

        <div class="nav-logo">
            <a href="index.php" style="display:inline-flex;align-items:center;gap:10px;">
                <img src="assets/images/logo-joyzone.png" alt="Joyzone Vape" width="48" height="48" style="width:48px;height:48px;border-radius:50%;display:block;">
                <span>JOYZONE</span>
            </a>
        </div>

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


        <!-- MENU NAVBAR -->
        <div class="nav-menu" id="navMenu">

            <?php if ($namaUser !== null): ?>

                <a href="profil.php" class="user-account user-account-link" title="Ubah profil &amp; kata sandi">
                    <div class="account-header">
                        <div class="account-icon"><?php $fp = fotoProfilUrl((int) $userId); ?><?php if ($fp !== ''): ?><img src="<?= htmlspecialchars($fp) ?>" alt=""><?php else: ?>👤<?php endif; ?></div>
                        <div class="account-text">
                            <div class="account-label">Akun Saya</div>
                            <div class="account-name"><?= htmlspecialchars($namaUser) ?></div>
                            <div class="account-email"><?= htmlspecialchars($emailUser ?? '') ?></div>
                        </div>
                    </div>
                    <span class="account-arrow" aria-hidden="true">&rsaquo;</span>
                </a>

            <?php else: ?>

                <div class="user-account user-guest">
                    <div class="account-header">
                        <div class="account-icon">👤</div>
                        <div class="account-text">
                            <div class="account-label">Akun Saya</div>
                            <div class="account-email">Belum login</div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

            <div class="menu-divider"></div>

            <a href="index.php">Home</a>
            <a href="katalog.php">Katalog</a>
            <a href="riwayat_pesanan.php">🧾 Riwayat Pesanan</a>

            <?php if ($namaUser !== null): ?>
                <a href="logout.php" class="logout-link">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>

            <div class="menu-divider"></div>

            <a href="tentang.php">Tentang Kami</a>
            <a href="kontak.php">Kontak Kami</a>
            <a href="faq.php">FAQ</a>
            <a href="syarat.php">Syarat &amp; Ketentuan</a>
            <a href="privasi.php">Kebijakan Privasi</a>

        </div>

    </div>

</nav>


<!-- ==================================================
     ISI HALAMAN
================================================== -->

<main>

<section class="hp-section hp-all">

    <header class="hp-head hp-head-center">
        <h2>Watchlist</h2>
        <p id="subJudul"><?= $jumlahWatch ?> produk tersimpan</p>
    </header>

    <?php if ($dbError !== null): ?>
        <div class="wl-error"><?= htmlspecialchars($dbError) ?></div>
    <?php endif; ?>

    <?php if ($jumlahWatch > 0): ?>

        <div class="hp-grid" id="gridWatch">
            <?php foreach ($items as $p) { kartuWatchlist($p); } ?>
        </div>

    <?php endif; ?>

    <!-- KONDISI KOSONG (juga ditampilkan lewat JS saat item terakhir dihapus) -->
    <div class="wl-kosong" id="watchKosong"<?= $jumlahWatch > 0 ? ' style="display:none"' : '' ?>>
        <div class="ikon">🤍</div>
        <h3>Watchlist masih kosong</h3>
        <p>Tekan ikon hati pada produk untuk menyimpannya di sini.</p>
        <a href="index.php" class="hp-btn hp-btn-solid" style="padding:12px 22px;">Lihat produk</a>
    </div>

</section>

</main>


<!-- ==================================================
     FOOTER (sama dengan index.php)
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


<script>
/* BUKA / TUTUP MENU */
function toggleMenu() {
    document.getElementById("navMenu").classList.toggle("active");
}

/* TUTUP MENU JIKA KLIK DI LUAR NAVBAR */
document.addEventListener("click", function (event) {
    var navMenu = document.getElementById("navMenu");
    var navbar  = document.querySelector(".navbar");

    if (!navbar.contains(event.target)) {
        navMenu.classList.remove("active");
    }
});
</script>

<script>
/* ==================================================
   WATCHLIST: HAPUS PRODUK (AJAX)
================================================== */
(function () {

    var badge  = document.getElementById("watchBadge");
    var sub    = document.getElementById("subJudul");
    var kosong = document.getElementById("watchKosong");
    var grid   = document.getElementById("gridWatch");

    function updateTotal(total) {
        if (badge) {
            badge.textContent = total > 99 ? "99+" : total;
            badge.style.display = total > 0 ? "" : "none";
        }
        if (sub) sub.textContent = total + " produk tersimpan";
        if (total === 0) {
            if (grid) grid.style.display = "none";
            if (kosong) kosong.style.display = "";
        }
    }

    document.addEventListener("click", function (event) {

        var btn = event.target.closest(".btn-watch");
        if (!btn) { return; }

        event.preventDefault();
        if (btn.disabled) { return; }
        btn.disabled = true;

        var data = new FormData();
        data.append("product_id", btn.getAttribute("data-id"));
        data.append("aksi", "hapus");

        fetch("watchlist_toggle.php", {
            method: "POST",
            body: data,
            headers: { "X-Requested-With": "XMLHttpRequest" }
        })
        .then(function (res) { return res.json(); })
        .then(function (json) {

            if (json.login) { window.location.href = "login.php"; return; }

            if (!json.ok) {
                alert(json.pesan || "Gagal memproses watchlist.");
                btn.disabled = false;
                return;
            }

            var card = btn.closest(".hp-card");
            card.classList.add("hilang");
            setTimeout(function () { card.remove(); }, 250);
            updateTotal(json.total);
        })
        .catch(function () {
            alert("Terjadi kesalahan, coba lagi.");
            btn.disabled = false;
        });

    });

})();
</script>

</body>
</html>
