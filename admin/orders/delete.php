<?php
/**
 * admin/orders/delete.php
 * Script untuk menghapus Pesanan (Order) beserta detail itemnya (Order Items) menggunakan Transaksi.
 * 🔥 REVIEW: Kode sudah benar dan menggunakan transaksi (GOOD PRACTICE). Penyesuaian minor pada format pesan.
 */

// Pastikan koneksi dan variabel global tersedia dari dashboard.php
$db_koneksi = $GLOBALS['koneksi'] ?? null;
$router_path = $router_path ?? 'dashboard.php';

// Pastikan session sudah dimulai jika belum
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Fungsi untuk Formatting ID
function format_order_id($id) {
    return '#ORD-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}

// Pastikan hanya request GET dengan ID yang valid yang diterima
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['id'])) {
    // 🔥 Penyesuaian: Gunakan alert style untuk pesan
    $_SESSION['message'] = '<div class="alert alert-danger">Permintaan hapus tidak valid.</div>';
    header('Location: ' . $router_path . '?page=orders/index');
    exit;
}

$order_id = (int)$_GET['id'];
$formatted_id = format_order_id($order_id); // Dapatkan format ID untuk pesan

if ($order_id <= 0 || !$db_koneksi) {
    // 🔥 Penyesuaian: Gunakan alert style untuk pesan
    $_SESSION['message'] = '<div class="alert alert-danger">ID Pesanan tidak valid atau koneksi database error.</div>';
    header('Location: ' . $router_path . '?page=orders/index');
    exit;
}

// Mulai Transaksi
// Pastikan driver MySQL mendukung Transaksi (umumnya iya, jika menggunakan InnoDB)
$db_koneksi->begin_transaction();
$success = false;
$error_message = '';

try {
    // 1. Hapus Item Pesanan (tabel order_items)
    $stmt_items = $db_koneksi->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmt_items->bind_param("i", $order_id);
    
    if (!$stmt_items->execute()) {
        throw new Exception("Gagal menghapus item pesanan: " . $stmt_items->error);
    }
    $stmt_items->close();

    // 2. Hapus Pesanan Utama (tabel orders)
    $stmt_order = $db_koneksi->prepare("DELETE FROM orders WHERE id = ?");
    $stmt_order->bind_param("i", $order_id);
    
    if (!$stmt_order->execute()) {
        throw new Exception("Gagal menghapus pesanan utama: " . $stmt_order->error);
    }
    
    // Cek apakah ada baris yang terpengaruh
    if ($stmt_order->affected_rows === 0) {
        // Ini tidak harus menjadi error fatal, karena item mungkin sudah terhapus,
        // tapi kita bisa pertahankan untuk memastikan pesanan memang ada.
        throw new Exception("Pesanan $formatted_id tidak ditemukan atau sudah terhapus.");
    }
    
    $stmt_order->close();

    // Komit Transaksi jika kedua operasi berhasil
    $db_koneksi->commit();
    $success = true;

} catch (Exception $e) {
    // Rollback jika terjadi kesalahan
    $db_koneksi->rollback();
    $error_message = $e->getMessage();
}

// 3. Redirect kembali ke halaman index
if ($success) {
    // 🔥 Penyesuaian: Gunakan alert style untuk pesan sukses
    $_SESSION['message'] = '<div class="alert alert-success">Pesanan **' . $formatted_id . '** dan item terkait berhasil dihapus.</div>';
} else {
    // 🔥 Penyesuaian: Tambahkan pesan kegagalan transaksi yang lebih jelas
    $_SESSION['message'] = '<div class="alert alert-danger">Gagal menghapus pesanan **' . $formatted_id . '**. ' . $error_message . ' Transaksi dibatalkan.</div>';
}

header('Location: ' . $router_path . '?page=orders/index');
exit;
?>