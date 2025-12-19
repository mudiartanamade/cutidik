<?php
// views/laporan.php

// 1. PROTEKSI HALAMAN
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin_dinas') {
    echo "<script>window.location='index.php';</script>";
    exit;
}

// --- LOGIKA HITUNG DURASI (Hanya untuk tampilan tabel) ---
// Kita deklarasikan ulang di sini untuk keperluan tabel HTML
$libur_nasional = [];
try {
    $q_lib = $conn->query("SELECT tanggal FROM hari_libur");
    while($l = $q_lib->fetch()) { $libur_nasional[] = $l['tanggal']; }
} catch (Exception $e) {}

if (!function_exists('hitungDurasiReal')) {
    function hitungDurasiReal($start, $end, $hari_kerja, $libur_array) {
        if(empty($start) || empty($end)) return 0;
        $start_date = new DateTime($start);
        $end_date   = new DateTime($end);
        if($start_date > $end_date) return 0;
        $durasi = 0;
        $period = new DatePeriod($start_date, new DateInterval('P1D'), $end_date->modify('+1 day'));
        foreach($period as $dt) {
            $curr = $dt->format('Y-m-d');
            $dayW = $dt->format('w');
            if($dayW == 0) continue; 
            if($dayW == 6 && $hari_kerja == '5 Hari') continue; 
            if(in_array($curr, $libur_array)) continue; 
            $durasi++;
        }
        return $durasi;
    }
}

// --- FILTER & QUERY ---
$default_awal = date('Y-m-01');
$default_akhir = date('Y-m-d');

$tgl_awal = $_GET['tgl_awal'] ?? $default_awal;
$tgl_akhir = $_GET['tgl_akhir'] ?? $default_akhir;
$sekolah = $_GET['sekolah'] ?? '';
$jenis = $_GET['jenis'] ?? '';
$keyword = $_GET['q'] ?? ''; 

$sql_base = "SELECT pc.*, p.nama, p.nip, p.sekolah, ds.hari_belajar 
             FROM pengajuan_cuti pc 
             JOIN pegawai p ON pc.pegawai_id = p.id 
             LEFT JOIN data_sekolah ds ON p.sekolah = ds.nama_sekolah
             WHERE pc.status = 'disetujui'";

if (!empty($tgl_awal) && !empty($tgl_akhir)) {
    $sql_base .= " AND (pc.tgl_mulai BETWEEN '$tgl_awal' AND '$tgl_akhir')";
}
if (!empty($sekolah)) {
    $sql_base .= " AND p.sekolah = '$sekolah'";
}
if (!empty($jenis)) {
    $sql_base .= " AND pc.jenis_cuti = '$jenis'";
}
if (!empty($keyword)) {
    $sql_base .= " AND p.nama LIKE '%$keyword%'";
}

// Pagination
$stmt_count = $conn->query(str_replace("pc.*, p.nama, p.nip, p.sekolah, ds.hari_belajar", "COUNT(*)", $sql_base));
$total_data = $stmt_count->fetchColumn();

$batas = 10; 
$halaman = isset($_GET['hal']) ? (int)$_GET['hal'] : 1;
$halaman_awal = ($halaman > 1) ? ($halaman * $batas) - $batas : 0;
$total_halaman = ceil($total_data / $batas);

$stmt = $conn->query($sql_base . " ORDER BY pc.tgl_mulai DESC LIMIT $halaman_awal, $batas");
$data_laporan = $stmt->fetchAll();

$opt_sekolah = $conn->query("SELECT DISTINCT nama_sekolah FROM data_sekolah ORDER BY nama_sekolah ASC")->fetchAll();
$opt_jenis = $conn->query("SELECT DISTINCT nama_cuti FROM jenis_cuti")->fetchAll();
?>

<div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-800">Laporan Cuti Guru-KS</h2>
            <p class="text-sm text-slate-500">Rekapitulasi data cuti (Durasi: Hari Kerja Efektif)</p>
        </div>
        
        <a href="views/export_laporan.php?tgl_awal=<?= $tgl_awal ?>&tgl_akhir=<?= $tgl_akhir ?>&sekolah=<?= urlencode($sekolah) ?>&jenis=<?= urlencode($jenis) ?>&q=<?= urlencode($keyword) ?>" 
           target="_blank"
           class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 shadow-sm transition">
            <i class="fa-solid fa-file-csv"></i> Unduh CSV
        </a>
    </div>

    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 mb-6">
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="hidden" name="page" value="laporan">

            <div class="md:col-span-1">
                <label class="block text-xs font-bold text-slate-500 mb-1">Dari Tanggal</label>
                <input type="date" name="tgl_awal" value="<?= $tgl_awal ?>" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="md:col-span-1">
                <label class="block text-xs font-bold text-slate-500 mb-1">Sampai Tanggal</label>
                <input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="md:col-span-1">
                <label class="block text-xs font-bold text-slate-500 mb-1">Unit Kerja / Sekolah</label>
                <select name="sekolah" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Semua Sekolah --</option>
                    <?php foreach ($opt_sekolah as $s): ?>
                        <option value="<?= $s['nama_sekolah'] ?>" <?= $sekolah == $s['nama_sekolah'] ? 'selected' : '' ?>>
                            <?= $s['nama_sekolah'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="md:col-span-1">
                <label class="block text-xs font-bold text-slate-500 mb-1">Jenis Cuti</label>
                <select name="jenis" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Semua Jenis --</option>
                    <?php foreach ($opt_jenis as $j): ?>
                        <option value="<?= $j['nama_cuti'] ?>" <?= $jenis == $j['nama_cuti'] ? 'selected' : '' ?>>
                            <?= $j['nama_cuti'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="md:col-span-3">
                <label class="block text-xs font-bold text-slate-500 mb-1">Cari Nama Guru-KS</label>
                <div class="relative">
                    <i class="fa-solid fa-search absolute left-3 top-2.5 text-slate-400"></i>
                    <input type="text" name="q" value="<?= $keyword ?>" placeholder="Ketik nama Guru-KS..." class="w-full pl-10 pr-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="md:col-span-1 flex items-end">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg text-sm font-bold transition">
                    <i class="fa-solid fa-filter mr-1"></i> Terapkan Filter
                </button>
            </div>
        </form>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200">
        <table class="w-full text-sm text-left text-slate-600">
            <thead class="bg-slate-50 text-slate-700 uppercase font-bold text-xs">
                <tr>
                    <th class="px-4 py-3 text-center border-b">No</th>
                    <th class="px-4 py-3 border-b">Guru-KS</th>
                    <th class="px-4 py-3 border-b">Unit Kerja</th>
                    <th class="px-4 py-3 border-b">Tanggal & Durasi</th>
                    <th class="px-4 py-3 border-b">Kontak Cuti</th>
                    <th class="px-4 py-3 border-b">Alasan</th>
                    <th class="px-4 py-3 border-b text-center">Bukti</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (count($data_laporan) > 0): ?>
                    <?php 
                        $no = $halaman_awal + 1;
                        foreach ($data_laporan as $row): 
                            $hari_kerja_sekolah = !empty($row['hari_belajar']) ? $row['hari_belajar'] : '6 Hari';
                            $durasi_real = hitungDurasiReal($row['tgl_mulai'], $row['tgl_selesai'], $hari_kerja_sekolah, $libur_nasional);
                    ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3 text-center font-bold text-slate-400"><?= $no++ ?></td>
                        <td class="px-4 py-3">
                            <p class="font-bold text-slate-700"><?= $row['nama'] ?></p>
                            <p class="text-xs text-slate-400">NIP. <?= $row['nip'] ?></p>
                            <span class="inline-block mt-1 px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-bold border border-blue-100">
                                <?= $row['jenis_cuti'] ?>
                            </span>
                        </td>
                        <td class="px-4 py-3"><?= $row['sekolah'] ?></td>
                        
                        <td class="px-4 py-3">
                            <div class="text-xs font-bold text-slate-600">
                                <?= date('d M Y', strtotime($row['tgl_mulai'])) ?>
                                <i class="fa-solid fa-arrow-right mx-1 text-slate-300"></i>
                                <?= date('d M Y', strtotime($row['tgl_selesai'])) ?>
                            </div>
                            <span class="text-[10px] text-slate-400 font-bold mt-1 block"><?= $durasi_real ?> Hari Kerja</span>
                        </td>

                        <td class="px-4 py-3 text-xs">
                            <div class="flex items-start gap-2 mb-1">
                                <i class="fa-solid fa-location-dot text-slate-400 mt-0.5"></i>
                                <span class="leading-tight"><?= $row['alamat_cuti'] ?: '-' ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fa-brands fa-whatsapp text-green-500"></i>
                                <span class="font-mono text-slate-600"><?= $row['no_wa'] ?: '-' ?></span>
                            </div>
                        </td>

                        <td class="px-4 py-3 text-xs italic text-slate-500 max-w-xs truncate" title="<?= $row['alasan'] ?>">
                            "<?= $row['alasan'] ?>"
                        </td>
                        
                        <td class="px-4 py-3 text-center border-b">
    <?php 
    if (!empty($row['file_bukti'])) {
        $path = "uploads/" . $row['file_bukti'];
        // Tombol hanya muncul jika file fisik masih ada di folder
        if (file_exists($path)) {
            echo '<a href="'.$path.'" target="_blank" class="text-blue-600 hover:text-blue-800">
                    <i class="fa-solid fa-file-pdf"></i>
                  </a>';
        } else {
            echo '<span class="text-[10px] text-slate-400 italic">Terhapus (30hr)</span>';
        }
    } else {
        echo '<span class="text-slate-300">-</span>';
    }
    ?>
</td>
                        
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400 italic">
                            <div class="mb-2"><i class="fa-regular fa-folder-open text-3xl"></i></div>
                            Tidak ada data laporan ditemukan.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_halaman > 1): ?>
    <div class="mt-6 flex justify-center gap-2">
        <?php 
            $params = "&tgl_awal=$tgl_awal&tgl_akhir=$tgl_akhir&sekolah=$sekolah&jenis=$jenis&q=$keyword"; 
        ?>
        <?php if ($halaman > 1): ?>
            <a href="?page=laporan<?= $params ?>&hal=<?= $halaman - 1 ?>" class="px-3 py-1 bg-white border border-slate-300 rounded text-sm hover:bg-slate-100 transition">Prev</a>
        <?php endif; ?>

        <?php for ($x = 1; $x <= $total_halaman; $x++): ?>
            <a href="?page=laporan<?= $params ?>&hal=<?= $x ?>" 
               class="px-3 py-1 border rounded text-sm font-bold transition <?= $x == $halaman ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-100' ?>">
                <?= $x ?>
            </a>
        <?php endfor; ?>

        <?php if ($halaman < $total_halaman): ?>
            <a href="?page=laporan<?= $params ?>&hal=<?= $halaman + 1 ?>" class="px-3 py-1 bg-white border border-slate-300 rounded text-sm hover:bg-slate-100 transition">Next</a>
        <?php endif; ?>
    </div>
    <div class="text-center mt-2 text-xs text-slate-400">
        Menampilkan Halaman <?= $halaman ?> dari <?= $total_halaman ?>
    </div>
    <?php endif; ?>

</div>