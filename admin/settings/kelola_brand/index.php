<?php
// admin/settings/kelola_brand/index.php - CRUD Brand Website & Logo

// Pastikan Anda memiliki koneksi dan sesi yang diinisialisasi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../koneksi.php'; 
global $koneksi; 

// --- Path untuk Logo ---
$logo_upload_root_path = realpath(__DIR__ . '/../../../') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'brand' . DIRECTORY_SEPARATOR; 
$logo_upload_dir_display = '../../../uploads/brand/'; 

// Fungsi helper untuk SweetAlert
function set_swal($icon, $title, $text = '') {
    $_SESSION['swal_icon'] = $icon;
    $_SESSION['swal_title'] = $title;
    $_SESSION['swal_text'] = $text;
}

// 1. Ambil data pengaturan saat ini (CRUD - Read)
$settings = [];
// Query TANPA whatsapp_number
$sql_read = "SELECT id, website_name, tagline, logo_image_path FROM website_settings WHERE id = 1";
$result = $koneksi->query($sql_read);

if ($result && $result->num_rows > 0) {
    $settings = $result->fetch_assoc();
    $current_logo_path = $settings['logo_image_path'];
} else {
    // Logika fallback jika ada masalah
    $settings = ['id' => 1, 'website_name' => 'TokoOnlineku', 'tagline' => 'Gaya Setiap Aksi 🛍️', 'logo_image_path' => NULL];
    $current_logo_path = NULL;
}

// 2. Logika Update (CRUD - Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_brand'])) {
    
    $new_name = trim($_POST['website_name']);
    $new_tagline = trim($_POST['tagline']);
    // whatsapp_number dihapus
    $new_logo_name = $current_logo_path; 

    if (empty($new_name)) {
        set_swal('error', "Gagal!", "Nama website tidak boleh kosong!");
        goto end_post;
    } 
    
    // --- Logika Hapus Logo ---
    if (isset($_POST['delete_logo']) && $_POST['delete_logo'] == '1') {
        if (!empty($current_logo_path)) {
            $old_file = $logo_upload_root_path . $current_logo_path;
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
        $new_logo_name = null; 
    }
    
    // --- Logika Upload Logo ---
    if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['logo_image']['tmp_name'];
        $file_name = basename($_FILES['logo_image']['name']);
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
        $max_file_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file_extension, $allowed_extensions)) {
            set_swal('error', "Gagal Upload!", "Ekstensi file logo tidak didukung. Gunakan JPG, PNG, SVG, atau WEBP.");
            goto end_post;
        }
        if ($_FILES['logo_image']['size'] > $max_file_size) {
             set_swal('error', "Gagal Upload!", "Ukuran file logo terlalu besar. Maksimal 2MB.");
            goto end_post;
        }
        
        $unique_file_name = 'logo_' . time() . '.' . $file_extension;
        $upload_path = $logo_upload_root_path . $unique_file_name;

        if (!is_dir($logo_upload_root_path)) {
            mkdir($logo_upload_root_path, 0777, true);
        }

        if (move_uploaded_file($file_tmp, $upload_path)) {
            if (!empty($current_logo_path) && (!isset($_POST['delete_logo']) || $_POST['delete_logo'] != '1')) {
                $old_file = $logo_upload_root_path . $current_logo_path;
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }
            $new_logo_name = $unique_file_name;
        } else {
            set_swal('error', "Gagal Upload!", "Gagal mengunggah file logo. Periksa izin folder 'uploads/brand'.");
            goto end_post;
        }
    }
    
    // --- SQL Update TANPA whatsapp_number ---
    $sql_update = "UPDATE website_settings SET website_name = ?, tagline = ?, logo_image_path = ? WHERE id = 1";
    $stmt = $koneksi->prepare($sql_update);
    
    if ($stmt) {
        // Tipe data: string, string, string
        $stmt->bind_param("sss", $new_name, $new_tagline, $new_logo_name);
        if ($stmt->execute()) {
            set_swal('success', "Berhasil!", "Pengaturan merek berhasil diperbarui.");
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            set_swal('error', "Gagal Update!", "Terjadi kesalahan database: " . $stmt->error);
        }
        $stmt->close();
    } else {
        set_swal('error', "Gagal Update!", "Gagal menyiapkan query: " . $koneksi->error);
    }
}
end_post: 

// 3. Persiapan Tampilan
$logo_file_exists = !empty($settings['logo_image_path']) && file_exists($logo_upload_root_path . $settings['logo_image_path']);

$display_logo_url = $logo_file_exists
                    ? $logo_upload_dir_display . htmlspecialchars($settings['logo_image_path']) 
                    : 'https://via.placeholder.com/100x40?text=Logo+Teks';

?>

<div class="container-fluid">
    <h1 class="mt-4 mb-4"><i data-feather="gift" class="me-2"></i> Kelola Brand Website & Logo</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item">Pengaturan Toko</li>
        <li class="breadcrumb-item active">Kelola Brand & Logo</li>
    </ol>

    <div class="card shadow-lg p-4 mb-5">
        <h4 class="card-title mb-4 border-bottom pb-2">Form Pengaturan Brand Website</h4>
        <form action="" method="POST" enctype="multipart/form-data">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="website_name" class="form-label fw-bold">Nama Website</label>
                        <input type="text" class="form-control" id="website_name" name="website_name" 
                               value="<?php echo htmlspecialchars($settings['website_name'] ?? ''); ?>" required>
                        <small class="text-muted">Nama yang muncul di judul halaman (&lt;title&gt;) dan Navbar.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="tagline" class="form-label fw-bold">Tagline Website</label>
                        <input type="text" class="form-control" id="tagline" name="tagline" 
                               value="<?php echo htmlspecialchars($settings['tagline'] ?? ''); ?>">
                        <small class="text-muted">Slogan pendek, muncul setelah nama website.</small>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-md-12">
                    <label class="form-label fw-bold">Logo Saat Ini</label>
                    <div class="mb-3 p-3 border rounded bg-light d-inline-block">
                        <img src="<?php echo $display_logo_url; ?>" alt="Logo Saat Ini" style="max-height: 50px;">
                        <span class="text-muted ms-3 small">
                            (File: <?php echo htmlspecialchars($settings['logo_image_path'] ?? 'Tidak Ada'); ?>)
                        </span>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="logo_image" class="form-label">Upload Logo Baru (Opsional)</label>
                <input type="file" class="form-control" id="logo_image" name="logo_image" accept="image/jpeg,image/png,image/svg+xml,image/webp">
                <small class="text-muted">Maksimal 2MB. Format: JPG, PNG, SVG, atau WEBP disarankan.</small>
            </div>
            
            <?php if (!empty($settings['logo_image_path'])): ?>
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" value="1" id="delete_logo" name="delete_logo">
                <label class="form-check-label text-danger" for="delete_logo">
                    <i class="fas fa-trash me-1"></i> Hapus Logo Saat Ini
                </label>
            </div>
            <?php endif; ?>
            
            <button type="submit" name="update_brand" class="btn btn-primary btn-lg mt-3"><i class="fas fa-save me-2"></i> Simpan Pengaturan Brand</button>
        </form>
    </div>
</div>

<?php 
// SweetAlert Display Logic
if (isset($_SESSION['swal_title'])): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: '<?php echo $_SESSION['swal_icon']; ?>',
            title: '<?php echo htmlspecialchars($_SESSION['swal_title']); ?>',
            text: '<?php echo htmlspecialchars($_SESSION['swal_text']); ?>',
            showConfirmButton: false,
            timer: 3000
        });
    });
</script>
<?php 
    unset($_SESSION['swal_icon']);
    unset($_SESSION['swal_title']);
    unset($_SESSION['swal_text']);
endif; 
?>