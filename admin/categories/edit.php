<?php
// admin/categories/edit.php
/**
 * 🔥 PERBAIKAN: Menambahkan internal CSS untuk styling modern.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// PERBAIKAN PATH: Menggunakan koneksi dari global scope dashboard.php
$koneksi = $GLOBALS['koneksi'] ?? null; 
// Variabel router path harus tersedia dari scope dashboard.php
$router_path = $router_path ?? 'dashboard.php'; // Default ke dashboard.php

if (!$koneksi) {
    // Fatal error jika koneksi tidak tersedia
    echo '<div class="alert alert-danger">Kesalahan: Koneksi database tidak tersedia.</div>';
    exit;
}

$category_id = $_GET['id'] ?? null;
$category = null;
$error = '';

if (!$category_id || !is_numeric($category_id)) {
    // Gunakan Flash Message Array untuk error redirect
    $_SESSION['message'] = [
        'type' => 'danger',
        'text' => 'ID kategori tidak valid.'
    ];
    header('Location: ' . $router_path . '?page=categories/index');
    exit;
}

// 1. Ambil data kategori saat ini
$stmt = $koneksi->prepare("SELECT id, name FROM categories WHERE id = ?");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();
$category = $result->fetch_assoc();
$stmt->close();

if (!$category) {
    // Gunakan Flash Message Array untuk not found
    $_SESSION['message'] = [
        'type' => 'warning',
        'text' => 'Kategori tidak ditemukan.'
    ];
    // Gunakan ob_clean() jika session_start() dilakukan di dashboard.php
    if (ob_get_length()) { ob_clean(); } 
    header('Location: ' . $router_path . '?page=categories/index');
    exit;
}

// Set nilai input default (untuk ditampilkan di form)
$category_name_input = $_POST['name'] ?? $category['name'];


// --- LOGIKA UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = trim($_POST['name']);
    
    if (empty($new_name)) {
        $error = "Nama kategori wajib diisi.";
    } elseif ($new_name === $category['name']) {
        // Hanya set error, tidak perlu redirect
        $error = "Tidak ada perubahan yang dilakukan.";
    } else {
        // Cek duplikasi (jika nama baru berbeda)
        $stmt_check = $koneksi->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
        $stmt_check->bind_param("si", $new_name, $category_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $error = "Kategori '{$new_name}' sudah ada.";
        } else {
            // Update data
            $stmt_update = $koneksi->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt_update->bind_param("si", $new_name, $category_id);
            
            if ($stmt_update->execute()) {
                // Redirect ke index.php dengan status sukses dan flash message array
                $_SESSION['message'] = [
                    'type' => 'success',
                    'text' => "Kategori **" . htmlspecialchars($category['name']) . "** berhasil diperbarui menjadi **" . htmlspecialchars($new_name) . "**. ✅"
                ];
                // Gunakan ob_clean() jika session_start() dilakukan di dashboard.php
                if (ob_get_length()) { ob_clean(); } 
                header('Location: ' . $router_path . '?page=categories/index');
                exit;
            } else {
                $error = "Gagal memperbarui kategori: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
        $stmt_check->close();
    }
    // Update $category_name_input untuk form jika terjadi error
    $category_name_input = $new_name; 
}
?>

<style>
    /* -------------------------------------- */
    /* GENERAL STYLING */
    /* -------------------------------------- */
    .container-fluid {
        padding: 1.5rem;
    }

    /* Konsistensi Judul Halaman */
    .page-header-title {
        border-bottom: 2px solid #0dcaf0; /* Garis bawah biru info */
        padding-bottom: 10px;
    }

    /* Mengubah Card Container Utama */
    .bg-white.rounded.p-4.shadow-lg {
        background-color: #ffffff !important;
        border-radius: 0.5rem;
        padding: 2rem !important;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }

    /* -------------------------------------- */
    /* FORM STYLING */
    /* -------------------------------------- */
    .card-header.bg-info {
        font-size: 1.1rem;
        background-color: #0dcaf0 !important;
        border-radius: 0.5rem 0.5rem 0 0 !important;
    }
    
    .form-control-lg {
        height: calc(2.8rem + 2px);
        font-size: 1.1rem;
    }

    /* Styling tombol aksi */
    .btn {
        transition: all 0.2s ease;
    }

</style>

<div class="container-fluid pt-4 px-4">
    <div class="row g-4 justify-content-center">
        <div class="col-lg-7 col-md-9 col-sm-12">

            <div class="bg-white rounded p-4 shadow-lg">
                
                <div class="d-sm-flex align-items-center justify-content-between mb-4 page-header-title">
                    <h1 class="h3 mb-0 text-dark fw-bold">
                        <!-- Menggunakan Feather Icons -->
                        <i data-feather="edit-3" class="me-2 text-info" style="width: 30px; height: 30px;"></i>
                        Edit Kategori
                    </h1>
                    <a href="<?php echo $router_path; ?>?page=categories/index" class="btn btn-sm btn-outline-secondary shadow-sm text-nowrap">
                        <i data-feather="list" class="me-1" style="width: 18px; height: 18px;"></i>
                        Daftar Kategori
                    </a>
                </div>
                
                <hr class="mt-0 mb-4 d-none d-sm-block">

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i data-feather="alert-triangle" class="me-2" style="width: 18px; height: 18px;"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow">
                    <div class="card-header bg-info text-white fw-bold">
                        <i data-feather="hard-drive" class="me-1" style="width: 18px; height: 18px;"></i>
                        Mengedit Kategori: **<?php echo htmlspecialchars($category['name']); ?>** (ID: <?php echo $category['id']; ?>)
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?php echo $router_path; ?>?page=categories/edit&id=<?php echo $category_id; ?>">
                            <div class="mb-3">
                                <label for="category_name" class="form-label fw-bold">Nama Kategori Baru</label>
                                <input type="text" class="form-control form-control-lg" id="category_name" name="name" 
                                        placeholder="Masukkan nama kategori yang diperbarui"
                                        value="<?php echo htmlspecialchars($category_name_input); ?>" required>
                                <div id="nameHelp" class="form-text">Perubahan nama akan berlaku secara instan di semua produk terkait.</div>
                            </div>
                            
                            <div class="d-flex justify-content-end pt-3 border-top">
                                <a href="<?php echo $router_path; ?>?page=categories/index" class="btn btn-secondary me-2">
                                    <i data-feather="x-circle" class="me-1" style="width: 18px; height: 18px;"></i>
                                    Batal
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i data-feather="save" class="me-1" style="width: 18px; height: 18px;"></i>
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<!-- Script untuk inisialisasi Feather Icons -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
</script>