<?php
// Hubungkan ke database - naik satu tingkat karena file berada di folder /views/
include '../config/database.php'; 
session_start();

// Proteksi: Hanya Admin Sekolah
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin_sekolah') { 
    exit("Akses ditolak."); 
}

$sekolah = $_SESSION['nama_sekolah'];

// 1. Tangkap Parameter Filter dari URL
$cari         = isset($_GET['cari']) ? $_GET['cari'] : "";
$f_jenis      = isset($_GET['f_jenis']) ? $_GET['f_jenis'] : "";
$f_tgl_dari   = isset($_GET['f_tgl_dari']) ? $_GET['f_tgl_dari'] : "";
$f_tgl_sampai = isset($_GET['f_tgl_sampai']) ? $_GET['f_tgl_sampai'] : "";

// 2. Build Query (Sinkron dengan filter di pengajuan.php)
$where_clause = " WHERE p.sekolah = ?";
$params = [$sekolah];

if(!empty($cari)) {
    $where_clause .= " AND (p.nama LIKE ? OR p.nip LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}
if(!empty($f_jenis)) {
    $where_clause .= " AND pc.jenis_cuti = ?";
    $params[] = $f_jenis;
}
if(!empty($f_tgl_dari) && !empty($f_tgl_sampai)) {
    $where_clause .= " AND pc.tgl_mulai BETWEEN ? AND ?";
    $params[] = $f_tgl_dari;
    $params[] = $f_tgl_sampai;
}

// Ambil Data
try {
    $sql = "SELECT pc.*, p.nama, p.nip FROM pengajuan_cuti pc 
            JOIN pegawai p ON pc.pegawai_id = p.id" . $where_clause . " 
            ORDER BY pc.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
} catch (PDOException $e) {
    exit("Kesalahan Database: " . $e->getMessage());
}

// 3. Header untuk Download Excel
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Rekap_Cuti_".date('Ymd').".xls");
header("Pragma: no-cache");
header("Expires: 0");
?>

<style>
    /* Format agar teks panjang atau angka NIP tidak berantakan di Excel */
    .str { mso-number-format:\@; }
    .header-table { background-color: #3b82f6; color: #ffffff; font-weight: bold; text-align: center; }
</style>

<center>
    <h3>REKAPITULASI PENGAJUAN CUTI PEGAWAI</h3>
    <h4>INSTANSI: <?php echo strtoupper($sekolah); ?></h4>
</center>

<table border="1">
    <thead>
        <tr>
            <th class="header-table" width="50">No</th>
            <th class="header-table" width="180">NIP</th>
            <th class="header-table" width="250">Nama Pegawai</th>
            <th class="header-table" width="150">Jenis Cuti</th>
            <th class="header-table" width="250">Alasan</th>
            <th class="header-table" width="120">Tgl Mulai</th>
            <th class="header-table" width="120">Tgl Selesai</th>
            <th class="header-table" width="80">Durasi</th>
            <th class="header-table" width="120">Status</th>
            <th class="header-table" width="300">Alamat Cuti</th>
            <th class="header-table" width="150">No. WA</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $no = 1; 
        if(count($data) > 0):
            foreach($data as $row): 
        ?>
        <tr>
            <td align="center"><?= $no++; ?></td>
            <td class="str" align="left"><?= $row['nip']; ?></td>
            <td><?= $row['nama']; ?></td>
            <td><?= $row['jenis_cuti']; ?></td>
            <td><?= strip_tags($row['alasan']); ?></td>
            <td align="center"><?= date('d/m/Y', strtotime($row['tgl_mulai'])); ?></td>
            <td align="center"><?= date('d/m/Y', strtotime($row['tgl_selesai'])); ?></td>
            <td align="center"><?= $row['durasi']; ?> Hari</td>
            <td align="center"><?= strtoupper($row['status']); ?></td>
            <td><?= strip_tags($row['alamat_cuti']); ?></td>
            <td class="str" align="left"><?= $row['no_wa']; ?></td>
        </tr>
        <?php 
            endforeach; 
        else:
        ?>
        <tr>
            <td colspan="11" align="center" style="height: 50px;">Data tidak ditemukan.</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>