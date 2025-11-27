<?php
/**
 * cancel_order.php
 * Script backend untuk membatalkan pesanan yang dibuat oleh user dan MENGEMBALIKAN STOK PRODUK.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'koneksi.php';
global $koneksi;

// Cek Otentikasi
if (!isset($_SESSION['user_id'])) {
    header('Location: login_user.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['id'] ?? null;

// 1. Validasi ID Pesanan
if (!is_numeric($order_id) || $order_id <= 0) {
    $_SESSION['toast_message'] = "ID Pesanan tidak valid.";
    $_SESSION['toast_type'] = 'error';
    header('Location: orders_user.php');
    exit;
}

// Mulai Database Transaction untuk memastikan atomicity (semua atau tidak sama sekali)
$koneksi->begin_transaction();
$success = false;
$current_status = '';

try {
    // 2. Ambil Status Pesanan saat ini dan cek kepemilikan (menggunakan LOCK untuk menghindari race condition)
    // 'FOR UPDATE' mengunci baris ini selama transaksi berlangsung
    $stmt_check = $koneksi->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ? FOR UPDATE");
    $stmt_check->bind_param("ii", $order_id, $user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        throw new Exception("Pesanan tidak ditemukan atau Anda tidak memiliki akses.");
    }
    
    $order_data = $result_check->fetch_assoc();
    $current_status = strtolower($order_data['status']);
    $stmt_check->close();
    
    // 3. Batasi Pembatalan pada status yang diizinkan
    $allowed_statuses = ['pending', 'processing', 'payment_sent']; // Izinkan dibatalkan jika belum dikirim
    
    if (!in_array($current_status, $allowed_statuses)) {
        throw new Exception("Pesanan tidak dapat dibatalkan karena status saat ini adalah **" . ucwords($current_status) . "**.");
    }

    // 4. Ambil item-item pesanan untuk MENGEMBALIKAN STOK
    $stmt_items = $koneksi->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $stmt_items->bind_param("i", $order_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    
    // 5. Update Status Pesanan menjadi 'dibatalkan'
    $new_status = 'dibatalkan';
    $stmt_update = $koneksi->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt_update->bind_param("si", $new_status, $order_id);
    if (!$stmt_update->execute()) {
        throw new Exception("Gagal mengupdate status pesanan menjadi 'dibatalkan'.");
    }
    $stmt_update->close();
    
    // 6. Kembalikan Stok Produk
    $stmt_stock_restore = $koneksi->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
    
    while ($item = $result_items->fetch_assoc()) {
        $stmt_stock_restore->bind_param("ii", $item['quantity'], $item['product_id']);
        if (!$stmt_stock_restore->execute()) {
             throw new Exception("Gagal mengembalikan stok untuk produk ID " . $item['product_id'] . ".");
        }
    }

    $stmt_items->close();
    $stmt_stock_restore->close();
    
    $koneksi->commit(); // Komit transaksi jika semua sukses
    $success = true;
    
} catch (Exception $e) {
    $koneksi->rollback(); // Batalkan semua perubahan jika terjadi Exception
    $_SESSION['toast_message'] = "❌ Pembatalan Gagal: " . $e->getMessage();
    $_SESSION['toast_type'] = 'error';
    header('Location: orders_user.php');
    exit;
}

$koneksi->close();

if ($success) {
    $formatted_id = '#ORD-' . str_pad($order_id, 6, '0', STR_PAD_LEFT);
    $_SESSION['toast_message'] = "✅ Pesanan **{$formatted_id}** berhasil dibatalkan. Stok produk telah dikembalikan.";
    $_SESSION['toast_type'] = 'success';
}

header('Location: orders_user.php');
exit;
?>