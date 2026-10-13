<header class="site-header">
    <div class="container header-inner">

        <a href="index.php" class="logo">
            Joyzone
        </a>

        <nav class="main-nav">

            <a href="index.php">Beranda</a>

            <a href="produk.php">Produk</a>

            <a href="keranjang.php">Keranjang</a>

            <?php if (isset($_SESSION['user_id'])): ?>

                <a href="riwayat_pesanan.php">
                    Riwayat Pesanan
                </a>

                <a href="logout.php">
                    Logout
                </a>

            <?php else: ?>

                <a href="login.php">
                    Login
                </a>

            <?php endif; ?>

        </nav>

    </div>
</header>