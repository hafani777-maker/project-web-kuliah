<?php
/*
 * Data bersama untuk navbar & footer gaya halaman Home.
 * Panggil di BARIS PALING ATAS halaman (sebelum ada output HTML).
 * Menyediakan: $userId, $namaUser, $emailUser, $jumlahTroli, $jumlahWatch
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/koneksi.php';

$userId    = $_SESSION['user_id'] ?? null;
$namaUser  = null;
$emailUser = null;

$jumlahTroli = 0;
foreach ($_SESSION['cart'] ?? [] as $itemTroli) {
    $jumlahTroli += (int) ($itemTroli['jumlah'] ?? 0);
}

if ($userId !== null) {
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
        } else {
            $userId = null;
            unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email']);
        }
    } catch (PDOException $e) {
        $userId = null;
    }
}

$watchIds = [];
if ($userId !== null) {
    try {
        $stmtWatch = $pdo->prepare("SELECT product_id FROM watchlist WHERE user_id = ?");
        $stmtWatch->execute([$userId]);
        $watchIds = array_map('intval', $stmtWatch->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $e) {
        $watchIds = [];
    }
}
$jumlahWatch = count($watchIds);
