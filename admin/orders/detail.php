<?php
/**
 * admin/orders/detail.php
 * Halaman detail pesanan untuk admin.
 * Menampilkan detail lengkap, item, pelanggan, dan formulir update status/resi.
 * PERBAIKAN STYLING: Ditingkatkan untuk tampilan admin yang modern dan profesional.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Asumsi: File ini di-include oleh dashboard.php, sehingga $koneksi dan $router_path sudah tersedia.
$koneksi = $GLOBALS['koneksi'] ?? null;
$router_path = 'dashboard.php';

// Cek otentikasi admin (Asumsi ada mekanisme otentikasi admin)
// if (!isset($_SESSION['admin_id'])) {
//     header("Location: login_admin.php");
//     exit;
// }

// 🔥 PERBAIKAN: Definisikan fungsi helper yang hilang
if (!function_exists('format_rupiah')) {
    function format_rupiah($angka) {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}
// END PERBAIKAN

$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$order = null;
$order_items = [];
$error = [];

// Daftar Status dan Kurir yang tersedia
$available_statuses = [
    'Baru' => 'Pesanan Baru (Menunggu Cek)',
    'pending' => 'Menunggu Pembayaran',
    'payment_sent' => 'Bukti Bayar Terkirim (Perlu Verifikasi)',
    'processing' => 'Sedang Diproses/Dikemas',
    'dikirim' => 'Sudah Dikirim',
    'selesai' => 'Selesai/Diterima',
    'dibatalkan' => 'Dibatalkan'
];

$shipping_carriers = [
    'JNE' => 'JNE Express',
    'TIKI' => 'TIKI Reguler',
    'POS' => 'POS Indonesia',
    'GOSEND' => 'GoSend',
    'GRABEX' => 'GrabExpress'
];

// Fungsi untuk Formatting ID
function format_order_id($id) {
    return '#ORD-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}

// Cek ID dan Koneksi
if (!$koneksi || !$order_id || $order_id <= 0) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => "ID Pesanan tidak valid atau koneksi database gagal."];
    header("Location: {$router_path}?page=orders/index");
    exit;
}

// ===================================================
// --- 1. AMBIL DETAIL PESANAN ---
// ===================================================
$sql_order = "SELECT o.*, u.name AS user_name, u.email AS user_email 
              FROM orders o
              LEFT JOIN users u ON o.user_id = u.id
              WHERE o.id = ?";
$stmt_order = $koneksi->prepare($sql_order);
$stmt_order->bind_param("i", $order_id);
$stmt_order->execute();
$result_order = $stmt_order->get_result();
$order = $result_order->fetch_assoc();
$stmt_order->close();

if (!$order) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => "Pesanan dengan ID " . format_order_id($order_id) . " tidak ditemukan."];
    header("Location: {$router_path}?page=orders/index");
    exit;
}

$formatted_order_id = format_order_id($order_id);

// --- 2. AMBIL ITEM PESANAN ---
$sql_items = "SELECT product_name, quantity, price_at_order, variant_id FROM order_items WHERE order_id = ?";
$stmt_items = $koneksi->prepare($sql_items);
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();
while ($item = $result_items->fetch_assoc()) {
    $order_items[] = $item;
}
$stmt_items->close();

// --- 3. LOGIKA UPDATE STATUS/RESI (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    
    $new_status = $_POST['status'] ?? $order['status'];
    $new_resi = trim($_POST['shipping_tracking_number'] ?? $order['shipping_tracking_number']);
    
    if (!array_key_exists($new_status, $available_statuses)) {
        $error[] = "Status yang dipilih tidak valid.";
    }

    if (empty($error)) {
        $sql_update = "UPDATE orders SET status = ?, shipping_tracking_number = ? WHERE id = ?";
        $stmt_update = $koneksi->prepare($sql_update);
        
        if ($stmt_update) {
            $stmt_update->bind_param("ssi", $new_status, $new_resi, $order_id);
            if ($stmt_update->execute()) {
                $_SESSION['message'] = [
                    'type' => 'success',
                    'text' => "Status Pesanan {$formatted_order_id} berhasil diperbarui menjadi **" . htmlspecialchars($available_statuses[$new_status]) . "**."
                ];
                // Refresh data order setelah update
                header("Location: {$router_path}?page=orders/detail&id={$order_id}");
                exit;
            } else {
                $error[] = "Gagal memperbarui database: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $error[] = "Gagal menyiapkan query update.";
        }
    }
}

// Helper untuk status badge (disalin dari dashboard/main.php)
$status_classes = [
    'Baru' => 'danger',
    'pending' => 'danger', 
    'payment_sent' => 'warning',
    'processing' => 'primary', 
    'dikirim' => 'info', 
    'selesai' => 'success', 
    'dibatalkan' => 'secondary'
];
$current_status_class = $status_classes[strtolower($order['status'] ?? 'pending')] ?? 'secondary';
$current_status_display = $available_statuses[$order['status']] ?? $order['status'];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan Admin <?php echo $formatted_order_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://unpkg.com/feather-icons"></script>
    
    <style>
        /* 🔥 CSS INTERNAL YANG DITINGKATKAN */
        :root {
            --primary-admin: #2C3E50; /* Dark Blue/Charcoal */
            --accent-green: #27AE60;
            --accent-orange: #F39C12;
            --accent-blue: #3498DB;
            --danger-color: #E74C3C;
        }

        body {
            background-color: #ecf0f1; /* Light gray background */
            color: #34495e;
        }

        .text-primary { color: var(--primary-admin) !important; }

        .order-header-card {
            border-top: 5px solid var(--accent-blue);
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            font-size: 1.2rem;
            font-weight: 800;
            padding: 10px 15px;
            border-radius: 8px;
            letter-spacing: 0.5px;
            min-width: 250px; /* Lebar minimal untuk status */
            display: inline-block;
            text-align: center;
        }

        /* Detail Boxes */
        .detail-box {
            background-color: #ffffff;
            border-left: 4px solid var(--accent-green);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            min-height: 100%;
            transition: all 0.3s;
        }
        .detail-box:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .detail-box strong {
            color: var(--primary-admin);
        }
        
        /* Form Update Area */
        .admin-action-form {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            border: 1px solid #dee2e6;
        }
        .admin-action-form .btn-success {
             background-color: var(--accent-green);
             border-color: var(--accent-green);
             font-weight: 600;
        }
        .admin-action-form .btn-success:hover {
             background-color: #1a7e48;
        }
        
        /* Table Styling */
        .table thead th {
            background-color: var(--primary-admin) !important;
            color: white;
            font-weight: 700;
            text-transform: uppercase;
        }
        .price-total {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--danger-color); /* Merah untuk total */
        }
        .table-striped tbody tr:nth-of-type(odd) {
             background-color: #fcfcfc;
        }
        .table-hover tbody tr:hover {
             background-color: #f1f1f1;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-primary" href="<?php echo $router_path; ?>">Admin Dashboard</a>
        
        <div class="ms-auto d-flex align-items-center">
            <a class="btn btn-sm btn-outline-secondary me-2" href="<?php echo $router_path; ?>?page=orders/index">
                <i class="fas fa-arrow-left me-1"></i> Daftar Pesanan
            </a>
            <a class="btn btn-sm btn-outline-danger" href="logout_admin.php">
                <i class="fas fa-sign-out-alt me-1"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid my-5">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <h1 class="display-6 fw-bold text-primary"><i class="fas fa-receipt me-2"></i> Kelola Pesanan <?php echo $formatted_order_id; ?></h1>
        <a href="<?php echo $router_path; ?>?page=orders/index" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar
        </a>
    </div>

    <?php 
    // Menampilkan pesan dari session setelah redirect (asumsi pesan sudah di-handle di router/dashboard)
    if (isset($_SESSION['message'])): 
        $msg = $_SESSION['message'];
        unset($_SESSION['message']);
    ?>
        <div class="alert alert-<?php echo htmlspecialchars($msg['type']); ?> alert-dismissible fade show" role="alert">
            <?php echo $msg['text']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <ul><?php foreach ($error as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-lg mb-5 order-header-card">
        <div class="card-body p-4">
            <div class="row g-4">
                
                <div class="col-md-5">
                    <h4 class="fw-bold mb-3 text-primary">Status Pesanan:</h4>
                    <span class="badge status-badge bg-<?php echo $current_status_class; ?> p-3 mb-4 shadow-sm">
                        <?php echo htmlspecialchars($current_status_display); ?>
                    </span>

                    <h4 class="fw-bold mb-3 mt-4" style="color: var(--accent-blue);">Detail Pelanggan:</h4>
                    <div class="detail-box">
                        <p class="mb-1"><strong>ID Transaksi:</strong> <span class="text-danger fw-bold"><?php echo $formatted_order_id; ?></span></p>
                        <p class="mb-1"><strong>Dipesan:</strong> <?php echo date("d M Y H:i", strtotime($order['order_date'])); ?> WIB</p>
                        <hr class="my-2">
                        <p class="mb-1"><strong>Nama:</strong> <?php echo htmlspecialchars($order['user_name'] ?? $order['customer_name'] ?? 'N/A'); ?></p>
                        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($order['user_email'] ?? $order['customer_email'] ?? 'N/A'); ?></p>
                        <p class="mb-0"><strong>Metode Bayar:</strong> <span class="fw-bold text-success"><?php echo htmlspecialchars($order['payment_method']); ?></span></p>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="admin-action-form">
                        <h4 class="fw-bold mb-4" style="color: var(--accent-green);">Kelola Status & Pengiriman</h4>
                        
                        <form action="<?php echo $router_path; ?>?page=orders/detail&id=<?php echo $order_id; ?>" method="POST">
                            <input type="hidden" name="action" value="update_status">
                            
                            <div class="mb-3">
                                <label for="status" class="form-label fw-bold">Ubah Status Pesanan</label>
                                <select name="status" id="status" class="form-select" required>
                                    <?php foreach ($available_statuses as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" 
                                            <?php echo ($order['status'] === $key) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="shipping_tracking_number" class="form-label fw-bold">Nomor Resi / Pelacakan</label>
                                <input type="text" name="shipping_tracking_number" id="shipping_tracking_number" 
                                    class="form-control" placeholder="Masukkan nomor resi..."
                                    value="<?php echo htmlspecialchars($order['shipping_tracking_number'] ?? ''); ?>">
                                <div class="form-text">Kurir: **<?php echo htmlspecialchars($shipping_carriers[$order['shipping_carrier']] ?? $order['shipping_carrier'] ?? '-'); ?>**. Isi ini saat status diubah ke "dikirim".</div>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-sync-alt me-2"></i> Simpan & Perbarui Status
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <?php if ($order['payment_proof_path']): ?>
                    <div class="mt-4 pt-3 border-top">
                        <p class="mb-2 fw-bold">Bukti Pembayaran Terkirim:</p>
                        <a href="../uploads/payments/<?php echo urlencode($order['payment_proof_path']); ?>" target="_blank" class="btn btn-warning btn-sm text-dark">
                             <i class="fas fa-image me-1"></i> Lihat Bukti Bayar
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <h3 class="fw-bold mb-3 text-primary"><i class="fas fa-list me-2"></i> Item Pesanan</h3>
    <div class="card shadow-sm mb-5">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama Produk</th>
                            <th>Varian ID</th>
                            <th>Harga Satuan</th>
                            <th class="text-center">Kuantitas</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; $grand_total_calculated = 0; ?>
                        <?php if (empty($order_items)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">Tidak ada item dalam pesanan ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($order_items as $item): 
                                $subtotal = $item['price_at_order'] * $item['quantity'];
                                $grand_total_calculated += $subtotal;
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($item['variant_id']); ?></span></td>
                                <td><?php echo format_rupiah($item['price_at_order']); ?></td>
                                <td class="text-center"><?php echo number_format($item['quantity']); ?></td>
                                <td class="text-end"><?php echo format_rupiah($subtotal); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-light fw-bold">
                            <td colspan="5" class="text-end py-3">GRAND TOTAL (Database: <?php echo format_rupiah($order['total_amount']); ?>)</td>
                            <td class="text-end py-3 price-total"><?php echo format_rupiah($grand_total_calculated); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <h3 class="fw-bold mb-3 text-primary"><i class="fas fa-truck me-2"></i> Detail Pengiriman</h3>
    <div class="detail-box mb-5">
        <p class="mb-1"><strong>Alamat Pengiriman:</strong> <br><span class="d-block p-2 border rounded bg-white mt-1"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></span></p>
        <p class="mb-1 mt-3"><strong>Kurir:</strong> <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($shipping_carriers[$order['shipping_carrier']] ?? $order['shipping_carrier'] ?? '-'); ?></span></p>
        <p class="mb-0"><strong>Nomor Resi:</strong> <span class="fw-bold text-success"><?php echo htmlspecialchars($order['shipping_tracking_number'] ?? '-'); ?></span></p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    feather.replace();
</script>
</body>
</html>