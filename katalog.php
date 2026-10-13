<?php
// katalog.php
// Katalog produk dari database (tabel products), dikelompokkan per kategori.

session_start();

require_once 'config/koneksi.php';
if (is_file(__DIR__ . '/notif.php')) {
    require_once __DIR__ . '/notif.php';
}

/* ==================================================
   KATEGORI UTAMA (urutan tampil)
   Alias dipakai agar penulisan berbeda tetap masuk
   ke kategori yang sama (mis. "Accessoris" / "Accesoris").
================================================== */

$grupUtama = [
    'device' => [
        'label' => 'Device',
        'alias' => ['device', 'devices'],
    ],
    'accesoris' => [
        'label' => 'Accesoris',
        'alias' => ['accesoris', 'accessoris', 'accessories', 'aksesoris'],
    ],
    'liquid-freebase' => [
        'label' => 'Liquid Freebase',
        'alias' => ['liquid freebase', 'liquid free base', 'freebase'],
    ],
    'liquid-saltnic' => [
        'label' => 'Liquid Saltnic',
        'alias' => ['liquid saltnic', 'liquid salt nic', 'liquid saltnicotine', 'saltnic'],
    ],
];

function normKategori($k)
{
    return strtolower(trim(preg_replace('/\s+/', ' ', (string) $k)));
}

function cariGrup($kategori, $grupUtama)
{
    $n = normKategori($kategori);

    foreach ($grupUtama as $key => $g) {
        if (in_array($n, $g['alias'], true)) {
            return $key;
        }
    }

    return null;
}

function rupiah($harga)
{
    return 'Rp ' . number_format((float) $harga, 0, ',', '.');
}

/*
 * Cari file gambar yang diupload admin.
 * Admin menyimpan di folder admin/uploads/, folder lain dicoba sebagai cadangan.
 */
function gambarUrl($file)
{
    $file = basename(trim((string) $file));

    if ($file === '') {
        return null;
    }

    foreach (['admin/uploads/', 'upload/', 'assets/images/', 'assets/'] as $dir) {
        if (is_file(__DIR__ . '/' . $dir . $file)) {
            return $dir . rawurlencode($file);
        }
    }

    return null;
}


/* ==================================================
   AMBIL DATA PRODUK
================================================== */

$products = [];
$dbError  = null;

try {
    $products = $pdo
        ->query("SELECT * FROM products ORDER BY nama ASC")
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $dbError = $e->getMessage();
}


/* ==================================================
   KELOMPOKKAN PER KATEGORI
================================================== */

$groups = [];

foreach ($grupUtama as $key => $g) {
    $groups[$key] = ['label' => $g['label'], 'items' => []];
}

foreach ($products as $p) {

    $key = cariGrup($p['kategori'] ?? '', $grupUtama);

    if ($key === null) {
        $label = trim((string) ($p['kategori'] ?? ''));
        if ($label === '') {
            $label = 'Lainnya';
        }

        $key = 'lain-' . md5(normKategori($label));

        if (!isset($groups[$key])) {
            $groups[$key] = ['label' => $label, 'items' => []];
        }
    }

    $groups[$key]['items'][] = $p;
}

// Untuk dropdown: empat kategori utama selalu ada
$filterOptions = $groups;

// Untuk tampilan: hanya kategori yang punya produk
$groupsTampil = array_filter($groups, function ($g) {
    return !empty($g['items']);
});

/* ==================================================
   DATA TAMBAHAN UNTUK TAMPILAN BARU
================================================== */

$userId = $_SESSION['user_id'] ?? null;

/* Harga setelah diskon (diskon_persen bisa belum ada di tabel) */
function hargaAkhir(array $p): float
{
    $diskon = (int) ($p['diskon_persen'] ?? 0);

    return round((float) $p['harga'] * (100 - $diskon) / 100);
}

/* Singkatan harga untuk tombol: 50rb, 1,5jt */
function ringkas(int $n): string
{
    if ($n >= 1000000) {
        return rtrim(rtrim(number_format($n / 1000000, 1, ',', '.'), '0'), ',') . 'jt';
    }

    if ($n >= 1000) {
        return round($n / 1000) . 'rb';
    }

    return (string) $n;
}

/* Daftar produk rata, urut sesuai kategori */
$produkSemua = [];

foreach ($groupsTampil as $key => $g) {
    foreach ($g['items'] as $p) {
        $p['_key']   = $key;
        $p['_label'] = $g['label'];
        $produkSemua[] = $p;
    }
}

/* Batas slider harga */
$hargaList = array_map('hargaAkhir', $produkSemua);
$maxHarga  = $hargaList ? (int) max($hargaList) : 0;
$langkah   = $maxHarga >= 500000 ? 10000 : 1000;
$batasMax  = $maxHarga > 0 ? (int) (ceil($maxHarga / $langkah) * $langkah) : 0;

/* Empat tombol "Pilih cepat" (dibagi rata dari harga tertinggi) */
$t = [];
for ($i = 1; $i <= 3; $i++) {
    $t[$i] = (int) (round($batasMax * $i / 4 / $langkah) * $langkah);
}

$quick = [
    ['min' => 0,     'max' => $t[1],     'label' => 'Di bawah ' . ringkas($t[1])],
    ['min' => $t[1], 'max' => $t[2],     'label' => ringkas($t[1]) . ' - ' . ringkas($t[2])],
    ['min' => $t[2], 'max' => $t[3],     'label' => ringkas($t[2]) . ' - ' . ringkas($t[3])],
    ['min' => $t[3], 'max' => $batasMax, 'label' => ringkas($t[3]) . ' ke atas'],
];

/* Kategori yang sudah tercentang bila datang dari beranda (?kategori=...) */
$preKey = null;

if (!empty($_GET['kategori'])) {

    $preKey = cariGrup($_GET['kategori'], $grupUtama);

    if ($preKey === null) {
        foreach ($groups as $k => $g) {
            if (normKategori($g['label']) === normKategori($_GET['kategori'])) {
                $preKey = $k;
                break;
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Produk - JOYZONE</title>
    <link rel="icon" type="image/png" href="assets/images/logo-joyzone.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="katalog-baru.css?v=2">
</head>
<body>

<nav class="navbar">
    <div class="nav-container">
        <div class="nav-logo">
            <a href="index.php" style="display:inline-flex;align-items:center;gap:10px;">
                <img src="assets/images/logo-joyzone.png" alt="Joyzone Vape" width="44" height="44" style="width:44px;height:44px;border-radius:50%;display:block;">
                <span>JOYZONE</span>
            </a>
        </div>

        <button class="menu-toggle" onclick="toggleMenu()" aria-label="Buka menu" type="button">☰</button>

        <div class="nav-menu" id="navMenu">
            <a href="index.php">Home</a>
            <a href="katalog.php" class="active">Katalog</a>
            <a href="cart.php">🛒 Troli</a>

            <?php if ($userId !== null): ?>
                <a href="riwayat_pesanan.php">🧾 Riwayat Pesanan</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>

            <a href="tentang.php">Tentang Kami</a>
            <a href="kontak.php">Kontak Kami</a>
            <a href="faq.php">FAQ</a>
            <a href="syarat.php">Syarat & Ketentuan</a>
            <a href="privasi.php">Kebijakan Privasi</a>
        </div>
    </div>
</nav>

<?php if ($dbError !== null): ?>

    <main class="kt-error">
        <h1>Gagal memuat produk</h1>
        <p><?= htmlspecialchars($dbError) ?></p>
    </main>

<?php else: ?>

<div class="kt-layout">

    <!-- ==================================================
         SIDEBAR FILTER
    ================================================== -->

    <aside class="kt-sidebar" id="ktSidebar" aria-label="Filter produk">

        <div class="kt-side-head">
            <h2>Filter</h2>
            <button type="button" class="kt-reset" id="resetFilter">Reset</button>
        </div>

        <?php if ($batasMax > 0): ?>

        <section class="kt-block">
            <h3>Rentang Harga</h3>

            <div class="range" id="range" style="--lo:0%;--hi:100%">
                <div class="range-track"></div>
                <input type="range" id="minRange" min="0" max="<?= $batasMax ?>" step="<?= $langkah ?>" value="0" aria-label="Harga minimum">
                <input type="range" id="maxRange" min="0" max="<?= $batasMax ?>" step="<?= $langkah ?>" value="<?= $batasMax ?>" aria-label="Harga maksimum">
            </div>

            <div class="range-labels">
                <span id="minLabel">Rp 0</span>
                <span id="maxLabel"><?= rupiah($batasMax) ?></span>
            </div>

            <p class="kt-sub">Pilih cepat</p>

            <div class="quick">
                <?php foreach ($quick as $i => $q): ?>
                    <button
                        type="button"
                        class="quick-btn"
                        data-min="<?= $q['min'] ?>"
                        data-max="<?= $q['max'] ?>"
                    ><?= htmlspecialchars($q['label']) ?></button>
                <?php endforeach; ?>
            </div>
        </section>

        <?php endif; ?>

        <section class="kt-block">
            <h3>Kategori</h3>

            <ul class="cat-list">
                <?php foreach ($filterOptions as $key => $g): ?>
                    <li>
                        <label>
                            <input
                                type="checkbox"
                                class="cat-check"
                                value="<?= htmlspecialchars($key) ?>"
                                <?= $preKey === $key ? 'checked' : '' ?>
                            >
                            <span class="cat-name"><?= htmlspecialchars($g['label']) ?></span>
                            <span class="cat-count"><?= count($g['items']) ?></span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

    </aside>


    <!-- ==================================================
         DAFTAR PRODUK
    ================================================== -->

    <main class="kt-main">

        <header class="kt-top">

            <div>
                <h1>Katalog Produk</h1>
                <p id="resultInfo"><?= count($produkSemua) ?> produk ditampilkan</p>
            </div>

            <div class="kt-tools">
                <button type="button" class="kt-filter-btn" id="filterToggle" aria-controls="ktSidebar" aria-expanded="false">Filter</button>

                <div class="search-box">
                    <input
                        type="search"
                        id="searchInput"
                        placeholder="Cari nama produk"
                        aria-label="Cari nama produk"
                        autocomplete="off"
                    >
                </div>
            </div>

        </header>

        <div class="kt-grid" id="grid">

            <?php foreach ($produkSemua as $product): ?>

                <?php
                $img    = gambarUrl($product['gambar'] ?? '');
                $stok   = (int) ($product['stok'] ?? 0);
                $diskon = (int) ($product['diskon_persen'] ?? 0);
                $asli   = (float) $product['harga'];
                $akhir  = hargaAkhir($product);
                $desk   = trim((string) ($product['deskripsi'] ?? ''));
                ?>

                <article
                    class="kt-card"
                    data-name="<?= htmlspecialchars(mb_strtolower($product['nama']), ENT_QUOTES) ?>"
                    data-category="<?= htmlspecialchars($product['_key']) ?>"
                    data-price="<?= (int) $akhir ?>"
                    data-nama="<?= htmlspecialchars($product['nama'], ENT_QUOTES) ?>"
                    data-label="<?= htmlspecialchars($product['_label'], ENT_QUOTES) ?>"
                    data-harga="<?= htmlspecialchars(rupiah($akhir), ENT_QUOTES) ?>"
                    data-stok="<?= $stok ?>"
                >

                    <div class="kt-media">

                        <?php if ($diskon > 0): ?>
                            <span class="kt-flag">-<?= $diskon ?>%</span>
                        <?php endif; ?>

                        <?php if ($img !== null): ?>

                            <img
                                src="<?= htmlspecialchars($img) ?>"
                                alt="<?= htmlspecialchars($product['nama']) ?>"
                                loading="lazy"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                            >
                            <div class="kt-noimg"><span>Gambar tidak ditemukan</span></div>

                        <?php else: ?>

                            <div class="kt-noimg is-visible"><span>Tidak ada gambar</span></div>

                        <?php endif; ?>

                    </div>

                    <div class="kt-body">

                        <span class="pill"><?= htmlspecialchars($product['_label']) ?></span>

                        <h2><?= htmlspecialchars($product['nama']) ?></h2>

                        <?php if ($desk !== ''): ?>
                            <p class="kt-desc"><?= htmlspecialchars($desk) ?></p>
                        <?php endif; ?>

                        <div class="kt-price-row">

                            <div class="kt-price">
                                <?php if ($diskon > 0): ?>
                                    <s><?= rupiah($asli) ?></s>
                                <?php endif; ?>
                                <strong><?= rupiah($akhir) ?></strong>
                            </div>

                            <?php if ($stok > 0): ?>
                                <span class="pill">Stok <?= $stok ?></span>
                            <?php else: ?>
                                <span class="pill pill-habis">Stok habis</span>
                            <?php endif; ?>

                        </div>

                        <div class="kt-actions">

                            <a href="detail_produk.php?id=<?= (int) $product['id'] ?>" class="kt-btn kt-btn-ghost">
                                Detail
                            </a>

                            <form method="post" action="beli_langsung.php">
                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="kt-btn kt-btn-solid" <?= $stok < 1 ? 'disabled' : '' ?>>
                                    Beli Sekarang
                                </button>
                            </form>

                        </div>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

        <div id="emptyState" class="kt-empty<?= empty($produkSemua) ? ' is-visible' : '' ?>">
            <h2>Produk tidak ditemukan</h2>
            <p>Ubah kata kunci, rentang harga, atau kategori, lalu coba lagi.</p>
        </div>

    </main>

</div>

<?php endif; ?>

<footer class="kt-footer">
    <img src="assets/images/logo-joyzone.png" alt="Joyzone Vape" width="56" height="56" style="width:56px;height:56px;border-radius:50%;display:block;margin:0 auto 10px;">
    <p>&copy; <?= date('Y') ?> JOYZONE. Semua hak dilindungi.</p>
</footer>

<div class="modal" id="productModal" aria-hidden="true">
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modalName">
        <button class="modal-close" onclick="closeProductInfo()" aria-label="Tutup" type="button">&times;</button>
        <span class="pill" id="modalCategory"></span>
        <h2 id="modalName"></h2>
        <p class="modal-price" id="modalPrice"></p>
        <p id="modalStock"></p>
        <button class="kt-btn kt-btn-solid modal-ok" onclick="closeProductInfo()" type="button">Tutup</button>
    </div>
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
   FILTER: kata kunci + rentang harga + kategori
================================================== */

var searchInput = document.getElementById("searchInput");
var minRange    = document.getElementById("minRange");
var maxRange    = document.getElementById("maxRange");
var rangeBox    = document.getElementById("range");
var cards       = document.querySelectorAll(".kt-card");
var cats        = document.querySelectorAll(".cat-check");
var quickBtns   = document.querySelectorAll(".quick-btn");
var emptyState  = document.getElementById("emptyState");
var resultInfo  = document.getElementById("resultInfo");

function rupiahJs(n) {
    return "Rp " + Math.round(n).toLocaleString("id-ID");
}

function syncRange(changed) {
    if (!minRange) { return; }

    var lo = parseInt(minRange.value, 10);
    var hi = parseInt(maxRange.value, 10);

    if (lo > hi) {
        if (changed === minRange) { lo = hi; minRange.value = lo; }
        else { hi = lo; maxRange.value = hi; }
    }

    var max = parseInt(maxRange.max, 10) || 1;

    rangeBox.style.setProperty("--lo", (lo / max * 100) + "%");
    rangeBox.style.setProperty("--hi", (hi / max * 100) + "%");

    document.getElementById("minLabel").textContent = rupiahJs(lo);
    document.getElementById("maxLabel").textContent = rupiahJs(hi);

    quickBtns.forEach(function (b) {
        b.classList.toggle(
            "is-on",
            parseInt(b.dataset.min, 10) === lo && parseInt(b.dataset.max, 10) === hi
        );
    });
}

function filterProducts() {
    var keyword = searchInput ? searchInput.value.toLowerCase().trim() : "";
    var lo = minRange ? parseInt(minRange.value, 10) : 0;
    var hi = maxRange ? parseInt(maxRange.value, 10) : Infinity;

    var aktif = [];
    cats.forEach(function (c) { if (c.checked) { aktif.push(c.value); } });

    var visible = 0;

    cards.forEach(function (card) {
        var harga = parseInt(card.dataset.price, 10);

        var ok =
            card.dataset.name.indexOf(keyword) !== -1 &&
            harga >= lo && harga <= hi &&
            (aktif.length === 0 || aktif.indexOf(card.dataset.category) !== -1);

        card.style.display = ok ? "" : "none";
        if (ok) { visible++; }
    });

    resultInfo.textContent = visible + " produk ditampilkan";
    emptyState.classList.toggle("is-visible", visible === 0);
}

if (searchInput) { searchInput.addEventListener("input", filterProducts); }

if (minRange) {
    minRange.addEventListener("input", function () { syncRange(minRange); filterProducts(); });
    maxRange.addEventListener("input", function () { syncRange(maxRange); filterProducts(); });
}

cats.forEach(function (c) { c.addEventListener("change", filterProducts); });

quickBtns.forEach(function (b) {
    b.addEventListener("click", function () {
        minRange.value = b.dataset.min;
        maxRange.value = b.dataset.max;
        syncRange(null);
        filterProducts();
    });
});

var resetBtn = document.getElementById("resetFilter");

if (resetBtn) {
    resetBtn.addEventListener("click", function () {
        if (searchInput) { searchInput.value = ""; }
        if (minRange) { minRange.value = minRange.min; maxRange.value = maxRange.max; }
        cats.forEach(function (c) { c.checked = false; });
        syncRange(null);
        filterProducts();
    });
}

/* Tombol Filter (tampilan ponsel) */
var filterToggle = document.getElementById("filterToggle");

if (filterToggle) {
    filterToggle.addEventListener("click", function () {
        var open = document.getElementById("ktSidebar").classList.toggle("open");
        filterToggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
}

syncRange(null);
filterProducts();


/* ==================================================
   MODAL DETAIL
================================================== */

function showProductInfo(button) {
    var card  = button.closest(".kt-card");
    var stock = parseInt(card.dataset.stok, 10);

    document.getElementById("modalName").textContent     = card.dataset.nama;
    document.getElementById("modalCategory").textContent = card.dataset.label;
    document.getElementById("modalPrice").textContent    = card.dataset.harga;
    document.getElementById("modalStock").textContent    =
        stock > 0 ? "Stok tersedia: " + stock : "Stok habis";

    var modal = document.getElementById("productModal");
    modal.classList.add("show");
    modal.setAttribute("aria-hidden", "false");
}

function closeProductInfo() {
    var modal = document.getElementById("productModal");
    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");
}

document.getElementById("productModal").addEventListener("click", function (event) {
    if (event.target === this) { closeProductInfo(); }
});

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") { closeProductInfo(); }
});
</script>

<?php if (function_exists('render_notif')) { render_notif(); } ?>

</body>
</html>