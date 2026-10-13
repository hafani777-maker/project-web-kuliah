<?php

session_start();

require_once 'config/koneksi.php';

$orderId = $_SESSION['order_id'] ?? 0;

if (!$orderId) {
    die("Pesanan tidak ditemukan.");
}


/* ==================================================
   CEK STATUS PESANAN
================================================== */

$stmtStatus = $pdo->prepare("
    SELECT id, status
    FROM orders
    WHERE id = ?
    LIMIT 1
");

$stmtStatus->execute([$orderId]);

$orderStatus = $stmtStatus->fetch(PDO::FETCH_ASSOC);

if (!$orderStatus) {
    die("Pesanan tidak ditemukan.");
}


/* =========================================================
   CEK LOGIN & PESANAN
========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['order_id'])) {
    header("Location: cart.php");
    exit;
}

$user_id  = (int) $_SESSION['user_id'];
$order_id = (int) $_SESSION['order_id'];


/* =========================================================
   AMBIL DATA PESANAN + PEMBAYARAN
   (pesanan harus milik user yang sedang login)
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.total,
        p.metode,
        p.channel,
        p.jumlah,
        p.status AS status_pembayaran
    FROM orders o
    JOIN payments p ON p.order_id = o.id
    WHERE o.id = ?
      AND o.user_id = ?
    LIMIT 1
");

$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: cart.php");
    exit;
}


/* =========================================================
   AMBIL ITEM PESANAN
========================================================= */

$stmtItems = $pdo->prepare("
    SELECT
        d.jumlah,
        d.harga,
        pr.nama
    FROM order_details d
    JOIN products pr ON pr.id = d.product_id
    WHERE d.order_id = ?
");

$stmtItems->execute([$order_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   DATA TAMPILAN
========================================================= */

$metode  = $order['metode'];
$channel = $order['channel'];
$jumlah  = (float) $order['jumlah'];
$statusPesanan = $orderStatus['status'];

if ($statusPesanan === 'Diproses') {
    $status = 'Diproses';   $statusClass = 'ok';
} elseif ($statusPesanan === 'Dibatalkan') {
    $status = 'Dibatalkan'; $statusClass = 'bad';
} else {
    $status = 'Menunggu';   $statusClass = '';
}   
function rupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

/*
 * Nomor tujuan (SIMULASI).
 * - Virtual Account : prefix bank + nomor pesanan
 * - E-Wallet        : nomor contoh
 */
if ($metode === 'Virtual Account') {

    $prefix = [
        'Mandiri' => '70012',
        'BCA'     => '39581',
        'BNI'     => '8808',
        'BRI'     => '26215'
    ];

    $nomorTujuan = ($prefix[$channel] ?? '99999')
                 . str_pad((string) $order_id, 10, '0', STR_PAD_LEFT);

    $labelNomor = 'Nomor Virtual Account ' . $channel;

    $langkah = [
        'Buka aplikasi m-banking atau ATM ' . $channel . '.',
        'Pilih menu Transfer, lalu Virtual Account.',
        'Masukkan nomor Virtual Account di atas.',
        'Pastikan jumlah tagihan sesuai, lalu selesaikan pembayaran.'
    ];

} else {

    $nomorTujuan = '087739811285';

    $labelNomor = 'Nomor ' . $channel;

    $langkah = [
        'Buka aplikasi ' . $channel . ' di ponsel kamu.',
        'Pilih menu Kirim atau Transfer.',
        'Masukkan nomor ' . $channel . ' di atas.',
        'Pastikan jumlah sesuai, lalu selesaikan pembayaran.'
    ];

}

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pembayaran - Joyzone Store</title>
    <link rel="icon" type="image/png" href="assets/images/logo-joyzone.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>

        :root {
            --bg:      #f4f5f3;
            --surface: #ffffff;
            --ink:     #1c1f1d;
            --muted:   #6b716d;
            --line:    #e3e6e2;
            --accent:  #1f6f54;
            --accent-dark: #17543f;
            --wait-bg: #fff3d1;
            --wait-fg: #8a5a00;
            --radius:  14px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif;
            background: var(--bg);
            color: var(--ink);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* ---------- HEADER ---------- */

        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--line);
        }

        .topbar-inner {
            max-width: 1040px;
            margin: 0 auto;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
        }

        .brand-mark {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
            flex-shrink: 0;
        }

        .brand-name { font-weight: 800; font-size: 18px; line-height: 1.1; }
        .brand-sub  { font-size: 12px; color: var(--muted); }

        /* ---------- HALAMAN ---------- */

        .page {
            max-width: 1040px;
            margin: 0 auto;
            padding: 40px 24px 64px;
        }

        .page-head { margin-bottom: 28px; }

        .page-head h1 {
            font-size: 34px;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.15;
        }

        .page-head p {
            margin-top: 6px;
            color: var(--muted);
        }

        .layout {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 28px;
        }

        .card-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 24px;
        }

        .card-head h2 {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .card-head small {
            display: block;
            margin-top: 2px;
            color: var(--muted);
            font-size: 14px;
        }

        /* ---------- STATUS ---------- */

        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 14px;
            border-radius: 999px;
            background: var(--wait-bg);
            color: var(--wait-fg);
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }

        /* ---------- NOMOR TUJUAN (elemen utama) ---------- */

        .pay-number {
            background: var(--ink);
            color: #fff;
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 14px;
        }

        .pay-number-label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }

        .pay-number-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .pay-number-value {
            font-size: 30px;
            font-weight: 800;
            letter-spacing: 0.04em;
            font-variant-numeric: tabular-nums;
            word-break: break-all;
        }

        .btn-copy {
            border: 1px solid rgba(255, 255, 255, 0.35);
            background: transparent;
            color: #fff;
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            padding: 9px 16px;
            border-radius: 9px;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .btn-copy:hover { background: #fff; color: var(--ink); }

        .btn-copy:focus-visible {
            outline: 3px solid #8fd6b9;
            outline-offset: 2px;
        }

        .btn-copy.is-copied {
            background: #fff;
            color: var(--accent);
            border-color: #fff;
        }

        /* ---------- JUMLAH ---------- */

        .pay-amount {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 18px 24px;
            margin-bottom: 28px;
        }

        .pay-amount span { color: var(--muted); font-size: 14px; }

        .pay-amount strong {
            font-size: 22px;
            font-weight: 800;
            color: var(--accent);
            font-variant-numeric: tabular-nums;
        }

        /* ---------- LANGKAH ---------- */

        .steps-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .steps {
            list-style: none;
            counter-reset: step;
            display: grid;
            gap: 14px;
        }

        .steps li {
            counter-increment: step;
            display: flex;
            gap: 14px;
            align-items: flex-start;
            font-size: 15px;
        }

        .steps li::before {
            content: counter(step);
            flex-shrink: 0;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--bg);
            border: 1px solid var(--line);
            display: grid;
            place-items: center;
            font-size: 13px;
            font-weight: 700;
        }

        .note {
            margin-top: 26px;
            padding: 14px 16px;
            background: var(--bg);
            border-radius: 10px;
            font-size: 13px;
            color: var(--muted);
        }

        /* ---------- RINGKASAN ---------- */

        .summary { position: sticky; top: 24px; }

        .summary h2 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .summary-order {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 18px;
        }

        .summary-items {
            list-style: none;
            border-top: 1px solid var(--line);
        }

        .summary-items li {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 0;
            border-bottom: 1px solid var(--line);
            font-size: 14px;
        }

        .summary-items .name { font-weight: 600; }
        .summary-items .qty  { color: var(--muted); font-size: 13px; }

        .summary-items .sub {
            font-weight: 600;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding-top: 18px;
        }

        .summary-total span { color: var(--muted); font-size: 14px; }

        .summary-total strong {
            font-size: 22px;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .summary-method {
            display: flex;
            justify-content: space-between;
            margin-top: 12px;
            font-size: 14px;
        }

        .summary-method span { color: var(--muted); }
        .summary-method strong { font-weight: 600; }

        .btn-home {
            display: block;
            margin-top: 22px;
            text-align: center;
            text-decoration: none;
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid var(--line);
            color: var(--ink);
            font-weight: 600;
            font-size: 14px;
            transition: background 0.2s ease;
        }

        .btn-home:hover { background: var(--bg); }

        .btn-home:focus-visible {
            outline: 3px solid var(--accent);
            outline-offset: 2px;
        }

        /* ---------- RESPONSIVE ---------- */

        @media (max-width: 860px) {

            .layout { grid-template-columns: minmax(0, 1fr); }

            .summary { position: static; }

            .page { padding: 28px 16px 48px; }

            .page-head h1 { font-size: 28px; }

            .card { padding: 22px; }

            .pay-number-value { font-size: 24px; }

        }

        @media (max-width: 480px) {

            .card-head { flex-direction: column; }

            .pay-amount { flex-direction: column; align-items: flex-start; gap: 4px; }

        }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; }
        }


        .status.ok  { background: #d9f2e6; color: #17543f; }
        .status.bad { background: #fde0e0; color: #a12626; }

    </style>

</head>

<body>

    <header class="topbar">
        <div class="topbar-inner">
            <a href="index.php" class="brand">
                <img class="brand-mark" src="assets/images/logo-joyzone.png" alt="Joyzone Vape" width="44" height="44">
                <div>
                    <div class="brand-name">Joyzone</div>
                    <div class="brand-sub">Online store</div>
                </div>
            </a>
        </div>
    </header>


    <main class="page">

        <div class="page-head">
            <h1>Selesaikan pembayaran</h1>
            <p>Pesanan #<?= (int) $order['id'] ?> sudah dibuat. Lakukan pembayaran sesuai petunjuk di bawah.</p>
        </div>


        <div class="layout">

            <!-- ===== INSTRUKSI PEMBAYARAN ===== -->
            <section class="card">

                <div class="card-head">
                    <div>
                        <h2>Bayar lewat <?= htmlspecialchars($channel) ?></h2>
                        <small><?= htmlspecialchars($metode) ?></small>
                    </div>

                    <span class="status <?= $statusClass ?>" id="statusBadge">
                        <?= htmlspecialchars($status) ?>
                    </span>
                </div>


                <div class="pay-number">
                    <div class="pay-number-label">
                        <?= htmlspecialchars($labelNomor) ?>
                    </div>

                    <div class="pay-number-row">
                        <div class="pay-number-value" id="nomorTujuan">
                            <?= htmlspecialchars($nomorTujuan) ?>
                        </div>

                        <button
                            type="button"
                            class="btn-copy"
                            id="btnCopy"
                            data-nomor="<?= htmlspecialchars($nomorTujuan) ?>"
                        >
                            Salin nomor
                        </button>
                    </div>
                </div>


                <div class="pay-amount">
                    <span>Jumlah yang harus dibayar</span>
                    <strong><?= rupiah($jumlah) ?></strong>
                </div>


                <h3 class="steps-title">Cara membayar</h3>

                <ol class="steps">
                    <?php foreach ($langkah as $teks): ?>
                        <li><?= htmlspecialchars($teks) ?></li>
                    <?php endforeach; ?>
                </ol>


                <p class="note">
                    Ini adalah simulasi pembayaran untuk website Joyzone Store.
                    Tidak ada uang yang benar-benar dipindahkan.
                </p>

            </section>


            <!-- ===== RINGKASAN PESANAN ===== -->
            <aside class="card summary">

                <h2>Ringkasan pesanan</h2>
                <p class="summary-order">Pesanan #<?= (int) $order['id'] ?></p>

                <ul class="summary-items">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <div>
                                <div class="name"><?= htmlspecialchars($item['nama']) ?></div>
                                <div class="qty">
                                    <?= (int) $item['jumlah'] ?> x <?= rupiah($item['harga']) ?>
                                </div>
                            </div>

                            <div class="sub">
                                <?= rupiah((float) $item['harga'] * (int) $item['jumlah']) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="summary-total">
                    <span>Total pembayaran</span>
                    <strong><?= rupiah($order['total']) ?></strong>
                </div>

                <div class="summary-method">
                    <span>Metode</span>
                    <strong><?= htmlspecialchars($metode . ' - ' . $channel) ?></strong>
                </div>

                <a href="index.php" class="btn-home">Kembali ke beranda</a>

            </aside>

        </div>

    </main>


    <script>

        (function () {

            var tombol = document.getElementById('btnCopy');

            if (!tombol) return;

            tombol.addEventListener('click', function () {

                var nomor = tombol.getAttribute('data-nomor');

                function tandai() {
                    tombol.textContent = 'Nomor tersalin';
                    tombol.classList.add('is-copied');

                    setTimeout(function () {
                        tombol.textContent = 'Salin nomor';
                        tombol.classList.remove('is-copied');
                    }, 2000);
                }

                if (navigator.clipboard && window.isSecureContext) {

                    navigator.clipboard.writeText(nomor).then(tandai);

                } else {

                    /* Cadangan untuk localhost tanpa HTTPS */
                    var temp = document.createElement('textarea');
                    temp.value = nomor;
                    document.body.appendChild(temp);
                    temp.select();
                    document.execCommand('copy');
                    document.body.removeChild(temp);
                    tandai();

                }

            });

        })();

    </script>

    <script>

        (function () {

            var orderId = <?= (int) $order['id'] ?>;
            var badge = document.getElementById('statusBadge');
            var selesai = false;

            function cek() {

                if (selesai) return;

                fetch('cek_status_pesanan.php?order_id=' + encodeURIComponent(orderId), { cache: 'no-store' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {

                        if (!data.success) return;

                        if (data.status === 'Diproses') {
                            selesai = true;
                            badge.textContent = 'Diproses';
                            badge.className = 'status ok';
                            alert('Pesanan telah dikonfirmasi');
                        } else if (data.status === 'Dibatalkan') {
                            selesai = true;
                            badge.textContent = 'Dibatalkan';
                            badge.className = 'status bad';
                            alert('Pesanan telah ditolak');
                        }

                    })
                    .catch(function (e) { console.log('Gagal mengecek status:', e); });

            }

            setInterval(cek, 3000);

        })();

    </script>

</body>

</html>