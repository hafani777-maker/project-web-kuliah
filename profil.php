<?php

session_start();

require_once 'config/koneksi.php';
require_once __DIR__ . '/foto_profil.php';

/* =========================================================
   WAJIB LOGIN
========================================================= */

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function samarkanEmail(string $email): string
{
    $pos = strpos($email, '@');

    if ($pos === false || $pos < 2) {
        return $email;
    }

    return $email[0] . str_repeat('*', max(3, $pos - 2)) . $email[$pos - 1] . substr($email, $pos);
}

$sukses = '';
$error  = '';

/* =========================================================
   SIMPAN PERUBAHAN NAMA
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $token)) {

        $error = 'Sesi tidak valid, silakan muat ulang halaman.';

    } elseif (isset($_POST['hapus_foto'])) {

        hapusFotoProfil($userId);
        $sukses = 'Foto profil dihapus.';

    } elseif (($_POST['aksi'] ?? '') === 'foto') {

        $error = simpanFotoProfil($userId, $_FILES['foto'] ?? []);

        if ($error === '') {
            $sukses = 'Foto profil berhasil diperbarui.';
        }

    } else {

        $nama = trim((string) ($_POST['nama'] ?? ''));

        if (mb_strlen($nama) < 2 || mb_strlen($nama) > 60) {

            $error = 'Nama harus 2 sampai 60 karakter.';

        } else {

            try {

                // Tabel users punya kolom name dan nama, jadi keduanya diisi
                $stmt = $pdo->prepare("UPDATE users SET name = ?, nama = ? WHERE id = ?");
                $stmt->execute([$nama, $nama, $userId]);

                $_SESSION['user_name'] = $nama;
                $sukses = 'Nama berhasil diperbarui.';

            } catch (PDOException $e) {

                $error = 'Gagal menyimpan perubahan.';

            }
        }
    }
}

/* =========================================================
   DATA USER
========================================================= */

$namaUser  = '';
$emailUser = '';
$fotoUrl   = fotoProfilUrl($userId);

try {

    $stmt = $pdo->prepare("
        SELECT COALESCE(NULLIF(TRIM(name), ''), NULLIF(TRIM(nama), ''), '') AS name,
               email
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: logout.php');
        exit;
    }

    $namaUser  = (string) $user['name'];
    $emailUser = (string) $user['email'];

} catch (PDOException $e) {

    $error = $error !== '' ? $error : 'Gagal memuat data akun.';

}

/* Data navbar & footer gaya beranda (jumlah troli, watchlist, akun) */
require_once __DIR__ . '/home_data.php';

?>
<!DOCTYPE html>
<html lang="id">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ubah Profil - Joyzone Store</title>
    <link rel="icon" type="image/png" href="assets/images/logo-joyzone.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="style.css?v=12">
    <link rel="stylesheet" href="banner.css">
    <link rel="stylesheet" href="index.css?v=1">
    <link rel="stylesheet" href="home.css?v=1">
    <link rel="stylesheet" href="profil.css?v=3">

</head>

<body class="home pf">

<?php require __DIR__ . '/header_home.php'; ?>

<div class="pf-bar">
    <a href="index.php" class="pf-back" aria-label="Kembali">&larr;</a>
    <h1>Ubah Profil</h1>
</div>

<main class="pf-wrap">

    <?php if ($sukses !== ''): ?>
        <div class="pf-alert pf-ok"><?= htmlspecialchars($sukses) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="pf-alert pf-err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="pf-card pf-avatar" id="formFoto">

        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
        <input type="hidden" name="aksi" value="foto">
        <input type="file" name="foto" id="inputFoto" accept="image/jpeg,image/png,image/webp" hidden>

        <label for="inputFoto" class="pf-avatar-circle pf-avatar-pick" title="Pilih foto profil">
            <?php if ($fotoUrl !== ''): ?>
                <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="Foto profil">
            <?php else: ?>
                <span aria-hidden="true">👤</span>
            <?php endif; ?>
        </label>

        <label for="inputFoto" class="pf-avatar-ubah">✎ Ubah foto</label>

        <?php if ($fotoUrl !== ''): ?>
            <button type="submit" name="hapus_foto" value="1" class="pf-link-hapus"
                    onclick="return confirm('Hapus foto profil?')">Hapus foto</button>
        <?php endif; ?>

        <div class="pf-hint">JPG, PNG, atau WEBP. Maksimal 2 MB.</div>

    </form>

    <div class="pf-card">

        <button type="button" class="pf-row" id="barisNama" aria-expanded="false">
            <span>Nama</span>
            <span class="nilai">
                <span id="teksNama"><?= htmlspecialchars($namaUser !== '' ? $namaUser : 'Atur sekarang') ?></span>
                <span class="panah">&rsaquo;</span>
            </span>
        </button>

        <form method="post" class="pf-edit" id="formNama" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
            <input type="hidden" name="aksi" value="nama">
            <input type="text" name="nama" maxlength="60" required
                   value="<?= htmlspecialchars($namaUser) ?>" placeholder="Nama lengkap">
            <button type="submit" class="pf-btn">Simpan nama</button>
        </form>

        <div class="pf-row">
            <span>Email</span>
            <span class="nilai"><span><?= htmlspecialchars(samarkanEmail($emailUser)) ?></span></span>
        </div>

    </div>

    <div class="pf-card">

        <a href="ubah_password.php" class="pf-row">
            <span>Kata Sandi</span>
            <span class="nilai aksen">
                <span>Ubah sekarang</span>
                <span class="panah">&rsaquo;</span>
            </span>
        </a>

    </div>

    <p class="pf-note">Email tidak dapat diubah dari halaman ini.</p>

</main>

<script>
(function () {
    var baris = document.getElementById("barisNama");
    var form  = document.getElementById("formNama");

    baris.addEventListener("click", function () {
        var buka = form.classList.toggle("show");
        baris.setAttribute("aria-expanded", buka ? "true" : "false");
        if (buka) { form.querySelector("input[name=nama]").focus(); }
    });

    // Foto: kirim otomatis setelah file dipilih
    var inputFoto = document.getElementById("inputFoto");

    inputFoto.addEventListener("change", function () {
        var f = inputFoto.files[0];
        if (!f) { return; }

        if (f.size > 2 * 1024 * 1024) {
            alert("Ukuran foto maksimal 2 MB.");
            inputFoto.value = "";
            return;
        }

        document.getElementById("formFoto").submit();
    });

    <?php if ($error !== '' && ($_POST['aksi'] ?? '') === 'nama'): ?>
    form.classList.add("show");
    <?php endif; ?>
})();
</script>

<?php require __DIR__ . '/footer_home.php'; ?>

</body>
</html>