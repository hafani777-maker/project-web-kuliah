<?php

/* =========================================================
   FOTO PROFIL
   Disimpan sebagai file: uploads/profil/user_<id>.jpg
   (tidak perlu kolom baru di tabel users)
========================================================= */

const FOTO_PROFIL_DIR = 'uploads/profil';
const FOTO_PROFIL_MAKS = 2 * 1024 * 1024; // 2 MB

/* URL foto profil user, atau '' jika belum punya */
function fotoProfilUrl(int $userId): string
{
    if ($userId <= 0) {
        return '';
    }

    $files = glob(__DIR__ . '/' . FOTO_PROFIL_DIR . '/user_' . $userId . '.*');

    if (!$files) {
        return '';
    }

    return FOTO_PROFIL_DIR . '/' . basename($files[0]) . '?v=' . filemtime($files[0]);
}

function hapusFotoProfil(int $userId): void
{
    foreach (glob(__DIR__ . '/' . FOTO_PROFIL_DIR . '/user_' . $userId . '.*') ?: [] as $file) {
        @unlink($file);
    }
}

/* Simpan foto dari $_FILES['foto']. Mengembalikan pesan error, '' jika berhasil. */
function simpanFotoProfil(int $userId, array $file): string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return 'Upload tidak valid.';
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return 'Pilih foto terlebih dahulu.';
    }

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return 'Ukuran foto maksimal 2 MB.';
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'Gagal mengunggah foto.';
    }

    if ($file['size'] > FOTO_PROFIL_MAKS) {
        return 'Ukuran foto maksimal 2 MB.';
    }

    // Validasi isi file (bukan hanya ekstensi)
    $info = @getimagesize($file['tmp_name']);
    $mime = $info['mime'] ?? '';
    $ekstensi = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if (!isset($ekstensi[$mime])) {
        return 'Format foto harus JPG, PNG, atau WEBP.';
    }

    $dir = __DIR__ . '/' . FOTO_PROFIL_DIR;

    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        return 'Folder penyimpanan foto tidak bisa dibuat.';
    }

    // Larang eksekusi script di folder ini
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "<FilesMatch \"\\.(php|phtml|phar)$\">\n    Require all denied\n</FilesMatch>\n");
    }

    hapusFotoProfil($userId);

    // Potong jadi persegi 400x400 bila GD tersedia
    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {

        $src = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));

        if ($src) {

            $w  = imagesx($src);
            $h  = imagesy($src);
            $s  = min($w, $h);
            $sx = intdiv($w - $s, 2);
            $sy = intdiv($h - $s, 2);

            $out = imagecreatetruecolor(400, 400);
            imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
            imagecopyresampled($out, $src, 0, 0, $sx, $sy, 400, 400, $s, $s);

            $ok = imagejpeg($out, $dir . '/user_' . $userId . '.jpg', 85);

            imagedestroy($src);
            imagedestroy($out);

            return $ok ? '' : 'Gagal menyimpan foto.';
        }
    }

    // Tanpa GD: simpan file asli
    return move_uploaded_file($file['tmp_name'], $dir . '/user_' . $userId . '.' . $ekstensi[$mime])
        ? ''
        : 'Gagal menyimpan foto.';
}
