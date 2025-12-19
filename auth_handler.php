<?php
session_start();
require_once 'config/database.php';

// PROSES LOGIN
if (isset($_POST['login'])) {
    
    // --- 1. VERIFIKASI CAPTCHA (CASE SENSITIVE) ---
    // Cek apakah input captcha cocok dengan session SECARA PERSIS (Huruf Besar/Kecil Berpengaruh)
    if (!isset($_POST['captcha']) || !isset($_SESSION['captcha_code']) || $_POST['captcha'] !== $_SESSION['captcha_code']) {
        // Jika Salah: Redirect kembali dengan error captcha
        header("Location: index.php?error=captcha");
        exit;
    }

    // --- 2. PROSES LOGIN BIASA ---
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Ambil data user dari database
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Verifikasi Password
    if ($user && password_verify($password, $user['password'])) {
        // Login Berhasil
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role']; 
        $_SESSION['nama_sekolah'] = $user['nama_sekolah'];
        $_SESSION['jenjang'] = $user['jenjang'];
        
        // Hapus session captcha agar tidak bisa dipakai ulang
        unset($_SESSION['captcha_code']);
        
        // Redirect ke Dashboard
        header("Location: dashboard.php");
        exit;
    } else {
        // Gagal Login (Username/Pass Salah)
        header("Location: index.php?error=1");
        exit;
    }
}

// Jika file diakses langsung tanpa submit form
header("Location: index.php");
?>