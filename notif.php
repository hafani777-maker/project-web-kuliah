<?php
/* notif.php
   Notifikasi (toast) sederhana berbasis session.

   1. Di halaman yang menampilkan notifikasi:
        require_once 'notif.php';          // di bagian atas (setelah session_start)
        <?php render_notif(); ?>           // sebelum </body>

   2. Di proses yang ingin memunculkan notifikasi (mis. tambah ke troli),
      panggil sebelum header('Location: ...'):
        require_once 'notif.php';
        set_notif('Vape Pen ditambahkan ke troli', 'sukses', 'cart.php', 'Lihat Troli');

   Tipe: sukses | info | peringatan | error
*/

if (!function_exists('set_notif')) {

    function set_notif($pesan, $tipe = 'sukses', $link = null, $labelLink = null)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $tipeValid = ['sukses', 'info', 'peringatan', 'error'];

        $_SESSION['notif'] = [
            'pesan' => (string) $pesan,
            'tipe'  => in_array($tipe, $tipeValid, true) ? $tipe : 'info',
            'link'  => $link,
            'label' => $labelLink,
        ];
    }
}

if (!function_exists('render_notif')) {

    function render_notif()
    {
        $notif = null;

        // Ambil notifikasi sekali saja, lalu hapus dari session
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['notif'])) {
            $notif = $_SESSION['notif'];
            unset($_SESSION['notif']);
        }

        $data = $notif
            ? htmlspecialchars(json_encode($notif, JSON_UNESCAPED_UNICODE), ENT_QUOTES)
            : '';
        ?>
        <link rel="stylesheet" href="notif.css">

        <div
            class="notif-box"
            id="notifBox"
            role="status"
            aria-live="polite"
            data-notif="<?= $data ?>"
        ></div>

        <script src="notif.js" defer></script>
        <?php
    }
}
