<?php
/**
 * cart.php
 * Halaman frontend untuk menampilkan keranjang belanja dengan dukungan VARIAN (product_variants).
 * Item key: product_id_variant_id
 * Diperbarui: Mendukung Sweet Alert untuk notifikasi.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php'; // Asumsikan file ini menyediakan $koneksi

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login_user.php');
    exit;
}

// 1. LOGIKA PENGAMBILAN FLASH MESSAGE (SWAL & TOAST)
// 🔥 Ambil variabel Sweet Alert
$session_swal_title = '';
$session_swal_text = '';
$session_swal_icon = '';

if (isset($_SESSION['swal_title']) && isset($_SESSION['swal_icon'])) {
    $session_swal_title = $_SESSION['swal_title'];
    $session_swal_text = $_SESSION['swal_text'] ?? '';
    $session_swal_icon = $_SESSION['swal_icon'];
    
    // Hapus sesi SWAL agar tidak muncul lagi
    unset($_SESSION['swal_title']);
    unset($_SESSION['swal_text']);
    unset($_SESSION['swal_icon']);
}


// Logika Toast lama (Dipertahankan sebagai fallback/local messages)
$toast_message = null;
$toast_type = null;

if (isset($_SESSION['toast_message'])) {
    $toast_message = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'] ?? 'success'; // Default ke success
    
    // Hapus pesan dari session agar tidak muncul lagi saat refresh
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}

$cart_items = $_SESSION['cart'] ?? [];
$total_price = 0;

// 2. Persiapan Data untuk Query Varian & Validasi Awal Key
$variant_ids_in_cart = [];
$valid_cart_items_temp = []; 

if (!empty($cart_items)) {
    foreach ($cart_items as $item_key => $item) {
        $parts = explode('_', $item_key);
        // Memastikan key berformat "product_id_variant_id"
        if (count($parts) === 2 && is_numeric($parts[1]) && is_numeric($parts[0])) {
            $variant_id = (int)$parts[1];
            $valid_cart_items_temp[$item_key] = $item; // Simpan item yang format key-nya valid
            
            if ($variant_id > 0) {
                $variant_ids_in_cart[] = $variant_id;
            }
        } else {
             // Opsional: set needs_redirect jika ada key yang tidak valid
        }
    }
    $cart_items = $valid_cart_items_temp; 
    $_SESSION['cart'] = $cart_items; 
}

$product_detail_map = [];
$variant_stock_map = [];
$needs_redirect = false;

// 3. Query Detail Varian dan Produk dari Database
if (!empty($variant_ids_in_cart)) {
    $placeholders = implode(',', array_fill(0, count($variant_ids_in_cart), '?'));
    
    $sql_detail = "
        SELECT 
            pv.id AS variant_id, 
            pv.product_id, 
            pv.size, 
            pv.stock,
            p.name,
            p.price
        FROM product_variants pv
        JOIN products p ON pv.product_id = p.id
        WHERE pv.id IN ($placeholders)
    ";
    
    $stmt_detail = $koneksi->prepare($sql_detail);
    if ($stmt_detail) {
        $types = str_repeat('i', count($variant_ids_in_cart));
        
        // Menggunakan reference operator untuk bind_param
        $params = array_merge([$types], $variant_ids_in_cart);
        $stmt_detail->bind_param(...$params); 
        
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();
        
        while($row = $result_detail->fetch_assoc()) {
            $key = $row['product_id'] . '_' . $row['variant_id'];

            // Simpan data varian, stok, dan product_id (PENTING)
            $variant_stock_map[$key] = [
                'stock' => (int)$row['stock'],
                'size' => $row['size'],
                'product_id' => (int)$row['product_id'], 
            ];

            // Simpan data harga & nama produk
            $product_detail_map[$key] = [
                'name' => $row['name'],
                'price' => (float)$row['price'],
            ];
        }
        $stmt_detail->close();
    }
}

// 4. Hitung Total Harga Final, Validasi Stok, dan Update Session
$temp_cart_items = $cart_items; 
$cart_items = []; 

foreach ($temp_cart_items as $item_key => $item) {
    
    if (isset($product_detail_map[$item_key])) {
        $db_data = $product_detail_map[$item_key];
        $variant_data = $variant_stock_map[$item_key];

        $current_price = $db_data['price'];
        $stock_limit = $variant_data['stock'];
        $variant_size = $variant_data['size'];

        // Batasi kuantitas ke stok maksimal
        $quantity = min((int)$item['quantity'], $stock_limit);
        $quantity = max(1, $quantity); // Kuantitas minimal 1

        if ($stock_limit <= 0) {
            // Hapus item jika stok 0 (walaupun kuantitasnya 1)
            $needs_redirect = true;
            continue; 
        }

        $subtotal = $current_price * $quantity;
        $total_price += $subtotal;

        // Buat item baru dari data sesi lama dan timpa dengan data DB terbaru
        $new_item = $item;
        $new_item['quantity'] = $quantity; 
        $new_item['current_price'] = $current_price;
        $new_item['stock_limit'] = $stock_limit;
        $new_item['variant_size'] = $variant_size;
        $new_item['product_name'] = $db_data['name'];
        $new_item['product_id'] = $variant_data['product_id']; // Fix Undefined array key

        $cart_items[$item_key] = $new_item; 

    } else {
        // Jika produk/varian tidak ditemukan di DB, hapus dari cart
        $needs_redirect = true;
    }
}

// Update session dan redirect jika ada item yang dihapus atau kuantitas disesuaikan
if ($needs_redirect) {
    $_SESSION['cart'] = $cart_items; 
    if (!isset($_SESSION['toast_message'])) {
        // Tambahkan Sweet Alert/Toast saat ada item yang dihapus/disesuaikan
        $_SESSION['swal_icon'] = 'warning';
        $_SESSION['swal_title'] = 'Keranjang Disesuaikan';
        $_SESSION['swal_text'] = "Keranjang disesuaikan karena ada item yang tidak valid, kehabisan stok, atau kuantitas melebihi batas.";
    }
    header('Location: cart.php'); 
    exit;
}

$_SESSION['cart'] = $cart_items; // Simpan keranjang yang sudah tervalidasi
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 
    <style>
        .cart-container { max-width: 1000px; margin: 0 auto; }
        .item-row { 
            border-bottom: 1px solid #f0f0f0; 
            padding: 15px 0; 
            display: flex; 
            align-items: center; 
            transition: background-color 0.3s ease; 
        }
        .item-row:hover { background-color: #f8f9fa; }
        .item-row:last-child { border-bottom: none; }
        .summary-box { 
            background-color: #ffffff; 
            border-radius: 10px; 
            border: 1px solid #dee2e6; 
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); 
        }
        .total-price { font-size: 2rem; color: #dc3545; }
        
        /* Custom styles for responsiveness */
        
        /* Ubah tata letak di layar mobile (default) */
        @media (max-width: 767.98px) {
            .item-row {
                flex-direction: column; /* Tumpuk item */
                align-items: flex-start; /* Sejajarkan ke kiri */
                padding: 10px 0;
            }
            .item-row > div {
                width: 100%; /* Lebar penuh */
                padding-top: 5px;
                padding-bottom: 5px;
            }
            .item-name-col { 
                order: 1; 
            }
            .item-qty-col { 
                order: 2; 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                border-top: 1px solid #f0f0f0;
                padding-top: 10px !important;
            }
            .item-price-col { 
                order: 3; 
                text-align: right !important; /* Harga tetap di kanan */
                border-top: 1px solid #f0f0f0;
                padding-top: 10px !important;
            }
            
            /* Sembunyikan label kuantitas di mobile */
            .qty-label-desktop { display: none; }
            
            /* Penyesuaian Input Kuantitas di Mobile */
            .quantity-input {
                max-width: 100px !important; /* Batasi lebar input di mobile */
                margin: 0 !important;
            }
            
            .total-price { 
                font-size: 1.5rem; /* Harga total lebih kecil di mobile */
            }
            
            .btn-group-footer button, .btn-group-footer a {
                margin-top: 8px; /* Jarak antar tombol */
            }
            
            /* Non-aktifkan sticky di mobile */
            .summary-box-sticky-top {
                position: static !important;
                top: auto !important;
            }
        }
        
        /* Styles untuk tablet dan desktop */
        @media (min-width: 768px) {
            .item-name-col { width: 58.33%; } /* col-7 */
            .item-qty-col { width: 25%; } /* col-3, diubah ke 25% untuk ruang */
            .item-price-col { width: 16.66%; } /* col-2 */
            .quantity-input { max-width: 70px !important; margin-left: auto; margin-right: auto; }
            .qty-label-mobile { display: none; }
            .summary-box-sticky-top {
                position: sticky;
                top: 20px;
            }
        }
        
        .toast-container { z-index: 1080 !important; position: fixed; top: 0; right: 0; margin-top: 15px; margin-right: 15px; }
    </style>
</head>
<body class="bg-light">

<?php 
// HTML TOAST CONTAINER (Jika ada pesan Toast)
if ($toast_message): 
    $toast_class = ($toast_type === 'error') ? 'bg-danger' : (($toast_type === 'warning') ? 'bg-warning text-dark' : 'bg-success');
?>
<div class="toast-container">
    <div id="liveToast" class="toast fade align-items-center text-white <?php echo $toast_class; ?>" 
        role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-bold">
                <?php echo htmlspecialchars($toast_message); ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
<?php endif; ?>


<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container cart-container">
        <a class="navbar-brand fw-bold text-primary" href="toko_sepatu.php"><i class="bi bi-shop me-2"></i>TokoOnlineku</a>
        <a class="btn btn-outline-secondary" href="toko_sepatu.php">
             <i class="bi bi-arrow-left-short me-1"></i> Lanjut Belanja
        </a>
    </div>
</nav>

<div class="container cart-container mt-5 mb-5">
    <h1 class="mb-5 fw-bold text-center text-dark-emphasis" style="font-size: clamp(1.8rem, 5vw, 2.5rem);"><i class="bi bi-cart4 me-3 text-primary"></i>Keranjang Belanja</h1>
    
    <?php if (empty($cart_items)): ?>
        <div class="alert alert-warning text-center p-5 shadow-sm rounded-3">
            <i class="bi bi-box-seam-fill display-3 mb-3 text-warning"></i>
            <h4 class="alert-heading">Keranjang Anda Kosong!</h4>
            <p>Yuk, <a href="toko_sepatu.php" class="alert-link fw-bold">cari produk impian Anda</a> sekarang juga!</p>
        </div>
    <?php else: ?>
        
        <form action="update_cart.php" method="POST" id="cartForm">
            <div class="row">
                <div class="col-lg-8 col-12 order-lg-1 order-2"> <div class="card shadow-lg mb-4 rounded-3 border-0">
                        <div class="card-header bg-primary text-white border-bottom p-3 rounded-top-3">
                            <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i> Item di Keranjang (<?php echo count($cart_items); ?>)</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php foreach ($cart_items as $item_key => $item): 
                                $current_price = $item['current_price'] ?? 0;
                                $stock_limit = $item['stock_limit'] ?? 0;
                                $variant_size = $item['variant_size'] ?? 'N/A';
                                $product_name = $item['product_name'] ?? 'Produk Tidak Ditemukan';
                                $subtotal = $current_price * $item['quantity'];
                            ?>
                            <div class="item-row">
                                <div class="item-name-col">
                                    <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($product_name); ?></h6>
                                    <p class="mb-1 small text-primary fw-bold">
                                        Varian: <?php echo htmlspecialchars($variant_size); ?> 
                                    </p>
                                    <p class="mb-0 small text-muted d-none d-md-block">
                                        Harga Satuan: Rp <?php echo number_format($current_price, 0, ',', '.'); ?>
                                    </p>
                                </div>
                                
                                <div class="item-qty-col">
                                    <label class="form-label small mb-1 text-secondary qty-label-desktop">Qty (Maks: <?php echo $stock_limit; ?>)</label>
                                    <label class="form-label small mb-0 text-secondary d-md-none qty-label-mobile">Kuantitas (Maks: <?php echo $stock_limit; ?>)</label>
                                    <input type="number" 
                                             name="quantity[<?php echo $item_key; ?>]" 
                                             value="<?php echo $item['quantity']; ?>" 
                                             min="1" 
                                             max="<?php echo $stock_limit; ?>"
                                             class="form-control form-control-sm quantity-input">
                                </div>
                                
                                <div class="item-price-col text-end">
                                    <p class="mb-2 fw-bold text-primary fs-6">
                                        Rp <?php echo number_format($subtotal, 0, ',', '.'); ?>
                                    </p>
                                    <p class="mb-1 small text-muted d-md-none">
                                        Satuan: Rp <?php echo number_format($current_price, 0, ',', '.'); ?>
                                    </p>
                                    <a href="remove_from_cart.php?key=<?php echo urlencode($item_key); ?>" 
                                         class="btn btn-sm btn-outline-danger mt-1" 
                                         title="Hapus Item"
                                         onclick="return confirm('Yakin ingin menghapus <?php echo htmlspecialchars($product_name); ?> ukuran <?php echo htmlspecialchars($variant_size); ?> dari keranjang?');">
                                         <i class="bi bi-trash"></i> <span class="d-md-none">Hapus</span>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="card-footer bg-light border-top text-end p-3 btn-group-footer d-flex flex-column flex-md-row justify-content-end">
                            <button type="submit" form="cartForm" class="btn btn-info shadow-sm fw-bold d-block d-md-inline-block order-md-1">
                                <i class="bi bi-arrow-repeat me-2"></i> Perbarui Jumlah
                            </button>
                            <a href="remove_from_cart.php?action=clear" 
                                class="btn btn-outline-danger shadow-sm fw-bold d-block d-md-inline-block mt-2 mt-md-0 ms-md-2 order-md-2"
                                onclick="return confirm('Apakah Anda yakin ingin MENGOSONGKAN seluruh isi keranjang?');">
                                <i class="bi bi-trash-fill me-2"></i> Kosongkan Keranjang
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-12 order-lg-2 order-1 mb-4 mb-lg-0"> 
                    <div class="card summary-box p-4 shadow border-0 summary-box-sticky-top">
                        <h4 class="text-primary mb-4 border-bottom pb-2"><i class="bi bi-calculator me-2"></i> Ringkasan Pembayaran</h4>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <span class="fw-normal text-dark-emphasis">Subtotal Item (<?php echo count($cart_items); ?> produk)</span>
                            <span class="fw-bold">Rp <?php echo number_format($total_price, 0, ',', '.'); ?></span>
                        </div>

                        <div class="d-flex justify-content-between mb-4 pt-3 border-top">
                            <span class="fw-bolder fs-5 text-dark">GRAND TOTAL</span>
                            <span class="fw-bolder total-price">Rp <?php echo number_format($total_price, 0, ',', '.'); ?></span>
                        </div>
                        
                        <a href="checkout.php" class="btn btn-primary btn-lg d-grid shadow-lg rounded-3" style="--bs-btn-bg: #dc3545; --bs-btn-hover-bg: #a71d2a; --bs-btn-border-color: #dc3545; --bs-btn-active-bg: #a71d2a; --bs-btn-active-border-color: #a71d2a;">
                            <i class="bi bi-bag-check me-2"></i> Lanjutkan ke Checkout
                        </a>
                        
                        <a href="toko_sepatu.php" class="btn btn-link mt-3 text-secondary">
                            <i class="bi bi-chevron-left me-1"></i> Kembali ke Katalog
                        </a>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // 🔥 FUNGSI SWEET ALERT (BARU) 🔥
    function showSweetAlert(icon, title, text, timer = 3000) {
        Swal.fire({
            icon: icon,
            title: title,
            text: text,
            showConfirmButton: false,
            timer: timer,
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        var cartForm = document.getElementById('cartForm');
        var quantityInputs = document.querySelectorAll('.quantity-input');

        // --- 1. LOGIKA SWEET ALERT ---
        const sessionSwalTitle = "<?= htmlspecialchars($session_swal_title) ?>";
        const sessionSwalText = "<?= htmlspecialchars($session_swal_text) ?>";
        const sessionSwalIcon = "<?= htmlspecialchars($session_swal_icon) ?>"; 

        if (sessionSwalTitle && sessionSwalIcon) {
             // Prioritaskan Sweet Alert jika ada
             showSweetAlert(sessionSwalIcon, sessionSwalTitle, sessionSwalText, 3500);
        } else {
             // --- 2. LOGIKA TOAST (Fallback untuk pesan yang dibuat di halaman ini) ---
             var toastEl = document.getElementById('liveToast');
             if (toastEl) {
                 var toast = new bootstrap.Toast(toastEl, {
                     autohide: true,
                     delay: 4000 
                 });
                 toast.show();
             }
        }
        
        // LOGIKA AUTO-SUBMIT FORM SAAT KUANTITAS BERUBAH
        if (cartForm) {
            quantityInputs.forEach(function(input) {
                input.addEventListener('change', function() {
                    // Ambil nilai batasan
                    let min = parseInt(this.min);
                    let max = parseInt(this.max);
                    let currentVal = parseInt(this.value);

                    // Perbaiki nilai jika di luar batas
                    if (currentVal < min || isNaN(currentVal)) {
                        this.value = min;
                    } else if (currentVal > max) {
                        this.value = max;
                    }
                    
                    // Beri sedikit delay sebelum submit
                    setTimeout(function() {
                        cartForm.submit();
                    }, 300); 
                });
            });
        }
    });
</script>

</body>
</html>
<?php 
// Tutup koneksi di akhir skrip
if (isset($koneksi)) {
    $koneksi->close(); 
}
?>