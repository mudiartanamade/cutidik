<?php
// Proteksi: Hanya Admin Sekolah
if($_SESSION['role'] != 'admin_sekolah') { 
    echo "<script>window.location='index.php';</script>"; 
    exit; 
}

// Tambahkan CDN SweetAlert2
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

// CATATAN: Fungsi tgl_indo() dihapus dari sini karena sudah ada di config/database.php
// agar tidak terjadi bentrok (Fatal Error: Cannot redeclare tgl_indo)

// --- BAGIAN 1: LOGIKA PROSES BACKEND ---

// A. BATALKAN PENGAJUAN
// Menghapus data pengajuan yang masih berstatus 'pending'
if(isset($_POST['batalkan_pengajuan'])) {
    $id_batal = $_POST['id_pengajuan'];
    
    // Cek file bukti lama untuk dihapus
    $cek = $conn->prepare("SELECT file_bukti FROM pengajuan_cuti WHERE id = ?");
    $cek->execute([$id_batal]);
    $d = $cek->fetch();
    
    if($d && $d['file_bukti'] && file_exists("uploads/".$d['file_bukti'])) {
        unlink("uploads/".$d['file_bukti']);
    }
    
    // Hapus record database
    $del = $conn->prepare("DELETE FROM pengajuan_cuti WHERE id = ? AND status = 'pending'");
    $del->execute([$id_batal]);
    
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire('Dibatalkan', 'Pengajuan cuti berhasil dibatalkan dan dihapus.', 'success')
            .then(() => { window.location='?page=pengajuan&tab=riwayat'; });
        });
    </script>";
}

// B. UPDATE REVISI (Fitur Edit Ajuan Ditolak)
// Memperbarui data kontak/berkas tanpa mengubah data pokok (NIP, Nama, Tanggal)
if(isset($_POST['update_pengajuan'])) {
    $id_edit = $_POST['id_edit'];
    
    // Data yang diizinkan diedit
    $alamat_baru = $_POST['alamat_cuti'];
    $wa_baru     = $_POST['no_wa'];
    $alasan_baru = $_POST['alasan'];
    
    $upload_ok = true;
    $file_name = null;
    
    // Cek jika ada upload file baru
    if(isset($_FILES['bukti_baru']) && $_FILES['bukti_baru']['size'] > 0) {
        $allowed = ['jpg','jpeg','png','pdf'];
        $ext = strtolower(pathinfo($_FILES['bukti_baru']['name'], PATHINFO_EXTENSION));
        $size = $_FILES['bukti_baru']['size'];
        
        if(!in_array($ext, $allowed)) {
             echo "<script>document.addEventListener('DOMContentLoaded', () => Swal.fire('Format Salah', 'File bukti harus PDF atau Gambar (JPG/PNG).', 'error'));</script>"; 
             $upload_ok = false;
        } elseif($size > 1048576) { 
     echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'File Terlalu Besar',
                text: 'File revisi maksimal 1MB. Silakan pilih kembali file yang lebih kecil.',
                icon: 'error',
                confirmButtonText: 'Paham'
            });
        });
     </script>"; 
     $upload_ok = false;
} else {
            $target_dir = "uploads/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            
            $file_name = time() . "_rev_" . rand(100,999) . "." . $ext;
            move_uploaded_file($_FILES['bukti_baru']['tmp_name'], $target_dir . $file_name);
            
            // Hapus file lama jika ada
            $file_lama = $_POST['file_lama'];
            if($file_lama && file_exists($target_dir.$file_lama)) {
                unlink($target_dir.$file_lama);
            }
        }
    } else {
        // Jika tidak upload baru, gunakan file lama
        $file_name = $_POST['file_lama'];
    }

    if($upload_ok) {
        // Update database: Status dikembalikan ke 'pending', catatan perbaikan dihapus
        $sql = "UPDATE pengajuan_cuti SET 
                alamat_cuti = ?, 
                no_wa = ?, 
                alasan = ?, 
                file_bukti = ?,
                status = 'pending', 
                catatan_perbaikan = NULL 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$alamat_baru, $wa_baru, $alasan_baru, $file_name, $id_edit]);
        
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire('Revisi Terkirim', 'Data telah diperbarui dan status kembali Pending untuk diverifikasi Dinas.', 'success')
                .then(() => { window.location='?page=pengajuan&tab=riwayat'; });
            });
        </script>";
    }
}

// C. SUBMIT PENGAJUAN BARU
if(isset($_POST['ajukan_cuti'])){
    $pegawai_id = $_POST['pegawai_id'];
    $jenis      = $_POST['jenis_cuti'];
    $alasan     = $_POST['alasan'];
    
    // Input Tambahan
    $alamat_cuti = $_POST['alamat_cuti'];
    $no_wa       = $_POST['no_wa'];
    
    $start      = $_POST['tgl_mulai'];
    $end        = $_POST['tgl_selesai'];
    
    $start_date = new DateTime($start);
    $end_date   = new DateTime($end);
    
    // --- KONFIGURASI ---
    
    // 1. Tahun Aplikasi
    $stmt_app = $conn->query("SELECT setting_value FROM app_settings WHERE setting_key='tahun_aplikasi'");
    $row_app = $stmt_app->fetch();
    $tahun_ini = $row_app ? $row_app['setting_value'] : date('Y');

    // 2. Hari Kerja Sekolah
    $sekolah = $_SESSION['nama_sekolah'];
    $q_sek = $conn->prepare("SELECT hari_belajar FROM data_sekolah WHERE nama_sekolah = ?");
    $q_sek->execute([$sekolah]);
    $d_sek = $q_sek->fetch();
    $hari_kerja_sekolah = $d_sek ? $d_sek['hari_belajar'] : '6 Hari'; 

    // 3. Libur Nasional
    $libur_nasional = [];
    $q_lib = $conn->query("SELECT tanggal FROM hari_libur");
    while($l = $q_lib->fetch()) { $libur_nasional[] = $l['tanggal']; }

    // VALIDASI TANGGAL DASAR
    if($start_date > $end_date) {
        echo "<script>document.addEventListener('DOMContentLoaded', () => Swal.fire('Tanggal Salah', 'Tanggal Selesai tidak boleh sebelum Tanggal Mulai.', 'error'));</script>";
    } else {
        // HITUNG DURASI EFEKTIF (Skip Sabtu/Minggu/Libur)
        $durasi_efektif = 0;
        $period = new DatePeriod($start_date, new DateInterval('P1D'), $end_date->modify('+1 day'));
        
        foreach($period as $dt) {
            $curr = $dt->format('Y-m-d');
            $dayW = $dt->format('w'); // 0=Minggu, 6=Sabtu
            
            if($dayW == 0) continue; 
            if($dayW == 6 && $hari_kerja_sekolah == '5 Hari') continue; 
            if(in_array($curr, $libur_nasional)) continue; 
            
            $durasi_efektif++;
        }
        
        if($durasi_efektif <= 0) {
            echo "<script>document.addEventListener('DOMContentLoaded', () => Swal.fire('Durasi Kosong', 'Rentang tanggal yang dipilih jatuh pada hari libur semua.', 'warning'));</script>";
        } else {
            // PROSES UPLOAD BUKTI
            $file_name = null;
            $upload_ok = true;
            
            if($jenis != 'Cuti Tahunan') {
                if(isset($_FILES['bukti']) && $_FILES['bukti']['error'] == 0) {
                    $allowed = ['jpg','jpeg','png','pdf'];
                    $ext = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
                    $size = $_FILES['bukti']['size'];
                    
                    if(!in_array($ext, $allowed)) {
                        echo "<script>document.addEventListener('DOMContentLoaded', () => Swal.fire('Format Salah', 'File bukti harus PDF atau Gambar.', 'error'));</script>"; 
                        $upload_ok = false;
                    } elseif($size > 1048576) { 
    // Kita simpan pesan error, tapi tidak melakukan redirect agar isian form di $_POST tetap ada
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Ukuran Besar',
                text: 'File maksimal 1MB. Data isian Anda tetap tersimpan, silakan pilih file yang lebih kecil.',
                icon: 'error',
                confirmButtonText: 'Perbaiki File'
            });
        });
    </script>"; 
    $upload_ok = false;
} else {
                        $target_dir = "uploads/";
                        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
                        $file_name = time() . "_" . rand(100,999) . "." . $ext;
                        move_uploaded_file($_FILES['bukti']['tmp_name'], $target_dir . $file_name);
                    }
                } else {
                    echo "<script>document.addEventListener('DOMContentLoaded', () => Swal.fire('Bukti Kurang', 'Jenis cuti ini wajib melampirkan bukti dokumen.', 'error'));</script>"; 
                    $upload_ok = false;
                }
            }
            
            if($upload_ok) {
                // VALIDASI KUOTA (Walking Balance)
                $lanjut_simpan = true;
                if($jenis == 'Cuti Tahunan') {
                    // 1. Ambil Setting Tahun Basis
                    $stmt_basis = $conn->query("SELECT setting_value FROM app_settings WHERE setting_key='tahun_saldo_awal'");
                    $row_basis = $stmt_basis->fetch();
                    $tahun_basis = $row_basis ? $row_basis['setting_value'] : ($tahun_ini - 1);

                    // 2. Ambil Data Pegawai
                    $q_data_peg = $conn->prepare("SELECT sisa_cuti_thn_lalu FROM pegawai WHERE id = ?");
                    $q_data_peg->execute([$pegawai_id]);
                    $d_peg = $q_data_peg->fetch();

                    // 3. Cache Cuti Bersama
                    $list_cb = [];
                    $q_cb_all = $conn->query("SELECT tahun, jml_cuti_bersama FROM pengaturan_cuti");
                    while($rcb = $q_cb_all->fetch()) { $list_cb[$rcb['tahun']] = $rcb['jml_cuti_bersama']; }

                    // 4. Hitung Saldo Berjalan
                    $saldo_berjalan = $d_peg['sisa_cuti_thn_lalu'];
                    $sisa_real = 0;

                    if ($tahun_ini == $tahun_basis) {
                        $hak_murni = 12 - ($list_cb[$tahun_ini] ?? 0);
                        $q_used = $conn->prepare("SELECT SUM(durasi) as total FROM pengajuan_cuti WHERE pegawai_id = ? AND YEAR(tgl_mulai) = ? AND status = 'disetujui' AND jenis_cuti = 'Cuti Tahunan'");
                        $q_used->execute([$pegawai_id, $tahun_ini]);
                        $used = $q_used->fetch()['total'] ?? 0;
                        $sisa_real = ($d_peg['sisa_cuti_thn_lalu'] + $hak_murni) - $used;
                    } else {
                        for ($y = ($tahun_basis + 1); $y <= $tahun_ini; $y++) {
                            $carry_over = ($saldo_berjalan > 6) ? 6 : $saldo_berjalan;
                            $hak_murni  = 12 - ($list_cb[$y] ?? 0);
                            $total_hak  = $carry_over + $hak_murni;
                            
                            $q_used = $conn->prepare("SELECT SUM(durasi) as total FROM pengajuan_cuti WHERE pegawai_id = ? AND YEAR(tgl_mulai) = ? AND status = 'disetujui' AND jenis_cuti = 'Cuti Tahunan'");
                            $q_used->execute([$pegawai_id, $y]);
                            $used = $q_used->fetch()['total'] ?? 0;
                            
                            $saldo_berjalan = $total_hak - $used;
                        }
                        $sisa_real = $saldo_berjalan;
                    }
                    
                    if($sisa_real < $durasi_efektif) {
                        echo "<script>document.addEventListener('DOMContentLoaded', () => Swal.fire('Kuota Tidak Cukup', 'Sisa cuti Guru-KS: $sisa_real hari. Pengajuan: $durasi_efektif hari.', 'error'));</script>";
                        $lanjut_simpan = false;
                    }
                }
                
                if($lanjut_simpan) {
                    // INSERT DATA
                    $sql = "INSERT INTO pengajuan_cuti (pegawai_id, jenis_cuti, alasan, alamat_cuti, no_wa, tgl_mulai, tgl_selesai, durasi, file_bukti, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$pegawai_id, $jenis, $alasan, $alamat_cuti, $no_wa, $start, $end, $durasi_efektif, $file_name]);
                    
                    echo "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            Swal.fire({
                                title: 'Berhasil!',
                                text: 'Pengajuan cuti berhasil dikirim ke Dinas.',
                                icon: 'success'
                            }).then(() => { window.location='?page=pengajuan&tab=riwayat'; });
                        });
                    </script>";
                }
            }
        }
    }
}

// --- BAGIAN 2: PERSIAPAN DATA (VIEW) ---

$sekolah = $_SESSION['nama_sekolah'];

// List Pegawai Sekolah
$q_peg = $conn->prepare("SELECT * FROM pegawai WHERE sekolah = ? AND status_aktif='aktif' ORDER BY nama ASC");
$q_peg->execute([$sekolah]);
$list_pegawai = $q_peg->fetchAll();

// Jenis Cuti
$jenis_opsi = $conn->query("SELECT nama_cuti FROM jenis_cuti WHERE status = '1'")->fetchAll();

// Info Hari Kerja (Untuk Info Box)
$q_sek = $conn->prepare("SELECT hari_belajar FROM data_sekolah WHERE nama_sekolah = ?");
$q_sek->execute([$sekolah]);
$d_sek = $q_sek->fetch();
$hari_kerja_sekolah_info = $d_sek ? $d_sek['hari_belajar'] : '6 Hari'; 

// Paginasi & Pencarian
$batas   = 10; 
$halaman = isset($_GET['hal']) ? (int)$_GET['hal'] : 1;
$halaman_awal = ($halaman > 1) ? ($halaman * $batas) - $batas : 0;
$cari    = isset($_GET['cari']) ? $_GET['cari'] : "";

// Query Data Riwayat
$sql_data = "SELECT pc.*, p.nama, p.nip FROM pengajuan_cuti pc JOIN pegawai p ON pc.pegawai_id = p.id WHERE p.sekolah = ?";
$params_data = [$sekolah];
if(!empty($cari)) {
    $sql_data .= " AND p.nama LIKE ?";
    $params_data[] = "%$cari%";
}
$sql_data .= " ORDER BY pc.id DESC LIMIT $halaman_awal, $batas";
$stmt_data = $conn->prepare($sql_data);
$stmt_data->execute($params_data);
$riwayat = $stmt_data->fetchAll();

// Hitung Total Data
$sql_count = "SELECT COUNT(*) FROM pengajuan_cuti pc JOIN pegawai p ON pc.pegawai_id = p.id WHERE p.sekolah = ?";
$params_count = [$sekolah];
if(!empty($cari)) {
    $sql_count .= " AND p.nama LIKE ?";
    $params_count[] = "%$cari%";
}
$stmt_count = $conn->prepare($sql_count);
$stmt_count->execute($params_count);
$jumlah_data = $stmt_count->fetchColumn();
$total_halaman = ceil($jumlah_data / $batas);
?>

<div class="space-y-6">
    <!-- HEADER -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-col md:flex-row justify-between items-center gap-4">
        <div>
            <h3 class="font-bold text-xl text-slate-800">Administrasi Cuti Sekolah</h3>
            <p class="text-sm text-slate-400">Ajukan cuti Guru-KS dan pantau status persetujuan dari Dinas.</p>
        </div>
        
        <div class="flex bg-slate-100 p-1 rounded-xl">
            <button onclick="switchTab('form')" id="btn-form" class="px-6 py-2 rounded-lg text-sm font-bold transition-all bg-white text-blue-600 shadow-sm">
                <i class="fa-solid fa-pen-to-square mr-2"></i> Formulir
            </button>
            <button onclick="switchTab('riwayat')" id="btn-riwayat" class="px-6 py-2 rounded-lg text-sm font-bold transition-all text-slate-500 hover:text-slate-700">
                <i class="fa-solid fa-clock-rotate-left mr-2"></i> Riwayat
            </button>
        </div>
    </div>

    <!-- TAB FORMULIR PENGAJUAN -->
    <div id="tab-form" class="block animate-[fadeIn_0.3s_ease-out]">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-2">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                    <div class="border-b border-slate-100 pb-4 mb-6">
                        <h3 class="font-bold text-slate-800 text-lg">Isi Formulir Pengajuan</h3>
                        <p class="text-xs text-slate-400">Pastikan data kontak dan alamat benar untuk keperluan administrasi surat.</p>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Nama Guru/KS</label>
                                <select name="pegawai_id" required class="w-full border border-slate-300 rounded-xl p-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 bg-slate-50/50">
                                    <option value="">-- Pilih Guru/KS --</option>
                                    <?php foreach($list_pegawai as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= $p['nama'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Jenis Cuti</label>
                                <select name="jenis_cuti" id="jenis_cuti" onchange="toggleUpload()" required class="w-full border border-slate-300 rounded-xl p-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 bg-slate-50/50">
                                    <option value="">-- Pilih Jenis --</option>
                                    <?php foreach($jenis_opsi as $j): ?>
                                        <option value="<?= $j['nama_cuti'] ?>"><?= $j['nama_cuti'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-[10px] text-slate-400 mt-1 italic">*Jika 'Cuti Tahunan', sisa kuota akan divalidasi otomatis.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 bg-blue-50/30 p-4 rounded-xl border border-blue-100">
                            <div class="md:col-span-2 text-xs font-bold text-blue-600 mb-1 flex items-center gap-2"><i class="fa-solid fa-address-book"></i> DATA KONTAK SELAMA CUTI</div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Alamat Lengkap</label>
                                <input type="text" name="alamat_cuti" required placeholder="Jalan, Desa, Kecamatan..." class="w-full border border-slate-300 rounded-xl p-3 text-sm focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">No. WhatsApp (Aktif)</label>
                                <input type="number" name="no_wa" required placeholder="08xxxxxxxx" class="w-full border border-slate-300 rounded-xl p-3 text-sm focus:border-blue-500 outline-none">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Mulai Tanggal</label>
                                <input type="date" name="tgl_mulai" required class="w-full border border-slate-300 rounded-xl p-3 text-sm focus:border-blue-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Sampai Tanggal</label>
                                <input type="date" name="tgl_selesai" required class="w-full border border-slate-300 rounded-xl p-3 text-sm focus:border-blue-500 outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1.5">Alasan Pengajuan</label>
                            <textarea name="alasan" rows="3" required class="w-full border border-slate-300 rounded-xl p-3 text-sm focus:border-blue-500 placeholder-slate-400" placeholder="Jelaskan alasan pengajuan cuti secara singkat..."></textarea>
                        </div>

                        <div id="upload_area" class="hidden p-4 bg-yellow-50 border border-yellow-200 rounded-xl border-dashed">
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Upload Bukti Pendukung (Wajib)</label>
                            <input type="file" name="bukti" id="input_bukti" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-yellow-100 file:text-yellow-700 hover:file:bg-yellow-200 transition">
                            <p class="text-[10px] text-slate-500 mt-2"><i class="fa-solid fa-circle-exclamation mr-1"></i> Surat Dokter, Surat Keterangan, dll. (Max 1MB)</p>
                        </div>

                        <div class="pt-2">
                            <button type="submit" name="ajukan_cuti" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-blue-500/30 transition flex items-center justify-center gap-2">
                                <i class="fa-solid fa-paper-plane"></i> Kirim Pengajuan ke Dinas
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- INFO BOX BIRU -->
            <div class="md:col-span-1 space-y-4">
                <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-white opacity-10 rounded-full blur-2xl"></div>
                    <div class="absolute bottom-0 left-0 -ml-8 -mb-8 w-24 h-24 bg-white opacity-10 rounded-full blur-2xl"></div>

                    <h4 class="font-bold mb-4 flex items-center gap-2 text-lg border-b border-white/20 pb-3">
                        <i class="fa-solid fa-circle-info text-yellow-300"></i> Info Penting
                    </h4>
                    
                    <div class="text-sm space-y-4 opacity-95">
                        <div class="flex gap-3">
                            <i class="fa-regular fa-calendar-check mt-1 text-blue-200"></i>
                            <div>
                                <span class="font-bold block text-blue-100 text-xs uppercase mb-1">Perhitungan Hari</span>
                                <?php if($hari_kerja_sekolah_info == '5 Hari'): ?>
                                    Hari Sabtu, Minggu, dan Libur Nasional <b>tidak dihitung</b> dalam durasi cuti.
                                <?php else: ?>
                                    Hari Minggu dan Libur Nasional <b>tidak dihitung</b> dalam durasi cuti.
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <i class="fa-solid fa-school mt-1 text-blue-200"></i>
                            <div>
                                <span class="font-bold block text-blue-100 text-xs uppercase mb-1">Jam Kerja</span>
                                Sekolah Anda menerapkan sistem kerja <b><?= $hari_kerja_sekolah_info ?></b>.
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <i class="fa-brands fa-whatsapp mt-1 text-blue-200"></i>
                            <div>
                                <span class="font-bold block text-blue-100 text-xs uppercase mb-1">Kontak Darurat</span>
                                Pastikan nomor WhatsApp yang diinput adalah nomor aktif untuk koordinasi.
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <i class="fa-solid fa-file-upload mt-1 text-blue-200"></i>
                            <div>
                                <span class="font-bold block text-blue-100 text-xs uppercase mb-1">Bukti Dukung</span>
                                Selain Cuti Tahunan, <b>WAJIB</b> melampirkan file bukti (PDF/Gambar).
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <i class="fa-solid fa-book-open mt-1 text-blue-200"></i>
                            <div>
                                <span class="font-bold block text-blue-100 text-xs uppercase mb-1">Aturan Cuti</span>
                                <span class="leading-relaxed">
                                    Baca aturan cuti PNS <a href="/uploads/Cuti-PNS.pdf" target="_blank" class="underline hover:text-yellow-300 font-bold decoration-white/50 hover:decoration-yellow-300 transition">di sini</a>, 
                                    aturan cuti PNS perubahan <a href="/uploads/Cuti-PNS-perubahan.pdf" target="_blank" class="underline hover:text-yellow-300 font-bold decoration-white/50 hover:decoration-yellow-300 transition">di sini</a>, 
                                    dan aturan cuti PPPK <a href="/uploads/Cuti-P3K.pdf" target="_blank" class="underline hover:text-yellow-300 font-bold decoration-white/50 hover:decoration-yellow-300 transition">di sini</a>.
                                </span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB RIWAYAT PENGAJUAN -->
    <div id="tab-riwayat" class="hidden animate-[fadeIn_0.3s_ease-out]">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            
            <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="text-sm text-slate-500">
                    Total: <b><?= $jumlah_data ?></b> Pengajuan.
                </div>
                <form action="" method="GET" class="relative w-full md:w-64">
                    <input type="hidden" name="page" value="pengajuan">
                    <input type="hidden" name="tab" value="riwayat">
                    <span class="absolute left-3 top-2.5 text-slate-400"><i class="fa-solid fa-search"></i></span>
                    <input type="text" name="cari" value="<?= $cari ?>" placeholder="Cari nama Guru/KS..." class="w-full pl-9 pr-4 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-blue-500 transition">
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-slate-600">
                    <thead class="text-xs text-slate-500 uppercase bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-4 font-semibold w-1/4">Nama Guru-KS</th>
                            <th class="px-6 py-4 font-semibold">Jenis & Tanggal</th>
                            <th class="px-6 py-4 font-semibold text-center">Bukti</th>
                            <th class="px-6 py-4 font-semibold text-center">Status</th>
                            <th class="px-6 py-4 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if(count($riwayat) == 0): ?>
                            <tr><td colspan="4" class="p-8 text-center text-slate-400 italic">Data pengajuan tidak ditemukan.</td></tr>
                        <?php endif; ?>

                        <?php foreach($riwayat as $row): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <!-- KOLOM 1: PEGAWAI -->
                            <td class="px-6 py-4 valign-top">
                                <div class="font-bold text-slate-800 text-sm"><?= $row['nama'] ?></div>
                                <div class="text-xs text-slate-500 font-mono mt-0.5">NIP. <?= $row['nip'] ?></div>
                                <?php if(!empty($row['alasan'])): ?>
                                    <div class="mt-2 text-[11px] text-slate-500 italic bg-slate-100 p-2 rounded border border-slate-200">
                                        "<?= $row['alasan'] ?>"
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- KOLOM 2: JENIS & TANGGAL -->
                            <td class="px-6 py-4 valign-top">
                                <div class="text-xs font-bold text-slate-700 uppercase bg-slate-100 inline-block px-2 py-0.5 rounded mb-1"><?= $row['jenis_cuti'] ?></div>
                                
                                <div class="text-xs text-slate-600 flex flex-col gap-1 mt-1">
                                    <!-- Jika disetujui, tampilkan tanggal SK. Jika tidak, tampilkan range pengajuan -->
                                    <?php if($row['status'] == 'disetujui' && !empty($row['tgl_disetujui'])): ?>
                                        <span class="text-green-600 font-bold"><i class="fa-solid fa-check-circle"></i> Disetujui: <?= tgl_indo($row['tgl_disetujui']) ?></span>
                                        <span class="text-[10px] text-slate-400">Ajuan: <?= tgl_indo($row['tgl_mulai']) ?> - <?= tgl_indo($row['tgl_selesai']) ?></span>
                                    <?php else: ?>
                                        <span><i class="fa-regular fa-calendar"></i> <?= tgl_indo($row['tgl_mulai']) ?> - <?= tgl_indo($row['tgl_selesai']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs font-bold text-blue-600 mt-1 pl-0">
                                    <?= $row['durasi'] ?> Hari Kerja
                                </div>
                            </td>
                            
                            </td>

                            <td class="px-6 py-4 text-center">
    <?php 
    if (!empty($row['file_bukti'])) {
        $path = "uploads/" . $row['file_bukti'];
        if (file_exists($path)) {
            echo '<a href="'.$path.'" target="_blank" class="text-blue-600 font-bold bg-blue-50 px-2 py-1 rounded border border-blue-100">
                    <i class="fa-solid fa-eye"></i>
                  </a>';
        } else {
            echo '<i class="fa-solid fa-trash-can text-slate-300" title="File sudah dibersihkan sistem"></i>';
        }
    } else { echo '-'; }
    ?>
</td>
                            
                            <!-- KOLOM 3: STATUS -->
                            <td class="px-6 py-4 text-center valign-top">
                                <?php 
                                if($row['status']=='disetujui') {
                                    echo '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold bg-green-100 text-green-700 border border-green-200"><i class="fa-solid fa-check"></i> Disetujui</span>';
                                }
                                elseif($row['status']=='ditolak') {
                                    echo '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold bg-red-100 text-red-700 border border-red-200"><i class="fa-solid fa-xmark"></i> Ditolak</span>';
                                    if(!empty($row['catatan_perbaikan'])) {
                                        echo '<div class="mt-2 text-[10px] text-red-600 italic bg-red-50 p-1.5 rounded border border-red-100 text-left">
                                                <i class="fa-solid fa-circle-exclamation mr-1"></i> '.$row['catatan_perbaikan'].'
                                              </div>';
                                    }
                                }
                                else {
                                    echo '<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold bg-yellow-100 text-yellow-700 border border-yellow-200 animate-pulse"><i class="fa-regular fa-clock"></i> Pending</span>';
                                }
                                ?>
                            
                            <!-- KOLOM 4: AKSI -->
                            <td class="px-6 py-4 text-right valign-top">
                                <div class="flex flex-col items-end gap-2">
                                    <?php if($row['status']=='disetujui'): ?>
                                        <a href="print_sk.php?token=<?= $row['token'] ?>" target="_blank" class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-800 font-bold text-xs bg-indigo-50 px-3 py-1.5 rounded-lg border border-indigo-100 transition w-full justify-center">
                                            <i class="fa-solid fa-print"></i> Cetak Surat
                                        </a>
                                    <?php elseif($row['status']=='ditolak'): ?>
                                        <!-- TOMBOL EDIT CANGGIH (REVISI) -->
                                        <button onclick='editBerkas(<?= json_encode($row) ?>)' class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 font-bold text-xs bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100 transition w-full justify-center">
                                            <i class="fa-solid fa-pen-to-square"></i> Perbaiki
                                        </button>
                                    <?php elseif($row['status']=='pending'): ?>
                                        <button onclick="batalkan(<?= $row['id'] ?>, '<?= $row['nama'] ?>')" class="inline-flex items-center gap-2 text-red-500 hover:text-red-700 font-bold text-xs bg-red-50 px-3 py-1.5 rounded-lg border border-red-100 transition w-full justify-center">
                                            <i class="fa-solid fa-trash"></i> Batal
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if($total_halaman > 1): ?>
            <div class="p-4 border-t border-slate-100 bg-slate-50 flex justify-center gap-2">
                <?php if($halaman > 1): ?>
                    <a href="?page=pengajuan&tab=riwayat&cari=<?= $cari ?>&hal=<?= $halaman - 1 ?>" class="px-3 py-1 bg-white border border-slate-300 rounded text-sm hover:bg-slate-100 text-slate-600">Prev</a>
                <?php endif; ?>

                <?php for($x = 1; $x <= $total_halaman; $x++): ?>
                    <?php if($x == $halaman): ?>
                        <span class="px-3 py-1 bg-blue-600 text-white border border-blue-600 rounded text-sm font-bold"><?= $x ?></span>
                    <?php else: ?>
                        <a href="?page=pengajuan&tab=riwayat&cari=<?= $cari ?>&hal=<?= $x ?>" class="px-3 py-1 bg-white border border-slate-300 rounded text-sm hover:bg-slate-100 text-slate-600"><?= $x ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if($halaman < $total_halaman): ?>
                    <a href="?page=pengajuan&tab=riwayat&cari=<?= $cari ?>&hal=<?= $halaman + 1 ?>" class="px-3 py-1 bg-white border border-slate-300 rounded text-sm hover:bg-slate-100 text-slate-600">Next</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- FORM MODAL BATAL -->
<form id="formBatal" method="POST" class="hidden">
    <input type="hidden" name="batalkan_pengajuan" value="1">
    <input type="hidden" name="id_pengajuan" id="batal_id">
</form>

<!-- MODAL EDIT REVISI (FULL FORM) -->
<div id="modalEdit" class="fixed inset-0 bg-slate-900/60 z-[9999] hidden flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden animate-[fadeIn_0.2s_ease-out] max-h-[90vh] overflow-y-auto">
        <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-lg text-slate-800">Perbaiki Pengajuan (Revisi)</h3>
            <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-slate-400 hover:text-red-500"><i class="fa-solid fa-times"></i></button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="update_pengajuan" value="1">
            <input type="hidden" name="id_edit" id="edit_id">
            <input type="hidden" name="file_lama" id="edit_file_lama">
            
            <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-100 text-xs text-yellow-700 flex gap-2">
                <i class="fa-solid fa-lock mt-0.5"></i>
                <div><b>Data Terkunci:</b> Info Guru-KS, Jenis Cuti, dan Tanggal tidak dapat diubah. Silakan perbaiki data kontak, alasan, atau dokumen pendukung.</div>
            </div>

            <!-- FIELD TERKUNCI (READONLY) -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Nama Guru-KS</label>
                    <input type="text" id="view_nama" readonly class="w-full border bg-slate-100 text-slate-500 rounded-lg p-2 text-sm cursor-not-allowed font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-400 mb-1">NIP</label>
                    <input type="text" id="view_nip" readonly class="w-full border bg-slate-100 text-slate-500 rounded-lg p-2 text-sm cursor-not-allowed font-mono">
                </div>
            </div>
            
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Jenis Cuti</label>
                    <input type="text" id="view_jenis" readonly class="w-full border bg-slate-100 text-slate-500 rounded-lg p-2 text-sm cursor-not-allowed font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Mulai</label>
                    <input type="text" id="view_mulai" readonly class="w-full border bg-slate-100 text-slate-500 rounded-lg p-2 text-sm cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Selesai</label>
                    <input type="text" id="view_selesai" readonly class="w-full border bg-slate-100 text-slate-500 rounded-lg p-2 text-sm cursor-not-allowed">
                </div>
            </div>

            <!-- FIELD BISA DIEDIT -->
            <div class="border-t border-slate-100 pt-4 mt-2">
                <h4 class="text-sm font-bold text-blue-600 mb-3"><i class="fa-solid fa-pen-to-square"></i> Data yang dapat diperbaiki:</h4>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Alamat Lengkap</label>
                        <input type="text" name="alamat_cuti" id="edit_alamat" required class="w-full border border-blue-300 bg-blue-50 rounded-lg p-2 text-sm text-slate-800 focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-slate-600 mb-1">No. WhatsApp</label>
                        <input type="text" name="no_wa" id="edit_wa" required class="w-full border border-blue-300 bg-blue-50 rounded-lg p-2 text-sm text-slate-800 focus:ring-1 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-xs font-bold uppercase text-slate-600 mb-1">Alasan Pengajuan</label>
                    <textarea name="alasan" id="edit_alasan" rows="2" required class="w-full border border-blue-300 bg-blue-50 rounded-lg p-2 text-sm text-slate-800 focus:ring-1 focus:ring-blue-500"></textarea>
                </div>
                
                <div id="div_edit_file" class="bg-blue-50 p-3 rounded-lg border border-blue-200 border-dashed">
                    <label class="block text-xs font-bold uppercase text-slate-700 mb-1">Upload Bukti Baru (Opsional)</label>
                    <input type="file" name="bukti_baru" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm text-slate-500 file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200">
                    <p class="text-[10px] text-slate-500 mt-1 italic">Biarkan kosong jika tidak ingin mengubah file bukti lama.</p>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 mt-2">
                <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200 font-bold">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-bold hover:bg-blue-700 shadow-lg shadow-blue-500/30">Simpan Revisi</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Tab Switching Logic
    function switchTab(tabName) {
        document.getElementById('tab-form').classList.add('hidden');
        document.getElementById('tab-riwayat').classList.add('hidden');
        
        document.getElementById('btn-form').classList.remove('bg-white', 'text-blue-600', 'shadow-sm');
        document.getElementById('btn-form').classList.add('text-slate-500');
        document.getElementById('btn-riwayat').classList.remove('bg-white', 'text-blue-600', 'shadow-sm');
        document.getElementById('btn-riwayat').classList.add('text-slate-500');

        document.getElementById('tab-' + tabName).classList.remove('hidden');
        document.getElementById('btn-' + tabName).classList.add('bg-white', 'text-blue-600', 'shadow-sm');
        document.getElementById('btn-' + tabName).classList.remove('text-slate-500');
    }

    // Toggle Upload Field based on Leave Type
    function toggleUpload() {
        var jenis = document.getElementById("jenis_cuti").value;
        var area = document.getElementById("upload_area");
        var input = document.getElementById("input_bukti");
        
        if (jenis === "Cuti Tahunan" || jenis === "") {
            area.classList.add("hidden");
            input.required = false; 
            input.value = ""; 
        } else {
            area.classList.remove("hidden");
            input.required = true; 
        }
    }

    // Modal Batal
    function batalkan(id, nama){
        Swal.fire({
            title: 'Batalkan Pengajuan?',
            text: `Pengajuan atas nama ${nama} akan dihapus permanen.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Ya, Batalkan',
            cancelButtonText: 'Tidak'
        }).then((r)=>{
            if(r.isConfirmed){
                document.getElementById('batal_id').value=id;
                document.getElementById('formBatal').submit();
            }
        });
    }

    // Modal Edit (Revisi)
    function editBerkas(d){
        document.getElementById('edit_id').value=d.id;
        document.getElementById('edit_file_lama').value=d.file_bukti;
        
        // Set Readonly Fields
        document.getElementById('view_nama').value = d.nama;
        document.getElementById('view_nip').value = d.nip;
        document.getElementById('view_jenis').value = d.jenis_cuti;
        document.getElementById('view_mulai').value = d.tgl_mulai;
        document.getElementById('view_selesai').value = d.tgl_selesai;
        
        // Set Editable Fields
        document.getElementById('edit_alamat').value = d.alamat_cuti;
        document.getElementById('edit_wa').value = d.no_wa;
        document.getElementById('edit_alasan').value = d.alasan;
        
        // Hide upload if Cuti Tahunan (kecuali user mau nambah bukti)
        var divFile = document.getElementById('div_edit_file');
        if(d.jenis_cuti == 'Cuti Tahunan') {
            divFile.style.display = 'none'; // Opsional, bisa diubah jadi block jika ingin tetap bisa upload
        } else {
            divFile.style.display = 'block';
        }
        
        document.getElementById('modalEdit').classList.remove('hidden');
    }

    // Initialize Tab from URL
    const urlParams = new URLSearchParams(window.location.search);
    if(urlParams.get('tab') === 'riwayat') {
        switchTab('riwayat');
    }
// Validasi ukuran file sebelum submit form (Formulir Baru)
document.querySelector('form').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('input_bukti');
    const jenisCuti = document.getElementById('jenis_cuti').value;

    // Hanya cek jika bukan cuti tahunan dan ada file yang dipilih
    if (jenisCuti !== 'Cuti Tahunan' && fileInput.files.length > 0) {
        const fileSize = fileInput.files[0].size / 1024 / 1024; // Convert ke MB
        if (fileSize > 1) {
            e.preventDefault(); // Hentikan pengiriman form
            Swal.fire({
                title: 'File Terlalu Besar',
                text: 'Ukuran file maksimal adalah 1MB. Silakan kompres file Anda.',
                icon: 'error',
                confirmButtonText: 'Perbaiki File'
            });
        }
    }
});

// Validasi ukuran file untuk Modal Revisi
document.querySelector('#modalEdit form').addEventListener('submit', function(e) {
    const fileInput = this.querySelector('input[name="bukti_baru"]');
    if (fileInput.files.length > 0) {
        const fileSize = fileInput.files[0].size / 1024 / 1024;
        if (fileSize > 1) {
            e.preventDefault();
            Swal.fire({
                title: 'File Terlalu Besar',
                text: 'File revisi maksimal 1MB. Silakan pilih file yang lebih kecil.',
                icon: 'error',
                confirmButtonText: 'Perbaiki'
            });
        }
    }
});
</script>