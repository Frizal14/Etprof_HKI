<?php
/**
 * admin/products/delete.php
 * Logika untuk Menghapus Produk. (DELETE)
 * Penyesuaian: Menambahkan pengecekan Foreign Key (FK) ke order_items sebelum menghapus.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ambil koneksi database dan path PHP dari global scope
$koneksi = $GLOBALS['koneksi'] ?? null;
$router_path = 'dashboard.php'; 
$target_dir = $GLOBALS['uploads_path_php_file_op'] ?? "../uploads/product_images/"; 


if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header("Location: {$router_path}?page=products/index");
    exit;
}

if (!$koneksi) {
    $_SESSION['message'] = [
        'type' => 'danger',
        'text' => "❌ Error: Koneksi database tidak tersedia."
    ];
    header("Location: {$router_path}?page=products/index");
    exit;
}

$product_id = (int)$_POST['id'];
$image_to_delete = null;
$product_name_for_msg = "Produk ID #{$product_id}"; // Default message


// 1. Ambil nama file gambar dan nama produk sebelum dihapus
$sql_select = "SELECT name, image_path FROM products WHERE id = ?";
$stmt_select = $koneksi->prepare($sql_select);

if ($stmt_select === false) {
    $_SESSION['message'] = [
        'type' => 'danger',
        'text' => "❌ Error: Gagal menyiapkan query SELECT: " . $koneksi->error
    ];
    header("Location: {$router_path}?page=products/index");
    exit;
}

$stmt_select->bind_param("i", $product_id);
$stmt_select->execute();
$result_select = $stmt_select->get_result();
$product = $result_select->fetch_assoc();
$stmt_select->close();

if ($product) {
    $image_to_delete = $product['image_path'];
    $product_name_for_msg = htmlspecialchars($product['name']);

    // 🔥 Pengecekan Keterkaitan dengan Order Items
    $sql_check_orders = "SELECT COUNT(*) AS total FROM order_items WHERE product_id = ?";
    $stmt_check = $koneksi->prepare($sql_check_orders);
    $stmt_check->bind_param("i", $product_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $order_count = $result_check->fetch_assoc()['total'] ?? 0;
    $stmt_check->close();

    if ($order_count > 0) {
        // BATALKAN JIKA ADA RIWAYAT PESANAN
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => "❌ Gagal menghapus produk **{$product_name_for_msg}**. Produk ini memiliki **{$order_count} riwayat pesanan** dan tidak dapat dihapus. Silakan arsipkan produk ini sebagai gantinya."
        ];
        header("Location: {$router_path}?page=products/index");
        exit;
    }
    // 🔥 AKHIR Pengecekan

    
    // 🔥 MULAI TRANSAKSI
    $koneksi->begin_transaction();
    $delete_success = false;

    try {
        // OPSIONAL: Jika Foreign Key belum ON DELETE CASCADE, hapus varian secara eksplisit
        $sql_delete_variants = "DELETE FROM product_variants WHERE product_id = ?";
        $stmt_delete_variants = $koneksi->prepare($sql_delete_variants);
        if ($stmt_delete_variants === false) {
             throw new Exception("Gagal menyiapkan query DELETE Varian: " . $koneksi->error);
        }
        $stmt_delete_variants->bind_param("i", $product_id);
        $stmt_delete_variants->execute();
        $stmt_delete_variants->close();
        
        // 2. Hapus record dari tabel products
        $sql_delete_product = "DELETE FROM products WHERE id = ?";
        $stmt_delete_product = $koneksi->prepare($sql_delete_product);
        
        if ($stmt_delete_product === false) {
            throw new Exception("Gagal menyiapkan query DELETE Produk: " . $koneksi->error);
        }

        $stmt_delete_product->bind_param("i", $product_id);
        
        if ($stmt_delete_product->execute()) {
            $delete_success = true;
            $koneksi->commit(); // COMMIT TRANSAKSI
        } else {
             throw new Exception("Gagal menghapus produk dari database: " . $stmt_delete_product->error);
        }
        $stmt_delete_product->close();

    } catch (Exception $e) {
        $koneksi->rollback(); // ROLLBACK JIKA ADA ERROR
        $_SESSION['message'] = [
            'type' => 'danger',
            'text' => "❌ Gagal menghapus produk **{$product_name_for_msg}**: " . $e->getMessage()
        ];
        header("Location: {$router_path}?page=products/index");
        exit;
    }
    
    
    // 3. Hapus file gambar dari server jika delete_success TRUE
    if ($delete_success) {
        if (!empty($image_to_delete) && $image_to_delete != '0' && file_exists($target_dir . $image_to_delete)) {
            // Menggunakan @unlink untuk menekan error jika file tidak bisa dihapus
            @unlink($target_dir . $image_to_delete); 
        }
        
        $_SESSION['message'] = [
            'type' => 'warning', // Biasanya delete menggunakan warning/danger
            'text' => "🗑️ Produk **{$product_name_for_msg}** berhasil dihapus, termasuk varian dan gambarnya."
        ];
    }

} else {
    $_SESSION['message'] = [
        'type' => 'info',
        'text' => "⚠️ Produk dengan ID #{$product_id} tidak ditemukan (mungkin sudah dihapus)."
    ];
}

header("Location: {$router_path}?page=products/index");
exit;
?>