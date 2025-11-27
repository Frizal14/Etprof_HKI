<?php
/**
 * confirm_receipt.php
 * Logika untuk mengubah status pesanan menjadi 'Selesai' (Diterima oleh user).
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login_user.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['id'] ?? null;
$new_status = 'selesai';

if (!is_numeric($order_id) || $order_id <= 0) {
    $_SESSION['toast_message'] = "❌ Gagal: ID Pesanan tidak valid.";
    $_SESSION['toast_type'] = 'error';
    header('Location: orders_user.php');
    exit;
}

// 1. Cek Pesanan dan Status Saat Ini (Hanya boleh diubah jika statusnya 'dikirim')
$stmt_check = $koneksi->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
$stmt_check->bind_param("ii", $order_id, $user_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    $_SESSION['toast_message'] = "❌ Gagal: Pesanan tidak ditemukan.";
    $_SESSION['toast_type'] = 'error';
    header('Location: orders_user.php');
    exit;
}

$order = $result_check->fetch_assoc();
$stmt_check->close();

if (strtolower($order['status']) !== 'dikirim') {
    $_SESSION['toast_message'] = "⚠️ Konfirmasi penerimaan hanya bisa dilakukan jika status pesanan 'Dikirim'.";
    $_SESSION['toast_type'] = 'warning';
    header('Location: orders_user.php');
    exit;
}


// 2. Update Status Pesanan menjadi 'selesai'
$stmt_update = $koneksi->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");

// Jika kolom updated_at belum ada di DB, gunakan query:
// $stmt_update = $koneksi->prepare("UPDATE orders SET status = ? WHERE id = ?"); 

if ($stmt_update) {
    $stmt_update->bind_param("si", $new_status, $order_id);
    
    if ($stmt_update->execute()) {
        $_SESSION['toast_message'] = "🎉 Pesanan **#ORD-" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . "** berhasil dikonfirmasi diterima. Terima kasih!";
        $_SESSION['toast_type'] = 'success';
    } else {
        $_SESSION['toast_message'] = "❌ Gagal mengkonfirmasi pesanan: " . $stmt_update->error;
        $_SESSION['toast_type'] = 'error';
    }
    $stmt_update->close();
} else {
    $_SESSION['toast_message'] = "❌ Gagal menyiapkan query database.";
    $_SESSION['toast_type'] = 'error';
}

$koneksi->close();
header('Location: orders_user.php'); // Redirect kembali ke daftar pesanan user
exit;
?>