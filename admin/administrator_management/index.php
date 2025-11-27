<?php
/**
 * admin/administrator_management/index.php
 * Halaman utama untuk mengelola administrator (Admin Management).
 * 🔥 PERBAIKAN: Mengganti ikon SVG statis dengan tag Feather Icons JS untuk konsistensi.
 * 🔥 PERBAIKAN: Mengganti konfirmasi delete() dengan Bootstrap Modal.
 * 🆕 BARU: Menambahkan fungsionalitas Pencarian (Username/Nama Lengkap).
 * 🗑️ DIHAPUS: Filter Role.
 */

// Pastikan koneksi tersedia (dari file include sebelumnya)
if (!isset($GLOBALS['koneksi'])) {
    die("Koneksi database tidak tersedia.");
}
$koneksi = $GLOBALS['koneksi'];

// Variabel router path harus tersedia dari scope dashboard.php
$router_path = $router_path ?? 'dashboard.php'; 

// Pastikan sesi dimulai dan user_id tersedia
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$current_user_id = $_SESSION['user_id'] ?? 0;
$flash_message = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']); // Hapus flash message setelah diambil

// --- LOGIKA PENCARIAN ---
$search_query = $_GET['search'] ?? '';


// ----------------------------------------------------
// LOGIKA DELETE ADMIN (Dipertahankan di atas untuk segera redirect)
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_to_delete = (int)$_GET['id'];
    
    // Keamanan: Ambil ID admin yang sedang login
    if ($id_to_delete == $current_user_id) {
        // Set flash message untuk error
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => "Anda tidak dapat menghapus akun administrator yang sedang Anda gunakan saat ini."
        ];
    } else {
        
        // KRUSIAL: Menghapus dari tabel 'admins'
        $stmt = $koneksi->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->bind_param("i", $id_to_delete);
        
        if ($stmt->execute()) {
            // Set flash message untuk sukses delete
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Administrator berhasil dihapus.'
            ];
        } else {
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => "Gagal menghapus admin: " . $stmt->error
            ];
        }
        $stmt->close();
    }
    // Redirect ke halaman index tanpa parameter action/id
    header("Location: {$router_path}?page=administrator_management/index");
    exit();
}


// ----------------------------------------------------
// PENGAMBILAN DATA SEMUA ADMIN (DENGAN PENCARIAN)
// ----------------------------------------------------
$where_conditions = [];
    
// Logika Pencarian (Username atau Nama Lengkap)
if (!empty($search_query)) {
    $search_term = $koneksi->real_escape_string($search_query);
    $where_conditions[] = "(username LIKE '%$search_term%' OR full_name LIKE '%$search_term%')";
}

$where_clause = !empty($where_conditions) ? ' WHERE ' . implode(' AND ', $where_conditions) : '';

// KRUSIAL: Menargetkan tabel 'admins' dan menggunakan kolom 'username'
$sql = "SELECT id, username, email, full_name, role, created_at, updated_at 
        FROM admins 
        {$where_clause}
        ORDER BY id DESC";
        
$result = $koneksi->query($sql);

$admins = [];
$error_message = '';
if ($result) {
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $admins[] = $row;
        }
    }
} else {
    // Pesan error diperbarui
    $error_message = "Gagal mengambil data administrator. Pastikan tabel 'admins' tersedia. Error SQL: " . $koneksi->error;
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <h1 class="h3 fw-bold text-dark mb-0">
            <i data-feather="users" class="me-2" style="width: 24px; height: 24px;"></i>
            Kelola Administrator
        </h1>
        <a href="<?php echo $router_path; ?>?page=administrator_management/form" class="btn btn-primary shadow-sm text-nowrap">
            <i data-feather="plus" style="width: 18px; height: 18px;"></i>
            Tambah Admin Baru
        </a>
    </div>

    <?php 
    // ----------------------------------------------------
    // TAMPILKAN FLASH MESSAGE (Jika menggunakan sesi)
    // ----------------------------------------------------
    if ($flash_message) {
        $alert_class = 'alert-' . ($flash_message['type'] === 'danger' ? 'danger' : 'success');
        $message = $flash_message['message'];
        echo "<div class='alert {$alert_class} alert-dismissible fade show' role='alert'>
                  {$message}
                  <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
    }
    // ----------------------------------------------------
    // TAMPILKAN ERROR MESSAGE DARI LOGIKA DI HALAMAN INI
    // ----------------------------------------------------
    if (isset($error_message) && !empty($error_message)) {
          echo "<div class='alert alert-danger'>{$error_message}</div>";
    }
    ?>

    <div class="card mb-4 p-3 shadow-sm">
        <form method="GET" action="<?php echo $router_path; ?>" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="administrator_management/index">
            
            <div class="col-md-5 col-lg-4">
                <label for="search" class="form-label small fw-bold mb-0">Cari Admin (Username/Nama Lengkap):</label>
                <div class="input-group">
                    <span class="input-group-text"><i data-feather="search" style="width: 16px; height: 16px;"></i></span>
                    <input type="text" class="form-control form-control-sm" id="search" name="search" placeholder="Cari username atau nama lengkap..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </div>
            
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">Cari</button>
            </div>
            
            <div class="col-auto">
                <a href="<?php echo $router_path; ?>?page=administrator_management/index" class="btn btn-sm btn-secondary">Reset</a>
            </div>
        </form>
    </div>
    <div class="card shadow-lg mb-5">
        <div class="card-header bg-dark text-white fw-bold py-3 d-flex justify-content-between align-items-center">
            Daftar Pengguna Administrator
            <span class="badge bg-primary rounded-pill">Total: <?php echo count($admins); ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($admins)): ?>
                <div class="alert alert-info text-center m-3">Belum ada data administrator yang terdaftar.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="small fw-bold">ID</th>
                            <th class="small fw-bold">Username</th>
                            <th class="small fw-bold d-none d-sm-table-cell">Email</th>
                            <th class="small fw-bold d-none d-md-table-cell">Nama Lengkap</th>
                            <th class="small fw-bold">Role</th>
                            <th class="small fw-bold d-none d-lg-table-cell">Dibuat</th>
                            <th class="small fw-bold d-none d-lg-table-cell">Diperbarui</th>
                            <th class="small fw-bold text-center" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($admins as $admin): 
                        $is_current_user = ($admin['id'] == $current_user_id);
                        ?>
                        <tr class="<?php echo $is_current_user ? 'table-primary bg-opacity-10' : ''; ?>">
                            <td class="small text-muted"><?php echo htmlspecialchars($admin['id']); ?></td>
                            <td class="fw-bold">
                                <?php echo htmlspecialchars($admin['username']); ?>
                                <?php if ($is_current_user): ?>
                                    <span class="badge bg-secondary ms-1">Anda</span>
                                <?php endif; ?>
                            </td>
                            <td class="small d-none d-sm-table-cell"><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td class="small d-none d-md-table-cell"><?php echo htmlspecialchars($admin['full_name']); ?></td>
                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($admin['role']); ?></span></td>
                            <td class="small text-muted d-none d-lg-table-cell"><?php echo date("d/m/Y", strtotime($admin['created_at'])); ?></td>
                            <td class="small text-muted d-none d-lg-table-cell"><?php echo date("d/m/Y", strtotime($admin['updated_at'])); ?></td>
                            <td class="text-nowrap text-center">
                                <a href="<?php echo $router_path; ?>?page=administrator_management/form&id=<?php echo $admin['id']; ?>" class="btn btn-sm btn-info text-white me-1" title="Edit">
                                    <i data-feather="edit-2" style="width: 14px; height: 14px;"></i> 
                                </a>
                                
                                <?php if ($is_current_user): ?>
                                    <button class="btn btn-sm btn-danger" disabled title="Tidak bisa menghapus akun sendiri">
                                        <i data-feather="trash-2" style="width: 14px; height: 14px;"></i>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                            title="Hapus"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteAdminModal"
                                            data-id="<?php echo $admin['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($admin['username']); ?>">
                                        <i data-feather="trash-2" style="width: 14px; height: 14px;"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteAdminModal" tabindex="-1" aria-labelledby="deleteAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteAdminModalLabel">Konfirmasi Penghapusan Administrator</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menghapus administrator **<span id="adminName" class="fw-bold"></span>** (ID: <span id="adminId" class="fw-bold"></span>)? Tindakan ini tidak dapat dibatalkan.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a id="confirmDeleteLink" href="#" class="btn btn-danger">Ya, Hapus Permanen</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inisialisasi Feather Icons
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        // Logika untuk Delete Modal
        const deleteModal = document.getElementById('deleteAdminModal');
        const confirmDeleteLink = document.getElementById('confirmDeleteLink');
        const adminNameSpan = document.getElementById('adminName');
        const adminIdSpan = document.getElementById('adminId');

        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const adminId = button.getAttribute('data-id');
                const adminName = button.getAttribute('data-name');
                
                adminNameSpan.textContent = adminName;
                adminIdSpan.textContent = adminId;

                // Set link Hapus Permanen
                const basePath = '<?php echo $router_path; ?>';
                // Arahkan ke endpoint delete di halaman index ini
                confirmDeleteLink.href = basePath + '?page=administrator_management/index&action=delete&id=' + adminId;
            });
        }
    });
</script>