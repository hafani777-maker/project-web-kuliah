<?php
 
session_start();

$cartFlash = $_SESSION['cart_flash'] ?? null;
unset($_SESSION['cart_flash']);
 
require_once 'config/koneksi.php';

// Endpoint AJAX: tambah produk ke troli tanpa meninggalkan halaman detail.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah_troli_ajax') {
    header('Content-Type: application/json; charset=utf-8');

    $idAjax = filter_input(INPUT_POST, 'id_produk', FILTER_VALIDATE_INT);
    $jumlahAjax = filter_input(INPUT_POST, 'jumlah', FILTER_VALIDATE_INT);
    $jumlahAjax = max(1, (int) ($jumlahAjax ?: 1));

    if (!$idAjax || $idAjax < 1) {
        echo json_encode(['success' => false, 'message' => 'Produk tidak valid.']);
        exit;
    }

    $stmtAjax = $pdo->prepare("SELECT id, nama, harga, gambar, stok, diskon_persen FROM products WHERE id = ? LIMIT 1");
    $stmtAjax->execute([$idAjax]);
    $produkAjax = $stmtAjax->fetch(PDO::FETCH_ASSOC);

    if (!$produkAjax) {
        echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan.']);
        exit;
    }

    $stokAjax = (int) ($produkAjax['stok'] ?? 0);
    if ($stokAjax < 1) {
        echo json_encode(['success' => false, 'message' => 'Stok produk sedang habis.']);
        exit;
    }

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Cari produk yang sama agar jumlahnya bertambah, bukan membuat duplikat.
    $ditemukan = false;
    foreach ($_SESSION['cart'] as &$itemAjax) {
        if ((int) ($itemAjax['id'] ?? 0) === (int) $produkAjax['id']) {
            $jumlahLama = (int) ($itemAjax['jumlah'] ?? 0);
            if ($jumlahLama + $jumlahAjax > $stokAjax) {
                unset($itemAjax);
                echo json_encode([
                    'success' => false,
                    'message' => 'Jumlah melebihi stok yang tersedia (' . $stokAjax . ').'
                ]);
                exit;
            }
            $itemAjax['jumlah'] = $jumlahLama + $jumlahAjax;
            $ditemukan = true;
            break;
        }
    }
    unset($itemAjax);

    if (!$ditemukan) {
        if ($jumlahAjax > $stokAjax) {
            echo json_encode([
                'success' => false,
                'message' => 'Jumlah melebihi stok yang tersedia (' . $stokAjax . ').'
            ]);
            exit;
        }

        $_SESSION['cart'][] = [
            'id' => (int) $produkAjax['id'],
            'nama' => $produkAjax['nama'],
            'harga' => (float) $produkAjax['harga'],
            'gambar' => $produkAjax['gambar'] ?? '',
            'jumlah' => $jumlahAjax,
            'diskon_persen' => (int) ($produkAjax['diskon_persen'] ?? 0)
        ];
    }

    $jumlahSemua = 0;
    foreach ($_SESSION['cart'] as $itemHitung) {
        $jumlahSemua += (int) ($itemHitung['jumlah'] ?? 0);
    }

    echo json_encode([
        'success' => true,
        'message' => $produkAjax['nama'] . ' berhasil ditambahkan ke troli.',
        'cart_count' => $jumlahSemua
    ]);
    exit;
}
 
$id = $_GET['id'] ?? 0;
 
if (!$id) {
    die("ID produk tidak ditemukan.");
}
 
$sql = "SELECT * FROM products WHERE id = ?";
 
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
 
$product = $stmt->fetch();
 
if (!$product) {
    die("Produk tidak ditemukan.");
}

// Catat untuk fitur "Terakhir Dilihat & Dipesan"
require_once __DIR__ . '/terakhir_dilihat.php';
catatDilihat($pdo, (int) $product['id']);

// Status watchlist (sudah disimpan oleh user yang login atau belum)
$sudahWatch  = false;
$userIdLogin = (int) ($_SESSION['user_id'] ?? 0);

if ($userIdLogin > 0) {
    try {
        $stmtW = $pdo->prepare("SELECT 1 FROM watchlist WHERE user_id = ? AND product_id = ? LIMIT 1");
        $stmtW->execute([$userIdLogin, $product['id']]);
        $sudahWatch = (bool) $stmtW->fetchColumn();
    } catch (PDOException $e) {
        $sudahWatch = false; // tabel watchlist belum dibuat
    }
}
 
 
/* =========================================================
   DATA ULASAN / PENILAIAN PRODUK
========================================================= */
 
// Samarkan nama: "hafani@gmail.com" -> "h*****i"
function maskNama($s)
{
    $s = trim($s);
    if (strpos($s, '@') !== false) {
        $s = substr($s, 0, strpos($s, '@'));
    }
    $len = strlen($s);
    if ($len <= 2) {
        return $s;
    }
    return $s[0] . str_repeat('*', min($len - 2, 5)) . $s[$len - 1];
}
 
// Cari nama pengguna yang sedang login (sesuaikan kalau nama session kamu berbeda)
$namaLogin = '';
foreach (['nama', 'username', 'name', 'nama_user', 'email', 'user_email'] as $k) {
    if (!empty($_SESSION[$k]) && is_string($_SESSION[$k])) {
        $namaLogin = $_SESSION[$k];
        break;
    }
}
if ($namaLogin === '' && !empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    foreach (['nama', 'username', 'name', 'email'] as $k) {
        if (!empty($_SESSION['user'][$k])) {
            $namaLogin = (string) $_SESSION['user'][$k];
            break;
        }
    }
}
 
// ----- Kirim ulasan baru -----
$errorUlasan = '';
$old = ['rating' => 0, 'komentar' => '', 'nama' => ''];
 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_ulasan'])) {
 
    $rating   = (int) ($_POST['rating'] ?? 0);
    $komentar = trim($_POST['komentar'] ?? '');
    $namaIsi  = trim($_POST['nama'] ?? '');
    $nama     = $namaLogin !== '' ? maskNama($namaLogin) : $namaIsi;
 
    $old = ['rating' => $rating, 'komentar' => $komentar, 'nama' => $namaIsi];
 
    if ($rating < 1 || $rating > 5) {
        $errorUlasan = 'Pilih jumlah bintang terlebih dahulu.';
    } elseif ($nama === '') {
        $errorUlasan = 'Nama wajib diisi.';
    } elseif (strlen($komentar) < 5) {
        $errorUlasan = 'Komentar minimal 5 karakter.';
    } elseif (strlen($komentar) > 1000) {
        $errorUlasan = 'Komentar maksimal 1000 karakter.';
    }
 
    // Upload foto (opsional, maksimal 3, JPG/PNG/WEBP, 2 MB per foto)
    $fotoTersimpan = [];
 
    if ($errorUlasan === '' && !empty($_FILES['foto']['name'][0])) {
 
        $dir = __DIR__ . '/assets/images/reviews/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
 
        $izin  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
 
        foreach (array_slice($_FILES['foto']['tmp_name'], 0, 3, true) as $i => $tmp) {
 
            if ($_FILES['foto']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
 
            if ($_FILES['foto']['size'][$i] > 2 * 1024 * 1024) {
                $errorUlasan = 'Ukuran setiap foto maksimal 2 MB.';
                break;
            }
 
            $mime = $finfo->file($tmp);
 
            if (!isset($izin[$mime])) {
                $errorUlasan = 'Format foto harus JPG, PNG, atau WEBP.';
                break;
            }
 
            $namaFile = 'r' . (int) $product['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $izin[$mime];
 
            if (move_uploaded_file($tmp, $dir . $namaFile)) {
                $fotoTersimpan[] = $namaFile;
            }
        }
    }
 
    if ($errorUlasan === '') {
 
        $stmt = $pdo->prepare(
            "INSERT INTO reviews (product_id, nama_user, rating, variasi, komentar, foto)
             VALUES (?, ?, ?, 'Default', ?, ?)"
        );
        $stmt->execute([
            $product['id'],
            $nama,
            $rating,
            $komentar,
            $fotoTersimpan ? implode(',', $fotoTersimpan) : null
        ]);
 
        // Redirect agar refresh halaman tidak mengirim ulasan dua kali
        $_SESSION['flash_ulasan'] = 'Terima kasih! Ulasan kamu berhasil dikirim.';
        header('Location: detail_produk.php?id=' . (int) $product['id'] . '#ulasan');
        exit;
    }
}
 
$flashUlasan = $_SESSION['flash_ulasan'] ?? '';
unset($_SESSION['flash_ulasan']);
 
// Ringkasan: jumlah ulasan & rata-rata rating
$stmt = $pdo->prepare("SELECT COUNT(*) AS total, AVG(rating) AS rata FROM reviews WHERE product_id = ?");
$stmt->execute([$product['id']]);
$ringkasan = $stmt->fetch(PDO::FETCH_ASSOC);
 
$totalUlasan = (int) ($ringkasan['total'] ?? 0);
$rataRating  = $totalUlasan > 0 ? round((float) $ringkasan['rata'], 1) : 0;
 
// Daftar ulasan (terbaru dulu)
$stmt = $pdo->prepare("SELECT * FROM reviews WHERE product_id = ? ORDER BY created_at DESC, id DESC");
$stmt->execute([$product['id']]);
$ulasan = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
// Chip kata kunci (jumlahnya dihitung dari isi komentar)
$tagKunci = [
    'Kemasan Aman'     => ['kemasan', 'packing', 'bungkus', 'aman', 'rapi'],
    'Kualitas Bagus'   => ['kualitas', 'bagus', 'original', 'mantap'],
    'Pengiriman Cepat' => ['cepat', 'pengiriman', 'sampai']
];
 
$tagHitung = [];
foreach ($tagKunci as $label => $kataKunci) {
    $tagHitung[$label] = 0;
    foreach ($ulasan as $r) {
        $teks = strtolower($r['komentar'] ?? '');
        foreach ($kataKunci as $k) {
            if (strpos($teks, $k) !== false) {
                $tagHitung[$label]++;
                break;
            }
        }
    }
}
 
// Format jumlah: 25700 -> 25,7RB
function formatJumlah($n)
{
    if ($n >= 1000) {
        $x = rtrim(rtrim(number_format($n / 1000, 1, ',', '.'), '0'), ',');
        return $x . 'RB';
    }
    return (string) $n;
}
 
// Bintang (terisi + kosong)
function bintang($n)
{
    $n = max(0, min(5, (int) $n));
    return '<span class="bintang-isi">' . str_repeat('★', $n) . '</span>'
         . '<span class="bintang-kosong">' . str_repeat('★', 5 - $n) . '</span>';
}
 
// Warna avatar berdasarkan nama
function warnaAvatar($nama)
{
    $warna = ['#a24bd6', '#e5527a', '#2f9e8f', '#3b82f6', '#f59e0b', '#16a34a', '#ef4444'];
    return $warna[abs(crc32($nama)) % count($warna)];
}

/* =========================================================
   DATA TAMBAHAN UNTUK TAMPILAN BARU
========================================================= */

$jumlahTroli = 0;
foreach ($_SESSION['cart'] ?? [] as $itemTroli) {
    $jumlahTroli += (int) ($itemTroli['jumlah'] ?? 0);
}

$sudahLogin = $userIdLogin > 0;

// Cari file gambar produk (upload/ dulu, lalu assets/images/)
function gambarProduk($file)
{
    $file = basename(trim((string) $file));

    if ($file === '') {
        return null;
    }

    foreach (['upload/', 'assets/images/', 'assets/'] as $dir) {
        if (is_file(__DIR__ . '/' . $dir . $file)) {
            return $dir . rawurlencode($file);
        }
    }

    return 'assets/images/' . rawurlencode($file);
}

function rp($n)
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function hargaSetelahDiskon(array $p)
{
    return round((float) $p['harga'] * (100 - (int) ($p['diskon_persen'] ?? 0)) / 100);
}

function tglIndo($s)
{
    static $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                     'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    $t = strtotime((string) $s);

    if (!$t) {
        return '';
    }

    return date('j', $t) . ' ' . $bulan[(int) date('n', $t)] . ' ' . date('Y, H:i', $t);
}

// Sebaran bintang (5 sampai 1) dari daftar ulasan yang sudah diambil
$sebaran = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($ulasan as $r) {
    $n = (int) $r['rating'];
    if (isset($sebaran[$n])) {
        $sebaran[$n]++;
    }
}

// Produk terkait & terpopuler (urut jumlah ulasan)
function ambilProdukKartu(PDO $pdo, $where, array $params, $limit)
{
    try {
        $st = $pdo->prepare(
            "SELECT p.*, COUNT(r.id) AS jml_ulasan, AVG(r.rating) AS rata_ulasan
             FROM products p
             LEFT JOIN reviews r ON r.product_id = p.id
             WHERE $where
             GROUP BY p.id
             ORDER BY jml_ulasan DESC, p.id DESC
             LIMIT " . (int) $limit
        );
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

$produkTerkait = ambilProdukKartu(
    $pdo,
    'p.id <> ? AND p.kategori = ?',
    [$product['id'], $product['kategori']],
    5
);

$dikecualikan = array_merge([$product['id']], array_column($produkTerkait, 'id'));

$produkPopuler = ambilProdukKartu(
    $pdo,
    'p.id NOT IN (' . implode(',', array_fill(0, count($dikecualikan), '?')) . ')',
    $dikecualikan,
    5
);

// Kartu produk kecil (dipakai di dua bagian)
function kartuMini(array $p)
{
    $img    = gambarProduk($p['gambar'] ?? '');
    $diskon = (int) ($p['diskon_persen'] ?? 0);
    $jml    = (int) ($p['jml_ulasan'] ?? 0);
    ?>

    <a class="mini" href="detail_produk.php?id=<?= (int) $p['id'] ?>">

        <span class="mini-img">
            <?php if ($diskon > 0): ?><span class="mini-flag">-<?= $diskon ?>%</span><?php endif; ?>
            <?php if ($img !== null && !empty($p['gambar'])): ?>
                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($p['nama']) ?>" loading="lazy"
                     onerror="this.style.display='none';">
            <?php endif; ?>
        </span>

        <span class="mini-cat"><?= htmlspecialchars($p['kategori'] ?? '') ?></span>
        <strong class="mini-name"><?= htmlspecialchars($p['nama']) ?></strong>
        <span class="mini-price"><?= rp(hargaSetelahDiskon($p)) ?></span>

        <span class="mini-rate">
            <span class="star-one">★</span>
            <?= $jml > 0 ? number_format((float) $p['rata_ulasan'], 1, '.', '') : '-' ?>
            <small><?= $jml > 0 ? $jml . ' ulasan' : 'Belum ada ulasan' ?></small>
        </span>

    </a>

    <?php
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($product['nama']) ?> - Detail Produk</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="detail-baru.css?v=1">
    <link rel="stylesheet" href="cart-toast.css?v=2">
</head>

<body>

<?php if ($cartFlash): ?>
<div class="cart-toast" id="cartToast" role="status" aria-live="polite">
    <div class="cart-toast-check"><span>✓</span></div>
    <div class="cart-toast-text">
        <strong>Berhasil ditambahkan ke troli!</strong>
        <span><?= htmlspecialchars($cartFlash['nama'] ?? 'Produk') ?></span>
    </div>
    <a href="cart.php" class="cart-toast-link">Lihat Troli</a>
    <button type="button" class="cart-toast-close" aria-label="Tutup notifikasi" onclick="document.getElementById('cartToast')?.classList.add('cart-toast-hide')">×</button>
</div>

<script>
window.setTimeout(() => {
    const toast = document.getElementById('cartToast');
    if (toast) toast.classList.add('cart-toast-hide');
}, 3800);
</script>
<?php endif; ?>


<div class="topbar">Khusus pembeli usia 18 tahun ke atas. Pesanan dicek langsung oleh admin.</div>

<!-- NAVBAR -->

<nav class="navbar">
    <div class="nav-container">

        <div class="nav-logo"><a href="index.php">JOYZONE</a></div>

        <div class="nav-actions">

            <a href="cart.php" class="nav-icon" aria-label="Troli" title="Troli">
                <span class="nav-ico">🛒
                    <span class="badge" id="cart-count"<?= $jumlahTroli < 1 ? ' hidden' : '' ?>><?= $jumlahTroli > 99 ? '99+' : $jumlahTroli ?></span>
                </span>
            </a>

            <a href="watchlist.php" class="nav-icon" aria-label="Watchlist" title="Watchlist">
                <span class="nav-ico">❤️</span>
            </a>

            <button class="menu-toggle" onclick="toggleMenu()" aria-label="Buka menu" type="button">☰</button>

        </div>

        <div class="nav-menu" id="navMenu">
            <a href="index.php">Home</a>
            <a href="katalog.php">Katalog</a>

            <?php if ($sudahLogin): ?>
                <a href="riwayat_pesanan.php">🧾 Riwayat Pesanan</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>

            <a href="tentang.php">Tentang Kami</a>
            <a href="kontak.php">Kontak Kami</a>
            <a href="faq.php">FAQ</a>
        </div>

    </div>
</nav>


<main class="page">

    <!-- BREADCRUMB -->

    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="index.php">Home</a>
        <span aria-hidden="true">›</span>
        <a href="katalog.php">Katalog</a>
        <?php if (!empty($product['kategori'])): ?>
            <span aria-hidden="true">›</span>
            <a href="katalog.php?kategori=<?= urlencode($product['kategori']) ?>"><?= htmlspecialchars($product['kategori']) ?></a>
        <?php endif; ?>
        <span aria-hidden="true">›</span>
        <span class="crumb-now"><?= htmlspecialchars($product['nama']) ?></span>
    </nav>


    <!-- ==================================================
         DETAIL PRODUK
    ================================================== -->

    <?php
        $diskonProduk = (int) ($product['diskon_persen'] ?? 0);
        $hargaAsli    = (float) $product['harga'];
        $hargaAkhir   = round($hargaAsli * (100 - $diskonProduk) / 100);
        $stokProduk   = (int) $product['stok'];
        $imgUtama     = gambarProduk($product['gambar'] ?? '');
        $deskripsi    = trim((string) ($product['deskripsi'] ?? ''));
        if ($deskripsi === '') { $deskripsi = 'Belum ada deskripsi.'; }
    ?>

    <section class="detail">

        <div class="gallery">

            <div class="gallery-main">
                <?php if (!empty($product['gambar'])): ?>
                    <img
                        src="<?= htmlspecialchars($imgUtama) ?>"
                        alt="<?= htmlspecialchars($product['nama']) ?>"
                        onerror="this.style.display='none';"
                    >
                <?php endif; ?>
            </div>

            <div class="gallery-tools">

                <button type="button" class="round-btn" id="btnShare" aria-label="Bagikan produk" title="Bagikan">⇪</button>

                <button
                    type="button"
                    class="round-btn<?= $sudahWatch ? ' aktif' : '' ?>"
                    id="btnWatchDetail"
                    data-id="<?= (int) $product['id'] ?>"
                    aria-label="<?= $sudahWatch ? 'Hapus dari watchlist' : 'Tambah ke watchlist' ?>"
                    title="Watchlist"
                ><?= $sudahWatch ? '❤️' : '🤍' ?></button>

            </div>

        </div>


        <div class="info">

            <?php if (!empty($product['kategori'])): ?>
                <span class="brand"><?= htmlspecialchars($product['kategori']) ?></span>
            <?php endif; ?>

            <h1><?= htmlspecialchars($product['nama']) ?></h1>

            <div class="price-line">

                <div class="price">
                    <?php if ($diskonProduk > 0): ?>
                        <s><?= rp($hargaAsli) ?></s>
                    <?php endif; ?>
                    <strong><?= rp($hargaAkhir) ?></strong>
                    <?php if ($diskonProduk > 0): ?>
                        <span class="flag">-<?= $diskonProduk ?>%</span>
                    <?php endif; ?>
                </div>

                <div class="meta">
                    <span class="<?= $stokProduk < 1 ? 'habis' : '' ?>">
                        <?= $stokProduk < 1 ? 'Stok habis' : 'Stok ' . $stokProduk ?>
                    </span>
                    <span class="sep"></span>
                    <span class="rate"><span class="star-one">★</span> <?= $totalUlasan > 0 ? number_format($rataRating, 1, '.', '') : '-' ?></span>
                </div>

            </div>

            <h2 class="desc-title">Deskripsi</h2>

            <div class="desc" id="desc"><?= nl2br(htmlspecialchars($deskripsi)) ?></div>

            <button type="button" class="desc-more" id="descMore" hidden>Lihat selengkapnya</button>

            <div class="cta">

                <button
                    type="button"
                    id="btnTambahTroli"
                    class="btn btn-cart"
                    data-id="<?= (int) $product['id'] ?>"
                    <?= $stokProduk < 1 ? 'disabled aria-disabled="true"' : '' ?>
                >
                    <svg class="cart-ico" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2 3h2.6l2.2 11.2a1.6 1.6 0 0 0 1.6 1.3h8.4a1.6 1.6 0 0 0 1.6-1.2L20 7H5.4"/>
                        <circle cx="9.5" cy="19.5" r="1.4"/>
                        <circle cx="17" cy="19.5" r="1.4"/>
                    </svg>
                    Tambah ke Troli
                </button>

                <!-- CHECKOUT: langsung ke halaman ringkasan + pembayaran -->
                <form method="get" action="beli_langsung.php">
                    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                    <input type="hidden" name="jumlah" value="1">
                    <button type="submit" class="btn btn-line" <?= $stokProduk < 1 ? 'disabled' : '' ?>>
                        Beli Sekarang
                    </button>
                </form>

            </div>

            <a class="back-link" href="katalog.php">&larr; Kembali ke katalog</a>

        </div>

    </section>


    <!-- ==================================================
         PRODUK TERKAIT
    ================================================== -->

    <?php if (!empty($produkTerkait)): ?>

    <section class="block">

        <header class="block-head">
            <h2>Produk Terkait</h2>
            <a href="katalog.php?kategori=<?= urlencode($product['kategori'] ?? '') ?>">Lihat semua</a>
        </header>

        <div class="mini-row">
            <?php foreach ($produkTerkait as $p) { kartuMini($p); } ?>
        </div>

    </section>

    <?php endif; ?>


    <!-- ==================================================
         ULASAN PRODUK
    ================================================== -->

    <section class="block" id="ulasan">

        <header class="block-head">
            <h2>Ulasan Produk</h2>
        </header>

        <?php if ($flashUlasan !== ''): ?>
            <div class="alert alert-ok" role="status"><?= htmlspecialchars($flashUlasan) ?></div>
        <?php endif; ?>

        <?php if ($errorUlasan !== ''): ?>
            <div class="alert alert-err" role="alert"><?= htmlspecialchars($errorUlasan) ?></div>
        <?php endif; ?>

        <!-- RINGKASAN -->

        <div class="summary">

            <div class="summary-score">

                <div
                    class="ring"
                    style="--p: <?= $totalUlasan > 0 ? round($rataRating / 5 * 100) : 0 ?>"
                    aria-label="Rating rata-rata"
                >
                    <span><?= $totalUlasan > 0 ? number_format($rataRating, 1, '.', '') : '-' ?></span>
                </div>

                <div>
                    <div class="stars"><?= bintang((int) round($rataRating)) ?></div>
                    <p class="from"><?= $totalUlasan > 0 ? 'dari ' . formatJumlah($totalUlasan) . ' ulasan' : 'Belum ada ulasan' ?></p>
                </div>

            </div>

            <div class="bars">
                <?php foreach ($sebaran as $bintangNilai => $jumlah): ?>
                    <div class="bar-row">
                        <span class="bar-label"><?= $bintangNilai ?>.0 <span class="star-one">★</span></span>
                        <span class="bar"><i style="width: <?= $totalUlasan > 0 ? round($jumlah / $totalUlasan * 100) : 0 ?>%"></i></span>
                        <span class="bar-num"><?= $jumlah ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>


        <!-- FORM TULIS ULASAN -->

        <details class="write" <?= ($errorUlasan !== '' || $old['rating'] > 0 || $old['komentar'] !== '') ? 'open' : '' ?>>

            <summary>Tulis ulasan</summary>

            <form
                class="form-ulasan"
                method="POST"
                action="detail_produk.php?id=<?= (int) $product['id'] ?>#ulasan"
                enctype="multipart/form-data"
            >

                <span class="form-label first">Beri penilaian</span>

                <div class="rating-input">
                    <?php for ($b = 5; $b >= 1; $b--): ?>
                        <input
                            type="radio"
                            name="rating"
                            id="bintang<?= $b ?>"
                            value="<?= $b ?>"
                            <?= $old['rating'] === $b ? 'checked' : '' ?>
                        >
                        <label for="bintang<?= $b ?>" title="<?= $b ?> bintang">★</label>
                    <?php endfor; ?>
                </div>

                <div class="rating-teks" id="ratingTeks"></div>

                <?php if ($namaLogin === ''): ?>
                    <label class="form-label" for="namaUlasan">Nama</label>
                    <input
                        type="text"
                        id="namaUlasan"
                        name="nama"
                        maxlength="50"
                        placeholder="Nama kamu"
                        value="<?= htmlspecialchars($old['nama']) ?>"
                    >
                <?php endif; ?>

                <label class="form-label" for="komentarUlasan">Komentar</label>
                <textarea
                    id="komentarUlasan"
                    name="komentar"
                    maxlength="1000"
                    placeholder="Ceritakan pengalaman kamu dengan produk ini..."
                ><?= htmlspecialchars($old['komentar']) ?></textarea>

                <label class="form-label" for="fotoUlasan">Foto (opsional)</label>
                <input
                    type="file"
                    id="fotoUlasan"
                    name="foto[]"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                >
                <div class="form-hint">Maksimal 3 foto, format JPG/PNG/WEBP, masing-masing maksimal 2 MB.</div>

                <button type="submit" name="kirim_ulasan" value="1" class="btn btn-solid btn-kirim">Kirim Ulasan</button>

            </form>

        </details>


        <?php if ($totalUlasan > 0): ?>

        <div class="reviews">

            <!-- FILTER -->

            <aside class="filter" aria-label="Filter ulasan">

                <h3>Filter Ulasan</h3>

                <div class="filter-group">
                    <h4>Rating</h4>
                    <?php foreach ($sebaran as $bintangNilai => $jumlah): ?>
                        <label class="check">
                            <input type="checkbox" class="f-rating" value="<?= $bintangNilai ?>">
                            <span class="box"></span>
                            <span class="star-one">★</span> <?= $bintangNilai ?>
                            <small><?= $jumlah ?></small>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="filter-group">
                    <h4>Topik Ulasan</h4>
                    <?php foreach ($tagKunci as $label => $kataKunci): ?>
                        <?php if ($tagHitung[$label] > 0): ?>
                            <label class="check">
                                <input
                                    type="checkbox"
                                    class="f-topik"
                                    value="<?= htmlspecialchars(implode('|', $kataKunci)) ?>"
                                >
                                <span class="box"></span>
                                <?= htmlspecialchars($label) ?>
                                <small><?= $tagHitung[$label] ?></small>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

            </aside>


            <!-- DAFTAR -->

            <div class="review-list">

                <div class="review-top">

                    <h3>Daftar Ulasan</h3>

                    <div class="search-ulasan">
                        <input type="search" id="cariUlasan" placeholder="Cari ulasan" aria-label="Cari ulasan">
                    </div>

                </div>

                <div class="tabs" role="tablist">
                    <button type="button" class="tab is-on" data-tab="all">Semua Ulasan</button>
                    <button type="button" class="tab" data-tab="foto">Dengan Foto</button>
                    <button type="button" class="tab" data-tab="teks">Dengan Komentar</button>
                </div>

                <div id="daftarUlasan">

                    <?php foreach ($ulasan as $r): ?>

                        <?php
                            $fotoList = [];
                            if (!empty($r['foto'])) {
                                $fotoList = array_filter(array_map('trim', explode(',', $r['foto'])));
                            }
                            $huruf      = strtoupper(substr($r['nama_user'], 0, 1));
                            $sudahBantu = in_array((int) $r['id'], $_SESSION['membantu'] ?? [], true);
                        ?>

                        <article
                            class="review-item"
                            data-rating="<?= (int) $r['rating'] ?>"
                            data-foto="<?= $fotoList ? 1 : 0 ?>"
                            data-komentar="<?= !empty(trim((string) ($r['komentar'] ?? ''))) ? 1 : 0 ?>"
                            data-teks="<?= htmlspecialchars(strtolower($r['nama_user'] . ' ' . ($r['komentar'] ?? ''))) ?>"
                        >

                            <div class="stars"><?= bintang($r['rating']) ?></div>

                            <?php if (!empty($r['komentar'])): ?>
                                <p class="review-text"><?= nl2br(htmlspecialchars($r['komentar'])) ?></p>
                            <?php endif; ?>

                            <time class="review-date"><?= htmlspecialchars(tglIndo($r['created_at'] ?? '')) ?></time>

                            <?php if ($fotoList): ?>
                                <div class="review-photos">
                                    <?php foreach ($fotoList as $f): ?>
                                        <img
                                            src="assets/images/reviews/<?= htmlspecialchars($f) ?>"
                                            alt="Foto ulasan"
                                            loading="lazy"
                                        >
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="review-user">

                                <div class="user-left">
                                    <div class="avatar" style="background: <?= warnaAvatar($r['nama_user']) ?>">
                                        <?= htmlspecialchars($huruf) ?>
                                    </div>
                                    <span class="user-name"><?= htmlspecialchars($r['nama_user']) ?></span>
                                </div>

                                <button
                                    type="button"
                                    class="btn-membantu<?= $sudahBantu ? ' aktif' : '' ?>"
                                    data-id="<?= (int) $r['id'] ?>"
                                    aria-label="Ulasan ini membantu"
                                >
                                    👍 <span class="jml"><?= (int) $r['membantu'] ?></span>
                                </button>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

                <div class="review-kosong" id="ulasanTidakAda" hidden>
                    Tidak ada ulasan yang cocok.
                </div>

                <nav class="pager" id="pager" aria-label="Halaman ulasan"></nav>

            </div>

        </div>

        <?php else: ?>

            <div class="review-kosong">Belum ada ulasan untuk produk ini.</div>

        <?php endif; ?>

    </section>


    <!-- ==================================================
         TERPOPULER
    ================================================== -->

    <?php if (!empty($produkPopuler)): ?>

    <section class="block">

        <header class="block-head">
            <h2>Terpopuler</h2>
            <a href="katalog.php">Lihat semua</a>
        </header>

        <div class="mini-row">
            <?php foreach ($produkPopuler as $p) { kartuMini($p); } ?>
        </div>

    </section>

    <?php endif; ?>

</main>


<!-- FOOTER -->

<footer class="footer">

    <div class="footer-grid">

        <div>
            <strong class="footer-logo">JOYZONE</strong>
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

    <div class="footer-bottom">&copy; <?= date('Y') ?> Joyzone Store</div>

</footer>


<!-- Lightbox foto ulasan -->
<div class="lightbox" id="lightbox">
    <img src="" alt="Foto ulasan">
</div>


<script>
function toggleMenu() {
    document.getElementById("navMenu").classList.toggle("active");
}

document.addEventListener("click", function (event) {
    var navMenu = document.getElementById("navMenu");
    var navbar  = document.querySelector(".navbar");

    if (!navbar.contains(event.target)) {
        navMenu.classList.remove("active");
    }
});


/* ==================================================
   DESKRIPSI: LIHAT SELENGKAPNYA
================================================== */

(function () {
    var desc = document.getElementById('desc');
    var more = document.getElementById('descMore');

    if (!desc || !more) return;

    desc.classList.add('clamp');

    if (desc.scrollHeight > desc.clientHeight + 2) {
        more.hidden = false;
    } else {
        desc.classList.remove('clamp');
    }

    more.addEventListener('click', function () {
        var terbuka = desc.classList.toggle('clamp') === false;
        more.textContent = terbuka ? 'Lihat lebih sedikit' : 'Lihat selengkapnya';
    });
})();


/* ==================================================
   BAGIKAN
================================================== */

(function () {
    var btn = document.getElementById('btnShare');
    if (!btn) return;

    btn.addEventListener('click', function () {
        var url = window.location.href.split('#')[0];

        if (navigator.share) {
            navigator.share({ title: document.title, url: url }).catch(function () {});
            return;
        }

        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function () {
                btn.title = 'Tautan disalin';
                btn.textContent = '✓';
                setTimeout(function () { btn.textContent = '⇪'; btn.title = 'Bagikan'; }, 1500);
            });
        }
    });
})();


/* ==================================================
   ULASAN: FILTER, TAB, CARI, HALAMAN
================================================== */

(function () {
    var PER_HALAMAN = 5;

    var items     = Array.prototype.slice.call(document.querySelectorAll('.review-item'));
    var inputCari = document.getElementById('cariUlasan');
    var tabs      = document.querySelectorAll('.tab');
    var rating    = document.querySelectorAll('.f-rating');
    var topik     = document.querySelectorAll('.f-topik');
    var kosong    = document.getElementById('ulasanTidakAda');
    var pager     = document.getElementById('pager');

    var tabAktif  = 'all';
    var halaman   = 1;

    function cocok(el) {
        var teks = el.getAttribute('data-teks');
        var q    = inputCari ? inputCari.value.trim().toLowerCase() : '';

        if (q !== '' && teks.indexOf(q) === -1) return false;

        if (tabAktif === 'foto' && el.getAttribute('data-foto') !== '1') return false;
        if (tabAktif === 'teks' && el.getAttribute('data-komentar') !== '1') return false;

        var nilai = [];
        rating.forEach(function (c) { if (c.checked) nilai.push(c.value); });
        if (nilai.length && nilai.indexOf(el.getAttribute('data-rating')) === -1) return false;

        var kata = [];
        topik.forEach(function (c) { if (c.checked) kata = kata.concat(c.value.split('|')); });
        if (kata.length && !kata.some(function (k) { return teks.indexOf(k) !== -1; })) return false;

        return true;
    }

    function tombol(label, tujuan, opsi) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = label;
        opsi = opsi || {};
        if (opsi.on) b.className = 'is-on';
        if (opsi.mati) { b.disabled = true; }
        else { b.addEventListener('click', function () { halaman = tujuan; tampil(); }); }
        return b;
    }

    function gambarPager(total) {
        pager.innerHTML = '';
        if (total <= 1) return;

        pager.appendChild(tombol('‹', halaman - 1, { mati: halaman === 1 }));

        var daftar = [];
        for (var p = 1; p <= total; p++) {
            if (p === 1 || p === total || Math.abs(p - halaman) <= 1) daftar.push(p);
            else if (daftar[daftar.length - 1] !== '…') daftar.push('…');
        }

        daftar.forEach(function (p) {
            if (p === '…') {
                var s = document.createElement('span');
                s.textContent = '…';
                pager.appendChild(s);
            } else {
                pager.appendChild(tombol(String(p), p, { on: p === halaman }));
            }
        });

        pager.appendChild(tombol('›', halaman + 1, { mati: halaman === total }));
    }

    function tampil() {
        var lolos = items.filter(cocok);
        var total = Math.max(1, Math.ceil(lolos.length / PER_HALAMAN));

        if (halaman > total) halaman = total;

        var awal = (halaman - 1) * PER_HALAMAN;

        items.forEach(function (el) { el.hidden = true; });
        lolos.slice(awal, awal + PER_HALAMAN).forEach(function (el) { el.hidden = false; });

        if (kosong) kosong.hidden = lolos.length !== 0;

        gambarPager(lolos.length ? total : 0);
    }

    function ulang() { halaman = 1; tampil(); }

    if (inputCari) inputCari.addEventListener('input', ulang);
    rating.forEach(function (c) { c.addEventListener('change', ulang); });
    topik.forEach(function (c) { c.addEventListener('change', ulang); });

    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            tabs.forEach(function (x) { x.classList.remove('is-on'); });
            t.classList.add('is-on');
            tabAktif = t.getAttribute('data-tab');
            ulang();
        });
    });

    if (items.length) tampil();

    // Tombol "Membantu"
    document.querySelectorAll('.btn-membantu').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var data = new FormData();
            data.append('id', btn.getAttribute('data-id'));

            fetch('review_membantu.php', { method: 'POST', body: data })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    if (json.ok) {
                        btn.querySelector('.jml').textContent = json.jumlah;
                        btn.classList.add('aktif');
                    }
                })
                .catch(function () {});
        });
    });

    // Keterangan teks untuk bintang yang dipilih
    var teksBintang = { 1: 'Sangat Buruk', 2: 'Buruk', 3: 'Cukup', 4: 'Bagus', 5: 'Sangat Bagus' };
    var ratingTeks  = document.getElementById('ratingTeks');

    document.querySelectorAll('.rating-input input').forEach(function (radio) {
        function tampilkan() {
            if (radio.checked) ratingTeks.textContent = teksBintang[radio.value] + ' (' + radio.value + '/5)';
        }
        radio.addEventListener('change', tampilkan);
        tampilkan();
    });

    // Lightbox foto
    var lightbox = document.getElementById('lightbox');
    var lbImg    = lightbox.querySelector('img');

    document.querySelectorAll('.review-photos img').forEach(function (img) {
        img.addEventListener('click', function () {
            lbImg.src = img.src;
            lightbox.classList.add('tampil');
        });
    });

    lightbox.addEventListener('click', function () {
        lightbox.classList.remove('tampil');
    });
})();
</script>

<script>
(function () {
    var btn = document.getElementById('btnWatchDetail');
    if (!btn) return;

    btn.addEventListener('click', function () {
        if (btn.disabled) return;
        btn.disabled = true;

        var data = new FormData();
        data.append('product_id', btn.getAttribute('data-id'));

        fetch('watchlist_toggle.php', {
            method: 'POST',
            body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (res) { return res.json(); })
        .then(function (json) {
            if (json.login) { window.location.href = 'login.php'; return; }
            if (!json.ok) { alert(json.pesan || 'Gagal memproses watchlist.'); return; }

            btn.classList.toggle('aktif', json.aktif);
            btn.textContent = json.aktif ? '\u2764\uFE0F' : '\uD83E\uDD0D';
            btn.setAttribute('aria-label', json.aktif ? 'Hapus dari watchlist' : 'Tambah ke watchlist');
        })
        .catch(function () { alert('Terjadi kesalahan, coba lagi.'); })
        .finally(function () { btn.disabled = false; });
    });
})();
</script>


<script>
/* Tambah ke troli via AJAX: tetap di halaman detail produk. */
(function () {
    const tombol = document.getElementById('btnTambahTroli');
    if (!tombol) return;

    tombol.addEventListener('click', async function () {
        if (tombol.disabled) return;

        const gambar = document.querySelector('.gallery-main img');
        const ikonTroli = document.querySelector('.navbar a.nav-icon[aria-label="Troli"]');
        const badge = document.getElementById('cart-count');
        const teksAsli = tombol.innerHTML;

        tombol.disabled = true;
        tombol.classList.add('sedang-menambah');
        tombol.textContent = 'Menambahkan...';

        try {
            const body = new URLSearchParams();
            body.set('aksi', 'tambah_troli_ajax');
            body.set('id_produk', tombol.dataset.id);
            body.set('jumlah', '1');

            const response = await fetch(window.location.pathname + window.location.search, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString()
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                tampilkanPesanTroli((data && data.message) || 'Produk gagal ditambahkan ke troli.', false);
                return;
            }

            // Animasi gambar menuju ikon troli, tanpa navigasi halaman.
            if (gambar && ikonTroli && gambar.getClientRects().length) {
                const asal = gambar.getBoundingClientRect();
                const tujuan = ikonTroli.getBoundingClientRect();
                const terbang = document.createElement('img');

                terbang.src = gambar.currentSrc || gambar.src;
                terbang.alt = '';
                terbang.setAttribute('aria-hidden', 'true');
                Object.assign(terbang.style, {
                    position: 'fixed',
                    zIndex: '2147483647',
                    left: (asal.left + asal.width / 2 - 45) + 'px',
                    top: (asal.top + asal.height / 2 - 45) + 'px',
                    width: '90px',
                    height: '90px',
                    objectFit: 'contain',
                    padding: '5px',
                    background: '#fff',
                    borderRadius: '12px',
                    boxShadow: '0 8px 24px rgba(0,0,0,.2)',
                    pointerEvents: 'none',
                    transition: 'all 650ms cubic-bezier(.45,0,.85,.45)'
                });

                document.body.appendChild(terbang);
                terbang.getBoundingClientRect();

                requestAnimationFrame(function () {
                    const target = ikonTroli.getBoundingClientRect();
                    terbang.style.left = (target.left + target.width / 2 - 12) + 'px';
                    terbang.style.top = (target.top + target.height / 2 - 12) + 'px';
                    terbang.style.width = '24px';
                    terbang.style.height = '24px';
                    terbang.style.opacity = '.25';
                    terbang.style.transform = 'scale(.4)';
                });

                window.setTimeout(function () { terbang.remove(); }, 700);
                ikonTroli.style.transform = 'scale(1.15)';
                window.setTimeout(function () { ikonTroli.style.transform = ''; }, 250);
            }

            if (badge && typeof data.cart_count !== 'undefined') {
                badge.textContent = data.cart_count > 99 ? '99+' : data.cart_count;
                badge.hidden = Number(data.cart_count) < 1;
            }

            tampilkanPesanTroli(data.message || 'Produk berhasil ditambahkan ke troli.', true);
        } catch (error) {
            tampilkanPesanTroli('Terjadi kesalahan saat menambahkan produk. Silakan coba lagi.', false);
        } finally {
            tombol.disabled = false;
            tombol.classList.remove('sedang-menambah');
            tombol.innerHTML = teksAsli;
        }
    });

    function tampilkanPesanTroli(teks, sukses) {
        let toast = document.getElementById('cartToastAjax');

        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'cartToastAjax';
            toast.className = 'cart-toast';
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            toast.innerHTML =
                '<div class="cart-toast-check"><span>✓</span></div>' +
                '<div class="cart-toast-text"><strong></strong><span></span></div>' +
                '<a href="cart.php" class="cart-toast-link">Lihat Troli</a>' +
                '<button type="button" class="cart-toast-close" aria-label="Tutup notifikasi">×</button>';
            document.body.appendChild(toast);

            toast.querySelector('.cart-toast-close').addEventListener('click', function () {
                toast.classList.add('cart-toast-hide');
            });
        }

        toast.classList.remove('cart-toast-hide');
        toast.querySelector('.cart-toast-check span').textContent = sukses ? '✓' : '!';
        toast.querySelector('.cart-toast-text strong').textContent = sukses
            ? 'Berhasil ditambahkan ke troli!'
            : 'Produk belum ditambahkan';
        toast.querySelector('.cart-toast-text span').textContent = teks;
        toast.style.borderColor = sukses ? '' : '#dc2626';

        window.clearTimeout(toast._timer);
        toast._timer = window.setTimeout(function () {
            toast.classList.add('cart-toast-hide');
        }, 3800);
    }
})();
</script>

</body>
</html>