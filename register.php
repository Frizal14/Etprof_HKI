<?php
// register.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Definisikan path assets
$assets_path = 'assets/';

// Pastikan koneksi.php mendefinisikan $koneksi
require_once 'koneksi.php'; 
global $koneksi;

// --- TAMBAHAN: AMBIL LOGO DARI DATABASE ---
$site_logo_file = '';
$logo_uploads_path = 'uploads/brand/'; // Path folder upload logo

// Ambil data logo dari website_settings
if (isset($koneksi) && $koneksi instanceof mysqli) {
    $sql_settings = "SELECT logo_image_path FROM website_settings WHERE id = 1";
    $result_settings = $koneksi->query($sql_settings);
    if ($result_settings && $result_settings->num_rows > 0) {
        $row_settings = $result_settings->fetch_assoc();
        $site_logo_file = htmlspecialchars($row_settings['logo_image_path'] ?? '');
    }
}
// ------------------------------------------

$error = []; 
$name = $email = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validasi input wajib (PHP Fallback)
    if (empty($name)) { $error[] = "Nama lengkap wajib diisi."; }
    if (empty($email)) { $error[] = "Alamat email wajib diisi."; }
    if (empty($password)) { $error[] = "Password wajib diisi."; }
    if (empty($confirm_password)) { $error[] = "Konfirmasi password wajib diisi."; }

    // Validasi Detail Lanjutan
    if (empty($error)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $error[] = "Format email tidak valid."; }
        if (strlen($password) < 6) { $error[] = "Password minimal 6 karakter."; }
        if ($password !== $confirm_password) { $error[] = "Konfirmasi password tidak cocok."; }
    }


    if (empty($error)) {
        // Cek email sudah terdaftar
        $stmt_check = $koneksi->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            // Error Email Duplikat
            $error[] = "Email sudah terdaftar. Silakan login atau gunakan email lain.";
        } else {
            $stmt_check->close(); 

            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Simpan ke database (tabel users)
            $stmt_insert = $koneksi->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("sss", $name, $email, $password_hash); 

            if ($stmt_insert->execute()) {
                // Pendaftaran berhasil
                // Menggunakan Sweet Alert (SWAL)
                $_SESSION['swal_icon'] = "success";
                $_SESSION['swal_title'] = "Akun Berhasil Dibuat!";
                $_SESSION['swal_text'] = "Selamat! Anda telah terdaftar. Silakan login untuk mulai berbelanja.";
                
                // Redirect ke halaman login untuk melihat SWAL
                header('Location: login_user.php'); 
                exit;
            } else {
                $error[] = "Gagal mendaftar: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
    }
}

$error_toast_message = '';
if (!empty($error)) {
    // Gabungkan semua error menjadi satu pesan untuk ditampilkan di Toast
    $error_toast_message = implode('<br>', $error); 
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Baru | Toko SepatuKu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> 
    <style>
        /* CSS Dihilangkan untuk keringkasan */
        :root {
            --primary-color: #007bff; 
            --secondary-color: #6c757d;
            --brand-bg: #f8f9fa; 
            --danger-color: #dc3545;
            --success-color: #28a745;
            --info-color: #0d6efd;
        }
        body {
            background: linear-gradient(135deg, #007bff 0%, #00c6ff 100%); 
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; 
            padding: 20px 0; 
        }
        .card {
            width: 450px;
            max-width: 95%; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            border: none;
            margin: auto; 
        }
        .card-header {
            background-color: var(--brand-bg);
            color: #343a40;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            text-align: center;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }
        .brand-logo { 
            width: 80px; 
            height: 80px; 
            margin-bottom: 5px; 
            object-fit: contain; /* Agar logo tidak gepeng */
        }
        .card-body {
            padding: 20px; 
        }
        @media (min-width: 576px) {
            .card-body {
                padding: 25px; 
            }
        }
        .form-label {
            margin-bottom: 0.3rem; 
            font-size: 0.9rem; 
            font-weight: 600;
        }
        .mb-3 {
            margin-bottom: 0.8rem !important; 
        }
        .form-control {
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 1rem;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 8px;
            padding: 10px 14px;
            transition: background-color 0.3s, transform 0.1s;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            transform: translateY(-1px);
        }
        .password-container {
            position: relative;
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
        .mt-4 {
            margin-top: 1.5rem !important; 
        }
        .toast-container { 
            position: fixed; 
            top: 20px; 
            right: 20px; 
            z-index: 1050; 
        }
        @media (max-width: 576px) {
            .toast-container {
                top: 10px; right: 10px; left: 10px; display: flex; flex-direction: column; align-items: center;
            }
            .toast {
                width: 95%; max-width: 350px; transform: translateX(0) !important; opacity: 0; transition: opacity 0.5s ease-out;
            }
            .toast.show { opacity: 1; transform: translateX(0) !important; }
        }
        
        .toast {
            background-color: var(--info-color); color: white; padding: 15px 25px; border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); margin-bottom: 10px; display: flex;
            align-items: center; gap: 10px; opacity: 0; transform: translateX(100%);
            transition: opacity 0.5s ease-out, transform 0.5s ease-out;
            min-width: 250px;
        }
        .toast.show { opacity: 1; transform: translateX(0); }
        .toast.error { background-color: var(--danger-color); }
        .toast.success { background-color: var(--success-color); }
        .toast .feather { width: 20px; height: 20px; stroke: white; }
    </style>
</head>
<body>

<div class="toast-container">
    </div>

<div class="card">
    <div class="card-header">
        <?php 
        // Cek file logo dari database
        $logo_url = (!empty($site_logo_file) && file_exists($logo_uploads_path . $site_logo_file)) 
            ? $logo_uploads_path . $site_logo_file 
            : $assets_path . 'images/logo.png'; // Fallback
        ?>
        <img src="<?php echo $logo_url; ?>" alt="Toko Logo" class="brand-logo rounded-circle"> 
        <h5 class="fw-bold mb-1">Buat Akun Baru</h5>
        <p class="mb-0 text-muted" style="font-size: 0.9rem;">Daftar untuk mulai berbelanja.</p>
        
    </div>
    <div class="card-body">
        
        <form action="register.php" method="POST" class="needs-validation" id="registerForm" novalidate>
            <div class="mb-3">
                <label for="name" class="form-label">Nama Lengkap</label>
                <input type="text" class="form-control" id="name" name="name" placeholder="Nama Anda" required value="<?php echo htmlspecialchars($name); ?>">
                <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Alamat Email</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="contoh@email.com" required value="<?php echo htmlspecialchars($email); ?>">
                <div class="invalid-feedback">Alamat email wajib diisi dan harus valid.</div>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="password-container">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Minimal 6 karakter" required minlength="6">
                    <i class="fas fa-eye toggle-password" data-target="password"></i>
                    <div class="invalid-feedback">Password minimal 6 karakter wajib diisi.</div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Konfirmasi Password</label>
                <div class="password-container">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Ulangi password" required minlength="6">
                    <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
                    <div class="invalid-feedback" id="confirmPasswordFeedback">Konfirmasi password wajib diisi.</div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 fw-bold mt-2">
                <i class="fas fa-user-plus me-1"></i> Daftar Akun
            </button>
        </form>
        
        <p class="mt-4 text-center text-muted" style="font-size: 0.9rem;">Sudah punya akun? <a href="login_user.php" class="fw-bold text-decoration-none text-primary">Login di sini</a></p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    feather.replace();

    // =======================================================
    // FUNGSI SWEET ALERT (BARU)
    // =======================================================
    function showSweetAlert(icon, title, text, timer = 4000) {
        Swal.fire({
            icon: icon,
            title: title,
            text: text,
            showConfirmButton: false,
            timer: timer,
        });
    }

    // =======================================================
    // FUNGSI UTAMA TOAST (Dipertahankan untuk Error)
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
        // Mengganti penggunaan innerHTML dengan textContent untuk pesan yang berasal dari imploded error array
        // Namun, karena pesan error di sini menggunakan <br> dari PHP, innerHTML dipertahankan, 
        // tapi pastikan pesan error dari PHP sudah di-escape sebelumnya.
        toast.innerHTML = `${iconHtml} <span>${message}</span>`; 
        container.appendChild(toast);
        feather.replace(); 

        setTimeout(() => { 
            toast.classList.add('show'); 
        }, 100);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => { toast.remove(); }, 500); 
        }, 4000);
    }
    
    // =======================================================
    // LOGIKA JAVASCRIPT
    // =======================================================
    (function () {
        'use strict'
        
        var form = document.querySelector('.needs-validation')
        var passwordInput = document.getElementById('password');
        var confirmPasswordInput = document.getElementById('confirm_password');
        var confirmPasswordFeedback = document.getElementById('confirmPasswordFeedback');
        
        // --- Logika Toggle Password (Ikon Mata) ---
        document.querySelectorAll('.toggle-password').forEach(icon => {
            icon.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const targetField = document.getElementById(targetId);
                
                const type = targetField.getAttribute('type') === 'password' ? 'text' : 'password';
                targetField.setAttribute('type', type);
                
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });

        // --- Logika Validasi Kesamaan Password & Min Length di Client-Side ---
        function checkPasswordMatch() {
            const minLength = 6;
            
            if (confirmPasswordInput.value.length > 0 && confirmPasswordInput.value.length < minLength) {
                confirmPasswordInput.setCustomValidity('Password terlalu pendek');
                confirmPasswordFeedback.textContent = `Password minimal ${minLength} karakter.`;
            } else if (passwordInput.value !== confirmPasswordInput.value && confirmPasswordInput.value.length > 0) {
                confirmPasswordInput.setCustomValidity('Password tidak cocok');
                confirmPasswordFeedback.textContent = 'Konfirmasi password tidak cocok.';
            } else {
                confirmPasswordInput.setCustomValidity(''); 
                confirmPasswordFeedback.textContent = 'Konfirmasi password wajib diisi.';
            }
        }

        passwordInput.addEventListener('input', checkPasswordMatch);
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);

        // --- Logika Submit Form ---
        form.addEventListener('submit', function (event) {
            checkPasswordMatch();
            
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }

            form.classList.add('was-validated')
        }, false);
        
        // --- Tampilkan Toast Error Server-Side ---
        const errorToastMessage = <?php echo json_encode($error_toast_message); ?>;
        
        if (errorToastMessage.length > 0) {
            showToast(errorToastMessage, 'error');
        }

    })()

</script>
</body>
</html>
<?php 
// Tutup koneksi di akhir
if (isset($koneksi) && $koneksi instanceof mysqli) {
    $koneksi->close(); 
}
?>