<?php
// Set Timezone ke WITA (Waktu Indonesia Tengah - Denpasar)
date_default_timezone_set('Asia/Makassar');

// Konfigurasi Database
$host = "localhost";
$user = "aioaomjk_elinktech";     // Default user XAMPP/Laragon
$pass = "VBY.lN.nntH~qHoT";         // Default password XAMPP/Laragon (biarkan kosong jika default)
$db   = "aioaomjk_db_cuti_disdikpora";

try {
    // Membuat koneksi menggunakan PDO (PHP Data Objects)
    // Charset utf8 penting agar simbol atau karakter khusus tersimpan dengan benar
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    
    // Set mode error ke Exception agar mudah debugging jika ada error database
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode ke Associative Array (agar data dipanggil dengan nama kolom, misal $row['nama'])
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    // Jika koneksi gagal, hentikan program dan tampilkan pesan error
    die("Koneksi Database Gagal: " . $e->getMessage());
}

/**
 * Fungsi Helper: Format Tanggal Indonesia
 * Mengubah format YYYY-MM-DD menjadi format Hari Bulan Tahun
 * Contoh: 2024-08-17 menjadi 17 Agustus 2024
 */
function tgl_indo($tanggal){
    // Cek jika tanggal kosong atau invalid
    if($tanggal == '0000-00-00' || $tanggal == null || $tanggal == '') {
        return '-';
    }
    
    $bulan = array (
        1 =>   'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    );
    
    $pecahkan = explode('-', $tanggal);
    
    // Validasi format tanggal harus ada 3 bagian (tahun-bulan-tanggal)
    if(count($pecahkan) < 3) {
        return $tanggal;
    }
    
    // Format: Tanggal + Nama Bulan + Tahun
    return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
}
?>