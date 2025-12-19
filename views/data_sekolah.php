<?php
// Proteksi: Hanya Admin Dinas
if($_SESSION['role'] != 'admin_dinas') { echo "<script>alert('Akses Ditolak'); window.location='?page=home';</script>"; exit; }

// --- LOGIKA PHP (BACKEND) ---

// 1. Tambah Sekolah
if(isset($_POST['tambah_sekolah'])) {
    try {
        $stmt = $conn->prepare("INSERT INTO data_sekolah (npsn, nama_sekolah, alamat, hari_belajar) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['npsn'], $_POST['nama_sekolah'], $_POST['alamat'], $_POST['hari_belajar']]);
        echo "<script>alert('Data Sekolah Berhasil Ditambahkan!'); window.location='?page=data_sekolah';</script>";
    } catch (PDOException $e) { echo "<script>alert('Gagal! NPSN mungkin sudah ada.');</script>"; }
}

// 2. Import CSV
if(isset($_POST['import_sekolah'])) {
    $fileName = $_FILES['file_csv']['tmp_name'];
    if ($_FILES['file_csv']['size'] > 0) {
        $file = fopen($fileName, "r");
        fgetcsv($file); $sukses = 0;
        while (($col = fgetcsv($file, 10000, ",")) !== FALSE) {
            if(!empty($col[0])) {
                try {
                    $conn->prepare("INSERT INTO data_sekolah (npsn, nama_sekolah, alamat, hari_belajar) VALUES (?, ?, ?, ?)")->execute([$col[0], $col[1], $col[2], $col[3]]);
                    $sukses++;
                } catch(Exception $e) { }
            }
        }
        echo "<script>alert('Import Selesai! Berhasil: $sukses'); window.location='?page=data_sekolah';</script>";
    }
}

// 3. Edit Sekolah
if(isset($_POST['edit_sekolah'])) {
    try {
        $conn->prepare("UPDATE data_sekolah SET nama_sekolah=?, alamat=?, hari_belajar=? WHERE id=?")->execute([$_POST['nama_sekolah'], $_POST['alamat'], $_POST['hari_belajar'], $_POST['id_sekolah']]);
        echo "<script>alert('Data Sekolah Berhasil Diupdate!'); window.location='?page=data_sekolah';</script>";
    } catch (PDOException $e) { echo "<script>alert('Gagal update.');</script>"; }
}

// 4. Hapus Sekolah (Logic Hapus ada di view dengan SweetAlert)
if(isset($_GET['hapus_id'])) {
    $conn->prepare("DELETE FROM data_sekolah WHERE id = ?")->execute([$_GET['hapus_id']]);
    echo "<script>alert('Data Sekolah Dihapus!'); window.location='?page=data_sekolah';</script>";
}

// --- PAGINATION & SEARCH LOGIC ---
$batas = 10;
$halaman = isset($_GET['hal']) ? (int)$_GET['hal'] : 1;
$halaman_awal = ($halaman > 1) ? ($halaman * $batas) - $batas : 0;
$cari = isset($_GET['cari']) ? trim($_GET['cari']) : "";

// Params Query
$params = [];
$where = "";

if(!empty($cari)){
    $where = "WHERE nama_sekolah LIKE ? OR npsn LIKE ?";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}

// Count Total
$stmt_count = $conn->prepare("SELECT COUNT(*) FROM data_sekolah $where");
$stmt_count->execute($params);
$jumlah_data = $stmt_count->fetchColumn();
$total_halaman = ceil($jumlah_data / $batas);

// Get Data
$sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM pegawai p WHERE p.sekolah = s.nama_sekolah AND p.tipe = 'PNS' AND p.status_aktif='aktif') as jml_pns,
        (SELECT COUNT(*) FROM pegawai p WHERE p.sekolah = s.nama_sekolah AND p.tipe = 'P3K' AND p.status_aktif='aktif') as jml_p3k,
        (SELECT COUNT(*) FROM pegawai p WHERE p.sekolah = s.nama_sekolah AND p.tipe = 'P3K Paruh Waktu' AND p.status_aktif='aktif') as jml_p3kparuhwaktu
        FROM data_sekolah s 
        $where
        ORDER BY s.nama_sekolah ASC LIMIT $halaman_awal, $batas";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden relative z-0">
    <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-white">
        <div>
            <h3 class="font-bold text-slate-800 text-lg">Data Sekolah & Unit Kerja</h3>
            <p class="text-slate-400 text-xs mt-0.5">Total Sekolah: <span class="font-bold text-slate-700"><?= $jumlah_data ?></span></p>
        </div>
        
        <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
            <form action="" method="GET" class="relative w-full md:w-64">
                <input type="hidden" name="page" value="data_sekolah">
                <span class="absolute left-3 top-2.5 text-slate-400"><i class="fa-solid fa-search"></i></span>
                <input type="text" name="cari" value="<?= htmlspecialchars($cari) ?>" placeholder="Cari Sekolah / NPSN..." class="w-full pl-9 pr-4 py-2 border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-blue-500 transition">
            </form>

            <div class="flex gap-2">
                <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-lg shadow-blue-500/30 hover:bg-blue-700 transition flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i> <span class="hidden sm:inline">Tambah</span>
                </button>
                <button onclick="document.getElementById('modalImport').classList.remove('hidden')" class="bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-lg shadow-emerald-500/30 hover:bg-emerald-700 transition flex items-center gap-2">
                    <i class="fa-solid fa-file-csv"></i> <span class="hidden sm:inline">Import</span>
                </button>
            </div>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-slate-600">
            <thead class="text-xs text-slate-500 uppercase bg-slate-50/50 border-b border-slate-100">
                <tr>
                    <th class="px-6 py-4 font-semibold text-center w-12">No</th>
                    <th class="px-6 py-4 font-semibold">Identitas Sekolah</th>
                    <th class="px-6 py-4 font-semibold">Alamat & Hari Belajar</th>
                    <th class="px-6 py-4 font-semibold text-center">PNS</th>
                    <th class="px-6 py-4 font-semibold text-center">P3K</th>
                    <th class="px-6 py-4 font-semibold text-center">P3K PW</th>
                    <th class="px-6 py-4 font-semibold text-center">Total</th>
                    <th class="px-6 py-4 font-semibold text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php
                if($jumlah_data == 0): 
                    echo '<tr><td colspan="8" class="p-8 text-center text-slate-400 italic">Data sekolah tidak ditemukan.</td></tr>';
                endif;

                $no = $halaman_awal + 1;
                while($row = $stmt->fetch()):
                    $total = $row['jml_pns'] + $row['jml_p3k'] + $row['jml_p3kparuhwaktu'];
                ?>
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="px-6 py-4 text-center text-xs text-slate-400"><?= $no++ ?></td>
                    <td class="px-6 py-4">
                        <div class="font-bold text-slate-800"><?= $row['nama_sekolah'] ?></div>
                        <div class="text-xs text-blue-600 font-mono mt-0.5">NPSN: <?= $row['npsn'] ?></div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-xs text-slate-600 line-clamp-2"><?= $row['alamat'] ?></div>
                        <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200">
                            <?= $row['hari_belajar'] ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center font-medium text-blue-600 bg-blue-50/30"><?= $row['jml_pns'] ?></td>
                    <td class="px-6 py-4 text-center font-medium text-orange-600 bg-orange-50/30"><?= $row['jml_p3k'] ?></td>
                    <td class="px-6 py-4 text-center font-medium text-purple-600 bg-purple-50/30"><?= $row['jml_p3kparuhwaktu'] ?></td>
                    <td class="px-6 py-4 text-center font-bold text-slate-800 bg-slate-50"><?= $total ?></td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick='bukaEditSekolah(<?= json_encode($row) ?>)' class="w-8 h-8 rounded-lg bg-yellow-50 text-yellow-600 hover:bg-yellow-100 transition flex items-center justify-center border border-yellow-200"><i class="fa-solid fa-pen-to-square"></i></button>
                            <a href="?page=data_sekolah&hapus_id=<?= $row['id'] ?>" onclick="konfirmasiHapus(event, this.href, 'Data sekolah dan relasi pegawai di dalamnya mungkin akan terpengaruh.')" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition flex items-center justify-center border border-red-200"><i class="fa-solid fa-trash"></i></a>
                        </div>
                    </td>
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
            <?php if($halaman > 1): ?>
                <a href="?page=data_sekolah&cari=<?= $cari ?>&hal=<?= $halaman - 1 ?>" class="px-3 py-1 bg-white border border-slate-300 rounded text-sm hover:bg-slate-100 text-slate-600">Previous</a>
            <?php endif; ?>

            <?php 
            $start_num = max(1, $halaman - 2);
            $end_num = min($total_halaman, $halaman + 2);
            for($x = $start_num; $x <= $end_num; $x++): 
            ?>
                <a href="?page=data_sekolah&cari=<?= $cari ?>&hal=<?= $x ?>" class="px-3 py-1 border rounded text-sm <?= $x == $halaman ? 'bg-blue-600 text-white border-blue-600 font-bold' : 'bg-white border-slate-300 hover:bg-slate-100 text-slate-600' ?>"><?= $x ?></a>
            <?php endfor; ?>

            <?php if($halaman < $total_halaman): ?>
                <a href="?page=data_sekolah&cari=<?= $cari ?>&hal=<?= $halaman + 1 ?>" class="px-3 py-1 bg-white border border-slate-300 rounded text-sm hover:bg-slate-100 text-slate-600">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div id="modalTambah" class="fixed inset-0 bg-slate-900/60 hidden flex items-center justify-center p-4 backdrop-blur-sm transition-opacity" style="z-index: 9999;">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden animate-[fadeIn_0.3s]">
        <div class="bg-slate-50 px-6 py-4 border-b flex justify-between items-center"><h3 class="font-bold text-lg text-slate-800">Tambah Data Sekolah</h3><button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button></div>
        <form method="POST" class="p-6 space-y-4">
            <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">NPSN</label><input type="number" name="npsn" required class="w-full border rounded-lg p-2.5"></div>
            <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Sekolah</label><input type="text" name="nama_sekolah" required class="w-full border rounded-lg p-2.5" placeholder="Contoh: SMP Negeri 1 Denpasar"></div>
            <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Alamat Lengkap</label><textarea name="alamat" required rows="2" class="w-full border rounded-lg p-2.5"></textarea></div>
            <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Hari Belajar</label><select name="hari_belajar" class="w-full border rounded-lg p-2.5 bg-white"><option value="5 Hari">5 Hari Kerja</option><option value="6 Hari">6 Hari Kerja</option></select></div>
            <div class="pt-4 flex justify-end gap-2 border-t mt-4"><button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg">Batal</button><button type="submit" name="tambah_sekolah" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg transition font-medium">Simpan Data</button></div>
        </form>
    </div>
</div>
<div id="modalImport" class="fixed inset-0 bg-slate-900/60 hidden flex items-center justify-center p-4 backdrop-blur-sm transition-opacity" style="z-index: 9999;">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-[fadeIn_0.3s]">
        <div class="bg-slate-50 px-6 py-4 border-b flex justify-between items-center"><h3 class="font-bold text-lg text-slate-800">Import Data Sekolah</h3><button onclick="document.getElementById('modalImport').classList.add('hidden')" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button></div>
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 text-sm text-blue-800 mb-4"><p class="font-bold mb-1">Format CSV:</p><p>NPSN, Nama Sekolah, Alamat, Hari Belajar</p><a href="template_sekolah.csv" download class="text-blue-600 underline font-bold mt-2 block">Download Template</a></div>
            <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Upload File CSV</label><input type="file" name="file_csv" accept=".csv" required class="w-full border border-slate-300 rounded-lg p-2"></div>
            <div class="pt-4 flex justify-end gap-2 border-t mt-2"><button type="button" onclick="document.getElementById('modalImport').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg">Batal</button><button type="submit" name="import_sekolah" class="px-4 py-2 bg-emerald-600 text-white rounded-lg">Mulai Import</button></div>
        </form>
    </div>
</div>
<div id="modalEdit" class="fixed inset-0 bg-slate-900/60 hidden flex items-center justify-center p-4 backdrop-blur-sm transition-opacity" style="z-index: 9999;">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b flex justify-between items-center"><h3 class="font-bold text-lg text-slate-800">Edit Data Sekolah</h3><button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-times text-xl"></i></button></div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id_sekolah" id="edit_id">
            <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-100 text-xs text-yellow-700 mb-2 flex items-center gap-2"><i class="fa-solid fa-lock"></i> NPSN tidak dapat diubah.</div>
            <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Sekolah</label><input type="text" name="nama_sekolah" id="edit_nama" required class="w-full border rounded-lg p-2.5"></div>
            <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Alamat Lengkap</label><textarea name="alamat" id="edit_alamat" required rows="2" class="w-full border rounded-lg p-2.5"></textarea></div>
            <div><label class="block text-xs font-bold uppercase text-slate-500 mb-1">Hari Belajar</label><select name="hari_belajar" id="edit_hari" class="w-full border rounded-lg p-2.5 bg-white"><option value="5 Hari">5 Hari Kerja</option><option value="6 Hari">6 Hari Kerja</option></select></div>
            <div class="pt-4 flex justify-end gap-2 border-t mt-4"><button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg">Batal</button><button type="submit" name="edit_sekolah" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-lg transition font-medium">Update Data</button></div>
        </form>
    </div>
</div>
<script>
    function bukaEditSekolah(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_nama').value = data.nama_sekolah;
        document.getElementById('edit_alamat').value = data.alamat;
        document.getElementById('edit_hari').value = data.hari_belajar;
        document.getElementById('modalEdit').classList.remove('hidden');
    }
</script>