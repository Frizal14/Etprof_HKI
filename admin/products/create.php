<?php
/**
 * admin/products/create.php
 * Form dan Logika untuk Menambah Produk Baru (CREATE) dengan Dukungan Varian Ukuran.
 */

// Pastikan koneksi, session_start(), dan variabel global telah didefinisikan sebelum file ini dipanggil.

$koneksi = $GLOBALS['koneksi'] ?? null;
$router_path = 'dashboard.php'; 

$error = [];
$name = ''; 
$description = '';
$price = 0;
$category_id = ''; 
$categories = []; 
// Variabel untuk menyimpan data varian yang dimasukkan jika terjadi error
$variant_sizes = []; 

// Mengambil path PHP absolut untuk operasi file (dari dashboard.php)
$target_dir_php = $GLOBALS['uploads_path_php_file_op'] ?? dirname(__DIR__, 2) . '/uploads/product_images/'; 

// Ambil daftar kategori dari database (Diperlukan untuk dropdown)
if ($koneksi) {
    $result_cat = $koneksi->query("SELECT id, name FROM categories ORDER BY name ASC");
    if ($result_cat) {
        while ($row_cat = $result_cat->fetch_assoc()) {
            $categories[] = $row_cat;
        }
    } else {
        $error[] = "Gagal mengambil daftar kategori: " . $koneksi->error;
    }
}

$image_path = NULL; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verifikasi Koneksi Database
    if (!$koneksi) {
        $error[] = "Sistem gagal tersambung ke database.";
    }

    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0.01]]) ?? -1; 
    $category_id = (int)($_POST['category_id'] ?? 0); 
    
    // --- AMBIL DATA VARIAN UKURAN DARI FORM DINAMIS ---
    // $_POST['variant_size'] dan $_POST['variant_stock'] adalah array
    $input_sizes = $_POST['variant_size'] ?? [];
    $input_stocks = $_POST['variant_stock'] ?? [];

    // Reset varian yang dimasukkan untuk ditampilkan kembali di form
    $variant_sizes = [];
    $valid_variants = []; // Array untuk menyimpan varian yang lolos validasi

    // 2. Validasi Input Dasar
    if (empty($name)) { $error[] = "Nama produk wajib diisi."; }
    if ($price === false || $price <= 0) { $error[] = "Harga harus lebih dari 0 dan berupa angka yang valid."; }
    if ($category_id <= 0) { $error[] = "Kategori wajib dipilih."; } 

    // 3. Validasi Varian Ukuran (Stok)
    $has_valid_stock = false;
    $submitted_sizes = [];

    if (count($input_sizes) !== count($input_stocks)) {
        $error[] = "Data ukuran dan stok tidak konsisten.";
    } else {
        foreach ($input_sizes as $i => $size_val) {
            $stock_val = (int)($input_stocks[$i] ?? 0);
            $size_val = trim($size_val);

            // Simpan kembali input varian untuk repopulasi form
            $variant_sizes[] = ['size' => $size_val, 'stock' => $stock_val];

            // A. Validasi Ukuran
            if (empty($size_val)) { 
                $error[] = "Ukuran varian ke-" . ($i + 1) . " wajib diisi.";
                continue; // Lanjut ke varian berikutnya
            }
            // B. Validasi Duplikat Ukuran
            if (in_array($size_val, $submitted_sizes)) {
                 $error[] = "Ukuran **" . htmlspecialchars($size_val) . "** duplikat. Setiap ukuran harus unik.";
                continue;
            }
            $submitted_sizes[] = $size_val;
            
            // C. Validasi Stok (harus angka non-negatif)
            if (!is_numeric($stock_val) || $stock_val < 0) {
                 $error[] = "Stok untuk ukuran **" . htmlspecialchars($size_val) . "** harus berupa angka non-negatif.";
                continue;
            }
            
            // Jika lolos validasi dasar
            $valid_variants[] = ['size' => $size_val, 'stock' => $stock_val];

            if ($stock_val > 0) {
                $has_valid_stock = true;
            }
        }
    }
    
    // Validasi Akhir Stok: Minimal satu varian harus ada
    if (empty($valid_variants)) {
        $error[] = "Setidaknya harus ada satu varian ukuran (dan stoknya) yang diisi dengan benar.";
    }

    // 4. Proses Upload Gambar
    // Logika upload file tetap sama
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE && empty($error)) {
         // ... (Logika upload gambar di sini, sama seperti kode asli Anda) ...
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
             $error[] = "Gagal upload file (Error Code: " . $_FILES['image']['error'] . "). Cek konfigurasi php.ini.";
        } else {
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
                if (!is_dir($target_dir_php)) {
                    if (!@mkdir($target_dir_php, 0777, true)) {
                         $error[] = "❌ GAGAL MEMBUAT FOLDER. Cek Izin Folder/Root Server.";
                    }
                }

                if (empty($error) && move_uploaded_file($image_file["tmp_name"], $target_file)) {
                    $image_path = $unique_filename; 
                } else {
                      $error[] = "❌ GAGAL UPLOAD FILE. Target: " . htmlspecialchars($target_file) . ". Cek Izin Folder (chmod 777) dan Pastikan Path Benar."; 
                }
            }
        }
    }

    if (empty($image_path)) {
        $image_path = ''; 
    }


    // 5. Simpan ke Database
    if (empty($error)) {
        // --- A. INSERT KE TABEL products (HAPUS is_available) ---
        // Kolom yang di-insert: name, description, price, category_id, image_path
        $sql_product = "INSERT INTO products (name, description, price, category_id, image_path) VALUES (?, ?, ?, ?, ?)";
        $stmt_product = $koneksi->prepare($sql_product);
        
        if ($stmt_product === false) {
             $error[] = "Gagal menyiapkan query produk: " . $koneksi->error;
        } else {
            // Tentukan is_available (ini hanya variabel lokal untuk logika, tidak dibinding)
            $is_available = $has_valid_stock ? 1 : 0; 

            // Binding: s s d i s (name, description, price, category_id, image_path)
            // Hati-hati dengan urutan binding!
            // s (name), s (description), d (price), i (category_id), s (image_path)
            $stmt_product->bind_param("ssdis", $name, $description, $price, $category_id, $image_path);
            
            if ($stmt_product->execute()) {
                $new_product_id = $koneksi->insert_id;
                $stmt_product->close();
                
                // --- B. INSERT KE TABEL product_variants ---
                $sql_variant = "INSERT INTO product_variants (product_id, size, stock) VALUES (?, ?, ?)";
                $stmt_variant = $koneksi->prepare($sql_variant);

                if ($stmt_variant === false) {
                    $error[] = "Gagal menyiapkan query varian: " . $koneksi->error;
                } else {
                    $variant_success_count = 0;
                    foreach ($valid_variants as $variant) {
                        // Binding: i s i (product_id, size, stock)
                        $stmt_variant->bind_param("isi", $new_product_id, $variant['size'], $variant['stock']);
                        
                        if ($stmt_variant->execute()) {
                            $variant_success_count++;
                        } else {
                            // Ini mungkin terjadi jika ada masalah koneksi atau error DB lain
                              $error[] = "Gagal menyimpan varian " . htmlspecialchars($variant['size']) . " (DB Error): " . $stmt_variant->error;
                        }
                    }
                    $stmt_variant->close();
                }
                
                // C. Finalisasi
                if (empty($error) && $variant_success_count > 0) {
                    $_SESSION['message'] = [
                        'type' => 'success',
                        'text' => "Produk **" . htmlspecialchars($name) . "** berhasil ditambahkan beserta $variant_success_count varian ukurannya. ✅" 
                    ]; 
                    header("Location: {$router_path}?page=products/index"); 
                    exit;
                } else {
                    // Jika produk utama berhasil, tapi varian gagal semua, hapus produk utama.
                    $koneksi->query("DELETE FROM products WHERE id = $new_product_id");
                    $error[] = "Gagal menyimpan varian ukuran. Produk utama telah dibatalkan.";
                }

            } else {
                $error[] = "Gagal menyimpan produk (DB Error): " . $stmt_product->error;
                $stmt_product->close();
            }
        }
    }
} else {
    // Jika tidak ada POST, inisialisasi satu baris varian kosong untuk tampilan
    $variant_sizes[] = ['size' => '', 'stock' => 0];
}
?>
<style>
    /* ... (CSS Styling Tetap Sama) ... */
    :root {
        --primary-color: #0d6efd; /* Bootstrap Primary */
        --success-color: #198754; /* Bootstrap Success */
        --border-radius: 8px;
        --shadow-elevation: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .page-header {
        border-bottom: 2px solid var(--primary-color);
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
        background-color: var(--primary-color) !important;
        color: white;
    }

    .form-control:focus, .form-select:focus, .btn:focus {
        border-color: rgba(13, 110, 253, 0.5); 
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
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
        box-shadow: 0 0 0 5px rgba(13, 110, 253, 0.2);
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
        <h1 class="mt-4 mb-3 text-primary display-5 fw-bold">
            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-plus-circle me-2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="16"></line>
                <line x1="8" y1="12" x2="16" y2="12"></line>
            </svg>
            Tambah Produk Baru
        </h1>
        <p class="lead text-secondary">Isi formulir di bawah ini untuk menambahkan produk baru ke sistem inventaris. **(Menggunakan Varian Ukuran)**</p>
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
            <h4 class="alert-heading">⚠️ Terjadi Kesalahan!</h4>
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
            <form action="<?php echo $router_path; ?>?page=products/create" method="POST" enctype="multipart/form-data" class="row g-4">
                
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
                                <?php echo ($category_id == $cat['id']) ? 'selected' : ''; ?>>
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
                    <div class="card border-primary border-2">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-ruler-combined me-2"></i> Varian Ukuran & Stok <span class="text-warning">*</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Masukkan setiap ukuran yang tersedia dan jumlah stok untuk ukuran tersebut (misal: 39, 40, 41, dst).</p>
                            
                            <div id="variantsContainer">
                                <?php 
                                // Loop untuk repopulasi varian jika POST gagal
                                foreach ($variant_sizes as $index => $variant):
                                ?>
                                <div class="row variant-row g-3">
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
                                        <?php if ($index > 0): // Jangan tampilkan tombol hapus untuk baris pertama ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger mt-3 remove-variant-btn">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <button type="button" id="addVariantBtn" class="btn btn-sm btn-outline-primary mt-3">
                                <i class="fas fa-plus me-1"></i> Tambah Varian Ukuran
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <label for="imageInput" class="form-label fw-bold">Gambar Produk</label>
                    <input type="file" class="form-control" id="imageInput" name="image" accept="image/*">
                    <div class="form-text">Maksimal ukuran file: 5MB. Format: JPG, JPEG, PNG, GIF.</div>
                </div>
                
                <div class="col-12">
                    <label class="form-label fw-bold">Preview Gambar:</label><br>
                    <img id="imagePreview" src="https://via.placeholder.com/150x150/F8F9FA/6C757D?text=Preview+Gambar" 
                                 style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #dee2e6; border-radius: var(--border-radius); display: block;">
                </div>
                
                <div class="col-12 border-top pt-4 mt-4">
                    <button type="submit" class="btn btn-success btn-lg me-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-save me-1">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        Simpan Produk
                    </button>
                    <button type="reset" class="btn btn-outline-secondary btn-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-rotate-ccw me-1">
                            <polyline points="1 4 1 10 7 10"></polyline>
                            <path d="M3.5 15a9 9 0 1 0-.7-7.7L1 10"></path>
                        </svg>
                        Reset Form
                    </button>
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
             // Placeholder jika tidak ada file
             preview.src = "https://via.placeholder.com/150x150/F8F9FA/6C757D?text=Preview+Gambar";
        }
    });

    /**
     * 🌟 Logika Form Dinamis untuk Varian Ukuran 🌟
     */
    document.addEventListener('DOMContentLoaded', function() {
        const variantsContainer = document.getElementById('variantsContainer');
        const addVariantBtn = document.getElementById('addVariantBtn');

        // Fungsi untuk membuat baris varian baru
        function createVariantRow() {
            const newRow = document.createElement('div');
            newRow.className = 'row variant-row g-3';
            newRow.innerHTML = `
                <div class="col-5">
                    <label class="form-label small text-muted">Ukuran</label>
                    <input type="text" class="form-control" name="variant_size[]" placeholder="Contoh: 40" required>
                </div>
                <div class="col-5">
                    <label class="form-label small text-muted">Stok Ukuran Ini</label>
                    <input type="number" min="0" class="form-control" name="variant_stock[]" value="0" placeholder="Jumlah stok" required>
                </div>
                <div class="col-2 text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger mt-3 remove-variant-btn">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            variantsContainer.appendChild(newRow);
            
            // Tambahkan event listener untuk tombol hapus pada baris baru
            newRow.querySelector('.remove-variant-btn').addEventListener('click', function() {
                newRow.remove();
            });
        }

        // Event listener untuk tombol 'Tambah Varian'
        addVariantBtn.addEventListener('click', createVariantRow);

        // Event listener untuk tombol hapus yang sudah ada (saat repopulasi form)
        variantsContainer.querySelectorAll('.remove-variant-btn').forEach(button => {
            button.addEventListener('click', function() {
                this.closest('.variant-row').remove();
            });
        });
        
        // Hapus tombol hapus pada baris pertama jika hanya ada 1 baris saat DOMContentLoaded
        if (variantsContainer.children.length === 1) {
            const firstRemoveBtn = variantsContainer.querySelector('.remove-variant-btn');
            if(firstRemoveBtn) {
                 firstRemoveBtn.remove();
            }
        }
    });
</script>