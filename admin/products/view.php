<?php
/**
 * admin/products/view.php
 * Halaman untuk Melihat Detail Produk (READ Detail).
 * 🔥 PERUBAHAN: Mengambil data varian (size, stock) dari tabel product_variants
 * dan menghapus referensi ke kolom products.stock.
 */

// Pastikan koneksi dan variabel global telah didefinisikan (dari dashboard.php)
$koneksi = $GLOBALS['koneksi'] ?? null; 
$router_path = 'dashboard.php';
// Path URL untuk menampilkan gambar di browser
$uploads_path_browser = $GLOBALS['uploads_path'] ?? '/e-commerce_sederhana/uploads/product_images/'; 
if (substr($uploads_path_browser, -1) !== '/') {
    $uploads_path_browser .= '/';
}


if (!$koneksi || $koneksi->connect_error) {
    echo '<div class="alert alert-danger">Kesalahan: Koneksi database tidak tersedia.</div>';
    exit;
}

// 1. Ambil ID Produk
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Menggunakan format flash message berbasis array
    $_SESSION['message'] = [
        'type' => 'danger',
        'text' => "ID Produk tidak valid."
    ];
    header('Location: ' . $router_path . '?page=products/index');
    exit;
}

$product_id = (int)$_GET['id'];
$product = null;
$product_variants = [];
$total_stock = 0;

// 2. Query Detail Produk dengan Kategori
$sql = "SELECT 
            p.*, 
            c.name AS category_name 
        FROM 
            products p
        LEFT JOIN 
            categories c ON p.category_id = c.id
        WHERE 
            p.id = ?";

$stmt = $koneksi->prepare($sql); 

if ($stmt === false) {
    echo '<div class="alert alert-danger">Gagal menyiapkan query produk: ' . htmlspecialchars($koneksi->error) . '</div>';
    exit;
}

$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Menggunakan format flash message berbasis array
    $_SESSION['message'] = [
        'type' => 'info',
        'text' => "Produk tidak ditemukan."
    ];
    if (ob_get_length()) { ob_clean(); } 
    header('Location: ' . $router_path . '?page=products/index');
    exit;
}
$product = $result->fetch_assoc();
$stmt->close();

// 🔥 3. Query Detail Varian Produk
$sql_variants = "SELECT 
                    id, 
                    size, 
                    stock 
                 FROM 
                    product_variants 
                 WHERE 
                    product_id = ?
                 ORDER BY FIELD(size, 'S', 'M', 'L', 'XL', 'XXL', 'All Size')"; // Urutkan ukuran secara logis

$stmt_variants = $koneksi->prepare($sql_variants);
if ($stmt_variants === false) {
    $error_variants = '<div class="alert alert-danger">Gagal menyiapkan query varian: ' . htmlspecialchars($koneksi->error) . '</div>';
} else {
    $stmt_variants->bind_param("i", $product_id);
    $stmt_variants->execute();
    $variants_result = $stmt_variants->get_result();
    $product_variants = $variants_result->fetch_all(MYSQLI_ASSOC);
    $stmt_variants->close();

    // Hitung total stok
    foreach ($product_variants as $variant) {
        $total_stock += $variant['stock'];
    }
}


// Menentukan URL Gambar
$image_file = htmlspecialchars($product['image_path']);
$image_src = (!empty($image_file) && $image_file != '0') 
    ? $uploads_path_browser . $image_file
    : 'https://via.placeholder.com/250x250/F8F9FA/6C757D?text=No+Image';

// Fungsi untuk format mata uang
function format_rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>

<style>
    :root {
        --primary-color: #0d6efd; /* Bootstrap Primary */
        --success-color: #198754; /* Bootstrap Success */
        --border-radius: 12px;
        --shadow-elevation: 0 8px 20px rgba(0, 0, 0, 0.1);
        --light-blue-bg: #e9f0ff; /* Light background for the main card */
    }
    
    .page-header {
        border-bottom: 3px solid var(--primary-color);
        padding-bottom: 10px;
        margin-bottom: 30px;
    }

    /* Card Utama */
    .card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-elevation);
        background-color: #fff;
    }

    .card-header {
        border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
        background-color: var(--primary-color) !important;
        color: white;
        padding: 1rem 1.5rem;
    }

    /* Styling Gambar Produk */
    .product-image {
        width: 280px;
        height: 280px;
        object-fit: cover;
        border: 5px solid #d0d9f0; /* Border warna light primary */
        border-radius: var(--border-radius);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }
    .product-image:hover {
        transform: scale(1.03);
    }
    
    /* Tabel Detail */
    .table th {
        color: #495057; /* Darker grey for labels */
        font-weight: 600;
        vertical-align: middle;
    }

    .table td {
        vertical-align: middle;
    }

    /* Deskripsi */
    .description-box {
        border: 1px solid #ced4da;
        background-color: var(--light-blue-bg);
        border-radius: var(--border-radius);
        min-height: 100px;
        white-space: pre-wrap; /* Memastikan baris baru di PHP ditampilkan */
    }

    /* Tombol Aksi */
    .btn-outline-secondary {
        border-radius: 50rem !important;
    }

    /* Styling Tambahan untuk Varian */
    .variant-table th {
        background-color: #f8f9fa;
        color: var(--primary-color);
        font-weight: 700;
    }
</style>
<div class="container-fluid p-4">
    <div class="page-header">
        <h1 class="mt-4 mb-3 text-primary display-5 fw-bold">
            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-eye me-2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
            Detail Produk: <span class="text-secondary"><?php echo htmlspecialchars($product['name']); ?></span>
        </h1>
        <p class="lead">Informasi lengkap mengenai produk dan semua varian stok.</p>
    </div>
    
    <div class="d-flex mb-4">
        <a href="<?php echo $router_path; ?>?page=products/index" class="btn btn-outline-secondary me-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-left me-1">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Kembali
        </a>
        <a href="<?php echo $router_path; ?>?page=products/edit&id=<?php echo $product_id; ?>" class="btn btn-warning text-dark">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-edit me-1">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            Edit Produk & Varian
        </a>
    </div>

    <div class="card shadow-lg mb-5">
        <div class="card-header">
            <h5 class="mb-0 fw-bold">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-package me-2">
                    <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line>
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.3 12 11.2 20.73 6.3"></polyline>
                    <line x1="12" y1="22.78" x2="12" y2="11.2"></line>
                </svg>
                Data Produk #<?php echo $product_id; ?>
            </h5>
        </div>
        <div class="card-body p-4">
            <div class="row">
                
                <div class="col-lg-4 col-md-5 text-center border-end p-4">
                    <h5 class="mb-3 text-muted">Gambar Produk</h5>
                    <img src="<?php echo $image_src; ?>" 
                                alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                class="product-image"
                                onerror="this.onerror=null;this.src='https://via.placeholder.com/280x280/F8F9FA/6C757D?text=Error+Loading+Image';">

                    <h4 class="mt-4 mb-3 text-primary fw-bold">Total Stok</h4>
                    <span class="badge bg-<?php echo ($total_stock > 10) ? 'success' : (($total_stock > 0) ? 'warning' : 'danger'); ?> fs-4 p-3 rounded-pill">
                        <?php echo $total_stock; ?> unit
                    </span>
                </div>

                <div class="col-lg-8 col-md-7 p-4">
                    <h4 class="mb-4 text-primary fw-bold">Informasi Dasar & Harga</h4>
                    <table class="table table-hover table-borderless table-detail mb-5">
                        <tbody>
                            <tr>
                                <th style="width: 20%;">Nama Produk:</th>
                                <td class="fw-bold fs-5 text-dark"><?php echo htmlspecialchars($product['name']); ?></td>
                            </tr>
                            <tr>
                                <th>Kategori:</th>
                                <td><span class="badge bg-info text-dark fs-6 p-2"><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></span></td>
                            </tr>
                            <tr>
                                <th>Harga Awal:</th>
                                <td><h4 class="text-success mb-0 fw-bold"><?php echo format_rupiah($product['price']); ?></h4></td>
                            </tr>
                            </tbody>
                    </table>

                    <h4 class="mt-4 mb-3 text-primary fw-bold">Deskripsi Produk</h4>
                    <div class="description-box p-3 shadow-sm">
                        <?php 
                        if (!empty($product['description'])) {
                            // Menggunakan nl2br untuk memformat baris baru
                            echo nl2br(htmlspecialchars($product['description'])); 
                        } else {
                            echo '<em class="text-muted">Tidak ada deskripsi yang tersedia.</em>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card shadow-lg mb-5">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0 fw-bold">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-trello me-2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <rect x="7" y="7" width="3" height="10"></rect>
                    <rect x="14" y="7" width="3" height="5"></rect>
                </svg>
                Detail Varian (Ukuran & Stok Per Varian)
            </h5>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($product_variants)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped variant-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 5%;">ID Varian</th>
                                <th style="width: 25%;">Ukuran (Size)</th>
                                <th style="width: 20%;">Stok Per Varian</th>
                                <th style="width: 50%;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($product_variants as $variant): ?>
                                <?php $variant_stock = $variant['stock']; ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($variant['id']); ?></td>
                                    <td class="fw-bold text-primary"><?php echo htmlspecialchars($variant['size']); ?></td>
                                    <td>
                                        <span class="fw-bold text-<?php echo ($variant_stock > 10) ? 'success' : (($variant_stock > 0) ? 'warning' : 'danger'); ?>">
                                            <?php echo htmlspecialchars($variant_stock); ?>
                                        </span> unit
                                    </td>
                                    <td>
                                        <?php 
                                        if ($variant_stock > 10) {
                                            echo '<span class="text-success">Stok Aman</span>';
                                        } elseif ($variant_stock > 0) {
                                            echo '<span class="text-warning">Stok Rendah, perlu restock.</span>';
                                        } else {
                                            echo '<span class="text-danger">Stok Habis (Out of Stock)</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-0" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-alert-triangle me-1"><path d="M10.29 3.86L1.86 18.29c-.77 1.33.19 3 1.73 3h16.82c1.54 0 2.5-1.67 1.73-3L13.71 3.86c-.77-1.33-2.69-1.33-3.46 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12" y2="17"></line></svg>
                    Produk ini belum memiliki varian ukuran dan stok. Silakan edit produk untuk menambahkannya.
                </div>
            <?php endif; ?>
            <?php if (isset($error_variants)): echo $error_variants; endif; ?>
        </div>
    </div>


    <h4 class="mt-5 mb-3 text-primary fw-bold">Metadata Sistem</h4>
    <div class="card shadow-lg mb-5 p-4">
        <table class="table table-sm table-borderless meta-table">
            <tbody>
                <tr>
                    <th style="width: 15%;">ID Produk:</th>
                    <td><code class="bg-light p-1 rounded text-dark border"><?php echo htmlspecialchars($product['id']); ?></code></td>
                    <th style="width: 15%;">ID Kategori:</th>
                    <td><code class="bg-light p-1 rounded text-dark border"><?php echo htmlspecialchars($product['category_id'] ?? 'N/A'); ?></code></td>
                </tr>
                <tr>
                    <th>Dibuat Pada:</th>
                    <td>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-calendar me-1 text-secondary">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <?php echo htmlspecialchars($product['created_at']); ?>
                    </td>
                    <th>Diperbarui Pada:</th>
                    <td>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-clock me-1 text-secondary">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <?php echo htmlspecialchars($product['updated_at']); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>