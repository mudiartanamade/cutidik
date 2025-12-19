<?php
require_once 'config/database.php';

// --- FUNGSI BANTUAN TANGGAL INDO ---
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

$valid = false;
$data = null;
$nomor_surat_full = "-";

if(isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Cari data berdasarkan Token
    $query = "SELECT pc.*, p.nama, p.nip, p.sekolah 
              FROM pengajuan_cuti pc 
              JOIN pegawai p ON pc.pegawai_id = p.id 
              WHERE pc.token = ? AND pc.status = 'disetujui'";
    $stmt = $conn->prepare($query);
    $stmt->execute([$token]);
    $data = $stmt->fetch();
    
    if($data) {
        $valid = true;

        // --- LOGIKA PENYUSUNAN NOMOR SURAT ---
        // Menyesuaikan prefix berdasarkan jenis cuti (Sama seperti di modal persetujuan)
        $prefix = "000.1.12.7 / "; // Default/Pengantar
        if($data['jenis_cuti'] == 'Cuti Tahunan') {
            $prefix = "800.1.11.4 / ";
        } elseif($data['jenis_cuti'] == 'Cuti Melahirkan') {
            $prefix = "800.1.11.5 / ";
        } elseif($data['jenis_cuti'] == 'Cuti Sakit') {
            $prefix = "800.1.11.6 / ";
        }

        // Gabungkan: Prefix + Nomor Input + Suffix Dinas
        $nomor_input = $data['nomor_surat_input'] ?? '...';
        $nomor_surat_full = $prefix . $nomor_input . " / Disdikpora";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validasi Surat Cuti</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-200">
        <div class="bg-[#0f172a] p-6 text-center">
            <div class="w-16 h-16 bg-white rounded-full mx-auto flex items-center justify-center mb-3 shadow-lg">
                <img src="uploads/logo_dps.png" class="w-12" alt="Logo">
            </div>
            <h1 class="text-white font-bold text-lg">E-CUTI DISDIKPORA</h1>
            <p class="text-slate-400 text-xs uppercase tracking-widest">Verifikasi Dokumen Elektronik</p>
        </div>

        <div class="p-8">
            <?php if($valid): ?>
                <div class="text-center mb-6">
                    <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-4xl mx-auto mb-4 animate-bounce">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-800">DOKUMEN VALID</h2>
                    <p class="text-slate-500 text-sm mt-1">Surat Cuti ini Asli dan Terdaftar.</p>
                </div>

                <div class="space-y-4 border-t border-slate-100 pt-6">
                    
                    <div>
                        <p class="text-xs text-slate-400 uppercase font-bold">Nomor Surat</p>
                        <p class="text-slate-800 font-medium text-lg"><?= $nomor_surat_full ?></p>
                    </div>

                    <div>
                        <p class="text-xs text-slate-400 uppercase font-bold">Nama Lengkap</p>
                        <p class="text-slate-800 font-semibold text-lg"><?= $data['nama'] ?></p>
                        <p class="text-slate-500 text-sm"><?= $data['nip'] ?></p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-slate-400 uppercase font-bold">Jenis Cuti</p>
                            <p class="text-slate-800 font-medium"><?= $data['jenis_cuti'] ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 uppercase font-bold">Durasi</p>
                            <p class="text-slate-800 font-medium"><?= $data['durasi'] ?> Hari</p>
                        </div>
                    </div>

                    <div>
                        <p class="text-xs text-slate-400 uppercase font-bold">Tanggal Cuti</p>
                        <p class="text-slate-800 font-medium">
                            <?= tgl_indo($data['tgl_mulai']) ?> s/d <?= tgl_indo($data['tgl_selesai']) ?>
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-slate-400 uppercase font-bold">Unit Kerja</p>
                        <p class="text-slate-800 font-medium"><?= $data['sekolah'] ?></p>
                    </div>
                    
                    <div>
                        <p class="text-xs text-slate-400 uppercase font-bold">Tanggal Validasi Surat</p>
                        <p class="text-slate-800 font-medium"><?= tgl_indo($data['tgl_disetujui']) ?></p>
                    </div>
                    
                    <div class="pt-4 border-t border-dashed border-slate-200 mt-4">
                        <div class="mb-4">
                            <p class="text-xs text-slate-400 uppercase font-bold">Pejabat Penandatangan</p>
                            <p class="text-slate-800 font-medium">Kepala Dinas Pendidikan Kepemudaan dan Olahraga Kota Denpasar</p>
                        </div>

                        <div>
                            <p class="text-xs text-slate-400 uppercase font-bold">Nama/NIP Pejabat</p>
                            <p class="text-slate-800 font-medium">Drs. Anak Agung Gede Wiratama, M.Ag.</p>
                            <p class="text-slate-500 text-sm">19680404 199403 1 016</p>
                        </div>
                    </div>
                    
                </div>

            <?php else: ?>
                <div class="text-center py-8">
                    <div class="w-20 h-20 bg-red-100 text-red-600 rounded-full flex items-center justify-center text-4xl mx-auto mb-4">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-800">TIDAK DITEMUKAN</h2>
                    <p class="text-slate-500 text-sm mt-2">Dokumen tidak terdaftar di database kami atau link verifikasi salah.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-slate-50 p-4 text-center text-[10px] text-slate-400 border-t border-slate-100">
            &copy; 2026 Dinas Pendidikan Kepemudaan dan Olahraga
        </div>
    </div>

</body>
</html>