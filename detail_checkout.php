<?php
/**
 * detail_checkout.php
 * Halaman frontend untuk menampilkan detail satu pesanan, termasuk opsi upload bukti bayar, kurir, dan Nomor Resi.
 * 🔥 PERBAIKAN FINAL: Menggunakan JOIN ke product_variants untuk size, dan ke products untuk memastikan nama produk selalu tampil (sebagai fallback).
 * 🚀 PERBAIKAN RESPONSIVITAS MOBILE/TAB 🚀
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Pastikan file koneksi ada
if (!file_exists('koneksi.php')) {
    die("Error: File koneksi.php tidak ditemukan.");
}
require_once 'koneksi.php';
global $koneksi; 

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login_user.php');
    exit;
}

$user_id = $_SESSION['user_id'];
// Pastikan order_id sudah bersih
$order_id = $_GET['order_id'] ?? null;
if ($order_id) {
    // Sanitize input untuk keamanan (asumsi $koneksi ada dan merupakan mysqli)
    $order_id = is_numeric($order_id) ? (int)$order_id : null; 
}

$order = null;
$order_items = [];
$formatted_id = 'N/A'; // Default

// Logika Toast Message 
$toast_message = $_SESSION['toast_message'] ?? null;
$toast_type = $_SESSION['toast_type'] ?? 'success';
unset($_SESSION['toast_message'], $_SESSION['toast_type']);

// Fungsi untuk Formatting ID
function format_order_id($id) {
    return '#ORD-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}

// 🔥 DETAIL REKENING BANK (Disimpan di frontend untuk demo. Dalam produksi, ini idealnya diambil dari DB.)
$bank_accounts = [
    'BCA' => ['name' => 'Bank Central Asia', 'number' => '1234-5678-90', 'holder' => 'PT Toko Sepatu Jaya'],
    'MANDIRI' => ['name' => 'Bank Mandiri', 'number' => '9876-5432-10', 'holder' => 'PT Toko Sepatu Jaya'],
    'BRI' => ['name' => 'Bank Rakyat Indonesia', 'number' => '1234-56-789012-345', 'holder' => 'PT Toko Sepatu Jaya'],
    'BNI' => ['name' => 'Bank Negara Indonesia', 'number' => '1122334455', 'holder' => 'PT Toko Sepatu Jaya'],
    'BANK_JATIM' => ['name' => 'Bank Jatim', 'number' => '9876543210', 'holder' => 'PT Toko Sepatu Jaya'],
];

// 1. Validasi Order ID
if (!is_numeric($order_id) || $order_id <= 0) {
    $_SESSION['toast_message'] = "ID Pesanan tidak valid.";
    $_SESSION['toast_type'] = 'error';
    header('Location: orders_user.php'); 
    exit;
}

// 2. Ambil Detail Pesanan dari tabel 'orders'
// Menggunakan prepared statement untuk keamanan
$sql_order = "SELECT id, total_amount, shipping_address, shipping_carrier, shipping_tracking_number, status, order_date, customer_name, customer_email, payment_method, payment_proof_path
             FROM orders 
             WHERE id = ? AND user_id = ?";
             
$stmt_order = $koneksi->prepare($sql_order);
$stmt_order->bind_param("ii", $order_id, $user_id);
$stmt_order->execute();
$result_order = $stmt_order->get_result();

if ($result_order->num_rows > 0) {
    $order = $result_order->fetch_assoc();
    $formatted_id = format_order_id($order['id']); // Format ID
} else {
    $_SESSION['toast_message'] = "Pesanan tidak ditemukan atau tidak memiliki akses.";
    $_SESSION['toast_type'] = 'error';
    header('Location: orders_user.php'); 
    exit;
}
$stmt_order->close();


// 3. 🔥 PERUBAHAN KRITIS: Ambil Item Pesanan dengan DOUBLE JOIN
// Menggunakan prepared statement untuk keamanan
$stmt_items = $koneksi->prepare("
    SELECT 
        oi.product_name AS name_at_order,  -- Nama yang tersimpan (Mungkin kosong)
        oi.quantity, 
        oi.price_at_order,
        p.name AS product_fallback_name,   -- Nama dari tabel products (untuk fallback)
        pv.size AS product_variant_size    -- Ukuran dari tabel product_variants
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id         -- JOIN untuk fallback nama produk
    LEFT JOIN product_variants pv ON oi.variant_id = pv.id    -- JOIN untuk detail ukuran
    WHERE oi.order_id = ?
");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();

while ($item = $result_items->fetch_assoc()) {
    $order_items[] = $item; 
}
$stmt_items->close();

$order_date_raw = $order['order_date']; 

// Helper untuk status badge
$status_classes = [
    'pending' => 'secondary', 'processing' => 'primary', 
    'dikirim' => 'info', 'selesai' => 'success', 'dibatalkan' => 'danger',
    'payment_sent' => 'warning' // Status untuk bukti terkirim
];
$current_status_class = $status_classes[strtolower($order['status'] ?? 'pending')] ?? 'secondary';

// Helper untuk Metode Pembayaran 
$payment_methods_display = [
    'COD' => 'Bayar di Tempat (COD)',
    'BANK_TRANSFER' => 'Transfer Bank',
    'E_WALLET' => 'E-Wallet'
];

// Helper untuk Kurir
$shipping_carriers_display = [
    'JNE' => 'JNE Express',
    'TIKI' => 'TIKI Reguler',
    'POS' => 'POS Indonesia',
    'GOSEND' => 'GoSend',
    'GRABEX' => 'GrabExpress'
];

// Logika penentuan tombol upload
$db_payment_method = $order['payment_method'];
$is_bank_transfer = strpos($db_payment_method, 'BANK_TRANSFER') !== false;
$is_e_wallet = strpos($db_payment_method, 'E_WALLET') !== false;

$payment_requires_proof = $is_bank_transfer || $is_e_wallet; 
$is_pending = (strtolower($order['status']) === 'pending');
$is_payment_sent = (strtolower($order['status']) === 'payment_sent');
$has_proof_path = !empty($order['payment_proof_path']);
$tracking_number = $order['shipping_tracking_number'] ?? '-'; 

// Logika Dapatkan detail bank yang dipilih oleh user dari string `payment_method`
$chosen_bank_code = null;
if ($is_bank_transfer && preg_match('/\((.*?)\)/', $db_payment_method, $matches)) {
    $chosen_bank_code = strtoupper(trim($matches[1] ?? ''));
}
// Ambil detail bank dari array $bank_accounts
$chosen_bank_detail = $bank_accounts[$chosen_bank_code] ?? null;

// URL Bukti Pembayaran 
$payment_proof_url = 'uploads/payments/' . urlencode($order['payment_proof_path'] ?? ''); 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Detail Pesanan <?php echo $formatted_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* 🔥 Styling Baru: Menggunakan skema biru tua/primary yang lebih konsisten */
        :root {
            --bs-primary: #004d99; /* Biru Tua */
            --bs-success: #198754;
        }
        .text-primary { color: var(--bs-primary) !important; }
        .bg-primary { background-color: var(--bs-primary) !important; }
        .btn-primary { 
            background-color: var(--bs-primary); 
            border-color: var(--bs-primary); 
        }
        .btn-primary:hover {
            background-color: #003366;
            border-color: #003366;
        }

        .toast-container {
            z-index: 1080 !important; 
            position: fixed;
            top: 0;
            right: 0;
            margin-top: 15px;
            margin-right: 15px;
        }
        
        /* Box Informasi yang Lebih Terdefinisi */
        .info-box {
            background-color: #f7f9fc;
            border-left: 5px solid var(--bs-primary);
            padding: 15px;
            border-radius: 5px;
            min-height: 100%; /* Agar tinggi kolom sama */
        }
        
        /* Styling Detail Bank */
        .bank-detail-card {
            background-color: #fff3cd; /* Warna kuning lembut */
            border: 1px dashed #ffc107;
            color: #664d03;
            max-width: 450px;
            margin: 20px auto;
        }
        /* Penyesuaian agar bank detail menjadi full-width di mobile */
        @media (max-width: 576px) {
             .bank-detail-card {
               max-width: 100%; /* Full width di mobile */
             }
        }
        .bank-detail-card strong.fs-5 {
            color: var(--bs-primary);
        }

        /* 🚀 Perbaikan Responsivitas Mobile/Tab (Tabel Geser) 🚀 */
        /* Penyesuaian agar hero-section-detail bertumpuk di layar kecil */
        .hero-section-detail .col-md-6 {
            padding-left: calc(var(--bs-gutter-x) * .5);
            padding-right: calc(var(--bs-gutter-x) * .5);
        }

        @media (max-width: 767.98px) { /* Maksimal layar di bawah md (mobile/tablet portrait) */
            .info-box {
                margin-bottom: 20px; /* Tambah jarak antar box di mobile */
            }
            /* Menghapus border-start di mobile agar tidak double, kecuali yang pertama */
            .hero-section-detail .col-md-6:last-child .info-box {
                 border-left-color: var(--bs-success) !important; /* Gunakan warna border yang sesuai */
            }
            .hero-section-detail .col-md-6:last-child .text-success {
                color: var(--bs-success) !important; /* Pertahankan warna judul (hijau) */
            }
            /* **Fokus Perbaikan Geser:** Memaksa padding agar tidak terlalu mepet pada elemen .table-responsive */
            .table-responsive {
                 /* Hanya untuk memastikan area geser punya ruang */
                 margin-left: -5px;
                 margin-right: -5px;
                 padding-left: 5px;
                 padding-right: 5px;
                 overflow-x: auto; /* Memastikan sifat geser aktif */
                 -webkit-overflow-scrolling: touch; /* Untuk iOS smooth scrolling */
            }
            .table {
                font-size: 0.9rem; /* Perkecil font tabel */
                /* Opsional: Tentukan lebar minimum agar tabel pasti bisa digeser */
                min-width: 500px; 
            }
            .py-3.fs-5 {
                font-size: 1.2rem !important; /* Jaga agar Grand Total tetap menonjol */
            }
            .btn-lg {
                font-size: 1rem;
                padding: 0.5rem 1rem;
            }
            
            /* Penyesuaian Kolom Tabel di Mobile */
            .table th, .table td {
                padding: 0.5rem;
            }
            .table thead th:nth-child(2) { width: 15%; } /* Qty lebih lebar sedikit */
            .table thead th:nth-child(3), .table thead th:nth-child(4) { width: 30%; } /* Harga/Subtotal lebih lebar */
        }

        @media print {
            .navbar, .no-print, .toast-container {
                display: none !important;
            }
            .card, .shadow-lg {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
            }
            .container {
                margin: 0;
                padding: 0;
                width: 100%;
            }
            .info-box {
                border-left: none;
                background-color: transparent;
            }
            /* Print Layout */
            .hero-section-detail .col-md-6 {
                width: 50% !important;
                float: left;
                padding: 0 10px;
            }
            .hero-section-detail .border-start {
                border-left: none !important;
            }
            .hero-section-detail::after {
                content: "";
                display: table;
                clear: both;
            }
            /* Pastikan tabel tidak terpotong saat print */
            .table-responsive {
                 overflow-x: visible !important;
            }
            .table {
                 min-width: 100% !important;
            }
        }
    </style>
</head>
<body class="bg-light">

<?php if (isset($toast_message) && $toast_message): 
    $toast_class = ($toast_type === 'error') ? 'bg-danger' : (($toast_type === 'warning') ? 'bg-warning text-dark' : (($toast_type === 'info') ? 'bg-info' : 'bg-success'));
?>
<div class="toast-container">
    <div id="liveToast" class="toast fade align-items-center text-white <?php echo $toast_class; ?>" 
        role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-bold">
                <?php echo $toast_message; ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm no-print">
    <div class="container">
        <a class="navbar-brand text-primary fw-bold" href="toko_sepatu.php">Toko SepatuKu</a>
        <a class="btn btn-sm btn-outline-secondary ms-auto d-none d-sm-inline-block" href="orders_user.php"><i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar Pesanan</a>
        <a class="btn btn-sm btn-outline-secondary ms-auto d-sm-none" href="orders_user.php"><i class="fas fa-arrow-left"></i></a>
    </div>
</nav>

<div class="container mt-4 mb-5 mt-sm-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-11"> 
            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0 fs-5"><i class="fas fa-box-open me-2"></i> Detail Pesanan <?php echo $formatted_id; ?></h5>
                </div>
                <div class="card-body p-4 p-sm-4">
                    
                    <div class="row mb-4 hero-section-detail">
                        
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="info-box">
                                <h6 class="mb-3 text-primary"><i class="fas fa-clipboard-list me-1"></i> Informasi Pesanan</h6>
                                
                                <p class="mb-2 small"><strong>ID Pesanan:</strong> <span class="fw-bold text-danger"><?php echo $formatted_id; ?></span></p>
                                <p class="mb-2 small"><strong>Tanggal Pesan:</strong> <?php echo date('d M Y, H:i', strtotime($order_date_raw)); ?></p>
                                <p class="mb-2">
                                    <strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $current_status_class; ?> fs-6">
                                        <?php echo htmlspecialchars(ucwords($order['status'])); ?>
                                    </span>
                                </p>
                                <hr class="my-2">
                                <p class="mb-2 small"><strong>Metode Bayar:</strong> <span class="fw-bold"><?php echo htmlspecialchars($payment_methods_display[$db_payment_method] ?? $db_payment_method ?? '-'); ?></span></p>
                                <p class="mb-2 small">
                                    <strong>Kurir:</strong> 
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-truck me-1"></i> <?php echo htmlspecialchars($shipping_carriers_display[$order['shipping_carrier'] ?? ''] ?? $order['shipping_carrier'] ?? '-'); ?>
                                    </span>
                                </p>
                                <?php if ($tracking_number != '-'): ?>
                                <p class="mb-0 small">
                                    <strong>Nomor Resi:</strong> 
                                    <span class="fw-bold text-success">
                                        <i class="fas fa-barcode me-1"></i> <?php echo htmlspecialchars($tracking_number); ?>
                                    </span>
                                </p>
                                <?php else: ?>
                                <p class="mb-0 small"><strong>Nomor Resi:</strong> -</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="info-box" style="border-left: 5px solid var(--bs-success);"> 
                                <h6 class="mb-3 text-success"><i class="fas fa-map-marker-alt me-1"></i> Detail Pengiriman</h6>
                                
                                <p class="mb-2 small"><strong>Penerima:</strong> <?php echo htmlspecialchars($order['customer_name'] ?? $_SESSION['user_name'] ?? '-'); ?></p>
                                <p class="mb-2 small"><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email'] ?? $_SESSION['user_email'] ?? '-'); ?></p>
                                <p class="mb-0 small"><strong>Alamat Pengiriman:</strong> <br>
                                    <span class="d-block border p-2 bg-white rounded small mt-1">
                                        <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6 class="mt-4 mb-3 border-bottom pb-2 text-primary"><i class="fas fa-hand-holding-dollar me-1"></i> Status Pembayaran</h6>
                    
                    <?php if ($payment_requires_proof): // Non-COD (Transfer/E-Wallet) ?>
                        <div class="alert alert-<?php echo ($is_pending) ? 'warning' : (($is_payment_sent) ? 'info' : 'success'); ?> p-3 text-center">
                            
                            <?php if ($is_pending): ?>
                                <p class="mb-2 fw-bold fs-6">Pesanan ini memerlukan bukti pembayaran.</p>
                                <p class="mb-0 small">Mohon segera transfer total **Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?>**.</p>
                                
                                <?php if ($is_bank_transfer && $chosen_bank_detail): ?>
                                    <div class="card p-3 my-3 bank-detail-card">
                                        <h6 class="fw-bold mb-2 small"><i class="fas fa-money-check-alt me-1"></i> Transfer ke Rekening Tujuan:</h6>
                                        <p class="mb-1 small">Bank Tujuan: <strong class="text-primary"><?php echo htmlspecialchars($chosen_bank_detail['name']); ?></strong></p>
                                        <p class="mb-1">Nomor Rekening: <strong class="text-dark fs-5 copy-nr" style="cursor: pointer;" title="Klik untuk menyalin"><?php echo htmlspecialchars($chosen_bank_detail['number']); ?> <i class="far fa-copy small ms-1"></i></strong></p>
                                        <p class="mb-0 small">Atas Nama: <strong class="text-dark"><?php echo htmlspecialchars($chosen_bank_detail['holder']); ?></strong></p>
                                    </div>
                                <?php endif; ?>

                                <a href="payment_upload.php?order_id=<?php echo $order['id']; ?>" class="btn btn-success btn-lg mt-3 w-100 w-sm-auto">
                                    <i class="fas fa-upload me-2"></i> Kirim Bukti Pembayaran
                                </a>
                                
                            <?php elseif ($is_payment_sent): ?>
                                <p class="mb-2 fw-bold fs-6">Bukti Pembayaran Sudah Terkirim (Menunggu Verifikasi Admin).</p>
                                <p class="mb-0 small">Kami akan memproses pesanan setelah bukti bayar dikonfirmasi.</p>
                                <?php if ($has_proof_path): ?>
                                    <a href="<?php echo $payment_proof_url; ?>" target="_blank" class="btn btn-sm btn-info text-white mt-2">
                                        <i class="fas fa-eye me-1"></i> Lihat Bukti yang Diunggah
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="mb-0 fw-bold fs-6">Pembayaran Telah Diverifikasi. Pesanan sedang diproses.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: // COD (Bayar di Tempat) ?>
                        <div class="alert alert-success p-3 text-center">
                            <p class="mb-2 fw-bold fs-6">Metode Pembayaran: Bayar di Tempat (COD)</p>
                            <p class="mb-0 small">Total yang harus Anda bayarkan kepada kurir adalah **Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?>**.</p>
                            <p class="mb-0 small mt-2">Pesanan Anda telah dikonfirmasi dan sedang diproses untuk pengiriman. Mohon siapkan uang tunai yang pas.</p>
                        </div>
                    <?php endif; ?>
                    
                    <h6 class="mt-5 mb-3 border-bottom pb-2 text-primary"><i class="fas fa-list-ul me-1"></i> Item Pesanan:</h6>
                    <div class="table-responsive"> 
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr class="bg-light">
                                    <th class="text-primary">Produk</th>
                                    <th class="text-center" style="width:10%;">Qty</th>
                                    <th class="text-end" style="width:20%;">Harga Satuan</th>
                                    <th class="text-end" style="width:20%;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $subtotal = 0; ?>
                                <?php 
                                // Cek apakah array order_items terisi
                                if (empty($order_items)) : ?>
                                <tr>
                                    <td colspan="4" class="text-center text-danger">Tidak ada item yang ditemukan untuk pesanan ini.</td>
                                </tr>
                                <?php
                                else:
                                foreach ($order_items as $item): 
                                    $item_total = $item['price_at_order'] * $item['quantity'];
                                    $subtotal += $item_total;
                                    
                                    // 🔥 Logika Tampilan Nama Produk: Pilih dari 'name_at_order' atau 'product_fallback_name'
                                    $product_name_to_display = !empty($item['name_at_order']) ? $item['name_at_order'] : $item['product_fallback_name'];
                                    
                                    // Tentukan tampilan akhir (termasuk placeholder jika kedua-duanya kosong)
                                    if (empty($product_name_to_display)) {
                                        $product_display = '<i class="text-danger small">Produk (Nama Tidak Tersedia - Cek DB)</i>';
                                    } else {
                                        $product_display = htmlspecialchars($product_name_to_display);
                                    }

                                    // 🔥 Implementasi Tampilan Varian (Size)
                                    if (!empty($item['product_variant_size'])) {
                                        $product_display .= ' <span class="badge bg-secondary ms-1 fw-normal">Size: ' . htmlspecialchars($item['product_variant_size']) . '</span>';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo $product_display; ?></td>
                                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                                    <td class="text-end">Rp <?php echo number_format($item['price_at_order'], 0, ',', '.'); ?></td>
                                    <td class="text-end">Rp <?php echo number_format($item_total, 0, ',', '.'); ?></td>
                                </tr>
                                <?php endforeach; 
                                endif; // Akhir if (empty($order_items))
                                ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-primary text-white fw-bold">
                                    <td colspan="3" class="text-end py-3 fs-5">GRAND TOTAL</td>
                                    <td class="py-3 text-end"><strong class="fs-5">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="no-print d-flex flex-column flex-sm-row justify-content-between align-items-center mt-4 gap-2">
                        <button class="btn btn-outline-primary w-100 w-sm-auto" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Cetak Detail Pesanan
                        </button>
                        <a href="toko_sepatu.php" class="btn btn-success w-100 w-sm-auto">
                            <i class="fas fa-home me-1"></i> Lanjut Belanja
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    <?php if (isset($toast_message) && $toast_message): ?>
    document.addEventListener('DOMContentLoaded', function() {
        var toastEl = document.getElementById('liveToast');
        if (toastEl) {
            var toast = new bootstrap.Toast(toastEl, {
                autohide: true,
                delay: 7000 
            });
            toast.show();
        }
    });
    <?php endif; ?>

    // 🔥 Script untuk Menyalin Nomor Rekening
    document.addEventListener('DOMContentLoaded', function() {
        const copyElement = document.querySelector('.copy-nr');
        if (copyElement) {
            copyElement.addEventListener('click', function() {
                // Hapus spasi dan strip yang tidak perlu dari nomor rekening, hanya menyisakan angka dan '-' yang valid
                const accountNumber = this.textContent.replace(/[^\d-]/g, '').trim();
                navigator.clipboard.writeText(accountNumber).then(() => {
                    // Beri feedback visual
                    const icon = this.querySelector('.far.fa-copy');
                    const originalText = this.innerHTML;
                    
                    if (icon) icon.remove(); // Hapus ikon copy lama
                    this.innerHTML = '<i class="fas fa-check-circle me-1"></i> Tersalin!';
                    this.classList.add('text-success');
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('text-success');
                    }, 1500);
                }).catch(err => {
                    console.error('Gagal menyalin:', err);
                    alert('Gagal menyalin nomor rekening. Silakan salin manual.');
                });
            });
        }
    });
</script>

</body>
</html>
<?php 
// Koneksi ditutup di sini (akhir file)
if (isset($koneksi) && $koneksi instanceof mysqli) {
    $koneksi->close(); 
}
?>