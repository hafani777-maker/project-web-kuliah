-- Import file ini setelah database utama website sudah dibuat.
-- Panel admin memakai koneksi dari ../config/koneksi.php.

CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Opsional: tambahkan kolom deskripsi jika ingin menyimpan deskripsi produk
-- dari form Tambah Produk.
-- Jalankan hanya jika kolom deskripsi memang belum ada:
-- ALTER TABLE products ADD COLUMN deskripsi TEXT NULL;
