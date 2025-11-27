<?php
/**
 * detail_ajax.php
 * Endpoint AJAX untuk mengambil data detail produk dan merender HTML untuk modal.
 * Diperbaiki: Tombol "Lihat Halaman Detail Penuh" kini hanya muncul satu kali (Dihapus dari konten AJAX).
 * Diperbarui: Peningkatan responsif untuk perangkat mobile dan tablet.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Mengatur header agar browser tahu ini adalah respons HTML
header('Content-Type: text/html');

$is_logged_in = isset($_SESSION['user_id']);
require_once 'koneksi.php'; // Asumsikan file ini menyediakan $koneksi
$uploads_path = 'uploads/product_images/';
$product = null;
$variants = []; // Array untuk menyimpan varian
$total_stock = 0; // Variabel untuk menghitung total stok

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo '<div class="alert alert-danger">ID Produk tidak valid.</div>';
    exit;
}

$product_id = (int)$_GET['id'];

if (!$koneksi) {
    http_response_code(500);
    echo '<div class="alert alert-danger">Koneksi database gagal.</div>';
    exit;
}

// --- 1. Ambil data produk utama (tanpa kolom 'stock') ---
$stmt = $koneksi->prepare("SELECT id, name, description, price, image_path FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();
    $stmt->close();

    // --- 2. Ambil data varian dan hitung total stok ---
    $stmt_variant = $koneksi->prepare("SELECT id, size, stock FROM product_variants WHERE product_id = ? ORDER BY size ASC");
    $stmt_variant->bind_param("i", $product_id);
    $stmt_variant->execute();
    $result_variant = $stmt_variant->get_result();

    while ($row = $result_variant->fetch_assoc()) {
        $variants[] = $row;
        if ($row['stock'] > 0) {
            $total_stock += $row['stock'];
        }
    }
    $stmt_variant->close();

} else {
    $stmt->close();
}
$koneksi->close();

if (!$product): ?>
    <div class="alert alert-warning text-center">
        Produk tidak ditemukan atau ID tidak valid.
    </div>
<?php else: 
    // Logika gambar
    $image_src = $uploads_path . htmlspecialchars($product['image_path']);
    $is_image_available = !empty($product['image_path']) && file_exists($uploads_path . $product['image_path']);
    
    if (!$is_image_available || $product['image_path'] === '0') {
        $image_src = 'https://via.placeholder.com/400x400?text=' . urlencode(htmlspecialchars($product['name'] . ' - No Image'));
    }
?>
    <style>
        .product-modal-container {
            font-family: 'Poppins', sans-serif; 
        }
        .image-container img {
            transition: transform 0.3s ease-in-out;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: none !important; 
            /* Batasi tinggi gambar di mobile/modal agar tidak terlalu besar */
            max-height: 300px; /* Nilai default yang lebih responsif */
            width: 100%; /* Pastikan lebar gambar 100% dari kolomnya */
            object-fit: cover;
        }
        @media (min-width: 768px) { /* Medium devices (tablet) and up */
             .image-container img {
                max-height: 380px; /* Kembalikan ke tinggi asli untuk tablet/desktop */
            }
        }
        .image-container img:hover {
            transform: scale(1.02);
        }
        /* Penyesuaian font size untuk judul di mobile */
        .product-title {
            font-size: 1.5rem; /* Lebih kecil di mobile */
        }
        @media (min-width: 768px) {
            .product-title {
                font-size: 2rem; /* Ukuran normal di tablet/desktop */
            }
        }
        .price-text {
            font-size: 1.75rem; /* Lebih kecil untuk harga di mobile */
        }
        @media (min-width: 768px) {
            .price-text {
                font-size: 2.5rem; /* Ukuran normal di tablet/desktop (fs-1 di Bootstrap 5) */
            }
        }

        .text-justify-clamp {
            text-align: justify;
            display: -webkit-box;
            -webkit-line-clamp: 4; 
            -webkit-box-orient: vertical;
            overflow: hidden;
            max-height: 80px; 
        }
        /* Varian style untuk user login (radio) */
        .variant-label {
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #ced4da;
            padding: 6px 12px;
            border-radius: 8px;
            white-space: nowrap; /* Mencegah varian memotong teks */
        }
        .variant-input:checked + .variant-label {
            border-color: #007bff !important;
            background-color: #007bff;
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }
         .variant-input:disabled + .variant-label {
            opacity: 0.6;
            cursor: not-allowed;
            text-decoration: line-through;
            border-color: #dc3545;
            color: #dc3545;
        }
        /* Varian style untuk user non-login (badge) */
        .variant-tag {
            background-color: #e9ecef;
            color: #495057;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
            border: 1px solid #dee2e6;
            white-space: nowrap;
        }
        .variant-tag.out-of-stock {
            opacity: 0.6;
            text-decoration: line-through;
            border-color: #dc3545;
        }
        /* Penyesuaian untuk input jumlah di mobile */
        .quantity-input {
            max-width: 100%; /* Lebar penuh di mobile */
        }
        @media (min-width: 768px) {
            .quantity-input {
                max-width: 150px; /* Ukuran normal di tablet/desktop */
            }
        }
    </style>

    <div class="row p-2 product-modal-container">
        
        <div class="col-12 col-md-5 mb-4 mb-md-0 d-flex align-items-center justify-content-center image-container">
            <img src="<?php echo $image_src; ?>" class="img-fluid rounded-4" 
                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                 style="max-height: 380px; width: 100%; object-fit: cover;">
        </div>
        
        <div class="col-12 col-md-7">
            
            <h2 class="text-dark fw-bold border-bottom pb-2 mb-3 product-title">
                <?php echo htmlspecialchars($product['name']); ?>
            </h2>
            
            <p class="price-text fw-bolder mb-3 text-success"> 
                Rp <?php echo number_format($product['price'], 0, ',', '.'); ?>
            </p>
            
            <p class="fs-6 mb-3">
                <i class="fas fa-cubes me-2 text-primary"></i> <strong>Total Ketersediaan:</strong> 
                <?php if ($total_stock > 0): ?>
                    <span class="badge rounded-pill bg-success p-2 shadow-sm">
                        Stok Tersedia (<?php echo $total_stock; ?> Unit Total)
                    </span>
                <?php else: ?>
                    <span class="badge rounded-pill bg-danger p-2 shadow-sm">Stok Habis</span>
                <?php endif; ?>
            </p>
            
            <hr class="my-3">

            <?php if ($is_logged_in): ?>
                
                <form id="addToCartForm" method="POST" action="add_to_cart.php"> 
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

                    <?php if ($total_stock > 0 && !empty($variants)): ?>
                        <div class="mb-3">
                            <h6 class="fw-bold mb-2 text-dark">Pilih Ukuran Tersedia <span class="text-danger">*</span>:</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($variants as $variant): ?>
                                    <input type="radio" class="btn-check variant-input" 
                                                 name="variant_id" 
                                                 id="variant-<?php echo $variant['id']; ?>" 
                                                 value="<?php echo $variant['id']; ?>" 
                                                 <?php echo $variant['stock'] <= 0 ? 'disabled' : ''; ?>
                                                 required>
                                    <label class="btn btn-outline-secondary variant-label rounded-pill px-3 py-1 fw-normal" 
                                                 for="variant-<?php echo $variant['id']; ?>"
                                                 title="Stok: <?php echo $variant['stock']; ?> Unit">
                                        <?php echo htmlspecialchars($variant['size']); ?>
                                        <small class="d-block fw-normal text-muted"><?php echo $variant['stock'] > 0 ? $variant['stock'] : 'Habis'; ?></small>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="fw-bold mb-2 text-dark">Jumlah Pembelian:</h6>
                            <input type="number" name="quantity" class="form-control quantity-input" value="1" min="1" max="<?php echo $total_stock; ?>" required>
                            <small class="text-muted">Maksimal total: <?php echo $total_stock; ?> unit.</small>
                        </div>
                    <?php endif; ?>

                    <hr class="my-3">

                    <div class="mb-4">
                        <h6 class="fw-bold mb-2 text-secondary">Deskripsi Produk:</h6>
                        <p class="text-muted small text-justify-clamp">
                            <?php echo nl2br(htmlspecialchars($product['description'] ?? 'Tidak ada deskripsi tersedia untuk produk ini.')); ?>
                        </p>
                    </div>

                    <div class="mt-4">
                        <div class="d-grid gap-2">
                            <?php if ($total_stock > 0): ?>
                                <button type="submit" class="btn btn-primary btn-lg shadow-sm rounded-3">
                                    <i class="fas fa-cart-plus me-2"></i> Tambah ke Keranjang
                                </button>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-lg rounded-3" disabled>Stok Habis</button>
                            <?php endif; ?>
                            
                            </div>
                    </div>
                </form>

            <?php else: ?>

                <?php if (!empty($variants)): ?>
                    <div class="mb-4">
                        <h6 class="fw-bold mb-2 text-dark">Varian Tersedia:</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($variants as $variant): ?>
                                <span class="variant-tag <?php echo $variant['stock'] <= 0 ? 'out-of-stock' : ''; ?>"
                                         title="Stok: <?php echo $variant['stock']; ?> Unit">
                                    <?php echo htmlspecialchars($variant['size']); ?> 
                                    <?php if ($variant['stock'] <= 0): ?>
                                        <i class="fas fa-times-circle text-danger ms-1"></i>
                                    <?php else: ?>
                                        <i class="fas fa-check-circle text-success ms-1"></i>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="mb-4">
                    <h6 class="fw-bold mb-2 text-secondary">Deskripsi Produk:</h6>
                    <p class="text-muted small text-justify-clamp">
                        <?php echo nl2br(htmlspecialchars($product['description'] ?? 'Tidak ada deskripsi tersedia untuk produk ini.')); ?>
                    </p>
                </div>

                <div class="mt-4">
                    <div class="d-grid gap-2">
                        <?php if ($total_stock > 0): ?>
                            <a href="login_user.php" class="btn btn-danger btn-lg shadow-sm rounded-3">
                                <i class="fas fa-sign-in-alt me-2"></i> Login untuk Membeli
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-lg rounded-3" disabled>Stok Habis</button>
                        <?php endif; ?>
                        
                        </div>
                </div>

            <?php endif; ?>
            
            </div>
    </div>
<?php endif; ?>