<?php
session_start();

// 1. Generate Kode Acak (Case Sensitive)
// Kita buat pool karakter: Angka, Huruf Kecil, Huruf Besar
// Saya hilangkan huruf/angka yang mirip (0, O, 1, l) agar tidak membingungkan user
$permitted_chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';

function generate_string($input, $strength = 5) {
    $input_length = strlen($input);
    $random_string = '';
    for($i = 0; $i < $strength; $i++) {
        $random_character = $input[mt_rand(0, $input_length - 1)];
        $random_string .= $random_character;
    }
    return $random_string;
}

$captcha_code = generate_string($permitted_chars, 5);

// 2. Simpan Kode ke Session
$_SESSION['captcha_code'] = $captcha_code;

// 3. Buat Gambar Captcha
header("Content-type: image/png");
$width = 120;
$height = 45;
$image = imagecreatetruecolor($width, $height);

// Warna-warna
$background_color = imagecolorallocate($image, 255, 255, 255); // Putih
$text_color = imagecolorallocate($image, 30, 41, 59);       // Slate-800 (Gelap)
$line_color = imagecolorallocate($image, 203, 213, 225);    // Slate-300 (Garis)
$pixel_color = imagecolorallocate($image, 148, 163, 184);   // Slate-400 (Titik)

// Isi Background
imagefill($image, 0, 0, $background_color);

// Tambahkan Garis Gangguan
for($i=0; $i<5; $i++) {
    imageline($image, 0, rand()%50, 200, rand()%50, $line_color);
}

// Tambahkan Titik-titik Gangguan
for($i=0; $i<1000; $i++) {
    imagesetpixel($image, rand()%$width, rand()%$height, $pixel_color);
}

// Tulis Kode Captcha ke Gambar
// Font bawaan GD (Angka 5)
imagestring($image, 5, 35, 14, $captcha_code, $text_color);

// Output Gambar
imagepng($image);
imagedestroy($image);
?>