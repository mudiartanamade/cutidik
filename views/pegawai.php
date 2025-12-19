<?php
// --- CONFIGURASI TAHUN & SETTING ---
$q_app = $conn->query("SELECT * FROM app_settings");
$app_config = [];
while($r = $q_app->fetch()) { $app_config[$r['setting_key']] = $r['setting_value']; }

$tahun_ini = $app_config['tahun_aplikasi'] ?? '2026'; 
$tahun_basis = $app_config['tahun_saldo_awal'] ?? '2025';
$isAdminSekolah = ($_SESSION['role'] == 'admin_sekolah');

// Cache Cuti Bersama
$list_cb = [];
$q_cb = $conn->query("SELECT tahun, jml_cuti_bersama FROM pengaturan_cuti");
while($row = $q_cb->fetch()) { $list_cb[$row['tahun']] = $row['jml_cuti_bersama']; }

// --- LOGIKA BACKEND (CRUD) ---

// 1. Tambah Pegawai
if(isset($_POST['tambah_pegawai'])) {
    if(!$isAdminSekolah) { echo "<script>alert('Akses Ditolak');</script>"; }
    else {
        try {
            $nip = $_POST['nip'];
            $cek = $conn->prepare("SELECT id, status_aktif FROM pegawai WHERE nip = ?");
            $cek->execute([$nip]);
            $existing = $cek->fetch();

            if ($existing) {
                if ($existing['status_aktif'] == 'aktif') {
                    echo "<script>alert('Gagal! NIP sudah terdaftar.'); window.location='?page=pegawai';</script>";
                } else {
                    $sql = "UPDATE pegawai SET nama = ?, pangkat = ?, jabatan = ?, tipe = ?, sekolah = ?, jenjang = ?, status_aktif = 'aktif' WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$_POST['nama'], $_POST['pangkat'], $_POST['jabatan'], $_POST['tipe'], $_SESSION['nama_sekolah'], $_SESSION['jenjang'], $existing['id']]);
                    echo "<script>alert('Data Guru-KS dipulihkan dari arsip (Restore).'); window.location='?page=pegawai';</script>";
                }
            } else {
                $sisa_lalu = intval($_POST['sisa_cuti_thn_lalu']);
                if($sisa_lalu > 6) $sisa_lalu = 6;
                $sql = "INSERT INTO pegawai (nip, nama, pangkat, jabatan, tipe, sekolah, jenjang, kuota_cuti, sisa_cuti_thn_lalu, cuti_terpakai, status_aktif) VALUES (?, ?, ?, ?, ?, ?, ?, 12, ?, 0, 'aktif')";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$nip, $_POST['nama'], $_POST['pangkat'], $_POST['jabatan'], $_POST['tipe'], $_SESSION['nama_sekolah'], $_SESSION['jenjang'], $sisa_lalu]);
                echo "<script>alert('Berhasil menambah Guru-KS!'); window.location='?page=pegawai';</script>";
            }
        } catch (PDOException $e) { echo "<script>alert('Terjadi kesalahan sistem.');</script>"; }
    }
}

// 2. Import CSV
if(isset($_POST['import_pegawai'])) {
    if(!$isAdminSekolah) { echo "<script>alert('Akses Ditolak');</script>"; }
    else {
        $fileName = $_FILES['file_csv']['tmp_name'];
        if ($_FILES['file_csv']['size'] > 0) {
            $file = fopen($fileName, "r");
            fgetcsv($file); // Skip header
            $sukses = 0; $restored = 0;
            while (($col = fgetcsv($file, 10000, ",")) !== FALSE) {
                if(!empty($col[0])) {
                    $nip = $col[0];
                    $cek = $conn->prepare("SELECT id, status_aktif FROM pegawai WHERE nip = ?");
                    $cek->execute([$nip]);
                    $ada = $cek->fetch();
                    
                    if($ada) {
                        if($ada['status_aktif'] == 'nonaktif') {
                            $conn->prepare("UPDATE pegawai SET status_aktif='aktif', nama=?, pangkat=?, jabatan=?, tipe=? WHERE id=?")->execute([$col[1], $col[2], $col[3], $col[4], $ada['id']]);
                            $restored++;
                        }
                    } else {
                        try {
                            $sisa_lalu = isset($col[5]) ? intval($col[5]) : 0;
                            if($sisa_lalu > 6) $sisa_lalu = 6;
                            if($sisa_lalu < 0) $sisa_lalu = 0;

                            $sql_insert = "INSERT INTO pegawai (nip, nama, pangkat, jabatan, tipe, sekolah, jenjang, kuota_cuti, sisa_cuti_thn_lalu, cuti_terpakai, status_aktif) VALUES (?, ?, ?, ?, ?, ?, ?, 12, ?, 0, 'aktif')";
                            $stmt = $conn->prepare($sql_insert);
                            $stmt->execute([$col[0], $col[1], $col[2], $col[3], $col[4], $_SESSION['nama_sekolah'], $_SESSION['jenjang'], $sisa_lalu]);
                            
                            $sukses++;
                        } catch(Exception $e) { continue; }
                    }
                }
            }
            echo "<script>alert('Import selesai! Baru: $sukses, Dipulihkan: $restored'); window.location='?page=pegawai';</script>";
        }
    }
}

// 3. Edit Pegawai
if(isset($_POST['edit_pegawai'])) {
    if(!$isAdminSekolah) { echo "<script>alert('Akses Ditolak');</script>"; }
    else {
        $conn->prepare("UPDATE pegawai SET nama=?, pangkat=?, jabatan=?, tipe=? WHERE id=?")->execute([$_POST['nama'], $_POST['pangkat'], $_POST['jabatan'], $_POST['tipe'], $_POST['id_pegawai']]);
        echo "<script>alert('Data diupdate!'); window.location='?page=pegawai';</script>";
    }
}

// 4. Hapus Pegawai (Soft Delete)
if(isset($_GET['hapus_id'])) {
    if(!$isAdminSekolah) { echo "<script>alert('Akses Ditolak');</script>"; }
    else {
        $conn->prepare("UPDATE pegawai SET status_aktif = 'nonaktif' WHERE id = ?")->execute([$_GET['hapus_id']]);
        echo "<script>alert('Guru/KS dinonaktifkan.'); window.location='?page=pegawai';</script>";
    }
}

// --- LOGIKA PAGINATION & PENCARIAN ---
$batas = 10; 
$halaman = isset($_GET['hal']) ? (int)$_GET['hal'] : 1;
$halaman_awal = ($halaman > 1) ? ($halaman * $batas) - $batas : 0;
$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";
$filter_sekolah = isset($_GET['filter_sekolah']) ? $_GET['filter_sekolah'] : "";

$params = [];
$where = "WHERE status_aktif = 'aktif'";

if($_SESSION['role'] != 'admin_dinas'){
    $where .= " AND sekolah = ?";
    $params[] = $_SESSION['nama_sekolah'];
} else {
    if(!empty($filter_sekolah)) {
        $where .= " AND sekolah = ?";
        $params[] = $filter_sekolah;
    }
}

if(!empty($cari)){
    $where .= " AND (nama LIKE ? OR nip LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}

$sql_count = "SELECT COUNT(*) FROM pegawai $where";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->execute($params);
$jumlah_data = $stmt_count->fetchColumn();
$total_halaman = ceil($jumlah_data / $batas);

$sql_data = "SELECT * FROM pegawai $where ORDER BY nama ASC LIMIT $halaman_awal, $batas";
$stmt = $conn->prepare($sql_data);
$stmt->execute($params);
?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden relative z-0">
    
    <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-white">
        <div>
            <h3 class="font-bold text-slate-800 text-lg">Direktori Guru-KS</h3>
            <p class="text-slate-400 text-xs mt-0.5">
                Total: <span class="font-bold text-slate-700"><?= $jumlah_data ?> Guru-KS</span> &bull; 
                Cuti Bersama: <span class="font-bold text-red-500"><?= $list_cb[$tahun_ini] ?? 0 ?> Hari</span>
            </p>
        </div>
        
        <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
            <form action="" method="GET" class="flex flex-col md:flex-row gap-2 w-full md:w-auto">
                <input type="hidden" name="page" value="pegawai">
                
                <?php if($_SESSION['role'] == 'admin_dinas'): ?>
                    <select name="filter_sekolah" onchange="this.form.submit()" class="border border-slate-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-blue-500 bg-white min-w-[180px]">
                        <option value="">-- Semua Sekolah --</option>
                        <?php 
                        $list_sekolah_opt = $conn->query("SELECT DISTINCT sekolah FROM pegawai WHERE status_aktif='aktif' ORDER BY sekolah ASC")->fetchAll();
                        foreach($list_sekolah_opt as $s): 
                        ?>
                            <option value="<?= $s['sekolah'] ?>" <?= $filter_sekolah == $s['sekolah'] ? 'selected' : '' ?>><?= $s['sekolah'] ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <div class="relative w-full md:w-64">
                    <span class="absolute left-3 top-2.5 text-slate-400"><i class="fa-solid fa-search"></i></span>
                    <input type="text" name="cari" value="<?= htmlspecialchars($cari) ?>" placeholder="Cari Nama / NIP..." class="w-full pl-9 pr-4 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-blue-500 transition">
                </div>
            </form>

            <div class="flex gap-2">
                <a href="views/export_pegawai.php?cari=<?= urlencode($cari) ?>&filter_sekolah=<?= urlencode($filter_sekolah) ?>" 
                   class="bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-lg shadow-emerald-500/30 hover:bg-emerald-700 transition flex items-center gap-2">
                    <i class="fa-solid fa-file-csv"></i> <span class="hidden sm:inline">Unduh CSV</span>
                </a>

                <?php if($isAdminSekolah): ?>
                    <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-lg shadow-blue-500/30 hover:bg-blue-700 transition flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i> <span class="hidden sm:inline">Tambah</span>
                    </button>
                    <button onclick="document.getElementById('modalImport').classList.remove('hidden')" class="bg-blue-500 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-lg shadow-blue-500/30 hover:bg-blue-600 transition flex items-center gap-2">
                        <i class="fa-solid fa-file-import"></i> <span class="hidden sm:inline">Import</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-slate-600">
            <thead class="text-xs text-slate-500 uppercase bg-slate-50/50 border-b border-slate-100">
                <tr>
                    <th class="px-6 py-4 font-semibold">Nama / NIP</th>
                    <th class="px-6 py-4 font-semibold">Jabatan</th>
                    <th class="px-6 py-4 font-semibold text-center bg-blue-50/30">Sisa <?= $tahun_basis ?></th>
                    <th class="px-6 py-4 font-semibold text-center bg-green-50/30">Hak <?= $tahun_ini ?></th>
                    <th class="px-6 py-4 font-semibold text-center">Terpakai</th>
                    <th class="px-6 py-4 font-semibold text-center font-bold text-slate-700">Total Sisa</th>
                    <?php if($isAdminSekolah): ?><th class="px-6 py-4 text-center">Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php
                if($jumlah_data == 0): 
                    echo '<tr><td colspan="7" class="p-8 text-center text-slate-400 italic">Data Guru-KS tidak ditemukan.</td></tr>';
                endif;

                while($p = $stmt->fetch()):
                    $saldo_berjalan = $p['sisa_cuti_thn_lalu']; 
                    $display_carry_over = 0;
                    $display_hak_murni = 0;
                    $display_terpakai = 0;
                    $display_total_sisa = 0;

                    for ($y = ($tahun_basis + 1); $y <= $tahun_ini; $y++) {
                        $carry_over = ($saldo_berjalan > 6) ? 6 : $saldo_berjalan;
                        $hak_murni = 12 - ($list_cb[$y] ?? 0);
                        $total_hak = $carry_over + $hak_murni;
                        
                        $q_used = $conn->prepare("SELECT SUM(durasi) as total FROM pengajuan_cuti WHERE pegawai_id = ? AND YEAR(tgl_mulai) = ? AND status = 'disetujui' AND jenis_cuti = 'Cuti Tahunan'");
                        $q_used->execute([$p['id'], $y]);
                        $used = $q_used->fetch()['total'] ?? 0;
                        
                        $saldo_berjalan = $total_hak - $used;
                        
                        if ($y == $tahun_ini) {
                            $display_carry_over = $carry_over;
                            $display_hak_murni = $hak_murni;
                            $display_terpakai = $used;
                            $display_total_sisa = $saldo_berjalan;
                        }
                    }

                    if ($tahun_ini == $tahun_basis) {
                        $display_carry_over = 0; 
                        $display_hak_murni = 12 - ($list_cb[$tahun_ini] ?? 0);
                        $display_terpakai = 0; 
                        $display_total_sisa = $p['sisa_cuti_thn_lalu'] + $display_hak_murni;
                    }
                    $bg_sisa = $display_total_sisa < 4 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700';
                ?>
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="px-6 py-4">
                        <div class="font-bold text-slate-800"><?= $p['nama'] ?></div>
                        <div class="text-xs text-slate-500 font-mono mt-0.5"><?= $p['nip'] ?></div>
                        <div class="text-[10px] font-bold text-blue-600 mt-1">
                            <i class="fa-solid fa-school text-[9px] mr-1"></i> <?= $p['sekolah'] ?>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="font-medium text-slate-700"><?= $p['pangkat'] ?></div>
                        <div class="text-xs text-slate-500"><?= $p['tipe'] ?> - <?= $p['jabatan'] ?></div>
                    </td>
                    <td class="px-6 py-4 text-center bg-blue-50/30 font-medium text-slate-600">
                        <?= $display_carry_over ?> <span class="text-[9px] text-slate-400 block">(Max 6)</span>
                    </td>
                    <td class="px-6 py-4 text-center bg-green-50/30 font-medium text-slate-600"><?= $display_hak_murni ?></td>
                    <td class="px-6 py-4 text-center font-medium text-red-500">- <?= $display_terpakai ?></td>
                    <td class="px-6 py-4 text-center">
                        <div class="inline-flex w-8 h-8 rounded-lg items-center justify-center font-bold text-sm <?= $bg_sisa ?>">
                            <?= $display_total_sisa ?>
                        </div>
                    </td>
                    <?php if($isAdminSekolah): ?>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick='bukaEdit(<?= json_encode($p) ?>)' class="w-8 h-8 rounded-lg bg-yellow-50 text-yellow-600 hover:bg-yellow-100 transition flex items-center justify-center border border-yellow-200"><i class="fa-solid fa-pen-to-square"></i></button>
                            <a href="?page=pegawai&hapus_id=<?= $p['id'] ?>" onclick="konfirmasiHapus(event, this.href, 'Guru-KS akan dinonaktifkan (Soft Delete). Data cuti tetap tersimpan di arsip.')" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition flex items-center justify-center border border-red-200"><i class="fa-solid fa-trash"></i></a>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php if($total_halaman > 1): ?>
    <div class="p-4 border-t border-slate-100 bg-slate-50 flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="text-xs text-slate-500">
            Halaman <b><?= $halaman ?></b> dari <b><?= $total_halaman ?></b>
        </div>
        <div class="flex gap-1">
            <?php 
            $query_string = "&cari=" . urlencode($cari) . "&filter_sekolah=" . urlencode($filter_sekolah);
            if($halaman > 1): ?>
                <a href="?page=pegawai<?= $query_string ?>&hal=<?= $halaman - 1 ?>" class="px-3 py-1 bg-white border border-slate-300 rounded text-sm hover:bg-slate-100 text-slate-600">Previous</a>
            <?php endif; ?>

            <?php 
            $start_num = max(1, $halaman - 2);
            $end_num = min($total_halaman, $halaman + 2);
            for($x = $start_num; $x <= $end_num; $x++): 
            ?>
                <a href="?page=pegawai<?= $query_string ?>&hal=<?= $x ?>" class="px-3 py-1 border rounded text-sm <?= $x == $halaman ? 'bg-blue-600 text-white border-blue-600 font-bold' : 'bg-white border-slate-300 hover:bg-slate-100 text-slate-600' ?>"><?= $x ?></a>
            <?php endfor; ?>

            <?php if($halaman < $total_halaman): ?>
                <a href="?page=pegawai<?= $query_string ?>&hal=<?= $halaman + 1 ?>" class="px-3 py-1 bg-white border border-slate-300 rounded text-sm hover:bg-slate-100 text-slate-600">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div id="modalTambah" class="fixed inset-0 bg-slate-900/60 z-[9999] hidden flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b flex justify-between items-center"><h3 class="font-bold text-lg text-slate-800">Tambah Guru/KS Baru</h3><button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button></div>
        <form method="POST" class="p-6 space-y-4">
            <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">NIP</label><input type="number" name="nip" required class="w-full border rounded-lg p-2.5 focus:ring-blue-500"></div>
            <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Lengkap</label><input type="text" name="nama" required class="w-full border rounded-lg p-2.5 focus:ring-blue-500"></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Tipe</label><select name="tipe" class="w-full border rounded-lg p-2.5 bg-white"><option value="PNS">PNS</option><option value="P3K">P3K</option><option value="P3K Paruh Waktu">P3K Paruh Waktu</option></select></div>
                <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Sisa Cuti <?= $tahun_basis ?></label><input type="number" name="sisa_cuti_thn_lalu" value="0" min="0" max="6" class="w-full border rounded-lg p-2.5 bg-yellow-50 border-yellow-200"></div>
            </div>
            <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Jabatan</label><input type="text" name="jabatan" value="Guru Kelas" class="w-full border rounded-lg p-2.5"></div>
            <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Pangkat/Gol.Ruang</label><input type="text" name="pangkat" required class="w-full border rounded-lg p-2.5"></div>
            <div class="pt-4 flex justify-end gap-2 border-t mt-4"><button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg">Batal</button><button type="submit" name="tambah_pegawai" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Simpan</button></div>
        </form>
    </div>
</div>

<div id="modalImport" class="fixed inset-0 bg-slate-900/60 z-[9999] hidden flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b flex justify-between items-center"><h3 class="font-bold text-lg text-slate-800">Import Data</h3><button onclick="document.getElementById('modalImport').classList.add('hidden')" class="text-slate-400 hover:text-red-500"><i class="fa-solid fa-times text-xl"></i></button></div>
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 text-sm text-blue-800 mb-4"><p class="font-bold mb-1">Format CSV:</p><p>NIP, Nama, Pangkat, Jabatan, Tipe, Sisa Cuti Awal</p><a href="template_pegawai.csv" download class="text-blue-600 underline font-bold mt-2 block">Download Template</a></div>
            <div><input type="file" name="file_csv" accept=".csv" required class="w-full border border-slate-300 rounded-lg p-2"></div>
            <div class="pt-4 flex justify-end gap-2 border-t mt-2"><button type="button" onclick="document.getElementById('modalImport').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg">Batal</button><button type="submit" name="import_pegawai" class="px-4 py-2 bg-emerald-600 text-white rounded-lg">Import</button></div>
        </form>
    </div>
</div>

<div id="modalEdit" class="fixed inset-0 bg-slate-900/60 z-[9999] hidden flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b flex justify-between items-center"><h3 class="font-bold text-lg text-slate-800">Edit Guru-KS</h3><button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-slate-400 hover:text-red-500"><i class="fa-solid fa-times text-xl"></i></button></div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id_pegawai" id="edit_id">
            <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-100 text-xs text-yellow-700 mb-4 flex items-center gap-2"><i class="fa-solid fa-lock"></i> Info NIP dan Sisa Cuti Awal terkunci.</div>
            <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Lengkap</label><input type="text" name="nama" id="edit_nama" required class="w-full border rounded-lg p-2.5"></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Tipe</label><select name="tipe" id="edit_tipe" class="w-full border rounded-lg p-2.5 bg-white"><option value="PNS">PNS</option><option value="P3K">P3K</option><option value="P3K Paruh Waktu">P3K Paruh Waktu</option></select></div>
                <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Sisa Cuti Awal (Locked)</label><input type="number" name="sisa_cuti_thn_lalu" id="edit_sisa_lalu" readonly class="w-full border rounded-lg p-2.5 bg-slate-200 text-slate-500 cursor-not-allowed"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Jabatan</label><input type="text" name="jabatan" id="edit_jabatan" required class="w-full border rounded-lg p-2.5"></div>
                <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Pangkat/Gol.Ruang</label><input type="text" name="pangkat" id="edit_pangkat" required class="w-full border rounded-lg p-2.5"></div>
            </div>
            <div class="pt-4 flex justify-end gap-2 border-t mt-4"><button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg">Batal</button><button type="submit" name="edit_pegawai" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Update</button></div>
        </form>
    </div>
</div>

<script>
    function bukaEdit(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_nama').value = data.nama;
        document.getElementById('edit_tipe').value = data.tipe;
        document.getElementById('edit_jabatan').value = data.jabatan;
        document.getElementById('edit_pangkat').value = data.pangkat;
        document.getElementById('edit_sisa_lalu').value = data.sisa_cuti_thn_lalu;
        document.getElementById('modalEdit').classList.remove('hidden');
    }
</script>