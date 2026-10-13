<?php

session_start();

require_once 'config/koneksi.php';

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

/* =========================================================
   CEK & BUAT PASSWORD
   Mengikuti format yang sudah tersimpan di database supaya
   login.php tetap bisa memverifikasinya:
   - bcrypt/argon  -> password_verify / password_hash
   - md5 (32 hex)  -> md5
   - lainnya       -> dianggap teks biasa
========================================================= */

function adaHash(string $tersimpan): bool
{
    return (bool) preg_match('/^\$(2y|2a|2b|argon2i|argon2id)\$/', $tersimpan);
}

function adaMd5(string $tersimpan): bool
{
    return (bool) preg_match('/^[a-f0-9]{32}$/i', $tersimpan);
}

function cekPassword(string $input, string $tersimpan): bool
{
    if (adaHash($tersimpan)) {
        return password_verify($input, $tersimpan);
    }

    if (adaMd5($tersimpan)) {
        return hash_equals(strtolower($tersimpan), md5($input));
    }

    return hash_equals($tersimpan, $input);
}

function buatPassword(string $baru, string $tersimpanLama): string
{
    if (adaHash($tersimpanLama)) {
        return password_hash($baru, PASSWORD_DEFAULT);
    }

    if (adaMd5($tersimpanLama)) {
        return md5($baru);
    }

    return $baru;
}

$sukses = '';
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token   = $_POST['csrf'] ?? '';
    $lama    = (string) ($_POST['password_lama'] ?? '');
    $baru    = (string) ($_POST['password_baru'] ?? '');
    $ulangi  = (string) ($_POST['password_ulangi'] ?? '');

    if (!hash_equals($_SESSION['csrf'], $token)) {

        $error = 'Sesi tidak valid, silakan muat ulang halaman.';

    } elseif ($lama === '' || $baru === '' || $ulangi === '') {

        $error = 'Semua kolom wajib diisi.';

    } elseif (strlen($baru) < 8) {

        $error = 'Kata sandi baru minimal 8 karakter.';

    } elseif ($baru !== $ulangi) {

        $error = 'Konfirmasi kata sandi tidak sama.';

    } elseif ($baru === $lama) {

        $error = 'Kata sandi baru harus berbeda dari yang lama.';

    } else {

        try {

            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $tersimpan = $stmt->fetchColumn();

            if ($tersimpan === false) {

                $error = 'Akun tidak ditemukan.';

            } elseif (!cekPassword($lama, (string) $tersimpan)) {

                $error = 'Kata sandi lama salah.';

            } else {

                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([buatPassword($baru, (string) $tersimpan), $userId]);

                session_regenerate_id(true);
                $_SESSION['csrf'] = bin2hex(random_bytes(16));

                $sukses = 'Kata sandi berhasil diubah.';
            }

        } catch (PDOException $e) {

            $error = 'Gagal mengubah kata sandi. Pastikan tabel users punya kolom "password".';

        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ubah Kata Sandi - Joyzone Store</title>
    <link rel="icon" type="image/png" href="assets/images/logo-joyzone.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="profil.css?v=2">

</head>

<body class="pf">

<header class="pf-bar">
    <a href="profil.php" class="pf-back" aria-label="Kembali">&larr;</a>
    <h1>Ubah Kata Sandi</h1>
</header>

<main class="pf-wrap">

    <?php if ($sukses !== ''): ?>
        <div class="pf-alert pf-ok"><?= htmlspecialchars($sukses) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="pf-alert pf-err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="pf-card pf-form" autocomplete="off">

        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

        <div class="pf-field">
            <label for="pl">Kata sandi lama</label>
            <div class="pf-input">
                <input type="password" id="pl" name="password_lama" required autocomplete="current-password">
                <button type="button" class="pf-eye" data-target="pl" aria-label="Tampilkan kata sandi">👁</button>
            </div>
        </div>

        <div class="pf-field">
            <label for="pb">Kata sandi baru</label>
            <div class="pf-input">
                <input type="password" id="pb" name="password_baru" required minlength="8" autocomplete="new-password">
                <button type="button" class="pf-eye" data-target="pb" aria-label="Tampilkan kata sandi">👁</button>
            </div>
            <div class="pf-hint">Minimal 8 karakter.</div>
        </div>

        <div class="pf-field">
            <label for="pu">Ulangi kata sandi baru</label>
            <div class="pf-input">
                <input type="password" id="pu" name="password_ulangi" required minlength="8" autocomplete="new-password">
                <button type="button" class="pf-eye" data-target="pu" aria-label="Tampilkan kata sandi">👁</button>
            </div>
        </div>

        <button type="submit" class="pf-btn">Simpan kata sandi</button>

    </form>

</main>

<script>
document.querySelectorAll(".pf-eye").forEach(function (btn) {
    btn.addEventListener("click", function () {
        var input = document.getElementById(btn.getAttribute("data-target"));
        input.type = input.type === "password" ? "text" : "password";
    });
});
</script>

</body>
</html>
