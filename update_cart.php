<?php
/**
 * update_cart.php
 * Logika untuk memperbarui kuantitas item produk di keranjang belanja (PHP Session) 
 * dengan validasi stok terbaru dari tabel product_variants, dan mengatur flash message.
 * DIUBAH: Menggunakan Sweet Alert (SWAL) untuk notifikasi sukses, dan Toast untuk warning/error.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    // Tetap gunakan Toast untuk error login
    $_SESSION['toast_message'] = 'Anda harus login untuk mengelola keranjang!';
    $_SESSION['toast_type'] = 'error';
    header('Location: login_user.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quantity'])) {
    $quantities = $_POST['quantity'];
    $has_stock_error = false;
    
    // Inisialisasi default ke Sukses (akan diubah jika ada error/warning)
    $message_type = 'success';
    $message_text = "Keranjang berhasil diperbarui.";

    // 1. Filter Kunci Item dan Ekstrak Variant IDs
    $variant_ids_to_check = [];
    $key_to_variant_id = [];
    $valid_cart_keys = [];

    foreach ($quantities as $item_key => $new_quantity) {
        // Item Key format: "product_id_variant_id"
        if (isset($_SESSION['cart'][$item_key])) {
            $parts = explode('_', $item_key);
            if (count($parts) === 2 && is_numeric($parts[1])) {
                $variant_id = (int)$parts[1];
                $variant_ids_to_check[] = $variant_id;
                $key_to_variant_id[$item_key] = $variant_id;
                $valid_cart_keys[] = $item_key;
            }
        }
    }
    
    if (empty($valid_cart_keys)) {
        $koneksi->close();
        $_SESSION['swal_icon'] = 'warning'; // Gunakan SWAL untuk peringatan keranjang kosong/invalid
        $_SESSION['swal_title'] = 'Keranjang Kosong';
        $_SESSION['swal_text'] = "Keranjang kosong atau data yang dikirim tidak valid.";
        header('Location: cart.php');
        exit;
    }

    // 2. Query untuk mendapatkan Stok Varian terbaru
    $koneksi->begin_transaction();
    
    try {
        $placeholders = implode(',', array_fill(0, count($variant_ids_to_check), '?'));
        
        // Query untuk mendapatkan stok terbaru, nama produk, dan ukuran varian
        $sql_stock = "
            SELECT 
                pv.id AS variant_id, 
                pv.stock, 
                pv.size,
                p.name AS product_name
            FROM product_variants pv
            JOIN products p ON pv.product_id = p.id
            WHERE pv.id IN ($placeholders)
        ";
        
        $stmt_stock = $koneksi->prepare($sql_stock);
        $types = str_repeat('i', count($variant_ids_to_check));
        
        // Menggunakan reference operator untuk bind_param
        $params = array_merge([$types], $variant_ids_to_check);
        $stmt_stock->bind_param(...$params); 
        
        $stmt_stock->execute();
        $result_stock = $stmt_stock->get_result();
        
        $variant_details_by_id = [];
        while($row = $result_stock->fetch_assoc()) {
            $variant_details_by_id[$row['variant_id']] = [
                'stock' => (int)$row['stock'], 
                'name' => $row['product_name'] . ' (Ukuran: ' . $row['size'] . ')'
            ];
        }
        $stmt_stock->close();

        $updated_items = 0;
        $error_names = [];

        // 3. Iterasi dan Validasi Stok Varian
        foreach ($valid_cart_keys as $item_key) {
            $variant_id = $key_to_variant_id[$item_key];
            $new_quantity = (int)max(1, $quantities[$item_key]); // Kuantitas minimal 1
            $detail = $variant_details_by_id[$variant_id] ?? ['stock' => 0, 'name' => 'Produk Tidak Valid'];

            $available_stock = $detail['stock'];
            $item_name = $detail['name'];
            $old_quantity = $_SESSION['cart'][$item_key]['quantity'] ?? 0;

            if ($new_quantity > $available_stock) {
                // Jika melebihi stok, set kuantitas di session ke maksimal
                $_SESSION['cart'][$item_key]['quantity'] = $available_stock;
                $has_stock_error = true;
                $error_names[] = $item_name;
                
                // Hitung sebagai item yang diperbarui jika stok maks berbeda dari kuantitas lama
                if ($available_stock != $old_quantity) {
                    $updated_items++;
                }

            } else {
                // Update quantity di session hanya jika berubah
                if ($new_quantity != $old_quantity) {
                    $_SESSION['cart'][$item_key]['quantity'] = $new_quantity;
                    $updated_items++;
                }
            }
        }
        
        // Terapkan perubahan database jika berhasil (walaupun ini hanya SELECT, commit/rollback tetap baik)
        $koneksi->commit();

        // 4. Set Pesan Final Berdasarkan Hasil
        if ($has_stock_error) {
            // Jika ada error stok, gunakan Toast Warning
            $first_error_name = count($error_names) > 0 ? htmlspecialchars($error_names[0]) : 'Beberapa produk';
            $message_text = "⚠️ Perhatian! Kuantitas **{$first_error_name}** disesuaikan karena stok tidak mencukupi.";
            $message_type = 'warning'; 
            
        } elseif ($updated_items > 0) {
            // Jika ada item yang benar-benar diupdate dan tidak ada error stok, gunakan Sweet Alert Success
            $message_text = "Keranjang Anda telah berhasil diperbarui!";
            $message_type = 'success'; // SWAL
        } else {
             // Jika tidak ada error stok dan tidak ada item yang diupdate (semua kuantitas sama)
             $message_text = "Tidak ada perubahan yang dilakukan pada keranjang.";
             $message_type = 'info'; // SWAL Info
        }
        
    } catch (Exception $e) {
        $koneksi->rollback();
        // Gunakan Toast Error untuk kegagalan server
        $message_text = "❌ Gagal memperbarui keranjang. Terjadi kesalahan server.";
        $message_type = 'error';
    }
    
    $koneksi->close();

    // 5. Set flash message final (pisahkan SWAL dan Toast)
    if ($message_type === 'success' || $message_type === 'info') {
        $_SESSION['swal_icon'] = $message_type;
        $_SESSION['swal_title'] = ($message_type === 'success') ? 'Berhasil Disimpan!' : 'Info Keranjang';
        $_SESSION['swal_text'] = $message_text;
    } else {
        $_SESSION['toast_message'] = $message_text;
        $_SESSION['toast_type'] = $message_type;
    }
    
    header('Location: cart.php');
    exit;

} else {
    // Jika tidak ada data POST
    $koneksi->close();
    header('Location: cart.php');
    exit;
}
?>