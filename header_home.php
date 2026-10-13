<div class="hp-topbar">Khusus pembeli usia 18 tahun ke atas. Pesanan dicek langsung oleh admin.</div>


<!-- ==================================================
     NAVBAR
================================================== -->

<nav class="navbar">

    <div class="nav-container">


        <!-- ==================================================
             LOGO
        ================================================== -->

        <div class="nav-logo">

            <a href="index.php" style="display:inline-flex;align-items:center;gap:10px;">
                <img src="assets/images/logo-joyzone.png" alt="Joyzone Vape" width="48" height="48" style="width:48px;height:48px;border-radius:50%;display:block;">
                <span>JOYZONE</span>
            </a>

        </div>



        <!-- ==================================================
             AKSI NAVBAR: TROLI, WATCHLIST, HAMBURGER
        ================================================== -->

        <div class="nav-actions">

            <!-- TROLI -->
            <a href="cart.php" class="nav-icon-link" aria-label="Troli" title="Troli">

                <span class="troli-icon">

                    <svg class="cart-ico" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2 3h2.6l2.2 11.2a1.6 1.6 0 0 0 1.6 1.3h8.4a1.6 1.6 0 0 0 1.6-1.2L20 7H5.4"/>
                        <circle cx="9.5" cy="19.5" r="1.4"/>
                        <circle cx="17" cy="19.5" r="1.4"/>
                    </svg>

                    <?php if ($jumlahTroli > 0): ?>

                        <span class="troli-badge">
                            <?= $jumlahTroli > 99 ? '99+' : $jumlahTroli ?>
                        </span>

                    <?php endif; ?>

                </span>

            </a>

            <!-- WATCHLIST -->
            <a href="watchlist.php" class="nav-icon-link" aria-label="Watchlist" title="Watchlist">

                <span class="troli-icon">

                    ❤️

                    <span
                        class="troli-badge"
                        id="watchBadge"<?= $jumlahWatch > 0 ? '' : ' style="display:none"' ?>
                    >
                        <?= $jumlahWatch > 99 ? '99+' : $jumlahWatch ?>
                    </span>

                </span>

            </a>

            <!-- TOMBOL HAMBURGER -->
            <button
                class="menu-toggle"
                onclick="toggleMenu()"
                aria-label="Buka menu"
                type="button"
            >

                ☰

            </button>

        </div>



        <!-- ==================================================
             MENU NAVBAR
        ================================================== -->

        <div
            class="nav-menu"
            id="navMenu"
        >


            <!-- ==================================================
                 ACCOUNT
                 PALING ATAS
            ================================================== -->

            <?php if ($userId !== null && $namaUser !== null): ?>


                <!-- ACCOUNT USER YANG LOGIN -->

                <div class="user-account">

                    <div class="account-header">


                        <div class="account-icon">

                            👤

                        </div>


                        <div class="account-text">


                            <div class="account-label">

                                Akun Saya

                            </div>


                            <div class="account-name">

                                <?= htmlspecialchars($namaUser) ?>

                            </div>


                            <div class="account-email">

                                <?= htmlspecialchars(
                                    $emailUser ?? ''
                                ) ?>

                            </div>


                        </div>

                    </div>

                </div>


            <?php else: ?>


                <!-- ACCOUNT BELUM LOGIN -->

                <div class="user-account user-guest">

                    <div class="account-header">


                        <div class="account-icon">

                            👤

                        </div>


                        <div class="account-text">


                            <div class="account-label">

                                Akun Saya

                            </div>


                            <div class="account-email">

                                Belum login

                            </div>


                        </div>

                    </div>

                </div>


            <?php endif; ?>



            <!-- ==================================================
                 PEMISAH
            ================================================== -->

            <div class="menu-divider"></div>



            <!-- ==================================================
                 HOME
            ================================================== -->

            <a href="index.php">

                Home

            </a>



            <!-- ==================================================
                 KATALOG
            ================================================== -->

            <a href="katalog.php">

                Katalog

            </a>



            <!-- ==================================================
                 RIWAYAT PESANAN
            ================================================== -->

            <?php if ($userId !== null): ?>

                <a href="riwayat_pesanan.php">

                    🧾 Riwayat Pesanan

                </a>

            <?php endif; ?>



            <!-- ==================================================
                 LOGIN / LOGOUT
            ================================================== -->

            <?php if ($userId !== null && $namaUser !== null): ?>


                <a
                    href="logout.php"
                    class="logout-link"
                >

                    Logout

                </a>


            <?php else: ?>


                <a href="login.php">

                    Login

                </a>


                <a href="register.php">

                    Register

                </a>


            <?php endif; ?>



            <!-- ==================================================
                 PEMISAH
            ================================================== -->

            <div class="menu-divider"></div>



            <!-- ==================================================
                 TENTANG KAMI
            ================================================== -->

            <a href="tentang.php">

                Tentang Kami

            </a>



            <!-- ==================================================
                 KONTAK
            ================================================== -->

            <a href="kontak.php">

                Kontak Kami

            </a>



            <!-- ==================================================
                 FAQ
            ================================================== -->

            <a href="faq.php">

                FAQ

            </a>



            <!-- ==================================================
                 SYARAT
            ================================================== -->

            <a href="syarat.php">

                Syarat & Ketentuan

            </a>



            <!-- ==================================================
                 PRIVASI
            ================================================== -->

            <a href="privasi.php">

                Kebijakan Privasi

            </a>


        </div>

    </div>

</nav>
