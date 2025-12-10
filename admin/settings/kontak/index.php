<?php
/**
 * admin/settings/kontak/index.php
 * Halaman utama CRUD Kontak Admin (READ: Daftar Kontak) dengan tampilan modern.
 */

// Pastikan koneksi tersedia (dari admin/dashboard.php -> kakek.php)
if (!isset($GLOBALS['koneksi'])) {
    die("Koneksi database tidak tersedia.");
}
$koneksi = $GLOBALS['koneksi'];
$router_path = $router_path ?? 'dashboard.php'; // Pastikan router path tersedia

// --- 1. PROSES DELETE ---
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    $id_hapus = (int)$_GET['id'];
    $delete_sql = "DELETE FROM admin_kontak_entri WHERE id = $id_hapus";
    
    // Perbaikan: Pastikan redirect menggunakan $router_path yang sudah ada
    if ($koneksi->query($delete_sql)) {
        $_SESSION['crud_success_toast'] = "Entri kontak berhasil dihapus!";
        header('Location: ' . $router_path . '?page=settings/kontak/index');
        exit;
    } else {
        $error_message = "Gagal menghapus entri: " . $koneksi->error;
    }
}

// --- 2. AMBIL DATA KONTAK ---
$kontak_list = [];
$sql_select = "SELECT * FROM admin_kontak_entri ORDER BY prioritas ASC, dibuat_pada DESC";
$result = $koneksi->query($sql_select);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $kontak_list[] = $row;
    }
}

// Helper function untuk icon (DIPERBAIKI: Mengembalikan array agar warna dapat digunakan di HTML)
function get_contact_icon($tipe) {
    switch (strtoupper($tipe)) {
        case 'WA': return ['icon' => 'fab fa-whatsapp', 'color' => 'text-success'];
        case 'EMAIL': return ['icon' => 'fas fa-envelope', 'color' => 'text-warning'];
        case 'IG': return ['icon' => 'fab fa-instagram', 'color' => 'text-danger'];
        case 'FB': return ['icon' => 'fab fa-facebook-f', 'color' => 'text-primary'];
        case 'TIKTOK': return ['icon' => 'fab fa-tiktok', 'color' => 'text-dark'];
        case 'TELP': return ['icon' => 'fas fa-phone-alt', 'color' => 'text-info'];
        default: return ['icon' => 'fas fa-info-circle', 'color' => 'text-muted'];
    }
}
?>

<style>
    /* Styling Kustom untuk Halaman Admin Kontak */
    .table thead th {
        font-weight: 700;
        color: #495057;
        background-color: #f8f9fa;
        vertical-align: middle;
    }
    .table tbody td {
        vertical-align: middle;
    }
    
    /* Tombol Aksi Modern */
    .btn-action {
        width: 35px;
        height: 35px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px; 
        transition: all 0.2s;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .btn-action:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    .btn-edit {
        background-color: #3498db !important; 
        border-color: #3498db !important;
    }
    .btn-delete {
        background-color: #e74c3c !important; 
        border-color: #e74c3c !important;
    }
    /* Mengatasi warna ikon di modal */
    .modal-header .btn-close-white {
        filter: invert(1);
    }
    /* Warna header modal (disesuaikan dengan warna admin dashboard) */
    .modal-header.bg-success {
        background-color: #27ae60 !important;
    }
    .modal-header.bg-warning {
        background-color: #f39c12 !important;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
    <h1 class="display-6 fw-bold text-primary"><i class="fas fa-headset me-2"></i> Kelola Daftar Kontak Toko</h1>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<div class="card shadow-lg border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center p-3">
        <h5 class="m-0 fw-bold text-secondary">Daftar Semua Saluran Komunikasi</h5>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tambahKontakModal">
            <i data-feather="plus-circle" class="feather me-1"></i> Tambah Kontak Baru
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th>Label Kontak</th>
                        <th>Tipe</th>
                        <th>Nilai Kontak / URL</th>
                        <th class="text-center" style="width: 10%;">Prioritas</th>
                        <th class="text-center" style="width: 10%;">Status</th>
                        <th class="text-center" style="width: 10%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($kontak_list)): $i = 1; ?>
                        <?php foreach ($kontak_list as $kontak): 
                            // Memanggil helper function yang sudah diperbarui
                            $icon_data = get_contact_icon($kontak['tipe_kontak']); 
                        ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><span class="fw-bold"><?php echo htmlspecialchars($kontak['nama_kontak']); ?></span></td>
                                <td>
                                    <i class="<?php echo $icon_data['icon']; ?> me-2 <?php echo $icon_data['color']; ?>"></i>
                                    <span class="small text-uppercase"><?php echo htmlspecialchars($kontak['tipe_kontak']); ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($kontak['nilai_kontak']); ?>
                                </td>
                                <td class="text-center fw-bold"><?php echo $kontak['prioritas']; ?></td>
                                <td class="text-center">
                                    <?php 
                                    echo $kontak['is_active'] 
                                        ? '<span class="badge bg-success py-2 px-3">Aktif</span>' 
                                        : '<span class="badge bg-secondary py-2 px-3">Nonaktif</span>'; 
                                    ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-action btn-edit text-white me-1 btn-edit-kontak"
                                            data-id="<?php echo $kontak['id']; ?>"
                                            data-nama="<?php echo htmlspecialchars($kontak['nama_kontak']); ?>"
                                            data-tipe="<?php echo htmlspecialchars($kontak['tipe_kontak']); ?>"
                                            data-nilai="<?php echo htmlspecialchars($kontak['nilai_kontak']); ?>"
                                            data-prioritas="<?php echo htmlspecialchars($kontak['prioritas']); ?>"
                                            data-active="<?php echo htmlspecialchars($kontak['is_active']); ?>"
                                            title="Edit">
                                        <i data-feather="edit-3" class="feather"></i>
                                    </button>
                                    
                                    <button class="btn btn-action btn-delete text-white btn-hapus" data-id="<?php echo $kontak['id']; ?>" title="Hapus">
                                        <i data-feather="trash-2" class="feather"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Belum ada entri kontak yang ditambahkan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="tambahKontakModal" tabindex="-1" aria-labelledby="tambahKontakModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white"> 
                <h5 class="modal-title" id="tambahKontakModalLabel"><i data-feather="plus-circle" class="feather me-2"></i> Tambah Entri Kontak</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formKontak" method="POST" action="<?php echo $router_path; ?>?page=settings/kontak/proses">
                <div class="modal-body">
                    <input type="hidden" name="id_kontak" id="id_kontak">
                    <input type="hidden" name="action_type" id="action_type" value="tambah">

                    <div class="mb-3">
                        <label for="nama_kontak" class="form-label">Label (Contoh: WA Support)</label>
                        <input type="text" class="form-control" id="nama_kontak" name="nama_kontak" required>
                    </div>

                    <div class="mb-3">
                        <label for="tipe_kontak" class="form-label">Tipe Saluran</label>
                        <select class="form-select" id="tipe_kontak" name="tipe_kontak" required>
                            <option value="">Pilih Tipe</option>
                            <option value="WA">WhatsApp (WA)</option>
                            <option value="Email">Email</option>
                            <option value="IG">Instagram (IG)</option>
                            <option value="FB">Facebook (FB)</option>
                            <option value="TikTok">TikTok</option>
                            <option value="Telp">Telepon / Hotline</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="nilai_kontak" class="form-label">Nilai / URL / Nomor</label>
                        <input type="text" class="form-control" id="nilai_kontak" name="nilai_kontak" required placeholder="Contoh: 0812xxxxxx atau https://instagram.com/toko">
                        <div class="form-text">Untuk WA, masukkan nomor diawali 08 (format Indonesia). Untuk Sosmed, masukkan URL lengkap.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="prioritas" class="form-label">Prioritas Tampilan</label>
                            <input type="number" class="form-control" id="prioritas" name="prioritas" value="99" min="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="is_active" class="form-label">Status</label>
                            <select class="form-select" id="is_active" name="is_active" required>
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-success" id="btnSubmitModal">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Helper untuk mengubah warna header modal
    const modalHeader = document.querySelector('#tambahKontakModal .modal-header');
    function setModalHeaderClass(type) {
        // Hapus semua kelas warna yang mungkin ada
        modalHeader.classList.remove('bg-success', 'bg-warning', 'bg-primary'); 
        
        if (type === 'tambah') {
            modalHeader.classList.add('bg-success'); // Hijau untuk Tambah
            // Memperbarui ikon di header modal
            modalTitle.innerHTML = '<i data-feather="plus-circle" class="feather me-2"></i> Tambah Entri Kontak';
        } else if (type === 'edit') {
            modalHeader.classList.add('bg-warning'); // Kuning/Oranye untuk Edit
            // Memperbarui ikon di header modal
            // (Judul akan diatur di dalam listener edit)
        }
    }
    
    // --- Logika SweetAlert Hapus ---
    document.querySelectorAll('.btn-hapus').forEach(button => {
        button.addEventListener('click', function() {
            const kontakId = this.dataset.id;
            
            Swal.fire({
                title: 'Yakin Hapus Data?',
                text: "Data kontak ini akan dihapus permanen. Aksi ini tidak dapat dibatalkan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '<?php echo $router_path; ?>?page=settings/kontak/index&action=hapus&id=' + kontakId;
                }
            });
        });
    });

    // --- Logika Modal Tambah/Edit ---
    const modalTitle = document.getElementById('tambahKontakModalLabel');
    const formKontak = document.getElementById('formKontak');
    const actionType = document.getElementById('action_type');
    const idKontak = document.getElementById('id_kontak');
    const btnSubmitModal = document.getElementById('btnSubmitModal');

    // 1. Reset form saat modal ditutup
    document.getElementById('tambahKontakModal').addEventListener('hidden.bs.modal', function () {
        setModalHeaderClass('tambah'); // Reset warna header
        formKontak.reset();
        actionType.value = 'tambah';
        idKontak.value = '';
        btnSubmitModal.textContent = 'Simpan Data';
        btnSubmitModal.classList.remove('btn-warning');
        btnSubmitModal.classList.add('btn-success');
        if (typeof feather !== 'undefined') { feather.replace(); } // Re-render feather icons
    });
    
    // Pastikan tombol 'Tambah Kontak Baru' juga memanggil reset dan setModalHeaderClass
    document.querySelector('[data-bs-target="#tambahKontakModal"]').addEventListener('click', function() {
        setModalHeaderClass('tambah');
        // Judul sudah diatur di fungsi setModalHeaderClass('tambah')
        if (typeof feather !== 'undefined') { feather.replace(); }
    });


    // 2. Logic Edit: Mengisi form dengan data yang dipilih
    document.querySelectorAll('.btn-edit-kontak').forEach(button => {
        button.addEventListener('click', function() {
            const data = this.dataset;

            // Isi Form
            idKontak.value = data.id;
            actionType.value = 'edit';
            document.getElementById('nama_kontak').value = data.nama;
            document.getElementById('tipe_kontak').value = data.tipe;
            document.getElementById('nilai_kontak').value = data.nilai;
            document.getElementById('prioritas').value = data.prioritas;
            document.getElementById('is_active').value = data.active;
            
            // Ubah Tampilan Modal
            modalTitle.innerHTML = '<i data-feather="edit-3" class="feather me-2"></i> Edit Entri Kontak (ID: ' + data.id + ')';
            btnSubmitModal.textContent = 'Perbarui Data';
            btnSubmitModal.classList.remove('btn-success');
            btnSubmitModal.classList.add('btn-warning');
            setModalHeaderClass('edit'); // Set warna header ke warning/edit

            // Tampilkan Modal
            const modal = new bootstrap.Modal(document.getElementById('tambahKontakModal'));
            modal.show();
            if (typeof feather !== 'undefined') { feather.replace(); } // Re-render feather icons
        });
    });
    
    // Inisialisasi awal Feather Icons
    if (typeof feather !== 'undefined') { feather.replace(); }
});
</script>