<?php
/*
 * Halaman Beli Langsung
 * Membutuhkan: metode_pembayaran.php (satu folder dengan file ini)
 * Form dikirim ke Checkout.php dengan field metode_pembayaran
 * (contoh nilai: "Virtual Account - BCA").
 */
require_once __DIR__ . '/home_data.php';
require_once __DIR__ . '/metode_pembayaran.php';

$id_produk = (int) ($_GET['id'] ?? $_POST['product_id'] ?? 0);
$jumlah    = max(1, (int) ($_GET['jumlah'] ?? $_POST['qty'] ?? 1));

if ($id_produk <= 0) {
    header("Location: katalog.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Beli Langsung - JoyZone Vape</title>
    <link rel="icon" type="image/png" href="assets/images/logo-joyzone.png">
    <link rel="stylesheet" href="style.css?v=12">
    <link rel="stylesheet" href="banner.css">
    <link rel="stylesheet" href="index.css?v=1">
    <link rel="stylesheet" href="metode-pembayaran.css?v=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="home.css?v=1">
    <link rel="stylesheet" href="menu-fix.css?v=1">
    <style>
        :root {
            --bg: #f5f6f8;
            --card: #ffffff;
            --text: #1c2230;
            --muted: #6b7385;
            --line: #dfe3ea;
            --accent: #2457d6;
            --accent-soft: #eaf0ff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.5;
        }
        .wrap { max-width: 640px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { font-size: 1.5rem; margin: 0 0 16px; }

        form.checkout {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 20px;
        }
        .field { margin-bottom: 16px; }
        .field label { display: block; font-weight: 600; margin-bottom: 6px; }
        .field input[type="text"],
        .field input[type="tel"],
        .field input[type="number"],
        .field textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            font: inherit;
            background: #fff;
        }
        .field input:focus, .field textarea:focus {
            outline: 2px solid var(--accent);
            outline-offset: 1px;
        }

        button.bayar {
            width: 100%;
            padding: 12px;
            border: 0;
            border-radius: 10px;
            background: var(--accent);
            color: #fff;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }
        button.bayar:hover { filter: brightness(1.08); }
    </style>
</head>
<body class="home">
<?php require __DIR__ . '/header_home.php'; ?>
<div class="wrap">
    <h1>Beli Langsung</h1>

    <form class="checkout" action="Checkout.php" method="post">
        <input type="hidden" name="id_produk" value="<?= $id_produk ?>">

        <div class="field">
            <label for="jumlah">Jumlah</label>
            <input type="number" id="jumlah" name="jumlah" min="1" value="<?= $jumlah ?>" required>
        </div>

        <div class="field">
            <label for="nama">Nama penerima</label>
            <input type="text" id="nama" name="nama" required>
        </div>

        <div class="field">
            <label for="telepon">Nomor telepon</label>
            <input type="tel" id="telepon" name="telepon" required>
        </div>

        <?php tampilMetodePembayaran(); ?>

        <button type="submit" class="bayar">Lanjut ke pembayaran</button>
    </form>
</div>
<?php require __DIR__ . '/footer_home.php'; ?>
</body>
</html>
