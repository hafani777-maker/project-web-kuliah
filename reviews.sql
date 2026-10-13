-- Jalankan di phpMyAdmin (database joyzonevape) tab SQL

CREATE TABLE IF NOT EXISTS reviews (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT NOT NULL,
    nama_user   VARCHAR(100) NOT NULL,
    rating      TINYINT NOT NULL DEFAULT 5,      -- 1 sampai 5
    variasi     VARCHAR(100) DEFAULT 'Default',
    komentar    TEXT,
    foto        VARCHAR(500) DEFAULT NULL,       -- nama file dipisah koma, contoh: r1.jpg,r2.jpg
    membantu    INT NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (product_id)
);

-- Data contoh (ganti product_id sesuai id produk kamu)
INSERT INTO reviews (product_id, nama_user, rating, variasi, komentar, foto) VALUES
(1, 'achmad_fauzi_16', 5, 'Default', 'Pengiriman cepat dan dibungkus plastik, ok banget', NULL),
(1, 'j*****e', 5, 'Default', 'Barang datang dalam keadaan baik, packing rapi, pengirimannya cepat, jumlahnya sesuai pesanan. Kualitas bagus.', NULL),
(1, 'r****a', 4, 'Default', 'Kemasan aman, barang sesuai deskripsi.', NULL);
