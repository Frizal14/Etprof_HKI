<?php
// login_user.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';
global $koneksi;

// Definisikan path assets
$assets_path = 'assets/';

// Inisialisasi variabel email dan flag error Toast
$email = '';
$error_message = '';
$show_error_toast = false; // Flag untuk error dari PHP (Gagal Login/Validasi)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // 1. Validasi Input Kosong (TIDAK LAGI MENGGUNAKAN REDIRECT/SESSION UNTUK INI)
    if (empty($email) || empty($password)) {
        $error_message = "Email dan password wajib diisi!";
        $show_error_toast = true;
        // Simpan email yang diinput agar bisa ditampilkan kembali di form
        $_SESSION['login_attempt_email'] = htmlspecialchars($email); 
        // Lanjutkan eksekusi ke bagian HTML/JS untuk menampilkan Toast error
    } else {
        // 2. Proses Verifikasi Kredensial
        $stmt = $koneksi->prepare("SELECT id, name, email, password_hash, profile_image_path FROM users WHERE email = ?");
        
        if ($stmt === false) {
             $error_message = "Terjadi kesalahan database saat login.";
             $show_error_toast = true;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Login berhasil
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_profile_img'] = $user['profile_image_path']; 
                $_SESSION['user_email'] = $user['email']; 
                $_SESSION['is_logged_in'] = true; 
                
                // Set sesi Sweet Alert Sukses untuk halaman berikutnya (toko_sepatu.php)
                // Menggunakan variabel sesi yang berbeda untuk SweetAlert
                $_SESSION['swal_icon'] = 'success';
                $_SESSION['swal_title'] = 'Login Berhasil!';
                $_SESSION['swal_text'] = "Selamat datang, " . htmlspecialchars($user['name']) . ".";
                
                header('Location: toko_sepatu.php'); 
                exit;
            } else {
                // Gagal Login (Email atau Password Salah)
                $error_message = "Email atau password salah!";
                $show_error_toast = true;
                $_SESSION['login_attempt_email'] = htmlspecialchars($email); 
            }
        }
    }
}

// Cek status logout dari query parameter (DIUBAH UNTUK SWEET ALERT)
$show_logout_swal = false;
if (isset($_GET['status']) && $_GET['status'] == 'loggedout') {
    $show_logout_swal = true;
}

// Cek toast dari sesi (digunakan untuk register sukses dari register.php) -> DIUBAH KE SWEET ALERT
$session_swal_title = '';
$session_swal_text = '';
$session_swal_icon = '';

if (isset($_SESSION['swal_title']) && isset($_SESSION['swal_icon'])) {
    $session_swal_title = $_SESSION['swal_title'];
    $session_swal_text = isset($_SESSION['swal_text']) ? $_SESSION['swal_text'] : '';
    $session_swal_icon = $_SESSION['swal_icon'];
    
    // Hapus sesi agar Sweet Alert hanya muncul sekali
    unset($_SESSION['swal_title']);
    unset($_SESSION['swal_text']);
    unset($_SESSION['swal_icon']);
    
    // Hapus juga sesi toast lama jika masih ada (fallback)
    unset($_SESSION['toast_message']);
    unset($_SESSION['toast_type']);
}

// Ambil email dari sesi jika ada percobaan login gagal
if (isset($_SESSION['login_attempt_email'])) {
    $email = $_SESSION['login_attempt_email'];
    unset($_SESSION['login_attempt_email']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Pengguna | Toko SepatuKu</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* CSS DISINKRONISASI & DITINGKATKAN */
        :root {
            --primary-color: #007bff; 
            --secondary-color: #6c757d;
            --brand-bg: #f8f9fa; 
            --danger-color: #dc3545;
            --success-color: #28a745;
            --info-color: #0d6efd; 
        }
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: linear-gradient(135deg, #007bff 0%, #00c6ff 100%); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            margin: 0; 
            /* PERBAIKAN RESPONSIVITAS 1: Tambahkan padding vertikal untuk mobile/tab */
            padding: 20px 0; 
        }
        .card {
            width: 450px;
            /* PERBAIKAN RESPONSIVITAS 2: Batasi lebar di layar kecil */
            max-width: 95%; 
            padding: 30px; 
            background-color: #ffffff; 
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: none;
            animation: fadeIn 0.5s ease-out;
            margin: auto; /* Memastikan card di tengah jika body padding */
        }
        /* PERBAIKAN RESPONSIVITAS 3: Kurangi padding card di layar sangat kecil */
        @media (max-width: 400px) {
            .card {
                padding: 20px;
            }
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }

        .card-header {
            background-color: transparent;
            color: #343a40;
            padding: 0 0 20px 0;
            border-bottom: none;
            text-align: center;
        }
        .card-header img {
            width: 100px; /* Ukuran disesuaikan untuk mobile */
            height: 100px; 
            margin-bottom: 15px;
        }
        .form-control {
            width: 100%; 
            padding: 12px 16px; /* Padding disesuaikan */
            margin-bottom: 20px; 
            border: 1px solid #e0e0e0; 
            border-radius: 10px; 
            font-size: 16px; 
            transition: border 0.3s ease, box-shadow 0.3s ease;
        }
        .form-control:focus { 
            border-color: var(--primary-color); 
            box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.1); 
            outline: none; 
        }
        .btn-primary {
            width: 100%; 
            padding: 14px; 
            background: var(--primary-color); 
            border: none; 
            border-radius: 10px; 
            color: #ffffff; 
            font-size: 18px; 
            font-weight: bold; 
            cursor: pointer; 
            transition: background-color 0.3s ease, transform 0.2s; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 10px; 
        }
        .btn-primary:hover { background: #0056b3; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3); }

        .password-container {
            position: relative;
            margin-bottom: 20px;
        }
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--secondary-color);
            z-index: 10;
        }
        
        .back-to-shop {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
            padding: 10px;
            border-top: 1px solid #eee;
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 600;
            border-radius: 8px;
            transition: background-color 0.3s;
        }
        .back-to-shop:hover {
            background-color: var(--brand-bg);
        }
        
        /* Gaya Toast Notification - Dipertahankan untuk Error */
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 1050; }
        
        /* PERBAIKAN RESPONSIVITAS 4: Posisikan Toast di tengah-atas di layar kecil */
        @media (max-width: 576px) {
            .toast-container {
                top: 10px;
                right: 10px;
                left: 10px;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .toast {
                /* Beri lebar yang wajar untuk mobile */
                width: 95%; 
                max-width: 350px; 
                transform: translateX(0) !important; 
                opacity: 0;
                transition: opacity 0.5s ease-out;
            }
            .toast.show { 
                opacity: 1; 
                transform: translateX(0) !important; 
            }
        }

        /* Gaya Toast Default */
        .toast {
            background-color: var(--info-color); color: white; padding: 15px 25px; border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); margin-bottom: 10px; display: flex;
            align-items: center; gap: 10px; opacity: 0; transform: translateX(100%);
            transition: opacity 0.5s ease-out, transform 0.5s ease-out;
            min-width: 250px;
        }
        .toast.error { background-color: var(--danger-color); }
        .toast.success { background-color: var(--success-color); }
        .toast.info { background-color: var(--info-color); }
        .toast.show { opacity: 1; transform: translateX(0); }
        .toast .feather { width: 20px; height: 20px; stroke: white; }
    </style>
</head>
<body>

<div class="toast-container">
    </div>

<div class="card">
    <div class="card-header">
        <img src="<?php echo $assets_path; ?>images/logo.png" alt="Toko SepatuKu Logo" class="brand-logo rounded-circle">
        <h4 class="fw-bold">Login Pelanggan</h4>
        <p class="mb-0 text-muted" style="font-size: 0.9rem;">Masuk untuk melanjutkan belanja Anda.</p>
        
    </div>
    
    <div class="card-body">
        <form action="login_user.php" method="POST" id="loginForm" novalidate>
            <div class="mb-3">
                <input type="email" class="form-control" id="email" name="email" required placeholder="Alamat Email" value="<?php echo htmlspecialchars($email); ?>">
            </div>
            
            <div class="password-container">
                <input type="password" class="form-control" id="password" name="password" required placeholder="Password">
                <i class="fas fa-eye toggle-password" id="togglePassword"></i>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 fw-bold">
                <i class="fas fa-sign-in-alt me-1"></i> Login
            </button>
        </form>
        
        <p class="mt-4 text-center" style="font-size: 0.9rem;">
            Belum punya akun? <a href="register.php" class="fw-bold text-decoration-none text-primary">Daftar sekarang</a>
        </p>

        <a href="toko_sepatu.php" class="back-to-shop">
            <i data-feather="arrow-left" class="me-2"></i> Kembali ke Toko
        </a>
        
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    feather.replace();

    // =======================================================
    // FUNGSI UTAMA TOAST (Dipertahankan untuk Error/Info non-penting)
    // =======================================================
    function showToast(message, type = 'info') {
        const container = document.querySelector('.toast-container');
        const toast = document.createElement('div');
        
        let iconHtml;
        if (type === 'success') {
            iconHtml = '<i data-feather="check-circle"></i>';
        } else if (type === 'error' || type === 'danger') {
            iconHtml = '<i data-feather="x-circle"></i>';
        } else { // info
            iconHtml = '<i data-feather="info"></i>';
        }

        toast.classList.add('toast', type);
        toast.innerHTML = `${iconHtml} <span>${message}</span>`;
        container.appendChild(toast);
        feather.replace(); 

        setTimeout(() => { toast.classList.add('show'); }, 100);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => { toast.remove(); }, 500); 
        }, 4000);
    }

    // =======================================================
    // FUNGSI SWEET ALERT (BARU)
    // =======================================================
    /**
     * Menampilkan Sweet Alert.
     * @param {string} icon - 'success', 'error', 'warning', 'info', 'question'.
     * @param {string} title - Judul Sweet Alert.
     * @param {string} text - Teks isi Sweet Alert.
     * @param {number} timer - Durasi sebelum alert menutup otomatis (ms). Default 3000ms.
     */
    function showSweetAlert(icon, title, text, timer = 3000) {
        Swal.fire({
            icon: icon,
            title: title,
            text: text,
            showConfirmButton: false,
            timer: timer,
            // Custom Styling agar mirip gambar Anda (opsional)
            customClass: {
                container: 'swal2-container-custom',
                popup: 'swal2-popup-custom',
                title: 'swal2-title-custom',
            },
            // Style ikon:
            // iconColor: (icon === 'success' ? '#28a745' : undefined), 
        });
    }

    // =======================================================
    // LOGIKA JAVASCRIPT
    // =======================================================
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        const passwordField = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');
        
        // 1. Logic Toggle Password
        if (togglePassword && passwordField) {
            togglePassword.addEventListener('click', function (e) {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                
                // Toggle ikon Font Awesome
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash'); 
            });
        }
        
        // 2. Logic Validasi Kekosongan (Client-Side)
        form.addEventListener('submit', function(event) {
            const email = form.elements['email'].value.trim();
            const password = form.elements['password'].value.trim();

            if (email === '' || password === '') {
                event.preventDefault(); // Hentikan form submit
                showToast('Email dan password wajib diisi!', 'error');

                // Fokus ke field yang kosong (opsional)
                const fieldToFocus = email === '' ? form.elements['email'] : form.elements['password'];
                fieldToFocus.focus();
            }
            // Jika tidak kosong, biarkan form disubmit ke PHP
        });

        // 3. Tampilkan Notifikasi (Sweet Alert / Toast)
        
        // Sweet Alert Logout dari Query Parameter
        <?php if ($show_logout_swal): ?>
            showSweetAlert('info', 'Sampai Jumpa!', 'Anda telah berhasil keluar dari akun Anda.', 3000);
        <?php endif; ?>
        
        // Sweet Alert dari Sesi PHP (Misalnya: Register Sukses dari register.php)
        const sessionSwalTitle = "<?= htmlspecialchars($session_swal_title) ?>";
        const sessionSwalText = "<?= htmlspecialchars($session_swal_text) ?>";
        const sessionSwalIcon = "<?= htmlspecialchars($session_swal_icon) ?>"; // Akan berisi 'success'

        if (sessionSwalTitle && sessionSwalIcon) {
             // Contoh untuk notifikasi register sukses, mirip gambar Anda
             if (sessionSwalIcon === 'success') {
                 showSweetAlert(
                     'success', 
                     'Selamat!', 
                     sessionSwalText || 'Anda telah berhasil mendaftar.',
                     4000
                 );
             } else {
                 // Jika ada icon lain yang diset, tampilkan
                 showSweetAlert(sessionSwalIcon, sessionSwalTitle, sessionSwalText, 4000);
             }
        }

        // Notifikasi Error Gagal Login dari POST (Menggunakan Toast)
        <?php if ($show_error_toast): ?>
            const errorMessage = "<?= htmlspecialchars($error_message) ?>";
            if (errorMessage) {
                showToast(errorMessage, 'error');
            }
        <?php endif; ?>
    });
</script>
</body>
</html>
<?php 
if (isset($koneksi) && $koneksi instanceof mysqli) {
    $koneksi->close(); 
}
?>