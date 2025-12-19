<?php
// Proteksi: Hanya Admin Dinas
if($_SESSION['role'] != 'admin_dinas') { echo "<script>alert('Akses Ditolak'); window.location='?page=home';</script>"; exit; }

// --- LOGIKA BACKEND ---

// 1. Simpan Pengaturan Umum (Tahun & Cuti Bersama)
if(isset($_POST['simpan_umum'])) {
    $thn_app = $_POST['tahun_aplikasi'];
    $thn_saldo = $_POST['tahun_saldo_awal'];
    
    // Simpan ke app_settings
    $conn->prepare("REPLACE INTO app_settings (setting_key, setting_value) VALUES ('tahun_aplikasi', ?)")->execute([$thn_app]);
    $conn->prepare("REPLACE INTO app_settings (setting_key, setting_value) VALUES ('tahun_saldo_awal', ?)")->execute([$thn_saldo]);
    
    // Simpan jumlah cuti bersama ke tabel pengaturan_cuti
    $cek = $conn->prepare("SELECT id FROM pengaturan_cuti WHERE tahun = ?");
    $cek->execute([$thn_app]);
    
    if($cek->rowCount() > 0) {
        $conn->prepare("UPDATE pengaturan_cuti SET jml_cuti_bersama = ? WHERE tahun = ?")->execute([$_POST['jml_cuti_bersama'], $thn_app]);
    } else {
        $conn->prepare("INSERT INTO pengaturan_cuti (jml_cuti_bersama, tahun) VALUES (?, ?)")->execute([$_POST['jml_cuti_bersama'], $thn_app]);
    }
    
    echo "<script>alert('Pengaturan Umum Berhasil Disimpan!'); window.location='?page=pengaturan_cuti';</script>";
}

// 2. Toggle Status Jenis Cuti (Aktif/Nonaktif)
if(isset($_GET['toggle_cuti'])) {
    $id = $_GET['toggle_cuti'];
    $status = $_GET['s']; // 1 = Aktif, 0 = Nonaktif
    $conn->prepare("UPDATE jenis_cuti SET status = ? WHERE id = ?")->execute([$status, $id]);
    echo "<script>window.location='?page=pengaturan_cuti';</script>";
}

// 3. Tambah Hari Libur
if(isset($_POST['tambah_libur'])) {
    $conn->prepare("INSERT INTO hari_libur (tanggal, keterangan) VALUES (?, ?)")
         ->execute([$_POST['tanggal'], $_POST['keterangan']]);
    echo "<script>alert('Hari Libur Berhasil Ditambahkan'); window.location='?page=pengaturan_cuti';</script>";
}

// 4. Hapus Hari Libur
if(isset($_GET['hapus_libur'])) {
    $conn->prepare("DELETE FROM hari_libur WHERE id = ?")->execute([$_GET['hapus_libur']]);
    echo "<script>alert('Hari Libur Berhasil Dihapus'); window.location='?page=pengaturan_cuti';</script>";
}

// --- AMBIL DATA DARI DATABASE ---

// Ambil Konfigurasi Tahun
$q_app = $conn->query("SELECT * FROM app_settings");
$app_config = [];
while($r = $q_app->fetch()) { $app_config[$r['setting_key']] = $r['setting_value']; }

$tahun_ini = $app_config['tahun_aplikasi'] ?? date('Y'); // Default tahun sekarang jika kosong
$tahun_lalu = $app_config['tahun_saldo_awal'] ?? (date('Y') - 1);

// Ambil Jumlah Cuti Bersama untuk Tahun Aplikasi
$q_cb = $conn->prepare("SELECT jml_cuti_bersama FROM pengaturan_cuti WHERE tahun = ?");
$q_cb->execute([$tahun_ini]);
$cb = $q_cb->fetch();
$jml_cb = $cb ? $cb['jml_cuti_bersama'] : 0;

// Ambil Daftar Jenis Cuti
$list_jenis = $conn->query("SELECT * FROM jenis_cuti");

// Ambil Daftar Hari Libur (Urut tanggal terbaru)
$list_libur = $conn->query("SELECT * FROM hari_libur ORDER BY tanggal DESC");
?>

<div class="space-y-8">
    
    <!-- BAGIAN 1: PENGATURAN UMUM -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
            <div>
                <h3 class="font-bold text-lg text-slate-800">1. Pengaturan Umum Sistem</h3>
                <p class="text-xs text-slate-400">Konfigurasi tahun berjalan dan potongan cuti bersama</p>
            </div>
            <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-gear"></i>
            </div>
        </div>
        <div class="p-6">
            <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-2">Tahun Aplikasi Aktif</label>
                    <input type="number" name="tahun_aplikasi" value="<?= $tahun_ini ?>" class="w-full border-2 border-indigo-100 rounded-lg p-2.5 bg-white font-bold text-indigo-700 focus:ring-indigo-500 focus:border-indigo-500">
                    <p class="text-[10px] text-slate-400 mt-1">Tahun dasar perhitungan cuti 12 hari.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-2">Tahun Saldo Awal</label>
                    <input type="number" name="tahun_saldo_awal" value="<?= $tahun_lalu ?>" class="w-full border-2 border-orange-100 rounded-lg p-2.5 bg-white font-bold text-orange-700 focus:ring-orange-500 focus:border-orange-500">
                    <p class="text-[10px] text-slate-400 mt-1">Tahun referensi untuk sisa cuti (carry over).</p>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-2">Potongan Cuti Bersama</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="jml_cuti_bersama" value="<?= $jml_cb ?>" class="w-24 border-2 border-red-100 rounded-lg p-2.5 bg-white font-bold text-red-600 text-center focus:ring-red-500 focus:border-red-500">
                        <span class="text-sm font-bold text-slate-600">Hari</span>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1">Mengurangi hak cuti tahunan.</p>
                </div>
                <div class="md:col-span-3 text-right border-t border-slate-100 pt-4 mt-2">
                    <button type="submit" name="simpan_umum" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg shadow-lg hover:bg-blue-700 transition font-bold text-sm">
                        <i class="fa-solid fa-save mr-2"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        
        <!-- BAGIAN 2: PENGATURAN JENIS CUTI -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden h-fit">
            <div class="p-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-lg text-slate-800">2. Jenis Cuti</h3>
                    <p class="text-xs text-slate-400">Kelola jenis cuti yang tampil di form pengajuan</p>
                </div>
                <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-sm">
                    <i class="fa-solid fa-list-check"></i>
                </div>
            </div>
            <div class="p-0">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 text-slate-500 font-bold border-b">
                        <tr>
                            <th class="p-4 pl-6">Nama Cuti</th>
                            <th class="p-4 text-center">Status</th>
                            <th class="p-4 pr-6 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php while($row = $list_jenis->fetch()): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="p-4 pl-6 font-medium text-slate-700"><?= $row['nama_cuti'] ?></td>
                            <td class="p-4 text-center">
                                <?php if($row['status'] == '1'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700 border border-green-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Nonaktif
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 pr-6 text-right">
                                <?php if($row['status'] == '1'): ?>
                                    <a href="?page=pengaturan_cuti&toggle_cuti=<?= $row['id'] ?>&s=0" 
   onclick="konfirmasiAksi(event, this.href, 'Nonaktifkan Cuti?', 'Jenis cuti ini tidak akan muncul di form pengajuan.', '#ef4444')"
   class="text-red-500 hover:text-red-700 text-xs font-bold bg-red-50 px-3 py-1.5 rounded-lg border border-red-100 transition">Matikan</a>
                                <?php else: ?>
                                    <a href="?page=pengaturan_cuti&toggle_cuti=<?= $row['id'] ?>&s=1" class="text-green-600 hover:text-green-800 text-xs font-bold bg-green-50 px-3 py-1.5 rounded-lg border border-green-100 transition">Hidupkan</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- BAGIAN 3: KALENDER HARI LIBUR -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-lg text-slate-800">3. Kalender Libur</h3>
                    <p class="text-xs text-slate-400">Daftar tanggal merah (selain akhir pekan)</p>
                </div>
                <div class="w-8 h-8 bg-red-100 text-red-600 rounded-full flex items-center justify-center text-sm">
                    <i class="fa-solid fa-calendar-xmark"></i>
                </div>
            </div>
            
            <!-- Form Tambah Libur -->
            <div class="p-4 border-b border-slate-100 bg-slate-50/50">
                <form method="POST" class="flex gap-2">
                    <input type="date" name="tanggal" required class="border border-slate-300 rounded-lg p-2 text-sm flex-1 focus:ring-indigo-500 focus:border-indigo-500">
                    <input type="text" name="keterangan" required placeholder="Keterangan Libur (Misal: Nyepi)" class="border border-slate-300 rounded-lg p-2 text-sm flex-[2] focus:ring-indigo-500 focus:border-indigo-500">
                    <button type="submit" name="tambah_libur" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-bold shadow hover:bg-indigo-700 transition">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </form>
            </div>

            <!-- Tabel Daftar Libur -->
            <div class="max-h-80 overflow-y-auto custom-scrollbar">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 text-slate-500 font-bold sticky top-0 shadow-sm">
                        <tr>
                            <th class="p-3 pl-6">Tanggal</th>
                            <th class="p-3">Keterangan</th>
                            <th class="p-3 pr-6 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php while($lib = $list_libur->fetch()): ?>
                        <tr class="hover:bg-red-50/30 transition">
                            <td class="p-3 pl-6 text-slate-600 font-mono text-xs"><?= tgl_indo($lib['tanggal']) ?></td>
                            <td class="p-3 font-medium text-slate-800"><?= $lib['keterangan'] ?></td>
                            <td class="p-3 pr-6 text-right">
                                <a href="?page=pengaturan_cuti&hapus_libur=<?= $lib['id'] ?>" 
   onclick="konfirmasiHapus(event, this.href, 'Data tanggal merah ini akan dihapus dari kalender.')" 
   class="text-red-400 hover:text-red-600 transition p-2 rounded-full hover:bg-red-50">
    <i class="fa-solid fa-trash"></i>
</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if($list_libur->rowCount() == 0): ?>
                            <tr><td colspan="3" class="p-6 text-center text-slate-400 text-xs italic">Belum ada data hari libur.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>