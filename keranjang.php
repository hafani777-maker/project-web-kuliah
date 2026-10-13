<?php
/*
 * File pengalih: link lama ke keranjang.php
 * otomatis diarahkan ke cart.php (query string ikut dibawa).
 */
$query = $_SERVER['QUERY_STRING'] ?? '';
header("Location: cart.php" . ($query !== '' ? '?' . $query : ''));
exit;
