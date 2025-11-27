<?php
/**
 * user_profile.php
 * Halaman bagi pengguna untuk melihat dan mengedit data profil mereka.
 * Memungkinkan update nama, email, dan foto profil.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    // Redirect ke halaman login jika belum login
    header("Location: login_user.php");
    exit();
}

require_once 'koneksi.php'; // Ambil koneksi database

$user_id = $_SESSION['user_id'];
$assets_path = 'assets/'; // Path untuk CSS
$profile_upload_path = 'uploads/user_profiles/'; // Path untuk foto profil

// 1. Ambil Data User Saat Ini
$user_data = [];
$sql = "SELECT id, name, email, profile_image_path FROM users WHERE id = ?";
$stmt = $koneksi->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
    } else {
        // Handle jika user tidak ditemukan (meskipun sudah login, ini error)
        $_SESSION['toast_message'] = "Terjadi kesalahan saat memuat data pengguna.";
        $_SESSION['toast_type'] = "error";
        header("Location: toko_sepatu.php");
        exit();
    }
    $stmt->close();
} else {
    // Handle error prepare
}

// 2. Ambil Flash Message (SWAL dan Toast)
// 🔥 Tambahkan variabel untuk Sweet Alert
$session_swal_title = '';
$session_swal_text = '';
$session_swal_icon = '';

// Prioritaskan pengambilan Sweet Alert
if (isset($_SESSION['swal_title']) && isset($_SESSION['swal_icon'])) {
    $session_swal_title = $_SESSION['swal_title'];
    $session_swal_text = isset($_SESSION['swal_text']) ? $_SESSION['swal_text'] : '';
    $session_swal_icon = $_SESSION['swal_icon'];
    
    // Hapus sesi SWAL agar tidak muncul lagi
    unset($_SESSION['swal_title']);
    unset($_SESSION['swal_text']);
    unset($_SESSION['swal_icon']);
}


// Ambil Toast (hanya jika SWAL tidak disetel, atau untuk error)
$toast_message = null;
$toast_type = null;

if (isset($_SESSION['toast_message'])) {
    $toast_message = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'] ?? 'success'; 
    
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}

// Tentukan path foto profil yang akan ditampilkan
$current_profile_img = $user_data['profile_image_path'];
if (empty($current_profile_img)) {
    // URL default jika tidak ada foto
    $profile_img_url = 'https://via.placeholder.com/150/EEEEEE/AAAAAA?text=Default'; 
} else {
    $profile_img_url = $profile_upload_path . htmlspecialchars($current_profile_img);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil | TokoOnlineku</title> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> 
    <link rel="stylesheet" href="<?php echo $assets_path; ?>css/gaya.css?v=<?php echo time(); ?>"> 
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Gaya tambahan agar foto profil terlihat bagus */
        .profile-img {
            /* Pastikan ukuran tetap sama di semua layar untuk konsistensi */
            width: 150px;
            height: 150px;
            object-fit: cover;
            /* PERUBAHAN: Border tebal dan warna primer */
            border: 7px solid #0d6efd; /* var(--bs-primary) */
            border-radius: 50%; /* Jaminan bulat */
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.2); /* Shadow lebih kuat */
            transition: transform 0.3s ease-in-out;
        }
        .profile-img:hover {
            transform: scale(1.05); /* Efek hover menarik */
        }
        
        /* PERUBAHAN UTAMA UNTUK CARD */
        .card-header {
            /* Gunakan warna yang lebih mencolok, misalnya warna primer Bootstrap */
            background-color: #0d6efd; /* bg-primary */
            color: white; /* Teks putih di atas background biru */
            border-bottom: none;
            padding-top: 1.5rem;
            padding-bottom: 1.5rem;
            border-top-left-radius: 0.5rem !important;
            border-top-right-radius: 0.5rem !important;
        }

        /* 🔥 PERBAIKAN: Tambahkan Padding ke Body dan Pastikan Navbar Fixed 🔥 */
        body {
            /* Padding-top untuk mengimbangi Navbar fixed-top */
            padding-top: 80px; 
            /* PERUBAHAN: Latar belakang abu-abu muda untuk kontras dengan card putih */
            background-color: #f0f2f5; 
        }
        .navbar.fixed-top { 
            z-index: 1030;
        }
        /* ------------------------------------------------------------- */

        /* KONTEN CARD */
        .card {
            border: none; /* Hilangkan border default */
            border-radius: 0.5rem;
            overflow: hidden; /* Penting agar header radius terlihat */
        }

        /* INPUT FILE LEBIH MENARIK */
        .file-upload-box {
            background-color: #f8f9fa; /* Latar belakang abu-abu */
            border: 1px dashed #ced4da; /* Border putus-putus */
            transition: border-color 0.3s;
        }
        .file-upload-box:hover {
            border-color: #0d6efd; /* Biru saat hover */
        }

        /* TOMBOL UTAMA */
        .btn-primary.btn-lg {
            box-shadow: 0 0.25rem 0.5rem rgba(13, 110, 253, 0.3);
        }

        /* Gaya Toast (untuk pesan error/warning) */
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 1090; }

        /* RESPONSIVITAS TAMBAHAN (Mobile/Tablet) */
        @media (max-width: 767.98px) {
            /* Kurangi padding di container utama untuk menggunakan ruang layar secara maksimal */
            .container {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            /* Kurangi padding card-body */
            .card-body {
                padding: 1.5rem !important;
            }
            /* Sesuaikan ukuran header di mobile */
            .card-header h2 {
                font-size: 1.5rem;
            }
            /* Teks kecil di input email diperjelas */
            .text-info {
                font-size: 0.75rem;
            }
            /* Tombol aksi di navbar menyatu lebih baik */
            .navbar .nav-item {
                margin-left: 0 !important;
                margin-top: 0.25rem;
            }
            .navbar-nav {
                flex-direction: row; /* Biar tombol tetap sejajar horizontal di mobile */
            }
        }
    </style>
</head>
<body>

<?php 
// HTML TOAST CONTAINER (Jika ada pesan Toast)
if ($toast_message): 
    $toast_class = ($toast_type === 'error') ? 'bg-danger' : (($toast_type === 'warning') ? 'bg-warning text-dark' : 'bg-success');
?>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
    <div id="liveToast" class="toast fade align-items-center text-white <?php echo $toast_class; ?>" 
        role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-bold">
                <?php echo htmlspecialchars($toast_message); ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="toko_sepatu.php">TokoOnlineku</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="btn btn-outline-secondary" href="toko_sepatu.php">
                        <i class="fas fa-home me-1"></i> Beranda
                    </a>
                </li>
                <li class="nav-item ms-md-2 mt-2 mt-md-0"> <a class="btn btn-outline-danger" href="logout_user.php" onclick="return confirm('Apakah Anda yakin ingin keluar dari akun ini?');">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8">
            <div class="card shadow-lg">
                <div class="card-header text-center text-white"> 
                    <h2 class="mb-0 fw-bold"><i class="fas fa-user-edit me-2"></i> Edit Profil Saya</h2>
                </div>
                <div class="card-body p-4 p-sm-5"> <?php if ($toast_message && !isset($_SESSION['toast_message'])): // Tampilkan alert jika toast tidak otomatis ditampilkan oleh JS ?>
                        <div class="alert alert-<?php echo ($toast_type === 'error') ? 'danger' : 'success'; ?> alert-dismissible fade show d-md-none" role="alert">
                            <?php echo htmlspecialchars($toast_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mb-4">
                        <img src="<?php echo $profile_img_url; ?>" class="rounded-circle profile-img" alt="Foto Profil">
                        <h4 class="mt-3 fw-bold text-primary"><?php echo htmlspecialchars($user_data['name']); ?></h4>
                        <p class="text-muted"><?php echo htmlspecialchars($user_data['email']); ?></p>
                    </div>

                    <form action="user_profile_handler.php" method="POST" enctype="multipart/form-data">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label fw-bold">Nama Lengkap</label>
                            <input type="text" class="form-control form-control-lg" id="name" name="name" 
                                value="<?php echo htmlspecialchars($user_data['name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold">Alamat Email</label>
                            <input type="email" class="form-control form-control-lg bg-light" id="email" name="email" 
                                value="<?php echo htmlspecialchars($user_data['email']); ?>" required readonly disabled>
                            <small class="text-info"><i class="fas fa-info-circle me-1"></i> Email adalah ID unik, tidak dapat diubah dari sini.</small>
                        </div>

                        <div class="mb-4 p-3 rounded file-upload-box"> 
                            <label for="profile_image" class="form-label fw-bold text-primary"><i class="fas fa-camera me-1"></i> Ganti Foto Profil (Opsional)</label>
                            <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/jpeg, image/png">
                            <small class="text-muted">Maksimal ukuran file 2MB. Format: JPG atau PNG.</small>
                        </div>
                        
                        <?php if (!empty($current_profile_img)): ?>
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" value="1" id="delete_photo" name="delete_photo">
                            <label class="form-check-label text-danger fw-bold" for="delete_photo">
                                <i class="fas fa-trash me-1"></i> Hapus Foto Profil Saat Ini
                            </label>
                        </div>
                        <?php endif; ?>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" name="update_profile" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-1"></i> Simpan Perubahan Profil
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // 🔥 FUNGSI SWEET ALERT (BARU) 🔥
    function showSweetAlert(icon, title, text, timer = 4000) {
        Swal.fire({
            icon: icon,
            title: title,
            text: text,
            showConfirmButton: false,
            timer: timer,
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        
        // --- 1. LOGIKA SWEET ALERT ---
        const sessionSwalTitle = "<?= htmlspecialchars($session_swal_title) ?>";
        const sessionSwalText = "<?= htmlspecialchars($session_swal_text) ?>";
        const sessionSwalIcon = "<?= htmlspecialchars($session_swal_icon) ?>"; 

        if (sessionSwalTitle && sessionSwalIcon) {
             // Jika ada pesan SWAL, tampilkan
             showSweetAlert(sessionSwalIcon, sessionSwalTitle, sessionSwalText, 4000);
        }

        // --- 2. LOGIKA TOAST (untuk error/warning) ---
        <?php if ($toast_message): ?>
        var toastEl = document.getElementById('liveToast');
        if (toastEl) {
            var toast = new bootstrap.Toast(toastEl, {
                autohide: true,
                delay: 4000 
            });
            toast.show();
        }
        <?php endif; ?>
    });
</script>

</body>
</html>