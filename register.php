<?php

session_start();

require_once 'config/koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// Batas usia minimal pembeli
const USIA_MINIMAL = 18;

$error = "";
$success = "";


// ==================================================
// VERIFIKASI USIA
// ==================================================

function tanggalLahirValid(string $tanggal): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $tanggal);

    if (!$d || $d->format('Y-m-d') !== $tanggal) {
        return false;
    }

    $hariIni = new DateTime('today');

    return $d <= $hariIni && (int) $d->format('Y') >= 1900;
}

function hitungUsia(string $tanggal): int
{
    return (new DateTime($tanggal))->diff(new DateTime('today'))->y;
}


// ==================================================
// PROSES REGISTER
// ==================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $password   = $_POST['password'] ?? '';
    $konfirmasi = isset($_POST['usia_18']);


    // ==================================================
    // VALIDASI
    // ==================================================

    if (
        $name === '' ||
        $email === '' ||
        $birth_date === '' ||
        $password === ''
    ) {

        $error = "Semua data wajib diisi.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Format email tidak valid.";

    } elseif (!tanggalLahirValid($birth_date)) {

        $error = "Tanggal lahir tidak valid.";

    } elseif (hitungUsia($birth_date) < USIA_MINIMAL) {

        $error = "Maaf, Joyzone Store khusus pembeli berusia " . USIA_MINIMAL . " tahun ke atas.";

    } elseif (!$konfirmasi) {

        $error = "Centang pernyataan bahwa kamu berusia " . USIA_MINIMAL . " tahun ke atas.";

    } elseif (strlen($password) < 6) {

        $error = "Password minimal 6 karakter.";

    } else {

        try {

            // ==================================================
            // CEK EMAIL SUDAH TERDAFTAR
            // ==================================================

            $check = $pdo->prepare(
                "SELECT id FROM users WHERE email = ? LIMIT 1"
            );

            $check->execute([$email]);

            if ($check->fetch()) {

                $error = "Email sudah terdaftar.";

            } else {

                // ==================================================
                // HASH PASSWORD
                // ==================================================

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


                // ==================================================
                // SIMPAN DATA KE DATABASE
                // ==================================================

                $sql = "INSERT INTO users
                        (nama, email, tanggal_lahir, password)
                        VALUES (?, ?, ?, ?)";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    $name,
                    $email,
                    $birth_date,
                    $hashedPassword
                ]);


                // ==================================================
                // BERHASIL
                // ==================================================

                $success = "Registrasi berhasil. Silakan login.";

                // Kosongkan password setelah berhasil
                $_POST['password'] = '';

            }

        } catch (PDOException $e) {

            $error = "Error database: " . $e->getMessage();

        }

    }

}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register - Joyzone Store</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap">

    <style>
        /* Warna & ukuran huruf mengikuti halaman index / katalog */
        :root {
            --ink: #111111;
            --muted: #6b6b66;
            --line: #e5e5e1;
            --mist: #f3f3f0;
            --paper: #ffffff;
            --signal: #e8590c;
            --sand: #cbb48a;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: "Manrope", "Segoe UI", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            font-size: 14px;
            background: var(--mist);
            color: var(--ink);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-container { width: 100%; max-width: 400px; }

        .auth-card {
            background: var(--paper);
            padding: 40px 24px 32px;
            border-radius: 14px;
            border: 1px solid var(--line);
            border-top: 3px solid var(--sand);
            box-shadow: 0 18px 40px rgba(17, 17, 17, .08);
        }

        .auth-title {
            text-align: center;
            color: var(--ink);
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -.01em;
            margin-bottom: 28px;
        }

        .error,
        .success {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            line-height: 1.5;
        }

        .error   { background: #fdecea; color: #b3261e; }
        .success { background: #e8f5ec; color: #1b6b3a; }

        .sr-only {
            position: absolute;
            width: 1px; height: 1px;
            overflow: hidden;
            clip: rect(0 0 0 0);
            white-space: nowrap;
        }

        .field-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            margin: 0 0 4px 12px;
        }

        input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            margin-bottom: 14px;
            font-family: inherit;
            font-size: 14px;
            color: var(--ink);
            background: var(--paper);
            outline: none;
            transition: border-color .2s;
        }

        input::placeholder { color: #9a9a94; }

        input:focus { border-color: var(--ink); }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--ink);
            box-shadow: 0 0 0 1000px var(--paper) inset;
            transition: background-color 5000s ease-in-out 0s;
        }

        input[type="date"] { color-scheme: light; }

        /* Login memakai kolom berbentuk pil, sesuai desain */
        .auth-pill input { border-radius: 999px; padding: 11px 16px; }

        .btn {
            display: block;
            width: 100%;
            padding: 11px 16px;
            border: 1.5px solid var(--ink);
            border-radius: 999px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: background .2s, color .2s, border-color .2s, transform .1s;
        }

        .btn:active { transform: translateY(1px); }

        .btn:focus-visible,
        .back-home:focus-visible,
        input:focus-visible { outline: 2px solid var(--signal); outline-offset: 2px; }

        /* Tombol utama: hitam, hover oranye (sama dengan "Beli Sekarang") */
        .btn-primary,
        .btn-login { background: var(--ink); color: #fff; }

        .btn-primary:hover,
        .btn-login:hover { background: var(--signal); border-color: var(--signal); }

        /* Tombol kedua: putih berbingkai (sama dengan "Detail") */
        .btn-secondary { background: var(--paper); color: var(--ink); margin-top: 12px; }
        .btn-secondary:hover { background: var(--mist); }

        /* Tombol login: kecil, di tengah */
        .btn-login {
            width: auto;
            min-width: 150px;
            margin: 26px auto 0;
            letter-spacing: .04em;
        }

        .age-note {
            margin: -4px 12px 12px;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.5;
        }

        .age-check {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 2px 4px 0;
            font-size: 13px;
            color: var(--muted);
            line-height: 1.5;
            cursor: pointer;
        }

        .age-check input {
            width: 17px;
            height: 17px;
            margin: 2px 0 0;
            padding: 0;
            flex: none;
            accent-color: var(--ink);
            cursor: pointer;
        }

        .form-actions { margin-top: 26px; }

        .back-home {
            display: block;
            text-align: center;
            margin-top: 22px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .back-home:hover { color: var(--ink); }

        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: var(--muted);
        }

        .register-link a { color: var(--ink); font-weight: 700; text-decoration: none; }
        .register-link a:hover { color: var(--signal); text-decoration: underline; }

    </style>

</head>


<body>

<div class="auth-container">

    <div class="auth-card">

        <h1 class="auth-title">Register</h1>

        <!-- PESAN ERROR -->

        <?php if ($error !== ''): ?>

            <div class="error" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <!-- PESAN BERHASIL -->

        <?php if ($success !== ''): ?>

            <div class="success" role="status">
                <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>

        <!-- FORM REGISTER -->

        <form action="register.php" method="POST">

            <label class="sr-only" for="name">Nama</label>

            <input
                type="text"
                id="name"
                name="name"
                placeholder="Nama lengkap"
                autocomplete="name"
                value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                required
            >

            <label class="sr-only" for="email">Email</label>

            <input
                type="email"
                id="email"
                name="email"
                placeholder="Email"
                autocomplete="email"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                required
            >

            <label class="field-label" for="birth_date">Tanggal lahir</label>

            <input
                type="date"
                id="birth_date"
                name="birth_date"
                min="1900-01-01"
                max="<?= date('Y-m-d', strtotime('-' . USIA_MINIMAL . ' years')) ?>"
                value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>"
                required
            >

            <p class="age-note">Joyzone Store khusus pembeli berusia <?= USIA_MINIMAL ?> tahun ke atas.</p>

            <label class="sr-only" for="password">Password</label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Password (minimal 6 karakter)"
                autocomplete="new-password"
                minlength="6"
                required
            >

            <label class="age-check">
                <input
                    type="checkbox"
                    name="usia_18"
                    value="1"
                    <?= isset($_POST['usia_18']) ? 'checked' : '' ?>
                    required
                >
                <span>Saya menyatakan berusia <?= USIA_MINIMAL ?> tahun ke atas dan data tanggal lahir saya benar.</span>
            </label>

            <div class="form-actions">

                <button type="submit" class="btn btn-primary">Register</button>

                <a href="login.php" class="btn btn-secondary">Sudah punya akun? Login</a>

            </div>

        </form>

        <!-- KEMBALI KE HOME -->

        <a href="index.php" class="back-home">&larr; Kembali ke Home</a>

    </div>

</div>

</body>

</html>