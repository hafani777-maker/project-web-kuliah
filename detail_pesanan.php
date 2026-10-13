<?php

session_start();

require_once 'config/koneksi.php';

/* =========================================================
   CEK LOGIN
========================================================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id  = (int) $_SESSION['user_id'];
$order_id = (int) ($_GET['id'] ?? 0);

if ($order_id <= 0) {
    die("ID pesanan tidak ditemukan. <a href='index.php'>Kembali</a>");
}

/* =========================================================
   DATA PESANAN (hanya milik user yang sedang login)
========================================================= */
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Pesanan tidak ditemukan. <a href='index.php'>Kembali</a>");
}

/* DAFTAR PRODUK DALAM PESANAN */
$stmt = $pdo->prepare("
    SELECT d.product_id, d.jumlah, d.harga, p.nama, p.gambar
    FROM order_details d
    LEFT JOIN products p ON p.id = d.product_id
    WHERE d.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* DATA PEMBAYARAN */
$stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? LIMIT 1");
$stmt->execute([$order_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================================================
   HELPER
========================================================= */
function urlGambar($nama)
{
    if ($nama === '' || $nama === null) {
        return '';
    }

    $kandidat = [
        $nama,
        'assets/images/' . $nama,
        'images/'  . $nama,
        'uploads/' . $nama,
        'img/'     . $nama
    ];

    foreach ($kandidat as $path) {
        if (is_file(__DIR__ . '/' . $path)) {
            return $path;
        }
    }

    return '';
}

function kelasStatus($status)
{
    $s = strtolower($status);

    if (strpos($s, 'menunggu') !== false) return 'status-menunggu';
    if (strpos($s, 'batal') !== false || strpos($s, 'gagal') !== false) return 'status-batal';
    if (strpos($s, 'selesai') !== false || strpos($s, 'lunas') !== false || strpos($s, 'dibayar') !== false) return 'status-selesai';

    return 'status-proses';
}

$perluBayar = stripos($order['status'], 'menunggu') !== false;

// payment.php membaca order dari session, jadi diarahkan ke pesanan ini
if ($perluBayar) {
    $_SESSION['order_id'] = $order['id'];
}

$kembali = 'index.php';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?= (int) $order['id'] ?> - Joyzone Store</title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f5f5;
            color: #222;
        }

        .container { max-width: 1000px; margin: 40px auto; padding: 20px; }

        h1 { margin-bottom: 25px; font-size: 32px; }

        .box {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 22px;
        }

        .box-title {
            padding: 18px 25px;
            font-size: 18px;
            font-weight: bold;
            border-bottom: 1px solid #eee;
        }

        /* INFO PESANAN */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px 30px;
            padding: 25px;
        }

        .info-item .label {
            font-size: 13px;
            color: #888;
            margin-bottom: 5px;
        }

        .info-item .value {
            font-size: 16px;
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }

        .status-menunggu { background: #fff3dd; color: #b26a00; }
        .status-proses   { background: #e3eefc; color: #1d5fbf; }
        .status-selesai  { background: #e3f5e8; color: #1b7a3a; }
        .status-batal    { background: #fdeaea; color: #b3261e; }

        /* DAFTAR PRODUK */
        .cart-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
        }

        .cart-image {
            width: 90px;
            height: 90px;
            flex-shrink: 0;
            background: #f5f3ee;
            border-radius: 12px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .cart-image img { width: 100%; height: 100%; object-fit: contain; padding: 8px; }

        .cart-info { flex: 1; }
        .cart-info h3 { margin-bottom: 7px; font-size: 18px; }
        .price { font-weight: bold; margin-bottom: 6px; }
        .jumlah { color: #666; margin-bottom: 6px; font-size: 14px; }
        .subtotal strong { font-size: 15px; }

        .cart-footer { padding: 25px; }

        .total {
            display: flex;
            justify-content: space-between;
            font-size: 21px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .button-group { display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }

        .btn-primary,
        .btn-back {
            display: inline-block;
            padding: 12px 18px;
            border-radius: 9px;
            text-decoration: none;
            font-weight: bold;
            font-size: 15px;
            transition: 0.2s ease;
        }

        .btn-back { background: #eee; color: #222; }
        .btn-back:hover { background: #ddd; }

        .btn-primary { background: #222; color: #fff; }
        .btn-primary:hover { background: #444; transform: translateY(-1px); }

        .kosong { padding: 30px; text-align: center; color: #888; }

        @media (max-width: 700px) {
            .container { margin: 20px auto; padding: 15px; }
            h1 { font-size: 26px; }
            .info-grid { grid-template-columns: 1fr; padding: 20px; }
            .cart-item { padding: 18px 20px; }
            .button-group { flex-direction: column; }
            .btn-primary, .btn-back { text-align: center; }
        }

        @media (max-width: 480px) {
            .cart-item { flex-direction: column; align-items: flex-start; }
            .cart-image { width: 100%; height: 160px; }
        }
    </style>
</head>

<body>

<div class="container">

    <h1>🧾 Detail Pesanan #<?= (int) $order['id'] ?></h1>

    <!-- INFO PESANAN & PEMBAYARAN -->
    <div class="box">
        <div class="box-title">Informasi Pesanan</div>

        <div class="info-grid">

            <div class="info-item">
                <div class="label">Nomor Pesanan</div>
                <div class="value">#<?= (int) $order['id'] ?></div>
            </div>

            <div class="info-item">
                <div class="label">Tanggal Pesanan</div>
                <div class="value"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></div>
            </div>

            <div class="info-item">
                <div class="label">Status Pesanan</div>
                <div class="value">
                    <span class="badge <?= kelasStatus($order['status']) ?>">
                        <?= htmlspecialchars($order['status']) ?>
                    </span>
                </div>
            </div>

            <div class="info-item">
                <div class="label">Metode Pembayaran</div>
                <div class="value">
                    <?php if ($payment): ?>
                        <?= htmlspecialchars($payment['metode']) ?> - <?= htmlspecialchars($payment['channel']) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($payment): ?>
                <div class="info-item">
                    <div class="label">Status Pembayaran</div>
                    <div class="value">
                        <span class="badge <?= kelasStatus($payment['status']) ?>">
                            <?= htmlspecialchars($payment['status']) ?>
                        </span>
                    </div>
                </div>

                <div class="info-item">
                    <div class="label">Jumlah Pembayaran</div>
                    <div class="value">Rp <?= number_format((float) $payment['jumlah'], 0, ',', '.') ?></div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- PRODUK -->
    <div class="box">
        <div class="box-title">Produk yang Dipesan</div>

        <?php if (count($items) > 0): ?>

            <?php foreach ($items as $item): ?>

                <?php $gambarUrl = urlGambar($item['gambar'] ?? ''); ?>

                <div class="cart-item">

                    <div class="cart-image">
                        <?php if ($gambarUrl !== ''): ?>
                            <img
                                src="<?= htmlspecialchars($gambarUrl) ?>"
                                alt="<?= htmlspecialchars($item['nama'] ?? 'Produk') ?>"
                            >
                        <?php else: ?>
                            <span style="font-size: 32px;">📦</span>
                        <?php endif; ?>
                    </div>

                    <div class="cart-info">
                        <h3><?= htmlspecialchars($item['nama'] ?? 'Produk telah dihapus') ?></h3>

                        <div class="price">
                            Rp <?= number_format((float) $item['harga'], 0, ',', '.') ?>
                        </div>

                        <div class="jumlah">Jumlah: <?= (int) $item['jumlah'] ?></div>

                        <div class="subtotal">
                            Subtotal:
                            <strong>
                                Rp <?= number_format((float) $item['harga'] * (int) $item['jumlah'], 0, ',', '.') ?>
                            </strong>
                        </div>
                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="kosong">Tidak ada produk pada pesanan ini.</div>

        <?php endif; ?>

        <div class="cart-footer">

            <div class="total">
                <span>Total</span>
                <span>Rp <?= number_format((float) $order['total'], 0, ',', '.') ?></span>
            </div>

            <div class="button-group">
                <a href="<?= htmlspecialchars($kembali) ?>" class="btn-back">← Kembali</a>

                <?php if ($perluBayar): ?>
                    <a href="payment.php" class="btn-primary">Bayar Sekarang →</a>
                <?php endif; ?>
            </div>

        </div>
    </div>

</div>

</body>
</html>
