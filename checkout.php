<?php
/**
 * checkout.php
 * Halaman konfirmasi checkout dengan dukungan VARIAN (product_variants).
 * Item key: product_id_variant_id
 *
 * PERBAIKAN: Mengatasi "Unknown column 'variant_id' in 'field list'" dan memisahkan penyimpanan detail bank.
 *
 * MODIFIKASI: Penambahan kelas Bootstrap untuk dukungan responsive (mobile-first).
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';
global $koneksi;

// Daftar metode pembayaran yang tersedia
$payment_methods = [
    'COD' => 'Bayar di Tempat (Cash On Delivery)',
    'BANK_TRANSFER' => 'Transfer Bank (BCA/Mandiri/Lainnya)',
    'E_WALLET' => 'E-Wallet (Dana/Gopay/OVO)'
];

// 🔥 Daftar bank untuk Transfer Bank
$bank_options = [
    'BCA' => 'Bank Central Asia (BCA)',
    'MANDIRI' => 'Bank Mandiri',
    'BRI' => 'Bank Rakyat Indonesia (BRI)',
    'BNI' => 'Bank Negara Indonesia (BNI)',
    'BANK_JATIM' => 'Bank Jatim',
    'LAINNYA' => 'Bank Lainnya'
];

// Daftar pilihan kurir yang tersedia
$shipping_carriers = [
    'JNE' => 'JNE Express',
    'TIKI' => 'TIKI Reguler',
    'POS' => 'POS Indonesia',
    'GOSEND' => 'GoSend (Instant/Same Day)',
    'GRABEX' => 'GrabExpress (Instant/Same Day)'
];

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login_user.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$cart_items = $_SESSION['cart'] ?? [];
$total_price = 0;
$error = [];
$order_id = null; 

// Ambil detail pelanggan dari sesi
$customer_name = $_SESSION['user_name'] ?? 'Pelanggan ID ' . $user_id;
$customer_email = $_SESSION['user_email'] ?? ''; 

// Cek jika keranjang kosong
if (empty($cart_items)) {
    $_SESSION['toast_message'] = "Keranjang Anda kosong. Tidak dapat melakukan checkout.";
    $_SESSION['toast_type'] = 'warning';
    header('Location: cart.php'); 
    exit;
}

// Hitung total harga dan validasi data item di keranjang
$item_data_for_checkout = [];
foreach ($cart_items as $item_key => $item) {
    $price = (float)($item['current_price'] ?? $item['price'] ?? 0);
    $quantity = (int)($item['quantity'] ?? 0);
    
    if ($price > 0 && $quantity > 0) {
        $total_price += $price * $quantity;

        $parts = explode('_', $item_key);
        $product_id = (int)($parts[0] ?? 0);
        // Penting: Ambil variant_id, jika tidak ada, berikan nilai 0 atau NULL
        // Kita gunakan 0 di sini, nanti di DB bisa jadi NULL jika kolomnya NULLABLE
        $variant_id = (int)($parts[1] ?? 0); 

        // Validasi, pastikan product_id ada. variant_id bisa 0 jika produk non-varian.
        if ($product_id > 0) {
            $item_data_for_checkout[$item_key] = [
                'product_id' => $product_id,
                'variant_id' => $variant_id > 0 ? $variant_id : NULL, // Gunakan NULL jika tidak ada varian
                'name' => $item['product_name'] ?? $item['name'] ?? 'Produk Tidak Dikenal',
                'size' => $item['variant_size'] ?? 'N/A',
                'quantity' => $quantity,
                'price' => $price
            ];
        }
    }
}

// Cek ulang jika total harga menjadi 0 setelah pembersihan
if ($total_price <= 0 || empty($item_data_for_checkout)) {
    $_SESSION['toast_message'] = "Keranjang Anda tidak memiliki item valid untuk checkout.";
    $_SESSION['toast_type'] = 'warning';
    header('Location: cart.php'); 
    exit;
}

// Simpan nilai default atau dari input POST
$address = trim($_POST['address'] ?? '');
$payment_method = $_POST['payment_method'] ?? 'COD'; 
$shipping_carrier = $_POST['shipping_carrier'] ?? 'JNE';
// 🔥 BARU: Simpan pilihan bank
$selected_bank = $_POST['selected_bank'] ?? 'BCA';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Validasi Input Form
    if (empty($address)) {
        $error[] = "Alamat pengiriman wajib diisi.";
    }

    // 2. Validasi Metode Pembayaran & Kurir 
    if (!array_key_exists($payment_method, $payment_methods)) {
        $error[] = "Metode pembayaran tidak valid.";
    }
    if (!array_key_exists($shipping_carrier, $shipping_carriers)) {
        $error[] = "Pilihan kurir tidak valid.";
    }

    // 🔥 BARU: Validasi Pilihan Bank jika metode transfer
    if ($payment_method === 'BANK_TRANSFER') {
        if (!array_key_exists($selected_bank, $bank_options)) {
            $error[] = "Pilihan bank tidak valid.";
        }
    }

    // 🔥🔥 3. PENGECEKAN KETERSEDIAAN STOK VARIAN
    // Hanya cek stok varian jika ada item yang memiliki variant_id > 0
    $variant_ids_to_check = array_filter(array_column($item_data_for_checkout, 'variant_id'));
    
    if (empty($error) && !empty($variant_ids_to_check)) {
        
        $placeholders = implode(',', array_fill(0, count($variant_ids_to_check), '?'));
        
        // Menggunakan FOR UPDATE untuk mengunci baris stok
        $sql_stock_check = "
            SELECT 
                pv.id AS variant_id, 
                pv.stock,
                p.name AS product_name, 
                pv.size
            FROM product_variants pv
            JOIN products p ON pv.product_id = p.id
            WHERE pv.id IN ($placeholders) FOR UPDATE"; 
        
        $stmt_check = $koneksi->prepare($sql_stock_check);
        
        if ($stmt_check === false) {
             $error[] = "Error database saat mengecek stok. Coba lagi.";
        } else {
            $types = str_repeat('i', count($variant_ids_to_check));
            $stmt_check->bind_param($types, ...$variant_ids_to_check);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            $available_stock_map = [];
            while($row = $result_check->fetch_assoc()) {
                $available_stock_map[$row['variant_id']] = [
                    'stock' => (int)$row['stock'],
                    'name_full' => htmlspecialchars($row['product_name']) . ' (Ukuran: ' . htmlspecialchars($row['size']) . ')',
                ];
            }
            $stmt_check->close();

            foreach ($item_data_for_checkout as $item_key => $item) {
                // Hanya cek jika item memiliki variant_id (produk bervarian)
                if (!is_null($item['variant_id'])) { 
                    $variant_id = $item['variant_id'];
                    $required_quantity = $item['quantity'];
                    
                    $stock_info = $available_stock_map[$variant_id] ?? ['stock' => 0, 'name_full' => $item['name'] . ' (Ukuran: ' . $item['size'] . ')'];
                    
                    if ($stock_info['stock'] < $required_quantity) {
                        $stok_tersedia = $stock_info['stock'];
                        $error[] = "❌ Stok untuk varian **{$stock_info['name_full']}** tidak mencukupi (Tersedia: {$stok_tersedia}, Diminta: {$required_quantity}). Silakan kembali ke keranjang.";
                        break; 
                    }
                }
            }
        }
    }
    // 🔥🔥 AKHIR PENGECEKAN STOK VARIAN
    
    if (empty($error)) {
        // Mulai transaksi
        $koneksi->begin_transaction();
        
        // LOGIKA BARU UNTUK MENENTUKAN STATUS AWAL
        $default_status = ($payment_method === 'COD') ? 'processing' : 'pending'; 

        try {
            // 1. INSERT INTO orders 
            // 🔥 PERUBAHAN: Gunakan kolom bank_chosen terpisah untuk penyimpanan bank
            $bank_to_insert = ($payment_method === 'BANK_TRANSFER') ? $selected_bank : NULL;
            $null_tracking = NULL; // Untuk kolom shipping_tracking_number

            $stmt_order = $koneksi->prepare("
                INSERT INTO orders 
                (user_id, customer_name, customer_email, total_amount, shipping_address, shipping_carrier, status, payment_method, bank_chosen, shipping_tracking_number) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt_order->bind_param(
                "issdssssss", // 1 int, 2 str, 1 double, 6 str (termasuk bank_chosen dan tracking)
                $user_id, 
                $customer_name, 
                $customer_email, 
                $total_price, 
                $address,
                $shipping_carrier, 
                $default_status, 
                $payment_method, // Simpan hanya BANK_TRANSFER/COD/E_WALLET
                $bank_to_insert,  // Simpan detail bank (e.g., BCA)
                $null_tracking    // Tracking number awal NULL
            ); 
            
            if (!$stmt_order->execute()) {
                throw new Exception("Gagal menyimpan pesanan utama: " . $stmt_order->error);
            }
            
            $order_id = $koneksi->insert_id;
            $stmt_order->close();

            // 2. Simpan item dan update stok
            // 🔥 PERBAIKAN: Kolom variant_id di order_items harus sudah ada di database
            $stmt_item = $koneksi->prepare("
                INSERT INTO order_items 
                (order_id, product_id, variant_id, product_name, quantity, price_at_order) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            // Statement update stok hanya untuk varian. Asumsikan produk tanpa varian stoknya tidak dikelola di product_variants.
            $stmt_stock = $koneksi->prepare("UPDATE product_variants SET stock = stock - ? WHERE id = ?");

            foreach ($item_data_for_checkout as $item) {
                // Simpan Item
                $variant_id_to_insert = $item['variant_id']; // Akan menjadi NULL jika non-varian
                
                $stmt_item->bind_param("iisisd", // i (order_id), i (product_id), s (variant_id/NULL), s (name), i (quantity), d (price)
                    $order_id, 
                    $item['product_id'], 
                    $variant_id_to_insert, 
                    $item['name'], 
                    $item['quantity'], 
                    $item['price']
                );
                
                if (!$stmt_item->execute()) {
                    throw new Exception("Gagal menyimpan item pesanan untuk produk {$item['name']}.");
                }

                // Update Stok HANYA jika ada variant_id
                if (!is_null($variant_id_to_insert)) {
                    $stmt_stock->bind_param("ii", $item['quantity'], $variant_id_to_insert);
                    if (!$stmt_stock->execute()) {
                        throw new Exception("Gagal mengupdate stok untuk varian ID {$variant_id_to_insert}.");
                    }
                }
            }

            $stmt_item->close();
            $stmt_stock->close();
            $koneksi->commit(); // Komit (Sukses)
            
            // 3. Bersihkan Keranjang & Redirect
            unset($_SESSION['cart']);

            // Buat pesan toast yang lebih spesifik
            $payment_display_name = $payment_methods[$payment_method] ?? $payment_method;
            if ($payment_method === 'BANK_TRANSFER') {
                $payment_display_name = "Transfer Bank ke **" . htmlspecialchars($bank_options[$selected_bank] ?? 'Bank yang Dipilih') . "**";
            } else if ($payment_method === 'COD') {
                $payment_display_name = "Bayar di Tempat (COD)";
            }

            if ($payment_method === 'COD') {
                $_SESSION['toast_message'] = "🎉 Pesanan Anda (ID: {$order_id}) berhasil dibuat dengan **{$payment_display_name}**. Pesanan Anda akan segera diproses. Siapkan uang tunai sebesar Rp " . number_format($total_price, 0, ',', '.') . " saat kurir tiba.";
                $_SESSION['toast_type'] = 'success'; 
            } else {
                $_SESSION['toast_message'] = "✅ Pesanan Anda (ID: {$order_id}) berhasil dibuat! Segera lakukan pembayaran melalui **{$payment_display_name}** dan unggah bukti di halaman detail pesanan.";
                $_SESSION['toast_type'] = 'info'; 
            }
            
            header('Location: detail_checkout.php?order_id=' . $order_id);
            exit;

        } catch (Exception $e) {
            $koneksi->rollback(); // Rollback (Gagal)
            // Tampilkan pesan error lengkap
            $error[] = "Checkout gagal: " . $e->getMessage() . " Transaksi dibatalkan.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Konfirmasi Pesanan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Perbaikan Styling: Warna yang Lebih Bersih dan Fokus */
        :root {
            --bs-primary: #198754; /* Hijau yang lebih solid */
            --bs-primary-rgb: 25, 135, 84;
            --bs-success: #198754;
            --bs-success-rgb: 25, 135, 84;
            --bs-info-light: #f0f7f4; /* Hijau sangat muda untuk background summary */
        }

        .checkout-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        /* Custom Styling untuk Ringkasan Belanja */
        .summary-card {
            background-color: var(--bs-info-light); 
            border: 1px solid var(--bs-primary); 
            border-radius: 8px;
            padding: 20px;
        }
        .summary-card h4 {
            color: var(--bs-primary);
            border-bottom: 2px solid var(--bs-primary);
            padding-bottom: 10px;
        }
        .form-control, .form-select {
            border-radius: 0.5rem;
        }
        .form-select-lg {
            font-size: 1.1rem;
        }
        /* Mengganti total-price-box dengan warna yang lebih elegan (dark) */
        .total-price-box {
            background-color: #343a40; /* Dark gray */
            color: white;
            padding: 15px; /* Sedikit lebih besar */
            border-radius: 5px;
        }
        .total-price-box h5, .total-price-box h4 {
            color: white !important; /* Pastikan teks putih */
        }
        .navbar-brand.text-primary {
            color: var(--bs-primary) !important;
        }
        .text-primary {
            color: var(--bs-primary) !important;
        }
        .btn-primary {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        .btn-primary:hover {
            background-color: #157347; /* Sedikit lebih gelap saat hover */
            border-color: #157347;
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container checkout-container">
        <a class="navbar-brand fw-bold text-success" href="toko_sepatu.php"></a>
        <a class="btn btn-sm btn-outline-secondary" href="cart.php">
            <i class="fas fa-arrow-left me-1"></i> Kembali ke Keranjang
        </a>
    </div>
</nav>

<div class="container checkout-container mt-5 mb-5">
    <h1 class="mb-5 text-center text-success">Konfirmasi Pesanan Anda</h1>

    <?php 
    if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <p class="fw-bold">Gagal memproses pesanan:</p>
            <ul><?php foreach ($error as $err): ?><li><?php echo $err; ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">
            <div class="row">
                
                <div class="col-12 col-md-5 mb-4 mb-md-0"> 
                    <div class="summary-card h-100">
                        <h4 class="mb-4"><i class="fas fa-receipt me-2"></i> Ringkasan Belanja</h4>
                        
                        <ul class="list-group list-group-flush mb-4">
                            <?php foreach ($item_data_for_checkout as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-2 border-bottom">
                                    <span class="text-dark fw-light me-2"> 
                                        <?php echo htmlspecialchars($item['name']); ?> 
                                        <small class="text-muted d-block">Ukuran: <?php echo htmlspecialchars($item['size']); ?> | Harga: Rp <?php echo number_format($item['price'], 0, ',', '.'); ?> x <?php echo $item['quantity']; ?></small>
                                    </span>
                                    <span class="fw-bold text-end flex-shrink-0">
                                        Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4 total-price-box">
                            <h5 class="mb-0 fw-bold">TOTAL PESANAN</h5>
                            <h4 class="mb-0 fw-bolder">Rp <?php echo number_format($total_price, 0, ',', '.'); ?></h4>
                        </div>
                        
                        <div class="pt-3 border-top">
                            <p class="small text-muted mb-1">
                                Metode Pembayaran Dipilih: 
                                <strong class="text-dark">
                                    <?php 
                                        $display_method = $payment_methods[$payment_method] ?? '-';
                                        if ($payment_method === 'BANK_TRANSFER') {
                                            $display_method .= ' (' . htmlspecialchars($bank_options[$selected_bank] ?? 'Bank Tidak Diketahui') . ')';
                                        }
                                        echo htmlspecialchars($display_method); 
                                    ?>
                                </strong>
                            </p>
                            <p class="small text-muted mb-1">
                                Kurir Dipilih: <strong class="text-dark"><?php echo htmlspecialchars($shipping_carriers[$shipping_carrier] ?? '-'); ?></strong>
                            </p>
                            <p class="small text-muted mb-0">
                                Status Awal: <strong class="text-dark"><?php echo ($payment_method === 'COD' ? 'Siap Diproses' : 'Menunggu Pembayaran'); ?></strong>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-md-7 border-start ps-md-4 pt-4 pt-md-0">
                    <h4 class="mb-4 text-success"><i class="fas fa-user-check me-2"></i> Detail Pengiriman & Pilihan</h4>
                    
                    <form action="checkout.php" method="POST">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="user_name" class="form-label fw-bold small">Nama Penerima</label>
                                <input type="text" class="form-control" id="user_name" value="<?php echo htmlspecialchars($customer_name); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label for="user_email" class="form-label fw-bold small">Email Kontak</label>
                                <input type="email" class="form-control" id="user_email" value="<?php echo htmlspecialchars($customer_email); ?>" disabled>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="address" class="form-label fw-bold"><i class="fas fa-map-marker-alt me-1 text-success"></i> Alamat Pengiriman Lengkap</label>
                            <textarea class="form-control" id="address" name="address" rows="3" placeholder="Masukkan alamat, kode pos, dan nomor kontak yang mudah dihubungi" required><?php echo htmlspecialchars($_POST['address'] ?? $address); ?></textarea>
                            <div class="form-text">Pastikan alamat Anda sudah benar, pesanan akan dikirim ke alamat ini.</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="shipping_carrier" class="form-label fw-bold"><i class="fas fa-shipping-fast me-1 text-success"></i> Pilih Kurir Pengiriman</label>
                            <select class="form-select form-select-lg" id="shipping_carrier" name="shipping_carrier" required>
                                <?php foreach ($shipping_carriers as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($shipping_carrier == $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Pilihan kurir akan menentukan biaya dan estimasi waktu tiba (saat ini biaya kirim diabaikan).</div>
                        </div>

                        <div class="mb-4">
                            <label for="payment_method" class="form-label fw-bold"><i class="fas fa-credit-card me-1 text-success"></i> Pilih Metode Pembayaran</label>
                            <select class="form-select form-select-lg" id="payment_method" name="payment_method" required onchange="toggleBankSelection()">
                                <?php foreach ($payment_methods as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($payment_method == $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text text-danger">Pilih **Bayar di Tempat (COD)** jika Anda ingin membayar saat barang diterima.</div>
                        </div>
                        
                        <div id="bank-selection-container" class="mb-5" style="display: <?php echo ($payment_method === 'BANK_TRANSFER' ? 'block' : 'none'); ?>;">
                            <label for="selected_bank" class="form-label fw-bold"><i class="fas fa-building-columns me-1 text-success"></i> Pilih Bank Tujuan Transfer</label>
                            <select class="form-select form-select-lg" id="selected_bank" name="selected_bank">
                                <?php foreach ($bank_options as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($selected_bank == $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Pilih bank tempat Anda akan melakukan transfer.</div>
                        </div>
                        <button type="submit" class="btn btn-success btn-lg w-100 py-3 shadow-sm">
                            <i class="fas fa-check-circle me-2"></i> SELESAIKAN PESANAN
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// 🔥 BARU: Fungsi JavaScript untuk menampilkan/menyembunyikan pilihan bank
function toggleBankSelection() {
    const paymentMethod = document.getElementById('payment_method').value;
    const bankContainer = document.getElementById('bank-selection-container');
    
    if (paymentMethod === 'BANK_TRANSFER') {
        bankContainer.style.display = 'block';
        document.getElementById('selected_bank').setAttribute('required', 'required');
    } else {
        bankContainer.style.display = 'none';
        document.getElementById('selected_bank').removeAttribute('required');
    }
}

// Panggil saat halaman dimuat untuk menyesuaikan tampilan awal
document.addEventListener('DOMContentLoaded', toggleBankSelection);
</script>
</body>
</html>
<?php $koneksi->close(); ?>