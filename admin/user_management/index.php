<?php
/**
 * admin/user_management/index.php
 * Halaman daftar pelanggan (READ), sekarang menampilkan foto profil.
 * Di-include oleh admin/dashboard.php.
 * 🆕 BARU: Tambahan Pencarian (Nama/Email) dan Modal Delete.
 * 🔥 PERBAIKAN: Mengganti ikon FontAwesome (fas) ke Feather Icons.
 * 🐛 FIX: Mengubah logika Delete Modal menjadi form POST agar sesuai dengan delete.php.
 * ✨ STYLE: Memberikan styling yang lebih modern pada modal delete.
 */

$db_koneksi = $GLOBALS['koneksi'] ?? null;
$router_path = $router_path ?? 'dashboard.php';
$profile_upload_dir = '../uploads/user_profiles/'; // Path ke folder foto profil

// --- LOGIKA PENCARIAN ---
$search_query = $_GET['search'] ?? '';

$users = [];
$error_fetch = '';

if (!$db_koneksi || !($db_koneksi instanceof mysqli) || ($db_koneksi->connect_error ?? false)) {
    $error_fetch = '<div class="alert alert-danger">⚠️ Kesalahan Database: Koneksi database tidak tersedia.</div>';
} else {
    $where_conditions = [];
    
    // Logika Pencarian (Nama atau Email)
    if (!empty($search_query)) {
        // PERBAIKAN: Gunakan prepared statement untuk pencarian jika memungkinkan, 
        // tetapi untuk kesederhanaan, kita gunakan real_escape_string yang sudah ada.
        $search_term = $db_koneksi->real_escape_string($search_query);
        $where_conditions[] = "(name LIKE '%$search_term%' OR email LIKE '%$search_term%')";
    }

    $where_clause = !empty($where_conditions) ? ' WHERE ' . implode(' AND ', $where_conditions) : '';

    // SQL: Ambil data pelanggan dengan filter
    $sql = "SELECT id, name, email, created_at, profile_image_path 
            FROM users 
            {$where_clause}
            ORDER BY id DESC";
    
    $result = $db_koneksi->query($sql);
    
    if ($result) {
        $users = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $error_fetch = '<div class="alert alert-danger">Gagal mengambil data pelanggan: ' . htmlspecialchars($db_koneksi->error) . '</div>';
    }
}

// Menangani Flash Message dari sesi
$message_html = '';
if (isset($_SESSION['crud_success_toast'])) {
    $message = $_SESSION['crud_success_toast']; // Tidak perlu htmlspecialchars di sini jika sudah aman
    $message_html = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                         {$message}
                         <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                     </div>";
    unset($_SESSION['crud_success_toast']);
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <h1 class="h3 fw-bold text-dark mb-0">
            <i data-feather="users" class="me-2" style="width: 24px; height: 24px;"></i>
            Manajemen Pelanggan
        </h1>
        <a href="<?php echo $router_path; ?>?page=user_management/create" class="btn btn-primary shadow-sm text-nowrap">
            <i data-feather="user-plus" style="width: 18px; height: 18px;"></i>
            Tambah Pelanggan Baru
        </a>
    </div>

    <?php echo $error_fetch; ?>
    <?php echo $message_html; ?>

    <div class="card mb-4 p-3 shadow-sm">
        <form method="GET" action="<?php echo $router_path; ?>" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="user_management/index">
            
            <div class="col-md-6 col-lg-5">
                <label for="search" class="form-label small fw-bold mb-0">Cari Pelanggan (Nama/Email):</label>
                <div class="input-group">
                    <span class="input-group-text"><i data-feather="search" style="width: 16px; height: 16px;"></i></span>
                    <input type="text" class="form-control form-control-sm" id="search" name="search" placeholder="Cari nama atau email pelanggan..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </div>
            
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">Cari</button>
            </div>
            
            <div class="col-auto">
                <a href="<?php echo $router_path; ?>?page=user_management/index" class="btn btn-sm btn-secondary">Reset</a>
            </div>
        </form>
    </div>
    <div class="card shadow-lg mb-4">
        <div class="card-header bg-dark text-white fw-bold py-3 d-flex justify-content-between align-items-center">
            <span class="d-flex align-items-center">
                <i data-feather="list" class="me-2" style="width: 18px; height: 18px;"></i>
                Daftar Pelanggan
            </span>
            <span class="badge bg-primary rounded-pill">Total: <?php echo count($users); ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="small fw-bold text-center" style="width: 50px;">ID</th>
                            <th class="small fw-bold text-center" style="width: 70px;">Foto</th> 
                            <th class="small fw-bold">Nama Lengkap</th>
                            <th class="small fw-bold">Email</th>
                            <th class="small fw-bold d-none d-md-table-cell">Tgl. Daftar</th>
                            <th class="small fw-bold text-center" style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach($users as $row):
                                $profile_img = $row['profile_image_path'];
                                // Pastikan URL gambar di-encode
                                $profile_img_url = !empty($profile_img) 
                                    ? $profile_upload_dir . urlencode($profile_img)
                                    : 'https://via.placeholder.com/40/EEEEEE/AAAAAA?text=U'; 
                            ?>
                            <tr>
                                <td class="text-center small text-muted"><?php echo htmlspecialchars($row['id']); ?></td>
                                <td class="text-center">
                                    <?php if (!empty($profile_img)): ?>
                                        <img src="<?php echo $profile_img_url; ?>" alt="Foto" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;" onerror="this.onerror=null;this.src='https://via.placeholder.com/40/EEEEEE/AAAAAA?text=U';">
                                    <?php else: ?>
                                        <i data-feather="user" class="text-muted" style="width: 30px; height: 30px;"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td class="small text-muted d-none d-md-table-cell"><?php echo date("d/m/Y H:i", strtotime($row['created_at'])); ?></td>
                                
                                <td class="text-nowrap text-center">
                                    <a href="<?php echo $router_path; ?>?page=user_management/edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info text-white me-1" title="Edit Pelanggan">
                                        <i data-feather="edit-2" style="width: 14px; height: 14px;"></i> 
                                    </a>
                                    
                                    <button type="button" class="btn btn-sm btn-danger delete-btn" 
                                            title="Hapus Pelanggan"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteUserModal"
                                            data-id="<?php echo $row['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($row['name']); ?>">
                                        <i data-feather="trash-2" style="width: 14px; height: 14px;"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                <div class="p-3 my-2 bg-light rounded text-muted">
                                    <i data-feather="info" class="me-1" style="width: 16px; height: 16px;"></i>
                                    Belum ada pelanggan terdaftar.
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"> <div class="modal-content border-0 shadow-lg"> <form method="POST" action="<?php echo $router_path; ?>?page=user_management/delete">
                <input type="hidden" name="id" id="modalUserId">
                <input type="hidden" name="delete_user" value="1">
                
                <div class="modal-header bg-danger text-white py-3"> <h5 class="modal-title d-flex align-items-center" id="deleteUserModalLabel">
                        <i data-feather="alert-triangle" class="me-2" style="width: 20px; height: 20px;"></i>
                        Konfirmasi Penghapusan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4"> <p class="mb-3">
                        Anda akan menghapus pelanggan **<span id="userName" class="fw-bold text-danger"></span>** (ID: <span id="userId" class="fw-bold text-danger"></span>).
                    </p>
                    
                    <div class="alert alert-danger border-start border-4 border-danger py-2" role="alert">
                        <small class="d-block fw-bold">PERINGATAN:</small>
                        <small class="d-block">
                            Tindakan ini **permanen**, tidak dapat dibatalkan, dan akan menghapus semua data terkait **termasuk file foto profil** dari server.
                        </small>
                    </div>
                </div>
                
                <div class="modal-footer d-flex justify-content-between"> <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Hapus Permanen</button> 
                </div>
            </form>
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
        const deleteModal = document.getElementById('deleteUserModal');
        const modalUserIdInput = document.getElementById('modalUserId');
        const userNameSpan = document.getElementById('userName');
        const userIdSpan = document.getElementById('userId');

        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const userId = button.getAttribute('data-id');
                const userName = button.getAttribute('data-name');
                
                userNameSpan.textContent = userName;
                userIdSpan.textContent = userId;

                // Mengisi hidden input dengan ID pengguna
                modalUserIdInput.value = userId; 
            });
        }
    });
</script>