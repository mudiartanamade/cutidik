<?php
include "../config/database.php"; // Sesuaikan path koneksi
session_start();

if (!isset($_SESSION['role'])) { die("Akses ditolak."); }

// Ambil Parameter Filter
$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";
$filter_sekolah = isset($_GET['filter_sekolah']) ? $_GET['filter_sekolah'] : "";

// Build Query
$params = [];
$where = "WHERE status_aktif = 'aktif'";

if ($_SESSION['role'] != 'admin_dinas') {
    $where .= " AND sekolah = ?";
    $params[] = $_SESSION['nama_sekolah'];
} else if (!empty($filter_sekolah)) {
    $where .= " AND sekolah = ?";
    $params[] = $filter_sekolah;
}

if (!empty($cari)) {
    $where .= " AND (nama LIKE ? OR nip LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}

$sql = "SELECT nip, nama, pangkat, jabatan, tipe, sekolah FROM pegawai $where ORDER BY sekolah ASC, nama ASC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Konfigurasi Header Download CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Rekap_Guru_KS_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');

// Menulis BOM agar Excel mengenali UTF-8
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Tulis Header Kolom
fputcsv($output, ['NIP', 'Nama Lengkap', 'Pangkat/Gol', 'Jabatan', 'Tipe', 'Unit Kerja']);

// Tulis Data
foreach ($data as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;