<?php
require 'config/database.php';

if(!isset($_GET['token'])) die("Akses ditolak: Token tidak valid.");
$token_url = $_GET['token'];

// UBAH: Query berdasarkan kolom 'token'
$sql = "SELECT pc.*, p.nama, p.nip, p.pangkat, p.jabatan, p.sekolah, p.tipe 
        FROM pengajuan_cuti pc 
        JOIN pegawai p ON pc.pegawai_id = p.id 
        WHERE pc.token = ?"; // Menggunakan pc.token
        
$stmt = $conn->prepare($sql);
$stmt->execute([$token_url]);
$row = $stmt->fetch();

if(!$row || $row['status'] != 'disetujui') {
    die("Dokumen belum tersedia atau pengajuan belum disetujui.");
}

// --- FUNGSI PEMBANTU ---
if (!function_exists('tgl_indo')) {
    function tgl_indo($tanggal){
        if(!$tanggal) return "-";
        $bulan = array (
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        );
        $pecahkan = explode('-', $tanggal);
        if(count($pecahkan) < 3) return $tanggal; 
        return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
    }
}

if (!function_exists('terbilang')) {
    function terbilang($x) {
        $angka = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
        if ($x < 12) return $angka[$x];
        elseif ($x < 20) return $angka[$x - 10] . " Belas";
        elseif ($x < 100) return $angka[(int)($x / 10)] . " Puluh " . $angka[$x % 10];
        else return $x; 
    }
}

// --- LOGIKA LABEL STATUS PEGAWAI ---
// Mengambil status dari database, default ke PNS jika kosong
$status_db = isset($row['tipe']) ? $row['tipe'] : 'PNS';
$label_status = "Pegawai Negeri Sipil"; // Default

// Cek Logika (Case Insensitive)
if (stripos($status_db, 'P3K') !== false || stripos($status_db, 'PPPK') !== false) {
    // Cek spesifik apakah Paruh Waktu
    if (stripos($status_db, 'Paruh Waktu') !== false) {
        $label_status = "PPPK Paruh Waktu";
    } else {
        $label_status = "Pegawai Pemerintah dengan Perjanjian Kerja";
    }
} elseif (stripos($status_db, 'Kontrak') !== false || stripos($status_db, 'Honorer') !== false) {
    $label_status = "Pegawai Kontrak Daerah";
} else {
    $label_status = "Pegawai Negeri Sipil";
}

// --- SETUP VARIABEL SURAT ---
$jenis = $row['jenis_cuti'];
$nomor_tengah = $row['nomor_surat_input'] ?? '.....'; 
$tgl_surat_indo = tgl_indo($row['tgl_disetujui'] ?? date('Y-m-d'));

// Token & QR Validasi
$token = $row['token'] ?? '';
$base_url = "http://" . $_SERVER['HTTP_HOST'] . str_replace("print_sk.php", "", $_SERVER['PHP_SELF']);
$validasi_url = $base_url . "validasi_sk.php?token=" . $token;
$qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($validasi_url);

// --- LOGIKA PEMILIHAN FORMAT ---
$list_sk = ['Cuti Tahunan', 'Cuti Melahirkan', 'Cuti Sakit'];

$is_sk = false;
$judul_surat = "";
$nomor_surat = "";

if(in_array($jenis, $list_sk)) {
    // === FORMAT SK ===
    $is_sk = true;
    if($jenis == 'Cuti Tahunan') $kode_surat = "800.1.11.4";
    elseif($jenis == 'Cuti Melahirkan') $kode_surat = "800.1.11.5";
    elseif($jenis == 'Cuti Sakit') $kode_surat = "800.1.11.6";
    else $kode_surat = "800"; 

    $nomor_surat = $kode_surat . " / " . $nomor_tengah . " / Disdikpora";
    $judul_surat = "SURAT IZIN " . strtoupper($jenis);
    
    // MENGGUNAKAN LABEL STATUS DINAMIS DI SINI
    $pembuka_sk = "Diberikan ". $jenis ." kepada <b>". $label_status ."</b> :";
    
} else {
    // === FORMAT SURAT PENGANTAR ===
    $is_sk = false;
    $nomor_surat = "000.1.12.7 / " . $nomor_tengah . " / Disdikpora"; 
    $judul_surat = "SURAT PENGANTAR";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dokumen Cuti - <?= $row['nama'] ?></title>
    <style>
        @page { size: A4; margin: 0; }
        body { 
            font-family: 'Bookman Old Style', 'Times New Roman', serif; 
            font-size: 11pt; 
            line-height: 1.4; 
            color: #000;
            background: #525659;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }
        .sheet { 
            width: 210mm; 
            min-height: 297mm; 
            padding: 10mm 15mm; 
            margin: 0; 
            background: white; 
            box-sizing: border-box; 
            position: relative;
        }
        @media print { 
            body { background: none; padding: 0; }
            .sheet { width: auto; height: auto; padding: 10mm 15mm; } 
        }
        
        /* KOP SURAT */
        .kop { width: 100%; text-align: center; margin-bottom: 10px; border-bottom: 3px double #000; padding-bottom: 5px; }
        .kop img { width: 100%; max-width: 100%; height: auto; } 

        /* UTILITAS */
        .center { text-align: center; }
        .justify { text-align: justify; }
        .bold { font-weight: bold; }
        .underline { text-decoration: underline; }
        
        /* SK ELEMENT */
        .sk-title { font-size: 13pt; margin-top: 10px; margin-bottom: 2px; text-transform: uppercase; }
        .sk-nomor { font-size: 11pt; margin-bottom: 15px; }
        
        .table-biodata { width: 100%; margin-left: 5px; margin-bottom: 10px; }
        .table-biodata td { vertical-align: top; padding: 1px 0; } 
        .label-col { width: 190px; }
        .sep-col { width: 15px; text-align: center; }

        .ketentuan { margin-left: 20px; margin-top: 5px; }
        .ketentuan td { vertical-align: top; text-align: justify; padding-bottom: 3px; }

        /* PENGANTAR ELEMENT */
        .pengantar-header { text-align: right; margin-bottom: 10px; margin-top: -5px; }
        .kepada-yth { margin-left: 55%; width: 45%; margin-bottom: 20px; font-size: 10pt; }
        
        .table-grid { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table-grid th, .table-grid td { border: 1px solid #000; padding: 5px 8px; vertical-align: top; }
        .table-grid th { background-color: #e0e0e0; text-align: center; font-weight: bold; text-transform: uppercase; font-size: 10pt; }
        .table-grid td { font-size: 10pt; }

        /* --- STYLING TANDA TANGAN & QR CODE (UPDATE) --- */
        .ttd-container {
            width: 100%;
            margin-top: 20px; 
            display: flex;
            justify-content: space-between; /* Memisahkan QR ke kiri dan TTD ke kanan */
            align-items: flex-end; /* Meratakan elemen di garis bawah */
        }
        
        .ttd-box { 
            width: 58%; /* Diperbesar sesuai permintaan sebelumnya */ 
            text-align: center; 
            position: relative; 
        }
        .ttd-img { width: 100%; height: auto; margin: -5px 0; } 
        
        /* QR Code Style Baru */
        .qr-validation {
            text-align: left;    /* Rata kiri */
            width: auto;         /* Lebar otomatis */
            margin-left: 0;      /* Mepet kiri */
            margin-bottom: 25px; /* Mengangkat posisi QR Code agar 'agak naik' */
        }
        .qr-validation img { width: 65px; height: 65px; }
        .qr-text { font-size: 8px; font-family: Arial, sans-serif; margin-top: 2px; color: #333; }
        
        /* FOOTER SK */
        .footer-sk { margin-top: 25px; font-size: 9pt; }
        .footer-sk ol { padding-left: 15px; margin-top: 2px; }

    </style>
</head>
<body onload="window.print()">
    <div class="sheet">
        
        <div class="kop" style="border:none; padding-bottom:0;">
            <img src="/uploads/kop_dinas.png" alt="Kop Dinas">
        </div>

        <?php if($is_sk): ?>
            <div class="center sk-title bold underline"><?= $judul_surat ?></div>
            <div class="center sk-nomor">NOMOR : <?= $nomor_surat ?></div>

            <div class="justify" style="margin-bottom: 10px;">
                <?= $pembuka_sk ?>
            </div>

            <table class="table-biodata">
                <tr>
                    <td class="label-col">Nama</td>
                    <td class="sep-col">:</td>
                    <td class="bold"><?= $row['nama'] ?></td>
                </tr>
                <tr>
                    <td class="label-col">NIP</td>
                    <td class="sep-col">:</td>
                    <td><?= $row['nip'] ?></td>
                </tr>
                <tr>
                    <td class="label-col">Pangkat/Golongan ruang</td>
                    <td class="sep-col">:</td>
                    <td><?= $row['pangkat'] ?></td>
                </tr>
                <tr>
                    <td class="label-col">Jabatan</td>
                    <td class="sep-col">:</td>
                    <td><?= $row['jabatan'] ?></td>
                </tr>
                <tr>
                    <td class="label-col">Tempat Tugas</td>
                    <td class="sep-col">:</td>
                    <td><?= $row['sekolah'] ?></td>
                </tr>
                <tr>
                    <td class="label-col">Satuan Organisasi</td>
                    <td class="sep-col">:</td>
                    <td>Dinas Pendidikan Kepemudaan Dan Olahraga Kota Denpasar.</td>
                </tr>
            </table>

            <div class="justify">
                Terhitung mulai tanggal <b><?= tgl_indo($row['tgl_mulai']) ?></b> sampai dengan <b><?= tgl_indo($row['tgl_selesai']) ?></b> selama <b><?= $row['durasi'] ?> (<?= strtolower(terbilang($row['durasi'])) ?>) hari</b> dengan ketentuan sebagai berikut:
            </div>

            <table class="ketentuan">
                <tr>
                    <td width="20">a.</td>
                    <td>Sebelum menjalankan <?= $jenis ?>, wajib menyerahkan pekerjaannya kepada atasan langsungnya atau pejabat lain yang ditunjuk.</td>
                </tr>
                <tr>
                    <td width="20">b.</td>
                    <td>Setelah melaksanakan <?= $jenis ?>, wajib melaporkan diri kepada atasan langsungnya dan bekerja kembali sebagaimana biasa.</td>
                </tr>
            </table>

            <p class="justify" style="margin-top: 10px;">
                Demikian <?= ucwords(strtolower($judul_surat)) ?> ini dibuat untuk dapat dipergunakan sebagaimana mestinya.
            </p>

            <div class="ttd-container">
                <div class="qr-validation">
                    <img src="<?= $qr_api ?>">
                    <div class="qr-text">
                        Validasi Dokumen<br>
                        <b><?= $tgl_surat_indo ?></b>
                    </div>
                </div>

                <div class="ttd-box">
                    <div style="margin-bottom: 2px;">&nbsp;&nbsp;&nbsp;Denpasar, <?= $tgl_surat_indo ?></div>
                    <img src="/uploads/kadis2.png" class="ttd-img" alt="TTD Kepala Dinas">
                </div>
            </div>

            <div class="footer-sk">
                <b>Tembusan:</b>
                <ol>
                    <li>Kepala <?= $row['sekolah'] ?></li>
                    <li>Yang Bersangkutan.</li>
                    <li>Arsip.</li>
                </ol>
            </div>

        <?php else: ?>
            <div class="pengantar-header">
                Denpasar, <?= $tgl_surat_indo ?>
            </div>

            <div class="kepada-yth">
                Kepada<br>
                Yth. Walikota Denpasar<br>
                Cq. Kepala BKPSDM Kota Denpasar<br>
                di -<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Denpasar
            </div>

            <div class="center sk-title underline" style="margin-top:0;">SURAT PENGANTAR</div>
            <div class="center" style="font-size:11pt; margin-bottom: 10px;">Nomor : <?= $nomor_surat ?></div>

            <table class="table-grid">
                <thead>
                    <tr>
                        <th style="width: 5%;">NO</th>
                        <th style="width: 45%;">JENIS SURAT YANG DIKIRIM</th>
                        <th style="width: 15%;">BANYAKNYA</th>
                        <th style="width: 35%;">KETERANGAN</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="center">1</td>
                        <td>
                            Permohonan <?= $row['jenis_cuti'] ?> <br>
                            atas nama :<br><br>
                            <b><?= $row['nama'] ?></b><br>
                            NIP. <?= $row['nip'] ?><br>
                            <?= $row['sekolah'] ?>
                        </td>
                        <td class="center">
                            1 ( satu )<br>
                            gabung
                        </td>
                        <td class="justify">
                            Dengan hormat, bersama ini kami kirim untuk dapat diproses lebih lanjut.
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="ttd-container" style="margin-top: 40px;">
                <div class="qr-validation">
                    <img src="<?= $qr_api ?>">
                    <div class="qr-text">
                        Validasi Dokumen<br>
                        <b><?= $tgl_surat_indo ?></b>
                    </div>
                </div>

                <div class="ttd-box">
                    <img src="/uploads/kadis2.png" class="ttd-img" alt="TTD Kepala Dinas">
                </div>
            </div>

        <?php endif; ?>

    </div>
</body>
</html>