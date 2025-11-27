<?php
// admin/categories/delete.php
/**
 * Logika untuk Menghapus Kategori (DELETE).
 * Penyesuaian: Migrasi ke sistem Flash Message berbasis $_SESSION['message'] array.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Ambil koneksi dari global scope dashboard.php
$koneksi = $GLOBALS['koneksi'] ?? null; 
// Variabel router path harus tersedia dari scope dashboard.php
$router_path = $router_path ?? 'dashboard.php'; 

if (!$koneksi) {
    // Fatal error jika koneksi tidak tersedia
    $_SESSION['message'] = [
        'type' => 'danger',
        'text' => 'Kesalahan Database: Koneksi database tidak tersedia.'
    ];
    header('Location: ' . $router_path . '?page=categories/index');
    exit;
}

// Cek ID yang diterima
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = [
        'type' => 'danger',
        'text' => 'ID kategori tidak valid.'
    ];
    header('Location: ' . $router_path . '?page=categories/index');
    exit;
}

$category_id = (int)$_GET['id'];
$category_name = 'Kategori'; // Default name for error messages

$koneksi->begin_transaction();

try {
    // 1. Ambil nama kategori untuk pesan notifikasi
    $stmt_name = $koneksi->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt_name->bind_param("i", $category_id);
    $stmt_name->execute();
    $result_name = $stmt_name->get_result();
    
    if ($result_name->num_rows === 0) {
        throw new Exception("Kategori tidak ditemukan.");
    }
    
    $category = $result_name->fetch_assoc();
    $category_name = $category['name'];
    $stmt_name->close();
    
    // 2. Hapus kategori
    $stmt_delete = $koneksi->prepare("DELETE FROM categories WHERE id = ?");
    $stmt_delete->bind_param("i", $category_id);
    
    if (!$stmt_delete->execute()) {
        // Pengecekan error Foreign Key lebih detail
        if (strpos($koneksi->error, 'foreign key constraint fails') !== false) {
             // Rollback dan lempar exception spesifik untuk FK
             $koneksi->rollback();
             throw new Exception("Tidak dapat menghapus kategori **" . htmlspecialchars($category_name) . "** karena masih digunakan oleh produk.");
        }
        throw new Exception("Gagal menghapus kategori: " . $stmt_delete->error);
    }
    $stmt_delete->close();
    
    $koneksi->commit();
    
    // Set Flash Message Sukses
    $_SESSION['message'] = [
        'type' => 'success',
        'text' => "Kategori **" . htmlspecialchars($category_name) . "** (ID: $category_id) berhasil dihapus. 🗑️"
    ];
    
    // Redirect ke index.php melalui router
    header('Location: ' . $router_path . '?page=categories/index');
    exit;

} catch (Exception $e) {
    $koneksi->rollback();
    
    // Set Flash Message Error
    $msg = $e->getMessage();
    
    // Cek apakah pesan error sudah spesifik (e.g., dari throw di atas)
    if (strpos($msg, 'masih digunakan oleh produk') !== false) {
        // Gunakan pesan yang sudah di-custom
        $final_msg = $msg;
    } else {
        // Gunakan pesan error umum
        $final_msg = "Gagal melakukan operasi: " . htmlspecialchars($msg);
    }

    $_SESSION['message'] = [
        'type' => 'danger',
        'text' => $final_msg . " ❌"
    ];

    // Redirect ke index.php melalui router
    header('Location: ' . $router_path . '?page=categories/index');
    exit;
}
?>