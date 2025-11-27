<?php
/**
 * admin/user_management/edit.php
 * Halaman edit pelanggan (UPDATE), sekarang mendukung update foto profil.
 * Di-include oleh admin/dashboard.php.
 */

$db_koneksi = $GLOBALS['koneksi'] ?? null;
$error = [];
$user_id = (int)($_GET['id'] ?? 0);
$data = ['id' => 0, 'name' => '', 'email' => '', 'profile_image_path' => null];

// Tentukan folder upload profil (Relatif ke dashboard.php)
$profile_upload_dir = '../uploads/user_profiles/'; 

// ------------------------------------------
// 1. LOGIKA UPDATE (POST)
// ------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id > 0) {
    // Ambil data POST
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $new_password = $_POST['password'];
    $delete_photo = (isset($_POST['delete_photo']) && $_POST['delete_photo'] == 1);
    
    // Simpan data POST ke $data jika ada error
    $data = ['id' => $user_id, 'name' => $name, 'email' => $email];

    // Cek foto lama sebelum update (diperlukan untuk hapus file lama)
    $old_image_path_on_server = null;
    $stmt_old = $db_koneksi->prepare("SELECT profile_image_path FROM users WHERE id = ?");
    $stmt_old->bind_param("i", $user_id);
    $stmt_old->execute();
    $result_old = $stmt_old->get_result();
    if ($row_old = $result_old->fetch_assoc()) {
        $old_image_path_on_server = $row_old['profile_image_path'];
    }
    $stmt_old->close();

    // Validasi
    if (empty($name) || empty($email)) {
        $error[] = "Nama dan Email wajib diisi.";
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $error[] = "Password baru minimal 6 karakter.";
    }

    // Tentukan path gambar yang baru: default = path lama
    $new_image_path_to_db = $old_image_path_on_server;

    // --- LOGIKA UPLOAD FOTO BARU ---
    $is_image_uploaded = (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK);

    if ($is_image_uploaded) {
        $file_info = $_FILES['profile_image'];
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($file_info['type'], $allowed_types) || $file_info['size'] > $max_size) {
            $error[] = "Foto profil gagal diunggah: File harus JPG/PNG dan maksimal 2MB.";
        } else {
            $extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
            $unique_filename = uniqid('user_') . '.' . $extension;
            $target_file = $profile_upload_dir . $unique_filename;

            if (move_uploaded_file($file_info['tmp_name'], $target_file)) {
                $new_image_path_to_db = $unique_filename; 
            } else {
                $error[] = "Gagal memindahkan file upload. Periksa izin folder (uploads/user_profiles/).";
            }
        }
    } elseif ($delete_photo) {
        // Jika checkbox hapus dicentang DAN tidak ada upload baru
        $new_image_path_to_db = null;
    }

    if (empty($error)) {
        // --- BUAT QUERY UPDATE DINAMIS ---
        
        $set_parts = ["name = ?", "email = ?", "profile_image_path = ?"];
        $params = [$name, $email, $new_image_path_to_db];
        $types = "sss";
        
        if (!empty($new_password)) {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $set_parts[] = "password_hash = ?";
            $params[] = $password_hash;
            $types .= "s";
        }
        
        $sql = "UPDATE users SET " . implode(", ", $set_parts) . " WHERE id = ?";
        $params[] = $user_id;
        $types .= "i"; 

        $stmt = $db_koneksi->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            // Hapus file lama di server setelah sukses update database (kecuali file baru sama dengan lama)
            if (($is_image_uploaded || $delete_photo) && !empty($old_image_path_on_server)) {
                // Jangan hapus jika ada upload baru dan file lama tidak sama
                if ($old_image_path_on_server !== $new_image_path_to_db) {
                     $old_file = $profile_upload_dir . $old_image_path_on_server;
                     if (file_exists($old_file)) {
                         unlink($old_file);
                     }
                }
            }

            $_SESSION['crud_success_toast'] = 'Pelanggan **' . htmlspecialchars($name) . '** berhasil diperbarui.';
            header('Location: ' . $router_path . '?page=user_management/index');
            exit;
        } else {
             $error[] = 'Gagal memperbarui: Email mungkin sudah terdaftar. Error: ' . $stmt->error;
             // Jika gagal, hapus file baru yang mungkin sempat terupload
             if ($is_image_uploaded && file_exists($profile_upload_dir . $new_image_path_to_db)) {
                 unlink($profile_upload_dir . $new_image_path_to_db);
             }
        }
        $stmt->close();
    }
}

// ------------------------------------------
// 2. LOGIKA READ (GET - Mengambil Data Lama)
// ------------------------------------------

// Hanya ambil data lama jika bukan POST yang gagal
if (empty($error) && $user_id > 0) {
    // PERBAIKAN: Tambahkan profile_image_path di query GET
    $stmt = $db_koneksi->prepare("SELECT id, name, email, profile_image_path FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $data = $result->fetch_assoc();
    } else {
        $_SESSION['crud_success_toast'] = '<div class="alert alert-warning">Pelanggan tidak ditemukan.</div>';
        header('Location: ' . $router_path . '?page=user_management/index');
        exit;
    }
    $stmt->close();
} elseif ($user_id == 0) {
    // Jika tidak ada ID di URL
    $_SESSION['crud_success_toast'] = '<div class="alert alert-warning">ID pelanggan tidak valid.</div>';
    header('Location: ' . $router_path . '?page=user_management/index');
    exit;
}

// Tentukan URL gambar profil untuk tampilan
$current_profile_img_path = $data['profile_image_path'];
$profile_img_url = !empty($current_profile_img_path) 
    ? '../uploads/user_profiles/' . htmlspecialchars($current_profile_img_path)
    : 'https://via.placeholder.com/100/EEEEEE/AAAAAA?text=No+Photo';
?>

<div class="container-fluid">
    <h1 class="mt-4">Edit Pelanggan: <?php echo htmlspecialchars($data['name']); ?></h1>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <ul><?php foreach ($error as $err): ?><li><?php echo $err; ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-warning text-dark">
            <i class="fas fa-user-edit me-1"></i>
            Form Edit Pelanggan
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo $router_path; ?>?page=user_management/edit&id=<?php echo $data['id']; ?>" enctype="multipart/form-data">
                
                <div class="mb-3">
                    <label for="name" class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($data['name']); ?>">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($data['email']); ?>">
                </div>
                
                <hr>
                
                <div class="mb-3 p-3 border rounded">
                    <h5 class="mb-3"><i class="fas fa-camera me-1"></i> Foto Profil Saat Ini</h5>
                    
                    <div class="d-flex align-items-center mb-3">
                        <img src="<?php echo $profile_img_url; ?>" alt="Foto Profil" class="img-thumbnail rounded-circle me-3" style="width: 100px; height: 100px; object-fit: cover;">
                        
                        <?php if (!empty($current_profile_img_path)): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="delete_photo" name="delete_photo">
                                <label class="form-check-label text-danger" for="delete_photo">
                                    Hapus Foto Profil Ini (Menjadi Default)
                                </label>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">Menggunakan foto default.</span>
                        <?php endif; ?>
                    </div>

                    <label for="profile_image" class="form-label">Ganti Foto Baru (Opsional)</label>
                    <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/jpeg, image/png">
                    <small class="text-muted">Maksimal 2MB. File baru akan menimpa yang lama dan mengabaikan opsi 'Hapus Foto'.</small>
                </div>
                <hr>

                <div class="mb-3">
                    <label for="password" class="form-label">Password Baru</label>
                    <input type="password" class="form-control" id="password" name="password">
                    <small class="text-muted">Kosongkan jika tidak ingin mengubah password. Minimal 6 karakter jika diisi.</small>
                </div>
                
                <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Perbarui</button>
                <a href="<?php echo $router_path; ?>?page=user_management/index" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    </div>
</div>