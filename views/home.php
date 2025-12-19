<?php
// --- 1. LOGIKA QUERY DATA (BACKEND) ---
$tahun_ini = date('Y');
$role = $_SESSION['role'];
$sekolah = $_SESSION['nama_sekolah'] ?? '';

// Helper Label Bulan & Warna untuk Chart
$label_bulan_indo = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#6366f1']; // Biru, Hijau, Kuning, Merah, Ungu, Pink, Indigo

// A. FILTER DATA BERDASARKAN ROLE
if($role == 'admin_dinas'){
    $where_sekolah = "";
    $params_sekolah = [];
} else {
    $where_sekolah = " AND p.sekolah = ?";
    $params_sekolah = [$sekolah];
}

// B. DATA WIDGET UTAMA (Baris 1)
// 1. Total Pegawai
$sql_total = "SELECT COUNT(*) FROM pegawai p WHERE status_aktif = 'aktif' $where_sekolah";
$stmt = $conn->prepare($sql_total);
$stmt->execute($params_sekolah);
$total_pegawai = $stmt->fetchColumn();

// 2. Counter Sedang Cuti (Realtime)
$sql_count_away = "SELECT COUNT(*) FROM pengajuan_cuti pc JOIN pegawai p ON pc.pegawai_id = p.id 
                   WHERE pc.status='disetujui' AND CURDATE() BETWEEN pc.tgl_mulai AND pc.tgl_selesai $where_sekolah";
$stmt_count_away = $conn->prepare($sql_count_away);
$stmt_count_away->execute($params_sekolah);
$jml_away = $stmt_count_away->fetchColumn();

// 3. Counter Action Needed
if($role == 'admin_dinas') {
    $sql_action = "SELECT COUNT(*) FROM pengajuan_cuti WHERE status IN ('pending', 'disetujui_menunggu')";
    $label_action = "Tugas Tertunda";
    $desc_action = "Verifikasi & Upload SK";
    $link_action = "?page=persetujuan";
    $stmt_action = $conn->query($sql_action);
    $need_action = $stmt_action->fetchColumn();
} else {
    $sql_action = "SELECT COUNT(*) FROM pengajuan_cuti pc JOIN pegawai p ON pc.pegawai_id = p.id 
                   WHERE p.sekolah = ? AND pc.status = 'pending'";
    $label_action = "Menunggu Verifikasi";
    $desc_action = "Pengajuan belum diproses";
    $link_action = "?page=pengajuan&tab=riwayat"; 
    $stmt_action = $conn->prepare($sql_action);
    $stmt_action->execute($params_sekolah);
    $need_action = $stmt_action->fetchColumn();
}

// C. DATA GRAFIK & INFOGRAFIS BARU

// 1. TREN BULANAN (Line Chart)
$data_bulan = array_fill(1, 12, 0);
$sql_trend = "SELECT MONTH(pc.tgl_mulai) as bulan, COUNT(*) as total FROM pengajuan_cuti pc JOIN pegawai p ON pc.pegawai_id = p.id 
              WHERE YEAR(pc.tgl_mulai) = ? $where_sekolah GROUP BY bulan";
$params_trend = array_merge([$tahun_ini], $params_sekolah);
$stmt_trend = $conn->prepare($sql_trend);
$stmt_trend->execute($params_trend);
while($row = $stmt_trend->fetch()){ $data_bulan[$row['bulan']] = $row['total']; }
$json_trend = json_encode(array_values($data_bulan));

// 2. KOMPOSISI JENIS CUTI (Pie Chart)
$sql_pie = "SELECT pc.jenis_cuti, COUNT(*) as total FROM pengajuan_cuti pc JOIN pegawai p ON pc.pegawai_id = p.id 
            WHERE YEAR(pc.tgl_mulai) = ? $where_sekolah GROUP BY pc.jenis_cuti";
$stmt_pie = $conn->prepare($sql_pie);
$stmt_pie->execute($params_trend); // Parameter sama dengan trend (Tahun + Sekolah)
$pie_labels = [];
$pie_data = [];
while($row = $stmt_pie->fetch()){
    $pie_labels[] = $row['jenis_cuti'];
    $pie_data[] = $row['total'];
}

// 3. TOP 5 SEKOLAH / PEGAWAI (Horizontal Bar)
if($role == 'admin_dinas'){
    // Jika Dinas: Tampilkan Top 5 Sekolah dengan cuti terbanyak
    $judul_top = "Top 5 Sekolah Cuti Terbanyak";
    $sql_top = "SELECT p.sekolah as label, COUNT(*) as total FROM pengajuan_cuti pc JOIN pegawai p ON pc.pegawai_id = p.id 
                WHERE YEAR(pc.tgl_mulai) = ? GROUP BY p.sekolah ORDER BY total DESC LIMIT 5";
    $params_top = [$tahun_ini];
} else {
    // Jika Sekolah: Tampilkan Top 5 Pegawai di sekolah tersebut
    $judul_top = "Top 5 Guru-KS Paling Sering Cuti";
    $sql_top = "SELECT p.nama as label, COUNT(*) as total FROM pengajuan_cuti pc JOIN pegawai p ON pc.pegawai_id = p.id 
                WHERE YEAR(pc.tgl_mulai) = ? AND p.sekolah = ? GROUP BY p.nama ORDER BY total DESC LIMIT 5";
    $params_top = [$tahun_ini, $sekolah];
}
$stmt_top = $conn->prepare($sql_top);
$stmt_top->execute($params_top);
$top_labels = [];
$top_data = [];
while($row = $stmt_top->fetch()){
    $top_labels[] = strlen($row['label']) > 15 ? substr($row['label'], 0, 15).'...' : $row['label']; // Singkat nama panjang
    $top_data[] = $row['total'];
}

// 4. GAUGE METER (Rata-rata Penyerapan Cuti)
// [PERBAIKAN ERROR DI SINI]: Menambahkan alias 'p' pada tabel pegawai
$sql_avg = "SELECT AVG(cuti_terpakai) FROM pegawai p WHERE status_aktif='aktif' $where_sekolah";
$stmt_avg = $conn->prepare($sql_avg);
$stmt_avg->execute($params_sekolah);
$avg_usage = round($stmt_avg->fetchColumn(), 1); // Misal: 4.5 hari
$avg_percent = ($avg_usage / 12) * 100; // Asumsi kuota 12 hari
$avg_percent = $avg_percent > 100 ? 100 : $avg_percent;

// 5. LIST UPCOMING LEAVES (7 Hari Kedepan)
$sql_upcoming = "SELECT p.nama, p.sekolah, pc.jenis_cuti, pc.tgl_mulai 
                 FROM pengajuan_cuti pc JOIN pegawai p ON pc.pegawai_id = p.id 
                 WHERE pc.status='disetujui' 
                 AND pc.tgl_mulai BETWEEN CURDATE() + INTERVAL 1 DAY AND CURDATE() + INTERVAL 7 DAY 
                 $where_sekolah ORDER BY pc.tgl_mulai ASC LIMIT 5";
$stmt_upcoming = $conn->prepare($sql_upcoming);
$stmt_upcoming->execute($params_sekolah);
$list_upcoming = $stmt_upcoming->fetchAll();

// 6. LIST WHO IS AWAY (Hari Ini)
$sql_away = "SELECT p.nama, p.sekolah, pc.jenis_cuti, pc.tgl_selesai 
             FROM pengajuan_cuti pc JOIN pegawai p ON pc.pegawai_id = p.id 
             WHERE pc.status='disetujui' AND CURDATE() BETWEEN pc.tgl_mulai AND pc.tgl_selesai 
             $where_sekolah ORDER BY pc.tgl_selesai ASC LIMIT 5";
$stmt_away = $conn->prepare($sql_away);
$stmt_away->execute($params_sekolah);
$list_away = $stmt_away->fetchAll();
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-5 hover:shadow-md transition group">
        <div class="w-16 h-16 rounded-2xl bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition flex items-center justify-center text-2xl shadow-sm">
            <i class="fa-solid fa-users"></i>
        </div>
        <div>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-wider">Total Guru-KS</p>
            <h3 class="text-3xl font-extrabold text-slate-800"><?= $total_pegawai ?></h3>
            <p class="text-xs text-slate-400 mt-1">Data Guru-KS Aktif</p>
        </div>
    </div>
    
    <a href="<?= $link_action ?>" class="bg-white p-6 rounded-2xl shadow-sm border-l-4 border-orange-500 flex items-center gap-5 hover:shadow-md transition group cursor-pointer relative overflow-hidden">
        <div class="absolute right-0 top-0 p-4 opacity-10 text-orange-500 transform translate-x-2 -translate-y-2">
            <i class="fa-solid fa-bell text-8xl"></i>
        </div>
        <div class="w-16 h-16 rounded-2xl bg-orange-50 text-orange-600 group-hover:bg-orange-600 group-hover:text-white transition flex items-center justify-center text-2xl shadow-sm z-10">
            <i class="fa-solid <?= $role == 'admin_dinas' ? 'fa-inbox' : 'fa-hourglass-half' ?>"></i>
        </div>
        <div class="z-10">
            <p class="text-slate-500 text-xs font-bold uppercase tracking-wider text-orange-600"><?= $label_action ?></p>
            <h3 class="text-3xl font-extrabold text-slate-800"><?= $need_action ?></h3>
            <p class="text-xs text-slate-400 mt-1"><?= $desc_action ?></p>
        </div>
    </a>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-5 hover:shadow-md transition group">
        <div class="w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white transition flex items-center justify-center text-2xl shadow-sm">
            <i class="fa-solid fa-plane-departure"></i>
        </div>
        <div>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-wider">Sedang Cuti</p>
            <h3 class="text-3xl font-extrabold text-slate-800"><?= $jml_away ?></h3>
            <p class="text-xs text-slate-400 mt-1">Tidak ada di kantor hari ini</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="font-bold text-slate-800 text-lg"><i class="fa-solid fa-chart-line mr-2 text-blue-600"></i> Tren Cuti Bulanan</h3>
                <p class="text-xs text-slate-400">Statistik Guru-KS cuti tahun <?= $tahun_ini ?></p>
            </div>
        </div>
        <div class="h-80 w-full">
            <canvas id="chartTrend"></canvas>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
        <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
            <h3 class="font-bold text-slate-800 text-sm"><i class="fa-solid fa-user-clock mr-2 text-emerald-600"></i> Siapa Cuti Hari Ini?</h3>
            <span class="text-[10px] bg-emerald-100 text-emerald-700 px-2 py-1 rounded-full font-bold"><?= tgl_indo(date('Y-m-d')) ?></span>
        </div>
        <div class="flex-1 overflow-y-auto custom-scrollbar p-0">
            <?php if(count($list_away) > 0): ?>
                <div class="divide-y divide-slate-50">
                    <?php foreach($list_away as $away): ?>
                    <div class="p-4 hover:bg-slate-50 transition flex items-start gap-3">
                        <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-[10px] border border-slate-200 shrink-0">
                            <?= strtoupper(substr($away['nama'], 0, 2)) ?>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-700 line-clamp-1"><?= $away['nama'] ?></p>
                            <p class="text-[10px] text-slate-400 mb-1 line-clamp-1"><?= $away['sekolah'] ?></p>
                            <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold bg-blue-50 text-blue-600 border border-blue-100">
                                <?= $away['jenis_cuti'] ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="h-full flex flex-col items-center justify-center p-8 text-center">
                    <i class="fa-regular fa-face-smile text-3xl text-slate-300 mb-2"></i>
                    <p class="text-xs text-slate-400">Semua Guru-KS hadir.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col">
        <h3 class="font-bold text-slate-800 text-sm mb-1">Komposisi Jenis Cuti</h3>
        <p class="text-xs text-slate-400 mb-4">Persentase alasan ketidakhadiran.</p>
        <div class="flex-1 flex items-center justify-center relative h-64">
            <?php if(count($pie_data) > 0): ?>
                <canvas id="chartPie"></canvas>
            <?php else: ?>
                <div class="h-full flex items-center justify-center text-xs text-slate-400 italic">Belum ada data cuti.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-6 flex flex-col">
        
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex-1">
            <div class="flex justify-between items-start mb-2">
                <div>
                    <h3 class="font-bold text-slate-800 text-sm">Rata-rata Penyerapan</h3>
                    <p class="text-xs text-slate-400">Penggunaan kuota cuti tahunan.</p>
                </div>
                <div class="text-right">
                    <span class="text-2xl font-extrabold text-blue-600"><?= $avg_usage ?></span>
                    <span class="text-xs text-slate-400 font-medium">/ 12 Hari</span>
                </div>
            </div>
            <div class="relative h-32 flex items-center justify-center">
                 <canvas id="chartGauge"></canvas>
                 <div class="absolute bottom-4 flex flex-col items-center">
                     <span class="text-xl font-bold text-slate-700"><?= number_format($avg_percent, 0) ?>%</span>
                     <span class="text-[10px] text-slate-400 uppercase tracking-widest">Terpakai</span>
                 </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex-1">
            <div class="p-4 border-b border-slate-100 bg-yellow-50/50">
                <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                    <i class="fa-regular fa-calendar-days text-yellow-600"></i> Akan Cuti (7 Hari)
                </h3>
            </div>
            <div class="p-0">
                <?php if(count($list_upcoming) > 0): ?>
                    <?php foreach($list_upcoming as $up): ?>
                    <div class="p-3 border-b border-slate-50 last:border-0 flex justify-between items-center hover:bg-slate-50 transition">
                        <div>
                            <p class="text-xs font-bold text-slate-700"><?= $up['nama'] ?></p>
                            <p class="text-[10px] text-slate-400"><?= tgl_indo($up['tgl_mulai']) ?></p>
                        </div>
                        <span class="text-[9px] font-bold px-2 py-1 rounded bg-slate-100 text-slate-600 border border-slate-200"><?= $up['jenis_cuti'] ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-6 text-center text-xs text-slate-400 italic">
                        Tidak ada cuti minggu depan.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col">
        <h3 class="font-bold text-slate-800 text-sm mb-1"><?= $judul_top ?></h3>
        <p class="text-xs text-slate-400 mb-4">Ranking frekuensi pengajuan cuti.</p>
        <div class="flex-1 relative h-64">
            <?php if(count($top_data) > 0): ?>
                <canvas id="chartTop"></canvas>
            <?php else: ?>
                <div class="h-full flex items-center justify-center text-xs text-slate-400 italic">Belum ada data.</div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.color = '#64748b';

    // 1. Chart Tren (Line)
    const ctxTrend = document.getElementById('chartTrend').getContext('2d');
    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: <?= json_encode($label_bulan_indo) ?>,
            datasets: [{
                label: 'Jumlah Cuti',
                data: <?= $json_trend ?>,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                borderWidth: 3,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#2563eb',
                pointRadius: 4,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, grid: { borderDash: [4, 4] }, ticks: { stepSize: 1 } }, x: { grid: { display: false } } }
        }
    });

    // 2. Chart Pie (Komposisi)
    <?php if(count($pie_data) > 0): ?>
    const ctxPie = document.getElementById('chartPie').getContext('2d');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($pie_labels) ?>,
            datasets: [{
                data: <?= json_encode($pie_data) ?>,
                backgroundColor: <?= json_encode($colors) ?>,
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } }
            },
            cutout: '65%'
        }
    });
    <?php endif; ?>

    // 3. Chart Horizontal Bar (Top 5)
    <?php if(count($top_data) > 0): ?>
    const ctxTop = document.getElementById('chartTop').getContext('2d');
    new Chart(ctxTop, {
        type: 'bar',
        data: {
            labels: <?= json_encode($top_labels) ?>,
            datasets: [{
                label: 'Jumlah Cuti',
                data: <?= json_encode($top_data) ?>,
                backgroundColor: '#3b82f6',
                borderRadius: 4,
                barThickness: 20
            }]
        },
        options: {
            indexAxis: 'y', // Membuat Horizontal
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { display: false }, y: { grid: { display: false } } }
        }
    });
    <?php endif; ?>

    // 4. Gauge Meter (Semi Circle Doughnut)
    const ctxGauge = document.getElementById('chartGauge').getContext('2d');
    const val = <?= $avg_percent ?>;
    new Chart(ctxGauge, {
        type: 'doughnut',
        data: {
            labels: ['Terpakai', 'Sisa'],
            datasets: [{
                data: [val, 100 - val],
                backgroundColor: [val > 75 ? '#ef4444' : (val > 50 ? '#f59e0b' : '#10b981'), '#f1f5f9'],
                borderWidth: 0,
                circumference: 180, // Setengah Lingkaran
                rotation: 270 // Mulai dari arah jam 9
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            cutout: '80%'
        }
    });
</script>