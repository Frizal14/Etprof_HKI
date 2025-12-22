<?php
/**
 * cancel_order.php
 * Script backend untuk membatalkan pesanan dan MENGEMBALIKAN STOK.
 * Perbaikan: Support untuk produk varian dan non-varian.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'koneksi.php'; // Pastikan path ini benar
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

// Mulai Transaksi Database
$koneksi->begin_transaction();
$success = false;

try {
    // 2. Ambil Status Pesanan & Cek Kepemilikan (LOCK FOR UPDATE)
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
    
    // 3. Batasi Status yang Boleh Dibatalkan
    $allowed_statuses = ['pending', 'processing', 'payment_sent', 'menunggu pembayaran', 'diproses']; 
    
    if (!in_array($current_status, $allowed_statuses)) {
        throw new Exception("Pesanan tidak dapat dibatalkan karena status saat ini adalah: " . ucfirst($current_status));
    }

    // 4. Ambil item pesanan untuk RESTOCK
    // PERBAIKAN: Mengambil kolom 'product_id' DAN 'variant_id'
    $query_items = "SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = ?";
    $stmt_items = $koneksi->prepare($query_items);
    $stmt_items->bind_param("i", $order_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    
    // 5. Update Status Pesanan jadi 'dibatalkan'
    $new_status = 'dibatalkan';
    $stmt_update = $koneksi->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt_update->bind_param("si", $new_status, $order_id);
    if (!$stmt_update->execute()) {
        throw new Exception("Gagal mengupdate status pesanan.");
    }
    $stmt_update->close();
    
    // 6. Kembalikan Stok (LOGIKA BARU)
    // Siapkan statement untuk update stok varian
    $stmt_restock_variant = $koneksi->prepare("UPDATE product_variants SET stock = stock + ? WHERE id = ?");
    
    // Siapkan statement untuk update stok produk utama (jika tidak ada varian)
    // PASTIKAN TABEL PRODUCTS SUDAH ADA KOLOM 'stock'
    $stmt_restock_product = $koneksi->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
    
    while ($item = $result_items->fetch_assoc()) {
        $qty = $item['quantity'];
        $id_varian = $item['variant_id']; // Kolom sesuai tabel order_items
        $id_produk = $item['product_id'];

        if (!empty($id_varian)) {
            // KASUS A: Barang memiliki Varian (Size L, XL, dll) -> Update tabel product_variants
            $stmt_restock_variant->bind_param("ii", $qty, $id_varian);
            if (!$stmt_restock_variant->execute()) {
                throw new Exception("Gagal mengembalikan stok Varian ID: $id_varian");
            }
        } else {
            // KASUS B: Barang TIDAK memiliki Varian -> Update tabel products
            $stmt_restock_product->bind_param("ii", $qty, $id_produk);
            if (!$stmt_restock_product->execute()) {
                throw new Exception("Gagal mengembalikan stok Produk ID: $id_produk (Pastikan kolom 'stock' ada di tabel products)");
            }
        }
    }

    $stmt_items->close();
    $stmt_restock_variant->close();
    $stmt_restock_product->close();
    
    // Jika semua lancar, COMMIT perubahan
    $koneksi->commit();
    $success = true;
    
} catch (Exception $e) {
    // Jika ada error, batalkan semua perubahan
    $koneksi->rollback();
    
    $_SESSION['toast_message'] = "❌ Pembatalan Gagal: " . $e->getMessage();
    $_SESSION['toast_type'] = 'error';
    
    header('Location: orders_user.php');
    exit;
}

// Tutup koneksi
if (isset($koneksi)) {
    $koneksi->close();
}

if ($success) {
    $formatted_id = '#ORD-' . str_pad($order_id, 6, '0', STR_PAD_LEFT);
    $_SESSION['toast_message'] = "✅ Pesanan **{$formatted_id}** berhasil dibatalkan. Stok produk telah dikembalikan.";
    $_SESSION['toast_type'] = 'success';
}

header('Location: orders_user.php');
exit;
?>