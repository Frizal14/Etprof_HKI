<?php
/**
 * admin/login.php
 * Form dan Logika Login untuk Administrator.
 */

// Mulai sesi dan include file penting
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Definisikan path assets untuk REFERENSI URL (HTML/CSS)
// Dari 'admin/' naik satu level ke root, lalu masuk ke 'assets/'
$assets_path = '../assets/'; 

// Definisikan path BASE ABSOLUT untuk REQUIRE/INCLUDE (Lebih aman dari error path)
// dirname(__DIR__) akan menghasilkan path ke direktori 'e-commerce_sederhana' (root)
$root_path = dirname(__DIR__) . DIRECTORY_SEPARATOR; 

// === START: LOGIKA PENGECEKAN TOAST KHUSUS LOGOUT ===
$logout_message = '';
if (isset($_SESSION['logout_success_toast'])) {
    $logout_message = $_SESSION['logout_success_toast'];
    // Hapus pesan dari sesi agar tidak muncul lagi setelah refresh
    unset($_SESSION['logout_success_toast']);
}
// === END: LOGIKA PENGECEKAN TOAST KHUSUS LOGOUT ===

// === START: LOGIKA PENGECEKAN TOAST KHUSUS LOGIN SUKSES ===
$login_success_message = ''; 
if (isset($_SESSION['login_success_toast'])) {
    $login_success_message = $_SESSION['login_success_toast'];
    // Hapus pesan dari sesi agar tidak muncul lagi setelah refresh
    unset($_SESSION['login_success_toast']);
}
// === END: LOGIKA PENGECEKAN TOAST KHUSUS LOGIN SUKSES ===


// Jika admin sudah login, langsung redirect ke dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $_SESSION['login_success_toast'] = 'Anda sudah login. Selamat datang di Dashboard!';
    header('Location: dashboard.php');
    exit;
}

// === PERBAIKAN PATH: Menggunakan $root_path karena file ada di root ===
require_once $root_path . 'koneksi.php'; 
require_once $root_path . 'hash_setup.php'; 
// ====================================================================

// --- TAMBAHAN: AMBIL LOGO DARI DATABASE ---
$site_logo_file = '';
// Path untuk browser (HTML src) - naik satu folder dari admin/
$logo_html_path = '../uploads/brand/'; 
// Path untuk server (file_exists) - absolut path
$logo_server_path = $root_path . 'uploads/brand/';

if (isset($koneksi) && $koneksi instanceof mysqli) {
    $sql_settings = "SELECT logo_image_path FROM website_settings WHERE id = 1";
    $result_settings = $koneksi->query($sql_settings);
    if ($result_settings && $result_settings->num_rows > 0) {
        $row_settings = $result_settings->fetch_assoc();
        $site_logo_file = htmlspecialchars($row_settings['logo_image_path'] ?? '');
    }
}
// ------------------------------------------

$error = '';
$login_attempted = false; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_attempted = true; 
    
    // Pastikan $koneksi didefinisikan dan koneksi berhasil
    if (!isset($koneksi) || (isset($koneksi) && $koneksi->connect_error)) {
        $error = "Gagal terhubung ke database. Cek file koneksi.php Anda.";
    } else {
        $username = $koneksi->real_escape_string(trim($_POST['username']));
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $error = "Mohon isi semua bidang (Username dan Password)!";
        } else {
            $sql = "SELECT id, password, full_name, role FROM admins WHERE username = '$username'";
            $result = $koneksi->query($sql);

            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();
                $stored_hash = $admin['password'];

                if (function_exists('verifyPassword') && verifyPassword($password, $stored_hash)) {
                    // Login Sukses
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_name'] = $admin['full_name'];
                    $_SESSION['admin_role'] = $admin['role'];

                    $_SESSION['login_success_toast'] = 'Selamat datang, ' . htmlspecialchars($admin['full_name']) . '! Anda berhasil masuk.';

                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = "Username atau password salah.";
                }
            } else {
                $error = "Username atau password salah.";
            }
        }
    }
}

if (isset($koneksi) && $koneksi instanceof mysqli) {
    $koneksi->close();
}

// Tentukan apakah toast error login harus ditampilkan
$show_login_error_toast = $login_attempted && !empty($error);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Profesional</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    
    <style>
        :root {
            --primary-color: #007bff;
            --danger-color: #dc3545;
            --success-color: #198754;
            --info-color: #0d6efd;
            --warning-color: #ffc107;
        }
        body { 
            background: #f4f6f9; 
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            transition: background-color 0.5s ease;
        }
        .login-container { 
            max-width: 380px; 
            width: 90%;
            padding: 25px;
        }
        .card {
            border-radius: 16px; 
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08); 
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px); 
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.1);
        }
        .card-header-modern {
            padding: 30px 20px 20px; 
            text-align: center;
            border-bottom: none; 
            background-color: white;
        }
        .card-header-modern h4 {
            color: #212529; 
            font-weight: 700; 
        }
        .card-header-modern img {
            width: 50px; /* Ukuran logo */
            height: 50px;
            margin-bottom: 10px;
            object-fit: contain; /* Agar logo tidak gepeng */
        }
        .card-body {
            padding: 20px 40px 40px 40px !important; 
        }

        /* Styling Input Field dengan Ikon */
        .input-group-custom {
            position: relative;
            margin-bottom: 20px;
        }
        .input-group-custom .form-control {
            border-radius: 10px;
            padding: 12px 15px 12px 45px; 
            height: 50px; 
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #dee2e6;
        }
        /* Penyesuaian padding untuk input password yang memiliki tombol di kanan */
        .input-group-custom #password {
            padding-right: 45px; /* Memberi ruang untuk tombol mata */
        }
        
        .input-group-custom .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.1);
        }
        .input-group-custom .input-icon {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #6c757d; 
            transition: color 0.3s ease;
            pointer-events: none; 
        }
        .input-group-custom .form-control:focus + .input-icon {
            color: var(--primary-color); 
        }

        /* Styling Tombol */
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 10px;
            padding: 12px 0;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(0, 123, 255, 0.2);
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 123, 255, 0.3);
        }

        /* Styling Toast */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1080;
        }
        .toast {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        /* Style untuk toast warning/validation */
        #validationToast.toast {
            background-color: var(--warning-color); 
            color: #212529; 
        }
        /* Style untuk toast error/danger (Login Gagal) */
        #errorToast.toast {
            background-color: var(--danger-color); 
            color: white;
        }
        /* Style untuk toast success/logout (Hijau) */
        #logoutSuccessToast.toast {
            background-color: var(--success-color); 
            color: white;
        }
        /* Style untuk toast success/login (Biru) */
        #loginSuccessToast.toast {
            background-color: var(--info-color); 
            color: white;
        }
        .btn-close-white {
            filter: invert(1);
        }
        /* Style untuk ikon Feather di dalam Toast */
        .toast-body .feather {
            vertical-align: middle;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="login-container">
            <div class="card">
                
                <div class="card-header-modern">
                    <?php 
                    $final_logo_url = $assets_path . 'images/logo.png'; // Default fallback
                    if (!empty($site_logo_file)) {
                        // Cek apakah file ada di server (menggunakan path absolut server)
                        if (file_exists($logo_server_path . $site_logo_file)) {
                            // Jika ada, gunakan path HTML (relative browser)
                            $final_logo_url = $logo_html_path . $site_logo_file;
                        }
                    }
                    ?>
                    <img src="<?php echo $final_logo_url; ?>" alt="Logo Toko" class="d-block mx-auto mb-2">
                    
                    <h4>Sistem Administrasi</h4>
                    <p class="text-muted mb-0" style="font-size: 0.95rem;">Selamat datang kembali, silakan masuk.</p>
                    <link rel="stylesheet" href="<?php echo $assets_path; ?>css/logo.css">
                </div>
                
                <div class="card-body">
                    
                    <form id="loginForm" method="POST" action="login.php" novalidate>
                        
                        <div class="input-group-custom">
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" placeholder="Username" required autocomplete="username">
                            <i data-feather="user" class="input-icon"></i>
                        </div>

                        <div class="input-group-custom mb-4">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required autocomplete="current-password">
                            <i data-feather="lock" class="input-icon"></i>
                            <button type="button" id="togglePassword" class="btn btn-sm" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); z-index: 10; padding: 0;">
                                <i data-feather="eye-off" class="text-muted" style="width: 20px; height: 20px;"></i>
                            </button>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">MASUK</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast-container">
    <div class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true" id="validationToast" data-bs-delay="4000">
        <div class="d-flex">
            <div class="toast-body">
                <i data-feather="alert-triangle" class="me-2" style="width: 18px; height: 18px;"></i> Mohon isi semua bidang (Username dan Password)!
            </div>
            <button type="button" class="btn-close btn-close-dark me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
    
    <div class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true" id="errorToast" data-bs-delay="5000">
        <div class="d-flex">
            <div class="toast-body">
                <i data-feather="x-circle" class="me-2" style="width: 18px; height: 18px;"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>

    <div class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true" id="logoutSuccessToast" data-bs-delay="5000">
        <div class="d-flex">
            <div class="toast-body" id="logoutToastBody">
                </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>

    <div class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true" id="loginSuccessToast" data-bs-delay="5000">
        <div class="d-flex">
            <div class="toast-body" id="loginToastBody">
                </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Inisialisasi Feather Icons Awal (untuk ikon input form)
    if (typeof feather !== 'undefined') {
        feather.replace(); 
    }

    const form = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const validationToastElement = document.getElementById('validationToast');
    const validationToast = new bootstrap.Toast(validationToastElement);
    
    // Helper untuk merender Feather Icon di Toast atau elemen lain
    function renderFeatherIconInToast(toastElement) {
        if (typeof feather !== 'undefined') {
            feather.replace({ root: toastElement });
        }
    }

    // 2. Logika untuk menampilkan Toast Error Login (dari server-side PHP - Merah)
    const errorToastElement = document.getElementById('errorToast');
    
    <?php if ($show_login_error_toast): ?>
        renderFeatherIconInToast(errorToastElement); 
        const errorToast = new bootstrap.Toast(errorToastElement);
        errorToast.show();
    <?php endif; ?>
    
    // 3. Logika untuk menampilkan Toast Sukses Logout (Hijau)
    const logoutMessage = <?php echo json_encode($logout_message); ?>; 
    
    if (logoutMessage && logoutMessage.trim() !== "") {
        const logoutSuccessToastElement = document.getElementById('logoutSuccessToast');
        const logoutToastBody = document.getElementById('logoutToastBody');

        logoutToastBody.innerHTML = '<i data-feather="check-circle" class="me-2" style="width: 18px; height: 18px;"></i> ' + logoutMessage;
        
        renderFeatherIconInToast(logoutSuccessToastElement);

        const logoutSuccessToast = new bootstrap.Toast(logoutSuccessToastElement);
        logoutSuccessToast.show();
    }

    // 4. Logika untuk menampilkan Toast Sukses Login (Biru)
    const loginMessage = <?php echo json_encode($login_success_message); ?>; 
    
    if (loginMessage && loginMessage.trim() !== "") {
        const loginSuccessToastElement = document.getElementById('loginSuccessToast');
        const loginToastBody = document.getElementById('loginToastBody'); 

        loginToastBody.innerHTML = '<i data-feather="smile" class="me-2" style="width: 18px; height: 18px;"></i> ' + loginMessage;
        
        renderFeatherIconInToast(loginSuccessToastElement);

        const loginSuccessToast = new bootstrap.Toast(loginSuccessToastElement);
        loginSuccessToast.show();
    }
    
    // 6. LOGIKA SHOW/HIDE PASSWORD
    const togglePassword = document.getElementById('togglePassword');
    
    if (togglePassword && passwordInput) {
        // Render ikon mata awal (penting karena feather.replace() dipanggil sebelum elemen ini ada di DOM saat inisialisasi awal)
        feather.replace({ root: togglePassword }); 

        togglePassword.addEventListener('click', function (e) {
            // Toggle tipe input antara 'password' dan 'text'
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle ikon mata (eye-off / eye)
            const icon = this.querySelector('i');
            if (type === 'text') {
                icon.setAttribute('data-feather', 'eye');
            } else {
                icon.setAttribute('data-feather', 'eye-off');
            }
            // Render ulang ikon feather di dalam tombol
            feather.replace({ root: this });
        });
    }

    // 5. Validasi Sisi Klien (Toast kolom kosong - Kuning)
    form.addEventListener('submit', function (event) {
        if (usernameInput.value.trim() === '' || passwordInput.value.trim() === '') {
            event.preventDefault(); 
            
            renderFeatherIconInToast(validationToastElement);
            
            validationToast.show();
            
            if (usernameInput.value.trim() === '') {
                usernameInput.focus();
            } else if (passwordInput.value.trim() === '') {
                passwordInput.focus();
            }
        }
    });
});
</script>
</body>
</html>