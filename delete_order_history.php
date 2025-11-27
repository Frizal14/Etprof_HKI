<?php
/**
 * delete_order_history.php
 * Script untuk menghapus satu atau semua riwayat pesanan (yang sudah selesai/dibatalkan) user.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login_user.php');
    exit;
}

require_once 'koneksi.php'; 

$user_id = $_SESSION['user_id'];
$order_id_to_delete = $_GET['id'] ?? null;

if ($koneksi) {
    $koneksi->begin_transaction();
    $deleted_count = 0;
    
    try {
        if ($order_id_to_delete) {
            // HAPUS SATU PESANAN (Jika sudah selesai atau dibatalkan)
            $sql = "DELETE FROM orders WHERE id = ? AND user_id = ? AND status IN ('selesai', 'dibatalkan')";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("ii", $order_id_to_delete, $user_id);
            $stmt->execute();
            $deleted_count = $stmt->affected_rows;
            $stmt->close();
            
            if ($deleted_count > 0) {
                $_SESSION['toast_message'] = "Pesanan #ORD-" . str_pad($order_id_to_delete, 6, '0', STR_PAD_LEFT) . " berhasil dihapus dari riwayat.";
                $_SESSION['toast_type'] = "success";
            } else {
                $_SESSION['toast_message'] = "Gagal menghapus pesanan. Pastikan statusnya Selesai atau Dibatalkan.";
                $_SESSION['toast_type'] = "error";
            }
        
        } else {
            // HAPUS SEMUA RIWAYAT (Pesanan Selesai atau Dibatalkan)
            $sql = "DELETE FROM orders WHERE user_id = ? AND status IN ('selesai', 'dibatalkan')";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $deleted_count = $stmt->affected_rows;
            $stmt->close();
            
            $_SESSION['toast_message'] = "$deleted_count pesanan berhasil dihapus dari riwayat Anda.";
            $_SESSION['toast_type'] = "success";
        }
        
        $koneksi->commit();
        
    } catch (mysqli_sql_exception $exception) {
        $koneksi->rollback();
        $_SESSION['toast_message'] = "Terjadi kesalahan database saat menghapus riwayat.";
        $_SESSION['toast_type'] = "error";
    }
    
    $koneksi->close();
} else {
    $_SESSION['toast_message'] = "Koneksi database gagal.";
    $_SESSION['toast_type'] = "error";
}

header('Location: orders_user.php');
exit;
?>