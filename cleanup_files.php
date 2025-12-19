<?php
if (php_sapi_name() != 'cli') {
    die("Akses ditolak. Skrip ini hanya boleh dijalankan melalui sistem.");
}
// cleanup_files.php
include "config/database.php"; // Pastikan path koneksi benar

$target_dir = "uploads/";

try {
    // Ambil hanya file yang terdaftar di tabel pengajuan yang usianya > 30 hari
    // Kita filter berdasarkan tgl_mulai atau tgl_input
    $sql = "SELECT file_bukti FROM pengajuan_cuti 
            WHERE file_bukti IS NOT NULL 
            AND tgl_mulai <= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    
    $stmt = $conn->query($sql);
    $files_to_delete = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $count = 0;
    if ($files_to_delete) {
        foreach ($files_to_delete as $file_name) {
            $file_path = $target_dir . $file_name;
            
            // Eksekusi hapus hanya jika file ada di folder
            if (!empty($file_name) && file_exists($file_path)) {
                if (unlink($file_path)) {
                    $count++;
                }
            }
        }
    }
    echo "Pembersihan selesai. Berhasil menghapus $count file bukti cuti lama. File permanen tetap aman.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>