<?php
// Proteksi: Hanya Admin Dinas
if($_SESSION['role'] != 'admin_dinas') { 
    echo "<script>window.location='index.php';</script>"; 
    exit; 
}

// Tambahkan CDN SweetAlert2
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

// --- LOGIKA PROSES PERSETUJUAN (UPDATE DATABASE) ---
if(isset($_POST['proses_persetujuan'])) {
    $id_pengajuan = $_POST['id_pengajuan'];
    $aksi = $_POST['aksi']; // 'setuju' atau 'tolak'
    
    if($aksi == 'setuju') {
        $nomor_input = $_POST['nomor_input']; // Input manual dari modal
        
        // Generate Token Unik untuk Validasi QR (Agar ID tidak terekspos)
        $token = bin2hex(random_bytes(16)); // 32 karakter hex acak
        
        // Update Status, Nomor Input, Tanggal Disetujui, dan Token
        $sql = "UPDATE pengajuan_cuti SET 
                status = 'disetujui', 
                nomor_surat_input = ?, 
                tgl_disetujui = CURDATE(),
                token = ?,
                catatan_perbaikan = NULL 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$nomor_input, $token, $id_pengajuan]);
        
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire('Disetujui!', 'Pengajuan telah disetujui. Token Validasi & Surat berhasil diterbitkan.', 'success');
            });
        </script>";
        
    } elseif($aksi == 'tolak') {
        $catatan = $_POST['catatan_perbaikan'];
        
        // Reset token jika ada, set status ditolak
        $sql = "UPDATE pengajuan_cuti SET 
                status = 'ditolak', 
                catatan_perbaikan = ?,
                tgl_disetujui = NULL,
                token = NULL
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$catatan, $id_pengajuan]);
        
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire('Ditolak', 'Pengajuan ditolak. Catatan perbaikan telah dikirim ke Sekolah.', 'warning');
            });
        </script>";
    }
}

// --- PENGAMBILAN DATA ---
$sekolah_filter = isset($_GET['sekolah']) ? $_GET['sekolah'] : '';
$cari = isset($_GET['cari']) ? $_GET['cari'] : '';

// Ambil pengajuan status pending
$sql_pending = "SELECT pc.*, p.nama, p.nip, p.sekolah, p.jabatan 
                FROM pengajuan_cuti pc 
                JOIN pegawai p ON pc.pegawai_id = p.id 
                WHERE pc.status = 'pending'";

if(!empty($sekolah_filter)) {
    $sql_pending .= " AND p.sekolah = '$sekolah_filter'";
}
if(!empty($cari)) {
    $sql_pending .= " AND (p.nama LIKE '%$cari%' OR p.nip LIKE '%$cari%')";
}
$sql_pending .= " ORDER BY pc.tgl_mulai ASC";

$list_pending = $conn->query($sql_pending)->fetchAll();
$list_sekolah = $conn->query("SELECT DISTINCT sekolah FROM pegawai ORDER BY sekolah ASC")->fetchAll();
?>

<div class="space-y-6">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
            <div>
                <h3 class="font-bold text-xl text-slate-800">Verifikasi Pengajuan Cuti</h3>
                <p class="text-sm text-slate-400">Proses pengajuan masuk dari sekolah-sekolah.</p>
            </div>
            
            <form action="" method="GET" class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                <input type="hidden" name="page" value="persetujuan">
                <select name="sekolah" onchange="this.form.submit()" class="border border-slate-300 rounded-xl p-2.5 text-sm bg-slate-50 focus:border-blue-500 outline-none">
                    <option value="">-- Semua Sekolah --</option>
                    <?php foreach($list_sekolah as $s): ?>
                        <option value="<?= $s['sekolah'] ?>" <?= $sekolah_filter == $s['sekolah'] ? 'selected' : '' ?>><?= $s['sekolah'] ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="relative w-full md:w-64">
                    <span class="absolute left-3 top-3 text-slate-400"><i class="fa-solid fa-search"></i></span>
                    <input type="text" name="cari" value="<?= $cari ?>" placeholder="Cari Guru-KS / NIP..." class="w-full pl-9 pr-4 py-2.5 border border-slate-300 rounded-xl text-sm focus:outline-none focus:border-blue-500">
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-slate-600">
                <thead class="text-xs text-slate-500 uppercase bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-4 font-semibold">Nama Guru-KS</th>
                        <th class="px-6 py-4 font-semibold">Jenis & Durasi</th>
                        <th class="px-6 py-4 font-semibold w-1/4">Alasan</th>
                        <th class="px-6 py-4 font-semibold text-center">Bukti</th>
                        <th class="px-6 py-4 font-semibold text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(count($list_pending) == 0): ?>
                        <tr><td colspan="5" class="p-8 text-center text-slate-400 italic">Tidak ada pengajuan pending saat ini.</td></tr>
                    <?php endif; ?>

                    <?php foreach($list_pending as $row): ?>
                    <tr class="hover:bg-slate-50/80 transition">
                        <td class="px-6 py-4 valign-top">
                            <div class="font-bold text-slate-800"><?= $row['nama'] ?></div>
                            <div class="text-xs text-slate-500 font-mono"><?= $row['nip'] ?></div>
                            <div class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-700 text-[10px] font-bold border border-blue-100">
                                <i class="fa-solid fa-school"></i> <?= $row['sekolah'] ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 valign-top">
                            <div class="font-bold text-slate-700 uppercase text-xs mb-1"><?= $row['jenis_cuti'] ?></div>
                            <div class="text-xs text-slate-500 flex items-center gap-1">
                                <i class="fa-regular fa-calendar"></i>
                                <?= date('d M', strtotime($row['tgl_mulai'])) ?> - <?= date('d M Y', strtotime($row['tgl_selesai'])) ?>
                            </div>
                            <div class="text-xs font-bold text-blue-600 mt-1"><?= $row['durasi'] ?> Hari Kerja</div>
                        </td>
                        <td class="px-6 py-4 valign-top">
                            <p class="text-xs italic text-slate-500 bg-slate-50 p-2 rounded border border-slate-100">"<?= $row['alasan'] ?>"</p>
                        </td>
                        <td class="px-6 py-4 text-center valign-top">
                            <?php if($row['file_bukti']): ?>
                                <a href="uploads/<?= $row['file_bukti'] ?>" target="_blank" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 text-xs font-bold bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg border border-blue-100 transition">
                                    <i class="fa-solid fa-paperclip"></i> Lihat
                                </a>
                            <?php else: ?>
                                <span class="text-xs text-slate-400 italic">Tidak ada</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center valign-top">
                            <div class="flex flex-col items-center gap-2">
                                <button onclick="modalSetuju(<?= $row['id'] ?>, '<?= $row['jenis_cuti'] ?>')" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg shadow-sm text-xs font-bold transition flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-check"></i> Setuju
                                </button>
                                <button onclick="modalTolak(<?= $row['id'] ?>)" class="w-full bg-white text-red-600 hover:bg-red-50 border border-red-200 px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center justify-center gap-2">
                                    <i class="fa-solid fa-xmark"></i> Tolak
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- FORM HIDDEN UNTUK PROSES -->
<form id="formProses" method="POST" class="hidden">
    <input type="hidden" name="proses_persetujuan" value="1">
    <input type="hidden" name="id_pengajuan" id="input_id">
    <input type="hidden" name="aksi" id="input_aksi">
    <input type="hidden" name="nomor_input" id="input_nomor">
    <input type="hidden" name="catatan_perbaikan" id="input_catatan">
</form>

<script>
    function modalSetuju(id, jenis) {
        // --- 1. Logika Penomoran Dinamis ---
        let prefix = "";
        let suffix = "/ Disdikpora";
        
        // Tentukan kode klasifikasi berdasarkan jenis cuti
        if(jenis === 'Cuti Tahunan') prefix = "800.1.11.4 / ";
        else if(jenis === 'Cuti Melahirkan') prefix = "800.1.11.5 / ";
        else if(jenis === 'Cuti Sakit') prefix = "800.1.11.6 / ";
        else prefix = "000.1.12.7 / "; // Default/Pengantar

        // --- 2. Tampilan Modal Profesional ---
        Swal.fire({
            title: '<span class="text-slate-700 text-xl font-bold">Terbitkan Surat Cuti?</span>',
            // Gunakan HTML Tailwind untuk layout yang rapi
            html: `
                <div class="text-left space-y-4 px-1">
                    <div class="bg-blue-50 border border-blue-100 rounded-lg p-3 flex items-start gap-3 mt-2">
                        <div class="bg-blue-100 text-blue-600 rounded-full p-1.5 shrink-0 mt-0.5">
                             <i class="fa-solid fa-file-signature text-xs"></i>
                        </div>
                        <div>
                            <p class="text-sm text-slate-700 font-medium leading-tight">Menyetujui: <span class="text-blue-700 font-bold">${jenis}</span></p>
                            <p class="text-[11px] text-slate-500 mt-1">Sistem akan membuat Surat Digital & Token Validasi.</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1.5 ml-1">Nomor Surat (Urut)</label>
                        <div class="flex items-stretch w-full rounded-lg border border-slate-300 overflow-hidden focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-100 transition-all shadow-sm">
                            
                            <div class="bg-slate-100 px-3 py-2.5 border-r border-slate-200 flex items-center">
                                <span class="text-sm font-mono font-bold text-slate-500 whitespace-nowrap select-none">${prefix}</span>
                            </div>
                            
                            <input type="number" id="nomor_manual" 
                                class="flex-1 bg-white px-3 py-2.5 text-slate-800 text-sm font-bold outline-none placeholder:text-slate-300 placeholder:font-normal" 
                                placeholder="001" autocomplete="off">

                            <div class="bg-slate-100 px-3 py-2.5 border-l border-slate-200 flex items-center">
                                <span class="text-sm font-mono font-bold text-slate-500 whitespace-nowrap select-none">${suffix}</span>
                            </div>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1.5 text-right italic">*Masukkan angka tengah saja, format lain otomatis.</p>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Ya, Terbitkan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#059669', // Emerald-600 yang lebih gelap sedikit agar kontras
            cancelButtonColor: '#94a3b8', // Slate-400
            
            // Auto-focus ke input saat modal terbuka
            didOpen: () => {
                const input = Swal.getPopup().querySelector('#nomor_manual');
                if(input) {
                    input.focus();
                    // Opsional: Tekan Enter untuk submit
                    input.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') Swal.clickConfirm();
                    });
                }
            },

            // Validasi Input
            preConfirm: () => {
                const nomor = Swal.getPopup().querySelector('#nomor_manual').value;
                if (!nomor) {
                    Swal.showValidationMessage('⚠️ Mohon isi nomor urut surat terlebih dahulu!');
                }
                return nomor;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Masukkan data ke form hidden dan submit
                document.getElementById('input_id').value = id;
                document.getElementById('input_aksi').value = 'setuju';
                document.getElementById('input_nomor').value = result.value;
                document.getElementById('formProses').submit();
            }
        });
    }

    function modalTolak(id) {
        Swal.fire({
            title: 'Tolak Pengajuan?',
            input: 'textarea',
            inputLabel: 'Catatan Perbaikan (Wajib)',
            inputPlaceholder: 'Contoh: Mohon upload ulang bukti cuti yang lebih jelas...',
            inputAttributes: {
                'aria-label': 'Catatan perbaikan'
            },
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Tolak & Minta Revisi',
            confirmButtonColor: '#ef4444',
            cancelButtonText: 'Batal',
            inputValidator: (value) => {
                if (!value) {
                    return 'Anda harus menuliskan catatan perbaikan!'
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('input_id').value = id;
                document.getElementById('input_aksi').value = 'tolak';
                document.getElementById('input_catatan').value = result.value;
                document.getElementById('formProses').submit();
            }
        });
    }
</script>