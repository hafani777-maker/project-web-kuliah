<?php
/*
 * Fitur "Terakhir Dilihat & Dipesan"
 *
 * Cara pakai:
 *   1. Catat produk yang dibuka: catatDilihat($pdo, $idProduk)
 *   2. Catat produk yang dipesan: catatDilihat($pdo, $idProduk)
 *   3. Tampilkan daftar: tampilTerakhirDilihat($pdo)
 *
 * Tabel produk_dilihat dibuat otomatis jika belum ada.
 * Pengguna login disimpan di database; tamu disimpan di session.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('TD_BATAS')) {
    define('TD_BATAS', 10);
}

function tdBuatTabel(PDO $pdo): void
{
    static $sudah = false;

    if ($sudah) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS produk_dilihat (
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            dilihat_at DATETIME NOT NULL,
            PRIMARY KEY (user_id, product_id),
            KEY idx_user_waktu (user_id, dilihat_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $sudah = true;
}

/** Catat produk yang baru dilihat atau dipesan. */
function catatDilihat(PDO $pdo, $idProduk): void
{
    $idProduk = (int) $idProduk;
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if ($idProduk <= 0) {
        return;
    }

    if ($userId <= 0) {
        $daftar = $_SESSION['dilihat'] ?? [];
        unset($daftar[$idProduk]);
        $daftar = [$idProduk => time()] + $daftar;
        $_SESSION['dilihat'] = array_slice($daftar, 0, TD_BATAS, true);
        return;
    }

    try {
        tdBuatTabel($pdo);
        $st = $pdo->prepare("
            INSERT INTO produk_dilihat (user_id, product_id, dilihat_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE dilihat_at = NOW()
        ");
        $st->execute([$userId, $idProduk]);
    } catch (PDOException $e) {
        error_log('catatDilihat Error: ' . $e->getMessage());
    }
}

function tdGambar($file): ?string
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

    return 'assets/images/' . rawurlencode($file);
}

function tdRp($n): string
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

/** Ambil data produk terakhir dilihat oleh pengguna atau tamu. */
function tdAmbilData(PDO $pdo): array
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    try {
        if ($userId > 0) {
            tdBuatTabel($pdo);

            $sql = "
                SELECT p.*,
                       COALESCE((
                           SELECT COUNT(DISTINCT o.id)
                           FROM orders o
                           JOIN order_details d ON d.order_id = o.id
                           WHERE o.user_id = ?
                             AND d.product_id = p.id
                             AND o.status <> 'Dibatalkan'
                       ), 0) AS kali_pesan
                FROM produk_dilihat v
                JOIN products p ON p.id = v.product_id
                WHERE v.user_id = ?
                ORDER BY v.dilihat_at DESC
                LIMIT " . (int) TD_BATAS;

            $st = $pdo->prepare($sql);
            $st->execute([$userId, $userId]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        }

        // Untuk tamu, ID produk dan waktu dilihat disimpan di session.
        $daftar = $_SESSION['dilihat'] ?? [];
        if (!is_array($daftar) || !$daftar) {
            return [];
        }

        $ids = array_slice(array_map('intval', array_keys($daftar)), 0, TD_BATAS);
        $ids = array_values(array_filter($ids, static fn($id) => $id > 0));

        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
        $st->execute($ids);
        $hasil = $st->fetchAll(PDO::FETCH_ASSOC);

        // Urutkan kembali sesuai urutan terakhir dilihat di session.
        $urut = [];
        foreach ($hasil as $produk) {
            $urut[(int) $produk['id']] = $produk;
            $urut[(int) $produk['id']]['kali_pesan'] = 0;
        }

        $tersusun = [];
        foreach ($ids as $id) {
            if (isset($urut[$id])) {
                $tersusun[] = $urut[$id];
            }
        }

        return $tersusun;
    } catch (PDOException $e) {
        error_log('tdAmbilData Error: ' . $e->getMessage());
        return [];
    }
}

/** Tampilkan bagian "Terakhir Dilihat & Dipesan" pada halaman beranda. */
function tampilTerakhirDilihat(PDO $pdo): void
{
    $produkDilihat = tdAmbilData($pdo);
    if (empty($produkDilihat)) {
        return;
    }
    ?>
    <style>
        .td-section {
            margin: 20px 0;
            padding: 18px;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
        }
        .td-section .td-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .td-section .td-header h2 {
            margin: 0;
            color: #bd5d4d;
            font-size: 22px;
        }
        .td-section .td-header a {
            color: #888;
            text-decoration: none;
            white-space: nowrap;
        }
        .td-list {
            display: flex;
            gap: 14px;
            overflow-x: auto;
            padding-bottom: 6px;
            scrollbar-width: thin;
        }
        .td-item {
            display: flex;
            flex: 0 0 390px;
            min-width: 0;
            height: 174px;
            overflow: hidden;
            border-radius: 12px;
            background: #fafafa;
            text-decoration: none;
            color: #333;
            border: 1px solid #f1f1f1;
        }
        .td-item-image {
            flex: 0 0 43%;
            width: 43%;
            height: 100%;
            background: #f3f3f3;
        }
        .td-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .td-item-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            gap: 8px;
            padding: 12px;
            min-width: 0;
        }
        .td-item-info h3 {
            margin: 0;
            font-size: 17px;
            line-height: 1.35;
            font-weight: 500;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .td-item-info .td-status {
            padding: 4px 7px;
            border-radius: 5px;
            background: #fff2d8;
            color: #b98d40;
            font-size: 12px;
        }
        .td-item-info .td-price {
            color: #bd5d4d;
            font-size: 15px;
            font-weight: 700;
        }
        @media (max-width: 600px) {
            .td-section { padding: 14px; }
            .td-section .td-header h2 { font-size: 18px; }
            .td-item { flex-basis: 310px; height: 145px; }
            .td-item-info { gap: 6px; padding: 10px; }
            .td-item-info h3 { font-size: 15px; }
        }
    </style>

    <section class="td-section" aria-label="Terakhir dilihat dan dipesan">
        <header class="td-header">
            <h2>Terakhir Dilihat &amp; Dipesan</h2>
            <a href="katalog.php">Lihat Semua ›</a>
        </header>

        <div class="td-list">
            <?php foreach ($produkDilihat as $produk): ?>
                <?php
                $id = (int) ($produk['id'] ?? 0);
                $nama = (string) ($produk['nama'] ?? 'Produk');
                $harga = (float) ($produk['harga'] ?? 0);
                $gambar = tdGambar($produk['gambar'] ?? '');
                $kaliPesan = (int) ($produk['kali_pesan'] ?? 0);
                ?>
                <a class="td-item" href="detail_produk.php?id=<?= $id ?>">
                    <div class="td-item-image">
                        <?php if ($gambar !== null): ?>
                            <img src="<?= htmlspecialchars($gambar, ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>"
                                 loading="lazy"
                                 onerror="this.style.display='none';">
                        <?php endif; ?>
                    </div>
                    <div class="td-item-info">
                        <h3><?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?></h3>
                        <span class="td-status">
                            <?= $kaliPesan > 0 ? 'Dipesan ' . $kaliPesan . ' kali' : 'Terakhir dilihat' ?>
                        </span>
                        <span class="td-price"><?= tdRp($harga) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}
