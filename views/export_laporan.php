<?php
// views/export_laporan.php

// 1. MULAI SESI & KONEKSI
session_start();
// Sesuaikan path ke config database (naik satu folder)
require_once '../config/database.php';

// 2. PROTEKSI
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin_dinas') {
    exit("Akses Ditolak");
}

// 3. FUNGSI HITUNG DURASI
function hitungDurasiReal($start, $end, $hari_kerja, $libur_array) {
    if(empty($start) || empty($end)) return 0;
    
    $start_date = new DateTime($start);
    $end_date   = new DateTime($end);
    
    if($start_date > $end_date) return 0;

    $durasi = 0;
    $period = new DatePeriod($start_date, new DateInterval('P1D'), $end_date->modify('+1 day'));
    
    foreach($period as $dt) {
        $curr = $dt->format('Y-m-d');
        $dayW = $dt->format('w'); // 0=Minggu, 6=Sabtu
        
        if($dayW == 0) continue; 
        if($dayW == 6 && $hari_kerja == '5 Hari') continue; 
        if(in_array($curr, $libur_array)) continue; 
        
        $durasi++;
    }
    return $durasi;
}

// 4. AMBIL DATA LIBUR
$libur_nasional = [];
try {
    $q_lib = $conn->query("SELECT tanggal FROM hari_libur");
    while($l = $q_lib->fetch()) { 
        $libur_nasional[] = $l['tanggal']; 
    }
} catch (Exception $e) { }

// 5. AMBIL PARAMETER FILTER
$tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$sekolah = $_GET['sekolah'] ?? '';
$jenis = $_GET['jenis'] ?? '';
$keyword = $_GET['q'] ?? ''; 

// 6. QUERY DATA
$sql = "SELECT pc.*, p.nama, p.nip, p.sekolah, ds.hari_belajar 
        FROM pengajuan_cuti pc 
        JOIN pegawai p ON pc.pegawai_id = p.id 
        LEFT JOIN data_sekolah ds ON p.sekolah = ds.nama_sekolah
        WHERE pc.status = 'disetujui'";

if (!empty($tgl_awal) && !empty($tgl_akhir)) {
    $sql .= " AND (pc.tgl_mulai BETWEEN '$tgl_awal' AND '$tgl_akhir')";
}
if (!empty($sekolah)) {
    $sql .= " AND p.sekolah = '$sekolah'";
}
if (!empty($jenis)) {
    $sql .= " AND pc.jenis_cuti = '$jenis'";
}
if (!empty($keyword)) {
    $sql .= " AND p.nama LIKE '%$keyword%'";
}

$sql .= " ORDER BY p.sekolah ASC, p.nama ASC";

$stmt = $conn->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. HEADER DOWNLOAD CSV
// Header ini memberitahu browser bahwa ini adalah file download, bukan halaman web
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Laporan_Cuti_' . date('Ymd_His') . '.csv');

// 8. OUTPUT CSV
$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // BOM untuk Excel Windows

// Header Kolom
fputcsv($out, [
    'No', 'NIP', 'Nama Pegawai', 'Unit Kerja', 
    'Jenis Cuti', 'Tgl Mulai', 'Tgl Selesai', 
    'Durasi (Hari Kerja)', 'Alamat Cuti', 'No. WhatsApp', 'Alasan Cuti'
]);

$no = 1;
foreach ($data as $row) {
    $hari_kerja_sekolah = !empty($row['hari_belajar']) ? $row['hari_belajar'] : '6 Hari';
    $durasi_real = hitungDurasiReal($row['tgl_mulai'], $row['tgl_selesai'], $hari_kerja_sekolah, $libur_nasional);

    fputcsv($out, [
        $no++,
        "'" . $row['nip'], 
        $row['nama'],
        $row['sekolah'],
        $row['jenis_cuti'],
        date('d/m/Y', strtotime($row['tgl_mulai'])),
        date('d/m/Y', strtotime($row['tgl_selesai'])),
        $durasi_real, 
        $row['alamat_cuti'] ?? '-', 
        "'" . ($row['no_wa'] ?? '-'), 
        $row['alasan'] ?? '-'
    ]);
}

fclose($out);
exit;