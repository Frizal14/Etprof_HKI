<?php
/**
 * admin/categories/index.php
 * Menampilkan daftar kategori (READ) dan pesan status.
 * 🔥 PERBAIKAN: Mengganti list-group menjadi tabel responsif.
 * 🔥 PERBAIKAN: Mengganti confirm() menjadi Bootstrap Modal untuk aksi Delete.
 * 🔥 PERBAIKAN: Menggunakan Feather Icons yang lebih konsisten (asumsi ada script JS untuk inisialisasi Feather Icons).
 * 🆕 BARU: Menambahkan fungsionalitas Pencarian (Nama Kategori).
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Ambil koneksi dari global scope dashboard.php
$koneksi = $GLOBALS['koneksi'] ?? null; 

// Variabel router path harus tersedia dari scope dashboard.php
$router_path = $router_path ?? 'dashboard.php'; 

// --- LOGIKA PENCARIAN ---
$search_query = $_GET['search'] ?? '';

// --- LOGIKA MENANGANI FLASH MESSAGE DARI $_SESSION ---
$message_html = '';
if (isset($_SESSION['message']) && is_array($_SESSION['message'])) {
    $type = htmlspecialchars($_SESSION['message']['type']);
    $text = $_SESSION['message']['text']; // Text mungkin mengandung HTML aman (e.g., **bold**)
    // Perbaikan: Pastikan type alert sesuai dengan kelas Bootstrap
    $alert_class = ($type == 'success' || $type == 'danger' || $type == 'info' || $type == 'warning') ? $type : 'secondary';
    
    $message_html = '<div class="alert alert-'. $alert_class .' alert-dismissible fade show" role="alert">' . $text . 
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['message']);
}

// --- LOGIKA AMBIL DAFTAR KATEGORI (DENGAN PENCARIAN) ---
$categories = [];
$error_fetch = '';

if (!$koneksi) {
    // Menampilkan error jika koneksi tidak tersedia
    $error_fetch = '<div class="alert alert-danger">Kesalahan Database: Koneksi database tidak tersedia.</div>';
} else {
    $where_clause = '';
    // Logika Pencarian
    if (!empty($search_query)) {
        $search_term = $koneksi->real_escape_string($search_query);
        $where_clause = " WHERE name LIKE '%$search_term%'";
    }

    $sql = "SELECT id, name FROM categories {$where_clause} ORDER BY name ASC";
    
    $result = $koneksi->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // HTML escape nama kategori
            $row['name'] = htmlspecialchars($row['name']); 
            $categories[] = $row;
        }
    } else {
        $error_fetch = '<div class="alert alert-danger">Gagal mengambil data kategori: ' . htmlspecialchars($koneksi->error) . '</div>';
    }
}
?>

<style>
    /* Styling umum container */
    .container-fluid {
        padding: 0 1rem;
    }
    
    /* Styling Header Halaman */
    .page-header-container {
        border-bottom: 2px solid #e9ecef; /* Garis pemisah lembut */
        margin-bottom: 1.5rem;
    }

    /* Styling Input Pencarian */
    .card.shadow-sm form .input-group-text {
        background-color: #f8f9fa;
        border-right: none;
    }
    .card.shadow-sm form .form-control-sm {
        border-left: none;
    }

    /* Styling Tabel */
    .table-responsive {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05); /* Bayangan lembut untuk tabel */
        border-radius: 0.5rem;
        overflow: hidden; /* Memastikan sudut tabel dipotong */
    }
    .table-hover tbody tr:hover {
        background-color: #f2f7ff; /* Hover color yang sedikit lebih menonjol */
        transform: translateY(-1px); /* Efek angkat ringan saat hover */
        transition: all 0.2s ease-in-out;
    }

    /* Konsistensi ukuran font di header tabel */
    .table thead th {
        font-size: 0.85rem;
        color: #495057;
        text-transform: uppercase;
    }

    /* Konsistensi ukuran font di body tabel */
    .table tbody td {
        font-size: 0.95rem;
    }

    /* Menghilangkan margin bawah pada tabel di dalam card-body p-0 */
    .card-body .table {
        margin-bottom: 0;
    }
    
    /* Tombol Aksi */
    .btn-sm {
        padding: 0.3rem 0.5rem;
        font-size: 0.8rem;
    }
</style>

<div class="container-fluid py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 page-header-container pb-3">
        <h1 class="h3 fw-bold text-dark mb-0">
            <i data-feather="tag" class="me-2" style="width: 20px; height: 20px;"></i>
            Manajemen Kategori Produk
        </h1>
        <a href="<?php echo $router_path; ?>?page=categories/create" class="btn btn-primary shadow-sm text-nowrap">
            <i data-feather="plus" style="width: 18px; height: 18px;"></i>
            Tambah Baru
        </a>
    </div>

    <?php 
    // Tampilkan Flash Message
    echo $message_html; 
    // Tampilkan Error Fetch
    echo $error_fetch; 
    ?>

    <div class="card mb-4 p-3 shadow-sm border-0">
        <form method="GET" action="<?php echo $router_path; ?>" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="categories/index">
            
            <div class="col-md-5 col-lg-4">
                <label for="search" class="form-label small fw-bold mb-0">Cari Nama Kategori:</label>
                <div class="input-group">
                    <span class="input-group-text"><i data-feather="search" style="width: 16px; height: 16px;"></i></span>
                    <input type="text" class="form-control form-control-sm" id="search" name="search" placeholder="Cari nama kategori..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </div>
            
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">Cari</button>
            </div>
            
            <div class="col-auto">
                <a href="<?php echo $router_path; ?>?page=categories/index" class="btn btn-sm btn-secondary">Reset</a>
            </div>
        </form>
    </div>
    <div class="card shadow-lg border-0">
        <div class="card-header bg-dark text-white fw-bold py-3 d-flex justify-content-between align-items-center">
            <span class="d-flex align-items-center">
                <i data-feather="list" class="me-2" style="width: 18px; height: 18px;"></i>
                Daftar Kategori Tersedia
            </span>
            <span class="badge bg-primary text-white px-3 py-2 fw-bold rounded-pill">
                Total: <?php echo count($categories); ?>
            </span>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped table-sm align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th scope="col" class="text-secondary fw-bold small text-center" style="width: 80px;">ID</th>
                            <th scope="col" class="text-secondary fw-bold small">Nama Kategori</th>
                            <th scope="col" class="text-center text-secondary fw-bold small" style="width: 180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    <i data-feather="box" class="me-1" style="width: 20px; height: 20px;"></i>
                                    Belum ada kategori yang ditambahkan.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td class="text-center fw-bold small text-muted">#<?php echo $category['id']; ?></td>
                                    <td class="fw-bold text-dark"><?php echo $category['name']; ?></td>
                                    <td class="text-center text-nowrap">
                                        <a href="<?php echo $router_path; ?>?page=categories/edit&id=<?php echo $category['id']; ?>" 
                                           class="btn btn-sm btn-info text-white me-2" 
                                           title="Edit Kategori">
                                            <i data-feather="edit-2" style="width: 14px; height: 14px;"></i> 
                                            <span class="d-none d-sm-inline">Edit</span>
                                        </a>
                                        
                                        <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                                    title="Hapus Kategori"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal"
                                                    data-id="<?php echo $category['id']; ?>" 
                                                    data-name="<?php echo $category['name']; ?>">
                                            <i data-feather="trash-2" style="width: 14px; height: 14px;"></i>
                                            <span class="d-none d-sm-inline">Hapus</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Penghapusan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>PERINGATAN! Apakah Anda yakin ingin menghapus kategori **<span id="categoryName" class="fw-bold"></span>**?</p>
                <p class="text-danger fw-bold">Tindakan ini tidak dapat dibatalkan, dan akan menyebabkan error pada produk yang masih menggunakan kategori ini!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a id="confirmDelete" href="#" class="btn btn-danger">Ya, Hapus Permanen</a>
            </div>
        </div>
    </div>
</div>

<script>
    // Asumsi Feather Icons telah dimuat secara global di dashboard.php atau di sini
    document.addEventListener('DOMContentLoaded', function() {
        // Inisialisasi Feather Icons jika tersedia
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        // Logika untuk Delete Modal
        const deleteModal = document.getElementById('deleteModal');
        const confirmDelete = document.getElementById('confirmDelete');
        const categoryNameSpan = document.getElementById('categoryName');

        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const categoryId = button.getAttribute('data-id');
                const categoryName = button.getAttribute('data-name');
                
                categoryNameSpan.textContent = categoryName;

                // Set link Hapus Permanen
                const basePath = '<?php echo $router_path; ?>';
                confirmDelete.href = basePath + '?page=categories/delete&id=' + categoryId;
            });
        }
    });
</script>

<?php 
// Tutup koneksi jika ada
if (isset($koneksi) && $koneksi instanceof mysqli) {
    $koneksi->close(); 
}
?>