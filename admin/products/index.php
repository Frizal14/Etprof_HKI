<?php
/**
 * admin/products/index.php
 * Halaman daftar produk (READ).
 * Disesuaikan: Menambahkan kolom "Ukuran/Varian" dengan menggunakan GROUP_CONCAT.
 */

// Pastikan sesi dimulai jika belum (Walaupun harusnya dari dashboard.php)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Variabel global dari dashboard.php
$db_koneksi = $GLOBALS['koneksi'] ?? null;
// Mengambil path URL browser dari global
$uploads_path_base = $GLOBALS['uploads_path'] ?? '/e-commerce_sederhana/uploads/product_images/'; 
if (substr($uploads_path_base, -1) !== '/') {
    $uploads_path_base .= '/';
}

$router_path = 'dashboard.php'; 
$result = false; 

// --- LOGIKA PENCARIAN & FILTER ---
$search_query = $_GET['search'] ?? '';
$filter_category = $_GET['category'] ?? '';

// Ambil semua kategori untuk filter dropdown
$categories_list = [];
if ($db_koneksi) {
    $cat_result = $db_koneksi->query("SELECT id, name FROM categories ORDER BY name ASC");
    if ($cat_result) {
        $categories_list = $cat_result->fetch_all(MYSQLI_ASSOC);
    }
}

// Fungsi pembantu untuk memotong deskripsi
function truncate_description($text, $limit = 50) {
    if (strlen($text) > $limit) {
        // Menggunakan substr secara aman
        return substr(strip_tags($text), 0, $limit) . '...';
    }
    return $text;
}

// Cek keamanan koneksi dan ambil data produk
$products = [];
$error_fetch = '';

if (!$db_koneksi || !($db_koneksi instanceof mysqli) || ($db_koneksi->connect_error ?? false)) {
    $error_fetch = '<div class="alert alert-danger">⚠️ Kesalahan Database: Koneksi database tidak tersedia.</div>';
} else {
    $where_conditions = [];
    
    // Logika Pencarian
    if (!empty($search_query)) {
        $search_term = $db_koneksi->real_escape_string($search_query);
        $where_conditions[] = "p.name LIKE '%$search_term%'";
    }
    
    // Logika Filter Kategori
    if (!empty($filter_category) && is_numeric($filter_category)) {
        $category_id = intval($filter_category);
        $where_conditions[] = "p.category_id = $category_id";
    }

    $where_clause = !empty($where_conditions) ? ' WHERE ' . implode(' AND ', $where_conditions) : '';


    // 🔥 PERUBAHAN SQL: Menambahkan GROUP_CONCAT untuk mengambil semua ukuran/varian
    $sql = "SELECT 
                p.id, 
                p.name, 
                p.price, 
                p.image_path, 
                p.description,
                c.name AS category_name,
                COALESCE(SUM(pv.stock), 0) AS total_stock_varian,
                GROUP_CONCAT(DISTINCT pv.size ORDER BY FIELD(pv.size, 'S', 'M', 'L', 'XL', 'XXL', 'All Size')) AS sizes_available  -- 🔥 AMBIL SEMUA UKURAN
            FROM 
                products p
            LEFT JOIN 
                categories c ON p.category_id = c.id
            LEFT JOIN
                product_variants pv ON p.id = pv.product_id
            {$where_clause}
            GROUP BY 
                p.id, p.name, p.price, p.image_path, p.description, c.name 
            ORDER BY 
                p.id DESC";
    
    $result = $db_koneksi->query($sql);

    if ($result) {
        $products = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error_fetch = '<div class="alert alert-danger">Gagal mengambil data produk: ' . htmlspecialchars($db_koneksi->error) . '</div>';
    }
}

// Menangani Flash Message
$message_html = '';
if (isset($_SESSION['message']) && is_array($_SESSION['message'])) {
    $type = htmlspecialchars($_SESSION['message']['type']);
    $text = $_SESSION['message']['text']; 
    $message_html = '<div class="alert alert-'. $type .' alert-dismissible fade show" role="alert">' . $text . 
                        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['message']);
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <h1 class="h3 fw-bold text-dark mb-0">
            <i data-feather="shopping-bag" class="me-2" style="width: 20px; height: 20px;"></i>
            Manajemen Produk
        </h1>
        <a href="<?php echo $router_path; ?>?page=products/create" class="btn btn-primary shadow-sm text-nowrap">
            <i data-feather="plus" style="width: 18px; height: 18px;"></i>
            Tambah Produk Baru
        </a>
    </div>

    <?php echo $message_html; ?>
    <?php echo $error_fetch; ?>

    <div class="card mb-4 p-3 shadow-sm">
        <form method="GET" action="<?php echo $router_path; ?>" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="products/index">
            
            <div class="col-md-5 col-lg-4">
                <label for="search" class="form-label small fw-bold mb-0">Cari Nama Produk:</label>
                <div class="input-group">
                    <span class="input-group-text"><i data-feather="search" style="width: 16px; height: 16px;"></i></span>
                    <input type="text" class="form-control form-control-sm" id="search" name="search" placeholder="Cari nama produk..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </div>
            
            <div class="col-md-4 col-lg-3">
                <label for="category" class="form-label small fw-bold mb-0">Filter Kategori:</label>
                <select class="form-select form-select-sm" id="category" name="category">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories_list as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo (intval($filter_category) === intval($cat['id'])) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">Terapkan Filter</button>
            </div>
            
            <div class="col-auto">
                <a href="<?php echo $router_path; ?>?page=products/index" class="btn btn-sm btn-secondary">Reset</a>
            </div>
        </form>
    </div>
    <div class="card shadow-lg mb-4">
        <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center py-3">
            <span class="d-flex align-items-center">
                <i data-feather="list" class="me-2" style="width: 18px; height: 18px;"></i>
                Daftar Produk
            </span>
            <span class="badge bg-primary text-white px-3 py-2 fw-bold rounded-pill">
                Total: <?php echo count($products); ?>
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="small fw-bold text-center" style="width: 50px;">ID</th>
                            <th class="small fw-bold text-center" style="width: 60px;">Foto</th>
                            <th class="small fw-bold">Nama Produk</th>
                            <th class="small fw-bold">Kategori</th> 
                            <th class="small fw-bold">Harga Awal</th> <th class="small fw-bold">Ukuran/Varian</th> <th class="small fw-bold text-center">Total Stok</th>
                            <th class="small fw-bold d-none d-lg-table-cell">Deskripsi</th> 
                            <th class="small fw-bold text-center" style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (!empty($products)): 
                            foreach($products as $row):
                                $total_stock = $row['total_stock_varian']; 
                                $sizes_str = htmlspecialchars($row['sizes_available'] ?? '');

                                // Menentukan URL gambar
                                $image_file = htmlspecialchars($row['image_path']);
                                $image_src = (!empty($image_file) && $image_file != '0') 
                                    ? $uploads_path_base . $image_file
                                    : 'https://via.placeholder.com/50/6C757D/F8F9FA?text=No';
                        ?>
                        <tr>
                            <td class="text-center small text-muted"><?php echo htmlspecialchars($row['id']); ?></td>
                            <td class="text-center">
                                <img src="<?php echo $image_src; ?>" 
                                            alt="<?php echo htmlspecialchars($row['name']); ?>" 
                                            style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;"
                                            onerror="this.onerror=null;this.src='https://via.placeholder.com/50?text=Error';">
                            </td>
                            <td class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?></span></td> 
                            <td class="text-nowrap"><span class="fw-bold text-success">Rp <?php echo number_format($row['price'], 0, ',', '.'); ?></span></td>
                            
                            <td>
                                <?php if (!empty($sizes_str)): ?>
                                    <?php 
                                    // Pecah string ukuran menjadi array dan tampilkan sebagai badge
                                    $sizes = explode(',', $sizes_str);
                                    $count = 0;
                                    foreach($sizes as $size): 
                                        if ($count >= 4) { // Batasi hanya 4 badge
                                            echo '<span class="badge bg-light text-secondary me-1 my-1">...+</span>';
                                            break;
                                        }
                                    ?>
                                        <span class="badge bg-info text-dark me-1 my-1"><?php echo trim($size); ?></span>
                                    <?php 
                                        $count++;
                                    endforeach; ?>
                                <?php else: ?>
                                    <span class="badge bg-danger">Belum Ada</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <span class="badge bg-<?php echo ($total_stock > 10) ? 'success' : (($total_stock > 0) ? 'warning' : 'danger'); ?>">
                                    <?php echo htmlspecialchars($total_stock); ?>
                                </span>
                            </td>
                            
                            <td class="small text-muted d-none d-lg-table-cell"><?php echo htmlspecialchars(truncate_description($row['description'])); ?></td> 
                            
                            <td class="text-nowrap text-center">
                                <a href="<?php echo $router_path; ?>?page=products/view&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info text-white" title="Lihat Detail">
                                    <i data-feather="eye" style="width: 14px; height: 14px;"></i> 
                                </a>
                                
                                <a href="<?php echo $router_path; ?>?page=products/edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning text-dark" title="Edit Produk (dan Varian)">
                                    <i data-feather="edit-2" style="width: 14px; height: 14px;"></i> 
                                </a>
                                
                                <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                            title="Hapus Produk"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteProductModal"
                                            data-id="<?php echo $row['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($row['name']); ?>">
                                    <i data-feather="trash-2" style="width: 14px; height: 14px;"></i>
                                </button>
                            </td>
                        </tr>
                        <?php 
                            endforeach; 
                        else:
                        ?>
                        <tr>
                            <td colspan="9" class="text-center"> <div class="p-3 my-2 bg-light rounded text-muted">
                                    <i data-feather="info" class="me-1" style="width: 16px; height: 16px;"></i>
                                    <?php 
                                    if ($db_koneksi && ($db_koneksi->error ?? false)):
                                        echo "❌ Terjadi kesalahan saat mengambil data produk atau database error: " . htmlspecialchars($db_koneksi->error);
                                    else:
                                        echo "Belum ada produk yang ditambahkan. Silakan klik tombol **Tambah Produk Baru** di atas.";
                                    endif;
                                    ?>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteProductModalLabel">Konfirmasi Penghapusan Produk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menghapus produk **<span id="productName" class="fw-bold"></span>** (ID: <span id="productId" class="fw-bold"></span>)? Tindakan ini akan **menghapus semua varian ukuran dan stok** yang terkait dan tidak dapat dibatalkan.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form id="deleteForm" method="POST" action="<?php echo $router_path; ?>?page=products/delete" class="d-inline">
                    <input type="hidden" name="id" id="deleteProductIdInput">
                    <button type="submit" class="btn btn-danger">Ya, Hapus Permanen</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inisialisasi Feather Icons
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        // Logika untuk Delete Modal
        const deleteModal = document.getElementById('deleteProductModal');
        const deleteProductIdInput = document.getElementById('deleteProductIdInput');
        const productNameSpan = document.getElementById('productName');
        const productIdSpan = document.getElementById('productId');

        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const productId = button.getAttribute('data-id');
                const productName = button.getAttribute('data-name');
                
                productNameSpan.textContent = productName;
                productIdSpan.textContent = productId;

                // Set nilai ID ke input form POST
                deleteProductIdInput.value = productId;
            });
        }
    });
</script>