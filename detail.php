<?php
/**
 * detail.php
 * Halaman detail produk (Sisi pengguna).
 * Diperbaiki untuk menangani Varian (stock & size) dari tabel product_variants.
 * MEMBATASI PENGGUNAAN FORM PEMBELIAN HANYA UNTUK USER YANG SUDAH LOGIN.
 */

// 1. Sesi dan Status Login
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['user_id']);

require_once 'koneksi.php'; // Pastikan file koneksi.php ada di root

// 2. Ambil ID Produk dari URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Jika ID tidak valid, kembalikan ke halaman utama
    header('Location: toko_sepatu.php'); 
    exit;
}

$product_id = (int)$_GET['id'];
$uploads_path = 'uploads/product_images/';
$product = null;
$variants = []; // Array untuk menyimpan varian
$total_stock = 0; // Variabel untuk menghitung total stok

// 3. Ambil data produk utama (TANPA kolom 'stock')
$stmt = $koneksi->prepare("SELECT id, name, description, price, image_path FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();
    $stmt->close();

    // 4. Ambil data varian dan hitung total stok
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

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product ? htmlspecialchars($product['name']) . ' | Detail Produk' : 'Produk Tidak Ditemukan'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet"> 
    <script src="https://unpkg.com/feather-icons"></script>

    <style>
        /* Desain Minimalis & Modern */
        :root {
            --primary-color: #0d6efd; 
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --accent-color: #fd7e14; 
        }

        body {
            background-color: #f0f2f5; 
            font-family: 'Inter', sans-serif; 
            /* 🔥 PERUBAHAN UTAMA: Ganti padding untuk mengimbangi fixed-top navbar 🔥 */
            padding-top: 80px; 
        }

        /* PERUBAHAN UTAMA: Gunakan fixed-top pada navbar */
        .navbar.fixed-top {
            z-index: 1030;
        }

        .navbar-brand {
            font-weight: 800; 
            color: var(--primary-color) !important;
        }
        
        [data-feather] {
            width: 1em;
            height: 1em;
            vertical-align: -0.125em; 
            stroke-width: 2.5; 
        }

        .product-detail-card {
            background: #fff;
            border-radius: 16px; 
            padding: 20px; 
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1); 
        }

        /* Terapkan padding 40px hanya pada layar besar */
        @media (min-width: 768px) {
             .product-detail-card {
                padding: 40px; 
             }
        }

        .img-fluid {
            max-height: 500px;
            object-fit: cover;
            width: 100%;
            border-radius: 12px; 
            transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94), box-shadow 0.4s ease;
        }
        .img-fluid:hover {
            transform: scale(1.03); 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        h1 {
            font-weight: 700;
            font-size: 2rem; 
            margin-bottom: 0.5rem;
        }
        @media (min-width: 768px) {
            h1 {
                font-size: 2.5rem;
            }
        }

        .price-tag {
            font-size: 2rem; 
            font-weight: 800;
            color: var(--accent-color); 
        }
        @media (min-width: 768px) {
            .price-tag {
                font-size: 3rem;
            }
        }
        
        .badge {
            font-size: 0.85em;
            font-weight: 600;
            letter-spacing: 0.5px;
            border-radius: 50px;
        }

        .btn-action {
            width: 100%;
            margin-bottom: 15px;
            font-weight: 600;
            transition: all 0.3s;
            border-radius: 10px; 
            padding: 0.75rem 1rem;
        }
        .btn-buy {
            background-color: var(--accent-color); 
            border-color: var(--accent-color);
        }
        .btn-buy:hover {
            background-color: #e66a00;
            border-color: #e66a00;
        }

        .product-description {
            text-align: justify; 
            line-height: 1.8; 
            color: #495057;
        }
        
        .variant-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px; 
        }
        .variant-label {
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid #ced4da;
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: 600;
            color: #495057;
            text-align: center;
            flex: 1 1 auto; 
            min-width: 80px;
        }
        .variant-input:checked + .variant-label {
            border-color: var(--primary-color) !important;
            background-color: var(--primary-color);
            color: white;
            box-shadow: 0 0 0 0.15rem rgba(13, 110, 253, 0.25);
        }
        .variant-input:disabled + .variant-label {
            opacity: 0.6;
            cursor: not-allowed;
            text-decoration: line-through;
            border-color: #dc3545;
            color: #dc3545;
            background-color: #f8d7da;
        }
        
        #quantityInput {
            max-width: 100% !important; 
        }
        @media (min-width: 576px) {
            #quantityInput {
                max-width: 180px !important; 
            }
        }
        /* Perbaikan Navbar Collapse di Mobile */
        .navbar-collapse {
             /* Tambahkan padding agar tombol di dalam collapse tidak terlalu mepet ke border */
            padding-bottom: 10px; 
        }
        @media (max-width: 991.98px) { /* Di bawah breakpoint lg */
            .navbar .nav-item, .navbar .btn {
                width: 100%;
            }
            .navbar-collapse .ms-auto > * {
                margin: 5px 0 !important; /* Jarak vertikal antar tombol di mobile */
            }
        }


    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="toko_sepatu.php">TokoOnlineku</a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#detailNavbar" aria-controls="detailNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="detailNavbar">
            <div class="ms-auto d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center mt-2 mt-lg-0">
                
                <a class="btn btn-sm btn-outline-secondary me-lg-2 mb-2 mb-lg-0 d-flex align-items-center justify-content-center" href="toko_sepatu.php">
                    <i data-feather="arrow-left" class="me-1"></i> Kembali
                </a>
                
                <?php if ($is_logged_in): ?>
                    <a class="btn btn-sm btn-outline-danger d-flex align-items-center justify-content-center" 
                        href="logout_user.php"
                        onclick="return confirm('Apakah Anda yakin ingin keluar dari akun ini?');">
                        <i data-feather="log-out" class="me-1"></i> Logout
                    </a>
                <?php else: ?>
                    <a class="btn btn-sm btn-primary text-white d-flex align-items-center justify-content-center" 
                        href="login_user.php">
                        <i data-feather="log-in" class="me-1"></i> Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="container my-5">
    <?php if ($product): ?>
        <div class="row product-detail-card g-4"> 
            <div class="col-md-5 mb-4 mb-md-0">
                <?php 
                $image_src = $uploads_path . htmlspecialchars($product['image_path']);
                $is_image_available = !empty($product['image_path']) && file_exists($uploads_path . $product['image_path']);

                if (!$is_image_available) {
                    $image_src = 'https://via.placeholder.com/400x400?text=' . urlencode(htmlspecialchars($product['name']));
                }
                ?>
                <img src="<?php echo $image_src; ?>" class="img-fluid rounded shadow-lg" alt="<?php echo htmlspecialchars($product['name']); ?>">
            </div>
            
            <div class="col-md-7">
                <h1 class="text-dark"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <p class="price-tag my-3">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></p>
                
                <p class="fs-5"><strong>Status Stok:</strong> 
                    <?php if ($total_stock > 0): ?>
                        <span class="badge bg-success">
                            Stok Tersedia (<?php echo $total_stock; ?> Unit Total)
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger">Stok Habis</span>
                    <?php endif; ?>
                </p>
                
                <hr class="my-4">
                
                <h4 class="fw-bold mb-3">Deskripsi Produk</h4>
                <p class="text-muted product-description mb-4">
                    <?php echo nl2br(htmlspecialchars($product['description'] ?? 'Tidak ada deskripsi tersedia untuk produk ini.')); ?>
                </p>
                
                <hr class="my-4">

                <?php if ($is_logged_in): ?>
                    <form id="productForm" method="POST" action="handle_cart_or_checkout.php">
                        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

                        <?php if ($total_stock > 0 && !empty($variants)): ?>
                            
                            <div class="mb-4">
                                <h5 class="fw-bold mb-3 text-dark">Pilih Ukuran/Varian <span class="text-danger">*</span>:</h5>
                                <div class="variant-group"> 
                                    <?php foreach ($variants as $variant): ?>
                                        <input type="radio" class="btn-check variant-input" 
                                                    name="variant_id" 
                                                    id="variant-<?php echo $variant['id']; ?>" 
                                                    value="<?php echo $variant['id']; ?>" 
                                                    <?php echo $variant['stock'] <= 0 ? 'disabled' : ''; ?>
                                                    required>
                                        <label class="variant-label" 
                                                    for="variant-<?php echo $variant['id']; ?>"
                                                    title="Stok Tersedia: <?php echo $variant['stock']; ?> Unit">
                                            <?php echo htmlspecialchars($variant['size']); ?>
                                            <small class="d-block fw-normal text-muted"><?php echo $variant['stock'] > 0 ? $variant['stock'] . ' Tersedia' : 'Habis'; ?></small>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h5 class="fw-bold mb-3 text-dark">Jumlah Pembelian:</h5>
                                <input type="number" 
                                            name="quantity" 
                                            id="quantityInput" 
                                            class="form-control form-control-lg" 
                                            value="1" 
                                            min="1" 
                                            max="<?php echo $total_stock; ?>" 
                                            required 
                                            style="max-width: 180px;">
                                <small class="text-muted mt-2 d-block" id="maxStockInfo">Maks. total: <?php echo $total_stock; ?> unit.</small>
                            </div>
                        <?php endif; ?>
                    
                        <div class="row mt-5 g-3">
                            <?php if ($total_stock > 0): ?>
                                <div class="col-sm-6">
                                    <button type="submit" 
                                            formaction="add_to_cart.php" 
                                            class="btn btn-primary btn-lg btn-action d-flex align-items-center justify-content-center">
                                        <i data-feather="shopping-cart" class="me-2"></i> Tambah ke Keranjang
                                    </button>
                                </div>
                                <div class="col-sm-6">
                                    <button type="submit" 
                                            formaction="checkout.php" 
                                            class="btn btn-buy btn-lg btn-action text-white d-flex align-items-center justify-content-center">
                                        <i data-feather="credit-card" class="me-2"></i> Beli Sekarang
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="col-12">
                                    <button class="btn btn-secondary btn-lg btn-action" disabled>Stok Habis</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php else: ?>
                    <?php if (!empty($variants)): ?>
                        <div class="mb-5">
                            <h5 class="fw-bold mb-3 text-dark">Varian Tersedia:</h5>
                            <div class="variant-group">
                                <?php foreach ($variants as $variant): ?>
                                    <span class="badge p-2 px-3 fw-normal <?php echo $variant['stock'] > 0 ? 'bg-info text-dark' : 'bg-danger'; ?>"
                                            title="Stok: <?php echo $variant['stock']; ?> Unit">
                                        <?php echo htmlspecialchars($variant['size']); ?>
                                        <small class="ms-2 fw-bold text-black-50">(<?php echo $variant['stock'] > 0 ? $variant['stock'] : 'Habis'; ?>)</small>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row mt-5">
                        <div class="col-12">
                            <?php if ($total_stock > 0): ?>
                                <a href="login_user.php" class="btn btn-primary btn-lg btn-action d-flex align-items-center justify-content-center">
                                    <i data-feather="log-in" class="me-2"></i> Login untuk Membeli
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-lg btn-action" disabled>Stok Habis</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger text-center product-detail-card shadow-sm">
            <h2 class="alert-heading">Produk Tidak Ditemukan</h2>
            <p>Kami mohon maaf, produk yang Anda cari mungkin telah dihapus atau ID-nya tidak valid.</p>
            <a href="toko_sepatu.php" class="btn btn-primary mt-3">
                <i data-feather="arrow-left" class="me-1"></i> Kembali ke Daftar Produk
            </a>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize Feather Icons
    feather.replace();

    // Script ini hanya berjalan jika form productForm ada (yaitu, jika user sudah login)
    document.addEventListener('DOMContentLoaded', function() {
        const productForm = document.getElementById('productForm');
        if (!productForm) return;

        const variantRadios = document.querySelectorAll('input[name="variant_id"]');
        const quantityInput = document.getElementById('quantityInput');
        const maxStockInfo = document.getElementById('maxStockInfo');
        // Ambil data varian dari PHP (hanya untuk user yang sudah login)
        const variants = <?php echo json_encode($variants); ?>;

        function updateMaxQuantity() {
            let selectedVariantId = document.querySelector('input[name="variant_id"]:checked');
            
            if (selectedVariantId) {
                // Cari data varian yang dipilih
                let selectedVariant = variants.find(v => v.id == selectedVariantId.value);
                let maxStock = selectedVariant ? selectedVariant.stock : 0;

                // Update input kuantitas
                quantityInput.max = maxStock;
                quantityInput.setAttribute('max', maxStock); // Pastikan atribut max HTML juga terupdate
                maxStockInfo.textContent = `Maks. untuk ukuran ${selectedVariant.size}: ${maxStock} unit.`;

                // Jika kuantitas saat ini melebihi stok, atur kembali ke stok maksimum
                if (parseInt(quantityInput.value) > maxStock) {
                    quantityInput.value = maxStock > 0 ? 1 : 0;
                }
                
            } else {
                // Jika tidak ada yang dipilih, gunakan total stok sebagai batas awal
                const totalStock = <?php echo $total_stock; ?>;
                quantityInput.max = totalStock;
                quantityInput.setAttribute('max', totalStock);
                quantityInput.value = totalStock > 0 ? 1 : 0;
                maxStockInfo.textContent = `Pilih ukuran untuk melihat batas stok per varian. (Maks. total: ${totalStock} unit.)`;
            }

            // Nonaktifkan tombol beli jika stok = 0 setelah perubahan
            const buyButtons = productForm.querySelectorAll('.btn-action');
            if (parseInt(quantityInput.value) === 0) {
                 buyButtons.forEach(btn => btn.setAttribute('disabled', 'disabled'));
            } else {
                 buyButtons.forEach(btn => btn.removeAttribute('disabled'));
            }

        }

        variantRadios.forEach(radio => {
            radio.addEventListener('change', updateMaxQuantity);
        });
        quantityInput.addEventListener('input', updateMaxQuantity); // Tambahkan event listener untuk input kuantitas

        // Panggil saat halaman dimuat untuk inisialisasi
        updateMaxQuantity();
    });
</script>

</body>
</html>
<?php 
// Tutup koneksi di akhir
if (isset($koneksi) && $koneksi instanceof mysqli) {
    $koneksi->close(); 
}
?>