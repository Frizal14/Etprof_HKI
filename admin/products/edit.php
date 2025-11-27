<?php
/**
 * admin/products/edit.php
 * Form dan Logika untuk Mengubah Produk yang Ada (UPDATE) dengan Dukungan Varian Ukuran.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$koneksi = $GLOBALS['koneksi'] ?? null;
$router_path = 'dashboard.php';

$error = [];
$product = null;
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$categories = [];
$variant_sizes_db = []; // Varian yang diambil dari DB
$variant_sizes = [];     // Varian yang akan ditampilkan di form (bisa dari DB atau dari POST error)

// --- PATH KONFIGURASI ---
// Path PHP (untuk operasi move/delete file)
$target_dir_php = $GLOBALS['uploads_path_php_file_op'] ?? dirname(__DIR__, 2) . '/uploads/product_images/'; 
// Path Browser (untuk menampilkan gambar) - Digunakan oleh edit.php dan list.php
$uploads_path_browser = $GLOBALS['uploads_path'] ?? '/e-commerce_sederhana/uploads/product_images/'; 
if (substr($uploads_path_browser, -1) !== '/') {
    $uploads_path_browser .= '/';
}


// 0. Cek ID Produk
if (!$product_id || $product_id <= 0) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => "ID Produk tidak valid."];
    header("Location: {$router_path}?page=products/index");
    exit;
}

// 1. Ambil Data Kategori
if ($koneksi) {
    $result_cat = $koneksi->query("SELECT id, name FROM categories ORDER BY name ASC");
    if ($result_cat) {
        $categories = $result_cat->fetch_all(MYSQLI_ASSOC);
    }
}


/**
 * Fungsi untuk mengambil data produk dan varian (Dipanggil saat GET dan setelah POST sukses/gagal)
 * @param mysqli $koneksi
 * @param int $product_id
 * @return array
 */
function get_product_data($koneksi, $product_id) {
    $data = ['product' => null, 'variants' => []];

    // Ambil data produk utama
    $sql_product = "SELECT id, name, description, price, category_id, image_path FROM products WHERE id = ?";
    $stmt_product = $koneksi->prepare($sql_product);
    $stmt_product->bind_param("i", $product_id);
    $stmt_product->execute();
    $result_product = $stmt_product->get_result();
    $data['product'] = $result_product->fetch_assoc();
    $stmt_product->close();

    if (!$data['product']) {
        return $data; // Produk tidak ditemukan
    }

    // Ambil data varian
    $sql_variant = "SELECT id, size, stock FROM product_variants WHERE product_id = ? ORDER BY FIELD(size, 'S', 'M', 'L', 'XL', 'XXL', 'All Size', '36', '37', '38', '39', '40', '41', '42')";
    $stmt_variant = $koneksi->prepare($sql_variant);
    $stmt_variant->bind_param("i", $product_id);
    $stmt_variant->execute();
    $result_variant = $stmt_variant->get_result();
    while ($row_variant = $result_variant->fetch_assoc()) {
        $data['variants'][] = $row_variant;
    }
    $stmt_variant->close();

    return $data;
}


// --- 2. LOGIKA UTAMA (GET DATA AWAL) ---
$data_awal = get_product_data($koneksi, $product_id);
$product = $data_awal['product'];
$variant_sizes = $data_awal['variants']; // Varian dari DB
$current_image_db = $product['image_path'] ?? '';


if (!$product) {
    $_SESSION['message'] = ['type' => 'danger', 'text' => "Produk dengan ID #{$product_id} tidak ditemukan."];
    header("Location: {$router_path}?page=products/index");
    exit;
}

// Persiapan data untuk form (awal)
$name = $product['name'] ?? '';
$description = $product['description'] ?? '';
$price = $product['price'] ?? 0;
$category_id = $product['category_id'] ?? 0;
$image_path = $product['image_path'] ?? ''; // Path gambar yang saat ini ada di DB

// Tentukan URL gambar untuk tampilan
$display_image_url = (!empty($image_path) && $image_path != '0')
    ? $uploads_path_browser . htmlspecialchars($image_path) 
    : 'https://via.placeholder.com/150x150/F8F9FA/6C757D?text=No+Image';


// ===================================================
// --- 3. LOGIKA UPDATE (POST HANDLER) ---
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A. Ambil dan Validasi Input Dasar
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0.01]]) ?? -1; 
    $category_id = (int)($_POST['category_id'] ?? 0); 
    $current_image_path = $_POST['current_image'] ?? ''; // Nama file gambar lama

    // Validasi
    if (empty($name)) { $error[] = "Nama produk wajib diisi."; }
    if ($price === false || $price <= 0) { $error[] = "Harga harus lebih dari 0 dan berupa angka yang valid."; }
    if ($category_id <= 0) { $error[] = "Kategori wajib dipilih."; } 

    // B. Ambil dan Validasi Varian Ukuran dari Form POST
    $input_size_ids = $_POST['variant_id'] ?? [];    // ID Varian (ID dari product_variants table)
    $input_sizes = $_POST['variant_size'] ?? [];    // Ukuran (S, M, L, 40, etc)
    $input_stocks = $_POST['variant_stock'] ?? [];  // Stok

    $submitted_sizes = [];
    $variants_to_process = []; // Data varian yang akan di-Insert/Update
    $has_valid_stock = false;

    if (count($input_sizes) !== count($input_stocks)) {
        $error[] = "Data ukuran dan stok tidak konsisten.";
    } else {
        $variant_sizes = []; // Reset untuk repopulasi form
        foreach ($input_sizes as $i => $size_val) {
            $variant_id = (int)($input_size_ids[$i] ?? 0);
            $stock_val = (int)($input_stocks[$i] ?? 0);
            $size_val = trim($size_val);

            // Repopulasi form
            $variant_sizes[] = ['id' => $variant_id, 'size' => $size_val, 'stock' => $stock_val];

            // Validasi Ukuran/Stok
            if (empty($size_val)) { 
                $error[] = "Ukuran varian ke-" . ($i + 1) . " wajib diisi.";
                continue;
            }
            if (in_array($size_val, $submitted_sizes)) {
                $error[] = "Ukuran **" . htmlspecialchars($size_val) . "** duplikat. Setiap ukuran harus unik.";
                continue;
            }
            if (!is_numeric($stock_val) || $stock_val < 0) {
                $error[] = "Stok untuk ukuran **" . htmlspecialchars($size_val) . "** harus berupa angka non-negatif.";
                continue;
            }
            
            $submitted_sizes[] = $size_val;
            $variants_to_process[] = ['id' => $variant_id, 'size' => $size_val, 'stock' => $stock_val];

            if ($stock_val > 0) {
                $has_valid_stock = true;
            }
        }
    }

    if (empty($variants_to_process)) {
        $error[] = "Setidaknya harus ada satu varian ukuran (dan stoknya) yang diisi dengan benar.";
    }


    // C. Proses Upload Gambar
    $new_image_path = $current_image_path; // Default: pertahankan gambar lama
    
    // Jika ada file yang diupload DAN tidak ada error validasi lain (agar tidak menimpa)
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && empty($error)) {
        
        $image_file = $_FILES['image'];
        $image_name = basename($image_file["name"]);
        $image_extension = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
        $unique_filename = uniqid('prod_', true) . '.' . $image_extension;
        $target_file = $target_dir_php . $unique_filename; 
        
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($image_extension, $allowed_types)) {
            $error[] = "Hanya file JPG, JPEG, PNG & GIF yang diizinkan.";
        }
        if ($image_file["size"] > 5000000) {
            $error[] = "Ukuran file terlalu besar. Maksimal 5MB.";
        }

        if (empty($error)) {
            // Cek dan buat folder jika belum ada (safety check)
            if (!is_dir($target_dir_php)) {
                 @mkdir($target_dir_php, 0777, true);
            }

            if (move_uploaded_file($image_file["tmp_name"], $target_file)) {
                $new_image_path = $unique_filename;
                
                // HAPUS GAMBAR LAMA jika gambar lama bukan placeholder/kosong
                if (!empty($current_image_path) && $current_image_path != '0' && file_exists($target_dir_php . $current_image_path)) {
                    @unlink($target_dir_php . $current_image_path);
                }

            } else {
                 $error[] = "❌ GAGAL UPLOAD FILE BARU. Cek Izin Folder dan Pastikan Path Benar."; 
            }
        }
    }


    // D. Simpan Perubahan ke Database
    if (empty($error)) {
        // --- 1. UPDATE TABEL products ---
        $sql_product_update = "UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, image_path = ? WHERE id = ?";
        $stmt_product_update = $koneksi->prepare($sql_product_update);
        
        if ($stmt_product_update) {
            // Binding: s s d i s i (name, description, price, category_id, image_path, id)
            $stmt_product_update->bind_param("ssdisi", $name, $description, $price, $category_id, $new_image_path, $product_id);
            $product_success = $stmt_product_update->execute();
            $stmt_product_update->close();

            if (!$product_success) {
                $error[] = "Gagal memperbarui produk utama (DB Error): " . $koneksi->error;
            }
        } else {
            $error[] = "Gagal menyiapkan query update produk: " . $koneksi->error;
        }

        // --- 2. SYNC TABEL product_variants ---
        if ($product_success && empty($error)) {
            $success_count = 0;
            $db_variant_ids = array_column($data_awal['variants'], 'id'); // ID varian yang ada di DB
            $posted_variant_ids = array_column($variants_to_process, 'id'); // ID varian yang dikirim dari form (termasuk 0 untuk yang baru)

            // A. DELETE Varian yang Dihapus dari Form
            $ids_to_delete = array_diff($db_variant_ids, $posted_variant_ids);
            if (!empty($ids_to_delete)) {
                $ids_str = implode(',', array_map('intval', $ids_to_delete));
                $koneksi->query("DELETE FROM product_variants WHERE id IN ($ids_str)");
            }

            // B. INSERT/UPDATE Varian
            $sql_update_variant = "UPDATE product_variants SET size = ?, stock = ? WHERE id = ?";
            $stmt_update = $koneksi->prepare($sql_update_variant);
            $sql_insert_variant = "INSERT INTO product_variants (product_id, size, stock) VALUES (?, ?, ?)";
            $stmt_insert = $koneksi->prepare($sql_insert_variant);
            
            if ($stmt_update && $stmt_insert) {
                foreach ($variants_to_process as $variant) {
                    if ($variant['id'] > 0) {
                        // UPDATE (Varian Lama)
                        $stmt_update->bind_param("sii", $variant['size'], $variant['stock'], $variant['id']);
                        if ($stmt_update->execute()) { $success_count++; } 
                    } else {
                        // INSERT (Varian Baru)
                        $stmt_insert->bind_param("isi", $product_id, $variant['size'], $variant['stock']);
                        if ($stmt_insert->execute()) { $success_count++; } 
                    }
                }
                $stmt_update->close();
                $stmt_insert->close();
            } else {
                $error[] = "Gagal menyiapkan query varian (update/insert).";
            }
            
            // C. Finalisasi
            if ($success_count > 0 && empty($error)) {
                $_SESSION['message'] = [
                    'type' => 'success',
                    'text' => "Produk **" . htmlspecialchars($name) . "** berhasil diperbarui. ✅" 
                ]; 
                header("Location: {$router_path}?page=products/index"); 
                exit;
            } else {
                $error[] = "Gagal memperbarui varian ukuran. Cek log database.";
                // Jangan redirect, biarkan error tampil
            }
        }
    }
    
    // Jika ada error POST, tampilkan kembali form dengan data POST
    if (!empty($error)) {
        // Perlu update $display_image_url jika user mengupload gambar baru namun ada error di validasi lain
        if (!empty($new_image_path) && $new_image_path != $current_image_path) {
             // Jika upload file fisik berhasil, tapi ada error DB/validasi, tampilkan gambar yang baru di-upload
             // Namun, ini berisiko karena jika user meng-cancel/pergi, file ini akan jadi sampah.
             // Untuk kemudahan, kita akan menampilkan gambar LAMA dari DB jika POST gagal,
             // dan mengandalkan JS untuk preview gambar BARU yang di-upload (di form).
             // Jika gambar lama berhasil diganti di server, tapi DB gagal, kita akan membiarkan file sampah
             // dan mengandalkan $image_path dari DB untuk tampilan.
             
             // Untuk saat ini, kita biarkan $display_image_url di atas tetap menggunakan $image_path dari DB
             // dan mengandalkan JS Preview untuk menampilkan gambar yang di-upload.
        }
    }
} 
// Jika ini adalah GET request, $product dan $variant_sizes sudah diisi dari get_product_data()

?>

<?php if (isset($product_id) && $koneksi && empty($error) && $_SERVER['REQUEST_METHOD'] === 'GET'): ?>
<div class="alert alert-info small py-2 px-3">
    <strong>DEBUG: </strong>
    Path Gambar: 
    <a href="<?php echo $display_image_url; ?>" target="_blank">
        <?php echo $display_image_url; ?>
    </a> (Cek 404/Akses)<br>
    Varian DB: 
    <?php echo htmlspecialchars(json_encode(array_map(fn($v) => $v['id'] . ' (' . $v['size'] . ')', $data_awal['variants']))); ?>
</div>
<?php endif; ?>
<style>
/* ... (CSS Styling, SALIN DARI CREATE.PHP) ... */
:root {
    --primary-color: #0d6efd; /* Bootstrap Primary */
    --success-color: #198754; /* Bootstrap Success */
    --warning-color: #ffc107; /* Bootstrap Warning */
    --border-radius: 8px;
    --shadow-elevation: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.page-header {
    border-bottom: 2px solid var(--warning-color); /* Warna disesuaikan untuk Edit */
    padding-bottom: 10px;
    margin-bottom: 30px;
}

.card {
    border: none;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-elevation);
}

.card-header {
    border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
    background-color: var(--warning-color) !important; /* Warna disesuaikan untuk Edit */
    color: black;
}

.form-control:focus, .form-select:focus, .btn:focus {
    border-color: rgba(255, 193, 7, 0.5); /* Warna disesuaikan untuk Edit */
    box-shadow: 0 0 0 0.25rem rgba(255, 193, 7, 0.25);
}

.btn-lg {
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: transform 0.2s;
}

.btn-lg:hover {
    transform: translateY(-2px);
}

#imagePreview {
    transition: box-shadow 0.3s ease;
}
#imagePreview:hover {
    box-shadow: 0 0 0 5px rgba(255, 193, 7, 0.2); /* Warna disesuaikan untuk Edit */
}

/* Style untuk form varian baru */
.variant-row {
    align-items: center;
    margin-bottom: 10px;
    padding: 10px;
    border: 1px dashed #ccc;
    border-radius: 5px;
}
</style>

<div class="container-fluid p-4">
    <div class="page-header">
        <h1 class="mt-4 mb-3 text-warning display-5 fw-bold">
            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-edit me-2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            Edit Produk: <?php echo htmlspecialchars($name); ?> (ID: <?php echo $product_id; ?>)
        </h1>
        <p class="lead text-secondary">Perbarui detail produk dan sesuaikan varian ukuran serta stok yang tersedia.</p>
    </div>
    
    <a href="<?php echo $router_path; ?>?page=products/index" class="btn btn-outline-secondary mb-4 rounded-pill">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-left me-1">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        Kembali ke Daftar Produk
    </a>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h4 class="alert-heading">⚠️ Gagal Memperbarui Produk!</h4>
            <ul class="mb-0">
                <?php foreach ($error as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-lg mb-5 border-0">
        <div class="card-header">
            <h5 class="mb-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-package me-2">
                    <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line>
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.3 12 11.2 20.73 6.3"></polyline>
                    <line x1="12" y1="22.78" x2="12" y2="11.2"></line>
                </svg>
                Formulir Detail Produk
            </h5>
        </div>
        <div class="card-body p-4">
            <form action="<?php echo $router_path; ?>?page=products/edit&id=<?php echo $product_id; ?>" method="POST" enctype="multipart/form-data" class="row g-4">
                
                <div class="col-md-8">
                    <label for="name" class="form-label fw-bold">Nama Produk <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="Contoh: Sepatu Lari UltraBoost 22" value="<?php echo htmlspecialchars($name); ?>" required>
                </div>
                
                <div class="col-md-4">
                    <label for="category_id" class="form-label fw-bold">Kategori <span class="text-danger">*</span></label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                <?php echo (intval($category_id) === intval($cat['id'])) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                     <?php if (empty($categories)): ?>
                         <div class="form-text text-danger">⚠️ Belum ada kategori. Mohon <a href="<?php echo $router_path; ?>?page=categories/create" class="alert-link">tambah kategori</a>.</div>
                     <?php endif; ?>
                </div>

                <div class="col-12">
                    <label for="description" class="form-label fw-bold">Deskripsi</label>
                    <textarea class="form-control" id="description" name="description" rows="4" placeholder="Jelaskan detail produk secara singkat dan menarik..."><?php echo htmlspecialchars($description); ?></textarea>
                </div>
                
                <div class="col-md-6">
                    <label for="price" class="form-label fw-bold">Harga (Rp) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" step="0.01" min="0.01" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($price > 0 ? $price : ''); ?>" required>
                    </div>
                </div>
                
                <div class="col-12 mt-5">
                    <div class="card border-warning border-2">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="fas fa-ruler-combined me-2"></i> Varian Ukuran & Stok <span class="text-danger">*</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Perbarui setiap ukuran yang tersedia dan jumlah stok untuk ukuran tersebut.</p>
                            
                            <div id="variantsContainer">
                                <?php 
                                // Loop untuk menampilkan varian dari DB (GET) atau dari POST error
                                foreach ($variant_sizes as $index => $variant):
                                ?>
                                <div class="row variant-row g-3">
                                    <input type="hidden" name="variant_id[]" value="<?php echo htmlspecialchars($variant['id'] ?? 0); ?>"> 
                                    
                                    <div class="col-5">
                                        <label class="form-label small text-muted">Ukuran</label>
                                        <input type="text" class="form-control" name="variant_size[]" 
                                                   placeholder="Contoh: 40" 
                                                   value="<?php echo htmlspecialchars($variant['size']); ?>" required>
                                    </div>
                                    <div class="col-5">
                                        <label class="form-label small text-muted">Stok Ukuran Ini</label>
                                        <input type="number" min="0" class="form-control" name="variant_stock[]" 
                                                   placeholder="Jumlah stok" 
                                                   value="<?php echo htmlspecialchars($variant['stock']); ?>" required>
                                    </div>
                                    <div class="col-2 text-end">
                                        <?php 
                                            // Semua baris boleh dihapus di halaman edit
                                            // Kita hanya perlu memastikan minimal ada 1 baris yang tersisa (Logika JS)
                                        ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger mt-3 remove-variant-btn" 
                                                title="Hapus Varian Ini">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-trash-2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <button type="button" id="addVariantBtn" class="btn btn-sm btn-outline-warning mt-3">
                                <i class="fas fa-plus me-1"></i> Tambah Varian Baru
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 d-flex flex-wrap align-items-end">
                    <div class="mb-3 me-5">
                        <label class="form-label fw-bold">Gambar Saat Ini:</label><br>
                        <img id="imagePreview" 
                             src="<?php echo $display_image_url; ?>" 
                             style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #dee2e6; border-radius: var(--border-radius); display: block;">
                        
                        <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($image_path); ?>">
                    </div>

                    <div class="mb-3 flex-grow-1">
                        <label for="imageInput" class="form-label fw-bold">Ganti Gambar Produk</label>
                        <input type="file" class="form-control" id="imageInput" name="image" accept="image/*">
                        <div class="form-text">Biarkan kosong jika tidak ingin mengganti gambar. Maksimal 5MB.</div>
                    </div>
                </div>

                
                <div class="col-12 border-top pt-4 mt-4">
                    <button type="submit" class="btn btn-warning btn-lg me-2 text-dark">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-refresh-cw me-1">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M3.5 15a9 9 0 0 0 14.5-12 9 9 0 0 0-14.5 12z"></path>
                        </svg>
                        Perbarui Produk
                    </button>
                    <a href="<?php echo $router_path; ?>?page=products/index" class="btn btn-outline-secondary btn-lg">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    /**
     * Logika untuk menampilkan preview gambar yang dipilih oleh user.
     */
    document.getElementById('imageInput').addEventListener('change', function(event) {
        var file = event.target.files[0];
        var preview = document.getElementById('imagePreview');
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
            };
            reader.readAsDataURL(file);
        } else {
             // Jika file dibatalkan, kembalikan ke gambar yang ada di database
             const currentImagePath = document.querySelector('input[name="current_image"]').value;
             const basePath = '<?php echo $uploads_path_browser; ?>';
             const defaultPlaceholder = 'https://via.placeholder.com/150x150/F8F9FA/6C757D?text=No+Image';

             if (currentImagePath && currentImagePath !== '0') {
                 preview.src = basePath + currentImagePath;
             } else {
                 preview.src = defaultPlaceholder;
             }
        }
    });

    /**
     * 🌟 Logika Form Dinamis untuk Varian Ukuran (Update) 🌟
     */
    document.addEventListener('DOMContentLoaded', function() {
        const variantsContainer = document.getElementById('variantsContainer');
        const addVariantBtn = document.getElementById('addVariantBtn');

        // Fungsi untuk membuat baris varian baru (ID 0 = Baru, akan di INSERT)
        function createVariantRow() {
            const newRow = document.createElement('div');
            newRow.className = 'row variant-row g-3';
            newRow.innerHTML = `
                <input type="hidden" name="variant_id[]" value="0"> 
                
                <div class="col-5">
                    <label class="form-label small text-muted">Ukuran</label>
                    <input type="text" class="form-control" name="variant_size[]" placeholder="Contoh: 40" required>
                </div>
                <div class="col-5">
                    <label class="form-label small text-muted">Stok Ukuran Ini</label>
                    <input type="number" min="0" class="form-control" name="variant_stock[]" value="0" placeholder="Jumlah stok" required>
                </div>
                <div class="col-2 text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger mt-3 remove-variant-btn" title="Hapus Varian Ini">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-trash-2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                    </button>
                </div>
            `;
            variantsContainer.appendChild(newRow);
            
            // Tambahkan event listener untuk tombol hapus pada baris baru
            newRow.querySelector('.remove-variant-btn').addEventListener('click', function() {
                removeVariantRow(newRow);
            });
            checkRemoveButtonsVisibility();
        }

        // Fungsi untuk menghapus baris varian
        function removeVariantRow(rowElement) {
            rowElement.remove();
            checkRemoveButtonsVisibility();
        }

        // Fungsi untuk mengelola visibilitas tombol hapus (minimal 1 varian)
        function checkRemoveButtonsVisibility() {
            const rows = variantsContainer.querySelectorAll('.variant-row');
            rows.forEach(row => {
                const removeBtn = row.querySelector('.remove-variant-btn');
                if (removeBtn) {
                    removeBtn.style.display = rows.length > 1 ? 'inline-block' : 'none';
                }
            });
        }
        
        // Event listener untuk tombol 'Tambah Varian'
        addVariantBtn.addEventListener('click', createVariantRow);

        // Event listener untuk tombol hapus yang sudah ada (dari PHP)
        variantsContainer.querySelectorAll('.remove-variant-btn').forEach(button => {
            button.addEventListener('click', function() {
                removeVariantRow(this.closest('.variant-row'));
            });
        });
        
        // Panggil saat inisialisasi untuk menyembunyikan tombol hapus jika hanya 1 baris
        checkRemoveButtonsVisibility();
        
         // Inisialisasi Feather Icons
         if (typeof feather !== 'undefined') {
             feather.replace();
         }
    });
</script>