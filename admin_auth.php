<?php
/**
 * admin_auth.php
 * Middleware untuk mengecek apakah sesi admin aktif.
 */

// Mulai sesi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah variabel sesi admin_logged_in TIDAK ada atau bernilai false
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    
    // Gunakan path relatif. Karena dipanggil dari admin/dashboard.php, 
    // ia akan diarahkan ke admin/login.php
    $relative_path_to_login = 'login.php'; 

    // Opsional: Pesan error sesi
    $_SESSION['auth_error'] = "Sesi Anda telah berakhir atau Anda belum login.";

    header('Location: ' . $relative_path_to_login); 
    exit; // PENTING: Selalu hentikan eksekusi setelah redirect
}

// Jika sesi ada, admin dianggap terautentikasi dan kode akan terus berjalan.
?>