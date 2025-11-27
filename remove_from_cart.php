<?php
/**
 * remove_from_cart.php
 * Logika untuk menghapus item produk DENGAN VARIAN dari keranjang belanja (PHP Session) 
 * atau mengosongkan seluruh keranjang.
 * DIUBAH: Menggunakan Sweet Alert (SWAL) untuk pesan sukses, warning, dan info.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    // Tetap gunakan Toast untuk error login (sebelum ada SWAL di login)
    $_SESSION['toast_message'] = 'Anda harus login untuk mengelola keranjang!';
    $_SESSION['toast_type'] = 'error';
    header('Location: login_user.php');
    exit;
}

// Inisialisasi variabel Sweet Alert/Toast
$swal_title = 'Pembaruan Keranjang';
$swal_text = '';
$swal_icon = 'info';
$is_error = false;
$redirect = true;

// 1. Logika untuk MENGOSONGKAN SELURUH KERANJANG
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
        unset($_SESSION['cart']);
        $swal_text = '✅ Seluruh keranjang berhasil dikosongkan.';
        $swal_icon = 'info'; // Menggunakan info/success untuk konfirmasi clear
        $swal_title = 'Keranjang Dikosongkan!';
    } else {
        $swal_text = 'Keranjang Anda sudah kosong.';
        $swal_icon = 'warning';
        $swal_title = 'Sudah Kosong';
    }

} 
// 2. Logika untuk MENGHAPUS SATU ITEM VARIAN
else if (isset($_GET['key'])) {
    $item_key = $_GET['key']; // Kunci dalam format "product_id_variant_id"

    if (isset($_SESSION['cart']) && array_key_exists($item_key, $_SESSION['cart'])) {
        
        // Ambil nama produk dan varian untuk pesan yang informatif
        $product_name = $_SESSION['cart'][$item_key]['product_name'] ?? 'Item';
        $variant_size = $_SESSION['cart'][$item_key]['variant_size'] ?? 'Varian N/A';
        
        // Hapus item dari session cart
        unset($_SESSION['cart'][$item_key]);
        
        $swal_text = "🗑️ **" . htmlspecialchars($product_name) . " ($variant_size)** berhasil dihapus dari keranjang.";
        $swal_icon = 'success';
        $swal_title = 'Item Dihapus';
    
    } else {
        $swal_text = '⚠️ Item tidak ditemukan di keranjang Anda.';
        $swal_icon = 'warning';
        $swal_title = 'Gagal Menghapus';
    }
} 
// 3. Penanganan Jika TIDAK ADA 'key' maupun 'action'
else {
    $swal_text = '❌ Permintaan tidak valid. Kunci item keranjang (key) atau aksi (action) diperlukan.';
    $swal_icon = 'error';
    $swal_title = 'Permintaan Tidak Valid';
    $is_error = true; // Tandai sebagai error
}

// Set flash message menggunakan SWAL untuk sukses/info/warning, dan Toast untuk error
if ($is_error) {
    // Gunakan Toast untuk error agar konsisten
    $_SESSION['toast_message'] = $swal_text;
    $_SESSION['toast_type'] = 'error';
} else {
    // Gunakan Sweet Alert untuk semua notifikasi lainnya
    $_SESSION['swal_title'] = $swal_title;
    $_SESSION['swal_text'] = $swal_text;
    $_SESSION['swal_icon'] = $swal_icon;
}

// Redirect kembali ke halaman keranjang
if ($redirect) {
    header('Location: cart.php');
    exit;
}
?>