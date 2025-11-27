<?php
/**
 * admin/orders/index.php
 * Halaman daftar semua pesanan dengan bukti pembayaran, Kurir, Resi, dan Modal Delete.
 * 🔥 PERBAIKAN: Penyesuaian status dan logika tampilan kolom "Bukti Bayar" untuk COD & status 'payment_sent'.
 * 🔥 PERBAIKAN: Mengganti tombol aksi (Detail/Edit, Hapus) dengan **Feather Icons**.
 * 🔥 PERBAIKAN: Penyesuaian tampilan tabel agar responsif.
 * 🔥 BARU: Menambahkan tombol "Lihat Detail" (View) yang mengarah ke admin/orders/view.php.
 * 🆕 BARU: Menambahkan fungsionalitas Pencarian (berdasarkan ID/Nama Pelanggan) dan Filter Status.
 */

$db_koneksi = $GLOBALS['koneksi'] ?? null;
$router_path = $router_path ?? 'dashboard.php';
// Asumsi path URL browser untuk folder payments sudah didefinisikan secara global atau di dalam router utama
$uploads_path_payments = $GLOBALS['uploads_path_payments'] ?? '../uploads/payments/';
if (substr($uploads_path_payments, -1) !== '/') {
    $uploads_path_payments .= '/';
}


$orders = [];
// Ambil parameter dari URL untuk pencarian dan filter
$search_query = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';

// 🔥 PERBAIKAN: Mengganti 'diproses' dengan 'processing' agar konsisten
$status_classes = [
    'pending' => 'secondary', 
    'processing' => 'primary', // Status baru untuk COD atau Non-COD yang sudah bayar
    'dikirim' => 'info', 
    'selesai' => 'success', 
    'dibatalkan' => 'danger',
    'payment_sent' => 'warning' // Status Bukti Terkirim
];

// List Status untuk Dropdown Filter
// Jika data di DB masih pakai 'Diproses', key processing ini akan match
$all_statuses = [
    'pending' => 'Pending',
    'payment_sent' => 'Bukti Bayar Terkirim',
    'processing' => 'Processing (Diproses)',
    'dikirim' => 'Dikirim',
    'selesai' => 'Selesai',
    'dibatalkan' => 'Dibatalkan'
];

// Fungsi untuk Formatting ID
function format_order_id($id) {
    return '#ORD-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}

if ($db_koneksi) {
    $where_conditions = [];
    
    // Logika Pencarian (berdasarkan ID atau Nama Pelanggan)
    if (!empty($search_query)) {
        $search_term = $db_koneksi->real_escape_string($search_query);
        // Mencari di ID (sebagai angka) atau Nama Pelanggan
        $where_conditions[] = "(customer_name LIKE '%$search_term%' OR id = " . intval($search_term) . ")";
    }
    
    // Logika Filter Status
    if (!empty($filter_status) && array_key_exists($filter_status, $all_statuses)) {
        $status_to_filter = $filter_status;
        
        // 🔥 Jika user filter 'processing', kita juga harus mengambil data dengan status 'Diproses' dari DB (asumsi status lama)
        if ($filter_status === 'processing') {
            $where_conditions[] = "status IN ('processing', 'Diproses')";
        } else {
            $status_term = $db_koneksi->real_escape_string($status_to_filter);
            $where_conditions[] = "status = '$status_term'";
        }
    }

    $where_clause = !empty($where_conditions) ? ' WHERE ' . implode(' AND ', $where_conditions) : '';

    // Query tetap sama + WHERE clause
    $sql = "SELECT id, customer_name, total_amount, payment_method, order_date, status, payment_proof_path, shipping_carrier, shipping_tracking_number
             FROM orders 
             {$where_clause}
             ORDER BY order_date DESC";
    
    $result = $db_koneksi->query($sql);
    if ($result) {
        $orders = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        // Error handling untuk query
        $error = "Gagal mengambil data pesanan: " . $db_koneksi->error;
    }
}
?>

<div class="container-fluid">
    <h1 class="mt-4">Manajemen Pesanan</h1>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-dismissible fade show <?php echo (strpos($_SESSION['message'], 'danger') !== false) ? 'alert-danger' : 'alert-success'; ?>" role="alert">
            <?php echo $_SESSION['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4 p-3 shadow-sm">
        <form method="GET" action="<?php echo $router_path; ?>" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="orders/index">
            
            <div class="col-md-5 col-lg-4">
                <label for="search" class="form-label small fw-bold mb-0">Cari Pesanan (ID/Pelanggan):</label>
                <div class="input-group">
                    <span class="input-group-text"><i data-feather="search" style="width: 16px; height: 16px;"></i></span>
                    <input type="text" class="form-control form-control-sm" id="search" name="search" placeholder="Cari ID atau Nama Pelanggan..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </div>
            
            <div class="col-md-4 col-lg-3">
                <label for="status" class="form-label small fw-bold mb-0">Filter Status:</label>
                <select class="form-select form-select-sm" id="status" name="status">
                    <option value="">Semua Status</option>
                    <?php foreach ($all_statuses as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($filter_status === $key) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">Terapkan</button>
            </div>
            
            <div class="col-auto">
                <a href="<?php echo $router_path; ?>?page=orders/index" class="btn btn-sm btn-secondary">Reset</a>
            </div>
        </form>
    </div>
    <hr>
    <div class="card mb-4">
        <div class="card-header">
            <i data-feather="list" class="me-1" style="width: 16px; height: 16px;"></i>
            Daftar Pesanan
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm align-middle" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th class="text-nowrap">ID Pesanan</th>
                            <th>Pelanggan</th>
                            <th class="text-nowrap">Total</th>
                            <th class="text-nowrap">Metode Bayar</th>
                            <th>Kurir</th> 
                            <th>Resi</th> 
                            <th class="text-nowrap">Tanggal</th>
                            <th>Status</th>
                            <th class="text-center text-nowrap">Bukti Bayar</th> 
                            <th class="text-center text-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $row): ?>
                                <?php 
                                    $formatted_id = format_order_id($row['id']);
                                    $payment_proof = $row['payment_proof_path'];
                                    $payment_required = ($row['payment_method'] !== 'COD'); 
                                    $status_key = strtolower($row['status']);
                                    
                                    // Penyesuaian status: 'diproses' lama diconvert ke 'processing'
                                    if ($status_key === 'diproses') {
                                        $status_key = 'processing';
                                    }
                                    
                                    $class = $status_classes[$status_key] ?? 'secondary';
                                    
                                    $carrier = htmlspecialchars($row['shipping_carrier'] ?? '-');
                                    $tracking = htmlspecialchars($row['shipping_tracking_number'] ?? '-');
                                ?>
                                <tr>
                                    <td class="text-nowrap small fw-bold"><?php echo htmlspecialchars($formatted_id); ?></td>
                                    <td><?php echo htmlspecialchars($row['customer_name'] ?? 'Guest'); ?></td>
                                    <td class="text-nowrap">Rp <?php echo number_format($row['total_amount'], 0, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($row['payment_method'] ?? '-'); ?></td>
                                    
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo $carrier; ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <?php if ($tracking != '-'): ?>
                                            <span class="badge bg-info text-dark fw-bold small text-nowrap">
                                                <?php echo $tracking; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-nowrap small"><?php echo date("d/m/Y H:i", strtotime($row['order_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $class; ?> text-nowrap">
                                            <?php 
                                            // Menampilkan nama status yang lebih deskriptif untuk 'processing' atau 'payment_sent'
                                            if ($status_key === 'processing') {
                                                echo 'Diproses'; // Tampilkan yang lebih user-friendly
                                            } elseif ($status_key === 'payment_sent') {
                                                echo 'Bukti Terkirim';
                                            } else {
                                                echo htmlspecialchars(ucwords($row['status'])); 
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    
                                    <td class="text-center">
                                        <?php if ($row['payment_method'] === 'COD'): ?>
                                            <span class="badge bg-success small">COD</span>
                                        <?php elseif ($payment_required): ?>
                                            <?php if (!empty($payment_proof)): ?>
                                                <?php if ($status_key === 'payment_sent'): ?>
                                                    <a href="<?php echo $uploads_path_payments . urlencode($payment_proof); ?>" target="_blank" class="btn btn-sm btn-warning" title="Bukti Terkirim (Perlu Validasi)">
                                                        <i data-feather="alert-circle" style="width: 14px; height: 14px;"></i> Cek
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?php echo $uploads_path_payments . urlencode($payment_proof); ?>" target="_blank" class="btn btn-sm btn-success" title="Bukti Divalidasi">
                                                        <i data-feather="check-circle" style="width: 14px; height: 14px;"></i> OK
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-danger small text-nowrap">Belum Unggah</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-center text-nowrap"> 
                                        <a href="<?php echo $router_path; ?>?page=orders/view&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary text-white me-1" title="Lihat Detail Pesanan">
                                            <i data-feather="eye" style="width: 14px; height: 14px;"></i> 
                                        </a>

                                        <a href="<?php echo $router_path; ?>?page=orders/edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info text-white me-1" title="Edit Pesanan">
                                            <i data-feather="edit-2" style="width: 14px; height: 14px;"></i> 
                                        </a>
                                        
                                        <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                                                title="Hapus Pesanan"
                                                                data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                                data-id="<?php echo $row['id']; ?>" 
                                                                data-name="<?php echo $formatted_id; ?>">
                                            <i data-feather="trash-2" style="width: 14px; height: 14px;"></i> 
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center">Belum ada pesanan yang masuk.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Penghapusan Pesanan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menghapus pesanan **<span id="orderName"></span>**? Tindakan ini tidak dapat dibatalkan, dan detail item akan ikut terhapus.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a id="confirmDelete" href="#" class="btn btn-danger">Ya, Hapus Permanen</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        const deleteModal = document.getElementById('deleteModal');
        const confirmDelete = document.getElementById('confirmDelete');
        const orderNameSpan = document.getElementById('orderName');

        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const orderId = button.getAttribute('data-id');
                const orderName = button.getAttribute('data-name');
                
                orderNameSpan.textContent = orderName;

                const basePath = '<?php echo $router_path; ?>';
                confirmDelete.href = basePath + '?page=orders/delete&id=' + orderId;
            });
        }
    });
</script>