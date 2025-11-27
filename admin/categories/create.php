<?php
/**
 * admin/categories/create.php
 * Form dan Logika untuk Menambah Kategori Baru (CREATE).
 * Penyesuaian: Mengganti ikon Font Awesome dengan Feather Icons.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// PERBAIKAN PATH: Asumsi koneksi.php ada di root atau di-load di dashboard.php
// Kita asumsikan $koneksi sudah ada dari $GLOBALS['koneksi']
$koneksi = $GLOBALS['koneksi'] ?? null; 

// Variabel router path harus tersedia dari scope dashboard.php
$router_path = $router_path ?? '../dashboard.php'; 

if (!$koneksi) {
    echo '<div class="alert alert-danger">Kesalahan: Koneksi database tidak tersedia.</div>';
    exit;
}

$message = [];
$category_name = '';

// --- LOGIKA TAMBAH KATEGORI BARU (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $category_name = trim($_POST['name']);
    
    if (empty($category_name)) {
        $message['error'] = "Nama kategori wajib diisi.";
    } else {
        // Cek duplikasi
        $stmt_check = $koneksi->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt_check->bind_param("s", $category_name);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $message['error'] = "Kategori '{$category_name}' sudah ada.";
        } else {
            // Insert data
            $stmt_insert = $koneksi->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt_insert->bind_param("s", $category_name);
            
            if ($stmt_insert->execute()) {
                // Redirect ke index.php melalui router dengan flash message array
                $_SESSION['message'] = [
                    'type' => 'success',
                    'text' => "Kategori **" . htmlspecialchars($category_name) . "** berhasil ditambahkan. ✅"
                ];
                header('Location: ' . $router_path . '?page=categories/index');
                exit;
            } else {
                $message['error'] = "Gagal menambahkan kategori: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
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
        border-bottom: 2px solid #0d6efd; /* Garis bawah biru primary */
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
    .card-header.bg-primary {
        font-size: 1.1rem;
        background-color: #0d6efd !important;
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
                        <i data-feather="tag" class="me-2 text-primary" style="width: 30px; height: 30px;"></i>
                        Tambah Kategori Baru
                    </h1>
                    <a href="<?php echo $router_path; ?>?page=categories/index" class="btn btn-sm btn-outline-secondary shadow-sm text-nowrap">
                        <i data-feather="list" class="me-1" style="width: 18px; height: 18px;"></i>
                        Daftar Kategori
                    </a>
                </div>
                
                <hr class="mt-0 mb-4 d-none d-sm-block">

                <?php if (isset($message['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i data-feather="alert-triangle" class="me-2" style="width: 18px; height: 18px;"></i>
                        <?php echo $message['error']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow">
                    <div class="card-header bg-primary text-white fw-bold">
                        <i data-feather="file-text" class="me-1" style="width: 18px; height: 18px;"></i>
                        Form Penambahan Kategori
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?php echo $router_path; ?>?page=categories/create">
                            <div class="mb-3">
                                <label for="category_name" class="form-label fw-bold">Nama Kategori</label>
                                <input type="text" class="form-control form-control-lg" id="category_name" name="name" 
                                        placeholder="Contoh: Sepatu Lari, Sneaker Casual..."
                                        value="<?php echo htmlspecialchars($category_name); ?>" required>
                                <div id="nameHelp" class="form-text">Nama harus unik dan tidak boleh kosong.</div>
                            </div>
                            
                            <div class="d-flex justify-content-end pt-3 border-top">
                                <a href="<?php echo $router_path; ?>?page=categories/index" class="btn btn-secondary me-2">
                                    <i data-feather="x-circle" class="me-1" style="width: 18px; height: 18px;"></i>
                                    Batal
                                </a>
                                <button type="submit" name="add_category" class="btn btn-success">
                                    <i data-feather="plus-square" class="me-1" style="width: 18px; height: 18px;"></i>
                                    Tambah Kategori
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    });
</script>