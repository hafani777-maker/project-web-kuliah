<footer class="hp-footer">

    <div class="hp-footer-grid">

        <div>
            <img src="assets/images/logo-joyzone.png" alt="Joyzone Vape" width="64" height="64" style="width:64px;height:64px;border-radius:50%;display:block;margin-bottom:10px;">
            <strong class="hp-footer-logo">JOYZONE</strong>
            <p>Device, liquid, dan aksesoris. Khusus pembeli usia 18 tahun ke atas.</p>
        </div>

        <div>
            <h4>Belanja</h4>
            <a href="katalog.php">Katalog</a>
            <a href="watchlist.php">Watchlist</a>
            <a href="cart.php">Troli</a>
            <a href="riwayat_pesanan.php">Riwayat pesanan</a>
        </div>

        <div>
            <h4>Bantuan</h4>
            <a href="faq.php">FAQ</a>
            <a href="kontak.php">Kontak kami</a>
            <a href="tentang.php">Tentang kami</a>
        </div>

        <div>
            <h4>Legal</h4>
            <a href="syarat.php">Syarat &amp; ketentuan</a>
            <a href="privasi.php">Kebijakan privasi</a>
        </div>

    </div>

    <div class="hp-footer-bottom">
        &copy; <?= date('Y') ?> Joyzone Store
    </div>

</footer>

<script>


/* ==================================================
   BUKA / TUTUP MENU
================================================== */

function toggleMenu() {

    const navMenu =
        document.getElementById("navMenu");


    navMenu.classList.toggle("active");

}



/* ==================================================
   TUTUP MENU JIKA KLIK DI LUAR NAVBAR
================================================== */

document.addEventListener(
    "click",
    function(event) {


        const navMenu =
            document.getElementById("navMenu");


        const navbar =
            document.querySelector(".navbar");


        /*
         * Kalau klik di luar navbar,
         * menu ditutup.
         */

        if (!navbar.contains(event.target)) {

            navMenu.classList.remove("active");

        }

    }
);


</script>
