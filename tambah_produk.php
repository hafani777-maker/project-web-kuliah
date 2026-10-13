<?php
require_once '../config/koneksi.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Produk</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 40px;
        }

        .form-container {
            max-width: 500px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
        }

        h2 {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 6px;
        }

        input,
        select {
            width: 100%;
            padding: 11px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            margin-top: 25px;
            padding: 12px;
            border: none;
            background: #222;
            color: white;
            cursor: pointer;
        }

        button:hover {
            background: #444;
        }
    </style>
</head>

<body>

<div class="form-container">

    <h2>Tambah Produk</h2>

    <form
        action="../proses/simpan_produk.php"
        method="POST"
        enctype="multipart/form-data"
    >

        <label>Nama Produk</label>

        <input
            type="text"
            name="nama"
            required
        >


        <label>Kategori</label>

        <input
            type="text"
            name="kategori"
            required
        >


        <label>Harga</label>

        <input
            type="number"
            name="harga"
            min="0"
            required
        >


        <label>Stok</label>

        <input
            type="number"
            name="stok"
            min="0"
            required
        >


        <label>Gambar Produk</label>

        <input
            type="file"
            name="gambar"
            accept="image/jpeg,image/png,image/webp"
            required
        >


        <button type="submit">
            Simpan Produk
        </button>

    </form>

</div>

</body>
</html>