<?php
// add_to_cart.php
// Menangani penambahan produk ke keranjang dengan dukungan Varian.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    // Arahkan ke login, tetapi simpan tujuan kembali
    $_SESSION['redirect_after_login'] = 'toko_sepatu.php'; 
    header('Location: login_user.php');
    exit;
}

// 1. Ambil data dari POST (dari detail.php form) atau GET (dari link/tombol cepat)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $variant_id = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    // Logika ini untuk tombol cepat "add to cart" tanpa varian/quantity di toko_sepatu.php (jika ada)
    $product_id = (int)$_GET['id'];
    $variant_id = 0; // Harus diatasi, karena varian wajib. Kita akan mencari varian pertama.
    $quantity = 1;
} else {
    // ID Produk tidak valid
    $_SESSION['toast_message'] = "❌ Gagal: ID produk tidak valid.";
    $_SESSION['toast_type'] = 'error';
    header('Location: toko_sepatu.php');
    exit;
}

// Jika quantity kurang dari 1, set minimal 1
if ($quantity < 1) {
    $quantity = 1;
}

// --- Pengecekan dan Pengambilan Data Produk dan Varian ---

// 2. Ambil data produk utama (untuk nama, harga)
$stmt = $koneksi->prepare("SELECT id, name, price FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    $_SESSION['toast_message'] = "❌ Gagal: Produk tidak ditemukan.";
    $_SESSION['toast_type'] = 'error';
    header('Location: toko_sepatu.php');
    exit;
}

// 3. Ambil data varian
$variant = null;
if ($variant_id > 0) {
    // Jika variant_id disediakan dari form
    $stmt_v = $koneksi->prepare("SELECT id, size, stock FROM product_variants WHERE id = ? AND product_id = ?");
    $stmt_v->bind_param("ii", $variant_id, $product_id);
    $stmt_v->execute();
    $result_v = $stmt_v->get_result();
    $variant = $result_v->fetch_assoc();
    $stmt_v->close();
} elseif (isset($_GET['id']) && $variant_id == 0) {
    // Jika datang dari GET tanpa variant_id, ambil varian pertama yang stoknya > 0
    $stmt_v = $koneksi->prepare("SELECT id, size, stock FROM product_variants WHERE product_id = ? AND stock > 0 ORDER BY size ASC LIMIT 1");
    $stmt_v->bind_param("i", $product_id);
    $stmt_v->execute();
    $result_v = $stmt_v->get_result();
    $variant = $result_v->fetch_assoc();
    $stmt_v->close();
    $variant_id = $variant ? $variant['id'] : 0;
}

if (!$variant || $variant['stock'] <= 0) {
    $_SESSION['toast_message'] = "❌ Gagal: Varian tidak ditemukan atau stok untuk varian tersebut habis.";
    $_SESSION['toast_type'] = 'error';
    header('Location: toko_sepatu.php');
    exit;
}

// Tentukan kunci unik untuk item keranjang (product_id dan variant_id)
$cart_item_key = $product_id . '_' . $variant_id;
$stock_available = (int)$variant['stock'];

// 4. Inisialisasi Keranjang (Cart) jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// 5. Tambahkan atau Perbarui Item di Keranjang
if (array_key_exists($cart_item_key, $_SESSION['cart'])) {
    // Item sudah ada di keranjang
    $current_quantity = $_SESSION['cart'][$cart_item_key]['quantity'];
    $new_quantity = $current_quantity + $quantity;

    // Cek batasan stok
    if ($new_quantity > $stock_available) {
        $_SESSION['toast_message'] = "⚠️ Gagal: Penambahan $quantity unit melebihi stok yang tersedia ($stock_available) untuk ukuran " . htmlspecialchars($variant['size']) . ".";
        $_SESSION['toast_type'] = 'warning';
        header('Location: toko_sepatu.php');
        exit;
    }
    $_SESSION['cart'][$cart_item_key]['quantity'] = $new_quantity;

} else {
    // Item baru di keranjang
    
    // Cek stok awal
    if ($quantity > $stock_available) {
        $_SESSION['toast_message'] = "⚠️ Gagal: Jumlah pembelian melebihi stok yang tersedia ($stock_available) untuk ukuran " . htmlspecialchars($variant['size']) . ".";
        $_SESSION['toast_type'] = 'warning';
        header('Location: toko_sepatu.php');
        exit;
    }

    // Tambahkan item baru ke keranjang
    $_SESSION['cart'][$cart_item_key] = [
        'product_id' => $product['id'],
        'product_name' => $product['name'],
        'variant_id' => $variant['id'],
        'variant_size' => $variant['size'],
        'price' => $product['price'],
        'quantity' => $quantity,
    ];
}

// 6. Redirect kembali ke halaman toko dengan notifikasi
// 🔥 GANTI DARI TOAST KE SWEET ALERT (SWAL) UNTUK SUKSES 🔥
$_SESSION['swal_icon'] = "success";
$_SESSION['swal_title'] = "Ditambahkan ke Keranjang!";
$_SESSION['swal_text'] = "Produk " . htmlspecialchars($product['name']) . " ukuran " . htmlspecialchars($variant['size']) . " ($quantity unit) berhasil ditambahkan.";

header('Location: toko_sepatu.php');
exit;

// Tutup koneksi
$koneksi->close();
?>