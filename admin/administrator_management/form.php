<?php
/**
 * admin/administrator_management/form.php
 * Halaman untuk menambah atau mengedit data administrator.
 * PERBAIKAN FINAL: Menargetkan tabel 'admins' dan menggunakan kolom 'username'.
 */

// Pastikan koneksi tersedia
if (!isset($GLOBALS['koneksi'])) {
    die("Koneksi database tidak tersedia.");
}
$koneksi = $GLOBALS['koneksi'];

// Inisialisasi variabel
$is_edit = false;
$admin_id = 0;
$admin_data = [
    'username' => '', 
    'email' => '', 
    'full_name' => '', 
    'role' => 'admin' // Nilai default
];
$error_message = '';
$page_title = 'Tambah Admin Baru';
$form_action = 'Tambah Admin';

// ----------------------------------------------------
// 1. LOGIKA PENGAMBILAN DATA (EDIT MODE)
// ----------------------------------------------------
if (isset($_GET['id'])) {
    $admin_id = (int)$_GET['id'];
    
    if ($admin_id > 0) {
        // KRUSIAL: SELECT dari tabel 'admins'
        $stmt = $koneksi->prepare("SELECT username, email, full_name, role FROM admins WHERE id = ?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $admin_data = $result->fetch_assoc();
            $is_edit = true;
            $page_title = 'Edit Data Administrator';
            $form_action = 'Simpan Perubahan';
        } else {
            $error_message = "Admin dengan ID tersebut tidak ditemukan.";
        }
        $stmt->close();
    }
}

// ----------------------------------------------------
// 2. LOGIKA SUBMIT FORM (ADD/EDIT)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitasi dan Validasi Input
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $role = trim($_POST['role']);
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $timestamp = date('Y-m-d H:i:s');
    
    // Perbarui data admin untuk ditampilkan kembali jika terjadi error validasi
    $admin_data = [
        'username' => $username, 
        'email' => $email, 
        'full_name' => $full_name, 
        'role' => $role
    ];
    
    // Validasi Dasar
    if (empty($username) || empty($email) || empty($full_name) || empty($role)) {
        $error_message = "Semua field bertanda bintang (*) wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid.";
    } elseif (!$is_edit && (empty($password) || empty($password_confirm))) {
        $error_message = "Password wajib diisi untuk admin baru.";
    } elseif (!empty($password) && $password !== $password_confirm) {
        $error_message = "Password baru dan konfirmasi password tidak cocok.";
    }

    // C. Cek Duplikasi Username/Email
    if (empty($error_message)) {
        // KRUSIAL: Cek duplikasi di tabel 'admins'
        $check_sql = "SELECT id FROM admins WHERE (username = ? OR email = ?)";
        if ($is_edit) {
            $check_sql .= " AND id != ?";
        }
        $stmt_check = $koneksi->prepare($check_sql);
        
        if ($is_edit) {
            $stmt_check->bind_param("ssi", $username, $email, $admin_id);
        } else {
            $stmt_check->bind_param("ss", $username, $email);
        }
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = "Username atau Email sudah terdaftar pada admin lain.";
        }
        $stmt_check->close();
    }
    
    // ----------------------------------------------------
    // D. PROSES SIMPAN KE DATABASE
    // ----------------------------------------------------
    if (empty($error_message)) {
        if ($is_edit) {
            // Logika UPDATE - KRUSIAL: UPDATE tabel 'admins'
            $sql = "UPDATE admins SET username = ?, email = ?, full_name = ?, role = ?, updated_at = ?";
            $params = [$username, $email, $full_name, $role, $timestamp];
            $types = "sssss";

            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
                $sql .= ", password = ?";
                $params[] = $hashed_password;
                $types .= "s";
            }

            $sql .= " WHERE id = ?";
            $params[] = $admin_id;
            $types .= "i";
            
            $stmt = $koneksi->prepare($sql);
            
            // Perbaikan untuk bind_param dengan array dinamis
            $ref_params = [];
            foreach ($params as $key => $value) {
                $ref_params[$key] = &$params[$key];
            }
            array_unshift($ref_params, $types);
            call_user_func_array([$stmt, 'bind_param'], $ref_params);

            if ($stmt->execute()) {
                // Set flash message untuk sukses update
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'message' => "Data Administrator **" . htmlspecialchars($full_name) . "** berhasil diperbarui!"
                ];
                header("Location: dashboard.php?page=administrator_management/index");
                exit();
            } else {
                $error_message = "Gagal memperbarui data admin: " . $stmt->error;
            }
            $stmt->close();

        } else {
            // Logika INSERT (ADD) - KRUSIAL: INSERT ke tabel 'admins'
            $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

            $sql = "INSERT INTO admins (username, email, password, full_name, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $koneksi->prepare($sql);
            $stmt->bind_param("sssssss", $username, $email, $hashed_password, $full_name, $role, $timestamp, $timestamp);

            if ($stmt->execute()) {
                // Set flash message untuk sukses tambah
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'message' => "Administrator baru **" . htmlspecialchars($full_name) . "** berhasil ditambahkan!"
                ];
                header("Location: dashboard.php?page=administrator_management/index");
                exit();
            } else {
                $error_message = "Gagal menambahkan admin baru: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>

<h1 class="mb-4 display-6 fw-bold text-primary">
    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-user-plus me-2">
        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="8.5" cy="7" r="4"></circle>
        <line x1="20" y1="8" x2="20" y2="14"></line>
        <line x1="23" y1="11" x2="17" y2="11"></line>
    </svg>
    <?php echo $page_title; ?>
</h1>
<p class="text-muted">Formulir ini digunakan untuk <?php echo $is_edit ? 'mengubah data' : 'menambahkan pengguna baru'; ?>.</p>

<div class="card shadow-sm mb-5">
    <div class="card-body">

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            
            <div class="row mb-3">
                <label for="username" class="col-sm-3 col-form-label fw-bold">Username <span class="text-danger">*</span></label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($admin_data['username']); ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <label for="email" class="col-sm-3 col-form-label fw-bold">Email <span class="text-danger">*</span></label>
                <div class="col-sm-9">
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin_data['email']); ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <label for="full_name" class="col-sm-3 col-form-label fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($admin_data['full_name']); ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <label for="role" class="col-sm-3 col-form-label fw-bold">Role <span class="text-danger">*</span></label>
                <div class="col-sm-9">
                    <select class="form-select" id="role" name="role" required>
                        <option value="admin" <?php echo $admin_data['role'] == 'admin' ? 'selected' : ''; ?>>admin</option>
                        <option value="administrator" <?php echo $admin_data['role'] == 'administrator' ? 'selected' : ''; ?>>administrator</option>
                    </select>
                </div>
            </div>
            
            <hr class="my-4">
            <p class="text-info small mb-3">
                <?php echo $is_edit ? 'Isi kedua kolom di bawah ini hanya jika Anda ingin mengganti password.' : 'Password wajib diisi untuk admin baru.'; ?>
            </p>

            <div class="row mb-3">
                <label for="password" class="col-sm-3 col-form-label fw-bold">Password Baru</label>
                <div class="col-sm-9">
                    <input type="password" class="form-control" id="password" name="password" <?php echo !$is_edit ? 'required' : ''; ?>>
                </div>
            </div>

            <div class="row mb-3">
                <label for="password_confirm" class="col-sm-3 col-form-label fw-bold">Konfirmasi Password</label>
                <div class="col-sm-9">
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" <?php echo !$is_edit ? 'required' : ''; ?>>
                </div>
            </div>

            <div class="text-end mt-4">
                <a href="dashboard.php?page=administrator_management/index" class="btn btn-secondary me-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-corner-down-left me-1">
                        <polyline points="9 10 4 15 9 20"></polyline>
                        <path d="M20 4v7a4 4 0 0 1-4 4H4"></path>
                    </svg>
                    Kembali ke Daftar
                </a>
                
                <button type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-save me-1">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    <?php echo $form_action; ?>
                </button>
            </div>
        </form>
    </div>
</div>