<?php

session_start();

require_once 'config/koneksi.php';

header('Content-Type: application/json');


/* ==================================================
   CEK LOGIN
================================================== */

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        'success' => false,
        'message' => 'Belum login'
    ]);

    exit;
}


$userId = $_SESSION['user_id'];

$orderId = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;


if ($orderId <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'ID pesanan tidak valid'
    ]);

    exit;
}


/* ==================================================
   CEK STATUS
================================================== */

$stmt = $pdo->prepare("
    SELECT id, status
    FROM orders
    WHERE id = ?
      AND user_id = ?
    LIMIT 1
");

$stmt->execute([
    $orderId,
    $userId
]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$order) {

    echo json_encode([
        'success' => false,
        'message' => 'Pesanan tidak ditemukan'
    ]);

    exit;
}


/* ==================================================
   KIRIM STATUS
================================================== */

echo json_encode([

    'success' => true,

    'status' => $order['status']

]);