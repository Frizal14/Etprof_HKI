<?php
/**
 * admin/orders/edit.php
 * Halaman detail dan update pesanan (Status, Kurir, Resi) dengan Frontend Formatting ID,
 * dan fitur GENERATE RESI OTOMATIS (fiktif) saat status diubah menjadi 'Dikirim'.
 * 🔥 PERBAIKAN: Mengganti ikon Font Awesome (fas) dengan **Feather Icons** dan **MENAMBAH JOIN UNTUK DETAIL VARIAN/SIZE**.
 */

$db_koneksi = $GLOBALS['koneksi'] ?? null;
$router_path = $router_path ?? 'dashboard.php';
$error = [];
$order_id = (int)($_GET['id'] ?? 0);
$order = null;
$order_items = [];

// Status yang diizinkan untuk diubah oleh Admin
$allowed_statuses = ['pending', 'Diproses', 'Dikirim', 'Selesai', 'Dibatalkan', 'payment_sent'];
$allowed_carriers = ['JNE', 'TIKI', 'POS', 'GOSEND', 'GRABEX']; // Kurir yang diizinkan

// Fungsi untuk Formatting ID
function format_order_id($id) {
    return '#ORD-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}
$formatted_order_id = 'ID Tidak Valid'; // Default

// 🔥 FUNGSI GENERATOR RESI FIKTIF
/**
 * Membuat nomor resi fiktif yang unik berdasarkan ID pesanan dan Kurir.
 * Format: [PREFIX KURIR][TAHUN][ID PESANAN 6 DIGIT][RANDOM 4 DIGIT]
 */
function generate_fake_tracking_number($order_id, $carrier_name) {
    // Ambil 3 huruf/karakter pertama dari nama kurir
    $carrier_code = strtoupper(substr(preg_replace('/\s+/', '', $carrier_name), 0, 3)); 
    $year = date('y'); 
    $padded_id = str_pad($order_id, 6, '0', STR_PAD_LEFT);
    $random_suffix = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT); 
    
    return $carrier_code . $year . $padded_id . $random_suffix;
}
// 🔥 END FUNGSI GENERATOR


// ==========================================
// 1. LOGIKA UPDATE PESANAN (POST) 
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order_id > 0) {
    if (!$db_koneksi) {
        $error[] = "Koneksi database tidak tersedia.";
    } else {
        $new_status = $_POST['status'] ?? '';
        $new_carrier = $_POST['shipping_carrier'] ?? '';
        // Gunakan trim untuk memastikan nilai kosong (seperti hanya spasi) dianggap kosong
        $new_tracking = trim($_POST['shipping_tracking_number'] ?? ''); 
        
        $generated_tracking_message = ''; // Menyimpan pesan sukses generate resi
        $has_fatal_error = false; // 🔥 Flag untuk membedakan error validasi dari pesan sukses

        // Validasi Status
        if (!in_array($new_status, $allowed_statuses)) {
            $error[] = "Status yang dipilih tidak valid.";
            $has_fatal_error = true;
        }
        
        // Validasi Kurir (jika tidak kosong)
        if (!empty($new_carrier) && !in_array($new_carrier, $allowed_carriers)) {
            $error[] = "Kurir yang dipilih tidak valid.";
            $has_fatal_error = true;
        }
        
        // 🔥 LOGIKA GENERATE RESI OTOMATIS
        // Cek jika status diubah ke 'Dikirim' DAN Nomor Resi (tracking) benar-benar kosong.
        if (strtolower($new_status) === 'dikirim') {
            if (empty($new_tracking)) {
                 if (!empty($new_carrier)) {
                    // Generate resi baru
                    $new_tracking = generate_fake_tracking_number($order_id, $new_carrier);
                    // 🔥 SIMPAN PESAN DI VARIABEL KHUSUS, JANGAN DI $error
                    $generated_tracking_message = "Nomor Resi otomatis **$new_tracking** dibuat karena status diubah menjadi 'Dikirim'.";
                 } else {
                    // Jika mau dikirim tapi kurir belum dipilih
                    $error[] = "Status 'Dikirim' memerlukan Kurir yang valid untuk menggenerate Nomor Resi.";
                    $has_fatal_error = true; // Ini adalah error fatal
                 }
            }
        }
        // 🔥 END LOGIKA GENERATE

        // Sanitasi input (termasuk nilai $new_tracking yang mungkin baru digenerate)
        // Sanitasi di PHP: $clean_new_status = $db_koneksi->real_escape_string($new_status);
        // Tapi karena kita pakai prepared statement, cukup pastikan tipe data dan validasi sudah benar.

        // 🔥 Periksa apakah ada error validasi fatal
        if (!$has_fatal_error) { 
            // Query Update Tiga Field sekaligus: status, shipping_carrier, shipping_tracking_number
            $sql = "UPDATE orders SET status = ?, shipping_carrier = ?, shipping_tracking_number = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $db_koneksi->prepare($sql);
            
            // Perhatikan: Menambahkan updated_at dengan tipe data 's' (string NOW()) atau menghapusnya jika DB menangani sendiri
            // Di sini kita asumsikan updated_at diupdate manual:
            $stmt->bind_param("sssi", $new_status, $new_carrier, $new_tracking, $order_id);

            if ($stmt->execute()) {
                $temp_formatted_id = format_order_id($order_id); 
                
                // Siapkan pesan sukses
                $success_message = "Pesanan **$temp_formatted_id** berhasil diubah. Status: **$new_status**, Kurir: **$new_carrier**, Resi: **$new_tracking**.";
                
                // Tambahkan pesan generate jika resi baru dibuat
                if (!empty($generated_tracking_message)) {
                    // Gabungkan pesan generate resi ke pesan sukses
                    $success_message = $generated_tracking_message . '<br>' . $success_message;
                }
                
                $_SESSION['message'] = '<div class="alert alert-success">' . $success_message . '</div>';
                
                // REDIRECT untuk mencegah resubmission form
                header('Location: ' . $router_path . '?page=orders/edit&id=' . $order_id);
                exit;
            } else {
                // Jika update database gagal, masukkan error ke $error
                $error[] = "Gagal memperbarui pesanan: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// ==========================================
// 2. LOGIKA READ (GET - Mengambil Detail Pesanan)
// ==========================================

if ($order_id == 0) {
    $_SESSION['message'] = '<div class="alert alert-warning">ID Pesanan tidak valid.</div>';
    header('Location: ' . $router_path . '?page=orders/index');
    exit;
}

if ($db_koneksi) {
    // Query Utama Pesanan
    $sql_order = "SELECT * FROM orders WHERE id = ?";
    $stmt_order = $db_koneksi->prepare($sql_order);
    $stmt_order->bind_param("i", $order_id);
    $stmt_order->execute();
    $result_order = $stmt_order->get_result();
    
    if ($result_order->num_rows === 1) {
        $order = $result_order->fetch_assoc();
        // 🔥 Format ID setelah data order diambil
        $formatted_order_id = format_order_id($order['id']); 
    } else {
        $_SESSION['message'] = '<div class="alert alert-warning">Pesanan tidak ditemukan.</div>';
        header('Location: ' . $router_path . '?page=orders/index');
        exit;
    }
    $stmt_order->close();

    // 🔥 PERBAIKAN: Query Detail Item Pesanan (dengan DOUBLE JOIN)
    $sql_items = "
        SELECT 
            oi.id, 
            oi.product_name AS name_at_order,
            oi.quantity, 
            oi.price_at_order,
            p.name AS product_fallback_name, 
            pv.size AS product_variant_size
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_variants pv ON oi.variant_id = pv.id
        WHERE oi.order_id = ?
    ";
    $stmt_items = $db_koneksi->prepare($sql_items);
    $stmt_items->bind_param("i", $order_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    $order_items = $result_items->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();

} else {
    $error[] = "Kesalahan Database: Koneksi database tidak tersedia.";
}

// Menentukan kelas badge untuk tampilan detail
$status_classes = [
    'pending' => 'secondary', 
    'diproses' => 'primary', 
    'dikirim' => 'info', 
    'selesai' => 'success', 
    'dibatalkan' => 'danger',
    'payment_sent' => 'warning'
];

if ($order) {
    $current_status_key = strtolower($order['status']);
    $current_status_class = $status_classes[$current_status_key] ?? 'secondary';
    
    // Nilai default untuk kurir dan resi di form
    $current_carrier = $order['shipping_carrier'] ?? '';
    $current_tracking = $order['shipping_tracking_number'] ?? '';
} else {
    $current_status_class = 'secondary';
    $current_carrier = '';
    $current_tracking = '';
}
?>

<div class="container-fluid">
    <h1 class="mt-4">Detail Pesanan <?php echo htmlspecialchars($formatted_order_id); ?></h1>
    
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

    <?php if ($order): // Tampilkan konten hanya jika data $order berhasil dimuat ?>
    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <i data-feather="info" class="me-1" style="width: 16px; height: 16px;"></i> Informasi Pesanan
                </div>
                <div class="card-body">
                    <p><strong>ID Pesanan:</strong> <?php echo htmlspecialchars($formatted_order_id); ?></p>
                    <p><strong>Tanggal Pesanan:</strong> <?php echo date("d/m/Y H:i", strtotime($order['order_date'])); ?></p>
                    <p><strong>Total Pembayaran:</strong> Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></p>
                    <p><strong>Metode Pembayaran:</strong> <?php echo htmlspecialchars($order['payment_method'] ?? '-'); ?></p>
                    <p><strong>Kurir Pengiriman:</strong> <?php echo htmlspecialchars($current_carrier ?: '-'); ?></p>
                    <p><strong>Nomor Resi:</strong> <?php echo htmlspecialchars($current_tracking ?: '-'); ?></p>
                    <p><strong>Status Saat Ini:</strong> <span class="badge bg-<?php echo $current_status_class; ?> fs-6"><?php echo htmlspecialchars(ucwords($order['status'])); ?></span></p>
                    <hr>
                    <p><strong>Nama Pelanggan:</strong> <?php echo htmlspecialchars($order['customer_name'] ?? '-'); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email'] ?? '-'); ?></p>
                    <p><strong>Alamat Kirim:</strong> <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                    <?php if (!empty($order['payment_proof_path'])): ?>
                    <hr>
                    <p class="mb-0">
                        <a href="../uploads/payments/<?php echo urlencode($order['payment_proof_path']); ?>" target="_blank" class="btn btn-sm btn-success">
                            <i data-feather="eye" style="width: 16px; height: 16px;"></i> Lihat Bukti Pembayaran
                        </a>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <i data-feather="refresh-ccw" class="me-1" style="width: 16px; height: 16px;"></i> Perbarui Pesanan (Status, Kurir, Resi)
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $router_path; ?>?page=orders/edit&id=<?php echo $order['id']; ?>">
                        <div class="mb-3">
                            <label for="status" class="form-label">Ubah Status Pesanan</label>
                            <select class="form-select" id="status" name="status" required>
                                <?php foreach ($allowed_statuses as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo (strtolower($order['status']) == strtolower($s)) ? 'selected' : ''; ?>>
                                        <?php echo ucwords(str_replace('_', ' ', $s)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="shipping_carrier" class="form-label">Kurir Pengiriman</label>
                            <select class="form-select" id="shipping_carrier" name="shipping_carrier">
                                <option value="" <?php echo (empty($current_carrier)) ? 'selected' : ''; ?>>-- Pilih Kurir --</option>
                                <?php foreach ($allowed_carriers as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo ($current_carrier == $c) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="shipping_tracking_number" class="form-label">Nomor Resi / Tracking</label>
                            <input type="text" class="form-control" id="shipping_tracking_number" name="shipping_tracking_number" value="<?php echo htmlspecialchars($current_tracking); ?>" placeholder="Masukkan nomor resi atau biarkan kosong untuk digenerate saat status 'Dikirim'">
                        </div>
                        
                        <button type="submit" class="btn btn-warning w-100">
                            <i data-feather="save" style="width: 16px; height: 16px;"></i> Simpan Perubahan Pesanan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i data-feather="list" class="me-1" style="width: 16px; height: 16px;"></i> Item Pesanan
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Harga Satuan</th>
                            <th>Jumlah</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): 
                            // 🔥 Logika Fallback Nama Produk
                            $product_name_to_display = $item['name_at_order'] ?? '';
                            if (empty($product_name_to_display)) {
                                $product_name_to_display = $item['product_fallback_name'] ?? 'Produk Dihapus/Nama Kosong';
                            }
                            
                            // Tentukan tampilan akhir
                            $product_display = htmlspecialchars($product_name_to_display);
                            
                            // 🔥 Tambahkan Varian (Size) jika ada
                            if (!empty($item['product_variant_size'])) {
                                // Menggunakan badge dark agar terlihat jelas di area admin
                                $product_display .= ' <span class="badge bg-dark ms-2 fw-normal">' . htmlspecialchars($item['product_variant_size']) . '</span>';
                            }
                        ?>
                        <tr>
                            <td><?php echo $product_display; ?></td>
                            <td class="text-nowrap">Rp <?php echo number_format($item['price_at_order'], 0, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                            <td class="text-nowrap">Rp <?php echo number_format($item['price_at_order'] * $item['quantity'], 0, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end">GRAND TOTAL</th>
                            <th class="text-nowrap">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></th>
                        </tr>
                    </tfoot>
                </table>
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