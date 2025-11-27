<?php
/**
 * admin/user_management/create.php
 * Halaman tambah pelanggan baru.
 * Di-include oleh admin/dashboard.php.
 */

$db_koneksi = $GLOBALS['koneksi'] ?? null;
$error = [];
$name = $email = '';

// Tentukan folder upload profil
$profile_upload_dir = '../uploads/user_profiles/'; 
// Pastikan folder ada dan memiliki izin tulis (Relatif ke dashboard.php,
// karena file ini di-include dari dashboard.php, path-nya harus relatif ke root).

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $profile_image_path = null; // Default path kosong

    // Validasi Dasar
    if (empty($name)) { $error[] = "Nama wajib diisi."; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error[] = "Format email tidak valid."; }
    if (strlen($password) < 6) { $error[] = "Password minimal 6 karakter."; }

    // --- LOGIKA UPLOAD FOTO PROFIL (OPSIONAL) ---
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
        $file_info = $_FILES['profile_image'];
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 2 * 1024 * 1024; // Maks 2MB

        // 1. Validasi File
        if (!in_array($file_info['type'], $allowed_types) || $file_info['size'] > $max_size) {
            $error[] = "Foto profil gagal diunggah: File harus JPG/PNG dan maksimal 2MB.";
        } else {
            // 2. Proses Pindahkan File
            $extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
            $unique_filename = uniqid('user_') . '.' . $extension;
            $target_file = $profile_upload_dir . $unique_filename;

            if (move_uploaded_file($file_info['tmp_name'], $target_file)) {
                $profile_image_path = $unique_filename; // Simpan nama file ke database
            } else {
                $error[] = "Gagal memindahkan file upload. Periksa izin folder (uploads/user_profiles/).";
            }
        }
    }
    // --- AKHIR LOGIKA UPLOAD ---

    if (empty($error)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // 3. PERBAIKAN QUERY: Tambahkan kolom profile_image_path
        $sql = "INSERT INTO users (name, email, password_hash, profile_image_path) VALUES (?, ?, ?, ?)";
        $stmt = $db_koneksi->prepare($sql);
        // Tambahkan tipe data 's' untuk profile_image_path
        $stmt->bind_param("ssss", $name, $email, $password_hash, $profile_image_path);
        
        if ($stmt->execute()) {
            // Jika sukses, admin akan diarahkan kembali ke daftar pengguna
            $_SESSION['crud_success_toast'] = 'Pelanggan **' . htmlspecialchars($name) . '** berhasil ditambahkan.';
            // Gunakan $router_path dari dashboard.php
            header('Location: ' . $router_path . '?page=user_management/index'); 
            exit;
        } else {
            // Error, kemungkinan email sudah ada (UNIQUE constraint)
            $error[] = "Gagal: Email sudah terdaftar atau terjadi error database. (" . $db_koneksi->error . ")";
            
            // Hapus file yang baru saja di-upload jika query gagal
            if ($profile_image_path && file_exists($profile_upload_dir . $profile_image_path)) {
                unlink($profile_upload_dir . $profile_image_path);
            }
        }
        $stmt->close();
    }
}
?>

<div class="container-fluid">
    <h1 class="mt-4">Tambah Pelanggan Baru</h1>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <ul><?php foreach ($error as $err): ?><li><?php echo $err; ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-user-plus me-1"></i>
            Form Pendaftaran Pelanggan
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo $router_path; ?>?page=user_management/create" enctype="multipart/form-data">
                
                <div class="mb-3">
                    <label for="name" class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($name); ?>">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <small class="text-muted">Minimal 6 karakter.</small>
                </div>
                
                <div class="mb-3 p-3 border rounded">
                    <label for="profile_image" class="form-label"><i class="fas fa-camera me-1"></i> Foto Profil (Opsional)</label>
                    <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/jpeg, image/png">
                    <small class="text-muted">Maksimal 2MB. Format: JPG atau PNG.</small>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Pelanggan</button>
                <a href="<?php echo $router_path; ?>?page=user_management/index" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    </div>
</div>