<?php
/**
 * admin/orders/detail.php
 * Versi RAPIH & KONSISTEN dengan layout sidebar dashboard.
 * Diperbaiki: Masalah nama produk '0' di tabel item.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$koneksi = $GLOBALS['koneksi'] ?? null; 
$router_path = 'dashboard.php';

if (!function_exists('format_rupiah')) {
    function format_rupiah($angka) {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}

$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$order = null;
$order_items = [];
$error = [];

/* Status & Kurir */
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

function format_order_id($id) {
    return '#ORD-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}

if (!$koneksi || !$order_id || $order_id <= 0) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => "ID Pesanan tidak valid."];
    header("Location: {$router_path}?page=orders/index");
    exit;
}

/* Ambil Detail Pesanan */
$sql_order = "SELECT o.*, u.name AS user_name, u.email AS user_email 
              FROM orders o
              LEFT JOIN users u ON o.user_id = u.id
              WHERE o.id = ?";
$stmt_order = $koneksi->prepare($sql_order);
$stmt_order->bind_param("i", $order_id);
$stmt_order->execute();
$order = $stmt_order->get_result()->fetch_assoc();
$stmt_order->close();

if (!$order) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => "Pesanan tidak ditemukan."];
    header("Location: {$router_path}?page=orders/index");
    exit;
}

$formatted_order_id = format_order_id($order_id);

// ====================================================================
// START: PERBAIKAN QUERY ITEM UNTUK FALLBACK NAMA PRODUK
// ====================================================================

/* Ambil Item (MENGAMBIL SIZE VARIAN + FALLBACK NAMA PRODUK) */
$sql_items = "SELECT 
                oi.product_id, 
                oi.product_name AS name_at_order, 
                oi.quantity, 
                oi.price_at_order, 
                oi.variant_id, 
                pv.size,
                p.name AS current_product_name
              FROM order_items oi
              LEFT JOIN product_variants pv ON oi.variant_id = pv.id
              LEFT JOIN products p ON oi.product_id = p.id -- Join ke tabel products
              WHERE oi.order_id = ?";
$stmt_items = $koneksi->prepare($sql_items);
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();
while ($item = $result_items->fetch_assoc()) {
    $order_items[] = $item;
}
$stmt_items->close();

// ====================================================================
// END: PERBAIKAN QUERY ITEM UNTUK FALLBACK NAMA PRODUK
// ====================================================================


/* Badge Class (konsisten dengan dashboard) */
$status_classes = [
    'baru' => 'danger',
    'pending' => 'danger',
    'payment_sent' => 'warning',
    'processing' => 'primary',
    'dikirim' => 'info',
    'selesai' => 'success',
    'dibatalkan' => 'secondary'
];

// Inisialisasi tampilan status awal
$current_status_class = $status_classes[strtolower($order['status'])] ?? 'secondary';
$current_status_display = $available_statuses[$order['status']] ?? $order['status'];


// ====================================================================
// LOGIKA UPDATE STATUS & RESI
// ====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        
        // 1. Ambil input baru
        $new_status = $_POST['status'] ?? $order['status'];
        $new_resi = trim($_POST['shipping_tracking_number'] ?? ''); 

        // 2. Validasi
        if (!array_key_exists($new_status, $available_statuses)) {
            $error[] = "Status **'{$new_status}'** tidak valid.";
        }

        // 2.1 Validasi Logis: Resi wajib jika status 'dikirim'
        if ($new_status === 'dikirim' && empty($new_resi)) {
             $error[] = "Status 'Dikirim' memerlukan Nomor Resi.";
        }
        
        // 2.2 Opsional: Kosongkan Resi jika status dibatalkan
        if ($new_status === 'dibatalkan' && !empty($new_resi)) {
             $new_resi = ''; 
        }

        // 3. Eksekusi Update jika tidak ada error
        if (!$error) {
            $sql_update = "UPDATE orders SET status = ?, shipping_tracking_number = ? WHERE id = ?";
            $stmt = $koneksi->prepare($sql_update);
            $stmt->bind_param("ssi", $new_status, $new_resi, $order_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                 $_SESSION['message'] = [
                    'type' => 'success',
                    'text' => "Status dan/atau Nomor Resi pesanan berhasil diperbarui."
                ];
            } else {
                 $_SESSION['message'] = [
                    'type' => 'info',
                    'text' => "Tidak ada perubahan yang disimpan (Status dan Nomor Resi sama dengan sebelumnya)."
                ];
            }
           
            $stmt->close();

            // Redirect setelah sukses
            header("Location: {$router_path}?page=orders/detail&id={$order_id}");
            exit;
        }
        
        // 4. Pemulihan State (jika ada error validasi)
        $order['status'] = $new_status;
        $order['shipping_tracking_number'] = $new_resi;
        $current_status_class = $status_classes[strtolower($order['status'])] ?? 'secondary';
        $current_status_display = $available_statuses[$order['status']] ?? $order['status'];
    }
}

// ====================================================================
// END LOGIKA UPDATE STATUS & RESI
// ====================================================================

?>

<div class="page-content">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">
            <i data-feather="file-text" class="me-2"></i>
            Detail Pesanan <?= $formatted_order_id ?>
        </h2>

        <a href="<?= $router_path ?>?page=orders/index" class="btn btn-secondary">
            <i data-feather="arrow-left" class="me-1"></i> Kembali
        </a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message']['type'] ?> shadow-sm">
            <?= $_SESSION['message']['text'] ?>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            **Terdapat Kesalahan Input:**
            <?php foreach ($error as $e): ?>
                <div>• <?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">

            <h5 class="fw-bold">Status Pesanan</h5>
            <span class="badge bg-<?= $current_status_class ?> p-2 px-3 fs-6 fw-bold mb-3">
                <?= htmlspecialchars($current_status_display) ?>
            </span>

            <hr>

            <h5 class="fw-bold">Data Pelanggan</h5>
            <div class="mt-2">
                <p class="mb-1"><strong>Nama:</strong> <?= htmlspecialchars($order['user_name'] ?? 'Guest') ?></p>
                <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($order['user_email'] ?? '-') ?></p>
                <p class="mb-1"><strong>Metode Bayar:</strong> <?= htmlspecialchars($order['payment_method']) ?></p>
                <p class="mb-1"><strong>Dipesan:</strong> <?= date("d M Y H:i", strtotime($order['order_date'])) ?> WIB</p>
            </div>

        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">

            <h5 class="fw-bold mb-3">Update Status / Nomor Resi</h5>

            <form action="<?= $router_path ?>?page=orders/detail&id=<?= $order_id ?>" method="POST">
                <input type="hidden" name="action" value="update_status">

                <div class="mb-3">
                    <label class="fw-bold">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach ($available_statuses as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $order['status'] === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="fw-bold">Nomor Resi</label>
                    <input type="text" name="shipping_tracking_number" class="form-control"
                            value="<?= htmlspecialchars($order['shipping_tracking_number'] ?? '') ?>">
                     <small class="form-text text-muted">Wajib diisi jika status diubah menjadi "Sudah Dikirim".</small>
                </div>

                <button class="btn btn-success">
                    <i data-feather="save" class="me-1"></i> Simpan Perubahan
                </button>
            </form>

        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-3">Item Pesanan</h5>

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Produk</th>
                            <th>Varian</th>
                            <th>Harga</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        $no = 1;
                        $grand = 0;
                        foreach ($order_items as $it):
                            $sub = $it['price_at_order'] * $it['quantity'];
                            $grand += $sub;
                            
                            // Logika Fallback Nama Produk:
                            // 1. Prioritas: name_at_order (dari order_items)
                            // 2. Fallback: current_product_name (dari tabel products)
                            // 3. Jika semua gagal: 'Produk Tidak Ditemukan'
                            $display_product_name = trim($it['name_at_order'] ?? '');
                            
                            // Cek jika nama di order_items kosong atau berisi '0'
                            if (empty($display_product_name) || $display_product_name === '0') {
                                $display_product_name = $it['current_product_name'] ?? 'Produk Tidak Ditemukan (ID: ' . ($it['product_id'] ?? '-') . ')';
                            }

                            // Menentukan tampilan varian
                            $variant_display = $it['size'] ?? ($it['variant_id'] ? 'ID: ' . $it['variant_id'] : '-');
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($display_product_name) ?></td> 
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($variant_display) ?></span></td>
                            <td><?= format_rupiah($it['price_at_order']) ?></td>
                            <td class="text-center"><?= $it['quantity'] ?></td>
                            <td class="text-end fw-bold"><?= format_rupiah($sub) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>

                    <tfoot class="table-light">
                        <tr>
                            <td colspan="5" class="text-end fw-bold">GRAND TOTAL</td>
                            <td class="text-end fw-bold text-danger">
                                <?= format_rupiah($grand) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-body">
            <h5 class="fw-bold mb-3">Pengiriman</h5>

            <p class="mb-1"><strong>Alamat:</strong><br>
                <div class="p-2 border rounded bg-light mt-1">
                    <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
                </div>
            </p>

            <p class="mb-1 mt-3"><strong>Kurir:</strong>
                <span class="badge bg-primary">
                    <?= $shipping_carriers[$order['shipping_carrier']] ?? htmlspecialchars($order['shipping_carrier']) ?>
                </span>
            </p>

            <p><strong>Nomor Resi:</strong> <?= htmlspecialchars($order['shipping_tracking_number'] ?: '-') ?></p>
        </div>
    </div>

</div>

<script>
    if (typeof feather !== 'undefined') {
         feather.replace();
    }
</script>