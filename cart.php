<?php
session_start();
require_once 'config/koneksi.php';
require_once __DIR__ . '/home_data.php';
require_once __DIR__ . '/metode_pembayaran.php';

function urlGambar($nama) {
    if ($nama === '' || $nama === null) return '';
    $kandidat = [$nama, 'images/'.$nama, 'uploads/'.$nama, 'img/'.$nama, 'assets/images/'.$nama];
    foreach ($kandidat as $path) {
        if (is_file(__DIR__ . '/' . $path)) return $path;
    }
    return '';
}

// Tambahkan produk dari URL cart.php?id=ID
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($product) {
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['jumlah']++;
        } else {
            $diskon = (int)($product['diskon_persen'] ?? 0);
            $hargaAsli = (float)$product['harga'];
            $hargaAkhir = round($hargaAsli * (100 - $diskon) / 100);
            $_SESSION['cart'][$id] = [
                'id' => $product['id'], 'nama' => $product['nama'],
                'harga' => $hargaAkhir, 'harga_asli' => $hargaAsli,
                'diskon' => $diskon, 'gambar' => $product['gambar'] ?? '', 'jumlah' => 1
            ];
        }
    }
    header('Location: cart.php');
    exit;
}

// Ubah jumlah atau hapus item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'], $_POST['produk_id'])) {
    $produkId = (int)$_POST['produk_id'];
    $aksi = (string)$_POST['aksi'];
    if (isset($_SESSION['cart'][$produkId])) {
        if ($aksi === 'tambah') $_SESSION['cart'][$produkId]['jumlah']++;
        if ($aksi === 'kurang') {
            $_SESSION['cart'][$produkId]['jumlah']--;
            if ($_SESSION['cart'][$produkId]['jumlah'] < 1) unset($_SESSION['cart'][$produkId]);
        }
        if ($aksi === 'hapus') unset($_SESSION['cart'][$produkId]);
    }
    header('Location: cart.php');
    exit;
}

$cart = $_SESSION['cart'] ?? [];
$total = 0;
$jumlah_troli = 0;
foreach ($cart as $item) {
    $total += (float)($item['harga'] ?? 0) * (int)($item['jumlah'] ?? 0);
    $jumlah_troli += (int)($item['jumlah'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Keranjang Saya - Joyzone Store</title>
<link rel="icon" type="image/png" href="assets/images/logo-joyzone.png">
<link rel="stylesheet" href="cart.css?v=3">
<link rel="stylesheet" href="metode-pembayaran.css?v=2">
<link rel="stylesheet" href="style.css?v=12">
<link rel="stylesheet" href="banner.css">
<link rel="stylesheet" href="index.css?v=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="home.css?v=1">
<link rel="stylesheet" href="menu-fix.css?v=1">
</head>
<body class="home cart-page">
<?php require __DIR__ . '/header_home.php'; ?>

<main class="cart-shell">
    <div class="cart-title-row">
        <a class="cart-back-arrow" href="index.php" aria-label="Kembali">‹</a>
        <h1>Keranjang Saya <span>(<?= (int)$jumlah_troli ?>)</span></h1>
        <a class="cart-continue" href="katalog.php">Belanja lagi</a>
    </div>

    <?php if (!empty($cart)): ?>
        <section class="cart-list" aria-label="Daftar produk dalam keranjang">
            <div class="cart-list-head">
                <span>Produk</span><span>Harga</span><span>Jumlah</span><span>Subtotal</span><span></span>
            </div>
            <?php foreach ($cart as $key => $item): ?>
                <?php $gambarUrl = urlGambar($item['gambar'] ?? ''); $qty = max(1, (int)($item['jumlah'] ?? 1)); $harga = (float)($item['harga'] ?? 0); ?>
                <article class="cart-product-row">
                    <div class="cart-product-main">
                        <div class="cart-product-image<?= $gambarUrl === '' ? ' no-image' : '' ?>">
                            <?php if ($gambarUrl !== ''): ?>
                                <img src="<?= htmlspecialchars($gambarUrl) ?>" alt="<?= htmlspecialchars($item['nama'] ?? 'Produk') ?>">
                            <?php else: ?><span>📦</span><?php endif; ?>
                        </div>
                        <div class="cart-product-text">
                            <h2><?= htmlspecialchars($item['nama'] ?? 'Produk') ?></h2>
                            <?php if (($item['diskon'] ?? 0) > 0): ?>
                                <span class="cart-discount-tag">Diskon <?= (int)$item['diskon'] ?>%</span>
                            <?php endif; ?>
                            <span class="cart-mobile-price">Rp <?= number_format($harga, 0, ',', '.') ?></span>
                        </div>
                    </div>
                    <div class="cart-price-column">
                        <?php if (($item['diskon'] ?? 0) > 0): ?><del>Rp <?= number_format((float)($item['harga_asli'] ?? $harga), 0, ',', '.') ?></del><?php endif; ?>
                        <strong>Rp <?= number_format($harga, 0, ',', '.') ?></strong>
                    </div>
                    <div class="cart-quantity">
                        <form method="post" action="cart.php">
                            <input type="hidden" name="produk_id" value="<?= (int)$key ?>">
                            <input type="hidden" name="aksi" value="kurang">
                            <button type="submit" aria-label="Kurangi jumlah">−</button>
                        </form>
                        <span><?= $qty ?></span>
                        <form method="post" action="cart.php">
                            <input type="hidden" name="produk_id" value="<?= (int)$key ?>">
                            <input type="hidden" name="aksi" value="tambah">
                            <button type="submit" aria-label="Tambah jumlah">+</button>
                        </form>
                    </div>
                    <div class="cart-subtotal">Rp <?= number_format($harga * $qty, 0, ',', '.') ?></div>
                    <form class="cart-remove-form" method="post" action="cart.php" onsubmit="return confirm('Hapus produk ini dari keranjang?')">
                        <input type="hidden" name="produk_id" value="<?= (int)$key ?>">
                        <input type="hidden" name="aksi" value="hapus">
                        <button type="submit" aria-label="Hapus produk" title="Hapus produk">×</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="cart-summary">
            <div class="cart-summary-label"><span>Ringkasan Belanja</span><span><?= (int)$jumlah_troli ?> barang</span></div>
            <div class="cart-summary-line"><span>Total harga produk</span><strong>Rp <?= number_format($total, 0, ',', '.') ?></strong></div>
            <div class="cart-summary-total"><span>Total</span><strong>Rp <?= number_format($total, 0, ',', '.') ?></strong></div>
            <form action="Checkout.php" method="POST" class="cart-checkout-form">
                <?php tampilMetodePembayaran(); ?>
                <button type="submit" class="cart-checkout-button">Lanjut ke Checkout <span>→</span></button>
            </form>
        </section>
    <?php else: ?>
        <section class="cart-empty">
            <div class="cart-empty-icon">🛒</div>
            <h2>Keranjang masih kosong</h2>
            <p>Yuk, temukan produk yang kamu butuhkan.</p>
            <a href="katalog.php" class="cart-empty-button">Mulai Belanja</a>
        </section>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/footer_home.php'; ?>
</body>
</html>