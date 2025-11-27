<?php
/**
 * admin/orders/view.php
 * Halaman detail pesanan (View Only).
 * Menggunakan Feather Icons.
 * 🔥 FINAL FIX: Menggunakan CASE statement di Query untuk menangani product_name yang NULL, string kosong, atau string '0'.
 */

$db_koneksi = $GLOBALS['koneksi'] ?? null;
$router_path = $router_path ?? 'dashboard.php';
// Asumsi path URL browser untuk folder payments sudah didefinisikan secara global
$uploads_path_payments = $GLOBALS['uploads_path_payments'] ?? '../uploads/payments/';
if (substr($uploads_path_payments, -1) !== '/') {
    $uploads_path_payments .= '/';
}

$error = [];
$order_id = (int)($_GET['id'] ?? 0);
$order = null;
$order_items = [];

// Fungsi untuk Formatting ID
function format_order_id($id) {
    return '#ORD-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}
$formatted_order_id = 'ID Tidak Valid'; // Default

// Fungsi untuk format mata uang
function format_rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// ==========================================
// LOGIKA READ (GET - Mengambil Detail Pesanan)
// ==========================================

if ($order_id == 0) {
    // Pastikan session sudah dimulai jika belum
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    $_SESSION['message'] = '<div class="alert alert-warning">ID Pesanan tidak valid.</div>';
    header('Location: ' . $router_path . '?page=orders/index');
    exit;
}

if ($db_koneksi) {
    // Query Utama Pesanan
    $sql_order = "SELECT * FROM orders WHERE id = ?";
    $stmt_order = $db_koneksi->prepare($sql_order);
    if (!$stmt_order) {
        $error[] = "Gagal mempersiapkan query pesanan: " . $db_koneksi->error;
    } else {
        $stmt_order->bind_param("i", $order_id);
        $stmt_order->execute();
        $result_order = $stmt_order->get_result();
        
        if ($result_order->num_rows === 1) {
            $order = $result_order->fetch_assoc();
            $formatted_order_id = format_order_id($order['id']); 
        } else {
            // Pastikan session sudah dimulai jika belum
            if (session_status() == PHP_SESSION_NONE) { session_start(); }
            $_SESSION['message'] = '<div class="alert alert-warning">Pesanan tidak ditemukan.</div>';
            header('Location: ' . $router_path . '?page=orders/index');
            exit;
        }
        $stmt_order->close();
    }

    // Query Detail Item Pesanan
    $sql_items = "SELECT 
                    oi.id, 
                    oi.quantity, 
                    oi.price_at_order,
                    -- 🔥 MENGGUNAKAN CASE UNTUK MEMASTIKAN NAMA PRODUK TIDAK KOSONG ATAU '0'
                    CASE 
                        -- Cek jika product_name di order_items NULL, string kosong, atau '0'
                        WHEN oi.product_name IS NULL OR TRIM(oi.product_name) = '' OR oi.product_name = '0' 
                        THEN COALESCE(p.name, 'Produk Dihapus/Tidak Teridentifikasi') -- Fallback ke products.name
                        ELSE oi.product_name -- Gunakan product_name yang ada di order_items
                    END AS product_name 
                  FROM 
                    order_items oi
                  LEFT JOIN
                    products p ON oi.product_id = p.id
                  WHERE 
                    oi.order_id = ?";
    
    $stmt_items = $db_koneksi->prepare($sql_items);
    if (!$stmt_items) {
        $error[] = "Gagal mempersiapkan query item pesanan: " . $db_koneksi->error;
    } else {
        $stmt_items->bind_param("i", $order_id);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();
        $order_items = $result_items->fetch_all(MYSQLI_ASSOC);
        $stmt_items->close();
    }

} else {
    $error[] = "Kesalahan Database: Koneksi database tidak tersedia.";
}

// Menentukan kelas badge untuk tampilan detail
$status_classes = [
    'pending' => 'secondary', 
    'processing' => 'primary', // Key yang disepakati
    'dikirim' => 'info', 
    'selesai' => 'success', 
    'dibatalkan' => 'danger',
    'payment_sent' => 'warning'
];

if ($order) {
    $current_status_key = strtolower($order['status']);
    
    // Pemetaan Status agar konsisten dengan kelas
    if ($current_status_key === 'diproses') {
        $current_status_key = 'processing';
    }
    
    $current_status_class = $status_classes[$current_status_key] ?? 'secondary';
    
    $current_carrier = $order['shipping_carrier'] ?? '';
    $current_tracking = $order['shipping_tracking_number'] ?? '';
} else {
    $current_status_class = 'secondary';
    $current_carrier = '';
    $current_tracking = '';
}
?>

<div class="container-fluid">
    <h1 class="mt-4">Detail Pesanan <?php echo htmlspecialchars($formatted_order_id); ?> (Lihat Saja)</h1>
    
    <?php if (isset($_SESSION['message'])): ?>
        <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <ul><?php foreach ($error as $err): ?><li><?php echo $err; ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <a href="<?php echo $router_path; ?>?page=orders/index" class="btn btn-secondary mb-3">
        <i data-feather="arrow-left" style="width: 16px; height: 16px;"></i> Kembali ke Daftar Pesanan
    </a>
    <a href="<?php echo $router_path; ?>?page=orders/edit&id=<?php echo $order_id; ?>" class="btn btn-warning mb-3">
        <i data-feather="edit-2" style="width: 16px; height: 16px;"></i> Edit Pesanan
    </a>

    <?php if ($order): // Tampilkan konten hanya jika data $order berhasil dimuat ?>
    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-info text-white">
                    <i data-feather="info" class="me-1" style="width: 16px; height: 16px;"></i> Informasi Pesanan
                </div>
                <div class="card-body">
                    <p><strong>ID Pesanan:</strong> <span class="fw-bold"><?php echo htmlspecialchars($formatted_order_id); ?></span></p>
                    <p><strong>Tanggal Pesanan:</strong> <?php echo date("d/m/Y H:i", strtotime($order['order_date'])); ?></p>
                    <p><strong>Total Pembayaran:</strong> <span class="fw-bold text-danger"><?php echo format_rupiah($order['total_amount']); ?></span></p>
                    <p><strong>Metode Pembayaran:</strong> <?php echo htmlspecialchars($order['payment_method'] ?? '-'); ?></p>
                    <p><strong>Kurir Pengiriman:</strong> <?php echo htmlspecialchars($current_carrier ?: '-'); ?></p>
                    <p><strong>Nomor Resi:</strong> 
                        <?php if ($current_tracking): ?>
                            <span class="badge bg-info text-dark fw-bold"><?php echo htmlspecialchars($current_tracking); ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </p>
                    <p>
                        <strong>Status Saat Ini:</strong> 
                        <span class="badge bg-<?php echo $current_status_class; ?> fs-6">
                            <?php 
                                // Menampilkan nama status yang lebih deskriptif
                                if ($current_status_key === 'processing') {
                                    echo 'Diproses';
                                } elseif ($current_status_key === 'payment_sent') {
                                    echo 'Bukti Bayar Terkirim (Perlu Dicek)';
                                } else {
                                    echo htmlspecialchars(ucwords($order['status'])); 
                                }
                            ?>
                        </span>
                    </p>
                    <hr>
                    <h5 class="card-title">Data Pelanggan</h5>
                    <p><strong>Nama Pelanggan:</strong> <?php echo htmlspecialchars($order['customer_name'] ?? '-'); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email'] ?? '-'); ?></p>
                    <p><strong>Alamat Kirim:</strong> <br><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                    
                    <?php if (isset($order['payment_proof_path']) && $order['payment_method'] !== 'COD' && !empty($order['payment_proof_path'])): ?>
                        <hr>
                        <h5 class="card-title">Bukti Pembayaran</h5>
                        <p>
                            <a href="<?php echo $uploads_path_payments . urlencode($order['payment_proof_path']); ?>" target="_blank" class="btn btn-sm btn-success" title="Lihat Bukti Bayar">
                                <i data-feather="eye" style="width: 14px; height: 14px;"></i> Lihat Bukti Pembayaran
                            </a>
                        </p>
                    <?php elseif ($order['payment_method'] !== 'COD'): ?>
                        <hr>
                        <p class="text-danger small">Bukti Pembayaran belum diunggah oleh pelanggan.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i data-feather="shopping-bag" class="me-1" style="width: 16px; height: 16px;"></i> Item Pesanan
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th class="text-nowrap text-end">Harga Satuan</th>
                                    <th class="text-center">Jumlah</th>
                                    <th class="text-nowrap text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td class="text-nowrap text-end"><?php echo format_rupiah($item['price_at_order']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td class="text-nowrap text-end"><?php echo format_rupiah($item['price_at_order'] * $item['quantity']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-secondary">
                                    <th colspan="3" class="text-end">GRAND TOTAL</th>
                                    <th class="text-nowrap text-end text-danger fw-bold"><?php echo format_rupiah($order['total_amount']); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
</script>