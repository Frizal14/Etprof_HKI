<?php
/**
 * orders_user.php
 * Halaman frontend untuk melihat daftar dan status pesanan yang telah dibuat user.
 * * PERBAIKAN STYLING: Tampilan tabel lebih modern, penggunaan ikon Bootstrap Icons (sebagai pengganti Feather Icons)
 * untuk aksi tombol, dan warna netral yang lebih elegan.
 * * PERBAIKAN RESPONSIVITAS: Menggunakan media query dan penyesuaian markup untuk tampilan mobile/tablet (tabel berubah menjadi list/card).
 * * PENAMBAHAN: Tombol Hapus Riwayat Pesanan Selesai/Dibatalkan.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek Otentikasi
if (!isset($_SESSION['user_id'])) {
    header('Location: login_user.php');
    exit;
}

require_once 'koneksi.php'; 

$user_id = $_SESSION['user_id'];
$orders = [];
$has_removable_orders = false; // Flag untuk tombol hapus

// LOGIKA PENGAMBILAN FLASH MESSAGE (TOAST)
$toast_message = null;
$toast_type = null;

if (isset($_SESSION['toast_message'])) {
    $toast_message = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'] ?? 'success'; 
    
    // Hapus pesan dari session agar tidak muncul lagi saat refresh
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}


// Fungsi untuk Formatting ID
function format_order_id($id) {
    return '#ORD-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}

// Helper untuk status badge (Diperbarui dengan warna yang lebih solid dan konsisten)
$status_classes = [
    'pending' => 'secondary',        // Menunggu Pembayaran (Non-COD)
    'processing' => 'primary',       // Siap Diproses/Dikemas (COD atau Non-COD terverifikasi)
    'dikirim' => 'info',             // Sedang dalam Pengiriman
    'selesai' => 'success',          // Pesanan Selesai
    'dibatalkan' => 'danger',        // Dibatalkan
    'payment_sent' => 'warning'      // Bukti Pembayaran Terkirim
];

// Helper untuk Kurir (agar tampilan lebih ramah)
$shipping_carriers_display = [
    'JNE' => 'JNE',
    'TIKI' => 'TIKI',
    'POS' => 'POS',
    'GOSEND' => 'GoSend',
    'GRABEX' => 'GrabExpress'
];


if ($koneksi) {
    // Ambil semua pesanan user ini, diurutkan dari yang terbaru
    $sql = "SELECT id, total_amount, payment_method, shipping_carrier, order_date, status 
             FROM orders 
             WHERE user_id = ? 
             ORDER BY order_date DESC";
    
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        $orders = $result->fetch_all(MYSQLI_ASSOC);
        
        // Cek apakah ada pesanan yang bisa dihapus (selesai atau dibatalkan)
        foreach ($orders as $order) {
            $status = strtolower($order['status']);
            if ($status === 'selesai' || $status === 'dibatalkan') {
                $has_removable_orders = true;
                break;
            }
        }
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pesanan Anda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* TOAST Styling */
        .toast-container {
            z-index: 1080 !important; 
            position: fixed;
            top: 0;
            right: 0;
            margin-top: 15px;
            margin-right: 15px;
        }

        /* CARD Header Kustom - Lebih Netral dan Profesional */
        .card-header-custom {
            background-color: #343a40; /* Dark gray solid */
            color: white;
            padding: 1rem 1.5rem;
            border-bottom: 3px solid #0d6efd; /* Border biru untuk aksen */
            border-top-left-radius: 0.5rem !important; /* Sesuaikan dengan card border */
            border-top-right-radius: 0.5rem !important;
            /* 🔥 Tambahan: Flex untuk menempatkan tombol di header */
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header-custom h4 {
            font-weight: 700; /* Lebih tegas */
            margin-bottom: 0; /* Pastikan tidak ada margin bawah pada h4 */
        }
        
        /* TABLE Styling - Lebih Bersih */
        .table thead th {
            background-color: #f8f9fa; /* Light gray */
            color: #495057; /* Dark text */
            border-bottom: 2px solid #dee2e6;
            border-top: none;
            font-weight: 600; 
            letter-spacing: 0.5px;
            vertical-align: middle;
        }
        .table-hover tbody tr:hover {
            background-color: #e9ecef; /* Hover yang lebih jelas */
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #fbfbfb; /* Sangat subtle */
        }
        .table td {
            padding: 1rem 0.75rem; /* Padding lebih besar */
        }

        /* Warna teks ID pesanan dan Total */
        .text-order-id {
            color: #0d6efd; /* Primary Blue */
            font-size: 1.05rem;
        }
        .text-total-amount {
            color: #dc3545; /* Danger Red */
            font-size: 1.1rem;
        }
        /* Styling Badge Status */
        .badge {
            font-size: 0.85em; /* Badge sedikit lebih kecil */
            font-weight: 700;
            padding: 0.6em 0.9em;
            letter-spacing: 0.5px;
        }

        /* ************************************** */
        /* PERBAIKAN RESPONSIVITAS (Mobile/Tablet) */
        /* ************************************** */
        @media (max-width: 767.98px) {
            .container {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            h1.display-6 {
                font-size: 1.75rem !important;
                margin-bottom: 1rem !important;
            }
            .card-header-custom {
                padding: 0.75rem 1rem;
                /* 🔥 Penyesuaian: Tombol hapus mungkin turun ke baris baru */
                flex-direction: column; 
                align-items: flex-start;
            }
            .card-header-custom h4 {
                font-size: 1.25rem;
            }
            /* 🔥 Tambahan: Gaya tombol hapus di mobile */
            .btn-delete-mobile {
                margin-top: 0.75rem;
                width: 100%;
            }
            /* Menyembunyikan thead dan membuat baris tabel menjadi list item */
            .table thead {
                display: none;
            }
            /* Styling untuk mode card/list */
            .order-item-mobile {
                display: block; /* Tampilkan elemen khusus mobile */
                border: 1px solid #dee2e6;
                margin-bottom: 1rem;
                padding: 0.75rem;
                border-radius: 0.5rem;
                background-color: #fff;
                box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            }
            .order-item-mobile + .order-item-mobile {
                margin-top: 1rem;
            }
            .order-info-mobile {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 0.5rem;
            }
            .order-info-mobile .status-badge-container {
                flex-shrink: 0;
            }
            .text-order-id-mobile {
                font-size: 1.15rem;
            }
            .text-total-amount-mobile {
                font-size: 1.25rem;
            }
            .action-buttons-mobile .btn {
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body class="bg-light">

<?php if ($toast_message): 
    $toast_class = ($toast_type === 'error') ? 'bg-danger' : (($toast_type === 'warning') ? 'bg-warning text-dark' : 'bg-success');
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

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold text-dark" href="toko_sepatu.php">TokoOnlineku</a>
        <a class="btn btn-sm btn-outline-secondary ms-auto" href="toko_sepatu.php"><i class="bi bi-house"></i> Kembali ke Toko</a>
    </div>
</nav>

<div class="container mt-5 mb-5">
    <h1 class="mb-4 display-6 fw-bold text-dark-emphasis"><i class="bi bi-list-check me-2 text-primary"></i> Riwayat & Status Pesanan</h1>

    <div class="card shadow-lg border-0 rounded-3">
        <div class="card-header-custom rounded-3">
            <h4 class="mb-0"><i class="bi bi-clock-history me-2"></i> Semua Pesanan Anda</h4>
            
            <?php if ($has_removable_orders): ?>
            <a href="delete_order_history.php" 
               class="btn btn-sm btn-outline-light d-none d-md-inline-flex align-items-center"
               onclick="return confirm('❗ PERINGATAN! Anda yakin ingin menghapus SEMUA pesanan yang sudah Selesai atau Dibatalkan? Aksi ini tidak dapat dibatalkan.');">
                <i class="bi bi-trash me-1"></i> Hapus Riwayat
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($orders)): ?>
                <div class="alert alert-info text-center m-4 p-4 rounded-3">
                    <i class="bi bi-box-seam-fill display-4 mb-3"></i>
                    <p class="mb-0 fs-5 fw-medium">Anda belum memiliki riwayat pesanan. Mulai berbelanja sekarang!</p>
                </div>
            <?php else: ?>
                <?php if ($has_removable_orders): ?>
                <div class="d-md-none p-3 pb-0">
                    <a href="delete_order_history.php" 
                       class="btn btn-sm btn-outline-danger btn-delete-mobile w-100"
                       onclick="return confirm('❗ PERINGATAN! Anda yakin ingin menghapus SEMUA pesanan yang sudah Selesai atau Dibatalkan? Aksi ini tidak dapat dibatalkan.');">
                        <i class="bi bi-trash me-1"></i> Hapus Riwayat yang Sudah Selesai/Dibatalkan
                    </a>
                </div>
                <?php endif; ?>
            
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0 d-none d-md-table"> <thead>
                            <tr>
                                <th class="py-3">ID Pesanan</th>
                                <th class="py-3">Tanggal</th>
                                <th class="py-3">Total Bayar</th>
                                <th class="py-3">Metode</th>
                                <th class="py-3">Kurir</th> 
                                <th class="py-3">Status</th>
                                <th class="py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $row): ?>
                                <?php 
                                    $formatted_id = format_order_id($row['id']);
                                    $status_key = strtolower($row['status']);
                                    
                                    // Normalisasi status dari database ke key array
                                    if ($status_key === 'diproses') {
                                        $status_key = 'processing';
                                    }
                                    
                                    $class = $status_classes[$status_key] ?? 'secondary';
                                    
                                    $carrier_key = strtoupper($row['shipping_carrier'] ?? '');
                                    $display_carrier = $shipping_carriers_display[$carrier_key] ?? $row['shipping_carrier'] ?? '-';
                                    
                                    // Tampilkan nama status yang lebih user-friendly
                                    $display_status = htmlspecialchars(ucwords($row['status']));
                                    if ($status_key === 'processing') {
                                        $display_status = 'Diproses'; 
                                    } elseif ($status_key === 'payment_sent') {
                                        $display_status = 'Menunggu Verifikasi';
                                    }
                                    
                                    $actions_html = '';

                                    // Logika Tombol Aksi
                                    if ($status_key === 'dikirim') {
                                        $actions_html .= '
                                            <a href="confirm_receipt.php?id=' . $row['id'] . '" 
                                                class="btn btn-sm btn-success w-100 fw-medium"
                                                onclick="return confirm(\'Apakah Anda yakin barang sudah DITERIMA dengan baik? Konfirmasi ini akan menyelesaikan pesanan.\');">
                                                <i class="bi bi-bag-check"></i> Diterima
                                            </a>';
                                    } elseif ($status_key === 'pending' && $row['payment_method'] !== 'COD') {
                                        $actions_html .= '
                                            <a href="payment_upload.php?order_id=' . $row['id'] . '" 
                                                class="btn btn-sm btn-warning mb-1 w-100 text-dark fw-medium">
                                                <i class="bi bi-credit-card"></i> Bayar
                                            </a>
                                            <a href="cancel_order.php?id=' . $row['id'] . '" 
                                                class="btn btn-sm btn-outline-danger w-100 fw-medium"
                                                onclick="return confirm(\'❗ Anda yakin ingin membatalkan pesanan ini? Aksi ini tidak dapat dibatalkan.\');">
                                                <i class="bi bi-x-circle"></i> Batalkan
                                            </a>';
                                    } elseif ($status_key === 'processing' || ($status_key === 'pending' && $row['payment_method'] === 'COD')) {
                                        $actions_html .= '
                                            <a href="cancel_order.php?id=' . $row['id'] . '" 
                                                class="btn btn-sm btn-outline-danger w-100 fw-medium"
                                                onclick="return confirm(\'❗ Anda yakin ingin membatalkan pesanan ini? Pesanan sudah diproses dan akan dibatalkan.\');">
                                                <i class="bi bi-x-circle"></i> Batalkan
                                            </a>';
                                    }
                                ?>
                                <tr class="d-none d-md-table-row">
                                    <td class="fw-bold text-order-id"><?php echo htmlspecialchars($formatted_id); ?></td>
                                    <td><?php echo date("d M Y", strtotime($row['order_date'])); ?><br><small class="text-muted"><?php echo date("H:i", strtotime($row['order_date'])); ?></small></td>
                                    <td class="fw-bold text-total-amount">Rp <?php echo number_format($row['total_amount'], 0, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($row['payment_method'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-75"><?php echo htmlspecialchars($display_carrier); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $class; ?> py-2 px-3">
                                            <?php echo $display_status; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="detail_checkout.php?order_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary mb-1 w-100 fw-medium">
                                            <i class="bi bi-eye"></i> Detail
                                        </a>
                                        <?php echo $actions_html; ?>
                                        <?php if ($status_key === 'selesai' || $status_key === 'dibatalkan'): ?>
                                        <a href="delete_order_history.php?id=<?php echo $row['id']; ?>" 
                                            class="btn btn-sm btn-outline-danger mt-1 w-100 fw-medium"
                                            onclick="return confirm('Anda yakin ingin menghapus pesanan ini (<?php echo $formatted_id; ?>)?');">
                                            <i class="bi bi-trash"></i> Hapus
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="p-3 d-md-none">
                <?php foreach ($orders as $row): ?>
                    <?php 
                        $formatted_id = format_order_id($row['id']);
                        $status_key = strtolower($row['status']);
                        
                        // Normalisasi status dari database ke key array
                        if ($status_key === 'diproses') {
                            $status_key = 'processing';
                        }
                        
                        $class = $status_classes[$status_key] ?? 'secondary';
                        
                        $carrier_key = strtoupper($row['shipping_carrier'] ?? '');
                        $display_carrier = $shipping_carriers_display[$carrier_key] ?? $row['shipping_carrier'] ?? '-';

                        // Tampilkan nama status yang lebih user-friendly
                        $display_status = htmlspecialchars(ucwords($row['status']));
                        if ($status_key === 'processing') {
                            $display_status = 'Diproses'; 
                        } elseif ($status_key === 'payment_sent') {
                            $display_status = 'Menunggu Verifikasi';
                        }
                        
                        $actions_html = '';

                        // Logika Tombol Aksi
                        if ($status_key === 'dikirim') {
                            $actions_html .= '
                                <a href="confirm_receipt.php?id=' . $row['id'] . '" 
                                    class="btn btn-sm btn-success w-100 fw-medium"
                                    onclick="return confirm(\'Apakah Anda yakin barang sudah DITERIMA dengan baik? Konfirmasi ini akan menyelesaikan pesanan.\');">
                                    <i class="bi bi-bag-check"></i> Diterima
                                </a>';
                        } elseif ($status_key === 'pending' && $row['payment_method'] !== 'COD') {
                            $actions_html .= '
                                <a href="payment_upload.php?order_id=' . $row['id'] . '" 
                                    class="btn btn-sm btn-warning mb-2 w-100 text-dark fw-medium">
                                    <i class="bi bi-credit-card"></i> Bayar
                                </a>
                                <a href="cancel_order.php?id=' . $row['id'] . '" 
                                    class="btn btn-sm btn-outline-danger w-100 fw-medium"
                                    onclick="return confirm(\'❗ Anda yakin ingin membatalkan pesanan ini? Aksi ini tidak dapat dibatalkan.\');">
                                    <i class="bi bi-x-circle"></i> Batalkan
                                </a>';
                        } elseif ($status_key === 'processing' || ($status_key === 'pending' && $row['payment_method'] === 'COD')) {
                            $actions_html .= '
                                <a href="cancel_order.php?id=' . $row['id'] . '" 
                                    class="btn btn-sm btn-outline-danger w-100 fw-medium"
                                    onclick="return confirm(\'❗ Anda yakin ingin membatalkan pesanan ini? Pesanan sudah diproses dan akan dibatalkan.\');">
                                    <i class="bi bi-x-circle"></i> Batalkan
                                </a>';
                        }
                    ?>
                    <div class="order-item-mobile">
                        <div class="order-info-mobile">
                            <h5 class="mb-0 fw-bold text-order-id-mobile"><?php echo htmlspecialchars($formatted_id); ?></h5>
                            <div class="status-badge-container">
                                <span class="badge bg-<?php echo $class; ?> py-2 px-3"><?php echo $display_status; ?></span>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6 text-muted"><i class="bi bi-calendar"></i> Tanggal:</div>
                            <div class="col-6 text-end fw-medium"><?php echo date("d M Y H:i", strtotime($row['order_date'])); ?></div>
                            <div class="col-6 text-muted"><i class="bi bi-truck"></i> Kurir:</div>
                            <div class="col-6 text-end fw-medium"><?php echo htmlspecialchars($display_carrier); ?> (<?php echo htmlspecialchars($row['payment_method'] ?? '-'); ?>)</div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-top pt-2">
                            <span class="text-muted fw-bold">Total:</span>
                            <span class="fw-bold text-total-amount-mobile">Rp <?php echo number_format($row['total_amount'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="action-buttons-mobile mt-3 pt-2 border-top">
                            <a href="detail_checkout.php?order_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary mb-2 w-100 fw-medium">
                                <i class="bi bi-eye"></i> Lihat Detail
                            </a>
                            <?php echo $actions_html; ?>
                            <?php if ($status_key === 'selesai' || $status_key === 'dibatalkan'): ?>
                            <a href="delete_order_history.php?id=<?php echo $row['id']; ?>" 
                                class="btn btn-sm btn-outline-danger w-100 fw-medium"
                                onclick="return confirm('Anda yakin ingin menghapus pesanan ini (<?php echo $formatted_id; ?>)?');">
                                <i class="bi bi-trash"></i> Hapus
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($toast_message): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var toastEl = document.getElementById('liveToast');
        if (toastEl) {
            var toast = new bootstrap.Toast(toastEl, {
                autohide: true,
                delay: 4000 
            });
            toast.show();
        }
    });
</script>
<?php endif; ?>

</body>
</html>
<?php 
// Tutup koneksi di akhir skrip
if (isset($koneksi)) {
    $koneksi->close(); 
}
?>